<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/app.php';
use App\Repositories\CitacionRepository;
use App\Support\Auth;
function checkState(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$pdo = App\Database\Database::connection();
Auth::startSession();
$before = $_SESSION;
$pdo->beginTransaction();
try {
    $actor = $pdo->query("SELECT * FROM usuarios WHERE activo=1 AND rol='admin' ORDER BY id LIMIT 1")->fetch();
    checkState((bool) $actor, 'Se requiere un administrador activo.');
    $_SESSION['user'] = $actor;
    $pdo->exec('SET @actor_id=' . (int) $actor['id']);
    $row = $pdo->query('SELECT * FROM citacion_activos ORDER BY id DESC LIMIT 1')->fetch();
    checkState((bool) $row, 'Se requiere una citación.');
    $repo = new CitacionRepository($pdo);
    foreach (['realizada', 'no-realizada', 'pendiente'] as $state) {
        $repo->updateOutcome((int) $row['accidente_id'], [(int) $row['id']], $state);
        $rows = $repo->searchByAccidente((int) $row['accidente_id'], []);
        $match = array_values(array_filter($rows, fn($r) => (int) $r['id'] === (int) $row['id']));
        checkState(($match[0]['estado_realizacion'] ?? '') === $state, 'El listado debe recuperar el estado guardado.');
    }
    foreach ([['invalido', (int) $row['accidente_id']], ['realizada', -1]] as [$state, $case]) {
        $rejected = false;
        try { $repo->updateOutcome($case, [(int) $row['id']], $state); }
        catch (InvalidArgumentException|RuntimeException $e) { $rejected = true; }
        checkState($rejected, 'Debe rechazar estados inválidos y citaciones de otro expediente.');
    }
    echo "PASS: tres estados persistidos, lectura desde listado y rechazo de estado/expediente inválido.\n";
} finally {
    $pdo->rollBack();
    $_SESSION = $before;
    $pdo->exec('SET @actor_id=' . (int) ($before['user']['id'] ?? 0));
}
