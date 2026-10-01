<?php
/** CLI: sin --apply solo comprueba requisitos. Nunca importa la base de desarrollo. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['database:', 'apply']);
if (!isset($options['database']) || !is_string($options['database']) || $options['database'] === '') {
    fwrite(STDERR, "Uso: php docs/scripts/migrar_persona_snapshots.php --database=NOMBRE [--apply]\n");
    exit(2);
}
require dirname(__DIR__, 2) . '/bootstrap/app.php';
$pdo = \App\Database\Database::connection();
$schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$host = (string) app_config('database.host');
$port = (int) app_config('database.port');
if ($options['database'] !== $schema) {
    fwrite(STDERR, "Bloqueado: la base conectada no coincide con --database.\n");
    exit(2);
}
echo "Destino: {$host}:{$port} / {$schema}\n";
// Todas estas lecturas ocurren antes del primer ALTER TABLE.
$required = [
    'usuarios' => 'id,activo,rol',
    'accidentes' => 'id,fecha_accidente,eliminado_en',
    'personas' => 'id,tipo_doc,num_doc,apellido_paterno,apellido_materno,nombres,sexo,fecha_nacimiento,departamento_nac,provincia_nac,distrito_nac,nombre_padre,nombre_madre,edad,estado_civil,grado_instruccion,domicilio,celular,email',
    'involucrados_personas' => 'id,accidente_id,persona_id,orden_persona',
    'familiar_fallecido' => 'id,accidente_id,familiar_persona_id',
    'policial_interviniente' => 'id,accidente_id,persona_id',
    'propietario_vehiculo' => 'id,accidente_id,propietario_persona_id,representante_persona_id',
];
foreach ($required as $table => $fields) {
    $pdo->query("SELECT {$fields} FROM `{$table}` LIMIT 0");
}
$adminId = $pdo->query("SELECT id FROM usuarios WHERE activo=1 AND rol='admin' ORDER BY id LIMIT 1")->fetchColumn();
if ($adminId === false) throw new RuntimeException('Falta un administrador activo; no se modificó la base.');
$routines = $pdo->query("SELECT routine_name FROM information_schema.routines WHERE routine_schema=DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
foreach (['rbac_admin', 'rbac_case', 'rbac_person', 'rbac_vehicle', 'rbac_guardia_draft'] as $routine) {
    if (!in_array($routine, $routines, true)) throw new RuntimeException("Falta {$routine}. Complete primero la migración multiusuario; no se modificó la base.");
}
$invalidAges = (int) $pdo->query("SELECT COUNT(*) FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id JOIN accidentes a ON a.id=ip.accidente_id WHERE TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,a.fecha_accidente) NOT BETWEEN 0 AND 255")->fetchColumn();
if ($invalidAges > 0) throw new RuntimeException("Hay {$invalidAges} relaciones con fechas incompatibles para calcular edad. Revisarlas antes de migrar; no se modificó la base.");
$columns = [
    'personas' => [
        'numero_hijos' => 'ALTER TABLE personas ADD COLUMN numero_hijos TINYINT UNSIGNED NULL AFTER grado_instruccion',
        'domicilio_departamento' => 'ALTER TABLE personas ADD COLUMN domicilio_departamento VARCHAR(60) NULL',
        'domicilio_provincia' => 'ALTER TABLE personas ADD COLUMN domicilio_provincia VARCHAR(60) NULL',
        'domicilio_distrito' => 'ALTER TABLE personas ADD COLUMN domicilio_distrito VARCHAR(60) NULL',
    ],
    'involucrados_personas' => [
        'edad_snapshot' => 'ALTER TABLE involucrados_personas ADD COLUMN edad_snapshot TINYINT UNSIGNED NULL AFTER orden_persona',
        'snapshot_guardado' => 'ALTER TABLE involucrados_personas ADD COLUMN snapshot_guardado TINYINT(1) NOT NULL DEFAULT 0',
        'estado_civil_snapshot' => 'ALTER TABLE involucrados_personas ADD COLUMN estado_civil_snapshot VARCHAR(30) NULL',
        'grado_instruccion_snapshot' => 'ALTER TABLE involucrados_personas ADD COLUMN grado_instruccion_snapshot VARCHAR(60) NULL',
        'numero_hijos_snapshot' => 'ALTER TABLE involucrados_personas ADD COLUMN numero_hijos_snapshot TINYINT UNSIGNED NULL',
        'domicilio_snapshot' => 'ALTER TABLE involucrados_personas ADD COLUMN domicilio_snapshot VARCHAR(200) NULL',
        'domicilio_departamento_snapshot' => 'ALTER TABLE involucrados_personas ADD COLUMN domicilio_departamento_snapshot VARCHAR(60) NULL',
        'domicilio_provincia_snapshot' => 'ALTER TABLE involucrados_personas ADD COLUMN domicilio_provincia_snapshot VARCHAR(60) NULL',
        'domicilio_distrito_snapshot' => 'ALTER TABLE involucrados_personas ADD COLUMN domicilio_distrito_snapshot VARCHAR(60) NULL',
        'celular_snapshot' => 'ALTER TABLE involucrados_personas ADD COLUMN celular_snapshot VARCHAR(20) NULL',
        'email_snapshot' => 'ALTER TABLE involucrados_personas ADD COLUMN email_snapshot VARCHAR(120) NULL',
    ],
    'familiar_fallecido' => [
        'domicilio_snapshot' => 'ALTER TABLE familiar_fallecido ADD COLUMN domicilio_snapshot VARCHAR(200) NULL',
        'celular_snapshot' => 'ALTER TABLE familiar_fallecido ADD COLUMN celular_snapshot VARCHAR(20) NULL',
        'email_snapshot' => 'ALTER TABLE familiar_fallecido ADD COLUMN email_snapshot VARCHAR(120) NULL',
        'snapshot_guardado' => 'ALTER TABLE familiar_fallecido ADD COLUMN snapshot_guardado TINYINT(1) NOT NULL DEFAULT 0',
    ],
    'policial_interviniente' => [
        'domicilio_snapshot' => 'ALTER TABLE policial_interviniente ADD COLUMN domicilio_snapshot VARCHAR(200) NULL',
        'celular_snapshot' => 'ALTER TABLE policial_interviniente ADD COLUMN celular_snapshot VARCHAR(20) NULL',
        'email_snapshot' => 'ALTER TABLE policial_interviniente ADD COLUMN email_snapshot VARCHAR(120) NULL',
        'snapshot_guardado' => 'ALTER TABLE policial_interviniente ADD COLUMN snapshot_guardado TINYINT(1) NOT NULL DEFAULT 0',
    ],
    'propietario_vehiculo' => [
        'domicilio_nat_snapshot' => 'ALTER TABLE propietario_vehiculo ADD COLUMN domicilio_nat_snapshot VARCHAR(200) NULL',
        'celular_nat_snapshot' => 'ALTER TABLE propietario_vehiculo ADD COLUMN celular_nat_snapshot VARCHAR(20) NULL',
        'email_nat_snapshot' => 'ALTER TABLE propietario_vehiculo ADD COLUMN email_nat_snapshot VARCHAR(120) NULL',
        'domicilio_rep_snapshot' => 'ALTER TABLE propietario_vehiculo ADD COLUMN domicilio_rep_snapshot VARCHAR(200) NULL',
        'celular_rep_snapshot' => 'ALTER TABLE propietario_vehiculo ADD COLUMN celular_rep_snapshot VARCHAR(20) NULL',
        'email_rep_snapshot' => 'ALTER TABLE propietario_vehiculo ADD COLUMN email_rep_snapshot VARCHAR(120) NULL',
        'snapshot_guardado' => 'ALTER TABLE propietario_vehiculo ADD COLUMN snapshot_guardado TINYINT(1) NOT NULL DEFAULT 0',
    ],
];

$pending = [];
foreach ($columns as $table => $tableColumns) {
    foreach ($tableColumns as $column => $ddl) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
        $check->execute([$schema, $table, $column]);
        if ((int) $check->fetchColumn() === 0) $pending["{$table}.{$column}"] = $ddl;
    }
}
echo 'Columnas pendientes: ' . count($pending) . "\n";
foreach ($pending as $column => $ddl) echo "PENDIENTE {$column}\n";
if (!isset($options['apply'])) {
    echo "PLAN: agregar columnas de copias históricas, inicializar solo las pendientes, actualizar cuatro vistas e instalar protección de identidad.\n";
    echo "Conserva IDs, responsables y copias ya guardadas. Incluye expedientes eliminados.\n";
    echo "No se modificó la base. Con respaldo y aplicación en mantenimiento, ejecutar de nuevo con --apply.\n";
    exit;
}

foreach ($pending as $column => $ddl) {
    $pdo->exec($ddl);
    echo "Agregada columna {$column}\n";
}

// Copias iniciales, incluidas las de expedientes eliminados, en una transacción.
// La bandera solo vive en esta conexión CLI y se restaura incluso ante un error.
$pdo->exec('SET @actor_id = ' . (int) $adminId);
$pdo->exec('SET @rbac_migration = 1');
$pdo->beginTransaction();
try {
$pdo->exec("UPDATE involucrados_personas ip
    JOIN personas p ON p.id=ip.persona_id
    JOIN accidentes a ON a.id=ip.accidente_id
       SET ip.edad_snapshot=COALESCE(ip.edad_snapshot, IF(p.fecha_nacimiento IS NULL OR a.fecha_accidente IS NULL, NULL, TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,a.fecha_accidente))),
           ip.estado_civil_snapshot=COALESCE(ip.estado_civil_snapshot,p.estado_civil),
           ip.grado_instruccion_snapshot=COALESCE(ip.grado_instruccion_snapshot,p.grado_instruccion),
           ip.numero_hijos_snapshot=COALESCE(ip.numero_hijos_snapshot,p.numero_hijos),
           ip.domicilio_snapshot=COALESCE(ip.domicilio_snapshot,p.domicilio),
           ip.domicilio_departamento_snapshot=COALESCE(ip.domicilio_departamento_snapshot,p.domicilio_departamento),
           ip.domicilio_provincia_snapshot=COALESCE(ip.domicilio_provincia_snapshot,p.domicilio_provincia),
           ip.domicilio_distrito_snapshot=COALESCE(ip.domicilio_distrito_snapshot,p.domicilio_distrito),
           ip.celular_snapshot=COALESCE(ip.celular_snapshot,p.celular),
           ip.email_snapshot=COALESCE(ip.email_snapshot,p.email),
           ip.snapshot_guardado=1
     WHERE ip.snapshot_guardado=0");

$pdo->exec("UPDATE familiar_fallecido ff
    JOIN personas p ON p.id=ff.familiar_persona_id
    JOIN accidentes a ON a.id=ff.accidente_id
       SET ff.domicilio_snapshot=COALESCE(ff.domicilio_snapshot,p.domicilio),
           ff.celular_snapshot=COALESCE(ff.celular_snapshot,p.celular),
           ff.email_snapshot=COALESCE(ff.email_snapshot,p.email),
           ff.snapshot_guardado=1
     WHERE ff.snapshot_guardado=0");

$pdo->exec("UPDATE policial_interviniente pi
    JOIN personas p ON p.id=pi.persona_id
    JOIN accidentes a ON a.id=pi.accidente_id
       SET pi.domicilio_snapshot=COALESCE(pi.domicilio_snapshot,p.domicilio),
           pi.celular_snapshot=COALESCE(pi.celular_snapshot,p.celular),
           pi.email_snapshot=COALESCE(pi.email_snapshot,p.email),
           pi.snapshot_guardado=1
     WHERE pi.snapshot_guardado=0");

$pdo->exec("UPDATE propietario_vehiculo pv
    JOIN accidentes a ON a.id=pv.accidente_id
    LEFT JOIN personas pn ON pn.id=pv.propietario_persona_id
    LEFT JOIN personas pr ON pr.id=pv.representante_persona_id
       SET pv.domicilio_nat_snapshot=COALESCE(pv.domicilio_nat_snapshot,pn.domicilio),
           pv.celular_nat_snapshot=COALESCE(pv.celular_nat_snapshot,pn.celular),
           pv.email_nat_snapshot=COALESCE(pv.email_nat_snapshot,pn.email),
           pv.domicilio_rep_snapshot=COALESCE(pv.domicilio_rep_snapshot,pr.domicilio),
           pv.celular_rep_snapshot=COALESCE(pv.celular_rep_snapshot,pr.celular),
           pv.email_rep_snapshot=COALESCE(pv.email_rep_snapshot,pr.email),
           pv.snapshot_guardado=1
     WHERE pv.snapshot_guardado=0");

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
} finally {
    $pdo->exec('SET @rbac_migration = NULL');
    $pdo->exec('SET @actor_id = 0');
}

// La vista heredada enumeraba las columnas anteriores y ocultaba las nuevas instantáneas.
$pdo->exec("CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW involucrados_personas_activos AS
    SELECT d.* FROM involucrados_personas d
    LEFT JOIN accidentes a ON a.id=d.accidente_id
    WHERE d.accidente_id IS NULL OR a.eliminado_en IS NULL");
$pdo->exec("CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW familiar_fallecido_activos AS
    SELECT d.* FROM familiar_fallecido d
    LEFT JOIN accidentes a ON a.id=d.accidente_id
    WHERE d.accidente_id IS NULL OR a.eliminado_en IS NULL");
$pdo->exec("CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW policial_interviniente_activos AS
    SELECT d.* FROM policial_interviniente d
    LEFT JOIN accidentes a ON a.id=d.accidente_id
    WHERE d.accidente_id IS NULL OR a.eliminado_en IS NULL");
$pdo->exec("CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW propietario_vehiculo_activos AS
    SELECT d.* FROM propietario_vehiculo d
    LEFT JOIN accidentes a ON a.id=d.accidente_id
    WHERE d.accidente_id IS NULL OR a.eliminado_en IS NULL");

$pdo->exec('DROP TRIGGER IF EXISTS bu_personas_identidad_inmutable');
$pdo->exec("CREATE TRIGGER bu_personas_identidad_inmutable BEFORE UPDATE ON personas FOR EACH ROW
BEGIN
    IF NOT (OLD.tipo_doc <=> NEW.tipo_doc)
       OR NOT (OLD.num_doc <=> NEW.num_doc)
       OR NOT (OLD.apellido_paterno <=> NEW.apellido_paterno)
       OR NOT (OLD.apellido_materno <=> NEW.apellido_materno)
       OR NOT (OLD.nombres <=> NEW.nombres)
       OR NOT (OLD.sexo <=> NEW.sexo)
       OR NOT (OLD.fecha_nacimiento <=> NEW.fecha_nacimiento)
       OR NOT (OLD.departamento_nac <=> NEW.departamento_nac)
       OR NOT (OLD.provincia_nac <=> NEW.provincia_nac)
       OR NOT (OLD.distrito_nac <=> NEW.distrito_nac)
       OR NOT (OLD.nombre_padre <=> NEW.nombre_padre)
       OR NOT (OLD.nombre_madre <=> NEW.nombre_madre) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Los datos de identidad de una persona son inmutables';
    END IF;
    IF EXISTS(SELECT 1 FROM involucrados_personas WHERE persona_id=OLD.id)
       AND (NOT (OLD.edad <=> NEW.edad)
         OR NOT (OLD.estado_civil <=> NEW.estado_civil)
         OR NOT (OLD.grado_instruccion <=> NEW.grado_instruccion)
         OR NOT (OLD.numero_hijos <=> NEW.numero_hijos)
         OR NOT (OLD.domicilio <=> NEW.domicilio)
         OR NOT (OLD.domicilio_departamento <=> NEW.domicilio_departamento)
         OR NOT (OLD.domicilio_provincia <=> NEW.domicilio_provincia)
         OR NOT (OLD.domicilio_distrito <=> NEW.domicilio_distrito)
         OR NOT (OLD.celular <=> NEW.celular)
         OR NOT (OLD.email <=> NEW.email)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Actualice datos variables desde el expediente para conservar sus copias históricas';
    END IF;
END");

fwrite(STDOUT, "Migración de copias históricas aplicada a {$schema} en {$host}:{$port}.\n");
fwrite(STDOUT, "Las copias existentes no se reescriben. Las iniciales usan la ficha disponible hoy.\n");
fwrite(STDOUT, "Reinstale ahora los triggers de auditoría con migrar_multiusuario.php --schema-only --apply para incluir las columnas nuevas.\n");
