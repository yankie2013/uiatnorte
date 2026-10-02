<?php
/** Instala la recepción inicial y el pase a Archivo en una base multiusuario existente. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2).'/bootstrap/app.php';
$p = \App\Database\Database::connection();
$exists = $p->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='expediente_transferencias' AND column_name='tipo'")->fetchColumn();
if (!$exists) $p->exec("ALTER TABLE expediente_transferencias ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'investigacion'");
foreach (['informe_remision', 'oficio_remision'] as $column) {
    $check = $p->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='expediente_transferencias' AND column_name=?");
    $check->execute([$column]);
    if (!$check->fetchColumn()) $p->exec("ALTER TABLE expediente_transferencias ADD COLUMN `$column` VARCHAR(160) NULL");
}
$p->exec('SET @rbac_migration=1');
try { require __DIR__.'/multiusuario_triggers.php'; }
finally { $p->exec('SET @rbac_migration=NULL'); }
echo "Recepción inicial y archivo instalados.\n";
