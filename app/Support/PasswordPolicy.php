<?php
declare(strict_types=1);
namespace App\Support;
use InvalidArgumentException;
final class PasswordPolicy
{
    public static function validate(string $password, string $cip = ''): void
    {
        if (mb_strlen($password, 'UTF-8') < 10 || strlen($password) > 72
            || !preg_match('/\p{Lu}/u', $password) || !preg_match('/\p{Ll}/u', $password)
            || !preg_match('/[0-9]/', $password) || !preg_match('/[^\p{L}\p{N}\s]/u', $password)) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 10 caracteres, mayúscula, minúscula, número y carácter especial (máximo 72 bytes).');
        }
        if ($cip !== '' && hash_equals($cip, $password)) throw new InvalidArgumentException('La nueva contraseña debe ser distinta del CIP.');
    }
    public static function cip(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^[0-9]{1,30}$/D', $value)) throw new InvalidArgumentException('El CIP es obligatorio y debe contener solo números (máximo 30 dígitos).');
        return $value; // Conservar ceros iniciales.
    }
}
