<?php
/* ===========================================================
   word_oficio_camara.php  (UIAT NORTE)
   - Genera y descarga el oficio para solicitud/gestión de
     "Cámaras de Video Vigilancia"
   - Usa plantilla: /plantillas/oficio_camaras.docx
   - Entrada:
       ?oficio_id=XX   (obligatorio)
   - Tablas usadas: oficios, oficio_asunto, oficio_entidad, accidentes,
                    comisarias, fiscalia, oficio_oficial_ano,
                    grado_cargo, oficio_subentidad, oficio_persona_entidad
   =========================================================== */

require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
require_once __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

// Silenciar salida de errores al navegador (para no corromper el DOCX)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/php_errors.log');

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

/* -------------------- Helpers -------------------- */
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

/* -------------------- Parámetros -------------------- */
$oficio_id = isset($_GET['oficio_id']) ? (int)$_GET['oficio_id'] : 0;
if ($oficio_id <= 0) { http_response_code(400); exit('Falta oficio_id'); }

/* -------------------- Columnas opcionales -------------------- */
$cols = [];
try{
  $st=$pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oficios'");
  $cols = array_flip(array_map('strtolower',$st->fetchAll(PDO::FETCH_COLUMN)));
}catch(Throwable $e){}
$hasOficialAno = isset($cols['oficial_ano_id']);

/* Detectar disponibilidad de modalidad/consecuencia en accidentes y catálogos */
$accCols = [];
try{
  $st=$pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='accidentes'");
  $accCols = array_flip(array_map('strtolower',$st->fetchAll(PDO::FETCH_COLUMN)));
}catch(Throwable $e){}
$hasModIdCol  = isset($accCols['modalidad_id']);
$hasConIdCol  = isset($accCols['consecuencia_id']);
$hasModTxtCol = isset($accCols['modalidad']);
$hasConTxtCol = isset($accCols['consecuencia']);

$hasTablaModalidad = (bool)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='modalidad_accidente'")->fetchColumn();
$hasTablaConsec    = (bool)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consecuencia_accidente'")->fetchColumn();

/* -------------------- Consultar oficio + joins -------------------- */
$sql = "
SELECT 
  o.id AS oficio_id, o.numero,o.anio,o.fecha_emision,o.estado,o.referencia_texto,o.motivo,o.accidente_id,
  ".($hasOficialAno ? "o.oficial_ano_id" : "NULL")." AS oficial_ano_id,
  e.nombre AS entidad_nombre, e.siglas AS entidad_siglas,
  s.nombre AS asunto_nombre, s.detalle AS asunto_detalle,
  a.registro_sidpol,a.lugar,a.referencia,a.sentido,a.fecha_accidente,

  /* === MODALIDAD desde tabla puente === */
  (
    SELECT GROUP_CONCAT(DISTINCT ma.nombre ORDER BY ma.id SEPARATOR ', ')
    FROM accidente_modalidad am
    JOIN modalidad_accidente ma ON ma.id = am.modalidad_id
    WHERE am.accidente_id = a.id
  ) AS modalidad_nombre,

  /* === CONSECUENCIA desde tabla puente === */
  (
    SELECT GROUP_CONCAT(DISTINCT ca.nombre ORDER BY ca.id SEPARATOR ', ')
    FROM accidente_consecuencia ac
    JOIN consecuencia_accidente ca ON ca.id = ac.consecuencia_id
    WHERE ac.accidente_id = a.id
  ) AS consecuencia_nombre,

  c.nombre AS comisaria_nombre, f.nombre AS fiscalia_nombre,
  gc.nombre AS grado_cargo_nombre, gc.abreviatura AS grado_cargo_abrev, gc.tipo AS grado_cargo_tipo,
  se.nombre  AS subentidad_nombre, se.tipo AS subentidad_tipo,
  pe.nombres AS per_dest_nombres, pe.apellido_paterno AS per_dest_apep, COALESCE(pe.apellido_materno,'') AS per_dest_apem
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
if(!$of){ http_response_code(404); exit('Oficio no encontrado'); }

/* -------------------- Nombre oficial del año -------------------- */
$nombreOficialAno='';
if($hasOficialAno && $of['oficial_ano_id']){
  $st=$pdo->prepare("SELECT nombre FROM oficio_oficial_ano WHERE id=?");
  $st->execute([$of['oficial_ano_id']]);
  $nombreOficialAno=$st->fetchColumn()?:'';
}

/* -------------------- Plantilla -------------------- */
$plantilla = __DIR__.'/plantillas/oficio_camaras.docx';
if(!file_exists($plantilla)){
  http_response_code(500);
  exit('No se encuentra la plantilla: plantillas/oficio_camaras.docx');
}
$tpl = new TemplateProcessor($plantilla);

/* -------------------- Set valores (marcadores) -------------------- */
/* Oficio */
$tpl->setValue('oficio_numero',        h($of['numero']));
$tpl->setValue('oficio_anio',          h($of['anio']));
$tpl->setValue('oficio_fecha',         fecha_larga($of['fecha_emision']));
$tpl->setValue('oficio_fecha_abrev',   fecha_abrev($of['fecha_emision']));
$tpl->setValue('oficio_motivo',        h($of['motivo']));
$tpl->setValue('oficio_referencia',    h($of['referencia_texto']));
$tpl->setValue('nombre_oficial_ano',   h($nombreOficialAno));

/* Entidad destino */
$entNombre = trim((string)($of['entidad_nombre'] ?: $of['entidad_siglas'] ?: ''));
$entSiglas = trim((string)($of['entidad_siglas'] ?: ''));

$tpl->setValue('oficio_entidad_nombre',  h($entNombre));
$tpl->setValue('oficio_entidad_siglas',  h($entSiglas));
$tpl->setValue('oficio_subentidad_nombre', h($of['subentidad_nombre'] ?? ''));
$tpl->setValue('oficio_subentidad_tipo',   h($of['subentidad_tipo'] ?? ''));

/* alias extra por si la plantilla usa otros nombres */
$tpl->setValue('entidad_nombre', h($entNombre));
$tpl->setValue('ENTIDAD_NOMBRE', h($entNombre));
$tpl->setValue('ENTIDAD_SIGLAS', h($entSiglas));

$destNombre = trim(
  ($of['per_dest_nombres'] ?? '') . ' ' .
  ($of['per_dest_apep'] ?? '')    . ' ' .
  ($of['per_dest_apem'] ?? '')
);
$tpl->setValue('oficio_persona_destino', h($destNombre));

$linea = trim(
  ($entNombre) .
  (empty($entSiglas) ? '' : (' ('.$entSiglas.')')) .
  (empty($of['subentidad_nombre']) ? '' : (' — '.$of['subentidad_nombre'])) .
  (empty($destNombre) ? '' : (' — '.$destNombre))
);
$tpl->setValue('oficio_entidad_linea', h($linea));

/* alias adicionales por si acaso */
$tpl->setValue('entidad_linea', h($linea));

/* Asunto */
$tpl->setValue('asunto_nombre',  h($of['asunto_nombre']));
$tpl->setValue('asunto_detalle', h($of['asunto_detalle']));

/* Accidente (si aplica) */
$tpl->setValue('accidente_sidpol',       h($of['registro_sidpol']));
$tpl->setValue('accidente_lugar',        h($of['lugar']));
$tpl->setValue('accidente_referencia',   h($of['referencia']));
$tpl->setValue('accidente_sentido',      h($of['sentido']));
$tpl->setValue('accidente_fecha',        fecha_larga($of['fecha_accidente']));
$tpl->setValue('accidente_fecha_abrev',  fecha_abrev($of['fecha_accidente']));

/* Hora, modalidad, consecuencia */
$horaHecho = '';
if (!empty($of['fecha_accidente'])) {
  $t = strtotime($of['fecha_accidente']);
  if ($t) $horaHecho = date('H:i', $t);
}
$tpl->setValue('accidente_hora',         h($horaHecho));
$tpl->setValue('accidente_modalidad',    h($of['modalidad_nombre'] ?? ''));
$tpl->setValue('accidente_consecuencia', h($of['consecuencia_nombre'] ?? ''));

$tpl->setValue('comisaria_nombre',       h($of['comisaria_nombre']));
$tpl->setValue('fiscalia_nombre',        h($of['fiscalia_nombre']));

/* Firmante: grado/cargo */
$gc_nombre = trim((string)($of['grado_cargo_nombre']??''));
$gc_abrev  = trim((string)($of['grado_cargo_abrev']??''));
$gc_tipo   = trim((string)($of['grado_cargo_tipo']??''));
$gc_full   = trim($gc_nombre.($gc_abrev?(' — '.$gc_abrev):'').($gc_tipo?(' ['.$gc_tipo.']'):''));

$tpl->setValue('grado_cargo_nombre', h($gc_nombre));
$tpl->setValue('grado_cargo_abrev',  h($gc_abrev));
$tpl->setValue('grado_cargo_tipo',   h($gc_tipo));
$tpl->setValue('oficio_grado_cargo', h($gc_full));

/* -------------------- Descargar DOCX -------------------- */
$nombreFile = 'Oficio_Camaras_'.$of['numero'].'-'.$of['anio'].'.docx';

$tmp = tempnam(sys_get_temp_dir(), 'docx_');
$tpl->saveAs($tmp);

// Limpiar buffers para no corromper el zip
while (ob_get_level()) { ob_end_clean(); }

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$nombreFile.'"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($tmp);
@unlink($tmp);
exit;