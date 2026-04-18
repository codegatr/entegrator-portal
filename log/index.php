<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require();

$tab   = $_GET['tab'] ?? 'sistem';
$q     = trim($_GET['q'] ?? '');
$page  = max(1, (int)($_GET['p'] ?? 1));
$per   = 50;
$off   = ($page - 1) * $per;

// Tab'a göre sorgu
if ($tab === 'fatura') {
    $where = '1=1'; $par = [];
    if ($q !== '') { $where .= ' AND (f.fatura_no LIKE ? OR fl.aciklama LIKE ?)'; $par[] = "%$q%"; $par[] = "%$q%"; }

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM fatura_log fl LEFT JOIN faturalar f ON f.id=fl.fatura_id WHERE $where");
    $cnt->execute($par);
    $total = (int)$cnt->fetchColumn();

    $st = $pdo->prepare("
        SELECT fl.*, f.fatura_no, k.kullanici_adi
        FROM fatura_log fl
        LEFT JOIN faturalar f ON f.id = fl.fatura_id
        LEFT JOIN kullanicilar k ON k.id = fl.kullanici_id
        WHERE $where
        ORDER BY fl.id DESC
        LIMIT $per OFFSET $off
    ");
    $st->execute($par);
    $rows = $st->fetchAll();
} else {
    $where = '1=1'; $par = [];
    if ($q !== '') { $where .= ' AND (olay LIKE ? OR detay LIKE ? OR hedef LIKE ?)'; $par[] = "%$q%"; $par[] = "%$q%"; $par[] = "%$q%"; }

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM sistem_log WHERE $where");
    $cnt->execute($par);
    $total = (int)$cnt->fetchColumn();

    $st = $pdo->prepare("
        SELECT sl.*, k.kullanici_adi
        FROM sistem_log sl
        LEFT JOIN kullanicilar k ON k.id = sl.kullanici_id
        WHERE $where
        ORDER BY sl.id DESC
        LIMIT $per OFFSET $off
    ");
    $st->execute($par);
    $rows = $st->fetchAll();
}

$toplam_sayfa = max(1, (int)ceil($total / $per));

render_header('Aktivite Log', 'log');
?>

<div class="page-head">
    <div>
        <h1>Aktivite Takibi</h1>
        <div class="sub">Sistemdeki tüm işlemler burada loglanır · <?= number_format($total) ?> kayıt</div>
    </div>
</div>

<!-- Tab'lar -->
<div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid #e5e7eb">
    <a href="?tab=sistem" class="btn btn-ghost btn-sm" style="border-radius:7px 7px 0 0;<?= $tab==='sistem'?'background:#f6821f;color:#fff':'' ?>">
        <i class="fas fa-server"></i> Sistem Log
    </a>
    <a href="?tab=fatura" class="btn btn-ghost btn-sm" style="border-radius:7px 7px 0 0;<?= $tab==='fatura'?'background:#f6821f;color:#fff':'' ?>">
        <i class="fas fa-file-invoice"></i> Fatura Durum Log
    </a>
</div>

<form class="filters" method="GET">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <label>Ara:</label>
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Olay, detay, kullanıcı..." style="min-width:280px">
    <button type="submit" class="btn btn-ghost btn-sm"><i class="fas fa-search"></i> Ara</button>
    <?php if ($q): ?><a href="?tab=<?= h($tab) ?>" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a><?php endif; ?>
    <div style="margin-left:auto;color:#64748b;font-size:12px">Sayfa <?= $page ?> / <?= $toplam_sayfa ?></div>
</form>

<div class="table-wrap">
    <table class="table">
        <?php if ($tab === 'fatura'): ?>
        <thead>
            <tr>
                <th>Zaman</th>
                <th>Fatura</th>
                <th>Değişim</th>
                <th>Açıklama</th>
                <th>Kullanıcı</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6"><div class="table-empty"><i class="fas fa-list"></i>Kayıt yok</div></td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td style="white-space:nowrap;color:#64748b;font-size:12px"><?= fmt_datetime($r['created_at']) ?></td>
                    <td>
                        <?php if ($r['fatura_id']): ?>
                            <a href="<?= SITE_URL ?>/fatura/detay.php?id=<?= $r['fatura_id'] ?>" style="font-family:monospace;font-size:12px"><?= h($r['fatura_no'] ?: '#'.$r['fatura_id']) ?></a>
                        <?php else: ?>
                            <span style="color:#94a3b8">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['onceki_durum']): ?>
                            <?= fatura_durum_html($r['onceki_durum']) ?>
                            <i class="fas fa-arrow-right" style="color:#94a3b8;margin:0 3px"></i>
                        <?php endif; ?>
                        <?= fatura_durum_html($r['yeni_durum']) ?>
                    </td>
                    <td style="font-size:13px"><?= h($r['aciklama'] ?: '—') ?></td>
                    <td style="font-size:12.5px"><?= h($r['kullanici_adi'] ?: 'sistem') ?></td>
                    <td style="font-family:monospace;font-size:11px;color:#94a3b8"><?= h($r['ip'] ?: '—') ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <?php else: ?>
        <thead>
            <tr>
                <th>Zaman</th>
                <th>Olay</th>
                <th>Kullanıcı</th>
                <th>Detay</th>
                <th>Hedef</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6"><div class="table-empty"><i class="fas fa-server"></i>Kayıt yok</div></td></tr>
            <?php else: foreach ($rows as $r):
                $cls = 'badge-secondary';
                if (str_contains($r['olay'], '.fail') || str_contains($r['olay'], '.error')) $cls = 'badge-danger';
                elseif (str_contains($r['olay'], '.create')) $cls = 'badge-success';
                elseif (str_contains($r['olay'], '.update')) $cls = 'badge-info';
                elseif (str_contains($r['olay'], '.delete') || str_contains($r['olay'], '.iptal')) $cls = 'badge-warning';
                elseif (str_starts_with($r['olay'], 'auth.')) $cls = 'badge-primary';
            ?>
                <tr>
                    <td style="white-space:nowrap;color:#64748b;font-size:12px"><?= fmt_datetime($r['created_at']) ?></td>
                    <td><span class="badge <?= $cls ?>"><?= h($r['olay']) ?></span></td>
                    <td style="font-size:12.5px"><?= h($r['kullanici_adi'] ?: 'sistem') ?></td>
                    <td style="font-size:12.5px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($r['detay']) ?>"><?= h($r['detay'] ?: '—') ?></td>
                    <td style="font-family:monospace;font-size:11px;color:#64748b"><?= h($r['hedef'] ?: '—') ?></td>
                    <td style="font-family:monospace;font-size:11px;color:#94a3b8"><?= h($r['ip'] ?: '—') ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <?php endif; ?>
    </table>
</div>

<?php if ($toplam_sayfa > 1): ?>
<div style="margin-top:14px;display:flex;justify-content:center;gap:6px">
    <?php if ($page > 1): ?>
        <a href="?tab=<?= h($tab) ?>&q=<?= urlencode($q) ?>&p=<?= $page-1 ?>" class="btn btn-ghost btn-sm"><i class="fas fa-chevron-left"></i></a>
    <?php endif; ?>
    <span style="padding:7px 14px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;font-size:13px">
        Sayfa <?= $page ?> / <?= $toplam_sayfa ?>
    </span>
    <?php if ($page < $toplam_sayfa): ?>
        <a href="?tab=<?= h($tab) ?>&q=<?= urlencode($q) ?>&p=<?= $page+1 ?>" class="btn btn-ghost btn-sm"><i class="fas fa-chevron-right"></i></a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php render_footer(); ?>
