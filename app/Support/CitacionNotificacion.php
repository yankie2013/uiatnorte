<?php
declare(strict_types=1);
namespace App\Support;

final class CitacionNotificacion
{
    public static function forLawyer(array $citations, int $personId): array
    {
        $represented = array_values(array_filter($citations, static fn(array $citation): bool => $personId > 0 && (int) ($citation['persona_id'] ?? 0) === $personId));
        return $represented ?: $citations;
    }

    public static function isNotified(array $citations, array $notifications): bool
    {
        if ($citations === []) return false;
        foreach ($citations as $citation) {
            $notification = $notifications[(int) $citation['id']] ?? null;
            if (!$notification || !hash_equals($notification['contenido_hash'], self::hash($citation))) return false;
        }
        return true;
    }

    public static function hash(array $citacion): string
    {
        $values = [];
        foreach (['fecha', 'hora', 'lugar', 'motivo', 'tipo_diligencia', 'persona_nombres', 'persona_apep', 'persona_apem', 'en_calidad'] as $key) {
            $values[] = trim((string) ($citacion[$key] ?? ''));
        }
        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
