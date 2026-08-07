<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'Team Workspace',
        'env' => 'production',
        'debug' => false,
        'url' => 'https://example.com',
        'base_path' => '',
        'timezone' => 'UTC',
        'session_name' => 'team_workspace_session',
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
