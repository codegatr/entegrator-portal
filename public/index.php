<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require();

// ═══ KPI sorguları ═══
$k_fatura = (int)$pdo->query("SELECT COUNT(*) FROM faturalar")->fetchColumn();
$k_ay     = (int)$pdo->query("SELECT COUNT(*) FROM faturalar WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetchColumn();
$k_taslak = (int)$pdo->query("SELECT COUNT(*) FROM faturalar WHERE durum='taslak'")->fetchColumn();
$k_mukellef = (int)$pdo->query("SELECT COUNT(*) FROM mukellefler WHERE aktif=1")->fetchColumn();
$k_toplam = (float)$pdo->query("SELECT COALESCE(SUM(genel_toplam),0) FROM faturalar WHERE durum NOT IN ('taslak','iptal')")->fetchColumn();
$k_ay_tutar = (float)$pdo->query("SELECT COALESCE(SUM(genel_toplam),0) FROM faturalar WHERE DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m') AND durum NOT IN ('taslak','iptal')")->fetchColumn();

// ═══ Son fatura durumları ═══
$durum_dagilim = $pdo->query("SELECT durum, COUNT(*) AS c FROM faturalar GROUP BY durum")->fetchAll();

// ═══ Son 10 fatura ═══
$son_faturalar = $pdo->query("
    SELECT f.*, m.unvan AS musteri
    FROM faturalar f
    LEFT JOIN mukellefler m ON m.id = f.mukellef_id
    ORDER BY f.id DESC LIMIT 10
")->fetchAll();

// ═══ Son 10 sistem log ═══
$son_log = $pdo->query("
    SELECT sl.*, k.kullanici_adi
    FROM sistem_log sl
    LEFT JOIN kullanicilar k ON k.id = sl.kullanici_id
    ORDER BY sl.id DESC LIMIT 10
")->fetchAll();

// ═══ Sistem sağlığı ═══
$disk_free = @disk_free_space(STORAGE_PATH);
$disk_total = @disk_total_space(STORAGE_PATH);
$disk_pct = ($disk_total > 0) ? round((1 - $disk_free / $disk_total) * 100) : 0;

render_header('Dashboard', 'dashboard');
?>

<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <div class="sub">Hoş geldin, <?= h(auth_user()['ad_soyad']) ?> — <?= date('d F Y, H:i') ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= SITE_URL ?>/fatura/yeni.php" class="btn btn-primary"><i class="fas fa-plus"></i> Yeni Fatura</a>
    </div>
</div>

<!-- ═══ KPI Kartları ═══ -->
<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-icon blue"><i class="fas fa-file-invoice"></i></div>
        <div class="kpi-label">Toplam Fatura</div>
        <div class="kpi-value"><?= number_format($k_fatura) ?></div>
        <div class="kpi-sub">Bu ay: <?= $k_ay ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-icon orange"><i class="fas fa-pen-ruler"></i></div>
        <div class="kpi-label">Taslak Fatura</div>
        <div class="kpi-value"><?= $k_taslak ?></div>
        <div class="kpi-sub"><?= $k_taslak > 0 ? 'Tamamlanmayı bekliyor' : 'Tümü işlendi' ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-icon green"><i class="fas fa-building"></i></div>
        <div class="kpi-label">Aktif Müşteri</div>
        <div class="kpi-value"><?= $k_mukellef ?></div>
        <div class="kpi-sub">Mükellef kaydı</div>
    </div>
    <div class="kpi">
        <div class="kpi-icon purple"><i class="fas fa-lira-sign"></i></div>
        <div class="kpi-label">Bu Ay Fatura Tutarı</div>
        <div class="kpi-value" style="font-size:20px"><?= fmt_tl($k_ay_tutar) ?></div>
        <div class="kpi-sub">Toplam: <?= fmt_tl($k_toplam) ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px">
    <!-- ═══ Son Faturalar ═══ -->
    <div class="card">
        <div class="card-h">
            <i class="fas fa-clock-rotate-left"></i> Son Faturalar
            <a href="<?= SITE_URL ?>/fatura/liste.php" style="margin-left:auto;font-size:12px;font-weight:normal">Tümünü Gör →</a>
        </div>
        <div class="table-wrap" style="border:none;border-radius:0">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Müşteri</th>
                        <th>Tutar</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($son_faturalar)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="table-empty">
                                    <i class="fas fa-file-invoice"></i>
                                    Henüz fatura yok.
                                    <div style="margin-top:10px"><a href="<?= SITE_URL ?>/fatura/yeni.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> İlk faturanı oluştur</a></div>
                                </div>
                            </td>
                        </tr>
                    <?php else: foreach ($son_faturalar as $f): ?>
                        <tr>
                            <td><a href="<?= SITE_URL ?>/fatura/detay.php?id=<?= $f['id'] ?>" style="font-family:monospace;font-size:12px"><?= h($f['fatura_no']) ?></a></td>
                            <td><?= h($f['musteri'] ?? '—') ?></td>
                            <td style="font-variant-numeric:tabular-nums"><?= fmt_tl((float)$f['genel_toplam']) ?></td>
                            <td><?= fatura_durum_html($f['durum']) ?></td>
                            <td style="color:#64748b;font-size:12px"><?= fmt_datetime($f['created_at']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══ Sağ sütun ═══ -->
    <div>
        <!-- Durum Dağılımı -->
        <div class="card">
            <div class="card-h"><i class="fas fa-chart-pie"></i> Durum Dağılımı</div>
            <div class="card-b">
                <?php if (empty($durum_dagilim)): ?>
                    <div style="color:#94a3b8;font-size:13px;text-align:center;padding:10px 0">Veri yok</div>
                <?php else: foreach ($durum_dagilim as $d):
                    $pct = $k_fatura > 0 ? round($d['c'] / $k_fatura * 100) : 0;
                ?>
                    <div style="margin-bottom:9px">
                        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
                            <span><?= fatura_durum_html($d['durum']) ?></span>
                            <span style="color:#64748b"><strong><?= $d['c'] ?></strong> (<?= $pct ?>%)</span>
                        </div>
                        <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden">
                            <div style="height:100%;background:#f6821f;width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Sistem Durumu -->
        <div class="card">
            <div class="card-h"><i class="fas fa-heart-pulse"></i> Sistem Durumu</div>
            <div class="card-b" style="font-size:13px">
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9">
                    <span>Portal</span>
                    <span><span class="badge badge-success"><i class="fas fa-check-circle"></i> v<?= h(ayar_get($pdo, 'portal_surumu')) ?></span></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9">
                    <span>Kütüphane</span>
                    <span><span class="badge badge-info"><i class="fas fa-code"></i> v<?= h(ayar_get($pdo, 'kutuphane_surumu')) ?></span></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9">
                    <span>XAdES İmza</span>
                    <span><span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> v0.2'de</span></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9">
                    <span>GİB Entegrasyon</span>
                    <span><span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> v0.3'te</span></span>
                </div>
                <div style="padding:6px 0">
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:3px">
                        <span>Disk kullanımı</span>
                        <span><?= $disk_pct ?>%</span>
                    </div>
                    <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden">
                        <div style="height:100%;background:<?= $disk_pct > 85 ? '#dc2626' : ($disk_pct > 70 ? '#f59e0b' : '#10b981') ?>;width:<?= $disk_pct ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Son Aktiviteler (timeline) ═══ -->
<div class="card" style="margin-top:16px">
    <div class="card-h">
        <i class="fas fa-clock-rotate-left"></i> Son Sistem Aktiviteleri
        <a href="<?= SITE_URL ?>/log/index.php" style="margin-left:auto;font-size:12px;font-weight:normal">Tümünü Gör →</a>
    </div>
    <div class="card-b">
        <?php if (empty($son_log)): ?>
            <div style="color:#94a3b8;font-size:13px;text-align:center;padding:10px 0">Henüz kayıt yok</div>
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
                    <?= h($log['kullanici_adi'] ?: 'sistem') ?>
                    <?php if ($log['detay']): ?> · <?= h($log['detay']) ?><?php endif; ?>
                    <?php if ($log['hedef']): ?> · <?= h($log['hedef']) ?><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
