<?php
declare(strict_types=1);

namespace App\Support;

final class OficioContenido
{
    public static function asuntoBase(string $texto): string
    {
        return trim(preg_replace('/(?:^|\R)Rango solicitado:\s*entre las\s*\d{2}:\d{2}\s*hasta las\s*\d{2}:\d{2}\.?/iu', '', $texto) ?? $texto);
    }

    public static function rango(string $texto): array
    {
        if (preg_match('/Rango solicitado:\s*entre las\s*(\d{2}:\d{2})\s*hasta las\s*(\d{2}:\d{2})/iu', $texto, $matches)) {
            return [$matches[1], $matches[2]];
        }
        return ['', ''];
    }

    public static function componer(array $oficio): string
    {
        $base = self::asuntoBase((string) ($oficio['motivo'] ?? ''));
        $categoria = self::normalizar((string) (($oficio['categoria'] ?? '') . ' ' . ($oficio['asunto_nombre'] ?? '')));
        $persona = trim((string) ($oficio['persona_nombre'] ?? ''));
        if ($persona === '') {
            $persona = trim(implode(' ', array_filter([
                $oficio['fall_nombres'] ?? '', $oficio['fall_ap'] ?? '', $oficio['fall_am'] ?? '',
            ], static fn($value) => trim((string) $value) !== '')));
        }
        $placa = trim((string) ($oficio['veh_placa'] ?? ''));

        if ($base === '') {
            $base = str_contains($categoria, 'camara')
                ? 'Copia de grabaciones de cámaras de video vigilancia'
                : trim((string) (($oficio['asunto_nombre'] ?? '') ?: ($oficio['categoria'] ?? '')));
        }

        if (str_contains($categoria, 'necropsia') || str_contains($categoria, 'autopsia')) {
            if ($persona !== '') {
                if (str_starts_with(self::normalizar($base), 'solicita protocolo de necropsia')) {
                    $base = 'Protocolo de necropsia';
                }
                $base = preg_replace('/,?\s*por motivo que se indica\.?$/iu', '', $base) ?? $base;
                $base = rtrim($base, ' .,;') . ' de Q.E.V.F. ' . $persona;
            }
        } elseif (str_contains($categoria, 'peritaje') && $placa !== '') {
            if (!preg_match('/\bplaca\b/iu', $base)) {
                $base = preg_replace('/\bveh[ií]culo\b/iu', 'vehículo de placa ' . $placa, $base, 1, $count) ?? $base;
                if (!$count) {
                    $base = rtrim($base, ' .,;') . ' en vehículo de placa ' . $placa;
                }
            }
        } elseif (str_contains($categoria, 'camara') && (str_contains($categoria, 'video') || str_contains($categoria, 'vigilancia'))) {
            [$desde, $hasta] = self::rango((string) ($oficio['motivo'] ?? ''));
            $fecha = (string) ($oficio['fecha_accidente'] ?? '');
            if (preg_match('/(?:^|\R)Día solicitado:\s*(\d{4}-\d{2}-\d{2})/u', (string) ($oficio['motivo'] ?? ''), $matches)) {
                $fecha = $matches[1];
            }
            $base = 'Grabación de cámara de video vigilancia';
            if ($fecha !== '' && ($stamp = strtotime($fecha)) !== false) {
                $meses = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SET','OCT','NOV','DIC'];
                $base .= ', del día ' . date('d', $stamp) . $meses[(int)date('n', $stamp)-1] . date('Y', $stamp);
            }
            if ($desde !== '' && $hasta !== '') {
                $base .= ' entre las ' . $desde . ' y las ' . $hasta . ' horas';
            }
            $base .= ', por motivo que se indica. SOLICITA';
        }

        return trim($base);
    }

    private static function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    }
}
