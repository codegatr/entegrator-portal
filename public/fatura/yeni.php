<?php
require __DIR__ . '/../../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('operator');

use CodegaGib\Invoice\UblBuilder;
use CodegaGib\Invoice\Models\Party;
use CodegaGib\Invoice\Models\Address;
use CodegaGib\Invoice\Models\InvoiceLine;
use CodegaGib\Exception\InvalidInvoiceDataException;

$errors = [];
$form = [
    'mukellef_id'      => 0,
    'profil'           => UblBuilder::PROFILE_TEMEL,
    'tipi'             => UblBuilder::TYPE_SATIS,
    'duzenleme_tarihi' => date('Y-m-d'),
    'para_birimi'      => 'TRY',
    'notlar'           => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $errors['_'] = 'Güvenlik hatası. Sayfayı yenileyin.';
    } else {
        foreach ($form as $k => $v) $form[$k] = $_POST[$k] ?? $v;
        $form['mukellef_id'] = (int)$form['mukellef_id'];

        $lines_raw = $_POST['lines'] ?? [];

        // Validation
        if (!$form['mukellef_id']) $errors['mukellef_id'] = 'Müşteri seçilmedi';
        if (!$form['duzenleme_tarihi'] || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['duzenleme_tarihi'])) {
            $errors['duzenleme_tarihi'] = 'Geçersiz tarih';
        }

        $clean_lines = [];
        foreach ($lines_raw as $ln) {
            $adi = trim($ln['urun_adi'] ?? '');
            if ($adi === '') continue;
            $miktar = (float)str_replace(',', '.', $ln['miktar'] ?? '0');
            $fiyat  = (float)str_replace(',', '.', $ln['birim_fiyat'] ?? '0');
            if ($miktar <= 0 || $fiyat < 0) continue;
            $clean_lines[] = [
                'urun_adi'    => $adi,
                'miktar'      => $miktar,
                'birim_kodu'  => $ln['birim_kodu'] ?? 'C62',
                'birim_fiyat' => $fiyat,
                'iskonto'     => (float)str_replace(',', '.', $ln['iskonto'] ?? '0'),
                'kdv_oran'    => (float)str_replace(',', '.', $ln['kdv_oran'] ?? '20'),
            ];
        }
        if (empty($clean_lines)) $errors['lines'] = 'En az 1 geçerli satır olmalı';

        if (!$errors) {
            // Müşteriyi çek
            $mq = $pdo->prepare("SELECT * FROM mukellefler WHERE id=? AND aktif=1");
            $mq->execute([$form['mukellef_id']]);
            $musteri = $mq->fetch();
            if (!$musteri) { $errors['mukellef_id'] = 'Müşteri bulunamadı'; }
        }

        if (!$errors) {
            try {
                // ═══ CodegaGib kütüphanesini KULLAN ═══
                $supplier = new Party(
                    name: FIRMA_ADI,
                    taxId: FIRMA_VKN,
                    address: Address::tr(
                        city: FIRMA_IL,
                        district: FIRMA_ILCE,
                        street: FIRMA_ADRES,
                        postalCode: FIRMA_POSTA_KODU
                    ),
                    taxOffice: FIRMA_VERGI_DAIRESI,
                    email: FIRMA_EMAIL,
                    phone: FIRMA_TELEFON,
                    website: FIRMA_WEBSITE,
                );

                $customer = new Party(
                    name: $musteri['unvan'],
                    taxId: $musteri['vkn_tckn'],
                    address: Address::tr(
                        city: $musteri['il'],
                        district: $musteri['ilce'] ?: null,
                        street: $musteri['adres'] ?: null,
                        postalCode: $musteri['posta_kodu'] ?: null,
                    ),
                    taxOffice: $musteri['vergi_dairesi'] ?: ($musteri['vkn_tip'] === 'VKN' ? $musteri['vergi_dairesi'] : null),
                    email: $musteri['email'] ?: null,
                    phone: $musteri['telefon'] ?: null,
                    firstName: $musteri['adi'] ?: null,
                    familyName: $musteri['soyadi'] ?: null,
                );

                $fatura_no = next_fatura_no($pdo);

                $builder = (new UblBuilder())
                    ->setProfile($form['profil'])
                    ->setInvoiceType($form['tipi'])
                    ->setInvoiceNumber($fatura_no)
                    ->setIssueDate($form['duzenleme_tarihi'])
                    ->setCurrency($form['para_birimi'])
                    ->setSupplier($supplier)
                    ->setCustomer($customer);

                if ($form['notlar']) $builder->setNotes($form['notlar']);

                $line_id = 1;
                foreach ($clean_lines as $ln) {
                    $builder->addLine(new InvoiceLine(
                        id: $line_id++,
                        itemName: $ln['urun_adi'],
                        quantity: $ln['miktar'],
                        unitPrice: $ln['birim_fiyat'],
                        vatRate: $ln['kdv_oran'],
                        unitCode: $ln['birim_kodu'],
                        lineDiscount: $ln['iskonto'],
                    ));
                }

                $xml = $builder->build();
                $summary = $builder->summary();
                $totals  = $builder->calculateTotals();

                // DB'ye kaydet
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("
                        INSERT INTO faturalar
                          (fatura_no, ettn, mukellef_id, profil, tipi, duzenleme_tarihi, duzenleme_saati,
                           para_birimi, matrah, kdv_toplam, genel_toplam, notlar, durum, kullanici_id)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'hazir',?)
                    ")->execute([
                        $fatura_no, $summary['ettn'], $musteri['id'],
                        $form['profil'], $form['tipi'],
                        $form['duzenleme_tarihi'], date('H:i:s'),
                        $form['para_birimi'],
                        $totals['line_total'], $totals['tax_total'], $totals['grand_total'],
                        $form['notlar'] ?: null,
                        auth_user()['id'],
                    ]);
                    $fatura_id = (int)$pdo->lastInsertId();

                    $ls = $pdo->prepare("
                        INSERT INTO fatura_satirlari
                          (fatura_id, sira, urun_adi, miktar, birim_kodu, birim_fiyat, iskonto,
                           kdv_oran, matrah, kdv_tutar, satir_toplam)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)
                    ");
                    $sira = 1;
                    foreach ($clean_lines as $ln) {
                        $mat = round(($ln['miktar'] * $ln['birim_fiyat']) - $ln['iskonto'], 2);
                        if ($mat < 0) $mat = 0;
                        $tax = round($mat * $ln['kdv_oran'] / 100, 2);
                        $ls->execute([
                            $fatura_id, $sira++,
                            $ln['urun_adi'], $ln['miktar'], $ln['birim_kodu'], $ln['birim_fiyat'], $ln['iskonto'],
                            $ln['kdv_oran'], $mat, $tax, $mat + $tax,
                        ]);
                    }

                    // XML'i diske yaz
                    $xml_file = xml_store_path($summary['ettn'], $fatura_no);
                    file_put_contents($xml_file, $xml);
                    $rel_path = str_replace(STORAGE_PATH . '/', '', $xml_file);
                    $pdo->prepare("UPDATE faturalar SET xml_path=? WHERE id=?")->execute([$rel_path, $fatura_id]);

                    // Log
                    $pdo->prepare("
                        INSERT INTO fatura_log (fatura_id, onceki_durum, yeni_durum, aciklama, kullanici_id, ip)
                        VALUES (?, NULL, 'hazir', 'Fatura oluşturuldu ve UBL-TR XML üretildi', ?, ?)
                    ")->execute([$fatura_id, auth_user()['id'], client_ip()]);

                    audit_log($pdo, 'fatura.create', "no=$fatura_no tutar={$totals['grand_total']}", null, "fatura:$fatura_id");

                    $pdo->commit();
                    flash_set('success', "Fatura başarıyla oluşturuldu: $fatura_no (" . fmt_tl($totals['grand_total']) . ")");
                    redirect(SITE_URL . '/fatura/detay.php?id=' . $fatura_id);
                } catch (\Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } catch (InvalidInvoiceDataException $e) {
                $errors['_'] = 'Fatura verisi hatası: ' . $e->getMessage();
            } catch (\Exception $e) {
                $errors['_'] = 'Beklenmeyen hata: ' . $e->getMessage();
                error_log('fatura.create: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }
    }
}

render_header('Yeni Fatura', 'yeni');
?>

<div class="page-head">
    <div>
        <h1>Yeni Fatura Oluştur</h1>
        <div class="sub">Form tamamlanınca UBL-TR 2.1 XML otomatik üretilir</div>
    </div>
    <div class="page-actions">
        <a href="<?= SITE_URL ?>/fatura/liste.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Listeye Dön</a>
    </div>
</div>

<?php if (!empty($errors['_'])): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= h($errors['_']) ?></div>
<?php endif; ?>

<form method="POST" id="invoice-form">
    <?= csrf_field() ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px">
        <div>
            <!-- ═══ Müşteri ═══ -->
            <div class="card">
                <div class="card-h"><i class="fas fa-user"></i> Müşteri (Alıcı)</div>
                <div class="card-b">
                    <div class="fg" style="position:relative">
                        <label>Müşteri Ara / Seç *</label>
                        <input type="text" id="mukellef-search" placeholder="Ünvan, VKN veya TCKN ile ara..." autocomplete="off">
                        <input type="hidden" name="mukellef_id" id="mukellef-id" value="<?= (int)$form['mukellef_id'] ?>">
                        <div id="mukellef-dd" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:10;background:#fff;border:1px solid #e5e7eb;border-radius:8px;margin-top:4px;max-height:280px;overflow-y:auto;box-shadow:0 6px 18px rgba(0,0,0,.1)">
                        </div>
                        <?php if(!empty($errors['mukellef_id'])): ?><div class="err"><?= h($errors['mukellef_id']) ?></div><?php endif; ?>
                        <div class="hint">Henüz ekli değilse <a href="<?= SITE_URL ?>/musteri/duzenle.php?new=1" target="_blank">yeni müşteri ekle</a></div>
                    </div>
                </div>
            </div>

            <!-- ═══ Satırlar ═══ -->
            <div class="card">
                <div class="card-h">
                    <i class="fas fa-list-ol"></i> Fatura Satırları
                    <button type="button" id="line-add" class="btn btn-primary btn-sm" style="margin-left:auto"><i class="fas fa-plus"></i> Satır Ekle</button>
                </div>
                <div class="card-b">
                    <div class="line-editor">
                        <div style="display:grid;grid-template-columns:40px 1fr 100px 80px 110px 90px 100px 40px;gap:8px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;padding:0 8px">
                            <div style="text-align:center">#</div>
                            <div>Ürün / Hizmet</div>
                            <div>Miktar</div>
                            <div>Birim</div>
                            <div>Birim Fiyat (₺)</div>
                            <div>İskonto</div>
                            <div>KDV</div>
                            <div></div>
                        </div>
                        <div id="line-editor"></div>
                        <?php if(!empty($errors['lines'])): ?><div class="err" style="margin-top:4px"><?= h($errors['lines']) ?></div><?php endif; ?>
                    </div>

                    <div class="line-totals">
                        <div>
                            <div class="t">Matrah</div>
                            <div class="v" id="tot-matrah">0,00 ₺</div>
                        </div>
                        <div>
                            <div class="t">KDV Toplam</div>
                            <div class="v" id="tot-kdv">0,00 ₺</div>
                        </div>
                        <div>
                            <div class="t">Genel Toplam</div>
                            <div class="v" id="tot-toplam">0,00 ₺</div>
                        </div>
                        <div>
                            <div class="t">Satır Sayısı</div>
                            <div class="v" id="tot-satir">0</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ Notlar ═══ -->
            <div class="card">
                <div class="card-h"><i class="fas fa-sticky-note"></i> Notlar (opsiyonel)</div>
                <div class="card-b">
                    <div class="fg">
                        <textarea name="notlar" rows="2" maxlength="1000" placeholder="Faturada görünür not..."><?= h($form['notlar']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Sağ sütun ═══ -->
        <div>
            <div class="card">
                <div class="card-h"><i class="fas fa-cog"></i> Fatura Ayarları</div>
                <div class="card-b">
                    <div class="fg">
                        <label>Düzenlenme Tarihi *</label>
                        <input type="date" name="duzenleme_tarihi" value="<?= h($form['duzenleme_tarihi']) ?>" required>
                        <?php if(!empty($errors['duzenleme_tarihi'])): ?><div class="err"><?= h($errors['duzenleme_tarihi']) ?></div><?php endif; ?>
                    </div>
                    <div class="fg">
                        <label>Profil</label>
                        <select name="profil">
                            <option value="TEMELFATURA" <?= $form['profil']==='TEMELFATURA'?'selected':'' ?>>TEMELFATURA (kabul/red yok)</option>
                            <option value="TICARIFATURA" <?= $form['profil']==='TICARIFATURA'?'selected':'' ?>>TICARIFATURA</option>
                            <option value="EARSIVFATURA" <?= $form['profil']==='EARSIVFATURA'?'selected':'' ?>>EARSIVFATURA (son kullanıcı)</option>
                            <option value="IHRACAT" <?= $form['profil']==='IHRACAT'?'selected':'' ?>>IHRACAT</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Fatura Türü</label>
                        <select name="tipi">
                            <option value="SATIS" <?= $form['tipi']==='SATIS'?'selected':'' ?>>SATIS</option>
                            <option value="IADE" <?= $form['tipi']==='IADE'?'selected':'' ?>>IADE</option>
                            <option value="TEVKIFAT" <?= $form['tipi']==='TEVKIFAT'?'selected':'' ?>>TEVKIFAT</option>
                            <option value="ISTISNA" <?= $form['tipi']==='ISTISNA'?'selected':'' ?>>ISTISNA</option>
                            <option value="OZELMATRAH" <?= $form['tipi']==='OZELMATRAH'?'selected':'' ?>>OZELMATRAH</option>
                            <option value="IHRACKAYITLI" <?= $form['tipi']==='IHRACKAYITLI'?'selected':'' ?>>IHRACKAYITLI</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Para Birimi</label>
                        <select name="para_birimi">
                            <option value="TRY" <?= $form['para_birimi']==='TRY'?'selected':'' ?>>TRY (₺)</option>
                            <option value="USD" <?= $form['para_birimi']==='USD'?'selected':'' ?>>USD ($)</option>
                            <option value="EUR" <?= $form['para_birimi']==='EUR'?'selected':'' ?>>EUR (€)</option>
                            <option value="GBP" <?= $form['para_birimi']==='GBP'?'selected':'' ?>>GBP (£)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card" style="background:#fff7ed;border-color:#fde68a">
                <div class="card-b" style="font-size:12.5px;color:#9a3412;line-height:1.6">
                    <strong><i class="fas fa-info-circle"></i> Bilgi</strong><br>
                    Fatura oluşturulunca <strong>UBL-TR 2.1 uyumlu XML</strong> otomatik üretilir.
                    <br><br>
                    Henüz <strong>mali mühürle imzalama</strong> ve <strong>GİB'e gönderim</strong> aktif değil (v0.2 ve v0.3 sprintlerinde eklenecek).
                    <br><br>
                    Şu an fatura durumu <strong>"hazır"</strong> olarak kalır, sonra imzalanıp gönderilir.
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width:100%">
                <i class="fas fa-save"></i> Fatura Oluştur
            </button>
        </div>
    </div>
</form>

<?php render_footer(); ?>
