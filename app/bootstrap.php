<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    throw new RuntimeException('config.php not found. Copy config.example.php to config.php and configure the application.');
}

$GLOBALS['app_config'] = require $configFile;
if (!is_array($GLOBALS['app_config'])) {
    throw new RuntimeException('config.php must return an array.');
}

$autoloadFile = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoloadFile)) {
    throw new RuntimeException('Composer dependencies are missing. Run composer install.');
}
require_once $autoloadFile;

function config(string $key, mixed $default = null): mixed {
    $value = $GLOBALS['app_config'] ?? [];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function config_bool(string $key, bool $default = false): bool {
    return (bool) config($key, $default);
}

date_default_timezone_set((string) config('app.timezone', 'Europe/Berlin'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Harden PHP sessions before the session is started.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $configuredUrl = (string) config('app.url', '');
    $httpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $httpsConfigured = strtolower((string) parse_url($configuredUrl, PHP_URL_SCHEME)) === 'https';
    $secureCookie = (bool) config('security.secure_cookies', $httpsRequest || $httpsConfigured);

    session_name((string) config('app.session_name', 'corp_site_session'));
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secureCookie,
        'samesite' => 'Lax',
        'path' => ((string) config('app.base_path', '') !== '' ? rtrim((string) config('app.base_path', ''), '/') . '/' : '/'),
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/passkeys.php';
security_headers();
