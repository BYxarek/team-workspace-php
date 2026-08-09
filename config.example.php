<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'Team Workspace',
        'version' => '1.0.1',
        'env' => 'production',
        'debug' => false,
        'url' => 'https://example.com',
        'base_path' => '',
        'timezone' => 'UTC',
        'session_name' => 'team_workspace_session',
    ],
    'security' => [
        // Force Secure on the session cookie even behind a reverse proxy.
        'secure_cookies' => true,
        // Reject oversized JSON API bodies before parsing.
        'max_json_body_bytes' => 1048576,
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'team_workspace',
        'username' => 'team_workspace',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
];
