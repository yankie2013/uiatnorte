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

if (!current_user()) {
    json_out([
        'ok' => false,
        'error' => 'Sesión vencida. Inicia sesión nuevamente.',
    ], 401);
}

$placa = sanitize_placa((string) ($_GET['placa'] ?? ''));
if ($placa === '') {
    json_out(['ok' => false, 'error' => 'Falta placa.'], 400);
}

$baseUrl = seeker_base_url();
$token = seeker_token_value();
if ($baseUrl === '' || $token === '') {
    error_log('proxy_seeker.php ERROR: falta configuración de Seeker.');
    json_out(['ok' => false, 'error' => 'El servicio de placas no está disponible en este momento.'], 503);
}

$url = rtrim($baseUrl, '/') . '/vehiculos/api_newPlacas?placa=' . urlencode($placa) . '&token=' . urlencode($token);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json,text/plain,*/*',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
    ],
]);

$body = curl_exec($ch);
$error = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($body === false) {
    error_log('proxy_seeker.php ERROR: cURL error: ' . $error);
    json_out(['ok' => false, 'error' => 'No se pudo consultar la placa en este momento.'], 502);
}

if (stripos($contentType, 'text/html') !== false || stripos($body, '<html') !== false) {
    error_log('proxy_seeker.php ERROR: HTML inesperado. HTTP ' . $httpCode . ' content_type=' . $contentType . ' body=' . substr((string) $body, 0, 400));
    json_out(['ok' => false, 'error' => 'El servicio de placas devolvió una respuesta inválida.'], $httpCode >= 400 ? $httpCode : 502);
}

$json = json_decode($body, true);
if (!is_array($json)) {
    error_log('proxy_seeker.php ERROR: JSON inválido. HTTP ' . $httpCode . ' content_type=' . $contentType . ' body=' . substr((string) $body, 0, 400));
    json_out(['ok' => false, 'error' => 'El servicio de placas devolvió una respuesta inválida.'], $httpCode >= 400 ? $httpCode : 502);
}

if ($httpCode >= 400) {
    error_log('proxy_seeker.php ERROR: HTTP ' . $httpCode . ' body=' . substr(json_encode($json, JSON_UNESCAPED_UNICODE), 0, 400));
    json_out(['ok' => false, 'error' => 'No se pudo consultar la placa en este momento.'], $httpCode);
}

json_out([
    'ok' => true,
    'data' => $json,
], $httpCode ?: 200);
