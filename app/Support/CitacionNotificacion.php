<?php
declare(strict_types=1);
namespace App\Support;

final class CitacionNotificacion
{
    public static function hash(array $citacion): string
    {
        $values = [];
        foreach (['fecha', 'hora', 'lugar', 'motivo', 'tipo_diligencia', 'persona_nombres', 'persona_apep', 'persona_apem', 'en_calidad'] as $key) {
            $values[] = trim((string) ($citacion[$key] ?? ''));
        }
        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
