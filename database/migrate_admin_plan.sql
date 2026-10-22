-- Rode UMA vez no phpMyAdmin do banco já existente.
-- Cria o perfil "Administrador geral" (sem limites, fora dos planos de venda)
-- e move para ele a empresa do(s) administrador(es) global(is), que estava como Premium.
INSERT IGNORE INTO plans(code,name,price_cents,customer_limit,deal_limit,user_limit,task_limit)
VALUES ('admin','Administrador geral',0,4294967295,4294967295,4294967295,4294967295);

UPDATE company_subscriptions s
JOIN users u ON u.company_id = s.company_id AND u.role = 'global_admin'
SET s.plan_code = 'admin';
