<?php



declare(strict_types=1);

return [
    'app' => [
        'name' => getenv('APP_NAME') ?: 'Madocks Rewards',
        'env' => getenv('APP_ENV') ?: 'local',
        'base_url' => getenv('APP_BASE_URL') ?: '',
        'session_name' => getenv('APP_SESSION_NAME') ?: 'madocks_session',
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'madocks_rewards',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
];
