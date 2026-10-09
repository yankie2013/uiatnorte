<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\OficioRepository;
use App\Services\OficioService;
use App\Support\OficioContenido;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new OficioService(new OficioRepository($pdo));
$pendingDownloadUrl = '';
$downloadToken = (string)($_GET['download_saved'] ?? '');
if ($downloadToken !== '' && isset($_SESSION['oficio_pending_downloads'][$downloadToken])) {
    $pendingDownloadUrl = (string)$_SESSION['oficio_pending_downloads'][$downloadToken];
    unset($_SESSION['oficio_pending_downloads'][$downloadToken]);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === 'estado') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $service->changeEstado((int) ($_POST['id'] ?? 0), (string) ($_POST['estado'] ?? ''));
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$anio = trim((string) ($_GET['anio'] ?? date('Y')));
if ($anio !== 'todos' && !preg_match('/^\d{4}$/D', $anio)) $anio = date('Y');
$entidadId = trim((string) ($_GET['entidad_id'] ?? ''));
$encargadoId = max(0, (int) ($_GET['encargado_id'] ?? 0));
$sidpol = '';
$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
$estado = '';
$categoria = trim((string) ($_GET['categoria'] ?? ''));
$tipo = strtoupper(trim((string) ($_GET['tipo'] ?? '')));
if (!in_array($tipo, ['SOLICITAR', 'REMITIR'], true)) {
    $tipo = '';
}
$msg = trim((string) ($_GET['msg'] ?? ''));

$filters = [
    'q' => $q,
    'anio' => $anio === 'todos' ? '' : $anio,
    'entidad_id' => $entidadId,
    'encargado_id' => $encargadoId,
    'sidpol' => $sidpol,
    'accidente_id' => $accidenteId,
    'estado' => $estado,
    'categoria' => $categoria,
    'tipo' => $tipo,
];
$ctx = $service->listado($filters);
$rows = $ctx['rows'];
$encargados = $service->gestionContext()['encargados'];
$aniosDisponibles = array_values(array_unique(array_merge([(int)date('Y')], $ctx['anios'])));
rsort($aniosDisponibles, SORT_NUMERIC);
$returnTo = $_SERVER['REQUEST_URI'] ?? build_url([]);

function build_url(array $overrides): string
{
    $query = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    $qs = http_build_query($query);
    return basename(__FILE__) . ($qs !== '' ? ('?' . $qs) : '');
}

function format_display_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    $dt = date_create($value);
    if ($dt === false) {
        return $value;
    }

    return $dt->format('d/m/Y');
}

function normalize_match_text(?string $value): string
{
    $text = mb_strtolower(trim((string) $value), 'UTF-8');
    $text = strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n',
    ]);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

function registrante_breve(array $row): string
{
    $nombre = trim((string) ($row['registrante_nombre'] ?? ''));
    if ($nombre === '') return 'Sin registro';
    $partes = preg_split('/\s+/u', $nombre) ?: [];
    $apellido = '';
    foreach ($partes as $parte) {
        if (mb_strlen($parte, 'UTF-8') > 1 && $parte === mb_strtoupper($parte, 'UTF-8') && preg_match('/\p{L}/u', $parte)) {
            $apellido = $parte;
            break;
        }
    }
    if ($apellido === '') $apellido = $partes[count($partes) >= 3 ? count($partes) - 2 : count($partes) - 1] ?? $nombre;
    return trim((string) ($row['registrante_grado'] ?? '') . ' ' . mb_strtoupper($apellido, 'UTF-8'));
}

$estadoStats = array_fill_keys($ctx['estados'], 0);
foreach ($rows as $row) {
    $estadoItem = strtoupper(trim((string) ($row['estado'] ?? '')));
    if (isset($estadoStats[$estadoItem])) {
        $estadoStats[$estadoItem]++;
    }
}

$entidadSeleccionada = '';
foreach ($ctx['entidades'] as $entidad) {
    if ((string) ($entidad['id'] ?? '') !== $entidadId) {
        continue;
    }

    $entidadSeleccionada = trim((string) ($entidad['nombre'] ?? ''));
    break;
}

$activeFilters = [];
if ($encargadoId > 0) {
    $activeFilters[] = ['label' => 'JEFE EMI encargado', 'value' => $encargadoId, 'clear' => 'encargado_id'];
}
if ($q !== '') {
    $activeFilters[] = ['label' => 'Texto', 'value' => $q, 'clear' => 'q'];
}
if ($anio !== 'todos') {
    $activeFilters[] = ['label' => 'A&ntilde;o', 'value' => $anio, 'clear' => 'anio'];
}
if ($entidadSeleccionada !== '') {
    $activeFilters[] = ['label' => 'Entidad', 'value' => $entidadSeleccionada, 'clear' => 'entidad_id'];
}
if ($sidpol !== '') {
    $activeFilters[] = ['label' => 'SIDPOL', 'value' => $sidpol, 'clear' => 'sidpol'];
}
if ($estado !== '') {
    $activeFilters[] = ['label' => 'Estado', 'value' => $estado, 'clear' => 'estado'];
}
if ($categoria !== '') {
    $activeFilters[] = ['label' => 'Categoría', 'value' => $categoria, 'clear' => 'categoria'];
}
if ($tipo !== '') {
    $activeFilters[] = ['label' => 'Tipo de asunto', 'value' => $tipo === 'REMITIR' ? 'Remite' : 'Solicita', 'clear' => 'tipo'];
}

$clearFiltersUrl = build_url([
    'q' => null,
    'anio' => null,
    'entidad_id' => null,
    'encargado_id' => null,
    'sidpol' => null,
    'estado' => null,
    'categoria' => null,
    'tipo' => null,
]);

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Oficios | Listado</title>
<link rel="stylesheet" href="style_mushu.css">
<link rel="stylesheet" href="assets/css/expediente_card.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/expediente_card.css') ?>">
<style>
:root{
  --page:#f4f7fb;
  --card:#ffffff;
  --card-soft:#f8fbff;
  --text:#10203a;
  --muted:#66758f;
  --border:#d7e0ee;
  --primary:#2456d6;
  --primary-soft:rgba(36,86,214,.12);
  --ok:#0f766e;
  --ok-soft:rgba(15,118,110,.12);
  --warn:#b45309;
  --warn-soft:rgba(180,83,9,.12);
  --danger:#b42318;
  --danger-soft:rgba(180,35,24,.12);
  --shadow:0 20px 48px rgba(15,23,42,.12);
}
@media (prefers-color-scheme: dark){
  :root{
    --page:#081120;
    --card:#0f1a2f;
    --card-soft:#13213c;
    --text:#e8eefc;
    --muted:#9db0cb;
    --border:#243554;
    --primary:#6d96ff;
    --primary-soft:rgba(109,150,255,.16);
    --ok:#5eead4;
    --ok-soft:rgba(94,234,212,.12);
    --warn:#fbbf24;
    --warn-soft:rgba(251,191,36,.12);
    --danger:#fda4af;
    --danger-soft:rgba(253,164,175,.12);
    --shadow:0 20px 48px rgba(2,6,23,.45);
  }
}
body{background:var(--page);color:var(--text);font-size:13px}
.wrap{max-width:1420px;margin:14px auto;padding:12px 16px 22px}
.page-head{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-start;margin-bottom:12px}
.page-copy h1{margin:0;font-size:1.55rem;line-height:1.1}
.page-copy p{margin:5px 0 0;color:var(--muted);font-size:.84rem}
.head-chips,.actions,.filters-actions,.active-filters,.tools,.action-links{display:flex;gap:8px;flex-wrap:wrap}
.head-chips{margin-top:9px}
.pill{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;border:1px solid var(--border);background:var(--card-soft);color:var(--muted);font-size:.76rem}
.pill strong{color:var(--text);font-weight:800}
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  min-height:35px;padding:7px 11px;border-radius:10px;border:1px solid var(--border);
  background:var(--card);color:var(--text);text-decoration:none;font-weight:800;cursor:pointer;
  transition:transform .14s ease, box-shadow .14s ease, border-color .14s ease, background .14s ease;
}
.btn:hover{transform:translateY(-1px);box-shadow:0 12px 24px rgba(15,23,42,.10)}
.btn.primary{background:linear-gradient(135deg,var(--primary),#3d7bff);border-color:transparent;color:#fff}
.btn.soft{background:var(--primary-soft);border-color:transparent;color:var(--primary)}
.btn.danger{color:var(--danger)}
.btn.sm{min-height:29px;padding:5px 9px;border-radius:8px;font-size:.76rem}
.panel{
  background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.88));
  border:1px solid var(--border);
  border-radius:18px;
  box-shadow:var(--shadow);
  overflow:hidden;
}
@media (prefers-color-scheme: dark){
  .panel{background:linear-gradient(180deg,rgba(15,26,47,.96),rgba(15,26,47,.90))}
}
.panel-head{padding:13px 14px 6px}
.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:8px}
.stat{padding:10px 12px;border:1px solid var(--border);border-radius:13px;background:var(--card-soft)}
.stat .label{display:block;color:var(--muted);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
.stat .value{display:block;margin-top:4px;font-size:1.28rem;font-weight:900;line-height:1}
.stat .meta{display:block;margin-top:4px;color:var(--muted);font-size:.72rem}
.stat.primary{background:var(--primary-soft);border-color:transparent}
.stat.primary .label,.stat.primary .value{color:var(--primary)}
.filter-box{margin-top:10px;padding:12px;border:1px solid var(--border);border-radius:15px;background:var(--card)}
.filter-title{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:9px}
.filter-title strong{font-size:.88rem}
.small{font-size:.78rem;color:var(--muted)}
.filters-grid{display:grid;grid-template-columns:2.2fr .7fr 1.2fr .9fr 1fr;gap:8px;align-items:end}
.field label{display:block;margin:0 0 4px;color:var(--muted);font-size:.72rem;font-weight:800}
.field input,.field select{
  width:100%;
  min-height:37px;
  padding:8px 10px;
  border-radius:10px;
  border:1px solid var(--border);
  background:var(--card-soft);
  color:var(--text);
}
.field input:focus,.field select:focus{
  border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(36,86,214,.12);
  outline:none;
}
.filters-actions{margin-top:9px}
.active-filters{margin-top:9px;align-items:center}
.filter-chip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:5px 9px;
  border-radius:999px;
  border:1px solid var(--border);
  background:var(--card-soft);
  color:var(--text);
  text-decoration:none;
  font-size:.74rem;
}
.filter-chip span{color:var(--muted)}
.table-area{padding:0 14px 14px}
.table-meta{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;padding:7px 0 9px}
.table-shell{border:1px solid var(--border);border-radius:14px;overflow:auto;background:var(--card)}
table{width:100%;border-collapse:separate;border-spacing:0;min-width:1320px;table-layout:fixed}
thead th:nth-child(1){width:9%}
thead th:nth-child(2){width:9%}
thead th:nth-child(3){width:9%}
thead th:nth-child(4){width:14%}
thead th:nth-child(5){width:27%}
thead th:nth-child(6){width:14%}
thead th:nth-child(7){width:10%}
thead th:nth-child(8){width:8%}
thead th{
  position:sticky;
  top:0;
  z-index:1;
  padding:10px 11px;
  text-align:left;
  color:var(--muted);
  font-size:.7rem;
  letter-spacing:.05em;
  text-transform:uppercase;
  background:var(--card-soft);
  border-bottom:1px solid var(--border);
}
tbody td{padding:11px;border-bottom:1px solid var(--border);vertical-align:top;font-size:.78rem;line-height:1.35}
tbody tr:last-child td{border-bottom:none}
tbody tr:nth-child(odd) td{background:#ffffff}
tbody tr:nth-child(even) td{background:#e8eee4}
tbody tr:hover td{background:#d9e7d4}
tbody td+td{border-left:1px solid #d6dfd1}
thead th{background:#e0ead9;color:#31583d;border-bottom:2px solid #7f9d72}
html[data-theme-resolved="dark"] tbody tr:nth-child(odd) td{background:#17251f}
html[data-theme-resolved="dark"] tbody tr:nth-child(even) td{background:#22352a}
html[data-theme-resolved="dark"] tbody tr:hover td{background:#304b39}
html[data-theme-resolved="dark"] thead th{background:#263c2d;color:#c7dfbc}
tbody tr[data-case-id]{cursor:pointer}
tbody tr[data-case-id]:focus-visible{outline:3px solid var(--primary);outline-offset:-3px}
tbody tr.row-updated td{background:rgba(34,197,94,.10)}
.sidpol-main{display:inline-flex;align-items:center;gap:6px;padding:4px 7px;border-radius:999px;background:var(--primary-soft);color:var(--primary);font-weight:900;font-size:.78rem}
.sidpol-sub,.muted{margin-top:4px;color:var(--muted);font-size:.72rem}
.numero-main{font-size:.88rem;font-weight:900}
.numero-main,.sidpol-main,td[data-label="Fecha"] .cell-title{white-space:nowrap}
.cell-title{font-weight:800;line-height:1.35}
.cell-subtitle{margin-top:3px;color:var(--muted);line-height:1.35}
.ref-text{line-height:1.4;color:var(--muted);display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden}
.tool{
  display:inline-flex;align-items:center;justify-content:center;
  padding:4px 7px;border-radius:999px;border:1px solid var(--border);
  background:var(--card-soft);color:var(--text);text-decoration:none;font-size:.7rem;font-weight:700;
}
.tool.tool-documento-recibido{border:2px solid #0284c7;background:#e0f2fe;color:#075985;box-shadow:0 0 0 2px rgba(14,165,233,.12),0 5px 12px rgba(2,132,199,.14)}.tool.tool-documento-recibido:hover{border-color:#0369a1;background:#bae6fd;color:#0c4a6e}
.tools{gap:5px}
.action-links{margin-top:6px;gap:5px;align-items:center;flex-wrap:nowrap}
.action-links form{margin:0}
.office-icon-actions{display:flex;align-items:center;gap:6px;white-space:nowrap}
.office-icon-button{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #b9cdb3;border-radius:8px;background:var(--card);color:#31583d;text-decoration:none;transition:background .15s,border-color .15s}
.office-icon-button:hover{background:#d9e7d4;border-color:#7f9d72}
.office-icon-button:focus-visible{outline:2px solid var(--primary);outline-offset:2px}
.office-icon-button svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
html[data-theme-resolved="dark"] .office-icon-button{color:#c7dfbc;border-color:#4d6b50}
.office-content{line-height:1.42;overflow-wrap:anywhere}
.state{
  width:100%;
  min-width:105px;
  min-height:33px;
  padding:6px 8px;
  border-radius:9px;
  border:1px solid var(--border);
  background:var(--card-soft);
  color:var(--text);
  font-weight:800;
  font-size:.72rem;
}
.state[data-state="BORRADOR"]{border-color:transparent;background:var(--warn-soft);color:var(--warn)}
.state[data-state="FIRMADO"]{border-color:transparent;background:var(--primary-soft);color:var(--primary)}
.state[data-state="ENVIADO"]{border-color:transparent;background:var(--ok-soft);color:var(--ok)}
.state[data-state="ANULADO"]{border-color:transparent;background:var(--danger-soft);color:var(--danger)}
.state[data-state="ARCHIVADO"]{border-color:transparent;background:rgba(100,116,139,.14);color:var(--muted)}
.state.is-saving{opacity:.7}
.ok{margin:0 20px 16px;padding:12px 14px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:14px}
.empty{padding:34px 18px;text-align:center;color:var(--muted)}
@media (max-width:1100px){
  .filters-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:760px){
  .wrap{padding:18px 14px 24px}
  .page-copy h1{font-size:1.7rem}
  .panel-head,.table-area{padding-left:14px;padding-right:14px}
  .filter-box{padding:14px}
  .filters-grid,.stats-grid{grid-template-columns:1fr}
  .table-shell{border-radius:18px}
  table,thead,tbody,tr,td,th{display:block;min-width:0}
  table{min-width:0}
  thead{display:none}
  tbody{display:grid;gap:10px;padding:8px}
  tbody tr{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card)}
  tbody td{display:block;min-width:0;padding:7px 10px;border-bottom:1px solid var(--border);font-size:13px;line-height:1.35;overflow-wrap:anywhere}
  tbody td:nth-child(n+5){grid-column:1/-1}
  tbody td:nth-child(2){text-align:right}
  tbody td:nth-child(3){border-right:1px solid var(--border)}
  tbody td:nth-child(6),tbody td:nth-child(7){display:flex;gap:8px;align-items:baseline}
  tbody td:nth-child(6)::before,tbody td:nth-child(7)::before{flex:0 0 85px;margin:0}
  tbody td:last-child::before{display:none}
  tbody td:last-child{padding:8px 10px}
  tbody td .office-icon-actions{gap:8px}
  .table-area{padding-left:8px;padding-right:8px}
  tbody td:last-child{border-bottom:none}
  tbody td::before{
    content:attr(data-label);
    display:block;
    margin-bottom:3px;
    color:var(--muted);
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
  }
  .ref-text{max-width:none}
  .ok{margin-left:14px;margin-right:14px}
}

.office-more-filters,.office-extra-filters{display:contents}
.office-more-filters>summary{display:none}
@media screen and (max-width:760px){
  .panel-head{padding:8px 8px 0}
  .filter-box{margin-top:0;padding:10px!important;border-radius:12px}
  .filter-title{margin-bottom:8px;gap:4px}
  .filter-description{display:none}
  #oficio-filters .filters-grid{gap:8px!important}
  #oficio-filters .field label{font-size:12px;margin-bottom:4px}
  .office-more-filters{display:block;border-top:1px solid var(--border)}
  .office-more-filters>summary{display:flex;align-items:center;justify-content:space-between;min-height:44px;font-size:13px;font-weight:800;cursor:pointer;list-style:none}
  .office-more-filters>summary::-webkit-details-marker{display:none}
  .office-more-filters>summary::after{content:'+';font-size:21px;color:var(--primary)}
  .office-more-filters[open]>summary::after{content:'−'}
  #oficio-filters .office-extra-filters{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
  .office-extra-filters>.field{min-width:0}
  .office-extra-filters>.field:nth-child(2),.office-extra-filters>.field:nth-child(3){grid-column:1/-1}
  #oficio-filters .filters-actions{margin-top:8px;gap:8px}
  #oficio-filters .filters-actions>.btn{min-height:44px;padding:8px 10px;font-size:13px}
}
</style>
</head>
<body>
<div class="wrap">
  <div class="page-head">
    <div class="page-copy">
      <h1>Oficios</h1>
      <div class="head-chips">
        <?php if ($accidenteId > 0): ?><div class="pill"><span>Accidente</span><strong>#<?= h($accidenteId) ?></strong></div><?php endif; ?>
        <?php if ($sidpol !== ''): ?><div class="pill"><span>SIDPOL</span><strong><?= h($sidpol) ?></strong></div><?php endif; ?>
        <?php if ($estado !== ''): ?><div class="pill"><span>Estado</span><strong><?= h($estado) ?></strong></div><?php endif; ?>
      </div>
    </div>
    <div class="actions">
      <?php if ($accidenteId > 0): ?><a class="btn" href="Dato_General_accidente.php?accidente_id=<?= urlencode((string) $accidenteId) ?>">Datos generales SIDPOL</a><?php endif; ?>
      <?php if ($accidenteId > 0): ?><a class="btn soft" href="oficio_protocolo_express.php?accidente_id=<?= urlencode((string) $accidenteId) ?>&return_to=<?= urlencode($returnTo) ?>">Necropsia r&aacute;pida</a><?php endif; ?>
      <?php if ($accidenteId > 0): ?><a class="btn soft" href="oficio_peritaje_express.php?accidente_id=<?= urlencode((string) $accidenteId) ?>&return_to=<?= urlencode($returnTo) ?>">Peritaje r&aacute;pido</a><?php endif; ?>
      <a class="btn primary" data-oficio-modal="Nuevo oficio" href="oficios_nuevo.php<?= $accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : ($sidpol !== '' ? ('?sidpol=' . urlencode($sidpol)) : '') ?>">+ Nuevo oficio</a>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <?php if ($msg === 'eliminado'): ?><div class="ok">Oficio eliminado correctamente.</div><?php endif; ?>

      <div class="filter-box">
        <div class="filter-title">
          <div>
            <strong>Filtros de b&uacute;squeda</strong>
            <div class="small filter-description">Puedes combinar texto, tipo de asunto, categoría, a&ntilde;o, entidad y JEFE EMI encargado.</div>
          </div>
          <?php if ($activeFilters): ?><div class="small"><?= count($activeFilters) ?> filtro(s) activo(s)</div><?php endif; ?>
        </div>

        <form method="get" id="oficio-filters">
          <?php if ($accidenteId > 0): ?><input type="hidden" name="accidente_id" value="<?= h($accidenteId) ?>"><?php endif; ?>
          <div class="filters-grid">
            <div class="field">
              <label for="q">B&uacute;squeda general</label>
              <input id="q" type="text" name="q" value="<?= h($q) ?>" placeholder="N&uacute;mero, asunto, referencia o placa">
            </div>
            <details class="office-more-filters">
              <summary>Más filtros</summary>
              <div class="office-extra-filters">
            <div class="field">
              <label for="anio">A&ntilde;o</label>
              <select id="anio" name="anio">
                <option value="todos" <?= $anio === 'todos' ? 'selected' : '' ?>>Todos los años</option>
                <?php foreach ($aniosDisponibles as $anioDisponible): ?>
                  <option value="<?= h($anioDisponible) ?>" <?= $anio === (string)$anioDisponible ? 'selected' : '' ?>><?= h($anioDisponible) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="entidad_id">Entidad</label>
              <select id="entidad_id" name="entidad_id">
                <option value="">Todas</option>
                <?php foreach ($ctx['entidades'] as $entidad): ?>
                  <option value="<?= h($entidad['id']) ?>" <?= $entidadId !== '' && (string) $entidad['id'] === $entidadId ? 'selected' : '' ?>><?= h($entidad['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label for="encargado_id">JEFE EMI encargado</label>
              <select id="encargado_id" name="encargado_id">
                <option value="">Todos</option>
                <?php foreach ($encargados as $encargado): ?>
                  <option value="<?= (int)$encargado['id'] ?>" <?= $encargadoId === (int)$encargado['id'] ? 'selected' : '' ?>><?= h(trim(($encargado['grado'] ?? '').' '.$encargado['nombre'])) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="categoria">Categoría</label>
              <select id="categoria" name="categoria">
                <option value="">Todas</option>
                <?php foreach ($ctx['categorias'] as $item): ?>
                  <option value="<?= h($item) ?>" <?= $categoria === $item ? 'selected' : '' ?>><?= h($item) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="tipo">Tipo de asunto</label>
              <select id="tipo" name="tipo">
                <option value="">Todos</option>
                <?php foreach ($ctx['tipos'] as $item): ?>
                  <option value="<?= h($item) ?>" <?= $tipo === $item ? 'selected' : '' ?>><?= h($item === 'REMITIR' ? 'Remite' : 'Solicita') ?></option>
                <?php endforeach; ?>
              </select>
            </div>

              </div>
            </details>
          </div>

          <div class="filters-actions">
            <button class="btn primary" type="submit">Filtrar</button>
            <a class="btn" href="<?= h($clearFiltersUrl) ?>">Limpiar filtros</a>
          </div>
        </form>


      </div>
    </div>

    <div class="table-area">
      <div class="table-meta">
        <div class="small">Mostrando <?= count($rows) ?> registro(s) en el listado actual.</div>
        <?php if ($accidenteId > 0 || $sidpol !== ''): ?>
          <div class="small">
            Contexto:
            <?php if ($accidenteId > 0): ?> accidente #<?= h($accidenteId) ?><?php endif; ?>
            <?php if ($accidenteId > 0 && $sidpol !== ''): ?> | <?php endif; ?>
            <?php if ($sidpol !== ''): ?> SIDPOL <?= h($sidpol) ?><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="table-shell">
        <table>
          <thead>
            <tr>
              <th>N&uacute;mero</th>
              <th>Fecha</th>
              <th>Tipo de asunto</th>
              <th>Categoría</th>
              <th>Contenido</th>
              <th>Entidad de destino</th>
              <th><?= $accidenteId > 0 ? 'Registrado por' : 'Encargado' ?></th>
              <th aria-label="Opciones del oficio"></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="8">
                  <div class="empty">
                    <strong>No hay resultados para los filtros aplicados.</strong>
                    <div class="small" style="margin-top:8px;">Prueba limpiando filtros o usando una b&uacute;squeda m&aacute;s amplia.</div>
                    <?php if (\App\Support\Access::admin()): ?><a class="office-icon-button" href="oficios_eliminar.php?id=<?= (int)$row['id'] ?>&return_to=<?= urlencode($returnTo) ?>" title="Eliminar oficio" aria-label="Eliminar oficio <?= h($row['numero']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg></a><?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endif; ?>

            <?php foreach ($rows as $row): ?>
              <?php
                $txt = normalize_match_text((string) (($row['asunto_nombre'] ?? '') . ' ' . ($row['detalle'] ?? '')));
                $isRemitir = strtoupper(trim((string) ($row['asunto_tipo'] ?? ''))) === 'REMITIR' || str_contains($txt, 'remitir diligencias') || str_contains($txt, 'remitir diligencia');
                $isDosaje = str_contains($txt, 'dosaje et') || str_contains($txt, 'resultado dosaje') || preg_match('/resultado.*dosaje/i', $txt);
                $isPeritaje = str_contains($txt, 'peritaje de constat');
                $isNecropsia = str_contains($txt, 'protocolo de necropsia') || str_contains($txt, 'necropsia') || str_contains($txt, 'autopsia');
                $isCamaraVideo = (str_contains($txt, 'camara') || str_contains($txt, 'camaras')) && (str_contains($txt, 'video') || str_contains($txt, 'vigilancia'));
                $isSunarpHistorial = str_contains($txt, 'sunarp') || (str_contains($txt, 'historial') && str_contains($txt, 'transferenc'));
                $isInformacionCertificadoUper = str_contains($txt, 'informacion') && str_contains($txt, 'certificado');
                $isInformacionDiligencias = str_contains($txt, 'informacion') && str_contains($txt, 'diligenc');
                $isInformeMedico = str_contains($txt, 'informe') && str_contains($txt, 'medico');
                $contenido = OficioContenido::componer($row);
              ?>
              <tr<?= (int)($row['accid'] ?? 0) > 0 ? ' data-case-id="' . (int)$row['accid'] . '" tabindex="0" aria-label="Ver expediente del oficio ' . h($row['numero']) . '"' : '' ?>>
                <td data-label="N&uacute;mero">
                  <div class="numero-main"><?= h(sprintf('%03d', (int)$row['numero'])) ?></div>
                </td>
                <td data-label="Fecha">
                  <div class="cell-title"><?= h(format_display_date((string) ($row['fecha_emision'] ?? ''))) ?></div>
                </td>
                <td data-label="Tipo de asunto"><?= h($row['asunto_tipo'] === 'REMITIR' ? 'Remite' : 'Solicita') ?></td>
                <td data-label="Categoría"><?= h($row['categoria'] ?: ($row['asunto_nombre'] ?: '-')) ?></td>
                <td data-label="Contenido"><div class="office-content"><?= h($contenido !== '' ? $contenido : ($row['asunto_nombre'] ?: '-')) ?></div></td>
                <td data-label="Entidad de destino"><?= h($row['entidad'] ?: ($row['persona_destino_manual'] ?: '-')) ?></td>
                <td data-label="<?= $accidenteId > 0 ? 'Registrado por' : 'Encargado' ?>"><?= h($accidenteId > 0 ? registrante_breve($row) : (($row['encargado_nombre'] ?? '') ?: 'Sin encargado')) ?></td>
                <td data-label="Acciones">
                  <?php $officeOrigin = $accidenteId > 0 ? 'expediente' : 'gestion'; $downloadUrl = $service->downloadUrlForOficio((int)$row['id'], $row); ?>
                  <div class="office-icon-actions">
                    <a class="office-icon-button" data-oficio-modal="Ver oficio" href="oficios_leer.php?origin=<?= $officeOrigin ?>&id=<?= (int)$row['id'] ?>" title="Ver oficio" aria-label="Ver oficio <?= h($row['numero']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></a>
                    <?php if ($downloadUrl !== ''): ?><a class="office-icon-button" href="<?= h($downloadUrl) ?>" title="Descargar oficio" aria-label="Descargar oficio <?= h($row['numero']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/></svg></a><?php endif; ?>
                    <?php if ($service->canEdit($row)): ?><a class="office-icon-button" data-oficio-modal="Editar oficio" href="oficios_editar.php?origin=<?= $officeOrigin ?>&id=<?= (int)$row['id'] ?>" title="Editar oficio" aria-label="Editar oficio <?= h($row['numero']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m16 3 5 5-12 12-6 1 1-6L16 3Zm-3 3 5 5"/></svg></a><?php endif; ?>
                    <?php if (\App\Support\Access::admin()): ?><a class="office-icon-button" href="oficios_eliminar.php?id=<?= (int)$row['id'] ?>&return_to=<?= urlencode($returnTo) ?>" title="Eliminar oficio" aria-label="Eliminar oficio <?= h($row['numero']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg></a><?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<div class="case-modal-backdrop" id="office-case-modal" hidden aria-hidden="true">
  <div class="case-modal-dialog" role="dialog" aria-modal="true" aria-label="Ficha del expediente" tabindex="-1">
    <div class="case-modal-loading" role="status">Cargando expediente…</div>
  </div>
</div>
<dialog id="oficio-detail-modal" aria-labelledby="oficio-detail-title">
  <header><h2 id="oficio-detail-title">Oficio</h2><button class="btn" type="button" id="oficio-detail-close" aria-label="Cerrar oficio">×</button></header>
  <iframe title="Detalle del oficio" id="oficio-detail-frame"></iframe>
</dialog>
<style>
#oficio-detail-modal{width:min(1120px,calc(100vw - 32px));height:88vh;max-height:calc(100vh - 32px);padding:0;border:1px solid var(--border);border-radius:16px;background:var(--card);color:var(--text);box-shadow:0 24px 80px #0f172a55}
#oficio-detail-modal::backdrop{background:#0f172a88;backdrop-filter:blur(3px)}
#oficio-detail-modal header{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--border)}
#oficio-detail-modal h2{margin:0;font-size:1.15rem}
#oficio-detail-frame{display:block;width:100%;height:calc(100% - 64px);border:0;background:var(--card)}
</style>

<script>
(() => {
  const modal = document.getElementById('oficio-detail-modal');
  const frame = document.getElementById('oficio-detail-frame');
  let opener;
  document.addEventListener('click', event => {
    const link = event.target.closest('[data-oficio-modal]');
    if (!link) return;
    event.preventDefault();
    opener = link;
    const url = new URL(link.href, location.href);
    url.searchParams.set('embed','1');
    if (link.dataset.oficioModal === 'Nuevo oficio' && !url.searchParams.has('accidente_id')) url.searchParams.set('origin','gestion');
    document.getElementById('oficio-detail-title').textContent = link.dataset.oficioModal;
    frame.src = url.href;
    link.closest('details')?.removeAttribute('open');
    modal.showModal();
  });
  document.getElementById('oficio-detail-close').addEventListener('click', () => modal.close());
  modal.addEventListener('click', event => {
    const box = modal.getBoundingClientRect();
    if (event.target === modal && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) modal.close();
  });
  modal.addEventListener('close', () => { frame.src='about:blank'; opener?.focus(); });
  window.addEventListener('message', event => {
    if (event.origin === location.origin && event.source === frame.contentWindow && event.data?.type === 'oficio.close') modal.close();
  });
})();
</script>
<script>
(() => {
  const modal = document.getElementById('office-case-modal');
  const dialog = modal?.querySelector('.case-modal-dialog');
  if (!modal || !dialog) return;
  let previousFocus = null;
  let requestController = null;
  const close = () => {
    requestController?.abort();
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    modal.hidden = true;
    document.body.style.overflow = '';
    previousFocus?.focus();
  };
  const open = async (id) => {
    requestController?.abort();
    requestController = new AbortController();
    previousFocus = document.activeElement;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    dialog.innerHTML = '<div class="case-modal-loading" role="status">Cargando expediente…</div>';
    requestAnimationFrame(() => modal.classList.add('is-open'));
    dialog.focus();
    try {
      const response = await fetch('gestion_expedientes.php?id=' + encodeURIComponent(id) + '&modal=1', {
        headers: {'X-Requested-With': 'XMLHttpRequest'}, signal: requestController.signal
      });
      if (!response.ok) throw new Error('No se pudo cargar el expediente.');
      dialog.innerHTML = await response.text();
    } catch (error) {
      if (error.name === 'AbortError') return;
      dialog.innerHTML = '<div class="case-modal-error">No se pudo cargar el expediente.</div>';
    }
  };
  document.addEventListener('click', event => {
    const row = event.target.closest('tbody tr[data-case-id]');
    if (!row || event.target.closest('a, button, input, select, textarea, summary, details, form')) return;
    open(row.dataset.caseId);
  });
  document.addEventListener('keydown', event => {
    const row = event.target.closest('tbody tr[data-case-id]');
    if (!row || event.target !== row || !['Enter', ' '].includes(event.key)) return;
    event.preventDefault();
    open(row.dataset.caseId);
  });
  modal.addEventListener('click', event => {
    if (event.target === modal || event.target.closest('[data-case-modal-close]')) close();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !modal.hidden) close();
  });
})();

function syncStateClass(select) {
  select.dataset.state = (select.value || '').toUpperCase();
}

function bindOficioStates() {
document.querySelectorAll('.js-state').forEach(function(select){
  if (select.dataset.bound) return;
  select.dataset.bound = '1';
  syncStateClass(select);
  select.addEventListener('change', async function(){
    const previous = this.dataset.prev || this.value;
    this.disabled = true;
    this.classList.add('is-saving');
    try {
      const response = await fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},
        body: new URLSearchParams({ajax:'estado', id:this.dataset.id, estado:this.value})
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.msg || 'No se pudo actualizar el estado.');
      this.dataset.prev = this.value;
      syncStateClass(this);
      const row = this.closest('tr');
      if (row) {
        row.classList.add('row-updated');
        window.setTimeout(function(){ row.classList.remove('row-updated'); }, 1400);
      }
    } catch (error) {
      alert(error.message || 'No se pudo actualizar el estado.');
      this.value = previous;
      syncStateClass(this);
    } finally {
      this.disabled = false;
      this.classList.remove('is-saving');
    }
  });
  select.dataset.prev = select.value;
});
}
bindOficioStates();
document.addEventListener('oficios:filtered', bindOficioStates);
</script>
<script>
(() => {
  const form = document.getElementById('oficio-filters');
  const moreFilters = form.querySelector('.office-more-filters');
  const mobileFilters = window.matchMedia('(max-width:760px)');
  const syncFilterLayout = () => { moreFilters.open = !mobileFilters.matches; };
  syncFilterLayout();
  mobileFilters.addEventListener('change', syncFilterLayout);
  const search = form.querySelector('[name="q"]');
  let timer, controller, revision = 0;
  const status = document.createElement('div');
  status.className = 'small';
  status.setAttribute('role', 'status');
  form.append(status);
  const invalidate = () => {
    clearTimeout(timer);
    controller?.abort();
    revision++;
  };
  const update = async () => {
    invalidate();
    const current = revision;
    controller = new AbortController();
    const url = new URL(form.action || location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    status.textContent = 'Filtrando…';
    try {
      const response = await fetch(url, {signal: controller.signal});
      if (!response.ok) throw new Error();
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      const table = doc.querySelector('.table-area');
      if (!table) throw new Error();
      if (current !== revision) return;
      document.querySelector('.table-area').replaceWith(table);
      const title = document.querySelector('.filter-title');
      title.querySelector(':scope > .small')?.remove();
      const count = doc.querySelector('.filter-title > .small');
      if (count) title.append(count);
      history.replaceState(null, '', url);
      status.textContent = '';
      document.dispatchEvent(new Event('oficios:filtered'));
    } catch (error) {
      if (error.name !== 'AbortError' && current === revision) status.textContent = 'No se pudo filtrar. Intenta nuevamente.';
    }
  };
  form.addEventListener('submit', event => { event.preventDefault(); update(); });
  form.querySelectorAll('select').forEach(select => select.addEventListener('change', update));
  search.addEventListener('input', event => {
    invalidate();
    if (!event.isComposing) timer = setTimeout(update, 250);
  });
  search.addEventListener('compositionend', () => { invalidate(); timer = setTimeout(update, 250); });
  form.querySelector('.filters-actions a').addEventListener('click', event => {
    event.preventDefault();
    search.value = '';
    form.querySelectorAll('select').forEach(select => { select.value = select.name === 'anio' ? 'todos' : ''; });
    update();
  });
})();
</script>
<?php if ($pendingDownloadUrl !== ''): ?><iframe src="<?= h($pendingDownloadUrl) ?>" title="Descarga del oficio guardado" hidden></iframe><?php endif; ?>
</body>
</html>
