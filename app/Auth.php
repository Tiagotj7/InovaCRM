<?php
final class Auth {
 public static function login(string $email,string $password): bool { $q=db()->prepare('SELECT u.*,c.name company_name,c.status company_status,s.plan_code FROM users u JOIN companies c ON c.id=u.company_id LEFT JOIN company_subscriptions s ON s.company_id=c.id AND s.status="active" WHERE u.email=? AND u.status="active" AND u.deleted=0 LIMIT 1'); $q->execute([$email]); $u=$q->fetch(); if(!$u || !password_verify($password,$u['password_hash'])) return false; session_regenerate_id(true); unset($u['password_hash']); $_SESSION['user']=$u; db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]); return true; }
 public static function logout(): void { $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly']]); } session_destroy(); }
}
