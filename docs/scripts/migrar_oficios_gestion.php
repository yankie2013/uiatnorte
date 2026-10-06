<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/bootstrap/app.php';
$p = App\Database\Database::connection();
$columns = $p->query('SHOW COLUMNS FROM oficios')->fetchAll(PDO::FETCH_COLUMN);
foreach (['gestion'=>'TINYINT(1) NOT NULL DEFAULT 0','comisaria_id'=>'INT NULL','encargado_id'=>'INT NULL'] as $name=>$type) {
    if (!in_array($name,$columns,true)) $p->exec("ALTER TABLE oficios ADD COLUMN `$name` $type");
}
$p->exec('CREATE OR REPLACE VIEW oficios_activos AS SELECT d.* FROM oficios d LEFT JOIN accidentes a ON a.id=d.accidente_id WHERE d.accidente_id IS NULL OR a.eliminado_en IS NULL');
require __DIR__ . '/oficios_gestion_triggers.php';
echo "PASS: esquema y permisos de oficios de Gestión actualizados.\n";
