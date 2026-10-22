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
function require_login(): void {
    if(!current_user()) redirect('index.php?page=login');
    // Revalida no banco: suspensão, troca de perfil/nome/e-mail e plano valem imediatamente.
    $q=db()->prepare('SELECT u.name,u.email,u.role,u.status,u.deleted,c.status company_status,s.plan_code FROM users u JOIN companies c ON c.id=u.company_id LEFT JOIN company_subscriptions s ON s.company_id=c.id AND s.status="active" WHERE u.id=? LIMIT 1');
    $q->execute([$_SESSION['user']['id']]); $fresh=$q->fetch();
    if(!$fresh || $fresh['status']!=='active' || (int)$fresh['deleted']===1){ Auth::logout(); redirect('index.php?page=login'); }
    $_SESSION['user']=array_merge($_SESSION['user'],$fresh);
    if(($fresh['company_status']??'active')==='suspended') exit('Conta suspensa.');
}
function require_admin(): void { require_login(); if((current_user()['role']??'')!=='global_admin'){ http_response_code(403); exit('Acesso negado.'); } }
function is_global_admin(): bool { return (current_user()['role']??'')==='global_admin'; }
// Exclusão é exclusiva do administrador geral: valida perfil (403) e CSRF no servidor.
function require_admin_delete(): void { if(!is_global_admin()){ http_response_code(403); exit('Acesso negado.'); } verify_csrf(); }
function delete_button(string $page,string $action,string $field,int $id,string $confirm): void { ?><form method="post" action="index.php?page=<?=e($page)?>" style="display:inline" onsubmit="return confirm(<?=e(json_encode($confirm,JSON_UNESCAPED_UNICODE))?>);"><input type="hidden" name="_csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="<?=e($action)?>"><input type="hidden" name="<?=e($field)?>" value="<?=$id?>"><button class="btn small danger">Excluir</button></form><?php }
function tenant_id(): int { return (int)(current_user()['company_id']??0); }
function is_super_admin(): bool { $u=current_user(); return ($u['role']??'')==='global_admin' || ($u['plan_code']??'')==='admin'; }
function plan_limits(): array {
    // Perfil "Administrador geral": sem limites, independente de qualquer plano comercial.
    if(is_super_admin()){ $m=PHP_INT_MAX; return ['customers'=>$m,'deals'=>$m,'users'=>$m,'tasks'=>$m]; }
    $p=current_user()['plan_code']??'free';
    return ['free'=>['customers'=>5,'deals'=>5,'users'=>1,'tasks'=>5], 'pro'=>['customers'=>100,'deals'=>100,'users'=>3,'tasks'=>100], 'premium'=>['customers'=>500,'deals'=>500,'users'=>5,'tasks'=>500]][$p]??['customers'=>5,'deals'=>5,'users'=>1,'tasks'=>5];
}
function plan_limit_text(string $k): string { $v=plan_limits()[$k]??0; return $v>=PHP_INT_MAX ? 'ilimitado (Administrador geral)' : 'de '.$v.' no plano'; }
function count_tenant(string $table,string $where='1=1',array $params=[]): int { $allowed=['customers','deals','tasks','users']; if(!in_array($table,$allowed,true)) return 0; if($table==='users') $where.=' AND deleted=0'; $q=db()->prepare("SELECT COUNT(*) FROM `$table` WHERE company_id=? AND ($where)"); $q->execute(array_merge([tenant_id()],$params)); return (int)$q->fetchColumn(); }
function limit_reached(string $type,string $where='1=1',array $params=[]): bool { $l=plan_limits(); return count_tenant($type,$where,$params)>=$l[$type]; }
function audit(string $action,string $entity='',?int $entityId=null,string $details=''): void { $q=db()->prepare('INSERT INTO admin_audit_logs(user_id,company_id,action,entity,entity_id,details,created_at) VALUES(?,?,?,?,?,?,NOW())'); $u=current_user(); $q->execute([$u['id']??null,$u['company_id']??null,$action,$entity,$entityId,$details]); }
function layout_start(string $title): void { global $config; $u=current_user(); $flash=get_flash(); ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · Inova CRM</title><link rel="stylesheet" href="assets/app.css"></head><body><div class="app-shell"><aside class="sidebar"><a class="brand" href="index.php"><img src="assets/logo-inova-crm.png" alt="Inova CRM"><span>Inova <b>CRM</b></span></a><nav><?php $cur=$_GET['page']??'dashboard'; $items=['dashboard'=>['index.php','⌂ Dashboard'],'customers'=>['index.php?page=customers','♙ Clientes'],'deals'=>['index.php?page=deals','◈ Oportunidades'],'tasks'=>['index.php?page=tasks','✓ Tarefas']]; if($u && in_array($u['role'],['owner','global_admin'],true)) $items['users']=['index.php?page=users','☻ Usuários']; if($u && $u['role']==='global_admin') $items['admin']=['index.php?page=admin','⚙ Administração']; foreach($items as $k=>$it): ?><a href="<?=$it[0]?>" class="<?=$cur===$k?'active':''?>"><?=$it[1]?></a><?php endforeach; ?><a href="index.php?page=logout">↪ Sair</a></nav><div class="side-foot">Conecte. Organize.<br><strong>Cresça.</strong></div></aside><main class="main"><header class="topbar"><div><span class="eyebrow">INOVA CRM</span><h1><?=e($title)?></h1></div><div class="user-pill"><?=e($u['name']??'Visitante')?> <small><?=e($u['role']??'')?></small></div></header><?php if($flash): ?><div class="flash <?=$flash[0]?>"><?=e($flash[1])?></div><?php endif; ?><?php }
function layout_end(): void { ?></main></div></body></html><?php }
