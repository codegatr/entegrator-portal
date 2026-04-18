# Kurulum Yönergeleri

CODEGA Entegratör Portal'ın DirectAdmin shared hosting'e kurulumu. 2 farklı yol:

- **A) Web Installer (önerilen)** — Tarayıcıdan form doldur, her şey otomatik
- **B) Manuel Kurulum** — Dosyaları elle düzenlersin

## Gereksinimler

| Bileşen | Minimum | Önerilen |
|---|---|---|
| PHP | 8.1 | **8.3** |
| MySQL | 5.7 | 8.0 / MariaDB 10.6 |
| PHP eklentileri | `pdo_mysql`, `dom`, `libxml`, `openssl`, `mbstring`, `json`, `curl` | aynı |
| Apache modülleri | `mod_rewrite`, `mod_headers` | + `mod_expires` |
| Disk | 500 MB | 5 GB+ (XML arşivi zamanla büyür) |
| SSL | Let's Encrypt yeterli | Cloudflare + tam SSL |

---

# A) Web Installer ile Kurulum (Önerilen)

## Adım 1 — Subdomain Oluştur

DirectAdmin panelinde:
1. `Subdomain Management` (veya `Domain Setup` → subdomain ekle)
2. Adı: `entegrator`
3. Domain: `codega.com.tr`
4. Oluşan yol: `https://entegrator.codega.com.tr`

## Adım 2 — MySQL Database Oluştur

**DirectAdmin'de MySQL nerede?** Sürüme göre değişir:

- **Klasik arayüz:** `Account Manager` kategorisi → **`MySQL Management`**
- **Evolution arayüzü:** Üstte arama kutusuna `mysql` yaz → çıkar
- **Alternatif isim:** `MySQL Databases`

Bulamazsan hosting sağlayıcına "MySQL oluşturma yetkisi verir misiniz?" yazabilirsin.

### Yeni Database Oluşturma

1. **`Create new Database`** butonuna tıkla
2. Doldur:
   - **Database Name:** `entegrator_portal` — tam ad otomatik `codega_entegrator_portal` olur
   - **Database Username:** `entegrator_portal` — tam ad `codega_entegrator_portal` olur
   - **Password:** **Random** butonuna tıkla (güçlü şifre üretir)
   - Şifreyi **not al** — birazdan installer'a gireceksin
3. `Create`

### Doğrulama

DirectAdmin'de `phpMyAdmin` aç → sol listede `codega_entegrator_portal` görmeli, tıkla → boş (0 tablo) olmalı.

## Adım 3 — Dosyaları Yükle

### SSH ile (önerilen)

```bash
ssh codega@codega.com.tr

cd /home/codega/domains/codega.com.tr/public_html/
# Eski boş entegrator/ klasörünü temizle (varsa)
rm -rf entegrator

# Portal'ı clone et
git clone https://github.com/codegatr/entegrator-portal.git entegrator
cd entegrator
ls -la
# public/, includes/, libs/, storage/ vb. görmelisin
```

### ZIP ile (SSH yoksa)

1. İndir: https://github.com/codegatr/entegrator-portal/releases/latest
2. DirectAdmin File Manager → `domains/codega.com.tr/public_html/entegrator/`
3. Upload → ZIP'i yükle → Extract

## Adım 4 — Subdomain Document Root'unu Ayarla

**Bu adım kritik — güvenlik için şart.**

DirectAdmin → `Subdomain Management` → `entegrator.codega.com.tr` satırında `Edit` veya `Modify Public HTML Path`:

- **Public HTML path:** `/public_html/entegrator/public`
- Kaydet

> ⚠️ Eğer `Public HTML Path` seçeneği yoksa — panel buna izin vermiyorsa — bkz. [Durum B](#durum-b-document-root-değiştirilemezse) bölümü aşağıda.

## Adım 5 — SSL Sertifikası Aktifleştir

Tarayıcı installer'ı açabilsin diye SSL gerekli.

DirectAdmin → `SSL Certificates` → `entegrator.codega.com.tr` için:
- `Free & automatic certificate from Let's Encrypt`
- `Obtain Certificate`

Birkaç dakika bekle, sertifika gelsin.

## Adım 6 — Installer'ı Çalıştır

Tarayıcıda aç:

### **https://entegrator.codega.com.tr/install.php**

Karşına 5 adımlı kurulum sihirbazı gelir:

1. **Sistem Kontrolü** — PHP sürümü, eklentiler, yazma izinleri otomatik kontrol edilir
2. **Veritabanı** — Adım 2'de aldığın bilgileri gir (host: `localhost`, DB adı/user: `codega_entegrator_portal`, şifre)
3. **Firma Bilgileri** — CODEGA'nın gerçek VKN, vergi dairesi, adres vb.
4. **Admin Hesabı** — Kullanıcı adı + güçlü bir şifre belirle (en az 8 karakter)
5. **Tamam!** — config.php yazılır, 7 tablo otomatik oluşur, admin kullanıcın hazır

Installer tamamlandığında → `install.lock` dosyası oluşur, installer tekrar çalışmaz.

## Adım 7 — install.php Dosyasını Sil (Güvenlik)

Kurulum tamamlandıktan sonra installer'ı sunucudan sil:

```bash
ssh codega@codega.com.tr
cd /home/codega/domains/codega.com.tr/public_html/entegrator/
rm public/install.php
```

veya DirectAdmin File Manager → `entegrator/public/install.php` → seç → **Delete**

## Adım 8 — İlk Giriş

Tarayıcıda: **https://entegrator.codega.com.tr/**

Installer'da belirlediğin **kullanıcı adı + şifre** ile giriş yap. Dashboard açılmalı. İlk şeyler:

1. **Yeni Müşteri ekle** (Müşteriler > Yeni) — test için kendi firmana bir VKN ekle
2. **Yeni Fatura oluştur** (Yeni Fatura) — birkaç satır gir → oluştur
3. **Fatura Detay** — XML görüntüleyici çalışıyor mu kontrol et
4. **XML İndir** — dosya iniyor mu kontrol et

Her şey çalışıyorsa kurulum tamam.

---

## Durum B: Document Root Değiştirilemezse

Panel `Public HTML Path` değiştirmeye izin vermiyorsa (bazı ucuz shared hosting'ler kısıtlı). Çözüm: root'a mod_rewrite `.htaccess` koy, tüm istekleri `public/` altına yönlendir.

```bash
cd /home/codega/domains/codega.com.tr/public_html/entegrator/

cat > .htaccess <<'HTACCESS'
# Tüm istekleri public/ altına yönlendir
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ public/$1 [L]

# Hassas dosyalara dışarıdan erişim engeli
RedirectMatch 403 ^/config\.php$
RedirectMatch 403 ^/config\.example\.php$
RedirectMatch 403 ^/includes/
RedirectMatch 403 ^/libs/
RedirectMatch 403 ^/storage/
RedirectMatch 403 ^/\.git
RedirectMatch 403 ^/KURULUM\.md$
RedirectMatch 403 ^/README\.md$
RedirectMatch 403 ^/LICENSE$
HTACCESS
```

Sonra **Adım 5** (SSL) ve **Adım 6** (installer) ile aynı şekilde devam et. Installer `public/install.php`'yi `/install.php` URL'inden açar.

---

# B) Manuel Kurulum (Installer Kullanmadan)

Installer'a güvenmiyorsan veya özel senaryon varsa:

## B.1 — Dosyaları yükle (yukarıdaki Adım 1-4 aynı)

## B.2 — config.php'yi elle hazırla

```bash
cd /home/codega/domains/codega.com.tr/public_html/entegrator/
cp config.example.php config.php
nano config.php
```

Şu değerleri güncelle:
- `DB_NAME`, `DB_USER`, `DB_PASS`
- `SITE_URL`, `FIRMA_VKN`, `FIRMA_VERGI_DAIRESI`, adres bilgileri
- `ADMIN_DEFAULT_PASS` (ilk admin şifresi — sonra değişir)
- `CSRF_SECRET` (üret: `php -r "echo bin2hex(random_bytes(16));"`)

```bash
chmod 600 config.php
chmod -R 755 storage/
```

## B.3 — İlk Açılış

Tarayıcıda `https://entegrator.codega.com.tr/` aç. Migration otomatik çalışır, tablolar oluşur, admin hesabı `admin` / (config'deki) şifreyle seed'lenir.

Giriş → zorunlu şifre değişimi → dashboard.

---

# Kurulum Sonrası Sağlık Kontrolü

Her iki yolda da sonrası şunları test et:

```bash
# SSH'dan 10 saniyelik health check
php -r "
require '/home/codega/domains/codega.com.tr/public_html/entegrator/config.php';
\$tables = \$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'Tablo sayisi: ' . count(\$tables) . PHP_EOL;
echo 'Tablolar: ' . implode(', ', \$tables) . PHP_EOL;
echo 'Admin kullanici: ' . \$pdo->query(\"SELECT COUNT(*) FROM kullanicilar WHERE rol='admin'\")->fetchColumn() . PHP_EOL;
echo 'Kurulum kaydi: ' . \$pdo->query(\"SELECT COUNT(*) FROM sistem_log WHERE olay='install.complete'\")->fetchColumn() . PHP_EOL;
"
```

Beklenen çıktı:
```
Tablo sayisi: 7
Tablolar: ayarlar, fatura_log, fatura_satirlari, faturalar, kullanicilar, mukellefler, sistem_log
Admin kullanici: 1
Kurulum kaydi: 1   (installer ile kurduysan)
```

Web tarafı:
```
☐ https://entegrator.codega.com.tr → login ekranı
☐ https://entegrator.codega.com.tr/config.php → 403 veya boş (ERİŞİLMEMELİ)
☐ https://entegrator.codega.com.tr/storage/ → 403 (ERİŞİLMEMELİ)
☐ https://entegrator.codega.com.tr/install.php → 404 veya yok (silinmiş olmalı)
☐ Giriş başarılı
☐ Dashboard açıldı
```

---

# Güncelleme (Update)

Yeni sürüm çıktığında mevcut kurulumu nasıl yenilersin:

## Git ile (önerilen)

```bash
cd /home/codega/domains/codega.com.tr/public_html/entegrator/
git pull
# config.php .gitignore'da — dokunulmaz
# storage/ .gitignore'da — dokunulmaz
# install.lock'a dokunulmaz
```

## ZIP ile

1. Yeni release ZIP'ini indir
2. `public/`, `includes/`, `libs/` dizinlerini üzerine yaz
3. `config.php`, `storage/`, `install.lock` dosyalarına **dokunma**
4. Tarayıcıdan aç — migration otomatik yeni tabloları ekler (varsa)

---

# Yedekleme (Kritik!)

DirectAdmin → **Cron Jobs** → `Create a new Cron Job`:

**Haftalık DB yedeği** (Pazar 03:00):
```
0 3 * * 0 mysqldump -u codega_entegrator_portal -p'DB_SIFRE' codega_entegrator_portal | gzip > /home/codega/domains/codega.com.tr/public_html/entegrator/storage/backups/db_$(date +\%Y\%m\%d).sql.gz
```

**Aylık XML yedeği** (ayın 1'i 04:00):
```
0 4 1 * * tar -czf /home/codega/backups/entegrator-xml-$(date +\%Y\%m).tar.gz /home/codega/domains/codega.com.tr/public_html/entegrator/storage/xml/
```

İlk seferde:
```bash
mkdir -p /home/codega/backups
chmod 700 /home/codega/backups
```

---

# Sorun Giderme

### "MySQL Management bulunamadı"

DirectAdmin sürümüne göre:
- Klasik arayüz: `Account Manager` → MySQL Management
- Evolution: üstte arama kutusu → `mysql` yaz
- Yine yoksa: hosting sağlayıcına talep et

### "Installer 'config.php yazılamıyor' diyor"

Dizin yazma izni yok. SSH'dan:
```bash
cd /home/codega/domains/codega.com.tr/public_html/
chmod 755 entegrator/
# Installer'ı tekrar çalıştır
```

### "DB bağlantı hatası: Access denied"

MySQL kullanıcısına yetki verilmemiş olabilir. DirectAdmin MySQL Management'ta:
- Database adına tıkla
- User listesinde doğru kullanıcıyı ekle, "All privileges" ver

### "500 Internal Server Error"

```bash
# Error log
tail -50 /home/codega/domains/codega.com.tr/logs/error.log

# Geçici debug
nano config.php
# DEBUG_MODE'u true yap, sorunu gör, false yap
```

### "Installer'a erişemiyorum (404)"

- Document root `public/` olarak ayarlanmadı → Adım 4'ü kontrol et
- SSL aktif değil → Adım 5'i kontrol et
- DNS propagate olmadı → 15 dk bekle, `ping entegrator.codega.com.tr` dene

### "install.lock siliyorum ama installer tekrar çalışmıyor"

Session sorunu. Tarayıcıyı tamamen kapat, aç. Veya Incognito pencere kullan.

### "Müşteri autocomplete çalışmıyor (yeni fatura sayfası)"

Browser'ın Network sekmesinde:
- `/api/mukellef-search.php?q=xxx` → 200 ve JSON dönüyor mu?
- 404 → `public/api/` dizini yok, dosyaları tekrar yükle
- 401 → oturum düşmüş, tekrar giriş yap

### "Fatura oluşturuluyor ama XML dosyası diske yazılmıyor"

```bash
ls -la /home/codega/domains/codega.com.tr/public_html/entegrator/storage/xml/
# Sahibi codega, 755 olmalı
chmod -R 755 storage/
```

---

# Yardım

- Bug report: https://github.com/codegatr/entegrator-portal/issues
- E-posta: info@codega.com.tr

Kurulumda takılırsan ekran görüntüsü + hata mesajı paylaş — çözeriz.
