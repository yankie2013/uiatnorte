<?php
/* ===========================================================
   oficio_peritaje.php  (UIAT NORTE)
   - Genera y DESCARGA el oficio de peritaje (DOCX).
   - Entrada recomendada: ?oficio_id=ID
   - Requiere: composer require phpoffice/phpword
   =========================================================== */

$__DEBUG = isset($_GET['debug']);

// 0) Config de errores / salida limpia
@ini_set('zlib.output_compression', '0'); // evita que el binario se corrompa
@ini_set('output_buffering', '0');

if (!$__DEBUG) {
  error_reporting(0);
  ini_set('display_errors', 0);
} else {
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  set_error_handler(function($no,$str,$file,$line){
    header('Content-Type: text/plain; charset=utf-8');
    echo "[ERROR $no] $str\n$file:$line\n";
    exit;
  });
  set_exception_handler(function($e){
    header('Content-Type: text/plain; charset=utf-8');
    echo "[EXCEPTION] ".$e->getMessage()."\n".$e->getFile().":".$e->getLine()."\n";
    echo $e->getTraceAsString();
    exit;
  });
}

// Limpia cualquier buffer previo y arranca uno controlado
while (ob_get_level()) { @ob_end_clean(); }
ob_start();

/* --------- Dependencias / DB ---------- */
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

/* --------- Config ---------- */
define('ROL_CONDUCTOR',   1); // ajusta según catálogo real
define('ROL_PROPIETARIO', 5); // ajusta según catálogo real
$templatePath = __DIR__.'/plantillas/oficio_peritaje.docx';

/* --------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function val($row,$k,$def=''){ return isset($row[$k]) && $row[$k]!==null ? (string)$row[$k] : $def; }
function table_exists(PDO $pdo,$t){
  $q=$pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
  $q->execute([$t]); return (bool)$q->fetchColumn();
}
function fecha_sigla($dt){
  if(!$dt) return '';
  $ts = strtotime($dt);
  $dia = date('j', $ts);
  $anio = date('Y', $ts);
  $meses = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
  $mes = $meses[(int)date('n', $ts)] ?? '';
  return "$dia de $mes de $anio";
}
function fecha_abreviada_es($dt){
  if(!$dt) return '';
  $ts = strtotime($dt);
  $dia = date('d', $ts); $anio = date('Y', $ts);
  $meses = [1=>'ENE',2=>'FEB',3=>'MAR',4=>'ABR',5=>'MAY',6=>'JUN',7=>'JUL',8=>'AGO',9=>'SET',10=>'OCT',11=>'NOV',12=>'DIC'];
  $mes = $meses[(int)date('n', $ts)] ?? '';
  return strtoupper($dia.$mes.$anio);
}
function hora_hhmm($dt){ return $dt? date('H:i',strtotime($dt)) : ''; }
function compose_num_oficio($num,$anio){
  $pref='DIRNOS-DIRTTSV/DIVPIAT-UIAT-NORTE';
  if(!$num && !$anio) return '';
  return "OFICIO Nº {$num}-{$anio}-{$pref}.";
}
function clean_for_phpword($value) {
  if ($value === null) return '';
  $value = (string)$value;
  $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
  $value = preg_replace('/\s+/', ' ', $value);
  return trim($value);
}
function persona_por_rol(PDO $pdo,$accidente_id,$vehiculo_id,$rol_id){
  $sql="SELECT p.id, p.tipo_doc, p.num_doc,
               CONCAT(TRIM(p.nombres),' ',TRIM(p.apellido_paterno),' ',TRIM(p.apellido_materno)) AS nombre
        FROM involucrados_personas ip
        JOIN personas p ON p.id=ip.persona_id
        WHERE ip.accidente_id=? AND ip.vehiculo_id=? AND ip.rol_id=?
        ORDER BY ip.id ASC LIMIT 1";
  $st=$pdo->prepare($sql); $st->execute([$accidente_id,$vehiculo_id,$rol_id]);
  $r=$st->fetch(PDO::FETCH_ASSOC);
  return $r ?: ['nombre'=>'','tipo_doc'=>'','num_doc'=>''];
}
function resolve_nombre(PDO $pdo, $id, array $tables, array $fields=['nombre','detalle','descripcion','titulo']){
  if(!$id) return '';
  foreach($tables as $t){
    $q1=$pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $q1->execute([$t]); if(!$q1->fetchColumn()) continue;
    foreach($fields as $f){
      try{
        $q=$pdo->prepare("SELECT `$f` FROM `$t` WHERE id=? LIMIT 1");
        $q->execute([$id]); $v=$q->fetchColumn();
        if($v!==false && $v!=='') return (string)$v;
      }catch(Exception $e){}
    }
  }
  return '';
}
function resolve_distrito(PDO $pdo, $dep, $prov, $dist){
  $dep=(string)$dep; $prov=(string)$prov; $dist=(string)$dist;
  if ($dep==='' || $prov==='' || $dist==='') return '';
  if (table_exists($pdo,'ubigeo_distrito')) {
    $q=$pdo->prepare("SELECT nombre FROM ubigeo_distrito WHERE cod_dep=? AND cod_prov=? AND cod_dist=? LIMIT 1");
    $q->execute([$dep,$prov,$dist]); $n=$q->fetchColumn();
    if ($n) return (string)$n;
  }
  if (table_exists($pdo,'distritos')) {
    $codigo=$dep.$prov.$dist;
    $q=$pdo->prepare("SELECT nombre FROM distritos WHERE codigo=? LIMIT 1");
    $q->execute([$codigo]); $n=$q->fetchColumn();
    if ($n) return (string)$n;
  }
  return '';
}
function obtener_nombre_ano(PDO $pdo, $oficial_ano_id, $anio_oficio=null){
  if (!empty($oficial_ano_id)) {
    $q=$pdo->prepare("SELECT nombre FROM oficio_oficial_ano WHERE id=? LIMIT 1");
    $q->execute([(int)$oficial_ano_id]); $v=$q->fetchColumn();
    if ($v) return (string)$v;
  }
  $q=$pdo->query("SELECT nombre FROM oficio_oficial_ano WHERE vigente=1 ORDER BY id DESC LIMIT 1");
  $v=$q->fetchColumn(); if ($v) return (string)$v;
  if (!empty($anio_oficio)) {
    $q=$pdo->prepare("SELECT nombre FROM oficio_oficial_ano WHERE anio=? LIMIT 1");
    $q->execute([$anio_oficio]); $v=$q->fetchColumn();
    if ($v) return (string)$v;
  }
  return $anio_oficio ? ('AÑO '.$anio_oficio) : '';
}

function sanitize_for_docx($s){
  if ($s === null) return '';
  $s = (string)$s;

  // Normaliza espacios
  $s = preg_replace('/\s+/u', ' ', $s);

  // Reemplazos seguros para DOCX/XML
  $map = [
    "—" => "-", "–" => "-",
    "“" => '"', "”" => '"',
    "‘" => "'", "’" => "'",
    "•" => "-", "▪" => "-", "" => "-",
    "\xC2\xA0" => " ",
    "&" => " Y ", // <-- clave
  ];
  $s = strtr($s, $map);

  // Limpia controles invisibles
  $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);

  // Compacta espacios otra vez
  $s = preg_replace('/\s+/u', ' ', $s);

  return trim($s);
}

/* ===========================================================
   INPUT y resolución OFICIO -> INVOLUCRADO -> VEHÍCULO
   =========================================================== */
$oficio_id_forzado = isset($_GET['oficio_id']) ? (int)$_GET['oficio_id'] : 0;
$numero_q = isset($_GET['numero']) ? trim($_GET['numero']) : '';
$anio_q   = isset($_GET['anio'])   ? trim($_GET['anio'])   : '';
$placa    = isset($_GET['placa'])  ? trim($_GET['placa'])  : '';

if(!file_exists($templatePath)){
  http_response_code(500); header('Content-Type: text/plain; charset=utf-8');
  echo "No se encuentra la plantilla: ".$templatePath; exit;
}

/* 1) Cargar OFICIO (por id o por numero/año) */
$oficio = null;
if ($oficio_id_forzado>0) {
  $q=$pdo->prepare("SELECT * FROM oficios WHERE id=? LIMIT 1");
  $q->execute([$oficio_id_forzado]); $oficio=$q->fetch(PDO::FETCH_ASSOC);
} elseif ($numero_q!=='' && $anio_q!=='') {
  $q=$pdo->prepare("SELECT * FROM oficios WHERE numero=? AND anio=? ORDER BY id DESC LIMIT 1");
  $q->execute([$numero_q,$anio_q]); $oficio=$q->fetch(PDO::FETCH_ASSOC);
}

/* 2) Resolver vehículo/accidente desde el OFICIO */
$vehiculo=null; $vehiculo_id=null; $accidente_id=null; $involucrado_vehiculo_id=null; $iv=null;

if ($oficio) {
  if (!empty($oficio['involucrado_vehiculo_id'])) {
    $q=$pdo->prepare("SELECT * FROM involucrados_vehiculos WHERE id=? LIMIT 1");
    $q->execute([(int)$oficio['involucrado_vehiculo_id']]); $iv=$q->fetch(PDO::FETCH_ASSOC);
    if ($iv) {
      $involucrado_vehiculo_id=(int)$iv['id'];
      $vehiculo_id=(int)$iv['vehiculo_id'];
      $accidente_id=(int)$iv['accidente_id'];
    }
  }
  if (!$vehiculo_id && !empty($oficio['involucrado_persona_id'])) {
    $q=$pdo->prepare("SELECT * FROM involucrados_personas WHERE id=? LIMIT 1");
    $q->execute([(int)$oficio['involucrado_persona_id']]); $ip=$q->fetch(PDO::FETCH_ASSOC);
    if ($ip) { $vehiculo_id=(int)$ip['vehiculo_id']; $accidente_id=(int)$ip['accidente_id']; }
  }
  if ($vehiculo_id) {
    $q=$pdo->prepare("SELECT * FROM vehiculos WHERE id=? LIMIT 1");
    $q->execute([$vehiculo_id]); $vehiculo=$q->fetch(PDO::FETCH_ASSOC);
    if ($vehiculo) $placa=$vehiculo['placa'];
  }
}

/* 3) Si no hubo oficio o no resolvió vehículo, flujo por placa */
if (!$vehiculo && $placa!=='') {
  $st=$pdo->prepare("SELECT * FROM vehiculos WHERE placa=? LIMIT 1");
  $st->execute([$placa]); $vehiculo=$st->fetch(PDO::FETCH_ASSOC);
  if(!$vehiculo){ http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo "No existe vehículo con placa ".h($placa); exit; }
  $vehiculo_id=(int)$vehiculo['id'];

  $st=$pdo->prepare("SELECT * FROM involucrados_vehiculos WHERE vehiculo_id=? ORDER BY id DESC LIMIT 1");
  $st->execute([$vehiculo_id]); $iv=$st->fetch(PDO::FETCH_ASSOC);
  if(!$iv){ http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo "La placa ".h($placa)." no tiene registros en involucrados_vehiculos."; exit; }
  $accidente_id=(int)$iv['accidente_id'];
  $involucrado_vehiculo_id=(int)$iv['id'];

  if(!$oficio){
    $q=$pdo->prepare("SELECT * FROM oficios WHERE involucrado_vehiculo_id=? ORDER BY fecha_emision DESC, id DESC LIMIT 1");
    $q->execute([$involucrado_vehiculo_id]); $oficio=$q->fetch(PDO::FETCH_ASSOC);
  }
  if(!$oficio){
    $q=$pdo->prepare("SELECT * FROM oficios WHERE accidente_id=? ORDER BY fecha_emision DESC, id DESC LIMIT 1");
    $q->execute([$accidente_id]); $oficio=$q->fetch(PDO::FETCH_ASSOC);
  }
}

/* Validaciones finales */
if(!$oficio){ http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo "No se encontró un oficio para procesar."; exit; }
if(!$vehiculo){ http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo "No se pudo resolver el vehículo desde el oficio."; exit; }

/* ===========================================================
   Consultas complementarias
   =========================================================== */

/* Accidente */
$st=$pdo->prepare("SELECT * FROM accidentes WHERE id=? LIMIT 1");
$st->execute([$accidente_id]); $acc=$st->fetch(PDO::FETCH_ASSOC) ?: [];

/* Modalidades */
$modalidades_txt = '';
try{
  $modNombres = [];
  if (table_exists($pdo,'accidente_modalidad') && table_exists($pdo,'modalidad_accidente')) {
    $sqlMod = "SELECT ma.nombre
               FROM accidente_modalidad am
               JOIN modalidad_accidente ma ON ma.id = am.modalidad_id
               WHERE am.accidente_id = ?
               ORDER BY am.modalidad_id ASC";
    $st = $pdo->prepare($sqlMod);
    $st->execute([$accidente_id]);
    $modNombres = $st->fetchAll(PDO::FETCH_COLUMN);
  }
  if ($modNombres) {
    if (count($modNombres) === 1) $modalidades_txt = $modNombres[0];
    else { $u = array_pop($modNombres); $modalidades_txt = implode(', ', $modNombres).' y '.$u; }
  }
}catch(Throwable $e){
  if ($__DEBUG) { header('Content-Type: text/plain; charset=utf-8'); echo "[Modalidad ERROR] ".$e->getMessage(); exit; }
}

/* Destino */
$dest_persona=''; $dest_grado_cargo=''; $dest_entidad=''; $dest_subentidad='';

$__getGradoCargo = function(PDO $pdo, $id){
  if(!$id) return '';
  $q=$pdo->prepare("SELECT nombre, COALESCE(abreviatura,'') AS abrev FROM grado_cargo WHERE id=? LIMIT 1");
  $q->execute([(int)$id]); $row=$q->fetch(PDO::FETCH_ASSOC);
  if(!$row) return '';
  $nombre = trim((string)($row['nombre'] ?? ''));
  return $nombre !== '' ? $nombre : trim((string)($row['abrev'] ?? ''));
};

$ope = null;
if (!empty($oficio['persona_destino_id']) && table_exists($pdo,'oficio_persona_entidad')) {
  $q=$pdo->prepare("SELECT nombres, apellido_paterno, apellido_materno FROM oficio_persona_entidad WHERE id=? LIMIT 1");
  $q->execute([(int)$oficio['persona_destino_id']]); $ope = $q->fetch(PDO::FETCH_ASSOC);
  if ($ope) $dest_persona = trim(($ope['nombres']??'').' '.($ope['apellido_paterno']??'').' '.($ope['apellido_materno']??''));
}

if (!empty($oficio['grado_cargo_id']) && table_exists($pdo,'grado_cargo')) {
  $dest_grado_cargo = $__getGradoCargo($pdo, (int)$oficio['grado_cargo_id']);
}

if ($dest_grado_cargo==='' && table_exists($pdo,'personas') && table_exists($pdo,'grado_cargo')) {
  try{
    $q=$pdo->prepare("SELECT gc.id FROM personas p JOIN grado_cargo gc ON gc.id=p.grado_cargo_id WHERE p.id=? LIMIT 1");
    $q->execute([(int)$oficio['persona_destino_id']]); $idgc=$q->fetchColumn();
    if ($idgc) $dest_grado_cargo = $__getGradoCargo($pdo,(int)$idgc);
  }catch(Throwable $e){}
}

if (!empty($oficio['entidad_id_destino']) && table_exists($pdo,'oficio_entidad')) {
  $qe = $pdo->prepare("SELECT nombre FROM oficio_entidad WHERE id=? LIMIT 1");
  $qe->execute([(int)$oficio['entidad_id_destino']]);
  $dest_entidad = (string)($qe->fetchColumn() ?: '');
}

/* Conductor */
$conductor = persona_por_rol($pdo,$accidente_id,(int)$vehiculo['id'],ROL_CONDUCTOR);
if ($conductor['nombre']==='') {
  $q=$pdo->prepare("SELECT CONCAT(TRIM(p.nombres),' ',TRIM(p.apellido_paterno),' ',TRIM(p.apellido_materno)) AS nombre,p.tipo_doc,p.num_doc
                    FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id
                    WHERE ip.accidente_id=? AND ip.vehiculo_id=? ORDER BY ip.id ASC LIMIT 1");
  $q->execute([$accidente_id,(int)$vehiculo['id']]); $aux=$q->fetch(PDO::FETCH_ASSOC) ?: [];
  if($aux){ $conductor['nombre']=$aux['nombre']; $conductor['tipo_doc']=$aux['tipo_doc']; $conductor['num_doc']=$aux['num_doc']; }
}

/* Propietario */
$propietario_nombre = '';
$propietario_doc    = '';

if (empty($involucrado_vehiculo_id)) {
  $q=$pdo->prepare("SELECT id FROM involucrados_vehiculos WHERE accidente_id=? AND vehiculo_id=? ORDER BY id ASC LIMIT 1");
  $q->execute([$accidente_id,(int)$vehiculo['id']]);
  $involucrado_vehiculo_id = (int)($q->fetchColumn() ?: 0);
}

if ($involucrado_vehiculo_id && table_exists($pdo,'propietario_vehiculo')) {
  $q=$pdo->prepare("
    SELECT pv.*,
           p.tipo_doc  AS p_tipo_doc,  p.num_doc  AS p_num_doc,
           CONCAT(TRIM(p.nombres),' ',TRIM(p.apellido_paterno),' ',TRIM(p.apellido_materno)) AS p_nombre,
           rp.tipo_doc AS r_tipo_doc, rp.num_doc AS r_num_doc,
           CONCAT(TRIM(rp.nombres),' ',TRIM(rp.apellido_paterno),' ',TRIM(rp.apellido_materno)) AS r_nombre
    FROM propietario_vehiculo pv
    LEFT JOIN personas p  ON p.id  = pv.propietario_persona_id
    LEFT JOIN personas rp ON rp.id = pv.representante_persona_id
    WHERE pv.accidente_id=? AND pv.vehiculo_inv_id=?
    ORDER BY pv.id DESC
    LIMIT 1
  ");
  $q->execute([$accidente_id,$involucrado_vehiculo_id]);
  $pv = $q->fetch(PDO::FETCH_ASSOC);

  if ($pv) {
    if ($pv['tipo_propietario']==='NATURAL') {
      $propietario_nombre = (string)($pv['p_nombre'] ?: '');
      $propietario_doc    = trim(($pv['p_tipo_doc'] ?: '').' '.($pv['p_num_doc'] ?: ''));
    } else {
      $propietario_nombre = (string)($pv['razon_social'] ?: '');
      $propietario_doc    = trim('RUC '.($pv['ruc'] ?: ''));
      if (!empty($pv['representante_persona_id'])) {
        $rep    = trim($pv['r_nombre'] ?: '');
        $repDoc = trim(($pv['r_tipo_doc'] ?: '').' '.($pv['r_num_doc'] ?: ''));
        if ($rep) $propietario_nombre .= ' - Rep.: '.$rep.( $repDoc ? ' ('.$repDoc.')' : '' );
      }
    }
  }
}
if ($propietario_nombre === '') {
  $prop = persona_por_rol($pdo,$accidente_id,(int)$vehiculo['id'],ROL_PROPIETARIO);
  if (!empty($prop['nombre'])) {
    $propietario_nombre = $prop['nombre'];
    $propietario_doc    = trim(($prop['tipo_doc'] ?: '').' '.($prop['num_doc'] ?: ''));
  }
}

/* Nombre oficial del año */
$nombre_ano = obtener_nombre_ano($pdo, ($oficio['oficial_ano_id'] ?? null), ($oficio['anio'] ?? null));

/* Catálogos */
$marca_nombre=''; $modelo_nombre=''; $categoria_nombre=''; $tipo_nombre=''; $carroceria_nombre='';
$marca_id=(int)val($vehiculo,'marca_id',''); $modelo_id=(int)val($vehiculo,'modelo_id','');
$categoria_id=(int)val($vehiculo,'categoria_id',''); $tipo_id=(int)val($vehiculo,'tipo_id',''); $carroceria_id=(int)val($vehiculo,'carroceria_id','');

if ($marca_id && table_exists($pdo,'marcas_vehiculo')) {
  $q=$pdo->prepare("SELECT nombre FROM marcas_vehiculo WHERE id=? LIMIT 1");
  $q->execute([$marca_id]); $marca_nombre=(string)($q->fetchColumn() ?: '');
}
if ($modelo_id && table_exists($pdo,'modelos_vehiculo')) {
  $q=$pdo->prepare("SELECT nombre FROM modelos_vehiculo WHERE id=? LIMIT 1");
  $q->execute([$modelo_id]); $modelo_nombre=(string)($q->fetchColumn() ?: '');
}
if ($categoria_id && table_exists($pdo,'categoria_vehiculos')) {
  $q=$pdo->prepare("SELECT codigo FROM categoria_vehiculos WHERE id=? LIMIT 1");
  $q->execute([$categoria_id]); $categoria_nombre=(string)($q->fetchColumn() ?: '');
}
if ($categoria_nombre==='') {
  $categoria_nombre = resolve_nombre($pdo,$categoria_id,['categoria_vehiculos','categorias','clases','cat_categoria'],['codigo','nombre','descripcion','detalle','titulo']);
}
if ($carroceria_id && table_exists($pdo,'carroceria_vehiculo')) {
  $q=$pdo->prepare("SELECT COALESCE(NULLIF(nombre,''), descripcion) FROM carroceria_vehiculo WHERE id=? LIMIT 1");
  $q->execute([$carroceria_id]); $carroceria_nombre=(string)($q->fetchColumn() ?: '');
}
if ($carroceria_nombre==='') {
  $carroceria_nombre = resolve_nombre($pdo,$carroceria_id,['carroceria_vehiculo','carrocerias','cat_carroceria'],['nombre','descripcion','detalle','titulo']);
}
if ($marca_nombre      === '') $marca_nombre      = resolve_nombre($pdo,$marca_id,     ['marcas','cat_marca']);
if ($modelo_nombre     === '') $modelo_nombre     = resolve_nombre($pdo,$modelo_id,    ['modelos','cat_modelo']);
if ($tipo_nombre       === '') $tipo_nombre       = resolve_nombre($pdo,$tipo_id,      ['tipos','tipo_vehiculo','cat_tipo']);
if ($carroceria_nombre === '') $carroceria_nombre = resolve_nombre($pdo,$carroceria_id,['carrocerias','cat_carroceria'], ['descripcion','nombre']);

/* Distrito y hora */
$accidente_hora = hora_hhmm(val($acc,'fecha_accidente',''));
$accidente_distrito = '';
if (!empty($acc['cod_dep']) && !empty($acc['cod_prov']) && !empty($acc['cod_dist'])) {
  $accidente_distrito = resolve_distrito($pdo, (string)$acc['cod_dep'], (string)$acc['cod_prov'], (string)$acc['cod_dist']);
}
if ($accidente_distrito==='' && !empty($acc['comisaria_id']) && table_exists($pdo,'comisaria_distrito')) {
  $qd=$pdo->prepare("SELECT cod_dep,cod_prov,cod_dist FROM comisaria_distrito WHERE comisaria_id=? LIMIT 1");
  $qd->execute([(int)$acc['comisaria_id']]);
  if ($row=$qd->fetch(PDO::FETCH_ASSOC)) {
    $accidente_distrito = resolve_distrito($pdo, (string)($row['cod_dep']??''),(string)($row['cod_prov']??''),(string)($row['cod_dist']??''));
  }
}
if ($accidente_distrito==='' && !empty($acc['comisaria_id']) && table_exists($pdo,'comisarias')) {
  $q=$pdo->prepare("SELECT distrito FROM comisarias WHERE id=?");
  $q->execute([(int)$acc['comisaria_id']]); $accidente_distrito=(string)($q->fetchColumn() ?: '');
}

/* Textos */
$carta_lugar     = 'Santa Rosa';
$asunto_visible  = val($oficio,'motivo','') ?: 'Certificado de Constatación de Daños y sistemas, por motivo que se indica. - SOLICITA';
$acc_fecha_sigla = fecha_abreviada_es(val($acc,'fecha_accidente',''));
$acc_lugar       = trim(val($acc,'lugar','').(val($acc,'referencia','') ? ' - '.val($acc,'referencia','') : ''));
$accidente_resumen = "Por lo que se agradece a Ud., remitir el resultado estipulado según normas vigentes para efectos de proseguir con las diligencias respectivas relacionadas al esclarecimiento de un Accidente de Tránsito, ocurrido el día {$acc_fecha_sigla} a horas {$accidente_hora} aprox., en {$acc_lugar}.";

/* Firma/pie */
$firma_sigla    = 'OA-333163';
$firma_nombre   = 'Pedro Ismael DELGADO GOZAR';
$firma_grado    = 'COMANDANTE PNP';
$firma_cargo    = 'JEFE DE LA UNIDAD DE INVESTIGACIÓN DE ACCIDENTES DE TRÁNSITO - NORTE';
$siglas_tramite = 'PIDG/gms';

$pie_unidad     = 'DIVISIÓN DE PREVENCIÓN E INVESTIGACIÓN DE ACCIDENTES DE TRÁNSITO NORTE';
$pie_direccion  = 'Carretera Panamericana Norte Km. 42 alt. Garita control SUNAT - Santa Rosa';
$pie_telefono   = '980121336';
$pie_email      = 'divpiat.norte@policia.gob.pe';

/* Variables plantilla */
$vars = [
  'nombre_ano'        => clean_for_phpword($nombre_ano),
  'carta_lugar'       => clean_for_phpword($carta_lugar),
  'oficio_fecha'      => clean_for_phpword(fecha_sigla(val($oficio,'fecha_emision',''))),
  'oficio_titulo'     => clean_for_phpword(compose_num_oficio(val($oficio,'numero',''), val($oficio,'anio',''))),
  'oficio_numero'     => clean_for_phpword(val($oficio,'numero','')),
  'oficio_anio'       => clean_for_phpword(val($oficio,'anio','')),
  'oficio_asunto'     => clean_for_phpword($asunto_visible),
  'oficio_motivo'     => clean_for_phpword(val($oficio,'motivo','')),
  'oficio_referencia' => clean_for_phpword(val($oficio,'referencia_texto','')),

  'destino_persona'     => clean_for_phpword($dest_persona),
  'destino_grado_cargo' => clean_for_phpword($dest_grado_cargo),
  'destino_entidad'     => clean_for_phpword($dest_entidad),
  'destino_subentidad'  => clean_for_phpword($dest_subentidad),

  'solicitud_texto'   => clean_for_phpword('Es grato dirigirme a Ud. a fin de que se practique la constatación de daños y a los sistemas del vehículo, cuyas características se detallan:'),

  'veh_placa'       => clean_for_phpword(val($vehiculo,'placa','')),
  'veh_clase'       => clean_for_phpword($categoria_nombre),
  'veh_categoria'   => clean_for_phpword($categoria_nombre),
  'veh_marca'       => clean_for_phpword($marca_nombre),
  'veh_modelo'      => clean_for_phpword($modelo_nombre),
  'veh_anio'        => clean_for_phpword(val($vehiculo,'anio','')),
  'veh_carroceria'  => clean_for_phpword($carroceria_nombre),
  'veh_color'       => clean_for_phpword(val($vehiculo,'color','')),
  'veh_tipo'        => clean_for_phpword($tipo_nombre),
  'veh_orden'       => clean_for_phpword(val($iv??[],'orden_participacion','')),

  'accidente_lugar'       => clean_for_phpword($acc_lugar),
  'accidente_fecha'       => clean_for_phpword($acc_fecha_sigla),
  'accidente_hora'        => clean_for_phpword($accidente_hora),
  'accidente_distrito'    => clean_for_phpword($accidente_distrito),
  'accidente_sentido'     => clean_for_phpword(val($acc,'sentido','')),
  'accidente_sidpol'      => clean_for_phpword(val($acc,'sidpol','')),
  'accidente_referencia'  => clean_for_phpword(val($acc,'referencia','')),
  'accidente_modalidades' => clean_for_phpword($modalidades_txt),
  'accidente_resumen'     => clean_for_phpword($accidente_resumen),

  'conductor_nombre'   => clean_for_phpword(val($conductor,'nombre','')),
  'conductor_doc'      => clean_for_phpword(trim(val($conductor,'tipo_doc','').' '.val($conductor,'num_doc',''))),

 'propietario_nombre' => clean_for_phpword(sanitize_for_docx($propietario_nombre)),
  'propietario_doc'    => clean_for_phpword($propietario_doc),

  'cierre_cordial'   => clean_for_phpword('Aprovecho la oportunidad para expresarle los sentimientos más distinguidos y alta estima personal.'),
  'despedida'        => clean_for_phpword('Dios guarde a Ud.'),
  'firma_sigla'      => clean_for_phpword($firma_sigla),
  'firma_nombre'     => clean_for_phpword($firma_nombre),
  'firma_grado'      => clean_for_phpword($firma_grado),
  'firma_cargo'      => clean_for_phpword($firma_cargo),
  'siglas_tramite'   => clean_for_phpword($siglas_tramite),

  'pie_unidad'       => clean_for_phpword($pie_unidad),
  'pie_direccion'    => clean_for_phpword($pie_direccion),
  'pie_telefono'     => clean_for_phpword($pie_telefono),
  'pie_email'        => clean_for_phpword($pie_email),
];

/* Generar DOCX */
require_once __DIR__.'/vendor/autoload.php';
if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && file_exists(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
  require_once __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}
if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'PhpWord no esta disponible para generar el DOCX.';
  exit;
}

$tpl = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
foreach($vars as $k=>$v){ $tpl->setValue($k, (string)$v); }

/* Inspección opcional: ?vars=1 */
if (isset($_GET['vars']) && method_exists($tpl,'getVariables')) {
  while (ob_get_level()) { @ob_end_clean(); }
  header('Content-Type: text/plain; charset=utf-8');
  $docVars = $tpl->getVariables(); sort($docVars);
  $keys = array_keys($vars); sort($keys);
  echo "== DOC VARS ==\n"; print_r($docVars);
  echo "\n== KEYS EN \$vars ==\n"; print_r($keys);
  echo "\n== FALTAN EN \$vars ==\n"; print_r(array_values(array_diff($docVars, array_keys($vars))));
  echo "\n== SOBRAN EN \$vars ==\n"; print_r(array_values(array_diff(array_keys($vars), $docVars)));
  exit;
}

/* Guardar temporal */
$saveDir = __DIR__.'/tmp';
if(!is_dir($saveDir)) @mkdir($saveDir, 0775, true);
$tmp = tempnam($saveDir, 'oficio_');
if ($tmp === false) {
  while (ob_get_level()) { @ob_end_clean(); }
  header('Content-Type: text/plain; charset=utf-8');
  echo 'No se pudo crear un archivo temporal para el DOCX.';
  exit;
}
$tpl->saveAs($tmp);

/* Verificación básica del docx */
clearstatcache(true, $tmp);
if (!file_exists($tmp) || filesize($tmp) < 1000) {
  while (ob_get_level()) { @ob_end_clean(); }
  header('Content-Type: text/plain; charset=utf-8');
  echo "DOCX no generado correctamente. Tamaño: ".(file_exists($tmp)?filesize($tmp):0);
  exit;
}

/* DESCARGA LIMPIA (sin HTML, sin basura, sin buffers) */
while (ob_get_level()) { @ob_end_clean(); }

if (headers_sent($file, $line)) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "HEADERS YA ENVIADOS en $file:$line (eso rompe el DOCX). Revisa auth.php/db.php por echos/BOM.";
  @unlink($tmp);
  exit;
}

$fname = 'Oficio_Peritaje_'.preg_replace('/[^A-Z0-9\-]/i','', (string)$placa);
if(!empty($oficio['numero'])) $fname .= '_N'.$oficio['numero'];
$fname .= '.docx';

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');
// Ojo: Content-Length a veces rompe si algo intercepta. Mejor no mandarlo.
// header('Content-Length: '.filesize($tmp));

readfile($tmp);
@unlink($tmp);
exit;
