<?php
/**
 * auth.php — Login, session, yetki kontrolü.
 */

function auth_check(): bool
{
    return !empty($_SESSION['user_id']);
}

function auth_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'        => (int)$_SESSION['user_id'],
        'user'      => $_SESSION['user_name'] ?? '',
        'ad_soyad'  => $_SESSION['user_full'] ?? '',
        'rol'       => $_SESSION['user_rol'] ?? 'operator',
        'force_pwd' => (bool)($_SESSION['force_pwd'] ?? false),
    ];
}

function auth_require(): void
{
    if (!auth_check()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        redirect(SITE_URL . '/login.php?r=' . $redirect);
    }
}

function auth_require_role(string $role): void
{
    auth_require();
    $user = auth_user();
    $hierarchy = ['viewer' => 1, 'operator' => 2, 'admin' => 3];
    $current = $hierarchy[$user['rol']] ?? 0;
    $needed  = $hierarchy[$role] ?? 99;
    if ($current < $needed) {
        http_response_code(403);
        die('Yetkisiz işlem.');
    }
}

function auth_login(PDO $pdo, string $user, string $pass): array
{
    // Kilitli mi?
    $q = $pdo->prepare("SELECT * FROM kullanicilar WHERE kullanici_adi=? LIMIT 1");
    $q->execute([$user]);
    $row = $q->fetch();

    if (!$row) {
        sleep(1);
        return ['ok' => false, 'err' => 'Kullanıcı adı veya şifre yanlış.'];
    }

    if (!$row['aktif']) {
        return ['ok' => false, 'err' => 'Hesap pasif. Yöneticiye başvurun.'];
    }

    if (!empty($row['kilit_bitis']) && strtotime($row['kilit_bitis']) > time()) {
        return ['ok' => false, 'err' => 'Çok fazla yanlış deneme — hesap geçici kilitli. ' . fmt_datetime($row['kilit_bitis']) . ' sonra tekrar deneyin.'];
    }

    if (!password_verify($pass, $row['sifre_hash'])) {
        // Yanlış sayacı artır
        $yeni_sayi = (int)$row['yanlis_giris_sayisi'] + 1;
        $kilit = null;
        if ($yeni_sayi >= 5) {
            $kilit = date('Y-m-d H:i:s', time() + 600);  // 10 dakika kilit
        }
        $pdo->prepare("UPDATE kullanicilar SET yanlis_giris_sayisi=?, kilit_bitis=? WHERE id=?")
            ->execute([$yeni_sayi, $kilit, $row['id']]);
        audit_log($pdo, 'auth.login_fail', 'user='.$user, null, "kullanici:{$row['id']}");
        sleep(1);
        return ['ok' => false, 'err' => 'Kullanıcı adı veya şifre yanlış.' . ($yeni_sayi >= 3 ? ' ('.(5 - $yeni_sayi).' hak kaldı)' : '')];
    }

    // Başarılı
    $pdo->prepare("UPDATE kullanicilar SET son_giris=NOW(), son_ip=?, yanlis_giris_sayisi=0, kilit_bitis=NULL WHERE id=?")
        ->execute([client_ip(), $row['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$row['id'];
    $_SESSION['user_name'] = $row['kullanici_adi'];
    $_SESSION['user_full'] = $row['ad_soyad'];
    $_SESSION['user_rol']  = $row['rol'];
    $_SESSION['force_pwd'] = empty($row['sifre_degistirildi']);

    audit_log($pdo, 'auth.login_ok', null, (int)$row['id'], "kullanici:{$row['id']}");

    return ['ok' => true, 'force_pwd' => empty($row['sifre_degistirildi'])];
}

function auth_logout(PDO $pdo): void
{
    if (!empty($_SESSION['user_id'])) {
        audit_log($pdo, 'auth.logout', null, (int)$_SESSION['user_id']);
    }
    $_SESSION = [];
    session_destroy();
}
