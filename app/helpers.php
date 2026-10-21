<?php
declare(strict_types=1);
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: '.$url); exit; }
function flash(string $type,string $message): void { $_SESSION['_flash']=[$type,$message]; }
function get_flash(): ?array { $f=$_SESSION['_flash']??null; unset($_SESSION['_flash']); return $f; }
function csrf_token(): string { if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function verify_csrf(): void { if(!hash_equals($_SESSION['_csrf']??'',$_POST['_csrf']??'')){ http_response_code(419); exit('Token de segurança inválido.'); } }
function post(string $key,string $default=''): string { return trim((string)($_POST[$key]??$default)); }
function current_user(): ?array { return $_SESSION['user']??null; }
function require_login(): void { if(!current_user()) redirect('index.php?page=login'); if(($_SESSION['user']['company_status']??'active')==='suspended') exit('Conta suspensa.'); }
function require_admin(): void { require_login(); if((current_user()['role']??'')!=='global_admin'){ http_response_code(403); exit('Acesso negado.'); } }
function tenant_id(): int { return (int)(current_user()['company_id']??0); }
function plan_limits(): array { $p=current_user()['plan_code']??'free'; return ['free'=>['customers'=>5,'deals'=>5,'users'=>1,'tasks'=>5], 'pro'=>['customers'=>100,'deals'=>100,'users'=>3,'tasks'=>100], 'premium'=>['customers'=>500,'deals'=>500,'users'=>5,'tasks'=>500]][$p]??['customers'=>5,'deals'=>5,'users'=>1,'tasks'=>5]; }
function count_tenant(string $table,string $where='1=1',array $params=[]): int { $allowed=['customers','deals','tasks','users']; if(!in_array($table,$allowed,true)) return 0; $q=db()->prepare("SELECT COUNT(*) FROM `$table` WHERE company_id=? AND ($where)"); $q->execute(array_merge([tenant_id()],$params)); return (int)$q->fetchColumn(); }
function limit_reached(string $type,string $where='1=1',array $params=[]): bool { $l=plan_limits(); return count_tenant($type,$where,$params)>=$l[$type]; }
function audit(string $action,string $entity='',?int $entityId=null,string $details=''): void { $q=db()->prepare('INSERT INTO admin_audit_logs(user_id,company_id,action,entity,entity_id,details,created_at) VALUES(?,?,?,?,?,?,NOW())'); $u=current_user(); $q->execute([$u['id']??null,$u['company_id']??null,$action,$entity,$entityId,$details]); }
function layout_start(string $title): void { global $config; $u=current_user(); $flash=get_flash(); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · Inova CRM</title><link rel="stylesheet" href="assets/app.css"></head><body><div class="app-shell"><aside class="sidebar"><a class="brand" href="index.php"><img src="assets/logo-inova-crm.png" alt="Inova CRM"><span>Inova <b>CRM</b></span></a><nav><?php if($u && $u['role']==='global_admin'): ?><a href="index.php?page=admin">◈ Administração</a><?php else: ?><a href="index.php">⌂ Dashboard</a><a href="index.php?page=customers">♙ Clientes</a><a href="index.php?page=deals">◈ Oportunidades</a><a href="index.php?page=tasks">✓ Tarefas</a><?php endif; ?><a href="index.php?page=logout">↪ Sair</a></nav><div class="side-foot">Conecte. Organize.<br><strong>Cresça.</strong></div></aside><main class="main"><header class="topbar"><div><span class="eyebrow">INOVA CRM</span><h1><?=e($title)?></h1></div><div class="user-pill"><?=e($u['name']??'Visitante')?> <small><?=e($u['role']??'')?></small></div></header><?php if($flash): ?><div class="flash <?=$flash[0]?>"><?=e($flash[1])?></div><?php endif; ?><?php }
function layout_end(): void { ?></main></div></body></html><?php }
