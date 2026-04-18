<?php
/**
 * Müşteri Portalı Auth
 * Admin portalından tamamen ayrı session + auth sistemi.
 *
 * Session name: codega_musteri_session (admin: codega_portal_session)
 * Cookie path: / (SameSite, path çakışmasını engelleyen isim farkı zaten yeterli)
 *
 * ⚠️ ÖNEMLİ: Bu dosyayı include etmeden ÖNCE
 *    define('CODEGA_NO_AUTO_SESSION', true);
 * satırı ile config.php'nin otomatik session başlatmasını engellemek GEREKLİ.
 */

// ═══ AYRI SESSION ═══════════════════════════════════════════
// config.php CODEGA_NO_AUTO_SESSION flag'i ile otomatik session başlatmadı.
// Artık kendi session'ımızı temiz bir şekilde başlatabiliriz.

if (session_status() === PHP_SESSION_NONE) {
    session_name('codega_musteri_session');
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ═══ CSRF ═══════════════════════════════════════════════════

function mp_csrf_token(): string
{
    if (empty($_SESSION['mp_csrf'])) {
        $_SESSION['mp_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['mp_csrf'];
}

function mp_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(mp_csrf_token()) . '">';
}

function mp_csrf_verify(string $token): bool
{
    return !empty($_SESSION['mp_csrf']) && hash_equals($_SESSION['mp_csrf'], $token);
}

// ═══ FLASH ═══════════════════════════════════════════════════

function mp_flash_set(string $tip, string $msg): void
{
    $_SESSION['mp_flash'][] = ['tip' => $tip, 'msg' => $msg];
}

function mp_flash_get(): array
{
    $f = $_SESSION['mp_flash'] ?? [];
    unset($_SESSION['mp_flash']);
    return $f;
}

// ═══ LOGIN ═══════════════════════════════════════════════════

function mp_auth_login(PDO $pdo, string $kullanici_adi, string $sifre): array
{
    $kullanici_adi = trim(mb_strtolower($kullanici_adi));
    if ($kullanici_adi === '' || $sifre === '') {
        return ['ok' => false, 'err' => 'Kullanıcı adı ve şifre gerekli'];
    }

    $s = $pdo->prepare("
        SELECT mpk.*, m.unvan, m.vkn_tckn, m.aktif AS mukellef_aktif
        FROM musteri_portal_kullanicilar mpk
        LEFT JOIN mukellefler m ON m.id = mpk.mukellef_id
        WHERE mpk.kullanici_adi = ?
    ");
    $s->execute([$kullanici_adi]);
    $u = $s->fetch();

    if (!$u) {
        mp_audit($pdo, 'musteri.login_fail', "user=$kullanici_adi reason=not_found");
        return ['ok' => false, 'err' => 'Kullanıcı adı veya şifre hatalı'];
    }

    // Kilitli mi?
    if ($u['kilit_bitis'] && strtotime($u['kilit_bitis']) > time()) {
        $dakika = (int)ceil((strtotime($u['kilit_bitis']) - time()) / 60);
        return ['ok' => false, 'err' => "Hesap geçici olarak kilitli. $dakika dk sonra tekrar dene."];
    }

    // Aktif mi?
    if (!$u['aktif']) {
        return ['ok' => false, 'err' => 'Hesap pasif. Firma yöneticinizle iletişime geçin.'];
    }

    // Müşteri firma aktif mi?
    if (empty($u['mukellef_aktif'])) {
        return ['ok' => false, 'err' => 'Firma hesabı aktif değil. CODEGA ile iletişime geçin.'];
    }

    // Şifre doğru mu?
    if (!password_verify($sifre, $u['sifre_hash'])) {
        // Yanlış şifre sayısını artır
        $yanlis = $u['yanlis_giris_sayisi'] + 1;
        $kilit = null;
        if ($yanlis >= 5) {
            $kilit = date('Y-m-d H:i:s', time() + 600); // 10 dk kilitle
            $yanlis = 0;
        }
        $pdo->prepare("UPDATE musteri_portal_kullanicilar SET yanlis_giris_sayisi=?, kilit_bitis=? WHERE id=?")
            ->execute([$yanlis, $kilit, $u['id']]);
        mp_audit($pdo, 'musteri.login_fail', "user=$kullanici_adi reason=wrong_pwd attempts=$yanlis");
        if ($kilit) {
            return ['ok' => false, 'err' => 'Çok fazla başarısız deneme. Hesap 10 dk kilitlendi.'];
        }
        return ['ok' => false, 'err' => 'Kullanıcı adı veya şifre hatalı'];
    }

    // Başarılı giriş
    $pdo->prepare("UPDATE musteri_portal_kullanicilar SET son_giris=NOW(), son_ip=?, yanlis_giris_sayisi=0, kilit_bitis=NULL WHERE id=?")
        ->execute([client_ip(), $u['id']]);

    session_regenerate_id(true);

    $_SESSION['mp_user_id']     = (int)$u['id'];
    $_SESSION['mp_mukellef_id'] = (int)$u['mukellef_id'];
    $_SESSION['mp_kullanici']   = $u['kullanici_adi'];
    $_SESSION['mp_ad_soyad']    = $u['ad_soyad'];
    $_SESSION['mp_unvan']       = $u['unvan'];
    $_SESSION['mp_force_pwd']   = empty($u['sifre_degistirildi']);
    $_SESSION['mp_login_time']  = time();

    mp_audit($pdo, 'musteri.login_ok', "user=$kullanici_adi", $u['id'], $u['mukellef_id']);

    return ['ok' => true, 'force_pwd' => !empty($_SESSION['mp_force_pwd'])];
}

function mp_auth_logout(PDO $pdo): void
{
    if (!empty($_SESSION['mp_user_id'])) {
        mp_audit($pdo, 'musteri.logout', null, $_SESSION['mp_user_id'], $_SESSION['mp_mukellef_id'] ?? null);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function mp_auth_check(): bool
{
    return !empty($_SESSION['mp_user_id']);
}

function mp_auth_user(): ?array
{
    if (!mp_auth_check()) return null;
    return [
        'id'          => $_SESSION['mp_user_id'],
        'mukellef_id' => $_SESSION['mp_mukellef_id'],
        'user'        => $_SESSION['mp_kullanici'] ?? '',
        'ad_soyad'    => $_SESSION['mp_ad_soyad'] ?? '',
        'unvan'       => $_SESSION['mp_unvan'] ?? '',
        'force_pwd'   => !empty($_SESSION['mp_force_pwd']),
    ];
}

function mp_auth_require(): void
{
    if (!mp_auth_check()) {
        $target = SITE_URL . '/musteri-portal/login.php';
        if (($_SERVER['REQUEST_URI'] ?? '') && basename($_SERVER['SCRIPT_NAME']) !== 'login.php') {
            $target .= '?r=' . urlencode($_SERVER['REQUEST_URI']);
        }
        header('Location: ' . $target);
        exit;
    }
}

// ═══ AUDIT ═══════════════════════════════════════════════════

function mp_audit(PDO $pdo, string $olay, ?string $detay = null, ?int $kullanici_id = null, ?int $mukellef_id = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO musteri_portal_log (musteri_kullanici_id, mukellef_id, olay, detay, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $kullanici_id ?? $_SESSION['mp_user_id'] ?? null,
            $mukellef_id ?? $_SESSION['mp_mukellef_id'] ?? null,
            $olay,
            $detay,
            client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (\Exception $e) {
        error_log('mp_audit: ' . $e->getMessage());
    }
}

// ═══ HELPER ══════════════════════════════════════════════════

/**
 * Güvenli mukellef_id kontrol. Belirtilen fatura bu müşteriye mi ait?
 */
function mp_fatura_ait_mi(PDO $pdo, int $fatura_id, int $mukellef_id): bool
{
    $s = $pdo->prepare("SELECT 1 FROM faturalar WHERE id=? AND mukellef_id=?");
    $s->execute([$fatura_id, $mukellef_id]);
    return (bool)$s->fetchColumn();
}
