<?php
/**
 * layout.php v1.1 — Modern profesyonel layout
 * Inline SVG icons (Font Awesome bağımlılığı YOK)
 * Sidebar + Topbar pattern
 */

/**
 * SVG icon helper — tek yerde tanımlı, her yerden çağrılır
 */
function icon(string $name, int $size = 18): string
{
    static $icons = null;
    if ($icons === null) {
        $icons = [
            // Dashboard icons
            'dashboard' => '<path d="M3 3h8v10H3V3zm10 0h8v6h-8V3zM3 15h8v6H3v-6zm10-4h8v10h-8V11z"/>',
            'invoice'   => '<path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zM8 18v-2h5v2H8zm8-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
            'plus'      => '<path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>',
            'building'  => '<path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/>',
            'list'      => '<path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zm0-10v2h14V7H7z"/>',
            'settings'  => '<path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>',
            'activity'  => '<path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.11 0 2-.9 2-2V5c0-1.1-.89-2-2-2zm-5.5 15L12 16.5 10.5 18 7 14.5 10.5 11 12 12.5 8.5 16l2.5 2.5 5-5 1.5 1.5-5 5z"/>',
            'update'    => '<path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/>',
            'users'     => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>',
            'key'       => '<path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>',
            'logout'    => '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>',
            'search'    => '<path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>',
            'bell'      => '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>',
            'check'     => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
            'check-circle' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>',
            'x-circle'  => '<path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/>',
            'clock'     => '<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>',
            'alert'     => '<path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>',
            'info'      => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>',
            'edit'      => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
            'trash'     => '<path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>',
            'download'  => '<path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>',
            'upload'    => '<path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/>',
            'eye'       => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
            'heart-pulse' => '<path d="M19.5 3.5L18 2l-1.5 1.5L15 2l-1.5 1.5L12 2l-1.5 1.5L9 2 7.5 3.5 6 2v14H3v3c0 1.66 1.34 3 3 3h12c1.66 0 3-1.34 3-3V2l-1.5 1.5zM19 19c0 .55-.45 1-1 1s-1-.45-1-1v-3H8V5h11v14zm-9-9h6v2h-6zm0 3h6v2h-6zm0-6h6v2h-6z"/>',
            'chart'     => '<path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/>',
            'chart-pie' => '<path d="M11 2v20c-5.07-.5-9-4.79-9-10s3.93-9.5 9-10zm2.03 0v8.99H22c-.47-4.74-4.24-8.52-8.97-8.99zm0 11.01V22c4.74-.47 8.5-4.25 8.97-8.99h-8.97z"/>',
            'folder'    => '<path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>',
            'file-code' => '<path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1.54 14.54L11.05 18l-3.54-3.54 3.54-3.54 1.41 1.41L10.34 14l2.12 2.54zm2.08-5.08L15.95 10l3.54 3.54-3.54 3.54-1.41-1.41L16.07 14l-2.12-2.54zM13 9V3.5L18.5 9H13z"/>',
            'signature' => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
            'paper-plane' => '<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>',
            'ban'       => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1C19.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z"/>',
            'arrow-left'  => '<path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>',
            'arrow-right' => '<path d="M4 11v2h12l-5.5 5.5 1.42 1.42L19.84 12l-7.92-7.92L10.5 5.5 16 11z"/>',
            'chevron-right' => '<path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>',
            'filter'    => '<path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>',
            'refresh'   => '<path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>',
            'lock'      => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
            'shield'    => '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>',
            'package'   => '<path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.236L19.09 8 12 11.764 4.91 8 12 4.236zM4 9.618l7 3.5v7.764l-7-3.5V9.618zm9 11.264v-7.764l7-3.5v7.764l-7 3.5z"/>',
            'zap'       => '<path d="M7 2v11h3v9l7-12h-4l4-8H7z"/>',
            'credit-card' => '<path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>',
            'github'    => '<path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>',
            'building2' => '<path d="M17 11V3H7v4H3v14h8v-4h2v4h8V11h-4zM7 19H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V9h2v2zm4 4H9v-2h2v2zm0-4H9V9h2v2zm0-4H9V5h2v2zm4 8h-2v-2h2v2zm0-4h-2V9h2v2zm0-4h-2V5h2v2zm4 12h-2v-2h2v2zm0-4h-2v-2h2v2z"/>',
        ];
    }
    if (!isset($icons[$name])) return '';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="currentColor">'.$icons[$name].'</svg>';
}

function render_header(string $title = '', string $active = ''): void
{
    $user = auth_user();
    $full_title = $title ? ($title . ' · ' . SITE_NAME) : SITE_NAME;
    global $pdo;

    // Menü: [slug, label, icon, role, url, group]
    $menu = [
        ['dashboard', 'Dashboard',    'dashboard',  'viewer',   '/index.php',              'main'],
        ['fatura',    'Faturalar',    'invoice',    'viewer',   '/fatura/liste.php',       'main'],
        ['yeni',      'Yeni Fatura',  'plus',       'operator', '/fatura/yeni.php',        'main'],
        ['musteri',   'Müşteriler',   'building',   'viewer',   '/musteri/index.php',      'main'],
        ['log',       'Aktivite',     'activity',   'viewer',   '/log/index.php',          'main'],
        ['ayarlar',   'Ayarlar',      'settings',   'admin',    '/yonetim/ayarlar.php',    'yonetim'],
        ['kullanici', 'Kullanıcılar', 'users',      'admin',    '/yonetim/kullanici.php',  'yonetim'],
        ['musteri-kullanici', 'Müşteri Portal Kul.', 'shield', 'admin', '/yonetim/musteri-kullanici.php', 'yonetim'],
        ['guncelleme','Güncelleme',   'update',     'admin',    '/yonetim/guncelleme.php', 'yonetim'],
        ['sifre',     'Şifrem',       'key',        'viewer',   '/yonetim/sifre.php',      'yonetim'],
    ];
    $hierarchy = ['viewer' => 1, 'operator' => 2, 'admin' => 3];
    $user_level = $hierarchy[$user['rol'] ?? 'viewer'] ?? 1;

    // Portal ve kütüphane sürümlerini oku
    $portal_ver = 'v1.1.0';
    $lib_ver = 'v0.1';
    try {
        $mf = ROOT_PATH . '/manifest.json';
        if (file_exists($mf)) {
            $m = json_decode(file_get_contents($mf), true);
            if (isset($m['version'])) $portal_ver = 'v' . $m['version'];
        }
        if (isset($pdo)) {
            $lv = ayar_get($pdo, 'kutuphane_surumu', '0.1.0-alpha');
            $lib_ver = 'v' . $lv;
        }
    } catch (\Exception $e) {}

    // Page title için
    $page_titles = [
        'dashboard'  => ['Dashboard', 'Portal genel bakış'],
        'fatura'     => ['Faturalar', 'Tüm fatura kayıtları'],
        'yeni'       => ['Yeni Fatura', 'Fatura oluştur'],
        'musteri'    => ['Müşteriler', 'Mükellef kayıtları'],
        'log'        => ['Aktivite Log', 'Sistem kayıtları'],
        'ayarlar'    => ['Sistem Ayarları', 'Yapılandırma'],
        'kullanici'  => ['Kullanıcılar', 'Portal kullanıcıları'],
        'musteri-kullanici' => ['Müşteri Portal Kullanıcıları', 'Müşterilere özel portal erişimleri'],
        'guncelleme' => ['Güncelleme', 'Yazılım yönetimi'],
        'sifre'      => ['Şifre', 'Hesap güvenliği'],
    ];
    $pt = $page_titles[$active] ?? [$title, ''];

    ?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($full_title) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css?v=1.1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" rx="5" fill="#ff6b00"/><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6z" fill="#fff"/></svg>') ?>">
</head>
<body>
<div class="app">
    <aside class="sb" id="sidebar">
        <div class="sb-brand">
            <div class="sb-logo"><?= icon('invoice', 22) ?></div>
            <div>
                <div class="sb-title"><?= h(SITE_SHORT) ?></div>
                <div class="sb-subtitle">Entegratör Portal</div>
            </div>
        </div>

        <div class="sb-section">Ana Menü</div>
        <nav class="sb-nav">
            <?php foreach ($menu as [$slug, $label, $ic, $role_req, $url, $grp]):
                if (($hierarchy[$role_req] ?? 99) > $user_level) continue;
                if ($grp !== 'main') continue;
                $is_active = $slug === $active;
            ?>
                <a href="<?= SITE_URL . $url ?>" class="<?= $is_active ? 'active' : '' ?>">
                    <?= icon($ic) ?>
                    <span><?= $label ?></span>
                </a>
            <?php endforeach; ?>

            <div class="sb-section">Yönetim</div>
            <?php foreach ($menu as [$slug, $label, $ic, $role_req, $url, $grp]):
                if (($hierarchy[$role_req] ?? 99) > $user_level) continue;
                if ($grp !== 'yonetim') continue;
                $is_active = $slug === $active;
            ?>
                <a href="<?= SITE_URL . $url ?>" class="<?= $is_active ? 'active' : '' ?>">
                    <?= icon($ic) ?>
                    <span><?= $label ?></span>
                </a>
            <?php endforeach; ?>

            <div class="sb-section">&nbsp;</div>
            <a href="<?= SITE_URL ?>/logout.php" style="color:#fca5a5">
                <?= icon('logout') ?>
                <span>Çıkış Yap</span>
            </a>
        </nav>

        <div class="sb-foot">
            <div class="sb-version">
                <span>Portal</span>
                <strong><?= h($portal_ver) ?></strong>
            </div>
            <div class="sb-version">
                <span>Kütüphane</span>
                <strong><?= h($lib_ver) ?></strong>
            </div>
            <div class="sb-version" style="margin-top:6px;padding-top:6px;border-top:1px solid rgba(255,255,255,0.08)">
                <span style="display:flex;align-items:center;gap:6px"><span class="sb-update-dot"></span> Sistem</span>
                <strong style="color:#86efac">Aktif</strong>
            </div>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="tb-page">
                <h1><?= h($pt[0]) ?></h1>
                <?php if ($pt[1]): ?><div class="tb-breadcrumb"><?= h($pt[1]) ?></div><?php endif; ?>
            </div>

            <div class="tb-actions">
                <div class="tb-search">
                    <?= icon('search', 16) ?>
                    <input type="text" placeholder="Fatura no, müşteri, VKN..." onkeydown="if(event.key==='Enter'){location.href='<?= SITE_URL ?>/fatura/liste.php?q='+encodeURIComponent(this.value)}">
                </div>
                <div class="tb-user">
                    <div class="tb-avatar"><?= h(strtoupper(mb_substr($user['ad_soyad'] ?: $user['user'], 0, 1))) ?></div>
                    <div class="tb-user-info">
                        <div class="tb-user-name"><?= h($user['ad_soyad'] ?: $user['user']) ?></div>
                        <div class="tb-user-role"><?= h($user['rol']) ?></div>
                    </div>
                </div>
            </div>
        </header>

        <div class="content">
            <?php if (!empty($user['force_pwd']) && basename($_SERVER['SCRIPT_NAME']) !== 'sifre.php'): ?>
                <div class="alert alert-warning">
                    <?= icon('alert') ?>
                    <div>
                        <strong>İlk giriş:</strong> Güvenlik için şifrenizi değiştirmeden devam edemezsiniz.
                        <a href="<?= SITE_URL ?>/yonetim/sifre.php" style="margin-left:6px;font-weight:700"><strong>Şimdi değiştir →</strong></a>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            foreach (flash_get() as $f) {
                $cls = match($f['tip']) {
                    'success' => 'alert-success',
                    'danger'  => 'alert-danger',
                    'warning' => 'alert-warning',
                    default   => 'alert-info',
                };
                $ic = match($f['tip']) {
                    'success' => 'check-circle',
                    'danger'  => 'x-circle',
                    'warning' => 'alert',
                    default   => 'info',
                };
                echo '<div class="alert '.$cls.'">'.icon($ic).'<div>'.h($f['msg']).'</div></div>';
            }
            ?>
    <?php
}

function render_footer(): void
{
    ?>
        </div>
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/app.js?v=1.1"></script>
</body>
</html>
    <?php
}
