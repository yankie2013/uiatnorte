<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

function jexit(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function persona_payload(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'num_doc' => (string) ($row['num_doc'] ?? ''),
        'apellido_paterno' => (string) ($row['apellido_paterno'] ?? ''),
        'apellido_materno' => (string) ($row['apellido_materno'] ?? ''),
        'nombres' => (string) ($row['nombres'] ?? ''),
        'fecha_nacimiento' => $row['fecha_nacimiento'] ?? null,
        'domicilio' => (string) ($row['domicilio'] ?? ''),
        'telefono' => (string) ($row['telefono'] ?? ''),
        'celular' => (string) ($row['celular'] ?? ''),
    ];
}

if (!function_exists('current_user') || !current_user()) {
    jexit(['ok' => false, 'msg' => 'No autorizado'], 401);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET NAMES utf8mb4');

$op = trim((string) ($_GET['op'] ?? ''));

if ($op === 'buscar_dni') {
    $dni = trim((string) ($_GET['dni'] ?? ''));
    if (!preg_match('/^\d{8}$/', $dni)) {
        jexit(['ok' => false, 'msg' => 'DNI invalido'], 400);
    }

    $sql = "SELECT id, num_doc, apellido_paterno, apellido_materno, nombres, fecha_nacimiento, domicilio, celular, telefono
            FROM personas
            WHERE tipo_doc='DNI' AND num_doc = ?
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$dni]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jexit(['ok' => false, 'msg' => 'No existe en BD. Usa el boton + para registrarla.'], 404);
    }

    jexit(['ok' => true, 'persona' => persona_payload($row)]);
}

if ($op === 'buscar_id') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jexit(['ok' => false, 'msg' => 'ID invalido'], 400);
    }

    $sql = "SELECT id, num_doc, apellido_paterno, apellido_materno, nombres, fecha_nacimiento, domicilio, celular, telefono
            FROM personas
            WHERE id = ?
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jexit(['ok' => false, 'msg' => 'Persona no encontrada'], 404);
    }

    jexit(['ok' => true, 'persona' => persona_payload($row)]);
}

jexit(['ok' => false, 'msg' => 'Operacion no valida'], 400);
