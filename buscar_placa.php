<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config_seeker.php';

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function seeker_base_url(): string
{
    if (defined('API_BASE_URL')) {
        return trim((string) constant('API_BASE_URL'));
    }

    if (defined('SEEKER_BASE')) {
        return trim((string) constant('SEEKER_BASE'));
    }

    $value = $GLOBALS['SEEKER_BASE'] ?? '';
    return is_string($value) ? trim($value) : '';
}

function seeker_token_value(): string
{
    if (defined('API_TOKEN')) {
        return trim((string) constant('API_TOKEN'));
    }

    if (defined('SEEKER_TOKEN')) {
        return trim((string) constant('SEEKER_TOKEN'));
    }

    $value = $GLOBALS['SEEKER_TOKEN'] ?? '';
    return is_string($value) ? trim($value) : '';
}

function sanitize_placa(string $placa): string
{
    return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim($placa)));
}

function seeker_cookie_file(): string
{
    $dir = __DIR__ . '/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir . '/seeker_cookie_jar.txt';
}

function seeker_decode_json(string $body): ?array
{
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function seeker_provider_error(array $json, int $httpCode): ?array
{
    $code = strtoupper(trim((string) ($json['code'] ?? '')));
    $status = strtolower(trim((string) ($json['status'] ?? '')));
    $message = trim((string) ($json['message'] ?? $json['error'] ?? ''));

    if ($code === 'INVALID_TOKEN') {
        return [
            'ok' => false,
            'status' => 503,
            'error' => 'El acceso API de placas no esta habilitado o el token es invalido.',
            'log' => 'Proveedor devolvio INVALID_TOKEN. message=' . $message,
        ];
    }

    if ($status !== '' && $status !== 'success') {
        return [
            'ok' => false,
            'status' => $httpCode >= 400 ? $httpCode : 502,
            'error' => $message !== '' ? $message : 'El servicio de placas devolvio un estado no valido.',
            'log' => 'Proveedor devolvio status=' . $status . ' body=' . substr(json_encode($json, JSON_UNESCAPED_UNICODE), 0, 400),
        ];
    }

    if ($httpCode >= 400) {
        return [
            'ok' => false,
            'status' => $httpCode,
            'error' => $message !== '' ? $message : 'No se pudo consultar la placa en este momento.',
            'log' => 'HTTP ' . $httpCode . ' body=' . substr(json_encode($json, JSON_UNESCAPED_UNICODE), 0, 400),
        ];
    }

    return null;
}

function seeker_get_browser(string $placa): array
{
    $baseUrl = seeker_base_url();
    $token = seeker_token_value();

    if ($baseUrl === '' || $token === '') {
        return [
            'ok' => false,
            'status' => 503,
            'error' => 'El servicio de placas no esta disponible en este momento.',
            'log' => 'Falta configuracion SEEKER_BASE/API_BASE_URL o SEEKER_TOKEN/API_TOKEN.',
        ];
    }

    $url = rtrim($baseUrl, '/') . '/vehiculos/api_newPlacas?placa=' . urlencode($placa) . '&token=' . urlencode($token);
    $cookieFile = seeker_cookie_file();
    if (!file_exists($cookieFile)) {
        @file_put_contents($cookieFile, '');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json,text/plain,*/*',
            'Accept-Language: es-PE,es;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Referer: https://seeker.red/',
            'Origin: https://seeker.red',
            'Connection: keep-alive',
            'DNT: 1',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Dest: empty',
            'X-Requested-With: XMLHttpRequest',
        ],
    ]);

    $body = curl_exec($ch);
    if ($body === false) {
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'ok' => false,
            'status' => 502,
            'error' => 'No se pudo consultar la placa en este momento.',
            'log' => sprintf('cURL error (%d): %s', $errno, $error),
        ];
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $json = seeker_decode_json($body);
    if ($json === null) {
        return [
            'ok' => false,
            'status' => $httpCode >= 400 ? $httpCode : 502,
            'error' => 'El servicio de placas devolvio una respuesta invalida.',
            'log' => 'Respuesta no JSON. content_type=' . $contentType . ' body=' . substr(trim((string) $body), 0, 400),
        ];
    }

    if (stripos($contentType, 'application/json') === false) {
        error_log('buscar_placa.php WARN: content_type inesperado. HTTP ' . $httpCode . ' content_type=' . $contentType);
    }

    $providerError = seeker_provider_error($json, $httpCode);
    if ($providerError !== null) {
        return $providerError;
    }

    return [
        'ok' => true,
        'status' => $httpCode ?: 200,
        'json' => $json,
    ];
}

if (!current_user()) {
    json_out([
        'ok' => false,
        'error' => 'Sesion vencida. Inicia sesion nuevamente.',
    ], 401);
}

$placa = sanitize_placa((string) ($_GET['placa'] ?? ''));
if ($placa === '') {
    json_out([
        'ok' => false,
        'error' => 'Debes enviar la placa.',
    ], 400);
}

$result = seeker_get_browser($placa);
if (!$result['ok']) {
    if (!empty($result['log'])) {
        error_log('buscar_placa.php ERROR: ' . $result['log']);
    }

    json_out([
        'ok' => false,
        'placa' => $placa,
        'error' => $result['error'],
    ], (int) ($result['status'] ?? 500));
}

json_out([
    'ok' => true,
    'placa' => $placa,
    'respuesta' => $result['json'],
], (int) ($result['status'] ?? 200));