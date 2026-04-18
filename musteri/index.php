<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require();

$q = trim($_GET['q'] ?? '');
$where = '1=1'; $par = [];
if ($q !== '') {
    $where .= ' AND (unvan LIKE ? OR vkn_tckn LIKE ?)';
    $par[] = '%'.$q.'%'; $par[] = '%'.$q.'%';
}

$total = (int)$pdo->query("SELECT COUNT(*) FROM mukellefler WHERE $where", $par)->fetchColumn();
// PDO'da hazırlanmış kullanımı:
$st = $pdo->prepare("SELECT * FROM mukellefler WHERE $where ORDER BY unvan LIMIT 200");
$st->execute($par);
$rows = $st->fetchAll();

$cnt_q = $pdo->prepare("SELECT COUNT(*) FROM mukellefler WHERE $where");
$cnt_q->execute($par);
$total = (int)$cnt_q->fetchColumn();

render_header('Müşteriler', 'musteri');
?>

<div class="page-head">
    <div>
        <h1>Müşteriler (Mükellefler)</h1>
        <div class="sub"><?= $total ?> kayıt</div>
    </div>
    <div class="page-actions">
        <form method="GET" style="display:flex;gap:8px">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Ünvan veya VKN ara..." style="padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
            <button type="submit" class="btn btn-ghost"><i class="fas fa-search"></i></button>
        </form>
        <a href="<?= SITE_URL ?>/musteri/duzenle.php?new=1" class="btn btn-primary"><i class="fas fa-plus"></i> Yeni Müşteri</a>
    </div>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Ünvan</th>
                <th>VKN/TCKN</th>
                <th>Vergi Dairesi</th>
                <th>Şehir</th>
                <th>İletişim</th>
                <th>Durum</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7"><div class="table-empty">
                    <i class="fas fa-building"></i>
                    <strong>Henüz müşteri yok</strong>
                    <div style="margin-top:10px"><a href="<?= SITE_URL ?>/musteri/duzenle.php?new=1" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> İlk müşteriyi ekle</a></div>
                </div></td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <strong><?= h($r['unvan']) ?></strong>
                        <?php if ($r['e_fatura_mukellefi']): ?><span class="badge badge-primary" style="margin-left:6px"><i class="fas fa-check"></i> e-Fatura</span><?php endif; ?>
                    </td>
                    <td style="font-family:monospace;font-size:12px"><?= h($r['vkn_tip']) ?>: <?= h($r['vkn_tckn']) ?></td>
                    <td><?= h($r['vergi_dairesi'] ?: '—') ?></td>
                    <td><?= h($r['il']) ?><?= $r['ilce'] ? ' / '.h($r['ilce']) : '' ?></td>
                    <td style="font-size:12.5px">
                        <?php if($r['email']): ?><div><i class="fas fa-envelope" style="color:#94a3b8;width:14px"></i> <?= h($r['email']) ?></div><?php endif; ?>
                        <?php if($r['telefon']): ?><div><i class="fas fa-phone" style="color:#94a3b8;width:14px"></i> <?= h($r['telefon']) ?></div><?php endif; ?>
                    </td>
                    <td><?= $r['aktif'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Pasif</span>' ?></td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="<?= SITE_URL ?>/musteri/duzenle.php?id=<?= $r['id'] ?>" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php render_footer(); ?>
