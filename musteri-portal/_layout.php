<?php
/**
 * Müşteri Portalı Layout — Admin portal'dan ayrı, daha sade
 * Renk: Koyu lacivert + açık mavi (müşteri-dostu, kurumsal)
 */

// icon() fonksiyonu admin layout'tan kopya (bağımsız çalışsın diye)
if (!function_exists('mp_icon')) {
    function mp_icon(string $name, int $size = 18): string
    {
        static $icons = [
            'home'        => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
            'invoice'     => '<path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zM8 18v-2h5v2H8zm8-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
            'user'        => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
            'building'    => '<path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/>',
            'logout'      => '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>',
            'download'    => '<path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>',
            'search'      => '<path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>',
            'eye'         => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
            'key'         => '<path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>',
            'check'       => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
            'check-circle' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>',
            'x-circle'    => '<path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/>',
            'clock'       => '<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>',
            'alert'       => '<path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>',
            'info'        => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>',
            'calendar'    => '<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>',
            'chart'       => '<path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/>',
            'ban'         => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1C19.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z"/>',
            'paper-plane' => '<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>',
            'signature'   => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
            'file-code'   => '<path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM13 9V3.5L18.5 9H13z"/>',
            'shield'      => '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>',
            'phone'       => '<path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>',
            'envelope'    => '<path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>',
            'lock'        => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
            'chevron-right' => '<path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>',
        ];
        if (!isset($icons[$name])) return '';
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="currentColor">'.$icons[$name].'</svg>';
    }
}

function mp_render_header(string $title = '', string $active = ''): void
{
    $user = mp_auth_user();
    $full_title = $title ? ($title . ' · Müşteri Portalı') : 'Müşteri Portalı';

    $menu = [
        ['dashboard', 'Ana Sayfa',    'home',     '/musteri-portal/index.php'],
        ['faturalar', 'Faturalarım',  'invoice',  '/musteri-portal/faturalar.php'],
        ['profil',    'Profilim',     'user',     '/musteri-portal/profil.php'],
    ];
    ?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($full_title) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/musteri-portal/assets/mp-style.css?v=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<meta name="robots" content="noindex, nofollow">
</head>
<body>
<div class="mp-app">
    <!-- Üst bar -->
    <header class="mp-topbar">
        <div class="mp-topbar-inner">
            <div class="mp-logo">
                <div class="mp-logo-icon"><?= mp_icon('invoice', 22) ?></div>
                <div>
                    <div class="mp-logo-title">Müşteri Portalı</div>
                    <div class="mp-logo-sub">CODEGA Entegratör</div>
                </div>
            </div>

            <nav class="mp-nav">
                <?php foreach ($menu as [$slug, $label, $ic, $url]):
                    $is_active = $slug === $active;
                ?>
                    <a href="<?= SITE_URL . $url ?>" class="<?= $is_active ? 'active' : '' ?>">
                        <?= mp_icon($ic, 16) ?>
                        <span><?= $label ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="mp-user">
                <div class="mp-user-info">
                    <div class="mp-user-name"><?= htmlspecialchars($user['ad_soyad'] ?: $user['user']) ?></div>
                    <div class="mp-user-firm"><?= htmlspecialchars($user['unvan']) ?></div>
                </div>
                <div class="mp-avatar">
                    <?= htmlspecialchars(strtoupper(mb_substr($user['unvan'] ?: $user['user'], 0, 1))) ?>
                </div>
                <a href="<?= SITE_URL ?>/musteri-portal/logout.php" class="mp-logout" title="Çıkış">
                    <?= mp_icon('logout', 16) ?>
                </a>
            </div>
        </div>
    </header>

    <!-- İçerik -->
    <main class="mp-content">
        <?php if (!empty($user['force_pwd']) && basename($_SERVER['SCRIPT_NAME']) !== 'profil.php'): ?>
            <div class="mp-alert mp-alert-warning">
                <?= mp_icon('alert') ?>
                <div>
                    <strong>İlk giriş:</strong> Güvenlik için şifrenizi değiştirmeniz gerekmektedir.
                    <a href="<?= SITE_URL ?>/musteri-portal/profil.php" style="margin-left:6px;font-weight:700">Şimdi değiştir →</a>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach (mp_flash_get() as $f):
            $cls = match($f['tip']) {
                'success' => 'mp-alert-success',
                'danger'  => 'mp-alert-danger',
                'warning' => 'mp-alert-warning',
                default   => 'mp-alert-info',
            };
            $ic = match($f['tip']) {
                'success' => 'check-circle',
                'danger'  => 'x-circle',
                'warning' => 'alert',
                default   => 'info',
            };
        ?>
            <div class="mp-alert <?= $cls ?>"><?= mp_icon($ic) ?><div><?= htmlspecialchars($f['msg']) ?></div></div>
        <?php endforeach; ?>
    <?php
}

function mp_render_footer(): void
{
    ?>
    </main>

    <footer class="mp-footer">
        <div class="mp-footer-inner">
            <div>
                © <?= date('Y') ?> CODEGA — <a href="https://codega.com.tr" target="_blank">codega.com.tr</a>
            </div>
            <div>
                <?= mp_icon('shield', 13) ?>
                Güvenli Bağlantı · SSL Korumalı
            </div>
        </div>
    </footer>
</div>
</body>
</html>
    <?php
}
