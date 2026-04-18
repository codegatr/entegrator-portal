<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('operator');
$admin = auth_user();

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

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . SITE_URL . '/yonetim/destek.php'); exit; }

$q = $pdo->prepare("
    SELECT dt.*,
           m.unvan AS musteri_unvan, m.vkn_tckn AS musteri_vkn, m.telefon AS musteri_tel, m.email AS musteri_email,
           mpk.kullanici_adi AS olusturan_user, mpk.ad_soyad AS olusturan_ad, mpk.email AS olusturan_email,
           f.fatura_no AS ilgili_fatura_no
    FROM destek_talepleri dt
    LEFT JOIN mukellefler m ON m.id = dt.mukellef_id
    LEFT JOIN musteri_portal_kullanicilar mpk ON mpk.id = dt.musteri_kullanici_id
    LEFT JOIN faturalar f ON f.id = dt.ilgili_fatura_id
    WHERE dt.id=?
");
$q->execute([$id]);
$talep = $q->fetch();

if (!$talep) {
    http_response_code(404);
    render_header('Talep Bulunamadı', 'destek-admin');
    echo '<div class="alert alert-danger">' . icon('alert') . '<div>Talep bulunamadı.</div></div>';
    render_footer();
    exit;
}

$err = '';

// Yanıt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $err = 'Güvenlik hatası.';
    } else {
        $mesaj = trim($_POST['mesaj'] ?? '');
        $yeni_durum = $_POST['yeni_durum'] ?? 'cevaplandi';

        if (mb_strlen($mesaj) < 3) {
            $err = 'Mesaj en az 3 karakter olmalı';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO destek_mesajlari (talep_id, taraf, kullanici_id, kullanici_adi, mesaj, ip)
                    VALUES (?, 'admin', ?, ?, ?, ?)
                ")->execute([$id, $admin['id'], $admin['ad_soyad'] ?: $admin['kullanici_adi'], $mesaj, client_ip()]);

                $update_params = ['cevaplandi', $id];
                $update_sql = "UPDATE destek_talepleri SET durum=?, son_mesaj_tarihi=NOW(), son_mesaj_tarafi='admin', admin_okundu=1, musteri_okundu=0";

                if ($yeni_durum === 'kapali') {
                    $update_sql = "UPDATE destek_talepleri SET durum='kapali', kapali_tarihi=NOW(), son_mesaj_tarihi=NOW(), son_mesaj_tarafi='admin', admin_okundu=1, musteri_okundu=0";
                    $update_params = [$id];
                }
                $update_sql .= " WHERE id=?";
                $pdo->prepare($update_sql)->execute($update_params);

                if ($yeni_durum === 'kapali') {
                    $pdo->prepare("
                        INSERT INTO destek_mesajlari (talep_id, taraf, kullanici_adi, mesaj, sistem_mesaji)
                        VALUES (?, 'admin', ?, ?, 1)
                    ")->execute([$id, $admin['ad_soyad'] ?: $admin['kullanici_adi'], 'Talep CODEGA tarafından kapatıldı.']);
                }

                $pdo->commit();
                audit_log($pdo, 'destek.reply', "talep_id=$id durum=$yeni_durum", null, "destek:$id");

                // Müşteri e-postasına bildirim
                if (!empty($talep['olusturan_email']) && filter_var($talep['olusturan_email'], FILTER_VALIDATE_EMAIL)) {
                    @mail_destek_bildirim($talep['olusturan_email'], $talep['talep_no'], $talep['konu'],
                        $yeni_durum === 'kapali' ? 'kapandi' : 'admin_yanit', $mesaj, $id);
                }

                redirect(SITE_URL . '/yonetim/destek-detay.php?id=' . $id);
            } catch (\Exception $e) {
                $pdo->rollBack();
                $err = 'Mesaj kaydedilemedi: ' . $e->getMessage();
            }
        }
    }
}

// Durum/öncelik değiştir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status' && csrf_verify($_POST['csrf'] ?? '')) {
    $yeni_durum = $_POST['durum'] ?? '';
    if (in_array($yeni_durum, ['acik','cevaplandi','beklemede','kapali'], true)) {
        $kapali_tarihi = $yeni_durum === 'kapali' ? 'NOW()' : 'NULL';
        $pdo->prepare("UPDATE destek_talepleri SET durum=?, kapali_tarihi=" . ($yeni_durum === 'kapali' ? 'NOW()' : 'NULL') . " WHERE id=?")->execute([$yeni_durum, $id]);
        audit_log($pdo, 'destek.status', "talep_id=$id yeni=$yeni_durum", null, "destek:$id");
        flash_set('success', 'Durum güncellendi.');
    }
    redirect(SITE_URL . '/yonetim/destek-detay.php?id=' . $id);
}

// Admin okundu işaretle
if (!$talep['admin_okundu']) {
    $pdo->prepare("UPDATE destek_talepleri SET admin_okundu=1 WHERE id=?")->execute([$id]);
}

// Mesajlar
$m_q = $pdo->prepare("SELECT * FROM destek_mesajlari WHERE talep_id=? ORDER BY id ASC");
$m_q->execute([$id]);
$mesajlar = $m_q->fetchAll();

// Müşterinin diğer talepleri
$diger_q = $pdo->prepare("SELECT id, talep_no, konu, durum FROM destek_talepleri WHERE mukellef_id=? AND id!=? ORDER BY id DESC LIMIT 5");
$diger_q->execute([$talep['mukellef_id'], $id]);
$diger_talepler = $diger_q->fetchAll();

// Müşterinin son 5 faturası
$fat_q = $pdo->prepare("SELECT id, fatura_no, duzenleme_tarihi, genel_toplam, durum FROM faturalar WHERE mukellef_id=? ORDER BY id DESC LIMIT 5");
$fat_q->execute([$talep['mukellef_id']]);
$son_faturalar = $fat_q->fetchAll();

$kategori_map = [
    'fatura_sorunu' => ['🧾', 'Fatura Sorunu'],
    'teknik_destek' => ['⚙️', 'Teknik Destek'],
    'bilgi_talebi'  => ['💬', 'Bilgi Talebi'],
    'iptal_iade'    => ['↩️', 'İptal / İade'],
    'diger'         => ['📋', 'Diğer'],
];
$kat = $kategori_map[$talep['kategori']] ?? $kategori_map['diger'];

$durum_map = [
    'acik'       => ['🟠 Açık', '#fef3c7', '#78350f'],
    'cevaplandi' => ['✓ Cevaplandı', '#d1fae5', '#065f46'],
    'beklemede'  => ['⏳ Yanıt Bekliyor', '#fee2e2', '#7f1d1d'],
    'kapali'     => ['⚫ Kapalı', '#f1f5f9', '#475569'],
];
$dr = $durum_map[$talep['durum']] ?? $durum_map['acik'];

render_header($talep['talep_no'] . ' · ' . $talep['konu'], 'destek-admin');
?>

<div class="page-head">
    <a href="<?= SITE_URL ?>/yonetim/destek.php" class="btn btn-ghost">← Taleplere Dön</a>
</div>

<?php if ($err): ?>
    <div class="alert alert-danger"><?= icon('alert') ?><div><?= h($err) ?></div></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
    <!-- SOL: MESAJ THREAD -->
    <div>
        <div class="card">
            <div style="padding:22px 26px;border-bottom:1px solid #e2e8f0;background:linear-gradient(135deg,#f8fafc 0%,#fff 100%)">
                <div style="display:flex;gap:14px;align-items:flex-start">
                    <div style="width:52px;height:52px;background:#0a2540;color:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0">
                        <?= $kat[0] ?>
                    </div>
                    <div style="flex:1">
                        <div style="display:flex;gap:6px;align-items:center;margin-bottom:4px;flex-wrap:wrap">
                            <span style="font-family:monospace;font-size:11.5px;color:#94a3b8;font-weight:600"><?= h($talep['talep_no']) ?></span>
                            <span style="background:<?= $dr[1] ?>;color:<?= $dr[2] ?>;padding:2px 9px;border-radius:10px;font-size:11px;font-weight:700"><?= $dr[0] ?></span>
                            <span style="background:#f1f5f9;color:#475569;padding:2px 9px;border-radius:10px;font-size:11px"><?= $kat[1] ?></span>
                        </div>
                        <h2 style="margin:0;font-size:18px;font-weight:700;color:#0f172a"><?= h($talep['konu']) ?></h2>
                        <div style="font-size:12px;color:#94a3b8;margin-top:5px">
                            Açılış: <?= fmt_datetime($talep['created_at']) ?>
                            <?php if ($talep['ilgili_fatura_no']): ?>
                                · <a href="<?= SITE_URL ?>/fatura/detay.php?id=<?= $talep['ilgili_fatura_id'] ?>" style="color:#0b5cff;font-family:monospace">↗ <?= h($talep['ilgili_fatura_no']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MESAJLAR -->
            <div style="padding:22px 26px;background:#f8fafc;max-height:600px;overflow-y:auto">
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
                            <div style="width:40px;height:40px;<?= $is_admin ? 'background:linear-gradient(135deg,#0b5cff,#0a2540);color:#fff' : 'background:#e2e8f0;color:#475569' ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0"><?= h(strtoupper(mb_substr($m['kullanici_adi'] ?: 'C', 0, 1))) ?></div>
                            <div style="max-width:75%">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;<?= $is_admin ? 'justify-content:flex-end' : '' ?>">
                                    <strong style="font-size:13px;color:#0f172a"><?= h($m['kullanici_adi']) ?></strong>
                                    <?php if ($is_admin): ?>
                                        <span style="background:#eff6ff;color:#1e40af;padding:1px 7px;border-radius:10px;font-size:10px;font-weight:700">CODEGA</span>
                                    <?php else: ?>
                                        <span style="background:#fef3c7;color:#78350f;padding:1px 7px;border-radius:10px;font-size:10px;font-weight:700">MÜŞTERİ</span>
                                    <?php endif; ?>
                                    <span style="font-size:11px;color:#94a3b8"><?= fmt_datetime($m['created_at']) ?></span>
                                </div>
                                <div style="background:<?= $is_admin ? 'linear-gradient(135deg,#0b5cff,#0a2540)' : '#fff' ?>;<?= $is_admin ? 'color:#fff' : 'color:#0f172a;border:1px solid #e2e8f0' ?>;padding:12px 16px;border-radius:<?= $is_admin ? '14px 14px 2px 14px' : '14px 14px 14px 2px' ?>;font-size:13.5px;line-height:1.55;white-space:pre-wrap;word-break:break-word"><?= h($m['mesaj']) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- YANIT FORMU -->
            <?php if ($talep['durum'] !== 'kapali'): ?>
                <div style="padding:22px 26px;border-top:1px solid #e2e8f0">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reply">

                        <div class="fg">
                            <label>Yanıtınız *</label>
                            <textarea name="mesaj" required minlength="3" rows="5" style="font-family:inherit;resize:vertical" placeholder="Müşterinize nazik ve çözüm odaklı yanıt verin..."></textarea>
                        </div>

                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                                <input type="checkbox" name="yeni_durum" value="kapali">
                                <span>Yanıt sonrası talebi kapat</span>
                            </label>
                            <button type="submit" class="btn btn-primary">
                                <?= icon('paper-plane', 14) ?> Yanıtı Gönder
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div style="padding:22px 26px;border-top:1px solid #e2e8f0;text-align:center;background:#f8fafc">
                    <div style="color:#64748b;font-size:13.5px">
                        <?= icon('lock', 16) ?> Bu talep kapalı. Yeniden açmak için:
                    </div>
                    <form method="POST" style="display:inline-block;margin-top:10px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="durum" value="acik">
                        <button type="submit" class="btn btn-outline btn-sm">Tekrar Aç</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SAĞ: MÜŞTERİ BİLGİLERİ -->
    <div>
        <!-- Müşteri Kartı -->
        <div class="card">
            <div class="card-head">
                <?= icon('building') ?>
                <h3>Müşteri</h3>
            </div>
            <div class="card-body" style="padding:16px 22px;font-size:13px">
                <div style="padding:5px 0;border-bottom:1px solid #f1f5f9">
                    <div style="color:#94a3b8;font-size:10.5px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:2px">Firma</div>
                    <strong style="font-size:13.5px"><?= h($talep['musteri_unvan']) ?></strong>
                </div>
                <div style="padding:5px 0;border-bottom:1px solid #f1f5f9">
                    <div style="color:#94a3b8;font-size:10.5px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:2px">VKN</div>
                    <strong style="font-family:monospace"><?= h($talep['musteri_vkn']) ?></strong>
                </div>
                <div style="padding:5px 0;border-bottom:1px solid #f1f5f9">
                    <div style="color:#94a3b8;font-size:10.5px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:2px">Talep Sahibi</div>
                    <strong><?= h($talep['olusturan_ad'] ?: $talep['olusturan_user']) ?></strong>
                    <?php if ($talep['olusturan_email']): ?>
                        <div style="font-size:12px;color:#64748b;margin-top:2px"><?= h($talep['olusturan_email']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($talep['musteri_tel']): ?>
                    <div style="padding:5px 0;border-bottom:1px solid #f1f5f9">
                        <div style="color:#94a3b8;font-size:10.5px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:2px">Telefon</div>
                        <a href="tel:<?= h($talep['musteri_tel']) ?>" style="color:#0b5cff;font-weight:600"><?= h($talep['musteri_tel']) ?></a>
                    </div>
                <?php endif; ?>
                <div style="padding:5px 0">
                    <div style="color:#94a3b8;font-size:10.5px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:2px">Öncelik</div>
                    <?php
                    $oncelik_map = [
                        'acil'=>['🔴','Acil','#dc2626'], 'yuksek'=>['🟠','Yüksek','#d97706'],
                        'normal'=>['','Normal','#64748b'], 'dusuk'=>['⬇','Düşük','#64748b'],
                    ];
                    $onc = $oncelik_map[$talep['oncelik']] ?? $oncelik_map['normal'];
                    ?>
                    <strong style="color:<?= $onc[2] ?>"><?= $onc[0] ?> <?= $onc[1] ?></strong>
                </div>
            </div>
        </div>

        <!-- Hızlı Durum Değişim -->
        <?php if ($talep['durum'] !== 'kapali'): ?>
        <div class="card">
            <div class="card-head">
                <?= icon('refresh') ?>
                <h3>Hızlı İşlem</h3>
            </div>
            <div class="card-body" style="padding:14px 22px">
                <form method="POST" style="display:flex;gap:6px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_status">
                    <select name="durum" class="fg" style="flex:1;margin:0">
                        <option value="acik" <?= $talep['durum']==='acik'?'selected':'' ?>>Açık</option>
                        <option value="cevaplandi" <?= $talep['durum']==='cevaplandi'?'selected':'' ?>>Cevaplandı</option>
                        <option value="beklemede" <?= $talep['durum']==='beklemede'?'selected':'' ?>>Beklemede</option>
                        <option value="kapali">Kapat</option>
                    </select>
                    <button type="submit" class="btn btn-ghost btn-sm">Uygula</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Son Faturalar -->
        <?php if (!empty($son_faturalar)): ?>
        <div class="card">
            <div class="card-head">
                <?= icon('invoice') ?>
                <h3>Son Faturalar (<?= count($son_faturalar) ?>)</h3>
            </div>
            <div class="card-body" style="padding:10px 22px">
                <?php foreach ($son_faturalar as $f): ?>
                    <a href="<?= SITE_URL ?>/fatura/detay.php?id=<?= $f['id'] ?>" style="display:flex;padding:8px 0;border-bottom:1px solid #f1f5f9;text-decoration:none;gap:10px">
                        <div style="flex:1;min-width:0">
                            <div style="font-family:monospace;font-size:11.5px;font-weight:600;color:#0a2540;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($f['fatura_no']) ?></div>
                            <div style="font-size:10.5px;color:#94a3b8"><?= date('d.m.Y', strtotime($f['duzenleme_tarihi'])) ?></div>
                        </div>
                        <div style="font-size:12px;font-weight:700;color:#0f172a;font-variant-numeric:tabular-nums;white-space:nowrap"><?= fmt_tl((float)$f['genel_toplam']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Diğer Talepler -->
        <?php if (!empty($diger_talepler)): ?>
        <div class="card">
            <div class="card-head">
                <?= icon('megaphone') ?>
                <h3>Diğer Talepleri</h3>
            </div>
            <div class="card-body" style="padding:10px 22px">
                <?php foreach ($diger_talepler as $dt):
                    $dt_dr = $durum_map[$dt['durum']] ?? $durum_map['acik'];
                ?>
                    <a href="<?= SITE_URL ?>/yonetim/destek-detay.php?id=<?= $dt['id'] ?>" style="display:block;padding:8px 0;border-bottom:1px solid #f1f5f9;text-decoration:none">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px">
                            <span style="font-family:monospace;font-size:11px;color:#94a3b8;font-weight:600"><?= h($dt['talep_no']) ?></span>
                            <span style="background:<?= $dt_dr[1] ?>;color:<?= $dt_dr[2] ?>;padding:1px 7px;border-radius:8px;font-size:10px"><?= $dt_dr[0] ?></span>
                        </div>
                        <div style="font-size:12.5px;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($dt['konu']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
