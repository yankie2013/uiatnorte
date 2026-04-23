<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\AccidenteRepository;
use App\Services\AccidenteService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function json_out($a){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($a,JSON_UNESCAPED_UNICODE); exit; }

$accidenteRepo = new AccidenteRepository($pdo);
$accidenteService = new AccidenteService($accidenteRepo);

$buildContext = function(array $acc) use ($accidenteRepo): array {
  $provs = $acc['cod_dep'] ? $accidenteRepo->provinciasByDepartamento((string)$acc['cod_dep']) : [];
  $dists = ($acc['cod_dep'] && $acc['cod_prov'])
    ? $accidenteRepo->distritos((string)$acc['cod_dep'], (string)$acc['cod_prov'])
    : [];
  $comis = ($acc['cod_dep'] && $acc['cod_prov'] && $acc['cod_dist'])
    ? $accidenteRepo->comisariasByDistrito((string)$acc['cod_dep'], (string)$acc['cod_prov'], (string)$acc['cod_dist'])
    : [];

  $comisIds = is_array($comis)
    ? array_map(function($x){ return (int)$x['id']; }, $comis)
    : [];
  if (!empty($acc['comisaria_id']) && !in_array((int)$acc['comisaria_id'], $comisIds, true)) {
    $row = $accidenteRepo->comisariaById((int)$acc['comisaria_id']);
    if ($row) {
      $row['_fuera'] = 1;
      array_unshift($comis, $row);
    }
  }

  $fiscales = !empty($acc['fiscalia_id'])
    ? $accidenteRepo->fiscalesByFiscalia((int)$acc['fiscalia_id'])
    : [];
  $fiscalTelData = !empty($acc['fiscal_id'])
    ? $accidenteRepo->fiscalTelefono((int)$acc['fiscal_id'])
    : [];

  return [
    'provs' => $provs,
    'dists' => $dists,
    'comis' => $comis,
    'fiscales_de_fiscalia' => $fiscales,
    'fiscal_tel' => (string)($fiscalTelData['telefono'] ?? ''),
  ];
};

if (isset($_GET['ajax'])) {
  $a = $_GET['ajax'];

  if ($a==='prov'){
    $dep = substr($_GET['dep']??'',0,2);
    json_out(['ok'=>true,'data'=>$accidenteRepo->provinciasByDepartamento($dep)]);
  }

  if ($a==='dist'){
    $dep=substr($_GET['dep']??'',0,2);
    $prov=substr($_GET['prov']??'',0,2);
    json_out(['ok'=>true,'data'=>$accidenteRepo->distritos($dep,$prov)]);
  }

  if ($a==='comisarias_dist'){
    $dep  = substr($_GET['dep']??'',0,2);
    $prov = substr($_GET['prov']??'',0,2);
    $dist = substr($_GET['dist']??'',0,2);
    json_out(['ok'=>true,'data'=>$accidenteRepo->comisariasByDistrito($dep,$prov,$dist)]);
  }

  if ($a==='fiscales'){
    $fid = (int)($_GET['fiscalia_id'] ?? 0);
    json_out(['ok'=>true,'data'=>$accidenteRepo->fiscalesByFiscalia($fid)]);
  }

  if ($a==='fiscal_info'){
    $id = (int)($_GET['fiscal_id'] ?? 0);
    json_out(['ok'=>true,'data'=>$accidenteRepo->fiscalTelefono($id)]);
  }

  if ($a==='create'){
    $type=$_GET['type']??'';
    try{
      if ($type==='comisaria') {
        json_out(['ok'=>true] + $accidenteService->createComisaria($_POST));
      }

      if ($type==='fiscal') {
        json_out(['ok'=>true] + $accidenteService->createFiscal($_POST));
      }

      json_out(['ok'=>true] + $accidenteService->createSimpleCatalog($type, trim($_POST['nombre']??'')));
    }catch(Throwable $e){
      json_out(['ok'=>false,'msg'=>$e->getMessage()]);
    }
  }

  json_out(['ok'=>false,'msg'=>'Acciï¿½n no reconocida']);
}

$accidente_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($accidente_id<=0){ header('Location: accidente_listar.php'); exit; }

$deps          = $accidenteRepo->departamentos();
$fiscalias     = $accidenteRepo->fiscalias();
$modalidades   = $accidenteRepo->modalidades();
$consecuencias = $accidenteRepo->consecuencias();

$acc = $accidenteRepo->accidenteById($accidente_id);
if(!$acc){ header('Location: accidente_listar.php'); exit; }

$sidpol_url = $acc['sidpol'] ?? '';
$mod_sel = $accidenteRepo->modalidadIdsForAccidente($accidente_id);
$con_sel = $accidenteRepo->consecuenciaIdsForAccidente($accidente_id);

$context = $buildContext($acc);
$provs = $context['provs'];
$dists = $context['dists'];
$comis = $context['comis'];
$fiscales_de_fiscalia = $context['fiscales_de_fiscalia'];
$fiscal_tel = $context['fiscal_tel'];

$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $registro_sidpol=trim($_POST['registro_sidpol']??'');
  $tipo_registro=trim($_POST['tipo_registro']??'');
  $lugar=trim($_POST['lugar']??'');
  $referencia=trim($_POST['referencia']??'');
  $cod_dep=substr($_POST['cod_dep']??'',0,2);
  $cod_prov=substr($_POST['cod_prov']??'',0,2);
  $cod_dist=substr($_POST['cod_dist']??'',0,2);
  $comisaria_id = $_POST['comisaria_id']!=='' ? (int)$_POST['comisaria_id'] : null;

  $fecha_accidente = trim($_POST['fecha_accidente']??'');
  $fecha_comunicacion = trim($_POST['fecha_comunicacion']??'');
  $fecha_intervencion = trim($_POST['fecha_intervencion']??'');

  $comunicante_nombre=trim($_POST['comunicante_nombre']??'');
  $comunicante_telefono=trim($_POST['comunicante_telefono']??'');
  $comunicacion_decreto=trim($_POST['comunicacion_decreto']??'');
  $comunicacion_oficio=trim($_POST['comunicacion_oficio']??'');
  $comunicacion_carpeta_nro=trim($_POST['comunicacion_carpeta_nro']??'');

  $fiscalia_id = $_POST['fiscalia_id']!=='' ? (int)$_POST['fiscalia_id'] : null;
  $fiscal_id   = $_POST['fiscal_id']!=='' ? (int)$_POST['fiscal_id'] : null;

  $nro_informe=trim($_POST['nro_informe_policial']??'');
  $sentido=trim($_POST['sentido']??'');
  $secuencia=trim($_POST['secuencia']??'');

  $modalidad_ids    = array_map('intval', $_POST['modalidad_ids'] ?? []);
  $consecuencia_ids = array_map('intval', $_POST['consecuencia_ids'] ?? []);

  $estado = $_POST['estado'] ?? 'Pendiente';

  try{
    $result = $accidenteService->updateAccidente($accidente_id, [
      'registro_sidpol' => $registro_sidpol,
      'tipo_registro' => $tipo_registro,
      'lugar' => $lugar,
      'referencia' => $referencia,
      'cod_dep' => $cod_dep,
      'cod_prov' => $cod_prov,
      'cod_dist' => $cod_dist,
      'comisaria_id' => $comisaria_id,
      'fecha_accidente' => $fecha_accidente,
      'fecha_comunicacion' => $fecha_comunicacion,
      'fecha_intervencion' => $fecha_intervencion,
      'comunicante_nombre' => $comunicante_nombre,
      'comunicante_telefono' => $comunicante_telefono,
      'comunicacion_decreto' => $comunicacion_decreto,
      'comunicacion_oficio' => $comunicacion_oficio,
      'comunicacion_carpeta_nro' => $comunicacion_carpeta_nro,
      'fiscalia_id' => $fiscalia_id,
      'fiscal_id' => $fiscal_id,
      'nro_informe_policial' => $nro_informe,
      'sentido' => $sentido,
      'secuencia' => $secuencia,
      'modalidad_ids' => $modalidad_ids,
      'consecuencia_ids' => $consecuencia_ids,
      'estado' => $estado,
    ]);

    $base = rtrim(dirname($_SERVER['PHP_SELF']),'/').'/';
    header("Location: {$base}accidente_editar.php?id={$result['id']}&sidpol=".urlencode($result['sidpol'])."&ok=1");
    exit;
  }catch(Throwable $e){
    $err='Error al guardar: '.$e->getMessage();
  }

  $acc = array_merge($acc, [
    'registro_sidpol'=>$registro_sidpol,
    'tipo_registro'=>$tipo_registro,
    'lugar'=>$lugar,
    'referencia'=>$referencia,
    'cod_dep'=>str_pad($cod_dep, 2, '0', STR_PAD_LEFT),
    'cod_prov'=>str_pad($cod_prov, 2, '0', STR_PAD_LEFT),
    'cod_dist'=>str_pad($cod_dist, 2, '0', STR_PAD_LEFT),
    'comisaria_id'=>$comisaria_id,
    'fecha_accidente'=>$fecha_accidente,
    'estado'=>$estado,
    'fecha_comunicacion'=>$fecha_comunicacion,
    'fecha_intervencion'=>$fecha_intervencion,
    'comunicante_nombre'=>$comunicante_nombre,
    'comunicante_telefono'=>$comunicante_telefono,
    'comunicacion_decreto'=>$comunicacion_decreto,
    'comunicacion_oficio'=>$comunicacion_oficio,
    'comunicacion_carpeta_nro'=>$comunicacion_carpeta_nro,
    'fiscalia_id'=>$fiscalia_id,
    'fiscal_id'=>$fiscal_id,
    'nro_informe_policial'=>$nro_informe,
    'sentido'=>$sentido,
    'secuencia'=>$secuencia
  ]);
  $mod_sel = $modalidad_ids;
  $con_sel = $consecuencia_ids;

  $context = $buildContext($acc);
  $provs = $context['provs'];
  $dists = $context['dists'];
  $comis = $context['comis'];
  $fiscales_de_fiscalia = $context['fiscales_de_fiscalia'];
  $fiscal_tel = $context['fiscal_tel'];
}// incluir el sidebar (archivo en la misma carpeta uiatnorte)
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Editar Accidente | UIAT Norte</title>
<link rel="stylesheet" href="assets/accidente.css">
</head>
<body>
<div class="wrap">
  <div class="title">
    <h1>Registrar Accidente <span class="badge">Editar</span></h1>
    <nav class="toolbar">
      <a class="btn" href="index.php">ðŸ  Inicio</a>
      <a class="btn" href="accidente_listar.php">ðŸ“„ Listar</a>
      <a class="btn primary" href="accidente_nuevo.php">ï¼‹ Nuevo</a>
    </nav>
  </div>

  <?php if(isset($_GET['ok'])):?>
    <div class="ok">âœ… Cambios guardados correctamente.</div>
  <?php endif;?>
  <?php if($err):?><div class="error">âš ï¸ <?=h($err)?></div><?php endif;?>

  <div class="card">
    <form class="grid" method="post" onsubmit="return validarForm();">
      <!-- SIDPOL (readonly) + Registro SIDPOL -->
      <div class="col-3">
        <label>SIDPOL</label>
        <input type="text" id="sidpol" value="<?=h($sidpol_url)?>" readonly>
      </div>

      <div class="col-3">
        <label>Registro SIDPOL</label>
        <input type="text" name="registro_sidpol" id="registro_sidpol" maxlength="50" value="<?=h($acc['registro_sidpol'])?>">
      </div>

      <div class="col-3">
        <label>Tipo de registro</label>
        <?php $tipoRegistroActual = (string)($acc['tipo_registro'] ?? ''); ?>
        <select name="tipo_registro" id="tipo_registro">
          <option value="" <?= $tipoRegistroActual === '' ? 'selected' : '' ?>>-- Selecciona --</option>
          <option value="Carpeta" <?= $tipoRegistroActual === 'Carpeta' ? 'selected' : '' ?>>Carpeta</option>
          <option value="Intervencion" <?= $tipoRegistroActual === 'Intervencion' ? 'selected' : '' ?>>Intervención</option>
        </select>
      </div>

      <!-- ESTADO -->
      <div class="col-3">
        <label>Estado</label>
        <select name="estado" id="estado">
          <?php
            $estados = ['Pendiente','Resuelto','Con diligencias'];
            foreach($estados as $e){
              $sel = ($acc['estado']===$e)?'selected':'';
              echo "<option value=\"".h($e)."\" $sel>".h($e)."</option>";
            }
          ?>
        </select>
      </div>
      <div class="col-9"><label style="visibility:hidden">.</label></div>

      <!-- ClasificaciÃ³n -->
      <div class="col-12">
        <fieldset class="groupbox">
          <legend>ClasificaciÃ³n del evento</legend>
          <div class="groupbox-row">
            <div class="group">
              <div class="group-title">Modalidades *</div>
              <div class="group-tools">
                <input type="text" id="summary-mod" class="filter-input" placeholder="Selecciona opcionesâ€¦" readonly>
                <label class="tool"><input type="checkbox" onchange="toggleAll('mod', this.checked)"> Todos</label>
                <button type="button" class="plus" data-modal="modal-modalidad" title="Nueva modalidad">ï¼‹</button>
              </div>
              <div id="grid-mod" class="option-grid">
                <?php foreach($modalidades as $r): ?>
                  <?php $checked = in_array((int)$r['id'],$mod_sel) ? 'checked' : ''; ?>
                  <label class="option-card" data-kind="mod" data-text="<?=h(mb_strtolower($r['nombre']))?>">
                    <input type="checkbox" name="modalidad_ids[]" value="<?=$r['id']?>" <?=$checked?>>
                    <span class="check"></span>
                    <span class="text"><?=h($r['nombre'])?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="group">
              <div class="group-title">Consecuencias *</div>
              <div class="group-tools">
                <input type="text" id="summary-con" class="filter-input" placeholder="Selecciona opcionesâ€¦" readonly>
                <label class="tool"><input type="checkbox" onchange="toggleAll('con', this.checked)"> Todos</label>
                <button type="button" class="plus" data-modal="modal-consecuencia" title="Nueva consecuencia">ï¼‹</button>
              </div>
              <div id="grid-con" class="option-grid">
                <?php foreach($consecuencias as $r): ?>
                  <?php $checked = in_array((int)$r['id'],$con_sel) ? 'checked' : ''; ?>
                  <label class="option-card" data-kind="con" data-text="<?=h(mb_strtolower($r['nombre']))?>">
                    <input type="checkbox" name="consecuencia_ids[]" value="<?=$r['id']?>" <?=$checked?>>
                    <span class="check"></span>
                    <span class="text"><?=h($r['nombre'])?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="tool" style="margin-top:6px;color:#9aa3b2">Puedes seleccionar varias opciones en cada grupo.</div>
        </fieldset>
      </div>

      <div class="col-6"><label>Lugar del hecho *</label>
        <input type="text" name="lugar" maxlength="200" required value="<?=h($acc['lugar'])?>"></div>
      <div class="col-6"><label>Referencia</label>
        <input type="text" name="referencia" maxlength="200" value="<?=h($acc['referencia'])?>"></div>

      <div class="col-4"><label>Departamento *</label>
        <select name="cod_dep" id="dep" required>
          <option value="" disabled>-- Selecciona --</option>
          <?php foreach($deps as $d): 
                 $sel = ($acc['cod_dep']===$d['cod_dep'])?'selected':''; ?>
            <option value="<?=h($d['cod_dep'])?>" <?=$sel?>><?=h($d['nombre'])?></option>
          <?php endforeach;?>
        </select>
      </div>

      <div class="col-4"><label>Provincia *</label>
        <select name="cod_prov" id="prov" required>
          <option value="" disabled <?=empty($provs)?'selected':'';?>>-- Selecciona --</option>
          <?php foreach($provs as $p): 
                 $sel = ($acc['cod_prov']===$p['cod_prov'])?'selected':''; ?>
            <option value="<?=h($p['cod_prov'])?>" <?=$sel?>><?=h($p['nombre'])?></option>
          <?php endforeach;?>
        </select>
      </div>

      <div class="col-4"><label>Distrito *</label>
        <select name="cod_dist" id="dist" required>
          <option value="" disabled <?=empty($dists)?'selected':'';?>>-- Selecciona --</option>
          <?php foreach($dists as $d): 
                 $sel = ($acc['cod_dist']===$d['cod_dist'])?'selected':''; ?>
            <option value="<?=h($d['cod_dist'])?>" <?=$sel?>><?=h($d['nombre'])?></option>
          <?php endforeach;?>
        </select>
      </div>

      <div class="col-3"><label>ComisarÃ­a *</label>
        <div class="rowflex">
          <select name="comisaria_id" id="comisaria" required <?= empty($comis)?'disabled':'';?>
          <select name="comisaria_id" id="comisaria" required <?= empty($comis)?'disabled':'';?>
        data-current="<?= (int)$acc['comisaria_id'] ?>">
            <option value="" disabled <?=empty($comis)?'selected':'';?>>-- Selecciona --</option>
            <?php foreach($comis as $c):
      $sel = ((int)$acc['comisaria_id']===(int)$c['id']) ? 'selected' : '';
      $label = $c['nombre'] . (isset($c['_fuera']) && $c['_fuera'] ? ' (fuera del distrito)' : '');
?>
  <option value="<?=$c['id']?>" <?=$sel?>><?=h($label)?></option>
<?php endforeach; ?>
          </select>
          <button type="button" class="plus" data-modal="modal-comisaria">ï¼‹</button>
        </div>
      </div>
      <div class="col-4"><label>Comunicante</label>
        <input type="text" name="comunicante_nombre" maxlength="120" value="<?=h($acc['comunicante_nombre'])?>"></div>
      <div class="col-4"><label>TelÃ©fono</label>
        <input type="text" name="comunicante_telefono" maxlength="20" value="<?=h($acc['comunicante_telefono'])?>"></div>
      <div class="col-4"><label>Decreto</label>
        <input type="text" name="comunicacion_decreto" maxlength="120" value="<?=h($acc['comunicacion_decreto'] ?? '')?>"></div>
      <div class="col-6"><label>Oficio</label>
        <input type="text" name="comunicacion_oficio" maxlength="120" value="<?=h($acc['comunicacion_oficio'] ?? '')?>"></div>
      <div class="col-6"><label>Carpeta NÂ°</label>
        <input type="text" name="comunicacion_carpeta_nro" maxlength="120" value="<?=h($acc['comunicacion_carpeta_nro'] ?? '')?>"></div>

      <div class="col-3"><label>ComunicaciÃ³n</label>
        <input type="datetime-local" name="fecha_comunicacion" value="<?=h($acc['fecha_comunicacion'])?>"></div>
      <div class="col-3"><label>IntervenciÃ³n</label>
        <input type="datetime-local" name="fecha_intervencion" value="<?=h($acc['fecha_intervencion'])?>"></div>

      <div class="col-6"><label>Comunicante</label>
        <input type="text" name="comunicante_nombre" maxlength="120" value="<?=h($acc['comunicante_nombre'])?>"></div>
      <div class="col-3"><label>TelÃ©fono</label>
        <input type="text" name="comunicante_telefono" maxlength="20" value="<?=h($acc['comunicante_telefono'])?>"></div>

      <div class="col-4"><label>FiscalÃ­a</label>
        <div class="rowflex">
          <select name="fiscalia_id" id="fiscalia">
            <option value="" disabled <?= $acc['fiscalia_id']? '': 'selected'; ?>>-- Selecciona --</option>
            <?php foreach($fiscalias as $r): 
                   $sel = ((int)$acc['fiscalia_id']===(int)$r['id'])?'selected':''; ?>
              <option value="<?=$r['id']?>" <?=$sel?>><?=h($r['nombre'])?></option>
            <?php endforeach;?>
          </select>
          <button type="button" class="plus" data-modal="modal-fiscalia">ï¼‹</button>
        </div>
      </div>

      <div class="col-4"><label>Fiscal</label>
        <div class="rowflex">
          <select name="fiscal_id" id="fiscal">
            <option value="" disabled <?= empty($fiscales_de_fiscalia)?'selected':'';?>>-- Selecciona (segÃºn fiscalÃ­a) --</option>
            <?php foreach($fiscales_de_fiscalia as $f):
                   $sel = ((int)$acc['fiscal_id']===(int)$f['id'])?'selected':''; ?>
              <option value="<?=$f['id']?>" <?=$sel?>><?=h($f['nombre'])?></option>
            <?php endforeach;?>
          </select>
          <button type="button" class="plus" data-modal="modal-fiscal">ï¼‹</button>
        </div>
      </div>

      <div class="col-3"><label>Tel. Fiscal</label>
        <input type="text" id="fiscal_tel" placeholder="Auto" readonly value="<?=h($fiscal_tel)?>">
      </div>

      <div class="col-4"><label>NÂ° Informe Policial</label>
        <input type="text" name="nro_informe_policial" maxlength="40" value="<?=h($acc['nro_informe_policial'])?>"></div>

      <div class="col-12"><label>Sentido / DirecciÃ³n</label>
        <input type="text" name="sentido" maxlength="100" value="<?=h($acc['sentido'])?>"></div>
      <div class="col-12"><label>Secuencia de eventos</label>
        <textarea name="secuencia" rows="4"><?=h($acc['secuencia'])?></textarea></div>

      <div class="col-12 rowflex" style="justify-content:flex-end">
        <a class="btn" href="accidentes_listar.php">Volver</a>
        <button class="btn primary" type="submit">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<!-- MODALES CATÃLOGOS -->
<div class="modal" id="modal-comisaria"><div class="card"><h3>Nueva ComisarÃ­a</h3>
  <form onsubmit="return crearBasico(event,'comisaria');">
    <label>Nombre *</label><input type="text" name="nombre" required>
    <div class="rowflex" style="justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn" onclick="cerrarModal('modal-comisaria')">Cancelar</button>
      <button class="btn primary" type="submit">Guardar</button>
    </div>
  </form></div></div>

<div class="modal" id="modal-fiscalia"><div class="card"><h3>Nueva FiscalÃ­a</h3>
  <form onsubmit="return crearBasico(event,'fiscalia');">
    <label>Nombre *</label><input type="text" name="nombre" required>
    <div class="rowflex" style="justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn" onclick="cerrarModal('modal-fiscalia')">Cancelar</button>
      <button class="btn primary" type="submit">Guardar</button>
    </div>
  </form></div></div>

<div class="modal" id="modal-modalidad"><div class="card"><h3>Nueva Modalidad</h3>
  <form onsubmit="return crearBasico(event,'modalidad');">
    <label>Nombre *</label><input type="text" name="nombre" required>
    <div class="rowflex" style="justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn" onclick="cerrarModal('modal-modalidad')">Cancelar</button>
      <button class="btn primary" type="submit">Guardar</button>
    </div>
  </form></div></div>

<div class="modal" id="modal-consecuencia"><div class="card"><h3>Nueva Consecuencia</h3>
  <form onsubmit="return crearBasico(event,'consecuencia');">
    <label>Nombre *</label><input type="text" name="nombre" required>
    <div class="rowflex" style="justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn" onclick="cerrarModal('modal-consecuencia')">Cancelar</button>
      <button class="btn primary" type="submit">Guardar</button>
    </div>
  </form></div></div>

<!-- MODAL NUEVO FISCAL -->
<div class="modal" id="modal-fiscal"><div class="card"><h3>Nuevo Fiscal</h3>
  <form onsubmit="return crearFiscal(event);">
    <div class="rowflex" style="gap:8px">
      <input type="text" name="nombres" placeholder="Nombres *" required>
      <input type="text" name="apellido_paterno" placeholder="Ap. paterno">
      <input type="text" name="apellido_materno" placeholder="Ap. materno">
    </div>
    <div style="margin-top:8px">
      <input type="text" name="telefono" placeholder="TelÃ©fono" style="width:100%">
      <input type="text" name="cargo" placeholder="Cargo (opcional)" style="width:100%;margin-top:6px">
      <div style="color:#9aa3b2;margin-top:6px">Se registrarÃ¡ en la fiscalÃ­a seleccionada.</div>
    </div>
    <div class="rowflex" style="justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn" onclick="cerrarModal('modal-fiscal')">Cancelar</button>
      <button class="btn primary" type="submit">Guardar</button>
    </div>
  </form>
</div></div>

<!-- MODAL XL - VehÃ­culos -->
<div class="modal-xl" id="modal-veh">
  <div class="box">
    <div class="modalbar">
      <div class="ttl">ðŸš— Participantes VehÃ­culos</div>
      <button type="button" class="x" onclick="cerrarModalXL('modal-veh')">Cerrar âœ•</button>
    </div>
    <div class="ifwrap">
      <div class="loader" id="load-veh">Cargandoâ€¦</div>
      <iframe id="frame-veh" src="about:blank" loading="lazy"></iframe>
    </div>
  </div>
</div>

<!-- MODAL XL - Personas -->
<div class="modal-xl" id="modal-per">
  <div class="box">
    <div class="modalbar">
      <div class="ttl">ðŸ‘¥ Participantes Personas</div>
      <button type="button" class="x" onclick="cerrarModalXL('modal-per')">Cerrar âœ•</button>
    </div>
    <div class="ifwrap">
      <div class="loader" id="load-per">Cargandoâ€¦</div>
      <iframe id="frame-per" src="about:blank" loading="lazy"></iframe>
    </div>
  </div>
</div>

<script src="assets/accidente.js"></script>

<!-- ======== SCRIPT PARA SINCRONIZAR SELECTS (con id en cada request) ======== -->
<script>
(() => {
  const $ = s => document.querySelector(s);
  const dep = $('#dep'), prov = $('#prov'), dist = $('#dist'), comi = $('#comisaria');
  const fiscalia = $('#fiscalia'), fiscal = $('#fiscal'), fiscalTel = $('#fiscal_tel');

  const baseURL = window.location.pathname;
  const curId = (new URLSearchParams(window.location.search)).get('id') || '';

  function placeholder(sel){
    const o = document.createElement('option');
    o.value=''; o.textContent = (sel===fiscal? '-- Selecciona (segÃºn fiscalÃ­a) --' : '-- Selecciona --');
    o.disabled = true; o.selected = true; return o;
  }
  function setOptions(sel, arr, valKey, txtKey, selVal){
    const prev = sel.value;
    sel.innerHTML=''; sel.appendChild(placeholder(sel));
    (arr||[]).forEach(it=>{
      const o=document.createElement('option');
      o.value=String(it[valKey]??'');
      o.textContent=String(it[txtKey]??'');
      sel.appendChild(o);
    });
    if(selVal && [...sel.options].some(o=>o.value===String(selVal))) sel.value=String(selVal);
    else if(prev && [...sel.options].some(o=>o.value===String(prev))) sel.value=String(prev);
    sel.disabled = sel.options.length<=1;
  }
  async function fetchJSON(params){
    const u=new URL(baseURL, location.origin);
    if(curId) u.searchParams.set('id', curId);
    Object.entries(params).forEach(([k,v])=>u.searchParams.set(k,v));
    u.searchParams.set('_', Date.now());
    const r=await fetch(u.toString(), {credentials:'same-origin'});
    if(!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
  }

  async function loadProv(){
    if(!dep?.value){ setOptions(prov,[], '', ''); setOptions(dist,[], '', ''); setOptions(comi,[], '', ''); return; }
    const j = await fetchJSON({ajax:'prov', dep:dep.value.slice(0,2)});
    if(j.ok){
      setOptions(prov, j.data, 'cod_prov', 'nombre');
      setOptions(dist, [], '', '');
      setOptions(comi, [], '', '');
    }
  }
  async function loadDist(){
    if(!dep?.value || !prov?.value){ setOptions(dist,[], '', ''); setOptions(comi,[], '', ''); return; }
    const j = await fetchJSON({ajax:'dist', dep:dep.value.slice(0,2), prov:prov.value.slice(0,2)});
    if(j.ok){
      setOptions(dist, j.data, 'cod_dist', 'nombre');
      setOptions(comi, [], '', '');
    }
  }
async function loadComi(){
  if(!dep?.value || !prov?.value || !dist?.value){
    setOptions(comi,[], '', ''); return;
  }
  const j = await fetchJSON({ajax:'comisarias_dist',
    dep:dep.value.slice(0,2), prov:prov.value.slice(0,2), dist:dist.value.slice(0,2)});
  if(j.ok){
    const cur = (comi?.dataset?.current || '').toString();
    setOptions(comi, j.data, 'id', 'nombre', cur);
    if (comi && 'current' in comi.dataset) comi.dataset.current = '';
  }
}

  async function loadFiscales(){
    if(!fiscalia?.value){ setOptions(fiscal, [], '', ''); fiscalTel.value=''; return; }
    const j = await fetchJSON({ajax:'fiscales', fiscalia_id:fiscalia.value});
    if(j.ok){
      setOptions(fiscal, j.data, 'id', 'nombre');
      if(fiscal.value) await refreshFiscalTel();
    }
  }
  async function refreshFiscalTel(){
    if(!fiscal?.value){ fiscalTel.value=''; return; }
    const k = await fetchJSON({ajax:'fiscal_info', fiscal_id:fiscal.value});
    fiscalTel.value = (k.ok && k.data && k.data.telefono) ? k.data.telefono : '';
  }

  dep?.addEventListener('change', loadProv);
  prov?.addEventListener('change', loadDist);
  dist?.addEventListener('change', loadComi);
  fiscalia?.addEventListener('change', loadFiscales);
  fiscal?.addEventListener('change', refreshFiscalTel);

  document.addEventListener('DOMContentLoaded', async () => {
    try{
      if(dep && dep.value && (prov?.options.length||0) <= 1) await loadProv();
      if(dep && dep.value && prov && prov.value && (dist?.options.length||0) <= 1) await loadDist();
      if(dep && dep.value && prov && prov.value && dist && dist.value && (comi?.options.length||0) <= 1) await loadComi();
      if(fiscalia && fiscalia.value && (fiscal?.options.length||0) <= 1) await loadFiscales();
      if(fiscal && fiscal.value) await refreshFiscalTel();
    }catch(e){ console.error(e); }
  });
})();
</script>
</body>
</html>
