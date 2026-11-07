<?php
/* ============================================================
   UIAT Norte - involucrados_vehiculos_editar.php (REFACTORIZADO)
   ============================================================ */

require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';


use App\Repositories\InvolucradoVehiculoRepository;
use App\Services\InvolucradoVehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function ipost($k,$d=null){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function iget($k,$d=null){ return isset($_GET[$k])  ? trim($_GET[$k])  : $d; }
function okjson($arr){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr,JSON_UNESCAPED_UNICODE); exit; }

function yesno($v){
  if($v===null || $v==='') return '—';
  $v = strtolower((string)$v);
  return (in_array($v, ['1','si','sí','true','vigente','y'])) ? 'Vigente' : 'No vigente';
}
function badge_date($d){
  if(!$d) return '<span class="pill">—</span>';
  $today = date('Y-m-d');
  $ok = ($d >= $today);
  return '<span class="pill '.($ok?'pill-ok':'pill-bad').'">'.h($d).'</span>';
}
function vehiculo_text($row){
  return ($row['placa'] ?? 'ID '.$row['id']).(($row['color']??'') ? (' · '.$row['color']) : '').(($row['anio']??'') ? (' ('.$row['anio'].')') : '');
}

$repo = new InvolucradoVehiculoRepository($pdo);
$service = new InvolucradoVehiculoService($repo);

$id = (int)iget('id', 0);
if ($id<=0) { http_response_code(400); echo 'ID inválido'; exit; }

$iv = $repo->involucradoById($id);
if(!$iv){ http_response_code(404); echo 'Registro no encontrado'; exit; }

$accidente_id = (int)$iv['accidente_id'];
if(\App\Support\Access::role()==='guardia'){
 $check=$pdo->prepare('SELECT rbac_guardia_draft(?)');$check->execute([$accidente_id]);
 if(!$check->fetchColumn()){http_response_code(403);exit('El plazo de 12 horas terminó o este registro pertenece a otro usuario.');}
}

$return = iget('return_to', iget('return', 'involucrados_vehiculos_listar.php?accidente_id='.$accidente_id));
$tipo_opts = $service->tipoOptions();

if (iget('ajax')==='buscar_vehiculos') {
  okjson($service->buscarVehiculos(iget('q','')));
}

$err=''; $ok = iget('ok',''); $msg = iget('msg','');
if ($_SERVER['REQUEST_METHOD']==='POST' && iget('ajax')===null) {
  try{
    $service->actualizar($id, [
      'vehiculo_id' => (int)$iv['vehiculo_id'],
      'tipo' => ipost('tipo','Unidad'),
      'observaciones' => ipost('observaciones',''),
    ]);
    \App\Support\CaseParticipantEmbed::saved();
    header('Location: '.$return.'&ok=1');
    exit;
  }catch(Throwable $e){
    $err = 'Error al guardar: '.$e->getMessage();
    $iv = array_merge($iv, [
      'vehiculo_id' => (int)$iv['vehiculo_id'],
      'tipo' => ipost('tipo','Unidad'),
      'observaciones' => ipost('observaciones',''),
    ]);
  }
}


// incluir el sidebar (archivo en la misma carpeta uiatnorte)
if (!\App\Support\CaseParticipantEmbed::active()) include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar involucrado - Vehículo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
  .wrap{ max-width:1080px; margin:auto; padding:16px; }
  .top{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
  .pill{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:var(--muted-2); border:1px solid var(--line); font-weight:800; }
  /* pills de estado (reusa .pill que ya tienes) */
.pill-ok{ background:#e8f8ef; color:#18794e; font-weight:800; }
.pill-bad{ background:#feefef; color:#b42318; font-weight:800; }

/* tarjetas del resumen */
.dv-card h4{ margin:0 0 6px 0; font-size:15px; }
.dv-row{ display:flex; gap:8px; margin:6px 0; }
.dv-key{ min-width:130px; color:var(--fg-3); }
.dv-val{ font-weight:700; }
  .muted{ color: var(--fg-3); font-size:.9rem; }
  .grid{ display:grid; gap:10px; grid-template-columns: 1fr 1fr; }
  .grid-3{ display:grid; gap:10px; grid-template-columns: 1fr 1fr 1fr; }
  @media (max-width:900px){ .grid, .grid-3{ grid-template-columns: 1fr; } }
  label{ display:block; font-weight:700; font-size:.9rem; margin:4px 0; }
  input, select, textarea{ width:100%; border-radius:12px; font-size:.95rem; }
  textarea{ min-height:90px; }
  .grid-4{display:grid;gap:10px;grid-template-columns:repeat(4,1fr)}
@media (max-width:1200px){.grid-4{grid-template-columns:repeat(2,1fr)}}
@media (max-width:900px){.grid-4{grid-template-columns:1fr}}
  .actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
  .btn{ display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:12px; border:1px solid var(--line); background:var(--bg-2); color:var(--fg); font-weight:800; text-decoration:none; cursor:pointer; }
  .btn.primary{ background:var(--brand); color:#fff; border-color:var(--brand); }
  .btn.small{ padding:6px 10px; }

  /* chips acciones documentos */
  .chips{display:flex;gap:8px;flex-wrap:wrap}
  .chip{
    display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;
    border:1px solid var(--line);background:rgba(255,255,255,.05);color:var(--fg);
    font-weight:800;text-decoration:none;cursor:pointer;transition:.15s ease
  }
  .chip:hover{transform:translateY(-1px)}
  .chip.danger{border-color:#b91c1c;background:rgba(185,28,28,.15);color:#fee2e2}

  /* Modal iframe genérico */
  .modal{ position:fixed; inset:0; background:rgba(0,0,0,.5); display:none; align-items:center; justify-content:center; padding:16px; z-index:99; }
  .modal.open{ display:flex; }
  .modal .box{ background:var(--bg); border:1px solid var(--line); border-radius:16px; padding:12px; width:min(1000px,95%); height:90vh; color:var(--fg); display:flex; flex-direction:column; }
  .modal .box .head{ display:flex; align-items:center; justify-content:space-between; padding-bottom:8px; border-bottom:1px solid var(--line); }
  .modal .box iframe{ flex:1; width:100%; border:0; border-radius:12px; background:#fff; }
  @media (prefers-color-scheme: dark){ .modal .box iframe{ background:#0b0d0f; } }
  
  /* ==================== NUEVO ESTILO DE TARJETAS APILADAS ==================== */
.dv-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 10px;
}

.dv-card {
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 14px 18px;
  background: var(--bg-2);
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.dv-card h4 {
  margin: 0 0 8px 0;
  font-size: 15px;
  font-weight: 800;
  color: var(--fg);
  border-bottom: 1px solid var(--line);
  padding-bottom: 4px;
}

.dv-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
  margin: 4px 0;
}

.dv-key {
  color: var(--fg-3);
  min-width: 120px;
}

.dv-val {
  flex: 1;
  text-align: right;
  font-weight: 600;
  color: var(--fg);
  word-break: break-word;
}

/* Alineación izquierda en resumen del documento */
.dv-card, .dv-row, .dv-val {
  text-align: left !important;
}

.dv-val {
  font-weight: 500;
  color: var(--fg);
}
  
  /* Lista de daños en formato limpio */
.dv-list {
  margin: 6px 0 0 0;
  padding-left: 20px;
  list-style-type: "– ";
}
.dv-list li {
  margin-bottom: 2px;
  color: var(--fg);
  line-height: 1.3em;
}
/* Editor de participación */
.top .h1{font-size:23px;letter-spacing:-.025em;margin:0;color:#20324d}
.edit-intro{font-size:13px;line-height:1.6;color:#64748b;margin:6px 0 8px}
#formIV{--edit-ink:#20324d;--edit-muted:#64748b;--edit-line:#e2e8f0;--edit-bg:#fff;--edit-input:#f8fafc;padding:0;border:1px solid var(--edit-line);border-radius:20px;background:var(--edit-bg);box-shadow:0 6px 24px rgba(30,58,112,.07);color:var(--edit-ink);overflow:hidden}
#formIV *{box-sizing:border-box;min-width:0}
#formIV .edit-section{padding:22px 24px}
#formIV .edit-section + .edit-section{border-top:1px solid var(--edit-line)}
#formIV h2{font-size:15px;letter-spacing:-.01em;margin:0 0 18px;color:var(--edit-ink)}
#formIV .grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin-bottom:18px}
#formIV .edit-section:first-of-type .grid{margin-bottom:0}
#formIV label{display:table;max-width:100%;padding:4px 9px;margin:0 0 8px;border:1px solid rgba(148,173,214,.27);border-radius:8px;background:linear-gradient(135deg,rgba(219,234,254,.65),rgba(240,246,255,.3));color:var(--edit-ink);font-size:12px;font-weight:700;line-height:1.4;box-shadow:inset 0 1px rgba(255,255,255,.7);backdrop-filter:blur(8px)}
#formIV :is(input[type=text],select,textarea){display:block;width:100%;max-width:100%;padding:11px 12px;border:1px solid var(--edit-line);border-radius:11px;background:var(--edit-input);color:var(--edit-ink);font:inherit;font-size:13px;line-height:1.5;box-shadow:none}
#formIV :is(input[type=text],select){height:44px;min-height:44px;padding:8px 12px;line-height:normal}
#formIV input[readonly]{color:var(--edit-muted);background:transparent}
#formIV textarea{resize:vertical;min-height:105px}
#formIV :is(input,textarea)::placeholder{color:var(--edit-muted);opacity:1}
#formIV :is(input,select,textarea,button,a):focus-visible{outline:3px solid rgba(59,130,246,.4);outline-offset:2px}
.current-vehicle{display:flex;gap:14px;align-items:center;padding:16px;margin-bottom:22px;border:1px solid rgba(59,130,246,.15);border-radius:14px;background:linear-gradient(120deg,rgba(219,234,254,.5),rgba(240,249,255,.3))}
.current-vehicle>span{display:grid;place-items:center;flex-shrink:0;width:42px;height:42px;border-radius:12px;background:rgba(59,130,246,.1);font-size:23px}
.current-vehicle small{display:block;font-size:11px;color:var(--edit-muted);margin-bottom:5px}
.current-vehicle strong{display:block;font-size:15px;overflow-wrap:anywhere}
#formIV .plate-search{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
#formIV .plate-search input{flex:1 1 130px!important}
#formIV .btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 13px;white-space:nowrap;font-size:12px;font-weight:700;border:1px solid var(--edit-line);border-radius:10px;background:var(--edit-bg);color:var(--edit-ink);box-shadow:none;transition:background .15s,border-color .15s}
#formIV .btn:hover{background:var(--edit-input);border-color:#a8bbd8;transform:none}
#formIV .edit-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap;padding:18px 24px;margin:0;border-top:1px solid var(--edit-line);background:var(--edit-input)}
#formIV .delete-participation{order:-1;margin-right:auto;color:#be3545;border-color:transparent;background:transparent}
#formIV .delete-participation:hover{background:#fff1f2;border-color:#fecdd3}
#formIV .primary{background:linear-gradient(135deg,#2563eb,#4f46e5);color:#fff;border-color:#2563eb;padding-inline:20px;box-shadow:0 3px 9px rgba(37,99,235,.2)}
#formIV .primary:hover{background:#1d4ed8;color:#fff}
@media(max-width:760px){#formIV .grid{grid-template-columns:1fr;gap:18px}#formIV .edit-section{padding:18px}#formIV .edit-actions{padding:16px 18px}}
@media(prefers-color-scheme:dark){#formIV{--edit-ink:#e2e8f0;--edit-muted:#a5b4c8;--edit-line:#334155;--edit-bg:#172233;--edit-input:#111c2b}.top .h1{color:#e2e8f0}.edit-intro{color:#a5b4c8}#formIV label{background:rgba(96,165,250,.1);box-shadow:none}.current-vehicle{background:rgba(59,130,246,.08)}}
@media(prefers-reduced-motion:reduce){#formIV .btn{transition:none}}
</style>
<?php if (\App\Support\CaseParticipantEmbed::active()): ?>
<style>body{margin:0!important;padding:0!important;background:transparent!important}.wrap{display:block!important;max-width:none!important;min-height:0!important;margin:0!important;padding:16px!important}.hdr{display:none!important}</style>
<script src="assets/js/participant-embed.js?v=<?= filemtime(__DIR__ . '/assets/js/participant-embed.js') ?>" defer></script>
<?php endif; ?>
</head>
<body>
<div class="wrap">

  <div class="top">
    <div><h1 class="h1">Editar participación</h1><p class="edit-intro">Actualiza el tipo de participación y las observaciones del vehículo.</p></div>
  </div>

<?php if($err): ?><div class="card warn"><?=h($err)?></div><?php endif; ?>
  <?php if($ok || $msg === 'eliminado'): ?><div class="card soft"><?= $msg === 'eliminado' ? 'Documento del vehiculo eliminado correctamente.' : 'Cambios guardados correctamente.' ?></div><?php endif; ?>

  <form method="post" id="formIV" autocomplete="off" class="card">
    <input type="hidden" name="return" value="<?=h($return)?>">

<section class="edit-section" aria-labelledby="participation-section-title">
    <h2 id="participation-section-title">Participación y observaciones</h2>
    <div class="grid">
      <div>
        <label for="tipo">Tipo de participación</label>
        <select name="tipo" id="tipo" required>
          <?php foreach($tipo_opts as $t): ?>
            <option value="<?=h($t)?>" <?=$iv['tipo']===$t?'selected':''?>><?=h($t)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="veh_resumen">Resumen del vehículo seleccionado</label>
        <input type="text" id="veh_resumen" value="<?=h(vehiculo_text($iv))?>" readonly>
      </div>
    </div>

    <label for="participation-notes">Observaciones</label>
    <textarea id="participation-notes" name="observaciones" rows="3" placeholder="Añade detalles relevantes sobre la participación del vehículo…"><?=h($iv['observaciones'])?></textarea>
    </section>

    <div class="actions edit-actions">
      <?php if (\App\Support\CaseParticipantEmbed::active()): ?>
          <button type="button" class="btn" onclick="parent.postMessage({type:'participant.cancel'}, location.origin)">Cancelar</button>
          <?php else: ?>
<a class="btn" data-participant-cancel href="<?=h($return)?>">Cancelar</a>
          <?php endif; ?>
      <a class="btn delete-participation"
         href="involucrados_vehiculos_eliminar.php?id=<?=$id?>&return_to=<?=urlencode($return)?>"
         onclick="return confirm('¿Eliminar este involucrado?');">Eliminar</a>
      <button class="btn primary" type="submit">Guardar cambios</button>
    </div>
  </form>

</div><!-- /wrap -->


</body>
</html>
