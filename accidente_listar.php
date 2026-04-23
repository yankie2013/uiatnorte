<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function lower_u(string $value): string {
  return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function normalizar_rol_resumen(?string $rol): ?string {
  $rol = trim((string)$rol);
  if ($rol === '') return null;
  $norm = str_replace('Ã³', 'o', lower_u($rol));
  if (str_contains($norm, 'conduc')) return 'Conductor';
  if (str_contains($norm, 'peaton')) return 'peaton';
  return null;
}

function normalizar_lesion_resumen(?string $lesion): string {
  $lesion = trim((string)$lesion);
  if ($lesion === '') return 'Ileso';
  $norm = str_replace('Ã³', 'o', lower_u($lesion));
  if (str_contains($norm, 'fallec')) return 'Fallecido';
  if ($norm === 'ileso' || $norm === 'sin lesion' || $norm === 'sin lesiones') return 'Ileso';
  return 'Herido';
}

function fecha_lista_corta(?string $fecha): string {
  $fecha = trim((string)$fecha);
  if ($fecha === '') return '-';
  $ts = strtotime($fecha);
  if ($ts === false) return $fecha;
  return date('d/m/Y H:i', $ts);
}

function chip_rol_class(?string $rol): string {
  return trim((string)$rol) === 'Conductor' ? 'chip-role-conductor' : 'chip-role-peaton';
}

function chip_lesion_class(?string $lesion): string {
  return match (trim((string)$lesion)) {
    'Fallecido' => 'chip-status-fallecido',
    'Ileso' => 'chip-status-ileso',
    default => 'chip-status-herido',
  };
}

function placa_visible(?string $placa): string {
  $placa = trim((string)$placa);
  if ($placa === '') return 'SIN PLACA';
  return str_starts_with($placa, 'SPLACA') ? 'SIN PLACA' : $placa;
}

function url_estado_accidente(string $estado): string {
  return 'accidente_listar.php?' . http_build_query(['estado' => $estado]);
}

function tipo_registro_label(?string $tipo): string {
  $tipo = trim((string)$tipo);
  if ($tipo === 'Intervencion') return 'Intervención';
  return $tipo;
}

function vehiculo_tipo_resumen(array $veh): string {
  foreach (['tipo_nombre', 'carroceria_nombre', 'vinculo_tipo'] as $key) {
    $value = trim((string)($veh[$key] ?? ''));
    if ($value !== '') return $value;
  }
  return 'vehiculo';
}

/* ============================
   AJAX: cambiar estado (inline) / cambiar folder (inline) / priority
============================ */
if (($_POST['ajax'] ?? '') === 'estado') {
  $id     = (int)($_POST['id'] ?? 0);
  $estado = trim($_POST['estado'] ?? '');
  header('Content-Type: application/json; charset=utf-8');

  $permitidos = ['Pendiente','Resuelto','Con diligencias'];
  if ($id>0 && in_array($estado,$permitidos,true)) {
    $st = $pdo->prepare("UPDATE accidentes SET estado=? WHERE id=?");
    $st->execute([$estado,$id]);
    echo json_encode(['ok'=>true]);
  } else {
    echo json_encode(['ok'=>false,'msg'=>'Estado no permitido']);
  }
  exit;
}

if (($_POST['ajax'] ?? '') === 'folder') {
  $id     = (int)($_POST['id'] ?? 0);
  $raw    = $_POST['folder'] ?? '';
  header('Content-Type: application/json; charset=utf-8');

  if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID invÃ¡lido']); exit; }

  if ($raw === '') {
    // Guardar como NULL
    $st = $pdo->prepare("UPDATE accidentes SET folder=NULL WHERE id=?");
    $st->execute([$id]);
    echo json_encode(['ok'=>true,'val'=>null]);
  } else {
    $n = (int)$raw;
    if ($n>=1 && $n<=10) {
      $st = $pdo->prepare("UPDATE accidentes SET folder=? WHERE id=?");
      $st->execute([$n,$id]);
      echo json_encode(['ok'=>true,'val'=>$n]);
    } else {
      echo json_encode(['ok'=>false,'msg'=>'Folder invÃ¡lido (vacÃ­o o 1..10)']);
    }
  }
  exit;
}

/* NEW: handler para prioridad (priority) */
if (($_POST['ajax'] ?? '') === 'priority') {
  $id = (int)($_POST['id'] ?? 0);
  $raw = $_POST['priority'] ?? '';
  header('Content-Type: application/json; charset=utf-8');

  if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID invÃ¡lido']); exit; }

  // Permitimos '1' o '0' o valores vacÃ­os (-> 0)
  $n = ($raw === '' ? 0 : ((int)$raw ? 1 : 0));
  $st = $pdo->prepare("UPDATE accidentes SET priority=? WHERE id=?");
  $st->execute([$n, $id]);

  echo json_encode(['ok'=>true,'val'=>$n]);
  exit;
}

/* ============================
   FILTROS
============================ */
$q        = trim($_GET['q'] ?? '');
$desde    = trim($_GET['desde'] ?? '');
$hasta    = trim($_GET['hasta'] ?? '');
$comisaria_id = trim($_GET['comisaria_id'] ?? '');
$persona   = trim($_GET['persona']  ?? '');
$distrito  = trim($_GET['distrito'] ?? '');
$vehiculo  = trim($_GET['vehiculo'] ?? '');
$registro_sidpol = trim($_GET['registro_sidpol'] ?? ''); // <-- NUEVO
$nro_informe_policial = trim($_GET['nro_informe_policial'] ?? '');
$tipoRegistroOpciones = [
  '' => 'TODOS',
  'Carpeta' => 'CARPETA',
  'Intervencion' => 'INTERVENCIÓN',
];
$tipo_registro = trim($_GET['tipo_registro'] ?? '');
if (!array_key_exists($tipo_registro, $tipoRegistroOpciones)) {
  $tipo_registro = '';
}
$estadoOpciones = [
  'todos' => 'TODOS',
  'Pendiente' => 'PENDIENTE',
  'Resuelto' => 'RESUELTO',
  'Con diligencias' => 'CON DILIGENCIAS',
];
$estadoFiltro = trim($_GET['estado'] ?? 'Pendiente');
if (!array_key_exists($estadoFiltro, $estadoOpciones)) {
  $estadoFiltro = 'Pendiente';
}

/* ============================
   LISTA DE ComisariaS
============================ */
$comisarias = $pdo->query("SELECT id, nombre FROM comisarias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

/* ============================
   QUERY BASE
============================ */
// âžœ AÃ±adimos a.estado, a.folder y a.priority
$sql = "SELECT a.id,a.registro_sidpol,a.tipo_registro,a.nro_informe_policial,a.lugar,a.fecha_accidente,a.estado,a.folder,a.priority,c.nombre AS comisaria
        FROM accidentes a
        LEFT JOIN comisarias c ON c.id=a.comisaria_id
        WHERE 1=1";
$params = [];

if($q!==''){
  $sql .= " AND (a.registro_sidpol LIKE ? OR a.lugar LIKE ?)";
  $params[]="%$q%"; $params[]="%$q%";
}

if($registro_sidpol !== ''){
  $sql .= " AND a.registro_sidpol LIKE ?";
  $params[] = "%$registro_sidpol%";
}

if($nro_informe_policial !== ''){
  $sql .= " AND a.nro_informe_policial LIKE ?";
  $params[] = "%$nro_informe_policial%";
}
if($tipo_registro !== ''){
  $sql .= " AND a.tipo_registro = ?";
  $params[] = $tipo_registro;
}


if($desde!==''){
  $sql .= " AND a.fecha_accidente>=?";
  $params[]=$desde;
}
if($hasta!==''){
  $sql .= " AND a.fecha_accidente<=?";
  $params[]=$hasta;
}
if($comisaria_id!==''){
  $sql .= " AND a.comisaria_id=?";
  $params[]=$comisaria_id;
}
if($estadoFiltro !== 'todos'){
  $sql .= " AND COALESCE(NULLIF(TRIM(a.estado), ''), 'Pendiente') = ?";
  $params[] = $estadoFiltro;
}

/* PERSONA: nombres o apellidos */
if($persona!==''){
  $sql .= " AND EXISTS (
              SELECT 1
                FROM involucrados_personas ip
                JOIN personas p ON p.id = ip.persona_id
               WHERE ip.accidente_id = a.id
                 AND (
                   CONCAT_WS(' ', p.nombres, p.apellido_paterno, p.apellido_materno) LIKE ?
                   OR p.nombres LIKE ?
                   OR p.apellido_paterno LIKE ?
                   OR p.apellido_materno LIKE ?
                 )
            )";
  $params[] = "%$persona%";
  $params[] = "%$persona%";
  $params[] = "%$persona%";
  $params[] = "%$persona%";
}

/* DISTRITO: por nombre del distrito del ubigeo del accidente */
if($distrito!==''){
  $sql .= " AND EXISTS (
              SELECT 1
                FROM ubigeo_distrito d
               WHERE d.cod_dep = a.cod_dep
                 AND d.cod_prov = a.cod_prov
                 AND d.cod_dist = a.cod_dist
                 AND d.nombre LIKE ?
            )";
  $params[] = "%$distrito%";
}

/* vehiculo: por placa */
if($vehiculo!==''){
  $sql .= " AND EXISTS (
              SELECT 1
                FROM involucrados_vehiculos iv
                JOIN vehiculos v ON v.id = iv.vehiculo_id
               WHERE iv.accidente_id = a.id
                 AND v.placa LIKE ?
            )";
  $params[] = "%$vehiculo%";
}

/* Orden: prioritarios arriba, luego folder y fecha */
$sql .= " ORDER BY a.priority DESC, (a.folder IS NULL) ASC, a.folder ASC, a.fecha_accidente DESC LIMIT 200";
$st=$pdo->prepare($sql);
$st->execute($params);
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

$personasResumenPorAccidente = [];
$personasDetallePorAccidente = [];
$vehiculosResumenPorAccidente = [];
$accidenteIds = array_values(array_unique(array_map(static fn($row) => (int)($row['id'] ?? 0), $rows)));
if ($accidenteIds !== []) {
  $marks = implode(',', array_fill(0, count($accidenteIds), '?'));
  $sqlInv = "SELECT ip.accidente_id,
                    p.nombres, p.apellido_paterno, p.apellido_materno,
                    ip.lesion,
                    rp.Nombre AS rol_nombre
               FROM involucrados_personas ip
               JOIN personas p ON p.id = ip.persona_id
               JOIN participacion_persona rp ON rp.Id = ip.rol_id
              WHERE ip.accidente_id IN ($marks)
              ORDER BY ip.accidente_id ASC, rp.Nombre ASC, p.apellido_paterno ASC, p.apellido_materno ASC, p.nombres ASC";
  $stInv = $pdo->prepare($sqlInv);
  $stInv->execute($accidenteIds);

  while ($inv = $stInv->fetch(PDO::FETCH_ASSOC)) {
    $accId = (int)($inv['accidente_id'] ?? 0);
    if ($accId <= 0) continue;

    $nombre = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
      (string)($inv['nombres'] ?? ''),
      (string)($inv['apellido_paterno'] ?? ''),
      (string)($inv['apellido_materno'] ?? ''),
    ], static fn($part) => trim((string)$part) !== ''))));

    if ($nombre === '') continue;

    $rolNombre = trim((string)($inv['rol_nombre'] ?? ''));
    $lesionUi = normalizar_lesion_resumen($inv['lesion'] ?? '');

    $personasDetallePorAccidente[$accId][] = [
      'nombre' => $nombre,
      'rol' => ($rolNombre !== '' ? $rolNombre : 'Persona'),
      'lesion' => $lesionUi,
    ];

    $rolUi = normalizar_rol_resumen($rolNombre);
    if ($rolUi === null) continue;

    $personasResumenPorAccidente[$accId][] = [
      'nombre' => $nombre,
      'rol' => $rolUi,
      'lesion' => $lesionUi,
    ];
  }
  $sqlVeh = "SELECT iv.accidente_id,
                    iv.orden_participacion,
                    iv.tipo AS vinculo_tipo,
                    v.placa,
                    tv.nombre AS tipo_nombre,
                    car.nombre AS carroceria_nombre,
                    m.nombre AS marca_nombre,
                    mo.nombre AS modelo_nombre
               FROM involucrados_vehiculos iv
               JOIN vehiculos v ON v.id = iv.vehiculo_id
               LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
               LEFT JOIN carroceria_vehiculo car ON car.id = v.carroceria_id
               LEFT JOIN marcas_vehiculo m ON m.id = v.marca_id
               LEFT JOIN modelos_vehiculo mo ON mo.id = v.modelo_id
              WHERE iv.accidente_id IN ($marks)
              ORDER BY iv.accidente_id ASC, CAST(COALESCE(iv.orden_participacion,'0') AS UNSIGNED) ASC, iv.id ASC";
  $stVeh = $pdo->prepare($sqlVeh);
  $stVeh->execute($accidenteIds);

  while ($veh = $stVeh->fetch(PDO::FETCH_ASSOC)) {
    $accId = (int)($veh['accidente_id'] ?? 0);
    if ($accId <= 0) continue;

    $vehiculosResumenPorAccidente[$accId][] = [
      'orden' => trim((string)($veh['orden_participacion'] ?? '')),
      'placa' => placa_visible($veh['placa'] ?? ''),
      'tipo' => vehiculo_tipo_resumen($veh),
      'marca_modelo' => trim((string)implode(' ', array_filter([
        trim((string)($veh['marca_nombre'] ?? '')),
        trim((string)($veh['modelo_nombre'] ?? '')),
      ], static fn($part) => $part !== ''))),
    ];
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Accidentes | UIAT Norte</title>
<style>
/* ===== Variables de esta vista, atadas al tema global ===== */
html{
  --tbl-head-bg:#eef2ff;
  --tbl-head-bd:#00000014;
  --tbl-row-bg:#ffffff;
  --tbl-row-alt:#fafafa;
  --tbl-row-hover:#f3f4f6;
  --tbl-bd:#00000014;
}
html[data-theme-resolved="dark"]{
  --tbl-head-bg:#0f1628;
  --tbl-head-bd:#ffffff1f;
  --tbl-row-bg:#0f1422;
  --tbl-row-alt:#11192b;
  --tbl-row-hover:#1b2236;
  --tbl-bd:#ffffff1f;
}

/* ===== Layout base ===== */
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--fg);font:13px system-ui} /* ligera reducciÃ³n global */
.wrap{max-width:1200px;margin:20px auto;padding:14px}
.title{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap}
.quick-status{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  align-items:center;
  margin:0 0 12px;
}
.neon-state{
  --neon:#22d3ee;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:38px;
  padding:9px 16px;
  border:1px solid var(--neon);
  border-radius:12px;
  background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02));
  color:var(--fg);
  font-size:12px;
  font-weight:900;
  text-decoration:none;
  text-transform:uppercase;
  box-shadow:0 0 0 1px rgba(255,255,255,.08) inset,0 0 14px rgba(34,211,238,.18);
  transition:transform .12s ease, box-shadow .12s ease, background .12s ease;
}
.neon-state:hover,
.neon-state:focus-visible{
  transform:translateY(-1px);
  box-shadow:0 0 0 1px rgba(255,255,255,.12) inset,0 0 18px var(--neon),0 8px 24px rgba(15,23,42,.18);
  outline:none;
}
.neon-state.active{
  color:#071019;
  background:linear-gradient(90deg,var(--neon),#ffffff);
  box-shadow:0 0 0 1px rgba(255,255,255,.4) inset,0 0 22px var(--neon),0 10px 28px rgba(15,23,42,.2);
}
.neon-pending{--neon:#ff4fd8}
.neon-resolved{--neon:#39ff88}
.neon-dilig{--neon:#ffd166}
.card{background:var(--panel-bg);border:1px solid var(--panel-bd);border-radius:14px;padding:14px;backdrop-filter:blur(8px)}

label{display:block;font-weight:700;margin-bottom:6px;font-size:13px}
input,select{width:100%;padding:8px 10px;border:1px solid var(--field-bd);border-radius:10px;background:var(--field-bg);color:var(--fg)}
.rowflex{display:flex;gap:8px;align-items:center;flex-wrap:wrap}

.btn{display:inline-flex;gap:8px;padding:8px 12px;border:1px solid var(--field-bd);border-radius:10px;background:var(--pill-bg);color:var(--fg);text-decoration:none;font-size:13px}
.btn.primary{background:linear-gradient(90deg,#4f46e5,#06b6d4);border:none;color:#fff}
.btn.small{padding:6px 10px;border-radius:10px;font-weight:700;font-size:12px}
.btn.danger{background:var(--danger);border:none;color:#fff}

/* ===== Badges ===== */
.badge{font-size:12px;padding:3px 8px;border-radius:999px;border:1px solid var(--badge-bd);background:var(--badge-bg)}

/* ===== Caja de filtros ===== */
.filters{display:grid;grid-template-columns:repeat(12,1fr);gap:10px;margin-bottom:10px}
.col-12{grid-column:span 12}.col-6{grid-column:span 6}.col-4{grid-column:span 4}.col-3{grid-column:span 3}.col-2{grid-column:span 2}
@media(max-width:1000px){.col-6,.col-4,.col-3,.col-2{grid-column:span 12}}

/* ===== Tabla ===== */
.table-wrap{overflow:auto;border:1px solid var(--tbl-bd);border-radius:12px}
table{width:100%;border-collapse:separate;border-spacing:0;background:transparent}
thead th{
  position:sticky;top:0;z-index:1;
  background:var(--tbl-head-bg); color:var(--fg);
  text-align:left; font-weight:800; padding:10px; border-bottom:1px solid var(--tbl-head-bd); font-size:13px;
}
tbody td{padding:8px 10px;border-bottom:1px solid var(--tbl-bd); font-size:13px}
tbody tr:nth-child(odd){background:var(--tbl-row-bg)}
tbody tr:nth-child(even){background:var(--tbl-row-alt)}
tbody tr:hover{background:var(--tbl-row-hover)}
th:first-child, td:first-child{padding-left:14px}
th:last-child, td:last-child{padding-right:14px}
.td-actions{white-space:nowrap}
.empty{padding:18px;text-align:center;color:rgba(var(--muted-rgb),1)}
.badge.sidpol-reg { background:transparent; border-color:#d4af37; color:#facc15; font-weight:800; font-size:12px; }
.sidpol-link{ display:inline-block; text-decoration:none; }
.sidpol-link:hover .sidpol-reg,
.sidpol-link:focus-visible .sidpol-reg{ box-shadow:0 0 0 2px rgba(212,175,55,.18); transform:translateY(-1px); }
.inv-people{display:flex;flex-direction:column;gap:4px;min-width:250px}
.inv-person{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.inv-name{font-weight:700}
.inv-meta{display:inline-flex;align-items:center;gap:4px;font-size:11px;padding:2px 8px;border-radius:999px;background:rgba(148,163,184,.16);color:var(--fg)}

/* Refinamiento visual de la lista */
body{font:13px/1.45 Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
thead th{
  padding:11px 12px;
  font-size:12px;
}
tbody td{
  padding:12px;
  vertical-align:top;
}
tbody tr:hover{box-shadow:inset 0 0 0 1px rgba(148,163,184,.08)}
.badge.sidpol-reg{
  background:#fff7db;
  border-color:#f1cc5d;
  color:#b78103;
  font-size:13px;
  padding:5px 10px;
}
.cell-stack{display:flex;flex-direction:column;gap:4px}
.cell-primary{font-size:13px;font-weight:600;color:#14213d;line-height:1.35}
.cell-secondary{font-size:12px;font-weight:500;color:#5c6b7a;line-height:1.35}
.cell-place{max-width:340px}
.cell-date{white-space:nowrap}
.cell-comisaria{max-width:240px}
.inv-people{display:flex;flex-direction:column;gap:8px;min-width:290px;max-width:380px}
.inv-person{display:flex;flex-direction:column;gap:4px}
.inv-name{font-size:13px;line-height:1.35;color:#14213d}
.inv-chips{display:flex;flex-wrap:wrap;gap:6px}
.chip{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;line-height:1}
.chip-role-conductor{background:#e8f1ff;color:#1d4ed8}
.chip-role-peaton{background:#f3e8ff;color:#7c3aed}
.chip-status-ileso{background:#e8f7ee;color:#15803d}
.chip-status-herido{background:#fff4e5;color:#b45309}
.chip-status-fallecido{background:#fee2e2;color:#b91c1c}
.chip-more{background:rgba(148,163,184,.16);color:#475569}
.th-people{min-width:320px}
html[data-theme-resolved="dark"] .cell-primary,
html[data-theme-resolved="dark"] .inv-name{color:#e5edf8}
html[data-theme-resolved="dark"] .cell-secondary{color:#9fb0c6}
html[data-theme-resolved="dark"] .badge.sidpol-reg{
  background:rgba(212,175,55,.12);
  border-color:#d4af37;
  color:#facc15;
}
html[data-theme-resolved="dark"] .chip-more{
  background:rgba(148,163,184,.2);
  color:#cbd5e1;
}

/* Compactar aÃºn mÃ¡s la tabla */
table.compact thead th{ padding:6px 8px !important; font-size:12px !important; }
table.compact tbody td{ padding:6px 8px !important; font-size:12px !important; }
table.compact tbody tr{ height:42px; }

/* BotÃ³n eliminar solo con â€œXâ€ */
/* Ajuste fino de tabla */
table.compact thead th{ padding:11px 10px !important; font-size:12px !important; }
table.compact tbody td{ padding:11px 10px !important; font-size:13px !important; }
table.compact tbody tr{ height:auto; }

.btn-x{
  width:34px; height:34px;
  display:inline-flex; align-items:center; justify-content:center;
  font-weight:800;
  padding:0 !important;
  font-size:16px;
}

/* Chip Oficios */
.btn-oficios{
  background:#eef6ff; border:1px solid #cfe3ff;
}
html[data-theme-resolved="dark"]{
  .btn-oficios{ background:#172036; border-color:#263a66; }
}

/* ===== Estado editable ===== */
.estado-badge{
  cursor:pointer; user-select:none; font-weight:700;
  padding:6px 10px; border-radius:999px; border:1px solid var(--badge-bd);
  display:inline-block; font-size:12px;
}
.estado-pendiente{ background:#f8d7da; color:#842029; }   /* rojo claro */
.estado-resuelto{  background:#d1e7dd; color:#0f5132; }   /* verde */
.estado-dilig{     background:#fff3cd; color:#664d03; }   /* naranja/Ã¡mbar */

html[data-theme-resolved="dark"]{
  div.estado-popup{ background:#1e293b; border-color:#334155; color:#f8fafc; }
}

/* MenÃº flotante para cambiar estado (look igual a los badges) */
.estado-menu{
  position: fixed;
  z-index: 99999;
  display: flex; gap: 8px; align-items: center;
  padding: 8px;
  border-radius: 12px;
  background: var(--panel-bg);
  border: 1px solid var(--panel-bd);
  backdrop-filter: blur(8px);
  box-shadow: 0 8px 20px rgba(0,0,0,.15);
}
.estado-menu .opt{
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px; font-weight: 700;
  border: 1px solid var(--badge-bd);
  cursor: pointer; user-select: none;
  transition: transform .08s ease, box-shadow .08s ease, filter .08s ease;
}
.estado-menu .opt:hover{ transform: translateY(-1px); filter: brightness(1.05); }
.opt-pendiente{ background:#f8d7da; color:#842029; }
.opt-resuelto { background:#d1e7dd; color:#0f5132; }
.opt-dilig    { background:#fff3cd; color:#664d03; }

/* ===== Columnas Folder + Star ===== */
.col-folder { display:flex; align-items:center; gap:12px; min-width:120px; }
.col-folder .prio-btn { background:transparent; border:1px solid rgba(255,255,255,0.04); padding:6px 8px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
.col-folder .prio-btn[aria-pressed="true"] { box-shadow: 0 6px 18px rgba(0,0,0,.18); }

.star { font-size:16px; line-height:1; display:inline-block; }
.star-on { color:#ffd54f; text-shadow:0 1px 0 rgba(0,0,0,.25); }
.star-off { color:rgba(15,23,42,0.35); }

.select-folder{
  padding:6px 10px;
  border-radius:12px;
  font-weight:800;
  font-size:13px;
  background:linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.75));
  border:1.5px solid #d4af37;
  color:var(--fg);
  min-width:68px;
  text-align:center;
}
html[data-theme-resolved="dark"]{
  .select-folder {
    background: rgba(212,175,55,0.12);
    color: #f6f6f6;
    border-color: #e2c96c;
    box-shadow: 0 0 6px rgba(212,175,55,0.12) inset;
  }
  .star-off { color: rgba(255,255,255,0.35); }
}

/* ===== Cards de accidentes ===== */
.acc-card-list{display:flex;flex-direction:column;gap:14px}
.table-wrap{display:none}
.acc-card{
  border:1px solid var(--tbl-bd);
  border-radius:14px;
  background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(248,250,252,.96));
  overflow:hidden;
}
.acc-card-main{
  display:grid;
  grid-template-columns:minmax(0,1.5fr) minmax(220px,.9fr) auto;
  gap:14px;
  padding:14px 16px;
  align-items:start;
}
.acc-card-left{display:flex;flex-direction:column;gap:8px;min-width:0}
.acc-head{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.acc-report{
  display:inline-flex;
  align-items:center;
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:700;
  color:#475569;
  background:#eef2f7;
}
.tipo-reg-chip{
  display:inline-flex;
  align-items:center;
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:800;
  color:#0f766e;
  background:#ccfbf1;
  border:1px solid #99f6e4;
}
.tipo-reg-carpeta{color:#92400e;background:#fef3c7;border-color:#fde68a}
.tipo-reg-intervencion{color:#155e75;background:#cffafe;border-color:#a5f3fc}
.acc-place{font-size:15px;font-weight:700;line-height:1.35;color:#14213d}
.acc-meta{display:flex;flex-wrap:wrap;gap:10px 14px}
.acc-meta-item{display:flex;flex-direction:column;gap:2px;min-width:110px}
.acc-meta-label{font-size:11px;font-weight:700;color:#7b8794;text-transform:uppercase}
.acc-meta-value{font-size:13px;font-weight:600;color:#334155}
.acc-card-center{display:flex;flex-direction:column;gap:8px}
.acc-mini-counts{display:flex;flex-wrap:wrap;gap:6px}
.acc-summary-block{display:flex;flex-direction:column;gap:6px}
.acc-summary-title{font-size:11px;font-weight:700;color:#7b8794;text-transform:uppercase}
.acc-summary-line{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.acc-hint{font-size:12px;font-weight:600;color:#64748b}
.acc-card-right{display:flex;flex-direction:column;align-items:flex-end;gap:10px;justify-content:space-between;min-height:100%}
.acc-top-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.acc-bottom-actions{display:flex;justify-content:flex-end;width:100%}
.acc-card .col-folder{min-width:auto}
.acc-inline-form{display:inline}
.acc-toggle{
  width:34px;height:34px;border-radius:8px;border:1px solid var(--field-bd);
  background:var(--pill-bg);color:var(--fg);font-size:18px;font-weight:700;cursor:pointer;
}
.acc-toggle[aria-expanded="true"]{background:#e8f1ff;color:#1d4ed8;border-color:#bfdbfe}
.acc-detail{
  border-top:1px solid var(--tbl-bd);
  padding:14px 16px 16px;
  background:rgba(248,250,252,.85);
}
.acc-detail-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px}
.acc-panel{
  border:1px solid rgba(148,163,184,.18);
  background:#fff;
  border-radius:12px;
  padding:12px;
}
.acc-panel-title{font-size:12px;font-weight:800;color:#475569;text-transform:uppercase;margin-bottom:10px}
.detail-list{display:flex;flex-direction:column;gap:10px}
.person-detail,.vehicle-detail{display:flex;flex-direction:column;gap:5px}
.vehicle-main{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.vehicle-plate{font-size:14px;font-weight:800;color:#14213d}
.vehicle-type{
  display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;
  background:#eef2ff;color:#3730a3;font-size:11px;font-weight:700;
}
.vehicle-extra{font-size:12px;color:#64748b}
.empty-soft{font-size:12px;color:#64748b}

html[data-theme-resolved="dark"] .acc-card{
  background:linear-gradient(180deg,rgba(15,20,34,.96),rgba(17,25,43,.96));
}
html[data-theme-resolved="dark"] .acc-place,
html[data-theme-resolved="dark"] .vehicle-plate{color:#e5edf8}
html[data-theme-resolved="dark"] .acc-report{background:#1e293b;color:#cbd5e1}
html[data-theme-resolved="dark"] .tipo-reg-carpeta{background:rgba(245,158,11,.18);border-color:rgba(245,158,11,.34);color:#fcd34d}
html[data-theme-resolved="dark"] .tipo-reg-intervencion{background:rgba(6,182,212,.16);border-color:rgba(6,182,212,.34);color:#67e8f9}
html[data-theme-resolved="dark"] .acc-meta-value,
html[data-theme-resolved="dark"] .vehicle-extra{color:#9fb0c6}
html[data-theme-resolved="dark"] .acc-hint{color:#9fb0c6}
html[data-theme-resolved="dark"] .acc-detail{background:rgba(15,23,42,.72)}
html[data-theme-resolved="dark"] .acc-panel{
  background:rgba(15,23,42,.78);
  border-color:rgba(148,163,184,.2);
}
html[data-theme-resolved="dark"] .vehicle-type{background:#1e3a8a;color:#dbeafe}
html[data-theme-resolved="dark"] .acc-toggle[aria-expanded="true"]{
  background:#1e3a8a;color:#dbeafe;border-color:#3b82f6;
}

@media(max-width:980px){
  .acc-card-main{
    grid-template-columns:minmax(0,1fr) auto;
    grid-template-areas:
      "left right"
      "center center";
    align-items:start;
  }
  .acc-card-left{grid-area:left}
  .acc-card-center{grid-area:center}
  .acc-card-right{
    grid-area:right;
    align-self:start;
    justify-self:end;
    align-items:flex-end;
    min-height:100%;
  }
  .acc-top-actions{justify-content:flex-end}
  .acc-bottom-actions{justify-content:flex-end}
  .acc-detail-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="wrap">
  <div class="title">
    <h1 style="margin:0">Accidentes <span class="badge">Listado</span></h1>
    <nav class="toolbar" aria-label="Acciones">
      <a class="btn" href="#" onclick="history.back();return false;">Atras</a>
      <a class="btn" href="index.php">Inicio</a>
      <a class="btn primary" href="accidente_nuevo.php">Nuevo</a>
    </nav>
  </div>

  <nav class="quick-status" aria-label="Accesos rapidos por estado">
    <a class="neon-state neon-pending <?= $estadoFiltro === 'Pendiente' ? 'active' : '' ?>" href="<?=h(url_estado_accidente('Pendiente'))?>">Pendiente</a>
    <a class="neon-state neon-resolved <?= $estadoFiltro === 'Resuelto' ? 'active' : '' ?>" href="<?=h(url_estado_accidente('Resuelto'))?>">Resueltos</a>
    <a class="neon-state neon-dilig <?= $estadoFiltro === 'Con diligencias' ? 'active' : '' ?>" href="<?=h(url_estado_accidente('Con diligencias'))?>">Con diligencias</a>
  </nav>

  <div class="card">
    <form method="get" class="filters" id="filterForm">
        
    <div class="col-3">
  <label>Registro SIDPOL</label>
  <input type="text" name="registro_sidpol" placeholder="Ej: 2025-ABC-123" value="<?=h($_GET['registro_sidpol']??'')?>">
</div>    

    <div class="col-3">
      <label>N&deg; informe policial</label>
      <input type="text" name="nro_informe_policial" placeholder="Ej: 105-2025" value="<?=h($_GET['nro_informe_policial']??'')?>">
    </div>
        
      <div class="col-3">
        <label>Persona</label>
        <input type="text" name="persona" placeholder="Nombres o apellidos" value="<?=h($_GET['persona']??'')?>">
      </div>

      <div class="col-3">
        <label>Distrito</label>
        <input type="text" name="distrito" placeholder="Distrito" value="<?=h($_GET['distrito']??'')?>">
      </div>

      <div class="col-3">
        <label>vehiculo (placa)</label>
        <input type="text" name="vehiculo" placeholder="Placa" value="<?=h($_GET['vehiculo']??'')?>">
      </div>
      
       <div class="col-3">
        <label>Comisaria</label>
        <select name="comisaria_id">
          <option value="">-- Todas --</option>
          <?php foreach($comisarias as $c): ?>
            <option value="<?=$c['id']?>" <?=($comisaria_id==$c['id']?'selected':'')?>><?=h($c['nombre'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-3">
        <label>Estado</label>
        <select name="estado">
          <?php foreach($estadoOpciones as $estadoValue => $estadoLabel): ?>
            <option value="<?=h($estadoValue)?>" <?=($estadoFiltro===$estadoValue?'selected':'')?>><?=h($estadoLabel)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-3">
        <label>Tipo de registro</label>
        <select name="tipo_registro">
          <?php foreach($tipoRegistroOpciones as $tipoValue => $tipoLabel): ?>
            <option value="<?=h($tipoValue)?>" <?=($tipo_registro===$tipoValue?'selected':'')?>><?=h($tipoLabel)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-2" style="align-self:end;display:flex;gap:6px">
        <button class="btn small" type="submit">Filtrar</button>
        <a class="btn small" href="accidente_listar.php">Limpiar</a>
      </div>
    </form>

    <div class="acc-card-list" id="cards-list" role="list" aria-label="Lista de accidentes">
      <?php if (!$rows): ?>
        <div class="empty">Sin resultados</div>
      <?php else: foreach($rows as $i=>$r):
          $estado = $r['estado'] ?: 'Pendiente';
          $cls = ($estado==='Resuelto') ? 'estado-resuelto'
               : (($estado==='Con diligencias') ? 'estado-dilig' : 'estado-pendiente');
          $personasDetalle = $personasDetallePorAccidente[(int)$r['id']] ?? [];
          $personasResumen = $personasResumenPorAccidente[(int)$r['id']] ?? [];
          $vehiculosResumen = $vehiculosResumenPorAccidente[(int)$r['id']] ?? [];
          $vehiculosPreview = array_slice($vehiculosResumen, 0, 2);
          $isPrior = !empty($r['priority']) && (int)$r['priority']===1;
          $folderVal = ($r['folder'] === null ? '' : (string)$r['folder']);
          $tipoRegistro = tipo_registro_label($r['tipo_registro'] ?? '');
          $tipoRegistroClass = ($r['tipo_registro'] ?? '') === 'Intervencion' ? 'tipo-reg-intervencion' : 'tipo-reg-carpeta';
      ?>
        <article class="acc-card" role="listitem" data-id="<?= (int)$r['id'] ?>" data-date="<?= h($r['fecha_accidente'] ?? '') ?>">
          <div class="acc-card-main">
            <div class="acc-card-left">
              <div class="acc-head">
                <a class="sidpol-link" href="accidente_vista_tabs.php?accidente_id=<?= $r['id'] ?>" title="Ver detalles">
                  <span class="badge sidpol-reg"><?=h($r['registro_sidpol'])?></span>
                </a>
                <span class="acc-report"><?=h($r['nro_informe_policial'] ?? '-')?></span>
                <?php if ($tipoRegistro !== ''): ?>
                  <span class="tipo-reg-chip <?=h($tipoRegistroClass)?>"><?=h($tipoRegistro)?></span>
                <?php endif; ?>
              </div>
              <div class="acc-place"><?=h($r['lugar'])?></div>
              <div class="acc-meta">
                <div class="acc-meta-item">
                  <span class="acc-meta-label">Fecha</span>
                  <span class="acc-meta-value"><?=h(fecha_lista_corta($r['fecha_accidente'] ?? ''))?></span>
                </div>
                <div class="acc-meta-item">
                  <span class="acc-meta-label">Comisaria</span>
                  <span class="acc-meta-value"><?=h($r['comisaria'] ?? '-')?></span>
                </div>
              </div>
            </div>

            <div class="acc-card-center">
              <div class="acc-mini-counts">
                <span class="chip chip-more"><?=count($personasDetalle)?> persona(s)</span>
                <span class="chip chip-more"><?=count($vehiculosResumen)?> vehiculo(s)</span>
              </div>
              <div class="acc-hint">Pulsa + para ver personas y vehiculos</div>
            </div>

            <div class="acc-card-right">
              <div class="acc-top-actions">
                <div class="col-folder folder-cell">
                  <button class="prio-btn" title="<?= $isPrior ? 'Quitar prioridad' : 'Marcar prioridad' ?>"
                          data-id="<?= $r['id'] ?>" data-priority="<?= $isPrior ? '1' : '0' ?>"
                          aria-pressed="<?= $isPrior ? 'true' : 'false' ?>">
                    <span class="star <?= $isPrior ? 'star-on' : 'star-off' ?>"><?= $isPrior ? '&#9733;' : '&#9734;' ?></span>
                  </button>
                  <select class="select-folder" data-id="<?=$r['id']?>" aria-label="Folder">
                    <option value="" <?=($folderVal===''?'selected':'')?>>&mdash;</option>
                    <?php for($k=1;$k<=10;$k++): ?>
                      <option value="<?=$k?>" <?=($folderVal===(string)$k?'selected':'')?>><?=$k?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <form class="acc-inline-form" action="accidente_eliminar.php" method="post"
                      onsubmit="return confirm('Eliminar este accidente de forma permanente?');">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn danger small btn-x" title="Eliminar" aria-label="Eliminar">&times;</button>
                </form>
              </div>
              <span class="estado-badge <?=$cls?>"
                    data-id="<?=$r['id']?>"
                    data-estado="<?=h($estado)?>">
                <?=h($estado)?>
              </span>
              <div class="acc-bottom-actions">
                <button class="acc-toggle js-toggle-card" type="button" aria-expanded="false" aria-controls="acc-detail-<?= (int)$r['id'] ?>">+</button>
              </div>
            </div>
          </div>

          <div class="acc-detail" id="acc-detail-<?= (int)$r['id'] ?>" hidden>
            <div class="acc-detail-grid">
              <section class="acc-panel">
                <div class="acc-panel-title">Personas involucradas</div>
                <?php if ($personasDetalle === []): ?>
                  <div class="empty-soft">No hay personas registradas.</div>
                <?php else: ?>
                  <div class="detail-list">
                    <?php foreach ($personasDetalle as $personaItem): ?>
                      <div class="person-detail">
                        <span class="inv-name"><?=h($personaItem['nombre'])?></span>
                        <div class="inv-chips">
                          <span class="chip <?=h(chip_rol_class($personaItem['rol'] ?? ''))?>"><?=h($personaItem['rol'])?></span>
                          <span class="chip <?=h(chip_lesion_class($personaItem['lesion'] ?? ''))?>"><?=h($personaItem['lesion'])?></span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </section>

              <section class="acc-panel">
                <div class="acc-panel-title">Vehiculos involucrados</div>
                <?php if ($vehiculosResumen === []): ?>
                  <div class="empty-soft">No hay vehiculos registrados.</div>
                <?php else: ?>
                  <div class="detail-list">
                    <?php foreach ($vehiculosResumen as $vehiculoItem): ?>
                      <div class="vehicle-detail">
                        <div class="vehicle-main">
                          <span class="vehicle-plate"><?=h($vehiculoItem['placa'])?></span>
                          <span class="vehicle-type"><?=h($vehiculoItem['tipo'])?></span>
                        </div>
                        <div class="vehicle-extra">
                          <?=h(trim(($vehiculoItem['orden'] !== '' ? $vehiculoItem['orden'].' | ' : '').($vehiculoItem['marca_modelo'] !== '' ? $vehiculoItem['marca_modelo'] : 'Sin marca/modelo')))?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </section>
            </div>
          </div>
        </article>
      <?php endforeach; endif; ?>
    </div>

    <div class="table-wrap" role="region" aria-label="Lista de accidentes">
      <table class="compact" role="table" aria-describedby="tbl-desc">
<thead>
  <tr role="row">
    <th role="columnheader" data-sort="registro_sidpol">Registro SIDPOL <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="nro_informe_policial">NÂ° informe policial <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="lugar">Lugar <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="fecha_accidente">Fecha <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="Comisaria">Comisaria <span class="sort-indicator"></span></th>
    <th role="columnheader" class="th-people">Conductor / peaton</th>
    <th role="columnheader" data-sort="folder">Folder <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="estado">Estado <span class="sort-indicator"></span></th>
    <th class="td-actions" role="columnheader">Acciones</th>
  </tr>
</thead>
        <tbody id="tbody-rows" role="rowgroup">
          <?php if (!$rows): ?>
            <tr><td colspan="9" class="empty">Sin resultados</td></tr>
          <?php else: foreach($rows as $i=>$r): 
              $estado = $r['estado'] ?: 'Pendiente';
              $cls = ($estado==='Resuelto') ? 'estado-resuelto'
                   : (($estado==='Con diligencias') ? 'estado-dilig' : 'estado-pendiente');
              $folderVal = (string)($r['folder'] ?? '');
              $personasResumen = $personasResumenPorAccidente[(int)$r['id']] ?? [];
              $personasVisibles = array_slice($personasResumen, 0, 2);
              $personasExtra = array_slice($personasResumen, 2);
              $personasExtraTexto = implode(' | ', array_map(
                static fn($item) => trim((string)($item['nombre'] ?? '')).' - '.trim((string)($item['rol'] ?? '')).' - '.trim((string)($item['lesion'] ?? '')),
                $personasExtra
              ));
              $tipoRegistro = tipo_registro_label($r['tipo_registro'] ?? '');
              $tipoRegistroClass = ($r['tipo_registro'] ?? '') === 'Intervencion' ? 'tipo-reg-intervencion' : 'tipo-reg-carpeta';
          ?>
            <tr data-id="<?= (int)$r['id'] ?>" role="row">
  <td role="cell">
    <a class="sidpol-link" href="accidente_vista_tabs.php?accidente_id=<?= $r['id'] ?>" title="Ver detalles">
      <span class="badge sidpol-reg"><?=h($r['registro_sidpol'])?></span>
    </a>
  </td>
  <td role="cell">
    <div class="cell-stack">
      <span class="cell-primary"><?=h($r['nro_informe_policial'] ?? '-')?></span>
      <?php if ($tipoRegistro !== ''): ?>
        <span class="tipo-reg-chip <?=h($tipoRegistroClass)?>"><?=h($tipoRegistro)?></span>
      <?php endif; ?>
    </div>
  </td>
  <td role="cell">
    <div class="cell-stack cell-place" title="<?=h($r['lugar'])?>">
      <span class="cell-primary"><?=h($r['lugar'])?></span>
    </div>
  </td>
  <td role="cell" data-sort-value="<?=h($r['fecha_accidente'] ?? '')?>">
    <div class="cell-stack cell-date">
      <span class="cell-primary"><?=h(fecha_lista_corta($r['fecha_accidente'] ?? ''))?></span>
    </div>
  </td>
  <td role="cell">
    <div class="cell-stack cell-Comisaria" title="<?=h($r['Comisaria']??'-')?>">
      <span class="cell-secondary"><?=h($r['Comisaria']??'-')?></span>
    </div>
  </td>
  <td role="cell">
    <?php if ($personasResumen === []): ?>
      <span class="cell-secondary">Sin conductor o peaton</span>
    <?php else: ?>
      <div class="inv-people">
        <?php foreach ($personasVisibles as $personaItem): ?>
          <div class="inv-person">
            <span class="inv-name"><?=h($personaItem['nombre'])?></span>
            <div class="inv-chips">
              <span class="chip <?=h(chip_rol_class($personaItem['rol'] ?? ''))?>"><?=h($personaItem['rol'])?></span>
              <span class="chip <?=h(chip_lesion_class($personaItem['lesion'] ?? ''))?>"><?=h($personaItem['lesion'])?></span>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($personasExtra !== []): ?>
          <div class="inv-chips">
            <span class="chip chip-more" title="<?=h($personasExtraTexto)?>">+<?=count($personasExtra)?> mÃ¡s</span>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </td>
  <!-- FOLDER + ESTRELLA prioridad -->
  <td class="col-folder folder-cell" role="cell">
    <?php $isPrior = !empty($r['priority']) && (int)$r['priority']===1; ?>
    <button class="prio-btn" title="<?= $isPrior ? 'Quitar prioridad' : 'Marcar prioridad' ?>"
            data-id="<?= $r['id'] ?>" data-priority="<?= $isPrior ? '1' : '0' ?>"
            aria-pressed="<?= $isPrior ? 'true' : 'false' ?>">
      <span class="star <?= $isPrior ? 'star-on' : 'star-off' ?>"><?= $isPrior ? '&#9733;' : '&#9734;' ?></span>
    </button>

    <select class="select-folder" data-id="<?=$r['id']?>" aria-label="Folder">
      <?php $folderVal = ($r['folder'] === null ? '' : (string)$r['folder']); ?>
      <option value="" <?=($folderVal===''?'selected':'')?>>â€”</option>
      <?php for($k=1;$k<=10;$k++): ?>
        <option value="<?=$k?>" <?=($folderVal===(string)$k?'selected':'')?>><?=$k?></option>
      <?php endfor; ?>
    </select>
  </td>
  <td role="cell">
    <span class="estado-badge <?=$cls?>"
          data-id="<?=$r['id']?>"
          data-estado="<?=h($estado)?>">
      <?=h($estado)?>
    </span>
  </td>
  <td class="td-actions" role="cell">
    <form action="accidente_eliminar.php" method="post" style="display:inline"
          onsubmit="return confirm('Eliminar este accidente de forma permanente?');">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn danger small btn-x" title="Eliminar" aria-label="Eliminar">&times;</button>
    </form>
  </td>
</tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// Busqueda progresiva: los textos filtran mientras se escriben; los selects filtran al cambiar.
(function(){
  const form = document.getElementById('filterForm');
  if (!form) return;

  let submitTimer = null;
  const submitNow = () => form.submit();
  const submitSoon = () => {
    clearTimeout(submitTimer);
    submitTimer = setTimeout(submitNow, 450);
  };

  form.querySelectorAll('input[type="text"]').forEach(input=>{
    input.addEventListener('input', submitSoon);
  });

  form.querySelectorAll('select').forEach(select=>{
    select.addEventListener('change', submitNow);
  });

  const hasActiveFilters = () => {
    const data = new FormData(form);
    for (const [name, value] of data.entries()) {
      const val = String(value).trim();
      if (name === 'estado') {
        if (val !== '' && val !== 'Pendiente') return true;
        continue;
      }
      if (val !== '') return true;
    }
    return false;
  };

  document.addEventListener('keydown', (e)=>{
    if(e.key !== 'Escape') return;
    const menuOpen = document.querySelector('.estado-menu');
    if(menuOpen) return;
    if(!hasActiveFilters()) return;
    e.preventDefault();
    clearTimeout(submitTimer);
    window.location.href = 'accidente_listar.php';
  });
})();

document.querySelectorAll('.js-toggle-card').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const detail = document.getElementById(btn.getAttribute('aria-controls'));
    if(!detail) return;
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    btn.textContent = expanded ? '+' : '-';
    detail.hidden = expanded;
  });
});

document.addEventListener('keydown', (e)=>{
  if(e.key !== 'Escape') return;
  document.querySelectorAll('.js-toggle-card[aria-expanded="true"]').forEach(btn=>{
    const detail = document.getElementById(btn.getAttribute('aria-controls'));
    btn.setAttribute('aria-expanded', 'false');
    btn.textContent = '+';
    if(detail) detail.hidden = true;
  });
});

// MenÃº estilo badge para cambiar estado
(function(){
  let abierto = null;

  function cerrarMenu(){
    if (abierto){ abierto.remove(); abierto = null; document.removeEventListener('click', onClickFuera); }
  }
  function onClickFuera(e){
    if (abierto && !abierto.contains(e.target)) cerrarMenu();
  }

  document.querySelectorAll('.estado-badge').forEach(badge=>{
    badge.addEventListener('click', (e)=>{
      e.stopPropagation();
      cerrarMenu();

      const id = badge.dataset.id;
      const actual = badge.dataset.estado;

      const m = document.createElement('div');
      m.className = 'estado-menu';
      m.innerHTML = `
        <div class="opt opt-pendiente" data-val="Pendiente">Pendiente</div>
        <div class="opt opt-resuelto"  data-val="Resuelto">Resuelto</div>
        <div class="opt opt-dilig"     data-val="Con diligencias">Con diligencias</div>
      `;

      const r = badge.getBoundingClientRect();
      m.style.left = (r.left + window.scrollX) + 'px';
      m.style.top  = (r.bottom + window.scrollY + 6) + 'px';

      document.body.appendChild(m);
      abierto = m;
      setTimeout(()=>document.addEventListener('click', onClickFuera),0);

      [...m.querySelectorAll('.opt')].forEach(opt=>{
        if (opt.dataset.val === actual) opt.style.boxShadow = '0 0 0 2px rgba(99,102,241,.35)';

        opt.addEventListener('click', ()=>{
          const nuevo = opt.dataset.val;

          const fd = new FormData();
          fd.append('ajax','estado');
          fd.append('id', id);
          fd.append('estado', nuevo);

          fetch(location.pathname, { method:'POST', body:fd })
            .then(r=>r.json())
            .then(j=>{
              if(j.ok){
                badge.dataset.estado = nuevo;
                badge.textContent = nuevo;
                badge.classList.remove('estado-pendiente','estado-resuelto','estado-dilig');
                badge.classList.add(
                  nuevo==='Resuelto' ? 'estado-resuelto' :
                  (nuevo==='Con diligencias' ? 'estado-dilig' : 'estado-pendiente')
                );
              }else{
                alert(j.msg || 'No se pudo actualizar el estado');
              }
            })
            .finally(cerrarMenu);
        });
      });
    });
  });
})();

// Guardar Folder (1..10) al cambiar el select
document.querySelectorAll('.select-folder').forEach(sel=>{
  sel.addEventListener('change', ()=>{
    const id = sel.dataset.id;
    const val = sel.value;

    const fd = new FormData();
    fd.append('ajax','folder');
    fd.append('id', id);
    fd.append('folder', val);

    fetch(location.pathname, { method:'POST', body:fd })
      .then(r=>r.json())
      .then(j=>{
        if(!j.ok){
          alert(j.msg || 'No se pudo actualizar Folder');
        }
      })
      .catch(()=>alert('Error de red al guardar Folder'));
  });
});

// Toggle prioridad (estrella) - opciÃ³n C: subir si marca, bajar si desmarca
document.querySelectorAll('.col-folder .prio-btn').forEach(btn=>{
  btn.addEventListener('click', function(e){
    e.preventDefault();
    const id = this.dataset.id;
    const card = this.closest('.acc-card');
    const list = document.getElementById('cards-list');
    const tr = this.closest('tr');
    const tbody = document.getElementById('tbody-rows');

    const cur = this.dataset.priority === '1' ? 1 : 0;
    const nuevo = cur ? 0 : 1;

    const fd = new FormData();
    fd.append('ajax','priority');
    fd.append('id', id);
    fd.append('priority', nuevo);

    const star = this.querySelector('.star');

    // --- FEEDBACK INMEDIATO ---
    if (nuevo===1) {
      // Activar
      star.textContent='\u2605';
      star.classList.remove('star-off');
      star.classList.add('star-on');
      this.setAttribute('aria-pressed','true');
      this.dataset.priority='1';

      // Mover a la parte superior de la lista visible
      if (card && list) list.prepend(card);
      if (tr && tbody) tbody.prepend(tr);

    } else {
      // Desactivar
      star.textContent='\u2606';
      star.classList.remove('star-on');
      star.classList.add('star-off');
      this.setAttribute('aria-pressed','false');
      this.dataset.priority='0';

      // --- REUBICAR FILA SEGÃšN ORDEN (folder â†’ fecha) ---
      const folder = (card || tr)?.querySelector('.select-folder')?.value || '';
      const fecha = card?.dataset.date || tr?.children[3]?.dataset.sortValue || tr?.children[3]?.innerText.trim() || '';

      // Insertar segÃºn orden SQL: prioridad DESC, folder ASC, fecha DESC
      let insertado = false;

      // Recorremos las filas y buscamos el primer 'other' donde insertar antes
      const rows = list ? [...list.querySelectorAll('.acc-card')] : [...tbody.querySelectorAll('tr')];
      for (let other of rows) {
        if (insertado) break;
        if (other === card || other === tr) continue;

        const otherPrior = other.querySelector('.prio-btn')?.dataset.priority === '1';
        // Saltar filas prioritarias: siempre van arriba
        if (otherPrior) continue;

        const otherFolder = other.querySelector('.select-folder')?.value || '';
        const otherFecha = other.dataset?.date || other.children?.[3]?.dataset?.sortValue || other.children?.[3]?.innerText.trim() || '';

        // ComparaciÃ³n por folder (vacÃ­o = NULL â†’ va al final)
        const f1 = folder==='' ? 999 : parseInt(folder);
        const f2 = otherFolder==='' ? 999 : parseInt(otherFolder);

        if (f1 < f2) {
          if (card && list && other.classList?.contains('acc-card')) list.insertBefore(card, other);
          else if (tr && tbody) tbody.insertBefore(tr, other);
          insertado = true;
          break;
        }

        if (f1 === f2) {
          // Comparar fecha (mayor primero: fecha mÃ¡s reciente debe quedar antes)
          // Convertimos a string porque el formato en DB es YYYY-MM-DD hh:mm:ss y la comparaciÃ³n lexicogrÃ¡fica funciona
          if (fecha > otherFecha) {
            if (card && list && other.classList?.contains('acc-card')) list.insertBefore(card, other);
            else if (tr && tbody) tbody.insertBefore(tr, other);
            insertado = true;
            break;
          }
        }
      }

      // Si no se insertÃ³ en ninguna posiciÃ³n â†’ va al final
      if (!insertado && card && list) list.appendChild(card);
      if (!insertado && tr && tbody) tbody.appendChild(tr);
    }

    // --- GUARDAR EN BD ---
    fetch(location.pathname, { method:'POST', body:fd })
      .then(r=>r.json())
      .then(j=>{
        if(!j.ok){
          alert(j.msg || 'No se pudo actualizar prioridad');
          // No revertimos la posiciÃ³n por simplicidad, pero podrÃ­a revertirse si prefieres.
        }
      })
      .catch(()=>{
        alert('Error de red al guardar prioridad');
      });
  });
});

/* ---------- Ordenamiento por columna (client-side) ---------- */
(function(){
  function getCellText(row, colIndex){
    const cell = row.children[colIndex];
    if(!cell) return '';
    if(cell.dataset.sortValue) return cell.dataset.sortValue;
    // Si la celda contiene un input/select (ej: folder) preferimos su value
    const sel = cell.querySelector('select');
    if(sel) return sel.value === '' ? '' : sel.value;
    // Badge/registros: usar textContent
    return cell.textContent.trim();
  }

  function detectNumericSample(values){
    // Si mÃ¡s del 60% de valores parsean como nÃºmero => numÃ©rica
    let numCount = 0;
    let total = 0;
    for(const v of values){
      if(v==='') continue;
      total++;
      if(!isNaN(Number(v.replace(/[^\d\.\-]/g,'')))) numCount++;
    }
    if(total===0) return false;
    return (numCount/total) >= 0.6;
  }

  function compareValues(a,b, numeric, desc){
    if(numeric){
      const na = parseFloat(a.replace(/[^\d\.\-]/g,'')) || 0;
      const nb = parseFloat(b.replace(/[^\d\.\-]/g,'')) || 0;
      return desc ? (nb - na) : (na - nb);
    } else {
      // comparaciÃ³n lingÃ¼Ã­stica sensible
      const sa = a.toString().toLowerCase();
      const sb = b.toString().toLowerCase();
      if(sa < sb) return desc ? 1 : -1;
      if(sa > sb) return desc ? -1 : 1;
      return 0;
    }
  }

  function clearIndicators(ths){
    ths.forEach(th=> th.querySelector('.sort-indicator').textContent = '');
  }

  function initColumnSort(){
    const table = document.querySelector('.table-wrap table');
    if(!table) return;
    const thead = table.tHead;
    const tbody = table.tBodies[0];
    if(!thead || !tbody) return;

    const ths = [...thead.querySelectorAll('th')];
    ths.forEach((th, colIndex) => {
      // SÃ³lo aÃ±ado handler si tiene data-sort
      if(!th.dataset.sort) return;
      th.style.cursor = 'pointer';
      th.title = 'Ordenar por ' + (th.innerText || '').trim();
      th.addEventListener('click', function(e){
        // Determinar orden actual y alternar
        const cur = th.dataset.order === 'asc' ? 'asc' : (th.dataset.order === 'desc' ? 'desc' : null);
        const nuevo = cur === 'asc' ? 'desc' : 'asc';
        // recolectar valores de esa columna
        const rows = [...tbody.querySelectorAll('tr')];
        const sampleVals = rows.map(r => getCellText(r, colIndex));
        const numeric = detectNumericSample(sampleVals);

        // Crear array con [row, value] para ordenar
        const arr = rows.map(r => ({ row: r, val: getCellText(r, colIndex) }));

        arr.sort((A,B) => {
          return compareValues(A.val, B.val, numeric, nuevo === 'desc');
        });

        // Reinsertar en tbody segÃºn orden
        const frag = document.createDocumentFragment();
        arr.forEach(item => frag.appendChild(item.row));
        tbody.appendChild(frag);

        // Actualizar indicadores visuales
        ths.forEach(t => t.removeAttribute('data-order'));
        th.dataset.order = nuevo;
        clearIndicators(ths);
        th.querySelector('.sort-indicator').textContent = nuevo === 'asc' ? 'â–²' : 'â–¼';
      });
    });
  }

  // Inicializar tras carga completa del DOM (y tras tus otros handlers)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initColumnSort);
  } else {
    initColumnSort();
  }

})();
</script>
</body>
</html>
