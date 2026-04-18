<?php
define('CODEGA_NO_AUTO_SESSION', true);

require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

mp_auth_require();

// ═══ DEFENSIVE: Tablo varlık kontrolü ═══
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
    error_log('destek tablo: ' . $e->getMessage());
}
$user = mp_auth_user();
$mid = (int)$user['mukellef_id'];

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . SITE_URL . '/musteri-portal/destek.php');
    exit;
}

// Talebi al + yetki kontrol
$q = $pdo->prepare("
    SELECT dt.*, f.fatura_no AS ilgili_fatura_no
    FROM destek_talepleri dt
    LEFT JOIN faturalar f ON f.id = dt.ilgili_fatura_id
    WHERE dt.id=? AND dt.mukellef_id=?
");
$q->execute([$id, $mid]);
$talep = $q->fetch();

if (!$talep) {
    mp_audit($pdo, 'musteri.destek_denied', "talep_id=$id");
    http_response_code(404);
    mp_render_header('Talep Bulunamadı', 'destek');
    echo '<div class="mp-alert mp-alert-danger">' . mp_icon('x-circle') . '<div>Bu talep bulunamadı veya erişim yetkiniz yok.</div></div>';
    echo '<a href="'.SITE_URL.'/musteri-portal/destek.php" class="mp-btn mp-btn-primary">← Taleplere Dön</a>';
    mp_render_footer();
    exit;
}

$err = '';

// Yanıt ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    if (!mp_csrf_verify($_POST['csrf'] ?? '')) {
        $err = 'Güvenlik hatası.';
    } elseif ($talep['durum'] === 'kapali') {
        $err = 'Bu talep kapalı. Yeni bir talep oluşturun.';
    } else {
        $mesaj = trim($_POST['mesaj'] ?? '');
        if (mb_strlen($mesaj) < 3) {
            $err = 'Mesaj en az 3 karakter olmalı';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO destek_mesajlari (talep_id, taraf, kullanici_id, kullanici_adi, mesaj, ip)
                    VALUES (?, 'musteri', ?, ?, ?, ?)
                ")->execute([$id, $user['id'], $user['ad_soyad'] ?: $user['user'], $mesaj, client_ip()]);

                // Talep durumunu güncelle
                $pdo->prepare("
                    UPDATE destek_talepleri
                    SET durum='acik',
                        son_mesaj_tarihi=NOW(),
                        son_mesaj_tarafi='musteri',
                        musteri_okundu=1,
                        admin_okundu=0
                    WHERE id=?
                ")->execute([$id]);

                $pdo->commit();
                mp_audit($pdo, 'musteri.destek_yanit', "talep_id=$id");

                // Admin'e e-posta
                if (defined('MAIL_ADMIN_BCC') && MAIL_ADMIN_BCC) {
                    @mail_destek_bildirim(MAIL_ADMIN_BCC, $talep['talep_no'], $talep['konu'], 'musteri_yanit', $mesaj, $id);
                }

                header('Location: ' . SITE_URL . '/musteri-portal/destek-detay.php?id=' . $id . '#son');
                exit;
            } catch (\Exception $e) {
                $pdo->rollBack();
                $err = 'Mesaj gönderilemedi: ' . $e->getMessage();
            }
        }
    }
}

// Talep kapat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close') {
    if (mp_csrf_verify($_POST['csrf'] ?? '') && $talep['durum'] !== 'kapali') {
        $pdo->prepare("UPDATE destek_talepleri SET durum='kapali', kapali_tarihi=NOW() WHERE id=?")->execute([$id]);
        $pdo->prepare("
            INSERT INTO destek_mesajlari (talep_id, taraf, kullanici_adi, mesaj, sistem_mesaji)
            VALUES (?, 'musteri', ?, ?, 1)
        ")->execute([$id, $user['ad_soyad'] ?: $user['user'], 'Talep müşteri tarafından kapatıldı.']);
        mp_audit($pdo, 'musteri.destek_kapat', "talep_id=$id");
        header('Location: ' . SITE_URL . '/musteri-portal/destek-detay.php?id=' . $id);
        exit;
    }
}

// Müşteri okundu işaretle
if (!$talep['musteri_okundu']) {
    $pdo->prepare("UPDATE destek_talepleri SET musteri_okundu=1 WHERE id=?")->execute([$id]);
}

// Mesajları çek
$m_q = $pdo->prepare("SELECT * FROM destek_mesajlari WHERE talep_id=? ORDER BY id ASC");
$m_q->execute([$id]);
$mesajlar = $m_q->fetchAll();

mp_audit($pdo, 'musteri.view_destek_detay', "talep_id=$id");

// Kategori / durum etiketleri
$kategori_map = [
    'fatura_sorunu' => ['🧾', 'Fatura Sorunu'],
    'teknik_destek' => ['⚙️', 'Teknik Destek'],
    'bilgi_talebi'  => ['💬', 'Bilgi Talebi'],
    'iptal_iade'    => ['↩️', 'İptal / İade'],
    'diger'         => ['📋', 'Diğer'],
];
$kat = $kategori_map[$talep['kategori']] ?? $kategori_map['diger'];

$durum_map = [
    'acik'       => ['Açık', '#fef3c7', '#78350f'],
    'cevaplandi' => ['Cevaplandı', '#d1fae5', '#065f46'],
    'beklemede'  => ['Sizi Bekliyor', '#dbeafe', '#1e40af'],
    'kapali'     => ['Kapalı', '#f1f5f9', '#475569'],
];
$dr = $durum_map[$talep['durum']] ?? $durum_map['acik'];

mp_render_header($talep['talep_no'] . ' - ' . $talep['konu'], 'destek');
?>

<div class="mp-page-head">
    <a href="<?= SITE_URL ?>/musteri-portal/destek.php" class="mp-btn mp-btn-ghost">← Taleplere Dön</a>
</div>

<?php if ($err): ?>
    <div class="mp-alert mp-alert-danger"><?= mp_icon('x-circle') ?><div><?= h($err) ?></div></div>
<?php endif; ?>

<!-- TALEP BAŞLIĞI -->
<div class="mp-card">
    <div style="padding:22px 26px;border-bottom:1px solid #e2e8f0;background:linear-gradient(135deg,#f8fafc 0%,#fff 100%)">
        <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
            <div style="width:56px;height:56px;background:#0a2540;color:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0">
                <?= $kat[0] ?>
            </div>
            <div style="flex:1;min-width:240px">
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;flex-wrap:wrap">
                    <span style="font-family:monospace;font-size:12px;color:#94a3b8;font-weight:600"><?= h($talep['talep_no']) ?></span>
                    <span style="background:<?= $dr[1] ?>;color:<?= $dr[2] ?>;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:700"><?= $dr[0] ?></span>
                    <span style="background:#f1f5f9;color:#475569;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600"><?= $kat[1] ?></span>
                </div>
                <h2 style="margin:0;font-size:19px;font-weight:700;color:#0f172a;letter-spacing:-0.3px"><?= h($talep['konu']) ?></h2>
                <div style="font-size:12.5px;color:#94a3b8;margin-top:6px">
                    Oluşturulma: <?= fmt_datetime($talep['created_at']) ?>
                    <?php if ($talep['ilgili_fatura_no']): ?>
                        · İlgili fatura:
                        <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $talep['ilgili_fatura_id'] ?>" style="font-family:monospace;color:#0b5cff">↗ <?= h($talep['ilgili_fatura_no']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($talep['durum'] !== 'kapali'): ?>
                <form method="POST" onsubmit="return confirm('Bu talebi kapatmak istediğinizden emin misiniz? Kapatılan taleplere yeni mesaj eklenemez.')">
                    <?= mp_csrf_field() ?>
                    <input type="hidden" name="action" value="close">
                    <button type="submit" class="mp-btn mp-btn-ghost mp-btn-sm">
                        <?= mp_icon('x-circle', 13) ?> Talebi Kapat
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- MESAJ LİSTESİ -->
    <div style="padding:22px 26px;background:#f8fafc">
        <?php foreach ($mesajlar as $m):
            $is_admin = $m['taraf'] === 'admin';
            $is_sistem = !empty($m['sistem_mesaji']);
        ?>
            <?php if ($is_sistem): ?>
                <div style="text-align:center;margin:14px 0">
                    <span style="background:#e2e8f0;color:#64748b;padding:4px 12px;border-radius:12px;font-size:11.5px;font-weight:500">
                        <?= h($m['mesaj']) ?> · <?= fmt_datetime($m['created_at']) ?>
                    </span>
                </div>
            <?php else: ?>
                <div style="display:flex;gap:12px;margin-bottom:18px;<?= $is_admin ? 'flex-direction:row-reverse' : '' ?>">
                    <div style="width:40px;height:40px;<?= $is_admin ? 'background:linear-gradient(135deg,#0b5cff,#0a2540);color:#fff' : 'background:#e2e8f0;color:#475569' ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;font-size:14px">
                        <?= $is_admin ? 'C' : h(strtoupper(mb_substr($m['kullanici_adi'] ?: 'M', 0, 1))) ?>
                    </div>
                    <div style="max-width:75%">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;<?= $is_admin ? 'justify-content:flex-end' : '' ?>">
                            <strong style="font-size:13px;color:#0f172a"><?= $is_admin ? 'CODEGA Destek' : h($m['kullanici_adi']) ?></strong>
                            <?php if ($is_admin): ?>
                                <span style="background:#eff6ff;color:#1e40af;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700">DESTEK</span>
                            <?php endif; ?>
                            <span style="font-size:11px;color:#94a3b8"><?= fmt_datetime($m['created_at']) ?></span>
                        </div>
                        <div style="background:<?= $is_admin ? 'linear-gradient(135deg,#0b5cff,#0a2540)' : '#fff' ?>;<?= $is_admin ? 'color:#fff' : 'color:#0f172a;border:1px solid #e2e8f0' ?>;padding:12px 16px;border-radius:<?= $is_admin ? '14px 14px 2px 14px' : '14px 14px 14px 2px' ?>;font-size:13.5px;line-height:1.55;white-space:pre-wrap;word-break:break-word"><?= h($m['mesaj']) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <div id="son"></div>
    </div>

    <!-- YANIT FORMU -->
    <?php if ($talep['durum'] === 'kapali'): ?>
        <div style="padding:22px 26px;border-top:1px solid #e2e8f0;text-align:center;background:#f8fafc">
            <div style="color:#64748b;font-size:13.5px;margin-bottom:12px">
                <?= mp_icon('lock', 16) ?> Bu talep kapalı. Yeni bir sorununuz için yeni talep oluşturabilirsiniz.
            </div>
            <a href="<?= SITE_URL ?>/musteri-portal/destek.php#yeni-talep-form" class="mp-btn mp-btn-primary">
                <?= mp_icon('plus', 14) ?> Yeni Talep Oluştur
            </a>
        </div>
    <?php else: ?>
        <div style="padding:22px 26px;border-top:1px solid #e2e8f0;background:#fff">
            <form method="POST">
                <?= mp_csrf_field() ?>
                <input type="hidden" name="action" value="reply">

                <div class="mp-fg">
                    <label>Mesajınız *</label>
                    <textarea name="mesaj" required minlength="3" rows="4" style="font-family:inherit;resize:vertical" placeholder="Yanıtınızı yazın..."></textarea>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div style="font-size:12px;color:#94a3b8">
                        💡 İpucu: Ekran görüntüsü gönderecekseniz mesajda belirtin
                    </div>
                    <button type="submit" class="mp-btn mp-btn-primary">
                        <?= mp_icon('paper-plane', 14) ?> Mesajı Gönder
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
// En alt mesaja scroll
if (window.location.hash === '#son') {
    setTimeout(() => window.scrollTo(0, document.body.scrollHeight), 100);
}
</script>

<?php mp_render_footer(); ?>
