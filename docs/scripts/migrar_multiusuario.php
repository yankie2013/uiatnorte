<?php
/** Ejecutar por CLI después de un respaldo: php docs/scripts/migrar_multiusuario.php --owner=2 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2).'/bootstrap/app.php';
$p = App\Database\Database::connection();
$options = getopt('', ['owner:', 'schema-only', 'apply']);
$schemaOnly = isset($options['schema-only']);
if ($schemaOnly && isset($options['owner'])) {
    throw new RuntimeException('--schema-only no admite --owner: no reasigna expedientes.');
}
if ($schemaOnly && !isset($options['apply'])) {
    echo "PLAN: completar columnas y tablas multiusuario, índice CIP, vistas, funciones y triggers de permisos.\n";
    echo "Conserva filas, contraseñas, roles y responsables existentes. No rellena documentos históricos.\n";
    echo "Los expedientes sin responsable seguirán sin responsable; un administrador deberá asignarlos desde la aplicación.\n";
    echo "DDL no es transaccional: use respaldo y ventana de mantenimiento. Ejecute con --schema-only --apply para aplicar.\n";
    exit;
}
// Validar antes del primer DDL para no instalar permisos sin una cuenta administradora.
if ($schemaOnly) {
    if (!$p->query("SELECT COUNT(*) FROM usuarios WHERE activo=1 AND rol='admin'")->fetchColumn()) {
        throw new RuntimeException('No hay administrador activo con rol admin. No se modificó la base; revise los roles antes de migrar.');
    }
    $hasCip = $p->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='cip'")->fetchColumn();
    if ($hasCip && $p->query("SELECT COUNT(*) FROM (SELECT cip FROM usuarios WHERE cip IS NOT NULL GROUP BY cip HAVING COUNT(*)>1) duplicados")->fetchColumn()) {
        throw new RuntimeException('CIP duplicados: no se modificó la base. Deben revisarse antes de crear el índice único.');
    }
}
$p->exec('SET @rbac_migration = 1');
try {
$owner = (int)($options['owner'] ?? 0);
if (!$schemaOnly) {
$st=$p->prepare('SELECT id,nombre FROM usuarios WHERE id=?');$st->execute([$owner]);
$person=$st->fetch();
if (!$person || !preg_match('/giancarlo.*merino.*sancho/i',$person['nombre'])) throw new RuntimeException('Indique el ID verificado del usuario Giancarlo Merino Sancho con --owner.');
}
function column(PDO $p,string $t,string $n,string $definition): void {
    $s=$p->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$s->execute([$t,$n]);
    if (!$s->fetchColumn()) $p->exec("ALTER TABLE `$t` ADD COLUMN `$n` $definition");
}
$p->exec("CREATE TABLE IF NOT EXISTS app_migrations (nombre VARCHAR(100) PRIMARY KEY, aplicado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$done=$p->query("SELECT COUNT(*) FROM app_migrations WHERE nombre='20260924_multiusuario'")->fetchColumn();
$p->exec("ALTER TABLE usuarios MODIFY rol VARCHAR(30) NOT NULL DEFAULT 'viewer'");
foreach(['grado'=>'VARCHAR(40) NULL','cip'=>'VARCHAR(30) NULL','telefono'=>'VARCHAR(30) NULL','cargo'=>'VARCHAR(80) NULL','unidad'=>"VARCHAR(160) NOT NULL DEFAULT 'DEPIAT'"] as $n=>$d) column($p,'usuarios',$n,$d);
foreach(['responsable_id'=>'INT NULL','creado_por'=>'INT NULL','asignado_en'=>'DATETIME NULL','primera_actuacion_en'=>'DATETIME NULL','eliminado_en'=>'DATETIME NULL','eliminado_por'=>'INT NULL','motivo_eliminacion'=>'TEXT NULL','ubicacion_verificada'=>'TINYINT(1) NOT NULL DEFAULT 0'] as $n=>$d) column($p,'accidentes',$n,$d);
$p->exec("CREATE TABLE IF NOT EXISTS expediente_colaboradores (accidente_id INT NOT NULL, usuario_id INT NOT NULL, asignado_por INT NOT NULL, asignado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, revocado_en DATETIME NULL, PRIMARY KEY(accidente_id,usuario_id), FOREIGN KEY(accidente_id) REFERENCES accidentes(id), FOREIGN KEY(usuario_id) REFERENCES usuarios(id)) ENGINE=InnoDB");
$p->exec("CREATE TABLE IF NOT EXISTS expediente_transferencias (id INT AUTO_INCREMENT PRIMARY KEY, accidente_id INT NOT NULL, origen_id INT NOT NULL, destino_id INT NOT NULL, motivo TEXT NOT NULL, estado VARCHAR(20) NOT NULL DEFAULT 'pendiente', creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, resuelto_en DATETIME NULL, KEY(accidente_id,estado), FOREIGN KEY(accidente_id) REFERENCES accidentes(id), FOREIGN KEY(destino_id) REFERENCES usuarios(id)) ENGINE=InnoDB");
$p->exec("CREATE TABLE IF NOT EXISTS comunicaciones_guardia (id INT AUTO_INCREMENT PRIMARY KEY, accidente_id INT NULL UNIQUE, creado_por INT NOT NULL, registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, fecha_llamada DATETIME NOT NULL, comunicante VARCHAR(160) NULL, telefono VARCHAR(30) NULL, lugar VARCHAR(250) NOT NULL, referencia VARCHAR(250) NULL, descripcion TEXT NOT NULL, tipo_hecho VARCHAR(120) NULL, vehiculos TEXT NULL, afectados TEXT NULL, latitud DECIMAL(10,7) NULL, longitud DECIMAL(10,7) NULL, cod_dep CHAR(2) NULL, cod_prov CHAR(2) NULL, cod_dist CHAR(2) NULL, jefe_id INT NULL, asignado_en DATETIME NULL, eliminado_en DATETIME NULL, eliminado_por INT NULL, motivo_eliminacion TEXT NULL, FOREIGN KEY(accidente_id) REFERENCES accidentes(id), FOREIGN KEY(creado_por) REFERENCES usuarios(id), FOREIGN KEY(jefe_id) REFERENCES usuarios(id)) ENGINE=InnoDB");
$p->exec("CREATE TABLE IF NOT EXISTS auditoria (id BIGINT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NULL, tabla VARCHAR(80) NOT NULL, registro_id VARCHAR(80) NULL, accidente_id INT NULL, accion VARCHAR(50) NOT NULL, antes JSON NULL, despues JSON NULL, motivo TEXT NULL, registrado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY(accidente_id,registrado_en), KEY(usuario_id,registrado_en)) ENGINE=InnoDB");
// Las actas forman parte de los expedientes y deben existir antes de proteger sus escrituras.
foreach(['2026-06-11_actas.sql','2026-06-12_actas_visualizacion.sql'] as $file) $p->exec(file_get_contents(dirname(__DIR__).'/sql/'.$file));
foreach(['personas','vehiculos'] as $table) column($p,$table,'creado_por','INT NULL');
if (!$schemaOnly && !$done) {
    $p->beginTransaction();
    try {
        $p->prepare("UPDATE usuarios SET nombre='Giancarlo Jorge MERINO SANCHO',rol='jefe_emi',grado='ST3.PNP',cargo='JEFE EMI',unidad='DEPIAT' WHERE id=?")->execute([$owner]);
        $p->exec("UPDATE usuarios SET rol='admin' WHERE rol='kayiosama'");
        $p->prepare('UPDATE accidentes SET responsable_id=?, asignado_en=NOW() WHERE responsable_id IS NULL')->execute([$owner]);
        $p->prepare("INSERT INTO auditoria (tabla,registro_id,accidente_id,accion,despues,motivo) SELECT 'accidentes',id,id,'asignacion_inicial',JSON_OBJECT('responsable_id',responsable_id),'Asignación histórica autorizada a Giancarlo Merino Sancho. Autor original no registrado; se conserva creado_por sin atribuir.' FROM accidentes WHERE responsable_id=?")->execute([$owner]);
        $p->exec("INSERT INTO app_migrations(nombre) VALUES('20260924_multiusuario')");
        $p->commit();
    } catch(Throwable $e) { $p->rollBack(); throw $e; }
}
foreach(['oficios','actas','actas_visualizacion','citacion','Manifestacion'] as $table) {
    column($p,$table,'responsable_documento','JSON NULL');
    if (!$schemaOnly) $p->exec("UPDATE `$table` d JOIN accidentes a ON a.id=d.accidente_id JOIN usuarios u ON u.id=a.responsable_id SET d.responsable_documento=JSON_OBJECT('nombre',u.nombre,'grado',u.grado,'cip',u.cip,'cargo',u.cargo,'unidad',u.unidad,'telefono',u.telefono,'email',u.email) WHERE d.responsable_documento IS NULL");
}
$p->exec('ALTER TABLE accidentes MODIFY fecha_accidente DATETIME NULL');
$p->exec('CREATE OR REPLACE VIEW accidentes_activos AS SELECT * FROM accidentes WHERE eliminado_en IS NULL');
require __DIR__.'/multiusuario_views.php';
require __DIR__.'/acceso_cip_schema.php';
require __DIR__.'/multiusuario_triggers.php';
} finally {
    $p->exec('SET @rbac_migration = NULL');
}
if ($schemaOnly) {
    $unassigned = $p->query('SELECT COUNT(*) FROM accidentes WHERE responsable_id IS NULL')->fetchColumn();
    echo "Estructura multiusuario instalada. No se reasignaron expedientes ni se cambiaron roles o contraseñas.\n";
    echo "Expedientes sin responsable: $unassigned. Asignarlos desde una cuenta administradora según corresponda.\n";
} else {
    echo "Migración completa. Responsable histórico: usuario $owner.\n";
}
