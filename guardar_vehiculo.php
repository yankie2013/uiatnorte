<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

use App\Database\Database;

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

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        json_out([
            'ok' => false,
            'fatal' => true,
            'error' => 'Error interno al guardar el vehiculo.',
        ], 500);
    }
});

if (!current_user()) {
    json_out([
        'ok' => false,
        'error' => 'Sesion vencida. Inicia sesion nuevamente.',
    ], 401);
}

function mtToM(?string $valor): ?float {
    if (!$valor) {
        return null;
    }
    if (!preg_match('/([\d.,]+)/', $valor, $m)) {
        return null;
    }
    $n = (float) str_replace(',', '.', $m[1]);
    return round($n, 2);
}

function categoriaCodigoDesdeApi(array $data): ?string {
    $co = strtoupper(trim((string)($data['coCateg'] ?? '')));

    $asientos = (int) preg_replace('/\D+/', '', (string)($data['numAsientos'] ?? '0'));
    $pasajeros = (int) preg_replace('/\D+/', '', (string)($data['numPasajeros'] ?? '0'));
    $cap = $asientos > 0 ? $asientos : $pasajeros;

    if (str_contains($co, 'CATEGORIA M')) {
        if ($cap <= 8) {
            return 'M1';
        }
        if ($cap <= 22) {
            return 'M2';
        }
        return 'M3';
    }
    return null;
}

function requestPayload(mixed $payload): ?array
{
    if (!is_array($payload)) {
        return null;
    }

    if (is_array($payload['data'] ?? null)) {
        return $payload['data'];
    }

    if (is_array($payload['respuesta'] ?? null)) {
        return $payload['respuesta'];
    }

    return $payload;
}

function vehiculoDb(): PDO
{
    if (function_exists('db')) {
        $pdo = db();
        if ($pdo instanceof PDO) {
            return $pdo;
        }
    }

    global $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = Database::connection();
    Database::defineLegacyConstants();

    return $pdo;
}

$raw = file_get_contents('php://input');
$j = json_decode($raw ?: '', true);

if (!is_array($j)) {
    json_out(['ok' => false, 'error' => 'JSON invalido'], 400);
}

$data = requestPayload($j);
if (!is_array($data)) {
    json_out(['ok' => false, 'error' => 'No se encontro un payload valido.'], 400);
}

if (($data['status'] ?? '') !== 'success') {
    json_out(['ok' => false, 'error' => 'status != success'], 400);
}

$placa  = strtoupper(trim((string)($data['numPlaca'] ?? $data['placa'] ?? '')));
$marca  = trim((string)($data['marca'] ?? ''));
$modelo = trim((string)($data['modelo'] ?? ''));
$carro  = trim((string)($data['descTipoCarr'] ?? ''));
$color  = trim((string)($data['color'] ?? ''));
$serie  = trim((string)($data['numSerie'] ?? ''));
$motor  = trim((string)($data['numMotor'] ?? ''));
$anio   = isset($data['anoFab']) ? (int)$data['anoFab'] : null;
$largo = mtToM($data['longitud'] ?? null);
$ancho = mtToM($data['ancho'] ?? null);
$alto  = mtToM($data['altura'] ?? null);

if ($placa === '') {
    json_out(['ok' => false, 'error' => 'No vino numPlaca'], 400);
}

try {
    $pdo = vehiculoDb();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    $marca_id = null;
    if ($marca !== '') {
        $st = $pdo->prepare('SELECT id FROM marcas_vehiculo WHERE nombre = ? LIMIT 1');
        $st->execute([$marca]);
        $marca_id = $st->fetchColumn();

        if (!$marca_id) {
            $st = $pdo->prepare('INSERT INTO marcas_vehiculo(nombre) VALUES(?)');
            $st->execute([$marca]);
            $marca_id = $pdo->lastInsertId();
        }
    }

    $modelo_id = null;
    if ($modelo !== '' && $marca_id) {
        $st = $pdo->prepare('SELECT id FROM modelos_vehiculo WHERE marca_id = ? AND nombre = ? LIMIT 1');
        $st->execute([$marca_id, $modelo]);
        $modelo_id = $st->fetchColumn();

        if (!$modelo_id) {
            $st = $pdo->prepare('INSERT INTO modelos_vehiculo(marca_id,nombre) VALUES(?,?)');
            $st->execute([$marca_id, $modelo]);
            $modelo_id = $pdo->lastInsertId();
        }
    }

    $categoria_id = 1;
    $codigoCat = categoriaCodigoDesdeApi($data);
    if ($codigoCat) {
        $st = $pdo->prepare('SELECT id FROM categoria_vehiculos WHERE codigo = ? LIMIT 1');
        $st->execute([$codigoCat]);
        $categoria_id = (int)($st->fetchColumn() ?: 1);
    }

    $st = $pdo->prepare('SELECT id FROM tipos_vehiculo WHERE categoria_id = ? ORDER BY id ASC LIMIT 1');
    $st->execute([$categoria_id]);
    $tipo_id = (int)($st->fetchColumn() ?: 1);

    $carroceria_id = null;
    if ($carro !== '') {
        $st = $pdo->prepare('SELECT id FROM carroceria_vehiculo WHERE tipo_id = ? AND UPPER(nombre) LIKE UPPER(?) LIMIT 1');
        $st->execute([$tipo_id, "%{$carro}%"]);
        $carroceria_id = $st->fetchColumn();

        if (!$carroceria_id) {
            $st = $pdo->prepare('INSERT INTO carroceria_vehiculo(tipo_id,nombre) VALUES(?,?)');
            $st->execute([$tipo_id, $carro]);
            $carroceria_id = $pdo->lastInsertId();
        }
    }

    $sql = 'INSERT INTO vehiculos
        (placa, serie_vin, nro_motor, categoria_id, tipo_id, carroceria_id, marca_id, modelo_id, anio, color, largo_mm, ancho_mm, alto_mm, notas)
        VALUES
        (:placa, :serie, :motor, :categoria_id, :tipo_id, :carroceria_id, :marca_id, :modelo_id, :anio, :color, :largo, :ancho, :alto, :notas)
        ON DUPLICATE KEY UPDATE
          serie_vin=VALUES(serie_vin),
          nro_motor=VALUES(nro_motor),
          categoria_id=VALUES(categoria_id),
          tipo_id=VALUES(tipo_id),
          carroceria_id=VALUES(carroceria_id),
          marca_id=VALUES(marca_id),
          modelo_id=VALUES(modelo_id),
          anio=VALUES(anio),
          color=VALUES(color),
          largo_mm=VALUES(largo_mm),
          ancho_mm=VALUES(ancho_mm),
          alto_mm=VALUES(alto_mm),
          notas=VALUES(notas),
          actualizado_en=CURRENT_TIMESTAMP';

    $st = $pdo->prepare($sql);
    $st->execute([
        ':placa' => $placa,
        ':serie' => ($serie !== '' ? $serie : null),
        ':motor' => ($motor !== '' ? $motor : null),
        ':categoria_id' => $categoria_id,
        ':tipo_id' => $tipo_id,
        ':carroceria_id' => $carroceria_id,
        ':marca_id' => $marca_id,
        ':modelo_id' => $modelo_id,
        ':anio' => ($anio && $anio > 0 ? $anio : null),
        ':color' => ($color !== '' ? $color : null),
        ':largo' => $largo,
        ':ancho' => $ancho,
        ':alto' => $alto,
        ':notas' => json_encode([
            'coCateg' => $data['coCateg'] ?? null,
            'descTipoCarr' => $data['descTipoCarr'] ?? null,
            'descTipoUso' => $data['descTipoUso'] ?? null,
            'descTipoComb' => $data['descTipoComb'] ?? null,
            'pesoBruto' => $data['pesoBruto'] ?? null,
            'fecIns' => $data['fecIns'] ?? null,
            'numPartida' => $data['numPartida'] ?? null,
            'noVin' => $data['noVin'] ?? null,
        ], JSON_UNESCAPED_UNICODE),
    ]);

    $pdo->commit();

    json_out([
        'ok' => true,
        'placa' => $placa,
        'marca_id' => $marca_id,
        'modelo_id' => $modelo_id,
        'categoria_id' => $categoria_id,
        'tipo_id' => $tipo_id,
        'carroceria_id' => $carroceria_id,
        'largo_m' => $largo,
        'ancho_m' => $ancho,
        'alto_m' => $alto,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out([
        'ok' => false,
        'error' => 'No se pudo guardar el vehiculo.',
        'detail' => $e->getMessage(),
    ], 500);
}
