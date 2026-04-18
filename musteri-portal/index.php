<?php
// Müşteri portal: config.php otomatik session başlatmasın (ayrı session kullanıyoruz)
define('CODEGA_NO_AUTO_SESSION', true);

require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

mp_auth_require();
$user = mp_auth_user();
$mid = (int)$user['mukellef_id'];

// ═══ KPI SORGULARI ═══════════════════════════════════
$q = $pdo->prepare("SELECT COUNT(*) FROM faturalar WHERE mukellef_id=?");
$q->execute([$mid]); $toplam = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM faturalar WHERE mukellef_id=? AND DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m')");
$q->execute([$mid]); $bu_ay = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM faturalar WHERE mukellef_id=? AND DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH),'%Y-%m')");
$q->execute([$mid]); $gecen_ay = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM faturalar WHERE mukellef_id=? AND durum IN ('hazir','imzali','gonderildi')");
$q->execute([$mid]); $bekleyen = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COALESCE(SUM(genel_toplam),0) FROM faturalar WHERE mukellef_id=? AND durum NOT IN ('iptal','taslak')");
$q->execute([$mid]); $toplam_tutar = (float)$q->fetchColumn();

$q = $pdo->prepare("SELECT COALESCE(SUM(genel_toplam),0) FROM faturalar WHERE mukellef_id=? AND DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m') AND durum NOT IN ('iptal','taslak')");
$q->execute([$mid]); $bu_ay_tutar = (float)$q->fetchColumn();

$q = $pdo->prepare("SELECT COALESCE(SUM(genel_toplam),0) FROM faturalar WHERE mukellef_id=? AND YEAR(created_at)=YEAR(NOW()) AND durum NOT IN ('iptal','taslak')");
$q->execute([$mid]); $bu_yil_tutar = (float)$q->fetchColumn();

// Son 8 fatura
$q = $pdo->prepare("SELECT * FROM faturalar WHERE mukellef_id=? ORDER BY id DESC LIMIT 6");
$q->execute([$mid]); $son_faturalar = $q->fetchAll();

// Durum dağılımı
$q = $pdo->prepare("SELECT durum, COUNT(*) AS c FROM faturalar WHERE mukellef_id=? GROUP BY durum ORDER BY c DESC");
$q->execute([$mid]); $durum_dagilim = $q->fetchAll();

// Son 6 ay trend (fatura adedi ve tutar)
$q = $pdo->prepare("
    SELECT DATE_FORMAT(duzenleme_tarihi, '%Y-%m') AS ay,
           COUNT(*) AS adet,
           COALESCE(SUM(genel_toplam),0) AS tutar
    FROM faturalar
    WHERE mukellef_id=? AND duzenleme_tarihi >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ay
    ORDER BY ay ASC
");
$q->execute([$mid]);
$trend_raw = $q->fetchAll();

// Son 6 ay'ı boş olanları da dahil ederek doldur
$trend = [];
for ($i = 5; $i >= 0; $i--) {
    $ay = date('Y-m', strtotime("-$i months"));
    $trend[$ay] = ['ay' => $ay, 'adet' => 0, 'tutar' => 0];
}
foreach ($trend_raw as $r) {
    if (isset($trend[$r['ay']])) {
        $trend[$r['ay']] = ['ay' => $r['ay'], 'adet' => (int)$r['adet'], 'tutar' => (float)$r['tutar']];
    }
}
$trend = array_values($trend);
$max_adet = max(1, ...array_column($trend, 'adet'));
$max_tutar = max(1, ...array_column($trend, 'tutar'));

// En büyük 3 fatura (son 12 ay)
$q = $pdo->prepare("
    SELECT id, fatura_no, duzenleme_tarihi, genel_toplam, durum
    FROM faturalar
    WHERE mukellef_id=? AND durum NOT IN ('iptal','taslak')
    AND duzenleme_tarihi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    ORDER BY genel_toplam DESC LIMIT 5
");
$q->execute([$mid]);
$en_buyuk = $q->fetchAll();

// En çok alınan ürünler (top 5)
$q = $pdo->prepare("
    SELECT fs.urun_adi, COUNT(DISTINCT fs.fatura_id) AS kac_fatura, SUM(fs.miktar) AS toplam_miktar, SUM(fs.satir_toplam) AS toplam_tutar
    FROM fatura_satirlari fs
    INNER JOIN faturalar f ON f.id = fs.fatura_id
    WHERE f.mukellef_id = ? AND f.durum NOT IN ('iptal','taslak')
    GROUP BY fs.urun_adi
    ORDER BY kac_fatura DESC, toplam_tutar DESC
    LIMIT 5
");
$q->execute([$mid]);
$top_urunler = $q->fetchAll();

// Son aktiviteler (kendi)
$q = $pdo->prepare("SELECT * FROM musteri_portal_log WHERE musteri_kullanici_id=? ORDER BY id DESC LIMIT 6");
$q->execute([$user['id']]);
$aktiviteler = $q->fetchAll();

// Aktif duyurular (müşteri için)
$duyuru_q = $pdo->query("
    SELECT * FROM duyurular
    WHERE aktif=1
      AND hedef IN ('musteri','her_ikisi')
      AND (bitis_tarihi IS NULL OR bitis_tarihi > NOW())
    ORDER BY
        CASE tip WHEN 'onemli' THEN 1 WHEN 'bakim' THEN 2 WHEN 'uyari' THEN 3 ELSE 4 END,
        id DESC
    LIMIT 3
");
$duyurular = $duyuru_q ? $duyuru_q->fetchAll() : [];

// Destek istatistikleri (müşterinin kendi talepleri)
try {
    $dk = $pdo->prepare("SELECT COUNT(*) FROM destek_talepleri WHERE mukellef_id=? AND durum NOT IN ('kapali')");
    $dk->execute([$mid]);
    $acik_talep = (int)$dk->fetchColumn();

    $dn = $pdo->prepare("SELECT COUNT(*) FROM destek_talepleri WHERE mukellef_id=? AND musteri_okundu=0");
    $dn->execute([$mid]);
    $yeni_yanit = (int)$dn->fetchColumn();
} catch (\Exception $e) {
    $acik_talep = 0; $yeni_yanit = 0;
}

// Selamlama
$saat = (int)date('H');
$selam = $saat < 5 ? 'İyi geceler' : ($saat < 12 ? 'Günaydın' : ($saat < 18 ? 'İyi günler' : 'İyi akşamlar'));

// Aylık değişim
$aylik_degisim = $gecen_ay > 0 ? round(($bu_ay - $gecen_ay) / $gecen_ay * 100) : ($bu_ay > 0 ? 100 : 0);

// Firma bilgisi
$firma_q = $pdo->prepare("SELECT * FROM mukellefler WHERE id=?");
$firma_q->execute([$mid]);
$firma = $firma_q->fetch();

// Haftanın günü / Türkiye iş günü?
$bugun_gun = (int)date('N'); // 1=Pazartesi ... 7=Pazar
$is_gunu = $bugun_gun <= 5;
$ay_isimleri_tr = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

mp_audit($pdo, 'musteri.view_dashboard');

mp_render_header('Ana Sayfa', 'dashboard');
?>

<!-- ═══ DUYURULAR ═══ -->
<?php foreach ($duyurular as $d):
    $tip_style = match($d['tip']) {
        'onemli' => ['🔴', '#fee2e2', '#7f1d1d', '#dc2626'],
        'uyari'  => ['⚠️', '#fef3c7', '#78350f', '#d97706'],
        'bakim'  => ['🔧', '#ede9fe', '#4c1d95', '#7c3aed'],
        default  => ['💡', '#dbeafe', '#1e3a8a', '#0284c7'],
    };
?>
    <div style="background:<?= $tip_style[1] ?>;border-left:4px solid <?= $tip_style[3] ?>;padding:14px 18px;border-radius:10px;margin-bottom:14px;display:flex;gap:14px;align-items:flex-start">
        <div style="font-size:22px;flex-shrink:0"><?= $tip_style[0] ?></div>
        <div style="flex:1">
            <div style="font-weight:700;color:<?= $tip_style[2] ?>;font-size:14px;margin-bottom:4px"><?= h($d['baslik']) ?></div>
            <div style="font-size:13px;color:<?= $tip_style[2] ?>;opacity:0.9;line-height:1.5;white-space:pre-wrap"><?= h($d['icerik']) ?></div>
        </div>
    </div>
<?php endforeach; ?>

<!-- ═══ DESTEK YANIT BİLDİRİMİ ═══ -->
<?php if ($yeni_yanit > 0): ?>
    <a href="<?= SITE_URL ?>/musteri-portal/destek.php" style="display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,#0b5cff 0%,#0847c5 100%);color:#fff;padding:16px 20px;border-radius:10px;margin-bottom:14px;text-decoration:none;box-shadow:0 6px 20px rgba(11,92,255,0.25);position:relative;overflow:hidden">
        <div style="position:absolute;top:-40px;right:-40px;width:140px;height:140px;background:rgba(255,255,255,0.1);border-radius:50%"></div>
        <div style="width:44px;height:44px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative">
            <?= mp_icon('message', 22) ?>
        </div>
        <div style="flex:1;position:relative">
            <div style="font-weight:700;font-size:14.5px;margin-bottom:2px">CODEGA size yanıt verdi</div>
            <div style="font-size:12.5px;opacity:0.9"><?= $yeni_yanit ?> destek talebinize yeni mesaj geldi · görüntülemek için tıklayın</div>
        </div>
        <div style="font-size:22px;position:relative">→</div>
    </a>
<?php endif; ?>

<!-- ═══ HOŞGELDİN BANNER ═══ -->
<div class="mp-welcome" style="margin-bottom:20px">
    <div class="mp-welcome-content">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px">
            <div style="min-width:260px">
                <h2><?= $selam ?>, <?= h($user['ad_soyad'] ?: $user['user']) ?></h2>
                <p>
                    CODEGA Müşteri Portalı'na hoş geldiniz. Adınıza düzenlenen e-Faturaları buradan takip edebilir, XML dosyalarını indirebilirsiniz.
                </p>
                <div class="mp-welcome-firm">
                    <?= mp_icon('building', 14) ?>
                    <?= h($user['unvan']) ?>
                </div>
            </div>
            <div style="text-align:right;color:rgba(255,255,255,0.85);font-size:13px;min-width:180px">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;opacity:0.75;margin-bottom:4px">Bugün</div>
                <div style="font-size:22px;font-weight:700;color:#fff"><?= date('d') ?> <?= $ay_isimleri_tr[(int)date('n')-1] ?> <?= date('Y') ?></div>
                <div style="margin-top:4px;font-size:12px">
                    <?php
                    $gun_tr = ['Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi','Pazar'];
                    echo $gun_tr[$bugun_gun - 1];
                    ?>
                    <?php if (!$is_gunu): ?>
                        <span style="background:rgba(255,200,100,0.25);padding:2px 7px;border-radius:10px;margin-left:6px;font-size:10.5px">Tatil</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ KPI GRID ═══ -->
<div class="mp-kpi-grid">
    <div class="mp-kpi">
        <div class="mp-kpi-head">
            <div>
                <div class="mp-kpi-label">Toplam Fatura</div>
                <div class="mp-kpi-value"><?= number_format($toplam) ?></div>
                <div class="mp-kpi-sub"><?= $bu_yil_tutar > 0 ? 'Bu yıl: '.fmt_tl($bu_yil_tutar) : 'Kayıt bekleniyor' ?></div>
            </div>
            <div class="mp-kpi-icon blue"><?= mp_icon('invoice', 20) ?></div>
        </div>
    </div>

    <div class="mp-kpi">
        <div class="mp-kpi-head">
            <div>
                <div class="mp-kpi-label">Bu Ay</div>
                <div class="mp-kpi-value"><?= number_format($bu_ay) ?></div>
                <div class="mp-kpi-sub" style="display:flex;align-items:center;gap:4px">
                    <?php if ($aylik_degisim > 0): ?>
                        <span style="color:#059669;font-weight:600">↑ %<?= $aylik_degisim ?></span>
                    <?php elseif ($aylik_degisim < 0): ?>
                        <span style="color:#dc2626;font-weight:600">↓ %<?= abs($aylik_degisim) ?></span>
                    <?php else: ?>
                        <span style="color:#94a3b8">↔ değişim yok</span>
                    <?php endif; ?>
                    <span>önceki aya göre</span>
                </div>
            </div>
            <div class="mp-kpi-icon orange"><?= mp_icon('trending-up', 20) ?></div>
        </div>
    </div>

    <div class="mp-kpi">
        <div class="mp-kpi-head">
            <div>
                <div class="mp-kpi-label">İşlem Bekleyen</div>
                <div class="mp-kpi-value"><?= number_format($bekleyen) ?></div>
                <div class="mp-kpi-sub">
                    <?php if ($bekleyen > 0): ?>
                        <span style="color:#d97706;font-weight:600">⏳ İncelemeniz gerekiyor</span>
                    <?php else: ?>
                        <span style="color:#059669">✓ Tüm faturalar kapalı</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mp-kpi-icon orange"><?= mp_icon('clock', 20) ?></div>
        </div>
    </div>

    <div class="mp-kpi">
        <div class="mp-kpi-head">
            <div>
                <div class="mp-kpi-label">Bu Ay Tutar</div>
                <div class="mp-kpi-value small"><?= fmt_tl($bu_ay_tutar) ?></div>
                <div class="mp-kpi-sub">Tüm zaman: <?= fmt_tl($toplam_tutar) ?></div>
            </div>
            <div class="mp-kpi-icon green"><?= mp_icon('chart', 20) ?></div>
        </div>
    </div>
</div>

<!-- ═══ ANA GRID: TREND + DURUM + TOP ÜRÜN ═══ -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Sol: Son 6 Ay Trend SVG Chart -->
    <div class="mp-card">
        <div class="mp-card-head">
            <?= mp_icon('chart') ?>
            <h3>Son 6 Ay Fatura Trendi</h3>
            <span style="margin-left:auto;font-size:12px;color:#94a3b8;font-weight:500">
                <?= array_sum(array_column($trend, 'adet')) ?> adet · <?= fmt_tl(array_sum(array_column($trend, 'tutar'))) ?>
            </span>
        </div>
        <div class="mp-card-body">
            <?php if (array_sum(array_column($trend, 'adet')) === 0): ?>
                <div style="text-align:center;padding:40px 20px;color:#94a3b8">
                    <?= mp_icon('chart', 48) ?>
                    <div style="margin-top:10px;font-size:13.5px">Son 6 aylık trend için henüz yeterli veri yok</div>
                </div>
            <?php else: ?>
                <?php
                // SVG bar chart — 6 ay
                $w = 560; $h = 220; $pad_l = 40; $pad_r = 20; $pad_t = 20; $pad_b = 40;
                $inner_w = $w - $pad_l - $pad_r;
                $inner_h = $h - $pad_t - $pad_b;
                $bar_w = $inner_w / count($trend) * 0.6;
                $gap = $inner_w / count($trend);
                ?>
                <svg viewBox="0 0 <?= $w ?> <?= $h ?>" style="width:100%;height:auto;max-height:280px" xmlns="http://www.w3.org/2000/svg">
                    <!-- Grid -->
                    <?php for ($i = 0; $i <= 4; $i++):
                        $y = $pad_t + ($inner_h * $i / 4);
                        $val = $max_adet - ($max_adet * $i / 4);
                    ?>
                        <line x1="<?= $pad_l ?>" y1="<?= $y ?>" x2="<?= $w - $pad_r ?>" y2="<?= $y ?>" stroke="#f1f5f9" stroke-width="1"/>
                        <text x="<?= $pad_l - 8 ?>" y="<?= $y + 3 ?>" font-size="10" fill="#94a3b8" text-anchor="end" font-family="Inter,sans-serif"><?= round($val) ?></text>
                    <?php endfor; ?>

                    <!-- Bars -->
                    <?php foreach ($trend as $i => $d):
                        $bar_h = $inner_h * ($d['adet'] / $max_adet);
                        $x = $pad_l + $i * $gap + ($gap - $bar_w) / 2;
                        $y = $pad_t + ($inner_h - $bar_h);
                        $ay_label = $ay_isimleri_tr[(int)substr($d['ay'], 5, 2) - 1];
                        $yil_short = substr($d['ay'], 2, 2);
                    ?>
                        <?php if ($d['adet'] > 0): ?>
                            <rect x="<?= $x ?>" y="<?= $y ?>" width="<?= $bar_w ?>" height="<?= $bar_h ?>"
                                  rx="4" fill="url(#bar-grad-<?= $i ?>)">
                                <title><?= $ay_label ?> <?= substr($d['ay'], 0, 4) ?>: <?= $d['adet'] ?> fatura · <?= fmt_tl($d['tutar']) ?></title>
                            </rect>
                            <text x="<?= $x + $bar_w/2 ?>" y="<?= $y - 5 ?>" font-size="11" fill="#0f172a" text-anchor="middle" font-weight="600" font-family="Inter,sans-serif"><?= $d['adet'] ?></text>
                        <?php else: ?>
                            <rect x="<?= $x ?>" y="<?= $pad_t + $inner_h - 4 ?>" width="<?= $bar_w ?>" height="4" rx="2" fill="#e2e8f0"/>
                        <?php endif; ?>
                        <!-- Gradient -->
                        <defs>
                            <linearGradient id="bar-grad-<?= $i ?>" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#0b5cff"/>
                                <stop offset="100%" stop-color="#1e3a5f"/>
                            </linearGradient>
                        </defs>
                        <!-- Ay etiketleri -->
                        <text x="<?= $x + $bar_w/2 ?>" y="<?= $h - 18 ?>" font-size="11" fill="#64748b" text-anchor="middle" font-family="Inter,sans-serif" font-weight="500"><?= $ay_label ?></text>
                        <text x="<?= $x + $bar_w/2 ?>" y="<?= $h - 5 ?>" font-size="9" fill="#cbd5e1" text-anchor="middle" font-family="Inter,sans-serif">'<?= $yil_short ?></text>
                    <?php endforeach; ?>

                    <!-- X ekseni -->
                    <line x1="<?= $pad_l ?>" y1="<?= $pad_t + $inner_h ?>" x2="<?= $w - $pad_r ?>" y2="<?= $pad_t + $inner_h ?>" stroke="#cbd5e1" stroke-width="1"/>
                </svg>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sağ: Durum Dağılımı -->
    <div class="mp-card">
        <div class="mp-card-head">
            <?= mp_icon('check-circle') ?>
            <h3>Faturalarımın Durumu</h3>
        </div>
        <div class="mp-card-body">
            <?php if (empty($durum_dagilim)): ?>
                <div style="text-align:center;padding:30px 10px;color:#94a3b8;font-size:13px">
                    Henüz fatura yok
                </div>
            <?php else: foreach ($durum_dagilim as $d):
                $pct = $toplam > 0 ? round($d['c'] / $toplam * 100) : 0;
                $label_map = [
                    'taslak' => 'Taslak', 'hazir' => 'Hazırlanıyor', 'imzali' => 'İmzalandı',
                    'gonderildi' => 'Gönderildi', 'kabul' => 'Kabul Edildi', 'red' => 'Reddedildi',
                    'iptal' => 'İptal Edildi',
                ];
                $color_map = [
                    'taslak' => '#94a3b8', 'hazir' => '#0284c7', 'imzali' => '#6366f1',
                    'gonderildi' => '#f59e0b', 'kabul' => '#10b981', 'red' => '#dc2626',
                    'iptal' => '#dc2626',
                ];
                $color = $color_map[$d['durum']] ?? '#64748b';
            ?>
                <div style="margin-bottom:11px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:12.5px">
                        <span style="color:#475569;font-weight:500"><?= $label_map[$d['durum']] ?? $d['durum'] ?></span>
                        <span style="color:#94a3b8"><strong style="color:#0f172a"><?= $d['c'] ?></strong></span>
                    </div>
                    <div style="height:7px;background:#f1f5f9;border-radius:4px;overflow:hidden">
                        <div style="height:100%;background:<?= $color ?>;width:<?= $pct ?>%;border-radius:4px"></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- ═══ SON FATURALAR + EN BÜYÜK ═══ -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Son 6 Fatura -->
    <div class="mp-card">
        <div class="mp-card-head">
            <?= mp_icon('invoice') ?>
            <h3>Son Faturalar</h3>
            <a href="<?= SITE_URL ?>/musteri-portal/faturalar.php" class="mp-card-head-action">Tümünü Gör →</a>
        </div>
        <div class="mp-card-body tight">
            <div class="mp-table-wrap">
                <table class="mp-table">
                    <thead>
                        <tr>
                            <th>Fatura No</th>
                            <th>Tarih</th>
                            <th style="text-align:right">Tutar</th>
                            <th>Durum</th>
                            <th style="text-align:right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($son_faturalar)): ?>
                            <tr><td colspan="5">
                                <div class="mp-table-empty">
                                    <?= mp_icon('invoice', 48) ?>
                                    <h4>Henüz fatura yok</h4>
                                    <p>Adınıza düzenlenmiş fatura bulunmuyor</p>
                                </div>
                            </td></tr>
                        <?php else: foreach ($son_faturalar as $f):
                            $durum_badge = match($f['durum']) {
                                'taslak'     => ['secondary', 'Taslak', 'clock'],
                                'hazir'      => ['info',      'Hazırlanıyor', 'clock'],
                                'imzali'     => ['primary',   'İmzalandı', 'signature'],
                                'gonderildi' => ['warning',   'Gönderildi', 'paper-plane'],
                                'kabul'      => ['success',   'Kabul Edildi', 'check-circle'],
                                'red'        => ['danger',    'Reddedildi', 'x-circle'],
                                'iptal'      => ['danger',    'İptal Edildi', 'ban'],
                                default      => ['secondary', $f['durum'], 'info'],
                            };
                        ?>
                            <tr>
                                <td>
                                    <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $f['id'] ?>" style="font-family:monospace;font-size:12.5px;font-weight:600;color:#0a2540">
                                        <?= h($f['fatura_no']) ?>
                                    </a>
                                </td>
                                <td style="color:#64748b;font-size:12.5px;white-space:nowrap">
                                    <?= date('d.m.Y', strtotime($f['duzenleme_tarihi'])) ?>
                                </td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">
                                    <?= fmt_tl((float)$f['genel_toplam']) ?>
                                </td>
                                <td>
                                    <span class="mp-badge mp-badge-<?= $durum_badge[0] ?>">
                                        <?= mp_icon($durum_badge[2], 11) ?>
                                        <?= $durum_badge[1] ?>
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $f['id'] ?>" class="mp-btn mp-btn-ghost mp-btn-sm">
                                        <?= mp_icon('eye', 13) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- En Büyük Faturalar -->
    <div class="mp-card">
        <div class="mp-card-head">
            <?= mp_icon('flame') ?>
            <h3>En Büyük Faturalar</h3>
        </div>
        <div class="mp-card-body" style="padding:12px 22px">
            <?php if (empty($en_buyuk)): ?>
                <div style="text-align:center;padding:20px 0;color:#94a3b8;font-size:13px">Henüz kayıt yok</div>
            <?php else: foreach ($en_buyuk as $idx => $f):
                $medal = ['🥇','🥈','🥉','','',''][$idx] ?? '';
            ?>
                <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $f['id'] ?>"
                   style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:<?= $idx === count($en_buyuk)-1 ? 'none' : '1px solid #f1f5f9' ?>;text-decoration:none">
                    <div style="width:30px;font-size:16px;text-align:center;flex-shrink:0">
                        <?= $medal ?: '<span style="color:#cbd5e1;font-weight:700;font-size:13px">'.($idx+1).'</span>' ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-family:monospace;font-size:12px;font-weight:600;color:#0a2540;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($f['fatura_no']) ?></div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:1px"><?= date('d.m.Y', strtotime($f['duzenleme_tarihi'])) ?></div>
                    </div>
                    <div style="text-align:right;flex-shrink:0">
                        <div style="font-weight:700;font-size:13px;color:#0f172a;font-variant-numeric:tabular-nums"><?= fmt_tl((float)$f['genel_toplam']) ?></div>
                    </div>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- ═══ TOP ÜRÜNLER + AKTİVİTELER ═══ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Top Ürünler -->
    <div class="mp-card">
        <div class="mp-card-head">
            <?= mp_icon('package') ?>
            <h3>En Çok Alınan Ürün/Hizmet</h3>
        </div>
        <div class="mp-card-body" style="padding:14px 22px">
            <?php if (empty($top_urunler)): ?>
                <div style="text-align:center;padding:20px 0;color:#94a3b8;font-size:13px">Henüz ürün satırı yok</div>
            <?php else:
                $max_fatura_sayi = max(1, ...array_column($top_urunler, 'kac_fatura'));
                foreach ($top_urunler as $idx => $u):
                    $pct = round(($u['kac_fatura'] / $max_fatura_sayi) * 100);
            ?>
                <div style="padding:9px 0;<?= $idx === count($top_urunler)-1 ? '' : 'border-bottom:1px solid #f1f5f9' ?>">
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;gap:10px">
                        <span style="font-size:13px;font-weight:500;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:70%"><?= h($u['urun_adi']) ?></span>
                        <span style="font-size:11.5px;color:#94a3b8;font-weight:500;white-space:nowrap">
                            <strong style="color:#0f172a"><?= (int)$u['kac_fatura'] ?></strong> fatura
                        </span>
                    </div>
                    <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden">
                        <div style="height:100%;background:linear-gradient(90deg,#0b5cff,#1e3a5f);width:<?= $pct ?>%;border-radius:3px"></div>
                    </div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:3px">Toplam: <?= fmt_tl((float)$u['toplam_tutar']) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Aktivitelerim -->
    <div class="mp-card">
        <div class="mp-card-head">
            <?= mp_icon('clock') ?>
            <h3>Son Aktivitelerim</h3>
        </div>
        <div class="mp-card-body" style="padding:12px 22px">
            <?php if (empty($aktiviteler)): ?>
                <div style="text-align:center;padding:20px 0;color:#94a3b8;font-size:13px">Henüz aktivite yok</div>
            <?php else:
                $olay_etiket = [
                    'musteri.login_ok'       => ['Giriş yapıldı', '✓', '#059669'],
                    'musteri.logout'         => ['Çıkış yapıldı', '→', '#64748b'],
                    'musteri.view_dashboard' => ['Ana sayfa görüntülendi', '◉', '#0b5cff'],
                    'musteri.view_faturalar' => ['Fatura listesi açıldı', '≡', '#0b5cff'],
                    'musteri.view_fatura'    => ['Fatura görüntülendi', '👁', '#7c3aed'],
                    'musteri.view_yardim'    => ['Yardım açıldı', '?', '#d97706'],
                    'musteri.sifre_degistir' => ['Şifre değiştirildi', '🔒', '#059669'],
                    'musteri.indir'          => ['Dosya indirildi', '↓', '#16a34a'],
                ];
                foreach ($aktiviteler as $idx => $a):
                    [$label, $icon_char, $color] = $olay_etiket[$a['olay']] ?? [$a['olay'], '•', '#94a3b8'];
            ?>
                <div style="display:flex;gap:10px;padding:8px 0;<?= $idx === count($aktiviteler)-1 ? '' : 'border-bottom:1px solid #f1f5f9' ?>">
                    <div style="width:24px;height:24px;background:<?= $color ?>;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0"><?= $icon_char ?></div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;color:#0f172a;font-weight:500"><?= h($label) ?></div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:1px">
                            <?= fmt_datetime($a['created_at']) ?>
                            <?php if ($a['ip']): ?>
                                · <span style="font-family:monospace"><?= h($a['ip']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- ═══ CODEGA BİLGİ + YARDIM ═══ -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
    <!-- Firma Bilgileri + E-Fatura Bilgilendirme -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div class="mp-card">
            <div class="mp-card-head">
                <?= mp_icon('building') ?>
                <h3>Firma Bilgileriniz</h3>
            </div>
            <div class="mp-card-body" style="padding:16px 22px;font-size:13px">
                <div style="padding:6px 0;border-bottom:1px solid #f1f5f9">
                    <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px">Ünvan</div>
                    <strong><?= h($firma['unvan']) ?></strong>
                </div>
                <div style="padding:6px 0;border-bottom:1px solid #f1f5f9">
                    <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px"><?= $firma['vkn_tip'] ?></div>
                    <strong style="font-family:monospace"><?= h($firma['vkn_tckn']) ?></strong>
                </div>
                <?php if ($firma['vergi_dairesi']): ?>
                    <div style="padding:6px 0;border-bottom:1px solid #f1f5f9">
                        <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px">Vergi Dairesi</div>
                        <strong><?= h($firma['vergi_dairesi']) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if ($firma['il']): ?>
                    <div style="padding:6px 0;<?= $firma['email'] ? 'border-bottom:1px solid #f1f5f9' : '' ?>">
                        <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px">Adres</div>
                        <strong><?= h($firma['il']) ?><?= $firma['ilce'] ? ' / '.h($firma['ilce']) : '' ?></strong>
                    </div>
                <?php endif; ?>
                <?php if ($firma['email']): ?>
                    <div style="padding:6px 0">
                        <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px">E-posta</div>
                        <strong><?= h($firma['email']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mp-card" style="background:linear-gradient(135deg,#0b5cff 0%,#1e3a5f 100%);color:#fff;border:none">
            <div style="padding:22px">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                    <div style="width:38px;height:38px;background:rgba(255,255,255,0.2);border-radius:9px;display:flex;align-items:center;justify-content:center">
                        <?= mp_icon('star', 18) ?>
                    </div>
                    <h3 style="margin:0;font-size:15px;font-weight:700">e-Fatura Avantajları</h3>
                </div>
                <ul style="margin:0;padding:0 0 0 18px;font-size:12.5px;line-height:1.7;color:rgba(255,255,255,0.9)">
                    <li>Kâğıt, mürekkep, kargo masrafı yok</li>
                    <li>Anlık teslim, bekleme yok</li>
                    <li>10 yıl güvenli dijital arşiv</li>
                    <li>GİB tarafından tescilli, yasal geçerlilik</li>
                    <li>Fatura kaybı riski yok</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Destek / CODEGA -->
    <div class="mp-card">
        <div class="mp-card-head">
            <?= mp_icon('help') ?>
            <h3>Yardım & Destek</h3>
        </div>
        <div class="mp-card-body" style="padding:18px 22px">
            <div style="font-size:13px;color:#475569;line-height:1.55;margin-bottom:16px">
                Bir sorunuz mu var? CODEGA destek ekibi hafta içi <strong>09:00 – 18:00</strong> saatleri arasında size yardımcı olmaktan mutluluk duyar.
            </div>

            <div style="display:flex;flex-direction:column;gap:8px">
                <a href="tel:+905320652400" class="mp-btn mp-btn-brand" style="width:100%;justify-content:flex-start">
                    <?= mp_icon('phone', 14) ?> 0532 065 24 00
                </a>
                <a href="https://wa.me/905320652400" target="_blank" class="mp-btn mp-btn-outline" style="width:100%;justify-content:flex-start;background:#dcfce7;border-color:#bbf7d0;color:#16a34a">
                    <?= mp_icon('whatsapp', 14) ?> WhatsApp ile ulaş
                </a>
                <a href="<?= SITE_URL ?>/musteri-portal/yardim.php" class="mp-btn mp-btn-ghost" style="width:100%;justify-content:flex-start">
                    <?= mp_icon('help', 14) ?> Sıkça Sorulan Sorular
                </a>
            </div>

            <div style="margin-top:16px;padding-top:14px;border-top:1px solid #f1f5f9;text-align:center">
                <div style="font-size:10.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:4px">CODEGA Yazılım</div>
                <div style="font-size:12px;color:#64748b;line-height:1.5">
                    <?= mp_icon('map-pin', 11) ?> Konya, Türkiye<br>
                    <a href="https://codega.com.tr" target="_blank" style="color:#0b5cff">codega.com.tr</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php mp_render_footer(); ?>
