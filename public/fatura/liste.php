<?php
require __DIR__ . '/../../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require();

$q       = trim($_GET['q'] ?? '');
$durum   = $_GET['durum'] ?? '';
$tarih1  = $_GET['t1'] ?? '';
$tarih2  = $_GET['t2'] ?? '';

$where = '1=1'; $par = [];
if ($q !== '') {
    $where .= ' AND (f.fatura_no LIKE ? OR m.unvan LIKE ? OR m.vkn_tckn LIKE ?)';
    $par[] = "%$q%"; $par[] = "%$q%"; $par[] = "%$q%";
}
if ($durum !== '' && in_array($durum, ['taslak','hazir','imzali','gonderildi','kabul','red','iptal'], true)) {
    $where .= ' AND f.durum=?'; $par[] = $durum;
}
if ($tarih1) { $where .= ' AND f.duzenleme_tarihi>=?'; $par[] = $tarih1; }
if ($tarih2) { $where .= ' AND f.duzenleme_tarihi<=?'; $par[] = $tarih2; }

$sql = "SELECT f.*, m.unvan AS musteri, m.vkn_tckn AS musteri_vkn
        FROM faturalar f
        LEFT JOIN mukellefler m ON m.id = f.mukellef_id
        WHERE $where
        ORDER BY f.id DESC
        LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($par);
$rows = $st->fetchAll();

$cnt_q = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(f.genel_toplam),0) FROM faturalar f LEFT JOIN mukellefler m ON m.id=f.mukellef_id WHERE $where");
$cnt_q->execute($par);
[$total, $tot_tutar] = $cnt_q->fetch(PDO::FETCH_NUM);

render_header('Faturalar', 'fatura');
?>

<div class="page-head">
    <div>
        <h1>Faturalar</h1>
        <div class="sub"><?= number_format((int)$total) ?> kayıt · Toplam: <?= fmt_tl((float)$tot_tutar) ?></div>
    </div>
    <div class="page-actions">
        <a href="<?= SITE_URL ?>/fatura/yeni.php" class="btn btn-primary"><i class="fas fa-plus"></i> Yeni Fatura</a>
    </div>
</div>

<form class="filters" method="GET">
    <label>Ara:</label>
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="No, müşteri, VKN..." style="min-width:200px">

    <label>Durum:</label>
    <select name="durum">
        <option value="">Tümü</option>
        <?php foreach (['taslak','hazir','imzali','gonderildi','kabul','red','iptal'] as $d): ?>
            <option value="<?= $d ?>" <?= $durum===$d?'selected':'' ?>><?= ucfirst($d) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Tarih:</label>
    <input type="date" name="t1" value="<?= h($tarih1) ?>">
    <span style="color:#94a3b8">→</span>
    <input type="date" name="t2" value="<?= h($tarih2) ?>">

    <button type="submit" class="btn btn-ghost btn-sm"><i class="fas fa-filter"></i> Filtrele</button>
    <?php if ($q || $durum || $tarih1 || $tarih2): ?>
        <a href="<?= SITE_URL ?>/fatura/liste.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Temizle</a>
    <?php endif; ?>
</form>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Fatura No</th>
                <th>Müşteri</th>
                <th>Tarih</th>
                <th style="text-align:right">Matrah</th>
                <th style="text-align:right">KDV</th>
                <th style="text-align:right">Toplam</th>
                <th>Durum</th>
                <th>Tür</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9"><div class="table-empty">
                    <i class="fas fa-file-invoice"></i>
                    <strong>Fatura bulunamadı</strong>
                    <div style="margin-top:8px;font-size:12.5px"><?= $q||$durum||$tarih1||$tarih2 ? 'Filtreleri temizleyip deneyin' : 'Henüz hiç fatura yok' ?></div>
                    <?php if (!$q && !$durum): ?>
                        <div style="margin-top:10px"><a href="<?= SITE_URL ?>/fatura/yeni.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> İlk fatura</a></div>
                    <?php endif; ?>
                </div></td></tr>
            <?php else: foreach ($rows as $f): ?>
                <tr>
                    <td><a href="<?= SITE_URL ?>/fatura/detay.php?id=<?= $f['id'] ?>" style="font-family:monospace;font-size:12px;font-weight:600"><?= h($f['fatura_no']) ?></a></td>
                    <td>
                        <strong><?= h($f['musteri'] ?? '—') ?></strong>
                        <?php if ($f['musteri_vkn']): ?><div style="font-size:11px;color:#94a3b8;font-family:monospace"><?= h($f['musteri_vkn']) ?></div><?php endif; ?>
                    </td>
                    <td style="color:#64748b;white-space:nowrap"><?= fmt_date($f['duzenleme_tarihi']) ?></td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums"><?= fmt_tl((float)$f['matrah']) ?></td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;color:#64748b"><?= fmt_tl((float)$f['kdv_toplam']) ?></td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700"><?= fmt_tl((float)$f['genel_toplam']) ?></td>
                    <td><?= fatura_durum_html($f['durum']) ?></td>
                    <td style="font-size:11px;color:#64748b"><?= h($f['profil']) ?><br><?= h($f['tipi']) ?></td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="<?= SITE_URL ?>/fatura/detay.php?id=<?= $f['id'] ?>" class="btn btn-ghost btn-sm" title="Detay"><i class="fas fa-eye"></i></a>
                        <?php if ($f['xml_path']): ?>
                            <a href="<?= SITE_URL ?>/fatura/indir.php?id=<?= $f['id'] ?>" class="btn btn-ghost btn-sm" title="XML indir"><i class="fas fa-download"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php render_footer(); ?>
