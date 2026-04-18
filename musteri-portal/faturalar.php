<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

mp_auth_require();
$user = mp_auth_user();
$mid = (int)$user['mukellef_id'];

// Filtreler
$durum_filtre = $_GET['durum'] ?? '';
$yil_filtre = (int)($_GET['yil'] ?? 0);
$q_filtre = trim($_GET['q'] ?? '');

$valid_durumlar = ['taslak','hazir','imzali','gonderildi','kabul','red','iptal'];
$where = 'mukellef_id=?';
$par = [$mid];

if (in_array($durum_filtre, $valid_durumlar, true)) {
    $where .= ' AND durum=?';
    $par[] = $durum_filtre;
}
if ($yil_filtre >= 2020 && $yil_filtre <= (int)date('Y')) {
    $where .= ' AND YEAR(duzenleme_tarihi)=?';
    $par[] = $yil_filtre;
}
if ($q_filtre !== '') {
    $where .= ' AND fatura_no LIKE ?';
    $par[] = '%' . $q_filtre . '%';
}

// Sayım
$cnt = $pdo->prepare("SELECT COUNT(*) FROM faturalar WHERE $where");
$cnt->execute($par);
$total = (int)$cnt->fetchColumn();

// Liste
$st = $pdo->prepare("SELECT * FROM faturalar WHERE $where ORDER BY duzenleme_tarihi DESC, id DESC LIMIT 100");
$st->execute($par);
$faturalar = $st->fetchAll();

// Yıllar listesi (filtre için)
$yil_st = $pdo->prepare("SELECT DISTINCT YEAR(duzenleme_tarihi) AS y FROM faturalar WHERE mukellef_id=? ORDER BY y DESC");
$yil_st->execute([$mid]);
$yillar = $yil_st->fetchAll(PDO::FETCH_COLUMN);

mp_audit($pdo, 'musteri.view_faturalar');

mp_render_header('Faturalarım', 'faturalar');
?>

<div class="mp-page-head">
    <div>
        <h1>Faturalarım</h1>
        <div class="sub"><?= $total ?> kayıt listeleniyor</div>
    </div>
</div>

<!-- Filtreler -->
<div class="mp-card">
    <div class="mp-card-body" style="padding:16px 22px">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="mp-fg" style="margin:0;flex:1;min-width:200px">
                <label>Fatura No Ara</label>
                <input type="text" name="q" value="<?= h($q_filtre) ?>" placeholder="Örn: COD2026000000001">
            </div>
            <div class="mp-fg" style="margin:0;width:180px">
                <label>Durum</label>
                <select name="durum">
                    <option value="">Tümü</option>
                    <?php foreach (['taslak'=>'Taslak','hazir'=>'Hazırlanıyor','imzali'=>'İmzalandı','gonderildi'=>'Gönderildi','kabul'=>'Kabul Edildi','red'=>'Reddedildi','iptal'=>'İptal'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $durum_filtre===$k?'selected':'' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mp-fg" style="margin:0;width:140px">
                <label>Yıl</label>
                <select name="yil">
                    <option value="">Tümü</option>
                    <?php foreach ($yillar as $y): ?>
                        <option value="<?= $y ?>" <?= (int)$yil_filtre===(int)$y?'selected':'' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="mp-btn mp-btn-primary">
                <?= mp_icon('search', 14) ?> Filtrele
            </button>
            <?php if ($durum_filtre || $yil_filtre || $q_filtre): ?>
                <a href="<?= SITE_URL ?>/musteri-portal/faturalar.php" class="mp-btn mp-btn-ghost">Temizle</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Liste -->
<div class="mp-card">
    <div class="mp-card-body tight">
        <div class="mp-table-wrap">
            <table class="mp-table">
                <thead>
                    <tr>
                        <th>Fatura No</th>
                        <th>Düzenleme Tarihi</th>
                        <th>Profil</th>
                        <th style="text-align:right">Matrah</th>
                        <th style="text-align:right">KDV</th>
                        <th style="text-align:right">Toplam</th>
                        <th>Durum</th>
                        <th style="text-align:right"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faturalar)): ?>
                        <tr><td colspan="8">
                            <div class="mp-table-empty">
                                <?= mp_icon('invoice', 48) ?>
                                <h4><?= ($durum_filtre || $yil_filtre || $q_filtre) ? 'Sonuç bulunamadı' : 'Henüz fatura yok' ?></h4>
                                <p><?= ($durum_filtre || $yil_filtre || $q_filtre) ? 'Filtre kriterlerinize uygun fatura bulunmuyor' : 'Adınıza düzenlenmiş fatura bulunmuyor' ?></p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($faturalar as $f):
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
                            'TEMELFATURA' => 'Temel',
                            'TICARIFATURA' => 'Ticari',
                            'EARSIVFATURA' => 'e-Arşiv',
                            'IHRACAT' => 'İhracat',
                        ];
                    ?>
                        <tr>
                            <td>
                                <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $f['id'] ?>" style="font-family:monospace;font-size:12.5px;font-weight:600;color:#0a2540">
                                    <?= h($f['fatura_no']) ?>
                                </a>
                            </td>
                            <td style="color:#475569;font-size:13px;white-space:nowrap">
                                <?= date('d.m.Y', strtotime($f['duzenleme_tarihi'])) ?>
                            </td>
                            <td style="font-size:12px">
                                <span class="mp-badge mp-badge-secondary"><?= $profil_map[$f['profil']] ?? $f['profil'] ?></span>
                            </td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums;color:#64748b"><?= fmt_tl((float)$f['matrah']) ?></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums;color:#64748b"><?= fmt_tl((float)$f['kdv_toplam']) ?></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700;color:#0f172a">
                                <?= fmt_tl((float)$f['genel_toplam']) ?>
                            </td>
                            <td>
                                <span class="mp-badge mp-badge-<?= $durum_badge[0] ?>">
                                    <?= mp_icon($durum_badge[2], 11) ?>
                                    <?= $durum_badge[1] ?>
                                </span>
                            </td>
                            <td style="text-align:right;white-space:nowrap">
                                <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $f['id'] ?>" class="mp-btn mp-btn-ghost mp-btn-sm">
                                    <?= mp_icon('eye', 13) ?>
                                </a>
                                <?php if ($f['xml_path'] && in_array($f['durum'], ['imzali','gonderildi','kabul'])): ?>
                                    <a href="<?= SITE_URL ?>/musteri-portal/fatura-indir.php?id=<?= $f['id'] ?>&tip=xml" class="mp-btn mp-btn-ghost mp-btn-sm" title="XML indir">
                                        <?= mp_icon('download', 13) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($total > 100): ?>
    <div class="mp-alert mp-alert-info">
        <?= mp_icon('info') ?>
        <div>
            Son <strong>100 fatura</strong> gösteriliyor. Daha eski kayıtlar için filtre kullanın.
        </div>
    </div>
<?php endif; ?>

<?php mp_render_footer(); ?>
