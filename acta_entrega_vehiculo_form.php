<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\ActaRepository;
use App\Services\ActaService;

header('Content-Type: text/html; charset=utf-8');

function h($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function person_name(array $row): string {
    return trim(preg_replace('/\s+/u', ' ', trim((string) ($row['nombres'] ?? '') . ' ' . (string) ($row['apellido_paterno'] ?? '') . ' ' . (string) ($row['apellido_materno'] ?? ''))) ?? '');
}

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
:root{--bg:#f6f7fb;--panel:#fff;--text:#172033;--muted:#667085;--line:#dfe3ea;--primary:#b7791f}*{box-sizing:border-box}body{margin:0;padding:20px;background:var(--bg);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:980px;margin:auto;background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:20px}.head,.actions{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px}.full{grid-column:1/-1}label{display:block;font-weight:700;margin-bottom:6px}input,select,textarea{width:100%;padding:10px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--text)}textarea{min-height:100px;resize:vertical}.help{color:var(--muted);font-size:12px;margin-top:5px}.btn{display:inline-flex;padding:9px 13px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}.error{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;padding:10px;border-radius:9px;margin-top:12px}@media(max-width:720px){.grid{grid-template-columns:1fr}.full{grid-column:auto}}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div><h1 style="margin:0;font-size:1.25rem"><?= $id > 0 ? 'Editar' : 'Nueva' ?> acta de entrega de vehiculo</h1><div class="help">Caso #<?= $accidenteId ?>. La culminacion se propone 20 minutos despues del inicio.</div></div>
    <?php if ($embed): ?><button class="btn" type="button" onclick="window.parent.postMessage({type:'acta.close'},'*')">Cerrar</button><?php else: ?><a class="btn" href="accidente_vista_tabs.php?accidente_id=<?= $accidenteId ?>&tab=documentos">Volver</a><?php endif; ?>
  </div>
  <?php foreach ($errors as $error): ?><div class="error"><?= h($error) ?></div><?php endforeach; ?>
  <form method="post" class="grid">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="accidente_id" value="<?= $accidenteId ?>">
    <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
    <div class="full"><label>Tipo de acta</label><input value="Acta de entrega de vehiculo" readonly></div>
    <div>
      <label for="vehicle">Vehiculo entregado / placa de rodaje</label>
      <select id="vehicle" name="involucrado_vehiculo_id" required>
        <option value="">Seleccionar...</option>
        <?php foreach ($ctx['vehicles'] as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>" data-vehicle-id="<?= (int) $vehicle['vehiculo_id'] ?>" <?= (string) $data['involucrado_vehiculo_id'] === (string) $vehicle['id'] ? 'selected' : '' ?>><?= h($vehicle['orden_participacion'] . ' - ' . $vehicle['placa'] . (!empty($vehicle['clase']) ? ' - ' . $vehicle['clase'] : '') . (!empty($vehicle['color']) ? ' - ' . $vehicle['color'] : '')) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label>Estado</label><select name="estado"><?php foreach ($ctx['states'] as $state): ?><option <?= $data['estado'] === $state ? 'selected' : '' ?>><?= h($state) ?></option><?php endforeach; ?></select></div>
    <div>
      <label for="conductor">Conductor del vehiculo</label>
      <select id="conductor" name="conductor_involucrado_persona_id" required>
        <option value="">Seleccionar...</option>
        <?php foreach ($ctx['conductors'] as $person): ?><option value="<?= (int) $person['id'] ?>" data-vehicle-id="<?= (int) ($person['acta_vehiculo_id'] ?? $person['vehiculo_id']) ?>" <?= (string) $data['conductor_involucrado_persona_id'] === (string) $person['id'] ? 'selected' : '' ?>><?= h(person_name($person) . ' - ' . $person['tipo_doc'] . ' ' . $person['num_doc']) ?></option><?php endforeach; ?>
      </select>
      <div class="help">Se muestran conductores vinculados al vehiculo elegido.</div>
    </div>
    <div>
      <label for="owner">Propietario del vehiculo</label>
      <select id="owner" name="propietario_vehiculo_id">
        <option value="" <?= (string) $data['propietario_vehiculo_id'] === '' ? 'selected' : '' ?>>Sin propietario registrado - usar conductor</option>
        <?php foreach ($ctx['owners'] as $owner): ?>
          <?php
            $representanteNombre = trim(preg_replace('/\s+/u', ' ', trim((string) ($owner['representante_nombres'] ?? '') . ' ' . (string) ($owner['representante_apellido_paterno'] ?? '') . ' ' . (string) ($owner['representante_apellido_materno'] ?? ''))) ?? '');
            $ownerLabel = $owner['tipo_propietario'] === 'JURIDICA'
              ? trim((string) $owner['razon_social'] . ' - RUC ' . (string) $owner['ruc'] . ($representanteNombre !== '' ? ' - Representante: ' . $representanteNombre : ' - Sin representante registrado'))
              : person_name($owner) . ' - ' . (string) $owner['tipo_doc'] . ' ' . (string) $owner['num_doc'];
          ?>
          <option value="<?= (int) $owner['id'] ?>" data-involved-vehicle-id="<?= (int) ($owner['acta_vehiculo_inv_id'] ?? $owner['vehiculo_inv_id']) ?>" <?= (string) $data['propietario_vehiculo_id'] === (string) $owner['id'] ? 'selected' : '' ?>><?= h($ownerLabel) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="help">Si no existe propietario registrado, se consignara automaticamente al conductor.</div>
    </div>
    <div><label>Fecha de entrega</label><input type="date" name="fecha_entrega" value="<?= h($data['fecha_entrega']) ?>" required></div>
    <div><label>Hora de inicio</label><input id="start" type="time" name="hora_inicio" value="<?= h($data['hora_inicio']) ?>" required></div>
    <div><label>Hora de culminacion</label><input id="end" type="time" name="hora_culminacion" value="<?= h($data['hora_culminacion']) ?>" required><div class="help">Se actualiza automaticamente a +20 minutos al cambiar el inicio.</div></div>
    <div class="full"><label>Observaciones</label><textarea name="observaciones"><?= h($data['observaciones']) ?></textarea></div>
    <div class="full actions"><span class="help">Los demas vehiculos involucrados en el caso se incluiran al generar el Word.</span><button class="btn primary" type="submit">Guardar acta</button></div>
  </form>
</div>
<script>
const vehicle=document.getElementById('vehicle'), conductor=document.getElementById('conductor'), owner=document.getElementById('owner');
function filterRelations(){
  const selected=vehicle.options[vehicle.selectedIndex], vehicleId=selected?.dataset.vehicleId||'', involvedId=vehicle.value;
  [...conductor.options].forEach((o,i)=>{if(i)o.hidden=!!vehicleId&&o.dataset.vehicleId!==vehicleId});
  [...owner.options].forEach((o,i)=>{if(i)o.hidden=!!involvedId&&o.dataset.involvedVehicleId!==involvedId});
  if(conductor.selectedOptions[0]?.hidden) conductor.value='';
  if(owner.selectedOptions[0]?.hidden) owner.value='';
}
vehicle.addEventListener('change',filterRelations); filterRelations();
document.getElementById('start').addEventListener('change',e=>{if(!e.target.value)return;const [h,m]=e.target.value.split(':').map(Number),d=new Date(2000,0,1,h,m+20);document.getElementById('end').value=String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')});
</script>
</body>
</html>
