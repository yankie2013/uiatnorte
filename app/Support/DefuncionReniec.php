<?php
declare(strict_types=1);
namespace App\Support;

final class DefuncionReniec
{
    public const FIELDS = [
        'nombre_padre' => 'Nombre completo del padre',
        'nombre_madre' => 'Nombre completo de la madre',
        'lugar_fallecimiento' => 'Lugar de fallecimiento',
        'fecha_fallecimiento' => 'Fecha de fallecimiento',
        'hora_fallecimiento' => 'Hora de fallecimiento',
        'causa_muerte' => 'Causa de la muerte',
        'agente_causante' => 'Agente causante',
    ];
    public static function matches(string $text): bool
    {
        $text = strtr(mb_strtolower($text, 'UTF-8'), ['ó'=>'o']);
        return str_contains($text, 'defuncion') && str_contains($text, 'reniec');
    }
    public static function validate(mixed $input): array
    {
        if (!is_array($input)) throw new \InvalidArgumentException('Completa los datos de la morgue.');
        $values = [];
        foreach (self::FIELDS as $key => $label) {
            $value = $input[$key] ?? '';
            if (!is_string($value) || trim($value) === '' || mb_strlen($value) > 4000) throw new \InvalidArgumentException("Completa {$label} según los documentos de la morgue (máximo 4000 caracteres).");
            $values[$key] = trim($value);
        }
        $adjuntos = $input['adjuntos'] ?? '';
        if (!is_string($adjuntos) || mb_strlen($adjuntos) > 4000) throw new \InvalidArgumentException('Los adjuntos admiten hasta 4000 caracteres.');
        $values['adjuntos'] = trim($adjuntos);
        // Keep the date and time wording exactly as supplied by the morgue.
        return $values;
    }
}
