<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap/app.php';
use App\Support\Access;
$pdo = App\Database\Database::connection();
if (!preg_match('/_(dev|test)$/', (string) $pdo->query('SELECT DATABASE()')->fetchColumn())) throw new RuntimeException('Solo para desarrollo/pruebas.');
$session = $_SESSION['user'] ?? null;
$actor = (int) $pdo->query('SELECT COALESCE(@actor_id,0)')->fetchColumn();
$assert = static function (bool $ok, string $message): void {if (!$ok) throw new RuntimeException($message);};
try {
    foreach (Access::CASE_RECORD_DELETE_TABLES as $script => $table) {
        $row = $pdo->query("SELECT r.id,r.accidente_id,a.responsable_id FROM `$table` r JOIN accidentes_activos a ON a.id=r.accidente_id JOIN usuarios u ON u.id=a.responsable_id AND u.activo=1 AND u.rol='jefe_emi' LIMIT 1")->fetch();
        if (!$row) {echo "SKIP: $table sin registros.\n"; continue;}
        $_SESSION['user'] = ['id'=>(int)$row['responsable_id'], 'rol'=>'jefe_emi'];
        $case = Access::caseRecordDeletionCase($script, (int) $row['id']);
        $assert($case === (int) $row['accidente_id'] && Access::canDeleteCaseRecord($case), 'Responsable bloqueado.');
        $_SESSION['user']['rol'] = 'adjunto';
        $assert(!Access::canDeleteCaseRecord($case), 'Adjunto no debe eliminar.');
        $_SESSION['user'] = ['id'=>0, 'rol'=>'jefe_emi'];
        $assert(!Access::canDeleteCaseRecord($case), 'Otro encargado no debe eliminar.');
        $assert(Access::caseRecordDeletionCase($script, PHP_INT_MAX) === 0, 'Registro inexistente aceptado.');
        foreach ([(int)$row['responsable_id']=>true, 0=>false] as $user=>$allowed) {
            $pdo->exec('SET @actor_id=' . $user);
            $pdo->beginTransaction(); $deleted = false;
            try {$stmt = $pdo->prepare("DELETE FROM `$table` WHERE id=?"); $stmt->execute([$row['id']]); $deleted = true;}
            catch (PDOException $e) {if ($e->getCode() !== '45000') throw $e;}
            finally {$pdo->rollBack();}
            $assert($deleted === $allowed, "Permiso SQL incorrecto: $table");
        }
        $pdo->exec('SET @actor_id=' . (int) $row['responsable_id']);
        $pdo->beginTransaction(); $blocked = false;
        try {$stmt=$pdo->prepare('UPDATE accidentes SET eliminado_en=NOW(),eliminado_por=? WHERE id=?'); $stmt->execute([$row['responsable_id'],$case]);}
        catch (PDOException $e) {if ($e->getCode() !== '45000') throw $e; $blocked=true;}
        finally {$pdo->rollBack();}
        $assert($blocked, 'El encargado no puede eliminar el expediente.');
        echo "PASS: $table, propietario permitido, ajenos bloqueados y expediente protegido (cambios revertidos).\n";
    }
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->exec('SET @actor_id=' . $actor);
    if ($session === null) unset($_SESSION['user']); else $_SESSION['user'] = $session;
}
