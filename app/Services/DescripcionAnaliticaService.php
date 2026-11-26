<?php
declare(strict_types=1);
namespace App\Services;
use InvalidArgumentException;
final class DescripcionAnaliticaService
{
    public static function validate(array $data): array
    {
        $text = static function ($value): string {
            if (!is_scalar($value) || mb_strlen((string) $value) > 20000) throw new InvalidArgumentException('Campo no válido (máximo 20 000 caracteres).');
            return trim((string) $value);
        };
        $result = [];
        $inspection = $data['inspeccion'] ?? [];
        if (!is_array($inspection)) throw new InvalidArgumentException('Inspección no válida.');
        foreach (['fecha','hora','lugar','llegada','metodo','perennizacion','clima'] as $key) $result['inspeccion'][$key] = $text($inspection[$key] ?? '');
        $schemas = [
            'vias' => ['nombre','descripcion','configuracion','material','control','ordenamiento','maniobrabilidad','iluminacion','visibilidad','intensidad','fluidez','medidas','observaciones','fotografia','pie'],
            'consideraciones' => ['texto'], 'aproximaciones' => ['titulo','descripcion'], 'posiciones' => ['unidad','ubicacion'],
        ];
        foreach ($schemas as $key => $fields) {
            $items = $data[$key] ?? [];
            if (!is_array($items) || !array_is_list($items) || count($items) > 200) throw new InvalidArgumentException('Lista no válida (máximo 200 elementos).');
            $result[$key] = [];
            foreach ($items as $row) {
                if (!is_array($row)) throw new InvalidArgumentException('Registro no válido.');
                $item = [];
                foreach ($fields as $field) $item[$field] = $text($row[$field] ?? '');
                if (isset($item['fotografia']) && $item['fotografia'] !== '' && (str_contains($item['fotografia'], '../') || !preg_match('~^(?:https?://[^\s]+|uploads/[a-zA-Z0-9_./-]+)$~D', $item['fotografia']))) throw new InvalidArgumentException('La fotografía debe ser una URL HTTP/HTTPS o una ruta de uploads.');
                if (array_filter($item, static fn($value) => $value !== '')) $result[$key][] = $item;
            }
        }
        return $result;
    }
}
