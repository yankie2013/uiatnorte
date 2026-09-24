<?php
/**
 * Sincroniza solamente las vistas *_activos requeridas por la aplicación.
 * Por defecto inspecciona y muestra acciones; no modifica la base.
 * Aplicar en CLI con: php docs/scripts/sincronizar_vistas_activas.php --apply
 * No actualiza ni elimina filas de las tablas base.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Database\Database;

$pdo = Database::connection();
$apply = in_array('--apply', $argv, true);

$children = [
    'abogados', 'accidente_analisis_imagenes', 'accidente_consecuencia', 'accidente_modalidad',
    'citacion', 'diligencias_pendientes', 'documento_occiso', 'documento_rml', 'documentos_recibidos',
    'familiar_fallecido', 'involucrados_personas', 'involucrados_vehiculos', 'itp', 'Manifestacion',
    'oficios', 'policial_interviniente', 'propietario_vehiculo', 'actas', 'actas_visualizacion',
];

$exists = static function (PDO $pdo, string $name): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $st->execute([$name]);
    return (int) $st->fetchColumn() > 0;
};
$columns = static function (PDO $pdo, string $table): array {
    $st = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
    $st->execute([$table]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
};

$hasAccidents = $exists($pdo, 'accidentes');
$accidentColumns = $hasAccidents ? $columns($pdo, 'accidentes') : [];
$hasSoftDelete = in_array('eliminado_en', $accidentColumns, true);
$planned = [];

foreach ($children as $base) {
    $view = $base . '_activos';
    if ($exists($pdo, $view)) {
        echo "EXISTE  {$view}\n";
        continue;
    }
    if (!$exists($pdo, $base)) {
        echo "OMITIDA {$view}: no existe tabla base {$base}\n";
        continue;
    }

    $baseColumns = $columns($pdo, $base);
    if ($base === 'accidentes') {
        $select = $hasSoftDelete
            ? 'SELECT * FROM `accidentes` WHERE `eliminado_en` IS NULL'
            : 'SELECT * FROM `accidentes`';
    } elseif ($hasSoftDelete && in_array('accidente_id', $baseColumns, true)) {
        $select = "SELECT d.* FROM `{$base}` d LEFT JOIN `accidentes` a ON a.id=d.accidente_id WHERE d.accidente_id IS NULL OR a.eliminado_en IS NULL";
    } else {
        $select = "SELECT * FROM `{$base}`";
    }
    $planned[$view] = $select;
}

$view = 'documento_vehiculo_activos';
if ($exists($pdo, $view)) {
    echo "EXISTE  {$view}\n";
} elseif (!$exists($pdo, 'documento_vehiculo')) {
    echo "OMITIDA {$view}: no existe tabla base documento_vehiculo\n";
} else {
    $docColumns = $columns($pdo, 'documento_vehiculo');
    if ($hasSoftDelete && $exists($pdo, 'involucrados_vehiculos') && in_array('involucrado_vehiculo_id', $docColumns, true)) {
        $planned[$view] = 'SELECT d.* FROM `documento_vehiculo` d LEFT JOIN `involucrados_vehiculos` iv ON iv.id=d.involucrado_vehiculo_id LEFT JOIN `accidentes` a ON a.id=iv.accidente_id WHERE a.eliminado_en IS NULL';
    } else {
        $planned[$view] = 'SELECT * FROM `documento_vehiculo`';
    }
}

if (!$apply) {
    echo "\nRevisión terminada; no se modificó la base. Vistas que se crearían: " . count($planned) . ".\n";
    echo "Tras verificar este listado y tener el respaldo, aplica con --apply.\n";
    exit(0);
}

foreach ($planned as $name => $select) {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) {
        throw new RuntimeException('Nombre de vista inválido.');
    }
    $pdo->exec("CREATE VIEW `{$name}` AS {$select}");
    echo "CREADA  {$name}\n";
}

echo "\nSincronización terminada. Solo se crearon vistas faltantes; no se escribieron filas en tablas base.\n";
