<?php
declare(strict_types=1);

namespace App\Support;

final class RegistrationReturn
{
    public static function resolve(string $value, string $fallback): string
    {
        $value = trim($value);
        // Registration flows must never lead to administrative listings.
        if ($value === '' || preg_match('/[\x00-\x20\\\\]/', $value)
            || !preg_match('/^[a-zA-Z0-9_-]+\.php(?:[?#].*)?$/D', $value)) {
            return $fallback;
        }
        $path = (string) parse_url($value, PHP_URL_PATH);
        if (str_contains($path, '_listar.php')) {
            return $fallback;
        }
        return $value;
    }
}
