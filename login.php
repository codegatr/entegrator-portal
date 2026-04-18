<?php
require __DIR__ . '/config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';

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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<meta name="robots" content="noindex, nofollow">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-logo">
            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="currentColor">
                <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zM8 18v-2h5v2H8zm8-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
            </svg>
        </div>
        <h1>Portal Girişi</h1>
        <div class="sub"><?= h(SITE_NAME) ?></div>

        <?php if ($err): ?>
            <div class="alert alert-danger">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>
                <div><?= h($err) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="r" value="<?= h($_GET['r'] ?? '') ?>">
            <input type="text" name="u" placeholder="Kullanıcı adı" required autofocus value="<?= h($user_val) ?>">
            <input type="password" name="p" placeholder="Şifre" required>
            <button type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
                Giriş Yap
            </button>
        </form>

        <div style="margin-top:20px;text-align:center;font-size:11.5px;color:#94a3b8">
            <a href="https://codega.com.tr" target="_blank" style="color:#94a3b8;text-decoration:none">codega.com.tr</a>
            · v1.1.0
        </div>
    </div>
</div>
</body>
</html>
