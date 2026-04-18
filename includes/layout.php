<?php
/**
 * layout.php — Ortak sayfa iskeleti: render_header() ve render_footer().
 *
 * Kullanım:
 *   require '../../config.php';
 *   require INCLUDES_PATH.'/init.php';
 *   require INCLUDES_PATH.'/helpers.php';
 *   require INCLUDES_PATH.'/auth.php';
 *   require INCLUDES_PATH.'/layout.php';
 *   auth_require();
 *   render_header('Sayfa Başlığı', 'fatura');  // 2. parametre menü aktif
 *   // ... içerik ...
 *   render_footer();
 */

function render_header(string $title = '', string $active = ''): void
{
    $user = auth_user();
    $full_title = $title ? ($title . ' · ' . SITE_NAME) : SITE_NAME;

    // Menü: [slug, label, icon, role_required]
    $menu = [
        ['dashboard', 'Dashboard',    'chart-line',    'viewer',   '/index.php'],
        ['fatura',    'Faturalar',    'file-invoice',  'viewer',   '/fatura/liste.php'],
        ['yeni',      'Yeni Fatura',  'plus-circle',   'operator', '/fatura/yeni.php'],
        ['musteri',   'Müşteriler',   'building',      'viewer',   '/musteri/index.php'],
        ['log',       'Aktivite',     'list',          'viewer',   '/log/index.php'],
        ['yonetim',   'Yönetim',      'cog',           'admin',    '/yonetim/ayarlar.php'],
    ];

    $hierarchy = ['viewer' => 1, 'operator' => 2, 'admin' => 3];
    $user_level = $hierarchy[$user['rol'] ?? 'viewer'] ?? 1;

    ?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($full_title) ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/style.css?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sb-head">
            <div class="sb-logo">
                <div class="sb-d"><i class="fas fa-file-invoice-dollar"></i></div>
                <div>
                    <div class="sb-title"><?= h(SITE_SHORT) ?></div>
                    <div class="sb-sub">e-Fatura Portal</div>
                </div>
            </div>
        </div>
        <nav class="sb-nav">
            <?php foreach ($menu as [$slug, $label, $icon, $role_req, $url]):
                if (($hierarchy[$role_req] ?? 99) > $user_level) continue;
                $is_active = $slug === $active;
            ?>
                <a href="<?= SITE_URL . $url ?>" class="<?= $is_active ? 'active' : '' ?>">
                    <i class="fas fa-<?= $icon ?>"></i>
                    <span><?= $label ?></span>
                </a>
            <?php endforeach; ?>
            <div class="sb-sep"></div>
            <a href="<?= SITE_URL ?>/logout.php" class="sb-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Çıkış</span>
            </a>
        </nav>
        <div class="sb-foot">
            <div class="sb-user">
                <div class="sb-avatar"><?= h(strtoupper(mb_substr($user['ad_soyad'] ?: $user['user'], 0, 1))) ?></div>
                <div class="sb-user-info">
                    <div class="sb-user-name"><?= h($user['ad_soyad'] ?: $user['user']) ?></div>
                    <div class="sb-user-role"><?= h($user['rol']) ?></div>
                </div>
            </div>
            <div class="sb-version">v<?= h(ayar_get($GLOBALS['pdo'] ?? new PDO('sqlite::memory:'), 'portal_surumu', '1.0.0')) ?></div>
        </div>
    </aside>

    <main class="main">
        <?php if (!empty($user['force_pwd']) && basename($_SERVER['SCRIPT_NAME']) !== 'sifre.php'): ?>
            <div class="alert alert-warning" style="margin:18px 20px 0 20px">
                <i class="fas fa-exclamation-triangle"></i>
                İlk giriş — güvenlik için şifrenizi değiştirmeden devam edemezsiniz.
                <a href="<?= SITE_URL ?>/yonetim/sifre.php" style="margin-left:6px"><strong>Şimdi değiştir →</strong></a>
            </div>
        <?php endif; ?>

        <div class="main-inner">
            <?= flash_render() ?>
    <?php
}

function render_footer(): void
{
    ?>
        </div><!-- main-inner -->
    </main>
</div>
<script src="<?= SITE_URL ?>/assets/app.js?v=1"></script>
</body>
</html>
    <?php
}
