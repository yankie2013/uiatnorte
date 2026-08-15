<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\ActaRepository;
use App\Services\ActaService;

header('Content-Type: text/html; charset=utf-8');

function h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'); }

$repository = new ActaRepository($pdo);
$service = new ActaService($repository);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$accidenteId = (int) ($_GET['accidente_id'] ?? $_POST['accidente_id'] ?? 0);
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$row = $id > 0 ? $repository->find($id) : null;
if ($id > 0 && !$row) {
    http_response_code(404);
    exit('Acta no encontrada');
}
if ($row) {
    $accidenteId = (int) $row['accidente_id'];
}
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta accidente_id');
}

$data = $service->defaults($row, $accidenteId);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $service->defaults($_POST, $accidenteId);
    try {
        if ($id > 0) {
            $service->update($id, $_POST);
        } else {
            $id = $service->create($_POST);
        }
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>window.parent.postMessage({type:"acta.saved"},"*")</script><body style="font:14px system-ui;padding:20px">Acta guardada...</body>';
            exit;
        }
        header('Location: accidente_vista_tabs.php?accidente_id=' . $accidenteId . '&tab=documentos');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
$ctx = $service->context($accidenteId);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $id > 0 ? 'Editar' : 'Nueva' ?> acta de entrega de vehiculo</title>
<style>
:root{--bg:#f6f7fb;--panel:#fff;--text:#172033;--muted:#667085;--line:#dfe3ea;--primary:#b7791f;--soft:#fff8e7}*{box-sizing:border-box}body{margin:0;padding:20px;background:var(--bg);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:760px;margin:auto;background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:22px}.head,.actions{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:18px}.full{grid-column:1/-1}label{display:block;font-weight:700;margin-bottom:6px}input:not([type=checkbox]),select{width:100%;padding:12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--text);font-size:15px}.help{color:var(--muted);font-size:12px;margin-top:5px}.auto,.confirm{grid-column:1/-1;background:var(--soft);border:1px solid #f0d99a;border-radius:11px;padding:13px;color:#694b00}.auto strong{display:block;margin-bottom:4px}.confirm label{display:flex;align-items:flex-start;gap:10px;margin:0}.confirm input{margin-top:3px}.btn{display:inline-flex;padding:9px 13px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}.error{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;padding:10px;border-radius:9px;margin-top:12px}@media(max-width:720px){.grid{grid-template-columns:1fr}.full,.auto,.confirm{grid-column:auto}}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div><h1 style="margin:0;font-size:1.25rem"><?= $id > 0 ? 'Editar' : 'Nueva' ?> acta de entrega de vehiculo</h1><div class="help">Selecciona la placa e indica la hora de entrega.</div></div>
    <?php if ($embed): ?><button class="btn" type="button" onclick="window.parent.postMessage({type:'acta.close'},'*')">Cerrar</button><?php else: ?><button class="btn" type="button" onclick="if(window.history.length>1){window.history.back();}else{window.location.href='accidente_vista_tabs.php?accidente_id=<?= (int) $accidenteId ?>&tab=documentos&subtab=actas';}">← Volver atrás</button><?php endif; ?>
  </div>
  <?php foreach ($errors as $error): ?><div class="error"><?= h($error) ?></div><?php endforeach; ?>
  <form method="post" class="grid">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="accidente_id" value="<?= $accidenteId ?>">
    <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
    <div class="full">
      <label for="vehicle">Placa de rodaje del vehiculo</label>
      <select id="vehicle" name="involucrado_vehiculo_id" required>
        <option value="">Seleccionar...</option>
        <?php foreach ($ctx['vehicles'] as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>" data-has-owner="<?= !empty($vehicle['tiene_propietario']) ? '1' : '0' ?>" <?= (string) $data['involucrado_vehiculo_id'] === (string) $vehicle['id'] ? 'selected' : '' ?>><?= h($vehicle['orden_participacion'] . ' - ' . $vehicle['placa'] . (!empty($vehicle['clase']) ? ' - ' . $vehicle['clase'] : '') . (!empty($vehicle['color']) ? ' - ' . $vehicle['color'] : '')) ?></option><?php endforeach; ?>
      </select>
      <div class="help">Los combinados vehiculares aparecen con ambas placas separadas por /.</div>
    </div>
    <div class="full"><label for="start">Hora de entrega</label><input id="start" type="time" name="hora_inicio" value="<?= h($data['hora_inicio']) ?>" required></div>
    <div class="confirm" id="owner-confirm" hidden>
      <label><input id="use-driver-owner" type="checkbox" name="usar_conductor_como_propietario" value="1" <?= (int) ($_POST['usar_conductor_como_propietario'] ?? 0) === 1 ? 'checked' : '' ?>><span>Este vehiculo no tiene propietario registrado. Deseo consignar al conductor como propietario para esta acta.</span></label>
    </div>
    <div class="auto"><strong>Datos automaticos</strong>El sistema completara conductor, propietario registrado, fecha de hoy, hora de culminacion (+20 minutos) y todos los datos del vehiculo. Si no existe propietario, deberas autorizar el uso del conductor.</div>
    <div class="full actions"><span class="help">Caso #<?= $accidenteId ?></span><button class="btn primary" type="submit">Guardar acta</button></div>
  </form>
</div>
<script>
const vehicle=document.getElementById('vehicle'), confirmBox=document.getElementById('owner-confirm'), confirmCheck=document.getElementById('use-driver-owner');
function updateOwnerConfirmation(){
  const selected=vehicle.options[vehicle.selectedIndex];
  const needsConfirmation=!!vehicle.value&&selected?.dataset.hasOwner==='0';
  confirmBox.hidden=!needsConfirmation;
  confirmCheck.required=needsConfirmation;
  if(!needsConfirmation) confirmCheck.checked=false;
}
vehicle.addEventListener('change',updateOwnerConfirmation);
updateOwnerConfirmation();
</script>
</body>
</html>
