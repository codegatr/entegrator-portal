<?php
define('CODEGA_NO_AUTO_SESSION', true);

// ═══ FAIL-SAFE: Her turlu hatayi yakala ═══
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {


require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

mp_auth_require();
$user = mp_auth_user();
$mid = (int)$user['mukellef_id'];

// Arama parametreleri
$q            = trim($_GET['q'] ?? '');
$arama_tipi   = $_GET['tip'] ?? 'tumu'; // tumu, fatura, urun
$tarih_bas    = $_GET['tarih_bas'] ?? '';
$tarih_bit    = $_GET['tarih_bit'] ?? '';
$tutar_min    = $_GET['tutar_min'] ?? '';
$tutar_max    = $_GET['tutar_max'] ?? '';
$kdv_oran     = $_GET['kdv'] ?? '';

$fatura_sonuc = [];
$urun_sonuc = [];
$toplam_urun_miktar = 0;
$toplam_urun_tutar = 0;

if ($q !== '' || $tarih_bas || $tarih_bit || $tutar_min || $tutar_max) {
    // Fatura arama
    if (in_array($arama_tipi, ['tumu','fatura'], true)) {
        $f_where = 'f.mukellef_id=?';
        $f_par = [$mid];

        if ($q !== '') {
            $f_where .= ' AND (f.fatura_no LIKE ? OR f.ettn LIKE ? OR f.notlar LIKE ? OR f.irsaliye_no LIKE ?)';
            $f_par[] = '%'.$q.'%';
            $f_par[] = '%'.$q.'%';
            $f_par[] = '%'.$q.'%';
            $f_par[] = '%'.$q.'%';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih_bas)) {
            $f_where .= ' AND f.duzenleme_tarihi >= ?';
            $f_par[] = $tarih_bas;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih_bit)) {
            $f_where .= ' AND f.duzenleme_tarihi <= ?';
            $f_par[] = $tarih_bit;
        }
        if (is_numeric($tutar_min)) {
            $f_where .= ' AND f.genel_toplam >= ?';
            $f_par[] = (float)$tutar_min;
        }
        if (is_numeric($tutar_max)) {
            $f_where .= ' AND f.genel_toplam <= ?';
            $f_par[] = (float)$tutar_max;
        }

        $f_st = $pdo->prepare("SELECT f.* FROM faturalar f WHERE $f_where ORDER BY f.duzenleme_tarihi DESC, f.id DESC LIMIT 100");
        $f_st->execute($f_par);
        $fatura_sonuc = $f_st->fetchAll();
    }

    // Ürün/hizmet arama (satır bazlı)
    if (in_array($arama_tipi, ['tumu','urun'], true)) {
        $u_where = 'f.mukellef_id=? AND f.durum NOT IN (\'iptal\',\'taslak\')';
        $u_par = [$mid];

        if ($q !== '') {
            $u_where .= ' AND (fs.urun_adi LIKE ? OR fs.aciklama LIKE ? OR fs.urun_kodu LIKE ?)';
            $u_par[] = '%'.$q.'%';
            $u_par[] = '%'.$q.'%';
            $u_par[] = '%'.$q.'%';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih_bas)) {
            $u_where .= ' AND f.duzenleme_tarihi >= ?';
            $u_par[] = $tarih_bas;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih_bit)) {
            $u_where .= ' AND f.duzenleme_tarihi <= ?';
            $u_par[] = $tarih_bit;
        }
        if (is_numeric($kdv_oran) && $kdv_oran !== '') {
            $u_where .= ' AND fs.kdv_oran = ?';
            $u_par[] = (float)$kdv_oran;
        }

        $u_st = $pdo->prepare("
            SELECT fs.*, f.fatura_no, f.duzenleme_tarihi, f.durum AS fatura_durum, f.id AS fatura_id
            FROM fatura_satirlari fs
            INNER JOIN faturalar f ON f.id = fs.fatura_id
            WHERE $u_where
            ORDER BY f.duzenleme_tarihi DESC, fs.sira ASC
            LIMIT 200
        ");
        $u_st->execute($u_par);
        $urun_sonuc = $u_st->fetchAll();

        // Toplam
        foreach ($urun_sonuc as $r) {
            $toplam_urun_miktar += (float)$r['miktar'];
            $toplam_urun_tutar += (float)$r['satir_toplam'];
        }
    }

    mp_audit($pdo, 'musteri.arama', "q=$q tip=$arama_tipi fatura=".count($fatura_sonuc)." urun=".count($urun_sonuc));
}

mp_render_header('Arama', 'arama');
?>

<div class="mp-page-head">
    <div>
        <h1>Arama</h1>
        <div class="sub">Fatura numarası, ETTN veya ürün/hizmet adıyla geçmiş faturalarınızda arayın</div>
    </div>
</div>

<!-- ARAMA FORMU -->
<div class="mp-card">
    <div class="mp-card-body" style="padding:22px">
        <form method="GET">
            <!-- Ana arama -->
            <div style="position:relative;margin-bottom:14px">
                <div style="position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#94a3b8">
                    <?= mp_icon('search', 20) ?>
                </div>
                <input type="text" name="q" value="<?= h($q) ?>"
                       placeholder="Ürün, hizmet adı, fatura no, ETTN, irsaliye no..."
                       style="width:100%;padding:15px 16px 15px 46px;font-size:15px;border:2px solid #e2e8f0;border-radius:10px;box-sizing:border-box"
                       autofocus>
            </div>

            <!-- Arama tipi -->
            <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
                <?php foreach (['tumu'=>'🔍 Tümü','fatura'=>'🧾 Faturalarda','urun'=>'📦 Ürün/Hizmette'] as $k => $v):
                    $is_active = $arama_tipi === $k;
                ?>
                    <label style="cursor:pointer">
                        <input type="radio" name="tip" value="<?= $k ?>" <?= $is_active?'checked':'' ?> style="display:none" onchange="this.form.submit()">
                        <span style="display:inline-block;padding:8px 16px;border-radius:20px;font-size:13px;font-weight:600;<?= $is_active ? 'background:#0b5cff;color:#fff' : 'background:#f8fafc;color:#475569;border:1px solid #e2e8f0' ?>">
                            <?= $v ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Gelişmiş filtreler -->
            <details <?= ($tarih_bas || $tarih_bit || $tutar_min || $tutar_max || $kdv_oran!=='') ? 'open' : '' ?>>
                <summary style="cursor:pointer;font-size:13px;color:#475569;font-weight:600;padding:8px 0;list-style:none;display:flex;align-items:center;gap:6px">
                    <?= mp_icon('refresh', 14) ?>
                    Gelişmiş Filtreler
                </summary>

                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-top:12px">
                    <div class="mp-fg" style="margin:0">
                        <label style="font-size:11px">Tarih Başlangıç</label>
                        <input type="date" name="tarih_bas" value="<?= h($tarih_bas) ?>">
                    </div>
                    <div class="mp-fg" style="margin:0">
                        <label style="font-size:11px">Tarih Bitiş</label>
                        <input type="date" name="tarih_bit" value="<?= h($tarih_bit) ?>">
                    </div>
                    <div class="mp-fg" style="margin:0">
                        <label style="font-size:11px">Min Tutar (₺)</label>
                        <input type="number" step="0.01" name="tutar_min" value="<?= h($tutar_min) ?>">
                    </div>
                    <div class="mp-fg" style="margin:0">
                        <label style="font-size:11px">Maks Tutar (₺)</label>
                        <input type="number" step="0.01" name="tutar_max" value="<?= h($tutar_max) ?>">
                    </div>
                    <div class="mp-fg" style="margin:0">
                        <label style="font-size:11px">KDV Oranı</label>
                        <select name="kdv">
                            <option value="">Tümü</option>
                            <option value="0"  <?= $kdv_oran==='0'?'selected':'' ?>>%0</option>
                            <option value="1"  <?= $kdv_oran==='1'?'selected':'' ?>>%1</option>
                            <option value="10" <?= $kdv_oran==='10'?'selected':'' ?>>%10</option>
                            <option value="20" <?= $kdv_oran==='20'?'selected':'' ?>>%20</option>
                        </select>
                    </div>
                </div>
            </details>

            <div style="display:flex;gap:10px;margin-top:14px;align-items:center">
                <button type="submit" class="mp-btn mp-btn-primary">
                    <?= mp_icon('search', 14) ?> Ara
                </button>
                <?php if ($q || $tarih_bas || $tarih_bit || $tutar_min || $tutar_max || $kdv_oran!==''): ?>
                    <a href="<?= SITE_URL ?>/musteri-portal/arama.php" class="mp-btn mp-btn-ghost">Temizle</a>
                <?php endif; ?>
                <div style="flex:1"></div>
                <?php if ($q): ?>
                    <div style="font-size:12.5px;color:#64748b">
                        "<strong><?= h($q) ?></strong>" için sonuçlar
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- BOŞ DURUM -->
<?php if ($q === '' && !$tarih_bas && !$tarih_bit && !$tutar_min && !$tutar_max): ?>
    <div class="mp-card">
        <div style="padding:50px 30px;text-align:center">
            <div style="font-size:48px;margin-bottom:12px">🔍</div>
            <h3 style="margin:0 0 6px;color:#0f172a">Aramaya başlayın</h3>
            <p style="margin:0 0 20px;color:#64748b;font-size:13.5px;max-width:450px;margin-left:auto;margin-right:auto">
                Geçmiş faturalarınızda, aldığınız ürün ve hizmetlerde, fatura notlarında ve daha birçok yerde arama yapabilirsiniz.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;max-width:700px;margin:0 auto;text-align:left">
                <div style="padding:16px;background:#f8fafc;border-radius:10px">
                    <div style="font-size:22px;margin-bottom:6px">🧾</div>
                    <div style="font-weight:600;font-size:13.5px;margin-bottom:3px">Fatura Ara</div>
                    <div style="font-size:12px;color:#64748b;line-height:1.5">Fatura no, ETTN veya irsaliye no ile</div>
                </div>
                <div style="padding:16px;background:#f8fafc;border-radius:10px">
                    <div style="font-size:22px;margin-bottom:6px">📦</div>
                    <div style="font-weight:600;font-size:13.5px;margin-bottom:3px">Ürün/Hizmet Ara</div>
                    <div style="font-size:12px;color:#64748b;line-height:1.5">Hangi ürünü ne zaman almışım</div>
                </div>
                <div style="padding:16px;background:#f8fafc;border-radius:10px">
                    <div style="font-size:22px;margin-bottom:6px">📅</div>
                    <div style="font-weight:600;font-size:13.5px;margin-bottom:3px">Tarih Aralığı</div>
                    <div style="font-size:12px;color:#64748b;line-height:1.5">Belirli bir dönemdeki tüm hareketler</div>
                </div>
                <div style="padding:16px;background:#f8fafc;border-radius:10px">
                    <div style="font-size:22px;margin-bottom:6px">💰</div>
                    <div style="font-weight:600;font-size:13.5px;margin-bottom:3px">Tutar Aralığı</div>
                    <div style="font-size:12px;color:#64748b;line-height:1.5">Belirli bir tutar aralığındaki faturalar</div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- SONUÇ ÖZETİ -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:20px">
        <?php if (in_array($arama_tipi, ['tumu','fatura'], true)): ?>
            <div class="mp-kpi">
                <div class="mp-kpi-head">
                    <div>
                        <div class="mp-kpi-label">Bulunan Fatura</div>
                        <div class="mp-kpi-value"><?= count($fatura_sonuc) ?></div>
                        <?php if (!empty($fatura_sonuc)):
                            $t_tutar = array_sum(array_map(fn($r) => (float)$r['genel_toplam'], $fatura_sonuc));
                        ?>
                            <div class="mp-kpi-sub">Toplam: <?= fmt_tl($t_tutar) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mp-kpi-icon blue"><?= mp_icon('invoice', 20) ?></div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (in_array($arama_tipi, ['tumu','urun'], true)): ?>
            <div class="mp-kpi">
                <div class="mp-kpi-head">
                    <div>
                        <div class="mp-kpi-label">Bulunan Ürün/Hizmet Satırı</div>
                        <div class="mp-kpi-value"><?= count($urun_sonuc) ?></div>
                        <?php if ($toplam_urun_tutar > 0): ?>
                            <div class="mp-kpi-sub">Toplam: <?= fmt_tl($toplam_urun_tutar) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mp-kpi-icon green"><?= mp_icon('package', 20) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- FATURA SONUÇLARI -->
    <?php if (in_array($arama_tipi, ['tumu','fatura'], true) && !empty($fatura_sonuc)): ?>
        <div class="mp-card">
            <div class="mp-card-head">
                <?= mp_icon('invoice') ?>
                <h3>Fatura Sonuçları (<?= count($fatura_sonuc) ?>)</h3>
            </div>
            <div class="mp-card-body tight">
                <div class="mp-table-wrap">
                    <table class="mp-table">
                        <thead>
                            <tr>
                                <th>Fatura No</th>
                                <th>Tarih</th>
                                <th>İrsaliye</th>
                                <th style="text-align:right">Tutar</th>
                                <th>Durum</th>
                                <th style="text-align:right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fatura_sonuc as $f):
                                $durum_badge = match($f['durum']) {
                                    'taslak'=>['secondary','Taslak'], 'hazir'=>['info','Hazır'], 'imzali'=>['primary','İmzalı'],
                                    'gonderildi'=>['warning','Gönderildi'], 'kabul'=>['success','Kabul'],
                                    'red'=>['danger','Red'], 'iptal'=>['danger','İptal'],
                                    default=>['secondary',$f['durum']],
                                };
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $f['id'] ?>" style="font-family:monospace;font-size:12.5px;font-weight:600;color:#0a2540">
                                            <?= h($f['fatura_no']) ?>
                                        </a>
                                    </td>
                                    <td style="font-size:12.5px;color:#475569;white-space:nowrap"><?= date('d.m.Y', strtotime($f['duzenleme_tarihi'])) ?></td>
                                    <td style="font-size:11.5px;color:#64748b"><?= h($f['irsaliye_no'] ?? '') ?: '—' ?></td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700"><?= fmt_tl((float)$f['genel_toplam']) ?></td>
                                    <td><span class="mp-badge mp-badge-<?= $durum_badge[0] ?>"><?= $durum_badge[1] ?></span></td>
                                    <td style="text-align:right">
                                        <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $f['id'] ?>" class="mp-btn mp-btn-ghost mp-btn-sm">
                                            <?= mp_icon('eye', 13) ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ÜRÜN/HİZMET SATIR SONUÇLARI -->
    <?php if (in_array($arama_tipi, ['tumu','urun'], true) && !empty($urun_sonuc)): ?>
        <div class="mp-card">
            <div class="mp-card-head">
                <?= mp_icon('package') ?>
                <h3>Ürün / Hizmet Satırları (<?= count($urun_sonuc) ?>)</h3>
                <span style="margin-left:auto;font-size:12px;color:#94a3b8">Toplam miktar: <strong style="color:#0f172a"><?= number_format($toplam_urun_miktar, 2, ',', '.') ?></strong></span>
            </div>
            <div class="mp-card-body tight">
                <div class="mp-table-wrap">
                    <table class="mp-table">
                        <thead>
                            <tr>
                                <th>Ürün / Hizmet</th>
                                <th>Fatura</th>
                                <th>Tarih</th>
                                <th style="text-align:right">Miktar</th>
                                <th style="text-align:right">Birim Fiyat</th>
                                <th style="text-align:center">KDV</th>
                                <th style="text-align:right">Tutar</th>
                                <th style="text-align:right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($urun_sonuc as $r):
                                // Arama terimini highlight et
                                $urun_html = h($r['urun_adi']);
                                if ($q !== '') {
                                    $urun_html = preg_replace('/(' . preg_quote(h($q), '/') . ')/iu', '<mark style="background:#fef3c7;padding:0 2px;border-radius:2px">$1</mark>', $urun_html);
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= $urun_html ?></strong>
                                        <?php if ($r['aciklama']): ?>
                                            <div style="font-size:11px;color:#64748b;margin-top:2px"><?= h(mb_strimwidth($r['aciklama'], 0, 80, '…')) ?></div>
                                        <?php endif; ?>
                                        <?php if ($r['urun_kodu']): ?>
                                            <div style="font-size:10.5px;color:#94a3b8;font-family:monospace;margin-top:2px">Kod: <?= h($r['urun_kodu']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $r['fatura_id'] ?>" style="font-family:monospace;font-size:12px;font-weight:600;color:#0a2540">
                                            <?= h($r['fatura_no']) ?>
                                        </a>
                                    </td>
                                    <td style="font-size:12px;color:#64748b;white-space:nowrap"><?= date('d.m.Y', strtotime($r['duzenleme_tarihi'])) ?></td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums;font-size:12.5px">
                                        <?= number_format((float)$r['miktar'], 2, ',', '.') ?>
                                        <span style="color:#94a3b8;font-size:10.5px"><?= h($r['birim_kodu']) ?></span>
                                    </td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums;color:#64748b;font-size:12.5px"><?= fmt_tl((float)$r['birim_fiyat']) ?></td>
                                    <td style="text-align:center"><span class="mp-badge mp-badge-secondary" style="font-size:10.5px">%<?= (float)$r['kdv_oran'] ?></span></td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700"><?= fmt_tl((float)$r['satir_toplam']) ?></td>
                                    <td style="text-align:right">
                                        <a href="<?= SITE_URL ?>/musteri-portal/fatura-detay.php?id=<?= $r['fatura_id'] ?>" class="mp-btn mp-btn-ghost mp-btn-sm">
                                            <?= mp_icon('eye', 13) ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- SONUÇ YOK -->
    <?php if (empty($fatura_sonuc) && empty($urun_sonuc)): ?>
        <div class="mp-card">
            <div style="padding:50px 30px;text-align:center">
                <div style="font-size:56px;margin-bottom:12px">🔎</div>
                <h3 style="margin:0 0 6px">Sonuç bulunamadı</h3>
                <p style="margin:0;color:#64748b;font-size:13.5px">
                    Aradığınız kriterlere uygun kayıt bulunamadı.
                    Farklı bir arama terimi deneyin veya filtreleri temizleyin.
                </p>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php mp_render_footer(); ?>

<?php
} catch (Throwable $e) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code(500);
    $debug_mode = (isset($_GET['debug']) && $_GET['debug'] === '1')
        || (isset($user) && is_array($user) && ($user['id'] ?? 0) > 0);
    ?>
    <!DOCTYPE html>
    <html lang="tr"><head><meta charset="UTF-8"><title>Hata Olustu</title>
    <style>
    body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;padding:40px 20px;margin:0;color:#0f172a}
    .box{max-width:720px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:32px;box-shadow:0 4px 12px rgba(0,0,0,.04)}
    h1{color:#dc2626;font-size:22px;margin:0 0 10px}
    .msg{background:#fef3c7;border-left:4px solid #d97706;padding:14px 18px;border-radius:8px;margin:16px 0;font-size:14px;line-height:1.6}
    .trace{background:#0f172a;color:#a7f3d0;font-family:monospace;font-size:12px;padding:18px;border-radius:10px;overflow:auto;max-height:320px;white-space:pre-wrap;word-break:break-all}
    .hint{background:#eff6ff;padding:14px 18px;border-radius:8px;color:#1e40af;font-size:13.5px;line-height:1.6;margin-top:14px}
    a{color:#0b5cff;text-decoration:none;font-weight:600}
    .btn{display:inline-block;background:#0b5cff;color:#fff;padding:9px 18px;border-radius:8px;font-weight:600;font-size:13.5px;margin-top:16px;text-decoration:none}
    </style></head><body>
    <div class="box">
        <h1>Bu sayfada hata olustu</h1>
        <div class="msg">Teknik bir sorun tespit edildi. Bu hatayi CODEGA destek ekibiyle paylasirsaniz cozebiliriz.</div>
        <?php if ($debug_mode): ?>
            <div style="margin:20px 0">
                <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;margin-bottom:6px">HATA DETAYI</div>
                <div style="background:#fee2e2;color:#7f1d1d;padding:14px 18px;border-radius:8px;font-size:13.5px;font-family:monospace;word-break:break-all"><?= htmlspecialchars($e->getMessage()) ?></div>
            </div>
            <div style="margin:16px 0">
                <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;margin-bottom:6px">DOSYA</div>
                <div style="font-family:monospace;font-size:12px;color:#475569"><?= htmlspecialchars($e->getFile()) ?>:<strong style="color:#dc2626"><?= $e->getLine() ?></strong></div>
            </div>
            <details style="margin:16px 0">
                <summary style="cursor:pointer;font-weight:600;color:#475569;font-size:13px">Stack Trace</summary>
                <div class="trace"><?= htmlspecialchars($e->getTraceAsString()) ?></div>
            </details>
        <?php else: ?>
            <div class="hint"><strong>Detay icin:</strong> URLnin sonuna <code>?debug=1</code> ekleyip yeniden yukleyin.</div>
        <?php endif; ?>
        <div class="hint"><strong>CODEGA Destek:</strong> <a href="tel:+905320652400">0532 065 24 00</a> &middot; <a href="https://wa.me/905320652400" target="_blank">WhatsApp</a> &middot; <a href="mailto:info@codega.com.tr">info@codega.com.tr</a></div>
        <a href="/musteri-portal/index.php" class="btn">Ana Sayfaya Don</a>
    </div>
    <?php error_log(sprintf('[%s 500] %s at %s:%d', basename(__FILE__), $e->getMessage(), $e->getFile(), $e->getLine())); ?>
    </body></html>
    <?php
}
restore_error_handler();
?>
