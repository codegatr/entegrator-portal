<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';

// Zaten giriş yaptıysa dashboard'a yolla
if (auth_check()) {
    redirect(SITE_URL . '/index.php');
}

$err = '';
$user_val = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $err = 'Güvenlik hatası. Sayfayı yenileyin.';
    } else {
        $user_val = trim($_POST['u'] ?? '');
        $pass     = $_POST['p'] ?? '';
        $r = auth_login($pdo, $user_val, $pass);
        if ($r['ok']) {
            if ($r['force_pwd']) {
                redirect(SITE_URL . '/yonetim/sifre.php?force=1');
            }
            $redirect = $_GET['r'] ?? $_POST['r'] ?? (SITE_URL . '/index.php');
            // Redirect güvenlik: sadece kendi domainimize
            if (!str_starts_with($redirect, '/') && !str_starts_with($redirect, SITE_URL)) {
                $redirect = SITE_URL . '/index.php';
            }
            redirect($redirect);
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
    <title>Giriş · <?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-logo"><i class="fas fa-file-invoice-dollar"></i></div>
        <h1>Portal Girişi</h1>
        <div class="sub"><?= h(SITE_NAME) ?></div>

        <?php if ($err): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= h($err) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="r" value="<?= h($_GET['r'] ?? '') ?>">
            <input type="text" name="u" placeholder="Kullanıcı adı" required autofocus value="<?= h($user_val) ?>">
            <input type="password" name="p" placeholder="Şifre" required>
            <button type="submit"><i class="fas fa-sign-in-alt"></i> Giriş Yap</button>
        </form>

        <div style="margin-top:18px;text-align:center;font-size:11px;color:#94a3b8">
            <a href="https://codega.com.tr" target="_blank" style="color:#94a3b8;text-decoration:none">codega.com.tr</a>
        </div>
    </div>
</div>
</body>
</html>
