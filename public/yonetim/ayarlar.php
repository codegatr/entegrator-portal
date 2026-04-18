<?php
require __DIR__ . '/../../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('admin');

// İstatistikler
$stat = [
    'kullanici'      => (int)$pdo->query("SELECT COUNT(*) FROM kullanicilar WHERE aktif=1")->fetchColumn(),
    'mukellef'       => (int)$pdo->query("SELECT COUNT(*) FROM mukellefler WHERE aktif=1")->fetchColumn(),
    'fatura'         => (int)$pdo->query("SELECT COUNT(*) FROM faturalar")->fetchColumn(),
    'fatura_hazir'   => (int)$pdo->query("SELECT COUNT(*) FROM faturalar WHERE durum='hazir'")->fetchColumn(),
    'sistem_log'     => (int)$pdo->query("SELECT COUNT(*) FROM sistem_log")->fetchColumn(),
    'son_fatura_no'  => ayar_get($pdo, 'fatura_no_son_sira', '0'),
    'son_fatura_yil' => ayar_get($pdo, 'fatura_no_son_yil', date('Y')),
];

// XML depolama bilgisi
$xml_dir_size = 0;
$xml_count = 0;
if (is_dir(XML_PATH)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(XML_PATH));
    foreach ($it as $f) {
        if ($f->isFile()) { $xml_dir_size += $f->getSize(); $xml_count++; }
    }
}

$disk_free = @disk_free_space(STORAGE_PATH);
$disk_total = @disk_total_space(STORAGE_PATH);

render_header('Sistem Ayarları', 'yonetim');
?>

<div class="page-head">
    <div>
        <h1>Sistem Ayarları</h1>
        <div class="sub">Portal yapılandırması ve sistem sağlığı</div>
    </div>
    <div class="page-actions">
        <a href="<?= SITE_URL ?>/yonetim/kullanici.php" class="btn btn-ghost"><i class="fas fa-users"></i> Kullanıcılar</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <!-- Firma Bilgileri -->
    <div class="card">
        <div class="card-h"><i class="fas fa-building"></i> Firma Bilgileri (config.php)</div>
        <div class="card-b" style="font-size:13px">
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Ünvan</span><strong><?= h(FIRMA_ADI) ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">VKN</span><strong style="font-family:monospace"><?= h(FIRMA_VKN) ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Vergi Dairesi</span><strong><?= h(FIRMA_VERGI_DAIRESI) ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Adres</span><strong><?= h(FIRMA_ADRES . ', ' . FIRMA_ILCE . ' / ' . FIRMA_IL) ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">E-posta</span><strong><?= h(FIRMA_EMAIL) ?></strong>
            </div>
            <div style="padding:6px 0;display:flex;justify-content:space-between">
                <span style="color:#64748b">Fatura Seri Kodu</span><strong style="font-family:monospace"><?= h(FATURA_SERI_KODU) ?></strong>
            </div>
            <div class="alert alert-info" style="margin-top:14px;font-size:12.5px">
                <i class="fas fa-info-circle"></i>
                Bu bilgiler <code>config.php</code> dosyasında tanımlıdır. Multi-tenant'a geçişte tabloya taşınacak.
            </div>
        </div>
    </div>

    <!-- İstatistikler -->
    <div class="card">
        <div class="card-h"><i class="fas fa-chart-bar"></i> Sistem İstatistikleri</div>
        <div class="card-b" style="font-size:13px">
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Aktif Kullanıcı</span><strong><?= number_format($stat['kullanici']) ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Aktif Müşteri (Mükellef)</span><strong><?= number_format($stat['mukellef']) ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Toplam Fatura</span><strong><?= number_format($stat['fatura']) ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Hazır (imza bekliyor)</span>
                <strong><?= $stat['fatura_hazir'] ?> <?= $stat['fatura_hazir']>0?'<span class="badge badge-warning" style="margin-left:4px">bekleyen</span>':'' ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Sistem Log Kaydı</span><strong><?= number_format($stat['sistem_log']) ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Yılın Son Fatura Sırası</span><strong style="font-family:monospace"><?= h($stat['son_fatura_no']) ?> / <?= h($stat['son_fatura_yil']) ?></strong>
            </div>
            <div style="padding:6px 0;display:flex;justify-content:space-between">
                <span style="color:#64748b">Depodaki XML Sayısı</span><strong><?= number_format($xml_count) ?> (<?= round($xml_dir_size/1024, 1) ?> KB)</strong>
            </div>
        </div>
    </div>

    <!-- Sürüm Bilgileri -->
    <div class="card">
        <div class="card-h"><i class="fas fa-code-branch"></i> Sürüm Bilgileri</div>
        <div class="card-b" style="font-size:13px">
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Portal</span>
                <span><span class="badge badge-success"><i class="fas fa-check"></i> v<?= h(ayar_get($pdo, 'portal_surumu', '1.0.0')) ?></span></span>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">entegrator-gib Kütüphane</span>
                <span><span class="badge badge-info"><i class="fas fa-code"></i> v<?= h(ayar_get($pdo, 'kutuphane_surumu', '0.1.0-alpha')) ?></span></span>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">PHP</span>
                <strong><?= PHP_VERSION ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">XAdES İmza Motoru</span>
                <span><span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> v0.2'de gelecek</span></span>
            </div>
            <div style="padding:6px 0;display:flex;justify-content:space-between">
                <span style="color:#64748b">GİB Entegrasyon</span>
                <span><span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> v0.3'te gelecek</span></span>
            </div>
        </div>
    </div>

    <!-- Disk + Depo -->
    <div class="card">
        <div class="card-h"><i class="fas fa-hard-drive"></i> Disk ve Depolama</div>
        <div class="card-b" style="font-size:13px">
            <?php if ($disk_total > 0):
                $disk_used = $disk_total - $disk_free;
                $disk_pct = round($disk_used / $disk_total * 100);
            ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:4px">
                    <span>Disk Kullanımı</span>
                    <span><?= round($disk_used/1024/1024/1024, 1) ?> GB / <?= round($disk_total/1024/1024/1024, 1) ?> GB (%<?= $disk_pct ?>)</span>
                </div>
                <div style="height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden">
                    <div style="height:100%;background:<?= $disk_pct>85?'#dc2626':($disk_pct>70?'#f59e0b':'#10b981') ?>;width:<?= $disk_pct ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">XML Depo</span><strong><?= h(XML_PATH) ?></strong>
            </div>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between">
                <span style="color:#64748b">Sertifika Depo</span><strong><?= h(CERT_PATH) ?></strong>
            </div>
            <div style="padding:6px 0;display:flex;justify-content:space-between">
                <span style="color:#64748b">Yedek Depo</span><strong><?= h(BACKUP_PATH) ?></strong>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
