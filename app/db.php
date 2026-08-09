<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) config('database.host', '127.0.0.1');
    $port = (int) config('database.port', 3306);
    $name = (string) config('database.database', 'corp_site');
    $charset = (string) config('database.charset', 'utf8mb4');
    $username = (string) config('database.username', 'root');
    $password = (string) config('database.password', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
