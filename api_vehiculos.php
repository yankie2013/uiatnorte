<?php
declare(strict_types=1);

require_once __DIR__ . '/config_seeker.php';

function vehiculo_api_base_url(): string
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

function vehiculo_api_token(): string
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

function vehiculo_api_decode_json(string $body): ?array
{
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function vehiculo_api_error_from_provider(array $json, int $httpCode): ?array
{
    $code = strtoupper(trim((string) ($json['code'] ?? '')));
    $status = strtolower(trim((string) ($json['status'] ?? '')));
    $message = trim((string) ($json['message'] ?? $json['error'] ?? ''));

    if ($code === 'INVALID_TOKEN') {
        return [
            'ok' => false,
            'error' => 'El acceso API de placas no esta habilitado o el token es invalido.',
            'http_code' => 503,
            'provider_code' => $code,
        ];
    }

    if ($status !== '' && $status !== 'success') {
        return [
            'ok' => false,
            'error' => $message !== '' ? $message : 'El servicio de placas devolvio un estado no valido.',
            'http_code' => $httpCode >= 400 ? $httpCode : 502,
            'provider_code' => $code !== '' ? $code : null,
        ];
    }

    if ($httpCode >= 400) {
        return [
            'ok' => false,
            'error' => $message !== '' ? $message : 'No se pudo consultar la placa en este momento.',
            'http_code' => $httpCode,
            'provider_code' => $code !== '' ? $code : null,
        ];
    }

    return null;
}

function consultarVehiculoAPI(string $placa): array
{
    $placa = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim($placa)));
    if ($placa === '') {
        return [
            'ok' => false,
            'error' => 'Placa invalida.',
        ];
    }

    $baseUrl = vehiculo_api_base_url();
    $token = vehiculo_api_token();
    if ($baseUrl === '' || $token === '') {
        return [
            'ok' => false,
            'error' => 'El servicio de placas no esta disponible en este momento.',
        ];
    }

    $url = rtrim($baseUrl, '/') . '/vehiculos/api_newPlacas?placa=' . urlencode($placa) . '&token=' . urlencode($token);
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (UIATNorte; PHP cURL)',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json,text/plain,*/*',
            'X-Requested-With: XMLHttpRequest',
        ],
    ]);

    $rawBody = curl_exec($ch);
    if ($rawBody === false) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log('api_vehiculos.php ERROR: cURL error: ' . $error);
        return [
            'ok' => false,
            'error' => 'No se pudo consultar la placa en este momento.',
        ];
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $json = vehiculo_api_decode_json($rawBody);
    if ($json === null) {
        error_log('api_vehiculos.php ERROR: JSON invalido. HTTP ' . $httpCode . ' body=' . substr(trim((string) $rawBody), 0, 400));
        return [
            'ok' => false,
            'error' => 'El servicio de placas devolvio una respuesta invalida.',
            'http_code' => $httpCode,
        ];
    }

    if (stripos($contentType, 'application/json') === false) {
        error_log('api_vehiculos.php WARN: content_type inesperado. HTTP ' . $httpCode . ' content_type=' . $contentType . ' body=' . substr(trim((string) $rawBody), 0, 400));
    }

    $providerError = vehiculo_api_error_from_provider($json, $httpCode);
    if ($providerError !== null) {
        error_log('api_vehiculos.php ERROR: HTTP ' . ($providerError['http_code'] ?? $httpCode) . ' body=' . substr(json_encode($json, JSON_UNESCAPED_UNICODE), 0, 400));
        return $providerError;
    }

    return [
        'ok' => true,
        'http_code' => $httpCode ?: 200,
        'json' => $json,
    ];
}