<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

/* ---------- Leitura do .env (sem Composer) ---------- */
function load_env(string $file): void {
    if (!is_file($file) || !is_readable($file)) return;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $i => $line) {
        if ($i === 0) $line = preg_replace('/^\xEF\xBB\xBF/', '', $line); // remove BOM do Windows
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key === '') continue;
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        // Algumas hospedagens desativam putenv(); sem este teste o PHP 8 dá erro fatal.
        if (function_exists('putenv')) @putenv($key . '=' . $value);
    }
}

/* Lê uma variável de ambiente de qualquer fonte disponível. */
function env_value(string $key, ?string $default = null): ?string {
    foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)] as $v) {
        if ($v !== null && $v !== false && $v !== '') return (string) $v;
    }
    return $default;
}

load_env(APP_ROOT . '/.env');

/* ---------- Tratamento global de erros ---------- */
function app_log(string $message): void {
    $dir = APP_ROOT . '/storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ok = @file_put_contents($dir . '/app.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
    if ($ok === false) error_log('[InovaCRM] ' . $message);
}

set_exception_handler(function (Throwable $e): void {
    app_log(get_class($e) . ': ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);

    $debug = env_value('APP_DEBUG', '0') === '1';
    $detail = '';
    if ($debug) {
        $detail = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
    }

    $hint = 'Ocorreu um erro interno. Tente novamente em instantes.';
    if ($debug && $e instanceof PDOException) {
        $code = (string) $e->getCode();
        if (in_array($code, ['1045', '1044'], true)) $hint = 'O banco recusou o usuário/senha. Confira DB_USERNAME e DB_PASSWORD no arquivo .env do servidor.';
        elseif (in_array($code, ['2002', '2006'], true)) $hint = 'Não foi possível conectar ao servidor do banco. Confira DB_HOST e DB_PORT no .env (ou se o .env foi enviado ao servidor).';
        elseif ($code === '1049') $hint = 'O banco informado em DB_DATABASE não existe.';
        elseif ($code === '42S02') $hint = 'Faltam tabelas. Importe database/schema.sql no phpMyAdmin do banco.';
        elseif ($code === '42S22') $hint = 'O banco está com estrutura desatualizada. Reimporte database/schema.sql.';
    }

    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Erro · Inova CRM</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#0b1220;color:#e8eefc;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px}'
       . '.b{max-width:560px;background:#121a2e;border:1px solid #24304f;border-radius:14px;padding:28px}h1{margin:0 0 8px;font-size:1.3rem}'
       . 'code{display:block;margin-top:14px;padding:10px;background:#0b1220;border-radius:8px;font-size:.85rem;word-break:break-word;color:#ffb4a8}a{color:#7aa7ff}</style></head><body><div class="b">'
       . '<h1>Algo deu errado</h1><p>' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</p>'
       . ($detail !== '' ? '<code>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</code>' : '')
       . '<p><a href="index.php?page=login">Voltar ao login</a></p></div></body></html>';
});

/* ---------- Configuração e sessão ---------- */
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) { http_response_code(500); exit('Configuração ausente. Copie config/config.example.php para config/config.php.'); }
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

// Cabeçalhos básicos; CSP fica para uma etapa própria, pois a interface usa
// estilos/scripts inline e fontes externas.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, private');

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if ($isHttps) ini_set('session.cookie_secure', '1');
session_name($config['session_name'] ?? 'inova_crm');
session_start();

// Encerra sessões após 30 minutos sem atividade e troca o identificador.
$now = time();
if (isset($_SESSION['last_activity']) && $now - (int)$_SESSION['last_activity'] > 1800) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = $now;

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/profile.php';
