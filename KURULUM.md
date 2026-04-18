# Kurulum Yönergeleri

CODEGA Entegratör Portal'ın **DirectAdmin Paylaşımlı Hosting**'e kurulumu.

Portal v1.0.3'ten itibaren **düz yapıda** (flat structure). Tüm dosyalar doğrudan `public_html/entegrator/` altına konur, alt `public/` dizini yok. Hassas dizinler (`includes/`, `libs/`, `storage/`, `config.php`) `.htaccess` ile korunur.

## Gereksinimler

| Bileşen | Minimum | Önerilen |
|---|---|---|
| PHP | 8.1 | **8.3** |
| MySQL | 5.7 | 8.0 / MariaDB 10.6 |
| PHP eklentileri | `pdo_mysql`, `dom`, `libxml`, `openssl`, `mbstring`, `json`, `curl` | aynı |
| Apache modülleri | `mod_rewrite`, `mod_headers` | + `mod_expires` |
| Disk | 500 MB | 5 GB+ (XML arşivi zamanla büyür) |

## Yapı

Kurulumdan sonra `public_html/entegrator/` içinde:

```
entegrator/                  ← subdomain document root'u burası
├── .htaccess                ← güvenlik + hassas dizinleri bloklar
├── index.php                ← Dashboard
├── login.php
├── logout.php
├── install.php              ← kurulum sonrası silinir
├── config.php               ← installer tarafından oluşturulur
├── fatura/
│   ├── yeni.php
│   ├── liste.php
│   ├── detay.php
│   └── indir.php
├── musteri/
│   ├── index.php
│   └── duzenle.php
├── log/index.php
├── yonetim/
│   ├── ayarlar.php
│   ├── kullanici.php
│   └── sifre.php
├── api/mukellef-search.php
├── assets/
│   ├── style.css
│   └── app.js
├── includes/                ← .htaccess ile bloklu (web'den erişilemez)
├── libs/                    ← .htaccess ile bloklu
└── storage/                 ← .htaccess ile bloklu (XML arşivi)
```

---

# A) Web Installer ile Kurulum (Önerilen)

## Adım 1 — Subdomain Oluştur

DirectAdmin panelinde:

1. `Subdomain Management` (ya da `Domain Setup` → subdomain ekle)
2. Subdomain adı: `entegrator`
3. Domain: `codega.com.tr`
4. Sonuç: `https://entegrator.codega.com.tr`

DirectAdmin otomatik şu dizini oluşturur:
```
/home/codega/domains/codega.com.tr/public_html/entegrator/
```

Bu dizin subdomain'in **document root'u**. Buraya koyduğun dosyalar web'den erişilebilir.

## Adım 2 — MySQL Database Oluştur

**DirectAdmin'de MySQL Management nerede?**

- **Klasik arayüz:** `Account Manager` kategorisi → `MySQL Management`
- **Evolution arayüzü:** Üstte arama kutusuna `mysql` yaz
- **Alternatif isim:** `MySQL Databases`

### Yeni Database

1. `Create new Database`
2. Doldur:
   - **Database Name:** `entegrator_portal` → otomatik prefix ile `codega_entegrator_portal` olur
   - **Database Username:** `entegrator_portal` → `codega_entegrator_portal`
   - **Password:** `Random` butonuyla güçlü şifre üret — **not al**
3. `Create`

### Doğrulama

phpMyAdmin'de `codega_entegrator_portal` görünmeli, boş olmalı.

## Adım 3 — Dosyaları Yükle

Subdomain dizinini temizle ve portal dosyalarını buraya aç.

### SSH ile (hızlı yol)

```bash
ssh codega@codega.com.tr

cd /home/codega/domains/codega.com.tr/public_html/entegrator/

# Eski içerik varsa temizle (örn. eski karşılaştırma sitesi)
# DİKKAT: Yedek almadan silme
rm -rf ./* ./.htaccess 2>/dev/null
ls -la   # boş olmalı

# Portal'ı buraya aç
cd /tmp
wget https://github.com/codegatr/entegrator-portal/releases/latest/download/entegrator-portal-v1.0.3.zip
unzip entegrator-portal-v1.0.3.zip -d /home/codega/domains/codega.com.tr/public_html/entegrator/

cd /home/codega/domains/codega.com.tr/public_html/entegrator/
ls -la
# index.php, login.php, config.example.php, install.php, .htaccess vb. görmelisin
```

### ZIP ile (File Manager üzerinden)

1. ZIP indir: https://github.com/codegatr/entegrator-portal/releases/latest (`entegrator-portal-v1.0.3.zip`)
2. DirectAdmin → `File Manager`
3. `domains/codega.com.tr/public_html/entegrator/` dizinine git
4. Mevcut içeriği temizle (yedek al!)
5. Üst menüden `Upload` → ZIP'i yükle
6. ZIP'e sağ tık → `Extract`
7. Ekran görüntündeki gibi görünmeli: `includes/`, `libs/`, `public/`, `storage/`, ... — eğer **public/** klasörü varsa **eski sürüm** (v1.0.2 ve öncesi). v1.0.3 ZIP'inde public/ dizini YOK, her şey doğrudan kökte.

> ⚠️ **ZIP içinde bir alt klasör varsa** (ör. `entegrator-portal-v1.0.3/` klasörünün içinde dosyalar), o klasörün **içeriğini** taşı, klasörün kendisini değil. Son halinde `entegrator/` dizini altında doğrudan `index.php`, `.htaccess`, `install.php` vs. olmalı.

### Git ile (güncellenebilir)

```bash
cd /home/codega/domains/codega.com.tr/public_html/
rm -rf entegrator/*          # içeriği boşalt, klasörü silme
cd entegrator
git init
git remote add origin https://github.com/codegatr/entegrator-portal.git
git fetch origin
git checkout -f main
ls -la
```

## Adım 4 — PHP Sürümü

DirectAdmin → `Domain Setup` → `codega.com.tr` → `PHP Version Selector` (veya `Select PHP Version`)

- Subdomain için **PHP 8.3** seç
- Aktif eklentileri kontrol et:
  - ✅ `pdo_mysql`
  - ✅ `openssl`
  - ✅ `dom` (libxml dahil)
  - ✅ `mbstring`
  - ✅ `json`
  - ✅ `curl`

## Adım 5 — SSL Sertifikası

DirectAdmin → `SSL Certificates` → `entegrator.codega.com.tr`:
- `Free & automatic certificate from Let's Encrypt`
- `Obtain Certificate`
- 1-2 dakika bekle, aktifleşir.

## Adım 6 — Installer'ı Çalıştır

Tarayıcıda:

### **https://entegrator.codega.com.tr/install.php**

5 adımlı sihirbaz:

1. **Sistem Kontrolü** — PHP sürümü, eklentiler, yazma izinleri otomatik kontrol
2. **Veritabanı** — Adım 2'deki bilgiler (host: `localhost`, DB adı + user: `codega_entegrator_portal`, şifre)
3. **Firma Bilgileri** — CODEGA gerçek VKN, vergi dairesi, adres vs.
4. **Admin Hesabı** — Kullanıcı adı + güçlü şifre (en az 8 karakter)
5. **Tamam!** — config.php yazılır, 7 tablo oluşur, admin kullanıcı seed'lenir, `install.lock` dosyası oluşur

Installer tamamlandığında "Portal'a Giriş Yap" butonu çıkar.

## Adım 7 — install.php Dosyasını Sil (Güvenlik!)

Kurulum tamamlandıktan sonra installer'ı sunucudan sil:

```bash
ssh codega@codega.com.tr
cd /home/codega/domains/codega.com.tr/public_html/entegrator/
rm install.php
```

veya DirectAdmin File Manager → `install.php` → seç → **Delete**

> `install.lock` dosyası installer'ı kilitler ama yine de `install.php` dosyasını silmek en güvenli yol.

## Adım 8 — İlk Giriş ve Test

**https://entegrator.codega.com.tr/**

Installer'da belirlediğin kullanıcı adı + şifre ile giriş yap.

Test sırası:
1. **Yeni Müşteri ekle** (Müşteriler > Yeni)
2. **Yeni Fatura oluştur** (Yeni Fatura) — birkaç satır, KDV %20
3. **Fatura Detay** → XML görüntüleyici çalışıyor mu?
4. **XML İndir** → `.xml` dosyası iniyor mu?

---

# B) Manuel Kurulum (Installer Kullanmadan)

## B.1 — Adım 1-4 (subdomain, MySQL, dosya yükleme, PHP) aynı

## B.2 — config.php Elle Hazırla

```bash
cd /home/codega/domains/codega.com.tr/public_html/entegrator/
cp config.example.php config.php
nano config.php
```

Güncelle:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'codega_entegrator_portal');
define('DB_USER', 'codega_entegrator_portal');
define('DB_PASS', 'ADIM_2_DE_NOT_ALDIGIN_SIFRE');

define('SITE_URL',   'https://entegrator.codega.com.tr');

define('FIRMA_ADI',          'CODEGA Yazılım Hizmetleri');
define('FIRMA_VKN',          '1234567890');              // ⚠ GERÇEK VKN
define('FIRMA_VERGI_DAIRESI','Selçuk');                  // ⚠ GERÇEK
// ... diğer firma bilgileri

define('CSRF_SECRET', 'rastgele_32_karakter_string');    // üret: php -r "echo bin2hex(random_bytes(16));"
define('ADMIN_DEFAULT_PASS', 'GuclusifreSec123!');       // ilk admin şifresi

define('DEBUG_MODE', false);   // prod'da MUTLAKA false
```

İzinler:
```bash
chmod 600 config.php
chmod -R 755 storage/
```

## B.3 — Tarayıcıdan Aç

`https://entegrator.codega.com.tr/` → migration otomatik çalışır, tablolar oluşur, `admin` kullanıcısı seed'lenir.

Giriş → zorunlu şifre değişimi → dashboard.

---

# Kurulum Sonrası Sağlık Kontrolü

SSH'dan:

```bash
php -r "
require '/home/codega/domains/codega.com.tr/public_html/entegrator/config.php';
\$tables = \$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'Tablo sayisi: ' . count(\$tables) . PHP_EOL;
echo 'Tablolar: ' . implode(', ', \$tables) . PHP_EOL;
echo 'Admin sayisi: ' . \$pdo->query(\"SELECT COUNT(*) FROM kullanicilar WHERE rol='admin'\")->fetchColumn() . PHP_EOL;
"
```

Beklenen:
```
Tablo sayisi: 7
Tablolar: ayarlar, fatura_log, fatura_satirlari, faturalar, kullanicilar, mukellefler, sistem_log
Admin sayisi: 1
```

Web tarafı kontrol listesi:

```
☐ https://entegrator.codega.com.tr             → login ekranı
☐ https://entegrator.codega.com.tr/config.php   → 403 Forbidden (ERİŞİLMEMELİ)
☐ https://entegrator.codega.com.tr/storage/     → 403 Forbidden
☐ https://entegrator.codega.com.tr/includes/    → 403 Forbidden
☐ https://entegrator.codega.com.tr/libs/        → 403 Forbidden
☐ https://entegrator.codega.com.tr/install.php  → 404 Not Found (silmiş olmalısın)
☐ https://entegrator.codega.com.tr/KURULUM.md   → 403 Forbidden
☐ Dashboard girişi başarılı
☐ Test müşterisi eklendi
☐ Test faturası oluşturuldu, XML üretildi
```

`config.php` web'den erişilebiliyorsa (HTML/PHP kaynak kodu dönüyorsa) **ACİL** — Apache'de `mod_rewrite` veya `AllowOverride` kapalı demektir. Hosting'ten talep et.

Hızlı test:
```bash
curl -s -o /dev/null -w "%{http_code}" https://entegrator.codega.com.tr/config.php
# 403 veya 404 beklenir. 200 geliyorsa GÜVENLİK AÇIĞI.
```

---

# Güncelleme

## Git ile (önerilen)

```bash
cd /home/codega/domains/codega.com.tr/public_html/entegrator/
git pull
# config.php, storage/, install.lock — .gitignore'da, dokunulmaz
```

## ZIP ile

1. Yeni ZIP'i indir, aç
2. `config.php`, `storage/`, `install.lock` **hariç** tüm dosya/dizini üzerine yaz
3. Tarayıcıdan portal'ı aç — migration otomatik yeni tabloları ekler

---

# Yedekleme Cron'u

DirectAdmin → `Cron Jobs`:

**Haftalık DB yedeği (Pazar 03:00):**
```
0 3 * * 0 mysqldump -u codega_entegrator_portal -p'DB_SIFRE' codega_entegrator_portal | gzip > /home/codega/domains/codega.com.tr/public_html/entegrator/storage/backups/db_$(date +\%Y\%m\%d).sql.gz
```

**Aylık XML yedeği (ayın 1'i 04:00):**
```
0 4 1 * * tar -czf /home/codega/backups/entegrator-xml-$(date +\%Y\%m).tar.gz /home/codega/domains/codega.com.tr/public_html/entegrator/storage/xml/
```

İlk seferinde:
```bash
mkdir -p /home/codega/backups
chmod 700 /home/codega/backups
```

---

# Sorun Giderme

### "MySQL Management bulunamadı"

- Klasik arayüz: `Account Manager` kategorisi → MySQL Management
- Evolution arayüzü: Üstte arama kutusu → `mysql` yaz
- Yoksa hosting'e açtır

### "Installer 'dizin yazılamıyor' diyor"

```bash
chmod 755 /home/codega/domains/codega.com.tr/public_html/entegrator/
```

### "config.php yazıldı ama 500 hatası veriyor"

```bash
# Error log
tail -50 /home/codega/domains/codega.com.tr/logs/error.log

# Geçici olarak debug aç
nano /home/codega/domains/codega.com.tr/public_html/entegrator/config.php
# DEBUG_MODE'u true yap, hatayı oku, sorunu çöz, false yap
```

### "config.php web'den erişilebilir oluyor (kötü senaryo!)"

```bash
# Test:
curl -v https://entegrator.codega.com.tr/config.php 2>&1 | grep -E "HTTP|<\?"
```

Kaynak kodu dönüyorsa `.htaccess` çalışmıyor demektir. Olası sebepler:
1. Apache `AllowOverride None` — hosting sağlayıcıdan `AllowOverride All` iste
2. `.htaccess` dosyası yüklenmemiş — File Manager'da görünüyor mu kontrol et (gizli dosyaları göster seçeneğiyle)
3. `mod_rewrite` devredışı — `php -m` ile kontrol, aktif değilse hosting'e söyle

### "Müşteri autocomplete çalışmıyor"

Browser DevTools > Network:
- `/api/mukellef-search.php?q=test` → 200 + JSON bekleniyor
- 404 → dosya eksik, yeniden yükle
- 401 → oturum düşmüş

### "install.lock'u sildim, tekrar çalışmıyor"

Session sorunu. Tarayıcıyı tamamen kapat, aç. Veya Incognito.

### "Fatura oluşturuluyor ama XML dosyası yazılmıyor"

```bash
chmod -R 755 /home/codega/domains/codega.com.tr/public_html/entegrator/storage/
```

---

# Yardım

- Bug report: https://github.com/codegatr/entegrator-portal/issues
- E-posta: info@codega.com.tr

Kurulumda takılırsan ekran görüntüsü + hata mesajı at, çözeriz.
