<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\AccidenteRepository;
use App\Support\GeoSearch;
use App\Services\AccidenteService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function json_out($a){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($a,JSON_UNESCAPED_UNICODE); exit; }

$accidenteRepo = new AccidenteRepository($pdo);
$accidenteService = new AccidenteService($accidenteRepo);
$googleMapsApiKey = trim((string) app_config('services.google_maps.js_api_key', ''));

$draftEditId = (int)($_GET['draft_id'] ?? $_POST['draft_id'] ?? 0);
$guidedEmbed = $draftEditId > 0 && \App\Support\Access::role()==='guardia';
$savedDraft = null;
if ($draftEditId > 0) {
  $check=$pdo->prepare('SELECT rbac_guardia_draft(?)');$check->execute([$draftEditId]);
  if (!$guidedEmbed || !$check->fetchColumn()) {http_response_code(403);exit('Solo puedes corregir tu registro antes de entregarlo y dentro de las primeras 12 horas.');}
  $query=$pdo->prepare('SELECT * FROM accidentes WHERE id=?');$query->execute([$draftEditId]);$savedDraft=$query->fetch(PDO::FETCH_ASSOC);
}
/* =========================================================
 *                         AJAX
 * =======================================================*/
if (isset($_GET['ajax'])) {
  $a = $_GET['ajax'];

  // Provincias por departamento
  if ($a==='prov'){
    $dep = substr($_GET['dep']??'',0,2);
    json_out(['ok'=>true,'data'=>$accidenteRepo->provinciasByDepartamento($dep)]);
  }

  // Distritos por dep+prov
  if ($a==='dist'){
    $dep=substr($_GET['dep']??'',0,2);
    $prov=substr($_GET['prov']??'',0,2);
    json_out(['ok'=>true,'data'=>$accidenteService->distritosRegistro($dep,$prov)]);
  }

  // Comisarías por distrito (dep+prov+dist) usando comisaria_distrito
  if ($a==='comisarias_dist'){
    $dep  = substr($_GET['dep']??'',0,2);
    $prov = substr($_GET['prov']??'',0,2);
    $dist = substr($_GET['dist']??'',0,2);
    json_out(['ok'=>true,'data'=>$accidenteRepo->comisariasByDistrito($dep,$prov,$dist)]);
  }

  // Fiscales por fiscalía
  if ($a==='fiscales'){
    $fid = (int)($_GET['fiscalia_id'] ?? 0);
    json_out(['ok'=>true,'data'=>$accidenteService->fiscalesSeleccion($fid, isset($_GET['fiscalia_numero']) ? (string)$_GET['fiscalia_numero'] : null)]);
  }

  // Teléfono del fiscal
  if ($a==='fiscal_info'){
    $id = (int)($_GET['fiscal_id'] ?? 0);
    json_out(['ok'=>true,'data'=>$accidenteRepo->fiscalTelefono($id)]);
  }

  if ($a==='geo_search'){
    $q = trim((string) ($_GET['q'] ?? ''));
    $limit = (int) ($_GET['limit'] ?? 6);
    json_out(['ok' => true, 'data' => GeoSearch::searchPeruLima($q, $limit)]);
  }

  // Creaciones rápidas
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

    // Crear comisaría + mapear a distrito actual
    if ($type==='comisaria') {
      $nombre = trim($_POST['nombre'] ?? '');
      $dep  = substr($_POST['cod_dep']  ?? '', 0, 2);
      $prov = substr($_POST['cod_prov'] ?? '', 0, 2);
      $dist = substr($_POST['cod_dist'] ?? '', 0, 2);
      if($nombre==='') json_out(['ok'=>false,'msg'=>'Nombre de comisaría requerido']);

      $st=$pdo->prepare("SELECT id FROM comisarias WHERE nombre COLLATE utf8mb4_unicode_ci=? LIMIT 1");
      $st->execute([$nombre]);
      $id=$st->fetchColumn();
      if(!$id){
        $ins=$pdo->prepare("INSERT INTO comisarias (nombre) VALUES (?)");
        $ins->execute([$nombre]);
        $id=$pdo->lastInsertId();
      }

      $mapeada=false;
      if($dep && $prov && $dist){
        $chk=$pdo->prepare("SELECT 1 FROM ubigeo_distrito WHERE cod_dep=? AND cod_prov=? AND cod_dist=? LIMIT 1");
        $chk->execute([$dep,$prov,$dist]);
        if($chk->fetchColumn()){
          $ins2=$pdo->prepare("INSERT IGNORE INTO comisaria_distrito (comisaria_id,cod_dep,cod_prov,cod_dist) VALUES (?,?,?,?)");
          $ins2->execute([$id,$dep,$prov,$dist]);
          $mapeada=true;
        }
      }
      json_out(['ok'=>true,'id'=>$id,'label'=>$nombre,'type'=>'comisaria','mapeada'=>$mapeada]);
    }

    // Crear Fiscal
    if ($type==='fiscal') {
      $fiscalia_id = (int)($_POST['fiscalia_id'] ?? 0);
      $nombres = trim($_POST['nombres'] ?? '');
      $ap = trim($_POST['apellido_paterno'] ?? '');
      $am = trim($_POST['apellido_materno'] ?? '');
      $cargo = trim($_POST['cargo'] ?? '');
      $telefono = trim($_POST['telefono'] ?? '');
      if(!$fiscalia_id || $nombres==='') json_out(['ok'=>false,'msg'=>'Fiscalía y nombres son requeridos']);
      $ins=$pdo->prepare("INSERT INTO fiscales (fiscalia_id,nombres,apellido_paterno,apellido_materno,cargo,telefono) VALUES (?,?,?,?,?,?)");
      $ins->execute([$fiscalia_id,$nombres,$ap?:null,$am?:null,$cargo?:null,$telefono?:null]);
      $id=$pdo->lastInsertId();
      $label=trim("$nombres $ap $am");
      json_out(['ok'=>true,'id'=>$id,'label'=>$label,'type'=>'fiscal']);
    }

    // Catálogos simples
    $nombre=trim($_POST['nombre']??'');
    if($nombre==='') json_out(['ok'=>false,'msg'=>'Nombre requerido']);
    $map=[
      'fiscalia'     => ['table'=>'fiscalia','col'=>'nombre'],
      'modalidad'    => ['table'=>'modalidad_accidente','col'=>'nombre'],
      'consecuencia' => ['table'=>'consecuencia_accidente','col'=>'nombre'],
    ];
    if(!isset($map[$type])) json_out(['ok'=>false,'msg'=>'Tipo no permitido']);
    [$t,$c]=[$map[$type]['table'],$map[$type]['col']];
    $st=$pdo->prepare("SELECT id FROM {$t} WHERE {$c} COLLATE utf8mb4_unicode_ci=? LIMIT 1");
    $st->execute([$nombre]);
    $id=$st->fetchColumn();
    if(!$id){ $ins=$pdo->prepare("INSERT INTO {$t} ({$c}) VALUES (?)"); $ins->execute([$nombre]); $id=$pdo->lastInsertId(); }
    json_out(['ok'=>true,'id'=>$id,'label'=>$nombre,'type'=>$type]);
  }

  json_out(['ok'=>false,'msg'=>'Acción no reconocida']);
}

/* =========================================================
 *                    Catálogos base
 * =======================================================*/
$deps          = $pdo->query("SELECT cod_dep,nombre FROM ubigeo_departamento ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$fiscalias     = $pdo->query("SELECT id,nombre FROM fiscalia ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$modalidades   = $pdo->query("SELECT id,nombre FROM modalidad_accidente ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$consecuencias = $pdo->query("SELECT id,nombre FROM consecuencia_accidente ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$deps          = $accidenteRepo->departamentos();
$fiscalias     = $accidenteService->fiscaliasSeleccion();
$modalidades   = $accidenteRepo->modalidades();
$consecuencias = $accidenteRepo->consecuencias();

/* =========================================================
 *                       Guardado
 * =======================================================*/
if ($savedDraft && $_SERVER['REQUEST_METHOD'] !== 'POST') {
 $_POST=$savedDraft;
 $_POST['modalidad_ids']=$accidenteRepo->modalidadIdsForAccidente($draftEditId);
 $_POST['consecuencia_ids']=$accidenteRepo->consecuenciaIdsForAccidente($draftEditId);
 foreach($fiscalias as $office)if((int)$office['id']===(int)$savedDraft['fiscalia_id'])$_POST['fiscalia_numero']=$office['selection']['number'] ?: '';
}
foreach(['registro_sidpol','tipo_registro','lugar','referencia','latitud','longitud','comunicante_nombre','comunicante_telefono','comunicacion_decreto','comunicacion_oficio','comunicacion_carpeta_nro'] as $field) $$field=$_POST[$field] ?? '';
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  // $sidpol eliminado del POST (se autogenera) // NUEVO
  $registro_sidpol=trim($_POST['registro_sidpol']??''); // si lo estás usando
  $tipo_registro=trim($_POST['tipo_registro']??'');
  $lugar=trim($_POST['lugar']??'');
  $referencia=trim($_POST['referencia']??'');
  $latitud=trim($_POST['latitud']??'');
  $longitud=trim($_POST['longitud']??'');
  $cod_dep=substr($_POST['cod_dep']??'',0,2);
  $cod_prov=substr($_POST['cod_prov']??'',0,2);
  $cod_dist=substr($_POST['cod_dist']??'',0,2);
  $comisaria_id = $_POST['comisaria_id']!=='' ? (int)$_POST['comisaria_id'] : null;

  $fecha_accidente = trim($_POST['fecha_accidente']??'');
  if (isset($_POST['accidente_fecha']) || isset($_POST['accidente_hora'])) {
    $diaAccidente = trim((string)($_POST['accidente_fecha'] ?? ''));
    $horaAccidente = trim((string)($_POST['accidente_hora'] ?? ''));
    $fecha_accidente = $diaAccidente !== '' && $horaAccidente !== '' ? $diaAccidente . 'T' . $horaAccidente : '';
    $_POST['fecha_accidente'] = $fecha_accidente;
  }
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

  // Validaciones (sin sidpol obligatorio) // NUEVO
  if($lugar===''||!$cod_dep||!$cod_prov||!$cod_dist||$fecha_accidente===''){
    $err='Completa los campos obligatorios (*).';
  }
  if(!$err && (count($modalidad_ids)===0 || count($consecuencia_ids)===0)){
    $err='Selecciona al menos una Modalidad y una Consecuencia.';
  }
  if (!$err && $fiscal_id) {
    try { $fiscalia_id = $accidenteService->fiscaliaConFiscal($fiscalia_id, $fiscal_id); }
    catch (InvalidArgumentException $e) { $err = $e->getMessage(); }
  }

  // Validación antigua de duplicado SIDPOL eliminada (se genera por BD) // NUEVO

  $cod_dep  = str_pad($cod_dep,  2, '0', STR_PAD_LEFT);
  $cod_prov = str_pad($cod_prov, 2, '0', STR_PAD_LEFT);
  $cod_dist = str_pad($cod_dist, 2, '0', STR_PAD_LEFT);

  if(!$err){
    if(!(ctype_digit($cod_dep) && strlen($cod_dep)===2) ||
       !(ctype_digit($cod_prov) && strlen($cod_prov)===2) ||
       !(ctype_digit($cod_dist) && strlen($cod_dist)===2)){
      $err='Selecciona un Distrito válido.';
    }
  }
  if(!$err){
    $chkDist=$pdo->prepare("SELECT 1 FROM ubigeo_distrito WHERE cod_dep=? AND cod_prov=? AND cod_dist=? LIMIT 1");
    $chkDist->execute([$cod_dep,$cod_prov,$cod_dist]);
    if(!$chkDist->fetchColumn()){ $err='Selecciona un Distrito válido.'; }
  }
  if(!$err){
    if(!$comisaria_id || $comisaria_id<=0){
      $err='Selecciona una Comisaría.';
    }else{
      $map=$pdo->prepare("SELECT 1 FROM comisaria_distrito WHERE comisaria_id=? AND cod_dep=? AND cod_prov=? AND cod_dist=? LIMIT 1");
      $map->execute([$comisaria_id,$cod_dep,$cod_prov,$cod_dist]);
      if(!$map->fetchColumn()) $err='La comisaría no pertenece al distrito seleccionado.';
    }
  }

  if(!$err){
    try{
      $save = $draftEditId ? fn($input) => $accidenteService->updateGuardiaDraft($draftEditId, $input) : fn($input) => $accidenteService->registerAccidente($input);
      $result = $save([
        'registro_sidpol' => $registro_sidpol,
        'tipo_registro' => $tipo_registro,
        'lugar' => $lugar,
        'referencia' => $referencia,
        'latitud' => $latitud,
        'longitud' => $longitud,
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
        'fiscalia_numero' => $_POST['fiscalia_numero'] ?? '',
        'fiscal_id' => $fiscal_id,
        'nro_informe_policial' => $nro_informe,
        'sentido' => $sentido,
        'secuencia' => $secuencia,
        'modalidad_ids' => $modalidad_ids,
        'consecuencia_ids' => $consecuencia_ids,
        'estado' => $estado,
      ]);

      header("Location: ".(\App\Support\Access::role()==='guardia'?'guardia_registro.php?paso=vehiculos&accidente_id=':'accidente_creado.php?accidente_id=').(int)$result['id']);
      exit;

      $pdo->beginTransaction();

      // Quitar sidpol del INSERT (columna generada) // NUEVO
      $sql="INSERT INTO accidentes
        (registro_sidpol,tipo_registro,lugar,referencia,cod_dep,cod_prov,cod_dist,comisaria_id,
         fecha_accidente,estado,fecha_comunicacion,fecha_intervencion,
         comunicante_nombre,comunicante_telefono,fiscalia_id,fiscal_id,nro_informe_policial,
         sentido,secuencia)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
      $st=$pdo->prepare($sql);
      $st->execute([
        ($registro_sidpol?:null), ($tipo_registro?:null), $lugar, $referencia, $cod_dep, $cod_prov, $cod_dist, $comisaria_id?:null,
        ($fecha_accidente?:null), $estado, ($fecha_comunicacion?:null),($fecha_intervencion?:null),
        ($comunicante_nombre?:null),($comunicante_telefono?:null),
        $fiscalia_id?:null,$fiscal_id?:null,($nro_informe?:null),
        ($sentido?:null),($secuencia?:null)
      ]);
      $newId=(int)$pdo->lastInsertId();

      if($modalidad_ids){
        $ins=$pdo->prepare("INSERT IGNORE INTO accidente_modalidad (accidente_id, modalidad_id) VALUES (?,?)");
        foreach($modalidad_ids as $mid){ if($mid>0){ $ins->execute([$newId,$mid]); } }
      }
      if($consecuencia_ids){
        $ins=$pdo->prepare("INSERT IGNORE INTO accidente_consecuencia (accidente_id, consecuencia_id) VALUES (?,?)");
        foreach($consecuencia_ids as $cid){ if($cid>0){ $ins->execute([$newId,$cid]); } }
      }

      $pdo->commit();

// Generar y guardar el SIDPOL basado en ID
$sidpol_gen = str_pad((string)$newId, 8, '0', STR_PAD_LEFT);

$upd = $pdo->prepare("UPDATE accidentes SET sidpol=? WHERE id=?");
$upd->execute([$sidpol_gen, $newId]);



      // Calcular SIDPOL generado para el redirect (mismo formato del LPAD) // NUEVO
      $sidpol_gen = str_pad((string)$newId, 8, '0', STR_PAD_LEFT);

      header("Location: accidente_vista_tabs.php?accidente_id=".(int)$newId);
      exit;
    }catch(Exception $e){
      if($pdo->inTransaction()) $pdo->rollBack();
      $err='Error al guardar: '.$e->getMessage();
    }
  }
}

$accidente_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$inicioRegistro = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
$minutoRedondeado = (int)(round((int)$inicioRegistro->format('i') / 10) * 10);
$comunicacionRedondeada = $inicioRegistro->setTime((int)$inicioRegistro->format('H'), 0)->modify('+' . $minutoRedondeado . ' minutes');
$fechaAccidenteInicial = (string)($_POST['fecha_accidente'] ?? '');
$diaAccidenteInicial = (string)($_POST['accidente_fecha'] ?? ($fechaAccidenteInicial !== '' ? substr($fechaAccidenteInicial, 0, 10) : $inicioRegistro->format('Y-m-d')));
$horaAccidenteInicial = (string)($_POST['accidente_hora'] ?? ($fechaAccidenteInicial !== '' ? substr($fechaAccidenteInicial, 11, 5) : ''));
$fechaComunicacionInicial = (string)($_POST['fecha_comunicacion'] ?? $comunicacionRedondeada->format('Y-m-d\TH:i'));
$fechaIntervencionInicial = (string)($_POST['fecha_intervencion'] ?? $comunicacionRedondeada->modify('+1 hour')->format('Y-m-d\TH:i'));

$departamentoInicial = (string)($_POST['cod_dep'] ?? '15');
$provinciaInicial = (string)($_POST['cod_prov'] ?? '01');
$provinciasIniciales = $accidenteRepo->provinciasByDepartamento($departamentoInicial);
$distritosIniciales = $accidenteService->distritosRegistro($departamentoInicial, $provinciaInicial);

// incluir el sidebar (archivo en la misma carpeta uiatnorte)
if (!$guidedEmbed) include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nuevo Accidente | UIAT Norte</title>
<link rel="stylesheet" href="assets/accidente.css?v=<?= (int) filemtime(__DIR__ . '/assets/accidente.css') ?>">
<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css">
<?php if($guidedEmbed): ?><style>body{padding:0!important;background:white}.wrap{margin:0!important;padding:0!important;max-width:none!important}.title{display:none}.form-block{box-shadow:none}</style><?php endif; ?>
</head>
<body>
<?php if (\App\Support\Access::role()==='guardia' && !$guidedEmbed): ?><div style="max-width:1100px;margin:24px auto;padding:20px;background:#e2f3ee;border-radius:16px"><strong>1 · Datos del accidente → 2 · Vehículos → 3 · Personas → 4 · Entrega</strong><p>Completa los datos conocidos. Al guardar continuarás con los vehículos y las personas involucradas.</p></div><?php endif ?>
<div class="wrap">
  <div class="title">
    <h1>Registrar Accidente <span class="badge">Nuevo</span></h1>
    <nav class="toolbar">
      <a class="btn" href="index.php">Inicio</a>
      <a class="btn" href="accidente_listar.php">Listar</a>
      <a class="btn primary" href="accidente_nuevo.php">+ Nuevo</a>
    </nav>
  </div>

  <?php if($err):?><div class="error">Atención: <?=h($err)?></div><?php endif;?>

  <div class="accident-form-wrap">
    <form class="grid accident-form" method="post" onsubmit="return validarForm();">
      <input type="hidden" name="draft_id" value="<?= $draftEditId ?>">
      <section class="form-block" aria-labelledby="registro-title">
        <header class="form-block-heading"><span class="form-block-number" aria-hidden="true">01</span><h2 id="registro-title">Datos del registro</h2></header>
      <div class="col-4">
        <label>Registro SIDPOL</label>
        <input type="text" name="registro_sidpol" id="registro_sidpol" maxlength="50" placeholder="Opcional" value="<?=h($registro_sidpol ?? '')?>">
      </div>

      <div class="col-4">
        <label>Tipo de registro</label>
        <?php $tipoRegistroActual = $tipo_registro ?? ''; ?>
        <select name="tipo_registro" id="tipo_registro">
          <option value="" <?= $tipoRegistroActual === '' ? 'selected' : '' ?>>-- Selecciona --</option>
          <option value="Carpeta" <?= $tipoRegistroActual === 'Carpeta' ? 'selected' : '' ?>>Carpeta</option>
          <option value="Intervencion" <?= $tipoRegistroActual === 'Intervencion' ? 'selected' : '' ?>>Intervención</option>
        </select>
      </div>

      <!-- ESTADO -->
      <div class="col-4">
        <label>Estado</label>
        <select name="estado" id="estado">
          <option value="Pendiente" <?= ($_POST['estado']??'Pendiente')==='Pendiente' ? 'selected' : '' ?>>Pendiente</option>
          <option value="Resuelto" <?= ($_POST['estado']??'')==='Resuelto' ? 'selected' : '' ?>>Resuelto</option>
          <option value="Con diligencias" <?= ($_POST['estado']??'')==='Con diligencias' ? 'selected' : '' ?>>Con diligencias</option>
        </select>
      </div>
      </section>


      <section class="form-block" aria-labelledby="fechas-title">
        <header class="form-block-heading"><span class="form-block-number" aria-hidden="true">02</span><h2 id="fechas-title">Fechas y horas</h2></header>
      <div class="col-4"><label for="accidente_fecha">Fecha y hora del accidente *</label>
        <div style="display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:8px;">
          <input type="date" name="accidente_fecha" id="accidente_fecha" value="<?= h($diaAccidenteInicial) ?>" required aria-label="Fecha del accidente">
          <input type="time" name="accidente_hora" id="accidente_hora" value="<?= h($horaAccidenteInicial) ?>" required aria-label="Hora del accidente">
        </div>
        <input type="hidden" name="fecha_accidente" value="<?= h($fechaAccidenteInicial) ?>">
      </div>
      <div class="col-4"><label>Comunicación</label><input type="datetime-local" name="fecha_comunicacion" value="<?= h($fechaComunicacionInicial) ?>"></div>
      <div class="col-4"><label>Intervención</label><input type="datetime-local" name="fecha_intervencion" value="<?= h($fechaIntervencionInicial) ?>"></div>

      </section>

      <section class="form-block" aria-labelledby="clasificacion-title">
        <header class="form-block-heading"><span class="form-block-number" aria-hidden="true">03</span><h2 id="clasificacion-title">Clasificación del evento</h2></header>
      <!-- Clasificación -->
      <div class="col-12">
        <fieldset class="groupbox">

          <div class="groupbox-row">
            <div class="group">
              <div class="group-title">Modalidades *</div>
              <div class="group-tools">
                <input type="text" id="summary-mod" class="filter-input" placeholder="Selecciona opciones..." readonly>
                <label class="tool"><input type="checkbox" onchange="toggleAll('mod', this.checked)"> Todos</label>
                <button type="button" class="plus" data-modal="modal-modalidad" title="Nueva modalidad">+</button>
              </div>
              <div id="grid-mod" class="option-grid">
                <?php foreach($modalidades as $r): ?>
                  <label class="option-card" data-kind="mod" data-text="<?=h(mb_strtolower($r['nombre']))?>">
                    <input type="checkbox" name="modalidad_ids[]" value="<?=$r['id']?>" <?= in_array((int)$r['id'], array_map('intval', $_POST['modalidad_ids'] ?? []), true) ? 'checked' : '' ?>>
                    <span class="check"></span>
                    <span class="text"><?=h($r['nombre'])?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="group">
              <div class="group-title">Consecuencias *</div>
              <div class="group-tools">
                <input type="text" id="summary-con" class="filter-input" placeholder="Selecciona opciones..." readonly>
                <label class="tool"><input type="checkbox" onchange="toggleAll('con', this.checked)"> Todos</label>
                <button type="button" class="plus" data-modal="modal-consecuencia" title="Nueva consecuencia">+</button>
              </div>
              <div id="grid-con" class="option-grid">
                <?php foreach($consecuencias as $r): ?>
                  <label class="option-card" data-kind="con" data-text="<?=h(mb_strtolower($r['nombre']))?>">
                    <input type="checkbox" name="consecuencia_ids[]" value="<?=$r['id']?>" <?= in_array((int)$r['id'], array_map('intval', $_POST['consecuencia_ids'] ?? []), true) ? 'checked' : '' ?>>
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

      </section>

      <section class="form-block" aria-labelledby="ubicacion-title">
        <header class="form-block-heading"><span class="form-block-number" aria-hidden="true">04</span><h2 id="ubicacion-title">Ubicación y jurisdicción</h2></header>
      <div class="col-6"><label>Lugar del hecho *</label><input type="text" name="lugar" maxlength="200" value="<?=h($lugar)?>" required></div>
      <div class="col-6"><label>Referencia</label><input type="text" name="referencia" maxlength="200" value="<?=h($referencia)?>"></div>

      <div class="col-4"><label>Departamento *</label>
        <select name="cod_dep" id="dep" required>
          <option value="" disabled <?= $departamentoInicial === '' ? 'selected' : '' ?>>-- Selecciona --</option>
          <?php foreach($deps as $d):?><option value="<?=h($d['cod_dep'])?>" <?= (string)$d['cod_dep'] === $departamentoInicial ? 'selected' : '' ?>><?=h($d['nombre'])?></option><?php endforeach;?>
        </select></div>
      <div class="col-4"><label>Provincia *</label>
        <select name="cod_prov" id="prov" required>
          <option value="" disabled <?= $provinciaInicial === '' ? 'selected' : '' ?>>-- Selecciona --</option>
          <?php foreach($provinciasIniciales as $p): ?><option value="<?= h($p['cod_prov']) ?>" <?= (string)$p['cod_prov'] === $provinciaInicial ? 'selected' : '' ?>><?= h($p['nombre']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-4"><label>Distrito *</label>
        <select name="cod_dist" id="dist" required>
          <option value="" disabled selected>-- Selecciona --</option>
          <?php foreach($distritosIniciales as $d): ?><option value="<?= h($d['cod_dist']) ?>" <?= (string)($_POST['cod_dist']??'')===(string)$d['cod_dist'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option><?php endforeach; ?>
        </select></div>

      <div class="col-12"><label>Comisaría *</label>
        <div class="rowflex">
          <select name="comisaria_id" id="comisaria" required disabled>
            <option value="" disabled selected>-- Selecciona --</option>
          </select>
          <button type="button" class="plus" data-modal="modal-comisaria">+</button>
        </div>
      </div>

      <div class="col-3">
        <label>Latitud</label>
        <input type="text" name="latitud" id="latitud" value="<?=h($latitud ?? '')?>" placeholder="Ingresa o marca desde el mapa" inputmode="decimal">
      </div>
      <div class="col-3">
        <label>Longitud</label>
        <input type="text" name="longitud" id="longitud" value="<?=h($longitud ?? '')?>" placeholder="Ingresa o marca desde el mapa" inputmode="decimal">
      </div>
      <div class="col-6">
        <label>Georreferencia</label>
        <div class="rowflex geo-actions">
          <button type="button" class="btn" id="btn-open-geo" onclick="return abrirModalGeo();">Marcar en mapa</button>
          <button type="button" class="btn" id="btn-clear-geo" onclick="return limpiarGeoAccidente();">Limpiar punto</button>
          <a class="btn" id="geo-open-external" href="#" target="_blank" rel="noopener noreferrer" onclick="return abrirGeoExterno(event);">Ver en Google Maps</a>
        </div>
      </div>

      <div class="col-12">
        <div class="geo-preview" id="geo-preview-status">Todavía no hay un punto georreferenciado para este accidente.</div>
      </div>

      </section>



      <section class="form-block" aria-labelledby="comunicacion-title">
        <header class="form-block-heading"><span class="form-block-number" aria-hidden="true">05</span><h2 id="comunicacion-title">Datos de la comunicación</h2></header>
      <div class="col-6"><label>Comunicante</label><input type="text" name="comunicante_nombre" maxlength="120" value="<?=h($comunicante_nombre ?? '')?>"></div>
      <div class="col-6"><label>Teléfono</label><input type="text" name="comunicante_telefono" maxlength="20" value="<?=h($comunicante_telefono ?? '')?>"></div>
      <div class="col-4"><label>Decreto</label><input type="text" name="comunicacion_decreto" maxlength="120" value="<?=h($comunicacion_decreto ?? '')?>"></div>
      <div class="col-4"><label>Oficio</label><input type="text" name="comunicacion_oficio" maxlength="120" value="<?=h($comunicacion_oficio ?? '')?>"></div>
      <div class="col-4"><label>Carpeta N°</label><input type="text" name="comunicacion_carpeta_nro" maxlength="120" value="<?=h($comunicacion_carpeta_nro ?? '')?>"></div>

      </section>

      <section class="form-block" aria-labelledby="fiscalia-title">
        <header class="form-block-heading"><span class="form-block-number" aria-hidden="true">06</span><h2 id="fiscalia-title">Fiscalía y fiscal a cargo</h2></header>
      <div class="col-3" id="fiscalia-despacho-box"><label for="fiscalia-despacho">Despacho</label><select id="fiscalia-despacho" disabled><option value="">-- Selecciona distrito --</option></select></div>
      <div class="col-2" id="fiscalia-numero-box"><label for="fiscalia-numero">Nro. Fis.</label><select id="fiscalia-numero"><option value="">Sin número (opcional)</option><?php for ($n=1; $n<=3; $n++): ?><option value="<?= $n ?>"><?= $n ?>°</option><?php endfor; ?></select>

        <input type="hidden" name="fiscalia_numero" id="fiscalia-numero-valor" value="<?= h($_POST['fiscalia_numero'] ?? '') ?>"></div>
      <div class="col-4" id="fiscalia-nombre-box"><label for="fiscalia-nombre">Nombre de Fiscalía</label><select id="fiscalia-nombre" disabled><option value="">-- Selecciona distrito --</option></select></div>
      <div class="col-3" id="fiscalia-distrito-box"><label for="fiscalia-distrito">Distrito Fiscal</label><input id="fiscalia-distrito" readonly placeholder="Según distrito del accidente"></div>
      <div class="col-12" id="fiscalia-resumen" role="status" aria-live="polite" style="padding:12px 16px;border:1px solid var(--border);border-radius:12px;">Selecciona el distrito y los datos de la fiscalía para visualizar su nombre completo.</div>
      <div class="col-12" id="fiscalia-catalogo-box" hidden><label for="fiscalia">Fiscalía</label>
        <div class="rowflex"><select name="fiscalia_id" id="fiscalia">
          <option value="">-- Selecciona --</option>
          <?php foreach($fiscalias as $r):?><option value="<?= (int)$r['id'] ?>" <?= (string)($_POST['fiscalia_id'] ?? '') === (string)$r['id'] ? 'selected' : '' ?>><?= h($r['selection']['name'] ?? $r['nombre']) ?></option><?php endforeach;?>
        </select><button type="button" class="plus" data-modal="modal-fiscalia">+</button></div>
      </div>
      <script type="application/json" id="fiscalia-selection-data"><?= json_encode($fiscalias, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

      <div class="col-5"><label for="fiscal">Fiscal</label>
        <div class="fiscal-person-row">
          <select data-initial-fiscal="<?= h($_POST['fiscal_id'] ?? '') ?>" name="fiscal_id" id="fiscal">
            <option value="" disabled selected>-- Selecciona (según fiscalía) --</option>
          </select>
          <button type="button" class="plus" data-modal="modal-fiscal">+</button>
        </div>
      </div>

      <div class="col-4"><label for="fiscal-grado">Grado fiscal (opcional)</label>
        <select id="fiscal-grado" disabled><option value="">Todos los grados</option></select>
      </div>

      <div class="col-3"><label>Tel. Fiscal</label>
        <input type="text" id="fiscal_tel" placeholder="Auto" readonly>
      </div>

      </section>

      <section class="form-block" aria-labelledby="relato-title">
        <header class="form-block-heading"><span class="form-block-number" aria-hidden="true">07</span><h2 id="relato-title">Relato e informe</h2></header>
      <div class="col-4"><label>N° Informe Policial</label><input type="text" name="nro_informe_policial" maxlength="40" value="<?=h($_POST['nro_informe_policial']??'')?>"></div>

      <div class="col-12"><label>Sentido / Dirección</label><input type="text" name="sentido" maxlength="100" value="<?=h($_POST['sentido']??'')?>"></div>
      <div class="col-12"><label>Secuencia de eventos</label><textarea name="secuencia" rows="4"><?=h($_POST['secuencia']??'')?></textarea></div>

      </section>

      <div class="col-12 rowflex accident-form-actions">
        <a class="btn" href="accidente_listar.php">Cancelar</a>
        <button class="btn primary" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODALES CATÁLOGOS -->
<div class="modal" id="modal-comisaria"><div class="card"><h3>Nueva Comisaría</h3>
  <form onsubmit="return crearBasico(event,'comisaria');">
    <label>Nombre *</label><input type="text" name="nombre" required>
    <div class="rowflex" style="justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn" onclick="cerrarModal('modal-comisaria')">Cancelar</button>
      <button class="btn primary" type="submit">Guardar</button>
    </div>
  </form></div></div>

<div class="modal" id="modal-fiscalia"><div class="card"><h3>Nueva Fiscalía</h3>
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
      <input type="text" name="telefono" placeholder="Teléfono" style="width:100%">
      <label for="nuevo-fiscal-grado" style="margin-top:8px">Grado del nuevo fiscal (opcional)</label>
      <select name="cargo" id="nuevo-fiscal-grado"><option value="">-- Sin especificar --</option><option>Fiscal Provincial</option><option>Fiscal Adjunto Provincial</option><option>Fiscal Superior</option><option>Fiscal Adjunto Superior</option><option>Fiscal Supremo</option><option>Fiscal Adjunto Supremo</option></select>
      <div style="color:#9aa3b2;margin-top:6px">Se registrará en la fiscalía seleccionada.</div>
    </div>
    <div class="rowflex" style="justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn" onclick="cerrarModal('modal-fiscal')">Cancelar</button>
      <button class="btn primary" type="submit">Guardar</button>
    </div>
  </form>
</div></div>

<div class="modal" id="modal-geo">
  <div class="card geo-modal-card">
    <div class="geo-modal-head">
      <div>
        <h3 style="margin:0">Ubicación del accidente</h3>
        <div class="geo-modal-sub">Busca una dirección o haz clic sobre el mapa para fijar el punto exacto.</div>
      </div>
      <button type="button" class="btn" id="btn-close-geo">Cerrar</button>
    </div>
    <div class="geo-toolbar">
      <input type="text" id="geo-search" placeholder="Buscar dirección, cruce, avenida o referencia">
      <select id="geo-map-type">
        <option value="roadmap" selected>Mapa</option>
        <option value="hybrid">Híbrido</option>
        <option value="satellite">Satélite</option>
        <option value="terrain">Relieve</option>
      </select>
    </div>
    <div id="geo-map" class="geo-map"></div>
    <div class="geo-coords-bar">
      <span id="geo-coords-text">Sin coordenadas seleccionadas.</span>
    </div>
    <div class="rowflex" style="justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn" id="btn-cancel-geo">Cancelar</button>
      <button type="button" class="btn primary" id="btn-use-geo">Usar estas coordenadas</button>
    </div>
  </div>
</div>

<!-- MODAL XL - Vehículos -->
<div class="modal-xl" id="modal-veh">
  <div class="box">
    <div class="modalbar">
      <div class="ttl">Participantes Vehículos</div>
      <button type="button" class="x" onclick="cerrarModalXL('modal-veh')">Cerrar x</button>
    </div>
    <div class="ifwrap">
      <div class="loader" id="load-veh">Cargando...</div>
      <iframe id="frame-veh" src="about:blank" loading="lazy"></iframe>
    </div>
  </div>
</div>

<!-- MODAL XL - Personas -->
<div class="modal-xl" id="modal-per">
  <div class="box">
    <div class="modalbar">
      <div class="ttl">Participantes Personas</div>
      <button type="button" class="x" onclick="cerrarModalXL('modal-per')">Cerrar x</button>
    </div>
    <div class="ifwrap">
      <div class="loader" id="load-per">Cargando...</div>
      <iframe id="frame-per" src="about:blank" loading="lazy"></iframe>
    </div>
  </div>
</div>

<script src="assets/js/fiscalia-selection.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/fiscalia-selection.js') ?>"></script>
<script src="assets/accidente.js?v=<?= (int) filemtime(__DIR__ . '/assets/accidente.js') ?>"></script>
<script src="assets/vendor/leaflet/leaflet.js"></script>
<?php if ($googleMapsApiKey !== ''): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= h($googleMapsApiKey) ?>&libraries=places" async defer></script>
<?php endif; ?>
<script>window.initAccidenteGeoMap && window.initAccidenteGeoMap();</script>
<?php if($draftEditId): ?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const restore=(id,value)=>{if(!value)return;const select=document.getElementById(id);const apply=()=>{if([...select.options].some(o=>o.value===String(value))){select.value=String(value);select.dispatchEvent(new Event('change',{bubbles:true}));return true;}return false;};if(!apply()){const observer=new MutationObserver(()=>{if(apply())observer.disconnect();});observer.observe(select,{childList:true});}};
 document.getElementById('dist').dispatchEvent(new Event('change',{bubbles:true}));
 restore('comisaria',<?= json_encode((string)($_POST['comisaria_id']??'')) ?>);
 queueMicrotask(() => cargarFiscales());
});
</script>
<?php endif; ?>
</body>
</html>
