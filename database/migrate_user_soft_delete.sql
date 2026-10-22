-- Rode UMA vez no phpMyAdmin do banco já existente, ANTES de subir o código novo.
-- Exclusão de usuário passa a ser "lógica": a coluna deleted vai de 0 (normal) para 1 (excluído).
-- O registro continua no banco (histórico/auditoria), mas o usuário some da lista e não consegue mais entrar.
ALTER TABLE users
  ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN deleted_at DATETIME NULL AFTER deleted,
  ADD INDEX idx_users_deleted (deleted);

-- Para desfazer a exclusão de um usuário (de 1 volta para 0):
-- UPDATE users SET deleted=0, deleted_at=NULL, status='active' WHERE id=123;
