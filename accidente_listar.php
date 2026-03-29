<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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

  if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

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
      echo json_encode(['ok'=>false,'msg'=>'Folder inválido (vacío o 1..10)']);
    }
  }
  exit;
}

/* NEW: handler para prioridad (priority) */
if (($_POST['ajax'] ?? '') === 'priority') {
  $id = (int)($_POST['id'] ?? 0);
  $raw = $_POST['priority'] ?? '';
  header('Content-Type: application/json; charset=utf-8');

  if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

  // Permitimos '1' o '0' o valores vacíos (-> 0)
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

/* ============================
   LISTA DE COMISARÍAS
============================ */
$comisarias = $pdo->query("SELECT id, nombre FROM comisarias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

/* ============================
   QUERY BASE
============================ */
// ➜ Añadimos a.estado, a.folder y a.priority
$sql = "SELECT a.id,a.registro_sidpol,a.lugar,a.fecha_accidente,a.estado,a.folder,a.priority,c.nombre AS comisaria
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

/* VEHÍCULO: por placa */
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
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Accidentes | UIAT Norte</title>
<style>
/* ===== Tema auto (oscuro/claro) ===== */
:root{
  --bg:#0b0f19; --fg:#e6e8ef; --muted:156,163,175;
  --panel-bg:#141a29aa; --panel-bd:#ffffff22;
  --field-bg:#ffffff0d; --field-bd:#ffffff30;
  --pill-bg:#ffffff14; --overlay:#0008;
  --tbl-head-bg:#0f1628; --tbl-head-bd:#ffffff1f;
  --tbl-row-bg:#0f1422; --tbl-row-alt:#11192b;
  --tbl-row-hover:#1b2236; --tbl-bd:#ffffff1f;
  --badge-bg:#ffffff12; --badge-bd:#ffffff30;
  --ok:#10b981; --warn:#f59e0b; --danger:#ef4444;
}
@media (prefers-color-scheme: light){
  :root{
    --bg:#f6f7fb; --fg:#0b0f19; --panel-bg:#ffffffcc; --panel-bd:#00000014;
    --field-bg:#ffffff; --field-bd:#00000022; --pill-bg:#f2f4ff; --overlay:#0006;
    --tbl-head-bg:#eef2ff; --tbl-head-bd:#00000014;
    --tbl-row-bg:#ffffff; --tbl-row-alt:#fafafa;
    --tbl-row-hover:#f3f4f6; --tbl-bd:#00000014;
    --badge-bg:#eef2ff; --badge-bd:#dbeafe;
  }
}

/* ===== Layout base ===== */
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--fg);font:13px system-ui} /* ligera reducción global */
.wrap{max-width:1200px;margin:20px auto;padding:14px}
.title{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap}
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
.col-12{grid-column:span 12}.col-6{grid-column:span 6}.col-4{grid-column:span 4}.col-3{grid-column:span 3}
@media(max-width:1000px){.col-6,.col-4,.col-3{grid-column:span 12}}

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
.empty{padding:18px;text-align:center;color:rgba(var(--muted),1)}
.badge.sidpol-reg { background:transparent; border-color:#d4af37; color:#facc15; font-weight:800; font-size:12px; }

/* Compactar aún más la tabla */
table.compact thead th{ padding:6px 8px !important; font-size:12px !important; }
table.compact tbody td{ padding:6px 8px !important; font-size:12px !important; }
table.compact tbody tr{ height:42px; }

/* Botón eliminar solo con “X” */
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
@media (prefers-color-scheme: dark){
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
.estado-dilig{     background:#fff3cd; color:#664d03; }   /* naranja/ámbar */

@media (prefers-color-scheme: dark){
  div.estado-popup{ background:#1e293b; border-color:#334155; color:#f8fafc; }
}

/* Menú flotante para cambiar estado (look igual a los badges) */
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
.star-off { color:rgba(255,255,255,0.45); }

.select-folder{
  padding:6px 10px;
  border-radius:12px;
  font-weight:800;
  font-size:13px;
  background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.02));
  border:1.5px solid #d4af37;
  color:var(--fg);
  min-width:68px;
  text-align:center;
}
@media (prefers-color-scheme: dark){
  .select-folder {
    background: rgba(212,175,55,0.12);
    color: #f6f6f6;
    border-color: #e2c96c;
    box-shadow: 0 0 6px rgba(212,175,55,0.12) inset;
  }
  .star-off { color: rgba(255,255,255,0.35); }
}
</style>
</head>
<body>
<div class="wrap">
  <div class="title">
    <h1 style="margin:0">Accidentes <span class="badge">Listado</span></h1>
    <nav class="toolbar" aria-label="Acciones">
      <a class="btn" href="#" onclick="history.back();return false;">← Atrás</a>
      <a class="btn" href="index.php">🏠 Inicio</a>
      <a class="btn primary" href="accidente_nuevo.php">＋ Nuevo</a>
    </nav>
  </div>

  <div class="card">
    <form method="get" class="filters" id="filterForm">
        
    <div class="col-3">
  <label>Registro SIDPOL</label>
  <input type="text" name="registro_sidpol" placeholder="Ej: 2025-ABC-123" value="<?=h($_GET['registro_sidpol']??'')?>">
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
        <label>Vehículo (placa)</label>
        <input type="text" name="vehiculo" placeholder="Placa" value="<?=h($_GET['vehiculo']??'')?>">
      </div>
      
       <div class="col-3">
        <label>Comisaría</label>
        <select name="comisaria_id">
          <option value="">-- Todas --</option>
          <?php foreach($comisarias as $c): ?>
            <option value="<?=$c['id']?>" <?=($comisaria_id==$c['id']?'selected':'')?>><?=h($c['nombre'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-2" style="align-self:end;display:flex;gap:6px">
        <button class="btn small" type="submit">Filtrar</button>
        <a class="btn small" href="accidente_listar.php">Limpiar</a>
      </div>
    </form>

    <div class="table-wrap" role="region" aria-label="Lista de accidentes">
      <table class="compact" role="table" aria-describedby="tbl-desc">
<thead>
  <tr role="row">
    <th role="columnheader" data-sort="folder">Folder <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="estado">Estado <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="registro_sidpol">Registro SIDPOL <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="lugar">Lugar <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="fecha_accidente">Fecha <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="comisaria">Comisaría <span class="sort-indicator"></span></th>
    <th class="td-actions" role="columnheader">Acciones</th>
  </tr>
</thead>
        <tbody id="tbody-rows" role="rowgroup">
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="empty">Sin resultados</td></tr>
          <?php else: foreach($rows as $i=>$r): 
              $estado = $r['estado'] ?: 'Pendiente';
              $cls = ($estado==='Resuelto') ? 'estado-resuelto'
                   : (($estado==='Con diligencias') ? 'estado-dilig' : 'estado-pendiente');
              $folderVal = (string)($r['folder'] ?? '');
          ?>
            <tr data-id="<?= (int)$r['id'] ?>" role="row">
  <!-- FOLDER (primera columna) + ESTRELLA prioridad -->
<td class="col-folder folder-cell" role="cell">
  <?php $isPrior = !empty($r['priority']) && (int)$r['priority']===1; ?>
  <!-- Botón estrella separado -->
  <button class="prio-btn" title="<?= $isPrior ? 'Quitar prioridad' : 'Marcar prioridad' ?>"
          data-id="<?= $r['id'] ?>" data-priority="<?= $isPrior ? '1' : '0' ?>"
          aria-pressed="<?= $isPrior ? 'true' : 'false' ?>">
    <span class="star <?= $isPrior ? 'star-on' : 'star-off' ?>"><?= $isPrior ? '★' : '☆' ?></span>
  </button>

  <!-- Select Folder separado y con menor tipografía -->
  <select class="select-folder" data-id="<?=$r['id']?>" aria-label="Folder">
    <?php $folderVal = ($r['folder'] === null ? '' : (string)$r['folder']); ?>
    <option value="" <?=($folderVal===''?'selected':'')?>>—</option>
    <?php for($k=1;$k<=10;$k++): ?>
      <option value="<?=$k?>" <?=($folderVal===(string)$k?'selected':'')?>><?=$k?></option>
    <?php endfor; ?>
  </select>
</td>

  <!-- ESTADO -->
  <td role="cell">
    <span class="estado-badge <?=$cls?>"
          data-id="<?=$r['id']?>"
          data-estado="<?=h($estado)?>">
      <?=h($estado)?>
    </span>
  </td>

  <!-- DEMÁS CAMPOS -->
  <td role="cell"><span class="badge sidpol-reg"><?=h($r['registro_sidpol'])?></span></td>
  <td role="cell"><?=h($r['lugar'])?></td>
  <td role="cell"><?=h($r['fecha_accidente'])?></td>
  <td role="cell"><?=h($r['comisaria']??'-')?></td>
  <td class="td-actions" role="cell">
    <a class="btn small" title="Ver detalles"
       href="Dato_General_accidente.php?accidente_id=<?= $r['id'] ?>">👁 Detalles</a>

    <a class="btn small" href="accidente_editar.php?id=<?= $r['id'] ?>">✏️ Editar</a>

    <a class="btn small btn-oficios"
       href="oficios_listar.php?sidpol=<?= urlencode($r['registro_sidpol']) ?>">
      📝 Oficios
    </a>

    <!-- NUEVO: botón Recibidos (documentos recibidos relacionados al accidente) -->
    <a class="btn small" href="documento_recibido_listar.php?accidente_id=<?= (int)$r['id'] ?>" title="Documentos recibidos">
      📂 Recibidos
    </a>

    <form action="accidente_eliminar.php" method="post" style="display:inline"
          onsubmit="return confirm('¿Eliminar este accidente de forma permanente?');">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn danger small btn-x" title="Eliminar" aria-label="Eliminar">×</button>
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
// Auto-submit filtros al cambiar
document.querySelectorAll('#filterForm input, #filterForm select').forEach(el=>{
  el.addEventListener('change', ()=> document.getElementById('filterForm').submit());
});

// Menú estilo badge para cambiar estado
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

// Toggle prioridad (estrella) - opción C: subir si marca, bajar si desmarca
document.querySelectorAll('.col-folder .prio-btn').forEach(btn=>{
  btn.addEventListener('click', function(e){
    e.preventDefault();
    const id = this.dataset.id;
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
      star.textContent='★';
      star.classList.remove('star-off');
      star.classList.add('star-on');
      this.setAttribute('aria-pressed','true');
      this.dataset.priority='1';

      // Mover a la parte superior del tbody
      if (tr && tbody) tbody.prepend(tr);

    } else {
      // Desactivar
      star.textContent='☆';
      star.classList.remove('star-on');
      star.classList.add('star-off');
      this.setAttribute('aria-pressed','false');
      this.dataset.priority='0';

      // --- REUBICAR FILA SEGÚN ORDEN (folder → fecha) ---
      const folder = tr.querySelector('.select-folder')?.value || '';
      const fecha = tr.children[4]?.innerText.trim() || ''; // Fecha en columna 5

      // Insertar según orden SQL: prioridad DESC, folder ASC, fecha DESC
      let insertado = false;

      // Recorremos las filas y buscamos el primer 'other' donde insertar antes
      const rows = [...tbody.querySelectorAll('tr')];
      for (let other of rows) {
        if (insertado) break;
        if (other === tr) continue;

        const otherPrior = other.querySelector('.prio-btn')?.dataset.priority === '1';
        // Saltar filas prioritarias: siempre van arriba
        if (otherPrior) continue;

        const otherFolder = other.querySelector('.select-folder')?.value || '';
        const otherFecha = other.children[4]?.innerText.trim() || '';

        // Comparación por folder (vacío = NULL → va al final)
        const f1 = folder==='' ? 999 : parseInt(folder);
        const f2 = otherFolder==='' ? 999 : parseInt(otherFolder);

        if (f1 < f2) {
          tbody.insertBefore(tr, other);
          insertado = true;
          break;
        }

        if (f1 === f2) {
          // Comparar fecha (mayor primero: fecha más reciente debe quedar antes)
          // Convertimos a string porque el formato en DB es YYYY-MM-DD hh:mm:ss y la comparación lexicográfica funciona
          if (fecha > otherFecha) {
            tbody.insertBefore(tr, other);
            insertado = true;
            break;
          }
        }
      }

      // Si no se insertó en ninguna posición → va al final
      if (!insertado) tbody.appendChild(tr);
    }

    // --- GUARDAR EN BD ---
    fetch(location.pathname, { method:'POST', body:fd })
      .then(r=>r.json())
      .then(j=>{
        if(!j.ok){
          alert(j.msg || 'No se pudo actualizar prioridad');
          // No revertimos la posición por simplicidad, pero podría revertirse si prefieres.
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
    // Si la celda contiene un input/select (ej: folder) preferimos su value
    const sel = cell.querySelector('select');
    if(sel) return sel.value === '' ? '' : sel.value;
    // Badge/registros: usar textContent
    return cell.textContent.trim();
  }

  function detectNumericSample(values){
    // Si más del 60% de valores parsean como número => numérica
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
      // comparación lingüística sensible
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
      // Sólo añado handler si tiene data-sort
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

        // Reinsertar en tbody según orden
        const frag = document.createDocumentFragment();
        arr.forEach(item => frag.appendChild(item.row));
        tbody.appendChild(frag);

        // Actualizar indicadores visuales
        ths.forEach(t => t.removeAttribute('data-order'));
        th.dataset.order = nuevo;
        clearIndicators(ths);
        th.querySelector('.sort-indicator').textContent = nuevo === 'asc' ? '▲' : '▼';
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
