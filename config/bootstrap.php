<?php
declare(strict_types=1);

// Prevent multiple includes
if (defined('ISSP_BOOTSTRAP_LOADED')) {
    return;
}
define('ISSP_BOOTSTRAP_LOADED', true);

// Configure session settings BEFORE starting session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    // Lax is more reliable behind Hostinger proxies than Strict for login redirects.
    ini_set('session.cookie_samesite', 'Lax');
    $https = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1'))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443');
    if ($https) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Try multiple possible .env locations
$envPath = null;
$possiblePaths = [
    dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',  // Parent directory (normal structure)
    __DIR__ . DIRECTORY_SEPARATOR . '.env',           // Same directory as bootstrap
    dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . '.env', // Two levels up
];
foreach ($possiblePaths as $path) {
    if (is_file($path)) {
        $envPath = $path;
        break;
    }
}

if ($envPath && is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '=') !== false && substr(trim($line), 0, 1) !== '#') {
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if (!isset($_ENV[$k]) && !isset($_SERVER[$k])) putenv($k.'='.$v);
        }
    }
}

// Set headers only if not already sent
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: no-referrer');
    $strict = getenv('CSP_STRICT') === 'true';
    if ($strict) {
        header("Content-Security-Policy: default-src 'self' data:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'");
    } else {
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' https: data:");
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string {
        $v = getenv($key);
        return $v !== false ? $v : $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check(?string $token): bool {
        return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}

if (!function_exists('csrf_rotate')) {
    function csrf_rotate(): string {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
}

// App timezone (DICT Region II / PH). Falls back to Asia/Manila.
$appTz = getenv('TZ') ?: 'Asia/Manila';
if (is_string($appTz) && $appTz !== '' && in_array($appTz, timezone_identifiers_list(), true)) {
    date_default_timezone_set($appTz);
}