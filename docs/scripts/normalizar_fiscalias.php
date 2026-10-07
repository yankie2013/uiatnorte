<?php
// Normaliza nombres existentes sin cambiar IDs ni relaciones con fiscales/expedientes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2).'/bootstrap/app.php';
use App\Support\FiscaliaSelection;
$p = App\Database\Database::connection();
$changes = [];
foreach ($p->query('SELECT id,nombre FROM fiscalia') as $row) {
    $selection = FiscaliaSelection::describe($row['nombre']);
    if ($selection === null || $selection['name'] === $row['nombre']) continue;
    if (mb_strlen($selection['name']) > 150) throw new RuntimeException('Nombre demasiado largo.');
    $changes[] = ['id'=>(int)$row['id'],'antes'=>$row['nombre'],'despues'=>$selection['name']];
}
if (!in_array('--apply', $argv, true)) { echo json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"; exit; }
if ($changes === []) { echo "Nombres ya normalizados.\n"; exit; }
$backup = dirname(__DIR__).'/sql/fiscalias_nombres_anteriores.json';
if (file_exists($backup)) throw new RuntimeException('Ya existe el respaldo; revisar antes de ejecutar otra migración.');
if (file_put_contents($backup, json_encode($changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n") === false) throw new RuntimeException('No se pudo guardar el respaldo.');
try {
    $p->beginTransaction();
    $p->exec('SET @rbac_migration=1');
    $update = $p->prepare('UPDATE fiscalia SET nombre=? WHERE id=? AND nombre=?');
    foreach ($changes as $row) $update->execute([$row['despues'],$row['id'],$row['antes']]);
    $p->commit();
    echo count($changes)." nombres normalizados; IDs y vínculos conservados.\n";
} catch (Throwable $e) { if ($p->inTransaction()) $p->rollBack(); throw $e; }
finally { $p->exec('SET @rbac_migration=0'); }
