<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

// --- asegurar $accidente_id antes de incluir el sidebar ---
$accidente_id = $_GET['accidente_id'] ?? $accidente_id ?? null;

// asegurar que $pdo (o $db) esté definido para que sidebar pueda usarlo
if (!isset($pdo) && isset($db) && $db instanceof PDO) $pdo = $db;

ini_set('display_errors', 0);
header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt($s){ return $s!==null && $s!=='' ? h($s) : '—'; }

function join_con_y(array $items){
  $items = array_values(array_filter(array_map(static fn($item) => preg_replace('/\s+/u', ' ', trim((string)$item)) ?? trim((string)$item), $items)));
  $n = count($items);
  if ($n===0) return '—';
  $esc = [];
  foreach($items as $index => $item){
    $esc[] = h(list_item_case($item, $index === 0));
  }
  if ($n===1) return $esc[0];
  if ($n===2) return $esc[0].' y '.$esc[1];
  return implode(', ', array_slice($esc, 0, $n-1)).' y '.$esc[$n-1];
}

function list_item_case(string $item, bool $capitalize = false){
  $item = preg_replace('/\s+/u', ' ', trim($item)) ?? trim($item);
  if($item === '') return '';
  $item = mb_strtolower($item, 'UTF-8');
  if(!$capitalize) return $item;
  return mb_strtoupper(mb_substr($item, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($item, 1, null, 'UTF-8');
}

function fechaCortaEsp($fechaRaw){
  if(!$fechaRaw || !strtotime($fechaRaw)) return '—';
  $t = strtotime($fechaRaw);
  $meses = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
  $dia = date('d',$t);
  $mes = $meses[(int)date('n',$t)-1];
  $anio = date('Y',$t);
  return $dia.$mes.$anio;
}
function fechaHoraCortaEsp($fechaRaw){
  if(!$fechaRaw || !strtotime($fechaRaw)) return '—';
  $t = strtotime($fechaRaw);
  $meses = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
  $dia = date('d',$t);
  $mes = $meses[(int)date('n',$t)-1];
  $anio = date('Y',$t);
  $hora = date('H:i',$t);
  return $dia.$mes.$anio.' '.$hora;
}

/* =========================
 *  Resolver accidente
 * =======================*/
$param_sidpol = trim($_GET['sidpol'] ?? '');
$param_id     = isset($_GET['accidente_id']) ? (int)$_GET['accidente_id'] : 0;

function getAccBySidpol(PDO $pdo, string $sidpol){
  $st=$pdo->prepare("SELECT * FROM accidentes WHERE sidpol=? LIMIT 1");
  $st->execute([$sidpol]);
  return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$acc = null;
if ($param_sidpol!=='') $acc = getAccBySidpol($pdo,$param_sidpol);
if (!$acc && $param_id>0){
  $st=$pdo->prepare("SELECT * FROM accidentes WHERE id=? LIMIT 1");
  $st->execute([$param_id]);
  $acc = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$acc){
  $acc = $pdo->query("SELECT * FROM accidentes ORDER BY sidpol DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$acc) { die('No hay accidentes registrados.'); }

$accidente_id = (int)$acc['id'];
$sidpol       = (string)$acc['sidpol'];

/* Prev/Next por SIDPOL */
$st=$pdo->prepare("SELECT sidpol FROM accidentes WHERE sidpol < ? ORDER BY sidpol DESC LIMIT 1");
$st->execute([$sidpol]); $prevSid = (string)($st->fetchColumn() ?: '');
$st=$pdo->prepare("SELECT sidpol FROM accidentes WHERE sidpol > ? ORDER BY sidpol ASC LIMIT 1");
$st->execute([$sidpol]); $nextSid = (string)($st->fetchColumn() ?: '');

/* =========================
 *  Datos enriquecidos
 * =======================*/
$sqlInfo = "
  SELECT a.*,
         d.nombre  AS dep_nom,
         p.nombre  AS prov_nom,
         t.nombre  AS dist_nom,
         c.nombre  AS comisaria_nom,
         fa.nombre AS fiscalia_nom,
         CONCAT(fi.nombres,' ',fi.apellido_paterno,' ',fi.apellido_materno) AS fiscal_nom
  FROM accidentes a
  LEFT JOIN ubigeo_departamento d ON d.cod_dep=a.cod_dep
  LEFT JOIN ubigeo_provincia  p ON p.cod_dep=a.cod_dep AND p.cod_prov=a.cod_prov
  LEFT JOIN ubigeo_distrito   t ON t.cod_dep=a.cod_dep AND t.cod_prov=a.cod_prov AND t.cod_dist=a.cod_dist
  LEFT JOIN comisarias        c ON c.id=a.comisaria_id
  LEFT JOIN fiscalia          fa ON fa.id=a.fiscalia_id
  LEFT JOIN fiscales          fi ON fi.id=a.fiscal_id
  WHERE a.id=?
";
$sti=$pdo->prepare($sqlInfo);
$sti->execute([$accidente_id]);
$A = $sti->fetch(PDO::FETCH_ASSOC) ?: $acc;

/* Ubicación concatenada */
$ubicacion = implode(' / ', array_values(array_filter([
  $A['dep_nom'] ?? '', $A['prov_nom'] ?? '', $A['dist_nom'] ?? ''
])));

/* ===== Modalidades y Consecuencias ===== */
function fetchListSafe(PDO $pdo, string $sql1, string $sql2, array $params){
  try{
    $st=$pdo->prepare($sql1); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC);
  }catch(Throwable $e){
    $st=$pdo->prepare($sql2); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC);
  }
}
$rowsMods = fetchListSafe(
  $pdo,
  "SELECT m.nombre FROM accidente_modalidad am
    JOIN modalidad_accidente m ON m.id=am.modalidad_id
   WHERE am.accidente_id=? ORDER BY am.id",
  "SELECT m.nombre FROM accidente_modalidad am
    JOIN modalidad_accidente m ON m.id=am.modalidad_id
   WHERE am.accidente_id=? ORDER BY am.modalidad_id",
  [$accidente_id]
);
$mods = array_column($rowsMods,'nombre');

$rowsCons = fetchListSafe(
  $pdo,
  "SELECT c.nombre FROM accidente_consecuencia ac
    JOIN consecuencia_accidente c ON c.id=ac.consecuencia_id
   WHERE ac.accidente_id=? ORDER BY ac.id",
  "SELECT c.nombre FROM accidente_consecuencia ac
    JOIN consecuencia_accidente c ON c.id=ac.consecuencia_id
   WHERE ac.accidente_id=? ORDER BY ac.consecuencia_id",
  [$accidente_id]
);
$cons = array_column($rowsCons,'nombre');

$mods_concat = join_con_y($mods);
$cons_concat = $cons ? implode(' → ', array_map('h',$cons)) : '—';

$cons_concat = join_con_y($cons);
/* ===== Datos para mensaje WhatsApp ===== */
$modalidad_txt = $mods_concat ?: '—';
$lugar_acc     = $A['lugar'] ?? '—';

$fechaHoraRaw = $A['fecha_accidente'] ?? null;
if ($fechaHoraRaw && strtotime($fechaHoraRaw)) {
  $fecha_acc = date('d/m/Y', strtotime($fechaHoraRaw));
  $hora_acc  = date('H:i',   strtotime($fechaHoraRaw));
} else {
  $fecha_acc = '—';
  $hora_acc  = '—';
}

/* =========================
 *  Vehículos y Personas
 * =======================*/
$stV = $pdo->prepare("
  SELECT iv.id AS inv_id, iv.tipo, iv.observaciones,
        iv.orden_participacion,
         v.id AS veh_id, v.placa, v.color, v.anio,
         tv.nombre AS tipo_vehiculo,
         cv.descripcion AS cat_vehiculo
  FROM involucrados_vehiculos iv
  JOIN vehiculos v ON v.id=iv.vehiculo_id
  LEFT JOIN tipos_vehiculo tv ON tv.id=v.tipo_id
  LEFT JOIN categoria_vehiculos cv ON cv.id=v.categoria_id
  WHERE iv.accidente_id=?
  ORDER BY FIELD(iv.orden_participacion,'UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7')
");
$stV->execute([$accidente_id]);
$vehiculos = $stV->fetchAll(PDO::FETCH_ASSOC);

$stPV = $pdo->prepare("
  SELECT ip.id AS inv_per_id, ip.rol_id, ip.lesion, ip.observaciones,
         p.id AS persona_id, p.nombres, p.apellido_paterno, p.apellido_materno,
         p.num_doc, p.edad, p.celular, p.email,
         pr.Nombre AS rol_nombre
  FROM involucrados_personas ip
  JOIN personas p ON p.id=ip.persona_id
  LEFT JOIN participacion_persona pr ON pr.Id=ip.rol_id
  WHERE ip.accidente_id=? AND ip.vehiculo_id=?
  ORDER BY pr.Orden, p.apellido_paterno, p.apellido_materno, p.nombres
");

$stPSV = $pdo->prepare("
  SELECT ip.id AS inv_per_id, ip.rol_id, ip.lesion, ip.observaciones,
         p.id AS persona_id, p.nombres, p.apellido_paterno, p.apellido_materno,
         p.num_doc, p.edad, p.celular, p.email,
         pr.Nombre AS rol_nombre
  FROM involucrados_personas ip
  JOIN personas p ON p.id=ip.persona_id
  LEFT JOIN participacion_persona pr ON pr.Id=ip.rol_id
  WHERE ip.accidente_id=? AND (ip.vehiculo_id IS NULL OR ip.vehiculo_id=0)
  ORDER BY pr.Orden, p.apellido_paterno, p.apellido_materno, p.nombres
");
$stPSV->execute([$accidente_id]);
$personas_sin_veh = $stPSV->fetchAll(PDO::FETCH_ASSOC);

/* =========================
 *  Colores Estado
 * =======================*/
$estadoClass = 'st-orange';
if (strcasecmp(trim($A['estado']??''),'Pendiente')===0) $estadoClass = 'st-red';
elseif (strcasecmp(trim($A['estado']??''),'Resuelto')===0) $estadoClass = 'st-green';

/* ===== Helper conteo seguro ===== */
function countRowsSafe(PDO $pdo, array $sqlAlternas, array $params){
  foreach($sqlAlternas as $sql){
    try{
      $st=$pdo->prepare($sql); $st->execute($params);
      return (int)$st->fetchColumn();
    }catch(Throwable $e){ }
  }
  return 0;
}

/* ====== Conteos para “LED” ====== */
$cntItp = (int)$pdo->query("SELECT COUNT(*) FROM itp WHERE accidente_id=".(int)$accidente_id)->fetchColumn();
$cntVeh = is_array($vehiculos) ? count($vehiculos) : 0;

$cntPer = countRowsSafe($pdo, [
  "SELECT COUNT(*) FROM involucrados_personas WHERE accidente_id=?"
], [$accidente_id]);

$cntEfec = countRowsSafe($pdo, [
  "SELECT COUNT(*) FROM policial_interviniente WHERE accidente_id=?",
  "SELECT COUNT(*) FROM efectivos_intervinientes WHERE accidente_id=?",
  "SELECT COUNT(*) FROM efectivos_policiales WHERE accidente_id=?"
], [$accidente_id]);

$cntFam = countRowsSafe($pdo, [
  "SELECT COUNT(*) FROM familiar_fallecido WHERE accidente_id=?",
  "SELECT COUNT(*) FROM familiares_fallecidos WHERE accidente_id=?"
], [$accidente_id]);

$cntProp = countRowsSafe($pdo, [
  "SELECT COUNT(*) FROM propietario_vehiculo WHERE accidente_id=?",
  "SELECT COUNT(*) FROM propietarios_vehiculo WHERE accidente_id=?"
], [$accidente_id]);

$cntAbog = countRowsSafe($pdo, [
  "SELECT COUNT(*) FROM abogado WHERE accidente_id=?",
  "SELECT COUNT(*) FROM abogados WHERE accidente_id=?"
], [$accidente_id]);

$cntOfi = countRowsSafe($pdo, [
  "SELECT COUNT(*) FROM oficios WHERE accidente_id=?",
  "SELECT COUNT(*) FROM oficio WHERE accidente_id=?"
], [$accidente_id]);

$cntCit = countRowsSafe($pdo, [
  "SELECT COUNT(*) FROM citacion WHERE accidente_id=?",
  "SELECT COUNT(*) FROM citaciones WHERE accidente_id=?"
], [$accidente_id]);

// Diligencias pendientes (si aún no tienes tabla/consulta, queda 0)
$cntDili = 0;
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Datos Generales del Accidente</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{
    color-scheme: light dark;
    --bg:#0f1216; --surface:#141922; --line:#232936; --ink:#e9edf3; --muted:#9aa4b2;
    --chip:#1b2130; --brand:#7aa2ff; --ok:#10b981; --bad:#ef4444; --warn:#f59e0b;
    --fs:13px; --r:10px; --pad:6px;
  }
  @media (prefers-color-scheme: light){
    :root{ --bg:#f6f8fb; --surface:#ffffff; --line:#e5e7eb; --ink:#0f172a; --muted:#64748b; --chip:#f3f4f6;
           --ok:#15803d; --bad:#b91c1c; --warn:#b45309; }
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font:var(--fs)/1.4 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto;}
  .wrap{max-width:1180px;margin:16px auto;padding:0 12px}

  .hdr{display:flex;gap:8px;align-items:center;justify-content:space-between;margin-bottom:8px}
  .ttl{font-weight:900;letter-spacing:.2px}
  .badge{border:1px solid var(--line);background:var(--chip);border-radius:999px;padding:2px 8px;font-weight:800;color:var(--muted)}
  .bar{display:flex;gap:6px;align-items:center}
  .btn{padding:6px 10px;border:1px solid var(--line);background:var(--surface);border-radius:8px;color:inherit;text-decoration:none;font-weight:700}
  .btn.icon{padding:6px 8px}
  .in{height:34px;padding:6px 10px;border:1px solid var(--line);border-radius:8px;background:var(--chip);color:inherit;width:120px;text-align:center}

  .card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:6px;margin-bottom:2px}
  .sec{margin-top:2px}
  .st{font-weight:800;color:var(--muted);margin:0 0 6px 0;font-size:11.5px}

  .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:6px}
  .f{grid-column:span 3;min-width:0;background:var(--chip);border:1px solid var(--line);border-radius:var(--r);padding:var(--pad)}
  .f .l{font-size:11px;color:var(--muted);margin-bottom:2px;font-weight:800}
  .f .v{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .s2{grid-column:span 2}.s3{grid-column:span 3}.s4{grid-column:span 4}.s6{grid-column:span 6}.s12{grid-column:span 12}
  @media (max-width:980px){ .f{grid-column:span 6} }
  @media (max-width:640px){ .f{grid-column:span 12} }

  .st-red .v{color:var(--bad)}
  .st-green .v{color:var(--ok)}
  .st-orange .v{color:var(--warn)}

  .chips{display:flex;flex-wrap:wrap;gap:6px}
  .chip{background:var(--surface);border:1px solid var(--line);border-radius:999px;padding:2px 8px;font-size:11.5px}

  .vlist{display:grid;gap:6px}
  .vcard{border:1px solid var(--line);background:var(--chip);border-radius:10px;padding:8px}
  .vh{display:flex;justify-content:space-between;gap:8px;align-items:center}
  .vtit{font-weight:900}
  .plist{display:grid;gap:6px;margin-top:6px}
  .pcard{display:grid;grid-template-columns:1fr auto;gap:6px;align-items:center;border:1px solid var(--line);border-radius:8px;background:var(--surface);padding:8px}
  .pname{font-weight:800}
  .pmeta{color:var(--muted)}
  .oj,.ojv,.ojd{display:inline-grid;place-items:center;width:32px;height:32px;border:1px solid var(--line);border-radius:8px;background:var(--chip);cursor:pointer}

  .modal-xl{position:fixed;inset:0;background:#0009;display:none;align-items:center;justify-content:center;padding:16px;z-index:1000}
  .modal-xl.open{display:flex}
  .modal-xl .box{width:min(1100px,95vw);background:var(--surface);border:1px solid var(--line);border-radius:12px;overflow:hidden}
  .modalbar{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid var(--line);background:var(--chip)}
  .ifwrap{position:relative;width:100%;height:min(82vh,860px)}
  .ifwrap iframe{position:absolute;inset:0;width:100%;height:100%;border:0;background:#000}

  .summary{display:grid;grid-template-columns:repeat(12,1fr);gap:6px}
  .pill{grid-column:span 12;border:1px dashed var(--line);border-radius:10px;padding:8px;background:var(--chip)}
  .pill .k{font-weight:900;margin-right:18px;color:var(--muted)}
  .mono{font-variant-numeric:tabular-nums}

  .oj.whatsapp { color:#25D366; border-color:#25D366; }
  .oj.whatsapp:hover { background:#25D36622; }

  .f.sidpol-registro {
    border-color: #d4af37 !important;
    background: var(--surface);
    text-align: center;
  }
  .f.sidpol-registro .l {
    color: #d4af37;
    font-weight: 900;
    text-transform: uppercase;
  }
  .f.sidpol-registro .v {
    color: #facc15;
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 0.5px;
  }

  .copyable{ cursor: copy; }
  .copy-toast{
    position:fixed; right:20px; bottom:20px;
    background:var(--brand); color:#fff; padding:6px 12px;
    border-radius:6px; font-size:12px; opacity:0; transition:opacity .25s;
    z-index:9999;
  }
  .copy-toast.show{ opacity:1; }

  /* Badge especial para UT */
  .vtit {
    font-weight: 900;
    font-size: 15px;
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .vtit .badge.ut{
    background: linear-gradient(145deg,#2563eb,#3b82f6) !important;
    color:#fff !important;
    border:none !important;
    box-shadow:0 0 6px rgba(59,130,246,.5);
    font-weight:800;
  }
  .vtit .placa-highlight {
    color: #facc15;
    text-shadow: 0 0 4px #facc1580;
  }
  @media (prefers-color-scheme: light) {
    .vtit .placa-highlight {
      color: #b45309;
      text-shadow: 0 0 4px #b4530980;
    }
  }

  .f.centered { display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; }
  .f.centered .l { font-weight:800; color:var(--muted); text-transform:uppercase; font-size:11px; margin-bottom:4px; }
  .f.centered .v { font-weight:900; font-size:14px; }

  .btn-efectivo {
    background: linear-gradient(145deg, #16a34a, #22c55e);
    color: #fff;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 50px;
    box-shadow: 0 2px 8px rgba(34,197,94,.4);
    text-decoration: none;
    transition: all .25s ease;
    border: none;
  }
  .btn-efectivo:hover { background: linear-gradient(145deg, #15803d, #16a34a); box-shadow:0 0 12px rgba(22,163,74,.6); transform:translateY(-1px); }
  .btn-efectivo:active { transform: scale(.97); }

  .btn-fallecido {
    background: linear-gradient(145deg, #dc2626, #f87171);
    color: #fff;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 50px;
    box-shadow: 0 2px 8px rgba(239,68,68,.4);
    text-decoration: none;
    transition: all .25s ease;
    border: none;
  }
  .btn-fallecido:hover { background: linear-gradient(145deg, #b91c1c, #ef4444); box-shadow:0 0 12px rgba(239,68,68,.6); transform:translateY(-1px); }
  .btn-fallecido:active { transform: scale(.97); }

  .btn-propietario {
    background: linear-gradient(145deg, #facc15, #fbbf24);
    color: #000;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 50px;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(234,179,8,.35);
    border: none;
    transition: all .25s ease;
  }
  .btn-propietario:hover { background: linear-gradient(145deg, #eab308, #facc15); box-shadow:0 0 12px rgba(234,179,8,.55); transform:translateY(-1px); }
  .btn-propietario:active { transform: scale(.97); }

  .btn-oficio {
    background: linear-gradient(145deg, #38bdf8, #0ea5e9);
    color: #fff;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 50px;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(14,165,233,.4);
    border: none;
    transition: all .25s ease;
  }
  .btn-oficio:hover { background: linear-gradient(145deg, #0ea5e9, #38bdf8); box-shadow:0 0 12px rgba(14,165,233,.6); transform:translateY(-1px); }
  .btn-oficio:active { transform: scale(.97); }

  .btn-citacion {
    background: #f3e8ff;
    border: 1px solid #e0c9ff;
    color: #6b21a8;
    font-weight: 700;
    border-radius: 10px;
    padding: 6px 10px;
    text-decoration: none;
  }
  .btn-citacion:hover { background:#e9d5ff; }

  .btn-informe{
    display:inline-flex; align-items:center; gap:.5rem;
    padding:.5rem .8rem; border-radius:.5rem;
    background:#1e88e5; color:#fff; text-decoration:none; font-weight:600;
    box-shadow:0 2px 6px rgba(0,0,0,.12); transition:transform .05s ease, filter .15s ease;
    border:none;
  }
  .btn-informe:hover{ filter:brightness(1.05); }
  .btn-informe:active{ transform:translateY(1px); }

  .btn-vehiculo {
    background: linear-gradient(145deg, #2563eb, #3b82f6);
    color: #fff;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 50px;
    box-shadow: 0 2px 8px rgba(59,130,246,.4);
    text-decoration: none;
    border: none;
    transition: all .25s ease;
  }
  .btn-vehiculo:hover { background: linear-gradient(145deg, #3b82f6, #2563eb); box-shadow: 0 0 12px rgba(59,130,246,.6); transform: translateY(-1px); }
  .btn-vehiculo:active { transform: scale(.97); }

  .btn-persona {
    background: linear-gradient(145deg, #9333ea, #a855f7);
    color: #fff;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 50px;
    box-shadow: 0 2px 8px rgba(168,85,247,.4);
    text-decoration: none;
    border: none;
    transition: all .25s ease;
  }
  .btn-persona:hover { background: linear-gradient(145deg, #7e22ce, #9333ea); box-shadow: 0 0 12px rgba(147,51,234,.6); transform: translateY(-1px); }
  .btn-persona:active { transform: scale(.97); }

  .btn-abogado{
    background: linear-gradient(145deg,#0ea5e9,#0284c7);
    color:#fff; font-weight:800; padding:6px 14px; border-radius:50px;
    text-decoration:none; border:none; box-shadow:0 2px 8px rgba(2,132,199,.4);
    transition:all .25s ease;
  }
  .btn-abogado:hover{ background:linear-gradient(145deg,#0284c7,#0ea5e9); box-shadow:0 0 12px rgba(2,132,199,.6); transform:translateY(-1px); }
  .btn-abogado:active{ transform:scale(.97); }

  .fechas-linea {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    font-weight: 800;
    color: var(--ink);
    background: var(--chip);
    border: 1px solid var(--line);
    border-radius: var(--r);
    padding: var(--pad) 12px;
  }
  .fechas-linea strong { color: #d4af37; font-weight: 900; letter-spacing: .3px; }
  @media (max-width: 680px) {
    .fechas-linea { flex-direction: column; align-items: flex-start; gap: 4px; }
  }

  .volver-panel { display:flex; justify-content:center; margin: 12px 0 18px 0; }
  .btn-volver-panel {
    background: linear-gradient(145deg, #facc15, #fbbf24);
    color: #111;
    font-weight: 900;
    padding: 10px 22px;
    border-radius: 50px;
    text-decoration: none;
    font-size: 15px;
    box-shadow: 0 2px 10px rgba(234,179,8,.4);
    border: none;
    transition: all .25s ease;
  }
  .btn-volver-panel:hover { background: linear-gradient(145deg, #eab308, #facc15); box-shadow: 0 0 12px rgba(234,179,8,.6); transform: translateY(-1px); }
  .btn-volver-panel:active { transform: scale(.97); }

  /* LED dentro de botones */
  .btn, .btn-itp, .btn-efectivo, .btn-fallecido, .btn-propietario, .btn-oficio,
  .btn-citacion, .btn-informe, .btn-vehiculo, .btn-persona, .btn-abogado {
    position: relative;
    padding-right: 18px;
  }
  .led {
    position: absolute; right: 6px; top: 6px;
    width: 10px; height: 10px; border-radius: 50%;
    background: #9aa4b2;
    box-shadow: 0 0 0 1px var(--line) inset;
    opacity: .9;
  }
  .led.on {
    background: #22c55e;
    box-shadow: 0 0 8px #22c55e, 0 0 0 1px #14532d inset;
  }

  .btn-itp {
    display:inline-flex;
    align-items:center;
    gap:6px;
    margin:4px 6px;
    padding:7px 14px;
    border-radius:999px;
    background:linear-gradient(145deg,#2563eb,#3b82f6);
    color:#fff;
    font-weight:800;
    font-size:13px;
    text-decoration:none;
    box-shadow:0 2px 8px rgba(37,99,235,.45);
    transition:all .2s ease;
    border:none;
  }
  .btn-itp:hover { background:linear-gradient(145deg,#1d4ed8,#2563eb); transform:translateY(-1px); }
  .btn-itp:active { transform:scale(.97); }

  /* ======= MODIFICACIÓN: STICKY DEL ENCABEZADO ======= */
  .sticky-top-uiat{
    position: sticky;
    top: 0;
    z-index: 1500;
    background: var(--bg);
    padding-bottom: 8px;
    border-bottom: 1px solid var(--line);
  }

  /* ======= MODIFICACIÓN: MÓDULOS COMO PASTILLAS (PESTAÑAS) ======= */
  .mod-tabs .modbar{ display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
  .mod-tabs .tabbtn{ cursor:pointer; border:0; appearance:none; }
  .mod-tabs .tabbtn.active{
    outline:2px solid rgba(122,162,255,.45);
    outline-offset:2px;
    box-shadow:0 0 14px rgba(122,162,255,.28);
  }
  .mod-tabs .tabpanel{
    display:none;
    margin-top:10px;
    border:1px solid var(--line);
    background:var(--surface);
    border-radius:12px;
    padding:8px;
  }
  .mod-tabs .tabpanel.active{ display:block; }
  .mod-tabs iframe{
    width:100%;
    height:min(76vh, 920px);
    border:0;
    border-radius:10px;
    background:var(--chip);
  }

</style>
</head>
<body>
<div class="wrap">

<div class="volver-panel">
  <a href="accidente_listar.php" class="btn-volver-panel">⬅️ Volver al listado</a>
</div>


<!-- =======================
     ENCABEZADO STICKY (COMPLETO)
======================= -->
<div class="sticky-top-uiat">

  <div class="card">
    <div class="summary">
      <div class="pill mono"><span class="k">Modalidades:</span> <?= $mods_concat ?></div>
      <div class="pill mono"><span class="k">Consecuencias:</span> <?= $cons_concat ?></div>
    </div>
  </div>

  <div class="card">
    <div class="sec">
      <p class="st">Identificación</p>
      <div class="grid">
        <div class="f s3 sidpol-registro">
          <div class="l">Registro SIDPOL</div>
          <div class="v"><?=fmt($A['registro_sidpol'])?></div>
        </div>

        <div class="f s3 centered <?=$estadoClass?>">
          <div class="l">Estado</div>
          <div class="v"><?=fmt($A['estado'])?></div>
        </div>

        <div class="f s3 centered">
          <div class="l">N° informe policial</div>
          <div class="v"><?=fmt($A['nro_informe_policial'])?></div>
        </div>

        <div class="f s3">
          <div class="l">Comisaría</div>
          <div class="v"><?=fmt($A['comisaria_nom'])?></div>
        </div>
      </div>
    </div>

    <div class="sec">
      <p class="st">Fechas</p>
      <div class="f s12 fechas-linea">
        <div><strong>Accidente:</strong> <?=fechaHoraCortaEsp($A['fecha_accidente'])?></div>
        <div><strong>Comunicación:</strong> <?=fechaHoraCortaEsp($A['fecha_comunicacion'])?></div>
        <div><strong>Intervención:</strong> <?=fechaHoraCortaEsp($A['fecha_intervencion'])?></div>
      </div>
    </div>

    <div class="sec">
      <p class="st">Ubicación</p>
      <div class="grid">
        <div class="f s4">
          <div class="l">Lugar</div>
          <div class="v copyable" id="lugarNom" title="Click para copiar"><?=fmt($A['lugar'])?></div>
        </div>
        <div class="f s4">
          <div class="l">Ubicación</div>
          <div class="v"><?= $ubicacion ?: '—' ?></div>
        </div>
        <div class="f s4">
          <div class="l">Referencia</div>
          <div class="v"><?=fmt($A['referencia'])?></div>
        </div>
      </div>
    </div>

    <div class="sec">
      <p class="st">Autoridades</p>
      <div class="grid">
        <div class="f s6">
          <div class="l">Fiscalía</div>
          <div class="v copyable" id="fiscaliaNom" title="Click para copiar"><?=fmt($A['fiscalia_nom'])?></div>
        </div>
        <div class="f s6">
          <div class="l">Fiscal a cargo</div>
          <div class="v"><?=fmt($A['fiscal_nom'])?></div>
        </div>
      </div>
    </div>

    <div class="sec">
      <p class="st">Comunicación</p>
      <div class="grid">
        <div class="f s4"><div class="l">Comunicante</div><div class="v"><?=fmt($A['comunicante_nombre'])?></div></div>
        <div class="f s4"><div class="l">Tel. comunicante</div><div class="v"><?=fmt($A['comunicante_telefono'])?></div></div>
        <div class="f s4"><div class="l">Decreto</div><div class="v"><?=fmt($A['comunicacion_decreto'] ?? '')?></div></div>
        <div class="f s6"><div class="l">Oficio</div><div class="v"><?=fmt($A['comunicacion_oficio'] ?? '')?></div></div>
        <div class="f s6"><div class="l">Carpeta N°</div><div class="v"><?=fmt($A['comunicacion_carpeta_nro'] ?? '')?></div></div>
      </div>
    </div>

    <div class="sec">
      <p class="st">Descripción</p>
      <div class="grid">
        <div class="f s6"><div class="l">Sentido / Dirección</div><div class="v"><?=fmt($A['sentido'])?></div></div>
        <div class="f s6"><div class="l">Secuencia de eventos</div><div class="v" id="secuenciaEv" title="<?=h($A['secuencia'] ?? '')?>"><?=fmt($A['secuencia'])?></div></div>
      </div>
    </div>
  </div>

</div>
<!-- =======================
     FIN ENCABEZADO STICKY
======================= -->

<!-- Participantes -->
<div class="card">
  <div class="sec">
    <p class="st">Participantes — Vehículos y Personas</p>

    <?php if($vehiculos): ?>
    <div class="vlist">
      <?php foreach($vehiculos as $v):
        $stPV->execute([$accidente_id, $v['veh_id']]);
        $pers = $stPV->fetchAll(PDO::FETCH_ASSOC);
      ?>
        <div class="vcard">
          <div class="vh">
            <div class="vtit">
              <?php if (!empty($v['orden_participacion'])): ?>
                <span class="badge ut"><?= h($v['orden_participacion']) ?></span>
              <?php endif; ?>
              <span class="placa-highlight"><?= h($v['placa']) ?></span>

              <a class="ojv" style="margin-left:6px"
                 href="involucrados_vehiculos_editar.php?id=<?= (int)$v['inv_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                 title="Editar involucrado – vehículo">✏️</a>
            </div>

            <div class="chips">
              <?php if($v['tipo_vehiculo']): ?><span class="chip">Tipo: <?=h($v['tipo_vehiculo'])?></span><?php endif; ?>
              <?php if($v['cat_vehiculo']): ?><span class="chip">Cat: <?=h($v['cat_vehiculo'])?></span><?php endif; ?>
              <?php if($v['anio']): ?><span class="chip">Año: <?=h($v['anio'])?></span><?php endif; ?>
              <?php if($v['color']): ?><span class="chip">Color: <?=h($v['color'])?></span><?php endif; ?>
              <span class="chip">Participación: <?=h($v['tipo'])?></span>
            </div>

            <button class="ojv" type="button"
                    data-src="vehiculo_leer.php?id=<?= (int)$v['veh_id'] ?>&embed=1"
                    title="Ver ficha de vehículo">🚗</button>
          </div>

          <?php if($pers): ?>
            <div class="plist">
              <?php foreach($pers as $p):
                $full = trim(($p['apellido_paterno']??'').' '.($p['apellido_materno']??'').', '.($p['nombres']??''));
                $wa   = preg_replace('/\D+/','',$p['celular'] ?? '');
              ?>
              <div class="pcard">
                <div>
                  <div class="pname">
                    <?= h($full ?: '—') ?>
                    <?php
                      $lesion = strtolower(trim($p['lesion'] ?? ''));
                      if ($lesion !== '') {
                        if (strpos($lesion, 'ileso') !== false)
                          echo '<span class="estado-lesion estado-ileso">ILESO</span>';
                        elseif (strpos($lesion, 'herido') !== false)
                          echo '<span class="estado-lesion estado-herido">HERIDO</span>';
                        elseif (strpos($lesion, 'falle') !== false)
                          echo '<span class="estado-lesion estado-fallecido">FALLECIDO</span>';
                        else
                          echo '<span class="estado-lesion">'.h(strtoupper($lesion)).'</span>';
                      }
                    ?>
                  </div>
                  <div class="pmeta">
                    <?= h($p['rol_nombre'] ?: '—') ?> · DNI: <?= h($p['num_doc'] ?: '—') ?> ·
                    Edad: <?= h($p['edad']!==null && $p['edad']!=='' ? $p['edad'] : '—') ?> ·
                    Cel: <?= h($p['celular'] ?: '—') ?> · Email: <?= h($p['email'] ?: '—') ?>
                  </div>
                </div>
                <div>
                  <button class="oj" type="button"
                          data-src="persona_leer.php?id=<?= (int)$p['persona_id'] ?>&embed=1"
                          title="Ver ficha de persona">👁</button>
                  <a class="oj"
                     href="involucrados_personas_editar.php?id=<?= (int)$p['inv_per_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                     title="Editar involucrado – persona">✏️</a>

                  <?php if($wa): ?>
                    <?php
                      $mensaje = "Buen día le saluda ST3.PNP Giancarlo MERINO SANCHO de la UIAT NORTE, a cargo de la investigación por el accidente de tránsito {$modalidad_txt}, suscitado el día {$fecha_acc} a horas {$hora_acc} en la {$lugar_acc}.";
                    ?>
                    <a class="oj whatsapp"
                       href="https://wa.me/<?= h($wa) ?>?text=<?= rawurlencode($mensaje) ?>"
                       target="_blank" rel="noopener"
                       title="Contactar por WhatsApp">📲</a>
                  <?php endif; ?>

                  <?php if (stripos((string)$p['rol_nombre'], 'conductor') !== false): ?>
                    <a class="ojd"
                       href="marcador_manifestacion_investigado.php?involucrado_id=<?= (int)$p['inv_per_id'] ?>&accidente_id=<?= (int)$accidente_id ?>&download=1"
                       title="Descargar manifestación de conductor">📝</a>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="chips"><span class="chip">Sin personas asociadas</span></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="chips"><span class="chip">No hay vehículos registrados.</span></div>
    <?php endif; ?>

    <?php if($personas_sin_veh): ?>
      <div class="sec" style="margin-top:8px">
        <p class="st">Personas sin vehículo</p>
        <div class="plist">
          <?php foreach($personas_sin_veh as $p):
            $full = trim(($p['apellido_paterno']??'').' '.($p['apellido_materno']??'').', '.($p['nombres']??''));
            $wa   = preg_replace('/\D+/','',$p['celular'] ?? '');
          ?>
          <div class="pcard">
            <div>
              <div class="pname">
                <?= h($full ?: '—') ?>
                <?php
                  $lesion = strtolower(trim($p['lesion'] ?? ''));
                  if ($lesion !== '') {
                    if (strpos($lesion, 'ileso') !== false)
                      echo '<span class="estado-lesion estado-ileso">ILESO</span>';
                    elseif (strpos($lesion, 'herido') !== false)
                      echo '<span class="estado-lesion estado-herido">HERIDO</span>';
                    elseif (strpos($lesion, 'falle') !== false)
                      echo '<span class="estado-lesion estado-fallecido">FALLECIDO</span>';
                    else
                      echo '<span class="estado-lesion">'.h(strtoupper($lesion)).'</span>';
                  }
                ?>
              </div>
              <div class="pmeta">
                <?= h($p['rol_nombre'] ?: '—') ?> · DNI: <?= h($p['num_doc'] ?: '—') ?> ·
                Edad: <?= h($p['edad']!==null && $p['edad']!=='' ? $p['edad'] : '—') ?> ·
                Cel: <?= h($p['celular'] ?: '—') ?> · Email: <?= h($p['email'] ?: '—') ?>
              </div>
            </div>
            <div>
              <button class="oj" type="button"
                      data-src="persona_leer.php?id=<?= (int)$p['persona_id'] ?>&embed=1"
                      title="Ver ficha de persona">👁</button>
              <a class="oj"
                 href="involucrados_personas_editar.php?id=<?= (int)$p['inv_per_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                 title="Editar involucrado – persona">✏️</a>

              <?php if($wa): ?>
                <?php
                  $mensaje = "Buen día le saluda ST3.PNP Giancarlo MERINO SANCHO de la UIAT NORTE, a cargo de la investigación por el accidente de tránsito {$modalidad_txt}, suscitado el día {$fecha_acc} a horas {$hora_acc} en la {$lugar_acc}.";
                ?>
                <a class="oj whatsapp"
                   href="https://wa.me/<?= h($wa) ?>?text=<?= rawurlencode($mensaje) ?>"
                   target="_blank" rel="noopener"
                   title="Contactar por WhatsApp">📲</a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- =======================
     NUEVO: MÓDULOS COMO PASTILLAS (PESTAÑAS)
======================= -->
<div class="card mod-tabs">
  <div class="sec">
    <p class="st">Módulos</p>

    <div class="modbar" id="uiatModBar">
      <button class="tabbtn btn-fallecido active" type="button" data-tab="fam">
        💀 Familiar Fallecido
        <span class="led <?= $cntFam>0 ? 'on' : '' ?>"></span>
      </button>

      <button class="tabbtn btn-propietario" type="button" data-tab="prop">
        🚘 Propietario Vehículo
        <span class="led <?= $cntProp>0 ? 'on' : '' ?>"></span>
      </button>

      <button class="tabbtn btn-abogado" type="button" data-tab="abog">
        ⚖️ Abogados
        <span class="led <?= $cntAbog>0 ? 'on' : '' ?>"></span>
      </button>

      <button class="tabbtn btn-oficio" type="button" data-tab="ofi">
        📄 Oficios
        <span class="led <?= $cntOfi>0 ? 'on' : '' ?>"></span>
      </button>

      <button class="tabbtn btn-citacion" type="button" data-tab="cit">
        📅 Citaciones
        <span class="led <?= $cntCit>0 ? 'on' : '' ?>"></span>
      </button>

      <button class="tabbtn btn-itp" type="button" data-tab="itp">
        🧾 ITP
        <span class="led <?= $cntItp>0 ? 'on' : '' ?>"></span>
      </button>

      <button class="tabbtn btn-informe" type="button" data-tab="dil">
        📑 Diligencias Pendientes
        <span class="led <?= $cntDili>0 ? 'on' : '' ?>"></span>
      </button>
    </div>

    <div class="tabpanel active" id="tab-fam">
      <iframe data-src="familiar_fallecido_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
    </div>

    <div class="tabpanel" id="tab-prop">
      <iframe data-src="propietario_vehiculo_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
    </div>

    <div class="tabpanel" id="tab-abog">
      <iframe data-src="abogado_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
    </div>

    <div class="tabpanel" id="tab-ofi">
      <iframe data-src="oficios_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
    </div>

    <div class="tabpanel" id="tab-cit">
      <iframe data-src="citacion_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
    </div>

    <div class="tabpanel" id="tab-itp">
      <iframe data-src="itp_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
    </div>

    <div class="tabpanel" id="tab-dil">
      <!-- Cambia el nombre si tu archivo real se llama distinto -->
      <iframe data-src="diligencias_pendientes_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
    </div>

  </div>
</div>

<div class="mut" style="color:var(--muted);font-size:11px">UIAT Norte · <?= date('Y-m-d H:i') ?></div>
</div>

<!-- MODALES -->
<div class="modal-xl" id="mdPersona">
  <div class="box">
    <div class="modalbar">
      <div class="ttl">Ficha de persona</div>
      <button class="btn icon" type="button" id="btnCloseMdPer">✕</button>
    </div>
    <div class="ifwrap"><iframe id="ifPersona" src="about:blank" loading="lazy"></iframe></div>
  </div>
</div>

<div class="modal-xl" id="mdVehiculo">
  <div class="box">
    <div class="modalbar">
      <div class="ttl">Ficha de vehículo</div>
      <button class="btn icon" type="button" id="btnCloseMdVeh">✕</button>
    </div>
    <div class="ifwrap"><iframe id="ifVehiculo" src="about:blank" loading="lazy"></iframe></div>
  </div>
</div>

<div class="modal-xl" id="mdDili">
  <div class="box">
    <div class="modalbar">
      <div class="ttl">Diligencias del conductor</div>
      <button class="btn icon" type="button" id="btnCloseMdDili">✕</button>
    </div>
    <div class="ifwrap"><iframe id="ifDili" src="about:blank" loading="lazy"></iframe></div>
  </div>
</div>

<script>
document.addEventListener('keydown', (e)=>{
  const prev=<?= $prevSid!=='' ? json_encode($prevSid) : 'null' ?>;
  const next=<?= $nextSid!=='' ? json_encode($nextSid) : 'null' ?>;
  if(e.key==='ArrowLeft' && prev){ location='?sidpol='+encodeURIComponent(prev); }
  if(e.key==='ArrowRight' && next){ location='?sidpol='+encodeURIComponent(next); }
});

/* PERSONA modal */
function openPersona(src){
  const m=document.getElementById('mdPersona'); const ifr=document.getElementById('ifPersona');
  if(!m||!ifr||!src) return; ifr.src=src; m.classList.add('open');
}
function closePersona(){
  const m=document.getElementById('mdPersona'); const ifr=document.getElementById('ifPersona');
  if(!m||!ifr) return; ifr.src='about:blank'; m.classList.remove('open');
}
document.querySelectorAll('.oj[data-src]').forEach(btn=>{ btn.addEventListener('click',()=>openPersona(btn.dataset.src)); });
document.getElementById('btnCloseMdPer').addEventListener('click', closePersona);
document.getElementById('mdPersona').addEventListener('click', (e)=>{ if(e.target.id==='mdPersona') closePersona(); });

/* VEHÍCULO modal */
function openVehiculo(src){
  const m=document.getElementById('mdVehiculo'); const ifr=document.getElementById('ifVehiculo');
  if(!m||!ifr||!src) return; ifr.src=src; m.classList.add('open');
}
function closeVehiculo(){
  const m=document.getElementById('mdVehiculo'); const ifr=document.getElementById('ifVehiculo');
  if(!m||!ifr) return; ifr.src='about:blank'; m.classList.remove('open');
}
document.querySelectorAll('.ojv[data-src]').forEach(btn=>{ btn.addEventListener('click',()=>openVehiculo(btn.dataset.src)); });
document.getElementById('btnCloseMdVeh').addEventListener('click', closeVehiculo);
document.getElementById('mdVehiculo').addEventListener('click', (e)=>{ if(e.target.id==='mdVehiculo') closeVehiculo(); });

/* DILIGENCIAS modal */
function openDili(src){
  const m=document.getElementById('mdDili'); const ifr=document.getElementById('ifDili');
  if(!m||!ifr||!src) return; ifr.src=src; m.classList.add('open');
}
function closeDili(){
  const m=document.getElementById('mdDili'); const ifr=document.getElementById('ifDili');
  if(!m||!ifr) return; ifr.src='about:blank'; m.classList.remove('open');
}
document.querySelectorAll('.ojd[data-src]').forEach(btn=>{ btn.addEventListener('click',()=>openDili(btn.dataset.src)); });
document.getElementById('btnCloseMdDili').addEventListener('click', closeDili);
document.getElementById('mdDili').addEventListener('click', (e)=>{ if(e.target.id==='mdDili') closeDili(); });

/* ====== NUEVO: pestañas pastilla (módulos) con lazy-load + recordar ====== */
(function(){
  const bar = document.getElementById('uiatModBar');
  if(!bar) return;

  const key = 'uiat_mod_tab';
  const btns = bar.querySelectorAll('.tabbtn');

  const panels = {
    fam: document.getElementById('tab-fam'),
    prop: document.getElementById('tab-prop'),
    abog: document.getElementById('tab-abog'),
    ofi: document.getElementById('tab-ofi'),
    cit: document.getElementById('tab-cit'),
    itp: document.getElementById('tab-itp'),
    dil: document.getElementById('tab-dil'),
  };

  function ensureIframe(panel){
    const ifr = panel?.querySelector('iframe[data-src]');
    if(ifr && !ifr.src) ifr.src = ifr.getAttribute('data-src');
  }

  function activate(name){
    btns.forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    Object.entries(panels).forEach(([k,p]) => p && p.classList.toggle('active', k === name));
    ensureIframe(panels[name]);
    localStorage.setItem(key, name);
  }

  btns.forEach(btn => btn.addEventListener('click', ()=> activate(btn.dataset.tab)));

  const saved = localStorage.getItem(key);
  if(saved && panels[saved]) activate(saved);
  else ensureIframe(panels.fam);
})();
</script>

<div id="copyToast" class="copy-toast">Copiado ✅</div>
<script>
(function(){
  const toast = document.getElementById('copyToast');

  function showToast(){
    toast.classList.add('show');
    setTimeout(()=>toast.classList.remove('show'), 1400);
  }

  async function copyTextFrom(el){
    const txt = el.innerText.trim();
    if(!txt) return;
    try{ await navigator.clipboard.writeText(txt); showToast(); }
    catch(e){
      const ta = document.createElement('textarea');
      ta.value = txt; document.body.appendChild(ta);
      ta.select(); document.execCommand('copy'); ta.remove();
      showToast();
    }
  }

  const f1 = document.getElementById('fiscaliaNom');
  if(f1) f1.addEventListener('click', ()=>copyTextFrom(f1));

  const f2 = document.getElementById('lugarNom');
  if(f2) f2.addEventListener('click', ()=>copyTextFrom(f2));
})();
</script>

</body>
</html>
