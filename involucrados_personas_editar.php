<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\InvolucradoPersonaRepository;
use App\Services\InvolucradoPersonaService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function p($k,$d=null){ return isset($_POST[$k])? trim($_POST[$k]) : $d; }
function json_out($a){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$repo = new InvolucradoPersonaRepository($pdo);
$service = new InvolucradoPersonaService($repo);

$inv_id = (int)g('id',0);
if($inv_id<=0){ header('Location: involucrados_personas_listar.php'); exit; }

$inv = $repo->involucradoById($inv_id);
if(!$inv){ header('Location: involucrados_personas_listar.php'); exit; }

$accidente_id = (int)$inv['accidente_id'];
$returnTo = trim((string)($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if($returnTo === ''){
  $returnTo = 'involucrados_personas_listar.php?accidente_id=' . $accidente_id;
}

if (g('ajax')==='buscar_dni'){
  $dni = preg_replace('/\D/','', g('dni',''));
  if($dni==='') json_out(['ok'=>false,'msg'=>'Ingresa DNI']);
  $persona = $service->buscarPersonaBasica($dni);
  if(!$persona) json_out(['ok'=>false,'msg'=>'No se encontr� la persona']);
  json_out(['ok'=>true,'persona'=>$persona]);
}
if (g('ajax')==='vehiculos_accidente'){
  $aid=(int)g('accidente_id',0);
  json_out(['ok'=>true,'data'=>$repo->vehiculosPorAccidente($aid)]);
}
if (g('ajax')==='lc_persona' && isset($_GET['persona_id'])){
  $pid=(int)g('persona_id',0);
  json_out(['ok'=>true,'data'=>$repo->licenciasPersona($pid)]);
}
if (g('ajax')==='rml_persona' && isset($_GET['persona_id'])){
  $pid=(int)g('persona_id',0);
  json_out(['ok'=>true,'data'=>$repo->rmlPersona($pid)]);
}
if (g('ajax')==='dosaje_persona' && isset($_GET['persona_id'])){
  $pid=(int)g('persona_id',0);
  json_out(['ok'=>true,'data'=>$repo->dosajePersona($pid)]);
}
if (g('ajax')==='man_persona' && isset($_GET['persona_id']) && isset($_GET['accidente_id'])){
  $pid=(int)g('persona_id',0);
  $aid=(int)g('accidente_id',0);
  json_out(['ok'=>true,'data'=>$repo->manifestacionesPersona($pid,$aid)]);
}
if (g('ajax')==='occiso_persona' && isset($_GET['persona_id']) && isset($_GET['accidente_id'])){
  $pid=(int)g('persona_id',0);
  $aid=(int)g('accidente_id',0);
  try{
    json_out(['ok'=>true,'data'=>$repo->occisosPersona($pid,$aid)]);
  }catch(Throwable $e){
    json_out(['ok'=>false,'msg'=>$e->getMessage()]);
  }
}

$roles = $repo->roles();
$roles_req_map = [];
$roles_name_map = [];
foreach($roles as $r){
  $roles_req_map[(int)$r['id']] = (int)$r['req_veh'];
  $roles_name_map[(int)$r['id']] = $r['nombre'];
}

$vehiculos = $repo->vehiculosPorAccidente($accidente_id);
$accidentes = $repo->accidentes();

$err=''; $ok='';
if($_SERVER['REQUEST_METHOD']==='POST' && !isset($_GET['ajax'])){
  try{
    $service->actualizar($inv_id, [
      'persona_id' => (int)p('persona_id',0),
      'rol_id' => (int)p('rol_id',0),
      'vehiculo_id' => p('vehiculo_id','')===''? null : (int)p('vehiculo_id'),
      'lesion' => p('lesion',''),
      'observaciones' => p('observaciones',''),
      'orden_persona' => strtoupper(trim(p('orden_persona',''))),
      'dni' => p('dni',''),
    ]);
    header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'ok=updated');
    exit;
  }catch(Throwable $e){
    $err='Error: '.$e->getMessage();
    $inv = array_merge($inv, [
      'persona_id' => (int)p('persona_id',0),
      'rol_id' => (int)p('rol_id',0),
      'vehiculo_id' => p('vehiculo_id','')===''? null : (int)p('vehiculo_id'),
      'lesion' => p('lesion',''),
      'observaciones' => p('observaciones',''),
      'orden_persona' => strtoupper(trim(p('orden_persona',''))),
      'num_doc' => p('dni',$inv['num_doc'] ?? ''),
    ]);
  }
}
if(g('ok')==='1') $ok='Cambios guardados correctamente.';

$lesiones_opts = ['Ileso','Herido','Fallecido'];
$requiereVehInicial = (int)($roles_req_map[(int)$inv['rol_id']] ?? 0);
$rolNombreInicial = $roles_name_map[(int)$inv['rol_id']] ?? '';

// incluir el sidebar (archivo en la misma carpeta uiatnorte)
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar involucrado – Persona</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
  /* Extras específicos que no están en el css: */
  .chips{display:flex; flex-wrap:wrap; gap:8px; align-items:center}
  .chip{display:inline-flex; gap:6px; align-items:center; border:1px solid var(--line); background:var(--panel); border-radius:999px; padding:4px 10px; white-space:nowrap; font-weight:800; font-size:12px}
  .chip a{color:inherit; text-decoration:none}
  .mut{color:#fbbf24}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <a class="btn small" href="<?= h($returnTo) ?>">Volver</a>
    <a class="btn small" href="involucrados_personas_ver.php?id=<?= (int)$inv_id ?>&return_to=<?= urlencode($returnTo) ?>">Ver detalle</a>
    <span></span>
  </div>

  <h1>Editar involucrado – Persona <span class="badge">ID #<?= (int)$inv_id ?></span></h1>
  <div class="note">Busca por DNI para reemplazar la persona asociada si corresponde. Si el rol requiere vehículo, selecciónalo.</div>

  <?php if($ok): ?><div class="ok">✅ <?=h($ok)?></div><?php endif; ?>
  <?php if($err): ?><div class="err">Error: <?=h($err)?></div><?php endif; ?>

  <form method="post" class="card" autocomplete="off" id="formEdit">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <input type="hidden" name="persona_id" id="persona_id" value="<?= (int)$inv['persona_id'] ?>">

    <div class="grid">
      <div class="col-6">
        <label>Accidente</label>
        <select disabled>
          <?php foreach($accidentes as $a): ?>
            <option value="<?=$a['id']?>" <?=($a['id']==$accidente_id?'selected':'')?>><?=h($a['nom'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6">
        <label>DNI</label>
        <div class="rowin">
          <input type="text" name="dni" id="dni" placeholder="Ingresa DNI (8 dígitos)" value="<?=h($inv['num_doc'])?>" maxlength="12">
          <button class="btn small" type="button" id="btnBuscar">Buscar</button>
        </div>
      </div>

      <div class="col-4">
        <label>Nombres</label>
        <input type="text" id="nombres" value="<?=h($inv['nombres'])?>" readonly>
      </div>
      <div class="col-4">
        <label>Apellido paterno</label>
        <input type="text" id="ap" value="<?=h($inv['apellido_paterno'])?>" readonly>
      </div>
      <div class="col-4">
        <label>Apellido materno</label>
        <input type="text" id="am" value="<?=h($inv['apellido_materno'])?>" readonly>
      </div>

      <div class="col-4">
        <label>Sexo</label>
        <input type="text" id="sexo" value="<?=h($inv['sexo'])?>" readonly>
      </div>
      <div class="col-4">
        <label>Fecha de nacimiento</label>
        <input type="text" id="fnac" value="<?=h($inv['fecha_nacimiento'])?>" readonly>
      </div>
      <div class="col-4">
        <label>Edad (referencial)</label>
        <input type="text" id="edad" value="" readonly>
      </div>

      <div class="col-6">
        <label>Rol</label>
        <select name="rol_id" id="rol_id" required>
          <option value="">— Seleccionar —</option>
          <?php foreach($roles as $r): ?>
            <option value="<?=$r['id']?>" data-req-veh="<?=$r['req_veh']?>" <?=((int)$inv['rol_id']===(int)$r['id']?'selected':'')?>><?=h($r['nombre'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6">
        <label>Vehículo (si aplica)</label>
        <select name="vehiculo_id" id="vehiculo_id" <?= $requiereVehInicial ? '' : 'disabled' ?>>
          <option value="">— Sin vehículo —</option>
          <?php foreach($vehiculos as $v): ?>
            <?php $lbl = $v['placa'] . ($v['color']?(' · '.$v['color']):'') . ($v['anio']?(' ('.$v['anio'].')'):''); ?>
            <option value="<?=$v['id']?>" <?=((int)$inv['vehiculo_id']===(int)$v['id']?'selected':'')?>><?=h($lbl)?></option>
          <?php endforeach; ?>
        </select>
        <div id="vehReqHelp" class="mut" style="margin-top:4px; display:<?= $requiereVehInicial ? 'block':'none' ?>">Este rol requiere vehículo. Selecciona uno.</div>
      </div>

      <div class="col-6">
        <label>Lesión</label>
        <select name="lesion" id="lesion">
          <?php foreach($lesiones_opts as $opt): ?>
            <option value="<?=$opt?>" <?=($inv['lesion']===$opt?'selected':'')?> ><?=$opt?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- NUEVO: Orden persona A-Z (solo peaton/pasajero/ocupante/testigo) -->
      <div class="col-6">
        <label>Orden persona</label>
        <select name="orden_persona" id="orden_persona">
          <option value="">—</option>
          <?php foreach(range('A','Z') as $L): ?>
            <option value="<?=$L?>" <?= ($inv['orden_persona']===$L ? 'selected' : '') ?>><?=$L?></option>
          <?php endforeach; ?>
        </select>
        <div id="opHelp" class="note">Solo para Peatón / Pasajero / Ocupante / Testigo</div>
      </div>

      <div class="col-12">
        <label>Observaciones</label>
        <textarea name="observaciones" rows="4"><?=h($inv['observaciones'])?></textarea>
      </div>
    </div>

    <!-- ===== LICENCIA DE CONDUCIR ===== -->
    <div id="boxLC" class="card" style="display:none; margin-top:10px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px">
        <strong>Licencia de conducir</strong>
        <button class="btn micro" type="button" id="btnLcNew">＋ Nueva</button>
      </div>
      <div id="lcList" class="chips"><span class="chip">—</span></div>
      <div class="note" style="margin-top:6px">Si ya existe una licencia se mostrará arriba. Si no, regístrala con “＋ Nueva”.</div>
    </div>

    <!-- ===== R M L ===== -->
    <div id="boxRML" class="card" style="display:none; margin-top:10px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px">
        <strong>RML — Registros</strong>
        <button class="btn micro" type="button" id="btnRmlNew">＋ Nuevo RML</button>
      </div>
      <div id="rmlList" class="chips"></div>
      <div class="note" style="margin-top:6px">Si no hay registros, usa “＋ Nuevo RML”.</div>
    </div>

    <!-- ===== DOSAJE ETÍLICO ===== -->
    <div id="boxDOS" class="card" style="display:none; margin-top:10px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px">
        <strong>Dosaje etílico — Registros</strong>
        <button class="btn micro" type="button" id="btnDosNew">＋ Nuevo Dosaje</button>
      </div>
      <div id="dosList" class="chips"></div>
      <div class="note" style="margin-top:6px">Si no hay registros, usa “＋ Nuevo Dosaje”.</div>
    </div>
    
    <!-- ===== MANIFESTACIÓN ===== -->
    <div id="boxMAN" class="card" style="display:none; margin-top:10px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px">
        <strong>Manifestación — Documento</strong>
        <div style="display:flex;gap:8px;align-items:center">
          <button class="btn micro" type="button" id="btnManNew">＋ Nueva Manifestación</button>
        </div>
      </div>
      <div id="manList" class="chips"><span class="chip">—</span></div>
      <div class="note" style="margin-top:6px">
        Se habilita al seleccionar un rol. Crea y gestiona manifestaciones de la persona asociada a este accidente.
      </div>
    </div>

    <!-- ===== OCCISO (solo si Lesión = Fallecido) ===== -->
    <div id="boxOCC" class="card" style="display:none; margin-top:10px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px">
        <strong>Documento — Occiso</strong>
        <button class="btn micro" type="button" id="btnOccNew">＋ Nuevo Documento de Occiso</button>
      </div>
      <div id="occList" class="chips"><span class="chip">—</span></div>
      <div class="note" style="margin-top:6px">
        Esta sección se habilita cuando la lesión del involucrado es <b>Fallecido</b>.
      </div>
    </div>

    <div class="actions">
      <a class="btn small" href="<?= h($returnTo) ?>">Cancelar</a>
      <button class="btn primary small" type="submit">Guardar cambios</button>
    </div>
  </form>
</div>

<!-- Modal LC -->
<div class="modal-xl" id="modal-lc" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:60;padding:14px">
  <div class="box" style="width:min(100%,1000px);border-radius:14px;border:1px solid var(--line);background:var(--panel)">
    <div class="modalbar" style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.04)">
      <div class="ttl" style="font-weight:800">🚗 Licencia</div>
      <button type="button" class="btn small" id="btnLcClose">Cerrar ✕</button>
    </div>
    <div class="ifwrap" style="position:relative;height:min(76vh,760px)">
      <iframe id="frame-lc" src="about:blank" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
    </div>
  </div>
</div>

<!-- Modal RML -->
<div class="modal-xl" id="modal-rml" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:60;padding:14px">
  <div class="box" style="width:min(100%,1000px);border-radius:14px;border:1px solid var(--line);background:var(--panel)">
    <div class="modalbar" style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.04)">
      <div class="ttl" style="font-weight:800">📄 RML</div>
      <button type="button" class="btn small" id="btnRmlClose">Cerrar ✕</button>
    </div>
    <div class="ifwrap" style="position:relative;height:min(76vh,760px)">
      <iframe id="frame-rml" src="about:blank" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
    </div>
  </div>
</div>

<!-- Modal Dosaje -->
<div class="modal-xl" id="modal-dos" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:60;padding:14px">
  <div class="box" style="width:min(100%,1000px);border-radius:14px;border:1px solid var(--line);background:var(--panel)">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid var(--line)">
      <div class="ttl" style="font-weight:800">🍷 Dosaje</div>
      <button type="button" class="btn small" id="btnDosClose">Cerrar ✕</button>
    </div>
    <div style="position:relative;height:min(76vh,760px)">
      <iframe id="frame-dos" src="about:blank" style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
    </div>
  </div>
</div>

<!-- Modal Manifestación -->
<div class="modal-xl" id="modal-man" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:60;padding:14px">
  <div class="box" style="width:min(100%,1000px);border-radius:14px;border:1px solid var(--line);background:var(--panel)">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid var(--line)">
      <div class="ttl" style="font-weight:800">📝 Manifestación</div>
      <button type="button" class="btn small" id="btnManClose">Cerrar ✕</button>
    </div>
    <div style="position:relative;height:min(76vh,760px)">
      <iframe id="frame-man" src="about:blank" style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
    </div>
  </div>
</div>

<!-- Modal Occiso -->
<div class="modal-xl" id="modal-occ" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:60;padding:14px">
  <div class="box" style="width:min(100%,1000px);border-radius:14px;border:1px solid var(--line);background:var(--panel)">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid var(--line)">
      <div class="ttl" style="font-weight:800">🕊️ Documento de Occiso</div>
      <button type="button" class="btn small" id="btnOccClose">Cerrar ✕</button>
    </div>
    <div style="position:relative;height:min(76vh,760px)">
      <iframe id="frame-occ" src="about:blank" style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
    </div>
  </div>
</div>

<script>
/* ===== util edad ===== */
(function calcEdad(){
  const fn = document.getElementById('fnac').value;
  const fa = "<?= h($inv['fecha_accidente']) ?>";
  if(!fn || !fa){ document.getElementById('edad').value=''; return; }
  const b=new Date(fn), a=new Date(fa);
  if(isNaN(b)||isNaN(a)){ document.getElementById('edad').value=''; return; }
  let e=a.getFullYear()-b.getFullYear();
  const m=a.getMonth()-b.getMonth();
  if(m<0 || (m===0 && a.getDate()<b.getDate())) e--;
  document.getElementById('edad').value = e>=0? e : '';
})();

/* ===== Buscar por DNI (AJAX) ===== */
document.getElementById('btnBuscar').addEventListener('click', async ()=>{
  const dni = (document.getElementById('dni').value||'').replace(/\D/g,'');
  if(dni.length<8){ alert('Ingresa DNI (8 dígitos).'); return; }
  const url = 'involucrados_personas_editar.php?ajax=buscar_dni&dni='+encodeURIComponent(dni)+'&id=<?=$inv_id?>';
  try{
    const r = await fetch(url); const j = await r.json();
    if(!j.ok){ alert(j.msg||'No encontrado'); return; }
    const p = j.persona;
    document.getElementById('persona_id').value = p.id;
    document.getElementById('nombres').value   = p.nombres||'';
    document.getElementById('ap').value        = p.apellido_paterno||'';
    document.getElementById('am').value        = p.apellido_materno||'';
    document.getElementById('sexo').value      = p.sexo||'';
    document.getElementById('fnac').value      = p.fecha_nacimiento||'';
    (function(){
      const fn = document.getElementById('fnac').value;
      const fa = "<?= h($inv['fecha_accidente']) ?>";
      if(!fn || !fa){ document.getElementById('edad').value=''; return; }
      const b=new Date(fn), a=new Date(fa);
      let e=a.getFullYear()-b.getFullYear();
      const m=a.getMonth()-b.getMonth();
      if(m<0 || (m===0 && a.getDate()<b.getDate())) e--;
      document.getElementById('edad').value = e>=0? e : '';
    })();
    // refrescar secciones condicionales
    maybeToggleLC(true);
    maybeToggleRML(true);
    maybeToggleDOS(true);
    maybeToggleMAN();
    loadMAN();
    maybeToggleOCC(true);
  }catch(e){ alert('Error al buscar'); }
});

/* ===== helpers ===== */
function normalize(txt){
  return (txt||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
}

/* ===== Rol/vehículo ===== */
function applyRoleVehicleRequirement(){
  const sel = document.getElementById('rol_id');
  const req = Number(sel.options[sel.selectedIndex]?.dataset?.reqVeh||0);
  const veh = document.getElementById('vehiculo_id');
  const help= document.getElementById('vehReqHelp');

  if(req){
    veh.disabled = false;
    veh.required = true;
    help.style.display='block';
  }else{
    veh.value = "";
    veh.disabled = true;
    veh.required = false;
    help.style.display = 'none';
  }

  // Habilitar/deshabilitar Orden Persona según rol
  toggleOrdenPersona();
}

function toggleOrdenPersona(){
  const sel = document.getElementById('rol_id');
  const rolTxt = normalize(sel.options[sel.selectedIndex]?.text || '');
  const allow = /(peaton|pasajero|ocupante|testigo)/.test(rolTxt);
  const opSel = document.getElementById('orden_persona');
  const opHelp= document.getElementById('opHelp');
  if(allow){
    opSel.disabled = false;
    opHelp.textContent = 'Seleccione A, B, C… (orden dentro del grupo)';
  }else{
    opSel.value = '';
    opSel.disabled = true;
    opHelp.textContent = 'Solo para Peatón / Pasajero / Ocupante / Testigo';
  }
}

document.getElementById('rol_id').addEventListener('change', ()=>{
  applyRoleVehicleRequirement();
  maybeToggleLC(true);
  maybeToggleRML(true);
  maybeToggleDOS(true);
  maybeToggleMAN();
});
applyRoleVehicleRequirement();

/* ===== Mostrar/Ocultar LICENCIA ===== */
function isConductor(){
  const rolSel = document.getElementById('rol_id');
  const txt = (rolSel.options[rolSel.selectedIndex]?.text || '').toLowerCase();
  return txt.includes('conductor');
}
async function loadLC(){
  const pid = Number(document.getElementById('persona_id').value||0);
  const box = document.getElementById('lcList');
  box.innerHTML = '<span class="chip">Cargando…</span>';
  if(!pid){ box.innerHTML = '<span class="chip">— Sin persona —</span>'; return; }
  try{
    const r = await fetch('involucrados_personas_editar.php?ajax=lc_persona&persona_id='+pid+'&id=<?=$inv_id?>');
    const j = await r.json();
    box.innerHTML = '';
    if(!j.ok || !j.data || j.data.length===0){
      box.innerHTML = '<span class="chip">No registrada</span>';
      return;
    }
    j.data.forEach(it=>{
      const chip = document.createElement('span');
      chip.className = 'chip';
      const desde = it.vigente_desde || '—';
      const hasta = it.vigente_hasta || '—';
      const cat = it.categoria ? (' · Cat '+it.categoria) : '';
      chip.innerHTML = `Clase <strong>${escapeHtml(it.clase||'—')}</strong>${cat} · Nº ${escapeHtml(it.numero||'—')} · Vigente: ${desde} a ${hasta}
        <a href="javascript:void(0)" data-lc="${it.id}" class="lcEdit btn micro" style="margin-left:6px">Ver / Editar</a>`;
      box.appendChild(chip);
    });
    document.querySelectorAll('.lcEdit').forEach(a=>{
      a.addEventListener('click', ()=>{
        const id = a.getAttribute('data-lc');
        openLcModal('doc_lc_editar.php?id='+id+'&embed=1&return_to='+encodeURIComponent(location.href));
      });
    });
  }catch(_){
    box.innerHTML = '<span class="chip">Error al cargar.</span>';
  }
}
function maybeToggleLC(reload=false){
  const show = isConductor();
  const box  = document.getElementById('boxLC');
  box.style.display = show ? 'block' : 'none';
  if(show && reload) loadLC();
}
maybeToggleLC(true);

/* ===== Mostrar/Ocultar RML ===== */
function needsRML(){
  const rolSel = document.getElementById('rol_id');
  const rolTxt = (rolSel.options[rolSel.selectedIndex]?.text || '').toLowerCase();
  const lesion = (document.getElementById('lesion').value || '').toLowerCase();
  return rolTxt.includes('conductor') || lesion.includes('herido');
}
async function loadRML(){
  const pid = Number(document.getElementById('persona_id').value||0);
  const box = document.getElementById('rmlList');
  box.innerHTML = '<span class="chip">Cargando…</span>';
  if(!pid){ box.innerHTML = '<span class="chip">— Sin persona —</span>'; return; }
  try{
    const r = await fetch('involucrados_personas_editar.php?ajax=rml_persona&persona_id='+pid+'&id=<?=$inv_id?>');
    const j = await r.json();
    box.innerHTML = '';
    if(!j.ok || !j.data || j.data.length===0){
      box.innerHTML = '<span class="chip">No hay RML registrados.</span>';
      return;
    }
    j.data.forEach(it=>{
      const chip = document.createElement('span');
      chip.className = 'chip';
      const fecha = it.fecha ? it.fecha : 's/f';
      const inc  = it.incapacidad_medico ?? '—';
      const atf  = it.atencion_facultativo ?? '—';
      chip.innerHTML = `Nº <strong>${escapeHtml(it.numero||'—')}</strong> · ${fecha} · Incap.: ${escapeHtml(inc)} · Atenc.: ${escapeHtml(atf)}
        <a href="javascript:void(0)" data-rml="${it.id}" class="rmlEdit btn micro" style="margin-left:6px">Ver / Editar</a>`;
      box.appendChild(chip);
    });
    document.querySelectorAll('.rmlEdit').forEach(a=>{
      a.addEventListener('click', ()=>{
        const id = a.getAttribute('data-rml');
        openRmlModal('documento_rml_editar.php?id='+id+'&embed=1&return_to='+encodeURIComponent(location.href));
      });
    });
  }catch(_){
    box.innerHTML = '<span class="chip">Error al cargar.</span>';
  }
}
function maybeToggleRML(reload=false){
  const show = needsRML();
  const box  = document.getElementById('boxRML');
  box.style.display = show ? 'block' : 'none';
  if(show && reload) loadRML();
}
document.getElementById('lesion').addEventListener('change', ()=>{ maybeToggleRML(true); });
maybeToggleRML(true);

/* ===== Dosaje ===== */
function needsDOS(){
  const rolSel = document.getElementById('rol_id');
  const rolTxt = (rolSel.options[rolSel.selectedIndex]?.text || '').toLowerCase();
  return rolTxt.includes('conductor') || rolTxt.includes('peatón') || rolTxt.includes('peaton') || rolTxt.includes('pasajero') || rolTxt.includes('ocupante');
}
async function loadDOS(){
  const pid = Number(document.getElementById('persona_id').value||0);
  const box = document.getElementById('dosList');
  box.innerHTML = '<span class="chip">Cargando…</span>';
  if(!pid){ box.innerHTML='<span class="chip">— Sin persona —</span>'; return; }
  try{
    const r = await fetch('involucrados_personas_editar.php?ajax=dosaje_persona&persona_id='+pid+'&id=<?=$inv_id?>');
    const j = await r.json();
    box.innerHTML='';
    if(!j.ok || !j.data || j.data.length===0){
      box.innerHTML='<span class="chip">No hay dosajes.</span>';
      return;
    }
    j.data.forEach(it=>{
      const chip=document.createElement('span');
      chip.className='chip';
      const fecha = it.fecha_extraccion || 's/f';
      const cual  = it.resultado_cualitativo ? ` · ${escapeHtml(it.resultado_cualitativo)}` : '';
      const cuant = it.resultado_cuantitativo ? ` · ${escapeHtml(it.resultado_cuantitativo)} g/L` : '';
      chip.innerHTML=`Nº <strong>${escapeHtml(it.numero||'—')}</strong> · Reg ${escapeHtml(it.numero_registro||'—')} · ${fecha}${cual}${cuant}
        <a href="documento_dosaje_editar.php?id=${it.id}&embed=1&return_to=${encodeURIComponent(location.href)}" class="btn micro" style="margin-left:6px">Ver / Editar</a>`;
      box.appendChild(chip);
    });
  }catch(e){
    box.innerHTML='<span class="chip">Error al cargar.</span>';
  }
}
function maybeToggleDOS(reload=false){
  const show=needsDOS();
  const box=document.getElementById('boxDOS');
  box.style.display=show?'block':'none';
  if(show&&reload) loadDOS();
}
document.getElementById('rol_id').addEventListener('change',()=>maybeToggleDOS(true));
maybeToggleDOS(true);

/* ===== Manifestación ===== */
function needsMAN(){
  const sel = document.getElementById('rol_id');
  return !!(sel && sel.value && sel.value !== '');
}
function maybeToggleMAN(){
  const box = document.getElementById('boxMAN');
  box.style.display = needsMAN() ? 'block' : 'none';
  if (needsMAN()) loadMAN();
}
async function loadMAN(){
  const pid = Number(document.getElementById('persona_id').value||0);
  const aid = <?= (int)$accidente_id ?>;
  const box = document.getElementById('manList');
  if(!pid){ box.innerHTML='<span class="chip">— Sin persona —</span>'; return; }
  box.innerHTML = '<span class="chip">Cargando…</span>';
  try{
    const r = await fetch(`involucrados_personas_editar.php?ajax=man_persona&persona_id=${pid}&accidente_id=${aid}&id=<?=$inv_id?>`);
    const j = await r.json();
    box.innerHTML='';
    if(!j.ok || !j.data || j.data.length===0){
      box.innerHTML='<span class="chip">No hay manifestaciones registradas.</span>';
      return;
    }
    j.data.forEach(it=>{
      const chip=document.createElement('span');
      chip.className='chip';
      const hi = it.horario_inicio ? it.horario_inicio.substring(0,5) : '--:--';
      const ht = it.hora_termino   ? it.hora_termino.substring(0,5)   : '--:--';
      chip.innerHTML = `📄 <strong>${escapeHtml(it.modalidad||'—')}</strong> · ${escapeHtml(it.fecha||'s/f')} · ${hi}–${ht}
        <a href="documento_manifestacion_editar.php?id=${it.id}&embed=1&return_to=${encodeURIComponent(location.href)}" class="btn micro" style="margin-left:6px">Ver / Editar</a>`;
      box.appendChild(chip);
    });
  }catch(e){
    box.innerHTML='<span class="chip">Error al cargar.</span>';
  }
}
document.getElementById('rol_id').addEventListener('change', maybeToggleMAN);
maybeToggleMAN();

/* Abrir modal “Nueva Manifestación” */
document.getElementById('btnManNew').addEventListener('click', ()=>{
  const pid = Number(document.getElementById('persona_id').value||0);
  if(!pid){ alert('Selecciona / busca una persona primero.'); return; }

  const rolId = document.getElementById('rol_id').value || '';
  const accId = "<?= (int)$accidente_id ?>";
  const returnTo = encodeURIComponent(location.href);

  const url = `documento_manifestacion_nuevo.php?persona_id=${pid}&rol_id=${encodeURIComponent(rolId)}&accidente_id=${accId}&embed=1&return_to=${returnTo}`;
  openManModal(url);
});
function openManModal(url){
  document.body.style.overflow='hidden';
  const m = document.getElementById('modal-man');
  const f = document.getElementById('frame-man');
  m.style.display='flex';
  f.src = url;
}
function closeManModal(){
  document.body.style.overflow='';
  const m = document.getElementById('modal-man');
  const f = document.getElementById('frame-man');
  m.style.display='none';
  f.src='about:blank';
}
document.getElementById('btnManClose').addEventListener('click', closeManModal);

/* ===== OCCISO: solo si Lesión = Fallecido ===== */
function needsOCC(){
  const lesion = (document.getElementById('lesion').value || '').toLowerCase();
  return lesion === 'fallecido';
}
function maybeToggleOCC(reload=false){
  const box = document.getElementById('boxOCC');
  const show = needsOCC();
  box.style.display = show ? 'block' : 'none';
  if (show && reload) loadOCC();
}
document.getElementById('lesion').addEventListener('change', ()=>maybeToggleOCC(true));
maybeToggleOCC(true);

async function loadOCC(){
  const pid = Number(document.getElementById('persona_id').value||0);
  const aid = <?= (int)$accidente_id ?>;
  const box = document.getElementById('occList');
  if(!pid){ box.innerHTML = '<span class="chip">— Sin persona —</span>'; return; }
  box.innerHTML = '<span class="chip">Cargando…</span>';
  try{
    const r = await fetch(`involucrados_personas_editar.php?ajax=occiso_persona&persona_id=${pid}&accidente_id=${aid}&id=<?=$inv_id?>`);
    const j = await r.json();
    box.innerHTML = '';
    if(!j.ok){
      box.innerHTML = `<span class="chip">Error: ${escapeHtml(j.msg||'No se pudo cargar')}</span>`;
      return;
    }
    if(!j.data || j.data.length===0){
      box.innerHTML = '<span class="chip">No hay documentos de occiso.</span>';
      return;
    }

    j.data.forEach(it=>{
      const chip = document.createElement('span');
      chip.className = 'chip';
      const fh   = (it.fecha_levantamiento || 's/f') + ' ' + (it.hora_levantamiento || '');
      const lugar= it.lugar_levantamiento || '—';
      const npro = it.numero_protocolo ? ` · Prot. ${escapeHtml(it.numero_protocolo)}` : '';

      const returnTo = encodeURIComponent(location.href);
      const urlVer  = `documento_occiso_ver.php?id=${it.id}&embed=1&return_to=${returnTo}`;
      const urlEdit = `documento_occiso_editar.php?id=${it.id}&embed=1&return_to=${returnTo}`;

      // Creamos botones que redirigen (no modal)
      chip.innerHTML = `🕊️ <strong>${escapeHtml(lugar)}</strong> · ${escapeHtml(fh)}${npro}
        <a href="${urlVer}" class="btn micro" style="margin-left:6px">Ver</a>
        <a href="${urlEdit}" class="btn micro" style="margin-left:6px">Editar</a>`;

      box.appendChild(chip);
    });
  }catch(e){
    box.innerHTML = '<span class="chip">Error al cargar.</span>';
  }
}



/* Abrir modal “Nuevo Documento de Occiso” */
document.getElementById('btnOccNew').addEventListener('click', ()=>{
  const pid = Number(document.getElementById('persona_id').value||0);
  if(!pid){ alert('Selecciona / busca una persona primero.'); return; }
  if(!needsOCC()){ alert('La lesión debe ser "Fallecido" para registrar Occiso.'); return; }

  const rolId = document.getElementById('rol_id').value || '';
  const accId = "<?= (int)$accidente_id ?>";
  const returnTo = encodeURIComponent(location.href);

  const url = `documento_occiso_nuevo.php?persona_id=${pid}&personaId=${pid}&rol_id=${encodeURIComponent(rolId)}&accidente_id=${accId}&accidenteId=${accId}&embed=1&return_to=${returnTo}`;
  openOccModal(url);
});
/* Abrir modal “Nuevo Documento de Occiso” */
document.getElementById('btnOccNew').addEventListener('click', ()=>{
  const pid = Number(document.getElementById('persona_id').value||0);
  if(!pid){ alert('Selecciona / busca una persona primero.'); return; }
  if(!needsOCC()){ alert('La lesión debe ser "Fallecido" para registrar Occiso.'); return; }

  const rolId = document.getElementById('rol_id').value || '';
  const accId = "<?= (int)$accidente_id ?>";
  const returnTo = encodeURIComponent(location.href);

  const url = `documento_occiso_nuevo.php?persona_id=${pid}&personaId=${pid}&rol_id=${encodeURIComponent(rolId)}&accidente_id=${accId}&accidenteId=${accId}&embed=1&return_to=${returnTo}`;

  // Redirigir en lugar de abrir modal:
  location.href = url;
});

function closeOccModal(){
  document.body.style.overflow='';
  const m = document.getElementById('modal-occ');
  const f = document.getElementById('frame-occ');
  m.style.display='none';
  f.src='about:blank';
}
document.getElementById('btnOccClose').addEventListener('click', closeOccModal);

/* ===== Abrir modales (LC / RML / DOS) ===== */
document.getElementById('btnLcNew').addEventListener('click', ()=>{
  const pid = Number(document.getElementById('persona_id').value||0);
  if(!pid){ alert('Selecciona / busca una persona primero.'); return; }
  openLcModal('doc_lc_nuevo.php?persona_id='+pid+'&embed=1&return_to='+encodeURIComponent(location.href));
});
function openLcModal(url){
  const m = document.getElementById('modal-lc');
  const f = document.getElementById('frame-lc');
  document.body.style.overflow='hidden';
  m.style.display='flex';
  f.src = url;
}
function closeLcModal(){
  const m = document.getElementById('modal-lc');
  const f = document.getElementById('frame-lc');
  f.src = 'about:blank';
  m.style.display='none';
  document.body.style.overflow='';
}
document.getElementById('btnLcClose').addEventListener('click', closeLcModal);

document.getElementById('btnRmlNew').addEventListener('click', ()=>{
  const pid = Number(document.getElementById('persona_id').value||0);
  if(!pid){ alert('Selecciona / busca una persona primero.'); return; }
  openRmlModal('documento_rml_nuevo.php?persona_id='+pid+'&embed=1&return_to='+encodeURIComponent(location.href));
});
function openRmlModal(url){
  const m = document.getElementById('modal-rml');
  const f = document.getElementById('frame-rml');
  document.body.style.overflow='hidden';
  m.style.display='flex';
  f.src = url;
}
function closeRmlModal(){
  const m = document.getElementById('modal-rml');
  const f = document.getElementById('frame-rml');
  f.src = 'about:blank';
  m.style.display='none';
  document.body.style.overflow='';
}
document.getElementById('btnRmlClose').addEventListener('click', closeRmlModal);

document.getElementById('btnDosNew').addEventListener('click', ()=>{
  const pid=Number(document.getElementById('persona_id').value||0);
  if(!pid){ alert('Selecciona / busca una persona primero.'); return; }
  openDosModal('documento_dosaje_nuevo.php?persona_id='+pid+'&embed=1&return_to='+encodeURIComponent(location.href));
});
function openDosModal(url){
  document.body.style.overflow='hidden';
  document.getElementById('modal-dos').style.display='flex';
  document.getElementById('frame-dos').src=url;
}
function closeDosModal(){
  document.body.style.overflow='';
  document.getElementById('modal-dos').style.display='none';
  document.getElementById('frame-dos').src='about:blank';
}
document.getElementById('btnDosClose').addEventListener('click',closeDosModal);

/* Mensajes desde formularios embebidos */
window.addEventListener('message',(ev)=>{
  const d = ev.data||{};
  if(d.type==='lc.saved'){ closeLcModal(); loadLC(); }
  if(d.type==='rml.saved'){ closeRmlModal(); loadRML(); }
  if(d.type==='dosaje.saved'){ closeDosModal(); loadDOS(); }
  if(d.type==='manifestacion.saved'){ closeManModal(); loadMAN(); }
  if(d.type==='occiso.saved'){ closeOccModal(); loadOCC(); }
});

/* ===== Validación antes de enviar ===== */
document.getElementById('formEdit').addEventListener('submit', (e)=>{
  const persona_id = Number(document.getElementById('persona_id').value||0);
  if(!persona_id){ e.preventDefault(); alert('Debes seleccionar / buscar una persona por DNI.'); return; }
  const sel = document.getElementById('rol_id');
  const req = Number(sel.options[sel.selectedIndex]?.dataset?.reqVeh||0);
  const veh = document.getElementById('vehiculo_id').value;
  if(req && !veh){ e.preventDefault(); alert('Este rol requiere seleccionar un vehículo.'); }
});

/* Utilidad escape simple */
function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
</script>
</body>
</html>
