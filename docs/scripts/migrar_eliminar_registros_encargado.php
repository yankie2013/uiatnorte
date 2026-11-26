<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {http_response_code(404); exit;}
require __DIR__ . '/../../bootstrap/app.php';
$pdo = \App\Database\Database::connection();
$apply = in_array('--apply', $argv, true);
$owner = "rbac_admin() OR EXISTS(SELECT 1 FROM accidentes a JOIN usuarios u ON u.id=@actor_id AND u.activo=1 AND u.rol='jefe_emi' WHERE a.id=OLD.accidente_id AND a.eliminado_en IS NULL AND a.responsable_id=u.id)";
$definitions = [];
foreach (array_values(\App\Support\Access::CASE_RECORD_DELETE_TABLES) as $table) {
    $name = 'rbac_' . $table . '_delete';
    $row = $pdo->query("SHOW CREATE TRIGGER `$name`")->fetch(PDO::FETCH_ASSOC);
    $original = $row['SQL Original Statement'];
    if (str_contains($original, $owner)) {echo "$table: permisos actualizados.\n"; continue;}
    $pattern = "/IF NOT COALESCE\\(\\(.*?\\),0\\) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sin permiso para modificar este registro';/s";
    $updated = preg_replace($pattern, "IF NOT COALESCE(($owner),0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sin permiso para modificar este registro';", $original, 1, $count);
    if ($count !== 1) throw new RuntimeException("Trigger no reconocido: $name. No se aplicaron cambios.");
    $definitions[$name] = [$original, $updated];
}
if (!$apply) {echo 'Pendientes: ' . count($definitions) . ". Ejecuta con --apply para actualizar permisos, conservando auditoría y protección del expediente.\n"; exit;}
foreach ($definitions as $name => [$original, $updated]) {
    $pdo->exec("DROP TRIGGER `$name`");
    try {$pdo->exec($updated);} catch (Throwable $e) {$pdo->exec($original); throw $e;}
    echo "$name: actualizado.\n";
}
echo "Permisos de registros actualizados. La eliminación del expediente sigue reservada al administrador.\n";
