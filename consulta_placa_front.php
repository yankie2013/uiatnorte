<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!current_user()) {
    json_out([
        'ok' => false,
        'error' => 'Sesión vencida. Inicia sesión nuevamente.',
    ], 401);
}

$placa = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim((string) ($_GET['placa'] ?? ''))));
if ($placa === '') {
    json_out([
        'ok' => false,
        'error' => 'Falta placa.',
    ], 400);
}

$localUrl = 'buscar_placa.php?placa=' . rawurlencode($placa);

json_out([
    'ok' => true,
    'placa' => $placa,
    'url_seeker' => $localUrl,
    'url_local' => $localUrl,
]);