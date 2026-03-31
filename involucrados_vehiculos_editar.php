<?php
/* ============================================================
   UIAT Norte - involucrados_vehiculos_editar.php (REFACTORIZADO)
   ============================================================ */

require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
include __DIR__ . '/_boton_volver.php';

use App\Repositories\InvolucradoVehiculoRepository;
use App\Services\InvolucradoVehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function ipost($k,$d=null){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function iget($k,$d=null){ return isset($_GET[$k])  ? trim($_GET[$k])  : $d; }
function okjson($arr){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr,JSON_UNESCAPED_UNICODE); exit; }

function yesno($v){
  if($v===null || $v==='') return '—';
  $v = strtolower((string)$v);
  return (in_array($v, ['1','si','sí','true','vigente','y'])) ? 'Vigente' : 'No vigente';
}
function badge_date($d){
  if(!$d) return '<span class="pill">—</span>';
  $today = date('Y-m-d');
  $ok = ($d >= $today);
  return '<span class="pill '.($ok?'pill-ok':'pill-bad').'">'.h($d).'</span>';
}
function vehiculo_text($row){
  return ($row['placa'] ?? 'ID '.$row['id']).(($row['color']??'') ? (' · '.$row['color']) : '').(($row['anio']??'') ? (' ('.$row['anio'].')') : '');
}

$repo = new InvolucradoVehiculoRepository($pdo);
$service = new InvolucradoVehiculoService($repo);

$id = (int)iget('id', 0);
if ($id<=0) { http_response_code(400); echo 'ID inválido'; exit; }

$iv = $repo->involucradoById($id);
if(!$iv){ http_response_code(404); echo 'Registro no encontrado'; exit; }

$accidente_id = (int)$iv['accidente_id'];
$return = iget('return', 'involucrados_vehiculos_listar.php?accidente_id='.$accidente_id);
$tipo_opts = $service->tipoOptions();

if (iget('ajax')==='buscar_vehiculos') {
  okjson($service->buscarVehiculos(iget('q','')));
}

$err=''; $ok = iget('ok',''); $msg = iget('msg','');
if ($_SERVER['REQUEST_METHOD']==='POST' && iget('ajax')===null) {
  try{
    $service->actualizar($id, [
      'vehiculo_id' => (int)ipost('vehiculo_id', 0),
      'tipo' => ipost('tipo','Unidad'),
      'observaciones' => ipost('observaciones',''),
    ]);
    header('Location: '.$return.'&ok=1');
    exit;
  }catch(Throwable $e){
    $err = 'Error al guardar: '.$e->getMessage();
    $iv = array_merge($iv, [
      'vehiculo_id' => (int)ipost('vehiculo_id', 0),
      'tipo' => ipost('tipo','Unidad'),
      'observaciones' => ipost('observaciones',''),
    ]);
  }
}

$docs = $repo->documentosVehiculo($id);
$tipo_opts_doc = ['Unidad','Combinado vehicular 1','Combinado vehicular 2'];
$__es_unidad = in_array($iv['tipo'], $tipo_opts_doc, true);

// incluir el sidebar (archivo en la misma carpeta uiatnorte)
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar involucrado - Vehículo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
  .wrap{ max-width:1080px; margin:auto; padding:16px; }
  .top{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
  .pill{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:var(--muted-2); border:1px solid var(--line); font-weight:800; }
  /* pills de estado (reusa .pill que ya tienes) */
.pill-ok{ background:#e8f8ef; color:#18794e; font-weight:800; }
.pill-bad{ background:#feefef; color:#b42318; font-weight:800; }

/* tarjetas del resumen */
.dv-card h4{ margin:0 0 6px 0; font-size:15px; }
.dv-row{ display:flex; gap:8px; margin:6px 0; }
.dv-key{ min-width:130px; color:var(--fg-3); }
.dv-val{ font-weight:700; }
  .muted{ color: var(--fg-3); font-size:.9rem; }
  .grid{ display:grid; gap:10px; grid-template-columns: 1fr 1fr; }
  .grid-3{ display:grid; gap:10px; grid-template-columns: 1fr 1fr 1fr; }
  @media (max-width:900px){ .grid, .grid-3{ grid-template-columns: 1fr; } }
  label{ display:block; font-weight:700; font-size:.9rem; margin:4px 0; }
  input, select, textarea{ width:100%; border-radius:12px; font-size:.95rem; }
  textarea{ min-height:90px; }
  .grid-4{display:grid;gap:10px;grid-template-columns:repeat(4,1fr)}
@media (max-width:1200px){.grid-4{grid-template-columns:repeat(2,1fr)}}
@media (max-width:900px){.grid-4{grid-template-columns:1fr}}
  .actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
  .btn{ display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:12px; border:1px solid var(--line); background:var(--bg-2); color:var(--fg); font-weight:800; text-decoration:none; cursor:pointer; }
  .btn.primary{ background:var(--brand); color:#fff; border-color:var(--brand); }
  .btn.small{ padding:6px 10px; }

  /* chips acciones documentos */
  .chips{display:flex;gap:8px;flex-wrap:wrap}
  .chip{
    display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;
    border:1px solid var(--line);background:rgba(255,255,255,.05);color:var(--fg);
    font-weight:800;text-decoration:none;cursor:pointer;transition:.15s ease
  }
  .chip:hover{transform:translateY(-1px)}
  .chip.danger{border-color:#b91c1c;background:rgba(185,28,28,.15);color:#fee2e2}

  /* Modal iframe genérico */
  .modal{ position:fixed; inset:0; background:rgba(0,0,0,.5); display:none; align-items:center; justify-content:center; padding:16px; z-index:99; }
  .modal.open{ display:flex; }
  .modal .box{ background:var(--bg); border:1px solid var(--line); border-radius:16px; padding:12px; width:min(1000px,95%); height:90vh; color:var(--fg); display:flex; flex-direction:column; }
  .modal .box .head{ display:flex; align-items:center; justify-content:space-between; padding-bottom:8px; border-bottom:1px solid var(--line); }
  .modal .box iframe{ flex:1; width:100%; border:0; border-radius:12px; background:#fff; }
  @media (prefers-color-scheme: dark){ .modal .box iframe{ background:#0b0d0f; } }
  
  /* ==================== NUEVO ESTILO DE TARJETAS APILADAS ==================== */
.dv-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 10px;
}

.dv-card {
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 14px 18px;
  background: var(--bg-2);
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.dv-card h4 {
  margin: 0 0 8px 0;
  font-size: 15px;
  font-weight: 800;
  color: var(--fg);
  border-bottom: 1px solid var(--line);
  padding-bottom: 4px;
}

.dv-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
  margin: 4px 0;
}

.dv-key {
  color: var(--fg-3);
  min-width: 120px;
}

.dv-val {
  flex: 1;
  text-align: right;
  font-weight: 600;
  color: var(--fg);
  word-break: break-word;
}

/* Alineación izquierda en resumen del documento */
.dv-card, .dv-row, .dv-val {
  text-align: left !important;
}

.dv-val {
  font-weight: 500;
  color: var(--fg);
}
  
  /* Lista de daños en formato limpio */
.dv-list {
  margin: 6px 0 0 0;
  padding-left: 20px;
  list-style-type: "– ";
}
.dv-list li {
  margin-bottom: 2px;
  color: var(--fg);
  line-height: 1.3em;
}
</style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <h1 class="h1">Editar involucrado - Vehículo <span class="pill">ID #<?=h($id)?></span></h1>
    <a class="btn" href="<?=h($return)?>">Regresar</a>
  </div>

  <div class="card soft">
    <div class="muted">Accidente #<?=h($accidente_id)?> · <?=h(date('Y-m-d H:i', strtotime($iv['fecha_accidente'])))?> — <?=h($iv['lugar'])?></div>
  </div>

  <?php if($err): ?><div class="card warn"><?=h($err)?></div><?php endif; ?>
  <?php if($ok || $msg === 'eliminado'): ?><div class="card soft"><?= $msg === 'eliminado' ? 'Documento del vehiculo eliminado correctamente.' : 'Cambios guardados correctamente.' ?></div><?php endif; ?>

  <form method="post" id="formIV" autocomplete="off" class="card">
    <input type="hidden" name="return" value="<?=h($return)?>">

    <div class="grid">
      <div>
        <label>Búsqueda por placa</label>
        <div class="inline" style="display:flex; gap:8px;">
          <input type="text" id="qplaca" placeholder="Ej. ABC123" style="flex:1">
          <button class="btn small" type="button" id="btnBuscarPlaca">Buscar</button>
          <!-- Abre vehiculo_nuevo.php en modal (iframe) -->
          <button class="btn small" type="button" id="btnVehNuevo">+ Nuevo</button>
        </div>
      </div>
      <div>
        <label>Vehículo actual</label>
        <input type="text" value="<?=h(vehiculo_text($iv))?>" readonly>
      </div>
    </div>

    <div class="grid-3">
      <div>
        <label>Vehículo (reemplazar por...)</label>
        <select name="vehiculo_id" id="vehiculo_id" required>
          <option value="<?=$iv['vehiculo_id']?>" selected><?=h(vehiculo_text($iv))?></option>
        </select>
      </div>
      <div>
        <label>Tipo de participación</label>
        <select name="tipo" id="tipo" required>
          <?php foreach($tipo_opts as $t): ?>
            <option value="<?=h($t)?>" <?=$iv['tipo']===$t?'selected':''?>><?=h($t)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Resumen</label>
        <input type="text" id="veh_resumen" value="<?=h(vehiculo_text($iv))?>" readonly>
      </div>
    </div>

    <label>Observaciones</label>
    <textarea name="observaciones" rows="3"><?=h($iv['observaciones'])?></textarea>

    <div class="actions" style="margin-top:10px;">
      <a class="btn" href="<?=h($return)?>">Cancelar</a>
      <a class="btn" style="border-color:#c33;color:#c33;background:transparent"
         href="involucrados_vehiculos_eliminar.php?id=<?=$id?>&return_to=<?=urlencode($return)?>"
         onclick="return confirm('¿Eliminar este involucrado?');">Eliminar</a>
      <button class="btn primary" type="submit">Guardar cambios</button>
    </div>
  </form>

  <?php if ($__es_unidad): ?>
    <!-- ================== DOCUMENTO DEL VEHICULO ================== -->
    <div class="card" id="docveh-card" style="display:block; margin-top:12px;">
      <div class="card-h">Documento del vehículo</div>
      <div class="card-b">
        <div style="display:flex; gap:10px; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:8px">
          <div class="muted">Registra SOAT, Revisión Técnica y Peritaje del vehículo.</div>
          <button type="button" class="btn primary" onclick="openDocVehNew(<?= (int)$id ?>)" id="btnDocVeh">+ Documento del vehículo</button>
        </div>

        <div class="table-wrap">
            
          <table>
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>SOAT</th>
                <th>Revisión Técnica</th>
                <th>Peritaje</th>
                <th style="width:200px" class="td-actions">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$docs): ?>
                <tr><td colspan="5" class="muted">Sin documentos registrados.</td></tr>
              <?php else: foreach ($docs as $d): ?>
                <tr>
                  <td>#<?= h($d['id']) ?></td>
                  <td>
                    <?php if ($d['numero_soat']): ?><div><b>Nro.:</b> <?= h($d['numero_soat']) ?></div><?php endif; ?>
                    <div class="muted"><b>Vence:</b> <?= h($d['vencimiento_soat'] ?: '-') ?></div>
                  </td>
                  <td>
                    <?php if ($d['numero_revision']): ?><div><b>Nro.:</b> <?= h($d['numero_revision']) ?></div><?php endif; ?>
                    <div class="muted"><b>Vence:</b> <?= h($d['vencimiento_revision'] ?: '-') ?></div>
                  </td>
                  <td>
                    <?php if ($d['numero_peritaje']): ?><div><b>Nro.:</b> <?= h($d['numero_peritaje']) ?></div><?php endif; ?>
                    <div class="muted"><b>Fecha:</b> <?= h($d['fecha_peritaje'] ?: '-') ?></div>
                  </td>
                  <td class="td-actions">
                    <div class="chips">
                      <a href="#" class="chip" onclick="return openDocVehEdit(<?= (int)$d['id'] ?>);">Ver/Editar</a>
                      <a class="chip danger"
                         href="documento_vehiculo_eliminar.php?id=<?= (int)$d['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>">Eliminar</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal DOC VEH -->
    <div class="modal" id="mdDocVeh">
      <div class="box">
        <div class="head">
          <h3 style="margin:0" id="mdDocVehTitle">Documento de vehículo</h3>
          <button class="btn small" type="button" data-close="mdDocVeh">Cerrar</button>
        </div>
        <iframe id="ifrDocVeh" src="about:blank"></iframe>
      </div>
    </div>
    
     <?php
// tomar el ultimo documento (el primero de la lista ya viene ORDER BY id DESC)
$doc_ult = $docs[0] ?? null;
if ($doc_ult):
?>
  <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
  <div class="muted" style="margin-bottom:6px">Resumen del documento actual</div>

  <div class="dv-stack">
      
          <!-- Propiedad / Tarjeta -->
    <div class="card soft dv-card">
      <h4>Tarjeta de Propiedad</h4>
      <div class="dv-row"><div class="dv-key">Nro.</div>
        <div class="dv-val"><?= h($doc_ult['numero_propiedad'] ?? '') ?></div></div>
      <div class="dv-row"><div class="dv-key">Titulo</div>
        <div class="dv-val"><?= h($doc_ult['titulo_propiedad'] ?? '') ?></div></div>
      <div class="dv-row"><div class="dv-key">Partida</div>
        <div class="dv-val"><?= h($doc_ult['partida_propiedad'] ?? '') ?></div></div>
      <div class="dv-row"><div class="dv-key">Sede / SUNARP</div>
        <div class="dv-val"><?= h($doc_ult['sede_propiedad'] ?? '') ?></div></div>
    </div>
    <!-- SOAT -->
    <div class="card soft dv-card">
      <h4>SOAT</h4>
      <div class="dv-row"><div class="dv-key">Nro.</div><div class="dv-val"><?= h($doc_ult['numero_soat'] ?? '') ?></div></div>
      <div class="dv-row"><div class="dv-key">Aseguradora</div><div class="dv-val"><?= h($doc_ult['aseguradora_soat'] ?? '') ?></div></div>
      <div class="dv-row"><div class="dv-key">Vigente</div><div class="dv-val"><span class="pill"><?= yesno($doc_ult['vigente_soat'] ?? null) ?></span></div></div>
      <div class="dv-row"><div class="dv-key">Vence</div><div class="dv-val"><?= badge_date($doc_ult['vencimiento_soat'] ?? '') ?></div></div>
    </div>

    <!-- Revisión Técnica -->
    <div class="card soft dv-card">
      <h4>Revisión Técnica</h4>
      <div class="dv-row"><div class="dv-key">Nro.</div><div class="dv-val"><?= h($doc_ult['numero_revision'] ?? '') ?></div></div>
      <div class="dv-row"><div class="dv-key">Certificadora</div><div class="dv-val"><?= h($doc_ult['certificadora_revision'] ?? '') ?></div></div>
      <div class="dv-row"><div class="dv-key">Vigente</div><div class="dv-val"><span class="pill"><?= yesno($doc_ult['vigente_revision'] ?? null) ?></span></div></div>
      <div class="dv-row"><div class="dv-key">Vence</div><div class="dv-val"><?= badge_date($doc_ult['vencimiento_revision'] ?? '') ?></div></div>
    </div>

    <!-- Peritaje -->
    <div class="card soft dv-card">
      <h4>Peritaje</h4>
      <div class="dv-row"><div class="dv-key">Nro.</div><div class="dv-val"><?= h($doc_ult['numero_peritaje'] ?? '') ?></div></div>
      <div class="dv-row"><div class="dv-key">Fecha</div><div class="dv-val"><span class="pill"><?= h($doc_ult['fecha_peritaje'] ?? '') ?></span></div></div>
      <div class="dv-row"><div class="dv-key">Perito</div><div class="dv-val"><?= h($doc_ult['perito_peritaje'] ?? '') ?></div></div>
      <?php
$danosRaw = trim($doc_ult['danos_peritaje'] ?? '');
if ($danosRaw) {
    // Divide en líneas, elimina vacíos, muestra cada uno con guion
    $danosList = array_filter(preg_split('/[\r\n]+/', $danosRaw));
    echo '<ul class="dv-list">';
    foreach ($danosList as $d) {
        echo '<li>'.h($d).'</li>';
    }
    echo '</ul>';
} else {
    echo '<div class="dv-val">—</div>';
}
?>
    </div>
  </div>
<?php endif; ?>
    
    
  <?php endif; ?>

  <!-- Modal NUEVO VEHÍCULO -->
  <div class="modal" id="mdVehiculoNuevo">
    <div class="box">
      <div class="head">
        <h3 style="margin:0">Registrar nuevo vehículo</h3>
        <button class="btn small" type="button" data-close="mdVehiculoNuevo">Cerrar</button>
      </div>
      <iframe id="ifrNuevoVehiculo"
              src="vehiculo_nuevo.php?return=<?=urlencode($_SERVER['REQUEST_URI'])?>"></iframe>
    </div>
  </div>

</div><!-- /wrap -->

<script>
/* ===== Utilidades ===== */
const selVeh  = document.getElementById('vehiculo_id');
const resumen = document.getElementById('veh_resumen');
function actualizarResumen(){
  const opt = selVeh.options[selVeh.selectedIndex];
  if (resumen) resumen.value = (opt && opt.value) ? opt.textContent : '';
}
selVeh.addEventListener('change', actualizarResumen);

/* Buscar por placa */
document.getElementById('btnBuscarPlaca').addEventListener('click', async ()=>{
  const qRaw = document.getElementById('qplaca').value.trim();
  const q = qRaw.toUpperCase();
  document.getElementById('qplaca').value = q;

  const keep = selVeh.options[0];
  selVeh.innerHTML = '';
  if (keep) selVeh.appendChild(keep);
  resumen.value = keep ? keep.textContent : '';

  if (!q){ alert('Ingresa una placa (o parte) para buscar.'); return; }
  const r = await fetch(`involucrados_vehiculos_editar.php?id=<?= (int)$id ?>&ajax=buscar_vehiculos&q=${encodeURIComponent(q)}`);
  const data = await r.json();
  if (!Array.isArray(data) || data.length===0){
    if (confirm('No se encontraron vehículos. ¿Deseas registrarlo ahora?')) {
      openVehiculoNuevo();
    }
    return;
  }
  data.forEach(v=>{
    const opt = new Option(v.texto || v.placa || ('ID '+v.id), v.id);
    selVeh.appendChild(opt);
  });
  if (selVeh.options.length > 1){ selVeh.selectedIndex = 1; actualizarResumen(); }
}); 

/* Abrir modal nuevo vehículo */
function openVehiculoNuevo(){
  const modal=document.getElementById('mdVehiculoNuevo');
  const ifr=document.getElementById('ifrNuevoVehiculo');
  try{
    const url = new URL('vehiculo_nuevo.php', window.location.href);
    url.searchParams.set('return', '<?= addslashes($_SERVER['REQUEST_URI']) ?>');
    const q = (document.getElementById('qplaca').value||'').trim().toUpperCase();
    if(q) url.searchParams.set('placa_pref', q);
    ifr.src = url.toString();
  }catch(_){}
  modal.classList.add('open');
}
document.getElementById('btnVehNuevo').addEventListener('click', openVehiculoNuevo);

/* Mostrar/ocultar sección Documento según tipo
   Ahora muestra la sección para: Unidad, Combinado vehicular 1, Combinado vehicular 2 */
function toggleDocCard(){
  const card = document.getElementById('docveh-card');
  if (!card) return;
  const tipoVal = document.getElementById('tipo').value;
  const show = ['Unidad','Combinado vehicular 1','Combinado vehicular 2'].includes(tipoVal);
  card.style.display = show ? 'block' : 'none';
}
document.getElementById('tipo').addEventListener('change', toggleDocCard);
toggleDocCard();

/* Modal DOC Vehículo (Nuevo / Editar) */
function openDocVehNew(involId){
  const modal = document.getElementById('mdDocVeh');
  const ifr   = document.getElementById('ifrDocVeh');
  const ttl   = document.getElementById('mdDocVehTitle');
  if (ttl) ttl.textContent = 'Nuevo - Documento de vehículo';
  ifr.src = 'documento_vehiculo_nuevo.php?invol_id=' + encodeURIComponent(involId);
  modal.classList.add('open');
  return false;
}
function openDocVehEdit(docId){
  const modal = document.getElementById('mdDocVeh');
  const ifr   = document.getElementById('ifrDocVeh');
  const ttl   = document.getElementById('mdDocVehTitle');
  if (ttl) ttl.textContent = 'Editar - Documento de vehículo #' + docId;
  ifr.src = 'documento_vehiculo_editar.php?id=' + encodeURIComponent(docId);
  modal.classList.add('open');
  return false;
}

/* Cerrar modales */
document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click', ()=>{
  const id=b.dataset.close;
  const modal=document.getElementById(id);
  if(modal) modal.classList.remove('open');
}));

/* Mensajes desde iframes (vehículo nuevo / doc creado-actualizado-eliminado) */
window.addEventListener('message', (ev)=>{
  const d = ev.data;
  // Vehículo creado (dos variantes soportadas)
  if (d && typeof d === 'object' && d.ok && d.vehiculo && d.vehiculo.id) {
    const { id, texto } = d.vehiculo;
    selVeh.add(new Option(texto || ('ID '+id), id, true, true));
    actualizarResumen();
    const m=document.getElementById('mdVehiculoNuevo'); if (m) m.classList.remove('open');
    return;
  }
  if (typeof d === 'string' && d === 'vehiculo_creado') {
    location.reload();
    return;
  }
  // Documentos de vehículo
  if (d && typeof d === 'object' && (d.type==='docveh:created' || d.type==='docveh:updated' || d.type==='docveh:deleted')) {
    const m=document.getElementById('mdDocVeh'); if (m) m.classList.remove('open');
    location.reload();
  }
});
</script>
</body>
</html>
