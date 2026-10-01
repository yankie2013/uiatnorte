<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Database\Database;

$pdo = Database::connection();
$schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$dbConfig = app_config('database', []);
$host = strtolower((string) ($dbConfig['host'] ?? ''));
$port = (int) ($dbConfig['port'] ?? 0);
if (!preg_match('/_dev$/', $schema) || !in_array($host, ['localhost', '127.0.0.1', '::1'], true) || $port !== 8889) {
    fwrite(STDERR, "Bloqueado: la migración requiere MySQL local MAMP en puerto 8889 y una base terminada en _dev.\n");
    exit(2);
}

$columns = [
    'personas' => [
        'numero_hijos' => 'ALTER TABLE personas ADD COLUMN numero_hijos TINYINT UNSIGNED NULL AFTER grado_instruccion',
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
];

foreach ($columns as $table => $tableColumns) {
    foreach ($tableColumns as $column => $ddl) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
        $check->execute([$schema, $table, $column]);
        if ((int) $check->fetchColumn() === 0) {
            $pdo->exec($ddl);
            fwrite(STDOUT, "Agregada columna {$table}.{$column}\n");
        }
    }
}

// La inicialización es una operación administrativa de datos; las reglas de acceso de la aplicación
// permanecen activas y el actor de migración se limita a un administrador activo de esta base local.
$adminId = $pdo->query("SELECT id FROM usuarios WHERE activo=1 AND rol='admin' ORDER BY id LIMIT 1")->fetchColumn();
if ($adminId === false) {
    fwrite(STDERR, "No se encontró un administrador activo para inicializar las copias históricas.\n");
    exit(3);
}
$pdo->exec('SET @actor_id = ' . (int) $adminId);

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
           ip.snapshot_guardado=1");

// La vista heredada enumeraba las columnas anteriores y ocultaba las nuevas instantáneas.
$pdo->exec("CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW involucrados_personas_activos AS
    SELECT d.* FROM involucrados_personas d
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

fwrite(STDOUT, "Migración aplicada solo a {$schema} en {$host}:{$port}. Los valores históricos anteriores se inicializaron con la ficha actual; no se reconstruyen cambios antiguos que no estaban guardados.\n");
