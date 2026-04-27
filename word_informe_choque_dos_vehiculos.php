<?php
/* ===========================================================
   WORD: INFORME CHOQUE DOS VEHICULOS (DOCX con PhpWord)
   Proyecto: UIAT NORTE
   Parametros: ?accidente_id=ID  (alias ?id)
   Plantilla:  /plantillas/informe_choque_dos_vehiculos.docx
   =========================================================== */

require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
require_once __DIR__.'/word_manifestaciones_helper.php';
require_once __DIR__.'/word_filename_helper.php';

$DEBUG = (isset($_GET['debug']) && $_GET['debug']=='1');
if ($DEBUG) {
  ini_set('display_errors',1);
  ini_set('display_startup_errors',1);
  error_reporting(E_ALL);
} else {
  ini_set('display_errors',0);
  ini_set('display_startup_errors',0);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

if (is_file(__DIR__.'/vendor/autoload.php')) { require __DIR__.'/vendor/autoload.php'; }
if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && is_file(__DIR__.'/PHPWord-1.4.0/vendor/autoload.php')) {
  require __DIR__.'/PHPWord-1.4.0/vendor/autoload.php';
}
use PhpOffice\PhpWord\TemplateProcessor;

if (!class_exists(TemplateProcessor::class)) {
  http_response_code(500);
  exit('PhpWord no esta disponible para generar el DOCX.');
}

/* ---------- Parametro ---------- */
$accidente_id = (int)($_GET['accidente_id'] ?? $_GET['id'] ?? 0);
if ($accidente_id<=0) { http_response_code(400); echo "Falta ?accidente_id"; exit; }
/* ===========================================================
   HELPERS
=========================================================== */
function v($s){ return ($s!==null && $s!=='') ? $s : 'â€”'; }
function vblank($s){ return ($s!==null && $s!=='') ? $s : ''; }
function mes3($ts){
  return strtoupper(strtr(date('M',$ts),[
    'Jan'=>'ENE','Feb'=>'FEB','Mar'=>'MAR','Apr'=>'ABR','May'=>'MAY','Jun'=>'JUN',
    'Jul'=>'JUL','Aug'=>'AGO','Sep'=>'SEP','Oct'=>'OCT','Nov'=>'NOV','Dec'=>'DIC'
  ]));
}
function ymd_pe($dt){ if(!$dt) return ''; $ts=strtotime($dt); if(!$ts) return ''; return date('d',$ts).mes3($ts).date('Y',$ts); }
function fecha_corta($dt){ if(!$dt) return ''; $ts=strtotime($dt); if(!$ts) return ''; return date('d/m/Y',$ts); }
function hora_pe($dt){ if(!$dt) return ''; $ts=strtotime($dt); if(!$ts) return ''; return date('H:i',$ts); }
function edad_from($fecha){ if(!$fecha) return ''; $ts=strtotime($fecha); if(!$ts) return ''; $hoy=new DateTime('today'); $n=DateTime::createFromFormat('Y-m-d',date('Y-m-d',$ts)); if(!$n) return ''; return (string)$n->diff($hoy)->y; }
function nombre_completo($n='',$apep='',$apem=''){ return trim(($n?:'').' '.($apep?:'').' '.($apem?:'')); }
function list_item_case(string $item, bool $capitalize = false): string {
  $item = preg_replace('/\s+/u', ' ', trim($item)) ?? trim($item);
  if ($item === '') return '';
  $item = mb_strtolower($item, 'UTF-8');
  if (!$capitalize) return $item;
  return mb_strtoupper(mb_substr($item, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($item, 1, null, 'UTF-8');
}
function join_es(array $items): string {
  $items = array_values(array_filter(array_map(static fn($item) => trim((string)$item), $items), static fn($item) => $item !== ''));
  $count = count($items);
  if ($count === 0) return '';
  if ($count === 1) return list_item_case($items[0], true);

  $items = array_map(
    static fn($item, $index) => list_item_case((string)$item, $index === 0),
    $items,
    array_keys($items)
  );

  if ($count === 2) return $items[0].' y '.$items[1];
  return implode(', ', array_slice($items, 0, $count - 1)).' y '.$items[$count - 1];
}

/* ===========================================================
   ACCIDENTE
=========================================================== */
$sqlAcc = "
  SELECT a.*,
         d.nombre  AS dep_nombre,
         p.nombre  AS prov_nombre,
         t.nombre  AS distrito_nombre,
         c.nombre  AS comisaria_nombre,
         fa.nombre AS fiscalia_nombre,
         CONCAT(fi.nombres,' ',fi.apellido_paterno,' ',fi.apellido_materno) AS fiscal_nombre
    FROM accidentes a
    LEFT JOIN ubigeo_departamento d ON d.cod_dep=a.cod_dep
    LEFT JOIN ubigeo_provincia  p ON p.cod_dep=a.cod_dep AND p.cod_prov=a.cod_prov
    LEFT JOIN ubigeo_distrito   t ON t.cod_dep=a.cod_dep AND t.cod_prov=a.cod_prov AND t.cod_dist=a.cod_dist
    LEFT JOIN comisarias        c ON c.id=a.comisaria_id
    LEFT JOIN fiscalia          fa ON fa.id=a.fiscalia_id
    LEFT JOIN fiscales          fi ON fi.id=a.fiscal_id
   WHERE a.id=:id
";
$st=$pdo->prepare($sqlAcc);
$st->execute([':id'=>$accidente_id]);
$ACC=$st->fetch(PDO::FETCH_ASSOC);
if(!$ACC){ http_response_code(404); echo "Accidente no encontrado"; exit; }

/* Modalidades (si existe tabla relacional) */
$MODS=''; 
try{
  $q=$pdo->prepare("SELECT m.nombre FROM accidente_modalidad am JOIN modalidad_accidente m ON m.id=am.modalidad_id WHERE am.accidente_id=:a ORDER BY m.nombre");
  $q->execute([':a'=>$accidente_id]); $MODS=join_es($q->fetchAll(PDO::FETCH_COLUMN));
}catch(Throwable $e){ $MODS=''; }

/* Consecuencia (si existe relacional) */
$CONS='';
try{
  $q=$pdo->prepare("SELECT c.nombre FROM accidente_consecuencia ac JOIN consecuencia_accidente c ON c.id=ac.consecuencia_id WHERE ac.accidente_id=:a");
  $q->execute([':a'=>$accidente_id]); $CONS=join_es($q->fetchAll(PDO::FETCH_COLUMN));
}catch(Throwable $e){ $CONS=vblank($ACC['consecuencia'] ?? ''); }

/* ===========================================================
   FUNCIONES DE OBTENCIÃ“N POR UNIDAD (UT-1 / UT-2)
=========================================================== */

/* UT vehÃ­culo por orden_participacion (involucrados_vehiculos) */
function get_unidad(PDO $pdo, $accidente_id, $ordenUT){
  $sql="
    SELECT iv.*, v.*,
           tv.codigo AS tipo_codigo, tv.nombre AS tipo_nombre,
           cv.codigo AS categoria_codigo, cv.descripcion AS categoria_descripcion,
           car.nombre AS carroceria_nombre,
           mar.nombre AS veh_marca_nombre,
           modv.nombre AS veh_modelo_nombre
      FROM involucrados_vehiculos iv
      JOIN vehiculos v            ON v.id  = iv.vehiculo_id
      LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
      LEFT JOIN categoria_vehiculos cv ON cv.id=v.categoria_id
      LEFT JOIN carroceria_vehiculo car ON car.id=v.carroceria_id
      LEFT JOIN marcas_vehiculo mar     ON mar.id=v.marca_id
      LEFT JOIN modelos_vehiculo modv   ON modv.id=v.modelo_id
     WHERE iv.accidente_id=:a AND iv.orden_participacion=:ut
     LIMIT 1
  ";
  $q=$pdo->prepare($sql);
  $q->execute([':a'=>$accidente_id, ':ut'=>$ordenUT]);
  return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* Conductor vinculado a una unidad (por vehiculo_id) */
function get_conductor_por_unidad(PDO $pdo, $accidente_id, $vehiculo_id){
  if(!$vehiculo_id) return [];
  $sql="
    SELECT ip.id AS inv_id, ip.*, per.*
      FROM involucrados_personas ip
      JOIN personas per ON per.id=ip.persona_id
     WHERE ip.accidente_id=:a
       AND ip.vehiculo_id=:v
       AND (ip.rol_id IS NOT NULL OR ip.orden_persona IS NOT NULL)
     ORDER BY (CASE WHEN ip.lesion LIKE 'Falle%' THEN 0 ELSE 1 END), ip.id ASC
     LIMIT 1
  ";
  $q=$pdo->prepare($sql);
  $q->execute([':a'=>$accidente_id, ':v'=>$vehiculo_id]);
  return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* Propietario por unidad */
function get_propietario_por_unidad(PDO $pdo, $accidente_id, $vehiculo_inv_id){
  $sql="
    SELECT pv.*,
           per.id AS persona_id, per.nombres, per.apellido_paterno, per.apellido_materno,
           per.tipo_doc, per.num_doc, pv.ruc, pv.razon_social, pv.domicilio_fiscal, pv.tipo_propietario
      FROM propietario_vehiculo pv
      LEFT JOIN personas per ON per.id = pv.propietario_persona_id
     WHERE pv.accidente_id=:a AND pv.vehiculo_inv_id=:iv
     ORDER BY pv.id DESC LIMIT 1
  ";
  $q=$pdo->prepare($sql);
  $q->execute([':a'=>$accidente_id, ':iv'=>($vehiculo_inv_id ?: 0)]);
  return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* Documento del vehÃ­culo (por vehiculo_id o involucrado_vehiculo_id) */
function get_documento_vehiculo(PDO $pdo, $vehiculo_id, $accidente_id, $iv_id=null){
  // 1) por vehiculo_id
  if ($vehiculo_id) {
    $q=$pdo->prepare("SELECT * FROM documento_vehiculo WHERE vehiculo_id=:v ORDER BY id DESC LIMIT 1");
    $q->execute([':v'=>$vehiculo_id]);
    $r=$q->fetch(PDO::FETCH_ASSOC);
    if($r) return $r;
  }
  // 2) por involucrado_vehiculo_id
  if ($iv_id) {
    $q=$pdo->prepare("SELECT * FROM documento_vehiculo WHERE involucrado_vehiculo_id=:iv ORDER BY id DESC LIMIT 1");
    $q->execute([':iv'=>$iv_id]); $r=$q->fetch(PDO::FETCH_ASSOC);
    if($r) return $r;
  }
  return [];
}

/* Documentos de persona */
function get_lc(PDO $pdo, $persona_id){
  if(!$persona_id) return [];
  $q=$pdo->prepare("SELECT * FROM documento_lc WHERE persona_id=:p ORDER BY id DESC LIMIT 1");
  $q->execute([':p'=>$persona_id]); return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}
function get_dosaje(PDO $pdo, $persona_id){
  if(!$persona_id) return [];
  $q=$pdo->prepare("SELECT * FROM documento_dosaje WHERE persona_id=:p ORDER BY id DESC LIMIT 1");
  $q->execute([':p'=>$persona_id]); return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}
function get_rml(PDO $pdo, $persona_id, $accidente_id){
  if(!$persona_id || !$accidente_id) return [];
  $q=$pdo->prepare("SELECT * FROM documento_rml WHERE persona_id=:p AND accidente_id=:a ORDER BY id DESC LIMIT 1");
  $q->execute([':p'=>$persona_id, ':a'=>$accidente_id]); return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* Documento OCCISO (peatÃ³n/conductor fallecido) */
function get_doc_occiso(PDO $pdo, $persona_id, $accidente_id){
  if (!$persona_id || !$accidente_id) return [];
  $q=$pdo->prepare("SELECT * FROM documento_occiso WHERE persona_id=:p AND accidente_id=:a ORDER BY id DESC LIMIT 1");
  $q->execute([':p'=>$persona_id, ':a'=>$accidente_id]); return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* Abogados por persona/accidente */
function get_abogado(PDO $pdo, $accidente_id, $persona_id){
  if(!$persona_id) return [];
  $q=$pdo->prepare("SELECT * FROM abogados WHERE accidente_id=:a AND persona_id=:p ORDER BY id DESC LIMIT 1");
  $q->execute([':a'=>$accidente_id, ':p'=>$persona_id]);
  $r=$q->fetch(PDO::FETCH_ASSOC);
  if($r) return $r;
  $q=$pdo->prepare("SELECT * FROM abogados WHERE persona_id=:p ORDER BY id DESC LIMIT 1");
  $q->execute([':p'=>$persona_id]); return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* Familiar fallecido (FIX: usa fallecido_inv_id, no persona_id) */
function get_familiar_fallecido(PDO $pdo, $accidente_id, $occ_persona_id=null, $occ_inv_id=null){
  if(!$accidente_id) return [];
  // resolver inv_id del fallecido
  $inv_id = $occ_inv_id;
  if(!$inv_id && $occ_persona_id){
    $q=$pdo->prepare("SELECT id FROM involucrados_personas WHERE accidente_id=:a AND persona_id=:p ORDER BY id DESC LIMIT 1");
    $q->execute([':a'=>$accidente_id, ':p'=>$occ_persona_id]); $inv_id=$q->fetchColumn();
  }
  if(!$inv_id) return [];
  $sql="
    SELECT ff.*,
           per.id AS persona_id, per.nombres, per.apellido_paterno, per.apellido_materno,
           per.tipo_doc, per.num_doc, per.sexo, per.fecha_nacimiento, per.nacionalidad,
           per.grado_instruccion, per.ocupacion, per.estado_civil, per.domicilio, per.celular, per.email
      FROM familiar_fallecido ff
      LEFT JOIN personas per ON per.id = ff.familiar_persona_id
     WHERE ff.accidente_id=:a AND ff.fallecido_inv_id=:inv
     ORDER BY ff.id DESC LIMIT 1
  ";
  $q=$pdo->prepare($sql);
  $q->execute([':a'=>$accidente_id, ':inv'=>$inv_id]);
  return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* Efectivo policial (lista completa para otros usos) */
function get_efectivos_intervinientes(PDO $pdo, $accidente_id){
  $sql="
    SELECT ep.*,
           per.nombres, per.apellido_paterno, per.apellido_materno,
           per.tipo_doc, per.num_doc, per.sexo, per.fecha_nacimiento,
           per.nacionalidad, per.grado_instruccion, per.ocupacion,
           per.estado_civil, per.domicilio, per.celular, per.email
      FROM policial_interviniente ep
      LEFT JOIN personas per ON per.id = ep.persona_id
     WHERE ep.accidente_id=:a
     ORDER BY ep.id ASC
  ";
  $q=$pdo->prepare($sql);
  $q->execute([':a'=>$accidente_id]);
  return $q->fetchAll(PDO::FETCH_ASSOC);
}

/* ===========================================================
   CARGA DE UNIDADES
=========================================================== */
$UT1 = get_unidad($pdo, $accidente_id, 'UT-1');
$UT2 = get_unidad($pdo, $accidente_id, 'UT-2');

$COND1 = get_conductor_por_unidad($pdo, $accidente_id, $UT1['vehiculo_id'] ?? null);
$COND2 = get_conductor_por_unidad($pdo, $accidente_id, $UT2['vehiculo_id'] ?? null);

$PROP1 = get_propietario_por_unidad($pdo, $accidente_id, $UT1['id'] ?? null);
$PROP2 = get_propietario_por_unidad($pdo, $accidente_id, $UT2['id'] ?? null);

$DOCV1 = get_documento_vehiculo($pdo, $UT1['vehiculo_id'] ?? null, $accidente_id, $UT1['id'] ?? null);
$DOCV2 = get_documento_vehiculo($pdo, $UT2['vehiculo_id'] ?? null, $accidente_id, $UT2['id'] ?? null);

/* Documentos de conductores */
$LC1 = get_lc($pdo, $COND1['persona_id'] ?? null);
$LC2 = get_lc($pdo, $COND2['persona_id'] ?? null);
$DOS1 = get_dosaje($pdo, $COND1['persona_id'] ?? null);
$DOS2 = get_dosaje($pdo, $COND2['persona_id'] ?? null);
$RML1 = get_rml($pdo, $COND1['persona_id'] ?? null, $accidente_id);
$RML2 = get_rml($pdo, $COND2['persona_id'] ?? null, $accidente_id);

/* Occiso por unidad (si el conductor falleciÃ³) */
$OCC1 = [];
if (!empty($COND1['lesion']) && stripos($COND1['lesion'],'falle')!==false){
  $OCC1 = [
    'persona_id' => $COND1['persona_id'] ?? null,
    'inv_id'     => $COND1['inv_id']     ?? null,
  ];
}
$OCC2 = [];
if (!empty($COND2['lesion']) && stripos($COND2['lesion'],'falle')!==false){
  $OCC2 = [
    'persona_id' => $COND2['persona_id'] ?? null,
    'inv_id'     => $COND2['inv_id']     ?? null,
  ];
}

/* Documento occiso (si aplica) */
$DOCO1 = !empty($OCC1) ? get_doc_occiso($pdo, $OCC1['persona_id'],$accidente_id) : [];
$DOCO2 = !empty($OCC2) ? get_doc_occiso($pdo, $OCC2['persona_id'],$accidente_id) : [];

/* Familiar del fallecido (FIX con fallecido_inv_id) */
$FAM1 = !empty($OCC1) ? get_familiar_fallecido($pdo, $accidente_id, $OCC1['persona_id'] ?? null, $OCC1['inv_id'] ?? null) : [];
$FAM2 = !empty($OCC2) ? get_familiar_fallecido($pdo, $accidente_id, $OCC2['persona_id'] ?? null, $OCC2['inv_id'] ?? null) : [];

/* Abogados */
$ABOG_COND1 = get_abogado($pdo, $accidente_id, $COND1['persona_id'] ?? null);
$ABOG_COND2 = get_abogado($pdo, $accidente_id, $COND2['persona_id'] ?? null);
$ABOG_PROP1 = get_abogado($pdo, $accidente_id, $PROP1['persona_id'] ?? null);
$ABOG_PROP2 = get_abogado($pdo, $accidente_id, $PROP2['persona_id'] ?? null);

/* ===========================================================
   PLANTILLA
=========================================================== */
$tplPath = __DIR__.'/plantillas/informe_choque_dos_vehiculos.docx';
if(!is_file($tplPath)){ http_response_code(500); echo "No existe la plantilla: $tplPath"; exit; }

$T = new TemplateProcessor($tplPath);

/* ---------- Accidente ---------- */
$T->setValue('nro_informe_policial', v($ACC['nro_informe_policial']));
$T->setValue('acc_sidpol',           v($ACC['sidpol']));
$T->setValue('acc_registro_sidpol',  v($ACC['registro_sidpol'] ?? $ACC['sidpol'] ?? ''));
$T->setValue('acc_lugar',            v($ACC['lugar']));
$T->setValue('acc_referencia',       v($ACC['referencia']));
$T->setValue('dep_nombre',           v($ACC['dep_nombre']));
$T->setValue('prov_nombre',          v($ACC['prov_nombre']));
$T->setValue('distrito',             v($ACC['distrito_nombre']));
$T->setValue('comisaria',            v($ACC['comisaria_nombre']));
$T->setValue('modalidad',            v($MODS));
$T->setValue('consecuencia',         v($CONS));
$T->setValue('fiscalia',             v($ACC['fiscalia_nombre']));
$T->setValue('fiscal_nombre',        v($ACC['fiscal_nombre']));
$T->setValue('acc_fecha', ymd_pe($ACC['fecha_accidente']));
$T->setValue('acc_hora',  hora_pe($ACC['fecha_accidente']));
$T->setValue('com_fecha', ymd_pe($ACC['fecha_comunicacion']));
$T->setValue('com_hora',  hora_pe($ACC['fecha_comunicacion']));
$T->setValue('int_fecha', ymd_pe($ACC['fecha_intervencion']));
$T->setValue('int_hora',  hora_pe($ACC['fecha_intervencion']));

/* ---------- UT-1: VehÃ­culo ---------- */
$T->setValue('ut1_veh_placa',           v($UT1['placa'] ?? ''));
$T->setValue('ut1_veh_marca',           v($UT1['veh_marca_nombre'] ?? ''));
$T->setValue('ut1_veh_modelo',          v($UT1['veh_modelo_nombre'] ?? ''));
$T->setValue('ut1_veh_color',           v($UT1['color'] ?? ''));
$T->setValue('ut1_veh_anio',            v($UT1['anio'] ?? ''));
$T->setValue('ut1_veh_carroceria',      v($UT1['carroceria_nombre'] ?? ''));
$T->setValue('ut1_veh_tipo',            v($UT1['tipo_nombre'] ?? ''));
$T->setValue('ut1_veh_categoria_cod',   v($UT1['categoria_codigo'] ?? ''));
$T->setValue('ut1_veh_categoria_desc',  v($UT1['categoria_descripcion'] ?? ''));

/* Propietario UT-1 */
$T->setValue('ut1_prop_tipo',      v($PROP1['tipo_propietario'] ?? ''));
$T->setValue('ut1_prop_doc_tipo',  v($PROP1['tipo_doc'] ?? ''));
$T->setValue('ut1_prop_doc_num',   v(($PROP1['num_doc'] ?? '') ?: ($PROP1['ruc'] ?? '')));
$T->setValue('ut1_prop_nombre',    v(($PROP1['razon_social'] ?? '') ?: nombre_completo($PROP1['nombres'] ?? '', $PROP1['apellido_paterno'] ?? '', $PROP1['apellido_materno'] ?? '')));
$T->setValue('ut1_prop_domicilio', v($PROP1['domicilio_fiscal'] ?? ''));
/* Abogado propietario UT-1 */
$T->setValue('ut1_prop_abog_nombre',  v(nombre_completo($ABOG_PROP1['nombres'] ?? '', $ABOG_PROP1['apellido_paterno'] ?? '', $ABOG_PROP1['apellido_materno'] ?? '')));
$T->setValue('ut1_prop_abog_cond',    v($ABOG_PROP1['condicion'] ?? ''));
$T->setValue('ut1_prop_abog_coleg',   v($ABOG_PROP1['colegiatura'] ?? ''));
$T->setValue('ut1_prop_abog_reg',     v($ABOG_PROP1['registro'] ?? ''));
$T->setValue('ut1_prop_abog_casilla', v($ABOG_PROP1['casilla_electronica'] ?? ''));
$T->setValue('ut1_prop_abog_domproc', v($ABOG_PROP1['domicilio_procesal'] ?? ''));
$T->setValue('ut1_prop_abog_cel',     v($ABOG_PROP1['celular'] ?? ''));
$T->setValue('ut1_prop_abog_email',   v($ABOG_PROP1['email'] ?? ''));

/* Documentos vehÃ­culo UT-1 */
$T->setValue('ut1_doc_num_propiedad',      v($DOCV1['numero_propiedad'] ?? ''));
$T->setValue('ut1_doc_partida_propiedad',  v($DOCV1['partida_propiedad'] ?? ''));
$T->setValue('ut1_doc_titulo_propiedad',   v($DOCV1['titulo_propiedad'] ?? ''));
$T->setValue('ut1_doc_sede_propiedad',     v($DOCV1['sede_propiedad'] ?? ''));
$T->setValue('ut1_doc_num_soat',           v($DOCV1['numero_soat'] ?? ''));
$T->setValue('ut1_doc_aseguradora_soat',   v($DOCV1['aseguradora_soat'] ?? ''));
$T->setValue('ut1_doc_vigente_soat',       ymd_pe($DOCV1['vigente_soat'] ?? ''));
$T->setValue('ut1_doc_vencimiento_soat',   ymd_pe($DOCV1['vencimiento_soat'] ?? ''));
$T->setValue('ut1_doc_num_revision',       v($DOCV1['numero_revision'] ?? ''));
$T->setValue('ut1_doc_certificado_revision',v($DOCV1['certificadora_revision'] ?? ''));
$T->setValue('ut1_doc_vigente_revision',   ymd_pe($DOCV1['vigente_revision'] ?? ''));
$T->setValue('ut1_doc_vencimiento_revision', ymd_pe($DOCV1['vencimiento_revision'] ?? ''));
$T->setValue('ut1_doc_num_peritaje',       v($DOCV1['numero_peritaje'] ?? ''));
$T->setValue('ut1_doc_fecha_peritaje',     ymd_pe($DOCV1['fecha_peritaje'] ?? ''));
$T->setValue('ut1_doc_perito_peritaje',    v($DOCV1['perito_peritaje'] ?? ''));
$T->setValue('ut1_doc_danos_peritaje',     v($DOCV1['danos_peritaje'] ?? ''));

/* Conductor UT-1 */
$T->setValue('ut1_cond_doc_tipo',     v($COND1['tipo_doc'] ?? ''));
$T->setValue('ut1_cond_doc_num',      v($COND1['num_doc'] ?? ''));
$T->setValue('ut1_cond_apep',         v($COND1['apellido_paterno'] ?? ''));
$T->setValue('ut1_cond_apem',         v($COND1['apellido_materno'] ?? ''));
$T->setValue('ut1_cond_nombres',      v($COND1['nombres'] ?? ''));
$T->setValue('ut1_cond_sexo',         v($COND1['sexo'] ?? ''));
$T->setValue('ut1_cond_nacim',        fecha_corta($COND1['fecha_nacimiento'] ?? ''));
$T->setValue('ut1_cond_edad',         v(($COND1['edad']??'')!=='' ? $COND1['edad'] : edad_from($COND1['fecha_nacimiento'] ?? '')));
$T->setValue('ut1_cond_dep_nac',      v($COND1['departamento_nac'] ?? ''));
$T->setValue('ut1_cond_grado_instr',  v($COND1['grado_instruccion'] ?? ''));
$T->setValue('ut1_cond_estado_civil', v($COND1['estado_civil'] ?? ''));
$T->setValue('ut1_cond_nacionalidad', v($COND1['nacionalidad'] ?? ''));
$T->setValue('ut1_cond_domicilio',    v($COND1['domicilio'] ?? ''));
$T->setValue('ut1_cond_celular',      v($COND1['celular'] ?? ''));
$T->setValue('ut1_cond_email',        v($COND1['email'] ?? ''));
$T->setValue('ut1_cond_lesion',       v($COND1['lesion'] ?? ''));
$T->setValue('ut1_cond_observ',       v($COND1['observaciones'] ?? ''));
/* Abogado conductor UT-1 */
$T->setValue('ut1_cond_abog_nombre',  v(nombre_completo($ABOG_COND1['nombres'] ?? '', $ABOG_COND1['apellido_paterno'] ?? '', $ABOG_COND1['apellido_materno'] ?? '')));
$T->setValue('ut1_cond_abog_cond',    v($ABOG_COND1['condicion'] ?? ''));
$T->setValue('ut1_cond_abog_coleg',   v($ABOG_COND1['colegiatura'] ?? ''));
$T->setValue('ut1_cond_abog_reg',     v($ABOG_COND1['registro'] ?? ''));
$T->setValue('ut1_cond_abog_casilla', v($ABOG_COND1['casilla_electronica'] ?? ''));
$T->setValue('ut1_cond_abog_domproc', v($ABOG_COND1['domicilio_procesal'] ?? ''));
$T->setValue('ut1_cond_abog_cel',     v($ABOG_COND1['celular'] ?? ''));
$T->setValue('ut1_cond_abog_email',   v($ABOG_COND1['email'] ?? ''));

/* Licencia / Dosaje / RML UT-1 */
$T->setValue('ut1_lc_clase',         v($LC1['clase'] ?? ''));
$T->setValue('ut1_lc_categoria',     v($LC1['categoria'] ?? ''));
$T->setValue('ut1_lc_numero',        v($LC1['numero'] ?? ''));
$T->setValue('ut1_lc_expedido_por',  v($LC1['expedido_por'] ?? ''));
$T->setValue('ut1_lc_vigente_desde', ymd_pe($LC1['vigente_desde'] ?? ''));
$T->setValue('ut1_lc_vigente_hasta', ymd_pe($LC1['vigente_hasta'] ?? ''));
$T->setValue('ut1_lc_restricciones', v($LC1['restricciones'] ?? ''));

$T->setValue('ut1_dosaje_numero',          v($DOS1['numero'] ?? ''));
$T->setValue('ut1_dosaje_registro',        v($DOS1['numero_registro'] ?? ''));
$T->setValue('ut1_dosaje_fecha',           ymd_pe($DOS1['fecha_extraccion'] ?? ''));
$T->setValue('ut1_dosaje_resultado_cual',  v($DOS1['resultado_cualitativo'] ?? ''));
$T->setValue('ut1_dosaje_resultado_cuant', v($DOS1['resultado_cuantitativo'] ?? ''));
$T->setValue('ut1_dosaje_observ',          v($DOS1['observaciones'] ?? ''));

$T->setValue('ut1_rml_numero',       v($RML1['numero'] ?? ''));
$T->setValue('ut1_rml_fecha',        ymd_pe($RML1['fecha'] ?? ''));
$T->setValue('ut1_rml_incapacidad',  v($RML1['incapacidad_medico'] ?? ''));
$T->setValue('ut1_rml_atencion',     v($RML1['atencion_facultativo'] ?? ''));
$T->setValue('ut1_rml_observ',       v($RML1['observaciones'] ?? ''));

/* Occiso UT-1 (si aplica) */
$T->setValue('ut1_occ_nombres',      v(nombre_completo($COND1['nombres'] ?? '', $COND1['apellido_paterno'] ?? '', $COND1['apellido_materno'] ?? '')));
$T->setValue('ut1_occ_doc',          v(trim(($COND1['tipo_doc'] ?? '').' '.($COND1['num_doc'] ?? ''))));
$T->setValue('ut1_occ_fecha_lev',    ymd_pe($DOCO1['fecha_levantamiento'] ?? ''));
$T->setValue('ut1_occ_hora_lev',     v(hora_pe($DOCO1['hora_levantamiento'] ?? '')));
$T->setValue('ut1_occ_lugar_lev',    v($DOCO1['lugar_levantamiento'] ?? ''));
$T->setValue('ut1_occ_posicion_cuerpo', v($DOCO1['posicion_cuerpo_levantamiento'] ?? ''));
$T->setValue('ut1_occ_lesiones_lev', v($DOCO1['lesiones_levantamiento'] ?? ''));
$T->setValue('ut1_occ_presuntivo_lev', v($DOCO1['presuntivo_levantamiento'] ?? ''));
$T->setValue('ut1_occ_legista',      v($DOCO1['legista_levantamiento'] ?? ''));
$T->setValue('ut1_occ_cmp_legista',  v($DOCO1['cmp_legista'] ?? ''));
$T->setValue('ut1_occ_obs_lev',      v($DOCO1['observaciones_levantamiento'] ?? ''));
$T->setValue('ut1_occ_num_pericial', v($DOCO1['numero_pericial'] ?? ''));
$T->setValue('ut1_occ_fecha_pericial', ymd_pe($DOCO1['fecha_pericial'] ?? ''));
$T->setValue('ut1_occ_hora_pericial',  v(hora_pe($DOCO1['hora_pericial'] ?? '')));
$T->setValue('ut1_occ_obs_pericial', v($DOCO1['observaciones_pericial'] ?? ''));
$T->setValue('ut1_occ_num_protocolo', v($DOCO1['numero_protocolo'] ?? ''));
$T->setValue('ut1_occ_fecha_protocolo', ymd_pe($DOCO1['fecha_protocolo'] ?? ''));
$T->setValue('ut1_occ_hora_protocolo',  v(hora_pe($DOCO1['hora_protocolo'] ?? '')));
$T->setValue('ut1_occ_lesiones_prot', v($DOCO1['lesiones_protocolo'] ?? ''));
$T->setValue('ut1_occ_presuntivo_prot', v($DOCO1['presuntivo_protocolo'] ?? ''));
$T->setValue('ut1_occ_dosaje_prot',  v($DOCO1['dosaje_protocolo'] ?? ''));
$T->setValue('ut1_occ_toxico_prot',  v($DOCO1['toxicologico_protocolo'] ?? ''));
$T->setValue('ut1_occ_nosoc_epicrisis', v($DOCO1['nosocomio_epicrisis'] ?? ''));
$T->setValue('ut1_occ_num_hist_epic', v($DOCO1['numero_historia_epicrisis'] ?? ''));
$T->setValue('ut1_occ_trat_epic',    v($DOCO1['tratamiento_epicrisis'] ?? ''));
$T->setValue('ut1_occ_hora_alta',    v(hora_pe($DOCO1['hora_alta_epicrisis'] ?? '')));

/* Familiar del fallecido UT-1 */
$T->setValue('ut1_fam_parentesco',  v($FAM1['parentesco'] ?? ''));
$T->setValue('ut1_fam_nombres',     v(nombre_completo($FAM1['nombres'] ?? '', $FAM1['apellido_paterno'] ?? '', $FAM1['apellido_materno'] ?? '')));
$T->setValue('ut1_fam_doc',         v(trim(($FAM1['tipo_doc'] ?? '').' '.($FAM1['num_doc'] ?? ''))));
$T->setValue('ut1_fam_sexo',        v($FAM1['sexo'] ?? ''));
$T->setValue('ut1_fam_fecnac',      fecha_corta($FAM1['fecha_nacimiento'] ?? ''));
$T->setValue('ut1_fam_edad',        v(edad_from($FAM1['fecha_nacimiento'] ?? '')));
$T->setValue('ut1_fam_nacionalidad',v($FAM1['nacionalidad'] ?? ''));
$T->setValue('ut1_fam_grado_instr', v($FAM1['grado_instruccion'] ?? ''));
$T->setValue('ut1_fam_ocupacion',   v($FAM1['ocupacion'] ?? ''));
$T->setValue('ut1_fam_estado_civil',v($FAM1['estado_civil'] ?? ''));
$T->setValue('ut1_fam_domicilio',   v($FAM1['domicilio'] ?? ''));
$T->setValue('ut1_fam_celular',     v($FAM1['celular'] ?? ''));
$T->setValue('ut1_fam_email',       v($FAM1['email'] ?? ''));

/* ---------- UT-2 (mismos mapeos) ---------- */
$T->setValue('ut2_veh_placa',           v($UT2['placa'] ?? ''));
$T->setValue('ut2_veh_marca',           v($UT2['veh_marca_nombre'] ?? ''));
$T->setValue('ut2_veh_modelo',          v($UT2['veh_modelo_nombre'] ?? ''));
$T->setValue('ut2_veh_color',           v($UT2['color'] ?? ''));
$T->setValue('ut2_veh_anio',            v($UT2['anio'] ?? ''));
$T->setValue('ut2_veh_carroceria',      v($UT2['carroceria_nombre'] ?? ''));
$T->setValue('ut2_veh_tipo',            v($UT2['tipo_nombre'] ?? ''));
$T->setValue('ut2_veh_categoria_cod',   v($UT2['categoria_codigo'] ?? ''));
$T->setValue('ut2_veh_categoria_desc',  v($UT2['categoria_descripcion'] ?? ''));

$T->setValue('ut2_prop_tipo',      v($PROP2['tipo_propietario'] ?? ''));
$T->setValue('ut2_prop_doc_tipo',  v($PROP2['tipo_doc'] ?? ''));
$T->setValue('ut2_prop_doc_num',   v(($PROP2['num_doc'] ?? '') ?: ($PROP2['ruc'] ?? '')));
$T->setValue('ut2_prop_nombre',    v(($PROP2['razon_social'] ?? '') ?: nombre_completo($PROP2['nombres'] ?? '', $PROP2['apellido_paterno'] ?? '', $PROP2['apellido_materno'] ?? '')));
$T->setValue('ut2_prop_domicilio', v($PROP2['domicilio_fiscal'] ?? ''));

$T->setValue('ut2_prop_abog_nombre',  v(nombre_completo($ABOG_PROP2['nombres'] ?? '', $ABOG_PROP2['apellido_paterno'] ?? '', $ABOG_PROP2['apellido_materno'] ?? '')));
$T->setValue('ut2_prop_abog_cond',    v($ABOG_PROP2['condicion'] ?? ''));
$T->setValue('ut2_prop_abog_coleg',   v($ABOG_PROP2['colegiatura'] ?? ''));
$T->setValue('ut2_prop_abog_reg',     v($ABOG_PROP2['registro'] ?? ''));
$T->setValue('ut2_prop_abog_casilla', v($ABOG_PROP2['casilla_electronica'] ?? ''));
$T->setValue('ut2_prop_abog_domproc', v($ABOG_PROP2['domicilio_procesal'] ?? ''));
$T->setValue('ut2_prop_abog_cel',     v($ABOG_PROP2['celular'] ?? ''));
$T->setValue('ut2_prop_abog_email',   v($ABOG_PROP2['email'] ?? ''));

/* Documentos vehÃ­culo UT-2 */
$T->setValue('ut2_doc_num_propiedad',      v($DOCV2['numero_propiedad'] ?? ''));
$T->setValue('ut2_doc_partida_propiedad',  v($DOCV2['partida_propiedad'] ?? ''));
$T->setValue('ut2_doc_titulo_propiedad',   v($DOCV2['titulo_propiedad'] ?? ''));
$T->setValue('ut2_doc_sede_propiedad',     v($DOCV2['sede_propiedad'] ?? ''));
$T->setValue('ut2_doc_num_soat',           v($DOCV2['numero_soat'] ?? ''));
$T->setValue('ut2_doc_aseguradora_soat',   v($DOCV2['aseguradora_soat'] ?? ''));
$T->setValue('ut2_doc_vigente_soat',       ymd_pe($DOCV2['vigente_soat'] ?? ''));
$T->setValue('ut2_doc_vencimiento_soat',   ymd_pe($DOCV2['vencimiento_soat'] ?? ''));
$T->setValue('ut2_doc_num_revision',       v($DOCV2['numero_revision'] ?? ''));
$T->setValue('ut2_doc_certificado_revision',v($DOCV2['certificadora_revision'] ?? ''));
$T->setValue('ut2_doc_vigente_revision',   ymd_pe($DOCV2['vigente_revision'] ?? ''));
$T->setValue('ut2_doc_vencimiento_revision', ymd_pe($DOCV2['vencimiento_revision'] ?? ''));
$T->setValue('ut2_doc_num_peritaje',       v($DOCV2['numero_peritaje'] ?? ''));
$T->setValue('ut2_doc_fecha_peritaje',     ymd_pe($DOCV2['fecha_peritaje'] ?? ''));
$T->setValue('ut2_doc_perito_peritaje',    v($DOCV2['perito_peritaje'] ?? ''));
$T->setValue('ut2_doc_danos_peritaje',     v($DOCV2['danos_peritaje'] ?? ''));

/* Conductor UT-2 */
$T->setValue('ut2_cond_doc_tipo',     v($COND2['tipo_doc'] ?? ''));
$T->setValue('ut2_cond_doc_num',      v($COND2['num_doc'] ?? ''));
$T->setValue('ut2_cond_apep',         v($COND2['apellido_paterno'] ?? ''));
$T->setValue('ut2_cond_apem',         v($COND2['apellido_materno'] ?? ''));
$T->setValue('ut2_cond_nombres',      v($COND2['nombres'] ?? ''));
$T->setValue('ut2_cond_sexo',         v($COND2['sexo'] ?? ''));
$T->setValue('ut2_cond_nacim',        fecha_corta($COND2['fecha_nacimiento'] ?? ''));
$T->setValue('ut2_cond_edad',         v(($COND2['edad']??'')!=='' ? $COND2['edad'] : edad_from($COND2['fecha_nacimiento'] ?? '')));
$T->setValue('ut2_cond_dep_nac',      v($COND2['departamento_nac'] ?? ''));
$T->setValue('ut2_cond_grado_instr',  v($COND2['grado_instruccion'] ?? ''));
$T->setValue('ut2_cond_estado_civil', v($COND2['estado_civil'] ?? ''));
$T->setValue('ut2_cond_nacionalidad', v($COND2['nacionalidad'] ?? ''));
$T->setValue('ut2_cond_domicilio',    v($COND2['domicilio'] ?? ''));
$T->setValue('ut2_cond_celular',      v($COND2['celular'] ?? ''));
$T->setValue('ut2_cond_email',        v($COND2['email'] ?? ''));
$T->setValue('ut2_cond_lesion',       v($COND2['lesion'] ?? ''));
$T->setValue('ut2_cond_observ',       v($COND2['observaciones'] ?? ''));

$T->setValue('ut2_cond_abog_nombre',  v(nombre_completo($ABOG_COND2['nombres'] ?? '', $ABOG_COND2['apellido_paterno'] ?? '', $ABOG_COND2['apellido_materno'] ?? '')));
$T->setValue('ut2_cond_abog_cond',    v($ABOG_COND2['condicion'] ?? ''));
$T->setValue('ut2_cond_abog_coleg',   v($ABOG_COND2['colegiatura'] ?? ''));
$T->setValue('ut2_cond_abog_reg',     v($ABOG_COND2['registro'] ?? ''));
$T->setValue('ut2_cond_abog_casilla', v($ABOG_COND2['casilla_electronica'] ?? ''));
$T->setValue('ut2_cond_abog_domproc', v($ABOG_COND2['domicilio_procesal'] ?? ''));
$T->setValue('ut2_cond_abog_cel',     v($ABOG_COND2['celular'] ?? ''));
$T->setValue('ut2_cond_abog_email',   v($ABOG_COND2['email'] ?? ''));

$T->setValue('ut2_lc_clase',         v($LC2['clase'] ?? ''));
$T->setValue('ut2_lc_categoria',     v($LC2['categoria'] ?? ''));
$T->setValue('ut2_lc_numero',        v($LC2['numero'] ?? ''));
$T->setValue('ut2_lc_expedido_por',  v($LC2['expedido_por'] ?? ''));
$T->setValue('ut2_lc_vigente_desde', ymd_pe($LC2['vigente_desde'] ?? ''));
$T->setValue('ut2_lc_vigente_hasta', ymd_pe($LC2['vigente_hasta'] ?? ''));
$T->setValue('ut2_lc_restricciones', v($LC2['restricciones'] ?? ''));

$T->setValue('ut2_dosaje_numero',          v($DOS2['numero'] ?? ''));
$T->setValue('ut2_dosaje_registro',        v($DOS2['numero_registro'] ?? ''));
$T->setValue('ut2_dosaje_fecha',           ymd_pe($DOS2['fecha_extraccion'] ?? ''));
$T->setValue('ut2_dosaje_resultado_cual',  v($DOS2['resultado_cualitativo'] ?? ''));
$T->setValue('ut2_dosaje_resultado_cuant', v($DOS2['resultado_cuantitativo'] ?? ''));
$T->setValue('ut2_dosaje_observ',          v($DOS2['observaciones'] ?? ''));

$T->setValue('ut2_rml_numero',       v($RML2['numero'] ?? ''));
$T->setValue('ut2_rml_fecha',        ymd_pe($RML2['fecha'] ?? ''));
$T->setValue('ut2_rml_incapacidad',  v($RML2['incapacidad_medico'] ?? ''));
$T->setValue('ut2_rml_atencion',     v($RML2['atencion_facultativo'] ?? ''));
$T->setValue('ut2_rml_observ',       v($RML2['observaciones'] ?? ''));

/* Occiso UT-2 */
$T->setValue('ut2_occ_nombres',      v(nombre_completo($COND2['nombres'] ?? '', $COND2['apellido_paterno'] ?? '', $COND2['apellido_materno'] ?? '')));
$T->setValue('ut2_occ_doc',          v(trim(($COND2['tipo_doc'] ?? '').' '.($COND2['num_doc'] ?? ''))));
$T->setValue('ut2_occ_fecha_lev',    ymd_pe($DOCO2['fecha_levantamiento'] ?? ''));
$T->setValue('ut2_occ_hora_lev',     v(hora_pe($DOCO2['hora_levantamiento'] ?? '')));
$T->setValue('ut2_occ_lugar_lev',    v($DOCO2['lugar_levantamiento'] ?? ''));
$T->setValue('ut2_occ_posicion_cuerpo', v($DOCO2['posicion_cuerpo_levantamiento'] ?? ''));
$T->setValue('ut2_occ_lesiones_lev', v($DOCO2['lesiones_levantamiento'] ?? ''));
$T->setValue('ut2_occ_presuntivo_lev', v($DOCO2['presuntivo_levantamiento'] ?? ''));
$T->setValue('ut2_occ_legista',      v($DOCO2['legista_levantamiento'] ?? ''));
$T->setValue('ut2_occ_cmp_legista',  v($DOCO2['cmp_legista'] ?? ''));
$T->setValue('ut2_occ_obs_lev',      v($DOCO2['observaciones_levantamiento'] ?? ''));
$T->setValue('ut2_occ_num_pericial', v($DOCO2['numero_pericial'] ?? ''));
$T->setValue('ut2_occ_fecha_pericial', ymd_pe($DOCO2['fecha_pericial'] ?? ''));
$T->setValue('ut2_occ_hora_pericial',  v(hora_pe($DOCO2['hora_pericial'] ?? '')));
$T->setValue('ut2_occ_obs_pericial', v($DOCO2['observaciones_pericial'] ?? ''));
$T->setValue('ut2_occ_num_protocolo', v($DOCO2['numero_protocolo'] ?? ''));
$T->setValue('ut2_occ_fecha_protocolo', ymd_pe($DOCO2['fecha_protocolo'] ?? ''));
$T->setValue('ut2_occ_hora_protocolo',  v(hora_pe($DOCO2['hora_protocolo'] ?? '')));
$T->setValue('ut2_occ_lesiones_prot', v($DOCO2['lesiones_protocolo'] ?? ''));
$T->setValue('ut2_occ_presuntivo_prot', v($DOCO2['presuntivo_protocolo'] ?? ''));
$T->setValue('ut2_occ_dosaje_prot',  v($DOCO2['dosaje_protocolo'] ?? ''));
$T->setValue('ut2_occ_toxico_prot',  v($DOCO2['toxicologico_protocolo'] ?? ''));
$T->setValue('ut2_occ_nosoc_epicrisis', v($DOCO2['nosocomio_epicrisis'] ?? ''));
$T->setValue('ut2_occ_num_hist_epic', v($DOCO2['numero_historia_epicrisis'] ?? ''));
$T->setValue('ut2_occ_trat_epic',    v($DOCO2['tratamiento_epicrisis'] ?? ''));
$T->setValue('ut2_occ_hora_alta',    v(hora_pe($DOCO2['hora_alta_epicrisis'] ?? '')));

/* Familiar del fallecido UT-2 */
$T->setValue('ut2_fam_parentesco',  v($FAM2['parentesco'] ?? ''));
$T->setValue('ut2_fam_nombres',     v(nombre_completo($FAM2['nombres'] ?? '', $FAM2['apellido_paterno'] ?? '', $FAM2['apellido_materno'] ?? '')));
$T->setValue('ut2_fam_doc',         v(trim(($FAM2['tipo_doc'] ?? '').' '.($FAM2['num_doc'] ?? ''))));
$T->setValue('ut2_fam_sexo',        v($FAM2['sexo'] ?? ''));
$T->setValue('ut2_fam_fecnac',      fecha_corta($FAM2['fecha_nacimiento'] ?? ''));
$T->setValue('ut2_fam_edad',        v(edad_from($FAM2['fecha_nacimiento'] ?? '')));
$T->setValue('ut2_fam_nacionalidad',v($FAM2['nacionalidad'] ?? ''));
$T->setValue('ut2_fam_grado_instr', v($FAM2['grado_instruccion'] ?? ''));
$T->setValue('ut2_fam_ocupacion',   v($FAM2['ocupacion'] ?? ''));
$T->setValue('ut2_fam_estado_civil',v($FAM2['estado_civil'] ?? ''));
$T->setValue('ut2_fam_domicilio',   v($FAM2['domicilio'] ?? ''));
$T->setValue('ut2_fam_celular',     v($FAM2['celular'] ?? ''));
$T->setValue('ut2_fam_email',       v($FAM2['email'] ?? ''));

/* ===========================================================
   EFECTIVO POLICIAL (ÃšNICO)  ->  ***COLOCADO DESPUÃ‰S DE CREAR $T***
=========================================================== */
$EFEC=[];
try{
  $q=$pdo->prepare("
    SELECT ep.*,
           p.nombres, p.apellido_paterno, p.apellido_materno,
           p.tipo_doc, p.num_doc, p.sexo, p.fecha_nacimiento,
           p.nacionalidad, p.grado_instruccion, p.ocupacion,
           p.estado_civil, p.domicilio, p.celular, p.email
      FROM policial_interviniente ep
      LEFT JOIN personas p ON p.id=ep.persona_id
     WHERE ep.accidente_id=:a
     ORDER BY ep.id ASC LIMIT 1
  ");
  $q->execute([':a'=>$accidente_id]); $EFEC=$q->fetch(PDO::FETCH_ASSOC) ?: [];
}catch(Throwable $e){ if($DEBUG) error_log($e->getMessage()); }

$efecCampos=['apep','apem','nombres','grado_policial','cip','dependencia_policial','rol_funcion','tipo_doc','num_doc','sexo','fecha_nacimiento','edad','nacionalidad','grado_instruccion','ocupacion','estado_civil','domicilio','celular','email','observaciones'];
foreach($efecCampos as $c){ $T->setValue("efec1_{$c}", ''); }
if(!empty($EFEC)){
  $T->setValue('efec1_apep',                 vblank($EFEC['apellido_paterno'] ?? ''));
  $T->setValue('efec1_apem',                 vblank($EFEC['apellido_materno'] ?? ''));
  $T->setValue('efec1_nombres',              vblank($EFEC['nombres'] ?? ''));
  $T->setValue('efec1_grado_policial',       vblank($EFEC['grado_policial'] ?? ''));
  $T->setValue('efec1_cip',                  vblank($EFEC['cip'] ?? ''));
  $T->setValue('efec1_dependencia_policial', vblank($EFEC['dependencia_policial'] ?? ''));
  $T->setValue('efec1_rol_funcion',          vblank($EFEC['rol_funcion'] ?? ''));
  $T->setValue('efec1_tipo_doc',             vblank($EFEC['tipo_doc'] ?? ''));
  $T->setValue('efec1_num_doc',              vblank($EFEC['num_doc'] ?? ''));
  $T->setValue('efec1_sexo',                 vblank($EFEC['sexo'] ?? ''));
  $T->setValue('efec1_fecha_nacimiento',     vblank(fecha_corta($EFEC['fecha_nacimiento'] ?? '')));
  $T->setValue('efec1_edad',                 vblank(edad_from($EFEC['fecha_nacimiento'] ?? '')));
  $T->setValue('efec1_nacionalidad',         vblank($EFEC['nacionalidad'] ?? ''));
  $T->setValue('efec1_grado_instruccion',    vblank($EFEC['grado_instruccion'] ?? ''));
  $T->setValue('efec1_ocupacion',            vblank($EFEC['ocupacion'] ?? ''));
  $T->setValue('efec1_estado_civil',         vblank($EFEC['estado_civil'] ?? ''));
  $T->setValue('efec1_domicilio',            vblank($EFEC['domicilio'] ?? ''));
  $T->setValue('efec1_celular',              vblank($EFEC['celular'] ?? ''));
  $T->setValue('efec1_email',                vblank($EFEC['email'] ?? ''));
  $T->setValue('efec1_observaciones',        vblank($EFEC['observaciones'] ?? $EFEC['observaciones_ep'] ?? ''));
}

/* ===========================================================
   DILIGENCIAS (placeholder)
=========================================================== */
word_manifestation_set_template($T, 'ut1_cond_man', word_manifestation_first($pdo, $accidente_id, (int) ($COND1['persona_id'] ?? 0)));
word_manifestation_set_template($T, 'ut2_cond_man', word_manifestation_first($pdo, $accidente_id, (int) ($COND2['persona_id'] ?? 0)));
word_manifestation_set_template($T, 'ut1_prop_man', word_manifestation_first($pdo, $accidente_id, (int) ($PROP1['persona_id'] ?? 0)));
word_manifestation_set_template($T, 'ut2_prop_man', word_manifestation_first($pdo, $accidente_id, (int) ($PROP2['persona_id'] ?? 0)));
word_manifestation_set_template($T, 'ut1_fam_man', word_manifestation_first($pdo, $accidente_id, (int) ($FAM1['persona_id'] ?? 0)));
word_manifestation_set_template($T, 'ut2_fam_man', word_manifestation_first($pdo, $accidente_id, (int) ($FAM2['persona_id'] ?? 0)));
word_manifestation_fill_global_template($T, $pdo, $accidente_id);

$T->setValue('diligencias', 'â€”');

/* ---------- Salida ---------- */
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }
$tmp = tempnam($tmpDir,'wchq2v_');
if ($tmp === false) {
  http_response_code(500);
  exit('No se pudo crear un archivo temporal para el DOCX.');
}
$T->saveAs($tmp);
$filename = uiat_vehicle_report_filename('UT1', 'Choque', $UT1, $COND1, 'INFORME_CHOQUE_DOS_VEHICULOS_' . ($ACC['id'] ?? '0'));

while (ob_get_level()) { @ob_end_clean(); }
if (headers_sent($fileSent, $lineSent)) {
  @unlink($tmp);
  http_response_code(500);
  exit('No se pudo iniciar la descarga del DOCX porque ya habia salida previa en ' . $fileSent . ':' . $lineSent);
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
