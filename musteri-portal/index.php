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

// ═══ KPI SORGULARI (SADECE BU MÜŞTERİ) ═══
$toplam = (int)$pdo->prepare("SELECT COUNT(*) FROM faturalar WHERE mukellef_id=?")->execute([$mid]) ?: 0;
$q = $pdo->prepare("SELECT COUNT(*) FROM faturalar WHERE mukellef_id=?");
$q->execute([$mid]);
$toplam = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM faturalar WHERE mukellef_id=? AND DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m')");
$q->execute([$mid]);
$bu_ay = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM faturalar WHERE mukellef_id=? AND durum IN ('hazir','imzali','gonderildi')");
$q->execute([$mid]);
$bekleyen = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COALESCE(SUM(genel_toplam),0) FROM faturalar WHERE mukellef_id=? AND durum NOT IN ('iptal','taslak')");
$q->execute([$mid]);
$toplam_tutar = (float)$q->fetchColumn();

$q = $pdo->prepare("SELECT COALESCE(SUM(genel_toplam),0) FROM faturalar WHERE mukellef_id=? AND DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m') AND durum NOT IN ('iptal','taslak')");
$q->execute([$mid]);
$bu_ay_tutar = (float)$q->fetchColumn();

// Son 8 fatura
$q = $pdo->prepare("SELECT * FROM faturalar WHERE mukellef_id=? ORDER BY id DESC LIMIT 8");
$q->execute([$mid]);
$son_faturalar = $q->fetchAll();

// Durum dağılımı (sadece bu müşteri)
$q = $pdo->prepare("SELECT durum, COUNT(*) AS c FROM faturalar WHERE mukellef_id=? GROUP BY durum ORDER BY c DESC");
$q->execute([$mid]);
$durum_dagilim = $q->fetchAll();

// Selamlama
$saat = (int)date('H');
$selam = $saat < 5 ? 'İyi geceler' : ($saat < 12 ? 'Günaydın' : ($saat < 18 ? 'İyi günler' : 'İyi akşamlar'));

// Ziyaret logu
mp_audit($pdo, 'musteri.view_dashboard');

mp_render_header('Ana Sayfa', 'dashboard');
?>

<!-- HOŞGELDİN -->
<div class="mp-welcome">
    <div class="mp-welcome-content">
        <h2><?= $selam ?>, <?= h($user['ad_soyad'] ?: $user['user']) ?> 👋</h2>
        <p>CODEGA'nın sizin adınıza düzenlediği e-Faturaları buradan takip edebilirsiniz.</p>
        <div class="mp-welcome-firm">
            <?= mp_icon('building', 14) ?>
            <?= h($user['unvan']) ?>
        </div>
    </div>
</div>

<!-- KPI -->
<div class="mp-kpi-grid">
    <div class="mp-kpi">
        <div class="mp-kpi-head">
            <div>
                <div class="mp-kpi-label">Toplam Fatura</div>
                <div class="mp-kpi-value"><?= number_format($toplam) ?></div>
                <div class="mp-kpi-sub">Bu ay: <strong><?= $bu_ay ?></strong></div>
            </div>
            <div class="mp-kpi-icon blue"><?= mp_icon('invoice', 20) ?></div>
        </div>
    </div>

    <div class="mp-kpi">
        <div class="mp-kpi-head">
            <div>
                <div class="mp-kpi-label">İşlem Bekleyen</div>
                <div class="mp-kpi-value"><?= number_format($bekleyen) ?></div>
                <div class="mp-kpi-sub"><?= $bekleyen > 0 ? 'Yanıtlanmayı bekliyor' : 'Tüm faturalar kapalı' ?></div>
            </div>
            <div class="mp-kpi-icon orange"><?= mp_icon('clock', 20) ?></div>
        </div>
    </div>

    <div class="mp-kpi">
        <div class="mp-kpi-head">
            <div>
                <div class="mp-kpi-label">Bu Ay Tutar</div>
                <div class="mp-kpi-value small"><?= fmt_tl($bu_ay_tutar) ?></div>
                <div class="mp-kpi-sub">Toplam: <?= fmt_tl($toplam_tutar) ?></div>
            </div>
            <div class="mp-kpi-icon green"><?= mp_icon('chart', 20) ?></div>
        </div>
    </div>

    <div class="mp-kpi">
        <div class="mp-kpi-head">
            <div>
                <div class="mp-kpi-label">Portal Durumu</div>
                <div class="mp-kpi-value small" style="color:#059669;font-size:16px"><?= mp_icon('check-circle', 20) ?> Aktif</div>
                <div class="mp-kpi-sub">Son giriş: <?= date('d.m.Y H:i') ?></div>
            </div>
            <div class="mp-kpi-icon purple"><?= mp_icon('shield', 20) ?></div>
        </div>
    </div>
</div>

<!-- SON FATURALAR -->
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
                        <th style="text-align:right">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($son_faturalar)): ?>
                        <tr><td colspan="5">
                            <div class="mp-table-empty">
                                <?= mp_icon('invoice', 48) ?>
                                <h4>Henüz fatura yok</h4>
                                <p>Adınıza düzenlenmiş bir fatura bulunmuyor</p>
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
                            <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:600">
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
                                    <?= mp_icon('eye', 13) ?> Görüntüle
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($durum_dagilim)): ?>
<!-- DURUM DAĞILIMI + FİRMA BİLGİLERİ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="mp-card">
        <div class="mp-card-head">
            <?= mp_icon('chart') ?>
            <h3>Faturalarımın Durumu</h3>
        </div>
        <div class="mp-card-body">
            <?php foreach ($durum_dagilim as $d):
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
                <div style="margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;font-size:13px">
                        <span style="color:#475569;font-weight:500"><?= $label_map[$d['durum']] ?? $d['durum'] ?></span>
                        <span style="color:#94a3b8"><strong style="color:#0f172a"><?= $d['c'] ?></strong> · %<?= $pct ?></span>
                    </div>
                    <div style="height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden">
                        <div style="height:100%;background:<?= $color ?>;width:<?= $pct ?>%;border-radius:4px;transition:width .3s"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mp-card">
        <div class="mp-card-head">
            <?= mp_icon('building') ?>
            <h3>Firma Bilgileriniz</h3>
        </div>
        <div class="mp-card-body">
            <?php
            $firma = $pdo->prepare("SELECT * FROM mukellefler WHERE id=?");
            $firma->execute([$mid]);
            $f = $firma->fetch();
            ?>
            <div style="font-size:13.5px">
                <div style="padding:8px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span style="color:#64748b">Ünvan</span>
                    <strong><?= h($f['unvan']) ?></strong>
                </div>
                <div style="padding:8px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span style="color:#64748b"><?= $f['vkn_tip'] ?></span>
                    <strong style="font-family:monospace"><?= h($f['vkn_tckn']) ?></strong>
                </div>
                <?php if ($f['vergi_dairesi']): ?>
                <div style="padding:8px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span style="color:#64748b">Vergi Dairesi</span>
                    <strong><?= h($f['vergi_dairesi']) ?></strong>
                </div>
                <?php endif; ?>
                <?php if ($f['il']): ?>
                <div style="padding:8px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                    <span style="color:#64748b">Şehir</span>
                    <strong><?= h($f['il']) ?><?= $f['ilce'] ? ' / '.h($f['ilce']) : '' ?></strong>
                </div>
                <?php endif; ?>
                <?php if ($f['email']): ?>
                <div style="padding:8px 0;display:flex;justify-content:space-between">
                    <span style="color:#64748b">E-posta</span>
                    <strong><?= h($f['email']) ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;font-size:12px;color:#94a3b8">
                Bilgileriniz yanlış mı? <a href="mailto:<?= h(CONTACT_EMAIL) ?>" style="color:#0b5cff">CODEGA'yla iletişime geçin</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php mp_render_footer(); ?>
