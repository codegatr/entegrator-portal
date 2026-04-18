<?php
define('CODEGA_NO_AUTO_SESSION', true);

// ═══ FAIL-SAFE: Her türlü hatayı yakala ve sayfa olarak göster ═══
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

try {

require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

mp_auth_require();
$user = mp_auth_user();
$mid = (int)$user['mukellef_id'];

// ═══ DEFENSIVE: Tablo varlık kontrolü ═══
// Eğer migration çalışmadıysa tabloları oluştur
try {
    $tbl_check = $pdo->query("SHOW TABLES LIKE 'destek_talepleri'")->fetchColumn();
    if (!$tbl_check) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS destek_talepleri (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            talep_no VARCHAR(20) UNIQUE NOT NULL,
            mukellef_id INT UNSIGNED NOT NULL,
            musteri_kullanici_id INT UNSIGNED DEFAULT NULL,
            konu VARCHAR(200) NOT NULL,
            kategori ENUM('fatura_sorunu','teknik_destek','bilgi_talebi','iptal_iade','diger') DEFAULT 'diger',
            oncelik ENUM('dusuk','normal','yuksek','acil') DEFAULT 'normal',
            durum ENUM('acik','cevaplandi','beklemede','kapali') DEFAULT 'acik',
            ilgili_fatura_id INT UNSIGNED DEFAULT NULL,
            atanan_admin_id INT UNSIGNED DEFAULT NULL,
            son_mesaj_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
            son_mesaj_tarafi ENUM('musteri','admin') DEFAULT 'musteri',
            musteri_okundu TINYINT(1) DEFAULT 1,
            admin_okundu TINYINT(1) DEFAULT 0,
            kapali_tarihi DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_mukellef (mukellef_id),
            KEY idx_durum (durum),
            KEY idx_son_mesaj (son_mesaj_tarihi)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS destek_mesajlari (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            talep_id INT UNSIGNED NOT NULL,
            taraf ENUM('musteri','admin') NOT NULL,
            kullanici_id INT UNSIGNED DEFAULT NULL,
            kullanici_adi VARCHAR(100) DEFAULT NULL,
            mesaj TEXT NOT NULL,
            sistem_mesaji TINYINT(1) DEFAULT 0,
            ip VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_talep (talep_id),
            KEY idx_tarih (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch (\Exception $e) {
    error_log('destek tablo oluştur: ' . $e->getMessage());
}

$err = '';

// Yeni talep oluşturma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!mp_csrf_verify($_POST['csrf'] ?? '')) {
        $err = 'Güvenlik hatası. Sayfayı yenileyin.';
    } else {
        $konu = trim($_POST['konu'] ?? '');
        $kategori = in_array($_POST['kategori'] ?? '', ['fatura_sorunu','teknik_destek','bilgi_talebi','iptal_iade','diger'], true) ? $_POST['kategori'] : 'diger';
        $oncelik = in_array($_POST['oncelik'] ?? '', ['dusuk','normal','yuksek','acil'], true) ? $_POST['oncelik'] : 'normal';
        $mesaj = trim($_POST['mesaj'] ?? '');
        $fatura_id = (int)($_POST['fatura_id'] ?? 0);

        if (mb_strlen($konu) < 5) {
            $err = 'Konu en az 5 karakter olmalı';
        } elseif (mb_strlen($mesaj) < 10) {
            $err = 'Mesaj en az 10 karakter olmalı';
        } else {
            // Fatura doğrulama — müşterinin kendi faturası mı?
            if ($fatura_id > 0 && !mp_fatura_ait_mi($pdo, $fatura_id, $mid)) {
                $fatura_id = 0;
            }

            // Talep numarası üret
            $talep_no = 'TLP' . date('Y') . str_pad((string)(((int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM destek_talepleri")->fetchColumn()) + 1), 6, '0', STR_PAD_LEFT);

            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO destek_talepleri
                    (talep_no, mukellef_id, musteri_kullanici_id, konu, kategori, oncelik, durum,
                     ilgili_fatura_id, son_mesaj_tarihi, son_mesaj_tarafi, musteri_okundu, admin_okundu)
                    VALUES (?, ?, ?, ?, ?, ?, 'acik', ?, NOW(), 'musteri', 1, 0)
                ")->execute([$talep_no, $mid, $user['id'], $konu, $kategori, $oncelik, $fatura_id ?: null]);
                $tid = (int)$pdo->lastInsertId();

                // İlk mesaj
                $pdo->prepare("
                    INSERT INTO destek_mesajlari (talep_id, taraf, kullanici_id, kullanici_adi, mesaj, ip)
                    VALUES (?, 'musteri', ?, ?, ?, ?)
                ")->execute([$tid, $user['id'], $user['ad_soyad'] ?: $user['user'], $mesaj, client_ip()]);

                $pdo->commit();
                mp_audit($pdo, 'musteri.destek_talep_olustur', "talep_no=$talep_no konu=$konu");

                // Admin'e e-posta bildirimi
                if (defined('MAIL_ADMIN_BCC') && MAIL_ADMIN_BCC) {
                    @mail_destek_bildirim(
                        MAIL_ADMIN_BCC,
                        $talep_no,
                        $konu . ' · ' . ($user['unvan'] ?? 'Müşteri'),
                        'yeni_talep',
                        $mesaj,
                        $tid
                    );
                }

                mp_flash_set('success', "Talebiniz alındı. Talep No: <strong>$talep_no</strong>. En kısa sürede yanıtlayacağız.");
                header('Location: ' . SITE_URL . '/musteri-portal/destek-detay.php?id=' . $tid);
                exit;
            } catch (\Exception $e) {
                $pdo->rollBack();
                $err = 'Talep oluşturulamadı: ' . $e->getMessage();
            }
        }
    }
}

// Filtre
$durum_filtre = $_GET['durum'] ?? '';
$where = 'mukellef_id=?';
$par = [$mid];
if (in_array($durum_filtre, ['acik','cevaplandi','beklemede','kapali'], true)) {
    $where .= ' AND durum=?';
    $par[] = $durum_filtre;
}

// Talepler
$st = $pdo->prepare("
    SELECT dt.*,
           (SELECT COUNT(*) FROM destek_mesajlari WHERE talep_id = dt.id) AS mesaj_sayisi,
           f.fatura_no AS ilgili_fatura_no
    FROM destek_talepleri dt
    LEFT JOIN faturalar f ON f.id = dt.ilgili_fatura_id
    WHERE $where
    ORDER BY dt.son_mesaj_tarihi DESC
    LIMIT 50
");
$st->execute($par);
$talepler = $st->fetchAll();

// Sayımlar
$sayim_q = $pdo->prepare("SELECT durum, COUNT(*) AS c FROM destek_talepleri WHERE mukellef_id=? GROUP BY durum");
$sayim_q->execute([$mid]);
$sayimlar = [];
foreach ($sayim_q->fetchAll() as $r) $sayimlar[$r['durum']] = (int)$r['c'];
$toplam = array_sum($sayimlar);

// Okunmamış (müşteriye admin yanıtı gelmiş)
$okun_q = $pdo->prepare("SELECT COUNT(*) FROM destek_talepleri WHERE mukellef_id=? AND musteri_okundu=0");
$okun_q->execute([$mid]);
$okunmamis = (int)$okun_q->fetchColumn();

// İlgili fatura için dropdown — son 20 fatura
$fat_q = $pdo->prepare("SELECT id, fatura_no, duzenleme_tarihi FROM faturalar WHERE mukellef_id=? ORDER BY id DESC LIMIT 20");
$fat_q->execute([$mid]);
$fat_listesi = $fat_q->fetchAll();

mp_audit($pdo, 'musteri.view_destek');

mp_render_header('Destek Talepleri', 'destek');
?>

<div class="mp-page-head">
    <div>
        <h1>Destek Talepleri</h1>
        <div class="sub">
            <?= $toplam ?> talep
            <?php if ($okunmamis > 0): ?>
                · <strong style="color:#dc2626"><?= $okunmamis ?> yeni yanıt</strong>
            <?php endif; ?>
        </div>
    </div>
    <div class="mp-page-head-actions">
        <button onclick="document.getElementById('yeni-talep-form').scrollIntoView({behavior:'smooth'})" class="mp-btn mp-btn-primary">
            <?= mp_icon('plus', 14) ?> Yeni Talep Oluştur
        </button>
    </div>
</div>

<?php if ($err): ?>
    <div class="mp-alert mp-alert-danger"><?= mp_icon('x-circle') ?><div><?= h($err) ?></div></div>
<?php endif; ?>

<?php foreach (mp_flash_get() as $f):
    $cls = match($f['tip']) { 'success'=>'mp-alert-success', 'danger'=>'mp-alert-danger', default=>'mp-alert-info' };
?>
    <div class="mp-alert <?= $cls ?>"><?= mp_icon('check-circle') ?><div><?= $f['msg'] /* HTML içeriyor - flash'dan direkt */ ?></div></div>
<?php endforeach; ?>

<!-- FİLTRE SEKMELERI -->
<div class="mp-card" style="padding:0">
    <div style="padding:14px 22px;display:flex;gap:8px;flex-wrap:wrap;border-bottom:1px solid #e2e8f0">
        <?php
        $filtreler = [
            ''           => ['Tümü', $toplam, '#475569'],
            'acik'       => ['🟠 Açık', $sayimlar['acik'] ?? 0, '#d97706'],
            'cevaplandi' => ['✓ Cevaplandı', $sayimlar['cevaplandi'] ?? 0, '#059669'],
            'beklemede'  => ['⏳ Beklemede', $sayimlar['beklemede'] ?? 0, '#0284c7'],
            'kapali'     => ['⚫ Kapalı', $sayimlar['kapali'] ?? 0, '#64748b'],
        ];
        foreach ($filtreler as $k => [$lbl, $cnt, $clr]):
            $is_active = $durum_filtre === $k;
            $url = $k ? '?durum=' . $k : '?';
        ?>
            <a href="<?= h($url) ?>" style="padding:7px 14px;border-radius:20px;font-size:12.5px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;<?= $is_active ? 'background:'.$clr.';color:#fff' : 'background:#f8fafc;color:#475569;border:1px solid #e2e8f0' ?>">
                <span><?= $lbl ?></span>
                <span style="background:<?= $is_active ? 'rgba(255,255,255,0.2)' : '#e2e8f0' ?>;padding:1px 7px;border-radius:10px;font-size:11px"><?= $cnt ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- TALEPLER LİSTESİ -->
    <?php if (empty($talepler)): ?>
        <div class="mp-table-empty" style="padding:60px 20px">
            <?= mp_icon('message', 48) ?>
            <h4><?= $durum_filtre ? 'Bu kategoride talep yok' : 'Henüz talep yok' ?></h4>
            <p>Aşağıdaki formdan yeni bir destek talebi oluşturabilirsiniz</p>
        </div>
    <?php else: ?>
        <div>
            <?php foreach ($talepler as $t):
                $kategori_map = [
                    'fatura_sorunu' => ['🧾', 'Fatura Sorunu', '#dc2626'],
                    'teknik_destek' => ['⚙️', 'Teknik Destek', '#0b5cff'],
                    'bilgi_talebi'  => ['💬', 'Bilgi Talebi', '#7c3aed'],
                    'iptal_iade'    => ['↩️', 'İptal/İade', '#d97706'],
                    'diger'         => ['📋', 'Diğer', '#64748b'],
                ];
                $kat = $kategori_map[$t['kategori']] ?? $kategori_map['diger'];

                $durum_map = [
                    'acik'       => ['Açık', '#fef3c7', '#78350f'],
                    'cevaplandi' => ['Cevaplandı', '#d1fae5', '#065f46'],
                    'beklemede'  => ['Sizi Bekliyor', '#dbeafe', '#1e40af'],
                    'kapali'     => ['Kapalı', '#f1f5f9', '#475569'],
                ];
                $dr = $durum_map[$t['durum']] ?? $durum_map['acik'];

                $oncelik_map = [
                    'acil'   => ['🔴', 'Acil', '#dc2626'],
                    'yuksek' => ['🟠', 'Yüksek', '#d97706'],
                    'normal' => ['', '', ''],
                    'dusuk'  => ['⬇', 'Düşük', '#64748b'],
                ];
                $onc = $oncelik_map[$t['oncelik']] ?? $oncelik_map['normal'];

                $yeni_yanit = ($t['durum'] === 'cevaplandi' && !$t['musteri_okundu']);
            ?>
                <a href="<?= SITE_URL ?>/musteri-portal/destek-detay.php?id=<?= $t['id'] ?>"
                   style="display:block;padding:18px 22px;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit;<?= $yeni_yanit ? 'background:#eff6ff;border-left:3px solid #0b5cff' : '' ?>">
                    <div style="display:flex;gap:14px;align-items:flex-start">
                        <div style="width:42px;height:42px;background:<?= $kat[2] ?>15;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">
                            <?= $kat[0] ?>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
                                <span style="font-family:monospace;font-size:11.5px;color:#94a3b8;font-weight:600"><?= h($t['talep_no']) ?></span>
                                <span style="background:<?= $dr[1] ?>;color:<?= $dr[2] ?>;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600"><?= $dr[0] ?></span>
                                <?php if ($onc[0]): ?>
                                    <span style="color:<?= $onc[2] ?>;font-size:11px;font-weight:600"><?= $onc[0] ?> <?= $onc[1] ?></span>
                                <?php endif; ?>
                                <?php if ($yeni_yanit): ?>
                                    <span style="background:#dc2626;color:#fff;padding:2px 8px;border-radius:10px;font-size:10.5px;font-weight:700;animation:mp-pulse 2s infinite">YENİ YANIT</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:14.5px;font-weight:600;color:#0f172a;margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($t['konu']) ?></div>
                            <div style="font-size:12px;color:#64748b;display:flex;gap:14px;flex-wrap:wrap">
                                <span><?= mp_icon('message', 11) ?> <?= (int)$t['mesaj_sayisi'] ?> mesaj</span>
                                <span>Son güncelleme: <?= fmt_datetime($t['son_mesaj_tarihi']) ?></span>
                                <?php if ($t['ilgili_fatura_no']): ?>
                                    <span style="font-family:monospace;color:#0b5cff">↗ <?= h($t['ilgili_fatura_no']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="color:#cbd5e1;flex-shrink:0;font-size:24px">→</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- YENİ TALEP FORMU -->
<div id="yeni-talep-form" class="mp-card">
    <div class="mp-card-head">
        <?= mp_icon('plus') ?>
        <h3>Yeni Destek Talebi Oluştur</h3>
    </div>
    <div class="mp-card-body">
        <form method="POST">
            <?= mp_csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="mp-fg">
                <label>Konu *</label>
                <input type="text" name="konu" required minlength="5" maxlength="200" placeholder="Sorunuzu kısaca özetleyin (örn: TMP2026... faturası hakkında)">
            </div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                <div class="mp-fg">
                    <label>Kategori *</label>
                    <select name="kategori" required>
                        <option value="fatura_sorunu">🧾 Fatura Sorunu</option>
                        <option value="teknik_destek">⚙️ Teknik Destek</option>
                        <option value="bilgi_talebi">💬 Bilgi Talebi</option>
                        <option value="iptal_iade">↩️ İptal / İade</option>
                        <option value="diger" selected>📋 Diğer</option>
                    </select>
                </div>
                <div class="mp-fg">
                    <label>Öncelik</label>
                    <select name="oncelik">
                        <option value="dusuk">⬇ Düşük</option>
                        <option value="normal" selected>Normal</option>
                        <option value="yuksek">🟠 Yüksek</option>
                        <option value="acil">🔴 Acil</option>
                    </select>
                </div>
                <div class="mp-fg">
                    <label>İlgili Fatura (opsiyonel)</label>
                    <select name="fatura_id">
                        <option value="0">— Yok —</option>
                        <?php foreach ($fat_listesi as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= h($f['fatura_no']) ?> (<?= date('d.m.Y', strtotime($f['duzenleme_tarihi'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mp-fg">
                <label>Mesaj *</label>
                <textarea name="mesaj" required minlength="10" rows="6" style="font-family:inherit;resize:vertical"
                          placeholder="Sorununuzu ayrıntılı şekilde anlatın. Hata aldıysanız ne yaptığınızı, ne beklediğinizi ve ne olduğunu yazın."></textarea>
                <div class="hint">Ne kadar detay verirseniz o kadar hızlı çözülür. Ekran görüntüsü gönderecekseniz mesajda belirtin — e-postayla göndermeniz istenebilir.</div>
            </div>

            <div style="display:flex;gap:10px;align-items:center">
                <button type="submit" class="mp-btn mp-btn-primary">
                    <?= mp_icon('paper-plane', 14) ?> Talebi Gönder
                </button>
                <div style="font-size:12px;color:#94a3b8">
                    Talebiniz CODEGA destek ekibine iletilir. Yanıt geldiğinde e-posta bildirimi alırsınız.
                </div>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes mp-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
</style>

<?php mp_render_footer(); ?>

<?php
} catch (\Throwable $e) {
    // ═══ 500 hatasını anlamlı sayfaya çevir ═══
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code(500);

    $debug_mode = (isset($_GET['debug']) && $_GET['debug'] === '1')
        || (isset($user) && is_array($user) && ($user['id'] ?? 0) > 0);

    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <title>Destek - Hata Oluştu</title>
        <style>
            body { font-family: system-ui, -apple-system, Segoe UI, sans-serif; background: #f8fafc; padding: 40px 20px; margin: 0; color: #0f172a }
            .box { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 32px; box-shadow: 0 4px 12px rgba(0,0,0,.04) }
            h1 { color: #dc2626; font-size: 22px; margin: 0 0 10px }
            .msg { background: #fef3c7; border-left: 4px solid #d97706; padding: 14px 18px; border-radius: 8px; margin: 16px 0; font-size: 14px; line-height: 1.6 }
            .trace { background: #0f172a; color: #a7f3d0; font-family: monospace; font-size: 12px; padding: 18px; border-radius: 10px; overflow: auto; max-height: 320px; white-space: pre-wrap; word-break: break-all }
            .hint { background: #eff6ff; padding: 14px 18px; border-radius: 8px; color: #1e40af; font-size: 13.5px; line-height: 1.6; margin-top: 14px }
            a { color: #0b5cff; text-decoration: none; font-weight: 600 }
            .btn { display: inline-block; background: #0b5cff; color: #fff; padding: 9px 18px; border-radius: 8px; font-weight: 600; font-size: 13.5px; margin-top: 16px; text-decoration:none }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>⚠️ Destek sayfasında hata oluştu</h1>
            <div class="msg">
                Teknik bir sorun tespit edildi. Bu hata mesajını CODEGA destek ekibiyle paylaşırsanız hızla çözülür.
            </div>

            <?php if ($debug_mode): ?>
                <div style="margin:20px 0">
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;margin-bottom:6px">HATA DETAYI</div>
                    <div style="background:#fee2e2;color:#7f1d1d;padding:14px 18px;border-radius:8px;font-size:13.5px;font-family:monospace;word-break:break-all">
                        <?= htmlspecialchars($e->getMessage()) ?>
                    </div>
                </div>

                <div style="margin:16px 0">
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;margin-bottom:6px">DOSYA</div>
                    <div style="font-family:monospace;font-size:12px;color:#475569">
                        <?= htmlspecialchars($e->getFile()) ?>:<strong style="color:#dc2626"><?= $e->getLine() ?></strong>
                    </div>
                </div>

                <details style="margin:16px 0">
                    <summary style="cursor:pointer;font-weight:600;color:#475569;font-size:13px">▸ Stack Trace (geliştirici için)</summary>
                    <div class="trace"><?= htmlspecialchars($e->getTraceAsString()) ?></div>
                </details>
            <?php else: ?>
                <div class="hint">
                    <strong>Geliştirici modunda detay için:</strong> URL'nin sonuna <code>?debug=1</code> ekleyip yeniden yükleyin.
                </div>
            <?php endif; ?>

            <div class="hint">
                <strong>📞 CODEGA Destek:</strong>
                <a href="tel:+905320652400">0532 065 24 00</a> ·
                <a href="https://wa.me/905320652400" target="_blank">WhatsApp</a> ·
                <a href="mailto:info@codega.com.tr">info@codega.com.tr</a>
            </div>

            <a href="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '/musteri-portal/index.php') ?>" class="btn">← Ana Sayfaya Dön</a>
        </div>

        <?php
        // Hatayı her zaman error_log'a yaz
        error_log(sprintf(
            '[destek.php 500] %s at %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        ?>
    </body>
    </html>
    <?php
}
restore_error_handler();
?>
