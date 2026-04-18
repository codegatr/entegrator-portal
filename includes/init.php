<?php
/**
 * init.php — Migration + seed. Her sayfa yüklemede çalışır, idempotent.
 *
 * Üretilen tablolar:
 *   - kullanicilar        Admin/operator login
 *   - mukellefler         Alıcı firmalar (müşteriler)
 *   - faturalar           Üretilen e-faturalar
 *   - fatura_satirlari    Fatura detayları
 *   - fatura_log          Her durum değişimi (arka plan takip)
 *   - sistem_log          Audit trail (ISO 27001 için)
 *   - ayarlar             Key-value genel ayarlar
 */

require_once __DIR__ . '/../config.php';

// ═══ TABLOLAR ══════════════════════════════════════════════

$pdo->exec("CREATE TABLE IF NOT EXISTS kullanicilar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(50) UNIQUE NOT NULL,
    sifre_hash VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(100) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    rol ENUM('admin','operator','viewer') DEFAULT 'operator',
    aktif TINYINT(1) DEFAULT 1,
    sifre_degistirildi TINYINT(1) DEFAULT 0,
    son_giris DATETIME DEFAULT NULL,
    son_ip VARCHAR(45) DEFAULT NULL,
    yanlis_giris_sayisi INT UNSIGNED DEFAULT 0,
    kilit_bitis DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS mukellefler (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vkn_tckn VARCHAR(11) UNIQUE NOT NULL,
    vkn_tip ENUM('VKN','TCKN') NOT NULL,
    unvan VARCHAR(200) NOT NULL,
    adi VARCHAR(50) DEFAULT NULL,
    soyadi VARCHAR(50) DEFAULT NULL,
    vergi_dairesi VARCHAR(100) DEFAULT NULL,
    adres TEXT DEFAULT NULL,
    ilce VARCHAR(50) DEFAULT NULL,
    il VARCHAR(50) NOT NULL,
    posta_kodu VARCHAR(10) DEFAULT NULL,
    ulke VARCHAR(50) DEFAULT 'Türkiye',
    telefon VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    e_fatura_mukellefi TINYINT(1) DEFAULT 0,
    notlar TEXT DEFAULT NULL,
    aktif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_unvan (unvan),
    KEY idx_aktif (aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS faturalar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fatura_no VARCHAR(20) UNIQUE NOT NULL,
    ettn CHAR(36) UNIQUE NOT NULL,
    mukellef_id INT UNSIGNED NOT NULL,
    profil ENUM('TEMELFATURA','TICARIFATURA','EARSIVFATURA','IHRACAT') DEFAULT 'TEMELFATURA',
    tipi ENUM('SATIS','IADE','TEVKIFAT','ISTISNA','OZELMATRAH','IHRACKAYITLI') DEFAULT 'SATIS',
    duzenleme_tarihi DATE NOT NULL,
    duzenleme_saati TIME DEFAULT NULL,
    para_birimi VARCHAR(3) DEFAULT 'TRY',
    matrah DECIMAL(18,2) NOT NULL DEFAULT 0,
    kdv_toplam DECIMAL(18,2) NOT NULL DEFAULT 0,
    genel_toplam DECIMAL(18,2) NOT NULL DEFAULT 0,
    notlar TEXT DEFAULT NULL,
    durum ENUM('taslak','hazir','imzali','gonderildi','kabul','red','iptal') DEFAULT 'taslak',
    xml_path VARCHAR(255) DEFAULT NULL,
    pdf_path VARCHAR(255) DEFAULT NULL,
    imzalayan_id INT UNSIGNED DEFAULT NULL,
    imza_tarihi DATETIME DEFAULT NULL,
    gib_referans VARCHAR(100) DEFAULT NULL,
    gib_gonderim_tarihi DATETIME DEFAULT NULL,
    gib_yanit TEXT DEFAULT NULL,
    kullanici_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_mukellef (mukellef_id),
    KEY idx_durum (durum),
    KEY idx_tarih (duzenleme_tarihi),
    KEY idx_fatura_no (fatura_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS fatura_satirlari (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fatura_id INT UNSIGNED NOT NULL,
    sira INT UNSIGNED NOT NULL,
    urun_adi VARCHAR(200) NOT NULL,
    aciklama TEXT DEFAULT NULL,
    miktar DECIMAL(18,8) NOT NULL,
    birim_kodu VARCHAR(10) DEFAULT 'C62',
    birim_fiyat DECIMAL(18,4) NOT NULL,
    iskonto DECIMAL(18,2) DEFAULT 0,
    kdv_oran DECIMAL(5,2) DEFAULT 20,
    matrah DECIMAL(18,2) NOT NULL,
    kdv_tutar DECIMAL(18,2) NOT NULL,
    satir_toplam DECIMAL(18,2) NOT NULL,
    urun_kodu VARCHAR(50) DEFAULT NULL,
    KEY idx_fatura (fatura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS fatura_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fatura_id INT UNSIGNED NOT NULL,
    onceki_durum VARCHAR(20) DEFAULT NULL,
    yeni_durum VARCHAR(20) NOT NULL,
    aciklama TEXT DEFAULT NULL,
    kullanici_id INT UNSIGNED DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fatura (fatura_id),
    KEY idx_tarih (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS sistem_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kullanici_id INT UNSIGNED DEFAULT NULL,
    olay VARCHAR(100) NOT NULL,
    detay TEXT DEFAULT NULL,
    hedef VARCHAR(100) DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kullanici (kullanici_id),
    KEY idx_olay (olay),
    KEY idx_tarih (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS ayarlar (
    anahtar VARCHAR(80) PRIMARY KEY,
    deger TEXT DEFAULT NULL,
    aciklama VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ═══ v1.2.1+ KOLON EKLENTİLERİ (EDM tarzı detaylı takip) ═════
// Bu kolonlar faturalar tablosunda yoksa eklenir. Idempotent.
try {
    $pdo->exec("ALTER TABLE faturalar
        ADD COLUMN IF NOT EXISTS mail_gonderim ENUM('beklemede','gonderildi','basarisiz','gonderilmedi') DEFAULT 'gonderilmedi' AFTER gib_yanit,
        ADD COLUMN IF NOT EXISTS mail_gonderim_tarihi DATETIME DEFAULT NULL AFTER mail_gonderim,
        ADD COLUMN IF NOT EXISTS portal_goruntuleme INT UNSIGNED DEFAULT 0 AFTER mail_gonderim_tarihi,
        ADD COLUMN IF NOT EXISTS portal_son_goruntuleme DATETIME DEFAULT NULL AFTER portal_goruntuleme,
        ADD COLUMN IF NOT EXISTS konnektor_durumu ENUM('yok','bekliyor','basarili','basarisiz') DEFAULT 'yok' AFTER portal_son_goruntuleme,
        ADD COLUMN IF NOT EXISTS departman VARCHAR(100) DEFAULT NULL AFTER konnektor_durumu,
        ADD COLUMN IF NOT EXISTS irsaliye_no VARCHAR(50) DEFAULT NULL AFTER departman,
        ADD COLUMN IF NOT EXISTS ozel_alan_1 VARCHAR(255) DEFAULT NULL AFTER irsaliye_no,
        ADD COLUMN IF NOT EXISTS ozel_alan_2 VARCHAR(255) DEFAULT NULL AFTER ozel_alan_1");
} catch (\PDOException $e) {
    // MariaDB < 10.0.2 / MySQL < 8.0.29 IF NOT EXISTS desteklemiyor — sessizce geç
    // (zaten kurulu bir sistemde sorun yok, ilk kurulumda tablo yukarıda oluşuyor)
}

// ═══ MÜŞTERİ PORTALI TABLOLARI (v1.2.0+) ═════════════════

$pdo->exec("CREATE TABLE IF NOT EXISTS musteri_portal_kullanicilar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mukellef_id INT UNSIGNED NOT NULL,
    kullanici_adi VARCHAR(100) UNIQUE NOT NULL,
    sifre_hash VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(100) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    telefon VARCHAR(30) DEFAULT NULL,
    aktif TINYINT(1) DEFAULT 1,
    sifre_degistirildi TINYINT(1) DEFAULT 0,
    son_giris DATETIME DEFAULT NULL,
    son_ip VARCHAR(45) DEFAULT NULL,
    yanlis_giris_sayisi INT UNSIGNED DEFAULT 0,
    kilit_bitis DATETIME DEFAULT NULL,
    davet_token VARCHAR(64) DEFAULT NULL,
    davet_bitis DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_mukellef (mukellef_id),
    KEY idx_aktif (aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS musteri_portal_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    musteri_kullanici_id INT UNSIGNED DEFAULT NULL,
    mukellef_id INT UNSIGNED DEFAULT NULL,
    olay VARCHAR(100) NOT NULL,
    detay TEXT DEFAULT NULL,
    hedef VARCHAR(100) DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kullanici (musteri_kullanici_id),
    KEY idx_mukellef (mukellef_id),
    KEY idx_olay (olay),
    KEY idx_tarih (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ═══ DUYURULAR (v1.3.0+) — Admin'in müşterilere duyuru yayınlaması için
$pdo->exec("CREATE TABLE IF NOT EXISTS duyurular (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    baslik VARCHAR(200) NOT NULL,
    icerik TEXT NOT NULL,
    tip ENUM('bilgi','uyari','onemli','bakim') DEFAULT 'bilgi',
    hedef ENUM('musteri','admin','her_ikisi') DEFAULT 'musteri',
    aktif TINYINT(1) DEFAULT 1,
    baslangic_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    bitis_tarihi DATETIME DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_aktif (aktif),
    KEY idx_hedef (hedef),
    KEY idx_bitis (bitis_tarihi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ═══ SEED: İLK ADMIN ═══════════════════════════════════════

try {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM kullanicilar")->fetchColumn();
    if ($cnt === 0) {
        $hash = password_hash(ADMIN_DEFAULT_PASS, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO kullanicilar (kullanici_adi, sifre_hash, ad_soyad, rol, sifre_degistirildi) VALUES (?, ?, ?, ?, 0)")
            ->execute([ADMIN_DEFAULT_USER, $hash, 'Kurucu Admin', 'admin']);
    }
} catch (\Exception $e) {
    error_log('Admin seed: ' . $e->getMessage());
}

// ═══ SEED: VARSAYILAN AYARLAR ═══════════════════════════

$default_settings = [
    'fatura_no_son_sira'  => ['0', 'Son kullanılan fatura sıra numarası (yıllık)'],
    'fatura_no_son_yil'   => [date('Y'), 'Son fatura numarası yılı'],
    'kdv_oranlari'        => ['1,10,20', 'Geçerli KDV oranları (virgülle ayrılmış)'],
    'portal_surumu'       => ['1.0.0', 'Portal sürümü'],
    'kutuphane_surumu'    => ['0.1.0-alpha', 'entegrator-gib kütüphane sürümü'],
];
$st = $pdo->prepare("INSERT IGNORE INTO ayarlar (anahtar, deger, aciklama) VALUES (?, ?, ?)");
foreach ($default_settings as $k => [$v, $desc]) {
    $st->execute([$k, $v, $desc]);
}

// ═══ Dizinleri oluştur (ilk çalıştırmada) ═════════════

foreach ([XML_PATH, PDF_PATH, CERT_PATH, BACKUP_PATH] as $p) {
    if (!is_dir($p)) @mkdir($p, 0755, true);
}

// ═══ Helper: Sonraki fatura numarası ═══════════════════

function next_fatura_no(PDO $pdo): string
{
    $year = date('Y');
    // Yıl değiştiyse sırayı sıfırla
    $son_yil = $pdo->query("SELECT deger FROM ayarlar WHERE anahtar='fatura_no_son_yil'")->fetchColumn();
    if ($son_yil !== $year) {
        $pdo->prepare("UPDATE ayarlar SET deger=? WHERE anahtar='fatura_no_son_yil'")->execute([$year]);
        $pdo->prepare("UPDATE ayarlar SET deger='0' WHERE anahtar='fatura_no_son_sira'")->execute();
    }
    $pdo->beginTransaction();
    try {
        $son = (int)$pdo->query("SELECT deger FROM ayarlar WHERE anahtar='fatura_no_son_sira' FOR UPDATE")->fetchColumn();
        $yeni = $son + 1;
        $pdo->prepare("UPDATE ayarlar SET deger=? WHERE anahtar='fatura_no_son_sira'")->execute([$yeni]);
        $pdo->commit();
    } catch (\Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    // Format: 3 harf + 4 yıl + 9 sıra = 16 karakter
    return FATURA_SERI_KODU . $year . str_pad((string)$yeni, 9, '0', STR_PAD_LEFT);
}

// Helper: XML dosya yolu (tarih bazlı klasörler)
function xml_store_path(string $ettn, string $fatura_no): string
{
    $dir = XML_PATH . '/' . date('Y') . '/' . date('m');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/' . $fatura_no . '_' . substr($ettn, 0, 8) . '.xml';
}

// Helper: Ayar oku
function ayar_get(PDO $pdo, string $key, ?string $default = null): ?string
{
    $v = $pdo->prepare("SELECT deger FROM ayarlar WHERE anahtar=?");
    $v->execute([$key]);
    return $v->fetchColumn() ?: $default;
}
function ayar_set(PDO $pdo, string $key, ?string $value): void
{
    $pdo->prepare("INSERT INTO ayarlar (anahtar, deger) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE deger=VALUES(deger)")->execute([$key, $value]);
}

// Helper: fatura durum değişimi + log
function fatura_durum_degistir(PDO $pdo, int $fatura_id, string $yeni_durum, ?string $aciklama = null): void
{
    $onc = $pdo->prepare("SELECT durum FROM faturalar WHERE id=?");
    $onc->execute([$fatura_id]);
    $onceki = $onc->fetchColumn() ?: null;
    if ($onceki === $yeni_durum) return;

    $pdo->prepare("UPDATE faturalar SET durum=? WHERE id=?")->execute([$yeni_durum, $fatura_id]);
    $pdo->prepare("INSERT INTO fatura_log (fatura_id, onceki_durum, yeni_durum, aciklama, kullanici_id, ip) VALUES (?,?,?,?,?,?)")
        ->execute([$fatura_id, $onceki, $yeni_durum, $aciklama, $_SESSION['user_id'] ?? null, client_ip()]);
}
