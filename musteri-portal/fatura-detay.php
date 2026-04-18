<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

mp_auth_require();
$user = mp_auth_user();
$mid = (int)$user['mukellef_id'];

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . SITE_URL . '/musteri-portal/faturalar.php');
    exit;
}

// ⚠️ GÜVENLİK: Bu faturaya erişimi var mı?
if (!mp_fatura_ait_mi($pdo, $id, $mid)) {
    mp_audit($pdo, 'musteri.fatura_denied', "fatura_id=$id");
    http_response_code(404);
    mp_render_header('Fatura Bulunamadı', '');
    echo '<div class="mp-alert mp-alert-danger">';
    echo mp_icon('x-circle') . '<div>Bu fatura bulunamadı veya erişim yetkiniz yok.</div>';
    echo '</div>';
    echo '<a href="' . SITE_URL . '/musteri-portal/faturalar.php" class="mp-btn mp-btn-primary">← Faturalara Dön</a>';
    mp_render_footer();
    exit;
}

$q = $pdo->prepare("SELECT * FROM faturalar WHERE id=?");
$q->execute([$id]);
$f = $q->fetch();

// Satırlar
$st = $pdo->prepare("SELECT * FROM fatura_satirlari WHERE fatura_id=? ORDER BY sira ASC");
$st->execute([$id]);
$satirlar = $st->fetchAll();

// Fatura durum geçmişi
$log = $pdo->prepare("SELECT * FROM fatura_log WHERE fatura_id=? ORDER BY id DESC LIMIT 10");
$log->execute([$id]);
$log_kayitlari = $log->fetchAll();

mp_audit($pdo, 'musteri.view_fatura', "fatura_id=$id no={$f['fatura_no']}");

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

$profil_map = [
    'TEMELFATURA' => 'Temel Fatura',
    'TICARIFATURA' => 'Ticari Fatura',
    'EARSIVFATURA' => 'e-Arşiv Fatura',
    'IHRACAT' => 'İhracat Faturası',
];
$tip_map = [
    'SATIS' => 'Satış',
    'IADE' => 'İade',
    'TEVKIFAT' => 'Tevkifat',
    'ISTISNA' => 'İstisna',
    'OZELMATRAH' => 'Özel Matrah',
    'IHRACKAYITLI' => 'İhraç Kayıtlı',
];

mp_render_header('Fatura: ' . $f['fatura_no'], 'faturalar');
?>

<div class="mp-page-head">
    <a href="<?= SITE_URL ?>/musteri-portal/faturalar.php" class="mp-btn mp-btn-ghost">← Faturalara Dön</a>
    <div style="margin-left:auto;display:flex;gap:8px">
        <?php if ($f['xml_path'] && in_array($f['durum'], ['imzali','gonderildi','kabul'])): ?>
            <a href="<?= SITE_URL ?>/musteri-portal/fatura-indir.php?id=<?= $f['id'] ?>&tip=xml" class="mp-btn mp-btn-outline">
                <?= mp_icon('file-code', 14) ?> XML İndir
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ANA BİLGİLER -->
<div class="mp-invoice-header">
    <div class="mp-invoice-header-grid">
        <div>
            <div style="font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:4px">Fatura No</div>
            <div class="mp-invoice-no"><?= h($f['fatura_no']) ?></div>
            <div style="font-size:12.5px;color:#94a3b8;font-family:monospace;margin-top:3px">ETTN: <?= h($f['ettn']) ?></div>

            <div style="margin-top:16px">
                <span class="mp-badge mp-badge-<?= $durum_badge[0] ?>" style="font-size:13px;padding:5px 12px">
                    <?= mp_icon($durum_badge[2], 12) ?>
                    <?= $durum_badge[1] ?>
                </span>
                <span class="mp-badge mp-badge-secondary" style="margin-left:6px;font-size:12px">
                    <?= $profil_map[$f['profil']] ?? $f['profil'] ?>
                </span>
                <span class="mp-badge mp-badge-secondary" style="margin-left:6px;font-size:12px">
                    <?= $tip_map[$f['tipi']] ?? $f['tipi'] ?>
                </span>
            </div>

            <dl class="mp-invoice-meta">
                <dt>Düzenleme Tarihi</dt>
                <dd><?= date('d F Y', strtotime($f['duzenleme_tarihi'])) ?><?= $f['duzenleme_saati'] ? ' — ' . date('H:i', strtotime($f['duzenleme_saati'])) : '' ?></dd>

                <dt>Para Birimi</dt>
                <dd><?= h($f['para_birimi']) ?></dd>

                <?php if ($f['gib_referans']): ?>
                    <dt>GİB Referans</dt>
                    <dd style="font-family:monospace;font-size:12px"><?= h($f['gib_referans']) ?></dd>
                <?php endif; ?>

                <?php if ($f['imza_tarihi']): ?>
                    <dt>İmza Tarihi</dt>
                    <dd><?= fmt_datetime($f['imza_tarihi']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <div style="padding:20px;background:#f8fafc;border-radius:12px">
            <div style="font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:10px">TUTARLAR</div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13.5px">
                <span style="color:#64748b">Matrah</span>
                <strong style="font-variant-numeric:tabular-nums"><?= fmt_tl((float)$f['matrah']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13.5px">
                <span style="color:#64748b">KDV Toplam</span>
                <strong style="font-variant-numeric:tabular-nums"><?= fmt_tl((float)$f['kdv_toplam']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:12px 0 4px;font-size:17px">
                <span style="color:#0a2540;font-weight:700">Genel Toplam</span>
                <strong style="font-variant-numeric:tabular-nums;color:#0b5cff;font-size:20px"><?= fmt_tl((float)$f['genel_toplam']) ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- FATURA SATIRLARI -->
<div class="mp-card">
    <div class="mp-card-head">
        <?= mp_icon('invoice') ?>
        <h3>Fatura Satırları (<?= count($satirlar) ?>)</h3>
    </div>
    <div class="mp-card-body tight">
        <div class="mp-table-wrap">
            <table class="mp-table">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Ürün / Hizmet</th>
                        <th style="text-align:right">Miktar</th>
                        <th style="text-align:right">Birim Fiyat</th>
                        <th style="text-align:right">İskonto</th>
                        <th style="text-align:right">Matrah</th>
                        <th style="text-align:center">KDV</th>
                        <th style="text-align:right">Toplam</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($satirlar as $s): ?>
                        <tr>
                            <td style="color:#94a3b8;font-weight:600"><?= (int)$s['sira'] ?></td>
                            <td>
                                <strong><?= h($s['urun_adi']) ?></strong>
                                <?php if ($s['aciklama']): ?>
                                    <div style="font-size:12px;color:#64748b;margin-top:3px"><?= h($s['aciklama']) ?></div>
                                <?php endif; ?>
                                <?php if ($s['urun_kodu']): ?>
                                    <div style="font-size:11px;color:#94a3b8;font-family:monospace;margin-top:2px">Kod: <?= h($s['urun_kodu']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums">
                                <?= number_format((float)$s['miktar'], 2, ',', '.') ?>
                                <span style="color:#94a3b8;font-size:11px"><?= h($s['birim_kodu']) ?></span>
                            </td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= fmt_tl((float)$s['birim_fiyat']) ?></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums;color:<?= $s['iskonto']>0?'#dc2626':'#94a3b8' ?>">
                                <?= (float)$s['iskonto'] > 0 ? fmt_tl((float)$s['iskonto']) : '—' ?>
                            </td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= fmt_tl((float)$s['matrah']) ?></td>
                            <td style="text-align:center">
                                <span class="mp-badge mp-badge-secondary" style="font-size:11px">%<?= (float)$s['kdv_oran'] ?></span>
                                <div style="font-size:11px;color:#64748b;margin-top:2px"><?= fmt_tl((float)$s['kdv_tutar']) ?></div>
                            </td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700"><?= fmt_tl((float)$s['satir_toplam']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($f['notlar']): ?>
<div class="mp-card">
    <div class="mp-card-head">
        <?= mp_icon('info') ?>
        <h3>Notlar</h3>
    </div>
    <div class="mp-card-body">
        <div style="white-space:pre-wrap;font-size:13.5px;line-height:1.6;color:#475569"><?= h($f['notlar']) ?></div>
    </div>
</div>
<?php endif; ?>

<!-- FATURA LOG -->
<?php if (!empty($log_kayitlari)): ?>
<div class="mp-card">
    <div class="mp-card-head">
        <?= mp_icon('clock') ?>
        <h3>Durum Geçmişi</h3>
    </div>
    <div class="mp-card-body">
        <?php foreach ($log_kayitlari as $l): ?>
            <div style="padding:10px 0;border-bottom:1px solid #f1f5f9;display:flex;gap:14px;align-items:flex-start">
                <div style="width:8px;height:8px;border-radius:50%;background:#0b5cff;margin-top:7px;flex-shrink:0"></div>
                <div style="flex:1">
                    <div style="font-size:13px;font-weight:600">
                        <?php if ($l['onceki_durum']): ?>
                            <span style="color:#94a3b8"><?= h($l['onceki_durum']) ?></span> → <?= h($l['yeni_durum']) ?>
                        <?php else: ?>
                            <?= h($l['yeni_durum']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($l['aciklama']): ?>
                        <div style="font-size:12.5px;color:#64748b;margin-top:2px"><?= h($l['aciklama']) ?></div>
                    <?php endif; ?>
                    <div style="font-size:11px;color:#94a3b8;margin-top:3px"><?= fmt_datetime($l['created_at']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php mp_render_footer(); ?>
