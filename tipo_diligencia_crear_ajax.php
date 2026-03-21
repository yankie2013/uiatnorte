<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('current_user') || !current_user()) {
    json_out(['ok' => 0, 'error' => 'No autorizado'], 401);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET NAMES utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => 0, 'error' => 'Metodo no permitido'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    json_out(['ok' => 0, 'error' => 'Payload invalido'], 400);
}

$nombre = trim((string) ($data['nombre'] ?? ''));
$descripcion = trim((string) ($data['descripcion'] ?? ''));

if ($nombre === '') {
    json_out(['ok' => 0, 'error' => 'El nombre es obligatorio.'], 400);
}
if (mb_strlen($nombre) > 150) {
    json_out(['ok' => 0, 'error' => 'El nombre es demasiado largo (max 150 caracteres).'], 400);
}
if (mb_strlen($descripcion) > 2000) {
    json_out(['ok' => 0, 'error' => 'La descripcion es demasiado larga.'], 400);
}

$findExisting = static function () use ($pdo, $nombre): ?array {
    $stmt = $pdo->prepare('SELECT id, nombre, descripcion FROM tipo_diligencia WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1');
    $stmt->execute([':nombre' => $nombre]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
};

try {
    $exists = $findExisting();
    if ($exists) {
        json_out(['ok' => 1, 'id' => $exists['id'], 'nombre' => $exists['nombre'], 'descripcion' => $exists['descripcion']]);
    }

    $ins = $pdo->prepare('INSERT INTO tipo_diligencia (nombre, descripcion, creado_en) VALUES (:nombre, :descripcion, NOW())');
    $ins->execute([
        ':nombre' => $nombre,
        ':descripcion' => $descripcion !== '' ? $descripcion : null,
    ]);

    json_out(['ok' => 1, 'id' => $pdo->lastInsertId(), 'nombre' => $nombre, 'descripcion' => $descripcion]);
} catch (Throwable $e) {
    $exists = $findExisting();
    if ($exists) {
        json_out(['ok' => 1, 'id' => $exists['id'], 'nombre' => $exists['nombre'], 'descripcion' => $exists['descripcion']]);
    }

    json_out(['ok' => 0, 'error' => 'No se pudo crear el tipo.'], 500);
}
