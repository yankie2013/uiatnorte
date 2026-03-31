<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\VehiculoRepository;
use App\Services\VehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function placa_visible(string $placa): string {
    return str_starts_with($placa, 'SPLACA') ? 'SIN PLACA' : $placa;
}
function vehiculo_resumen(array $vehiculo): string {
    $texto = placa_visible(trim((string) ($vehiculo['placa'] ?? '')));
    $color = trim((string) ($vehiculo['color'] ?? ''));
    $anio = trim((string) ($vehiculo['anio'] ?? ''));

    if ($color !== '') {
        $texto .= ' - ' . $color;
    }
    if ($anio !== '') {
        $texto .= ' (' . $anio . ')';
    }

    return $texto;
}

$vehiculoRepo = new VehiculoRepository($pdo);
$vehiculoService = new VehiculoService($vehiculoRepo);
$isEmbed = isset($_GET['embed']) && $_GET['embed'] !== '0';
$prefillPlaca = trim((string) ($_GET['placa'] ?? ''));
$prefillSinPlaca = isset($_GET['sin_placa']) && $_GET['sin_placa'] !== '0';

$catalogos = $vehiculoService->catalogos();
$categorias = $catalogos['categorias'];
$marcas = $catalogos['marcas'];
$modelos = $catalogos['modelos'];
$tipos = $catalogos['tipos'];
$carrocerias = $catalogos['carrocerias'];

$catCodeToId = [];
foreach ($categorias as $categoria) {
    $catCodeToId[(string) $categoria['codigo']] = (int) $categoria['id'];
}

$err = '';
$old = $vehiculoService->oldInput();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $prefillPlaca !== '' && $old['placa'] === '') {
    $old['placa'] = mb_strtoupper($prefillPlaca, 'UTF-8');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $prefillSinPlaca && $old['sin_placa'] === '') {
    $old['sin_placa'] = '1';
    $old['placa'] = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $vehiculoService->oldInput($_POST);

    try {
        $vehiculoId = $vehiculoService->crear($_POST);
        if ($isEmbed) {
            $vehiculo = $vehiculoRepo->find($vehiculoId) ?? ['id' => $vehiculoId, 'placa' => $old['placa'], 'color' => $old['color'], 'anio' => $old['anio']];
            $payload = [
                'type' => 'vehiculo_creado',
                'vehiculo' => [
                    'id' => $vehiculoId,
                    'texto' => vehiculo_resumen($vehiculo),
                ],
            ];
            ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vehiculo creado</title>
<link rel="stylesheet" href="style_gian.css">
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="body">
      <div class="msg ok">Vehiculo creado correctamente.</div>
      <p>Se esta actualizando el formulario anterior.</p>
    </div>
  </div>
</div>
<script>
const payload = <?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
if (window.parent && window.parent !== window) {
  window.parent.postMessage(payload, window.location.origin);
}
</script>
</body>
</html>
<?php
            exit;
        }
        header('Location: vehiculo_listar.php?msg=creado');
        exit;
    } catch (InvalidArgumentException $e) {
        $err = $e->getMessage();
    } catch (Throwable $e) {
        $err = 'Error al guardar: ' . $e->getMessage();
    }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UIAT Norte — Nuevo Vehículo</title>
<link rel="stylesheet" href="style_gian.css">

<style>
  form.grid{
    display:grid;
    grid-template-columns: repeat(12, minmax(0,1fr));
    gap:8px;
  }
  form.grid > [class^="col-"]{ min-width:0; }
  form.grid > .col-3{ grid-column: span 4; }
  form.grid > .col-4{ grid-column: span 4; }
  form.grid > .col-12{ grid-column: span 12; }

  @media (max-width: 1100px){
    form.grid > .col-3,
    form.grid > .col-4{ grid-column: span 6; }
  }
  @media (max-width: 640px){
    form.grid > .col-3,
    form.grid > .col-4{ grid-column: span 12; }
  }

  form.grid input,
  form.grid select,
  form.grid textarea{
    padding:8px 10px !important;
    border-radius:10px !important;
    font-size:13px !important;
    width:100% !important;
  }
  form.grid label{
    margin-bottom:4px !important;
    font-size:12px !important;
    font-weight:700 !important;
  }
  .row{ display:flex; gap:6px; align-items:center; }
  .row .btn.small{ white-space:nowrap; }

  /* Modal base */
  .modal-mask{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:1000; }
  .modal{ width:100%; max-width:560px; background: rgba(var(--card),0.98); border-radius:16px; padding:16px; box-shadow:0 20px 50px rgba(0,0,0,.30); border:1px solid rgba(0,0,0,.08); }
  .modal h3{ margin:0 0 10px 0; font-size:18px; }
  .modal .actions{ display:flex; gap:8px; justify-content:flex-end; margin-top:12px; }
  .modal .help{ font-size:12px; color: rgba(var(--muted),1) }
  .hidden{ display:none }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="hdr">
      <div class="ttl">🚗 Nuevo Vehículo — UIAT Norte</div>
      <span class="pill"><?= $isEmbed ? 'Modo modal' : 'Estilo GIAN' ?></span>
    </div>

    <div class="body">
      <?php if($err): ?><div class="msg err">⚠️ <?=h($err)?></div><?php endif; ?>

      <form method="post" class="grid" novalidate>
        <!-- Placa + acciones -->
        <div class="col-3">
          <label for="placa">Placa</label>
          <input id="placa" name="placa" value="<?=h($old['placa'])?>" <?= !in_array(strtolower((string) $old['sin_placa']), ['1','on','true','si','sí'], true) ? 'required' : '' ?> maxlength="12" placeholder="ABC-123">
          <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-size:12px;font-weight:600;">
            <input type="checkbox" id="sin_placa" name="sin_placa" value="1" <?= in_array(strtolower((string) $old['sin_placa']), ['1','on','true','si','sí'], true) ? 'checked' : '' ?> style="width:auto;">
            Registrar como fugado sin placa
          </label>
          <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
            <button type="button" class="btn small" id="btnCheckPlaca">Verificar</button>
            <button type="button" class="btn small" id="btnAbrirSeeker">Abrir Seeker</button>
            <button type="button" class="btn small" id="btnPegarJson">Pegar JSON</button>
            <button type="button" class="btn small" id="btnSubirImagen">Subir/Pegar Imagen</button>
          </div>
          <div id="placaStatus" style="margin-top:6px; font-size:12px; opacity:.9;"></div>
        </div>

        <div class="col-3">
          <label for="serie_vin">Serie / VIN</label>
          <input id="serie_vin" name="serie_vin" value="<?=h($old['serie_vin'])?>">
        </div>
        <div class="col-3">
          <label for="nro_motor">Nro. Motor</label>
          <input id="nro_motor" name="nro_motor" value="<?=h($old['nro_motor'])?>">
        </div>
        <div class="col-3">
          <label for="anio">Año</label>
          <input id="anio" name="anio" value="<?=h($old['anio'])?>" maxlength="4" inputmode="numeric" placeholder="YYYY">
        </div>

        <!-- Categoría / Tipo / Carrocería -->
        <div class="col-4">
          <label for="categoria_id">Categoría</label>
          <select name="categoria_id" id="categoria_id" required>
            <option value="">(Selecciona)</option>
            <?php foreach($categorias as $c): ?>
              <option value="<?=$c['id']?>" <?=$old['categoria_id']==$c['id']?'selected':''?>>
                <?= h($c['codigo'].($c['descripcion']?' — '.$c['descripcion']:'')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-4">
          <label for="tipo_id">Tipo</label>
          <div class="row">
            <select name="tipo_id" id="tipo_id" disabled style="flex:1;">
              <option value="">(Selecciona una categoría primero)</option>
            </select>
            <button type="button" class="btn small" id="btnAddTipo">+ nuevo</button>
          </div>
        </div>

        <div class="col-4">
          <label for="carroceria_id">Carrocería</label>
          <div class="row">
            <select name="carroceria_id" id="carroceria_id" disabled style="flex:1;">
              <option value="">(Selecciona un tipo primero)</option>
            </select>
            <button type="button" class="btn small" id="btnAddCarroceria">+ nuevo</button>
          </div>
        </div>

        <!-- Marca / Modelo -->
        <div class="col-4">
          <label for="marca_id">Marca</label>
          <div class="row">
            <select name="marca_id" id="marca_id" required style="flex:1;">
              <option value="">(Selecciona)</option>
              <?php foreach($marcas as $m): ?>
                <option value="<?=$m['id']?>" <?=$old['marca_id']==$m['id']?'selected':''?>><?=h($m['nombre'])?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn small" id="btnAddMarca">+ nuevo</button>
          </div>
        </div>

        <div class="col-4">
          <label for="modelo_id">Modelo</label>
          <div class="row">
            <select name="modelo_id" id="modelo_id" disabled style="flex:1;">
              <option value="">(Selecciona una marca primero)</option>
            </select>
            <button type="button" class="btn small" id="btnAddModelo">+ nuevo</button>
          </div>
        </div>

        <div class="col-4">
          <label for="color">Color</label>
          <input id="color" name="color" value="<?=h($old['color'])?>">
        </div>

        <div class="col-4">
          <label for="largo_mm">Largo (m)</label>
          <input id="largo_mm" name="largo_mm" inputmode="decimal" value="<?=h($old['largo_mm'])?>" placeholder="Ej. 2.10">
        </div>
        <div class="col-4">
          <label for="ancho_mm">Ancho (m)</label>
          <input id="ancho_mm" name="ancho_mm" inputmode="decimal" value="<?=h($old['ancho_mm'])?>" placeholder="Ej. 1.23">
        </div>
        <div class="col-4">
          <label for="alto_mm">Alto (m)</label>
          <input id="alto_mm" name="alto_mm" inputmode="decimal" value="<?=h($old['alto_mm'])?>" placeholder="Ej. 1.45">
        </div>

        <div class="col-12">
          <label for="notas">Notas</label>
          <textarea id="notas" name="notas" rows="3"><?=h($old['notas'])?></textarea>
        </div>

        <div class="col-12 row" style="justify-content:flex-end;">
          <?php if($isEmbed): ?>
            <button class="btn sec" type="button" id="btnCerrarEmbed">Cancelar</button>
          <?php else: ?>
            <a class="btn sec" href="vehiculo_listar.php">Volver</a>
          <?php endif; ?>
          <button class="btn" type="submit">Guardar</button>
        </div>
      </form>
    </div>

    <div class="foot">
      <span class="hint">UIAT Norte · Registro de vehículos</span>
      <span class="hint">Auto light/dark • Glass • Compacto</span>
    </div>
  </div>
</div>

<script>
/* ===== Dependencias en cliente ===== */
const MODELOS     = <?= json_encode($modelos,     JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const TIPOS       = <?= json_encode($tipos,       JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const CARROCERIAS = <?= json_encode($carrocerias, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

const OLD = {
  marca:      "<?= h($old['marca_id']) ?>",
  modelo:     "<?= h($old['modelo_id']) ?>",
  categoria:  "<?= h($old['categoria_id']) ?>",
  tipo:       "<?= h($old['tipo_id']) ?>",
  carroceria: "<?= h($old['carroceria_id']) ?>",
};

function option(value, label, selected=false){
  const o = document.createElement('option');
  o.value = value; o.textContent = label;
  if (selected) o.selected = true;
  return o;
}
function clearInit(sel, placeholder){
  sel.innerHTML = ""; sel.appendChild(option("", placeholder)); sel.disabled = false;
}

/* Modelo <- Marca */
function refreshModelos(){
  const selMarca  = document.getElementById('marca_id');
  const selModelo = document.getElementById('modelo_id');
  clearInit(selModelo, "(Selecciona)");

  const marcaId = selMarca.value;
  if(!marcaId){ selModelo.innerHTML = '<option value="">(Selecciona una marca primero)</option>'; selModelo.disabled = true; return; }

  MODELOS.filter(m => String(m.marca_id)===String(marcaId))
         .forEach(m => selModelo.appendChild(option(m.id, m.nombre, String(m.id)===OLD.modelo)));
  selModelo.disabled = false;
}

/* Tipo <- Categoría */
function refreshTipos(){
  const selCat  = document.getElementById('categoria_id');
  const selTipo = document.getElementById('tipo_id');
  clearInit(selTipo, "(Selecciona)");

  const catId = selCat.value;
  if(!catId){ selTipo.innerHTML = '<option value="">(Selecciona una categoría primero)</option>'; selTipo.disabled = true; return; }

  TIPOS.filter(t => String(t.categoria_id)===String(catId))
       .forEach(t => selTipo.appendChild(option(t.id, `${t.codigo} — ${t.nombre}`, String(t.id)===OLD.tipo)));
  selTipo.disabled = false;
  refreshCarrocerias();
}

/* Carrocería <- Tipo */
function refreshCarrocerias(){
  const selTipo = document.getElementById('tipo_id');
  const selCar  = document.getElementById('carroceria_id');
  clearInit(selCar, "(Selecciona)");

  const tipoId = selTipo.value;
  if(!tipoId){ selCar.innerHTML = '<option value="">(Selecciona un tipo primero)</option>'; selCar.disabled = true; return; }

  CARROCERIAS.filter(c => String(c.tipo_id)===String(tipoId))
             .forEach(c => selCar.appendChild(option(c.id, c.nombre, String(c.id)===OLD.carroceria)));
  selCar.disabled = false;
}

/* Eventos cascada */
document.getElementById('marca_id').addEventListener('change', () => { OLD.modelo = ""; refreshModelos(); });
document.getElementById('categoria_id').addEventListener('change', () => { OLD.tipo = ""; refreshTipos(); });
document.getElementById('tipo_id').addEventListener('change', () => { OLD.carroceria = ""; refreshCarrocerias(); });

/* Init */
window.addEventListener('DOMContentLoaded', () => {
  if(document.getElementById('marca_id').value){ refreshModelos(); }
  if(document.getElementById('categoria_id').value){ refreshTipos(); }
  if(document.getElementById('tipo_id').value){ refreshCarrocerias(); }
});

/* Placa uppercase */
document.getElementById('placa').addEventListener('input', e => e.target.value = e.target.value.toUpperCase());
</script>

<!-- ========= MODAL: Crear Catálogo ========= -->
<div class="modal-mask" id="catModalMask" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="catModalTitle">
    <h3 id="catModalTitle">Nuevo registro</h3>
    <div id="catModalBody" class="grid" style="grid-template-columns: repeat(12, 1fr); gap:10px;">
      <div class="col-12 modal-block" id="blkMarca">
        <label>Nombre de la marca</label>
        <input id="inpMarcaNombre" placeholder="Ej. Toyota">
      </div>
      <div class="col-12 modal-block hidden" id="blkModelo">
        <div class="help">Se creará para la marca seleccionada.</div>
        <label>Nombre del modelo</label>
        <input id="inpModeloNombre" placeholder="Ej. Corolla">
      </div>
      <div class="col-6 modal-block hidden" id="blkTipo">
        <label>Código del tipo</label>
        <input id="inpTipoCodigo" placeholder="Ej. M1">
      </div>
      <div class="col-6 modal-block hidden" id="blkTipo2">
        <label>Nombre del tipo</label>
        <input id="inpTipoNombre" placeholder="Ej. Automóvil">
      </div>
      <div class="col-12 hidden" id="helpTipo">
        <div class="help">Se creará para la categoría seleccionada.</div>
      </div>
      <div class="col-12 modal-block hidden" id="blkCarroceria">
        <div class="help">Se creará para el tipo seleccionado.</div>
        <label>Nombre de la carrocería</label>
        <input id="inpCarroceriaNombre" placeholder="Ej. Sedán">
      </div>
    </div>
    <div class="actions">
      <button type="button" class="btn sec" id="btnCatCancel">Cancelar</button>
      <button type="button" class="btn" id="btnCatSave">Guardar</button>
    </div>
  </div>
</div>

<!-- ========= MODAL: Pegar JSON Seeker ========= -->
<div class="modal-mask" id="jsonModalMask" aria-hidden="true" style="z-index:1200;">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="jsonModalTitle">
    <h3 id="jsonModalTitle">Pegar respuesta JSON de Seeker</h3>
    <div class="help">Abre Seeker con la placa, luego Ctrl+A, Ctrl+C y pega aquí.</div>
    <textarea id="jsonSeekerBox" rows="8" style="width:100%;margin-top:10px;padding:10px;border-radius:12px;"></textarea>
    <div class="actions">
      <button type="button" class="btn sec" id="btnJsonCancel">Cancelar</button>
      <button type="button" class="btn" id="btnJsonAplicar">Aplicar al formulario</button>
    </div>
  </div>
</div>

<!-- ========= MODAL: OCR desde imagen ========= -->
<div class="modal-mask" id="ocrModalMask" aria-hidden="true" style="z-index:1250;">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="ocrModalTitle" style="max-width:760px;">
    <h3 id="ocrModalTitle">Extraer datos desde imagen</h3>
    <div class="help">Sube una captura o foto del documento del vehículo, o pega una imagen con Ctrl+V. Se intentará leer placa, serie, motor, año, color, categoría y medidas.</div>
    <input type="file" id="ocrImageInput" accept="image/png,image/jpeg,image/jpg,image/webp" style="margin-top:12px;">
    <div id="ocrPasteZone" tabindex="0" style="margin-top:12px;padding:12px;border-radius:12px;border:1px dashed rgba(0,0,0,.18); background:rgba(148,163,184,.08);">
      Pega aquí una imagen con <strong>Ctrl+V</strong> o <strong>Cmd+V</strong>
    </div>
    <div id="ocrPreviewWrap" style="display:none; margin-top:12px;">
      <img id="ocrPreview" alt="Vista previa OCR" style="max-width:100%; max-height:320px; border-radius:12px; border:1px solid rgba(0,0,0,.12); object-fit:contain;">
    </div>
    <div id="ocrStatus" class="help" style="margin-top:10px;"></div>
    <textarea id="ocrTextBox" rows="8" style="width:100%;margin-top:10px;padding:10px;border-radius:12px;" placeholder="Aquí aparecerá el texto detectado para revisión."></textarea>
    <div class="actions">
      <button type="button" class="btn sec" id="btnOcrCancel">Cancelar</button>
      <button type="button" class="btn sec" id="btnOcrProcesar">Procesar imagen</button>
      <button type="button" class="btn" id="btnOcrAplicar" disabled>Aplicar al formulario</button>
    </div>
  </div>
</div>

<script>
async function postForm(url, dataObj){
  const body = new URLSearchParams(dataObj);
  const res  = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body });
  let js; try{ js = await res.json(); } catch{ js = {ok:false,msg:'Respuesta inválida'} }
  if(!res.ok || !js.ok){ throw new Error(js.msg || 'Error en la operación'); }
  return js;
}

/* ===== Modal catálogo ===== */
const mask = document.getElementById('catModalMask');
const title= document.getElementById('catModalTitle');

const blkMarca = document.getElementById('blkMarca');
const blkModelo= document.getElementById('blkModelo');
const blkTipo  = document.getElementById('blkTipo');
const blkTipo2 = document.getElementById('blkTipo2');
const blkCar   = document.getElementById('blkCarroceria');
const helpTipo = document.getElementById('helpTipo');

const inpMarcaNombre     = document.getElementById('inpMarcaNombre');
const inpModeloNombre    = document.getElementById('inpModeloNombre');
const inpTipoCodigo      = document.getElementById('inpTipoCodigo');
const inpTipoNombre      = document.getElementById('inpTipoNombre');
const inpCarroceriaNombre= document.getElementById('inpCarroceriaNombre');

let currentKind = null;

function showBlocks(kind){
  [blkMarca, blkModelo, blkTipo, blkTipo2, blkCar, helpTipo].forEach(el => el.classList.add('hidden'));
  inpMarcaNombre.value = inpModeloNombre.value = inpTipoCodigo.value = inpTipoNombre.value = inpCarroceriaNombre.value = '';
  if(kind==='marca'){ blkMarca.classList.remove('hidden'); title.textContent='Nueva marca'; }
  if(kind==='modelo'){ blkModelo.classList.remove('hidden'); title.textContent='Nuevo modelo'; }
  if(kind==='tipo'){ blkTipo.classList.remove('hidden'); blkTipo2.classList.remove('hidden'); helpTipo.classList.remove('hidden'); title.textContent='Nuevo tipo'; }
  if(kind==='carroceria'){ blkCar.classList.remove('hidden'); title.textContent='Nueva carrocería'; }
}
function openModal(kind){
  currentKind = kind; showBlocks(kind);
  mask.style.display='flex'; mask.setAttribute('aria-hidden','false');
  setTimeout(()=>{
    (kind==='marca' && inpMarcaNombre.focus())||
    (kind==='modelo' && inpModeloNombre.focus())||
    (kind==='tipo' && inpTipoCodigo.focus())||
    (kind==='carroceria' && inpCarroceriaNombre.focus());
  },0);
}
function closeModal(){ mask.style.display='none'; mask.setAttribute('aria-hidden','true'); }

document.getElementById('btnCatCancel').addEventListener('click', closeModal);
mask.addEventListener('click', (e)=>{ if(e.target===mask) closeModal(); });

document.getElementById('btnAddMarca').addEventListener('click', ()=> openModal('marca'));
document.getElementById('btnAddModelo').addEventListener('click', ()=>{
  if(!document.getElementById('marca_id').value) return alert('Primero selecciona una MARCA.');
  openModal('modelo');
});
document.getElementById('btnAddTipo').addEventListener('click', ()=>{
  if(!document.getElementById('categoria_id').value) return alert('Primero selecciona una CATEGORÍA.');
  openModal('tipo');
});
document.getElementById('btnAddCarroceria').addEventListener('click', ()=>{
  if(!document.getElementById('tipo_id').value) return alert('Primero selecciona un TIPO.');
  openModal('carroceria');
});

document.getElementById('btnCatSave').addEventListener('click', async ()=>{
  try{
    if(currentKind==='marca'){
      const nombre = inpMarcaNombre.value.trim(); if(!nombre) return alert('Ingresa el nombre de la marca.');
      const js = await postForm('add_catalogo.php',{kind:'marca',nombre});
      const sel = document.getElementById('marca_id'); sel.add(new Option(js.label, js.id, true, true));
      sel.dispatchEvent(new Event('change')); alert('Marca creada.'); closeModal(); return;
    }
    if(currentKind==='modelo'){
      const mid = document.getElementById('marca_id').value; if(!mid) return alert('Selecciona una marca.');
      const nombre = inpModeloNombre.value.trim(); if(!nombre) return alert('Ingresa el nombre del modelo.');
      const js = await postForm('add_catalogo.php',{kind:'modelo',nombre, padre_id: mid});
      const sel = document.getElementById('modelo_id'); if(sel.disabled) sel.disabled=false;
      sel.add(new Option(js.label, js.id, true, true));
      MODELOS.push({id: js.id, marca_id: mid, nombre: js.label});
      alert('Modelo creado.'); closeModal(); return;
    }
    if(currentKind==='tipo'){
      const cid = document.getElementById('categoria_id').value; if(!cid) return alert('Selecciona una categoría.');
      const codigo= inpTipoCodigo.value.trim(); if(!codigo) return alert('Ingresa el código del tipo.');
      const nombre= inpTipoNombre.value.trim(); if(!nombre) return alert('Ingresa el nombre del tipo.');
      const js = await postForm('add_catalogo.php',{kind:'tipo', codigo, nombre, padre_id: cid});
      const sel = document.getElementById('tipo_id'); if(sel.disabled) sel.disabled=false;
      sel.add(new Option(`${codigo} — ${nombre}`, js.id, true, true));
      TIPOS.push({id: js.id, categoria_id: cid, codigo, nombre});
      alert('Tipo creado.'); closeModal(); refreshCarrocerias(); return;
    }
    if(currentKind==='carroceria'){
      const tid = document.getElementById('tipo_id').value; if(!tid) return alert('Selecciona un tipo.');
      const nombre = inpCarroceriaNombre.value.trim(); if(!nombre) return alert('Ingresa el nombre de la carrocería.');
      const js = await postForm('add_catalogo.php',{kind:'carroceria', nombre, padre_id: tid});
      const sel = document.getElementById('carroceria_id'); if(sel.disabled) sel.disabled=false;
      sel.add(new Option(js.label, js.id, true, true));
      CARROCERIAS.push({id: js.id, tipo_id: tid, nombre});
      alert('Carrocería creada.'); closeModal(); return;
    }
    alert('Operación no soportada.');
  }catch(e){ alert('Error: '+e.message); }
});
</script>

<script>
// ========= Integracion PLACA (existe / consultar backend / pegar json) =========
const EDIT_URL = "vehiculo_editar.php";    // <-- cambia si tu editor se llama distinto

const catCodeToId = <?= json_encode($catCodeToId, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

const placaInput  = document.getElementById('placa');
const placaStatus = document.getElementById('placaStatus');

const btnCheckPlaca  = document.getElementById('btnCheckPlaca');
const btnAbrirSeeker = document.getElementById('btnAbrirSeeker');
const btnPegarJson   = document.getElementById('btnPegarJson');

const jsonMask   = document.getElementById('jsonModalMask');
const jsonBox    = document.getElementById('jsonSeekerBox');
const btnJsonCancel = document.getElementById('btnJsonCancel');
const btnJsonAplicar = document.getElementById('btnJsonAplicar');
const btnSubirImagen = document.getElementById('btnSubirImagen');
const ocrMask = document.getElementById('ocrModalMask');
const ocrImageInput = document.getElementById('ocrImageInput');
const ocrPasteZone = document.getElementById('ocrPasteZone');
const ocrPreviewWrap = document.getElementById('ocrPreviewWrap');
const ocrPreview = document.getElementById('ocrPreview');
const ocrStatus = document.getElementById('ocrStatus');
const ocrTextBox = document.getElementById('ocrTextBox');
const btnOcrCancel = document.getElementById('btnOcrCancel');
const btnOcrProcesar = document.getElementById('btnOcrProcesar');
const btnOcrAplicar = document.getElementById('btnOcrAplicar');
let ocrClipboardFile = null;

function setStatus(msg, ok=null){
  placaStatus.textContent = msg || '';
  placaStatus.style.color = ok===true ? '#19a974' : ok===false ? '#ff4d4d' : '';
}

async function readJsonSafe(response){
  const raw = await response.text();
  try{
    return raw ? JSON.parse(raw) : {};
  }catch{
    throw new Error('El servidor no devolvio JSON valido.');
  }
}

function extractSeekerPayload(payload){
  if(!payload || typeof payload !== 'object') return null;
  if(payload.respuesta && typeof payload.respuesta === 'object') return payload.respuesta;
  if(payload.data && typeof payload.data === 'object') return payload.data;
  return payload;
}

function openJsonModal(){
  jsonBox.value = '';
  jsonMask.style.display = 'flex';
  jsonMask.setAttribute('aria-hidden','false');
  setTimeout(()=>jsonBox.focus(),0);
}
function closeJsonModal(){
  jsonMask.style.display = 'none';
  jsonMask.setAttribute('aria-hidden','true');
}
btnJsonCancel.addEventListener('click', closeJsonModal);
jsonMask.addEventListener('click', (e)=>{ if(e.target===jsonMask) closeJsonModal(); });

function openOcrModal(){
  ocrClipboardFile = null;
  ocrImageInput.value = '';
  ocrTextBox.value = '';
  ocrPreviewWrap.style.display = 'none';
  ocrPreview.removeAttribute('src');
  btnOcrAplicar.disabled = true;
  setOcrStatus('Sube una imagen del documento y luego procesa el OCR.', null);
  ocrMask.style.display = 'flex';
  ocrMask.setAttribute('aria-hidden','false');
  setTimeout(()=>ocrPasteZone.focus(), 0);
}

function closeOcrModal(){
  ocrMask.style.display = 'none';
  ocrMask.setAttribute('aria-hidden','true');
}

function setOcrStatus(msg, ok=null){
  ocrStatus.textContent = msg || '';
  ocrStatus.style.color = ok===true ? '#19a974' : ok===false ? '#ff4d4d' : '';
}

function loadOcrPreviewFromFile(file){
  if(!file){
    ocrPreviewWrap.style.display = 'none';
    ocrPreview.removeAttribute('src');
    return;
  }

  const reader = new FileReader();
  reader.onload = () => {
    ocrPreview.src = String(reader.result || '');
    ocrPreviewWrap.style.display = 'block';
  };
  reader.readAsDataURL(file);
}

function setOcrImageFile(file, sourceLabel){
  ocrClipboardFile = file || null;
  loadOcrPreviewFromFile(file || null);
  if(file){
    setOcrStatus(`${sourceLabel} lista. Presiona "Procesar imagen".`, null);
  }
}

function getOcrSelectedFile(){
  return (ocrImageInput.files && ocrImageInput.files[0]) || ocrClipboardFile;
}

function extractImageFromClipboardEvent(event){
  const items = event.clipboardData?.items || [];
  for(const item of items){
    if(item.kind === 'file' && item.type.startsWith('image/')){
      const file = item.getAsFile();
      if(file) return file;
    }
  }
  return null;
}

btnOcrCancel.addEventListener('click', closeOcrModal);
ocrMask.addEventListener('click', (e)=>{ if(e.target===ocrMask) closeOcrModal(); });
btnSubirImagen.addEventListener('click', openOcrModal);

ocrImageInput.addEventListener('change', ()=>{
  const file = ocrImageInput.files && ocrImageInput.files[0];
  if(!file){
    ocrClipboardFile = null;
    loadOcrPreviewFromFile(null);
    return;
  }
  setOcrImageFile(file, 'Imagen subida');
});

ocrPasteZone.addEventListener('paste', (event)=>{
  const file = extractImageFromClipboardEvent(event);
  if(!file) return;
  event.preventDefault();
  ocrImageInput.value = '';
  setOcrImageFile(file, 'Imagen pegada');
});

document.addEventListener('paste', (event)=>{
  if(ocrMask.getAttribute('aria-hidden') !== 'false') return;
  const tag = document.activeElement?.tagName || '';
  if(tag === 'INPUT' || tag === 'TEXTAREA') return;
  const file = extractImageFromClipboardEvent(event);
  if(!file) return;
  event.preventDefault();
  ocrImageInput.value = '';
  setOcrImageFile(file, 'Imagen pegada');
});

async function checkPlacaExists(placa){
  const r = await fetch(`vehiculo_check.php?placa=${encodeURIComponent(placa)}`, {credentials:'same-origin'});
  return await readJsonSafe(r);
}

function setSelectValue(id, value){
  const el = document.getElementById(id);
  if(!el) return;
  el.value = value || '';
  el.dispatchEvent(new Event('change'));
}

function mapCategoriaId(coCateg){
  const c = String(coCateg || '').toUpperCase().trim();

  // si viene M1, M2, N1, etc
  if (catCodeToId[c]) return String(catCodeToId[c]);

  // si viene "Categoria M"
  if (c.includes('CATEGORIA M') && catCodeToId['M1']) return String(catCodeToId['M1']);

  return '';
}

function toNum(s){
  if(!s) return '';
  const m = String(s).replace(',', '.').match(/[\d.]+/);
  return m ? m[0] : '';
}

function normalizeTextValue(value){
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^\w\s]/g, ' ')
    .replace(/_/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .toUpperCase();
}

function extractLineValue(lines, labels){
  const normalizedLabels = labels.map(normalizeTextValue);
  for(const line of lines){
    const raw = String(line || '').trim();
    if(!raw) continue;

    const normalized = normalizeTextValue(raw);
    for(const label of normalizedLabels){
      if(!normalized.startsWith(label)) continue;

      const tokens = normalized.split(' ');
      const labelTokens = label.split(' ');
      const remainderTokens = tokens.slice(labelTokens.length);
      let value = remainderTokens.join(' ').replace(/^[:;\-.\s]+/, '').trim();

      if(value === ''){
        const match = raw.match(/^[^:]+[:;]\s*(.+)$/);
        value = match ? String(match[1] || '').trim() : '';
      }

      if(value !== '') return value;
    }
  }
  return '';
}

function extractRegexValue(rawText, patterns){
  const text = String(rawText || '').replace(/\r/g, ' ');
  for(const pattern of patterns){
    const match = text.match(pattern);
    if(match && match[1]){
      return String(match[1]).trim();
    }
  }
  return '';
}

function normalizePlateCandidate(value){
  const compact = normalizeTextValue(value).replace(/[^A-Z0-9]/g, '');
  if(/^[A-Z0-9]{6}$/.test(compact)){
    return `${compact.slice(0,3)}-${compact.slice(3)}`;
  }
  if(/^[A-Z0-9]{3}-[A-Z0-9]{3}$/.test(String(value || '').toUpperCase().trim())){
    return String(value || '').toUpperCase().trim();
  }
  return compact;
}

function extractPlateCandidate(rawText){
  const text = String(rawText || '').replace(/\r/g, ' ');
  const labeled = extractRegexValue(text, [
    /PLACA\s*[:;]?\s*([A-Z0-9-]{6,8})/i,
  ]);
  const candidate = labeled || extractRegexValue(text, [
    /\b([A-Z]{3}-?[0-9]{3})\b/i,
    /\b([A-Z0-9]{3}-?[A-Z0-9]{3})\b/i,
  ]);
  return normalizePlateCandidate(candidate);
}

function extractVinCandidate(rawText){
  const text = String(rawText || '').replace(/\r/g, ' ');
  const labeled = extractRegexValue(text, [
    /(?:N\s*DE\s*VIN|N\s*VIN|NO\s*VIN|NUM\s*VIN|VIN)\s*[:;]?\s*([A-HJ-NPR-Z0-9]{17})/i,
    /(?:N\s*SERIE|NRO\s*SERIE|NO\s*SERIE|NUM\s*SERIE)\s*[:;]?\s*([A-HJ-NPR-Z0-9]{17})/i,
  ]);
  if(labeled) return labeled.toUpperCase();

  const global = extractRegexValue(text, [
    /\b([A-HJ-NPR-Z0-9]{17})\b/i,
  ]);
  return global ? global.toUpperCase() : '';
}

function extractMotorCandidate(rawText){
  const text = String(rawText || '').replace(/\r/g, ' ');
  const labeled = extractRegexValue(text, [
    /(?:N\s*MOTOR|NRO\s*MOTOR|NO\s*MOTOR|NUM\s*MOTOR|MOTOR)\s*[:;]?\s*([A-Z0-9-]{5,})/i,
  ]);
  return labeled ? labeled.toUpperCase() : '';
}

function extractCleanLabelValue(rawText, patterns){
  const text = String(rawText || '').replace(/\r/g, ' ');
  for(const pattern of patterns){
    const match = text.match(pattern);
    if(match && match[1]){
      return cleanSimpleField(match[1]);
    }
  }
  return '';
}

function cleanSimpleField(value){
  return String(value || '')
    .replace(/\bOFICINA\b.*$/i, '')
    .replace(/\bLIMA\b.*$/i, '')
    .replace(/^[:;\-.\s]+/, '')
    .trim();
}

function findSelectOptionValue(selectId, expectedText){
  const select = document.getElementById(selectId);
  if(!select || !expectedText) return '';
  const wanted = normalizeTextValue(expectedText);
  const option = Array.from(select.options).find(opt => normalizeTextValue(opt.textContent) === wanted)
    || Array.from(select.options).find(opt => normalizeTextValue(opt.textContent).includes(wanted) || wanted.includes(normalizeTextValue(opt.textContent)));
  return option ? String(option.value) : '';
}

function findCarroceriaMatch(nombre){
  const wanted = normalizeTextValue(nombre);
  return CARROCERIAS.find(item => normalizeTextValue(item.nombre) === wanted)
      || CARROCERIAS.find(item => normalizeTextValue(item.nombre).includes(wanted) || wanted.includes(normalizeTextValue(item.nombre)))
      || null;
}

function findTipoById(tipoId){
  return TIPOS.find(item => String(item.id) === String(tipoId)) || null;
}

async function ensureMarcaModeloSelections(marcaNombre, modeloNombre){
  const marca = cleanSimpleField(marcaNombre);
  const modelo = cleanSimpleField(modeloNombre);
  const marcaSelect = document.getElementById('marca_id');

  let marcaValue = marca ? findSelectOptionValue('marca_id', marca) : '';
  if(!marcaValue && marca){
    try{
      const js = await postForm('add_catalogo.php', { kind:'marca', nombre: marca });
      marcaSelect.add(new Option(js.label, js.id, true, true));
      marcaValue = String(js.id);
    }catch(e){
      marcaValue = findSelectOptionValue('marca_id', marca);
    }
  }

  if(marcaValue){
    setSelectValue('marca_id', marcaValue);
  }

  if(!modelo || !marcaValue) return;

  refreshModelos();
  let modeloValue = findSelectOptionValue('modelo_id', modelo);
  if(!modeloValue){
    try{
      const js = await postForm('add_catalogo.php', { kind:'modelo', nombre: modelo, padre_id: marcaValue });
      MODELOS.push({ id: js.id, marca_id: marcaValue, nombre: js.label });
      refreshModelos();
      modeloValue = String(js.id);
    }catch(e){
      refreshModelos();
      modeloValue = findSelectOptionValue('modelo_id', modelo);
    }
  }

  if(modeloValue){
    setSelectValue('modelo_id', modeloValue);
  }
}

function parseVehiculoImageText(rawText){
  const text = String(rawText || '').replace(/\r/g, '');
  const lines = text.split('\n').map(line => line.trim()).filter(Boolean);

  const parsed = {
    placa: extractPlateCandidate(text),
    tipoUso: cleanSimpleField(extractLineValue(lines, ['TIPO USO', 'TIPOUSO'])),
    categoria: extractLineValue(lines, ['CATEGORIA', 'CATEGORÍA']),
    carroceria: extractCleanLabelValue(text, [
      /CARROCER[ÍI]A\s*[:;]?\s*([A-ZÁÉÍÓÚÑ0-9 ]{3,30}?)(?=\s+(?:MARCA|MODELO|A[ÑN]O|N\s+MOTOR|TIPO|LONGITUD|ANCHO|ALTURA|PESO|OFICINA)\b|$)/i,
    ]) || cleanSimpleField(extractLineValue(lines, ['CARROCERIA', 'CARROCERÍA'])),
    marca: extractCleanLabelValue(text, [
      /MARCA\s*[:;]?\s*([A-ZÁÉÍÓÚÑ0-9 ]{2,30}?)(?=\s+(?:MODELO|A[ÑN]O|N\s+MOTOR|TIPO|LONGITUD|ANCHO|ALTURA|PESO|OFICINA)\b|$)/i,
    ]) || cleanSimpleField(extractLineValue(lines, ['MARCA'])),
    modelo: extractCleanLabelValue(text, [
      /MODELO\s*[:;]?\s*([A-ZÁÉÍÓÚÑ0-9 ]{2,30}?)(?=\s+(?:A[ÑN]O|N\s+MOTOR|TIPO|LONGITUD|ANCHO|ALTURA|PESO|OFICINA)\b|$)/i,
    ]) || cleanSimpleField(extractLineValue(lines, ['MODELO'])),
    anioMod: extractLineValue(lines, ['ANO MOD', 'AÑO MOD']),
    anioFab: extractLineValue(lines, ['ANO FAB', 'AÑO FAB']),
    serie: extractVinCandidate(text),
    vin: extractVinCandidate(text),
    color: extractCleanLabelValue(text, [
      /COLOR\s*1?\s*[:;]?\s*([A-ZÁÉÍÓÚÑ ]{3,20}?)(?=\s+(?:COLOR|N\s+MOTOR|TIPO|LONGITUD|ANCHO|ALTURA|PESO|OFICINA|CARROCER|MARCA|MODELO)\b|$)/i,
    ]) || cleanSimpleField(extractLineValue(lines, ['COLOR 1', 'COLOR'])),
    motor: extractMotorCandidate(text),
    combustible: cleanSimpleField(extractLineValue(lines, ['TIPO COMBUS', 'TIPO COMBUST', 'TIPO COMB'])),
    longitud: extractRegexValue(text, [
      /LONGITUD\s*[:;]?\s*([0-9]+(?:[.,][0-9]+)?)/i,
      /LONGITUD\s+([0-9]+(?:[.,][0-9]+)?)/i,
    ]) || extractLineValue(lines, ['LONGITUD']),
    ancho: extractRegexValue(text, [
      /ANCHO\s*[:;]?\s*([0-9]+(?:[.,][0-9]+)?)/i,
      /ANCHO\s+([0-9]+(?:[.,][0-9]+)?)/i,
    ]) || extractLineValue(lines, ['ANCHO']),
    altura: extractRegexValue(text, [
      /ALTURA\s*[:;]?\s*([0-9]+(?:[.,][0-9]+)?)/i,
      /ALTURA\s+([0-9]+(?:[.,][0-9]+)?)/i,
    ]) || extractLineValue(lines, ['ALTURA']),
    asientos: extractLineValue(lines, ['N ASIENTOS', 'NUM ASIENTOS']),
    partida: extractLineValue(lines, ['N PARTIDA', 'NO PARTIDA', 'NUM PARTIDA']),
  };

  return parsed;
}

async function applyImageParsedData(parsed, rawText){
  if(parsed.placa){
    placaInput.value = normalizePlateCandidate(parsed.placa);
  }
  if(parsed.motor){
    document.getElementById('nro_motor').value = parsed.motor.replace(/\s+/g, '');
  }
  const serie = parsed.serie || parsed.vin;
  if(serie){
    document.getElementById('serie_vin').value = serie.replace(/\s+/g, '');
  }

  const year = (parsed.anioMod || parsed.anioFab || '').match(/\b(19|20)\d{2}\b/);
  if(year){
    document.getElementById('anio').value = year[0];
  }

  if(parsed.color){
    document.getElementById('color').value = parsed.color;
  }

  document.getElementById('largo_mm').value = toNum(parsed.longitud);
  document.getElementById('ancho_mm').value = toNum(parsed.ancho);
  document.getElementById('alto_mm').value = toNum(parsed.altura);

  const categoria = normalizeTextValue(parsed.categoria).match(/\b(M|N|O|L)\d\b/);
  if(categoria && catCodeToId[categoria[0]]){
    setSelectValue('categoria_id', String(catCodeToId[categoria[0]]));
  }

  await ensureMarcaModeloSelections(parsed.marca, parsed.modelo);

  if(parsed.carroceria){
    const carroceriaMatch = findCarroceriaMatch(parsed.carroceria);
    if(carroceriaMatch){
      const tipoInfo = findTipoById(carroceriaMatch.tipo_id);
      if(tipoInfo && tipoInfo.categoria_id){
        setSelectValue('categoria_id', String(tipoInfo.categoria_id));
      }
      refreshTipos();
      const tipoValue = String(carroceriaMatch.tipo_id || '');
      if(tipoValue){
        setSelectValue('tipo_id', tipoValue);
      }
      refreshCarrocerias();
      setSelectValue('carroceria_id', String(carroceriaMatch.id));
    }
  }

  const notasEl = document.getElementById('notas');
  const noteParts = [];
  if(parsed.tipoUso) noteParts.push(`Tipo Uso OCR: ${parsed.tipoUso}`);
  if(parsed.marca) noteParts.push(`Marca OCR: ${parsed.marca}`);
  if(parsed.modelo) noteParts.push(`Modelo OCR: ${parsed.modelo}`);
  if(parsed.carroceria) noteParts.push(`Carrocería OCR: ${parsed.carroceria}`);
  if(parsed.combustible) noteParts.push(`Combustible OCR: ${parsed.combustible}`);
  if(parsed.partida) noteParts.push(`Partida OCR: ${parsed.partida}`);
  if(parsed.asientos) noteParts.push(`Asientos OCR: ${parsed.asientos}`);
  if(rawText){
    noteParts.push(`OCR TEXTO: ${rawText.replace(/\s+/g, ' ').trim()}`);
  }

  if(noteParts.length){
    const extra = noteParts.join(' | ');
    notasEl.value = notasEl.value ? `${notasEl.value}\n${extra}` : extra;
  }
}

async function ensureTesseractLoaded(){
  if(window.Tesseract) return window.Tesseract;

  await new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
    script.async = true;
    script.onload = resolve;
    script.onerror = () => reject(new Error('No se pudo cargar el motor OCR.'));
    document.head.appendChild(script);
  });

  if(!window.Tesseract){
    throw new Error('El motor OCR no quedó disponible.');
  }

  return window.Tesseract;
}

async function applySeekerToForm(data){
  if(data.numPlaca) placaInput.value = String(data.numPlaca).toUpperCase();
  document.getElementById('nro_motor').value = data.numMotor || '';
  document.getElementById('serie_vin').value = data.noVin || data.numSerie || '';
  document.getElementById('anio').value = (data.anoFab || '').toString().trim();
  document.getElementById('color').value = data.color || '';
  document.getElementById('largo_mm').value = toNum(data.longitud);
  document.getElementById('ancho_mm').value = toNum(data.ancho);
  document.getElementById('alto_mm').value  = toNum(data.altura);

  const catId = mapCategoriaId(data.coCateg);
  if(catId) setSelectValue('categoria_id', catId);

  await ensureMarcaModeloSelections(data.marca || '', data.modelo || '');

  const notaParts = [];
  if(data.marca) notaParts.push(`Marca API: ${data.marca}`);
  if(data.modelo) notaParts.push(`Modelo API: ${data.modelo}`);
  if(data.descTipoCarr) notaParts.push(`Carrocería API: ${data.descTipoCarr}`);
  if(data.descTipoComb) notaParts.push(`Combustible API: ${data.descTipoComb}`);
  if(data.fecIns) notaParts.push(`Fec. Inscripción API: ${data.fecIns}`);
  if(data.tipoActo) notaParts.push(`Acto API: ${data.tipoActo}`);

  const notasEl = document.getElementById('notas');
  const extra = notaParts.join(' | ');
  if(extra){
    notasEl.value = (notasEl.value ? (notasEl.value + "\n") : "") + extra;
  }
}

btnCheckPlaca.addEventListener('click', async ()=>{
  const placa = placaInput.value.trim().toUpperCase();
  if(!placa) return setStatus('Ingresa placa.', false);

  setStatus('Verificando en base de datos...', null);
  try{
    const j = await checkPlacaExists(placa);
    if(!j.ok) return setStatus(j.msg || 'Error verificando.', false);

    if(j.exists){
      setStatus(`Ya existe (ID ${j.id}). Redirigiendo a editar...`, true);
      window.location.href = `${EDIT_URL}?id=${encodeURIComponent(j.id)}`;
    }else{
      setStatus('No existe. Puedes crearlo o traer datos de Seeker.', false);
    }
  }catch(e){
    setStatus('Error verificando: ' + (e.message||e), false);
  }
});

btnAbrirSeeker.addEventListener('click', ()=>{
  const placa = placaInput.value.trim().toUpperCase();
  const url = placa ? `https://seeker.red/?placa=${encodeURIComponent(placa)}` : 'https://seeker.red/';
  window.open(url, '_blank', 'noopener,noreferrer');
  setStatus('Se abrió Seeker en otra pestaña. Consulta la placa, copia el JSON y luego usa "Pegar JSON".', true);
});

btnPegarJson.addEventListener('click', openJsonModal);

btnJsonAplicar.addEventListener('click', async ()=>{
  const raw = jsonBox.value.trim();
  if(!raw){ alert('Pega el JSON primero.'); return; }

  let data;
  try{ data = extractSeekerPayload(JSON.parse(raw)); }
  catch{ alert('Lo pegado no es JSON válido.'); return; }

  if(!data || (data.status||'') !== 'success'){
    alert('La respuesta no es success. Revisa placa o intenta otra vez.');
    return;
  }

  await applySeekerToForm(data);
  closeJsonModal();
  setStatus('Datos aplicados. Completa Marca/Modelo y guarda.', true);
});

btnOcrProcesar.addEventListener('click', async ()=>{
  const file = getOcrSelectedFile();
  if(!file){
    setOcrStatus('Selecciona o pega una imagen primero.', false);
    return;
  }

  btnOcrProcesar.disabled = true;
  btnOcrAplicar.disabled = true;
  ocrTextBox.value = '';

  try{
    const Tesseract = await ensureTesseractLoaded();
    const result = await Tesseract.recognize(file, 'spa', {
      logger: (message) => {
        if(message.status){
          const progress = typeof message.progress === 'number' ? ` ${Math.round(message.progress * 100)}%` : '';
          setOcrStatus(`${message.status}${progress}`, null);
        }
      }
    });

    const text = String(result?.data?.text || '').trim();
    if(!text){
      throw new Error('No se pudo extraer texto de la imagen.');
    }

    ocrTextBox.value = text;
    setOcrStatus('Texto extraído. Revisa y presiona "Aplicar al formulario".', true);
    btnOcrAplicar.disabled = false;
  }catch(e){
    setOcrStatus(e.message || 'No se pudo procesar la imagen.', false);
  }finally{
    btnOcrProcesar.disabled = false;
  }
});

btnOcrAplicar.addEventListener('click', async ()=>{
  const text = ocrTextBox.value.trim();
  if(!text){
    setOcrStatus('No hay texto extraído para aplicar.', false);
    return;
  }

  const parsed = parseVehiculoImageText(text);
  await applyImageParsedData(parsed, text);
  closeOcrModal();
  setStatus('Datos de la imagen aplicados. Revisa Marca/Modelo y guarda.', true);
});

// aviso rápido al salir de placa
placaInput.addEventListener('blur', async ()=>{
  const placa = placaInput.value.trim().toUpperCase();
  if(!placa) return;
  try{
    const j = await checkPlacaExists(placa);
    if(j.ok && j.exists){
      setStatus(`Ya existe (ID ${j.id}). Presiona "Verificar" para ir a editar.`, true);
    }else if(j.ok && !j.exists){
      setStatus('No existe. Puedes crearlo.', false);
    }
  }catch(e){
    // silencioso
  }
});

const btnCerrarEmbed = document.getElementById('btnCerrarEmbed');
if (btnCerrarEmbed) {
  btnCerrarEmbed.addEventListener('click', ()=>{
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type:'vehiculo_modal_cerrar' }, window.location.origin);
    }
  });
}

const sinPlacaCheckbox = document.getElementById('sin_placa');
if (sinPlacaCheckbox) {
  const syncSinPlaca = ()=>{
    const active = sinPlacaCheckbox.checked;
    placaInput.disabled = active;
    placaInput.required = !active;
    placaInput.placeholder = active ? 'SIN PLACA' : 'ABC-123';
    if (active) {
      placaInput.value = '';
    }
  };
  sinPlacaCheckbox.addEventListener('change', syncSinPlaca);
  syncSinPlaca();
}
</script>

</body>
</html>
