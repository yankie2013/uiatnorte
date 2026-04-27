<?php
/* ===========================================================
   manifestacion_familiar.php  (UIAT NORTE) — CÓDIGO COMPLETO
   - Genera y DESCARGA la manifestación del familiar del fallecido.
   - Entrada:   ?fam_id=XX     (ID de familiar_fallecido.id)
   - Tablas:    familiar_fallecido, accidentes, involucrados_personas, personas, fiscales, fiscalia
   - Plantilla: /plantillas/manifestacion_familiar.docx
   - Requiere:  phpoffice/phpword (autoload)
   =========================================================== */

ini_set('display_errors', 0);
header('Content-Type: text/html; charset=utf-8');
ob_start();

require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

/* ---------------- Helpers ---------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ig($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function slug_nombre_archivo($texto){
  $texto = trim((string)$texto);
  if ($texto === '') return 'sin_apellido';
  $reemplazos = [
    'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A',
    'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
    'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
    'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O',
    'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
    'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
    'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
    'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
    'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
    'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
    'Ñ' => 'N', 'ñ' => 'n',
  ];
  $texto = strtr($texto, $reemplazos);
  $texto = preg_replace('/[^A-Za-z0-9]+/', '_', $texto);
  $texto = trim((string)$texto, '_');
  return $texto !== '' ? strtolower($texto) : 'sin_apellido';
}

function fecha_larga_es($dt){ // "10 de octubre de 2025"
  if(!$dt) return '';
  $ts = is_numeric($dt) ? (int)$dt : strtotime($dt);
  $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  return date('j', $ts).' de '.$meses[(int)date('n',$ts)-1].' de '.date('Y',$ts);
}
function fecha_abrev_es($dt){ // "29ABR2025"
  if(!$dt) return '';
  $ts = is_numeric($dt) ? (int)$dt : strtotime($dt);
  $meses = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
  return str_pad(date('j',$ts),2,'0',STR_PAD_LEFT).$meses[(int)date('n',$ts)-1].date('Y',$ts);
}
function solo_fecha($dt){ return $dt ? date('d/m/Y', strtotime($dt)) : ''; }
function solo_hora($dt){ return $dt ? date('H:i', strtotime($dt)) : ''; }
function calc_edad($fec){ if(!$fec) return ''; $n=new DateTime($fec); $h=new DateTime('today'); return $n->diff($h)->y; }

/* ---------- Cargar autoload de PHPWord ---------- */
$autoloads = [
  __DIR__.'/vendor/autoload.php',
  __DIR__.'/PHPWord-1.4.0/vendor/autoload.php',
  __DIR__.'/PHPWord/vendor/autoload.php',
];
$autoload_ok = false;
foreach($autoloads as $a){ if (is_file($a)) { require_once $a; $autoload_ok=true; break; } }
if(!$autoload_ok){
  http_response_code(500);
  echo "No se encontró autoload de PHPWord. Instala 'phpoffice/phpword' o ajusta la ruta.";
  exit;
}
require_once __DIR__ . '/word_filename_helper.php';
use PhpOffice\PhpWord\TemplateProcessor;

if (class_exists('ZipArchive')) {
  \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::ZIPARCHIVE);
} else {
  \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::PCLZIP);
}
$tmpDir = __DIR__.'/tmp';
if (!is_dir($tmpDir)) {
  @mkdir($tmpDir, 0775, true);
}
\PhpOffice\PhpWord\Settings::setTempDir($tmpDir);

/* ---------------- Entrada ---------------- */
$fam_id = (int) ig('fam_id', 0);
if ($fam_id <= 0){
  http_response_code(400);
  echo "Falta parámetro fam_id.";
  exit;
}

/* ---------------- Query principal ----------------
   f  = familiar_fallecido
   acc= accidentes
   ipf= involucrados_personas (fallecido)
   pf  = personas (fallecido)
   pfa = personas (familiar declarante)
   fs  = fiscales
   fa  = fiscalia
---------------------------------------------------*/
$sql = "
SELECT
  f.id AS fam_id, f.accidente_id, f.fallecido_inv_id, f.familiar_persona_id,
  f.parentesco AS fam_parentesco, f.observaciones AS fam_obs,

  -- ACCIDENTE
  acc.sidpol AS acc_sidpol, acc.registro_sidpol AS acc_registro_sidpol,
  acc.lugar AS acc_lugar, acc.referencia AS acc_referencia,
  acc.cod_dep AS acc_cod_dep, acc.cod_prov AS acc_cod_prov, acc.cod_dist AS acc_cod_dist,
  acc.comisaria_id AS acc_comisaria_id, acc.fiscalia_id AS acc_fiscalia_id, acc.fiscal_id AS acc_fiscal_id,
  acc.estado AS acc_estado, acc.fecha_accidente AS acc_fecha_accidente,
  acc.fecha_comunicacion, acc.fecha_intervencion,
  acc.comunicante_nombre, acc.comunicante_telefono,
  acc.nro_informe_policial, acc.sentido, acc.secuencia,

  -- INVOLUCRADO FALLECIDO
  ipf.rol_id AS fal_rol_id, ipf.orden_persona AS fal_orden_persona,
  ipf.observaciones AS fal_obs,

  -- PERSONA FALLECIDA
  pf.tipo_doc  AS fal_tipo_doc, pf.num_doc AS fal_num_doc,
  pf.apellido_paterno AS fal_apellido_paterno, pf.apellido_materno AS fal_apellido_materno, pf.nombres AS fal_nombres,
  pf.sexo AS fal_sexo, pf.fecha_nacimiento AS fal_fecha_nacimiento, pf.edad AS fal_edad_db,
  pf.estado_civil AS fal_estado_civil, pf.nacionalidad AS fal_nacionalidad, pf.grado_instruccion AS fal_grado_instruccion,
  pf.ocupacion AS fal_ocupacion,
  pf.departamento_nac AS fal_departamento_nac,
  pf.provincia_nac AS fal_provincia_nac,
  pf.distrito_nac AS fal_distrito_nac,
  pf.domicilio AS fal_domicilio,
  pf.domicilio_departamento AS fal_dom_departamento,
  pf.domicilio_provincia AS fal_dom_provincia,
  pf.domicilio_distrito AS fal_dom_distrito,
  pf.celular AS fal_celular, pf.email AS fal_email,

  -- PERSONA FAMILIAR (DECLARANTE)
  pfa.tipo_doc  AS fam_tipo_doc, pfa.num_doc AS fam_num_doc,
  pfa.apellido_paterno AS fam_apellido_paterno, pfa.apellido_materno AS fam_apellido_materno, pfa.nombres AS fam_nombres,
  pfa.sexo AS fam_sexo, pfa.fecha_nacimiento AS fam_fecha_nacimiento, pfa.edad AS fam_edad_db,
  pfa.estado_civil AS fam_estado_civil, pfa.nacionalidad AS fam_nacionalidad, pfa.grado_instruccion AS fam_grado_instruccion,
  pfa.ocupacion AS fam_ocupacion,
  pfa.nombre_padre AS fam_nombre_padre,
  pfa.nombre_madre AS fam_nombre_madre,
  pfa.domicilio AS fam_domicilio,
  pfa.domicilio_departamento AS fam_dom_departamento,
  pfa.domicilio_provincia AS fam_dom_provincia,
  pfa.domicilio_distrito AS fam_dom_distrito,
  pfa.celular AS fam_celular, pfa.email AS fam_email,

  -- DATOS DEL FISCAL (tabla 'fiscales')
  fs.nombres AS fs_nombres, fs.apellido_paterno AS fs_apellido_paterno, fs.apellido_materno AS fs_apellido_materno,
  fs.cargo AS fs_cargo, fs.dni AS fs_dni, fs.telefono AS fs_telefono, fs.correo AS fs_correo,

  -- DATOS DE LA FISCALÍA (tabla 'fiscalia')
  fa.nombre AS fa_nombre, fa.direccion AS fa_direccion, fa.telefono AS fa_telefono, fa.correo AS fa_correo

FROM familiar_fallecido f
JOIN accidentes acc              ON acc.id = f.accidente_id
JOIN involucrados_personas ipf   ON ipf.id = f.fallecido_inv_id
JOIN personas pf                 ON pf.id = ipf.persona_id
JOIN personas pfa                ON pfa.id = f.familiar_persona_id

LEFT JOIN fiscales fs            ON fs.id = acc.fiscal_id
LEFT JOIN fiscalia fa            ON fa.id = acc.fiscalia_id

WHERE f.id = :fam_id
LIMIT 1
";
$q = $pdo->prepare($sql);
$q->execute([':fam_id'=>$fam_id]);
$row = $q->fetch(PDO::FETCH_ASSOC);

if(!$row){
  http_response_code(404);
  echo "No se encontró el registro fam_id=$fam_id.";
  exit;
}

/* ----------------- Preparar datos ----------------- */
$acc_dt = $row['acc_fecha_accidente'];
$now = date('Y-m-d H:i:s');

$fal_nombre_completo = trim(($row['fal_nombres']??'').' '.($row['fal_apellido_paterno']??'').' '.($row['fal_apellido_materno']??''));
$fam_nombre_completo = trim(($row['fam_nombres']??'').' '.($row['fam_apellido_paterno']??'').' '.($row['fam_apellido_materno']??''));

$fal_doc = trim(($row['fal_tipo_doc']??'').' '.($row['fal_num_doc']??''));
$fam_doc = trim(($row['fam_tipo_doc']??'').' '.($row['fam_num_doc']??''));

$fal_edad = $row['fal_edad_db'] ?: calc_edad($row['fal_fecha_nacimiento']);
$fam_edad = $row['fam_edad_db'] ?: calc_edad($row['fam_fecha_nacimiento']);

$acc_fecha_larga = fecha_larga_es($acc_dt);
$acc_fecha_abrev = fecha_abrev_es($acc_dt);

$manif_fecha_dt   = $now; // puedes cambiarlo a la fecha de intervención si prefieres
$manif_fecha      = solo_fecha($manif_fecha_dt);
$manif_hora       = solo_hora($manif_fecha_dt);
$manif_fecha_larga= fecha_larga_es($manif_fecha_dt);

/* ----- Construir nombre completo del fiscal y datos de fiscalia ----- */
$acc_fiscal_nombre_completo = '';
$acc_fiscal_cargo = '';
$acc_fiscal_dni = '';
$acc_fiscal_telefono = '';
$acc_fiscal_correo = '';

if(!empty($row['fs_nombres']) || !empty($row['fs_apellido_paterno']) || !empty($row['fs_apellido_materno'])){
  $acc_fiscal_nombre_completo = trim(($row['fs_nombres'] ?? '') . ' ' . ($row['fs_apellido_paterno'] ?? '') . ' ' . ($row['fs_apellido_materno'] ?? ''));
  $acc_fiscal_cargo = $row['fs_cargo'] ?? '';
  $acc_fiscal_dni = $row['fs_dni'] ?? '';
  $acc_fiscal_telefono = $row['fs_telefono'] ?? '';
  $acc_fiscal_correo = $row['fs_correo'] ?? '';
}

/* ----- Datos fiscalía ----- */
$acc_fiscalia_nombre = $row['fa_nombre'] ?? '';
$acc_fiscalia_direccion = $row['fa_direccion'] ?? '';
$acc_fiscalia_telefono = $row['fa_telefono'] ?? '';
$acc_fiscalia_correo = $row['fa_correo'] ?? '';

/* ----------- Cargar plantilla y setear valores ----------- */
$plantilla = __DIR__.'/plantillas/manifestacion_familiar.docx';
if(!is_file($plantilla)){
  http_response_code(500);
  echo "No existe la plantilla: ".$plantilla;
  exit;
}

$tpl = new TemplateProcessor($plantilla);

/* ===== Accidentes ===== */
$tpl->setValue('acc_sidpol',            $row['acc_sidpol'] ?? '');
$tpl->setValue('acc_registro_sidpol',   $row['acc_registro_sidpol'] ?? '');
$tpl->setValue('acc_lugar',             $row['acc_lugar'] ?? '');
$tpl->setValue('acc_referencia',        $row['acc_referencia'] ?? '');
$tpl->setValue('acc_cod_dep',           $row['acc_cod_dep'] ?? '');
$tpl->setValue('acc_cod_prov',          $row['acc_cod_prov'] ?? '');
$tpl->setValue('acc_cod_dist',          $row['acc_cod_dist'] ?? '');
$tpl->setValue('acc_comisaria_id',      $row['acc_comisaria_id'] ?? '');
$tpl->setValue('acc_fiscalia_id',       $row['acc_fiscalia_id'] ?? '');
$tpl->setValue('acc_fiscal_id',         $row['acc_fiscal_id'] ?? '');
$tpl->setValue('acc_estado',            $row['acc_estado'] ?? '');
$tpl->setValue('acc_fecha',             solo_fecha($acc_dt));
$tpl->setValue('acc_hora',              solo_hora($acc_dt));
$tpl->setValue('acc_fecha_larga',       $acc_fecha_larga);
$tpl->setValue('acc_fecha_abrev',       $acc_fecha_abrev);
$tpl->setValue('acc_nro_informe_policial', $row['nro_informe_policial'] ?? '');
$tpl->setValue('acc_sentido',           $row['sentido'] ?? '');
$tpl->setValue('acc_secuencia',         $row['secuencia'] ?? '');

/* ===== Datos del Fiscal y de la Fiscalía ===== */
$tpl->setValue('acc_fiscal_nombre_completo',    $acc_fiscal_nombre_completo);
$tpl->setValue('acc_fiscal_cargo',              $acc_fiscal_cargo);
$tpl->setValue('acc_fiscal_dni',                $acc_fiscal_dni);
$tpl->setValue('acc_fiscal_telefono',           $acc_fiscal_telefono);
$tpl->setValue('acc_fiscal_correo',             $acc_fiscal_correo);

$tpl->setValue('acc_fiscalia_nombre',           $acc_fiscalia_nombre);
$tpl->setValue('acc_fiscalia_direccion',        $acc_fiscalia_direccion);
$tpl->setValue('acc_fiscalia_telefono',         $acc_fiscalia_telefono);
$tpl->setValue('acc_fiscalia_correo',           $acc_fiscalia_correo);

/* ===== Fallecido ===== */
$tpl->setValue('fal_apellido_paterno',  $row['fal_apellido_paterno'] ?? '');
$tpl->setValue('fal_apellido_materno',  $row['fal_apellido_materno'] ?? '');
$tpl->setValue('fal_nombres',           $row['fal_nombres'] ?? '');
$tpl->setValue('fal_nombre_completo',   $fal_nombre_completo);
$tpl->setValue('fal_tipo_doc',          $row['fal_tipo_doc'] ?? '');
$tpl->setValue('fal_num_doc',           $row['fal_num_doc'] ?? '');
$tpl->setValue('fal_doc',               $fal_doc);
$tpl->setValue('fal_sexo',              $row['fal_sexo'] ?? '');
$tpl->setValue('fal_fecha_nacimiento',  solo_fecha($row['fal_fecha_nacimiento'] ?? ''));
$tpl->setValue('fal_edad',              $fal_edad);
$tpl->setValue('fal_estado_civil',      $row['fal_estado_civil'] ?? '');
$tpl->setValue('fal_nacionalidad',      $row['fal_nacionalidad'] ?? '');
$tpl->setValue('fal_grado_instruccion', $row['fal_grado_instruccion'] ?? '');
$tpl->setValue('fal_ocupacion',         $row['fal_ocupacion'] ?? '');
$tpl->setValue('fal_departamento_nac',  $row['fal_departamento_nac'] ?? '');
$tpl->setValue('fal_provincia_nac',     $row['fal_provincia_nac'] ?? '');
$tpl->setValue('fal_distrito_nac',      $row['fal_distrito_nac'] ?? '');
$tpl->setValue('fal_domicilio',         $row['fal_domicilio'] ?? '');
$tpl->setValue('fal_dom_departamento',  $row['fal_dom_departamento'] ?? '');
$tpl->setValue('fal_dom_provincia',     $row['fal_dom_provincia'] ?? '');
$tpl->setValue('fal_dom_distrito',      $row['fal_dom_distrito'] ?? '');
$tpl->setValue('fal_celular',           $row['fal_celular'] ?? '');
$tpl->setValue('fal_email',             $row['fal_email'] ?? '');
$tpl->setValue('fal_orden_persona',     $row['fal_orden_persona'] ?? '');
$tpl->setValue('fal_observaciones',     $row['fal_obs'] ?? '');

/* ===== Familiar declarante ===== */
$tpl->setValue('fam_parentesco',        $row['fam_parentesco'] ?? '');
$tpl->setValue('fam_observaciones',     $row['fam_obs'] ?? '');
$tpl->setValue('fam_apellido_paterno',  $row['fam_apellido_paterno'] ?? '');
$tpl->setValue('fam_apellido_materno',  $row['fam_apellido_materno'] ?? '');
$tpl->setValue('fam_nombres',           $row['fam_nombres'] ?? '');
$tpl->setValue('fam_nombre_completo',   $fam_nombre_completo);
$tpl->setValue('fam_tipo_doc',          $row['fam_tipo_doc'] ?? '');
$tpl->setValue('fam_num_doc',           $row['fam_num_doc'] ?? '');
$tpl->setValue('fam_doc',               $fam_doc);
$tpl->setValue('fam_sexo',              $row['fam_sexo'] ?? '');
$tpl->setValue('fam_fecha_nacimiento',  solo_fecha($row['fam_fecha_nacimiento'] ?? ''));
$tpl->setValue('fam_edad',              $fam_edad);
$tpl->setValue('fam_estado_civil',      $row['fam_estado_civil'] ?? '');
$tpl->setValue('fam_nacionalidad',      $row['fam_nacionalidad'] ?? '');
$tpl->setValue('fam_grado_instruccion', $row['fam_grado_instruccion'] ?? '');
$tpl->setValue('fam_ocupacion',         $row['fam_ocupacion'] ?? '');
$tpl->setValue('fam_nombre_padre',      $row['fam_nombre_padre'] ?? '');
$tpl->setValue('fam_nombre_madre',      $row['fam_nombre_madre'] ?? '');
$tpl->setValue('fam_domicilio',         $row['fam_domicilio'] ?? '');
$tpl->setValue('fam_dom_departamento',  $row['fam_dom_departamento'] ?? '');
$tpl->setValue('fam_dom_provincia',     $row['fam_dom_provincia'] ?? '');
$tpl->setValue('fam_dom_distrito',      $row['fam_dom_distrito'] ?? '');
$tpl->setValue('fam_celular',           $row['fam_celular'] ?? '');
$tpl->setValue('fam_email',             $row['fam_email'] ?? '');

/* ===== Control del acto ===== */
$tpl->setValue('manif_fecha',           $manif_fecha);
$tpl->setValue('manif_hora',            $manif_hora);
$tpl->setValue('manif_fecha_larga',     $manif_fecha_larga);
/* Si tienes estos datos en otra tabla, cámbialos aquí: */
$tpl->setValue('manif_lugar_toma',      'UIAT Lima Norte');
$tpl->setValue('manif_funcionario',     '________________________________');
$tpl->setValue('manif_cargo_funcionario','________________________________');
$tpl->setValue('manif_observaciones_finales', '');

/* ===== IDs de trabajo ===== */
$tpl->setValue('fam_id',                $row['fam_id']);
$tpl->setValue('accidente_id',          $row['accidente_id']);
$tpl->setValue('fallecido_inv_id',      $row['fallecido_inv_id']);
$tpl->setValue('familiar_persona_id',   $row['familiar_persona_id']);

/* -------- Descargar archivo -------- */
$fname = uiat_manifestacion_filename('Familiar', $row);
while (ob_get_level() > 0) {
  ob_end_clean();
}
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

$temp = tempnam(sys_get_temp_dir(), 'tpl');
$tpl->saveAs($temp);
readfile($temp);
@unlink($temp);
exit;
