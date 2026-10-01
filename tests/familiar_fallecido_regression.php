<?php
/** Prueba local con datos existentes: todas las escrituras se revierten, incluidos los registros de auditoría. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap/app.php';
$config = app_config('database', []);
if ($config['host'] !== '127.0.0.1' || (int) $config['port'] !== 8889 || $config['name'] !== 'uiatnorte_dev') {
    throw new RuntimeException('Esta prueba solo admite la base local de MAMP.');
}
App\Support\Auth::startSession();
$p = App\Database\Database::connection();
if (!str_starts_with((string) $p->query('SELECT @@datadir')->fetchColumn(), '/Applications/MAMP/')) throw new RuntimeException('No es MAMP.');
$caseId = (int) ($argv[1] ?? 63);
$st = $p->prepare("SELECT a.id,a.responsable_id,ip.id fallecido_id,ip.persona_id FROM accidentes a
 JOIN usuarios u ON u.id=a.responsable_id AND u.rol='jefe_emi' AND u.activo=1
 JOIN involucrados_personas ip ON ip.accidente_id=a.id AND LOWER(ip.lesion) LIKE '%falle%'
 WHERE a.eliminado_en IS NULL AND a.id=? LIMIT 1");
$st->execute([$caseId]);
$fixture = $st->fetch();
if (!$fixture) throw new RuntimeException('Se necesita un accidente local con JEFE EMI y un fallecido.');
$p->exec('SET @actor_id=' . (int) $fixture['responsable_id']);
$family = $p->query('SELECT * FROM personas WHERE id<>' . (int) $fixture['persona_id'] . ' AND NOT rbac_person(id)
 AND NOT EXISTS(SELECT 1 FROM familiar_fallecido ff WHERE ff.accidente_id=' . $caseId . ' AND ff.familiar_persona_id=personas.id)
 ORDER BY id LIMIT 1')->fetch();
if (!$family) throw new RuntimeException('Se necesita una persona compartida para reproducir el fallo.');
$repo = new App\Repositories\FamiliarFallecidoRepository($p);
$service = new App\Services\FamiliarFallecidoService($repo);
$input = ['accidente_id'=>$caseId,'fallecido_inv_id'=>$fixture['fallecido_id'],'familiar_persona_id'=>$family['id'],
 'parentesco'=>'Prueba temporal','observaciones'=>'Se revierte al finalizar','celular'=>$family['celular'],'email'=>$family['email']];
$checks = 0;
function checkFamily(bool $ok, string $message): void {
 global $checks;
 if (!$ok) throw new RuntimeException($message);
 $checks++;
}
$p->beginTransaction();
register_shutdown_function(static function () use ($p): void { if ($p->inTransaction()) $p->rollBack(); });
try {
    $newId = $service->create($input);
    checkFamily($newId > 0, 'No se pudo vincular a la persona compartida.');
    checkFamily($repo->personaById((int)$family['id'])['celular'] === $family['celular'], 'Se alteró el teléfono compartido.');
    $changed = $input;
    $changed['celular'] = '999999991';
    if ((string)$family['celular'] === $changed['celular']) $changed['celular'] = '999999992';
    try { $service->update($newId, $changed); throw new RuntimeException('Se permitió editar una ficha ajena.'); }
    catch (RuntimeException $e) { checkFamily(str_contains($e->getMessage(), 'ficha es compartida'), 'El rechazo de contacto no es claro.'); }
    checkFamily($repo->personaById((int)$family['id'])['celular'] === $family['celular'], 'El rechazo dejó cambios parciales.');
    $wrongActor = (int)$p->query("SELECT id FROM usuarios WHERE activo=1 AND rol='jefe_emi' AND id<>".(int)$fixture['responsable_id'].' LIMIT 1')->fetchColumn();
    $p->exec('SET @actor_id='.$wrongActor);
    try { $service->update($newId, $input); throw new RuntimeException('Se permitió modificar un expediente ajeno.'); }
    catch (InvalidArgumentException $e) { checkFamily(str_contains($e->getMessage(), 'responsable'), 'Falta el rechazo del expediente ajeno.'); }
    $p->exec('SET @actor_id='.(int)$fixture['responsable_id']);
    $form = $service->submittedData($input, $caseId);
    foreach (['tipo_doc','num_doc','nombre_familiar','domicilio','celular','email'] as $key) checkFamily(array_key_exists($key,$form), 'Falta campo tras error: '.$key);
    // Verifica que una operación compuesta fallida deshaga incluso cambios autorizados.
    $admin = (int)$p->query("SELECT id FROM usuarios WHERE activo=1 AND rol='admin' LIMIT 1")->fetchColumn();
    $p->exec('SET @actor_id='.$admin);
    try { $repo->transaction(function () use ($repo,$family,$changed): void {
        $repo->updatePersonaContact((int)$family['id'],$changed['celular'],$family['email']);
        throw new RuntimeException('Fallo deliberado posterior al contacto');
    }); } catch (RuntimeException $e) { checkFamily($e->getMessage()==='Fallo deliberado posterior al contacto', 'Falló la prueba de transacción.'); }
    checkFamily($repo->personaById((int)$family['id'])['celular'] === $family['celular'], 'El rollback no restauró el contacto.');
    $p->rollBack();
    // Reproduce un POST rechazado y comprueba el HTML completo sin imprimir datos personales.
    $st=$p->prepare('SELECT id,nombre,rol,email,auth_version FROM usuarios WHERE id=?');
    $st->execute([$fixture['responsable_id']]); $_SESSION['user']=$st->fetch();
    $p->exec('SET @actor_id='.(int)$fixture['responsable_id']);
    $_GET=['accidente_id'=>$caseId]; $_POST=$input; $_POST['email']='correo-invalido';
    $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['SCRIPT_NAME']='familiar_fallecido_nuevo.php';
    ob_start(); require dirname(__DIR__).'/familiar_fallecido_nuevo.php'; $html=ob_get_clean();
    checkFamily(!str_contains($html,'Warning:')&&!str_contains($html,'Fatal error'), 'El formulario emite avisos tras el rechazo.');
    checkFamily(str_contains($html,'El email no es valido.'), 'No se reprodujo el POST rechazado.');
    checkFamily(str_contains($html,htmlspecialchars($form['nombre_familiar'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')), 'Se perdió la persona seleccionada.');
    echo "OK: $checks comprobaciones. Todas las escrituras de prueba fueron revertidas.\n";
} finally {
    if ($p->inTransaction()) $p->rollBack();
    $p->exec('SET @actor_id=NULL');
    session_destroy();
}
