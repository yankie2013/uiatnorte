<?php
// Legacy bridge for endpoints that still include this file directly.

declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

$baseUrl = trim((string) app_config('services.seeker.base_url', 'https://seeker.red'));
$token = trim((string) app_config('services.seeker.token', ''));
$dniUrl = trim((string) app_config('services.seeker.dni_url', $baseUrl . '/personas/apiPremium/dni'));
$placaUrl = trim((string) app_config('services.seeker.placa_url', $baseUrl . '/vehiculos/api_newPlacas'));

if (!defined('API_TOKEN')) {
    define('API_TOKEN', $token);
}

if (!defined('API_DNI_URL')) {
    define('API_DNI_URL', $dniUrl);
}

if (!defined('API_PLACA_URL')) {
    define('API_PLACA_URL', $placaUrl);
}

function assert_token(): void
{
    if (!defined('API_TOKEN') || trim((string) API_TOKEN) === '') {
        throw new Exception('Configura SEEKER_TOKEN en .env.local o en las variables de entorno.');
    }
}

function curl_json(string $url, array $opts = []): array
{
    $ch = curl_init($url);

    $defaults = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    foreach ($opts as $key => $value) {
        $defaults[$key] = $value;
    }

    curl_setopt_array($ch, $defaults);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    curl_close($ch);

    if ($err) {
        throw new Exception('Error cURL: ' . $err);
    }

    $trim = ltrim((string) $raw);
    $isHtml = (stripos($ctype, 'text/html') !== false)
        || (strpos($trim, '<!DOCTYPE') === 0)
        || (strpos($trim, '<html') === 0);

    $json = null;
    if (!$isHtml) {
        $json = json_decode((string) $raw, true);
    }

    return [
        'http' => $http,
        'content_type' => $ctype,
        'is_html' => $isHtml,
        'raw' => (string) $raw,
        'json' => $json,
    ];
}

function consultar_dni(string $dni): array
{
    $dni = trim($dni);
    if (!preg_match('/^[0-9]{8}$/', $dni)) {
        throw new Exception('El DNI debe tener 8 digitos.');
    }

    assert_token();

    $res = curl_json(API_DNI_URL, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . API_TOKEN,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => http_build_query(['dni' => $dni]),
    ]);

    if ($res['http'] >= 400) {
        throw new Exception('Error HTTP ' . $res['http'] . ': ' . $res['raw']);
    }

    if ($res['is_html']) {
        throw new Exception('API devolvio HTML (login). Revisa token o bloqueo.');
    }

    if (!is_array($res['json'])) {
        throw new Exception('Respuesta invalida de la API DNI: ' . substr($res['raw'], 0, 300));
    }

    return $res['json'];
}

function consultar_placa(string $placa): array
{
    $placa = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', trim($placa)));
    if ($placa === '' || strlen($placa) < 5) {
        throw new Exception('Placa invalida.');
    }

    assert_token();

    $url = API_PLACA_URL . '?placa=' . urlencode($placa) . '&token=' . urlencode(API_TOKEN);
    $res = curl_json($url, [
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    if ($res['http'] >= 400) {
        throw new Exception('Error HTTP ' . $res['http'] . ': ' . $res['raw']);
    }

    if ($res['is_html']) {
        throw new Exception('API devolvio HTML (login). Revisa token o bloqueo.');
    }

    if (!is_array($res['json'])) {
        throw new Exception('Respuesta invalida de la API PLACA: ' . substr($res['raw'], 0, 300));
    }

    return $res['json'];
}
