<?php
declare(strict_types=1);
require __DIR__.'/../config/bootstrap.php';
$name=$argv[1]??'Administrador'; $email=$argv[2]??'admin@inova.local'; $password=$argv[3]??'';
if(!$password){fwrite(STDERR,"Uso: php database/seed.php "Nome" email senha
");exit(1);}
$db=db();$db->beginTransaction();$db->prepare('INSERT INTO companies(name,owner_name,email) VALUES(?,?,?)')->execute(['Inova CRM Admin',$name,$email]);$cid=(int)$db->lastInsertId();$db->prepare('INSERT INTO company_subscriptions(company_id,plan_code) VALUES(?,?)')->execute([$cid,'premium']);$db->prepare('INSERT INTO users(company_id,name,email,password_hash,role) VALUES(?,?,?,?,?)')->execute([$cid,$name,$email,password_hash($password,PASSWORD_DEFAULT),'global_admin']);$db->commit();echo "Administrador criado com sucesso.
";
