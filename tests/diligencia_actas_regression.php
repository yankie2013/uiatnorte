<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Repositories\DiligenciaPendienteRepository;
use App\Services\DiligenciaPendienteService;
use App\Support\Auth;
use App\Support\DiligenciaActas;
use App\Support\DiligenciaDocumentLink;

function checkActaLink(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

foreach (['Solicitar' => ['oficio'], 'Remitir' => ['oficio'], 'Citar' => ['citacion'], 'Realizar' => ['actas'], '' => []] as $action => $expected) {
    checkActaLink(array_keys(DiligenciaDocumentLink::optionsForAction($action)) === $expected, 'Opciones incorrectas para ' . $action);
}
$pdo = App\Database\Database::connection();
Auth::startSession();
$before = $_SESSION;
$pdo->beginTransaction();
try {
    $actor = $pdo->query("SELECT * FROM usuarios WHERE activo=1 AND rol='admin' ORDER BY id LIMIT 1")->fetch();
    checkActaLink((bool) $actor, 'Se requiere un administrador activo.');
    $_SESSION['user'] = $actor;
    $pdo->exec('SET @actor_id=' . (int) $actor['id']);
    $service = new DiligenciaPendienteService(new DiligenciaPendienteRepository($pdo));
    $tested = 0;
    foreach (DiligenciaActas::TYPES as $type => $config) {
        $acta = $pdo->query("SELECT ac.id,ac.accidente_id FROM {$config['table']} ac JOIN accidentes_activos a ON a.id=ac.accidente_id LIMIT 1")->fetch();
        if (!$acta) continue;
        $caseId = (int) $acta['accidente_id'];
        $data = $service->defaultData();
        $data['accidente_id'] = $caseId;
        $data['accion'] = 'Realizar';
        $data['contenido'] = 'Prueba de vínculos de actas';
        $id = $service->crear($data);
        $key = $type . ':' . $acta['id'];
        DiligenciaActas::attach($pdo, $id, $caseId, $type, (int) $acta['id']);
        DiligenciaActas::attach($pdo, $id, $caseId, $type, (int) $acta['id']);
        checkActaLink(DiligenciaActas::selected($pdo, $id) === [$key], 'El vínculo debe persistir sin duplicados.');
        checkActaLink($service->detalle($id)['row']['estado'] === 'Pendiente', 'Vincular un acta no debe completar la tarea.');
        $rejected = false;
        try { DiligenciaActas::sync($pdo, $id, $caseId, [$type . ':2147483647']); }
        catch (InvalidArgumentException $e) { $rejected = true; }
        checkActaLink($rejected && DiligenciaActas::selected($pdo, $id) === [$key], 'Un acta inválida no debe borrar vínculos existentes.');
        $rejected = false;
        try { DiligenciaActas::attach($pdo, $id, -1, $type, (int) $acta['id']); }
        catch (InvalidArgumentException $e) { $rejected = true; }
        checkActaLink($rejected, 'Debe rechazar vínculos con otro expediente.');
        DiligenciaActas::sync($pdo, $id, $caseId, []);
        checkActaLink(DiligenciaActas::selected($pdo, $id) === [], 'Debe permitir desvincular sin borrar el acta.');
        checkActaLink(isset(DiligenciaActas::options($pdo, $caseId)[$key]), 'Desvincular debe conservar el acta.');
        DiligenciaActas::sync($pdo, $id, $caseId, [$key, $key]);
        checkActaLink(DiligenciaActas::selected($pdo, $id) === [$key], 'Guardar la selección debe eliminar duplicados.');
        $tested++;
    }
    checkActaLink($tested > 0, 'Se requiere al menos un acta existente.');
    echo "PASS: opciones por acción y vínculos de actas ($tested tipos), sin duplicados ni cambios de estado.\n";
} finally {
    $pdo->rollBack();
    $_SESSION = $before;
    $pdo->exec('SET @actor_id=' . (int) ($before['user']['id'] ?? 0));
}
