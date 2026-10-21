<?php
declare(strict_types=1);
require __DIR__ . '/../db.php';
use App\Support\Access;
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/_(dev|test)$/', $database)) throw new RuntimeException('Ejecutar solo en desarrollo o pruebas.');
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$session = $_SESSION['user'] ?? null;
$actor = (int) $pdo->query('SELECT COALESCE(@actor_id,0)')->fetchColumn();
$tested = 0;
try {
    foreach (Access::PARTICIPANT_DELETE_TABLES as $script => $table) {
        $fixture = $pdo->query("SELECT r.id,r.accidente_id,a.responsable_id FROM `$table` r JOIN accidentes_activos a ON a.id=r.accidente_id JOIN usuarios u ON u.id=a.responsable_id WHERE u.activo=1 AND u.rol='jefe_emi' LIMIT 1")->fetch();
        if (!$fixture) { echo "SKIP: $table sin registro de prueba.\n"; continue; }
        $_SESSION['user'] = ['id'=>(int)$fixture['responsable_id'], 'rol'=>'jefe_emi'];
        $case = Access::participantDeletionCase($script, (int)$fixture['id']);
        $assert($case === (int)$fixture['accidente_id'], 'Resolver el expediente desde el registro.');
        $assert(Access::canDeleteCaseRecord($case), 'Responsable autorizado.');
        $_SESSION['user']['rol']='adjunto';
        $assert(!Access::canDeleteCaseRecord($case), 'Adjunto bloqueado.');
        $_SESSION['user']=['id'=>0,'rol'=>'jefe_emi'];
        $assert(!Access::canDeleteCaseRecord($case), 'Otro jefe bloqueado.');
        foreach ([(int)$fixture['responsable_id']=>true, 0=>false] as $id=>$allowed) {
            $pdo->exec('SET @actor_id='.$id);
            $pdo->beginTransaction();
            $deleted=false;
            try { $q=$pdo->prepare("DELETE FROM `$table` WHERE id=?");$q->execute([$fixture['id']]);$deleted=true; }
            catch (PDOException $e) { if ($e->getCode() !== '45000') throw $e; }
            finally { $pdo->rollBack(); }
            $assert($deleted===$allowed, "Permiso SQL incorrecto: $table");
        }
        ++$tested;
        echo "PASS: $table (eliminaciones revertidas).\n";
    }
    $assert($tested>0, 'Faltan registros de prueba.');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->exec('SET @actor_id='.$actor);
    if ($session===null) unset($_SESSION['user']); else $_SESSION['user']=$session;
}
