<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../db.php';
// Solo modifica permisos de participantes y citaciones; conserva auditoría.
$tables = array_values(\App\Support\Access::PARTICIPANT_DELETE_TABLES);
$owner = "EXISTS(SELECT 1 FROM accidentes a JOIN usuarios u ON u.id=@actor_id AND u.activo=1 AND u.rol='jefe_emi' WHERE a.id=OLD.accidente_id AND a.eliminado_en IS NULL AND a.responsable_id=u.id)";
$definitions = [];
foreach ($tables as $table) {
    $name = 'rbac_' . $table . '_delete';
    $row = $pdo->query("SHOW CREATE TRIGGER `$name`")->fetch(PDO::FETCH_ASSOC);
    $original = $row['SQL Original Statement'];
    if (str_contains($original, $owner)) continue;
    $updated = str_replace('AND rbac_admin()', "AND (rbac_admin() OR $owner)", $original, $count);
    if ($count !== 1) throw new RuntimeException("Definición no reconocida: $name. No se ha modificado ningún permiso.");
    $definitions[$name] = [$original, $updated];
}
foreach ($definitions as $name => [$original, $updated]) {
    $pdo->exec("DROP TRIGGER `$name`");
    try { $pdo->exec($updated); }
    catch (Throwable $e) { $pdo->exec($original); throw $e; }
}
echo "Permisos de eliminación de participantes y citaciones actualizados. Auditoría conservada.\n";
