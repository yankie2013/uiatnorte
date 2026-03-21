<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

$accidente_id = $_GET['accidente_id'] ?? null;
if (!isset($pdo) && isset($db) && $db instanceof PDO) $pdo = $db;

ini_set('display_errors', 0);
header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt($s){ return $s!==null && $s!=='' ? h($s) : '—'; }

function join_con_y(array $items){
  $n=count($items);
  if($n===0) return '—';
  $esc=array_map('h',$items);
  if($n===1) return $esc[0];
  if($n===2) return $esc[0].' y '.$esc[1];
  return implode(', ', array_slice($esc,0,$n-1)).' y '.$esc[$n-1];
}
function fechaHoraCortaEsp($fechaRaw){
  if(!$fechaRaw || !strtotime($fechaRaw)) return '—';
  $t=strtotime($fechaRaw);
  $meses=['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
  return date('d',$t).$meses[(int)date('n',$t)-1].date('Y',$t).' '.date('H:i',$t);
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

$acc=null;
if($param_sidpol!=='') $acc=getAccBySidpol($pdo,$param_sidpol);
if(!$acc && $param_id>0){
  $st=$pdo->prepare("SELECT * FROM accidentes WHERE id=? LIMIT 1");
  $st->execute([$param_id]);
  $acc=$st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if(!$acc){
  $acc=$pdo->query("SELECT * FROM accidentes ORDER BY sidpol DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
}
if(!$acc) die('No hay accidentes registrados.');

$accidente_id=(int)$acc['id'];
$sidpol=(string)$acc['sidpol'];

/* Datos enriquecidos */
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
$A=$sti->fetch(PDO::FETCH_ASSOC) ?: $acc;

$ubicacion = implode(' / ', array_values(array_filter([
  $A['dep_nom'] ?? '', $A['prov_nom'] ?? '', $A['dist_nom'] ?? ''
])));

/* Modalidades/Consecuencias */
function fetchListSafe(PDO $pdo, string $sql1, string $sql2, array $params){
  try{ $st=$pdo->prepare($sql1); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC); }
  catch(Throwable $e){ $st=$pdo->prepare($sql2); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC); }
}
$rowsMods = fetchListSafe(
  $pdo,
  "SELECT m.nombre FROM accidente_modalidad am JOIN modalidad_accidente m ON m.id=am.modalidad_id
   WHERE am.accidente_id=? ORDER BY am.id",
  "SELECT m.nombre FROM accidente_modalidad am JOIN modalidad_accidente m ON m.id=am.modalidad_id
   WHERE am.accidente_id=? ORDER BY am.modalidad_id",
  [$accidente_id]
);
$mods = array_column($rowsMods,'nombre');

$rowsCons = fetchListSafe(
  $pdo,
  "SELECT c.nombre FROM accidente_consecuencia ac JOIN consecuencia_accidente c ON c.id=ac.consecuencia_id
   WHERE ac.accidente_id=? ORDER BY ac.id",
  "SELECT c.nombre FROM accidente_consecuencia ac JOIN consecuencia_accidente c ON c.id=ac.consecuencia_id
   WHERE ac.accidente_id=? ORDER BY ac.consecuencia_id",
  [$accidente_id]
);
$cons = array_column($rowsCons,'nombre');

$mods_concat = join_con_y($mods);
$cons_concat = $cons ? implode(' → ', array_map('h',$cons)) : '—';

/* WhatsApp vars que usa el panel */
$modalidad_txt = $mods_concat ?: '—';
$lugar_acc     = $A['lugar'] ?? '—';
$fechaHoraRaw  = $A['fecha_accidente'] ?? null;
if ($fechaHoraRaw && strtotime($fechaHoraRaw)) {
  $fecha_acc = date('d/m/Y', strtotime($fechaHoraRaw));
  $hora_acc  = date('H:i',   strtotime($fechaHoraRaw));
} else { $fecha_acc='—'; $hora_acc='—'; }
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Accidente · Tabs</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
  /* Sticky superior */
  .u-sticky{
    position: sticky; top: 0; z-index: 2000;
    background: var(--bs-body-bg);
    border-bottom: 1px solid rgba(0,0,0,.08);
  }

  /* Panel “participantes_panel.php” usa clases propias:
     aquí las hacemos compatibles para prueba rápida */
  .st{font-weight:700;color:#6c757d;margin:0 0 .5rem 0;font-size:.9rem}
  .sec{margin-top:.5rem}
  .vlist{display:grid;gap:.75rem}
  .vcard{border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:.75rem;background:rgba(0,0,0,.02)}
  .vh{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;flex-wrap:wrap}
  .vtit{font-weight:800}
  .chips{display:flex;flex-wrap:wrap;gap:.5rem}
  .chip{border:1px solid rgba(0,0,0,.12);border-radius:999px;padding:.15rem .5rem;font-size:.85rem;background:#fff}
  .plist{display:grid;gap:.5rem;margin-top:.5rem}
  .pcard{display:grid;grid-template-columns:1fr auto;gap:.5rem;align-items:center;border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:.6rem;background:#fff}
  .pname{font-weight:800}
  .pmeta{color:#6c757d;font-size:.9rem}
  .oj,.ojv,.ojd{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid rgba(0,0,0,.15);border-radius:.6rem;background:#f8f9fa;text-decoration:none}
  .badge.ut{background:#0d6efd}
  .placa-highlight{font-weight:900}
  .role-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-weight:800;font-size:11.5px;border:1px solid;margin-right:8px}
  .role-peaton{background:#e7f1ff;color:#0d6efd;border-color:#b6d4fe}
  .role-pasajero{background:#f1f3f5;color:#212529;border-color:#dee2e6}
  .role-ocupante{background:#f3e8ff;color:#6f42c1;border-color:#d8b4fe}
  .role-testigo{background:#fff4e6;color:#9a3412;border-color:#ffd8a8}
  .role-conductor{background:#e6fcf5;color:#2b8a3e;border-color:#b2f2bb}
  .estado-lesion{display:inline-block;font-weight:900;font-size:11.5px;padding:2px 8px;border-radius:12px;margin-left:8px}
  .estado-ileso{color:#2b8a3e;background:#d3f9d8;border:1px solid #b2f2bb}
  .estado-herido{color:#b45309;background:#fff3bf;border:1px solid #ffe066}
  .estado-fallecido{color:#c92a2a;background:#ffe3e3;border:1px solid #ffa8a8}
  .whatsapp{color:#25D366;border-color:#25D366}
</style>
</head>

<body>
<div class="container-fluid py-3">

  <!-- ===== Sticky: Identificación siempre visible ===== -->
  <div class="u-sticky pb-2">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <div class="fw-bold">Accidente: <?= h($sidpol) ?></div>
      <a class="btn btn-warning btn-sm" href="accidente_listar.php">⬅️ Volver al listado</a>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
          <div class="small text-muted fw-bold">Registro SIDPOL</div>
          <div class="fw-bold"><?= fmt($A['registro_sidpol']) ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
          <div class="small text-muted fw-bold">Estado</div>
          <div class="fw-bold"><?= fmt($A['estado']) ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
          <div class="small text-muted fw-bold">N° Informe Policial</div>
          <div class="fw-bold"><?= fmt($A['nro_informe_policial']) ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card"><div class="card-body py-2">
          <div class="small text-muted fw-bold">Comisaría</div>
          <div class="fw-bold text-truncate"><?= fmt($A['comisaria_nom']) ?></div>
        </div></div>
      </div>
    </div>

    <div class="card mt-2">
      <div class="card-body py-2 small">
        <div><b>Modalidades:</b> <?= $mods_concat ?></div>
        <div><b>Consecuencias:</b> <?= $cons_concat ?></div>
        <hr class="my-2">
        <div class="d-flex flex-wrap gap-3">
          <div><b>Accidente:</b> <?= fechaHoraCortaEsp($A['fecha_accidente']) ?></div>
          <div><b>Comunicación:</b> <?= fechaHoraCortaEsp($A['fecha_comunicacion']) ?></div>
          <div><b>Intervención:</b> <?= fechaHoraCortaEsp($A['fecha_intervencion']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== Tabs ===== -->
  <div class="mt-3">
    <ul class="nav nav-tabs" id="tabsAcc" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="t-part" data-bs-toggle="tab" data-bs-target="#p-part" type="button" role="tab">
          👥 Participantes
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="t-fam" data-bs-toggle="tab" data-bs-target="#p-fam" type="button" role="tab">
          💀 Familiar Fallecido
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="t-prop" data-bs-toggle="tab" data-bs-target="#p-prop" type="button" role="tab">
          🚘 Propietario Vehículo
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="t-abog" data-bs-toggle="tab" data-bs-target="#p-abog" type="button" role="tab">
          ⚖️ Abogados
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="t-ofi" data-bs-toggle="tab" data-bs-target="#p-ofi" type="button" role="tab">
          📄 Oficios
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="t-cit" data-bs-toggle="tab" data-bs-target="#p-cit" type="button" role="tab">
          📅 Citaciones
        </button>
      </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom p-3 bg-body">

      <!-- Participantes embebido (SIN iframe) -->
      <div class="tab-pane fade show active" id="p-part" role="tabpanel">
        <?php include __DIR__ . '/participantes_panel.php'; ?>
      </div>

      <!-- Los demás por iframe para no tocar aún -->
      <div class="tab-pane fade" id="p-fam" role="tabpanel">
        <iframe style="width:100%;border:0;min-height:70vh"
                src="familiar_fallecido_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
      </div>

      <div class="tab-pane fade" id="p-prop" role="tabpanel">
        <iframe style="width:100%;border:0;min-height:70vh"
                src="propietario_vehiculo_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
      </div>

      <div class="tab-pane fade" id="p-abog" role="tabpanel">
        <iframe style="width:100%;border:0;min-height:70vh"
                src="abogado_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
      </div>

      <div class="tab-pane fade" id="p-ofi" role="tabpanel">
        <iframe style="width:100%;border:0;min-height:70vh"
                src="oficios_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
      </div>

      <div class="tab-pane fade" id="p-cit" role="tabpanel">
        <iframe style="width:100%;border:0;min-height:70vh"
                src="citacion_listar.php?accidente_id=<?=h($accidente_id)?>" loading="lazy"></iframe>
      </div>

    </div>
  </div>

</div>

<script>
/* Recordar pestaña activa */
(function(){
  const key='uiat_tabs_accidente';
  document.querySelectorAll('#tabsAcc button[data-bs-toggle="tab"]').forEach(btn=>{
    btn.addEventListener('shown.bs.tab', e=>{
      localStorage.setItem(key, e.target.getAttribute('data-bs-target'));
    });
  });
  const saved=localStorage.getItem(key);
  if(saved){
    const btn=document.querySelector(`#tabsAcc button[data-bs-target="${saved}"]`);
    if(btn) new bootstrap.Tab(btn).show();
  }
})();
</script>

</body>
</html>
