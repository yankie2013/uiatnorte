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
$anio = trim((string) ($_GET['anio'] ?? ''));
$entidadId = trim((string) ($_GET['entidad_id'] ?? ''));
$sidpol = trim((string) ($_GET['sidpol'] ?? ''));
$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
$estado = trim((string) ($_GET['estado'] ?? ''));
$msg = trim((string) ($_GET['msg'] ?? ''));

$filters = [
    'q' => $q,
    'anio' => $anio,
    'entidad_id' => $entidadId,
    'sidpol' => $sidpol,
    'accidente_id' => $accidenteId,
    'estado' => $estado,
];
$ctx = $service->listado($filters);
$rows = $ctx['rows'];
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
if ($q !== '') {
    $activeFilters[] = ['label' => 'Texto', 'value' => $q, 'clear' => 'q'];
}
if ($anio !== '') {
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

$clearFiltersUrl = build_url([
    'q' => null,
    'anio' => null,
    'entidad_id' => null,
    'sidpol' => null,
    'estado' => null,
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
body{background:var(--page);color:var(--text)}
.wrap{max-width:1380px;margin:28px auto;padding:20px 18px 28px}
.page-head{display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:flex-start;margin-bottom:18px}
.page-copy h1{margin:0;font-size:2rem;line-height:1.1}
.page-copy p{margin:8px 0 0;color:var(--muted);font-size:1rem}
.head-chips,.actions,.filters-actions,.active-filters,.tools,.action-links{display:flex;gap:8px;flex-wrap:wrap}
.head-chips{margin-top:14px}
.pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;border:1px solid var(--border);background:var(--card-soft);color:var(--muted);font-size:.88rem}
.pill strong{color:var(--text);font-weight:800}
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  min-height:42px;padding:10px 14px;border-radius:12px;border:1px solid var(--border);
  background:var(--card);color:var(--text);text-decoration:none;font-weight:800;cursor:pointer;
  transition:transform .14s ease, box-shadow .14s ease, border-color .14s ease, background .14s ease;
}
.btn:hover{transform:translateY(-1px);box-shadow:0 12px 24px rgba(15,23,42,.10)}
.btn.primary{background:linear-gradient(135deg,var(--primary),#3d7bff);border-color:transparent;color:#fff}
.btn.soft{background:var(--primary-soft);border-color:transparent;color:var(--primary)}
.btn.danger{color:var(--danger)}
.btn.sm{min-height:36px;padding:8px 12px;border-radius:10px;font-size:.92rem}
.panel{
  background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.88));
  border:1px solid var(--border);
  border-radius:24px;
  box-shadow:var(--shadow);
  overflow:hidden;
}
@media (prefers-color-scheme: dark){
  .panel{background:linear-gradient(180deg,rgba(15,26,47,.96),rgba(15,26,47,.90))}
}
.panel-head{padding:20px 20px 8px}
.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px}
.stat{padding:16px;border:1px solid var(--border);border-radius:18px;background:var(--card-soft)}
.stat .label{display:block;color:var(--muted);font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em}
.stat .value{display:block;margin-top:8px;font-size:1.7rem;font-weight:900;line-height:1}
.stat .meta{display:block;margin-top:6px;color:var(--muted);font-size:.88rem}
.stat.primary{background:var(--primary-soft);border-color:transparent}
.stat.primary .label,.stat.primary .value{color:var(--primary)}
.filter-box{margin-top:18px;padding:18px;border:1px solid var(--border);border-radius:22px;background:var(--card)}
.filter-title{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
.filter-title strong{font-size:1rem}
.small{font-size:.92rem;color:var(--muted)}
.filters-grid{display:grid;grid-template-columns:2.2fr .7fr 1.2fr .9fr 1fr;gap:12px;align-items:end}
.field label{display:block;margin:0 0 6px;color:var(--muted);font-size:.84rem;font-weight:800}
.field input,.field select{
  width:100%;
  min-height:46px;
  padding:11px 13px;
  border-radius:14px;
  border:1px solid var(--border);
  background:var(--card-soft);
  color:var(--text);
}
.field input:focus,.field select:focus{
  border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(36,86,214,.12);
  outline:none;
}
.filters-actions{margin-top:14px}
.active-filters{margin-top:14px;align-items:center}
.filter-chip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 12px;
  border-radius:999px;
  border:1px solid var(--border);
  background:var(--card-soft);
  color:var(--text);
  text-decoration:none;
  font-size:.88rem;
}
.filter-chip span{color:var(--muted)}
.table-area{padding:0 20px 20px}
.table-meta{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;padding:10px 0 14px}
.table-shell{border:1px solid var(--border);border-radius:22px;overflow:auto;background:var(--card)}
table{width:100%;border-collapse:separate;border-spacing:0;min-width:1080px}
thead th{
  position:sticky;
  top:0;
  z-index:1;
  padding:15px 14px;
  text-align:left;
  color:var(--muted);
  font-size:.82rem;
  letter-spacing:.05em;
  text-transform:uppercase;
  background:var(--card-soft);
  border-bottom:1px solid var(--border);
}
tbody td{padding:16px 14px;border-bottom:1px solid var(--border);vertical-align:top}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover td{background:rgba(148,163,184,.06)}
tbody tr.row-updated td{background:rgba(34,197,94,.10)}
.sidpol-main{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:var(--primary-soft);color:var(--primary);font-weight:900}
.sidpol-sub,.muted{margin-top:6px;color:var(--muted)}
.numero-main{font-size:1.08rem;font-weight:900}
.cell-title{font-weight:800;line-height:1.35}
.cell-subtitle{margin-top:4px;color:var(--muted);line-height:1.45}
.ref-text{max-width:360px;line-height:1.55;color:var(--muted)}
.tool{
  display:inline-flex;align-items:center;justify-content:center;
  padding:7px 10px;border-radius:999px;border:1px solid var(--border);
  background:var(--card-soft);color:var(--text);text-decoration:none;font-size:.84rem;font-weight:700;
}
.action-links{margin-top:10px}
.state{
  width:100%;
  min-width:130px;
  min-height:40px;
  padding:9px 12px;
  border-radius:12px;
  border:1px solid var(--border);
  background:var(--card-soft);
  color:var(--text);
  font-weight:800;
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
  tbody{display:grid;gap:12px;padding:12px}
  tbody tr{display:block;border:1px solid var(--border);border-radius:18px;overflow:hidden;background:var(--card)}
  tbody td{display:block;padding:12px 14px;border-bottom:1px solid var(--border)}
  tbody td:last-child{border-bottom:none}
  tbody td::before{
    content:attr(data-label);
    display:block;
    margin-bottom:6px;
    color:var(--muted);
    font-size:.78rem;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
  }
  .ref-text{max-width:none}
  .ok{margin-left:14px;margin-right:14px}
}
</style>
</head>
<body>
<div class="wrap">
  <div class="page-head">
    <div class="page-copy">
      <h1>Oficios</h1>
      <p>Listado operativo del m&oacute;dulo con filtros, seguimiento de estado y accesos r&aacute;pidos.</p>
      <div class="head-chips">
        <div class="pill"><span>Registros</span><strong><?= count($rows) ?></strong></div>
        <?php if ($accidenteId > 0): ?><div class="pill"><span>Accidente</span><strong>#<?= h($accidenteId) ?></strong></div><?php endif; ?>
        <?php if ($sidpol !== ''): ?><div class="pill"><span>SIDPOL</span><strong><?= h($sidpol) ?></strong></div><?php endif; ?>
        <?php if ($estado !== ''): ?><div class="pill"><span>Estado</span><strong><?= h($estado) ?></strong></div><?php endif; ?>
      </div>
    </div>
    <div class="actions">
      <?php if ($accidenteId > 0): ?><a class="btn" href="Dato_General_accidente.php?accidente_id=<?= urlencode((string) $accidenteId) ?>">Datos generales SIDPOL</a><?php endif; ?>
      <?php if ($accidenteId > 0): ?><a class="btn soft" href="oficio_protocolo_express.php?accidente_id=<?= urlencode((string) $accidenteId) ?>&return_to=<?= urlencode($returnTo) ?>">Necropsia r&aacute;pida</a><?php endif; ?>
      <?php if ($accidenteId > 0): ?><a class="btn soft" href="oficio_peritaje_express.php?accidente_id=<?= urlencode((string) $accidenteId) ?>&return_to=<?= urlencode($returnTo) ?>">Peritaje r&aacute;pido</a><?php endif; ?>
      <a class="btn primary" href="oficios_nuevo.php<?= $accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : ($sidpol !== '' ? ('?sidpol=' . urlencode($sidpol)) : '') ?>">+ Nuevo oficio</a>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <?php if ($msg === 'eliminado'): ?><div class="ok">Oficio eliminado correctamente.</div><?php endif; ?>

      <div class="stats-grid">
        <div class="stat primary">
          <span class="label">Total visible</span>
          <span class="value"><?= count($rows) ?></span>
          <span class="meta">Registros seg&uacute;n filtros actuales</span>
        </div>
        <div class="stat">
          <span class="label">Borradores</span>
          <span class="value"><?= (int) ($estadoStats['BORRADOR'] ?? 0) ?></span>
          <span class="meta">Pendientes de cierre o env&iacute;o</span>
        </div>
        <div class="stat">
          <span class="label">Enviados</span>
          <span class="value"><?= (int) ($estadoStats['ENVIADO'] ?? 0) ?></span>
          <span class="meta">Documentos ya despachados</span>
        </div>
        <div class="stat">
          <span class="label">Archivados</span>
          <span class="value"><?= (int) ($estadoStats['ARCHIVADO'] ?? 0) ?></span>
          <span class="meta">Historial consolidado</span>
        </div>
      </div>

      <div class="filter-box">
        <div class="filter-title">
          <div>
            <strong>Filtros de b&uacute;squeda</strong>
            <div class="small">Puedes combinar texto, a&ntilde;o, entidad, SIDPOL y estado.</div>
          </div>
          <?php if ($activeFilters): ?><div class="small"><?= count($activeFilters) ?> filtro(s) activo(s)</div><?php endif; ?>
        </div>

        <form method="get">
          <?php if ($accidenteId > 0): ?><input type="hidden" name="accidente_id" value="<?= h($accidenteId) ?>"><?php endif; ?>
          <div class="filters-grid">
            <div class="field">
              <label for="q">B&uacute;squeda general</label>
              <input id="q" type="text" name="q" value="<?= h($q) ?>" placeholder="N&uacute;mero, SIDPOL, asunto, referencia o placa">
            </div>
            <div class="field">
              <label for="anio">A&ntilde;o</label>
              <input id="anio" type="number" name="anio" value="<?= h($anio) ?>" placeholder="2026">
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
              <label for="sidpol">SIDPOL</label>
              <input id="sidpol" type="text" name="sidpol" value="<?= h($sidpol) ?>" placeholder="Ej. 32813425">
            </div>
            <div class="field">
              <label for="estado">Estado</label>
              <select id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach ($ctx['estados'] as $item): ?>
                  <option value="<?= h($item) ?>" <?= $estado === $item ? 'selected' : '' ?>><?= h($item) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="filters-actions">
            <button class="btn primary" type="submit">Filtrar</button>
            <a class="btn" href="<?= h($clearFiltersUrl) ?>">Limpiar filtros</a>
          </div>
        </form>

        <?php if ($activeFilters): ?>
          <div class="active-filters">
            <?php foreach ($activeFilters as $filter): ?>
              <a class="filter-chip" href="<?= h(build_url([$filter['clear'] => null])) ?>">
                <span><?= $filter['label'] ?></span>
                <strong><?= h($filter['value']) ?></strong>
              </a>
            <?php endforeach; ?>
            <a class="filter-chip" href="<?= h($clearFiltersUrl) ?>"><strong>Limpiar todo</strong></a>
          </div>
        <?php endif; ?>
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
              <th>SIDPOL</th>
              <th>N&uacute;mero</th>
              <th>Entidad / Asunto</th>
              <th>Referencia</th>
              <th>Fecha</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="7">
                  <div class="empty">
                    <strong>No hay resultados para los filtros aplicados.</strong>
                    <div class="small" style="margin-top:8px;">Prueba limpiando filtros o usando una b&uacute;squeda m&aacute;s amplia.</div>
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
                $referenciaFull = trim((string) ($row['detalle'] ?? ''));
                $referenciaCorta = $referenciaFull !== '' ? mb_strimwidth($referenciaFull, 0, 110, '...') : '-';
                $vehiculo = trim((string) (($row['veh_ut'] ?? '') . ' ' . ($row['veh_placa'] ?? '')));
              ?>
              <tr>
                <td data-label="SIDPOL">
                  <div class="sidpol-main"><?= h($row['registro_sidpol'] ?: '-') ?></div>
                  <div class="sidpol-sub">Acc. <?= h($row['accid'] ?: '-') ?></div>
                </td>
                <td data-label="N&uacute;mero">
                  <div class="numero-main"><?= h($row['numero']) ?>/<?= h($row['anio']) ?></div>
                  <div class="muted">ID <?= h($row['id']) ?></div>
                </td>
                <td data-label="Entidad / Asunto">
                  <div class="cell-title"><?= h($row['entidad'] ?: '-') ?></div>
                  <div class="cell-subtitle"><?= h($row['asunto_nombre'] ?: '-') ?></div>
                  <?php if ($vehiculo !== ''): ?><div class="cell-subtitle">Veh&iacute;culo: <?= h($vehiculo) ?></div><?php endif; ?>
                </td>
                <td data-label="Referencia">
                  <div class="ref-text" title="<?= h($referenciaFull !== '' ? $referenciaFull : '-') ?>"><?= h($referenciaCorta) ?></div>
                </td>
                <td data-label="Fecha">
                  <div class="cell-title"><?= h(format_display_date((string) ($row['fecha_emision'] ?? ''))) ?></div>
                  <div class="cell-subtitle"><?= h((string) ($row['fecha_emision'] ?? '')) ?></div>
                </td>
                <td data-label="Estado">
                  <select class="state js-state" data-id="<?= h($row['id']) ?>">
                    <?php foreach ($ctx['estados'] as $item): ?>
                      <option value="<?= h($item) ?>" <?= (string) ($row['estado'] ?? '') === $item ? 'selected' : '' ?>><?= h($item) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td data-label="Acciones">
                  <div class="tools">
                    <?php if ($isCamaraVideo): ?><a class="tool" target="_blank" rel="noopener" href="word_oficio_camaras.php?oficio_id=<?= h($row['id']) ?>">&#128249; Camara</a><?php endif; ?>
                    <?php if ($isRemitir): ?><a class="tool" target="_blank" rel="noopener" href="oficio_remitir_diligencia.php?oficio_id=<?= h($row['id']) ?><?= !empty($row['accid']) ? '&accidente_id=' . h($row['accid']) : '' ?>">Remitir</a><?php endif; ?>
                    <?php if ($isDosaje): ?><a class="tool" target="_blank" rel="noopener" href="oficio_resultado_dosaje.php?oficio_id=<?= h($row['id']) ?>">Dosaje</a><?php endif; ?>
                    <?php if ($isPeritaje): ?><a class="tool" target="_blank" rel="noopener" href="oficio_peritaje.php?oficio_id=<?= h($row['id']) ?>">Peritaje</a><?php endif; ?>
                    <?php if ($isNecropsia): ?><a class="tool" target="_blank" rel="noopener" href="oficio_protocolo.php?oficio_id=<?= h($row['id']) ?><?= !empty($row['inv_per_id']) ? '&inv_id=' . h($row['inv_per_id']) : '' ?>">Necropsia</a><?php endif; ?>
                  </div>
                  <div class="action-links">
                    <a class="btn sm" href="oficios_leer.php?id=<?= h($row['id']) ?>">Ver</a>
                    <a class="btn sm" href="oficios_editar.php?id=<?= h($row['id']) ?>">Editar</a>
                    <form action="oficios_eliminar.php" method="post" style="display:inline" onsubmit="return confirm('Eliminar el oficio?');">
                      <input type="hidden" name="id" value="<?= h($row['id']) ?>">
                      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                      <button class="btn sm danger" type="submit">Eliminar</button>
                    </form>
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
<script>
function syncStateClass(select) {
  select.dataset.state = (select.value || '').toUpperCase();
}

document.querySelectorAll('.js-state').forEach(function(select){
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
</script>
</body>
</html>
