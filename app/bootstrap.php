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
    session_name((string) config('app.session_name', 'team_workspace_session'));
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => ((string) config('app.base_path', '') !== '' ? rtrim((string) config('app.base_path', ''), '/') . '/' : '/'),
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
