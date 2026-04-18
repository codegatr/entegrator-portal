<?php
require __DIR__ . '/../../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(SITE_URL . '/fatura/liste.php');

$q = $pdo->prepare("
    SELECT f.*, m.unvan AS musteri, m.vkn_tckn AS musteri_vkn, m.vkn_tip,
           m.vergi_dairesi AS musteri_vergi_dairesi, m.il AS musteri_il, m.ilce AS musteri_ilce,
           m.email AS musteri_email, m.telefon AS musteri_telefon,
           k.kullanici_adi AS olusturan, k.ad_soyad AS olusturan_ad
    FROM faturalar f
    LEFT JOIN mukellefler m ON m.id = f.mukellef_id
    LEFT JOIN kullanicilar k ON k.id = f.kullanici_id
    WHERE f.id = ?
");
$q->execute([$id]);
$f = $q->fetch();
if (!$f) { flash_set('danger','Fatura bulunamadı.'); redirect(SITE_URL.'/fatura/liste.php'); }

// Satırlar
$ls = $pdo->prepare("SELECT * FROM fatura_satirlari WHERE fatura_id=? ORDER BY sira");
$ls->execute([$id]);
$satirlar = $ls->fetchAll();

// Log timeline
$lg = $pdo->prepare("
    SELECT fl.*, k.kullanici_adi
    FROM fatura_log fl
    LEFT JOIN kullanicilar k ON k.id = fl.kullanici_id
    WHERE fl.fatura_id = ?
    ORDER BY fl.id DESC
");
$lg->execute([$id]);
$loglar = $lg->fetchAll();

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'iptal' && auth_user()['rol'] !== 'viewer' && !in_array($f['durum'], ['iptal','gonderildi','kabul'], true)) {
        fatura_durum_degistir($pdo, $id, 'iptal', $_POST['iptal_sebep'] ?? null);
        audit_log($pdo, 'fatura.iptal', "no={$f['fatura_no']}", null, "fatura:$id");
        flash_set('success', 'Fatura iptal edildi.');
        redirect(SITE_URL . '/fatura/detay.php?id=' . $id);
    }
}

// XML dosyası
$xml_content = '';
$xml_size = 0;
if ($f['xml_path']) {
    $xml_full = STORAGE_PATH . '/' . $f['xml_path'];
    if (file_exists($xml_full)) {
        $xml_content = file_get_contents($xml_full);
        $xml_size = strlen($xml_content);
    }
}

render_header("Fatura {$f['fatura_no']}", 'fatura');
?>

<div class="page-head">
    <div>
        <h1><?= h($f['fatura_no']) ?></h1>
        <div class="sub">
            <?= fatura_durum_html($f['durum']) ?>
            · <?= h($f['profil']) ?> / <?= h($f['tipi']) ?>
            · <?= fmt_date($f['duzenleme_tarihi']) ?>
            · <code style="font-size:11px;color:#64748b"><?= h($f['ettn']) ?></code>
        </div>
    </div>
    <div class="page-actions">
        <?php if ($f['xml_path']): ?>
            <a href="<?= SITE_URL ?>/fatura/indir.php?id=<?= $id ?>" class="btn btn-ghost"><i class="fas fa-download"></i> XML İndir</a>
        <?php endif; ?>
        <?php if (!in_array($f['durum'], ['iptal','gonderildi','kabul'], true) && auth_user()['rol'] !== 'viewer'): ?>
            <form method="POST" style="display:inline" data-confirm="Fatura iptal edilsin mi? Bu işlem geri alınamaz." class="js-iptal">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="iptal">
                <button type="submit" class="btn btn-danger" data-confirm="Fatura iptal edilsin mi? Bu işlem geri alınamaz."><i class="fas fa-ban"></i> İptal Et</button>
            </form>
        <?php endif; ?>
        <a href="<?= SITE_URL ?>/fatura/liste.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Liste</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px">
    <div>
        <!-- ═══ Temel Bilgiler ═══ -->
        <div class="card">
            <div class="card-h"><i class="fas fa-info-circle"></i> Fatura Bilgileri</div>
            <div class="card-b">
                <div class="form-row">
                    <div>
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px">Satıcı</div>
                        <div style="font-size:14px;font-weight:600"><?= h(FIRMA_ADI) ?></div>
                        <div style="font-size:12px;color:#64748b">VKN: <?= h(FIRMA_VKN) ?> · <?= h(FIRMA_VERGI_DAIRESI) ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px">Alıcı</div>
                        <div style="font-size:14px;font-weight:600"><?= h($f['musteri']) ?></div>
                        <div style="font-size:12px;color:#64748b"><?= h($f['vkn_tip']) ?>: <?= h($f['musteri_vkn']) ?><?= $f['musteri_vergi_dairesi'] ? ' · '.h($f['musteri_vergi_dairesi']) : '' ?></div>
                    </div>
                </div>
                <?php if ($f['notlar']): ?>
                <div style="margin-top:14px;padding:10px 12px;background:#f8fafc;border-left:3px solid #f6821f;border-radius:4px;font-size:13px">
                    <strong style="color:#475569">Not:</strong> <?= h($f['notlar']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ Satırlar ═══ -->
        <div class="card">
            <div class="card-h"><i class="fas fa-list-ol"></i> Satırlar (<?= count($satirlar) ?>)</div>
            <div class="table-wrap" style="border:none;border-radius:0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ürün / Hizmet</th>
                            <th>Miktar</th>
                            <th style="text-align:right">Birim Fiyat</th>
                            <th style="text-align:right">İsk.</th>
                            <th style="text-align:center">KDV%</th>
                            <th style="text-align:right">Matrah</th>
                            <th style="text-align:right">Toplam</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($satirlar as $s): ?>
                        <tr>
                            <td><?= $s['sira'] ?></td>
                            <td><strong><?= h($s['urun_adi']) ?></strong><?php if($s['aciklama']): ?><br><small style="color:#64748b"><?= h($s['aciklama']) ?></small><?php endif; ?></td>
                            <td><?= rtrim(rtrim(number_format((float)$s['miktar'], 8, ',', '.'), '0'), ',') ?: '0' ?> <small style="color:#94a3b8"><?= h($s['birim_kodu']) ?></small></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= number_format((float)$s['birim_fiyat'], 4, ',', '.') ?></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums;color:<?= $s['iskonto']>0?'#dc2626':'#94a3b8' ?>"><?= fmt_tl((float)$s['iskonto']) ?></td>
                            <td style="text-align:center">%<?= number_format((float)$s['kdv_oran'], 0) ?></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= fmt_tl((float)$s['matrah']) ?></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:600"><?= fmt_tl((float)$s['satir_toplam']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#fff7ed;font-weight:600">
                            <td colspan="6" style="text-align:right;color:#9a3412">Matrah</td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= fmt_tl((float)$f['matrah']) ?></td>
                            <td></td>
                        </tr>
                        <tr style="background:#fff7ed;font-weight:600">
                            <td colspan="6" style="text-align:right;color:#9a3412">KDV Toplam</td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= fmt_tl((float)$f['kdv_toplam']) ?></td>
                            <td></td>
                        </tr>
                        <tr style="background:#f6821f;color:#fff;font-weight:700;font-size:15px">
                            <td colspan="6" style="text-align:right">GENEL TOPLAM</td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= fmt_tl((float)$f['genel_toplam']) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ═══ XML Görünümü ═══ -->
        <?php if ($xml_content): ?>
        <div class="card">
            <div class="card-h">
                <i class="fas fa-file-code"></i> UBL-TR XML (<?= number_format($xml_size) ?> byte)
                <a href="<?= SITE_URL ?>/fatura/indir.php?id=<?= $id ?>" style="margin-left:auto;font-size:12px;font-weight:normal"><i class="fas fa-download"></i> İndir</a>
            </div>
            <div class="card-b" style="padding:0">
                <pre class="xml-viewer"><?= h($xml_content) ?></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ Sağ sütun ═══ -->
    <div>
        <!-- Durum Timeline -->
        <div class="card">
            <div class="card-h"><i class="fas fa-clock-rotate-left"></i> Durum Geçmişi</div>
            <div class="card-b">
                <div class="timeline">
                    <?php if (empty($loglar)): ?>
                        <div style="color:#94a3b8;font-size:13px">Henüz kayıt yok</div>
                    <?php else: foreach ($loglar as $lg):
                        $tip = 'ok';
                        if ($lg['yeni_durum'] === 'red' || $lg['yeni_durum'] === 'iptal') $tip = 'err';
                        elseif ($lg['yeni_durum'] === 'taslak') $tip = 'warn';
                    ?>
                    <div class="tl-item <?= $tip ?>">
                        <div class="tl-time"><?= fmt_datetime($lg['created_at']) ?></div>
                        <div class="tl-title">
                            <?= $lg['onceki_durum'] ? h($lg['onceki_durum']) . ' → ' : '' ?>
                            <?= fatura_durum_html($lg['yeni_durum']) ?>
                        </div>
                        <div class="tl-desc">
                            <?= h($lg['kullanici_adi'] ?: 'sistem') ?>
                            <?php if ($lg['aciklama']): ?> · <?= h($lg['aciklama']) ?><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- Sonraki adımlar -->
        <div class="card" style="background:#fff7ed;border-color:#fde68a">
            <div class="card-h" style="background:#fed7aa;color:#7c2d12"><i class="fas fa-list-check"></i> Sonraki Adımlar</div>
            <div class="card-b" style="font-size:13px">
                <?php if ($f['durum'] === 'hazir'): ?>
                    <div style="margin-bottom:10px">1. <strong>İmzala</strong> <span class="badge badge-secondary"><i class="fas fa-hourglass-half"></i> v0.2'de</span></div>
                    <div style="margin-bottom:10px">2. <strong>GİB'e gönder</strong> <span class="badge badge-secondary"><i class="fas fa-hourglass-half"></i> v0.3'te</span></div>
                    <div>3. <strong>Durum takibi</strong> <span class="badge badge-secondary"><i class="fas fa-hourglass-half"></i> v0.3'te</span></div>
                <?php else: ?>
                    <?= fatura_durum_html($f['durum']) ?> durumundaki fatura için aksiyon beklenmiyor.
                <?php endif; ?>
            </div>
        </div>

        <!-- Kaynak bilgi -->
        <div class="card">
            <div class="card-h"><i class="fas fa-circle-info"></i> Detay</div>
            <div class="card-b" style="font-size:12.5px">
                <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f1f5f9">
                    <span style="color:#64748b">Oluşturan</span>
                    <strong><?= h($f['olusturan_ad'] ?: $f['olusturan'] ?: '—') ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f1f5f9">
                    <span style="color:#64748b">Oluşturulma</span>
                    <strong><?= fmt_datetime($f['created_at']) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f1f5f9">
                    <span style="color:#64748b">Son Güncelleme</span>
                    <strong><?= fmt_datetime($f['updated_at']) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:5px 0">
                    <span style="color:#64748b">Para Birimi</span>
                    <strong><?= h($f['para_birimi']) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
