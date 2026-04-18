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

// Filtreler
$durum_filtre = $_GET['durum'] ?? '';
$profil_filtre = $_GET['profil'] ?? '';
$tarih_bas = $_GET['tarih_bas'] ?? '';
$tarih_bit = $_GET['tarih_bit'] ?? '';
$tutar_min = $_GET['tutar_min'] ?? '';
$tutar_max = $_GET['tutar_max'] ?? '';
$q_filtre = trim($_GET['q'] ?? '');
$sayfa = max(1, (int)($_GET['s'] ?? 1));
$sayfa_basi = 25;
$offset = ($sayfa - 1) * $sayfa_basi;

$valid_durumlar = ['taslak','hazir','imzali','gonderildi','kabul','red','iptal'];
$valid_profiller = ['TEMELFATURA','TICARIFATURA','EARSIVFATURA','IHRACAT'];

$where = 'f.mukellef_id=?';
$par = [$mid];

if (in_array($durum_filtre, $valid_durumlar, true)) {
    $where .= ' AND f.durum=?';
    $par[] = $durum_filtre;
}
if (in_array($profil_filtre, $valid_profiller, true)) {
    $where .= ' AND f.profil=?';
    $par[] = $profil_filtre;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih_bas)) {
    $where .= ' AND f.duzenleme_tarihi >= ?';
    $par[] = $tarih_bas;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih_bit)) {
    $where .= ' AND f.duzenleme_tarihi <= ?';
    $par[] = $tarih_bit;
}
if (is_numeric($tutar_min)) {
    $where .= ' AND f.genel_toplam >= ?';
    $par[] = (float)$tutar_min;
}
if (is_numeric($tutar_max)) {
    $where .= ' AND f.genel_toplam <= ?';
    $par[] = (float)$tutar_max;
}
if ($q_filtre !== '') {
    $where .= ' AND (f.fatura_no LIKE ? OR f.ettn LIKE ?)';
    $par[] = '%' . $q_filtre . '%';
    $par[] = '%' . $q_filtre . '%';
}

// Sayım
$cnt = $pdo->prepare("SELECT COUNT(*) FROM faturalar f WHERE $where");
$cnt->execute($par);
$total = (int)$cnt->fetchColumn();
$total_sayfa = max(1, (int)ceil($total / $sayfa_basi));

// Liste
$par_list = array_merge($par, [$sayfa_basi, $offset]);
$st = $pdo->prepare("
    SELECT f.*, m.unvan AS m_unvan, m.vkn_tckn AS m_vkn
    FROM faturalar f
    LEFT JOIN mukellefler m ON m.id = f.mukellef_id
    WHERE $where
    ORDER BY f.duzenleme_tarihi DESC, f.id DESC
    LIMIT ? OFFSET ?
");
foreach ($par_list as $i => $v) {
    $st->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$st->execute();
$faturalar = $st->fetchAll();

// Toplam tutarlar (filtreli)
$sum_q = $pdo->prepare("SELECT COALESCE(SUM(genel_toplam),0) FROM faturalar f WHERE $where");
$sum_q->execute($par);
$toplam_tutar = (float)$sum_q->fetchColumn();

mp_audit($pdo, 'musteri.view_faturalar', "filter_count=$total");

mp_render_header('Faturalarım', 'faturalar');
?>

<div class="mp-page-head">
    <div>
        <h1>Faturalarım</h1>
        <div class="sub">
            <strong><?= number_format($total) ?></strong> fatura
            <?php if ($total > 0): ?> · toplam <strong><?= fmt_tl($toplam_tutar) ?></strong><?php endif; ?>
        </div>
    </div>
</div>

<!-- FİLTRELER -->
<div class="mp-card">
    <div class="mp-card-body" style="padding:16px 22px">
        <form method="GET" id="filtre-form">
            <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px">
                <div class="mp-fg" style="margin:0;grid-column:span 2">
                    <label style="font-size:11px">Fatura No / ETTN</label>
                    <input type="text" name="q" value="<?= h($q_filtre) ?>" placeholder="COD... veya UUID">
                </div>
                <div class="mp-fg" style="margin:0">
                    <label style="font-size:11px">Durum</label>
                    <select name="durum">
                        <option value="">Tümü</option>
                        <?php foreach (['taslak'=>'Taslak','hazir'=>'Hazırlanıyor','imzali'=>'İmzalandı','gonderildi'=>'Gönderildi','kabul'=>'Kabul','red'=>'Reddedildi','iptal'=>'İptal'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $durum_filtre===$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mp-fg" style="margin:0">
                    <label style="font-size:11px">Fatura Türü</label>
                    <select name="profil">
                        <option value="">Tümü</option>
                        <option value="TEMELFATURA"  <?= $profil_filtre==='TEMELFATURA'?'selected':'' ?>>Temel</option>
                        <option value="TICARIFATURA" <?= $profil_filtre==='TICARIFATURA'?'selected':'' ?>>Ticari</option>
                        <option value="EARSIVFATURA" <?= $profil_filtre==='EARSIVFATURA'?'selected':'' ?>>e-Arşiv</option>
                        <option value="IHRACAT"      <?= $profil_filtre==='IHRACAT'?'selected':'' ?>>İhracat</option>
                    </select>
                </div>
                <div class="mp-fg" style="margin:0">
                    <label style="font-size:11px">Tarih Baş.</label>
                    <input type="date" name="tarih_bas" value="<?= h($tarih_bas) ?>">
                </div>
                <div class="mp-fg" style="margin:0">
                    <label style="font-size:11px">Tarih Bit.</label>
                    <input type="date" name="tarih_bit" value="<?= h($tarih_bit) ?>">
                </div>
                <div class="mp-fg" style="margin:0">
                    <label style="font-size:11px">Min Tutar (₺)</label>
                    <input type="number" step="0.01" name="tutar_min" value="<?= h($tutar_min) ?>" placeholder="0">
                </div>
                <div class="mp-fg" style="margin:0">
                    <label style="font-size:11px">Maks Tutar (₺)</label>
                    <input type="number" step="0.01" name="tutar_max" value="<?= h($tutar_max) ?>">
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;grid-column:span 3">
                    <button type="submit" class="mp-btn mp-btn-primary">
                        <?= mp_icon('search', 14) ?> Filtrele
                    </button>
                    <?php if ($durum_filtre || $profil_filtre || $tarih_bas || $tarih_bit || $tutar_min || $tutar_max || $q_filtre): ?>
                        <a href="<?= SITE_URL ?>/musteri-portal/faturalar.php" class="mp-btn mp-btn-ghost">Temizle</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- LİSTE (EDM tarzı, geniş) -->
<div class="mp-card">
    <div class="mp-card-body tight">
        <div class="mp-table-wrap">
            <table class="mp-table" style="font-size:12.5px">
                <thead>
                    <tr>
                        <th>Tür</th>
                        <th>Fatura No</th>
                        <th>ETTN</th>
                        <th>Tarih</th>
                        <th style="text-align:right">Matrah</th>
                        <th style="text-align:right">KDV</th>
                        <th style="text-align:right">Tutar</th>
                        <th>Statü</th>
                        <th>İrsaliye No</th>
                        <th>Oluşturma</th>
                        <th>Mail</th>
                        <th>Portal</th>
                        <th>Departman</th>
                        <th style="text-align:right;position:sticky;right:0;background:#fcfcfd">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faturalar)): ?>
                        <tr><td colspan="14">
                            <div class="mp-table-empty">
                                <?= mp_icon('invoice', 48) ?>
                                <h4><?= ($durum_filtre || $profil_filtre || $q_filtre) ? 'Sonuç bulunamadı' : 'Henüz fatura yok' ?></h4>
                                <p><?= ($durum_filtre || $profil_filtre || $q_filtre) ? 'Filtre kriterlerinize uygun kayıt yok' : 'Adınıza düzenlenmiş fatura bulunmuyor' ?></p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($faturalar as $f):
                        $durum_badge = match($f['durum']) {
                            'taslak'     => ['secondary', 'Taslak', 'clock'],
                            'hazir'      => ['info',      'Hazırlanıyor', 'clock'],
                            'imzali'     => ['primary',   'İmzalı', 'signature'],
                            'gonderildi' => ['warning',   'Gönderildi', 'paper-plane'],
                            'kabul'      => ['success',   'Kabul Edildi', 'check-circle'],
                            'red'        => ['danger',    'Reddedildi', 'x-circle'],
                            'iptal'      => ['danger',    'İptal', 'ban'],
                            default      => ['secondary', $f['durum'], 'info'],
                        };
                        $profil_short = match($f['profil']) {
                            'TEMELFATURA' => 'Temel',
                            'TICARIFATURA' => 'Ticari',
                            'EARSIVFATURA' => 'e-Arşiv',
                            'IHRACAT' => 'İhracat',
                            default => $f['profil'],
                        };
                        $tip_short = match($f['tipi']) {
                            'SATIS' => 'Satış', 'IADE' => 'İade', 'TEVKIFAT' => 'Tevkifat',
                            'ISTISNA' => 'İstisna', 'OZELMATRAH' => 'Özel Matrah',
                            'IHRACKAYITLI' => 'İhraç Kayıtlı',
                            default => $f['tipi'],
                        };

                        // Mail gönderim badge
                        $mail_badge = match($f['mail_gonderim'] ?? 'gonderilmedi') {
                            'gonderildi' => ['success', 'Gönderildi'],
                            'beklemede'  => ['warning', 'Bekliyor'],
                            'basarisiz'  => ['danger',  'Başarısız'],
                            default      => ['secondary', '—'],
                        };

                        // Portal görüntülenme
                        $goruntu = (int)($f['portal_goruntuleme'] ?? 0);
                    ?>
                        <tr>
                            <td>
                                <span class="mp-badge mp-badge-secondary" style="font-size:10.5px"><?= $profil_short ?></span>
                                <div style="font-size:10.5px;color:#94a3b8;margin-top:2px"><?= $tip_short ?></div>
                            </td>
                            <td style="font-family:monospace;font-weight:600;color:#0a2540">
                                <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $f['id'] ?>" style="color:#0a2540">
                                    <?= h($f['fatura_no']) ?>
                                </a>
                            </td>
                            <td style="font-family:monospace;font-size:10.5px;color:#64748b">
                                <?= h(substr($f['ettn'], 0, 8)) ?>…
                            </td>
                            <td style="white-space:nowrap;color:#475569"><?= date('d.m.Y', strtotime($f['duzenleme_tarihi'])) ?></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums;color:#64748b"><?= fmt_tl((float)$f['matrah']) ?></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums;color:#64748b"><?= fmt_tl((float)$f['kdv_toplam']) ?></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700;color:#0f172a"><?= fmt_tl((float)$f['genel_toplam']) ?></td>
                            <td>
                                <span class="mp-badge mp-badge-<?= $durum_badge[0] ?>" style="font-size:11px">
                                    <?= mp_icon($durum_badge[2], 10) ?>
                                    <?= $durum_badge[1] ?>
                                </span>
                            </td>
                            <td style="font-size:11px;color:#64748b"><?= h($f['irsaliye_no'] ?? '') ?: '—' ?></td>
                            <td style="white-space:nowrap;color:#64748b;font-size:11px">
                                <?= fmt_datetime($f['created_at']) ?>
                            </td>
                            <td>
                                <span class="mp-badge mp-badge-<?= $mail_badge[0] ?>" style="font-size:10.5px"><?= $mail_badge[1] ?></span>
                            </td>
                            <td style="text-align:center;color:#64748b;font-size:11px">
                                <?php if ($goruntu > 0): ?>
                                    <?= mp_icon('eye', 12) ?> <?= $goruntu ?>
                                <?php else: ?>
                                    <span style="color:#cbd5e1">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:11px;color:#64748b"><?= h($f['departman'] ?? '') ?: '—' ?></td>
                            <td style="text-align:right;white-space:nowrap;position:sticky;right:0;background:#fff;box-shadow:-4px 0 6px -2px rgba(0,0,0,0.06)">
                                <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $f['id'] ?>" class="mp-btn mp-btn-ghost mp-btn-sm" title="Görüntüle">
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

        <!-- SAYFALAMA -->
        <?php if ($total_sayfa > 1):
            $url_base = '?';
            foreach ($_GET as $k => $v) {
                if ($k !== 's' && $v !== '') $url_base .= urlencode($k).'='.urlencode((string)$v).'&';
            }
        ?>
        <div style="padding:14px 22px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;font-size:12.5px;color:#64748b">
            <div>
                <?= (($sayfa-1)*$sayfa_basi)+1 ?> – <?= min($sayfa*$sayfa_basi, $total) ?> / <strong><?= number_format($total) ?></strong>
            </div>
            <div style="display:flex;gap:4px">
                <?php if ($sayfa > 1): ?>
                    <a href="<?= h($url_base.'s='.($sayfa-1)) ?>" class="mp-btn mp-btn-ghost mp-btn-sm">← Önceki</a>
                <?php endif; ?>
                <?php
                $show_pages = [];
                $show_pages[] = 1;
                for ($i = max(2, $sayfa - 2); $i <= min($total_sayfa - 1, $sayfa + 2); $i++) $show_pages[] = $i;
                if ($total_sayfa > 1) $show_pages[] = $total_sayfa;
                $show_pages = array_unique($show_pages);
                sort($show_pages);
                $prev = 0;
                foreach ($show_pages as $p):
                    if ($prev && $p - $prev > 1) echo '<span style="padding:6px 8px;color:#cbd5e1">…</span>';
                    $is_current = $p === $sayfa;
                ?>
                    <a href="<?= h($url_base.'s='.$p) ?>" class="mp-btn mp-btn-<?= $is_current?'brand':'ghost' ?> mp-btn-sm" style="min-width:36px;justify-content:center"><?= $p ?></a>
                    <?php $prev = $p; ?>
                <?php endforeach; ?>
                <?php if ($sayfa < $total_sayfa): ?>
                    <a href="<?= h($url_base.'s='.($sayfa+1)) ?>" class="mp-btn mp-btn-ghost mp-btn-sm">Sonraki →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php mp_render_footer(); ?>
