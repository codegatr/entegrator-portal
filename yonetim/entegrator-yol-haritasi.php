<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';
require INCLUDES_PATH . '/layout.php';

auth_require_role('admin');

// ═══ Yol haritası checklist - ayarlar tablosunda saklanacak ═══
// Key: entegrator.yol.{id} = 1 (tamamlandi) veya 0 (bekliyor)

// Durum değiştirme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle' && csrf_verify($_POST['csrf'] ?? '')) {
    $item_id = preg_replace('/[^a-z0-9_]/i', '', $_POST['item_id'] ?? '');
    if ($item_id) {
        $key = 'entegrator.yol.' . $item_id;
        $mevcut = ayar_get($pdo, $key, '0');
        $yeni = $mevcut === '1' ? '0' : '1';
        ayar_set($pdo, $key, $yeni);
        audit_log($pdo, 'entegrator.toggle', "item=$item_id durum=$yeni");
    }
    redirect(SITE_URL . '/yonetim/entegrator-yol-haritasi.php');
}

// FAZLAR VE GÖREVLER
$fazlar = [
    'faz0' => [
        'baslik'   => 'FAZ 0 — Ön Hazırlık & Yasal Yapı',
        'sure'     => 'Ay 0-2',
        'renk'     => '#dc2626',
        'icon'     => '📋',
        'aciklama' => 'Özel entegratör olabilmek için ÖNCE yapılması gereken yasal/idari işlemler',
        'items' => [
            ['as_donusum',      'A.Ş. Dönüşümü', 'CODEGA Yazılım A.Ş. (Limited → A.Ş.)', 'Mali Müşavir', '₺15.000-25.000', 'high'],
            ['sermaye_5m',      'Minimum 5M TL Sermaye', 'GİB şartı. Öz sermaye + aktif büyüklüğü denetlenir', 'Yunus Aksoy', '5.000.000 TL', 'critical'],
            ['mali_muhur_test', 'TÜBİTAK BİLGEM Test Mali Mührü', 'Test ortamında kullanılacak mali mühür sertifikası', 'TÜBİTAK BİLGEM', '₺500 + işlem süresi', 'high'],
            ['ticaret_sicil',   'Ticaret Sicil Kaydı Güncel', 'Faaliyet konusu: Bilişim Hizmetleri (62.xx NACE)', 'Ticaret Odası', '₺1.000-2.500', 'high'],
            ['kvkk_verbis',     'KVKK VERBİS Kaydı', 'Kişisel Verileri Koruma Kurulu kaydı zorunludur', 'Dâhili işlem', '₺0', 'high'],
            ['vergi_levha',     'Vergi Levhası (bilişim)', 'Vergi dairesine faaliyet kodu bildirimi', 'Mali Müşavir', '₺0', 'mid'],
            ['kep_adresi',      'KEP Adresi', 'Kayıtlı Elektronik Posta (PTT/Türkkep)', 'PTT veya TürkKEP', '₺150-300/yıl', 'mid'],
        ]
    ],
    'faz1' => [
        'baslik'   => 'FAZ 1 — Sertifikalar & İnsan Kaynağı',
        'sure'     => 'Ay 2-8',
        'renk'     => '#d97706',
        'icon'     => '🎓',
        'aciklama' => 'GİB\'in zorunlu tuttuğu sertifikalar + sertifikalı personel',
        'items' => [
            ['iso_27001',       'ISO/IEC 27001 Sertifikası', 'Bilgi Güvenliği Yönetim Sistemi (ZORUNLU)', 'TÜRKAK akreditasyon belgeli firma', '₺60.000-150.000', 'critical'],
            ['iso_22301',       'ISO 22301 İş Sürekliliği', 'Business Continuity Management (ZORUNLU)', 'Sertifikasyon firması', '₺40.000-80.000', 'high'],
            ['iso_20000',       'ISO/IEC 20000-1 ITIL', 'BT Hizmet Yönetimi (Bu 3 sertifikadan 1\'i yeterli fakat çoğu gerekir)', 'Sertifikasyon firması', '₺50.000-100.000', 'high'],
            ['itil_personel',   'En Az 1 ITIL Sertifikalı Personel', 'ITIL 4 Foundation veya üstü eğitim + sınav', 'AXELOS / Peoplecert', '₺8.000-15.000/kişi', 'critical'],
            ['isms_ekibi',      'Bilgi Güvenliği Ekibi', 'Min. 1 CISO + 1 BGYS sorumlusu (part-time olabilir)', 'İşe alım/danışman', '₺35.000-60.000/ay', 'high'],
            ['iso_danisman',    'ISO Danışman Firma', 'ISO sertifikalarına hazırlık için 4-6 ay danışmanlık', 'Sertifikasyon danışmanı', '₺30.000-60.000', 'mid'],
            ['kvkk_sorumlusu',  'KVKK Veri Sorumlusu', 'Resmi atama (Şirket yetkilisi olabilir)', 'Dâhili', '₺0', 'mid'],
        ]
    ],
    'faz2' => [
        'baslik'   => 'FAZ 2 — Teknik Altyapı & Yazılım',
        'sure'     => 'Ay 3-10',
        'renk'     => '#0284c7',
        'icon'     => '⚙️',
        'aciklama' => 'GİB standartlarına uygun yazılım ve altyapı geliştirme',
        'items' => [
            ['core_lib',          'Core Library (entegrator-gib)', 'UBL-TR 1.2.1 XML üretim kütüphanesi', 'CODEGA', '✓ v0.1 yayında', 'done'],
            ['xades_signing',     'XAdES-BES Dijital İmza', 'Mali mühür ile otomatik imzalama', 'CODEGA', 'v0.2 sprintte', 'high'],
            ['portal_v1',         'Müşteri Web Portal', 'Fatura yönetimi, destek, arama, duyuru', 'CODEGA', '✓ v1.4.0 yayında', 'done'],
            ['rest_api',          'RESTful API', 'Müşterilerin kendi sistemlerini entegre etmesi için', 'CODEGA', 'v0.3\'te', 'high'],
            ['soap_gib',          'GİB SOAP/REST Client', 'efaturatest.gib.gov.tr ile iletişim', 'CODEGA', 'v0.3\'te', 'critical'],
            ['vds_sunucu',        'VDS / Private Cloud Sunucu', 'DirectAdmin shared hosting yetmeyecek', 'Server sağlayıcı', '₺4.000-12.000/ay', 'critical'],
            ['active_active',     'Active-Active Mimari', 'İki farklı lokasyonda senkronize sunucu', 'Turkcell/Garanti/TurkTelekom', '₺15.000-40.000/ay', 'high'],
            ['drc_felaket',       'DRC (Felaket Kurtarma Merkezi)', 'İkincil data center, RPO<1saat, RTO<4saat', 'İkinci VDS + yedekleme', '₺8.000-20.000/ay', 'critical'],
            ['yedekleme',         'Otomatik Yedekleme Sistemi', 'Her 15 dakikada bir differential backup', 'Backup yazılımı', '₺2.000-5.000/ay', 'high'],
            ['monitoring',        'Monitoring & Alert Sistemi', '7/24 izleme, uptime %99.99 taahhüdü', 'Grafana/Zabbix/UptimeRobot', '₺1.500-4.000/ay', 'high'],
            ['pen_test',          'Penetrasyon Testi (Yıllık)', 'Bağımsız firmalar tarafından güvenlik testi', 'Güvenlik firması', '₺40.000-100.000/yıl', 'high'],
            ['ssl_wildcard',      'SSL Wildcard + EV Sertifika', 'Tüm alt alan adları + Extended Validation', 'DigiCert/Sectigo', '₺8.000-25.000/yıl', 'mid'],
        ]
    ],
    'faz3' => [
        'baslik'   => 'FAZ 3 — GİB Test & Değerlendirme',
        'sure'     => 'Ay 8-14',
        'renk'     => '#7c3aed',
        'icon'     => '🔍',
        'aciklama' => 'GİB sistemlerinde test ortamı başvurusu + test faturaları',
        'items' => [
            ['test_ortami',       'GİB Test Ortamı Başvurusu', 'efaturatest.gib.gov.tr erişimi talebi', 'GİB BİS', 'Süre: 2-4 hafta', 'critical'],
            ['test_mukellef',     'Test Mükellef Hesabı', 'Test ortamında sahte firma + mali mühür', 'GİB BİS', '₺0', 'high'],
            ['tubitak_rapor',     'TÜBİTAK BİLGEM Uyum Raporu', 'Mali mühür imzalama süreçleri uygunluk raporu', 'TÜBİTAK BİLGEM', '₺10.000-20.000', 'critical'],
            ['test_fatura_100',   'En Az 100 Test Faturası', 'e-Fatura, e-Arşiv, e-İrsaliye, e-SMM, e-MM', 'CODEGA', 'Süreç gereği', 'critical'],
            ['senaryo_test',      'GİB Test Senaryoları', 'İptal, iade, ihracat, istisna fatura vb.', 'CODEGA', 'Süreç gereği', 'critical'],
            ['test_zarf',         'Zarf Testleri', 'Gelen/giden belge zarfları (systemEnvelope)', 'CODEGA', 'Süreç gereği', 'high'],
            ['gib_audit',         'GİB Denetim', 'Başkanlık temsilcilerinin altyapı denetimi', 'GİB', 'Süreç gereği', 'critical'],
        ]
    ],
    'faz4' => [
        'baslik'   => 'FAZ 4 — Resmi Başvuru & Lisans',
        'sure'     => 'Ay 14-18',
        'renk'     => '#059669',
        'icon'     => '🏆',
        'aciklama' => 'Özel Entegratör lisansı için resmi başvuru',
        'items' => [
            ['dilekce',           'Dilekçe ve Başvuru Dosyası', 'Resmi başvuru evrakları (yaklaşık 120 sayfa)', 'CODEGA + Hukuk', '₺5.000-10.000', 'critical'],
            ['taahhutname',       'Taahhütname Dosyası', '7/24 hizmet, veri saklama, denetim taahhüdü', 'CODEGA yönetimi', '₺0', 'critical'],
            ['hizmet_sozlesmesi', 'Müşteri Sözleşme Taslağı', 'Mükellef ile imzalanacak standart sözleşme', 'Hukuk danışmanı', '₺8.000-15.000', 'high'],
            ['baski_mathur',      'Üretim Mali Mühür', 'Canlı ortamda kullanılacak imzalama sertifikası', 'TÜBİTAK BİLGEM', '₺1.000-2.500', 'critical'],
            ['bis_basvuru',       'BİS (GİB) Başvuru', 'ebelgebasvuru.gib.gov.tr üzerinden resmi başvuru', 'CODEGA yönetimi', '₺0', 'critical'],
            ['gib_onay',          'GİB Onay ve Lisans', 'Özel entegratör yetki belgesi', 'GİB', 'Süreç: 2-6 ay', 'critical'],
            ['listeye_girme',     'efatura.gov.tr Listesi', 'Resmi entegratör listesinde yayınlanma', 'GİB', '₺0', 'critical'],
        ]
    ],
    'faz5' => [
        'baslik'   => 'FAZ 5 — Canlı Ortama Geçiş & İşletme',
        'sure'     => 'Ay 18-24',
        'renk'     => '#0a2540',
        'icon'     => '🚀',
        'aciklama' => 'Lisans alındıktan sonra müşteri kabulü ve operasyon',
        'items' => [
            ['production_deploy', 'Production Deployment', 'Canlı sunucu devreye alma', 'CODEGA DevOps', 'Dâhili', 'mid'],
            ['ilk_musteri',       'İlk 10 Müşteri (Soft Launch)', 'Mevcut CODEGA müşterilerinden pilot grup', 'CODEGA Satış', '₺0 gelir paylaşımı', 'mid'],
            ['satis_ekibi',       'Satış & Pazarlama Ekibi', 'Min. 2 satış + 1 pazarlama uzmanı', 'İşe alım', '₺45.000-80.000/ay', 'mid'],
            ['call_center',       'Çağrı Merkezi / Destek Hattı', '7/24 müşteri destek altyapısı', 'CODEGA + dışkaynak', '₺25.000-60.000/ay', 'high'],
            ['faturalandirma',    'Faturalandırma & Tahsilat', 'Kontör satış + otomatik tahsilat sistemi', 'CODEGA ERP', 'Dâhili', 'mid'],
            ['scaling',           'Ölçeklenebilir Altyapı', '1000+ müşteri, 1M+ fatura/ay hazırlığı', 'DevOps', 'Kapasite planı', 'mid'],
        ]
    ],
];

// Mevcut durumları oku
$tamamlanan_sayilar = [];
$toplam_sayilar = [];
$genel_toplam = 0;
$genel_tamamlanan = 0;

foreach ($fazlar as $fk => $fv) {
    $tamamlanan = 0;
    foreach ($fv['items'] as $item) {
        $key = 'entegrator.yol.' . $item[0];
        $durum = ayar_get($pdo, $key, '0');
        // v(durum = 'done' or key=1) tamamlanmış
        $is_done = ($durum === '1') || (isset($item[5]) && $item[5] === 'done');
        if ($is_done) $tamamlanan++;
    }
    $tamamlanan_sayilar[$fk] = $tamamlanan;
    $toplam_sayilar[$fk] = count($fv['items']);
    $genel_tamamlanan += $tamamlanan;
    $genel_toplam += count($fv['items']);
}

$genel_yuzde = $genel_toplam > 0 ? round(($genel_tamamlanan / $genel_toplam) * 100) : 0;

render_header('Entegratör Yol Haritası', 'entegrator-yol-haritasi');
?>

<div class="page-head">
    <div>
        <h1>GİB Özel Entegratör Yol Haritası</h1>
        <div class="sub">CODEGA'nın özel entegratör lisansı alma sürecini takip eden tam yol haritası</div>
    </div>
</div>

<!-- GENEL İLERLEME -->
<div class="card" style="background:linear-gradient(135deg,#0a2540 0%,#0b5cff 100%);color:#fff;border:none;margin-bottom:20px">
    <div style="padding:24px 28px">
        <div style="display:flex;gap:24px;align-items:center;flex-wrap:wrap">
            <div style="flex:1;min-width:240px">
                <div style="font-size:12px;color:rgba(255,255,255,0.75);text-transform:uppercase;letter-spacing:0.8px;font-weight:600;margin-bottom:4px">GENEL İLERLEME</div>
                <div style="font-size:36px;font-weight:800;letter-spacing:-1px"><?= $genel_yuzde ?>% · <?= $genel_tamamlanan ?>/<?= $genel_toplam ?></div>
                <div style="font-size:13px;color:rgba(255,255,255,0.85);margin-top:4px">
                    12-24 aylık yol haritası · Hedef: Q4 2027 GİB lisansı
                </div>

                <!-- Progress bar -->
                <div style="margin-top:16px;height:10px;background:rgba(255,255,255,0.2);border-radius:5px;overflow:hidden">
                    <div style="width:<?= $genel_yuzde ?>%;height:100%;background:linear-gradient(90deg,#34d399 0%,#06d6a0 100%);transition:width 1s ease"></div>
                </div>
            </div>

            <!-- Faz özetleri -->
            <div style="display:grid;grid-template-columns:repeat(3,auto);gap:16px;font-size:12px">
                <?php foreach ($fazlar as $fk => $fv):
                    $yuzde = $toplam_sayilar[$fk] > 0 ? round(($tamamlanan_sayilar[$fk] / $toplam_sayilar[$fk]) * 100) : 0;
                ?>
                    <div style="text-align:center">
                        <div style="font-size:22px;margin-bottom:2px"><?= $fv['icon'] ?></div>
                        <div style="font-weight:700;font-size:13px">FAZ <?= substr($fk, 3) ?></div>
                        <div style="color:rgba(255,255,255,0.7);font-size:11px"><?= $tamamlanan_sayilar[$fk] ?>/<?= $toplam_sayilar[$fk] ?> · <?= $yuzde ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- KRİTİK UYARI -->
<?php if ($genel_yuzde < 50): ?>
<div class="alert alert-warning">
    <?= icon('alert') ?>
    <div>
        <strong>Önemli:</strong> Özel Entegratör olmak ortalama <strong>12-24 ay</strong> süren ve <strong>₺2-5 Milyon TL</strong> yatırım gerektiren bir süreçtir.
        Bu süreç paralel yürütülmelidir (A.Ş. dönüşümü, ISO sertifikalar, yazılım, GİB başvurusu aynı anda). Minimum <strong>5 Milyon TL sermaye</strong> şartı vardır.
    </div>
</div>
<?php endif; ?>

<!-- FAZ KARTLARI -->
<?php foreach ($fazlar as $fk => $fv):
    $yuzde = $toplam_sayilar[$fk] > 0 ? round(($tamamlanan_sayilar[$fk] / $toplam_sayilar[$fk]) * 100) : 0;
?>
    <div class="card" style="border-left:4px solid <?= $fv['renk'] ?>;margin-bottom:16px">
        <div style="padding:18px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0">
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                <div style="font-size:32px"><?= $fv['icon'] ?></div>
                <div style="flex:1;min-width:240px">
                    <h2 style="margin:0;font-size:17px;font-weight:700;color:<?= $fv['renk'] ?>"><?= h($fv['baslik']) ?></h2>
                    <div style="font-size:12.5px;color:#64748b;margin-top:3px">
                        <strong><?= h($fv['sure']) ?></strong> · <?= h($fv['aciklama']) ?>
                    </div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:22px;font-weight:800;color:<?= $fv['renk'] ?>"><?= $yuzde ?>%</div>
                    <div style="font-size:11.5px;color:#64748b"><?= $tamamlanan_sayilar[$fk] ?>/<?= $toplam_sayilar[$fk] ?> görev</div>
                </div>
            </div>
            <!-- Faz progress -->
            <div style="margin-top:10px;height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden">
                <div style="width:<?= $yuzde ?>%;height:100%;background:<?= $fv['renk'] ?>;transition:width .8s ease"></div>
            </div>
        </div>

        <div style="padding:4px 0">
            <?php foreach ($fv['items'] as $item):
                [$id, $baslik, $aciklama, $sorumlu, $maliyet, $oncelik] = $item;
                $key = 'entegrator.yol.' . $id;
                $db_durum = ayar_get($pdo, $key, '0');
                // "done" hardcoded olanlar veya DB'de 1 olanlar tamamlanmış
                $is_done = ($db_durum === '1') || $oncelik === 'done';

                $oncelik_badge = match($oncelik) {
                    'critical' => ['🔴 KRİTİK', '#fee2e2', '#7f1d1d'],
                    'high'     => ['🟠 Yüksek', '#fef3c7', '#78350f'],
                    'mid'      => ['🔵 Orta', '#dbeafe', '#1e40af'],
                    'done'     => ['✅ TAMAM', '#d1fae5', '#065f46'],
                    default    => ['', '', ''],
                };
            ?>
                <div style="padding:14px 22px;border-top:1px solid #f1f5f9;<?= $is_done ? 'background:#f0fdf4' : '' ?>">
                    <div style="display:flex;gap:12px;align-items:flex-start">
                        <!-- Checkbox -->
                        <?php if ($oncelik !== 'done'): ?>
                            <form method="POST" style="flex-shrink:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="item_id" value="<?= h($id) ?>">
                                <button type="submit" style="width:24px;height:24px;border-radius:6px;border:2px solid <?= $is_done ? '#059669' : '#cbd5e1' ?>;background:<?= $is_done ? '#059669' : '#fff' ?>;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;padding:0;margin-top:2px">
                                    <?= $is_done ? '✓' : '' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <div style="width:24px;height:24px;border-radius:6px;background:#059669;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;margin-top:2px;flex-shrink:0">✓</div>
                        <?php endif; ?>

                        <div style="flex:1;min-width:0">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px;flex-wrap:wrap">
                                <strong style="font-size:14px;color:<?= $is_done?'#065f46':'#0f172a' ?>;<?= $is_done?'text-decoration:line-through;opacity:0.7':'' ?>"><?= h($baslik) ?></strong>
                                <span style="background:<?= $oncelik_badge[1] ?>;color:<?= $oncelik_badge[2] ?>;padding:2px 8px;border-radius:10px;font-size:10.5px;font-weight:700"><?= $oncelik_badge[0] ?></span>
                            </div>
                            <div style="font-size:12.5px;color:#64748b;line-height:1.5"><?= h($aciklama) ?></div>
                            <div style="font-size:11.5px;color:#94a3b8;margin-top:6px;display:flex;gap:14px;flex-wrap:wrap">
                                <span>👤 <strong style="color:#475569"><?= h($sorumlu) ?></strong></span>
                                <span>💰 <strong style="color:#475569"><?= h($maliyet) ?></strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<!-- TAHMİNI MALİYETLER VE SÜRE -->
<div class="card">
    <div class="card-head">
        <?= icon('chart') ?>
        <h3>Toplam Tahmini Yatırım</h3>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:20px">
            <div style="padding:18px;background:#fef3c7;border-radius:10px;border-left:4px solid #d97706">
                <div style="font-size:11px;color:#78350f;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">ŞİRKET SERMAYESİ</div>
                <div style="font-size:24px;font-weight:800;color:#78350f;margin-top:4px">₺5.000.000</div>
                <div style="font-size:11.5px;color:#78350f;margin-top:2px">Minimum öz sermaye (zorunlu)</div>
            </div>
            <div style="padding:18px;background:#dbeafe;border-radius:10px;border-left:4px solid #0284c7">
                <div style="font-size:11px;color:#1e40af;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">BİR KEREDE GİDER</div>
                <div style="font-size:24px;font-weight:800;color:#1e40af;margin-top:4px">~₺500.000</div>
                <div style="font-size:11.5px;color:#1e40af;margin-top:2px">ISO + hukuk + danışmanlık + kurulum</div>
            </div>
            <div style="padding:18px;background:#ede9fe;border-radius:10px;border-left:4px solid #7c3aed">
                <div style="font-size:11px;color:#4c1d95;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">AYLIK İŞLETME</div>
                <div style="font-size:24px;font-weight:800;color:#4c1d95;margin-top:4px">~₺80.000-200.000</div>
                <div style="font-size:11.5px;color:#4c1d95;margin-top:2px">Sunucu + ekip + destek + sertifika yenileme</div>
            </div>
            <div style="padding:18px;background:#d1fae5;border-radius:10px;border-left:4px solid #059669">
                <div style="font-size:11px;color:#065f46;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">SÜREÇ</div>
                <div style="font-size:24px;font-weight:800;color:#065f46;margin-top:4px">12-24 Ay</div>
                <div style="font-size:11.5px;color:#065f46;margin-top:2px">Paralel yürütme ile optimize edilebilir</div>
            </div>
        </div>
    </div>
</div>

<!-- FAYDALI LINKLER -->
<div class="card">
    <div class="card-head">
        <?= icon('folder') ?>
        <h3>Resmi Kaynaklar ve Başvuru Adresleri</h3>
    </div>
    <div class="card-body" style="padding:10px 22px">
        <?php
        $linkler = [
            ['🏛️', 'GİB e-Belge Başvuru', 'https://ebelgebasvuru.gib.gov.tr/entegrasyon', 'Resmi entegratör başvuru ekranı'],
            ['📋', 'GİB e-Fatura Mevzuat', 'https://ebelge.gib.gov.tr/efaturaozelentegratorluk.html', 'Özel entegrasyon teknik kılavuzu ve listesi'],
            ['📜', '509 Sıra No.lu VUK Tebliğ', 'https://www.mevzuat.gov.tr/mevzuat?MevzuatNo=33905&MevzuatTur=9&MevzuatTertip=5', 'Entegratör şartlarının yasal dayanağı'],
            ['🔐', 'TÜBİTAK BİLGEM Kamu SM', 'https://kamusm.bilgem.tubitak.gov.tr/', 'Mali mühür ve uyum raporu başvurusu'],
            ['📊', 'TÜRKAK Akredite Kuruluşlar', 'https://www.turkak.org.tr/', 'ISO sertifikalarını verecek akredite firmalar'],
            ['🏢', 'KVKK VERBİS', 'https://verbis.kvkk.gov.tr/', 'Veri sorumlusu kayıt sistemi'],
            ['📧', 'TürkKEP (KEP Adresi)', 'https://www.turkkep.com.tr/', 'Kayıtlı Elektronik Posta'],
            ['🇹🇷', 'efatura.gov.tr Entegratör Listesi', 'https://ebelge.gib.gov.tr/efaturaozelentegratorluk.html', 'Mevcut entegratörler (rakip analizi için)'],
        ];
        foreach ($linkler as [$icon, $baslik, $url, $aciklama]):
        ?>
            <a href="<?= h($url) ?>" target="_blank" rel="noopener" style="display:flex;padding:12px 0;border-bottom:1px solid #f1f5f9;text-decoration:none;gap:14px;align-items:center">
                <div style="font-size:22px;flex-shrink:0"><?= $icon ?></div>
                <div style="flex:1">
                    <div style="font-size:13.5px;font-weight:600;color:#0f172a"><?= h($baslik) ?></div>
                    <div style="font-size:11.5px;color:#64748b;margin-top:2px"><?= h($aciklama) ?></div>
                </div>
                <div style="color:#0b5cff;font-size:13px">↗</div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php render_footer(); ?>
