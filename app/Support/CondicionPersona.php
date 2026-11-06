<?php
declare(strict_types=1);
namespace App\Support;

/** Shared vocabulary; persistence keeps the existing three database enum values. */
final class CondicionPersona
{
    private const EQUIVALENCIAS = [
        'herido'=>'Herido', 'herida'=>'Herido', 'lesionado'=>'Herido', 'lesionada'=>'Herido',
        'fallecido'=>'Fallecido', 'fallecida'=>'Fallecido', 'occiso'=>'Fallecido', 'occisa'=>'Fallecido',
        'ileso'=>'Ileso', 'ilesa'=>'Ileso',
    ];

    private static function clave(mixed $value): string
    {
        return is_string($value) ? mb_strtolower(trim($value), 'UTF-8') : '';
    }

    public static function normalizar(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) return null;
        $canonical = self::EQUIVALENCIAS[self::clave($value)] ?? null;
        if ($canonical === null) throw new \InvalidArgumentException('Condición de la persona inválida: selecciona ileso, lesionado/herido o fallecido/occiso.');
        return $canonical;
    }

    public static function esHerido(mixed $value): bool { return (self::EQUIVALENCIAS[self::clave($value)] ?? null) === 'Herido'; }
    public static function esFallecido(mixed $value): bool { return (self::EQUIVALENCIAS[self::clave($value)] ?? null) === 'Fallecido'; }
    public static function esIleso(mixed $value): bool { return (self::EQUIVALENCIAS[self::clave($value)] ?? null) === 'Ileso'; }
}
