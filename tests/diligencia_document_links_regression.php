<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Repositories\DiligenciaPendienteRepository;
use App\Services\DiligenciaPendienteService;
use App\Support\Auth;
use App\Support\DiligenciaDocumentLink;

function checkDiligenciaLink(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

$pdo = App\Database\Database::connection();
Auth::startSession();
$before = $_SESSION;
$pdo->beginTransaction();
try {
    $actor = $pdo->query("SELECT * FROM usuarios WHERE activo=1 AND rol='admin' ORDER BY id LIMIT 1")->fetch();
    checkDiligenciaLink((bool) $actor, 'Se requiere un administrador activo.');
    $_SESSION['user'] = $actor;
    $pdo->exec('SET @actor_id=' . (int) $actor['id']);
    $case = $pdo->query('SELECT o.accidente_id, o.id AS oficio_id, c.id AS citacion_id FROM oficios_activos o JOIN citacion_activos c ON c.accidente_id=o.accidente_id JOIN accidentes_activos a ON a.id=o.accidente_id LIMIT 1')->fetch();
    checkDiligenciaLink((bool) $case, 'Se requiere un expediente con oficio y citación.');
    $caseId = (int) $case['accidente_id'];
    $service = new DiligenciaPendienteService(new DiligenciaPendienteRepository($pdo));
    $data = $service->defaultData();
    $data['accidente_id'] = $caseId;
    $data['accion'] = 'Solicitar';
    $data['contenido'] = 'Prueba de vinculación de documentos';
    $id = $service->crear($data);
    DiligenciaDocumentLink::attach($pdo, $id, $caseId, 'citacion', (int) $case['citacion_id']);
    DiligenciaDocumentLink::attach($pdo, $id, $caseId, 'oficio', (int) $case['oficio_id']);
    DiligenciaDocumentLink::attach($pdo, $id, $caseId, 'citacion', (int) $case['citacion_id']);
    $detail = $service->detalle($id);
    checkDiligenciaLink($detail['row']['accion'] === 'Solicitar', 'Vincular documentos debe conservar la acción.');
    checkDiligenciaLink((int) $detail['row']['oficio_id'] === (int) $case['oficio_id'], 'Debe conservar el oficio al vincular citaciones.');
    checkDiligenciaLink($detail['citacion_ids'] === [(int) $case['citacion_id']], 'Debe conservar la citación sin duplicarla al vincular el oficio.');
    checkDiligenciaLink($detail['row']['estado'] === 'Pendiente', 'Vincular documentos no debe marcar la diligencia como realizada.');
    foreach (['Solicitar', 'Remitir', 'Citar', 'Realizar'] as $action) {
        $data = $service->defaultData($service->detalle($id)['row']);
        $data['accion'] = $action;
        $service->actualizar($id, $data);
        checkDiligenciaLink($service->detalle($id)['row']['accion'] === $action, 'Debe guardar y recuperar cada acción.');
    }
    $data['accion'] = 'Inválida';
    $rejectedAction = false;
    try { $service->actualizar($id, $data); }
    catch (InvalidArgumentException $e) { $rejectedAction = true; }
    checkDiligenciaLink($rejectedAction, 'Debe rechazar acciones fuera del selector.');
    $service->cambiarEstado($id, 'Realizado');
    checkDiligenciaLink($service->detalle($id)['row']['estado'] === 'Realizado', 'Debe permitir marcar como realizada sin documento de resultado.');
    $service->cambiarEstado($id, 'Pendiente');
    checkDiligenciaLink($service->detalle($id)['row']['estado'] === 'Pendiente', 'Debe permitir volver a pendiente.');
    $rejected = false;
    try {
        DiligenciaDocumentLink::attach($pdo, $id, -1, 'oficio', (int) $case['oficio_id']);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }
    checkDiligenciaLink($rejected, 'Debe rechazar vínculos con otro expediente.');
    $other = $pdo->query('SELECT id FROM oficios_activos WHERE accidente_id <> ' . $caseId . ' LIMIT 1')->fetchColumn();
    if ($other) {
        $rejected = false;
        try {
            DiligenciaDocumentLink::attach($pdo, $id, $caseId, 'oficio', (int) $other);
        } catch (InvalidArgumentException $e) {
            $rejected = true;
        }
        checkDiligenciaLink($rejected, 'Debe rechazar documentos ajenos al expediente.');
    }
    echo "PASS: vínculos conservados, sin duplicados, estado pendiente y rechazo de documentos de otro expediente.\n";
} finally {
    $pdo->rollBack();
    $_SESSION = $before;
    $pdo->exec('SET @actor_id=' . (int) ($before['user']['id'] ?? 0));
}
