-- Importe no banco existente antes de publicar a versão com rate limit de login.
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_email_time (email_hash, attempted_at),
    INDEX idx_login_attempts_ip_time (ip_hash, attempted_at),
    INDEX idx_login_attempts_attempted_at (attempted_at)
);
