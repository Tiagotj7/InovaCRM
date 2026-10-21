<?php
declare(strict_types=1);
$envFile=dirname(__DIR__).'/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line=trim($line);
        if ($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
        [$key,$value]=array_map('trim',explode('=',$line,2));
        $value=trim($value," \t\n\r\0\x0B\"'");
        if ($key!=='') { $_ENV[$key]=$value; putenv($key.'='.$value); }
    }
}
$configFile=__DIR__.'/config.php';
if (!is_file($configFile)) { http_response_code(500); exit('Configuração ausente. Copie config/config.example.php para config/config.php.'); }
$config=require $configFile;
date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');
ini_set('session.use_strict_mode','1');
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure','1');
session_name($config['session_name'] ?? 'inova_crm');
session_start();
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/Database.php';
require_once __DIR__.'/../app/Auth.php';
