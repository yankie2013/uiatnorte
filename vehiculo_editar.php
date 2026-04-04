<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\VehiculoRepository;
use App\Services\VehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($s)
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
}

$vehiculoRepo = new VehiculoRepository($pdo);
$vehiculoService = new VehiculoService($vehiculoRepo);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: vehiculo_listar.php');
    exit;
}

$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'vehiculo_listar.php';
}

$catalogos = $vehiculoService->catalogos();
$categorias = $catalogos['categorias'];
$marcas = $catalogos['marcas'];
$modelos = $catalogos['modelos'];
$tipos = $catalogos['tipos'];
$carrocerias = $catalogos['carrocerias'];

$veh = $vehiculoRepo->find($id);
if ($veh === null) {
    header('Location: vehiculo_listar.php');
    exit;
}

$err = '';
$old = $vehiculoService->oldInput([], $veh);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $vehiculoService->oldInput($_POST);

    try {
        $vehiculoService->actualizar($id, $_POST);
        header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'msg=editado');
        exit;
    } catch (InvalidArgumentException $e) {
        $err = $e->getMessage();
    } catch (Throwable $e) {
        $err = 'Error al guardar: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UIAT Norte - Editar vehiculo</title>
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

  .modal-mask{ position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:1000; }
  .modal{ width:100%; max-width:560px; background: rgba(var(--g-card),0.98); border-radius:16px; padding:16px; box-shadow:0 20px 50px rgba(0,0,0,.30); border:1px solid rgba(0,0,0,.08); }
  .modal h3{ margin:0 0 10px 0; font-size:18px; }
  .modal .actions{ display:flex; gap:8px; justify-content:flex-end; margin-top:12px; }
  .modal .help{ font-size:12px; color: rgba(var(--g-muted),1) }
  .hidden{ display:none }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="hdr">
      <div class="ttl">Editar vehiculo - ID #<?= (int) $id ?></div>
      <span class="pill">Estilo GIAN</span>
    </div>

    <div class="body">
      <?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>

      <form id="frmEdit" method="post" class="grid" novalidate>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">

        <div class="col-3">
          <label for="placa">Placa</label>
          <input id="placa" name="placa" value="<?= h($old['placa']) ?>" required maxlength="12">
        </div>
        <div class="col-3">
          <label for="serie_vin">Serie / VIN</label>
          <input id="serie_vin" name="serie_vin" value="<?= h($old['serie_vin']) ?>">
        </div>
        <div class="col-3">
          <label for="nro_motor">Nro. motor</label>
          <input id="nro_motor" name="nro_motor" value="<?= h($old['nro_motor']) ?>">
        </div>
        <div class="col-3">
          <label for="anio">Anio</label>
          <input id="anio" name="anio" value="<?= h($old['anio']) ?>" maxlength="4" inputmode="numeric" placeholder="YYYY">
        </div>

        <div class="col-4">
          <label for="categoria_id">Categoria</label>
          <select name="categoria_id" id="categoria_id" required>
            <option value="">(Selecciona)</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $old['categoria_id'] == $c['id'] ? 'selected' : '' ?>>
                <?= h($c['codigo'] . ($c['descripcion'] ? ' - ' . $c['descripcion'] : '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-4">
          <label for="tipo_id">Tipo</label>
          <div class="row">
            <select name="tipo_id" id="tipo_id" disabled style="flex:1;">
              <option value="">(Selecciona una categoria primero)</option>
            </select>
            <button type="button" class="btn small" id="btnAddTipo">+ nuevo</button>
          </div>
        </div>

        <div class="col-4">
          <label for="carroceria_id">Carroceria</label>
          <div class="row">
            <select name="carroceria_id" id="carroceria_id" disabled style="flex:1;">
              <option value="">(Selecciona un tipo primero)</option>
            </select>
            <button type="button" class="btn small" id="btnAddCarroceria">+ nuevo</button>
          </div>
        </div>

        <div class="col-4">
          <label for="marca_id">Marca</label>
          <div class="row">
            <select name="marca_id" id="marca_id" required style="flex:1;">
              <option value="">(Selecciona)</option>
              <?php foreach ($marcas as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $old['marca_id'] == $m['id'] ? 'selected' : '' ?>><?= h($m['nombre']) ?></option>
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
          <input id="color" name="color" value="<?= h($old['color']) ?>">
        </div>

        <div class="col-4">
          <label for="largo_mm">Largo (m)</label>
          <input id="largo_mm" name="largo_mm" inputmode="decimal" value="<?= h($old['largo_mm']) ?>">
        </div>
        <div class="col-4">
          <label for="ancho_mm">Ancho (m)</label>
          <input id="ancho_mm" name="ancho_mm" inputmode="decimal" value="<?= h($old['ancho_mm']) ?>">
        </div>
        <div class="col-4">
          <label for="alto_mm">Alto (m)</label>
          <input id="alto_mm" name="alto_mm" inputmode="decimal" value="<?= h($old['alto_mm']) ?>">
        </div>

        <div class="col-12">
          <label for="notas">Notas</label>
          <textarea id="notas" name="notas" rows="3"><?= h($old['notas']) ?></textarea>
        </div>

        <div class="col-12 row" style="justify-content:flex-end;">
          <a class="btn sec" href="<?= h($returnTo) ?>">Cancelar</a>
          <button class="btn" type="submit">Guardar cambios</button>
        </div>
      </form>
    </div>

    <div class="foot">
      <span class="hint">UIAT Norte - Registro de vehiculos</span>
      <span class="hint">Edicion</span>
    </div>
  </div>
</div>

<script>
const MODELOS = <?= json_encode($modelos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TIPOS = <?= json_encode($tipos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const CARROCERIAS = <?= json_encode($carrocerias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const OLD = {
  marca: "<?= h($old['marca_id']) ?>",
  modelo: "<?= h($old['modelo_id']) ?>",
  categoria: "<?= h($old['categoria_id']) ?>",
  tipo: "<?= h($old['tipo_id']) ?>",
  carroceria: "<?= h($old['carroceria_id']) ?>",
};

function option(value, label, selected = false) {
  const o = document.createElement('option');
  o.value = value;
  o.textContent = label;
  if (selected) o.selected = true;
  return o;
}

function clearInit(sel, placeholder) {
  sel.innerHTML = '';
  sel.appendChild(option('', placeholder));
  sel.disabled = false;
}

function refreshModelos() {
  const selMarca = document.getElementById('marca_id');
  const selModelo = document.getElementById('modelo_id');
  clearInit(selModelo, '(Selecciona)');

  const marcaId = selMarca.value;
  if (!marcaId) {
    selModelo.innerHTML = '<option value="">(Selecciona una marca primero)</option>';
    selModelo.disabled = true;
    return;
  }

  MODELOS
    .filter((m) => String(m.marca_id) === String(marcaId))
    .forEach((m) => selModelo.appendChild(option(m.id, m.nombre, String(m.id) === OLD.modelo)));

  selModelo.disabled = false;
}

function refreshTipos() {
  const selCat = document.getElementById('categoria_id');
  const selTipo = document.getElementById('tipo_id');
  clearInit(selTipo, '(Selecciona)');

  const catId = selCat.value;
  if (!catId) {
    selTipo.innerHTML = '<option value="">(Selecciona una categoria primero)</option>';
    selTipo.disabled = true;
    return;
  }

  TIPOS
    .filter((t) => String(t.categoria_id) === String(catId))
    .forEach((t) => selTipo.appendChild(option(t.id, `${t.codigo} - ${t.nombre}`, String(t.id) === OLD.tipo)));

  selTipo.disabled = false;
  refreshCarrocerias();
}

function refreshCarrocerias() {
  const selTipo = document.getElementById('tipo_id');
  const selCar = document.getElementById('carroceria_id');
  clearInit(selCar, '(Selecciona)');

  const tipoId = selTipo.value;
  if (!tipoId) {
    selCar.innerHTML = '<option value="">(Selecciona un tipo primero)</option>';
    selCar.disabled = true;
    return;
  }

  CARROCERIAS
    .filter((c) => String(c.tipo_id) === String(tipoId))
    .forEach((c) => selCar.appendChild(option(c.id, c.nombre, String(c.id) === OLD.carroceria)));

  selCar.disabled = false;
}

document.getElementById('marca_id').addEventListener('change', () => {
  OLD.modelo = '';
  refreshModelos();
});

document.getElementById('categoria_id').addEventListener('change', () => {
  OLD.tipo = '';
  refreshTipos();
});

document.getElementById('tipo_id').addEventListener('change', () => {
  OLD.carroceria = '';
  refreshCarrocerias();
});

window.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('marca_id').value) refreshModelos();
  if (document.getElementById('categoria_id').value) refreshTipos();
  if (document.getElementById('tipo_id').value) refreshCarrocerias();
});

document.getElementById('frmEdit').addEventListener('submit', (e) => {
  if (!confirm('Confirmas que deseas guardar los cambios del vehiculo?')) {
    e.preventDefault();
  }
});

document.getElementById('placa').addEventListener('input', (e) => {
  e.target.value = e.target.value.toUpperCase();
});
</script>

<div class="modal-mask" id="catModalMask" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="catModalTitle">
    <h3 id="catModalTitle">Nuevo registro</h3>
    <div id="catModalBody" class="grid" style="grid-template-columns: repeat(12, 1fr); gap:10px;">
      <div class="col-12 modal-block" id="blkMarca">
        <label>Nombre de la marca</label>
        <input id="inpMarcaNombre" placeholder="Ej. Toyota">
      </div>
      <div class="col-12 modal-block hidden" id="blkModelo">
        <div class="help">Se creara para la marca seleccionada.</div>
        <label>Nombre del modelo</label>
        <input id="inpModeloNombre" placeholder="Ej. Corolla">
      </div>
      <div class="col-6 modal-block hidden" id="blkTipo">
        <label>Codigo del tipo</label>
        <input id="inpTipoCodigo" placeholder="Ej. M1">
      </div>
      <div class="col-6 modal-block hidden" id="blkTipo2">
        <label>Nombre del tipo</label>
        <input id="inpTipoNombre" placeholder="Ej. Automovil">
      </div>
      <div class="col-12 hidden" id="helpTipo">
        <div class="help">Se creara para la categoria seleccionada.</div>
      </div>
      <div class="col-12 modal-block hidden" id="blkCarroceria">
        <div class="help">Se creara para el tipo seleccionado.</div>
        <label>Nombre de la carroceria</label>
        <input id="inpCarroceriaNombre" placeholder="Ej. Sedan">
      </div>
    </div>
    <div class="actions">
      <button type="button" class="btn sec" id="btnCatCancel">Cancelar</button>
      <button type="button" class="btn" id="btnCatSave">Guardar</button>
    </div>
  </div>
</div>

<script>
async function postForm(url, dataObj) {
  const body = new URLSearchParams(dataObj);
  const res = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
    body,
  });

  let js;
  try {
    js = await res.json();
  } catch {
    js = {ok: false, msg: 'Respuesta invalida'};
  }

  if (!res.ok || !js.ok) {
    throw new Error(js.msg || 'Error en la operacion');
  }

  return js;
}

const mask = document.getElementById('catModalMask');
const title = document.getElementById('catModalTitle');

const blkMarca = document.getElementById('blkMarca');
const blkModelo = document.getElementById('blkModelo');
const blkTipo = document.getElementById('blkTipo');
const blkTipo2 = document.getElementById('blkTipo2');
const blkCar = document.getElementById('blkCarroceria');
const helpTipo = document.getElementById('helpTipo');

const inpMarcaNombre = document.getElementById('inpMarcaNombre');
const inpModeloNombre = document.getElementById('inpModeloNombre');
const inpTipoCodigo = document.getElementById('inpTipoCodigo');
const inpTipoNombre = document.getElementById('inpTipoNombre');
const inpCarroceriaNombre = document.getElementById('inpCarroceriaNombre');

let currentKind = null;

function showBlocks(kind) {
  [blkMarca, blkModelo, blkTipo, blkTipo2, blkCar, helpTipo].forEach((el) => el.classList.add('hidden'));
  inpMarcaNombre.value = '';
  inpModeloNombre.value = '';
  inpTipoCodigo.value = '';
  inpTipoNombre.value = '';
  inpCarroceriaNombre.value = '';

  if (kind === 'marca') {
    blkMarca.classList.remove('hidden');
    title.textContent = 'Nueva marca';
  }
  if (kind === 'modelo') {
    blkModelo.classList.remove('hidden');
    title.textContent = 'Nuevo modelo';
  }
  if (kind === 'tipo') {
    blkTipo.classList.remove('hidden');
    blkTipo2.classList.remove('hidden');
    helpTipo.classList.remove('hidden');
    title.textContent = 'Nuevo tipo';
  }
  if (kind === 'carroceria') {
    blkCar.classList.remove('hidden');
    title.textContent = 'Nueva carroceria';
  }
}

function openModal(kind) {
  currentKind = kind;
  showBlocks(kind);
  mask.style.display = 'flex';
  mask.setAttribute('aria-hidden', 'false');

  setTimeout(() => {
    (kind === 'marca' && inpMarcaNombre.focus())
      || (kind === 'modelo' && inpModeloNombre.focus())
      || (kind === 'tipo' && inpTipoCodigo.focus())
      || (kind === 'carroceria' && inpCarroceriaNombre.focus());
  }, 0);
}

function closeModal() {
  mask.style.display = 'none';
  mask.setAttribute('aria-hidden', 'true');
}

document.getElementById('btnCatCancel').addEventListener('click', closeModal);
mask.addEventListener('click', (e) => {
  if (e.target === mask) closeModal();
});

document.getElementById('btnAddMarca').addEventListener('click', () => openModal('marca'));
document.getElementById('btnAddModelo').addEventListener('click', () => {
  if (!document.getElementById('marca_id').value) return alert('Primero selecciona una marca.');
  openModal('modelo');
});
document.getElementById('btnAddTipo').addEventListener('click', () => {
  if (!document.getElementById('categoria_id').value) return alert('Primero selecciona una categoria.');
  openModal('tipo');
});
document.getElementById('btnAddCarroceria').addEventListener('click', () => {
  if (!document.getElementById('tipo_id').value) return alert('Primero selecciona un tipo.');
  openModal('carroceria');
});

document.getElementById('btnCatSave').addEventListener('click', async () => {
  try {
    if (currentKind === 'marca') {
      const nombre = inpMarcaNombre.value.trim();
      if (!nombre) return alert('Ingresa el nombre de la marca.');
      const js = await postForm('add_catalogo.php', {kind: 'marca', nombre});
      const sel = document.getElementById('marca_id');
      sel.add(new Option(js.label, js.id, true, true));
      sel.dispatchEvent(new Event('change'));
      alert('Marca creada.');
      closeModal();
      return;
    }

    if (currentKind === 'modelo') {
      const mid = document.getElementById('marca_id').value;
      if (!mid) return alert('Selecciona una marca.');
      const nombre = inpModeloNombre.value.trim();
      if (!nombre) return alert('Ingresa el nombre del modelo.');
      const js = await postForm('add_catalogo.php', {kind: 'modelo', nombre, padre_id: mid});
      const sel = document.getElementById('modelo_id');
      if (sel.disabled) sel.disabled = false;
      sel.add(new Option(js.label, js.id, true, true));
      MODELOS.push({id: js.id, marca_id: mid, nombre: js.label});
      alert('Modelo creado.');
      closeModal();
      return;
    }

    if (currentKind === 'tipo') {
      const cid = document.getElementById('categoria_id').value;
      if (!cid) return alert('Selecciona una categoria.');
      const codigo = inpTipoCodigo.value.trim();
      if (!codigo) return alert('Ingresa el codigo del tipo.');
      const nombre = inpTipoNombre.value.trim();
      if (!nombre) return alert('Ingresa el nombre del tipo.');
      const js = await postForm('add_catalogo.php', {kind: 'tipo', codigo, nombre, padre_id: cid});
      const sel = document.getElementById('tipo_id');
      if (sel.disabled) sel.disabled = false;
      sel.add(new Option(`${codigo} - ${nombre}`, js.id, true, true));
      TIPOS.push({id: js.id, categoria_id: cid, codigo, nombre});
      alert('Tipo creado.');
      closeModal();
      refreshCarrocerias();
      return;
    }

    if (currentKind === 'carroceria') {
      const tid = document.getElementById('tipo_id').value;
      if (!tid) return alert('Selecciona un tipo.');
      const nombre = inpCarroceriaNombre.value.trim();
      if (!nombre) return alert('Ingresa el nombre de la carroceria.');
      const js = await postForm('add_catalogo.php', {kind: 'carroceria', nombre, padre_id: tid});
      const sel = document.getElementById('carroceria_id');
      if (sel.disabled) sel.disabled = false;
      sel.add(new Option(js.label, js.id, true, true));
      CARROCERIAS.push({id: js.id, tipo_id: tid, nombre});
      alert('Carroceria creada.');
      closeModal();
      return;
    }

    alert('Operacion no soportada.');
  } catch (e) {
    alert('Error: ' + e.message);
  }
});
</script>
</body>
</html>
