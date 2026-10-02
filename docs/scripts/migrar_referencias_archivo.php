<?php
/** Añade las referencias de remisión al pase a Archivo sin alterar expedientes existentes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2).'/bootstrap/app.php';
use App\Support\ArchiveReference;
$p = \App\Database\Database::connection();
$apply = in_array('--apply', $argv, true);
foreach (['informe_remision', 'oficio_remision'] as $column) {
    $check = $p->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='expediente_transferencias' AND column_name=?");
    $check->execute([$column]);
    if ($check->fetchColumn()) continue;
    if (!$apply) { echo "Pendiente: agregar $column. Ejecute con --apply.\n"; continue; }
    $p->exec("ALTER TABLE expediente_transferencias ADD COLUMN `$column` VARCHAR(160) NULL");
    echo "Agregada columna $column.\n";
}
if ($apply) {
    $p->exec('SET @rbac_migration=1');
    try {
        $records=$p->query("SELECT t.id,a.nro_informe_policial FROM expediente_transferencias t JOIN accidentes a ON a.id=t.accidente_id WHERE t.tipo='archivo' AND (t.informe_remision IS NULL OR t.informe_remision='')")->fetchAll(PDO::FETCH_ASSOC);
        $update=$p->prepare('UPDATE expediente_transferencias SET informe_remision=? WHERE id=?');
        $restored=0;
        foreach ($records as $record) {
            try {$report=ArchiveReference::report((string)($record['nro_informe_policial']??''));}
            catch (RuntimeException $error) {continue;}
            $update->execute([$report,(int)$record['id']]);
            $restored++;
        }
    } finally {$p->exec('SET @rbac_migration=NULL');}
    echo "Informes históricos completados: $restored. Los oficios anteriores deben registrarse con su número real.\n";
} else echo "No se modificó la base de datos.\n";
