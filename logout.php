<?php
require __DIR__ . '/config.php';
require INCLUDES_PATH . '/init.php';
require INCLUDES_PATH . '/helpers.php';
require INCLUDES_PATH . '/auth.php';

auth_logout($pdo);
flash_set('info', 'Çıkış yapıldı.');
redirect(SITE_URL . '/login.php');
