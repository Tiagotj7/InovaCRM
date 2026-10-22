-- Cria o primeiro administrador direto no phpMyAdmin (hospedagem sem SSH).
-- 1) Gere o hash da senha no seu computador:
--      php -r "echo password_hash('SUA_SENHA_LONGA', PASSWORD_DEFAULT);"
-- 2) Troque NOME, EMAIL e HASH_AQUI abaixo e execute na aba SQL do banco.
INSERT INTO companies (name, owner_name, email) VALUES ('Inova CRM Admin', 'NOME', 'EMAIL');
SET @cid = LAST_INSERT_ID();
INSERT INTO company_subscriptions (company_id, plan_code) VALUES (@cid, 'admin');
INSERT INTO users (company_id, name, email, password_hash, role) VALUES (@cid, 'NOME', 'EMAIL', 'HASH_AQUI', 'global_admin');
