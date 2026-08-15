<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/app/Support/CaseSummaryWidget.php';

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
$returnLabel = str_contains($returnTo, 'diligenciapendiente_ver.php') ? 'Volver a la diligencia pendiente' : 'Volver a Documentos';

$accidenteIdGet = isset($_GET['accidente_id']) ? (int) $_GET['accidente_id'] : 0;
$sidpolGet = trim((string) ($_GET['sidpol'] ?? ''));
$preselectedAccidenteId = $accidenteIdGet > 0 ? $accidenteIdGet : ($sidpolGet !== '' ? ($service->accidenteIdBySidpol($sidpolGet) ?? 0) : 0);
if ($returnTo === '' && $preselectedAccidenteId > 0) {
    $returnTo = 'accidente_vista_tabs.php?accidente_id=' . $preselectedAccidenteId . '&tab=documentos';
}

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
                echo json_encode(['ok' => true, 'items' => $service->asuntos((int) ($_GET['entidad_id'] ?? 0), (string) ($_GET['tipo'] ?? 'SOLICITAR'))], JSON_UNESCAPED_UNICODE);
                break;
            case 'asunto_info':
                echo json_encode(['ok' => true, 'item' => $service->asuntoInfo((int) ($_GET['id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'asunto_variantes':
                echo json_encode(['ok' => true, 'items' => $service->asuntoVariantes((int) ($_GET['id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'plantilla_info':
                echo json_encode(['ok' => true, 'item' => $service->plantillaInfo((int) ($_GET['asunto_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
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
            case 'personas_informe_medico':
                echo json_encode(['ok' => true, 'items' => $service->personasInformeMedicoAccidente((int) ($_GET['accidente_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
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
        'diligencias_solicitadas' => $_POST['diligencias_solicitadas'] ?? '',
        'referencia_texto' => $_POST['referencia_texto'] ?? '',
        'involucrado_vehiculo_id' => $_POST['involucrado_vehiculo_id'] ?? '',
        'involucrado_persona_id' => $_POST['involucrado_persona_id'] ?? '',
        'estado' => 'BORRADOR',
    ];

    try {
        $asignado = $service->create($data);
        $downloadAfterSave = !$embed && (string) ($_POST['save_action'] ?? '') === 'download';
        if ($downloadAfterSave) {
            $downloadUrl = $service->downloadUrlForOficio((int) $asignado['id']);
            if ($downloadUrl !== '') {
                header('Location: ' . $downloadUrl);
                exit;
            }
            $success = 'Oficio guardado, pero este asunto no tiene una descarga Word configurada.';
        }
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>try{ window.parent.postMessage({type:"oficio.saved"}, "*"); }catch(_){ }</script><body style="font:13px Inter,sans-serif;padding:16px">Guardado...</body>';
            exit;
        }
        if ($downloadAfterSave) {
            $data = $service->defaultData($service->oficio((int) $asignado['id']), $preselectedAccidenteId > 0 ? $preselectedAccidenteId : null);
            $data['anio_oficio'] = $asignado['anio'] ?? $data['anio_oficio'];
            $data['numero_oficio'] = $asignado['numero'] ?? $data['numero_oficio'];
        } else {
            if ($returnTo !== '') {
                header('Location: ' . $returnTo);
            } else {
                $accidenteIdGuardado = (int) ($data['accidente_id'] ?? 0);
                header('Location: accidente_vista_tabs.php?' . http_build_query([
                    'accidente_id' => $accidenteIdGuardado,
                    'tab' => 'documentos',
                ]));
            }
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$entidadActual = (int) ($data['entidad_id'] ?: 0);
$tipoActual = (string) ($data['tipo'] ?: 'SOLICITAR');
$subentidadesActuales = $entidadActual > 0 ? $service->subentidades($entidadActual) : [];
$personasActuales = $entidadActual > 0 ? $service->personas($entidadActual) : [];
$asuntosActuales = $entidadActual > 0 ? $service->asuntos($entidadActual, $tipoActual) : [];
$vehiculosActuales = !empty($data['accidente_id']) ? $service->vehiculosAccidente((int) $data['accidente_id']) : [];
$fallecidosActuales = !empty($data['accidente_id']) ? $service->fallecidosAccidente((int) $data['accidente_id']) : [];
$plantillasAsunto = $service->asuntosCatalogo(!empty($data['asunto_id']) ? (int) $data['asunto_id'] : null);
$asuntoActualInfo = !empty($data['asunto_id']) ? $service->asuntoInfo((int) $data['asunto_id']) : null;
$asuntoActualMatch = mb_strtolower((string) (($asuntoActualInfo['nombre'] ?? '') . ' ' . ($asuntoActualInfo['detalle'] ?? '')), 'UTF-8');
$asuntoActualMatch = strtr($asuntoActualMatch, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
$showVehiculoInicial = (str_contains($asuntoActualMatch, 'peritaje') && str_contains($asuntoActualMatch, 'constat'))
    || str_contains($asuntoActualMatch, 'sunarp')
    || (str_contains($asuntoActualMatch, 'historial') && str_contains($asuntoActualMatch, 'transferenc'))
    || (str_contains($asuntoActualMatch, 'informacion') && str_contains($asuntoActualMatch, 'certificado'))
    || (str_contains($asuntoActualMatch, 'identificacion') && str_contains($asuntoActualMatch, 'vehiculo'));
$showFallecidoInicial = str_contains($asuntoActualMatch, 'necropsia')
    || str_contains($asuntoActualMatch, 'autopsia')
    || (str_contains($asuntoActualMatch, 'identificacion') && str_contains($asuntoActualMatch, 'cadaver'));
$showInformeMedicoInicial = str_contains($asuntoActualMatch, 'informe') && str_contains($asuntoActualMatch, 'medico');
$personasInformeMedicoActuales = !empty($data['accidente_id']) ? $service->personasInformeMedicoAccidente((int) $data['accidente_id']) : [];
$personasCasoActuales = $showInformeMedicoInicial ? $personasInformeMedicoActuales : $fallecidosActuales;
$listarHref = 'oficios_listar.php' . (!empty($data['accidente_id']) ? ('?accidente_id=' . urlencode((string) $data['accidente_id'])) : ($sidpolGet !== '' ? ('?sidpol=' . urlencode($sidpolGet)) : ''));
$entidadesAutocomplete = [];
$categoriasEntidad = [];
$entidadDestinoTexto = '';
foreach ($ctx['entidades'] as $entidadItem) {
    $nombreEntidad = trim((string) ($entidadItem['nombre'] ?? ''));
    $siglasEntidad = trim((string) ($entidadItem['siglas'] ?? ''));
    $categoriaEntidad = trim((string) ($entidadItem['categoria'] ?? ''));
    $labelEntidad = $nombreEntidad . ($siglasEntidad !== '' ? ' (' . $siglasEntidad . ')' : '');
    $entidadesAutocomplete[] = [
        'id' => $entidadItem['id'] ?? '',
        'nombre' => $nombreEntidad,
        'siglas' => $siglasEntidad,
        'categoria' => $categoriaEntidad,
        'label' => $labelEntidad,
    ];
    if ($categoriaEntidad !== '') {
        $categoriasEntidad[$categoriaEntidad] = $categoriaEntidad;
    }
    if ($entidadDestinoTexto === '' && (string) ($data['entidad_id'] ?? '') === (string) ($entidadItem['id'] ?? '')) {
        $entidadDestinoTexto = $labelEntidad;
    }
}
foreach (($ctx['entidad_categorias'] ?? []) as $categoriaItem) {
    $codigoCategoria = trim((string) ($categoriaItem['codigo'] ?? ''));
    if ($codigoCategoria !== '') {
        $categoriasEntidad[$codigoCategoria] = trim((string) ($categoriaItem['nombre'] ?? '')) ?: str_replace('_', ' ', $codigoCategoria);
    }
}
ksort($categoriasEntidad, SORT_NATURAL | SORT_FLAG_CASE);
$personaDestinoTexto = trim((string) ($data['persona_destino_manual'] ?? ''));
if ($personaDestinoTexto === '' && !empty($data['persona_id'])) {
    foreach ($personasActuales as $personaItem) {
        if ((string) ($personaItem['id'] ?? '') === (string) $data['persona_id']) {
            $personaDestinoTexto = trim((string) ($personaItem['nombre'] ?? ''));
            break;
        }
    }
}
$caseSummaryContext = case_summary_widget_context($pdo, (int) ($data['accidente_id'] ?? 0));

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
:root{color-scheme:light;--page:#f4f7fb;--card:#fff;--panel:#fbfdff;--field:#fff;--button:#fff;--preview:#f8fbff;--actions:rgba(255,255,255,.86);--text:#0f172a;--form-text:#0f172a;--form-muted:#4b6285;--muted:#64748b;--border:#d7deea;--section-title:#17315e;--primary:#1d4ed8;--primary-soft:#e8f0ff;--gold:#c88912;--danger:#b91c1c;--ok:#166534}
html[data-theme-resolved="dark"]{color-scheme:dark;--page:#0b1220;--card:#101a2c;--panel:#0d1728;--field:#111d31;--button:#16243b;--preview:#0d192b;--actions:rgba(15,25,43,.9);--text:#e5edf8;--form-text:#e5edf8;--form-muted:#9fb0c6;--muted:#94a3b8;--border:#30415f;--section-title:#dbeafe;--primary:#60a5fa;--primary-soft:#172554;--gold:#facc15;--danger:#fecaca;--ok:#bbf7d0}
body{margin:0;background:radial-gradient(circle at 20% 0,rgba(29,78,216,.08),transparent 28%),var(--page);color:var(--text)}
.wrap{max-width:1180px;margin:24px auto 34px;padding:16px}
body.is-embed .wrap{margin:0 auto;padding:14px}
.office-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:16px}
.office-title h1{margin:0;font-size:26px;letter-spacing:0;color:var(--text)}
.office-title p{margin:5px 0 0;color:var(--muted);font-size:13px;font-weight:650}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:9px 14px;border-radius:10px;border:1px solid var(--border);background:var(--button);color:var(--form-text);text-decoration:none;font-weight:800;cursor:pointer;box-shadow:0 8px 18px rgba(15,23,42,.06)}
.btn.primary{background:linear-gradient(180deg,#2f68ff 0%,#1d4ed8 100%);color:#fff;border-color:transparent;box-shadow:0 12px 22px rgba(29,78,216,.22)}
.btn.mini{width:40px;min-width:40px;min-height:46px;padding:0;font-size:20px;line-height:1}
.btn:hover{transform:translateY(-1px);border-color:#b9c7dc}
.card{position:relative;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:14px;color:var(--form-text);box-shadow:0 18px 42px rgba(15,23,42,.10);overflow:visible}
.card::before{content:"";position:absolute;inset:0 0 auto;height:4px;background:linear-gradient(90deg,#1d4ed8,#38bdf8,#d6a130)}
.office-section{--section-accent:#2563eb;--section-accent-rgb:37,99,235;position:relative;margin:12px 0;padding:20px 18px 18px;border:2px solid rgba(var(--section-accent-rgb),.64);border-radius:14px;background:linear-gradient(135deg,rgba(var(--section-accent-rgb),.10),transparent 24%),linear-gradient(180deg,var(--card),var(--panel));box-shadow:0 12px 26px rgba(15,23,42,.07),inset 5px 0 0 var(--section-accent);overflow:visible}
.office-section:focus-within{z-index:30}
.office-section::before{content:"";position:absolute;inset:0 0 auto;height:4px;background:linear-gradient(90deg,var(--section-accent),rgba(var(--section-accent-rgb),.18))}
.office-section:first-of-type{--section-accent:#2563eb;--section-accent-rgb:37,99,235}
.office-section:nth-of-type(2){--section-accent:#7c3aed;--section-accent-rgb:124,58,237}
.office-section:nth-of-type(3){--section-accent:#d97706;--section-accent-rgb:217,119,6}
.office-section:nth-of-type(4){--section-accent:#059669;--section-accent-rgb:5,150,105}
.section-head{display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:11px;border-bottom:1px solid rgba(var(--section-accent-rgb),.34)}
.accordion-section>.section-head{cursor:pointer;user-select:none}
.accordion-section>.section-head:focus-visible{outline:3px solid rgba(var(--section-accent-rgb),.28);outline-offset:5px;border-radius:8px}
.accordion-section.is-collapsed{padding-bottom:10px;box-shadow:0 8px 18px rgba(15,23,42,.05),inset 5px 0 0 var(--section-accent)}
.accordion-section.is-collapsed>.section-head{margin-bottom:0;padding-bottom:0;border-bottom-color:transparent}
.accordion-section>.office-accordion-body{display:grid}
.section-mark{width:8px;height:26px;border-radius:99px;background:var(--section-accent);box-shadow:0 0 16px rgba(var(--section-accent-rgb),.42)}
.section-head h2{margin:0;font-size:15px;color:var(--section-title)}
.section-head span{margin-left:auto;padding:4px 9px;border:1px solid rgba(var(--section-accent-rgb),.34);border-radius:999px;background:rgba(var(--section-accent-rgb),.10);color:var(--form-text);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.section-toggle{display:grid;place-items:center;width:30px;height:30px;margin-left:2px;border:1px solid rgba(var(--section-accent-rgb),.38);border-radius:50%;background:rgba(var(--section-accent-rgb),.12);color:var(--form-text);font-size:18px;font-weight:900;transition:transform .18s ease}
.accordion-section.is-collapsed .section-toggle{transform:rotate(-90deg)}
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}
.c2{grid-column:span 2}.c3{grid-column:span 3}.c4{grid-column:span 4}.c5{grid-column:span 5}.c6{grid-column:span 6}.c8{grid-column:span 8}.c12{grid-column:span 12}
.office-recipient-row{grid-column:span 12;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
label{display:block;font-weight:900;color:var(--gold);font-size:12px;margin-bottom:7px}
input,select,textarea{width:100%;box-sizing:border-box;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:var(--field);color:var(--form-text);line-height:1.3;box-shadow:0 1px 0 rgba(15,23,42,.03)}
input::placeholder,textarea::placeholder{color:var(--form-muted);opacity:.8}
select option{background:var(--field);color:var(--form-text)}
select{min-height:46px;appearance:auto;-webkit-appearance:menulist;padding-right:38px}
input{min-height:46px}
textarea{min-height:150px;resize:vertical}
input:focus,select:focus,textarea:focus{outline:0;border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.16),0 1px 0 rgba(15,23,42,.03)}
.field-row{display:flex;gap:8px;align-items:stretch}
.field-row > *:first-child{flex:1 1 auto}
.combo-wrap{display:grid;gap:6px}
.combo-hint,.muted{color:var(--form-muted);font-size:.88rem;line-height:1.35}
.combo-menu{position:relative}
.combo-suggestions{position:absolute;top:calc(100% + 4px);left:0;min-width:100%;width:max-content;max-width:min(760px,calc(100vw - 80px));max-height:240px;overflow:auto;border:1px solid var(--border);border-radius:12px;background:var(--card);box-shadow:0 14px 28px rgba(15,23,42,.28);display:none;z-index:100}
.combo-suggestions.open{display:block}
.combo-suggestion{padding:9px 12px;color:var(--form-text);font-size:.84rem;line-height:1.25;cursor:pointer;white-space:normal;word-break:break-word}
.combo-suggestion:hover,.combo-suggestion.active{background:rgba(29,78,216,.10)}
.combo-empty{padding:9px 12px;font-size:.82rem;color:var(--form-muted)}
.alert{padding:12px 14px;border-radius:12px;margin-bottom:12px}
.alert.ok{background:rgba(22,163,74,.12);color:var(--ok)}
.alert.err{background:rgba(220,38,38,.12);color:var(--danger)}
.preview{border:1px dashed var(--border);border-radius:12px;padding:12px;background:var(--preview);color:var(--form-text)}
.preview h4{margin:.1rem 0 .5rem}
.office-actions{position:sticky;bottom:0;display:flex;justify-content:flex-end;gap:10px;margin:12px -14px -14px;padding:14px 18px;background:var(--actions);border-top:1px solid var(--border);backdrop-filter:blur(10px)}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:9999;padding:18px}
.modal{width:min(980px,96vw);height:min(680px,90vh);background:var(--card);border-radius:16px;overflow:hidden;border:1px solid var(--border)}
.modal header{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid var(--border)}
.modal iframe{width:100%;height:calc(100% - 52px);border:0}
@media (max-width:900px){.wrap{padding:10px}.office-page-head{display:grid}.toolbar{justify-content:flex-start}.card{padding:9px}.office-section{margin:9px 0;padding:17px 13px 15px}.c2,.c3,.c4,.c5,.c6,.c8{grid-column:span 12}.office-recipient-row{grid-template-columns:1fr}.office-actions{position:static;flex-wrap:wrap;margin:9px -9px -9px}.combo-suggestions{max-width:calc(100vw - 48px)}}
</style>
</head>
<body class="<?= $embed ? 'is-embed' : '' ?>">
<div class="wrap">
  <div class="office-page-head">
    <div class="office-title">
      <h1>Nuevo Oficio</h1>
      <p>Registro de oficio vinculado al accidente y su destinatario.</p>
      <?= case_summary_widget_render($caseSummaryContext, 'oficio-nuevo') ?>
    </div>
    <div class="toolbar">
      <?php if ($embed): ?>
        <button class="btn" type="button" onclick="try{window.parent&&window.parent.postMessage({type:'oficio.close'},'*');}catch(e){}">Cerrar</button>
      <?php else: ?>
        <button class="btn" type="button" onclick="if(window.history.length>1){window.history.back();}else{window.location.href='accidente_vista_tabs.php?accidente_id=<?= (int) $preselectedAccidenteId ?>&tab=documentos';}">← Volver atrás</button>
        <a class="btn" href="index.php">Ir al panel</a>
        <a class="btn primary" id="linkListado" href="<?= h($listarHref) ?>">Ver listado</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($error !== ''): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="alert ok"><?= h($success) ?><?php if ($asignado): ?> - ID: <?= (int) $asignado['id'] ?>, N° <?= (int) $asignado['numero'] ?>/<?= (int) $asignado['anio'] ?><?php endif; ?><?php if (!$embed && $returnTo !== ''): ?> - <a class="btn" href="<?= h($returnTo) ?>"><?= h($returnLabel) ?></a><?php endif; ?></div><?php endif; ?>

  <form method="post" class="card" id="frmOficio">
    <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <section class="office-section accordion-section is-expanded" data-accordion-section>
      <div class="section-head" role="button" tabindex="0" aria-expanded="true"><i class="section-mark"></i><h2>Datos del oficio</h2><span>Numeracion</span><b class="section-toggle" aria-hidden="true">⌄</b></div>
    <div class="office-accordion-body grid">
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

      <div class="c12">
        <label for="plantilla_asunto_id">Plantilla / asunto base</label>
        <select id="plantilla_asunto_id">
          <option value="">Selecciona una plantilla para cargar la ultima configuracion usada</option>
          <?php foreach ($plantillasAsunto as $plantilla): ?>
            <option value="<?= h($plantilla['id']) ?>" <?= (string) $data['asunto_id'] === (string) $plantilla['id'] ? 'selected' : '' ?>><?= h($plantilla['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="combo-hint" id="plantillaHint">Al elegir una plantilla se rellenan entidad, cargo y persona con el ultimo uso del mismo asunto. Puedes cambiar cualquier campo antes de guardar.</div>
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
    </div>
    </section>

    <section class="office-section accordion-section is-collapsed" data-accordion-section>
      <div class="section-head" role="button" tabindex="0" aria-expanded="false"><i class="section-mark"></i><h2>Destinatario</h2><span>Entidad, cargo y persona</span><b class="section-toggle" aria-hidden="true">⌄</b></div>
      <div class="office-accordion-body grid">
      <input type="hidden" name="subentidad_id" value="">

      <div class="c4">
        <label for="entidad_categoria">Categoría de entidad</label>
        <select id="entidad_categoria">
          <option value="">Todas las categorías</option>
          <?php foreach ($categoriasEntidad as $codigoCategoria => $nombreCategoria): ?>
            <option value="<?= h($codigoCategoria) ?>"><?= h($nombreCategoria) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="combo-hint">Filtra las entidades disponibles antes de buscar el destinatario.</div>
      </div>

      <div class="c8">
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

      <div class="office-recipient-row">
        <div>
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

        <div>
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
      </div>

      </div>
    </section>

    <section class="office-section accordion-section is-collapsed" data-accordion-section>
      <div class="section-head" role="button" tabindex="0" aria-expanded="false"><i class="section-mark"></i><h2>Asunto y contenido</h2><span>Detalle</span><b class="section-toggle" aria-hidden="true">⌄</b></div>
      <div class="office-accordion-body grid">
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
        <div class="muted">Este selector muestra solo los asuntos registrados para la entidad destino seleccionada.</div>
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

      <div class="c12" id="diligenciasSolicitadasBox" style="display:none;">
        <div class="preview">
          <h4>Informacion de diligencias solicitada</h4>
          <div class="field-row" style="margin-bottom:10px; flex-wrap:wrap;">
            <div style="flex:1 1 260px;">
              <label for="tipo_diligencia_selector">Tipo de diligencia</label>
              <select id="tipo_diligencia_selector">
                <option value="">Selecciona una diligencia frecuente</option>
                <?php foreach (($ctx['tipos_diligencia'] ?? []) as $tipoDiligencia): ?>
                  <?php
                    $descripcionDiligencia = trim((string) ($tipoDiligencia['descripcion'] ?? ''));
                    $labelDiligencia = trim((string) ($tipoDiligencia['nombre'] ?? ''));
                    if ($descripcionDiligencia !== '') {
                        $labelDiligencia .= ' - ' . $descripcionDiligencia;
                    }
                  ?>
                  <option value="<?= h((string) ($tipoDiligencia['nombre'] ?? '')) ?>"><?= h($labelDiligencia) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn" type="button" id="btnAgregarDiligencia">Agregar</button>
          </div>
          <label for="diligencias_solicitadas">Diligencias*</label>
          <textarea name="diligencias_solicitadas" id="diligencias_solicitadas" placeholder="Escribe una diligencia por linea"><?= h($data['diligencias_solicitadas']) ?></textarea>
          <div class="muted">Puedes solicitar una o varias diligencias. Escribe cada una en una linea distinta.</div>
        </div>
      </div>

      <div class="c12">
        <label>Referencia</label>
        <input type="text" name="referencia_texto" id="referencia_texto" value="<?= h($data['referencia_texto']) ?>" placeholder="Ej.: Informe Técnico N° 162-2025-UIATN">
      </div>

      </div>
    </section>

    <section class="office-section" id="caseLinksSection" <?= ($showVehiculoInicial || $showFallecidoInicial || $showInformeMedicoInicial) ? '' : 'hidden' ?>>
      <div class="section-head"><i class="section-mark"></i><h2>Vinculos del caso</h2><span>Opcional</span></div>
      <div class="grid">
      <div class="c6" id="vehiculoBox" style="<?= $showVehiculoInicial ? 'display:block;' : 'display:none;' ?>">
        <label>Vehículo involucrado</label>
        <select name="involucrado_vehiculo_id" id="involucrado_vehiculo_id" <?= $showVehiculoInicial ? 'required' : '' ?>>
          <option value="">Selecciona</option>
          <?php foreach ($vehiculosActuales as $item): ?>
            <option value="<?= h($item['id']) ?>" <?= (string) $data['involucrado_vehiculo_id'] === (string) $item['id'] ? 'selected' : '' ?>><?= h($item['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c6" id="fallecidoBox" style="<?= ($showFallecidoInicial || $showInformeMedicoInicial) ? 'display:block;' : 'display:none;' ?>">
        <label id="personaInvolucradaLabel"><?= $showInformeMedicoInicial ? 'Persona herida, lesionada o fallecida' : 'Persona fallecida' ?></label>
        <select name="involucrado_persona_id" id="involucrado_persona_id" <?= ($showFallecidoInicial || $showInformeMedicoInicial) ? 'required' : '' ?>>
          <option value="">Selecciona</option>
          <?php foreach ($personasCasoActuales as $item): ?>
            <option value="<?= h($item['id']) ?>" <?= (string) $data['involucrado_persona_id'] === (string) $item['id'] ? 'selected' : '' ?>><?= h($item['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      </div>
    </section>

      <div class="office-actions">
        <?php if ($embed): ?>
          <button class="btn" type="button" onclick="try{window.parent&&window.parent.postMessage({type:'oficio.close'},'*');}catch(e){}">Cancelar</button>
        <?php else: ?>
          <a class="btn" href="<?= h($returnTo !== '' ? $returnTo : $listarHref) ?>"><?= h($returnLabel) ?></a>
        <?php endif; ?>
        <?php if (!$embed): ?><button class="btn" type="submit" name="save_action" value="download">Guardar y descargar</button><?php endif; ?>
        <button class="btn primary" type="submit">Guardar oficio</button>
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
const plantillaSel = document.getElementById('plantilla_asunto_id');
const plantillaHint = document.getElementById('plantillaHint');
const entidadSel = document.getElementById('entidad_id');
const entidadTextInp = document.getElementById('entidad_id_text');
const entidadCategoriaSel = document.getElementById('entidad_categoria');
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
const referenciaInp = document.getElementById('referencia_texto');
const tipoDiligenciaSelector = document.getElementById('tipo_diligencia_selector');
const btnAgregarDiligencia = document.getElementById('btnAgregarDiligencia');
const linkListado = document.getElementById('linkListado');
const entidadOptionsBox = document.getElementById('entidad_id_options');
const accordionSections = Array.from(document.querySelectorAll('[data-accordion-section]'));
let lastModal = null;
let entidadItemsCache = <?= json_encode($entidadesAutocomplete, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let personaItemsCache = <?= json_encode($personasActuales, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let lastEntidadLoaded = String(entidadSel ? (entidadSel.value || '') : '');
let entidadSuggestions = [];
let handlingInvalidField = false;
let applyingPlantilla = false;

function openAccordionSection(section) {
  if (!section || !accordionSections.includes(section)) return;
  accordionSections.forEach((item) => {
    const expanded = item === section;
    item.classList.toggle('is-collapsed', !expanded);
    item.classList.toggle('is-expanded', expanded);
    const body = item.querySelector('.office-accordion-body');
    if (body) body.style.setProperty('display', expanded ? 'grid' : 'none', 'important');
    const head = item.querySelector(':scope > .section-head');
    if (head) head.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  });
}

accordionSections.forEach((section) => {
  const head = section.querySelector(':scope > .section-head');
  if (!head) return;
  head.addEventListener('click', () => openAccordionSection(section));
  head.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    openAccordionSection(section);
  });
});
openAccordionSection(accordionSections[0]);

document.getElementById('frmOficio').addEventListener('invalid', (event) => {
  if (handlingInvalidField) return;
  const section = event.target.closest('[data-accordion-section]');
  if (!section) return;
  handlingInvalidField = true;
  openAccordionSection(section);
  setTimeout(() => { handlingInvalidField = false; }, 0);
}, true);

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

function addDiligenciaSolicitada(value) {
  const input = document.getElementById('diligencias_solicitadas');
  const text = String(value || '').trim();
  if (!input || text === '') return;
  const lines = String(input.value || '')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line !== '');
  if (!lines.some((line) => normalizeText(line) === normalizeText(text))) {
    lines.push(text);
  }
  input.value = lines.join('\n');
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
  if (!select || String(select.tagName || '').toUpperCase() !== 'SELECT') return;
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
  const categoria = entidadCategoriaSel ? String(entidadCategoriaSel.value || '') : '';
  entidadSuggestions = entidadItemsCache.filter((item) => {
    if (categoria !== '' && String(item.categoria || '') !== categoria) return false;
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

async function applyPlantillaPreset(asuntoId) {
  if (!asuntoId || applyingPlantilla) return;
  applyingPlantilla = true;
  try {
    const data = await fetchJSON('?ajax=plantilla_info&asunto_id=' + encodeURIComponent(asuntoId));
    const item = data.item || {};
    if (!item.entidad_id) return;

    if (plantillaHint) {
      const downloadLabel = item.download && item.download.label ? ' Descarga habilitada: ' + item.download.label + '.' : ' Este asunto aun no tiene descarga Word configurada.';
      plantillaHint.textContent = (item.source === 'latest' ? 'Se cargo la ultima configuracion usada para este asunto.' : 'Se cargo la configuracion base del catalogo.') + downloadLabel;
    }

    if (tipoSel && item.tipo) tipoSel.value = item.tipo;
    if (entidadSel) entidadSel.value = String(item.entidad_id || '');
    setEntidadTextById(item.entidad_id || '');
    lastEntidadLoaded = '';
    await loadSubentidades(item.entidad_id || '', item.subentidad_id || '');
    await loadPersonas(item.entidad_id || '', item.persona_id || '');
    await loadAsuntos(item.entidad_id || '', item.tipo || 'SOLICITAR', item.asunto_id || asuntoId);
    if (asuntoSel) asuntoSel.value = String(item.asunto_id || asuntoId);
    lastEntidadLoaded = String(item.entidad_id || '');

    const cargoSel = document.getElementById('grado_cargo_id');
    if (cargoSel) cargoSel.value = item.grado_cargo_id ? String(item.grado_cargo_id) : '';
    if (personaSel) personaSel.value = item.persona_id ? String(item.persona_id) : '';
    if (personaTextInp) {
      if (item.persona_id) {
        const matched = personaItemsCache.find((persona) => String(persona.id || '') === String(item.persona_id));
        personaTextInp.value = matched ? String(matched.nombre || '').trim() : '';
      } else {
        personaTextInp.value = String(item.persona_destino_manual || '');
      }
    }
    if (personaManualInp) personaManualInp.value = item.persona_id ? '' : String(item.persona_destino_manual || '');
    if (motivoTxt) motivoTxt.value = String(item.motivo || '');
    if (referenciaInp) referenciaInp.value = String(item.referencia_texto || '');
    const diligenciasInput = document.getElementById('diligencias_solicitadas');
    if (diligenciasInput) diligenciasInput.value = String(item.diligencias_solicitadas || '');

    syncPersonaDestinoManual();
    await refreshAsuntoPreview();
    await toggleBoxesPorAsunto();
    openAccordionSection(accordionSections[1] || accordionSections[0]);
  } catch (error) {
    if (plantillaHint) plantillaHint.textContent = error.message || 'No se pudo cargar la configuracion de la plantilla.';
    console.error(error);
  } finally {
    applyingPlantilla = false;
  }
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
  const categoria = entidadCategoriaSel ? String(entidadCategoriaSel.value || '') : '';
  const matched = entidadItemsCache.find((item) => {
    if (categoria !== '' && String(item.categoria || '') !== categoria) return false;
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
  if (!entidadId) {
    fillSelect(asuntoSel, [], '', 'Selecciona el asunto');
    return;
  }
  const data = await fetchJSON('?ajax=asuntos&entidad_id=' + encodeURIComponent(entidadId) + '&tipo=' + encodeURIComponent(tipo || 'SOLICITAR'));
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
  const normalized = normalizeText(text);
  return text.includes('protocolo de necropsia')
    || text.includes('protocolo de autopsia')
    || text.includes('necropsia')
    || (normalized.includes('identificacion') && normalized.includes('cadaver'));
}
function asuntoEsCamaraVideo() {
  const text = normalizeText(asuntoTexto());
  return text.includes('camara') && text.includes('video');
}
function asuntoEsSunarpHistorial() {
  const text = normalizeText(asuntoTexto());
  return text.includes('historial') && text.includes('transferenc');
}
function asuntoEsInformacionCertificado() {
  const text = normalizeText(asuntoTexto());
  return text.includes('informacion') && text.includes('certificado');
}
function asuntoEsIdentificacionVehiculo() {
  const text = normalizeText(asuntoTexto());
  return text.includes('identificacion') && text.includes('vehiculo');
}
function asuntoEsInformacionDiligencias() {
  const text = normalizeText(asuntoTexto());
  return text.includes('informacion') && text.includes('diligenc');
}
function asuntoEsInformeMedico() {
  const text = normalizeText(asuntoTexto());
  return text.includes('informe') && text.includes('medico');
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
async function loadPersonasInformeMedico(selected = '') {
  const sel = document.getElementById('involucrado_persona_id');
  if (!accSel.value) { fillSelect(sel, [], '', 'Selecciona'); return; }
  const data = await fetchJSON('?ajax=personas_informe_medico&accidente_id=' + encodeURIComponent(accSel.value));
  fillSelect(sel, data.items || [], selected, 'Selecciona');
}
async function toggleBoxesPorAsunto() {
  const vehBox = document.getElementById('vehiculoBox');
  const fallBox = document.getElementById('fallecidoBox');
  const caseLinksSection = document.getElementById('caseLinksSection');
  const diligenciasBox = document.getElementById('diligenciasSolicitadasBox');
  const diligenciasInput = document.getElementById('diligencias_solicitadas');
  const requiresVehicle = asuntoEsPeritaje() || asuntoEsSunarpHistorial() || asuntoEsInformacionCertificado() || asuntoEsIdentificacionVehiculo();
  const vehSel = document.getElementById('involucrado_vehiculo_id');
  if (requiresVehicle) {
    vehBox.style.display = 'block';
    if (vehSel) vehSel.required = true;
    await loadVehiculosAccidente(vehSel ? vehSel.value : '');
  } else {
    vehBox.style.display = 'none';
    if (vehSel) {
      vehSel.required = false;
      vehSel.value = '';
    }
  }
  const personaSel = document.getElementById('involucrado_persona_id');
  const personaLabel = document.getElementById('personaInvolucradaLabel');
  if (asuntoEsInformeMedico()) {
    fallBox.style.display = 'block';
    personaSel.required = true;
    if (personaLabel) personaLabel.textContent = 'Persona herida, lesionada o fallecida';
    await loadPersonasInformeMedico(personaSel.value);
  } else if (asuntoEsNecropsia()) {
    fallBox.style.display = 'block';
    personaSel.required = true;
    if (personaLabel) personaLabel.textContent = 'Persona fallecida';
    await loadFallecidosAccidente(personaSel.value);
  } else {
    fallBox.style.display = 'none';
    personaSel.required = false;
    personaSel.value = '';
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
  if (diligenciasBox && diligenciasInput) {
    const active = asuntoEsInformacionDiligencias();
    diligenciasBox.style.display = active ? 'block' : 'none';
    diligenciasInput.required = active;
  }
  if (caseLinksSection) {
    caseLinksSection.hidden = vehBox.style.display === 'none' && fallBox.style.display === 'none';
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
  else if (lastModal === 'asunto' && entidadId) loadAsuntos(entidadId, tipo, asuntoSel.value).then(refreshAsuntoPreview).then(toggleBoxesPorAsunto);
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
if (entidadCategoriaSel) {
  entidadCategoriaSel.addEventListener('change', async () => {
    const categoria = String(entidadCategoriaSel.value || '');
    const entidadActual = entidadItemsCache.find((item) => String(item.id || '') === String(entidadSel.value || ''));
    if (entidadActual && categoria !== '' && String(entidadActual.categoria || '') !== categoria) {
      entidadSel.value = '';
      entidadTextInp.value = '';
      lastEntidadLoaded = '';
      clearEntidadDependents();
    }
    renderEntidadSuggestions(entidadTextInp ? entidadTextInp.value : '');
    if (entidadTextInp) entidadTextInp.focus();
  });
}
tipoSel.addEventListener('change', async () => {
  await loadAsuntos(entidadSel.value || '', tipoSel.value || 'SOLICITAR', asuntoSel.value || '');
  await refreshAsuntoPreview();
  await toggleBoxesPorAsunto();
});
if (plantillaSel) {
  plantillaSel.addEventListener('change', () => {
    applyPlantillaPreset(plantillaSel.value).catch(console.error);
  });
}
if (btnAgregarDiligencia && tipoDiligenciaSelector) {
  btnAgregarDiligencia.addEventListener('click', () => {
    addDiligenciaSolicitada(tipoDiligenciaSelector.value);
  });
}
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
    openAccordionSection(entidadTextInp ? entidadTextInp.closest('[data-accordion-section]') : null);
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
  openAccordionSection(accordionSections[0]);
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
