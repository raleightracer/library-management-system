<?php
declare(strict_types=1);

final class Auth
{
    private const SESSION_KEY = 'quadbyte_lms_user';
    private const COOKIE_NAME = 'quadbyte_lms_remember';
    private const CSRF_KEY = 'quadbyte_lms_csrf_token';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('QUADBYTE_LMS');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => self::shouldUseSecureCookie(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function user(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function userId(): ?int
    {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role_slug'] ?? null) === 'admin';
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION[self::CSRF_KEY]) || !is_string($_SESSION[self::CSRF_KEY])) {
            self::rotateCsrfToken();
        }

        return (string)$_SESSION[self::CSRF_KEY];
    }

    public static function rotateCsrfToken(): string
    {
        $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        return (string)$_SESSION[self::CSRF_KEY];
    }

    public static function requireCsrfToken(array $input = []): void
    {
        self::requireLogin();

        $expected = $_SESSION[self::CSRF_KEY] ?? '';
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['_csrf'] ?? '');

        if (!is_string($expected) || $expected === '' || !is_string($provided) || $provided === '') {
            Response::error('Invalid security token. Please refresh and try again.', 403);
        }

        if (!hash_equals($expected, $provided)) {
            Response::error('Invalid security token. Please refresh and try again.', 403);
        }
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            Response::error('Authentication required.', 401);
        }

        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if (($user['role_slug'] ?? null) !== 'admin') {
            Response::error('Admin access required.', 403);
        }

        return $user;
    }

    public static function requireMember(): array
    {
        $user = self::requireLogin();
        if (($user['role_slug'] ?? null) !== 'member') {
            Response::error('Member access required.', 403);
        }

        return $user;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $roleSlug = ($user['role_slug'] ?? '') === 'staff' ? 'member' : $user['role_slug'];
        $_SESSION[self::SESSION_KEY] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'avatar_url' => $user['avatar_url'] ?? null,
            'role_slug' => $roleSlug,
            'member_id' => isset($user['member_id']) ? (int)$user['member_id'] : null,
        ];
        self::rotateCsrfToken();
    }

    public static function logout(): void
    {
        $db = Database::connection();
        if (self::userId()) {
            $stmt = $db->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
            $stmt->execute(['user_id' => self::userId()]);
        }

        self::forgetRememberCookie();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }

    public static function createRememberToken(int $userId): void
    {
        $config = Database::config()['app'];
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('now'))->modify('+' . (int)$config['remember_days'] . ' days');

        $stmt = Database::connection()->prepare(
            'INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at)
             VALUES (:user_id, :selector, :validator_hash, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        setcookie(self::COOKIE_NAME, $selector . ':' . $validator, [
            'expires' => $expiresAt->getTimestamp(),
            'path' => '/',
            'secure' => self::shouldUseSecureCookie(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function loginFromRememberCookie(): void
    {
        if (self::user()) {
            return;
        }

        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!str_contains($cookie, ':')) {
            return;
        }

        [$selector, $validator] = explode(':', $cookie, 2);
        if ($selector === '' || $validator === '') {
            return;
        }

        $stmt = Database::connection()->prepare(
            'SELECT rt.*, u.*, r.slug AS role_slug, m.id AS member_id, m.status AS member_status
             FROM remember_tokens rt
             INNER JOIN users u ON u.id = rt.user_id
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN members m ON m.user_id = u.id AND m.deleted_at IS NULL
             WHERE rt.selector = :selector AND rt.expires_at > NOW() AND u.deleted_at IS NULL
               AND u.status IN ("active", "approved")
               AND (r.slug = "admin" OR m.status IN ("active", "approved"))
             LIMIT 1'
        );
        $stmt->execute(['selector' => $selector]);
        $row = $stmt->fetch();

        if (!$row || !hash_equals((string)$row['validator_hash'], hash('sha256', $validator))) {
            self::forgetRememberCookie();
            return;
        }

        self::login($row);
    }

    private static function forgetRememberCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => self::shouldUseSecureCookie(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function shouldUseSecureCookie(): bool
    {
        $app = Database::config()['app'] ?? [];
        $setting = strtolower((string)($app['cookie_secure'] ?? 'auto'));
        if (in_array($setting, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($setting, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    }
}
