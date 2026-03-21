<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

use App\Repositories\CatalogoOficioRepository;
use App\Services\CatalogoOficioService;

header('Content-Type: application/json; charset=utf-8');

if (!current_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$service = new CatalogoOficioService(new CatalogoOficioRepository($pdo));
$entidadId = (int) ($_GET['entidad_id'] ?? 0);

try {
    echo json_encode(['ok' => true, 'items' => $service->subentidadesPorEntidad($entidadId)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
