<?php
declare(strict_types=1);

namespace App\Support;

final class Auth
{
    private static bool $configured = false;

    public static function startSession(): void
    {
        self::configureSession();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function user(): ?array
    {
        self::startSession();
        $user = $_SESSION['user'] ?? null;
        return is_array($user) ? $user : null;
    }

    public static function requireLogin(string $redirectTo = 'login.php'): void
    {
        self::startSession();

        if (empty($_SESSION['user'])) {
            $_SESSION['flash'] = 'Inicia sesión';
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        $user = self::user();

        if (!$user || ($user['rol'] ?? '') !== $role) {
            http_response_code(403);
            exit('Acceso denegado');
        }
    }

    public static function logout(): void
    {
        self::startSession();

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
            session_destroy();
        }
    }

    private static function configureSession(): void
    {
        if (self::$configured || session_status() !== PHP_SESSION_NONE) {
            self::$configured = true;
            return;
        }

        self::$configured = true;

        $lifetime = max(86400, (int) app_config('session.lifetime', 31536000));
        $name = (string) app_config('session.name', 'UIATNORTESESSID');
        $savePath = (string) app_config('session.save_path', storage_path('sessions'));

        if ($savePath !== '' && (!is_dir($savePath) || is_writable($savePath))) {
            if (!is_dir($savePath)) {
                @mkdir($savePath, 0775, true);
            }

            if (is_dir($savePath) && is_writable($savePath)) {
                session_save_path($savePath);
            }
        }

        @ini_set('session.gc_maxlifetime', (string) $lifetime);
        @ini_set('session.cookie_lifetime', (string) $lifetime);
        @ini_set('session.use_strict_mode', '1');

        if ($name !== '') {
            session_name($name);
        }

        session_set_cookie_params(self::cookieOptions($lifetime));
    }

    private static function cookieOptions(int $lifetime): array
    {
        $secure = app_config('session.secure');
        if ($secure === null) {
            $secure = self::isHttps();
        }

        return [
            'lifetime' => $lifetime,
            'path' => (string) app_config('session.path', '/'),
            'domain' => (string) app_config('session.domain', ''),
            'secure' => (bool) $secure,
            'httponly' => (bool) app_config('session.http_only', true),
            'samesite' => (string) app_config('session.same_site', 'Lax'),
        ];
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
