<?php
require __DIR__ . '/../config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require __DIR__ . '/_auth.php';

mp_auth_logout($pdo);

header('Location: ' . SITE_URL . '/musteri-portal/login.php');
exit;
