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
$entidadesAutocomplete = [];
$entidadDestinoTexto = '';
foreach ($ctx['entidades'] as $entidadItem) {
    $nombreEntidad = trim((string) ($entidadItem['nombre'] ?? ''));
    $siglasEntidad = trim((string) ($entidadItem['siglas'] ?? ''));
    $labelEntidad = $nombreEntidad . ($siglasEntidad !== '' ? ' (' . $siglasEntidad . ')' : '');
    $entidadesAutocomplete[] = [
        'id' => $entidadItem['id'] ?? '',
        'nombre' => $nombreEntidad,
        'siglas' => $siglasEntidad,
        'label' => $labelEntidad,
    ];
    if ($entidadDestinoTexto === '' && (string) ($data['entidad_id'] ?? '') === (string) ($entidadItem['id'] ?? '')) {
        $entidadDestinoTexto = $labelEntidad;
    }
}
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
body{background:var(--page);color:var(--text)}.wrap{max-width:1180px;margin:24px auto;padding:16px}.toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.btn.mini{width:40px;min-width:40px;min-height:48px;padding:0;font-size:20px;line-height:1}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c2{grid-column:span 2}.c3{grid-column:span 3}.c4{grid-column:span 4}.c5{grid-column:span 5}.c6{grid-column:span 6}.c8{grid-column:span 8}.c12{grid-column:span 12}label{display:block;font-weight:700;color:var(--muted);margin-bottom:6px}input,select,textarea{width:100%;box-sizing:border-box;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);line-height:1.3}select{min-height:48px;appearance:auto;-webkit-appearance:menulist;padding-right:38px}input{min-height:48px}textarea{min-height:130px;resize:vertical}.field-row{display:flex;gap:8px;align-items:stretch}.field-row > *:first-child{flex:1 1 auto}.combo-wrap{display:grid;gap:6px}.combo-hint{color:var(--muted);font-size:.88rem}.combo-menu{position:relative}.combo-suggestions{position:absolute;top:calc(100% + 4px);left:0;min-width:100%;width:max-content;max-width:min(760px,calc(100vw - 80px));max-height:240px;overflow:auto;border:1px solid var(--border);border-radius:12px;background:var(--card);box-shadow:0 14px 28px rgba(15,23,42,.14);display:none;z-index:40}.combo-suggestions.open{display:block}.combo-suggestion{padding:9px 12px;font-size:.84rem;line-height:1.25;cursor:pointer;white-space:normal;word-break:break-word}.combo-suggestion:hover,.combo-suggestion.active{background:rgba(29,78,216,.10)}.combo-empty{padding:9px 12px;font-size:.82rem;color:var(--muted)}.alert{padding:12px 14px;border-radius:12px;margin-bottom:12px}.alert.ok{background:rgba(22,163,74,.12);color:var(--ok)}.alert.err{background:rgba(220,38,38,.12);color:var(--danger)}.muted{color:var(--muted);font-size:.9rem}.preview{border:1px dashed var(--border);border-radius:12px;padding:12px;background:rgba(148,163,184,.06)}.preview h4{margin:.1rem 0 .5rem}.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:9999;padding:18px}.modal{width:min(980px,96vw);height:min(680px,90vh);background:var(--card);border-radius:16px;overflow:hidden;border:1px solid var(--border)}.modal header{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid var(--border)}.modal iframe{width:100%;height:calc(100% - 52px);border:0}@media (max-width:900px){.c2,.c3,.c4,.c5,.c6,.c8{grid-column:span 12}.combo-suggestions{max-width:calc(100vw - 48px)}}
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
          <div class="combo-wrap combo-menu">
            <input type="hidden" name="entidad_id" id="entidad_id" value="<?= h((string) $data['entidad_id']) ?>">
            <input type="text" id="entidad_id_text" value="<?= h($entidadDestinoTexto) ?>" placeholder="Escribe para buscar la entidad" autocomplete="off" required>
            <div id="entidad_id_options" class="combo-suggestions" role="listbox" aria-label="Sugerencias de entidad"></div>
            <div class="combo-hint">Escribe el nombre o las siglas y selecciona una entidad de la lista.</div>
          </div>
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

      <div class="c12" id="camaraRangoBox" style="display:none;">
        <div class="preview">
          <h4>Camara de video vigilancia</h4>
          <div class="field-row" style="margin-bottom:10px; flex-wrap:wrap;">
            <div style="flex:1 1 220px;">
              <label for="camara_rango_desde">Entre las</label>
              <input type="time" id="camara_rango_desde">
            </div>
            <div style="flex:1 1 220px;">
              <label for="camara_rango_hasta">Hasta las</label>
              <input type="time" id="camara_rango_hasta">
            </div>
          </div>
          <div class="muted">Al completar ambos campos se agregara al motivo una linea como: "Rango solicitado: entre las 08:00 hasta las 10:00".</div>
          <div class="muted" style="margin-top:6px;">Marcadores disponibles en la plantilla Word: <strong>${oficio_rango_camaras}</strong>, <strong>${oficio_rango_desde}</strong> y <strong>${oficio_rango_hasta}</strong>.</div>
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
const entidadTextInp = document.getElementById('entidad_id_text');
const subSel = document.getElementById('subentidad_id');
const personaSel = document.getElementById('persona_id');
const personaTextInp = document.getElementById('persona_id_text');
const personaManualInp = document.getElementById('persona_destino_manual');
const tipoSel = document.getElementById('tipo');
const asuntoSel = document.getElementById('asunto_id');
const motivoTxt = document.getElementById('motivo');
const camaraRangoBox = document.getElementById('camaraRangoBox');
const camaraRangoDesdeInp = document.getElementById('camara_rango_desde');
const camaraRangoHastaInp = document.getElementById('camara_rango_hasta');
const fechaInp = document.getElementById('fecha_emision');
const anioInp = document.getElementById('anio_oficio');
const numInp = document.getElementById('numero_oficio');
const linkListado = document.getElementById('linkListado');
const entidadOptionsBox = document.getElementById('entidad_id_options');
let lastModal = null;
let entidadItemsCache = <?= json_encode($entidadesAutocomplete, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let personaItemsCache = <?= json_encode($personasActuales, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let lastEntidadLoaded = String(entidadSel ? (entidadSel.value || '') : '');
let entidadSuggestions = [];

function normalizeText(value) {
  return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function stripCamaraRangeLine(text) {
  return String(text || '')
    .split(/\r?\n/)
    .filter((line) => !normalizeText(line).startsWith('rango solicitado:'))
    .join('\n')
    .trim();
}

function extractCamaraRange(text) {
  const match = String(text || '').match(/Rango solicitado:\s*entre las\s*([0-2]\d:\d{2})\s*hasta las\s*([0-2]\d:\d{2})/i);
  return {
    desde: match ? match[1] : '',
    hasta: match ? match[2] : ''
  };
}

function camaraRangeLine() {
  if (!camaraRangoDesdeInp || !camaraRangoHastaInp) return '';
  const desde = String(camaraRangoDesdeInp.value || '').trim();
  const hasta = String(camaraRangoHastaInp.value || '').trim();
  if (!desde || !hasta) return '';
  return 'Rango solicitado: entre las ' + desde + ' hasta las ' + hasta + '.';
}

function syncCamaraRangeIntoMotivo() {
  if (!motivoTxt) return;
  const base = stripCamaraRangeLine(motivoTxt.value);
  const line = camaraRangeLine();
  motivoTxt.value = line ? (base ? (base + '\n' + line) : line) : base;
}

function hydrateCamaraRangeFromMotivo() {
  if (!camaraRangoDesdeInp || !camaraRangoHastaInp || !motivoTxt) return;
  const parsed = extractCamaraRange(motivoTxt.value);
  camaraRangoDesdeInp.value = parsed.desde;
  camaraRangoHastaInp.value = parsed.hasta;
}

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

function fillDatalist(listId, items, labelKey = 'nombre') {
  const list = document.getElementById(listId);
  if (!list) return;
  list.innerHTML = '';
  items.forEach((item) => {
    const option = document.createElement('option');
    option.value = String(item[labelKey] || '').trim();
    option.dataset.id = String(item.id || '');
    list.appendChild(option);
  });
}

function closeEntidadSuggestions() {
  if (entidadOptionsBox) entidadOptionsBox.classList.remove('open');
}

function openEntidadSuggestions() {
  if (entidadOptionsBox && entidadOptionsBox.innerHTML.trim() !== '') entidadOptionsBox.classList.add('open');
}

function renderEntidadSuggestions(filterValue = '') {
  if (!entidadOptionsBox) return;
  const normalizedFilter = normalizeText(filterValue).trim();
  entidadSuggestions = entidadItemsCache.filter((item) => {
    if (normalizedFilter === '') return true;
    return normalizeText(item.label || '').includes(normalizedFilter)
      || normalizeText(item.nombre || '').includes(normalizedFilter)
      || normalizeText(item.siglas || '').includes(normalizedFilter);
  }).slice(0, 20);

  entidadOptionsBox.innerHTML = '';
  if (entidadSuggestions.length === 0) {
    const empty = document.createElement('div');
    empty.className = 'combo-empty';
    empty.textContent = 'No hay coincidencias.';
    entidadOptionsBox.appendChild(empty);
    openEntidadSuggestions();
    return;
  }

  entidadSuggestions.forEach((item) => {
    const row = document.createElement('div');
    row.className = 'combo-suggestion';
    row.textContent = String(item.label || '').trim();
    row.dataset.id = String(item.id || '');
    row.addEventListener('mousedown', (event) => {
      event.preventDefault();
      selectEntidadSuggestion(item);
    });
    entidadOptionsBox.appendChild(row);
  });
  openEntidadSuggestions();
}

async function selectEntidadSuggestion(item) {
  if (!item || !entidadSel || !entidadTextInp) return;
  entidadSel.value = String(item.id || '');
  entidadTextInp.value = String(item.label || '').trim();
  entidadTextInp.setCustomValidity('');
  closeEntidadSuggestions();
  await handleEntidadSelectionChange();
}

function setEntidadTextById(entidadId) {
  if (!entidadTextInp) return;
  const matched = entidadItemsCache.find((item) => String(item.id || '') === String(entidadId || ''));
  entidadTextInp.value = matched ? String(matched.label || '').trim() : '';
}

function clearEntidadDependents() {
  fillSelect(subSel, [], '', 'Ninguna');
  personaItemsCache = [];
  fillDatalist('persona_id_options', []);
  if (personaSel) personaSel.value = '';
  if (personaTextInp) personaTextInp.value = '';
  if (personaManualInp) personaManualInp.value = '';
  fillSelect(asuntoSel, [], '', 'Selecciona el asunto');
  const asuntoPreview = document.getElementById('asuntoPreview');
  const asuntoVarBox = document.getElementById('asuntoVarBox');
  const asuntoVarSelect = document.getElementById('asuntoVarSelect');
  if (asuntoPreview) asuntoPreview.style.display = 'none';
  if (asuntoVarBox) asuntoVarBox.style.display = 'none';
  if (asuntoVarSelect) asuntoVarSelect.innerHTML = '';
}

function syncEntidadDestino() {
  if (!entidadTextInp || !entidadSel) return { changed: false, matched: false, value: '' };
  entidadTextInp.setCustomValidity('');
  const typed = entidadTextInp.value.trim();
  const currentValue = String(entidadSel.value || '');

  if (typed === '') {
    entidadSel.value = '';
    return { changed: currentValue !== '', matched: false, value: '' };
  }

  const typedNormalized = normalizeText(typed);
  const matched = entidadItemsCache.find((item) => {
    const label = normalizeText(item.label || '');
    const nombre = normalizeText(item.nombre || '');
    const siglas = normalizeText(item.siglas || '');
    return typedNormalized === label || typedNormalized === nombre || (siglas !== '' && typedNormalized === siglas);
  });

  if (!matched) {
    entidadSel.value = '';
    return { changed: currentValue !== '', matched: false, value: '' };
  }

  entidadSel.value = String(matched.id || '');
  entidadTextInp.value = String(matched.label || '').trim();
  return { changed: currentValue !== entidadSel.value, matched: true, value: entidadSel.value };
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
    setEntidadTextById(asuntoEntidadId);
    lastEntidadLoaded = asuntoEntidadId;
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
function asuntoEsCamaraVideo() {
  const text = normalizeText(asuntoTexto());
  return text.includes('camara') && text.includes('video');
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
  if (camaraRangoBox) {
    const isCamara = asuntoEsCamaraVideo();
    camaraRangoBox.style.display = isCamara ? 'block' : 'none';
    if (isCamara) {
      hydrateCamaraRangeFromMotivo();
      syncCamaraRangeIntoMotivo();
    } else if (motivoTxt) {
      motivoTxt.value = stripCamaraRangeLine(motivoTxt.value);
    }
  }
}
async function recalcularNumero() {
  const year = parseInt(anioInp.value || '', 10);
  if (!year) return;
  const data = await fetchJSON('?ajax=nextnum&anio=' + encodeURIComponent(year));
  numInp.value = data.next;
}

async function handleEntidadSelectionChange() {
  const entidadId = entidadSel ? String(entidadSel.value || '') : '';
  if (entidadId === lastEntidadLoaded) return;
  lastEntidadLoaded = entidadId;
  if (!entidadId) {
    clearEntidadDependents();
    await toggleBoxesPorAsunto();
    return;
  }
  await loadSubentidades(entidadId);
  await loadPersonas(entidadId);
  await loadAsuntos(entidadId, tipoSel.value || 'SOLICITAR');
  await refreshAsuntoPreview();
  await toggleBoxesPorAsunto();
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
if (entidadTextInp) {
  const syncEntidadAndReload = async () => {
    syncEntidadDestino();
    renderEntidadSuggestions(entidadTextInp.value || '');
    await handleEntidadSelectionChange();
  };
  entidadTextInp.addEventListener('input', () => {
    syncEntidadAndReload().catch(console.error);
  });
  entidadTextInp.addEventListener('change', () => {
    syncEntidadAndReload().catch(console.error);
  });
  entidadTextInp.addEventListener('focus', () => {
    renderEntidadSuggestions(entidadTextInp.value || '');
  });
}
tipoSel.addEventListener('change', async () => {
  await loadAsuntos(entidadSel.value || '', tipoSel.value || 'SOLICITAR', asuntoSel.value || '');
  await refreshAsuntoPreview();
  await toggleBoxesPorAsunto();
});
if (personaTextInp) {
  personaTextInp.addEventListener('input', syncPersonaDestinoManual);
  personaTextInp.addEventListener('change', syncPersonaDestinoManual);
}
if (camaraRangoDesdeInp) {
  camaraRangoDesdeInp.addEventListener('input', syncCamaraRangeIntoMotivo);
  camaraRangoHastaInp.addEventListener('input', syncCamaraRangeIntoMotivo);
}
asuntoSel.addEventListener('change', async () => {
  await refreshAsuntoPreview();
  await toggleBoxesPorAsunto();
});
document.getElementById('frmOficio').addEventListener('submit', (event) => {
  syncEntidadDestino();
  if (!entidadSel.value) {
    if (entidadTextInp) {
      entidadTextInp.setCustomValidity('Selecciona una entidad de la lista.');
      entidadTextInp.reportValidity();
    }
    event.preventDefault();
    return;
  }
  syncPersonaDestinoManual();
  if (asuntoEsCamaraVideo()) syncCamaraRangeIntoMotivo();
  else if (motivoTxt) motivoTxt.value = stripCamaraRangeLine(motivoTxt.value);
});

document.addEventListener('DOMContentLoaded', async () => {
  syncListadoHref();
  syncEntidadDestino();
  syncPersonaDestinoManual();
  hydrateCamaraRangeFromMotivo();
  renderEntidadSuggestions(entidadTextInp ? entidadTextInp.value : '');
  closeEntidadSuggestions();
  await loadAsuntos(entidadSel.value || '', tipoSel.value || 'SOLICITAR', asuntoSel.value || '');
  if (!numInp.value) await recalcularNumero();
  await refreshAsuntoPreview().catch(() => {});
  await toggleBoxesPorAsunto();
});

document.addEventListener('click', (event) => {
  if (!entidadTextInp || !entidadOptionsBox) return;
  const combo = entidadTextInp.closest('.combo-menu');
  if (combo && combo.contains(event.target)) return;
  closeEntidadSuggestions();
});
</script>
</body>
</html>
