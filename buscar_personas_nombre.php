<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/auth.php';
require __DIR__ . '/config_api.php';

function out_nombre(bool $ok, array $data = [], string $msg = '', int $status = 200): never
{
    http_response_code($status);
    echo json_encode([
        'ok' => $ok,
        'data' => $data,
        'msg' => $msg,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function clean_nombre(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    return preg_replace('/\s+/u', ' ', $text) ?: $text;
}

function upper_nombre(mixed $value): string
{
    $text = clean_nombre($value);
    return function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
}

function key_nombre(string $key): string
{
    $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
    $key = strtr($key, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n',
        'Ã¡' => 'a',
        'Ã©' => 'e',
        'Ã­' => 'i',
        'Ã³' => 'o',
        'Ãº' => 'u',
        'Ã¼' => 'u',
        'Ã±' => 'n',
    ]);
    return preg_replace('/[^a-z0-9]/', '', $key) ?: $key;
}

function first_scalar_nombre(array $row, array $keys): ?string
{
    $wanted = array_flip(array_map('key_nombre', $keys));

    foreach ($row as $key => $value) {
        if (isset($wanted[key_nombre((string) $key)]) && is_scalar($value) && trim((string) $value) !== '') {
            return clean_nombre($value);
        }
    }

    foreach ($row as $value) {
        if (is_array($value)) {
            $found = first_scalar_nombre($value, $keys);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function first_scalar_shallow_nombre(array $row, array $keys): ?string
{
    $wanted = array_flip(array_map('key_nombre', $keys));

    foreach ($row as $key => $value) {
        if (isset($wanted[key_nombre((string) $key)]) && is_scalar($value) && trim((string) $value) !== '') {
            return clean_nombre($value);
        }
    }

    return null;
}

function collect_rows_nombre(mixed $node, array &$rows): void
{
    if (!is_array($node)) {
        return;
    }

    $dni = first_scalar_shallow_nombre($node, ['dni', 'documento', 'num_doc', 'numero_documento', 'numero']);
    if ($dni !== null && preg_match('/\b(\d{8})\b/', $dni)) {
        $rows[] = $node;
        return;
    }

    foreach ($node as $value) {
        collect_rows_nombre($value, $rows);
    }
}

function normalize_resultado_nombre(array $row): ?array
{
    $dniRaw = first_scalar_nombre($row, ['dni', 'documento', 'num_doc', 'numero_documento', 'numero']);
    if ($dniRaw === null || !preg_match('/\b(\d{8})\b/', $dniRaw, $matches)) {
        return null;
    }

    $dni = $matches[1];
    $nombres = first_scalar_nombre($row, ['nombres', 'nombre', 'prenombres']);
    $paterno = first_scalar_nombre($row, ['paterno', 'apellido_paterno', 'ap_paterno', 'ape_paterno']);
    $materno = first_scalar_nombre($row, ['materno', 'apellido_materno', 'ap_materno', 'ape_materno']);
    $edad = first_scalar_nombre($row, ['edad']);

    return [
        'dni' => $dni,
        'nombres' => upper_nombre($nombres),
        'paterno' => upper_nombre($paterno),
        'materno' => upper_nombre($materno),
        'edad' => $edad !== null ? preg_replace('/\D+/', '', $edad) : '',
        'nombre_completo' => trim(implode(' ', array_filter([
            upper_nombre($paterno),
            upper_nombre($materno),
            upper_nombre($nombres),
        ]))),
    ];
}

function safe_error_nombre(string $message): string
{
    $lower = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);

    if (str_contains($lower, 'token') || str_contains($lower, 'authorization')) {
        return 'No se pudo consultar la API. Revisa la configuracion del token.';
    }

    if (str_contains($lower, 'html') || str_contains($lower, 'respuesta invalida')) {
        return 'La API no devolvio una respuesta valida. Revisa token o bloqueo.';
    }

    if (str_contains($lower, 'http')) {
        return 'La API devolvio un error. Intenta nuevamente.';
    }

    return $message !== '' ? $message : 'No se pudo buscar por nombres.';
}

if (!current_user()) {
    out_nombre(false, [], 'Sesion vencida. Inicia sesion nuevamente.', 401);
}

try {
    $nombres = clean_nombre($_POST['nombres'] ?? $_GET['nombres'] ?? '');
    $paterno = clean_nombre($_POST['paterno'] ?? $_GET['paterno'] ?? '');
    $materno = clean_nombre($_POST['materno'] ?? $_GET['materno'] ?? '');
    $edadMin = preg_replace('/\D+/', '', (string) ($_POST['edadMin'] ?? $_GET['edadMin'] ?? ''));
    $edadMax = preg_replace('/\D+/', '', (string) ($_POST['edadMax'] ?? $_GET['edadMax'] ?? ''));

    $json = consultar_personas_por_nombre($nombres, $paterno, $materno, $edadMin, $edadMax);

    $rows = [];
    collect_rows_nombre($json['data'] ?? $json, $rows);

    $resultados = [];
    $seen = [];
    foreach ($rows as $row) {
        $item = normalize_resultado_nombre($row);
        if ($item === null || isset($seen[$item['dni']])) {
            continue;
        }
        $seen[$item['dni']] = true;
        $resultados[] = $item;
    }

    out_nombre(true, [
        'resultados' => $resultados,
        'total' => count($resultados),
    ]);
} catch (Throwable $e) {
    error_log('buscar_personas_nombre.php ERROR: ' . $e->getMessage());
    out_nombre(false, [], safe_error_nombre($e->getMessage()), 400);
}
