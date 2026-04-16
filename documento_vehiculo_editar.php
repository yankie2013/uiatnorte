<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoVehiculoRepository;
use App\Services\DocumentoVehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function p($k,$d=null){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }

$service = new DocumentoVehiculoService(new DocumentoVehiculoRepository($pdo));
$id = (int) (g('id', 0) ?: p('id', 0));
if ($id <= 0) {
    http_response_code(400);
    exit('ID invalido');
}

$allowedSections = [
    'propiedad' => 'Tarjeta de Propiedad',
    'soat' => 'SOAT',
    'revision' => 'Revision Tecnica',
    'peritaje' => 'Peritaje',
];
$section = strtolower((string) (g('section', p('section', '')) ?? ''));
if (!isset($allowedSections[$section])) {
    $section = '';
}
$singleCardMode = $section !== '';
$sectionTitle = $singleCardMode ? ' · ' . $allowedSections[$section] : '';

$dv = $service->contextoEditar($id);
if (!$dv) {
    http_response_code(404);
    exit('Documento no encontrado');
}

$guardado = false;
$error_msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $service->actualizar($id, $_POST);
        $guardado = true;
        $dv = $service->contextoEditar($id) ?? $dv;
    } catch (Throwable $e) {
        $error_msg = $e->getMessage();
        $dv = $service->mergeOld($dv, $_POST);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar Documento de Vehiculo<?= h($sectionTitle) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
.p{ padding:18px; }
.topbar{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
.vehicle-chip{ display:inline-flex; gap:10px; padding:10px 14px; border-radius:999px; background:var(--bg-2,rgba(255,255,255,.06)); border:1px solid var(--line,#2a2f36); font-weight:800 }
.cards-2col{ display:grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap:12px; align-items:start }
.cards-2col.single-card-mode{ grid-template-columns:1fr }
.cards-2col.single-card-mode > .card{ display:none }
.cards-2col.single-card-mode > .card.is-active{ display:block }
@media(max-width:640px){ .cards-2col{ grid-template-columns:1fr } }
.grid-2{ display:grid; grid-template-columns:1fr 1fr; gap:12px }
.col-span-2{ grid-column:1 / -1 }
label{ font-weight:600; font-size:12px }
input[type="text"],input[type="date"],textarea{ width:100%; padding:10px 12px; border-radius:12px; border:1px solid var(--line) }
.form-actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:12px }
.btn{ padding:10px 14px; border-radius:12px; border:1px solid transparent; font-weight:700; cursor:pointer }
.btn.primary{ background:var(--brand); color:#fff }
.btn.ghost{ background:transparent; border:1px solid var(--line) }
#danosWrap{ display:flex; flex-direction:column; gap:8px }
.danio-row{ display:flex; align-items:center; gap:8px }
.danio-row input{ flex:1 }
.btn.icon{ width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1px solid var(--line) }
.danio-row .remove{ display:none } .danio-row.filled .remove{ display:inline-flex }
.section-header{ display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:10px }
.alert.success{ background:var(--success-bg); color:var(--success-fg); padding:12px 14px; border-radius:12px; margin-bottom:12px }
.alert.error{ background:var(--error-bg); color:var(--error-fg); padding:12px 14px; border-radius:12px; margin-bottom:12px }
</style>
</head>
<body class="p">

<?php if ($guardado): ?>
  <div class="alert success">Documento actualizado (#<?=h($id)?>).</div>
  <script>
    try { parent.postMessage({type:'docveh:updated', id:<?=json_encode($id)?>}, '*'); } catch (e) {}
  </script>
<?php elseif($error_msg): ?>
  <div class="alert error">Error: <?=h($error_msg)?></div>
<?php endif; ?>

<div class="topbar">
  <div>
    <div class="title">Editar - Documento de Vehiculo #<?=h($id)?></div>
    <?php if ($singleCardMode): ?>
      <div class="muted" style="margin-top:4px;"><?= h($allowedSections[$section]) ?></div>
    <?php endif; ?>
    <div class="vehicle-chip">
      <?php if (!empty($dv['placa'])): ?>
        <span>Placa: <b><?=h($dv['placa'])?></b></span>
        <?php if (!empty($dv['color'])) echo '<span>- ' . h($dv['color']) . '</span>'; ?>
        <?php if (!empty($dv['anio'])) echo '<span>- ' . h($dv['anio']) . '</span>'; ?>
      <?php else: ?><span><b>Sin vehiculo vinculado</b></span><?php endif; ?>
    </div>
  </div>
  <div><a class="btn ghost" href="javascript:history.back()">Volver</a></div>
</div>

<form method="post" autocomplete="off" id="formDocVeh">
  <input type="hidden" name="id" value="<?=h($id)?>">
  <input type="hidden" name="involucrado_vehiculo_id" value="<?=h($dv['invol_id'])?>">
  <input type="hidden" name="vehiculo_id" value="<?=h($dv['vehiculo_id'])?>">
  <input type="hidden" name="section" value="<?= h($section) ?>">
  <textarea name="danos_peritaje" id="danos_peritaje" hidden><?=h($dv['danos_peritaje'])?></textarea>

  <div class="cards-2col<?= $singleCardMode ? ' single-card-mode' : '' ?>">
    <div class="card<?= $section === 'propiedad' ? ' is-active' : '' ?>" data-doc-section="propiedad">
      <div class="card-h">Tarjeta de Propiedad</div>
      <div class="card-b grid-2">
        <div><label>Numero</label><input type="text" name="numero_propiedad" value="<?=h($dv['numero_propiedad'])?>"></div>
        <div><label>Titulo</label><input type="text" name="titulo_propiedad" value="<?=h($dv['titulo_propiedad'])?>"></div>
        <div><label>Partida</label><input type="text" name="partida_propiedad" value="<?=h($dv['partida_propiedad'])?>"></div>
        <div><label>Sede</label><input type="text" name="sede_propiedad" value="<?=h($dv['sede_propiedad'])?>"></div>
      </div>
    </div>

    <div class="card<?= $section === 'soat' ? ' is-active' : '' ?>" data-doc-section="soat">
      <div class="card-h">SOAT</div>
      <div class="card-b grid-2">
        <div><label>Numero</label><input type="text" name="numero_soat" value="<?=h($dv['numero_soat'])?>"></div>
        <div><label>Aseguradora</label><input type="text" name="aseguradora_soat" value="<?=h($dv['aseguradora_soat'])?>"></div>
        <div><label>Vigente desde</label><input type="date" name="vigente_soat" value="<?=h($dv['vigente_soat'])?>"></div>
        <div><label>Vence</label><input type="date" name="vencimiento_soat" value="<?=h($dv['vencimiento_soat'])?>"></div>
      </div>
    </div>

    <div class="card<?= $section === 'revision' ? ' is-active' : '' ?>" data-doc-section="revision">
      <div class="card-h">Revision Tecnica</div>
      <div class="card-b grid-2">
        <div><label>Numero</label><input type="text" name="numero_revision" value="<?=h($dv['numero_revision'])?>"></div>
        <div><label>Certificadora</label><input type="text" name="certificadora_revision" value="<?=h($dv['certificadora_revision'])?>"></div>
        <div><label>Vigente desde</label><input type="date" name="vigente_revision" value="<?=h($dv['vigente_revision'])?>"></div>
        <div><label>Vence</label><input type="date" name="vencimiento_revision" value="<?=h($dv['vencimiento_revision'])?>"></div>
      </div>
    </div>

    <div class="card<?= $section === 'peritaje' ? ' is-active' : '' ?>" data-doc-section="peritaje">
      <div class="card-h">Peritaje</div>
      <div class="card-b">
        <div class="grid-2">
          <div><label>Numero</label><input type="text" name="numero_peritaje" value="<?=h($dv['numero_peritaje'])?>"></div>
          <div><label>Fecha</label><input type="date" name="fecha_peritaje" value="<?=h($dv['fecha_peritaje'])?>"></div>
          <div class="col-span-2"><label>Perito</label><input type="text" name="perito_peritaje" value="<?=h($dv['perito_peritaje'])?>"></div>
          <div><label>Sistema electrico</label><input type="text" name="sistema_electrico_peritaje" maxlength="255" value="<?=h($dv['sistema_electrico_peritaje'] ?? '')?>"></div>
          <div><label>Sistema de frenos</label><input type="text" name="sistema_frenos_peritaje" maxlength="255" value="<?=h($dv['sistema_frenos_peritaje'] ?? '')?>"></div>
          <div><label>Sistema de direccion</label><input type="text" name="sistema_direccion_peritaje" maxlength="255" value="<?=h($dv['sistema_direccion_peritaje'] ?? '')?>"></div>
          <div><label>Sistema de transmision</label><input type="text" name="sistema_transmision_peritaje" maxlength="255" value="<?=h($dv['sistema_transmision_peritaje'] ?? '')?>"></div>
          <div><label>Sistema de suspension</label><input type="text" name="sistema_suspension_peritaje" maxlength="255" value="<?=h($dv['sistema_suspension_peritaje'] ?? '')?>"></div>
          <div><label>Planta motriz</label><input type="text" name="planta_motriz_peritaje" maxlength="255" value="<?=h($dv['planta_motriz_peritaje'] ?? '')?>"></div>
          <div class="col-span-2"><label>Otros</label><input type="text" name="otros_peritaje" maxlength="255" value="<?=h($dv['otros_peritaje'] ?? '')?>"></div>
        </div>

        <div class="section-header">
          <label style="margin:0;">Danos constatados</label>
          <button class="btn icon" type="button" id="btnAddDanio" title="Agregar dano">+</button>
        </div>
        <div id="danosWrap"></div>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <button type="button" class="btn ghost" onclick="history.back()">Cancelar</button>
    <button class="btn primary" type="submit">Guardar cambios</button>
  </div>
</form>

<script>
(function(){
  const wrap = document.getElementById('danosWrap');
  const addBtn = document.getElementById('btnAddDanio');
  const hidden = document.getElementById('danos_peritaje');
  const form = document.getElementById('formDocVeh');

  function rowTemplate(value=''){
    const row = document.createElement('div'); row.className='danio-row' + (value.trim() ? ' filled' : '');
    const inp = document.createElement('input'); inp.type='text'; inp.className='danio-input'; inp.placeholder='Describa un dano...'; inp.value=value || '';
    const rm = document.createElement('button'); rm.type='button'; rm.className='btn icon remove'; rm.textContent='x'; rm.title='Eliminar';
    rm.onclick = ()=>{ row.remove(); if (!wrap.querySelector('.danio-row')) addRow(''); syncHidden(); };
    inp.oninput = ()=>{ row.classList.toggle('filled', inp.value.trim().length > 0); syncHidden(); };
    row.appendChild(inp); row.appendChild(rm); return row;
  }
  function addRow(v=''){ const r=rowTemplate(v); wrap.appendChild(r); if(!v) r.querySelector('input').focus(); return r; }
  function syncHidden(){ hidden.value = Array.from(wrap.querySelectorAll('.danio-input')).map(i=>i.value.trim()).filter(Boolean).join('\n'); }
  const pre = hidden.value ? hidden.value.split(/\r?\n/) : [''];
  pre.forEach(v=>addRow(v));
  addBtn.onclick = ()=>addRow('');
  wrap.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab' || event.shiftKey) return;
    if (!event.target || !event.target.classList.contains('danio-input')) return;

    const inputs = Array.from(wrap.querySelectorAll('.danio-input'));
    const lastInput = inputs[inputs.length - 1];
    if (event.target !== lastInput) return;
    if (!event.target.value.trim()) return;

    event.preventDefault();
    syncHidden();
    addRow('');
  });
  form.addEventListener('submit', syncHidden);
})();
</script>

</body>
</html>
