<?php
declare(strict_types=1);
require __DIR__ . '/../db.php';
use App\Support\Access;
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/_(dev|test)$/', $database)) throw new RuntimeException('Ejecutar solo en desarrollo o pruebas.');
$task = $pdo->query("SELECT d.id,d.accidente_id,a.responsable_id FROM diligencias_pendientes_activos d JOIN accidentes_activos a ON a.id=d.accidente_id JOIN usuarios u ON u.id=a.responsable_id WHERE u.rol='jefe_emi' AND u.activo=1 LIMIT 1")->fetch();
if (!$task) throw new RuntimeException('Falta una tarea de prueba asignada a JEFE EMI.');
$assert = static function (bool $value, string $message): void { if (!$value) throw new RuntimeException($message); };
try {
    $_SESSION['user'] = ['id' => (int) $task['responsable_id'], 'rol' => 'jefe_emi'];
    $assert(Access::canDeletePendingTask((int) $task['accidente_id']), 'El encargado debe poder eliminar su tarea.');
    $assert(!Access::canDeletePendingTask(0), 'No se autoriza un expediente inexistente.');
    $_SESSION['user']['rol'] = 'adjunto';
    $assert(!Access::canDeletePendingTask((int) $task['accidente_id']), 'Un adjunto no debe eliminar tareas.');
    $_SESSION['user'] = ['id' => 0, 'rol' => 'jefe_emi'];
    $assert(!Access::canDeletePendingTask((int) $task['accidente_id']), 'Otro usuario no debe eliminar tareas.');
    foreach ([(int) $task['responsable_id'] => true, 0 => false] as $actor => $allowed) {
        $pdo->exec('SET @actor_id=' . $actor);
        $pdo->beginTransaction();
        $deleted = false;
        try { $s=$pdo->prepare('DELETE FROM diligencias_pendientes WHERE id=?'); $s->execute([$task['id']]); $deleted=true; }
        catch (PDOException $e) { if ($e->getCode() !== '45000') throw $e; }
        finally { $pdo->rollBack(); }
        $assert($deleted === $allowed, 'La base debe respetar el permiso sobre la tarea.');
    }
    $pdo->exec('SET @actor_id=' . (int) $task['responsable_id']);
    $pdo->beginTransaction();
    $denied = false;
    try { $s=$pdo->prepare('UPDATE accidentes SET eliminado_en=NOW(),eliminado_por=? WHERE id=?'); $s->execute([$task['responsable_id'],$task['accidente_id']]); }
    catch (PDOException $e) { if ($e->getCode() !== '45000') throw $e; $denied=true; }
    finally { $pdo->rollBack(); }
    $assert($denied, 'JEFE EMI no debe eliminar el expediente.');
    echo "PASS: encargado autorizado; otros perfiles bloqueados; expediente protegido. Pruebas revertidas.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->exec('SET @actor_id=0');
    unset($_SESSION['user']);
}
