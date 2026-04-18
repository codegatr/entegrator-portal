# CODEGA e-Fatura Portal

**Web arayüzlü e-Fatura oluşturma + yönetim portalı.** PHP 8.3 + MySQL + [entegrator-gib](https://github.com/codegatr/entegrator-gib) kütüphanesi tabanlı.

> Bu, CODEGA'nın GİB Onaylı Özel Entegratör olma sürecinde ilerlerken kullanacağı iç **çalışma portalıdır** (Seviye 2). Gerçek entegratör olunduğunda müşteriler için evrilecek.

## Özellikler (v1.0)

- **Login + session** — bcrypt şifre, rol tabanlı erişim (admin / operator / viewer), 5 yanlış denemede 10 dk kilit
- **Dashboard** — KPI kartları, son faturalar, durum dağılımı, sistem sağlığı, aktivite timeline
- **Müşteri (Mükellef) Yönetimi** — VKN/TCKN auto-detect, vergi dairesi validasyonu, e-fatura mükellef flag'i
- **Fatura Oluşturma** — 4 profil (TEMELFATURA / TICARIFATURA / EARSIVFATURA / IHRACAT) × 6 tür, çok satır + KDV oran seçimi, canlı tutar hesabı, AJAX müşteri arama
- **UBL-TR 2.1 XML üretimi** — `entegrator-gib` kütüphanesi ile, **tarih bazlı klasörlerde** depolama (`storage/xml/YYYY/MM/...`)
- **Fatura Detay** — 2 sütun layout, satır tablosu, **syntax-highlighted XML görüntüleyici**, durum timeline'ı, iptal aksiyon
- **Fatura Listesi** — filtre (durum/tarih/arama), sayfalama, XML indirme (path traversal korumalı)
- **Aktivite Log** — 2 tab: sistem log (audit trail) + fatura durum log (arka plan takip)
- **Yönetim** — kullanıcı CRUD, rol değişimi, şifre sıfırlama, sistem durumu
- **İlk giriş şifre zorlaması** — yeni kullanıcı ilk girişte şifresini mutlaka değiştirmek zorunda

## Teknik

- **Backend:** PHP 8.3 (zero-framework, single-file per page — CODEGA pattern)
- **DB:** MySQL 8 / MariaDB 10.6+, 7 tablo
- **Frontend:** Saf CSS + ~200 satır vanilla JS (jQuery yok)
- **Kütüphane:** [`codegatr/entegrator-gib`](https://github.com/codegatr/entegrator-gib) v0.1.0-alpha (libs/ altına bundle'lı)
- **Migration:** idempotent, her sayfa yüklemede çalışır (`CREATE TABLE IF NOT EXISTS`)

## Kurulum

Detaylı kurulum için [KURULUM.md](KURULUM.md) dosyasına bakın. Özetle:

1. DirectAdmin'de subdomain oluştur (`efatura.codega.com.tr`)
2. Subdomain'in **document root'unu** `public/` klasörüne yönlendir
3. MySQL database + user oluştur
4. ZIP'i sunucuya aç, `config.example.php`'yi `config.php` olarak kopyala ve düzenle
5. Subdomain'i aç → `admin` / `admin123` ile giriş → zorunlu şifre değiştirme

## Güvenlik

- ✅ PDO prepared statements (SQL injection yok)
- ✅ CSRF token tüm POST formlarında
- ✅ XSS kaçış tüm output'larda (`h()` fonksiyonu)
- ✅ Session fixation koruması (`session_regenerate_id` login'de)
- ✅ Rate limiting (5 yanlış = 10 dk kilit)
- ✅ Path traversal koruması (XML download'da `realpath` kontrolü)
- ✅ Storage klasörüne dışarıdan erişim engelli (`.htaccess`)
- ✅ HTTPS zorla (Cloudflare uyumlu)
- ✅ Güvenlik header'ları (X-Frame-Options, CSP hazır)
- ✅ Rol tabanlı erişim (admin/operator/viewer hiyerarşisi)
- ✅ Audit log (her işlem → sistem_log)

## Yol Haritası

**v1.0** ✅ _yayında_
- Portal iskelet, XML üretim

**v1.1** 🔜 — `entegrator-gib` v0.2 ile senkron
- "İmzala" butonu aktif olacak
- Mali mühür yönetim sayfası
- İmzalı XML görüntüleyici

**v1.2** 🔜 — `entegrator-gib` v0.3 ile senkron
- "GİB'e Gönder" butonu aktif
- GİB test ortamı entegrasyonu
- Durum otomatik senkronizasyonu (cron)

**v1.3** — Operasyon
- PDF üretimi (faturaların insan-okur versiyonu)
- E-posta ile fatura gönderimi
- Excel export/import

**v2.0** — Multi-tenant
- Her firma kendi hesabıyla giriş yapabilir
- Abonelik + kontör yönetimi
- Müşteri API (webhook)

## Lisans

MIT License — bkz. [LICENSE](LICENSE)
