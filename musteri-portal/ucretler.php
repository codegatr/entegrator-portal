<?php
define('CODEGA_NO_AUTO_SESSION', true);

require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

mp_auth_require();
mp_audit($pdo, 'musteri.view_ucretler');

mp_render_header('Ücretlendirme', 'ucretler');
?>

<div class="mp-page-head">
    <div>
        <h1>Ücretlendirme</h1>
        <div class="sub">CODEGA Entegratör hizmetleri şeffaf fiyatlandırma · KDV hariçtir</div>
    </div>
</div>

<!-- AÇIKLAMA BANNER -->
<div class="mp-card" style="background:linear-gradient(135deg,#0a2540 0%,#0b5cff 100%);color:#fff;border:none;margin-bottom:20px">
    <div style="padding:24px 28px">
        <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div style="width:52px;height:52px;background:rgba(255,255,255,0.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <?= mp_icon('info', 24) ?>
            </div>
            <div style="flex:1;min-width:250px">
                <h2 style="margin:0 0 4px;font-size:19px;font-weight:700">Kontör bazlı, adil fiyatlandırma</h2>
                <p style="margin:0;color:rgba(255,255,255,0.85);font-size:14px;line-height:1.6">
                    CODEGA'da aylık sabit ücret yoktur. <strong>Yalnızca kullandığınız kadar ödersiniz.</strong>
                    Her e-Belge 1 kontör tüketir. Kontörler hiç bitmez, istediğiniz zaman yeni kontör paketi alabilirsiniz.
                    <strong>10 yıl saklama ücretsizdir.</strong>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- KONTÖR PAKETLERİ -->
<div class="mp-card">
    <div class="mp-card-head">
        <?= mp_icon('package') ?>
        <h3>Kontör Paketleri</h3>
        <span style="margin-left:auto;font-size:12px;color:#94a3b8">5 yıl geçerli</span>
    </div>
    <div class="mp-card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
            <?php
            $paketler = [
                ['Başlangıç',  '1.000 kontör',  '₺599',    '₺0,60', 'KOBİ için ideal', false],
                ['Standart',   '5.000 kontör',  '₺2.499',  '₺0,50', 'En çok tercih edilen', true],
                ['Profesyonel','20.000 kontör', '₺8.999',  '₺0,45', 'Büyüyen işletmeler', false],
                ['Kurumsal',   '100.000 kontör','₺39.999', '₺0,40', 'Büyük ölçek', false],
            ];
            foreach ($paketler as [$isim, $adet, $fiyat, $birim, $aciklama, $populer]):
            ?>
                <div style="padding:20px;border:<?= $populer?'2px solid #0b5cff':'1px solid #e2e8f0' ?>;border-radius:12px;background:<?= $populer?'linear-gradient(135deg,#eff6ff 0%,#fff 100%)':'#fff' ?>;position:relative">
                    <?php if ($populer): ?>
                        <div style="position:absolute;top:-10px;right:14px;background:#0b5cff;color:#fff;padding:3px 10px;border-radius:10px;font-size:10.5px;font-weight:700">POPÜLER</div>
                    <?php endif; ?>
                    <div style="font-size:13px;color:#64748b;font-weight:600;margin-bottom:4px"><?= $isim ?></div>
                    <div style="font-size:22px;font-weight:800;color:#0f172a;letter-spacing:-0.3px"><?= $adet ?></div>
                    <div style="font-size:13px;color:#94a3b8;margin-top:2px"><?= $aciklama ?></div>
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #e2e8f0">
                        <div style="font-size:26px;font-weight:800;color:#0b5cff;letter-spacing:-0.5px"><?= $fiyat ?></div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:2px"><?= $birim ?> / kontör</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:16px;padding:14px;background:#f8fafc;border-radius:10px;font-size:12.5px;color:#64748b;line-height:1.6">
            <strong style="color:#0f172a">💡 Nasıl çalışır?</strong>
            Kontörler e-Fatura, e-Arşiv, e-İrsaliye, e-SMM, e-MM ve e-Adisyon gibi tüm e-belgelerde kullanılabilir.
            Kontörleriniz 5 yıl boyunca geçerlidir. Biten kontör paketi için yeni sipariş verebilirsiniz.
        </div>
    </div>
</div>

<!-- E-BELGE BAŞINA KONTÖR TÜKETİMİ -->
<div class="mp-card">
    <div class="mp-card-head">
        <?= mp_icon('invoice') ?>
        <h3>Belge Başına Kontör Tüketimi</h3>
        <span style="margin-left:auto;font-size:12px;color:#059669;font-weight:600">10 yıl saklama DAHİL</span>
    </div>
    <div class="mp-card-body tight">
        <table class="mp-table">
            <thead>
                <tr>
                    <th>Hizmet</th>
                    <th style="text-align:right">Kontör</th>
                    <th>Açıklama</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $belgeler = [
                    ['e-Fatura Gönderisi',            '1',    '10 yıl saklama dahil'],
                    ['e-Fatura Alışı',                '1',    'Size gelen faturalar için'],
                    ['e-İrsaliye Gönderisi',          '1',    'Sevk irsaliyeleri'],
                    ['e-İrsaliye Alışı',              '1',    'Gelen irsaliyeler'],
                    ['e-Arşiv Fatura Gönderisi',      '1',    'Mükellef olmayan alıcılar'],
                    ['e-Serbest Meslek Makbuzu',      '1',    'Avukat, doktor, mimar vb.'],
                    ['e-Müstahsil Makbuzu',           '1',    'Çiftçi alımları'],
                    ['e-Adisyon',                     '1',    'Restoran/kafe için'],
                    ['e-Bilet Gönderisi',             '1',    'Ulaşım ve etkinlik biletleri'],
                    ['SMS Bildirim Servisi',          '0,5',  'Her SMS başına'],
                    ['E-posta Bildirim',              '0,25', 'Her e-posta başına'],
                    ['GİB VKN Sorgulama',             '0,5',  'Mükellef durumu kontrolü'],
                    ['GİB Faaliyet Kodu Sorgulama',   '0,5',  'Mükellefin faaliyet kodu'],
                ];
                foreach ($belgeler as [$hizmet, $kontor, $aciklama]):
                ?>
                    <tr>
                        <td><strong><?= h($hizmet) ?></strong></td>
                        <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700;color:#0b5cff"><?= h($kontor) ?> <span style="color:#94a3b8;font-weight:400;font-size:12px">kontör</span></td>
                        <td style="color:#64748b;font-size:13px"><?= h($aciklama) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- EK HİZMETLER -->
<div class="mp-card">
    <div class="mp-card-head">
        <?= mp_icon('star') ?>
        <h3>Ek Hizmetler (Opsiyonel)</h3>
    </div>
    <div class="mp-card-body tight">
        <table class="mp-table">
            <thead>
                <tr>
                    <th>Hizmet</th>
                    <th style="text-align:right">Ücret</th>
                    <th>Açıklama</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $ek_hizmetler = [
                    ['Portal Aktivasyon',                   'Ücretsiz',     'İlk kurulum, tamamen CODEGA tarafından'],
                    ['7/24 Destek Hattı',                   'Ücretsiz',     'Telefon, WhatsApp, e-posta'],
                    ['Geçiş Danışmanlığı',                  'Ücretsiz',     'GİB başvuru süreci dahil'],
                    ['Veri İçe Aktarma',                    'Ücretsiz',     'Eski faturalarınızı taşıma'],
                    ['Standart Fatura Tasarımı',            'Ücretsiz',     'Logo ve imza ile birlikte'],
                    ['Özel Fatura Tasarımı',                '₺1.499/saat',  'Kurumsal kimliğinize özel'],
                    ['Fatura Excel Aktarımı (100.000 satır)','₺999',        'Toplu dışa aktarma hizmeti'],
                    ['Müşteri Domain Yönlendirme',          '₺4.999',       'Bir kerelik, fatura.sirket.com gibi'],
                    ['Eski Belge Yükleme',                  '0,5 kontör',   'Her belge için (manüel yükleme)'],
                    ['ERP / Muhasebe Entegrasyonu',         'Bedelsiz',     'CodeGa ERP ile ücretsiz'],
                    ['3. Parti ERP Entegrasyonu',           '₺4.999',       'Logo, Mikro, Netsis vb. için bir kerelik'],
                    ['Aselsan Uyumluluk Paketi',            '₺1.999',       'Özel formatlar için'],
                    ['Tasarım: Barkod/QR Ekleme',           '₺2.499',       'Faturaya barkod/QR kod'],
                    ['Tasarım: İngilizce/Arapça Şablon',    '₺7.999',       'Çok dilli fatura şablonu'],
                    ['Pasif Portal Kullanımı',              '₺2.999/yıl',   'Fatura kesmeyip sadece saklayanlar'],
                ];
                foreach ($ek_hizmetler as [$hizmet, $ucret, $aciklama]):
                    $is_free = str_contains($ucret, 'Ücretsiz') || str_contains($ucret, 'Bedelsiz');
                ?>
                    <tr>
                        <td><strong><?= h($hizmet) ?></strong></td>
                        <td style="text-align:right;font-weight:700;color:<?= $is_free?'#059669':'#0f172a' ?>;white-space:nowrap"><?= h($ucret) ?></td>
                        <td style="color:#64748b;font-size:13px"><?= h($aciklama) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CODEGA FARKı -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-bottom:20px">
    <div class="mp-card" style="border-left:4px solid #059669">
        <div style="padding:22px">
            <div style="font-size:28px;margin-bottom:8px">💰</div>
            <h3 style="margin:0 0 6px;font-size:15px">Aylık sabit ücret YOK</h3>
            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5">
                Sadece kullandığınız kadar ödersiniz. Kontör biterse yeni paket alın; bitmese bile zorunluluk yok.
            </p>
        </div>
    </div>
    <div class="mp-card" style="border-left:4px solid #0b5cff">
        <div style="padding:22px">
            <div style="font-size:28px;margin-bottom:8px">🇹🇷</div>
            <h3 style="margin:0 0 6px;font-size:15px">Türkiye merkezli destek</h3>
            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5">
                CODEGA Konya'da konumlanıyor. Yerel, hızlı ve Türkçe destek. Call-center'a değil, direkt yazılımcıya ulaşıyorsunuz.
            </p>
        </div>
    </div>
    <div class="mp-card" style="border-left:4px solid #7c3aed">
        <div style="padding:22px">
            <div style="font-size:28px;margin-bottom:8px">🔗</div>
            <h3 style="margin:0 0 6px;font-size:15px">ERP Bundle</h3>
            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5">
                <strong>CodeGa ERP müşterisiyseniz entegrasyon ücretsiz.</strong> Tek platformda muhasebe + fatura + CRM.
            </p>
        </div>
    </div>
    <div class="mp-card" style="border-left:4px solid #d97706">
        <div style="padding:22px">
            <div style="font-size:28px;margin-bottom:8px">🔒</div>
            <h3 style="margin:0 0 6px;font-size:15px">Veriniz size ait</h3>
            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5">
                XML dosyalarınızı istediğiniz zaman indirebilirsiniz. "Esir alma" yok, geçiş yapmak istediğinizde engellemeyiz.
            </p>
        </div>
    </div>
</div>

<!-- KIYASLAMA -->
<div class="mp-card">
    <div class="mp-card-head">
        <?= mp_icon('chart') ?>
        <h3>Diğer Entegratörlerle Kıyaslama</h3>
    </div>
    <div class="mp-card-body tight">
        <div class="mp-table-wrap">
            <table class="mp-table">
                <thead>
                    <tr>
                        <th>Hizmet</th>
                        <th style="text-align:center;background:#eff6ff"><strong style="color:#0b5cff">CODEGA</strong></th>
                        <th style="text-align:center">Rakipler (ortalama)</th>
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $kiyas = [
                        ['Kurulum', '✓ Ücretsiz', '₺500-1.500', 'CODEGA başlangıçta ücret almaz'],
                        ['Aylık sabit ücret', '✓ Yok', '₺150-400', 'Sadece kontör bazlı ödeme'],
                        ['1.000 kontör paketi', '₺599', '₺750-1.000', '%20-40 daha uygun'],
                        ['Destek hattı', '✓ Ücretsiz', 'Ücretsiz', 'Türkiye merkezli'],
                        ['10 yıl saklama', '✓ Dahil', 'Dahil', 'Yasal zorunluluğa uygun'],
                        ['ERP Entegrasyon', '₺4.999', '₺8.000-15.000', 'CodeGa ERP için bedava'],
                        ['Portal Aktivasyon', '✓ Ücretsiz', '₺500-1.000', 'CODEGA\'da sıfır maliyet'],
                        ['Portal yeniden aktivasyon', '₺2.499', '₺5.000', '%50 daha uygun'],
                    ];
                    foreach ($kiyas as [$hizmet, $codega, $rakip, $aciklama]):
                    ?>
                        <tr>
                            <td><strong><?= h($hizmet) ?></strong></td>
                            <td style="text-align:center;background:#eff6ff;color:#0b5cff;font-weight:700"><?= h($codega) ?></td>
                            <td style="text-align:center;color:#64748b"><?= h($rakip) ?></td>
                            <td style="color:#64748b;font-size:13px"><?= h($aciklama) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SIKÇA SORULANLAR -->
<div class="mp-card">
    <div class="mp-card-head">
        <?= mp_icon('help') ?>
        <h3>Fiyatlandırma Hakkında Sıkça Sorulanlar</h3>
    </div>
    <div class="mp-card-body" style="padding:10px 22px">
        <?php
        $ssss = [
            ['Kontörler ne zaman biter?', 'Kontörler 5 yıl geçerlidir. Bu süre içinde kullanmadığınız kontörler yanar. Yoğun aylarda hızlı, yavaş aylarda az tüketim olur; bu esneklik size uygundur.'],
            ['KDV dahil mi?', 'Hayır, listelenen fiyatlar KDV hariçtir. Fatura kesiminde %20 KDV eklenir.'],
            ['Fiyatlar değişir mi?', 'Enflasyon ve hammadde maliyetlerine göre yılda bir kez güncellenebilir. Mevcut müşterilerimiz için sözleşme süresince fiyat sabitlenir.'],
            ['İade mümkün mü?', 'Kullanılmayan kontörler için ilk 30 gün içinde iade mümkündür. Kullanılmış kontörler iade edilmez.'],
            ['Ödeme seçenekleri?', 'Havale/EFT, kredi kartı ve banka kartı ile ödeme yapabilirsiniz. Aylık/yıllık otomatik tahsilat da sunulmaktadır.'],
            ['Kurumsal indirim var mı?', '₺50.000 üzeri paketlerde %10-15, ₺100.000 üzeri paketlerde %20\'ye kadar indirim uygulanabilir. İletişime geçin.'],
        ];
        foreach ($ssss as $i => [$soru, $cevap]):
        ?>
            <details style="padding:12px 0;border-bottom:<?= $i === count($ssss)-1 ? 'none' : '1px solid #f1f5f9' ?>">
                <summary style="cursor:pointer;font-weight:600;font-size:13.5px;color:#0f172a;list-style:none;display:flex;align-items:center;gap:10px">
                    <span style="color:#0b5cff;font-weight:700;font-size:13px;min-width:20px">Q</span>
                    <span style="flex:1"><?= h($soru) ?></span>
                    <span style="color:#cbd5e1;transition:transform .2s" class="mp-chev">▾</span>
                </summary>
                <div style="padding:8px 0 4px 30px;font-size:13px;line-height:1.6;color:#475569"><?= h($cevap) ?></div>
            </details>
        <?php endforeach; ?>
    </div>
</div>

<!-- CTA -->
<div class="mp-card" style="background:linear-gradient(135deg,#0a2540 0%,#0b5cff 100%);color:#fff;border:none;text-align:center">
    <div style="padding:36px 30px">
        <h2 style="margin:0 0 8px;font-size:22px;font-weight:700">Size özel teklif ister misiniz?</h2>
        <p style="margin:0 0 20px;color:rgba(255,255,255,0.85);font-size:14px">
            Fatura hacminize göre özelleştirilmiş fiyat teklifi için CODEGA ile iletişime geçin
        </p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <a href="tel:+905320652400" class="mp-btn mp-btn-primary" style="background:#fff;color:#0b5cff">
                <?= mp_icon('phone', 14) ?> 0532 065 24 00
            </a>
            <a href="https://wa.me/905320652400" target="_blank" class="mp-btn mp-btn-ghost" style="background:rgba(255,255,255,0.15);color:#fff">
                <?= mp_icon('whatsapp', 14) ?> WhatsApp
            </a>
            <a href="<?= SITE_URL ?>/musteri-portal/destek.php" class="mp-btn mp-btn-ghost" style="background:rgba(255,255,255,0.15);color:#fff">
                <?= mp_icon('message', 14) ?> Destek Talebi
            </a>
        </div>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.15);font-size:11.5px;color:rgba(255,255,255,0.7)">
            Fiyatların son geçerlilik tarihi: 30.06.2026 · Fiyatlara KDV dahil değildir
        </div>
    </div>
</div>

<style>
details[open] .mp-chev { transform:rotate(180deg) }
details summary::-webkit-details-marker { display:none }
</style>

<?php mp_render_footer(); ?>
