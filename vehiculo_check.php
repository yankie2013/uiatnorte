<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';

use App\Repositories\VehiculoRepository;
use App\Services\VehiculoService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    http_response_code(401);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$service = new VehiculoService(new VehiculoRepository($pdo));
$placa = trim((string) ($_GET['placa'] ?? ''));
$excludeId = (int) ($_GET['exclude_id'] ?? 0);
$id = $service->buscarExistentePorPlaca($placa, $excludeId > 0 ? $excludeId : null);

echo json_encode([
    'ok' => true,
    'exists' => $id !== null,
    'id' => $id,
], JSON_UNESCAPED_UNICODE);
