<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\InvolucradoPersonaRepository;
use App\Services\InvolucradoPersonaService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function ipost($k,$d=null){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function iget($k,$d=null){ return isset($_GET[$k])  ? trim($_GET[$k])  : $d; }
function okjson($a){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($a,JSON_UNESCAPED_UNICODE); exit; }

$repo = new InvolucradoPersonaRepository($pdo);
$service = new InvolucradoPersonaService($repo);

if (iget('ajax')==='buscar_persona' && isset($_GET['dni'])) {
  $dni   = preg_replace('/\D/','',iget('dni',''));
  $accId = (int)iget('accidente_id',0);
  $persona = $service->buscarPersona($dni, $accId);
  okjson(['ok'=>!!$persona,'persona'=>$persona]);
}

if (iget('ajax')==='crear_persona' && $_SERVER['REQUEST_METHOD']==='POST') {
  try{
    $persona = $service->crearPersona($_POST);
    okjson(['ok'=>true,'id'=>$persona['id'],'label'=>$persona['label'],'dup'=>$persona['dup']]);
  }catch(Throwable $e){
    okjson(['ok'=>false,'msg'=>$e->getMessage()]);
  }
}

if (iget('ajax')==='vehiculos_por_accidente' && isset($_GET['accidente_id'])) {
  $aid=(int)iget('accidente_id',0);
  okjson($repo->vehiculosPorAccidente($aid));
}

$accidentes = $repo->accidentes();
$accidente_id = (int)iget('accidente_id', ($accidentes[0]['id'] ?? 0));
$accidente_fecha = $accidente_id ? $repo->accidenteFecha($accidente_id) : null;
$roles = $repo->roles();
$lesiones = ['Ileso','Leve','Moderada','Grave','Fallecido'];

$ok=''; $err='';
if ($_SERVER['REQUEST_METHOD']==='POST' && !isset($_GET['ajax'])) {
  try{
    $result = $service->registrar([
      'accidente_id' => (int)ipost('accidente_id',0),
      'persona_id' => (int)ipost('persona_id',0),
      'rol_id' => (int)ipost('rol_id',0),
      'vehiculo_id' => ipost('vehiculo_id','')==='' ? null : (int)ipost('vehiculo_id'),
      'lesion' => ipost('lesion','Ileso'),
      'observaciones' => ipost('observaciones',''),
      'next' => (int)ipost('next',0),
      'orden_persona' => strtoupper(trim(ipost('orden_persona',''))),
    ]);

    if($result['next']===1) header('Location: involucrados_personas_nuevo.php?ok=1&accidente_id='.$result['accidente_id']);
    else header('Location: involucrados_personas_listar.php?ok=1&accidente_id='.$result['accidente_id']);
    exit;
  }catch(Throwable $e){
    $err='Error: '.$e->getMessage();
    $accidente_id = (int)ipost('accidente_id', $accidente_id);
    $accidente_fecha = $accidente_id ? $repo->accidenteFecha($accidente_id) : null;
  }
}

$ok = iget('ok','');
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nuevo involucrado – Persona | UIAT Norte</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
 :root{
  color-scheme: light dark;
  --bg-light:#f6f7fb; --bg-dark:#0b0f16;
  --card-light:rgba(255,255,255,.85); --card-dark:rgba(20,25,35,.8);
  --line-light:rgba(0,0,0,.08); --line-dark:rgba(255,255,255,.12);
  --ink-light:#1f2937; --ink-dark:#e2e8f0;
  --muted-light:#6b7280; --muted-dark:#9aa4b2;
  --brand:#4f8cff; --brand2:#9b7bff;
  --ok:#10b981; --danger:#ef4444;
  --radius:14px; --radius-lg:18px;
  --fs:13px; --fs-sm:11.5px;
  --input-h:32px;
  --shadow-light:0 8px 22px rgba(0,0,0,.08);
  --shadow-dark:0 8px 22px rgba(0,0,0,.45);
}
*{box-sizing:border-box}
body{
  margin:0; font:var(--fs)/1.45 "Inter",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
  color:var(--ink-dark); background:linear-gradient(135deg,var(--bg-dark),#111827);
}
@media (prefers-color-scheme: light){
  body{ color:var(--ink-light); background:linear-gradient(135deg,var(--bg-light),#e8ebf4); }
}
.wrap{ max-width:920px; margin:16px auto; padding:0 12px; }
.title{ font-size:20px; font-weight:800; margin:0 0 4px; background:linear-gradient(90deg,var(--brand),var(--brand2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.subtitle{ color:var(--muted-dark); font-size:var(--fs-sm); margin-bottom:10px; }
.card{ background:var(--card-dark); border:1px solid var(--line-dark); border-radius:var(--radius-lg); padding:12px; margin-bottom:10px; }
@media(prefers-color-scheme:light){ .card{ background:var(--card-light); border:1px solid var(--line-light);} }
.grid-2{display:grid; gap:8px; grid-template-columns:1fr 1fr;}
.grid-3{display:grid; gap:8px; grid-template-columns:1fr 1fr 1fr;}
@media(max-width:860px){ .grid-2,.grid-3{grid-template-columns:1fr;} }
label{display:block; margin:0 0 3px 2px; font-size:var(--fs-sm); font-weight:700; color:var(--muted-dark);}
input,select,textarea{width:100%; height:var(--input-h); font-size:var(--fs); padding:6px 10px; border-radius:var(--radius); border:1px solid var(--line-dark); background:rgba(255,255,255,.04); color:inherit; outline:none;}
textarea{min-height:60px; resize:vertical}
.inline{display:flex; gap:6px; align-items:center}
.actions{display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; margin-top:10px}
.btn{border:1px solid var(--line-dark); border-radius:var(--radius); cursor:pointer; padding:6px 12px; font-size:var(--fs-sm); font-weight:700; background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.02));}
.btn.primary{background:linear-gradient(180deg,var(--brand),#386bdf); color:#fff;}
.btn.safe{background:linear-gradient(180deg,var(--ok),#059669); color:#fff;}
.btn.mini{padding:4px 8px}
.ok{background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.35); color:#10b981; padding:6px 10px; border-radius:var(--radius); margin-bottom:8px; font-weight:700;}
.err{background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.35); color:#ef4444; padding:6px 10px; border-radius:var(--radius); margin-bottom:8px; font-weight:700;}
/* Modal */
.modal-xl{position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,.55); z-index:60; padding:14px;}
.modal-xl.show{display:flex;}
.modal-xl .box{width:min(100%,1000px); border-radius:var(--radius-lg); border:1px solid var(--line-dark); background:var(--card-dark);}
@media(prefers-color-scheme:light){ .modal-xl .box{background:var(--card-light); border:1px solid var(--line-light);} }
.modalbar{display:flex; justify-content:space-between; align-items:center; gap:8px; padding:8px 12px; border-bottom:1px solid var(--line-dark); background:rgba(255,255,255,.04);}
.modalbar .ttl{font-weight:800; font-size:var(--fs);}
.modalbar .x{border:1px solid var(--line-dark); background:transparent; color:inherit; border-radius:10px; padding:4px 8px; cursor:pointer;}
.ifwrap{position:relative; height:min(76vh,760px);}
.ifwrap iframe{position:absolute; inset:0; width:100%; height:100%; border:0;}
</style>
</head>
<body>
<div class="wrap">
  <h1 class="title">Nuevo involucrado – Persona</h1>
  <div class="subtitle">Busca por DNI; si no existe, regístrala y se autocompleta.</div>

  <?php if($ok): ?><div class="ok">Guardado correctamente.</div><?php endif; ?>
  <?php if($err): ?><div class="err"><?=h($err)?></div><?php endif; ?>

  <form method="post" class="card" autocomplete="off" id="formIP">
    <input type="hidden" name="next" id="next" value="0">
    <input type="hidden" name="persona_id" id="persona_id" value="0">

    <div class="grid-2">
      <div>
        <label>Accidente</label>
        <select name="accidente_id" id="accidente_id" required>
          <option value="">— Seleccionar —</option>
          <?php foreach($accidentes as $a): ?>
            <option value="<?=$a['id']?>" <?=($accidente_id==$a['id']?'selected':'')?>><?=h($a['nom'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>DNI</label>
        <div class="inline">
          <input type="text" id="dni" placeholder="DNI (8 dígitos)" maxlength="15">
          <button class="btn mini" type="button" id="btnBuscarDNI">Buscar</button>
          <button class="btn mini" type="button" data-modalxl="modal-per" data-src="persona_nuevo.php?embed=1&from=involucrados&accidente_id=<?= (int)$accidente_id ?>">＋ Nueva</button>
        </div>
      </div>
    </div>

    <div class="grid-3" style="margin-top:8px">
      <div><label>Nombres</label><input type="text" id="nombres" readonly></div>
      <div><label>Apellido paterno</label><input type="text" id="ap" readonly></div>
      <div><label>Apellido materno</label><input type="text" id="am" readonly></div>
    </div>

    <div class="grid-3">
      <div><label>DNI</label><input type="text" id="dni_show" readonly></div>
      <div><label>Edad</label><input type="number" id="edad" readonly></div>
      <div><label>Sexo</label><input type="text" id="sexo" readonly></div>
    </div>

    <div class="grid-3">
      <div>
        <label>Rol</label>
        <select name="rol_id" id="rol_id" required>
          <option value="">— Seleccionar —</option>
          <?php foreach($roles as $r): ?>
            <option value="<?=$r['id']?>" data-req-veh="<?=$r['req_veh']?>"><?=h($r['nombre'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Vehículo</label>
        <select name="vehiculo_id" id="vehiculo_id"><option value="">— Sin vehículo —</option></select>
        <div id="veh_msg" class="subtitle">Este rol NO requiere vehículo.</div>
      </div>
      <div>
        <label>Lesión</label>
        <select name="lesion" id="lesion"><?php foreach($lesiones as $l): ?><option value="<?=$l?>"><?=$l?></option><?php endforeach; ?></select>
      </div>
    </div>

    <!-- Campo NUEVO: Orden persona (A, B, C...), habilitado solo para peaton/pasajero/ocupante/testigo -->
    <div style="margin-top:8px">
      <label>Orden persona</label>
      <select name="orden_persona" id="orden_persona" disabled>
        <option value="">—</option>
        <?php foreach(range('A','Z') as $L): ?>
          <option value="<?=$L?>"><?=$L?></option>
        <?php endforeach; ?>
      </select>
      <div class="subtitle" id="op_msg">Solo para Peatón / Pasajero / Ocupante / Testigo</div>
    </div>

    <label>Observaciones</label>
    <textarea name="observaciones" rows="4" placeholder="Notas u observaciones…"></textarea>

    <div class="actions">
      <a class="btn" href="involucrados_personas_listar.php?accidente_id=<?=h($accidente_id)?>">Cancelar</a>
      <button class="btn primary" type="submit" id="btnGuardar">Guardar</button>
      <button class="btn safe" type="submit" id="btnNext">Agregar siguiente</button>
    </div>
  </form>
</div>

<!-- MODAL XL: Persona -->
<div class="modal-xl" id="modal-per">
  <div class="box">
    <div class="modalbar">
      <div class="ttl">🧑‍💼 Registrar nueva persona</div>
      <button type="button" class="x" data-closexl="modal-per">Cerrar ✕</button>
    </div>
    <div class="ifwrap">
      <iframe id="frame-per" src="about:blank" loading="lazy"></iframe>
    </div>
  </div>
</div>

<script>
const ACCIDENTE_FECHA = <?= $accidente_fecha ? json_encode($accidente_fecha) : 'null' ?>;
function $(s,ctx=document){ return ctx.querySelector(s); }
function $all(s,ctx=document){ return Array.from(ctx.querySelectorAll(s)); }

/* -------- Modal Persona -------- */
function openModal(id, srcId, src){
  const m = $('#'+id), f = $('#'+srcId);
  if(!m || !f) return;
  document.body.style.overflow='hidden';
  m.classList.add('show');
  if(src) f.src = src;
}
function closeModal(id, srcId){
  const m = $('#'+id), f = $('#'+srcId);
  if(!m || !f) return;
  f.src='about:blank';
  m.classList.remove('show');
  document.body.style.overflow='';
}
$all('[data-modalxl]').forEach(b=>{
  b.addEventListener('click', ()=> openModal(b.getAttribute('data-modalxl'), 'frame-per', b.getAttribute('data-src')));
});
$all('[data-closexl]').forEach(b=>{
  b.addEventListener('click', ()=> closeModal(b.getAttribute('data-closexl'), 'frame-per'));
});
window.addEventListener('keydown',e=>{ if(e.key==='Escape'){ closeModal('modal-per','frame-per'); }});

/* -------- Vehículos por accidente -------- */
async function cargarVehiculos(){
  const aid = $('#accidente_id').value;
  const sel = $('#vehiculo_id');
  sel.innerHTML = '<option value="">— Sin vehículo —</option>';
  if(!aid) return;
  try{
    const r = await fetch(`?ajax=vehiculos_por_accidente&accidente_id=${encodeURIComponent(aid)}`);
    const j = await r.json();
    j.forEach(it=>{
      const o=document.createElement('option'); o.value=it.id; o.textContent=it.t; sel.appendChild(o);
    });
  }catch(_){}
}
$('#accidente_id').addEventListener('change', cargarVehiculos);
cargarVehiculos();

/* -------- Buscar por DNI -------- */
$('#btnBuscarDNI').addEventListener('click', async ()=>{
  const dni = ($('#dni').value||'').trim();
  if(!dni){ alert('Ingresa DNI'); return; }
  const accId = $('#accidente_id').value || '';
  try{
    const r = await fetch(`?ajax=buscar_persona&dni=${encodeURIComponent(dni)}&accidente_id=${encodeURIComponent(accId)}`);
    const j = await r.json();
    if(j.ok){
      const p=j.persona;
      $('#persona_id').value = p.id;
      $('#nombres').value = p.nombres||'';
      $('#ap').value = p.apellido_paterno||'';
      $('#am').value = p.apellido_materno||'';
      $('#dni_show').value = p.num_doc||dni;
      $('#sexo').value = p.sexo||'';
      $('#edad').value = (p.edad_calculada ?? p.edad ?? '');
    }else{
      alert('No se encontró. Usa “Nueva” para registrarla.');
    }
  }catch(_){ alert('Error al buscar'); }
});

/* Normalizar texto (sin tildes, minúsculas) */
function normalize(txt){
  return (txt||'').toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'');
}

/* -------- Reglas rol/vehículo + Orden persona -------- */
function applyRoleVehicleRequirement(){
  const sel = document.getElementById('rol_id');
  const req = Number(sel.options[sel.selectedIndex]?.dataset?.reqVeh || 0);
  const rolTxt = sel.options[sel.selectedIndex]?.text || '';

  const veh = document.getElementById('vehiculo_id');
  const msg = document.getElementById('veh_msg');
  if(req){
    veh.disabled = false;
    veh.required = true;
    msg.textContent = 'Este rol requiere vehículo.';
  }else{
    veh.value = '';
    veh.disabled = true;
    veh.required = false;
    msg.textContent = 'Este rol NO requiere vehículo.';
  }

  // Orden persona (solo peaton/pasajero/ocupante/testigo)
  const opSel = document.getElementById('orden_persona');
  const opMsg = document.getElementById('op_msg');
  const r = normalize(rolTxt);
  const allowOrden = /(peaton|pasajero|ocupante|testigo)/.test(r);

  if(allowOrden){
    opSel.disabled = false;
    opMsg.textContent = 'Seleccione A, B, C… (orden dentro del grupo)';
  }else{
    opSel.value = '';
    opSel.disabled = true;
    opMsg.textContent = 'Solo para Peatón / Pasajero / Ocupante / Testigo';
  }
}
document.getElementById('rol_id').addEventListener('change', applyRoleVehicleRequirement);
applyRoleVehicleRequirement(); // al cargar

/* -------- Botones submit -------- */
document.getElementById('btnNext').addEventListener('click', ()=>{ document.getElementById('next').value='1'; });
document.getElementById('btnGuardar').addEventListener('click', ()=>{ document.getElementById('next').value='0'; });
</script>
</body>
</html>
