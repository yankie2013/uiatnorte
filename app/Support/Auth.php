<?php
declare(strict_types=1);

namespace App\Support;

final class Auth
{
    public static function startSession(): void
    {
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
}
