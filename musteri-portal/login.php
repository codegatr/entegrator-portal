<?php
// Müşteri portal: config.php otomatik session başlatmasın (ayrı session kullanıyoruz)
define('CODEGA_NO_AUTO_SESSION', true);

require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';
require __DIR__ . '/_layout.php';

if (mp_auth_check()) {
    header('Location: ' . SITE_URL . '/musteri-portal/index.php');
    exit;
}

$err = '';
$user_val = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mp_csrf_verify($_POST['csrf'] ?? '')) {
        $err = 'Güvenlik hatası. Sayfayı yenileyin.';
    } else {
        $user_val = trim($_POST['u'] ?? '');
        $pass     = $_POST['p'] ?? '';
        $r = mp_auth_login($pdo, $user_val, $pass);
        if ($r['ok']) {
            $redirect = SITE_URL . '/musteri-portal/index.php';
            if (!empty($r['force_pwd'])) {
                $redirect = SITE_URL . '/musteri-portal/profil.php?ilk=1';
            } elseif (!empty($_GET['r']) && str_starts_with($_GET['r'], '/musteri-portal/')) {
                $redirect = SITE_URL . $_GET['r'];
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            $err = $r['err'];
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Müşteri Portalı · CODEGA</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/musteri-portal/assets/mp-style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<meta name="robots" content="noindex, nofollow">
</head>
<body>
<div class="mp-login-wrap">
    <div class="mp-login-box">
        <div class="mp-login-logo">
            <?= mp_icon('invoice', 30) ?>
        </div>
        <h1>Müşteri Portalı</h1>
        <div class="sub">E-fatura takibi için CODEGA müşteri girişi</div>

        <?php if ($err): ?>
            <div class="mp-alert mp-alert-danger">
                <?= mp_icon('x-circle') ?>
                <div><?= htmlspecialchars($err) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= mp_csrf_field() ?>
            <input type="text" name="u" placeholder="Kullanıcı adı veya e-posta" required autofocus value="<?= htmlspecialchars($user_val) ?>">
            <input type="password" name="p" placeholder="Şifre" required>
            <button type="submit">Giriş Yap</button>
        </form>

        <div class="mp-login-info">
            Giriş bilgilerinizi CODEGA'dan alabilirsiniz.<br>
            Şifrenizi mi unuttunuz? <a href="mailto:<?= h(CONTACT_EMAIL) ?>">Destek talep et</a>
        </div>
    </div>
</div>
</body>
</html>
