<?php
/**
 * Ortak yardımcı fonksiyonlar.
 */

// ── XSS-safe output ────────────────────────────────────
function h(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}

// ── CSRF ───────────────────────────────────────────────
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">';
}

// ── Redirect ───────────────────────────────────────────
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ── Flash mesajları ────────────────────────────────────
function flash_set(string $tip, string $msg): void
{
    $_SESSION['flash'][] = ['tip' => $tip, 'msg' => $msg];
}

function flash_get(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function flash_render(): string
{
    $out = '';
    foreach (flash_get() as $f) {
        $cls = match($f['tip']) {
            'success' => 'alert-success',
            'danger'  => 'alert-danger',
            'warning' => 'alert-warning',
            default   => 'alert-info',
        };
        $icon = match($f['tip']) {
            'success' => 'check-circle',
            'danger'  => 'times-circle',
            'warning' => 'exclamation-triangle',
            default   => 'info-circle',
        };
        $out .= '<div class="alert '.$cls.'"><i class="fas fa-'.$icon.'"></i> '.h($f['msg']).'</div>';
    }
    return $out;
}

// ── Tutar formatla ─────────────────────────────────────
function fmt_tl(float $v): string
{
    return number_format($v, 2, ',', '.') . ' ₺';
}

function fmt_date(?string $date, string $fmt = 'd.m.Y'): string
{
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '—';
}

function fmt_datetime(?string $dt): string
{
    return fmt_date($dt, 'd.m.Y H:i');
}

// ── Slugify ────────────────────────────────────────────
function slugify(string $text): string
{
    $tr = ['ğ'=>'g','Ğ'=>'g','ü'=>'u','Ü'=>'u','ş'=>'s','Ş'=>'s','ı'=>'i','İ'=>'i','ö'=>'o','Ö'=>'o','ç'=>'c','Ç'=>'c'];
    $text = strtr($text, $tr);
    $text = preg_replace('~[^a-zA-Z0-9]+~', '-', $text);
    return strtolower(trim($text, '-')) ?: 'item';
}

// ── IP + UA ────────────────────────────────────────────
function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
}

function client_ua(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
}

// ── Güvenli path ─────────────────────────────────────
function safe_path(string $base, string $relative): ?string
{
    $full = realpath($base . '/' . $relative);
    $baseReal = realpath($base);
    if ($full && $baseReal && str_starts_with($full, $baseReal)) {
        return $full;
    }
    return null;
}

// ── Log yazar — sistem_log tablosuna ─────────────────
function audit_log(PDO $pdo, string $event, ?string $detail = null, ?int $user_id = null, ?string $target = null): void
{
    try {
        $pdo->prepare(
            "INSERT INTO sistem_log (kullanici_id, olay, detay, hedef, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $user_id ?? ($_SESSION['user_id'] ?? null),
            $event,
            $detail,
            $target,
            client_ip(),
            client_ua(),
        ]);
    } catch (\Exception $e) {
        error_log('audit_log fail: '.$e->getMessage());
    }
}

// ── Fatura durumu etiketleri ─────────────────────────
function fatura_durum_html(string $durum): string
{
    $map = [
        'taslak'   => ['Taslak',    'secondary', 'clock'],
        'hazir'    => ['Hazır',     'info',      'check'],
        'imzali'   => ['İmzalı',    'primary',   'signature'],
        'gonderildi'=> ['Gönderildi','warning',  'paper-plane'],
        'kabul'    => ['Kabul',     'success',   'check-circle'],
        'red'      => ['Red',       'danger',    'x-circle'],
        'iptal'    => ['İptal',     'dark',      'ban'],
    ];
    [$text, $cls, $icon] = $map[$durum] ?? [$durum, 'secondary', 'info'];
    // icon() fonksiyonu layout.php'de tanımlı
    $svg = function_exists('icon') ? icon($icon, 11) : '';
    return '<span class="badge badge-'.$cls.'">'.$svg.' '.h($text).'</span>';
}
