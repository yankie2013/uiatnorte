<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
include __DIR__ . '/_boton_volver.php';
// DEBUG temporal (quítalo en prod)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

// incluir el sidebar (archivo en la misma carpeta uiatnorte)
include __DIR__ . '/sidebar.php';
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

$ok = g('ok','');
$msg = g('msg','');
$accidente_id = (int)g('accidente_id', 0);
$tipo         = g('tipo','');
$q            = g('q','');

// combos
$accidentes = $pdo->query("
  SELECT id, CONCAT('#',id,' – ',DATE_FORMAT(fecha_accidente,'%Y-%m-%d %H:%i'),' – ',COALESCE(lugar,'')) AS nom
  FROM accidentes ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// consulta
$sql = "
SELECT iv.id, iv.accidente_id, iv.vehiculo_id, iv.tipo, iv.observaciones,
       a.fecha_accidente, a.lugar,
       v.placa, v.color, v.anio
FROM involucrados_vehiculos iv
JOIN accidentes a ON a.id = iv.accidente_id
JOIN vehiculos  v ON v.id = iv.vehiculo_id
WHERE 1=1
";
$params = [];
if ($accidente_id>0){ $sql.=" AND iv.accidente_id=?"; $params[]=$accidente_id; }
if ($tipo!==''){      $sql.=" AND iv.tipo=?";          $params[]=$tipo; }
if ($q!==''){
  $sql.=" AND (v.placa LIKE ? OR v.color LIKE ?)";
  $like = "%$q%"; array_push($params,$like,$like);
}
$sql.=" ORDER BY iv.id DESC LIMIT 200";
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// helper URL base y return
$base = rtrim(dirname($_SERVER['PHP_SELF']),'/').'/';
$currentUrl = $_SERVER['REQUEST_URI'] ?? ($base.'involucrados_vehiculos_listar.php');
$returnParam = urlencode($currentUrl);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Involucrados – Vehículos | UIAT Norte</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
  :root{
    color-scheme: light dark;
    --bg: #0b0f16;
    --bg-soft: #0f1520;
    --card: rgba(255,255,255,0.06);
    --stroke: rgba(255,255,255,0.12);
    --text: #e7eaf0;
    --muted: #9aa3b2;
    --brand: #4f8cff;
    --brand-2: #9b7bff;
    --okbg: rgba(16,185,129,.12);
    --okbd: rgba(16,185,129,.35);
    --oktx: #7ce0b5;
    --shadow: 0 10px 30px rgba(0,0,0,.35);
    --radius: 14px;
    --radius-lg: 20px;
    --fs: 14px;
    --fs-sm: 12px;
    --pad: 10px;
  }

  @media (prefers-color-scheme: light){
    :root{
      --bg: #f6f7fb;
      --bg-soft:#eef1f7;
      --card: rgba(255,255,255,.8);
      --stroke: rgba(0,0,0,.08);
      --text: #1f2937;
      --muted: #6b7280;
      --shadow: 0 6px 22px rgba(0,0,0,.08);
    }
  }

  html,body{ height:100%; }
  body{
    margin:0; font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Helvetica Neue", sans-serif;
    font-size: var(--fs); color: var(--text);
    background:
      radial-gradient(1200px 600px at 5% 0%, rgba(79,140,255,.18), transparent 60%),
      radial-gradient(1000px 600px at 95% 0%, rgba(155,123,255,.18), transparent 60%),
      linear-gradient(180deg, var(--bg) 0%, var(--bg-soft) 100%);
  }
  .wrap{ max-width:1180px; margin:18px auto 28px auto; padding:0 14px; }

  .topbar{
    display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px;
  }
  .title{
    font-weight:800; letter-spacing:.2px; font-size:20px;
    display:flex; align-items:center; gap:10px;
  }
  .crumbs{ color: var(--muted); font-size: var(--fs-sm); }

  .btn{
    appearance:none; border:none; outline:none; text-decoration:none; cursor:pointer;
    padding:8px 12px; border-radius:12px;
    background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.02));
    border:1px solid var(--stroke); color:var(--text); font-weight:700; letter-spacing:.2px;
    transition: .15s ease; box-shadow: inset 0 0 0 1px rgba(255,255,255,.03);
  }
  .btn:hover{ transform: translateY(-1px); box-shadow: var(--shadow); }
  .btn.primary{
    background:
      linear-gradient(180deg, rgba(79,140,255,.7), rgba(79,140,255,.45)),
      linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.02));
    border-color: rgba(79,140,255,.55);
    color:white;
  }
  .btn.ghost{ background: transparent; }
  .btn.small{ padding:6px 10px; font-size: var(--fs-sm); border-radius:10px; }

  .card{
    background: var(--card);
    border: 1px solid var(--stroke);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    padding: 12px;
    margin-bottom: 12px;
    position: relative;
  }
  .card:before{
    content:""; position:absolute; inset:-1px; border-radius:inherit; pointer-events:none;
    background: radial-gradient(500px 120px at 20px -20px, rgba(79,140,255,.18), transparent 60%),
                radial-gradient(500px 120px at calc(100% - 20px) -20px, rgba(155,123,255,.18), transparent 60%);
    filter: blur(14px); opacity:.8; z-index:-1;
  }

  .grid{ display:grid; gap:10px; grid-template-columns: 1fr 1fr 1fr; }
  .field label{ display:block; margin:0 0 6px 2px; color: var(--muted); font-size: var(--fs-sm); font-weight:600; }
  select,input[type="text"]{
    width:100%; padding:8px 10px; border-radius:12px;
    border:1px solid var(--stroke); background: rgba(255,255,255,.04);
    color:var(--text); outline:none; box-shadow: inset 0 1px 0 rgba(255,255,255,.05);
  }
  select:focus,input:focus{ border-color: rgba(79,140,255,.55); box-shadow: 0 0 0 3px rgba(79,140,255,.18); }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }

  .table-wrap{ overflow:auto; border-radius: var(--radius); border:1px solid var(--stroke); }
  table{ width:100%; border-collapse: collapse; min-width: 780px; background: rgba(255,255,255,.02); }
  thead th{
    font-size: var(--fs-sm); text-transform: uppercase; letter-spacing:.4px;
    color: var(--muted); font-weight:800; padding:10px 10px; text-align:left;
    background: linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,0));
    position: sticky; top:0; z-index:2;
  }
  tbody td{ padding:9px 10px; border-top:1px solid var(--stroke); vertical-align: top; }
  tbody tr:hover{ background: rgba(255,255,255,.04); }
  .muted{ color: var(--muted); font-size: var(--fs-sm); }
  .td-actions{ white-space: nowrap; }

  .ok{ background: var(--okbg); border:1px solid var(--okbd); color: var(--oktx);
       padding:8px 10px; border-radius:12px; margin-bottom:10px; font-weight:700; }

  @media (max-width: 980px){
    .grid{ grid-template-columns: 1fr; }
    .td-hide-sm{ display:none; }
  }
  
  /* === Modal Vehículo === */
dialog#vehiculoModal{
  width: 960px; max-width: calc(100% - 24px);
  border: 1px solid var(--stroke);
  background: var(--card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow);
  padding: 0; overflow: hidden;
}
dialog#vehiculoModal::backdrop{ background: rgba(0,0,0,.55); }
.modal-head{
  display:flex; align-items:center; justify-content:space-between; gap:10px;
  padding:10px 12px; border-bottom:1px solid var(--stroke);
  background: linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,0));
  font-weight:800;
}
.modal-body{ padding:0; }
#vehiculoFrame{ width:100%; height: 72vh; border:0; display:block; }
</style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <div class="title">Involucrados – Vehículos</div>
    <div class="actions">
      <!-- NUEVOS BOTONES -->
      <a class="btn ghost" href="#" onclick="history.back();return false;">← Atrás</a>
      <a class="btn ghost" href="<?=$base?>accidente_listar.php">🗂️ Accidentes</a>
      <!-- EXISTENTES -->
      <a class="btn primary" href="<?=$base?>involucrados_vehiculos_nuevo.php<?=($accidente_id?('?accidente_id='.$accidente_id):'')?>">＋ Nuevo</a>
      <a class="btn ghost" href="<?=$base?>index.php">🏠 Panel</a>
    </div>
  </div>

  <?php if($ok): ?><div class="ok">Guardado correctamente.</div><?php endif; ?>

  <?php if($msg === 'eliminado'): ?><div class="ok">Involucrado eliminado correctamente.</div><?php endif; ?>
  <form class="card" method="get">
    <div class="grid">
      <div class="field">
        <label>Accidente</label>
        <select name="accidente_id">
          <option value="">— Todos —</option>
          <?php foreach($accidentes as $a): ?>
            <option value="<?=$a['id']?>" <?=($accidente_id==$a['id']?'selected':'')?>><?=h($a['nom'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Tipo</label>
        <select name="tipo">
          <option value="">— Todos —</option>
          <?php foreach(['Unidad','Impactada','Remolcada'] as $t): ?>
            <option value="<?=$t?>" <?=($tipo===$t?'selected':'')?>><?=$t?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Búsqueda</label>
        <input type="text" name="q" value="<?=h($q)?>" placeholder="Placa o color">
      </div>
    </div>
    <div class="actions" style="margin-top:10px">
      <button class="btn primary" type="submit">Filtrar</button>
      <a class="btn" href="<?=$base?>involucrados_vehiculos_listar.php">Limpiar</a>
    </div>
  </form>

  <div class="card table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:72px">ID</th>
          <th>Accidente</th>
          <th>Vehículo</th>
          <th class="td-hide-sm">Tipo</th>
          <th class="td-hide-sm">Obs.</th>
          <th class="td-actions">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="6" class="muted">Sin registros.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td>#<?=h($r['id'])?></td>
            <td>
              <div><strong>#<?=h($r['accidente_id'])?></strong> · <?=h(date('Y-m-d H:i', strtotime($r['fecha_accidente'])))?></div>
              <div class="muted"><?=h($r['lugar'])?></div>
            </td>
            <td>
              <div><strong><?=h($r['placa'])?></strong></div>
              <?php if($r['color']||$r['anio']): ?>
                <div class="muted"><?=h(($r['color']??'').($r['anio']?(' · '.$r['anio']):''))?></div>
              <?php endif; ?>
            </td>
            <td class="td-hide-sm"><?=h($r['tipo'])?></td>
            <td class="td-hide-sm"><?=h(mb_strimwidth($r['observaciones'] ?? '', 0, 80, '…','UTF-8'))?></td>
            <td class="td-actions">
              <a class="btn small" href="#"
   onclick="abrirVehiculo(<?= (int)$r['vehiculo_id'] ?>); return false;">🚗 Editar</a>
              
              <a class="btn small" href="<?=$base?>involucrados_vehiculos_editar.php?id=<?=urlencode($r['id'])?>&return=<?=$returnParam?>">✏️ Editar</a>
              <a class="btn small" href="<?=$base?>involucrados_vehiculos_eliminar.php?id=<?=urlencode($r['id'])?>&return_to=<?=$returnParam?>"
                 onclick="return confirm('¿Eliminar el registro #<?=h($r['id'])?>?');">🗑️ Eliminar</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

<dialog id="vehiculoModal">
  <div class="modal-head">
    <div>Editar vehículo</div>
    <button class="btn small" onclick="cerrarVehiculo()">✖ Cerrar</button>
  </div>
  <div class="modal-body">
    <iframe id="vehiculoFrame" src="about:blank" title="Editar vehículo"></iframe>
  </div>
</dialog>

</div>

<script>
  function abrirVehiculo(vehiculoId){
    const dlg = document.getElementById('vehiculoModal');
    const frm = document.getElementById('vehiculoFrame');
    // Carga el editor de vehículo en el iframe
    frm.src = '<?=$base?>vehiculo_editar.php?id=' + encodeURIComponent(vehiculoId);
    dlg.showModal();
  }
  function cerrarVehiculo(){
    const dlg = document.getElementById('vehiculoModal');
    const frm = document.getElementById('vehiculoFrame');
    dlg.close();
    // opcional: limpiar para liberar recursos
    frm.src = 'about:blank';
  }
  // Esc para cerrar
  window.addEventListener('keydown', (e) => {
    if(e.key === 'Escape'){
      const dlg = document.getElementById('vehiculoModal');
      if (dlg?.open) cerrarVehiculo();
    }
  });
</script>
</body>
</html>
