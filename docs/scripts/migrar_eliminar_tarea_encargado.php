<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['database:', 'apply']);
require __DIR__ . '/../../db.php';
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (($options['database'] ?? '') !== $database) { fwrite(STDERR, "Confirme --database=NOMBRE_REAL.\n"); exit(2); }
if (!isset($options['apply'])) { echo "PLAN: permitir eliminar tareas al JEFE EMI responsable; eliminar expedientes sigue reservado al administrador.\n"; exit; }
$pdo->exec('DROP TRIGGER IF EXISTS rbac_diligencias_pendientes_delete');
$pdo->exec("CREATE TRIGGER rbac_diligencias_pendientes_delete BEFORE DELETE ON diligencias_pendientes FOR EACH ROW BEGIN
IF COALESCE(@rbac_migration,0)<>1 THEN
IF NOT (rbac_admin() OR EXISTS(SELECT 1 FROM accidentes a JOIN usuarios u ON u.id=@actor_id AND u.activo=1 AND u.rol='jefe_emi' WHERE a.id=OLD.accidente_id AND a.eliminado_en IS NULL AND a.responsable_id=u.id)) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo el encargado del expediente puede eliminar esta tarea pendiente';
END IF; END IF; END");
echo "Permiso de eliminación de tareas actualizado; auditoría y permisos de expedientes conservados.\n";
