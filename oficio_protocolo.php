<?php
/* ===========================================================
   oficio_protocolo.php  (UIAT NORTE)
   - Genera y descarga el oficio “Protocolo de Necropsia” para UN fallecido
   - Usa plantilla: /plantillas/oficio_protocolo.docx
   - Entrada:
       ?oficio_id=XX (obligatorio)
       &inv_id=YY    (opcional; si no llega, usa o.involucrado_persona_id si existe; 
                      si no, toma el primer fallecido del accidente)
   - Tablas: oficios, oficio_asunto, oficio_entidad, accidentes, comisarias, fiscalia,
             involucrados_personas, personas, documento_occiso, oficio_oficial_ano, grado_cargo,
             oficio_subentidad, oficio_persona_entidad
   =========================================================== */

require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
require_once __DIR__.'/vendor/autoload.php';
if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && file_exists(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require_once __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}

use PhpOffice\PhpWord\TemplateProcessor;

if (!class_exists(TemplateProcessor::class)) {
    http_response_code(500);
    exit('PhpWord no esta disponible para generar el DOCX.');
}

// Producción del DOCX: no imprimir errores en salida (loguear en archivo)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/php_errors.log');

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

// -------------------- Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fecha_larga($f){
  if(!$f) return '';
  $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $t = strtotime($f);
  return $t ? date('j',$t).' de '.$meses[(int)date('n',$t)-1].' de '.date('Y',$t) : '';
}
function fecha_abrev($f){
  if(!$f) return '';
  $meses = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
  $t = strtotime($f);
  return $t ? strtoupper(date('d',$t).$meses[(int)date('n',$t)-1].date('Y',$t)) : '';
}
function edad_anios($nac,$ref=''){
  if(!$nac) return '';
  try{
    $a=new DateTime($nac); $b=new DateTime($ref?:date('Y-m-d'));
    return $a->diff($b)->y;
  }catch(Throwable $e){ return ''; }
}

// -------------------- Parámetros
$oficio_id = isset($_GET['oficio_id'])?(int)$_GET['oficio_id']:0;
$inv_id_in = isset($_GET['inv_id'])?(int)$_GET['inv_id']:0;
if($oficio_id<=0){ exit('Falta oficio_id'); }

// -------------------- Detección de columnas opcionales
$cols = [];
try{
  $st=$pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oficios'");
  $cols = array_flip(array_map('strtolower',$st->fetchAll(PDO::FETCH_COLUMN)));
}catch(Throwable $e){}
$hasInvPer     = isset($cols['involucrado_persona_id']);
$hasOficialAno = isset($cols['oficial_ano_id']);
$hasPersonaManual = isset($cols['persona_destino_manual']);

// -------------------- Consultar oficio + joins
$sql = "
SELECT 
  o.id AS oficio_id, o.numero,o.anio,o.fecha_emision,o.estado,o.referencia_texto,o.motivo,o.accidente_id,
  ".($hasInvPer?"o.involucrado_persona_id":"NULL")." AS inv_per_id_respaldo,
  ".($hasOficialAno?"o.oficial_ano_id":"NULL")." AS oficial_ano_id,
  e.nombre AS entidad_nombre, e.siglas AS entidad_siglas,
  s.nombre AS asunto_nombre, s.detalle AS asunto_detalle,
  a.registro_sidpol,a.lugar,a.referencia,a.sentido,a.fecha_accidente,
  c.nombre AS comisaria_nombre, f.nombre AS fiscalia_nombre,
  gc.nombre AS grado_cargo_nombre, gc.abreviatura AS grado_cargo_abrev, gc.tipo AS grado_cargo_tipo,
  se.nombre  AS subentidad_nombre, se.tipo AS subentidad_tipo,
  pe.nombres AS per_dest_nombres, pe.apellido_paterno AS per_dest_apep, COALESCE(pe.apellido_materno,'') AS per_dest_apem,
  ".($hasPersonaManual?"COALESCE(o.persona_destino_manual,'')":"''")." AS persona_destino_manual
FROM oficios o
LEFT JOIN oficio_entidad  e  ON e.id=o.entidad_id_destino
LEFT JOIN oficio_asunto   s  ON s.id=o.asunto_id
LEFT JOIN accidentes      a  ON a.id=o.accidente_id
LEFT JOIN comisarias      c  ON c.id=a.comisaria_id
LEFT JOIN fiscalia        f  ON f.id=a.fiscalia_id
LEFT JOIN grado_cargo     gc ON gc.id=o.grado_cargo_id
LEFT JOIN oficio_subentidad se      ON se.id = o.subentidad_destino_id
LEFT JOIN oficio_persona_entidad pe ON pe.id = o.persona_destino_id
WHERE o.id=? LIMIT 1";
$st=$pdo->prepare($sql);
$st->execute([$oficio_id]);
$of=$st->fetch(PDO::FETCH_ASSOC);
if(!$of) exit('Oficio no encontrado');

// -------------------- Nombre oficial del año
$nombreOficialAno='';
if($hasOficialAno && $of['oficial_ano_id']){
  $st=$pdo->prepare("SELECT nombre FROM oficio_oficial_ano WHERE id=?");
  $st->execute([$of['oficial_ano_id']]);
  $nombreOficialAno=$st->fetchColumn()?:'';
}

// -------------------- Determinar fallecido
$inv_id = $inv_id_in>0?$inv_id_in:(int)($of['inv_per_id_respaldo']??0);
$fallecido=null;

if($inv_id > 0){
  $st = $pdo->prepare("
    SELECT 
      ip.id AS inv_id,
      ip.rol_id, ip.lesion,
      p.id AS persona_id,
      p.nombres, p.apellido_paterno, p.apellido_materno,
      p.tipo_doc, p.num_doc, p.sexo, p.fecha_nacimiento, p.domicilio
    FROM involucrados_personas ip
    JOIN personas p ON p.id = ip.persona_id
    WHERE ip.id = ? 
      AND ip.accidente_id = ?
      AND UPPER(COALESCE(ip.lesion,'')) = 'FALLECIDO'
    LIMIT 1
  ");
  $st->execute([$inv_id, $of['accidente_id']]);
  $fallecido = $st->fetch(PDO::FETCH_ASSOC);
}

if(!$fallecido){
  // Fallback: primer fallecido del accidente
  $st = $pdo->prepare("
    SELECT 
      ip.id AS inv_id,
      ip.rol_id, ip.lesion,
      p.id AS persona_id,
      p.nombres, p.apellido_paterno, p.apellido_materno,
      p.tipo_doc, p.num_doc, p.sexo, p.fecha_nacimiento, p.domicilio
    FROM involucrados_personas ip
    JOIN personas p ON p.id = ip.persona_id
    WHERE ip.accidente_id = ?
      AND UPPER(COALESCE(ip.lesion,'')) = 'FALLECIDO'
    ORDER BY ip.id
    LIMIT 1
  ");
  $st->execute([ $of['accidente_id'] ]);
  $fallecido = $st->fetch(PDO::FETCH_ASSOC);
}

if(!$fallecido) exit('No se encontró fallecido.');

// -------------------- documento_occiso (número pericial)
$numero_pericial='';
try{
  $st=$pdo->prepare("SELECT numero_pericial FROM documento_occiso WHERE persona_id=? OR accidente_id=? ORDER BY id DESC LIMIT 1");
  $st->execute([$fallecido['persona_id'],$of['accidente_id']]);
  $numero_pericial=$st->fetchColumn()?:'';
}catch(Throwable $e){ $numero_pericial=''; }

// -------------------- Datos fallecido
$apPat=$fallecido['apellido_paterno']??'';
$apMat=$fallecido['apellido_materno']??'';
$nombres=$fallecido['nombres']??'';
$docu=trim(($fallecido['tipo_doc']??'').' '.($fallecido['num_doc']??''));
$edad=edad_anios($fallecido['fecha_nacimiento']??'', $of['fecha_accidente']??$of['fecha_emision']);

// -------------------- Cargar plantilla
$plantilla = __DIR__.'/plantillas/oficio_protocolo.docx';
if(!file_exists($plantilla)){ exit('No se encuentra la plantilla oficio_protocolo.docx'); }
$tpl=new TemplateProcessor($plantilla);

// -------------------- Set valores oficio
$tpl->setValue('oficio_numero',h($of['numero']));
$tpl->setValue('oficio_anio',h($of['anio']));
$tpl->setValue('oficio_fecha',fecha_larga($of['fecha_emision']));
$tpl->setValue('oficio_fecha_abrev',fecha_abrev($of['fecha_emision']));
$tpl->setValue('oficio_motivo',h($of['motivo']));
$tpl->setValue('oficio_referencia',h($of['referencia_texto']));
$tpl->setValue('entidad_nombre',h($of['entidad_nombre']));
$tpl->setValue('entidad_siglas',h($of['entidad_siglas']));
$tpl->setValue('asunto_nombre',h($of['asunto_nombre']));
$tpl->setValue('asunto_detalle',h($of['asunto_detalle']));
$tpl->setValue('nombre_oficial_ano',h($nombreOficialAno));

// -------- Grado/Cargo del firmante (catálogo grado_cargo)
$gc_nombre = trim((string)($of['grado_cargo_nombre']??''));
$gc_abrev  = trim((string)($of['grado_cargo_abrev']??''));
$gc_tipo   = trim((string)($of['grado_cargo_tipo']??''));
$gc_full   = trim($gc_nombre.($gc_abrev?(' — '.$gc_abrev):'').($gc_tipo?(' ['.$gc_tipo.']'):''));

$tpl->setValue('grado_cargo_nombre', h($gc_nombre));
$tpl->setValue('grado_cargo_abrev',  h($gc_abrev));
$tpl->setValue('grado_cargo_tipo',   h($gc_tipo));
$tpl->setValue('oficio_grado_cargo', h($gc_full));

// -------------------- Set valores accidente
$tpl->setValue('accidente_sidpol',h($of['registro_sidpol']));
$tpl->setValue('accidente_lugar',h($of['lugar']));
$tpl->setValue('accidente_referencia',h($of['referencia']));
$tpl->setValue('accidente_sentido',h($of['sentido']));
$tpl->setValue('accidente_fecha',fecha_larga($of['fecha_accidente']));
$tpl->setValue('accidente_fecha_abrev',fecha_abrev($of['fecha_accidente']));
$tpl->setValue('comisaria_nombre',h($of['comisaria_nombre']));
$tpl->setValue('fiscalia_nombre',h($of['fiscalia_nombre']));

// -------------------- Set valores fallecido
$tpl->setValue('fallecido_nombres',h($nombres));
$tpl->setValue('fallecido_apellidos',h(trim("$apPat $apMat")));
$tpl->setValue('fallecido_nombre_completo',h(trim("$nombres $apPat $apMat")));
$tpl->setValue('fallecido_edad',h($edad));
$tpl->setValue('fallecido_documento',h($docu));
$tpl->setValue('fallecido_domicilio',h($fallecido['domicilio']??''));
$tpl->setValue('fallecido_rol', h($fallecido['rol_id']??'')); // si quieres mapear a texto, hazlo aquí
$tpl->setValue('fallecido_lesion',h($fallecido['lesion']??''));
$tpl->setValue('numero_pericial',h($numero_pericial));

// ---- Entidad destino y persona destino
$tpl->setValue('oficio_entidad_nombre', h($of['entidad_nombre'] ?? ''));
$tpl->setValue('oficio_entidad_siglas', h($of['entidad_siglas'] ?? ''));
$tpl->setValue('oficio_subentidad_nombre', h($of['subentidad_nombre'] ?? ''));
$tpl->setValue('oficio_subentidad_tipo', h($of['subentidad_tipo'] ?? ''));

$destNombre = trim(
  ($of['per_dest_nombres'] ?? '') . ' ' .
  ($of['per_dest_apep'] ?? '')    . ' ' .
  ($of['per_dest_apem'] ?? '')
);
if ($destNombre === '') {
  $destNombre = trim((string) ($of['persona_destino_manual'] ?? ''));
}
$tpl->setValue('oficio_persona_destino', h($destNombre));

// Línea compuesta útil si la usas en el encabezado
$linea = trim(
  ($of['entidad_nombre'] ?? '') .
  (empty($of['entidad_siglas']) ? '' : (' ('.$of['entidad_siglas'].')')) .
  (empty($of['subentidad_nombre']) ? '' : (' — '.$of['subentidad_nombre'])) .
  (empty($destNombre) ? '' : (' — '.$destNombre))
);
$tpl->setValue('oficio_entidad_linea', h($linea));

// -------------------- Descargar (blindado)
$nombreFile = 'Oficio_Protocolo_'.$of['numero'].'-'.$of['anio'].'.docx';
$nombreFile = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombreFile) ?: ('Oficio_Protocolo_' . $oficio_id . '.docx');

$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }
$tmp = tempnam($tmpDir, 'docx_');
if ($tmp === false) {
  http_response_code(500);
  exit('No se pudo crear un archivo temporal para el DOCX.');
}
$tpl->saveAs($tmp);

while (ob_get_level()) { ob_end_clean(); }
if (headers_sent($fileSent, $lineSent)) {
  @unlink($tmp);
  http_response_code(500);
  exit('No se pudo iniciar la descarga del DOCX porque ya habia salida previa en ' . $fileSent . ':' . $lineSent);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$nombreFile.'"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($tmp);
@unlink($tmp);
exit;
