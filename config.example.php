<?php
/**
 * entegrator-portal — konfigürasyon
 *
 * ⚠️ Bu dosya update ZIP'lerine DAHİL EDİLMEZ.
 * ⚠️ İlk kurulumda config.example.php'den kopyalanır, düzenlenir.
 */

// ── Veritabanı ──────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'codega_entegrator_portal');
define('DB_USER', 'codega_entegrator_portal');
define('DB_PASS', '___DB_PASSWORD_HERE___');
define('DB_CHARSET', 'utf8mb4');

// ── Site ────────────────────────────────────────────────────
define('SITE_URL',   'https://efatura.codega.com.tr');
define('SITE_NAME',  'CODEGA e-Fatura Portal');
define('SITE_SHORT', 'CODEGA Portal');
define('SITE_OWNER', 'CODEGA');
define('CONTACT_EMAIL', 'info@codega.com.tr');

// ── Firma (sabit — CODEGA kendi bilgileri, GÜNCELLE) ──────
// ileride multi-tenant olunca bu değerler mukellefler tablosuna taşınır
define('FIRMA_ADI',          'CODEGA Yazılım Hizmetleri');
define('FIRMA_VKN',          '1234567890');       // ⚠️ Gerçek VKN ile değiştir
define('FIRMA_VERGI_DAIRESI','Selçuk');
define('FIRMA_ADRES',        'Yazılım Caddesi No:42');
define('FIRMA_ILCE',         'Selçuklu');
define('FIRMA_IL',           'Konya');
define('FIRMA_POSTA_KODU',   '42050');
define('FIRMA_TELEFON',      '+90 332 000 00 00');
define('FIRMA_EMAIL',        'info@codega.com.tr');
define('FIRMA_WEBSITE',      'https://codega.com.tr');

// ── Fatura Numarası Serisi ───────────────────────────────
// 3 büyük harf; her yıl başında sıra 1'den başlar
define('FATURA_SERI_KODU', 'COD');

// ── Default Admin (ilk çalıştırmada oluşur) ─────────────
define('ADMIN_DEFAULT_USER', 'admin');
define('ADMIN_DEFAULT_PASS', 'admin123');   // ilk girişte zorunlu değiştirilir

// ── Güvenlik ────────────────────────────────────────────
define('CSRF_SECRET', '___RANDOM_32_CHAR_SECRET___');
define('SESSION_NAME', 'codega_portal_session');
define('SESSION_LIFETIME', 28800);  // 8 saat

// ── Paths ──────────────────────────────────────────────
define('ROOT_PATH',     dirname(__DIR__));  // /public'in parent'ı
define('STORAGE_PATH',  ROOT_PATH . '/storage');
define('XML_PATH',      STORAGE_PATH . '/xml');
define('PDF_PATH',      STORAGE_PATH . '/pdf');
define('CERT_PATH',     STORAGE_PATH . '/certs');
define('BACKUP_PATH',   STORAGE_PATH . '/backups');
define('LIBS_PATH',     ROOT_PATH . '/libs');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// ── PHP Ayarları ──────────────────────────────────────
date_default_timezone_set('Europe/Istanbul');
mb_internal_encoding('UTF-8');

// ── Hata Gösterme (prod'da KAPATILACAK) ───────────────
define('DEBUG_MODE', true);  // prod'da false
if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH . '/php-error.log');
}

// ── CodegaGib Kütüphanesi Autoload ────────────────────
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'CodegaGib\\')) {
        $path = LIBS_PATH . '/entegrator-gib/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($path)) {
            require $path;
        }
    }
});

// ── PDO ────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('DB bağlantı hatası. Lütfen yönetici ile iletişime geçin.');
}

// ── Session ───────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
