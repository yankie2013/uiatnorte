<?php
declare(strict_types=1);

namespace App\Support;

final class Auth
{
    private static bool $configured = false;
    private const PENDING_REQUEST_KEY = 'auth_pending_request';
    private const RETURN_TO_KEY = 'auth_return_to';

    public static function startSession(): void
    {
        self::configureSession();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::refreshSessionCookie();
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
            self::rememberInterruptedRequest();
            $_SESSION['flash'] = !empty($_SESSION[self::PENDING_REQUEST_KEY])
                ? 'Tu sesion vencio al guardar. Inicia sesion para recuperar y confirmar los datos.'
                : 'Inicia sesion para continuar.';
            header('Location: ' . $redirectTo);
            exit;
        }

        $recoveryToken = trim((string) ($_POST['_uiat_recovery_token'] ?? ''));
        if ($recoveryToken !== '') {
            self::discardPendingRequest($recoveryToken);
            unset($_POST['_uiat_recovery_token']);
        }
    }

    public static function pendingRequest(): ?array
    {
        self::startSession();
        $pending = $_SESSION[self::PENDING_REQUEST_KEY] ?? null;
        if (!is_array($pending) || empty($pending['uri']) || empty($pending['token']) || !isset($pending['post'])) {
            return null;
        }
        if ((int) ($pending['captured_at'] ?? 0) < time() - 172800) {
            unset($_SESSION[self::PENDING_REQUEST_KEY]);
            return null;
        }
        return $pending;
    }

    public static function consumePendingRequest(string $token): ?array
    {
        $pending = self::pendingRequest();
        if ($pending === null || !hash_equals((string) $pending['token'], $token)) {
            return null;
        }
        unset($_SESSION[self::PENDING_REQUEST_KEY]);
        return $pending;
    }

    public static function discardPendingRequest(string $token): bool
    {
        $pending = self::pendingRequest();
        if ($pending === null || !hash_equals((string) $pending['token'], $token)) {
            return false;
        }
        unset($_SESSION[self::PENDING_REQUEST_KEY]);
        return true;
    }

    public static function postLoginDestination(): string
    {
        self::startSession();
        if (self::pendingRequest() !== null) {
            return 'session_resume.php';
        }

        $returnTo = self::safeLocalUri((string) ($_SESSION[self::RETURN_TO_KEY] ?? ''));
        unset($_SESSION[self::RETURN_TO_KEY]);
        return $returnTo !== '' ? $returnTo : 'index.php';
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

    private static function rememberInterruptedRequest(): void
    {
        $uri = self::safeLocalUri((string) ($_SERVER['REQUEST_URI'] ?? ''));
        if ($uri === '') {
            return;
        }

        $_SESSION[self::RETURN_TO_KEY] = $uri;
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || $_POST === []) {
            return;
        }

        $post = self::removeSensitiveFields($_POST);
        $encoded = json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > 2 * 1024 * 1024) {
            return;
        }

        $fileNames = [];
        foreach ($_FILES as $field => $file) {
            if (is_array($file) && !empty($file['name'])) {
                $names = is_array($file['name']) ? array_values(array_filter($file['name'])) : [(string) $file['name']];
                $fileNames[(string) $field] = $names;
            }
        }

        $_SESSION[self::PENDING_REQUEST_KEY] = [
            'token' => bin2hex(random_bytes(24)),
            'uri' => $uri,
            'post' => $post,
            'captured_at' => time(),
            'files' => $fileNames,
        ];
    }

    private static function removeSensitiveFields(array $values): array
    {
        $clean = [];
        foreach ($values as $key => $value) {
            if (preg_match('/pass(word)?|contrasena|clave/i', (string) $key)) {
                continue;
            }
            $clean[$key] = is_array($value) ? self::removeSensitiveFields($value) : $value;
        }
        return $clean;
    }

    private static function safeLocalUri(string $uri): string
    {
        $uri = trim(str_replace(["\r", "\n"], '', $uri));
        if ($uri === '' || str_starts_with($uri, '//')) {
            return '';
        }

        $parts = parse_url($uri);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return '';
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || !str_starts_with($path, '/')) {
            return '';
        }
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        return $path . $query;
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

    private static function refreshSessionCookie(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent() || session_id() === '') {
            return;
        }

        $lifetime = max(86400, (int) app_config('session.lifetime', 31536000));
        $options = self::cookieOptions($lifetime);
        $options['expires'] = time() + $lifetime;
        unset($options['lifetime']);

        setcookie(session_name(), session_id(), $options);
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
