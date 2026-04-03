<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\OficioRepository;
use App\Services\OficioService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new OficioService(new OficioRepository($pdo));
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));

$accidenteIdGet = isset($_GET['accidente_id']) ? (int) $_GET['accidente_id'] : 0;
$sidpolGet = trim((string) ($_GET['sidpol'] ?? ''));
$preselectedAccidenteId = $accidenteIdGet > 0 ? $accidenteIdGet : ($sidpolGet !== '' ? ($service->accidenteIdBySidpol($sidpolGet) ?? 0) : 0);

if (isset($_GET['ajax'])) {
    $ajax = trim((string) $_GET['ajax']);
    header('Content-Type: application/json; charset=utf-8');

    try {
        switch ($ajax) {
            case 'subentidades':
                echo json_encode(['ok' => true, 'items' => $service->subentidades((int) ($_GET['entidad_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'personas':
                echo json_encode(['ok' => true, 'items' => $service->personas((int) ($_GET['entidad_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'asuntos':
                echo json_encode(['ok' => true, 'items' => $service->asuntosCatalogo((int) ($_GET['selected_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'asunto_info':
                echo json_encode(['ok' => true, 'item' => $service->asuntoInfo((int) ($_GET['id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'asunto_variantes':
                echo json_encode(['ok' => true, 'items' => $service->asuntoVariantes((int) ($_GET['id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'grado_cargo':
                echo json_encode(['ok' => true, 'items' => $service->gradoCargo()], JSON_UNESCAPED_UNICODE);
                break;
            case 'nextnum':
                $anio = (int) ($_GET['anio'] ?? 0);
                if ($anio <= 0) {
                    throw new InvalidArgumentException('Año inválido.');
                }
                echo json_encode(['ok' => true, 'next' => $service->nextNumero($anio)], JSON_UNESCAPED_UNICODE);
                break;
            case 'vehiculos_accidente':
                echo json_encode(['ok' => true, 'items' => $service->vehiculosAccidente((int) ($_GET['accidente_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'fallecidos_accidente':
                echo json_encode(['ok' => true, 'items' => $service->fallecidosAccidente((int) ($_GET['accidente_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            default:
                throw new InvalidArgumentException('ajax inválido.');
        }
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$ctx = $service->formContext($preselectedAccidenteId > 0 ? $preselectedAccidenteId : null);
$data = $service->defaultData(null, $preselectedAccidenteId > 0 ? $preselectedAccidenteId : null);
$error = '';
$success = '';
$asignado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'accidente_id' => $_POST['accidente_id'] ?? '',
        'anio_oficio' => $_POST['anio_oficio'] ?? '',
        'numero_oficio' => $_POST['numero_oficio'] ?? '',
        'fecha_emision' => $_POST['fecha_emision'] ?? '',
        'oficial_ano_id' => $_POST['oficial_ano_id'] ?? '',
        'entidad_id' => $_POST['entidad_id'] ?? '',
        'subentidad_id' => $_POST['subentidad_id'] ?? '',
        'grado_cargo_id' => $_POST['grado_cargo_id'] ?? '',
        'persona_id' => $_POST['persona_id'] ?? '',
        'persona_destino_manual' => $_POST['persona_destino_manual'] ?? '',
        'tipo' => $_POST['tipo'] ?? 'SOLICITAR',
        'asunto_id' => $_POST['asunto_id'] ?? '',
        'motivo' => $_POST['motivo'] ?? '',
        'referencia_texto' => $_POST['referencia_texto'] ?? '',
        'involucrado_vehiculo_id' => $_POST['involucrado_vehiculo_id'] ?? '',
        'involucrado_persona_id' => $_POST['involucrado_persona_id'] ?? '',
        'estado' => 'BORRADOR',
    ];

    try {
        $asignado = $service->create($data);
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>try{ window.parent.postMessage({type:"oficio.saved"}, "*"); }catch(_){ }</script><body style="font:13px Inter,sans-serif;padding:16px">Guardado...</body>';
            exit;
        }
        $success = 'Oficio registrado correctamente.';
        $data = $service->defaultData(null, (int) ($data['accidente_id'] ?: 0));
        $data['tipo'] = $_POST['tipo'] ?? 'SOLICITAR';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$entidadActual = (int) ($data['entidad_id'] ?: 0);
$tipoActual = (string) ($data['tipo'] ?: 'SOLICITAR');
$subentidadesActuales = $entidadActual > 0 ? $service->subentidades($entidadActual) : [];
$personasActuales = $entidadActual > 0 ? $service->personas($entidadActual) : [];
$asuntosActuales = $service->asuntosCatalogo((int) ($data['asunto_id'] ?? 0));
$vehiculosActuales = !empty($data['accidente_id']) ? $service->vehiculosAccidente((int) $data['accidente_id']) : [];
$fallecidosActuales = !empty($data['accidente_id']) ? $service->fallecidosAccidente((int) $data['accidente_id']) : [];
$listarHref = 'oficios_listar.php' . (!empty($data['accidente_id']) ? ('?accidente_id=' . urlencode((string) $data['accidente_id'])) : ($sidpolGet !== '' ? ('?sidpol=' . urlencode($sidpolGet)) : ''));
$personaDestinoTexto = trim((string) ($data['persona_destino_manual'] ?? ''));
if ($personaDestinoTexto === '' && !empty($data['persona_id'])) {
    foreach ($personasActuales as $personaItem) {
        if ((string) ($personaItem['id'] ?? '') === (string) $data['persona_id']) {
            $personaDestinoTexto = trim((string) ($personaItem['nombre'] ?? ''));
            break;
        }
    }
}

if (!$embed) {
    include __DIR__ . '/sidebar.php';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nuevo Oficio</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6;--danger:#fecaca;--ok:#bbf7d0}}
body{background:var(--page);color:var(--text)}.wrap{max-width:1180px;margin:24px auto;padding:16px}.toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.btn.mini{width:40px;min-width:40px;min-height:48px;padding:0;font-size:20px;line-height:1}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c2{grid-column:span 2}.c3{grid-column:span 3}.c4{grid-column:span 4}.c5{grid-column:span 5}.c6{grid-column:span 6}.c8{grid-column:span 8}.c12{grid-column:span 12}label{display:block;font-weight:700;color:var(--muted);margin-bottom:6px}input,select,textarea{width:100%;box-sizing:border-box;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);line-height:1.3}select{min-height:48px;appearance:auto;-webkit-appearance:menulist;padding-right:38px}input{min-height:48px}textarea{min-height:130px;resize:vertical}.field-row{display:flex;gap:8px;align-items:stretch}.field-row > *:first-child{flex:1 1 auto}.combo-wrap{display:grid;gap:6px}.combo-hint{color:var(--muted);font-size:.88rem}.alert{padding:12px 14px;border-radius:12px;margin-bottom:12px}.alert.ok{background:rgba(22,163,74,.12);color:var(--ok)}.alert.err{background:rgba(220,38,38,.12);color:var(--danger)}.muted{color:var(--muted);font-size:.9rem}.preview{border:1px dashed var(--border);border-radius:12px;padding:12px;background:rgba(148,163,184,.06)}.preview h4{margin:.1rem 0 .5rem}.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:9999;padding:18px}.modal{width:min(980px,96vw);height:min(680px,90vh);background:var(--card);border-radius:16px;overflow:hidden;border:1px solid var(--border)}.modal header{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid var(--border)}.modal iframe{width:100%;height:calc(100% - 52px);border:0}@media (max-width:900px){.c2,.c3,.c4,.c5,.c6,.c8{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <h1>Nuevo Oficio</h1>
  <div class="toolbar">
    <?php if ($embed): ?>
      <button class="btn" type="button" onclick="try{window.parent&&window.parent.postMessage({type:'oficio.close'},'*');}catch(e){}">Cerrar</button>
    <?php else: ?>
      <button class="btn" type="button" onclick="history.back()">← Atrás</button>
      <a class="btn" href="index.php">Ir al panel</a>
      <a class="btn primary" id="linkListado" href="<?= h($listarHref) ?>">Ver listado</a>
    <?php endif; ?>
  </div>

  <?php if ($error !== ''): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="alert ok"><?= h($success) ?><?php if ($asignado): ?> - ID: <?= (int) $asignado['id'] ?>, N° <?= (int) $asignado['numero'] ?>/<?= (int) $asignado['anio'] ?><?php endif; ?></div><?php endif; ?>

  <form method="post" class="card" id="frmOficio">
    <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <div class="grid">
      <div class="c12">
        <label>Accidente asociado*</label>
        <select name="accidente_id" id="accidente_id" required>
          <option value="">Selecciona el accidente</option>
          <?php foreach ($ctx['accidentes'] as $accidente): ?>
            <option value="<?= h($accidente['id']) ?>" <?= (string) $data['accidente_id'] === (string) $accidente['id'] ? 'selected' : '' ?>><?= h($accidente['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($sidpolGet !== ''): ?><div class="muted">Preseleccionado por SIDPOL: <?= h($sidpolGet) ?></div><?php endif; ?>
      </div>

      <div class="c2">
        <label>Año*</label>
        <input type="number" name="anio_oficio" id="anio_oficio" value="<?= h($data['anio_oficio']) ?>" required>
      </div>
      <div class="c3">
        <label>Número*</label>
        <div class="field-row">
          <input type="number" name="numero_oficio" id="numero_oficio" value="<?= h($data['numero_oficio']) ?>" placeholder="Correlativo">
          <button class="btn mini" type="button" onclick="recalcularNumero()">↻</button>
        </div>
      </div>
      <div class="c3">
        <label>Fecha de emisión*</label>
        <input type="date" name="fecha_emision" id="fecha_emision" value="<?= h($data['fecha_emision']) ?>" required>
      </div>
      <div class="c4">
        <label>Nombre oficial del año*</label>
        <div class="field-row">
          <select name="oficial_ano_id" id="oficial_ano_id" required>
            <option value="">Selecciona</option>
            <?php foreach ($ctx['oficial_anos'] as $ano): ?>
              <?php $label = $ano['anio'] . ' - ' . $ano['nombre'] . ((int) ($ano['vigente'] ?? 0) === 1 ? ' (Vigente)' : ''); ?>
              <option value="<?= h($ano['id']) ?>" <?= (string) ($data['oficial_ano_id'] ?: $ctx['oficial_ano_default']) === (string) $ano['id'] ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn mini" type="button" onclick="openCreate('ano')">+</button>
        </div>
      </div>

      <div class="c6">
        <label>Entidad destino*</label>
        <div class="field-row">
          <select name="entidad_id" id="entidad_id" required>
            <option value="">Selecciona</option>
            <?php foreach ($ctx['entidades'] as $entidad): ?>
              <?php $label = $entidad['nombre'] . ($entidad['siglas'] !== '' ? ' (' . $entidad['siglas'] . ')' : ''); ?>
              <option value="<?= h($entidad['id']) ?>" <?= (string) $data['entidad_id'] === (string) $entidad['id'] ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn mini" type="button" onclick="openCreate('entidad')">+</button>
        </div>
      </div>

      <div class="c6">
        <label>Subentidad</label>
        <div class="field-row">
          <select name="subentidad_id" id="subentidad_id">
            <option value="">Ninguna</option>
            <?php foreach ($subentidadesActuales as $item): ?>
              <option value="<?= h($item['id']) ?>" <?= (string) $data['subentidad_id'] === (string) $item['id'] ? 'selected' : '' ?>><?= h($item['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn mini" type="button" onclick="openCreate('subentidad')">+</button>
        </div>
      </div>

      <div class="c6">
        <label>Grado y cargo</label>
        <div class="field-row">
          <select name="grado_cargo_id" id="grado_cargo_id">
            <option value="">(Opcional)</option>
            <?php foreach ($ctx['grado_cargo'] as $cargo): ?>
              <?php $label = $cargo['nombre'] . ($cargo['abrev'] !== '' ? ' - ' . $cargo['abrev'] : '') . ' [' . $cargo['tipo'] . ']'; ?>
              <option value="<?= h($cargo['id']) ?>" <?= (string) $data['grado_cargo_id'] === (string) $cargo['id'] ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn mini" type="button" onclick="openCreate('cargo')">+</button>
        </div>
      </div>

      <div class="c6">
        <label>Persona destino</label>
        <div class="field-row">
          <div class="combo-wrap">
            <input type="hidden" name="persona_id" id="persona_id" value="<?= h((string) $data['persona_id']) ?>">
            <input type="hidden" name="persona_destino_manual" id="persona_destino_manual" value="<?= h((string) ($data['persona_destino_manual'] ?? '')) ?>">
            <input type="text" id="persona_id_text" list="persona_id_options" value="<?= h($personaDestinoTexto) ?>" placeholder="Selecciona o escribe manualmente">
            <datalist id="persona_id_options">
              <?php foreach ($personasActuales as $persona): ?>
                <option value="<?= h(trim((string) $persona['nombre'])) ?>" data-id="<?= h((string) $persona['id']) ?>"></option>
              <?php endforeach; ?>
            </datalist>
            <div class="combo-hint">Puedes elegir una persona registrada o escribirla manualmente. Si escribes aqui, solo se guardara en este oficio.</div>
          </div>
          <button class="btn mini" type="button" onclick="openCreate('persona')">+</button>
        </div>
      </div>

      <div class="c4">
        <label>Tipo de asunto</label>
        <select name="tipo" id="tipo">
          <?php foreach ($ctx['tipos'] as $tipo): ?>
            <option value="<?= h($tipo) ?>" <?= $data['tipo'] === $tipo ? 'selected' : '' ?>><?= h($tipo) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c8">
        <label>Asunto*</label>
        <div class="field-row">
          <select name="asunto_id" id="asunto_id" required>
            <option value="">Selecciona el asunto</option>
            <?php foreach ($asuntosActuales as $asunto): ?>
              <option value="<?= h($asunto['id']) ?>" <?= (string) $data['asunto_id'] === (string) $asunto['id'] ? 'selected' : '' ?>><?= h($asunto['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn mini" type="button" onclick="openCreate('asunto')">+</button>
        </div>
        <div class="muted">Este selector siempre muestra todos los asuntos guardados. Si existen varias plantillas con el mismo nombre, podrás elegir la variante.</div>
      </div>

      <div class="c12">
        <div id="asuntoPreview" class="preview" style="display:none;">
          <h4 id="asuntoNombre"></h4>
          <div class="field-row" id="asuntoVarBox" style="display:none; margin:0 0 .6rem 0;">
            <label style="margin:0;">Variante</label>
            <select id="asuntoVarSelect"></select>
          </div>
          <div id="asuntoDetalle"></div>
        </div>
      </div>

      <div class="c12">
        <label>Motivo / contexto*</label>
        <textarea name="motivo" id="motivo" required><?= h($data['motivo']) ?></textarea>
      </div>

      <div class="c12">
        <label>Referencia</label>
        <input type="text" name="referencia_texto" value="<?= h($data['referencia_texto']) ?>" placeholder="Ej.: Informe Técnico N° 162-2025-UIATN">
      </div>

      <div class="c6" id="vehiculoBox" style="display:none;">
        <label>Vehículo involucrado</label>
        <select name="involucrado_vehiculo_id" id="involucrado_vehiculo_id">
          <option value="">Selecciona</option>
          <?php foreach ($vehiculosActuales as $item): ?>
            <option value="<?= h($item['id']) ?>" <?= (string) $data['involucrado_vehiculo_id'] === (string) $item['id'] ? 'selected' : '' ?>><?= h($item['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c6" id="fallecidoBox" style="display:none;">
        <label>Persona fallecida</label>
        <select name="involucrado_persona_id" id="involucrado_persona_id">
          <option value="">Selecciona</option>
          <?php foreach ($fallecidosActuales as $item): ?>
            <option value="<?= h($item['id']) ?>" <?= (string) $data['involucrado_persona_id'] === (string) $item['id'] ? 'selected' : '' ?>><?= h($item['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c12" style="display:flex;justify-content:flex-end;gap:10px;">
        <?php if ($embed): ?>
          <button class="btn" type="button" onclick="try{window.parent&&window.parent.postMessage({type:'oficio.close'},'*');}catch(e){}">Cancelar</button>
        <?php else: ?>
          <a class="btn" href="<?= h($listarHref) ?>">Cancelar</a>
        <?php endif; ?>
        <button class="btn primary" type="submit">Guardar oficio</button>
      </div>
    </div>
  </form>
</div>

<div class="modal-backdrop" id="modalBackdrop">
  <div class="modal">
    <header>
      <h3 id="modalTitle">Nuevo registro</h3>
      <button class="btn" type="button" onclick="closeModal()">Cerrar</button>
    </header>
    <iframe id="modalFrame" src="about:blank"></iframe>
  </div>
</div>

<script>
const accSel = document.getElementById('accidente_id');
const entidadSel = document.getElementById('entidad_id');
const subSel = document.getElementById('subentidad_id');
const personaSel = document.getElementById('persona_id');
const personaTextInp = document.getElementById('persona_id_text');
const personaManualInp = document.getElementById('persona_destino_manual');
const tipoSel = document.getElementById('tipo');
const asuntoSel = document.getElementById('asunto_id');
const motivoTxt = document.getElementById('motivo');
const fechaInp = document.getElementById('fecha_emision');
const anioInp = document.getElementById('anio_oficio');
const numInp = document.getElementById('numero_oficio');
const linkListado = document.getElementById('linkListado');
let lastModal = null;
let personaItemsCache = <?= json_encode($personasActuales, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

async function fetchJSON(url) {
  const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
  const data = await response.json();
  if (!response.ok || data.ok === false) {
    throw new Error(data.msg || 'Error cargando datos.');
  }
  return data;
}

function fillSelect(select, items, selectedValue, placeholder, labelKey = 'nombre') {
  select.innerHTML = '';
  const base = new Option(placeholder, '');
  select.add(base);
  items.forEach((item) => {
    const option = new Option(item[labelKey] || '', item.id);
    if (String(selectedValue) === String(item.id)) option.selected = true;
    select.add(option);
  });
}

function fillDatalist(listId, items) {
  const list = document.getElementById(listId);
  if (!list) return;
  list.innerHTML = '';
  items.forEach((item) => {
    const option = document.createElement('option');
    option.value = String(item.nombre || '').trim();
    option.dataset.id = String(item.id || '');
    list.appendChild(option);
  });
}

function syncPersonaDestinoManual() {
  if (!personaTextInp || !personaSel || !personaManualInp) return;
  const typed = personaTextInp.value.trim();
  if (typed === '') {
    personaSel.value = '';
    personaManualInp.value = '';
    return;
  }

  const matched = personaItemsCache.find((item) => String(item.nombre || '').trim().toLowerCase() === typed.toLowerCase());
  if (matched) {
    personaSel.value = String(matched.id || '');
    personaManualInp.value = '';
    personaTextInp.value = String(matched.nombre || '').trim();
    return;
  }

  personaSel.value = '';
  personaManualInp.value = typed;
}

async function loadSubentidades(entidadId, selected = '') {
  if (!entidadId) {
    fillSelect(subSel, [], '', 'Ninguna');
    return;
  }
  const data = await fetchJSON('?ajax=subentidades&entidad_id=' + encodeURIComponent(entidadId));
  fillSelect(subSel, data.items || [], selected, 'Ninguna');
}

async function loadPersonas(entidadId, selected = '') {
  if (!entidadId) {
    personaItemsCache = [];
    fillDatalist('persona_id_options', []);
    if (personaSel) personaSel.value = '';
    return;
  }
  const data = await fetchJSON('?ajax=personas&entidad_id=' + encodeURIComponent(entidadId));
  personaItemsCache = data.items || [];
  fillDatalist('persona_id_options', personaItemsCache);
  if (selected) {
    const matched = personaItemsCache.find((item) => String(item.id) === String(selected));
    if (matched && personaTextInp) {
      personaTextInp.value = String(matched.nombre || '').trim();
    }
  }
  syncPersonaDestinoManual();
}

async function loadAsuntos(entidadId, tipo, selected = '') {
  const data = await fetchJSON('?ajax=asuntos&selected_id=' + encodeURIComponent(selected || ''));
  fillSelect(asuntoSel, data.items || [], selected, 'Selecciona el asunto');
}

async function loadGradoCargo(selected = '') {
  const select = document.getElementById('grado_cargo_id');
  const current = selected || select.value;
  const data = await fetchJSON('?ajax=grado_cargo');
  fillSelect(select, data.items || [], current, '(Opcional)');
}

async function refreshAsuntoPreview() {
  const box = document.getElementById('asuntoPreview');
  const n = document.getElementById('asuntoNombre');
  const detail = document.getElementById('asuntoDetalle');
  const varBox = document.getElementById('asuntoVarBox');
  const varSel = document.getElementById('asuntoVarSelect');
  if (!asuntoSel.value) {
    box.style.display = 'none';
    return;
  }
  const info = await fetchJSON('?ajax=asunto_info&id=' + encodeURIComponent(asuntoSel.value));
  if (!info.item) {
    box.style.display = 'none';
    return;
  }
  if (tipoSel && info.item.tipo && tipoSel.value !== info.item.tipo) {
    tipoSel.value = info.item.tipo;
  }
  const asuntoEntidadId = String(info.item.entidad_id || '');
  if (entidadSel && asuntoEntidadId !== '' && entidadSel.value !== asuntoEntidadId) {
    entidadSel.value = asuntoEntidadId;
    await loadSubentidades(asuntoEntidadId);
    await loadPersonas(asuntoEntidadId, personaSel ? personaSel.value : '');
  }
  n.textContent = info.item.nombre || '';
  detail.textContent = (info.item.detalle || '').trim() || '—';
  box.style.display = 'block';
  if (!motivoTxt.value.trim()) motivoTxt.value = info.item.detalle || '';

  const variantes = await fetchJSON('?ajax=asunto_variantes&id=' + encodeURIComponent(asuntoSel.value));
  if (variantes.items && variantes.items.length > 1) {
    varSel.innerHTML = '';
    variantes.items.forEach((item, index) => {
      const text = (item.detalle || '').trim();
      const label = 'Plantilla ' + (index + 1) + (text ? ' - ' + (text.length > 60 ? text.slice(0, 60) + '…' : text) : '');
      const option = new Option(label, item.id);
      if (String(item.id) === String(asuntoSel.value)) option.selected = true;
      varSel.add(option);
    });
    varBox.style.display = 'flex';
    varSel.onchange = async () => {
      const info2 = await fetchJSON('?ajax=asunto_info&id=' + encodeURIComponent(varSel.value));
      if (info2.item) {
        detail.textContent = (info2.item.detalle || '').trim() || '—';
        if (!motivoTxt.value.trim()) motivoTxt.value = info2.item.detalle || '';
      }
      asuntoSel.value = varSel.value;
      toggleBoxesPorAsunto();
    };
  } else {
    varBox.style.display = 'none';
    varSel.innerHTML = '';
  }
}

function asuntoTexto() {
  const option = asuntoSel.options[asuntoSel.selectedIndex];
  return option ? option.text.toLowerCase() : '';
}
function asuntoEsPeritaje() {
  return asuntoTexto().includes('peritaje de constatación de daños') || asuntoTexto().includes('peritaje de constatacion de danos');
}
function asuntoEsNecropsia() {
  const text = asuntoTexto();
  return text.includes('protocolo de necropsia') || text.includes('protocolo de autopsia') || text.includes('necropsia');
}
async function loadVehiculosAccidente(selected = '') {
  const sel = document.getElementById('involucrado_vehiculo_id');
  if (!accSel.value) { fillSelect(sel, [], '', 'Selecciona'); return; }
  const data = await fetchJSON('?ajax=vehiculos_accidente&accidente_id=' + encodeURIComponent(accSel.value));
  fillSelect(sel, data.items || [], selected, 'Selecciona');
}
async function loadFallecidosAccidente(selected = '') {
  const sel = document.getElementById('involucrado_persona_id');
  if (!accSel.value) { fillSelect(sel, [], '', 'Selecciona'); return; }
  const data = await fetchJSON('?ajax=fallecidos_accidente&accidente_id=' + encodeURIComponent(accSel.value));
  fillSelect(sel, data.items || [], selected, 'Selecciona');
}
async function toggleBoxesPorAsunto() {
  const vehBox = document.getElementById('vehiculoBox');
  const fallBox = document.getElementById('fallecidoBox');
  if (asuntoEsPeritaje()) {
    vehBox.style.display = 'block';
    await loadVehiculosAccidente(document.getElementById('involucrado_vehiculo_id').value);
  } else {
    vehBox.style.display = 'none';
    document.getElementById('involucrado_vehiculo_id').value = '';
  }
  if (asuntoEsNecropsia()) {
    fallBox.style.display = 'block';
    await loadFallecidosAccidente(document.getElementById('involucrado_persona_id').value);
  } else {
    fallBox.style.display = 'none';
    document.getElementById('involucrado_persona_id').value = '';
  }
}
async function recalcularNumero() {
  const year = parseInt(anioInp.value || '', 10);
  if (!year) return;
  const data = await fetchJSON('?ajax=nextnum&anio=' + encodeURIComponent(year));
  numInp.value = data.next;
}
function syncListadoHref() {
  if (!linkListado) return;
  const base = 'oficios_listar.php';
  if (accSel.value) linkListado.href = base + '?accidente_id=' + encodeURIComponent(accSel.value);
  else if (<?= json_encode($sidpolGet) ?>) linkListado.href = base + '?sidpol=' + encodeURIComponent(<?= json_encode($sidpolGet) ?>);
  else linkListado.href = base;
}
function openModal(title, url, kind) {
  lastModal = kind;
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalFrame').src = url;
  document.getElementById('modalBackdrop').style.display = 'flex';
}
function closeModal() {
  document.getElementById('modalBackdrop').style.display = 'none';
  document.getElementById('modalFrame').src = 'about:blank';
  const entidadId = entidadSel.value || '';
  const tipo = tipoSel.value || 'SOLICITAR';
  if (lastModal === 'subentidad' && entidadId) loadSubentidades(entidadId, subSel.value);
  else if (lastModal === 'persona' && entidadId) loadPersonas(entidadId, personaSel.value);
  else if (lastModal === 'asunto') loadAsuntos('', '', asuntoSel.value).then(refreshAsuntoPreview).then(toggleBoxesPorAsunto);
  else if (lastModal === 'cargo') loadGradoCargo(document.getElementById('grado_cargo_id').value);
  else if (lastModal === 'entidad' || lastModal === 'ano') location.reload();
  lastModal = null;
}
function openCreate(kind) {
  const entidadId = entidadSel.value || '';
  const tipo = tipoSel.value || 'SOLICITAR';
  if (kind === 'entidad') return openModal('Nueva entidad', 'oficio_entidad_nuevo.php', kind);
  if (kind === 'subentidad') { if (!entidadId) return alert('Selecciona primero una entidad.'); return openModal('Nueva subentidad', 'oficio_subentidad_nuevo.php?entidad_id=' + encodeURIComponent(entidadId), kind); }
  if (kind === 'persona') { if (!entidadId) return alert('Selecciona primero una entidad.'); return openModal('Nueva persona', 'oficio_persona_entidad_nuevo.php?entidad_id=' + encodeURIComponent(entidadId), kind); }
  if (kind === 'asunto') { if (!entidadId) return alert('Selecciona primero una entidad.'); return openModal('Nuevo asunto', 'oficio_asunto_nuevo.php?entidad_id=' + encodeURIComponent(entidadId) + '&tipo=' + encodeURIComponent(tipo), kind); }
  if (kind === 'ano') return openModal('Nuevo nombre oficial del año', 'oficio_oficial_ano_nuevo.php', kind);
  if (kind === 'cargo') return openModal('Nuevo grado/cargo', 'oficio_cargo_nuevo.php', kind);
}
window.closeModal = closeModal;
window.openCreate = openCreate;

fechaInp.addEventListener('change', () => {
  const year = (fechaInp.value || '').slice(0, 4);
  if (year) {
    anioInp.value = year;
    recalcularNumero().catch(console.error);
  }
});
accSel.addEventListener('change', () => { syncListadoHref(); toggleBoxesPorAsunto().catch(console.error); });
entidadSel.addEventListener('change', async () => {
  const entidadId = entidadSel.value || '';
  await loadSubentidades(entidadId);
  await loadPersonas(entidadId);
  await refreshAsuntoPreview();
  await toggleBoxesPorAsunto();
});
tipoSel.addEventListener('change', async () => {
  await refreshAsuntoPreview();
  await toggleBoxesPorAsunto();
});
if (personaTextInp) {
  personaTextInp.addEventListener('input', syncPersonaDestinoManual);
  personaTextInp.addEventListener('change', syncPersonaDestinoManual);
}
asuntoSel.addEventListener('change', async () => {
  await refreshAsuntoPreview();
  await toggleBoxesPorAsunto();
});
document.getElementById('frmOficio').addEventListener('submit', () => {
  syncPersonaDestinoManual();
});

document.addEventListener('DOMContentLoaded', async () => {
  syncListadoHref();
  syncPersonaDestinoManual();
  await loadAsuntos('', '', asuntoSel.value || '');
  if (!numInp.value) await recalcularNumero();
  await refreshAsuntoPreview().catch(() => {});
  await toggleBoxesPorAsunto();
});
</script>
</body>
</html>
