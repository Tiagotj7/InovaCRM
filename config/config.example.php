<?php
declare(strict_types=1);
return [
    'app_name' => getenv('APP_NAME') ?: 'Inova CRM',
    'base_url' => getenv('APP_URL') ?: '',
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo',
    'session_name' => getenv('SESSION_NAME') ?: 'inova_crm_session',
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_DATABASE') ?: 'inova_crm',
        'user' => getenv('DB_USERNAME') ?: 'inova_crm_user',
        'pass' => getenv('DB_PASSWORD') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
];
