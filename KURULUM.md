# Kurulum Yönergeleri

CODEGA Entegratör Portal'ın DirectAdmin shared hosting'e kurulumu. Diğer ortamlar için aynı mantık geçerli, sadece panel adımları değişir.

## Gereksinimler

| Bileşen | Minimum | Önerilen |
|---|---|---|
| PHP | 8.1 | **8.3** |
| MySQL | 5.7 | 8.0 / MariaDB 10.6 |
| PHP eklentileri | `pdo_mysql`, `dom`, `libxml`, `openssl`, `mbstring`, `json`, `curl` | aynı |
| Apache modülleri | `mod_rewrite`, `mod_headers`, `mod_expires` | aynı |
| Disk | 500 MB | 5 GB+ (uzun vadede XML arşivi büyür) |
| SSL | Let's Encrypt yeterli | Cloudflare tam SSL |

## Adım 1 — Subdomain Oluştur

**DirectAdmin panelinde:**

1. `Subdomain Management` → `Create new subdomain`
2. Subdomain adı: `entegrator` (veya seçtiğin başka isim)
3. Domain: `codega.com.tr`
4. Tam adres: `entegrator.codega.com.tr`

DirectAdmin otomatik şu dizini oluşturur:
```
/home/codega/domains/codega.com.tr/public_html/entegrator/
```

## Adım 2 — Veritabanı Oluştur

**DirectAdmin'de `MySQL Management`:**

1. `Create new database`
2. DB adı: `entegrator_portal` → tam ad: `codega_entegrator_portal`
3. Kullanıcı adı: `entegrator_portal` → tam ad: `codega_entegrator_portal`
4. Güçlü bir şifre üret ve **not al**
5. Tüm yetkiler: ✓

## Adım 3 — Dosyaları Yerleştir

### Seçenek A: Git ile (önerilen — güncel kalır)

SSH ile bağlan:

```bash
ssh codega@codega.com.tr
cd /home/codega/domains/codega.com.tr/public_html/
rm -rf entegrator   # DirectAdmin'in oluşturduğu boş klasörü sil
git clone https://github.com/codegatr/entegrator-portal.git entegrator
cd entegrator
```

Sonra **DirectAdmin'de subdomain document root'unu değiştir:**

- `Subdomain Management` → `entegrator.codega.com.tr` → `Edit`
- Document Root: `/public_html/entegrator/public/` (sonundaki `/public/` kritik!)

> ⚠️ Document root **mutlaka `/public` altındaki** `public/` klasörünü göstermeli. Aksi halde `config.php`, `includes/`, `libs/`, `storage/` dizinleri web'den erişilebilir olur. **Güvenlik açığı!**

### Seçenek B: ZIP ile

1. Release'ten ZIP indir: `https://github.com/codegatr/entegrator-portal/releases/latest`
2. FTP/File Manager ile `entegrator/` klasörüne aç
3. Subdomain document root'unu `/entegrator/public/` yap (yukarıdaki gibi)

## Adım 4 — config.php Hazırla

```bash
cd /home/codega/domains/codega.com.tr/public_html/entegrator/
cp config.example.php config.php
nano config.php
```

Düzenle:

```php
// ── Veritabanı ──
define('DB_NAME', 'codega_entegrator_portal');     // Adım 2'de oluşturdun
define('DB_USER', 'codega_entegrator_portal');     // Adım 2'de oluşturdun
define('DB_PASS', 'BuRaYaGüçlüŞifre');             // Adım 2'de not aldığın

// ── Site ──
define('SITE_URL', 'https://entegrator.codega.com.tr');

// ── Firma (CODEGA kendi bilgileri — DOĞRU VKN İLE GÜNCELLE!) ──
define('FIRMA_VKN',          '1234567890');        // ⚠️ GERÇEK VKN
define('FIRMA_VERGI_DAIRESI','Selçuk');            // ⚠️ DOĞRU VERGİ DAİRESİ
define('FIRMA_ADI',          'CODEGA Yazılım Hizmetleri');
define('FIRMA_ADRES',        'Gerçek açık adres');
// ... diğer firma bilgilerini de gerçek verilerle doldur

// ── Güvenlik — MUTLAKA DEĞİŞTİR ──
define('CSRF_SECRET', 'buraya_32_karakter_rastgele_string_koy');
define('ADMIN_DEFAULT_PASS', 'guclu_bir_ilk_sifre');  // admin123 YERİNE başka bir şey

// ── Debug'ı kapat ──
define('DEBUG_MODE', false);   // prod'da MUTLAKA false!
```

**Rastgele CSRF secret üretmek için:**
```bash
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
```

## Adım 5 — Dizin İzinleri

```bash
cd /home/codega/domains/codega.com.tr/public_html/entegrator/

# storage/ dizini web server için yazılabilir olmalı
chmod 755 storage
chmod 755 storage/xml storage/pdf storage/certs storage/backups

# config.php sadece sahibi okuyabilsin
chmod 600 config.php
```

## Adım 6 — PHP Sürümünü Kontrol Et

DirectAdmin panelinde:

- `Domain Setup` → `codega.com.tr` → `PHP Version Selector`
- Subdomain için **PHP 8.3** seç
- Aktif eklentileri kontrol et: `pdo_mysql`, `openssl`, `dom`, `mbstring`, `json`, `curl` ✓

Kontrol:
```bash
php -v
php -m | grep -iE "pdo_mysql|openssl|dom|mbstring|json|curl"
```

## Adım 7 — İlk Açılış

Tarayıcıda aç: `https://entegrator.codega.com.tr/`

**Doğru çalışıyorsa görmen gereken:**

```
┌─────────────────────────────────┐
│   ┌─┐                           │
│   │📄│  Portal Girişi           │
│   └─┘  CODEGA Entegratör Portal   │
│                                 │
│   [Kullanıcı adı]               │
│   [Şifre]                       │
│   [  Giriş Yap  ]               │
└─────────────────────────────────┘
```

**İlk giriş:**
- Kullanıcı: `admin`
- Şifre: config.php'deki `ADMIN_DEFAULT_PASS` değeri (varsayılan `admin123`)

İlk giriş sonrası **otomatik şifre değiştirme sayfasına** yönlendirilirsin — güçlü bir şifre belirle.

## Adım 8 — Kurulum Sonrası Kontrol Listesi

```
☐ https://entegrator.codega.com.tr açılıyor ve login ekranı geliyor
☐ https://entegrator.codega.com.tr/config.php → 403 veya login (ERİŞİLMEMELİ)
☐ https://entegrator.codega.com.tr/storage/ → 403 (ERİŞİLMEMELİ)
☐ https://entegrator.codega.com.tr/includes/ → 403 (ERİŞİLMEMELİ)
☐ admin girişi başarılı
☐ Zorunlu şifre değiştirme çalıştı
☐ Dashboard geldi — 7 tablo otomatik oluşturulmuş olmalı
☐ Müşteri > Yeni Müşteri → bir test müşterisi ekledin
☐ Yeni Fatura → örnek fatura oluşturdun → XML üretildi
☐ Fatura Detay → XML görüntüleyici çalışıyor
☐ XML İndir → dosya iniyor
☐ Log ekranı → en az 5 kayıt görünüyor
☐ Çıkış → tekrar giriş çalışıyor
```

Hepsi ✓ ise **kurulum tamamdır**.

## Kurulum Doğrulama (tek komut)

SSH'den kurulumu hızla kontrol etmek için:

```bash
php -r "
require '/home/codega/domains/codega.com.tr/public_html/entegrator/config.php';
\$tables = \$pdo->query(\"SHOW TABLES\")->fetchAll(PDO::FETCH_COLUMN);
echo 'Tablo sayısı: ' . count(\$tables) . \"\n\";
echo 'Tablolar: ' . implode(', ', \$tables) . \"\n\";
echo 'Admin var mı: ' . (\$pdo->query(\"SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi='admin'\")->fetchColumn() ? 'Evet' : 'Hayır') . \"\n\";
"
```

Beklenen çıktı:
```
Tablo sayısı: 7
Tablolar: ayarlar, fatura_log, fatura_satirlari, faturalar, kullanicilar, mukellefler, sistem_log
Admin var mı: Evet
```

## Güncelleme

### Git kullanıyorsan

```bash
cd /home/codega/domains/codega.com.tr/public_html/entegrator/
git pull
# config.php zaten .gitignore'da — dokunulmaz
```

### ZIP güncellemesi yapıyorsan

1. Yeni release ZIP'ini indir
2. Sadece şu dizinleri güncelle:
   - `public/` (config.php HARİÇ — üzerine yazma!)
   - `includes/`
   - `libs/`
   - `README.md`, `KURULUM.md`
3. `config.php`'ye dokunma
4. `storage/` klasörüne dokunma (müşteri verileri var)
5. Tarayıcıdan portal'ı aç — migration otomatik yeni tablolar ekler

## Yedekleme (önemli!)

```bash
# Haftalık otomatik yedek için cron (DirectAdmin > Cron Jobs)
0 3 * * 0 mysqldump -u codega_entegrator_portal -p'SIFRE' codega_entegrator_portal | gzip > /home/codega/domains/codega.com.tr/public_html/entegrator/storage/backups/db_$(date +\%Y\%m\%d).sql.gz
```

XML dosyaları için rsync:
```bash
# Aylık yedek (1. gün 04:00)
0 4 1 * * rsync -a /home/codega/domains/codega.com.tr/public_html/entegrator/storage/xml/ /home/codega/backups/entegrator_xml/
```

## Sorun Giderme

### "DB bağlantı hatası"

1. `config.php`'deki DB_NAME, DB_USER, DB_PASS doğru mu?
2. DirectAdmin'de database gerçekten oluşmuş mu?
3. Kullanıcıya yetki verilmiş mi?

Test:
```bash
mysql -u codega_entegrator_portal -p codega_entegrator_portal -e "SELECT 1;"
```

### "500 Internal Server Error"

1. `DEBUG_MODE` true yap, hata detayını gör
2. `/home/codega/domains/codega.com.tr/logs/error.log` dosyasını incele
3. PHP sürümü 8.1+ mı kontrol et

### "Session hatası / sürekli login sayfasına atıyor"

- Cookie domain uyumsuzluğu: `SITE_URL` ve gerçek URL aynı mı?
- `session.save_path` yazılabilir mi?
- HTTPS proxy (Cloudflare) için `samesite: Lax` zaten yapıldı — sorun sürerse `Strict` deneme

### Üretilen XML'ler bozuk

1. Portal'da `Yönetim > Ayarlar` sayfasında kütüphane sürümünü kontrol et
2. libs/entegrator-gib/ klasörü eksiksiz mi?
3. `php -l libs/entegrator-gib/CodegaGib/Invoice/UblBuilder.php` syntax check

### Yeni fatura sayfasında müşteri araması çalışmıyor

1. Browser Network sekmesinde `/api/mukellef-search.php?q=...` çağrısı yapılıyor mu?
2. 401 dönüyor mu → login oturumun düşmüş
3. Boş dönüyor mu → en az 2 harf yazdın mı?
4. Sunucu log'unda 404 mü → `public/api/` dizini yüklenmemiş

## Multi-Tenant Geçişi (Gelecek)

v2.0'da her firma kendi hesabıyla portalı kullanacak. O zaman:

1. `firmalar` tablosu eklenir (her satır = bir müşteri firma)
2. `kullanicilar.firma_id` FK eklenir
3. `faturalar.firma_id` FK eklenir
4. `config.php`'deki FIRMA_* sabitleri tablodan okunan değerlerle değiştirilir
5. Her sorgu `WHERE firma_id = ?` filtresi ile kısıtlanır

Bu migration şu anki yapıyı bozmayacak şekilde tasarlandı — endişelenme.

## Yardım

- Portal bug report: https://github.com/codegatr/entegrator-portal/issues
- Kütüphane bug report: https://github.com/codegatr/entegrator-gib/issues
- E-posta: info@codega.com.tr
