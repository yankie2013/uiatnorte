<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/app.php';
use App\Repositories\CitacionRepository;
use App\Services\CitacionService;
function checkCompany(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$pdo = App\Database\Database::connection();
App\Support\Auth::startSession();
$before = $_SESSION;
$pdo->beginTransaction();
try {
    $actor = $pdo->query("SELECT * FROM usuarios WHERE activo=1 AND rol='admin' ORDER BY id LIMIT 1")->fetch();
    checkCompany((bool) $actor, 'Se requiere administrador activo.');
    $_SESSION['user'] = $actor;
    $pdo->exec('SET @actor_id=' . (int) $actor['id']);
    $vehicle = $pdo->query('SELECT id, accidente_id FROM involucrados_vehiculos_activos ORDER BY id DESC LIMIT 1')->fetch();
    checkCompany((bool) $vehicle, 'Se requiere vehículo vinculado.');
    $person = $pdo->query('SELECT id FROM personas ORDER BY id DESC LIMIT 1')->fetchColumn();
    $repo = new CitacionRepository($pdo);
    $service = new CitacionService($repo);
    foreach ([null, $person] as $representative) {
        $pdo->exec("SAVEPOINT company_fixture");
        $st = $pdo->prepare("INSERT INTO propietario_vehiculo (accidente_id, vehiculo_inv_id, tipo_propietario, ruc, razon_social, domicilio_fiscal, representante_persona_id) VALUES (?, ?, 'JURIDICA', '20999999991', 'Empresa de prueba de citación S.A.C.', 'Domicilio fiscal de prueba', ?)");
        $st->execute([$vehicle['accidente_id'], $vehicle['id'], $representative]);
        $owner = (int) $pdo->lastInsertId();
        $options = $service->formContext((int) $vehicle['accidente_id'])['personas'];
        $matches = array_values(array_filter($options, fn($p) => $p['fuente'] === 'PRO' && (int) $p['fuente_id'] === $owner));
        checkCompany(count($matches) === 1, 'La empresa debe aparecer una sola vez.');
        checkCompany($representative || ($matches[0]['tipo_doc'] === 'RUC' && $matches[0]['num_doc'] === '20999999991'), 'Debe mostrar RUC cuando no hay representante.');
        checkCompany(str_contains($matches[0]['extra'], 'Empresa de prueba de citación S.A.C.'), 'Debe identificar la empresa.');
        $quick = $service->quickContext((int) $vehicle['accidente_id'], 'PRO:' . $owner);
        checkCompany(!$representative || $quick['defaults']['en_calidad'] === 'Representante legal', 'Debe sugerir la calidad de representante legal.');
        $created = $service->create((int) $vehicle['accidente_id'], ['persona' => 'PRO:' . $owner, 'en_calidad' => 'Propietario del vehículo', 'fecha' => '2026-10-20', 'hora' => '10:00', 'lugar' => 'Sede UIAT', 'motivo' => 'Prueba de citación de empresa']);
        $citation = $service->detail($created['id']);
        if (!$representative) {
        checkCompany($citation['persona_nombres'] === 'Empresa de prueba de citación S.A.C.' && $citation['persona_doc_tipo'] === 'RUC', 'Debe guardar razón social y tipo RUC.');
        checkCompany($citation['persona_doc_num'] === '20999999991' && $citation['persona_domicilio'] === 'Domicilio fiscal de prueba', 'Debe conservar RUC y domicilio fiscal.');
        } else {
            $rep = $pdo->query('SELECT * FROM personas WHERE id=' . (int) $representative)->fetch();
            checkCompany($matches[0]['relacion'] === 'Representante legal', 'Debe mostrar el rol del representante.');
            checkCompany($citation['persona_nombres'] === $rep['nombres'] && $citation['persona_apep'] === $rep['apellido_paterno'], 'Debe citar al representante por su nombre.');
            checkCompany($citation['persona_doc_num'] === $rep['num_doc'], 'Debe guardar documento del representante.');
        }
        $pdo->exec("ROLLBACK TO SAVEPOINT company_fixture");
    }
    echo "PASS: empresa con/sin representante visible y citación con razón social, RUC y domicilio fiscal.\n";
} finally {
    $pdo->rollBack();
    $_SESSION = $before;
    $pdo->exec('SET @actor_id=' . (int) ($before['user']['id'] ?? 0));
}
