<?php
/** Ejecutar con --schema-only --apply. Solo modifica una copia temporal aislada. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
if (!in_array('--schema-only', $argv, true) || !in_array('--apply', $argv, true)) {
    throw new RuntimeException('Use --schema-only --apply');
}
require dirname(__DIR__).'/bootstrap/app.php';
$p = App\Database\Database::connection();
$original = $p->query('SELECT DATABASE()')->fetchColumn();
$test = 'uiat_schema_test_'.bin2hex(random_bytes(5));
try {
    $p->exec("CREATE DATABASE `$test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $tables = $p->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $p->exec("CREATE TABLE `$test`.`$table` LIKE `$original`.`$table`");
        $p->exec("INSERT INTO `$test`.`$table` SELECT * FROM `$original`.`$table`");
    }
    $p->exec("USE `$test`");
    foreach (['app_migrations','auditoria','comunicaciones_guardia','expediente_colaboradores','expediente_transferencias'] as $table) {
        $p->exec("DROP TABLE IF EXISTS `$table`");
    }
    $missing = [
        'accidentes'=>['asignado_en','creado_por','eliminado_en','eliminado_por','motivo_eliminacion','primera_actuacion_en','responsable_id','ubicacion_verificada'],
        'usuarios'=>['auth_version','cargo','cip','grado','must_change_password','telefono','unidad'],
        'personas'=>['creado_por'], 'vehiculos'=>['creado_por'],
    ];
    foreach (['oficios','actas','actas_visualizacion','citacion','Manifestacion'] as $table) $missing[$table]=['responsable_documento'];
    foreach ($missing as $table=>$columns) {
        $existing=$p->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($columns as $column) if (in_array($column,$existing,true)) $p->exec("ALTER TABLE `$table` DROP COLUMN `$column`");
    }
    $snapshots=[];
    $tables=$p->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $columns=$p->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        $select=implode(',',array_map(fn($c)=>"`$c`",$columns));
        $rows=$p->query("SELECT $select FROM `$table`")->fetchAll(PDO::FETCH_NUM);
        $rows=array_map('serialize',$rows); sort($rows);
        $snapshots[$table]=[$select,$rows];
    }
    require dirname(__DIR__).'/docs/scripts/migrar_multiusuario.php';
    foreach ($snapshots as $table=>[$select,$before]) {
        $after=array_map('serialize',$p->query("SELECT $select FROM `$table`")->fetchAll(PDO::FETCH_NUM)); sort($after);
        if ($before!==$after) throw new RuntimeException("Filas originales alteradas: $table");
    }
    foreach (['rbac_admin','rbac_case','rbac_person','rbac_vehicle'] as $function) {
        $st=$p->prepare('SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema=DATABASE() AND routine_name=?');
        $st->execute([$function]);
        if (!$st->fetchColumn()) throw new RuntimeException("Falta $function");
    }
    $p->query('SELECT responsable_id,ubicacion_verificada FROM accidentes_activos LIMIT 1');
    $admin=$p->query("SELECT id FROM usuarios WHERE activo=1 AND rol='admin' LIMIT 1")->fetchColumn();
    $p->exec('SET @actor_id='.(int)$admin);
    if (!(int)$p->query('SELECT rbac_admin()')->fetchColumn()) throw new RuntimeException('Administrador sin permisos');
    $p->exec('SET @actor_id=0');
    if ((int)$p->query('SELECT rbac_admin()')->fetchColumn()) throw new RuntimeException('Actor anónimo autorizado');
    echo "OK: esquema heredado reparado; filas originales idénticas en ".count($snapshots)." tablas; vistas y funciones verificadas.\n";
} finally {
    $p->exec("USE `$original`");
    $p->exec("DROP DATABASE IF EXISTS `$test`");
}
