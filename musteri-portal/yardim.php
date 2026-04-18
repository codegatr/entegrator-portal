<?php
// Müşteri portal: config.php otomatik session başlatmasın (ayrı session kullanıyoruz)
define('CODEGA_NO_AUTO_SESSION', true);

require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

mp_auth_require();
mp_audit($pdo, 'musteri.view_yardim');

mp_render_header('Yardım Merkezi', 'yardim');
?>

<div class="mp-page-head">
    <div>
        <h1>Yardım Merkezi</h1>
        <div class="sub">Sıkça sorulan sorular ve destek bilgileri</div>
    </div>
</div>

<!-- HIZLI DESTEK KARTLARI -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px">
    <a href="tel:+905320652400" style="text-decoration:none;padding:20px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;display:flex;align-items:center;gap:14px;transition:all .15s" onmouseover="this.style.borderColor='#0b5cff';this.style.boxShadow='0 4px 14px rgba(11,92,255,0.1)'" onmouseout="this.style.borderColor='#e2e8f0';this.style.boxShadow='none'">
        <div style="width:44px;height:44px;background:#e0f2fe;color:#0284c7;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?= mp_icon('phone', 22) ?>
        </div>
        <div>
            <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.3px;font-weight:600">Telefon</div>
            <div style="font-weight:700;font-size:14.5px;color:#0f172a;margin-top:2px">0532 065 24 00</div>
            <div style="font-size:11px;color:#64748b">Hafta içi 09:00 – 18:00</div>
        </div>
    </a>

    <a href="https://wa.me/905320652400" target="_blank" style="text-decoration:none;padding:20px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;display:flex;align-items:center;gap:14px;transition:all .15s" onmouseover="this.style.borderColor='#25D366';this.style.boxShadow='0 4px 14px rgba(37,211,102,0.15)'" onmouseout="this.style.borderColor='#e2e8f0';this.style.boxShadow='none'">
        <div style="width:44px;height:44px;background:#dcfce7;color:#16a34a;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?= mp_icon('whatsapp', 22) ?>
        </div>
        <div>
            <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.3px;font-weight:600">WhatsApp</div>
            <div style="font-weight:700;font-size:14.5px;color:#0f172a;margin-top:2px">Mesaj Gönder</div>
            <div style="font-size:11px;color:#64748b">Hızlı yanıt</div>
        </div>
    </a>

    <a href="mailto:<?= h(CONTACT_EMAIL) ?>" style="text-decoration:none;padding:20px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;display:flex;align-items:center;gap:14px;transition:all .15s" onmouseover="this.style.borderColor='#0b5cff';this.style.boxShadow='0 4px 14px rgba(11,92,255,0.1)'" onmouseout="this.style.borderColor='#e2e8f0';this.style.boxShadow='none'">
        <div style="width:44px;height:44px;background:#ede9fe;color:#7c3aed;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?= mp_icon('envelope', 22) ?>
        </div>
        <div>
            <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.3px;font-weight:600">E-posta</div>
            <div style="font-weight:700;font-size:13.5px;color:#0f172a;margin-top:2px"><?= h(CONTACT_EMAIL) ?></div>
            <div style="font-size:11px;color:#64748b">24 saat içinde</div>
        </div>
    </a>

    <div style="padding:20px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;background:#fef3c7;color:#d97706;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?= mp_icon('map-pin', 22) ?>
        </div>
        <div>
            <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.3px;font-weight:600">Adres</div>
            <div style="font-weight:700;font-size:13.5px;color:#0f172a;margin-top:2px">Konya</div>
            <div style="font-size:11px;color:#64748b">Türkiye merkezli</div>
        </div>
    </div>
</div>

<!-- HIZLI BAŞLAT -->
<div class="mp-card" style="background:linear-gradient(135deg,#0a2540 0%,#1e3a5f 100%);color:#fff;border:none">
    <div style="padding:26px 28px">
        <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
            <div style="width:54px;height:54px;background:rgba(255,255,255,0.15);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <?= mp_icon('bolt', 26) ?>
            </div>
            <div style="flex:1;min-width:250px">
                <h3 style="margin:0 0 4px;font-size:18px;font-weight:700">Hızlı Başlat</h3>
                <p style="margin:0 0 14px;color:rgba(255,255,255,0.8);font-size:13.5px;line-height:1.5">
                    CODEGA Müşteri Portalı'nı ilk kez kullanıyorsanız, aşağıdaki 3 adımla hemen başlayabilirsiniz.
                </p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
                    <div style="padding:14px;background:rgba(0,0,0,0.15);border-radius:10px">
                        <div style="font-size:11px;color:rgba(255,255,255,0.7);font-weight:600;margin-bottom:4px">ADIM 1</div>
                        <div style="font-size:13.5px;font-weight:600">Şifreni Değiştir</div>
                        <div style="font-size:12px;color:rgba(255,255,255,0.7);margin-top:3px">Profil > Şifre Değiştir</div>
                    </div>
                    <div style="padding:14px;background:rgba(0,0,0,0.15);border-radius:10px">
                        <div style="font-size:11px;color:rgba(255,255,255,0.7);font-weight:600;margin-bottom:4px">ADIM 2</div>
                        <div style="font-size:13.5px;font-weight:600">Faturalarını İncele</div>
                        <div style="font-size:12px;color:rgba(255,255,255,0.7);margin-top:3px">Faturalarım menüsünden</div>
                    </div>
                    <div style="padding:14px;background:rgba(0,0,0,0.15);border-radius:10px">
                        <div style="font-size:11px;color:rgba(255,255,255,0.7);font-weight:600;margin-bottom:4px">ADIM 3</div>
                        <div style="font-size:13.5px;font-weight:600">XML'leri Arşivle</div>
                        <div style="font-size:12px;color:rgba(255,255,255,0.7);margin-top:3px">Detay > XML İndir</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SSS GRUPLARI -->
<?php
$sss = [
    'Genel' => [
        [
            'E-Fatura nedir, neden önemli?',
            'E-Fatura, Gelir İdaresi Başkanlığı (GİB) tarafından tanımlanan standart XML formatında oluşturulan elektronik faturadır. Kâğıt fatura ile aynı hukuki geçerliliğe sahiptir. 2025 itibariyle yıllık cirosu belirli bir limiti aşan tüm işletmeler için zorunludur. Kâğıttan tasarruf, arşiv kolaylığı, mali mühür ile güvenli iletim, ve anında teslim avantajları vardır.'
        ],
        [
            'E-Fatura ile e-Arşiv Fatura farkı nedir?',
            "E-Fatura, sadece GİB sistemine kayıtlı firmalar arasında kesilir (alıcı da mükellef olmalı). E-Arşiv Fatura ise GİB mükellefi olmayan müşterilere (bireysel tüketici, e-ticaret alıcısı vb.) kesilen elektronik faturadır.\n\nCODEGA olarak her iki türü de kesebiliyoruz."
        ],
        [
            'Faturada kullanılan profil tipleri ne anlama geliyor?',
            "Temel Fatura: En yaygın fatura tipi. Basit mal/hizmet alımı.\nTicari Fatura: Alıcının kabul/red hakkı olan, ticari uyuşmazlıklarda koruma sağlayan tip.\ne-Arşiv: Mükellef olmayanlara kesilen.\nİhracat: Yurt dışı satışları için özel tip."
        ],
    ],
    'Faturalarım' => [
        [
            'Fatura bilgilerimde yanlışlık var, ne yapmalıyım?',
            "Vergi numarası, ünvan, adres gibi temel bilgilerinizde hata varsa:\n1. CODEGA'yı arayın (0532 065 24 00) veya WhatsApp'tan ulaşın\n2. Doğru bilgileri iletin\n3. Düzeltme için yeni fatura kesilir (eski iptal edilir)\n\nÖnemli: Fatura bir kez imzalanıp gönderildikten sonra içeriği değiştirilemez — iptal edilip yeniden kesilmesi gerekir."
        ],
        [
            'Fatura XML dosyasını nasıl indirebilirim?',
            "Faturalarım menüsünden ilgili faturaya girin. Sağ üstteki 'XML İndir' butonuna tıklayın. Yalnızca imzalı, gönderilmiş veya kabul edilmiş faturalar indirilebilir.\n\nTaslak durumundaki faturalar henüz imzalanmadığı için XML indirilemez."
        ],
        [
            'Faturanın durumları ne anlama geliyor?',
            "Taslak: Daha düzenleniyor\nHazırlanıyor: İmza bekliyor\nİmzalı: Mali mühür ile imzalandı\nGönderildi: GİB'e ve alıcıya gönderildi\nKabul Edildi: Alıcı onayladı\nReddedildi: Alıcı red etti (Ticari Fatura için)\nİptal: Fatura iptal edildi"
        ],
        [
            'Bir faturayı reddedebilir miyim?',
            "Sadece Ticari Fatura tipindeki faturaları reddedebilirsiniz. Reddetmeniz için CODEGA ile iletişime geçin. Temel Fatura kabul edilmiş sayılır ve reddedilemez.\n\nFaturayı almak istemediğinizi yasal süresi içinde (8 gün) bildirmeniz gerekir."
        ],
    ],
    'Güvenlik' => [
        [
            'Şifremi unuttum, ne yapmalıyım?',
            "CODEGA müşteri temsilcinizi arayın veya WhatsApp'tan ulaşın. Kimlik doğrulaması yapıldıktan sonra şifrenizi sıfırlayacağız. Yeni şifreniz sizinle güvenli yoldan paylaşılır.\n\nNot: CODEGA çalışanları asla şifrenizi sormaz. Şifre bilgisi isteyen kişilere karşı dikkatli olun."
        ],
        [
            'Portal güvenli mi?',
            "Evet. CODEGA Müşteri Portalı:\n• SSL/TLS şifreli bağlantı (https)\n• 5 hatalı girişte hesap 10 dk kilitlenir\n• Şifreler tek yönlü bcrypt ile saklanır\n• Her giriş ve erişim audit kaydı ile tutulur\n• Sadece kendi firmanıza ait faturaları görebilirsiniz\n• Oturum 1 saat sonra otomatik düşer"
        ],
        [
            'Başka birinin faturalarını görebilir miyim?',
            "Hayır. Portal mimarisi firma bazında izole edilmiştir. Her sorguda yalnızca sizin firmanıza ait kayıtlar gösterilir. Bu, hem programatik kontrol hem de database düzeyinde uygulanır."
        ],
        [
            'Kimler faturalarıma erişebilir?',
            "Sizin hesabınıza giriş yapan kullanıcılar (firmanız için birden fazla kullanıcı tanımlanabilir) ve CODEGA sistem yöneticileri. Her erişim audit loglarında tutulur — kim ne zaman hangi faturaya baktı kaydedilir."
        ],
    ],
    'Teknik' => [
        [
            'Portal hangi tarayıcılarla çalışır?',
            'Modern tüm tarayıcılarda çalışır: Chrome, Edge, Firefox, Safari (son 2 yıl). Internet Explorer desteklenmez. Mobilde Chrome (Android) ve Safari (iOS) önerilir.'
        ],
        [
            'Portal mobilde çalışır mı?',
            'Evet. Tüm sayfalar responsive tasarım ile mobil, tablet ve masaüstü için optimize edildi. Telefonunuzdan tarayıcı açıp portal adresine gidebilirsiniz.'
        ],
        [
            'Fatura detayı açıldığında ne kaydediliyor?',
            "Her fatura görüntülenmesinde:\n• Hangi kullanıcı (sizden)\n• Hangi fatura\n• Tarih ve saat\n• IP adresiniz\n\nBu bilgiler denetleme amaçlı 6 yıl saklanır. Sizin faturanıza kaç kez baktığınızı kendi hesabınız üzerinden göremezsiniz; bu bilgiyi sadece CODEGA yöneticisi görür."
        ],
        [
            'Portal neden bazen yenilenmemiş görünüyor?',
            'Faturalar anlık değil, birkaç saniye gecikmeli görünebilir. Eğer yeni bir faturanızı görmüyorsanız, sayfayı yenileyin (F5). Yine de görünmüyorsa CODEGA ile iletişime geçin.'
        ],
    ],
    'CODEGA Hakkında' => [
        [
            'CODEGA kimdir?',
            "CODEGA, Konya merkezli bir web yazılım firmasıdır. 2019'dan bu yana PHP web uygulamaları, kurumsal web siteleri, e-ticaret altyapıları ve özel yazılım çözümleri geliştirmektedir.\n\nWeb: codega.com.tr\nSosyal Medya: @codegatr"
        ],
        [
            'CODEGA GİB Özel Entegratörü mü?',
            "CODEGA, GİB Özel Entegratör lisansı alma sürecindedir. Şu an entegratör hizmetini güvenilir ortak altyapılar üzerinden sunmaktadır. Hedefimiz 2027 sonunda kendi bağımsız entegratör lisansımızı almaktır.\n\nBu süreçte tüm faturalarınız yasal olarak geçerli ve GİB onaylıdır."
        ],
        [
            'Fatura saklanma süresi ne kadar?',
            "Yasal olarak 10 yıl saklama zorunluluğu vardır. CODEGA tüm faturalarınızı yedeklemeli sistemlerde en az 10 yıl saklar. XML dosyalarını kendi sisteminizde arşivlemek için 'XML İndir' butonunu kullanmanızı öneririz."
        ],
    ],
];
?>

<?php foreach ($sss as $grup => $sorular): ?>
<div class="mp-card">
    <div class="mp-card-head">
        <?= mp_icon('help') ?>
        <h3><?= h($grup) ?></h3>
        <span class="mp-badge mp-badge-secondary" style="margin-left:auto;font-size:11px"><?= count($sorular) ?> soru</span>
    </div>
    <div class="mp-card-body" style="padding:0 22px">
        <?php foreach ($sorular as $i => [$soru, $cevap]): ?>
            <details style="padding:14px 0;border-bottom:<?= $i === count($sorular)-1 ? 'none' : '1px solid #f1f5f9' ?>">
                <summary style="cursor:pointer;font-weight:600;font-size:14px;color:#0f172a;padding:4px 0;list-style:none;display:flex;align-items:center;gap:10px">
                    <span style="color:#0b5cff;font-weight:700;font-size:13px;min-width:24px">Q</span>
                    <span style="flex:1"><?= h($soru) ?></span>
                    <span style="color:#cbd5e1;font-size:18px;transition:transform .2s" class="mp-chev">▾</span>
                </summary>
                <div style="padding:12px 0 6px 34px;font-size:13.5px;line-height:1.65;color:#475569;white-space:pre-wrap"><?= h($cevap) ?></div>
            </details>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- SON CTA -->
<div class="mp-card" style="border:2px dashed #cbd5e1;background:#f8fafc;text-align:center">
    <div style="padding:30px">
        <div style="font-size:32px;margin-bottom:8px">🤝</div>
        <h3 style="margin:0 0 8px;font-size:16px;font-weight:700">Sorunuz mu var?</h3>
        <p style="margin:0 0 16px;color:#64748b;font-size:13.5px">
            Burada cevabını bulamadığınız bir soru mu var?
            CODEGA destek ekibi size yardımcı olmaktan mutluluk duyar.
        </p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <a href="tel:+905320652400" class="mp-btn mp-btn-brand">
                <?= mp_icon('phone', 14) ?> Hemen Ara
            </a>
            <a href="https://wa.me/905320652400" target="_blank" class="mp-btn mp-btn-ghost" style="background:#dcfce7;color:#16a34a">
                <?= mp_icon('whatsapp', 14) ?> WhatsApp
            </a>
        </div>
    </div>
</div>

<style>
details[open] .mp-chev { transform:rotate(180deg) }
details summary::-webkit-details-marker { display:none }
</style>

<?php mp_render_footer(); ?>
