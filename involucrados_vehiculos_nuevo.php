<?php
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

$repo = new InvolucradoVehiculoRepository($pdo);
$service = new InvolucradoVehiculoService($repo);

if (iget('ajax')==='buscar_vehiculos') {
  okjson($service->buscarVehiculos(iget('q','')));
}
if (iget('ajax')==='modelos_por_marca') {
  $marcaId=(int)iget('marca_id',0);
  okjson($repo->modelosPorMarca($marcaId));
}
if (iget('ajax')==='tipos_por_categoria') {
  $catId=(int)iget('categoria_id',0);
  okjson($repo->tiposPorCategoria($catId));
}
if (iget('ajax')==='carrocerias_por_tipo') {
  $tipoId=(int)iget('tipo_id',0);
  okjson($repo->carroceriasPorTipo($tipoId));
}
if (iget('ajax')==='crear_categoria' && $_SERVER['REQUEST_METHOD']==='POST') {
  try{ $item = $service->crearCategoria($_POST); okjson(['ok'=>true,'id'=>$item['id'],'nombre'=>$item['nombre']]); }
  catch(Throwable $e){ okjson(['ok'=>false,'error'=>$e->getMessage()]); }
}
if (iget('ajax')==='crear_tipo' && $_SERVER['REQUEST_METHOD']==='POST') {
  try{ $item = $service->crearTipo($_POST); okjson(['ok'=>true,'id'=>$item['id'],'nombre'=>$item['nombre']]); }
  catch(Throwable $e){ okjson(['ok'=>false,'error'=>$e->getMessage()]); }
}
if (iget('ajax')==='crear_carroceria' && $_SERVER['REQUEST_METHOD']==='POST') {
  try{ $item = $service->crearCarroceria($_POST); okjson(['ok'=>true,'id'=>$item['id'],'nombre'=>$item['nombre']]); }
  catch(Throwable $e){ okjson(['ok'=>false,'error'=>$e->getMessage()]); }
}
if (iget('ajax')==='crear_marca' && $_SERVER['REQUEST_METHOD']==='POST') {
  try{ $item = $service->crearMarca($_POST); okjson(['ok'=>true,'id'=>$item['id'],'nombre'=>$item['nombre']]); }
  catch(Throwable $e){ okjson(['ok'=>false,'error'=>$e->getMessage()]); }
}
if (iget('ajax')==='crear_modelo' && $_SERVER['REQUEST_METHOD']==='POST') {
  try{ $item = $service->crearModelo($_POST); okjson(['ok'=>true,'id'=>$item['id'],'nombre'=>$item['nombre']]); }
  catch(Throwable $e){ okjson(['ok'=>false,'error'=>$e->getMessage()]); }
}
if (iget('ajax')==='crear_vehiculo' && $_SERVER['REQUEST_METHOD']==='POST') {
  try{
    $vehiculo = $service->crearVehiculo($_POST);
    okjson(['ok'=>true,'vehiculo'=>$vehiculo]);
  }catch(Throwable $e){
    okjson(['ok'=>false,'error'=>$e->getMessage()]);
  }
}

$accidentes = $repo->accidentes();
$accidente_id = (int)iget('accidente_id', ($accidentes[0]['id'] ?? 0));
$categorias = $repo->categorias();
$marcas     = $repo->marcas();
$tipos = $carrocerias = $modelos = [];

$err=''; $ok = iget('ok','');
$ut_opts = ['UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7'];
$orden_sugerido = $service->sugerirOrdenParticipacion($accidente_id);
$tipo_opts = ['Unidad','Combinado vehicular 1','Combinado vehicular 2','Fugado'];

if ($_SERVER['REQUEST_METHOD']==='POST' && iget('ajax')===null) {
  try{
    $result = $service->registrar([
      'accidente_id' => $accidente_id,
      'vehiculo_id' => (int)ipost('vehiculo_id', 0),
      'tipo' => ipost('tipo','Unidad'),
      'orden_participacion' => ipost('orden_participacion', $orden_sugerido),
      'observaciones' => ipost('observaciones',''),
      'next' => (int)ipost('next',0),
    ]);
    if($result['next']===1) header('Location: involucrados_vehiculos_nuevo.php?ok=1&accidente_id='.$result['accidente_id']);
    else header('Location: involucrados_vehiculos_listar.php?ok=1&accidente_id='.$result['accidente_id']);
    exit;
  }catch(Throwable $e){
    $err = 'Error al guardar: '.$e->getMessage();
  }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nuevo involucrado – Vehículo | UIAT Norte</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    color-scheme: light dark;
    --bg:#0b0f16; --bg-soft:#0f1520; --card:rgba(255,255,255,.06);
    --stroke:rgba(255,255,255,.12); --text:#e7eaf0; --muted:#9aa3b2;
    --brand:#4f8cff; --brand-2:#9b7bff;
    --okbg:rgba(16,185,129,.12); --okbd:rgba(16,185,129,.35); --oktx:#7ce0b5;
    --errbg:rgba(239,68,68,.12); --errbd:rgba(239,68,68,.35); --errtx:#ffb4b4;
    --shadow:0 10px 30px rgba(0,0,0,.35);
    --radius:14px; --radius-lg:20px;
    --fs:14px; --fs-sm:12px;
  }
  @media (prefers-color-scheme: light){
    :root{ --bg:#f6f7fb; --bg-soft:#eef1f7; --card:rgba(255,255,255,.8); --stroke:rgba(0,0,0,.08); --text:#1f2937; --muted:#6b7280; --shadow:0 6px 22px rgba(0,0,0,.08); }
  }
  html,body{height:100%}
  body{
    margin:0; font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Helvetica Neue", sans-serif;
    font-size:var(--fs); color:var(--text);
    background:
      radial-gradient(1200px 600px at 5% 0%, rgba(79,140,255,.18), transparent 60%),
      radial-gradient(1000px 600px at 95% 0%, rgba(155,123,255,.18), transparent 60%),
      linear-gradient(180deg, var(--bg) 0%, var(--bg-soft) 100%);
  }
  .wrap{ max-width:1180px; margin:18px auto 28px auto; padding:0 14px; }

  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
  .title{ font-size:20px; font-weight:800; letter-spacing:.2px; }

  .card{
    background:var(--card); border:1px solid var(--stroke);
    border-radius:var(--radius-lg); box-shadow:var(--shadow);
    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    padding:12px; margin-bottom:12px; position:relative;
  }
  .card:before{
    content:""; position:absolute; inset:-1px; border-radius:inherit; pointer-events:none;
    background: radial-gradient(500px 120px at 20px -20px, rgba(79,140,255,.18), transparent 60%),
                radial-gradient(500px 120px at calc(100% - 20px) -20px, rgba(155,123,255,.18), transparent 60%);
    filter: blur(14px); opacity:.8; z-index:-1;
  }

  .grid-3{ display:grid; gap:10px; grid-template-columns: 1fr 1fr 1fr; }
  .row{ display:grid; gap:10px; grid-template-columns: 1fr 1fr; }
  .row3{ display:grid; gap:10px; grid-template-columns: 1fr 1fr 1fr; }
  .grid-4{ display:grid; gap: 10px; grid-template-columns: repeat(4, 1fr); }
  @media (max-width:980px){ .grid-3,.row,.row3,.grid-4{ grid-template-columns:1fr; } }

  label{ display:block; margin:0 0 6px 2px; color:var(--muted); font-size:var(--fs-sm); font-weight:700; }
  select,input[type="text"],input[type="number"],textarea{
    width:100%; padding:8px 10px; border-radius:12px; border:1px solid var(--stroke);
    background: rgba(255,255,255,.04); color:var(--text); outline:none;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.05);
  }
  select:focus,input:focus,textarea:focus{ border-color: rgba(79,140,255,.55); box-shadow: 0 0 0 3px rgba(79,140,255,.18); }

  .inline{ display:flex; gap:8px; align-items:center; }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; margin-top:10px; }

  .btn{
    appearance:none; border:none; outline:none; text-decoration:none; cursor:pointer;
    padding:8px 12px; border-radius:12px;
    background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.02));
    border:1px solid var(--stroke); color:var(--text); font-weight:800; letter-spacing:.2px;
    transition:.15s ease; box-shadow: inset 0 0 0 1px rgba(255,255,255,.03);
  }
  .btn:hover{ transform: translateY(-1px); box-shadow: var(--shadow); }
  .btn.primary{
    background:
      linear-gradient(180deg, rgba(79,140,255,.7), rgba(79,140,255,.45)),
      linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.02));
    border-color: rgba(79,140,255,.55); color:white;
  }
  .btn.safe{
    background: linear-gradient(180deg, rgba(16,185,129,.65), rgba(16,185,129,.45));
    border-color: rgba(16,185,129,.5); color:white;
  }
  .btn.mini{ padding:6px 10px; font-size:var(--fs-sm); border-radius:10px; }

  .ok{ background: var(--okbg); border:1px solid var(--okbd); color: var(--oktx);
       padding:8px 10px; border-radius:12px; margin-bottom:10px; font-weight:700; }
  .err{ background: var(--errbg); border:1px solid var(--errbd); color: var(--errtx);
       padding:8px 10px; border-radius:12px; margin-bottom:10px; font-weight:700; }
  .muted{ color:var(--muted); font-size:var(--fs-sm); }

  /* Modal: overlay */
.modal { background: rgba(0,0,0,.55); }

/* Contenedor del modal */
.modal .box{
  width:min(100%, 980px);
  background: rgba(22,26,34,.96);          /* + opaco = mejor contraste */
  color: #f1f4fa;                           /* texto claro */
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 20px;
  box-shadow: 0 18px 50px rgba(0,0,0,.45);
  backdrop-filter: blur(10px) saturate(120%);
  -webkit-backdrop-filter: blur(10px) saturate(120%);
  padding: 14px;
}

/* Títulos y labels dentro del modal */
.modal .box h3{ color:#ffffff; font-weight:800; }
.modal .box label{ color:#cdd6e8 !important; font-weight:700; }

/* Campos del modal */
.modal .box input[type="text"],
.modal .box input[type="number"],
.modal .box select,
.modal .box textarea{
  background: rgba(255,255,255,.06);        /* sutil pero visible */
  color:#ffffff;
  border:1px solid rgba(255,255,255,.18);
  border-radius:12px;
}

/* Enfoque de campos */
.modal .box input:focus,
.modal .box select:focus,
.modal .box textarea:focus{
  border-color: rgba(79,140,255,.75);
  box-shadow: 0 0 0 3px rgba(79,140,255,.25);
}

/* Placeholders visibles */
.modal .box ::placeholder{ color:#b9c4da; opacity:1; }

/* Opciones del select (evita texto gris oscuro en algunos navegadores) */
.modal .box select option{ color:#111; }
@media (prefers-color-scheme: dark){
  .modal .box select option{ color:#f1f4fa; background:#141821; }
}

/* Mensajes y texto tenue dentro del modal */
.modal .box .muted{ color:#a8b2c5; }

/* Botones del modal (mantén tu estilo) */
.modal .box .btn.primary{ color:#fff; }
  *, *::before, *::after { box-sizing: border-box; }
  
  /* === FIX modal: z-index, contraste y scroll interno === */
.modal{ 
  z-index: 9999 !important;                 /* siempre encima */
  background: rgba(0,0,0,.58) !important;   /* overlay */
}
.modal.show{ display:flex !important; }

.modal .box{
  position: relative;
  z-index: 10000;
  /* glass con buen contraste (dark / light) */
  background: rgba(22, 26, 34, .94);        /* dark por defecto */
  color: #f4f7fd;
  border: 1px solid rgba(255,255,255,.14);
  backdrop-filter: blur(10px) saturate(120%);
  -webkit-backdrop-filter: blur(10px) saturate(120%);
  box-shadow: 0 18px 50px rgba(0,0,0,.45);
  max-height: 86vh;                         /* evita desbordes */
  overflow: auto;
}

/* labels y placeholders legibles */
.modal .box h3{ color:#fff; font-weight:800; }
.modal .box label{ color:#cfd8ea !important; font-weight:700; }
.modal .box ::placeholder{ color:#c1cbe0; opacity:1; }

/* campos */
.modal .box input[type="text"],
.modal .box input[type="number"],
.modal .box select,
.modal .box textarea{
  background: rgba(255,255,255,.07);
  color:#fff;
  border:1px solid rgba(255,255,255,.18);
  border-radius:12px;
}
.modal .box input:focus,
.modal .box select:focus,
.modal .box textarea:focus{
  border-color: rgba(79,140,255,.75);
  box-shadow: 0 0 0 3px rgba(79,140,255,.25);
}

/* modo claro coherente */
@media (prefers-color-scheme: light){
  .modal .box{
    background: rgba(255,255,255,.98);
    color:#1f2937;
    border:1px solid rgba(0,0,0,.08);
  }
  .modal .box input[type="text"],
  .modal .box input[type="number"],
  .modal .box select,
  .modal .box textarea{
    background:#fff;
    color:#111;
    border:1px solid #d6d9e0;
  }
  .modal .box ::placeholder{ color:#64748b; }
}
  
  /* ==== MODAL (overlay real, con scroll lock y z-index alto) ==== */
.modal{
  position: fixed !important;
  inset: 0 !important;
  display: none;                 /* oculto por defecto */
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(0,0,0,.58);   /* overlay */
  z-index: 9999;                 /* por encima de todo */
}

.modal.show{ display:flex !important; }

.modal .box{
  width: min(100%, 980px);
  max-height: 86vh;              /* si hay mucho contenido, hace scroll interno */
  overflow: auto;
  position: relative;
  z-index: 10000;                /* la caja por encima del overlay */
  border-radius: 20px;
  border: 1px solid rgba(0,0,0,.08);
  box-shadow: 0 18px 50px rgba(0,0,0,.35);
  /* Apariencia en light */
  background: rgba(255,255,255,.98);
  color: #1f2937;
  backdrop-filter: blur(10px) saturate(120%);
  -webkit-backdrop-filter: blur(10px) saturate(120%);
}

/* Apariencia en dark */
@media (prefers-color-scheme: dark){
  .modal .box{
    background: rgba(22,26,34,.96);
    color: #f4f7fd;
    border: 1px solid rgba(255,255,255,.14);
  }
}

/* Campos legibles dentro del modal */
.modal .box label{ color:#5b6476; font-weight:700; }
@media (prefers-color-scheme: dark){ .modal .box label{ color:#cfd8ea; } }

.modal .box input[type="text"],
.modal .box input[type="number"],
.modal .box select,
.modal .box textarea{
  background:#fff;
  color:#111;
  border:1px solid #d6d9e0;
  border-radius:12px;
}
@media (prefers-color-scheme: dark){
  .modal .box input[type="text"],
  .modal .box input[type="number"],
  .modal .box select,
  .modal .box textarea{
    background: rgba(255,255,255,.07);
    color:#fff;
    border:1px solid rgba(255,255,255,.18);
  }
}
.modal .box ::placeholder{ color:#94a3b8; }
@media (prefers-color-scheme: dark){ .modal .box ::placeholder{ color:#c1cbe0; } }

/* Evita que el resto de la página se mueva cuando el modal está abierto */
body.modal-open{ overflow: hidden !important; }

/* ==== Ajuste visual fino del modal ==== */

/* Labels visibles y suaves */
.modal .box label{
  color: #3b4253 !important;
  font-weight: 600;
  font-size: 13px;
  letter-spacing: .2px;
}
@media (prefers-color-scheme: dark){
  .modal .box label{
    color: #dce3f5 !important;
  }
}

/* Título del modal */
.modal .box h3{
  color: #1e293b;
  font-size: 18px;
  font-weight: 800;
  margin-bottom: 10px;
}
@media (prefers-color-scheme: dark){
  .modal .box h3{
    color: #ffffff;
  }
}

/* Inputs con estética moderna */
.modal .box input,
.modal .box select,
.modal .box textarea{
  border-radius: 10px;
  transition: all .2s ease;
}
.modal .box input:focus,
.modal .box select:focus,
.modal .box textarea:focus{
  border-color: #4f8cff;
  box-shadow: 0 0 0 3px rgba(79,140,255,.25);
}

/* === Botones dentro del modal === */
.modal .box .btn{
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  letter-spacing: .3px;
  padding: 7px 14px;
}

/* Botón primario (Guardar vehículo) */
.modal .box .btn.primary{
  background: linear-gradient(180deg, #4f8cff, #4175e6);
  border: none;
  color: #fff;
  box-shadow: 0 2px 6px rgba(79,140,255,.4);
}
.modal .box .btn.primary:hover{
  background: linear-gradient(180deg, #6296ff, #4f8cff);
  transform: translateY(-1px);
}

/* Botón secundario (Cancelar) */
.modal .box .btn:not(.primary){
  background: #e9ecf5;
  color: #1e293b;
  border: 1px solid #d3d8e5;
}
.modal .box .btn:not(.primary):hover{
  background: #dbe1f0;
}

/* Modo oscuro coherente */
@media (prefers-color-scheme: dark){
  .modal .box .btn:not(.primary){
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.15);
    color: #f1f4fa;
  }
  .modal .box .btn:not(.primary):hover{
    background: rgba(255,255,255,.14);
  }
}


</style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <div class="title">Nuevo involucrado – Vehículo</div>
    <div class="inline">
      <a class="btn" href="involucrados_vehiculos_listar.php?accidente_id=<?=h($accidente_id)?>">📋 Volver al listado</a>
    </div>
  </div>

  <?php if($ok): ?><div class="ok">Guardado correctamente.</div><?php endif; ?>
  <?php if($err): ?><div class="err"><?=h($err)?></div><?php endif; ?>

  <form method="post" class="card" autocomplete="off" id="formIV">
    <input type="hidden" name="next" id="next" value="0">

    <div class="row">
      <div>
        <label>Accidente</label>
        <select name="accidente_id" id="accidente_id" required>
          <option value="">— Seleccionar —</option>
          <?php foreach($accidentes as $a): ?>
            <option value="<?=$a['id']?>" <?=($accidente_id==$a['id']?'selected':'')?>><?=h($a['nom'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Búsqueda por placa</label>
        <div class="inline">
          <input type="text" id="qplaca" placeholder="Ej. ABC123">
          <button class="btn mini" type="button" id="btnBuscarPlaca">Buscar</button>
          <button class="btn mini" type="button" id="btnNuevoVehiculo" data-open="mdVehiculo">＋ Nuevo vehículo</button>
        </div>
      </div>
    </div>

    <div class="row3" style="margin-top:6px;">
      <div>
        <label>Vehículo</label>
        <select name="vehiculo_id" id="vehiculo_id" required>
          <option value="">— Selecciona de la búsqueda —</option>
        </select>
      </div>

      <div>
        <label>Tipo de participación</label>
        <select name="tipo" id="tipo" required>
          <?php foreach($tipo_opts as $t): ?>
            <option value="<?=h($t)?>" <?=($t==='Unidad' ? 'selected' : '')?>><?=h($t)?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- NUEVO: orden_participacion -->
      <div>
        <label>Orden de participación</label>
        <select name="orden_participacion" id="orden_participacion" required>
          <?php foreach($ut_opts as $u): ?>
            <option value="<?=$u?>" <?=($u===$orden_sugerido?'selected':'')?>><?=$u?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row" style="margin-top:6px;">
      <div>
        <label>Resumen</label>
        <input type="text" id="veh_resumen" placeholder="Auto" readonly>
      </div>
      <div></div>
    </div>

    <label style="margin-top:6px">Observaciones</label>
    <textarea name="observaciones" rows="3" placeholder="Notas breves…"></textarea>

    <div class="actions">
      <a class="btn" href="involucrados_vehiculos_listar.php?accidente_id=<?=h($accidente_id)?>">Cancelar</a>
      <button class="btn primary" type="submit" id="btnGuardar">Guardar</button>
      <button class="btn safe" type="submit" id="btnNext">Agregar siguiente vehículo</button>
    </div>
  </form>

  <!-- =================== MODAL NUEVO VEHÍCULO =================== -->
  <div class="modal" id="mdVehiculo">
    <div class="box">
      <h3 style="margin:0 0 8px 0">Registrar nuevo vehículo</h3>

      <div class="grid-4">
        <div><label>Placa *</label><input type="text" id="nv_placa" placeholder="ABC123"></div>
        <div><label>Año</label><input type="number" id="nv_anio" min="1900" max="2100"></div>
        <div><label>Color</label><input type="text" id="nv_color"></div>
        <div></div>
      </div>

      <div class="grid-4">
        <div>
          <label>Categoría</label>
          <div class="inline">
            <select id="nv_categoria_id">
              <option value="">—</option>
              <?php foreach($categorias as $c): ?><option value="<?=$c['id']?>"><?=h($c['nombre'])?></option><?php endforeach; ?>
            </select>
            <button class="btn mini" type="button" data-open="mdCategoria">＋</button>
          </div>
        </div>
        <div>
          <label>Tipo</label>
          <div class="inline">
            <select id="nv_tipo_id"><option value="">—</option></select>
            <button class="btn mini" type="button" data-open="mdTipo">＋</button>
          </div>
        </div>
        <div>
          <label>Carrocería</label>
          <div class="inline">
            <select id="nv_carroceria_id"><option value="">—</option></select>
            <button class="btn mini" type="button" data-open="mdCarroceria">＋</button>
          </div>
        </div>
        <div></div>
      </div>

      <div class="grid-4">
        <div>
          <label>Marca</label>
          <div class="inline">
            <select id="nv_marca_id">
              <option value="">—</option>
              <?php foreach($marcas as $m): ?><option value="<?=$m['id']?>"><?=h($m['nombre'])?></option><?php endforeach; ?>
            </select>
            <button class="btn mini" type="button" data-open="mdMarca">＋</button>
          </div>
        </div>
        <div>
          <label>Modelo</label>
          <div class="inline">
            <select id="nv_modelo_id"><option value="">—</option></select>
            <button class="btn mini" type="button" data-open="mdModelo">＋</button>
          </div>
        </div>
        <div><label>Largo (m)</label><input type="number" step="0.01" id="nv_largo_mm"></div>
        <div><label>Ancho (m)</label><input type="number" step="0.01" id="nv_ancho_mm"></div>
      </div>

      <div class="grid-4">
        <div><label>Alto (m)</label><input type="number" step="0.01" id="nv_alto_mm"></div>
        <div style="grid-column: 1 / -1;">
          <label>Notas</label><textarea id="nv_notas" rows="3"></textarea>
        </div>
      </div>

      <div class="actions">
        <button class="btn" type="button" data-close="mdVehiculo">Cancelar</button>
        <button class="btn primary" type="button" id="btnGuardarNV">Guardar vehículo</button>
      </div>
      <div id="nv_msg" class="muted"></div>
    </div>
  </div>

  <!-- =================== MODALES DE CATÁLOGO =================== -->
  <div class="modal" id="mdCategoria"><div class="box">
    <h3 style="margin:0 0 8px 0">Nueva categoría</h3>
    <div class="row"><div><label>Código *</label><input id="cat_codigo"></div><div><label>Descripción *</label><input id="cat_desc"></div></div>
    <div class="actions"><button class="btn" type="button" data-close="mdCategoria">Cancelar</button><button class="btn primary" type="button" id="btnSaveCat">Guardar</button></div>
    <div id="cat_msg" class="muted"></div>
  </div></div>

  <div class="modal" id="mdTipo"><div class="box">
    <h3 style="margin:0 0 8px 0">Nuevo tipo</h3>
    <div class="row">
      <div>
        <label>Categoría *</label>
        <select id="t_cat">
          <option value="">—</option>
          <?php foreach($categorias as $c): ?><option value="<?=$c['id']?>"><?=h($c['nombre'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Código *</label><input id="t_codigo"></div>
    </div>
    <div class="row"><div><label>Nombre *</label><input id="t_nombre"></div><div><label>Descripción</label><input id="t_desc"></div></div>
    <div class="actions"><button class="btn" type="button" data-close="mdTipo">Cancelar</button><button class="btn primary" type="button" id="btnSaveTipo">Guardar</button></div>
    <div id="t_msg" class="muted"></div>
  </div></div>

  <div class="modal" id="mdCarroceria"><div class="box">
    <h3 style="margin:0 0 8px 0">Nueva carrocería</h3>
    <div class="row">
      <div><label>Tipo *</label><select id="c_tipo"><option value="">—</option></select></div>
      <div><label>Nombre *</label><input id="c_nombre"></div>
    </div>
    <div><label>Descripción</label><input id="c_desc"></div>
    <div class="actions"><button class="btn" type="button" data-close="mdCarroceria">Cancelar</button><button class="btn primary" type="button" id="btnSaveCarroceria">Guardar</button></div>
    <div id="c_msg" class="muted"></div>
  </div></div>

  <div class="modal" id="mdMarca"><div class="box">
    <h3 style="margin:0 0 8px 0">Nueva marca</h3>
    <div class="row"><div><label>Nombre *</label><input id="m_nombre"></div><div><label>País de origen</label><input id="m_pais"></div></div>
    <div class="actions"><button class="btn" type="button" data-close="mdMarca">Cancelar</button><button class="btn primary" type="button" id="btnSaveMarca">Guardar</button></div>
    <div id="m_msg" class="muted"></div>
  </div></div>

  <div class="modal" id="mdModelo"><div class="box">
    <h3 style="margin:0 0 8px 0">Nuevo modelo</h3>
    <div class="row">
      <div>
        <label>Marca *</label>
        <select id="mo_marca">
          <option value="">—</option>
          <?php foreach($marcas as $m): ?><option value="<?=$m['id']?>"><?=h($m['nombre'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Nombre *</label><input id="mo_nombre"></div>
    </div>
    <div class="actions"><button class="btn" type="button" data-close="mdModelo">Cancelar</button><button class="btn primary" type="button" id="btnSaveModelo">Guardar</button></div>
    <div id="mo_msg" class="muted"></div>
  </div></div>

</div><!-- /wrap -->

<script>
/* ==== Utiles UI ==== */
const $$ = (s,ctx=document)=>ctx.querySelector(s);
const $$$ = (s,ctx=document)=>Array.from(ctx.querySelectorAll(s));

function openModal(id){ $$('#'+id)?.classList.add('show'); }
function closeModal(id){ $$('#'+id)?.classList.remove('show'); }
$$$('[data-open]').forEach(b=>b.addEventListener('click',e=>openModal(e.currentTarget.dataset.open)));
$$$('[data-close]').forEach(b=>b.addEventListener('click',e=>closeModal(e.currentTarget.dataset.close)));
window.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    $$$('.modal.show').forEach(m => m.classList.remove('show'));
  }
});

/* ==== Buscar por placa ==== */
$('#btnBuscarPlaca')?.addEventListener('click', async ()=>{
  const q = $('#qplaca').value.trim();
  const sel = $('#vehiculo_id');
  sel.innerHTML = '<option value="">Buscando…</option>';
  const r = await fetch(`?ajax=buscar_vehiculos&q=${encodeURIComponent(q)}`);
  const j = await r.json();
  sel.innerHTML = '<option value="">— Selecciona de la búsqueda —</option>';
  j.forEach(it=>{
    const o=document.createElement('option'); o.value=it.id; o.textContent=it.texto; sel.appendChild(o);
  });
});

/* Resumen vehículo */
$('#vehiculo_id')?.addEventListener('change', ()=>{
  const t = $('#vehiculo_id').selectedOptions[0]?.textContent || '';
  $('#veh_resumen').value = t;
});

/* ==== Encadenados catálogos (modal nuevo vehículo) ==== */
$('#nv_categoria_id')?.addEventListener('change', async (e)=>{
  const v = e.target.value || '';
  const sel = $('#nv_tipo_id'); const selc = $('#nv_carroceria_id');
  sel.innerHTML = '<option value="">Cargando…</option>'; selc.innerHTML='<option value="">—</option>';
  if(!v){ sel.innerHTML='<option value="">—</option>'; return; }
  const r = await fetch(`?ajax=tipos_por_categoria&categoria_id=${encodeURIComponent(v)}`);
  const j = await r.json();
  sel.innerHTML = '<option value="">—</option>';
  j.forEach(it=>{ const o=document.createElement('option'); o.value=it.id; o.textContent=it.nombre; sel.appendChild(o); });
});
$('#nv_tipo_id')?.addEventListener('change', async (e)=>{
  const v = e.target.value || '';
  const sel = $('#nv_carroceria_id');
  sel.innerHTML = '<option value="">Cargando…</option>';
  if(!v){ sel.innerHTML='<option value="">—</option>'; return; }
  const r = await fetch(`?ajax=carrocerias_por_tipo&tipo_id=${encodeURIComponent(v)}`);
  const j = await r.json();
  sel.innerHTML = '<option value="">—</option>';
  j.forEach(it=>{ const o=document.createElement('option'); o.value=it.id; o.textContent=it.nombre; sel.appendChild(o); });
});
$('#nv_marca_id')?.addEventListener('change', async (e)=>{
  const v = e.target.value || '';
  const sel = $('#nv_modelo_id');
  sel.innerHTML = '<option value="">Cargando…</option>';
  if(!v){ sel.innerHTML='<option value="">—</option>'; return; }
  const r = await fetch(`?ajax=modelos_por_marca&marca_id=${encodeURIComponent(v)}`);
  const j = await r.json();
  sel.innerHTML = '<option value="">—</option>';
  j.forEach(it=>{ const o=document.createElement('option'); o.value=it.id; o.textContent=it.nombre; sel.appendChild(o); });
});

/* ==== Altas rápidas de catálogo ==== */
async function postForm(url, data){
  const r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:new URLSearchParams(data)});
  return r.json();
}
$('#btnSaveCat')?.addEventListener('click', async ()=>{
  const codigo=$('#cat_codigo').value.trim(), descripcion=$('#cat_desc').value.trim();
  const j = await postForm('?ajax=crear_categoria',{codigo,descripcion});
  $('#cat_msg').textContent = j.ok? 'Creado ✔':'Error: '+j.error;
  if(j.ok){ const s=$('#nv_categoria_id'); const o=document.createElement('option'); o.value=j.id; o.textContent=j.nombre; s.appendChild(o); s.value=j.id; }
});
$('#btnSaveTipo')?.addEventListener('click', async ()=>{
  const categoria_id=$('#t_cat').value, codigo=$('#t_codigo').value.trim(), nombre=$('#t_nombre').value.trim(), descripcion=$('#t_desc').value.trim();
  const j = await postForm('?ajax=crear_tipo',{categoria_id,codigo,nombre,descripcion});
  $('#t_msg').textContent = j.ok? 'Creado ✔':'Error: '+j.error;
  if(j.ok){ const s=$('#nv_tipo_id'); const o=document.createElement('option'); o.value=j.id; o.textContent=j.nombre; s.appendChild(o); s.value=j.id; }
});
$('#btnSaveCarroceria')?.addEventListener('click', async ()=>{
  const tipo_id=$('#c_tipo').value, nombre=$('#c_nombre').value.trim(), descripcion=$('#c_desc').value.trim();
  const j = await postForm('?ajax=crear_carroceria',{tipo_id,nombre,descripcion});
  $('#c_msg').textContent = j.ok? 'Creado ✔':'Error: '+j.error;
  if(j.ok){ const s=$('#nv_carroceria_id'); const o=document.createElement('option'); o.value=j.id; o.textContent=j.nombre; s.appendChild(o); s.value=j.id; }
});
$('#btnSaveMarca')?.addEventListener('click', async ()=>{
  const nombre=$('#m_nombre').value.trim(), pais_origen=$('#m_pais').value.trim();
  const j = await postForm('?ajax=crear_marca',{nombre,pais_origen});
  $('#m_msg').textContent = j.ok? 'Creado ✔':'Error: '+j.error;
  if(j.ok){ const s=$('#nv_marca_id'); const o=document.createElement('option'); o.value=j.id; o.textContent=j.nombre; s.appendChild(o); s.value=j.id; }
});
$('#btnSaveModelo')?.addEventListener('click', async ()=>{
  const marca_id=$('#mo_marca').value, nombre=$('#mo_nombre').value.trim();
  const j = await postForm('?ajax=crear_modelo',{marca_id,nombre});
  $('#mo_msg').textContent = j.ok? 'Creado ✔':'Error: '+j.error;
  if(j.ok){ const s=$('#nv_modelo_id'); const o=document.createElement('option'); o.value=j.id; o.textContent=j.nombre; s.appendChild(o); s.value=j.id; }
});

/* ==== Guardar nuevo vehículo (modal) ==== */
$('#btnGuardarNV')?.addEventListener('click', async ()=>{
  const data = {
    placa: $('#nv_placa').value.trim(),
    anio: $('#nv_anio').value,
    color: $('#nv_color').value.trim(),
    categoria_id: $('#nv_categoria_id').value,
    tipo_id: $('#nv_tipo_id').value,
    carroceria_id: $('#nv_carroceria_id').value,
    marca_id: $('#nv_marca_id').value,
    modelo_id: $('#nv_modelo_id').value,
    largo_mm: $('#nv_largo_mm').value,
    ancho_mm: $('#nv_ancho_mm').value,
    alto_mm:  $('#nv_alto_mm').value,
    notas: $('#nv_notas').value
  };
  const j = await postForm('?ajax=crear_vehiculo', data);
  const msg = $('#nv_msg');
  if(j.ok){
    msg.textContent = 'Vehículo creado ✔';
    const sel = $('#vehiculo_id');
    const o = document.createElement('option'); o.value=j.vehiculo.id; o.textContent=j.vehiculo.texto;
    sel.appendChild(o); sel.value = j.vehiculo.id; sel.dispatchEvent(new Event('change'));
    setTimeout(()=>closeModal('mdVehiculo'), 350);
  }else{
    msg.textContent = 'Error: '+j.error;
  }
});

/* ==== Botones guardar ==== */
$('#btnNext')?.addEventListener('click', ()=>{ $('#next').value='1'; });
$('#btnGuardar')?.addEventListener('click', ()=>{ $('#next').value='0'; });

/* ==== Poblar select "Tipo" del modal Carrocería ==== */
$('#nv_tipo_id')?.addEventListener('change', ()=>{
  const v = $('#nv_tipo_id').value;
  $('#c_tipo').innerHTML = v ? `<option value="${v}">Seleccionado</option>` : '<option value="">—</option>';
});

/* helper */
function $(sel){ return document.querySelector(sel); }
</script>
</body>
</html>
