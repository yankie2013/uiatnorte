<?php
require_once __DIR__ . '/bootstrap/app.php';

use App\Support\Auth;

Auth::startSession();

if (!function_exists('require_login')) {
    function require_login(): void
    {
        Auth::requireLogin();
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('require_role')) {
    function require_role(string $role): void
    {
        Auth::requireRole($role);
    }
}
