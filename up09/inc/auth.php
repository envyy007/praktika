<?php
/** Сеансы, CSRF, роли, уведомления. Совместимо с PHP 7.1–8.3. */

require_once __DIR__ . '/db.php';

/* Полифилы строковых функций PHP 8 для старых версий интерпретатора. */
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $needle = (string)$needle;
        return $needle === '' || substr((string)$haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return $needle === '' || strncmp((string)$haystack, (string)$needle, strlen((string)$needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle === '' || strpos((string)$haystack, (string)$needle) !== false;
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('TARELKASID');
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function csrf_ok(): bool
{
    return hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''));
}

function current_user(): ?array
{
    static $done = false, $user = null;
    if ($done) return $user;
    $done = true;
    if (empty($_SESSION['uid'])) return $user = null;
    $user = q1('SELECT id, login, email, name, role, bio, created_at FROM users WHERE id = :id AND status = "active"',
        [':id' => (int)$_SESSION['uid']]);
    if (!$user) unset($_SESSION['uid']);
    return $user;
}
function is_logged(): bool { return current_user() !== null; }
function is_admin(): bool { $u = current_user(); return $u && in_array($u['role'], ['admin', 'editor'], true); }

function require_login(): void
{
    if (!is_logged()) {
        flash('warn', 'Войдите в аккаунт, чтобы продолжить.');
        header('Location: ' . base_url('login.php?back=' . urlencode(current_path())));
        exit;
    }
}
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Недостаточно прав');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid']  = (int)$user['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}
function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function notify(int $userId, string $body, string $url = ''): void
{
    ex('INSERT INTO notifications (user_id, body, url, is_read, created_at) VALUES (:u, :b, :url, 0, NOW())',
       [':u' => $userId, ':b' => mb_substr($body, 0, 300), ':url' => $url]);
}
function unread_count(): int
{
    $u = current_user();
    return $u ? (int)scalar('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0', [':u' => (int)$u['id']]) : 0;
}

function audit(string $action, string $detail = ''): void
{
    $u = current_user();
    ex('INSERT INTO audit_log (user_id, action, detail, ip, created_at) VALUES (:u, :a, :d, :ip, NOW())', [
        ':u' => $u ? (int)$u['id'] : null, ':a' => $action,
        ':d' => mb_substr($detail, 0, 300), ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
}

/** Базовый адрес проекта: работает из корня и из подпапки admin. */
function base_url(string $path = ''): string
{
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    if ($dir === '.' || $dir === '/') {
        $dir = '';
    }
    $suffix = '/admin';
    $len = strlen($suffix);
    if (strlen($dir) >= $len && substr($dir, -$len) === $suffix) {
        $dir = substr($dir, 0, strlen($dir) - $len);
    }
    return $dir . '/' . ltrim($path, '/');
}

function current_path(): string
{
    $file = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'index.php');
    $q = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    return $file . ($q !== '' ? '?' . $q : '');
}
function safe_back(?string $path, string $default = 'account.php'): string
{
    $path = (string)$path;
    return preg_match('~^[a-z0-9_\-]+\.php(\?[a-z0-9_\-=&%.]*)?$~i', $path) ? $path : $default;
}
function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}