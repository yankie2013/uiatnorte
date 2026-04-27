<?php
declare(strict_types=1);

if (!function_exists('uiat_filename_part')) {
    function uiat_filename_part(mixed $value, string $fallback = ''): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            $text = $fallback;
        }
        $text = strtr($text, [
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I', 'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U', 'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'Ñ' => 'N', 'ñ' => 'n',
        ]);
        $text = preg_replace('/[^A-Za-z0-9]+/', '_', $text);
        $text = trim((string) $text, '_');
        return $text !== '' ? $text : uiat_filename_part($fallback !== '' ? $fallback : 'sin_nombre');
    }
}

if (!function_exists('uiat_docx_filename')) {
    function uiat_docx_filename(array $parts, string $fallback): string
    {
        $clean = [];
        foreach ($parts as $part) {
            $part = uiat_filename_part($part);
            if ($part !== '' && strtolower($part) !== 'sin_nombre') {
                $clean[] = $part;
            }
        }
        $name = $clean !== [] ? implode('_', $clean) : uiat_filename_part($fallback, 'documento');
        return $name . '.docx';
    }
}

if (!function_exists('uiat_person_surnames')) {
    function uiat_person_surnames(array $person): string
    {
        $surnames = trim(
            (string) ($person['apellido_paterno'] ?? $person['fam_apellido_paterno'] ?? '') . ' ' .
            (string) ($person['apellido_materno'] ?? $person['fam_apellido_materno'] ?? '')
        );
        if ($surnames !== '') {
            return $surnames;
        }
        return trim((string) ($person['apellidos'] ?? $person['CONDUCTOR_APELLIDOS'] ?? ''));
    }
}

if (!function_exists('uiat_manifestacion_filename')) {
    function uiat_manifestacion_filename(string $calidad, array $person, string $fallback = 'manifestacion'): string
    {
        return uiat_docx_filename([$calidad, uiat_person_surnames($person)], $fallback);
    }
}

if (!function_exists('uiat_vehicle_report_filename')) {
    function uiat_vehicle_report_filename(string $unidad, string $estado, array $vehicle, array $driver, string $fallback): string
    {
        $unidad = $unidad !== '' ? $unidad : (string) ($vehicle['orden_participacion'] ?? '');
        $placa = trim((string) ($vehicle['placa'] ?? ''));
        if ($placa === '') {
            $placa = 'Sin_Placa';
        }
        return uiat_docx_filename([$unidad, $estado, $placa, uiat_person_surnames($driver)], $fallback);
    }
}
