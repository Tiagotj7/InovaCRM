<?php
declare(strict_types=1);
// Os valores vêm do .env (lido em config/bootstrap.php).
return [
    'app_name' => env_value('APP_NAME', 'Inova CRM'),
    'base_url' => env_value('APP_URL', ''),
    'timezone' => env_value('APP_TIMEZONE', 'America/Sao_Paulo'),
    'session_name' => env_value('SESSION_NAME', 'inova_crm_session'),
    'db' => [
        'host' => env_value('DB_HOST', 'localhost'),
        'port' => env_value('DB_PORT', '3306'),
        'name' => env_value('DB_DATABASE', 'inova_crm'),
        'user' => env_value('DB_USERNAME', 'inova_crm_user'),
        'pass' => env_value('DB_PASSWORD', ''),
        'charset' => env_value('DB_CHARSET', 'utf8mb4'),
    ],
];
