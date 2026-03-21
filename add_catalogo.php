<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';

use App\Repositories\InvolucradoVehiculoRepository;
use App\Services\InvolucradoVehiculoService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$repo = new InvolucradoVehiculoRepository($pdo);
$service = new InvolucradoVehiculoService($repo);

$kind = trim((string) ($_POST['kind'] ?? ''));

try {
    if ($kind === 'marca') {
        $item = $service->crearMarca($_POST);
        echo json_encode(['ok' => true, 'id' => $item['id'], 'label' => $item['nombre']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($kind === 'modelo') {
        $item = $service->crearModelo([
            'marca_id' => $_POST['padre_id'] ?? null,
            'nombre' => $_POST['nombre'] ?? '',
        ]);
        echo json_encode(['ok' => true, 'id' => $item['id'], 'label' => $item['nombre']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($kind === 'tipo') {
        $item = $service->crearTipo([
            'categoria_id' => $_POST['padre_id'] ?? null,
            'codigo' => $_POST['codigo'] ?? '',
            'nombre' => $_POST['nombre'] ?? '',
        ]);
        echo json_encode(['ok' => true, 'id' => $item['id'], 'label' => $item['nombre']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($kind === 'carroceria') {
        $item = $service->crearCarroceria([
            'tipo_id' => $_POST['padre_id'] ?? null,
            'nombre' => $_POST['nombre'] ?? '',
        ]);
        echo json_encode(['ok' => true, 'id' => $item['id'], 'label' => $item['nombre']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new InvalidArgumentException('Operación no soportada.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}


