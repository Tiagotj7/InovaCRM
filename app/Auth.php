<?php
declare(strict_types=1);

final class Auth {
    private const LOGIN_WINDOW_MINUTES = 15;
    private const MAX_EMAIL_ATTEMPTS = 5;
    private const MAX_IP_ATTEMPTS = 20;

    private static function loginHashes(string $email): array {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return [hash('sha256', strtolower(trim($email))), hash('sha256', $ip)];
    }

    private static function loginBlocked(string $emailHash, string $ipHash): bool {
        $q = db()->prepare(
            'SELECT SUM(email_hash = ?) AS email_attempts, SUM(ip_hash = ?) AS ip_attempts '
            . 'FROM login_attempts WHERE attempted_at >= NOW() - INTERVAL ' . self::LOGIN_WINDOW_MINUTES . ' MINUTE'
        );
        $q->execute([$emailHash, $ipHash]);
        $counts = $q->fetch() ?: [];
        return (int)($counts['email_attempts'] ?? 0) >= self::MAX_EMAIL_ATTEMPTS
            || (int)($counts['ip_attempts'] ?? 0) >= self::MAX_IP_ATTEMPTS;
    }

    private static function recordLoginFailure(string $emailHash, string $ipHash): void {
        if (random_int(1, 100) === 1) {
            db()->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 30 DAY');
        }
        $q = db()->prepare('INSERT INTO login_attempts(email_hash, ip_hash) VALUES(?, ?)');
        $q->execute([$emailHash, $ipHash]);
    }

    public static function login(string $email, string $password): bool {
        [$emailHash, $ipHash] = self::loginHashes($email);
        if (self::loginBlocked($emailHash, $ipHash)) return false;

        $q = db()->prepare('SELECT u.*,c.name company_name,c.status company_status,s.plan_code FROM users u JOIN companies c ON c.id=u.company_id LEFT JOIN company_subscriptions s ON s.company_id=c.id AND s.status="active" WHERE u.email=? AND u.status="active" AND u.deleted=0 LIMIT 1');
        $q->execute([strtolower(trim($email))]);
        $u = $q->fetch();
        if (!$u || !password_verify($password, (string)$u['password_hash'])) {
            self::recordLoginFailure($emailHash, $ipHash);
            return false;
        }

        session_regenerate_id(true);
        unset($u['password_hash']);
        $_SESSION['user'] = $u;
        $_SESSION['last_activity'] = time();
        db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]);
        db()->prepare('DELETE FROM login_attempts WHERE email_hash=?')->execute([$emailHash]);
        return true;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }
}
