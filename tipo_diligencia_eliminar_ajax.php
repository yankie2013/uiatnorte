<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function tipo_delete_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!current_user()) {
    tipo_delete_response(['ok' => false, 'error' => 'No autorizado.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tipo_delete_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}
$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data) || !is_string($data['token'] ?? null)
    || empty($_SESSION['tipo_diligencia_delete_token'])
    || !hash_equals($_SESSION['tipo_diligencia_delete_token'], $data['token'])) {
    tipo_delete_response(['ok' => false, 'error' => 'Sesión inválida. Vuelve a abrir el formulario.'], 403);
}
$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    tipo_delete_response(['ok' => false, 'error' => 'Tipo inválido.'], 400);
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT nombre FROM tipo_diligencia WHERE id = ? FOR UPDATE');
    $find->execute([$id]);
    $nombre = $find->fetchColumn();
    if ($nombre !== false) {
        // Preserve the historical name while removing the catalog reference.
        $unlink = $pdo->prepare("UPDATE diligencias_pendientes SET tipo_diligencia = CASE WHEN TRIM(COALESCE(tipo_diligencia, '')) = '' THEN ? ELSE tipo_diligencia END, tipo_diligencia_id = NULL WHERE tipo_diligencia_id = ?");
        $unlink->execute([$nombre, $id]);
        $delete = $pdo->prepare('DELETE FROM tipo_diligencia WHERE id = ?');
        $delete->execute([$id]);
    }
    $pdo->commit();
    tipo_delete_response(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    tipo_delete_response(['ok' => false, 'error' => 'No se pudo eliminar el tipo.'], 500);
}
