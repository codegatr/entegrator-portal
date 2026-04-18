<?php
/**
 * CODEGA Entegratör Portal — Web Installer
 *
 * Tarayıcıdan açılır, adım adım kurulum yapar:
 *   1. Sistem kontrolü (PHP, eklentiler, yazma izinleri)
 *   2. Veritabanı bağlantısı (canlı test)
 *   3. Firma bilgileri (VKN, vergi dairesi, adres)
 *   4. Admin hesabı
 *   5. Kurulum (config.php yazılır, 7 tablo oluşturulur, admin seed'lenir)
 *   6. Kilitlenme (install.lock oluşur, tekrar çalışmaz)
 *
 * Kullanım:
 *   https://entegrator.codega.com.tr/install.php
 *
 * Güvenlik:
 *   - install.lock varsa çalışmaz
 *   - CSRF token her formda
 *   - Config.php yazılınca chmod 0600
 *   - Kurulum sonrası kendini pasif hale getirir
 */

// ═══════════════════════════════════════════════════════════
// SAFETY: Kurulum tamamlandıysa çalıştırma
// ═══════════════════════════════════════════════════════════

// Yapı artık flat — install.php kök dizinde, yanında config.php, includes/, libs/, storage/
$ROOT = __DIR__;

if (!is_dir($ROOT . '/includes') || !is_dir($ROOT . '/libs/entegrator-gib')) {
    die('HATA: Portal dosyaları eksik. install.php aynı dizinde includes/, libs/, storage/ klasörleri olmalı. Dosyalar doğru yere yüklendi mi?');
}

$CONFIG_PATH = $ROOT . '/config.php';
$LOCK_PATH   = $ROOT . '/install.lock';

if (file_exists($LOCK_PATH)) {
    ?><!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Kurulum Tamamlandı</title>
    <style>body{font-family:sans-serif;background:#0f172a;color:#cbd5e1;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px}.box{background:#1e293b;padding:30px;border-radius:12px;max-width:500px;border:1px solid #334155}.box h1{color:#10b981;margin-top:0}code{background:#0f172a;padding:2px 6px;border-radius:4px}</style>
    </head><body><div class="box">
    <h1>✓ Kurulum Tamamlandı</h1>
    <p>Portal zaten kurulmuş durumda. Bu kurulum sihirbazı güvenlik için kilitlendi.</p>
    <p><strong>Portal'a git:</strong> <a href="./" style="color:#f6821f">entegrator.codega.com.tr</a></p>
    <hr style="border-color:#334155;margin:20px 0">
    <p style="font-size:13px;color:#94a3b8">Yeniden kurmak istiyorsan önce şu dosyayı sil:<br>
    <code><?= htmlspecialchars($LOCK_PATH) ?></code></p>
    <p style="font-size:13px;color:#94a3b8">Ve sonra bu dosyayı sil (güvenlik için):<br>
    <code><?= htmlspecialchars(__FILE__) ?></code></p>
    </div></body></html><?php
    exit;
}

session_start();

// CSRF
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['install_csrf'];

// Ortak yardımcılar
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function check_csrf() {
    return !empty($_POST['csrf']) && hash_equals($_SESSION['install_csrf'] ?? '', $_POST['csrf']);
}

// Wizard state
$step = (int)($_GET['step'] ?? $_SESSION['install_step'] ?? 1);
if ($step < 1 || $step > 6) $step = 1;

$errors = [];
$data = $_SESSION['install_data'] ?? [];

// ═══════════════════════════════════════════════════════════
// STEP 2 POST: Veritabanı bilgileri
// ═══════════════════════════════════════════════════════════
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf()) {
        $errors[] = 'Güvenlik hatası. Sayfayı yenileyin.';
    } else {
        $db = [
            'host' => trim($_POST['db_host'] ?? 'localhost'),
            'name' => trim($_POST['db_name'] ?? ''),
            'user' => trim($_POST['db_user'] ?? ''),
            'pass' => (string)($_POST['db_pass'] ?? ''),
        ];
        foreach (['host','name','user'] as $k) {
            if ($db[$k] === '') $errors[] = ucfirst($k) . ' boş olamaz';
        }
        if (!$errors) {
            try {
                $test = new PDO(
                    "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
                    $db['user'], $db['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
                );
                $test->query("SELECT 1");
                // DB boş mu? (mevcut tablo varsa uyar)
                $existing = $test->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $data['db'] = $db;
                $data['existing_tables'] = $existing;
                $_SESSION['install_data'] = $data;
                $_SESSION['install_step'] = 3;
                header('Location: ?step=3');
                exit;
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'Access denied')) {
                    $errors[] = 'Erişim reddedildi: Kullanıcı adı/şifre/yetki hatalı. Panelde kullanıcıya database üzerinde tam yetki verildiğinden emin olun.';
                } elseif (str_contains($msg, 'Unknown database')) {
                    $errors[] = 'Bu isimde bir database yok. Önce panelden database oluşturun.';
                } elseif (str_contains($msg, 'Connection refused') || str_contains($msg, "Can't connect")) {
                    $errors[] = 'MySQL sunucusuna bağlanılamıyor. Host değeri doğru mu? (shared hosting\'de genelde "localhost")';
                } else {
                    $errors[] = 'MySQL hatası: ' . $msg;
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════
// STEP 3 POST: Firma bilgileri
// ═══════════════════════════════════════════════════════════
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf()) {
        $errors[] = 'Güvenlik hatası.';
    } else {
        $firma = [
            'adi'           => trim($_POST['firma_adi'] ?? ''),
            'vkn'           => trim($_POST['firma_vkn'] ?? ''),
            'vergi_dairesi' => trim($_POST['firma_vergi_dairesi'] ?? ''),
            'adres'         => trim($_POST['firma_adres'] ?? ''),
            'ilce'          => trim($_POST['firma_ilce'] ?? ''),
            'il'            => trim($_POST['firma_il'] ?? ''),
            'posta_kodu'    => trim($_POST['firma_posta_kodu'] ?? ''),
            'telefon'       => trim($_POST['firma_telefon'] ?? ''),
            'email'         => trim($_POST['firma_email'] ?? ''),
            'website'       => trim($_POST['firma_website'] ?? ''),
            'seri_kodu'     => strtoupper(trim($_POST['fatura_seri_kodu'] ?? 'COD')),
            'site_url'      => rtrim(trim($_POST['site_url'] ?? ''), '/'),
        ];
        if (!$firma['adi']) $errors[] = 'Firma ünvanı zorunlu';
        if (!preg_match('/^\d{10}$/', $firma['vkn'])) $errors[] = 'VKN 10 haneli olmalı';
        if (!$firma['vergi_dairesi']) $errors[] = 'Vergi dairesi zorunlu';
        if (!$firma['il']) $errors[] = 'İl zorunlu';
        if (!preg_match('/^[A-Z]{3}$/', $firma['seri_kodu'])) $errors[] = 'Fatura seri kodu 3 BÜYÜK harf olmalı (örn: COD)';
        if ($firma['email'] && !filter_var($firma['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçersiz e-posta';
        if (!preg_match('~^https?://~', $firma['site_url'])) $errors[] = 'Site URL https:// ile başlamalı';

        if (!$errors) {
            $data['firma'] = $firma;
            $_SESSION['install_data'] = $data;
            $_SESSION['install_step'] = 4;
            header('Location: ?step=4');
            exit;
        }
    }
}

// ═══════════════════════════════════════════════════════════
// STEP 4 POST: Admin hesabı + nihai kurulum
// ═══════════════════════════════════════════════════════════
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf()) {
        $errors[] = 'Güvenlik hatası.';
    } else {
        $admin = [
            'user' => trim($_POST['admin_user'] ?? 'admin'),
            'ad'   => trim($_POST['admin_ad'] ?? ''),
            'mail' => trim($_POST['admin_mail'] ?? ''),
            'pass' => (string)($_POST['admin_pass'] ?? ''),
            'pass2'=> (string)($_POST['admin_pass2'] ?? ''),
        ];
        if (!preg_match('/^[a-z0-9_]{3,30}$/', $admin['user'])) $errors[] = 'Kullanıcı adı 3-30 karakter, sadece küçük harf/rakam/_';
        if (strlen($admin['pass']) < 8) $errors[] = 'Şifre en az 8 karakter olmalı';
        if ($admin['pass'] !== $admin['pass2']) $errors[] = 'Şifreler eşleşmiyor';
        if ($admin['mail'] && !filter_var($admin['mail'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçersiz e-posta';

        if (!$errors) {
            $data['admin'] = $admin;
            $_SESSION['install_data'] = $data;

            // ─── Kurulumu gerçekleştir ───
            try {
                // 1. config.php yaz
                $csrf_secret = bin2hex(random_bytes(16));
                $cfg = build_config_php($data, $csrf_secret);
                if (file_put_contents($CONFIG_PATH, $cfg) === false) {
                    throw new \RuntimeException('config.php yazılamadı. ' . $CONFIG_PATH . ' dizininde yazma izni var mı?');
                }
                @chmod($CONFIG_PATH, 0600);

                // 2. DB'ye bağlan ve tabloları oluştur
                $pdo = new PDO(
                    "mysql:host={$data['db']['host']};dbname={$data['db']['name']};charset=utf8mb4",
                    $data['db']['user'], $data['db']['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                create_tables($pdo);

                // 3. Admin kullanıcısını ekle
                $hash = password_hash($admin['pass'], PASSWORD_BCRYPT);
                $chk = $pdo->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi=?");
                $chk->execute([$admin['user']]);
                if (!$chk->fetchColumn()) {
                    $pdo->prepare(
                        "INSERT INTO kullanicilar (kullanici_adi, sifre_hash, ad_soyad, email, rol, sifre_degistirildi, aktif)
                         VALUES (?, ?, ?, ?, 'admin', 1, 1)"
                    )->execute([
                        $admin['user'], $hash,
                        $admin['ad'] ?: 'Kurucu Admin',
                        $admin['mail'] ?: null,
                    ]);
                }

                // 4. Varsayılan ayarlar
                $defaults = [
                    'fatura_no_son_sira'  => '0',
                    'fatura_no_son_yil'   => date('Y'),
                    'kdv_oranlari'        => '1,10,20',
                    'portal_surumu'       => '1.0.2',
                    'kutuphane_surumu'    => '0.1.0-alpha',
                ];
                $st = $pdo->prepare("INSERT IGNORE INTO ayarlar (anahtar, deger) VALUES (?, ?)");
                foreach ($defaults as $k => $v) $st->execute([$k, $v]);

                // 5. Kurulum log'u
                $pdo->prepare("INSERT INTO sistem_log (kullanici_id, olay, detay, ip) VALUES (NULL, 'install.complete', ?, ?)")
                    ->execute(['Portal kurulumu tamamlandı. v1.0.2 installer.', $_SERVER['REMOTE_ADDR'] ?? null]);

                // 6. storage/ dizinlerini oluştur
                foreach (['xml', 'pdf', 'certs', 'backups'] as $sub) {
                    $p = $ROOT . '/storage/' . $sub;
                    if (!is_dir($p)) @mkdir($p, 0755, true);
                }

                // 7. install.lock yaz
                file_put_contents($LOCK_PATH, 'Installed on ' . date('c') . "\nDelete this file to re-run installer.\n");
                @chmod($LOCK_PATH, 0600);

                // 8. Session temizle
                unset($_SESSION['install_data'], $_SESSION['install_step']);
                $_SESSION['install_step'] = 5;
                $_SESSION['install_success'] = [
                    'site_url' => $data['firma']['site_url'],
                    'admin_user' => $admin['user'],
                    'tables' => count($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN)),
                ];

                header('Location: ?step=5');
                exit;
            } catch (\Throwable $e) {
                $errors[] = 'Kurulum hatası: ' . $e->getMessage();
                // config.php yazıldıysa ama sonra hata aldıysak, yazılan dosyayı silme — debug için lazım
                // ama kullanıcıya bilgi ver
                if (file_exists($CONFIG_PATH)) {
                    $errors[] = '(config.php yazılmış durumda, tekrar deneyecekseniz önce silin: ' . $CONFIG_PATH . ')';
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════
// FONKSİYONLAR
// ═══════════════════════════════════════════════════════════

function build_config_php(array $data, string $csrf_secret): string
{
    $db = $data['db'];
    $f  = $data['firma'];
    $site_url = $f['site_url'];
    $parts = parse_url($site_url);
    $site_host = $parts['host'] ?? 'entegrator.codega.com.tr';
    $site_name = 'CODEGA Entegratör Portal';

    // esc: PHP string için tek tırnak kaçış
    $esc = fn($v) => str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$v);

    $template = <<<'PHP'
<?php
/**
 * entegrator-portal — config.php
 * Bu dosya installer tarafından oluşturuldu.
 * Oluşturulma tarihi: %DATE%
 */

// ── Veritabanı ──
define('DB_HOST', '%DB_HOST%');
define('DB_NAME', '%DB_NAME%');
define('DB_USER', '%DB_USER%');
define('DB_PASS', '%DB_PASS%');
define('DB_CHARSET', 'utf8mb4');

// ── Site ──
define('SITE_URL',   '%SITE_URL%');
define('SITE_NAME',  'CODEGA Entegratör Portal');
define('SITE_SHORT', 'CODEGA');
define('SITE_OWNER', 'CODEGA');
define('CONTACT_EMAIL', '%FIRMA_EMAIL%');

// ── Firma Bilgileri ──
define('FIRMA_ADI',          '%FIRMA_ADI%');
define('FIRMA_VKN',          '%FIRMA_VKN%');
define('FIRMA_VERGI_DAIRESI','%FIRMA_VERGI_DAIRESI%');
define('FIRMA_ADRES',        '%FIRMA_ADRES%');
define('FIRMA_ILCE',         '%FIRMA_ILCE%');
define('FIRMA_IL',           '%FIRMA_IL%');
define('FIRMA_POSTA_KODU',   '%FIRMA_POSTA_KODU%');
define('FIRMA_TELEFON',      '%FIRMA_TELEFON%');
define('FIRMA_EMAIL',        '%FIRMA_EMAIL%');
define('FIRMA_WEBSITE',      '%FIRMA_WEBSITE%');

// ── Fatura ──
define('FATURA_SERI_KODU', '%SERI_KODU%');

// ── Admin Varsayılan (installer sonrası anlamını yitirdi ama tutuyoruz) ──
define('ADMIN_DEFAULT_USER', 'admin');
define('ADMIN_DEFAULT_PASS', 'InstallerDanGecti');

// ── Güvenlik ──
define('CSRF_SECRET', '%CSRF_SECRET%');
define('SESSION_NAME', 'codega_portal_session');
define('SESSION_LIFETIME', 28800);

// ── Paths ──
define('ROOT_PATH',     __DIR__);  // config.php kök dizinde (flat structure)
define('STORAGE_PATH',  ROOT_PATH . '/storage');
define('XML_PATH',      STORAGE_PATH . '/xml');
define('PDF_PATH',      STORAGE_PATH . '/pdf');
define('CERT_PATH',     STORAGE_PATH . '/certs');
define('BACKUP_PATH',   STORAGE_PATH . '/backups');
define('LIBS_PATH',     ROOT_PATH . '/libs');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// ── PHP ──
date_default_timezone_set('Europe/Istanbul');
mb_internal_encoding('UTF-8');

// ── Debug ──
define('DEBUG_MODE', false);
if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH . '/php-error.log');
}

// ── CodegaGib Autoload ──
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'CodegaGib\\')) {
        $path = LIBS_PATH . '/entegrator-gib/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($path)) require $path;
    }
});

// ── PDO ──
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
    die('DB baglanti hatasi. Lutfen yonetici ile iletisime gecin.');
}

// ── Session ──
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
PHP;

    return strtr($template, [
        '%DATE%'                => date('c'),
        '%DB_HOST%'             => $esc($db['host']),
        '%DB_NAME%'             => $esc($db['name']),
        '%DB_USER%'             => $esc($db['user']),
        '%DB_PASS%'             => $esc($db['pass']),
        '%SITE_URL%'            => $esc($site_url),
        '%FIRMA_ADI%'           => $esc($f['adi']),
        '%FIRMA_VKN%'           => $esc($f['vkn']),
        '%FIRMA_VERGI_DAIRESI%' => $esc($f['vergi_dairesi']),
        '%FIRMA_ADRES%'         => $esc($f['adres']),
        '%FIRMA_ILCE%'          => $esc($f['ilce']),
        '%FIRMA_IL%'            => $esc($f['il']),
        '%FIRMA_POSTA_KODU%'    => $esc($f['posta_kodu']),
        '%FIRMA_TELEFON%'       => $esc($f['telefon']),
        '%FIRMA_EMAIL%'         => $esc($f['email']),
        '%FIRMA_WEBSITE%'       => $esc($f['website']),
        '%SERI_KODU%'           => $esc($f['seri_kodu']),
        '%CSRF_SECRET%'         => $esc($csrf_secret),
    ]);
}

function create_tables(PDO $pdo): void
{
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
}

// ═══════════════════════════════════════════════════════════
// STEP 1: Sistem kontrolü
// ═══════════════════════════════════════════════════════════
$sys_checks = [];
if ($step === 1) {
    $sys_checks['PHP sürümü (>=8.1)'] = version_compare(PHP_VERSION, '8.1.0', '>=') ? ['ok', PHP_VERSION] : ['fail', PHP_VERSION . ' — portal için 8.1+ gerek'];
    $sys_checks['pdo_mysql eklentisi'] = extension_loaded('pdo_mysql') ? ['ok', 'yüklü'] : ['fail', 'yüklü değil'];
    $sys_checks['openssl eklentisi']   = extension_loaded('openssl')   ? ['ok', 'yüklü'] : ['fail', 'yüklü değil'];
    $sys_checks['dom eklentisi']       = extension_loaded('dom')       ? ['ok', 'yüklü'] : ['fail', 'yüklü değil'];
    $sys_checks['mbstring eklentisi']  = extension_loaded('mbstring')  ? ['ok', 'yüklü'] : ['fail', 'yüklü değil'];
    $sys_checks['json eklentisi']      = extension_loaded('json')      ? ['ok', 'yüklü'] : ['fail', 'yüklü değil'];
    $sys_checks['curl eklentisi']      = extension_loaded('curl')      ? ['ok', 'yüklü'] : ['warn', 'önerilen (ileride gerekecek)'];
    $sys_checks['Portal dizini yazılabilir'] = is_writable($ROOT) ? ['ok', $ROOT] : ['fail', $ROOT . ' yazılabilir değil (config.php yazılamaz)'];
    $sys_checks['libs/entegrator-gib var'] = is_dir($ROOT . '/libs/entegrator-gib') ? ['ok', 'tamam'] : ['fail', 'eksik — portal dosyaları düzgün açılmamış'];
    $sys_checks['storage/ dizini'] = is_dir($ROOT . '/storage') ? ['ok', 'var'] : ['warn', 'oluşturulacak'];
}

$all_ok = true;
foreach ($sys_checks as $v) if ($v[0] === 'fail') $all_ok = false;

// Step 5 success data
$success = $_SESSION['install_success'] ?? null;

// ═══════════════════════════════════════════════════════════
// RENDER
// ═══════════════════════════════════════════════════════════
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CODEGA Entegratör Portal — Kurulum</title>
    <meta name="robots" content="noindex,nofollow">
    <style>
        *,*::before,*::after{box-sizing:border-box}
        html,body{margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:linear-gradient(135deg,#0f172a,#1e293b);color:#0f172a;min-height:100vh;padding:30px 20px;font-size:14px}
        .wrap{max-width:720px;margin:0 auto}
        .head{text-align:center;margin-bottom:26px}
        .head-logo{display:inline-flex;width:60px;height:60px;background:linear-gradient(135deg,#f6821f,#faae40);border-radius:14px;color:#fff;align-items:center;justify-content:center;font-size:26px;margin-bottom:12px}
        .head h1{color:#fff;font-size:24px;font-weight:800;margin:0 0 4px}
        .head .sub{color:#94a3b8;font-size:14px}

        .steps{display:flex;gap:4px;margin-bottom:18px;padding:10px;background:rgba(255,255,255,0.05);border-radius:10px;justify-content:space-between}
        .stp{flex:1;text-align:center;padding:7px 4px;border-radius:6px;font-size:12px;color:#64748b;font-weight:600}
        .stp.active{background:#f6821f;color:#fff}
        .stp.done{background:#10b981;color:#fff}
        .stp.done::before{content:"✓ "}

        .card{background:#fff;border-radius:14px;padding:26px;box-shadow:0 20px 50px rgba(0,0,0,.25)}
        .card h2{margin:0 0 6px;font-size:20px;font-weight:700}
        .card .lead{color:#64748b;font-size:13.5px;margin-bottom:20px}

        .fg{margin-bottom:14px}
        .fg label{display:block;font-weight:600;font-size:12.5px;color:#334155;margin-bottom:5px}
        .fg .hint{font-size:11.5px;color:#94a3b8;margin-top:3px}
        .fg input,.fg select,.fg textarea{width:100%;padding:10px 13px;border:1px solid #cbd5e1;border-radius:7px;font-size:14px;box-sizing:border-box;font-family:inherit;background:#fff}
        .fg input:focus,.fg select:focus{outline:none;border-color:#f6821f;box-shadow:0 0 0 3px rgba(246,130,31,.15)}

        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}

        .alert{padding:12px 15px;border-radius:7px;margin-bottom:16px;font-size:13.5px;display:flex;gap:10px;align-items:flex-start}
        .alert-err{background:#fee2e2;color:#7f1d1d;border:1px solid #fca5a5}
        .alert-err strong{color:#991b1b}
        .alert-ok{background:#dcfce7;color:#14532d;border:1px solid #86efac}
        .alert-warn{background:#fef3c7;color:#78350f;border:1px solid #fcd34d}
        .alert-info{background:#dbeafe;color:#1e3a8a;border:1px solid #93c5fd}

        .check-list{list-style:none;padding:0;margin:0 0 16px 0}
        .check-list li{padding:9px 12px;margin-bottom:6px;border-radius:7px;font-size:13.5px;display:flex;gap:10px;align-items:center}
        .check-list li.ok{background:#dcfce7;color:#14532d}
        .check-list li.fail{background:#fee2e2;color:#7f1d1d}
        .check-list li.warn{background:#fef3c7;color:#78350f}
        .check-list li .st{font-weight:700;width:24px;text-align:center}
        .check-list li .label{flex:1;font-weight:600}
        .check-list li .v{font-family:monospace;font-size:12px;opacity:.8}

        .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:18px;border-top:1px solid #e5e7eb}
        .btn{padding:11px 22px;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-family:inherit}
        .btn-primary{background:#f6821f;color:#fff}.btn-primary:hover{background:#e2761b}
        .btn-ghost{background:#f1f5f9;color:#334155}.btn-ghost:hover{background:#e2e8f0}
        .btn:disabled{opacity:.4;cursor:not-allowed}

        code{background:#f1f5f9;padding:2px 6px;border-radius:3px;font-size:12px;color:#dc2626;font-family:monospace}
        .kv{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
        .kv:last-child{border-bottom:none}
        .kv span:first-child{color:#64748b}
        .kv span:last-child{font-weight:600}

        .big-check{font-size:80px;text-align:center;margin:20px 0;color:#10b981}
    </style>
</head>
<body>
<div class="wrap">

    <div class="head">
        <div class="head-logo">📄</div>
        <h1>CODEGA Entegratör Portal</h1>
        <div class="sub">Kurulum Sihirbazı · v1.0.2</div>
    </div>

    <div class="steps">
        <div class="stp <?= $step>1?'done':($step===1?'active':'') ?>">1 · Sistem</div>
        <div class="stp <?= $step>2?'done':($step===2?'active':'') ?>">2 · Veritabanı</div>
        <div class="stp <?= $step>3?'done':($step===3?'active':'') ?>">3 · Firma</div>
        <div class="stp <?= $step>4?'done':($step===4?'active':'') ?>">4 · Admin</div>
        <div class="stp <?= $step>=5?'active':'' ?>">5 · Tamam</div>
    </div>

    <div class="card">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-err">
                <div>⚠</div>
                <div>
                    <strong>Hata:</strong>
                    <ul style="margin:4px 0 0;padding-left:18px">
                        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- ═══ STEP 1: Sistem Kontrolü ═══ -->
            <h2>1. Sistem Kontrolü</h2>
            <div class="lead">Sunucunun portal için gerekli koşulları sağlayıp sağlamadığı kontrol ediliyor.</div>

            <ul class="check-list">
                <?php foreach ($sys_checks as $label => [$st, $val]):
                    $sym = ['ok'=>'✓','fail'=>'✗','warn'=>'!'][$st] ?? '?';
                ?>
                <li class="<?= $st ?>">
                    <span class="st"><?= $sym ?></span>
                    <span class="label"><?= h($label) ?></span>
                    <span class="v"><?= h($val) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>

            <?php if (!$all_ok): ?>
                <div class="alert alert-err">
                    <div>⚠</div>
                    <div><strong>Bazı gereksinimler karşılanmıyor.</strong> Kurulum için yukarıdaki ✗ işaretli öğeleri düzelt. DirectAdmin panelinde <code>PHP Selector</code> → <code>Extensions</code> kısmından eksik eklentileri aktifleştirebilirsin.</div>
                </div>
            <?php else: ?>
                <div class="alert alert-ok">
                    <div>✓</div>
                    <div><strong>Her şey hazır.</strong> Devam butonuna basarak kuruluma geçebilirsin.</div>
                </div>
            <?php endif; ?>

            <div class="actions">
                <?php if ($all_ok): ?>
                    <a href="?step=2" class="btn btn-primary">Devam · Veritabanı →</a>
                <?php else: ?>
                    <a href="?step=1" class="btn btn-ghost">Yenile (düzeltme sonrası)</a>
                <?php endif; ?>
            </div>

        <?php elseif ($step === 2): ?>
            <!-- ═══ STEP 2: Veritabanı ═══ -->
            <h2>2. Veritabanı Bilgileri</h2>
            <div class="lead">
                DirectAdmin'de oluşturduğun MySQL database bilgilerini gir. Bu adımda bağlantı canlı test edilir.
            </div>

            <div class="alert alert-info">
                <div>💡</div>
                <div>
                    <strong>Database'i bulamıyorum?</strong><br>
                    DirectAdmin'de <code>Account Manager → MySQL Management</code> yolunu izle (sürüme göre "MySQL Databases" de denilebilir). Arama kutusuna <code>mysql</code> yazman en hızlı yol. <strong>Create new Database</strong> ile önce database + user oluştur, sonra buraya bilgilerini gir.
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

                <div class="row">
                    <div class="fg">
                        <label>MySQL Host *</label>
                        <input type="text" name="db_host" value="<?= h($_POST['db_host'] ?? $data['db']['host'] ?? 'localhost') ?>" required>
                        <div class="hint">Shared hosting'de genelde <code>localhost</code></div>
                    </div>
                    <div class="fg">
                        <label>Database Adı *</label>
                        <input type="text" name="db_name" value="<?= h($_POST['db_name'] ?? $data['db']['name'] ?? 'codega_entegrator_portal') ?>" required>
                        <div class="hint">DirectAdmin'deki tam ad (prefix'li)</div>
                    </div>
                </div>

                <div class="row">
                    <div class="fg">
                        <label>Kullanıcı Adı *</label>
                        <input type="text" name="db_user" value="<?= h($_POST['db_user'] ?? $data['db']['user'] ?? 'codega_entegrator_portal') ?>" required>
                    </div>
                    <div class="fg">
                        <label>Şifre *</label>
                        <input type="password" name="db_pass" required autocomplete="new-password">
                        <div class="hint">Güvenliğiniz için tekrar yazmanız gerekli</div>
                    </div>
                </div>

                <div class="actions">
                    <a href="?step=1" class="btn btn-ghost">← Geri</a>
                    <button type="submit" class="btn btn-primary">Bağlantıyı Test Et & Devam →</button>
                </div>
            </form>

        <?php elseif ($step === 3): ?>
            <!-- ═══ STEP 3: Firma Bilgileri ═══ -->
            <h2>3. Firma Bilgileri</h2>
            <div class="lead">
                CODEGA'nın fatura kesecek bilgileri. Bu değerler UBL-TR XML'inin <strong>satıcı (supplier)</strong> bölümüne yazılır. Doğru gir — sonra config.php'de değiştirebilirsin.
            </div>

            <?php if (!empty($data['existing_tables']) && count($data['existing_tables']) > 0): ?>
                <div class="alert alert-warn">
                    <div>⚠</div>
                    <div>Veritabanında zaten <strong><?= count($data['existing_tables']) ?> tablo</strong> var (<?= h(implode(', ', array_slice($data['existing_tables'], 0, 5))) ?><?= count($data['existing_tables'])>5?'…':'' ?>). Kurulum devam ederse mevcut tablolar korunur ama eksikler eklenir. Temiz başlamak istiyorsan phpMyAdmin'den database'i boşalt.</div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

                <div class="fg">
                    <label>Portal URL *</label>
                    <input type="url" name="site_url" value="<?= h($_POST['site_url'] ?? $data['firma']['site_url'] ?? 'https://entegrator.codega.com.tr') ?>" required>
                    <div class="hint">SSL aktif domain (https://...)</div>
                </div>

                <div class="fg">
                    <label>Firma Ticari Ünvan *</label>
                    <input type="text" name="firma_adi" value="<?= h($_POST['firma_adi'] ?? $data['firma']['adi'] ?? 'CODEGA Yazılım Hizmetleri') ?>" required maxlength="200">
                </div>

                <div class="row">
                    <div class="fg">
                        <label>VKN *</label>
                        <input type="text" name="firma_vkn" value="<?= h($_POST['firma_vkn'] ?? $data['firma']['vkn'] ?? '') ?>" required pattern="\d{10}" maxlength="10" placeholder="1234567890">
                        <div class="hint">10 haneli vergi kimlik numarası</div>
                    </div>
                    <div class="fg">
                        <label>Vergi Dairesi *</label>
                        <input type="text" name="firma_vergi_dairesi" value="<?= h($_POST['firma_vergi_dairesi'] ?? $data['firma']['vergi_dairesi'] ?? '') ?>" required maxlength="100" placeholder="Selçuk">
                    </div>
                </div>

                <div class="fg">
                    <label>Açık Adres</label>
                    <input type="text" name="firma_adres" value="<?= h($_POST['firma_adres'] ?? $data['firma']['adres'] ?? '') ?>" maxlength="255">
                </div>

                <div class="row3">
                    <div class="fg">
                        <label>İlçe</label>
                        <input type="text" name="firma_ilce" value="<?= h($_POST['firma_ilce'] ?? $data['firma']['ilce'] ?? 'Selçuklu') ?>" maxlength="50">
                    </div>
                    <div class="fg">
                        <label>İl *</label>
                        <input type="text" name="firma_il" value="<?= h($_POST['firma_il'] ?? $data['firma']['il'] ?? 'Konya') ?>" required maxlength="50">
                    </div>
                    <div class="fg">
                        <label>Posta Kodu</label>
                        <input type="text" name="firma_posta_kodu" value="<?= h($_POST['firma_posta_kodu'] ?? $data['firma']['posta_kodu'] ?? '') ?>" maxlength="10">
                    </div>
                </div>

                <div class="row">
                    <div class="fg">
                        <label>Telefon</label>
                        <input type="tel" name="firma_telefon" value="<?= h($_POST['firma_telefon'] ?? $data['firma']['telefon'] ?? '') ?>" maxlength="30">
                    </div>
                    <div class="fg">
                        <label>E-posta</label>
                        <input type="email" name="firma_email" value="<?= h($_POST['firma_email'] ?? $data['firma']['email'] ?? 'info@codega.com.tr') ?>" maxlength="150">
                    </div>
                </div>

                <div class="row">
                    <div class="fg">
                        <label>Website</label>
                        <input type="url" name="firma_website" value="<?= h($_POST['firma_website'] ?? $data['firma']['website'] ?? 'https://codega.com.tr') ?>" maxlength="255">
                    </div>
                    <div class="fg">
                        <label>Fatura Seri Kodu *</label>
                        <input type="text" name="fatura_seri_kodu" value="<?= h($_POST['fatura_seri_kodu'] ?? $data['firma']['seri_kodu'] ?? 'COD') ?>" required pattern="[A-Z]{3}" maxlength="3" style="text-transform:uppercase" placeholder="COD">
                        <div class="hint">3 BÜYÜK harf. Örn: COD, SFT</div>
                    </div>
                </div>

                <div class="actions">
                    <a href="?step=2" class="btn btn-ghost">← Geri</a>
                    <button type="submit" class="btn btn-primary">Devam · Admin Hesabı →</button>
                </div>
            </form>

        <?php elseif ($step === 4): ?>
            <!-- ═══ STEP 4: Admin Hesabı ═══ -->
            <h2>4. Yönetici Hesabı</h2>
            <div class="lead">
                Portal'a girişinde kullanacağın admin hesabı. Kurulum bitince bu hesapla giriş yapıp sistemi kullanmaya başlarsın.
            </div>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

                <div class="row">
                    <div class="fg">
                        <label>Kullanıcı Adı *</label>
                        <input type="text" name="admin_user" value="<?= h($_POST['admin_user'] ?? 'admin') ?>" required pattern="[a-z0-9_]{3,30}" maxlength="30">
                        <div class="hint">3-30 karakter, küçük harf/rakam/_</div>
                    </div>
                    <div class="fg">
                        <label>Ad Soyad</label>
                        <input type="text" name="admin_ad" value="<?= h($_POST['admin_ad'] ?? 'Yunus Aksoy') ?>" maxlength="100">
                    </div>
                </div>

                <div class="fg">
                    <label>E-posta</label>
                    <input type="email" name="admin_mail" value="<?= h($_POST['admin_mail'] ?? 'info@codega.com.tr') ?>" maxlength="150">
                    <div class="hint">Gelecekte şifre sıfırlama için kullanılabilir</div>
                </div>

                <div class="row">
                    <div class="fg">
                        <label>Şifre *</label>
                        <input type="password" name="admin_pass" required minlength="8" maxlength="100" autocomplete="new-password">
                        <div class="hint">En az 8 karakter — güçlü bir şifre kullan</div>
                    </div>
                    <div class="fg">
                        <label>Şifre (tekrar) *</label>
                        <input type="password" name="admin_pass2" required minlength="8" maxlength="100" autocomplete="new-password">
                    </div>
                </div>

                <div class="alert alert-info">
                    <div>🔒</div>
                    <div>
                        Bu adımın sonunda kurulum otomatik başlar: config.php yazılır, 7 tablo oluşur, admin kullanıcın eklenir, kurulum kilitlenir. İşlem 1-2 saniye sürer.
                    </div>
                </div>

                <div class="actions">
                    <a href="?step=3" class="btn btn-ghost">← Geri</a>
                    <button type="submit" class="btn btn-primary">✓ Kurulumu Tamamla</button>
                </div>
            </form>

        <?php elseif ($step === 5): ?>
            <!-- ═══ STEP 5: Başarılı ═══ -->
            <div class="big-check">✓</div>
            <h2 style="text-align:center;color:#10b981">Kurulum Tamamlandı!</h2>
            <div class="lead" style="text-align:center">
                Portal kuruldu ve kullanıma hazır.
            </div>

            <?php if ($success): ?>
            <div style="background:#f8fafc;border-radius:8px;padding:18px;margin:20px 0">
                <div class="kv"><span>Portal URL</span><span><?= h($success['site_url']) ?></span></div>
                <div class="kv"><span>Admin kullanıcı adı</span><span><?= h($success['admin_user']) ?></span></div>
                <div class="kv"><span>Oluşturulan tablo sayısı</span><span><?= (int)$success['tables'] ?></span></div>
                <div class="kv"><span>Kurulum zamanı</span><span><?= date('d.m.Y H:i') ?></span></div>
            </div>
            <?php endif; ?>

            <div class="alert alert-warn">
                <div>🗑️</div>
                <div>
                    <strong>Önemli:</strong> Güvenlik için şimdi <code>install.php</code> dosyasını sunucudan silmelisin. Kurulum zaten kilitli ama yine de dışarıda kodun olmasın.
                    <br><br>
                    SSH ile: <code>rm install.php</code><br>
                    veya DirectAdmin File Manager'dan <strong>install.php</strong> dosyasını seç → <strong>Delete</strong>
                </div>
            </div>

            <div class="actions" style="justify-content:center">
                <a href="<?= h($success['site_url'] ?? './') ?>" class="btn btn-primary">Portal'a Giriş Yap →</a>
            </div>

        <?php endif; ?>

    </div>

    <div style="text-align:center;color:#64748b;font-size:12px;margin-top:20px">
        © <?= date('Y') ?> CODEGA · entegrator-portal v1.0.2 · <?= date('d.m.Y H:i') ?>
    </div>

</div>
</body>
</html>
