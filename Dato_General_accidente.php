<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

// --- asegurar $accidente_id antes de incluir el sidebar ---
$accidente_id = $_GET['accidente_id'] ?? $accidente_id ?? null;

// asegurar que $pdo (o $db) esté definido para que sidebar pueda usarlo
if (!isset($pdo) && isset($db) && $db instanceof PDO) $pdo = $db;


// ... el resto de tu código sigue aquí ...
ini_set('display_errors', 0);
header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt($s){ return $s!==null && $s!=='' ? h($s) : '—'; }

function join_con_y(array $items){
  $n = count($items);
  if ($n===0) return '—';
  $esc = array_map('h', $items);
  if ($n===1) return $esc[0];
  if ($n===2) return $esc[0].' y '.$esc[1];
  return implode(', ', array_slice($esc, 0, $n-1)).' y '.$esc[$n-1];
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



/* ===== Helper conteo seguro (no rompe si cambia el nombre de la tabla) ===== */
function countRowsSafe(PDO $pdo, array $sqlAlternas, array $params){
  foreach($sqlAlternas as $sql){
    try{
      $st=$pdo->prepare($sql); $st->execute($params);
      $c=(int)$st->fetchColumn();
      return $c;
    }catch(Throwable $e){ /* intenta con la siguiente */ }
  }
  return 0;
}

/* ====== Conteos para “LED” en los botones ====== */

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
  
  .oj.whatsapp {
  color: #25D366; /* Verde oficial de WhatsApp */
  border-color: #25D366;
}
.oj.whatsapp:hover {
  background: #25D36622; /* leve brillo al pasar el mouse */
}

.f.sidpol-registro {
  border-color: #d4af37 !important; /* dorado */
  background: var(--surface);
  text-align: center;
}

.f.sidpol-registro .l {
  color: #d4af37;
  font-weight: 900;
  text-transform: uppercase;
}

.f.sidpol-registro .v {
  color: #facc15; /* dorado claro */
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

/* Tooltip visual suave */
.f .v[title] {
  position: relative;
}
.f .v[title]:hover::after {
  content: attr(title);
  position: absolute;
  left: 0;
  top: 110%;
  background: var(--chip);
  color: var(--ink);
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 12px;
  max-width: 360px;
  white-space: normal;
  box-shadow: 0 2px 8px #0006;
  z-index: 100;
  
  /* Resalta UT y placa */
.vtit {
  font-weight: 900;
  font-size: 15px;
  letter-spacing: 0.3px;
  display: flex;
  align-items: center;
  gap: 6px;
}

/* El badge UT más brillante */
.vtit .badge {
  background: linear-gradient(145deg, #2563eb, #3b82f6); /* azul elegante */
  color: #fff;
  border: none;
  box-shadow: 0 0 6px #3b82f680;
  font-weight: 800;
}

/* La placa con tono resaltado adaptable */
.vtit .placa-highlight {
  color: #facc15; /* dorado visible en modo oscuro */
  text-shadow: 0 0 4px #facc1580;
}
@media (prefers-color-scheme: light) {
  .vtit .placa-highlight {
    color: #b45309; /* dorado profundo en modo claro */
    text-shadow: 0 0 4px #b4530980;
  }
}
  
}

/* Badge especial para UT */
.vtit .badge.ut{
  background: linear-gradient(145deg,#2563eb,#3b82f6) !important;
  color:#fff !important;
  border:none !important;
  box-shadow:0 0 6px rgba(59,130,246,.5);
  font-weight:800;
}

/* Centrado especial para campos destacados */
.f.centered {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.f.centered .l {
  font-weight: 800;
  color: var(--muted);
  text-transform: uppercase;
  font-size: 11px;
  margin-bottom: 4px;
}
.f.centered .v {
  font-weight: 900;
  font-size: 14px;
}

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
.btn-efectivo:hover {
  background: linear-gradient(145deg, #15803d, #16a34a);
  box-shadow: 0 0 12px rgba(22,163,74,.6);
  transform: translateY(-1px);
}
.btn-efectivo:active {
  transform: scale(.97);
}

.estado-lesion {
  display: inline-block;
  font-weight: 900;
  font-size: 11.5px;
  padding: 2px 8px;
  border-radius: 12px;
  margin-left: 8px;
}
.estado-ileso {
  color: #16a34a;
  background: #dcfce7;
  border: 1px solid #16a34a40;
}
.estado-herido {
  color: #b45309;
  background: #fef3c7;
  border: 1px solid #b4530940;
}
.estado-fallecido {
  color: #b91c1c;
  background: #fee2e2;
  border: 1px solid #b91c1c40;
}

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
.btn-fallecido:hover {
  background: linear-gradient(145deg, #b91c1c, #ef4444);
  box-shadow: 0 0 12px rgba(239,68,68,.6);
  transform: translateY(-1px);
}
.btn-fallecido:active {
  transform: scale(.97);
}

.btn-propietario {
  background: linear-gradient(145deg, #facc15, #fbbf24); /* dorado suave */
  color: #000;
  font-weight: 800;
  padding: 6px 14px;
  border-radius: 50px;
  text-decoration: none;
  box-shadow: 0 2px 8px rgba(234,179,8,.35);
  border: none;
  transition: all .25s ease;
}
.btn-propietario:hover {
  background: linear-gradient(145deg, #eab308, #facc15);
  box-shadow: 0 0 12px rgba(234,179,8,.55);
  transform: translateY(-1px);
}
.btn-propietario:active {
  transform: scale(.97);
}

.btn-oficio {
  background: linear-gradient(145deg, #38bdf8, #0ea5e9); /* celeste degradado */
  color: #fff;
  font-weight: 800;
  padding: 6px 14px;
  border-radius: 50px;
  text-decoration: none;
  box-shadow: 0 2px 8px rgba(14,165,233,.4);
  border: none;
  transition: all .25s ease;
}
.btn-oficio:hover {
  background: linear-gradient(145deg, #0ea5e9, #38bdf8);
  box-shadow: 0 0 12px rgba(14,165,233,.6);
  transform: translateY(-1px);
}
.btn-oficio:active {
  transform: scale(.97);
}

.btn-citacion {
  background: #f3e8ff;
  border: 1px solid #e0c9ff;
  color: #6b21a8;
  font-weight: 700;
  border-radius: 10px;
  padding: 6px 10px;
  text-decoration: none;
}
.btn-citacion:hover {
  background: #e9d5ff;
}

.btn-informe{
  display:inline-flex; align-items:center; gap:.5rem;
  padding:.5rem .8rem; border-radius:.5rem;
  background:#1e88e5; color:#fff; text-decoration:none; font-weight:600;
  box-shadow:0 2px 6px rgba(0,0,0,.12); transition:transform .05s ease, filter .15s ease;
}
.btn-informe:hover{ filter:brightness(1.05); }
.btn-informe:active{ transform:translateY(1px); }
.btn-informe.is-disabled{
  pointer-events:none; opacity:.5; background:#9e9e9e; cursor:not-allowed;
}

/* Badge de ROL de la persona (similar a UT) */
.role-badge{
  display:inline-flex; align-items:center;
  padding:2px 8px; border-radius:999px; font-weight:800; font-size:11.5px;
  border:1px solid; margin-right:8px;
}
.role-peaton    { background:#e5f3ff; color:#2563eb; border-color:#93c5fd; } /* azul */
.role-pasajero  { background:#f1f5f9; color:#0f172a; border-color:#cbd5e1; } /* gris */
.role-ocupante  { background:#f3e8ff; color:#6b21a8; border-color:#d8b4fe; } /* lila */
.role-testigo   { background:#fff7ed; color:#9a3412; border-color:#fed7aa; } /* naranja */
.role-conductor { background:#dcfce7; color:#166534; border-color:#86efac; } /* verde */

/* === Barra superior: una sola fila, compacta === */
.hdr .ttl{
  display:flex !important;
  align-items:center;
  gap:6px !important;            /* menos separación */
  flex-wrap:nowrap !important;   /* evita salto de línea */
  overflow-x:auto;               /* si no entra, deslizable */
  padding-bottom:2px;
  scrollbar-width: thin;
}
.hdr .ttl::-webkit-scrollbar{ height:6px }
.hdr .ttl::-webkit-scrollbar-thumb{
  background: rgba(255,255,255,.12);
  border-radius:10px;
}

/* Botones compactados (aplica a todos los de la barra) */
.hdr .ttl .btn-efectivo,
.hdr .ttl .btn-fallecido,
.hdr .ttl .btn-propietario,
.hdr .ttl .btn-oficio,
.hdr .ttl .btn-citacion,
.hdr .ttl .btn-informe{
  font-size:12.5px;          /* antes ~13–14 */
  padding:5px 10px;          /* más pequeño */
  border-radius:12px;        /* menos “pill” */
  line-height:1;             /* reduce altura */
  white-space:nowrap;        /* no cortarlos */
  box-shadow:0 2px 6px rgba(0,0,0,.22);
}

/* Afinado de cada color para que sigan viéndose bien */
.hdr .ttl .btn-efectivo{
  background: linear-gradient(145deg, #16a34a, #22c55e);
  color:#fff; border:none;
}
.hdr .ttl .btn-fallecido{
  background: linear-gradient(145deg, #dc2626, #ef4444);
  color:#fff; border:none;
}
.hdr .ttl .btn-propietario{
  background: linear-gradient(145deg, #facc15, #fbbf24);
  color:#111; border:none;
}
.hdr .ttl .btn-oficio{
  background: linear-gradient(145deg, #38bdf8, #0ea5e9);
  color:#fff; border:none;
}
.hdr .ttl .btn-citacion{
  background:#e9d5ff; color:#6b21a8;
  border:1px solid #d8b4fe;
}
.hdr .ttl .btn-informe{
  background:#1e88e5; color:#fff; border:none;
}

/* Hover sutil (opcional) */
.hdr .ttl .btn-efectivo:hover,
.hdr .ttl .btn-fallecido:hover,
.hdr .ttl .btn-propietario:hover,
.hdr .ttl .btn-oficio:hover,
.hdr .ttl .btn-citacion:hover,
.hdr .ttl .btn-informe:hover{
  transform: translateY(-1px);
  filter: brightness(1.05);
}

/* En modo claro, mantén contraste */
@media (prefers-color-scheme: light){
  .hdr .ttl .btn-citacion{ background:#f5eaff; }
}

/* ===== Título arriba y botones abajo (misma .ttl) ===== */
.hdr .ttl{
  display:flex !important;
  flex-wrap:wrap !important;      /* permite 2 líneas */
  align-items:center;
  gap:8px;
  font-size:0;                    /* oculta el texto crudo dentro de .ttl */
}

/* Reinyectamos el título como bloque de 1ra línea */
.hdr .ttl::before{
  content:"Datos generales del accidente";
  flex:0 0 100%;                  /* ocupa toda la fila */
  font-size:18px;
  font-weight:900;
  letter-spacing:.2px;
  line-height:1.2;
  margin-bottom:4px;
}

/* Segunda línea: los botones (ya existentes) */
.hdr .ttl > a{
  font-size:12.5px;               /* tamaño compacto */
  padding:5px 10px;
  border-radius:12px;
  white-space:nowrap;
}

/* Si no entran todos, permite scroll horizontal suave */
.hdr .ttl{
  overflow-x:auto;
  padding-bottom:2px;
  scrollbar-width:thin;
}
.hdr .ttl::-webkit-scrollbar{ height:6px }
.hdr .ttl::-webkit-scrollbar-thumb{
  background:rgba(255,255,255,.12);
  border-radius:10px;
}

/* (opcional) un poco más de espacio respecto a la barra derecha */
.hdr .bar{ margin-left:auto; }


/* ==== Botones nuevos: Vehículos Inv. / Personas Inv. ==== */
.btn-vehiculo {
  background: linear-gradient(145deg, #2563eb, #3b82f6); /* azul */
  color: #fff;
  font-weight: 800;
  padding: 6px 14px;
  border-radius: 50px;
  box-shadow: 0 2px 8px rgba(59,130,246,.4);
  text-decoration: none;
  border: none;
  transition: all .25s ease;
}
.btn-vehiculo:hover {
  background: linear-gradient(145deg, #3b82f6, #2563eb);
  box-shadow: 0 0 12px rgba(59,130,246,.6);
  transform: translateY(-1px);
}
.btn-vehiculo:active { transform: scale(.97); }

.btn-persona {
  background: linear-gradient(145deg, #9333ea, #a855f7); /* morado */
  color: #fff;
  font-weight: 800;
  padding: 6px 14px;
  border-radius: 50px;
  box-shadow: 0 2px 8px rgba(168,85,247,.4);
  text-decoration: none;
  border: none;
  transition: all .25s ease;
}
.btn-persona:hover {
  background: linear-gradient(145deg, #7e22ce, #9333ea);
  box-shadow: 0 0 12px rgba(147,51,234,.6);
  transform: translateY(-1px);
}
.btn-persona:active { transform: scale(.97); }

/* Botón Abogados */
.btn-abogado{
  background: linear-gradient(145deg,#0ea5e9,#0284c7); /* celeste/azul legal */
  color:#fff; font-weight:800; padding:6px 14px; border-radius:50px;
  text-decoration:none; border:none; box-shadow:0 2px 8px rgba(2,132,199,.4);
  transition:all .25s ease;
}
.btn-abogado:hover{ background:linear-gradient(145deg,#0284c7,#0ea5e9); box-shadow:0 0 12px rgba(2,132,199,.6); transform:translateY(-1px); }
.btn-abogado:active{ transform:scale(.97); }

/* Paleta dorada adaptable (reusa si ya la añadiste antes) */
:root{
  --gold: #d4af37;                /* oscuro */
  --gold-2: #f6e27a;
  --gold-shadow: rgba(212,175,55,.28);
}
@media (prefers-color-scheme: light){
  :root{
    --gold: #8a6d1d;              /* claro (mejor contraste) */
    --gold-2: #c79a2b;
    --gold-shadow: rgba(138,109,29,.22);
  }
}

/* Título con dorado degradado y clip */
.hdr .ttl::before{
  background: linear-gradient(135deg, var(--gold-2), var(--gold));
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;                 /* muestra el degradado en el texto */
  text-shadow: 0 0 6px var(--gold-shadow);
}

/* Si prefieres SÓLO color plano (sin degradado), usa esto en vez del bloque de arriba: */
/*
.hdr .ttl::before{
  color: var(--gold);
  text-shadow: 0 0 6px var(--gold-shadow);
}
*/

/* 1) Paleta dorada adaptable */
:root{
  /* Dorado principal para modo oscuro */
  --gold: #d4af37;              /* “gold” clásico */
  --gold-soft: #f6e5a3;         /* resalte sutil */
  --gold-shadow: rgba(212,175,55,.25);
}

/* En modo CLARO bajamos la saturación y oscurecemos para mejor contraste */
@media (prefers-color-scheme: light){
  :root{
    --gold: #8a6d1d;            /* dorado profundo (mejor contraste en fondo claro) */
    --gold-soft: #b08927;       /* para efectos suaves */
    --gold-shadow: rgba(138,109,29,.18);
  }
}

/* 2) Aplica el dorado a las etiquetas */
.f .l{
  color: var(--gold) !important;
  font-weight: 900;               /* mantiene presencia */
  letter-spacing: .2px;
  text-transform: uppercase;
}

/* 3) Un “glow” sutil distinto para claro/oscuro (opcional pero ayuda a distinguir) */
@media (prefers-color-scheme: dark){
  .f .l{
    text-shadow: 0 0 6px var(--gold-shadow);
  }
}
@media (prefers-color-scheme: light){
  .f .l{
    text-shadow: 0 1px 0 rgba(0,0,0,.06);
  }
}

/* 4) Si también quieres dorado en los subtítulos de sección (.st), descomenta: */
/*
.st{
  color: var(--gold) !important;
  text-shadow: 0 0 4px var(--gold-shadow);
}
*/

/* 5) Si prefieres aplicar dorado sólo a ciertas etiquetas, crea un helper: */
.l--gold{ color: var(--gold) !important; text-shadow: 0 0 6px var(--gold-shadow); }


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

.fechas-linea strong {
  color: var(--gold); /* usa el dorado elegante */
  font-weight: 900;
  letter-spacing: .3px;
}

@media (max-width: 680px) {
  .fechas-linea {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
}

/* Botón grande superior para volver al panel */
.volver-panel {
  display: flex;
  justify-content: center;
  margin: 12px 0 18px 0;
}

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

.btn-volver-panel:hover {
  background: linear-gradient(145deg, #eab308, #facc15);
  box-shadow: 0 0 12px rgba(234,179,8,.6);
  transform: translateY(-1px);
}

.btn-volver-panel:active {
  transform: scale(.97);
}


/* LED dentro de botones */
.btn, .btn-itp, .btn-efectivo, .btn-fallecido, .btn-propietario, .btn-oficio,
.btn-citacion, .btn-informe, .btn-vehiculo, .btn-persona, .btn-abogado {
  position: relative;
  padding-right: 18px; /* deja espacio para el LED */
}

.led {
  position: absolute; right: 6px; top: 6px;
  width: 10px; height: 10px; border-radius: 50%;
  background: #9aa4b2;              /* gris: sin registros */
  box-shadow: 0 0 0 1px var(--line) inset;
  opacity: .9;
}
.led.on {
  background: #22c55e;              /* verde */
  box-shadow: 0 0 8px #22c55e, 0 0 0 1px #14532d inset;
}

.btn-informe {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: linear-gradient(135deg, #ffd65c, #ffb400);
  border: 1px solid #b8860b;
  border-radius: 10px;
  padding: 8px 12px;
  color: #000;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s ease;
  box-shadow: 0 2px 5px rgba(0,0,0,0.15);
}
.btn-informe:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}
.btn-informe .led {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-left: 5px;
  background-color: #aaa;
}
.btn-informe .led.on {
  background-color: #2ecc71;
  box-shadow: 0 0 4px #2ecc71;
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
}
.btn-itp:hover {
  background:linear-gradient(145deg,#1d4ed8,#2563eb);
  transform:translateY(-1px);
}
.btn-itp:active {
  transform:scale(.97);
}

</style>
</head>
<body>
<div class="wrap">

<div class="volver-panel">
  <a href="accidente_listar.php" class="btn-volver-panel">⬅️ Volver al listado</a>
</div>

<div class="hdr">
<div class="ttl" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
  Datos generales del accidente

<a href="itp_listar.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-itp"
   title="Informe Técnico Policial (ITP)">
  🧾 ITP
  <span class="led <?= $cntItp>0 ? 'on' : '' ?>"
        title="<?= $cntItp>0 ? 'Con registros' : 'Sin registros' ?>"></span>
</a>


<a href="policial_interviniente_listar.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-efectivo"
   title="Ver efectivos policiales intervinientes">
   👮‍♂️ Efectivo Policial
   <span class="led <?= $cntEfec>0 ? 'on' : '' ?>" title="<?= $cntEfec>0?'Con registros':'Sin registros' ?>"></span>
</a>

<a href="involucrados_vehiculos_listar.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-vehiculo"
   title="Vehículos involucrados en el accidente">
   🚗 Vehículos Inv.
   <span class="led <?= $cntVeh>0 ? 'on' : '' ?>" title="<?= $cntVeh>0?'Con registros':'Sin registros' ?>"></span>
</a>

<a href="involucrados_personas_listar.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-persona"
   title="Personas involucradas en el accidente">
   👥 Personas Inv.
   <span class="led <?= $cntPer>0 ? 'on' : '' ?>" title="<?= $cntPer>0?'Con registros':'Sin registros' ?>"></span>
</a>

<a href="familiar_fallecido_listar.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-fallecido"
   title="Familiares de Fallecidos">
   💀 Familiar Fallecido
   <span class="led <?= $cntFam>0 ? 'on' : '' ?>" title="<?= $cntFam>0?'Con registros':'Sin registros' ?>"></span>
</a>

<a href="propietario_vehiculo_listar.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-propietario"
   title="Propietarios de Vehículos">
   🚘 Propietario Vehículo
   <span class="led <?= $cntProp>0 ? 'on' : '' ?>" title="<?= $cntProp>0?'Con registros':'Sin registros' ?>"></span>
</a>

<a href="abogado_listar.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-abogado"
   title="Abogados relacionados al accidente">
   ⚖️ Abogados
   <span class="led <?= $cntAbog>0 ? 'on' : '' ?>" title="<?= $cntAbog>0?'Con registros':'Sin registros' ?>"></span>
</a>

<a href="oficios_listar.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-oficio"
   title="Oficios relacionados al accidente">
   📄 Oficios
   <span class="led <?= $cntOfi>0 ? 'on' : '' ?>" title="<?= $cntOfi>0?'Con registros':'Sin registros' ?>"></span>
</a>

<a href="citacion_listar.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-citacion"
   title="Citaciones relacionadas al accidente">
   📅 Citaciones
   <span class="led <?= $cntCit>0 ? 'on' : '' ?>" title="<?= $cntCit>0?'Con registros':'Sin registros' ?>"></span>
</a>

<a href="word_informe_atropello.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-informe"
   title="Generar Word: informe_atropello.docx"
   target="_blank">
   📝 Informe Atropello
   <span class="led <?= $cntPer>0 ? 'on' : '' ?>" title="<?= $cntPer>0?'Con registros de personas':'Sin registros de personas' ?>"></span>
</a>

<a href="word_informe_choque_dos_vehiculos.php?accidente_id=<?=h($accidente_id)?>"
   class="btn-informe"
   title="Descargar informe de choque entre dos vehículos">
   🚗💥 Informe Choque Dos Vehículos
   <span class="led <?= $cntChoque ?? 1 ? 'on' : '' ?>" 
         title="Generar documento Word del choque de vehículos"></span>
</a>
  
  
</div>
 
</div>

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
        
          <!-- Registro SIDPOL con estilo dorado -->
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
        <div class="f s3"><div class="l">Comisaría</div><div class="v"><?=fmt($A['comisaria_nom'])?></div></div>
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
  <div class="v copyable" id="lugarNom" title="Click para copiar">
    <?=fmt($A['lugar'])?>
  </div>
</div>
        <div class="f s4"><div class="l">Ubicación</div><div class="v"><?= $ubicacion ?: '—' ?></div></div>
        <div class="f s4"><div class="l">Referencia</div><div class="v"><?=fmt($A['referencia'])?></div></div>
      </div>
    </div>

    <div class="sec">
      <p class="st">Autoridades</p>
      <div class="grid">
        <div class="f s6">
  <div class="l">Fiscalía</div>
  <div class="v copyable" id="fiscaliaNom" title="Click para copiar">
    <?=fmt($A['fiscalia_nom'])?>
  </div>
</div>
        <div class="f s6"><div class="l">Fiscal a cargo</div><div class="v"><?=fmt($A['fiscal_nom'])?></div></div>
      </div>
    </div>

    <div class="sec">
      <p class="st">Comunicación</p>
      <div class="grid">
        <div class="f s6"><div class="l">Comunicante</div><div class="v"><?=fmt($A['comunicante_nombre'])?></div></div>
        <div class="f s6"><div class="l">Tel. comunicante</div><div class="v"><?=fmt($A['comunicante_telefono'])?></div></div>
      </div>
    </div>

    <div class="sec">
      <p class="st">Descripción</p>
      <div class="grid">
        <div class="f s6"><div class="l">Sentido / Dirección</div><div class="v"><?=fmt($A['sentido'])?></div></div>
        <div class="f s6">
  <div class="l">Secuencia de eventos</div>
  <div class="v" id="secuenciaEv" title="<?=h($A['secuencia'] ?? '')?>">
    <?=fmt($A['secuencia'])?>
  </div>
</div>
      </div>
    </div>

  </div>

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
  ...
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
  <?php
    $rol = trim($p['rol_nombre'] ?? '');
    $rolKey = mb_strtolower($rol, 'UTF-8');
    $rolClass = 'role-badge';
    if     (strpos($rolKey,'peat') !== false) $rolClass .= ' role-peaton';
    elseif (strpos($rolKey,'pasaj')!== false) $rolClass .= ' role-pasajero';
    elseif (strpos($rolKey,'ocup')  !== false) $rolClass .= ' role-ocupante';
    elseif (strpos($rolKey,'testig')!== false) $rolClass .= ' role-testigo';
    elseif (strpos($rolKey,'conduc')!== false) $rolClass .= ' role-conductor';
    if ($rol !== '') echo '<span class="'.$rolClass.'">'.h(mb_strtoupper($rol,'UTF-8')).'</span>';
  ?>
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
                    <!-- 👁 Ver persona (modal) -->
                    <button class="oj" type="button"
                            data-src="persona_leer.php?id=<?= (int)$p['persona_id'] ?>&embed=1"
                            title="Ver ficha de persona">👁</button>
                    <!-- ✏️ Editar involucrado-persona -->
                    <a class="oj"
                       href="involucrados_personas_editar.php?id=<?= (int)$p['inv_per_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                       title="Editar involucrado – persona">✏️</a>
                    <!-- 📲 WhatsApp (si hay celular) -->
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
     title="Descargar manifestación de conductor">
     📝
  </a>
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
  <?php
    $rol = trim($p['rol_nombre'] ?? '');
    $rolKey = mb_strtolower($rol, 'UTF-8');
    $rolClass = 'role-badge';
    if     (strpos($rolKey,'peat') !== false) $rolClass .= ' role-peaton';
    elseif (strpos($rolKey,'pasaj')!== false) $rolClass .= ' role-pasajero';
    elseif (strpos($rolKey,'ocup')  !== false) $rolClass .= ' role-ocupante';
    elseif (strpos($rolKey,'testig')!== false) $rolClass .= ' role-testigo';
    elseif (strpos($rolKey,'conduc')!== false) $rolClass .= ' role-conductor';
    if ($rol !== '') echo '<span class="'.$rolClass.'">'.h(mb_strtoupper($rol,'UTF-8')).'</span>';
  ?>
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
                <!-- 👁 Ver persona (modal) -->
                <button class="oj" type="button"
                        data-src="persona_leer.php?id=<?= (int)$p['persona_id'] ?>&embed=1"
                        title="Ver ficha de persona">👁</button>
                <!-- ✏️ Editar involucrado-persona -->
                <a class="oj"
                   href="involucrados_personas_editar.php?id=<?= (int)$p['inv_per_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                   title="Editar involucrado – persona">✏️</a>
                <!-- 📲 WhatsApp -->
                <?php if($wa): ?>
                  <?php
$mensaje = "Buen día le saluda ST3.PNP Giancarlo MERINO SANCHO de la UIAT NORTE, a cargo de la investigación por el accidente de tránsito {$modalidad_txt}, suscitado el día {$fecha_acc} a horas {$hora_acc} en la {$lugar_acc}.";
?>
<a class="oj whatsapp"
   href="https://wa.me/<?= h($wa) ?>?text=<?= rawurlencode($mensaje) ?>"
   target="_blank" rel="noopener"
   title="Contactar por WhatsApp">📲</a>
                <?php endif; ?>

                <?php if(stripos((string)$p['rol_nombre'],'conductor')!==false): ?>
                  <button class="ojd" type="button"
                          data-src="diligencias_conductor.php?accidente_id=<?=$accidente_id?>&persona_id=<?= (int)$p['persona_id'] ?>&inv_per_id=<?= (int)$p['inv_per_id'] ?>"
                          title="Diligencias del conductor">📑</button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

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
function closePersona(){ const m=document.getElementById('mdPersona'); const ifr=document.getElementById('ifPersona');
  if(!m||!ifr) return; ifr.src='about:blank'; m.classList.remove('open'); }
document.querySelectorAll('.oj[data-src]').forEach(btn=>{ btn.addEventListener('click',()=>openPersona(btn.dataset.src)); });
document.getElementById('btnCloseMdPer').addEventListener('click', closePersona);
document.getElementById('mdPersona').addEventListener('click', (e)=>{ if(e.target.id==='mdPersona') closePersona(); });

/* VEHÍCULO modal */
function openVehiculo(src){
  const m=document.getElementById('mdVehiculo'); const ifr=document.getElementById('ifVehiculo');
  if(!m||!ifr||!src) return; ifr.src=src; m.classList.add('open');
}
function closeVehiculo(){ const m=document.getElementById('mdVehiculo'); const ifr=document.getElementById('ifVehiculo');
  if(!m||!ifr) return; ifr.src='about:blank'; m.classList.remove('open'); }
document.querySelectorAll('.ojv[data-src]').forEach(btn=>{ btn.addEventListener('click',()=>openVehiculo(btn.dataset.src)); });
document.getElementById('btnCloseMdVeh').addEventListener('click', closeVehiculo);
document.getElementById('mdVehiculo').addEventListener('click', (e)=>{ if(e.target.id==='mdVehiculo') closeVehiculo(); });

/* DILIGENCIAS modal */
function openDili(src){
  const m=document.getElementById('mdDili'); const ifr=document.getElementById('ifDili');
  if(!m||!ifr||!src) return; ifr.src=src; m.classList.add('open');
}
function closeDili(){ const m=document.getElementById('mdDili'); const ifr=document.getElementById('ifDili');
  if(!m||!ifr) return; ifr.src='about:blank'; m.classList.remove('open'); }
document.querySelectorAll('.ojd[data-src]').forEach(btn=>{ btn.addEventListener('click',()=>openDili(btn.dataset.src)); });
document.getElementById('btnCloseMdDili').addEventListener('click', closeDili);
document.getElementById('mdDili').addEventListener('click', (e)=>{ if(e.target.id==='mdDili') closeDili(); });
</script>
<div id="copyToast" class="copy-toast">Copiado ✅</div>
<script>
(function(){
  const el = document.getElementById('fiscaliaNom');
  if(!el) return;
  el.addEventListener('click', async ()=>{
    const txt = el.innerText.trim();           // copia el texto completo (aunque se vea con “…”)
    if(!txt) return;
    try{
      await navigator.clipboard.writeText(txt);
      const toast = document.getElementById('copyToast');
      toast.classList.add('show');
      setTimeout(()=>toast.classList.remove('show'), 1400);
    }catch(e){
      // fallback
      const ta = document.createElement('textarea');
      ta.value = txt; document.body.appendChild(ta);
      ta.select(); document.execCommand('copy'); ta.remove();
    }
  });
})();

// Copiar LUGAR
(function(){
  const el = document.getElementById('lugarNom');
  if(!el) return;
  el.addEventListener('click', async ()=>{
    const txt = el.innerText.trim();
    if(!txt) return;
    try{
      await navigator.clipboard.writeText(txt);
      const toast = document.getElementById('copyToast');
      toast.classList.add('show');
      setTimeout(()=>toast.classList.remove('show'), 1400);
    }catch(e){
      const ta = document.createElement('textarea');
      ta.value = txt; document.body.appendChild(ta);
      ta.select(); document.execCommand('copy'); ta.remove();
    }
  });
})();

</script>

</body>
</html>
