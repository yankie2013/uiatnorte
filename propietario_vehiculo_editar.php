<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\PropietarioVehiculoRepository;
use App\Services\PropietarioVehiculoService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new PropietarioVehiculoService(new PropietarioVehiculoRepository($pdo));
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id');
}

$row = $service->detalle($id);
if ($row === null) {
    http_response_code(404);
    exit('Registro no encontrado.');
}

$accidenteId = (int) ($row['accidente_id'] ?? 0);
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'propietario_vehiculo_listar.php?accidente_id=' . $accidenteId;
}
$error = '';
$data = $service->defaultData($row, $accidenteId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'accidente_id' => $accidenteId,
        'vehiculo_inv_id' => $_POST['vehiculo_inv_id'] ?? '',
        'tipo_propietario' => $_POST['tipo_propietario'] ?? 'NATURAL',
        'propietario_persona_id' => $_POST['propietario_persona_id'] ?? '',
        'representante_persona_id' => $_POST['representante_persona_id'] ?? '',
        'ruc' => $_POST['ruc'] ?? '',
        'razon_social' => $_POST['razon_social'] ?? '',
        'domicilio_fiscal' => $_POST['domicilio_fiscal'] ?? '',
        'rol_legal' => $_POST['rol_legal'] ?? '',
        'observaciones' => $_POST['observaciones'] ?? '',
        'celular_nat' => $_POST['celular_nat'] ?? '',
        'email_nat' => $_POST['email_nat'] ?? '',
        'celular_rep' => $_POST['celular_rep'] ?? '',
        'email_rep' => $_POST['email_rep'] ?? '',
    ];

    try {
        $service->update($id, $data);
        header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'ok=updated');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$ctx = $service->formContext($accidenteId);
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar propietario de vehiculo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#ca8a04;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#facc15;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1120px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(202,138,4,.12);color:var(--primary);border:1px solid rgba(202,138,4,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#111827;border-color:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.field{display:flex;flex-direction:column;gap:6px}.label{font-size:12px;color:var(--muted);font-weight:700}.small{color:var(--muted);font-size:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}textarea{min-height:96px;resize:vertical}.search-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.search-row input{flex:1}.search-row button{white-space:nowrap}.suggest-list{margin-top:6px;border:1px solid var(--border);border-radius:12px;display:none;overflow:hidden}.suggest-item{padding:10px 12px;border-bottom:1px solid var(--border);cursor:pointer;background:var(--card)}.suggest-item:last-child{border-bottom:none}.suggest-item:hover{background:rgba(202,138,4,.08)}.segment{padding:14px;border:1px solid var(--border);border-radius:14px;background:rgba(148,163,184,.06)}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}.header-card{margin-bottom:14px}.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:9999;padding:16px}.modal{width:960px;max-width:96%;height:86vh;background:var(--card);border-radius:12px;overflow:hidden;display:flex;flex-direction:column}.modal header{padding:10px 12px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}.modal .body{flex:1}.modal iframe{border:0;width:100%;height:100%}@media(max-width:820px){.c6{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Propietario de vehiculo <span class="badge">Editar</span></h1>
      <div class="small">Registro #<?= (int) $id ?> - Accidente ID: <?= (int) $accidenteId ?></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="<?= h($returnTo) ?>">Volver</a>
      <a class="btn" href="propietario_vehiculo_leer.php?id=<?= (int) $id ?>&return_to=<?= urlencode($returnTo) ?>">Ver detalle</a>
      <button class="btn primary" type="submit" form="frmProp">Guardar cambios</button>
    </div>
  </div>

  <?php if ($ctx['accidente']): ?>
    <div class="card header-card">
      <strong>Accidente #<?= (int) $ctx['accidente']['id'] ?></strong>
      <div class="small" style="margin-top:4px;">SIDPOL: <?= h((string) ($ctx['accidente']['sidpol'] ?? 'Sin SIDPOL')) ?> - Registro: <?= h((string) ($ctx['accidente']['registro_sidpol'] ?? 'Sin registro')) ?> - Fecha: <?= h((string) ($ctx['accidente']['fecha_accidente'] ?? 'Sin fecha')) ?> - Lugar: <?= h((string) ($ctx['accidente']['lugar'] ?? 'Sin lugar')) ?></div>
    </div>
  <?php endif; ?>

  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <form method="post" class="card" id="frmProp" autocomplete="off">
    <input type="hidden" name="accidente_id" value="<?= (int) $accidenteId ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <input type="hidden" name="propietario_persona_id" id="propietario_persona_id" value="<?= h((string) $data['propietario_persona_id']) ?>">
    <input type="hidden" name="representante_persona_id" id="representante_persona_id" value="<?= h((string) $data['representante_persona_id']) ?>">

    <div class="grid">
      <div class="c12 field"><label class="label" for="vehiculo_inv_id">Vehiculo del accidente*</label><select name="vehiculo_inv_id" id="vehiculo_inv_id" required><option value="">Selecciona</option><?php foreach ($ctx['vehiculos'] as $vehiculo): ?><?php $label = trim((string) (($vehiculo['orden_participacion'] ?? '') !== '' ? $vehiculo['orden_participacion'] . ' - ' : '') . ($vehiculo['placa'] ?? 'SIN PLACA')); ?><option value="<?= (int) $vehiculo['inv_id'] ?>" <?= (string) $data['vehiculo_inv_id'] === (string) $vehiculo['inv_id'] ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
      <div class="c12 field"><label class="label" for="tipo_propietario">Tipo de propietario*</label><select name="tipo_propietario" id="tipo_propietario"><?php foreach ($ctx['tipos'] as $tipo): ?><option value="<?= h($tipo) ?>" <?= $data['tipo_propietario'] === $tipo ? 'selected' : '' ?>><?= h($tipo) ?></option><?php endforeach; ?></select></div>

      <div class="c12 segment" id="bloque_natural">
        <div class="field"><label class="label">Propietario natural*</label><div class="search-row"><input type="text" id="dni_nat" value="<?= h((string) ($row['dni_nat'] ?? '')) ?>" placeholder="DNI, nombre o apellidos del propietario"><button type="button" class="btn" id="btnBuscarNat">Buscar</button><button type="button" class="btn" id="btnEditarNat">Editar persona</button><button type="button" class="btn" id="btnLimpiarNat">Limpiar</button></div><div class="suggest-list" id="sug_nat"></div><div class="small" id="info_nat"><?= $data['propietario_persona_id'] !== '' ? '<b>' . h(trim((string) (($row['ap_nat'] ?? '') . ' ' . ($row['am_nat'] ?? '') . ' ' . ($row['no_nat'] ?? '')))) . '</b>' : '-' ?></div></div>
        <div class="grid" style="margin-top:12px;"><div class="c12 field"><label class="label">Nombre completo</label><input type="text" id="nat_nombre" value="<?= h(trim((string) (($row['ap_nat'] ?? '') . ' ' . ($row['am_nat'] ?? '') . ' ' . ($row['no_nat'] ?? '')))) ?>" readonly></div><div class="c12 field"><label class="label">Domicilio</label><input type="text" id="nat_dom" value="<?= h((string) ($row['dom_nat'] ?? '')) ?>" readonly></div><div class="c6 field"><label class="label">Celular</label><input type="text" name="celular_nat" id="nat_cel" value="<?= h((string) $data['celular_nat']) ?>"></div><div class="c6 field"><label class="label">Email</label><input type="email" name="email_nat" id="nat_email" value="<?= h((string) $data['email_nat']) ?>"></div></div>
      </div>

      <div class="c12 segment" id="bloque_juridica" style="display:none;">
        <div class="grid"><div class="c6 field"><label class="label">RUC*</label><input type="text" name="ruc" id="ruc" maxlength="11" value="<?= h((string) $data['ruc']) ?>"></div><div class="c6 field"><label class="label">Rol legal</label><input type="text" name="rol_legal" value="<?= h((string) $data['rol_legal']) ?>"></div><div class="c12 field"><label class="label">Razon social*</label><input type="text" name="razon_social" value="<?= h((string) $data['razon_social']) ?>"></div><div class="c12 field"><label class="label">Domicilio fiscal</label><input type="text" name="domicilio_fiscal" value="<?= h((string) $data['domicilio_fiscal']) ?>"></div></div>
        <div class="field" style="margin-top:12px;"><label class="label">Representante legal*</label><div class="search-row"><input type="text" id="dni_rep" value="<?= h((string) ($row['dni_rep'] ?? '')) ?>" placeholder="DNI, nombre o apellidos del representante"><button type="button" class="btn" id="btnBuscarRep">Buscar</button><button type="button" class="btn" id="btnEditarRep">Editar persona</button><button type="button" class="btn" id="btnLimpiarRep">Limpiar</button></div><div class="suggest-list" id="sug_rep"></div><div class="small" id="info_rep"><?= $data['representante_persona_id'] !== '' ? '<b>' . h(trim((string) (($row['ap_rep'] ?? '') . ' ' . ($row['am_rep'] ?? '') . ' ' . ($row['no_rep'] ?? '')))) . '</b>' : '-' ?></div></div>
        <div class="grid" style="margin-top:12px;"><div class="c12 field"><label class="label">Nombre completo</label><input type="text" id="rep_nombre" value="<?= h(trim((string) (($row['ap_rep'] ?? '') . ' ' . ($row['am_rep'] ?? '') . ' ' . ($row['no_rep'] ?? '')))) ?>" readonly></div><div class="c12 field"><label class="label">Domicilio</label><input type="text" id="rep_dom" value="<?= h((string) ($row['dom_rep'] ?? '')) ?>" readonly></div><div class="c6 field"><label class="label">Celular</label><input type="text" name="celular_rep" id="rep_cel" value="<?= h((string) $data['celular_rep']) ?>"></div><div class="c6 field"><label class="label">Email</label><input type="email" name="email_rep" id="rep_email" value="<?= h((string) $data['email_rep']) ?>"></div></div>
      </div>

      <div class="c12 field"><label class="label" for="observaciones">Observaciones</label><textarea id="observaciones" name="observaciones"><?= h((string) $data['observaciones']) ?></textarea></div>
    </div>

    <div class="actions"><a class="btn" href="<?= h($returnTo) ?>">Cancelar</a><button class="btn primary" type="submit">Guardar cambios</button></div>
  </form>
</div>

<div class="modal-backdrop" id="personaModal"><div class="modal"><header><h3 id="personaModalTitle">Persona</h3><button class="btn" type="button" id="btnPersonaCerrar">Cerrar</button></header><div class="body"><iframe id="personaFrame" src="about:blank"></iframe></div></div></div>
<script>
(function(){const tipoSel=document.getElementById('tipo_propietario');const blqNat=document.getElementById('bloque_natural');const blqJur=document.getElementById('bloque_juridica');const modal=document.getElementById('personaModal');const frame=document.getElementById('personaFrame');const title=document.getElementById('personaModalTitle');function toggleTipo(){const isNatural=tipoSel.value==='NATURAL';blqNat.style.display=isNatural?'block':'none';blqJur.style.display=isNatural?'none':'block';}tipoSel.addEventListener('change',toggleTipo);toggleTipo();function renderPersona(prefix,persona,hiddenId){document.getElementById(hiddenId).value=persona.id||'';document.getElementById(prefix+'_nombre').value=[persona.apellido_paterno||'',persona.apellido_materno||'',persona.nombres||''].join(' ').trim();document.getElementById(prefix+'_dom').value=persona.domicilio||'';document.getElementById(prefix+'_cel').value=persona.celular||'';document.getElementById(prefix+'_email').value=persona.email||'';document.getElementById('info_'+prefix).innerHTML='<b>'+document.getElementById(prefix+'_nombre').value+'</b> (id: '+persona.id+')';if(persona.num_doc)document.getElementById('dni_'+prefix).value=persona.num_doc;}async function fetchJson(url){const r=await fetch(url,{cache:'no-store'});return r.json();}function attachSearch(prefix,hiddenId,editBtnId){const input=document.getElementById('dni_'+prefix);const suggest=document.getElementById('sug_'+prefix);const btnBuscar=document.getElementById('btnBuscar'+(prefix==='nat'?'Nat':'Rep'));const btnEditar=document.getElementById(editBtnId);const btnLimpiar=document.getElementById('btnLimpiar'+(prefix==='nat'?'Nat':'Rep'));input.addEventListener('input',async function(){const q=(input.value||'').trim();if(q.length<2){suggest.style.display='none';suggest.innerHTML='';return;}try{const j=await fetchJson('propietario_vehiculo_nuevo.php?ajax=buscar_personas&q='+encodeURIComponent(q));if(!j.ok||!j.personas||!j.personas.length){suggest.innerHTML='<div class="suggest-item">Sin coincidencias</div>';suggest.style.display='block';return;}suggest.innerHTML=j.personas.map(function(persona){const nombre=[persona.apellido_paterno||'',persona.apellido_materno||'',persona.nombres||''].join(' ').trim();return '<div class="suggest-item" data-id="'+persona.id+'">'+nombre+' - '+(persona.num_doc||'')+'</div>';}).join('');suggest.style.display='block';}catch(e){suggest.style.display='none';}});suggest.addEventListener('click',async function(ev){const item=ev.target.closest('.suggest-item');if(!item||!item.dataset.id)return;const j=await fetchJson('propietario_vehiculo_nuevo.php?ajax=buscar_id&id='+encodeURIComponent(item.dataset.id));if(j.ok&&j.persona)renderPersona(prefix,j.persona,hiddenId);suggest.style.display='none';suggest.innerHTML='';});btnBuscar.addEventListener('click',async function(){const dni=(input.value||'').trim();const j=await fetchJson('propietario_vehiculo_nuevo.php?ajax=buscar_dni&dni='+encodeURIComponent(dni));if(!j.ok){document.getElementById('info_'+prefix).textContent=j.msg||'No encontrado.';return;}renderPersona(prefix,j.persona,hiddenId);});btnEditar.addEventListener('click',function(){const id=document.getElementById(hiddenId).value;if(!id){alert('No hay persona seleccionada.');return;}title.textContent='Editar persona';frame.src='persona_editar.php?id='+encodeURIComponent(id);modal.style.display='flex';modal.dataset.target=prefix;});btnLimpiar.addEventListener('click',function(){document.getElementById(hiddenId).value='';document.getElementById(prefix+'_nombre').value='';document.getElementById(prefix+'_dom').value='';document.getElementById(prefix+'_cel').value='';document.getElementById(prefix+'_email').value='';document.getElementById('info_'+prefix).textContent='-';input.value='';});}attachSearch('nat','propietario_persona_id','btnEditarNat');attachSearch('rep','representante_persona_id','btnEditarRep');document.getElementById('btnPersonaCerrar').addEventListener('click',function(){modal.style.display='none';frame.src='about:blank';});window.addEventListener('message',async function(ev){const d=ev.data||{};if((d.type==='persona_saved'||d.type==='persona_creada')&&d.id){const target=modal.dataset.target||'nat';const hiddenId=target==='nat'?'propietario_persona_id':'representante_persona_id';const j=await fetchJson('propietario_vehiculo_nuevo.php?ajax=buscar_id&id='+encodeURIComponent(d.id));if(j.ok&&j.persona)renderPersona(target,j.persona,hiddenId);modal.style.display='none';frame.src='about:blank';}});})();
</script>
</body>
</html>
