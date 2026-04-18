<?php
require __DIR__ . '/config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require();

// ═══ KPI SORGULARI ═══
$k_fatura = (int)$pdo->query("SELECT COUNT(*) FROM faturalar")->fetchColumn();
$k_ay     = (int)$pdo->query("SELECT COUNT(*) FROM faturalar WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetchColumn();
$k_ay_onceki = (int)$pdo->query("SELECT COUNT(*) FROM faturalar WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m')")->fetchColumn();
$k_taslak = (int)$pdo->query("SELECT COUNT(*) FROM faturalar WHERE durum='taslak'")->fetchColumn();
$k_hazir  = (int)$pdo->query("SELECT COUNT(*) FROM faturalar WHERE durum='hazir'")->fetchColumn();
$k_mukellef = (int)$pdo->query("SELECT COUNT(*) FROM mukellefler WHERE aktif=1")->fetchColumn();
$k_toplam = (float)$pdo->query("SELECT COALESCE(SUM(genel_toplam),0) FROM faturalar WHERE durum NOT IN ('taslak','iptal')")->fetchColumn();
$k_ay_tutar = (float)$pdo->query("SELECT COALESCE(SUM(genel_toplam),0) FROM faturalar WHERE DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m') AND durum NOT IN ('taslak','iptal')")->fetchColumn();

// Trend
$ay_trend = 0;
if ($k_ay_onceki > 0) {
    $ay_trend = round((($k_ay - $k_ay_onceki) / $k_ay_onceki) * 100);
}

// Son 7 günün fatura trendi (basit grafik için)
$son_7 = $pdo->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS c
    FROM faturalar
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d ASC
")->fetchAll();

// Durum dağılımı
$durum_dagilim = $pdo->query("SELECT durum, COUNT(*) AS c FROM faturalar GROUP BY durum ORDER BY c DESC")->fetchAll();

// Son 8 fatura
$son_faturalar = $pdo->query("
    SELECT f.*, m.unvan AS musteri
    FROM faturalar f
    LEFT JOIN mukellefler m ON m.id = f.mukellef_id
    ORDER BY f.id DESC LIMIT 8
")->fetchAll();

// Son 8 sistem log
$son_log = $pdo->query("
    SELECT sl.*, k.kullanici_adi, k.ad_soyad
    FROM sistem_log sl
    LEFT JOIN kullanicilar k ON k.id = sl.kullanici_id
    ORDER BY sl.id DESC LIMIT 8
")->fetchAll();

// Sistem sağlığı
$disk_free = @disk_free_space(STORAGE_PATH);
$disk_total = @disk_total_space(STORAGE_PATH);
$disk_pct = ($disk_total > 0) ? round((1 - $disk_free / $disk_total) * 100) : 0;

$user = auth_user();
$saat = (int)date('H');
$selam = $saat < 5 ? 'İyi geceler' : ($saat < 12 ? 'Günaydın' : ($saat < 18 ? 'İyi günler' : 'İyi akşamlar'));

$portal_ver = ayar_get($pdo, 'portal_surumu', '1.1.0');
$lib_ver    = ayar_get($pdo, 'kutuphane_surumu', '0.1.0-alpha');

render_header('Dashboard', 'dashboard');
?>

<!-- ═══ HOŞGELDİN BANNER ═══ -->
<div class="welcome">
    <div class="welcome-content">
        <div>
            <h2><?= h($selam) ?>, <?= h($user['ad_soyad'] ?: $user['user']) ?> 👋</h2>
            <p>CODEGA Entegratör Portal'a hoş geldiniz. Bugün <?= $k_ay ?> fatura işlendi, toplam <?= fmt_tl($k_ay_tutar) ?> tutarında.</p>
        </div>
        <div class="welcome-time">
            <strong><?= date('d F Y') ?></strong>
            <?= date('l, H:i') ?>
        </div>
    </div>
</div>

<!-- ═══ KPI KARTLARI ═══ -->
<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-head">
            <div>
                <div class="kpi-label">Toplam Fatura</div>
                <div class="kpi-value"><?= number_format($k_fatura) ?></div>
                <div class="kpi-sub">Bu ay: <strong style="color:#059669"><?= $k_ay ?></strong>
                    <?php if ($ay_trend > 0): ?>
                        <span class="badge badge-success" style="margin-left:6px">↑ %<?= $ay_trend ?></span>
                    <?php elseif ($ay_trend < 0): ?>
                        <span class="badge badge-danger" style="margin-left:6px">↓ %<?= abs($ay_trend) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="kpi-icon blue"><?= icon('invoice', 20) ?></div>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-head">
            <div>
                <div class="kpi-label">Hazır / Taslak</div>
                <div class="kpi-value"><?= $k_hazir ?> <span style="color:#94a3b8;font-size:20px">/ <?= $k_taslak ?></span></div>
                <div class="kpi-sub"><?= $k_hazir > 0 ? 'İmza bekliyor' : 'Tümü işlendi' ?></div>
            </div>
            <div class="kpi-icon orange"><?= icon('clock', 20) ?></div>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-head">
            <div>
                <div class="kpi-label">Aktif Müşteri</div>
                <div class="kpi-value"><?= number_format($k_mukellef) ?></div>
                <div class="kpi-sub">Mükellef kaydı</div>
            </div>
            <div class="kpi-icon green"><?= icon('building', 20) ?></div>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-head">
            <div>
                <div class="kpi-label">Bu Ay Tutar</div>
                <div class="kpi-value small"><?= fmt_tl($k_ay_tutar) ?></div>
                <div class="kpi-sub">Toplam: <?= fmt_tl($k_toplam) ?></div>
            </div>
            <div class="kpi-icon purple"><?= icon('chart', 20) ?></div>
        </div>
    </div>
</div>

<!-- ═══ 2 SÜTUN: SON FATURALAR + SAĞ PANEL ═══ -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
    <!-- Son Faturalar -->
    <div>
        <div class="card">
            <div class="card-head">
                <?= icon('clock') ?>
                <h3>Son Faturalar</h3>
                <a href="<?= SITE_URL ?>/fatura/liste.php" class="card-head-action">Tümünü Gör →</a>
            </div>
            <div class="card-body tight">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fatura No</th>
                            <th>Müşteri</th>
                            <th style="text-align:right">Tutar</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($son_faturalar)): ?>
                            <tr><td colspan="5">
                                <div class="table-empty">
                                    <?= icon('invoice', 40) ?>
                                    <h4>Henüz fatura yok</h4>
                                    <p>İlk faturanızı oluşturarak başlayın</p>
                                    <a href="<?= SITE_URL ?>/fatura/yeni.php" class="btn btn-primary btn-sm">
                                        <?= icon('plus') ?> İlk faturanı oluştur
                                    </a>
                                </div>
                            </td></tr>
                        <?php else: foreach ($son_faturalar as $f): ?>
                            <tr>
                                <td><a href="<?= SITE_URL ?>/fatura/detay.php?id=<?= $f['id'] ?>" style="font-family:monospace;font-size:12px;font-weight:600"><?= h($f['fatura_no']) ?></a></td>
                                <td><?= h($f['musteri'] ?? '—') ?></td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:600"><?= fmt_tl((float)$f['genel_toplam']) ?></td>
                                <td><?= fatura_durum_html($f['durum']) ?></td>
                                <td style="color:#94a3b8;font-size:12px"><?= fmt_datetime($f['created_at']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 7 GÜNLÜK TREND -->
        <?php if (!empty($son_7)):
            $max_c = max(array_column($son_7, 'c')) ?: 1;
            // Tüm 7 günü doldur (eksik günler 0)
            $days = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $days[$d] = 0;
            }
            foreach ($son_7 as $row) $days[$row['d']] = (int)$row['c'];
        ?>
        <div class="card">
            <div class="card-head">
                <?= icon('chart') ?>
                <h3>Son 7 Gün — Fatura Trendi</h3>
            </div>
            <div class="card-body">
                <div style="display:flex;align-items:flex-end;gap:10px;height:140px;padding:0 8px">
                    <?php foreach ($days as $gun => $c):
                        $pct = $max_c > 0 ? round(($c / $max_c) * 100) : 0;
                    ?>
                        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:8px">
                            <div style="flex:1;width:100%;display:flex;align-items:flex-end">
                                <div style="width:100%;height:<?= max($pct, 4) ?>%;background:linear-gradient(180deg,#ff6b00 0%,#ff8c3a 100%);border-radius:6px 6px 0 0;position:relative" title="<?= $c ?> fatura">
                                    <?php if ($c > 0): ?>
                                        <span style="position:absolute;top:-20px;left:50%;transform:translateX(-50%);font-size:11px;font-weight:700;color:#1a202c"><?= $c ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-size:11px;color:#94a3b8;font-weight:500"><?= date('d.m', strtotime($gun)) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sağ Sütun -->
    <div>
        <!-- Hızlı Eylemler -->
        <div class="card">
            <div class="card-head">
                <?= icon('zap') ?>
                <h3>Hızlı Eylemler</h3>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
                <a href="<?= SITE_URL ?>/fatura/yeni.php" class="btn btn-primary btn-block"><?= icon('plus') ?> Yeni Fatura</a>
                <a href="<?= SITE_URL ?>/musteri/duzenle.php?new=1" class="btn btn-outline btn-block"><?= icon('building') ?> Yeni Müşteri</a>
                <a href="<?= SITE_URL ?>/fatura/liste.php?durum=hazir" class="btn btn-outline btn-block"><?= icon('clock') ?> Hazır Faturalar</a>
            </div>
        </div>

        <!-- Durum Dağılımı -->
        <div class="card">
            <div class="card-head">
                <?= icon('chart-pie') ?>
                <h3>Durum Dağılımı</h3>
            </div>
            <div class="card-body">
                <?php if (empty($durum_dagilim)): ?>
                    <div class="text-center text-muted" style="padding:20px 0;font-size:13px">Henüz veri yok</div>
                <?php else: foreach ($durum_dagilim as $d):
                    $pct = $k_fatura > 0 ? round($d['c'] / $k_fatura * 100) : 0;
                ?>
                    <div style="margin-bottom:10px">
                        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px;align-items:center">
                            <?= fatura_durum_html($d['durum']) ?>
                            <span style="color:#64748b"><strong style="color:#1a202c"><?= $d['c'] ?></strong> · %<?= $pct ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="fill" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Sistem Sağlığı -->
        <div class="card">
            <div class="card-head">
                <?= icon('heart-pulse') ?>
                <h3>Sistem Sağlığı</h3>
            </div>
            <div class="card-body" style="font-size:13px;padding:12px 20px">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9">
                    <span style="color:#64748b">Portal</span>
                    <span class="badge badge-success"><?= icon('check') ?> v<?= h($portal_ver) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9">
                    <span style="color:#64748b">Kütüphane</span>
                    <span class="badge badge-info"><?= icon('file-code') ?> v<?= h($lib_ver) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9">
                    <span style="color:#64748b">PHP</span>
                    <span class="badge badge-secondary"><?= PHP_VERSION ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9">
                    <span style="color:#64748b">XAdES İmza</span>
                    <span class="badge badge-warning"><?= icon('clock') ?> v0.2'de</span>
                </div>
                <div style="padding:10px 0 4px">
                    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
                        <span style="color:#64748b">Disk Kullanımı</span>
                        <span style="font-weight:600">%<?= $disk_pct ?></span>
                    </div>
                    <div class="progress-bar <?= $disk_pct > 85 ? 'red' : ($disk_pct > 70 ? '' : 'green') ?>">
                        <div class="fill" style="width:<?= $disk_pct ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ SON AKTİVİTELER ═══ -->
<div class="card mt-3">
    <div class="card-head">
        <?= icon('activity') ?>
        <h3>Son Sistem Aktiviteleri</h3>
        <a href="<?= SITE_URL ?>/log/index.php" class="card-head-action">Tümünü Gör →</a>
    </div>
    <div class="card-body">
        <?php if (empty($son_log)): ?>
            <div class="text-center text-muted" style="padding:20px 0;font-size:13px">Henüz kayıt yok</div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($son_log as $log):
                $tip = 'ok';
                if (str_contains($log['olay'], 'fail') || str_contains($log['olay'], 'error')) $tip = 'err';
                elseif (str_contains($log['olay'], 'warn')) $tip = 'warn';
            ?>
            <div class="tl-item <?= $tip ?>">
                <div class="tl-time"><?= fmt_datetime($log['created_at']) ?></div>
                <div class="tl-title"><?= h($log['olay']) ?></div>
                <div class="tl-desc">
                    <strong><?= h($log['ad_soyad'] ?: $log['kullanici_adi'] ?: 'sistem') ?></strong>
                    <?php if ($log['detay']): ?> · <?= h($log['detay']) ?><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
