-- Rode UMA vez no phpMyAdmin do banco já existente, ANTES de subir o código novo.
-- Perfil do usuário: foto, cargo e telefone.
ALTER TABLE users
  ADD COLUMN avatar VARCHAR(120) NULL AFTER email,
  ADD COLUMN job_title VARCHAR(80) NULL AFTER avatar,
  ADD COLUMN phone VARCHAR(40) NULL AFTER job_title;
