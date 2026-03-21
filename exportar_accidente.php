<?php
/* exportar_accidente.php
 * Uso: exportar_accidente.php?id=18
 */
ini_set('display_errors',0);
ini_set('display_startup_errors',0);
error_reporting(0);

require __DIR__.'/db.php';
if (is_file(__DIR__.'/vendor/autoload.php')) { require __DIR__.'/vendor/autoload.php'; }
if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && is_file(__DIR__.'/PHPWord-1.4.0/vendor/autoload.php')) {
  require __DIR__.'/PHPWord-1.4.0/vendor/autoload.php';
}

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

if (!class_exists(TemplateProcessor::class) || !class_exists(PhpWord::class)) {
  http_response_code(500);
  exit('PhpWord no esta disponible para generar el DOCX.');
}

if (class_exists('ZipArchive')) {
  Settings::setZipClass(Settings::ZIPARCHIVE);
} else {
  Settings::setZipClass(Settings::PCLZIP);
}
$tmpDir = __DIR__.'/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }
Settings::setTempDir($tmpDir);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0) { http_response_code(400); exit('Falta ?id='); }

$sql = "SELECT
          a.id,
          a.sidpol,
          a.registro_sidpol,
          a.lugar,
          a.referencia,
          a.fecha_accidente,
          DATE_FORMAT(a.fecha_accidente,'%Y-%m-%d') AS fecha_sola,
          DATE_FORMAT(a.fecha_accidente,'%H:%i')   AS hora_accidente,
          a.estado,
          a.nro_informe_policial,
          a.sentido,
          a.secuencia,
          c.nombre AS comisaria
        FROM accidentes a
        LEFT JOIN comisarias c ON c.id = a.comisaria_id
        WHERE a.id = ?";

$st = $pdo->prepare($sql);
$st->execute([$id]);
$acc = $st->fetch(PDO::FETCH_ASSOC);
if (!$acc) { http_response_code(404); exit('No se encontro el accidente '.$id); }
foreach ($acc as $k=>$v) { if ($v===null) $acc[$k]=''; }

$involucradosTxt = '-';
try {
  $q = $pdo->prepare("
    SELECT p.apellidos, p.nombres, p.dni, ip.rol
    FROM involucrados_personas ip
    JOIN personas p ON p.id = ip.persona_id
    WHERE ip.accidente_id = ?
    ORDER BY ip.id
  ");
  $q->execute([$id]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);
  if ($rows) {
    $lis=[];
    foreach($rows as $r){
      $lis[] = trim(($r['rol']?:'').' - '.($r['apellidos']?:'').', '.($r['nombres']?:'').' (DNI '.($r['dni']?:'-').')');
    }
    $involucradosTxt = implode("\n", $lis);
  }
} catch(Throwable $e){ }

$safeName  = 'accidente_'.$id.'_'.date('Ymd_His').'.docx';
$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $safeName) ?: ('accidente_' . $id . '.docx');
$fileName = $tmpDir . '/' . $safeName;
$plantilla = __DIR__.'/plantilla_accidente.docx';

if (is_file($plantilla)) {
  $T = new TemplateProcessor($plantilla);
  $T->setValue('sidpol',              $acc['sidpol']);
  $T->setValue('registro_sidpol',     $acc['registro_sidpol']);
  $T->setValue('lugar',               $acc['lugar']);
  $T->setValue('referencia',          $acc['referencia']);
  $T->setValue('fecha_accidente',     $acc['fecha_accidente']);
  $T->setValue('fecha',               $acc['fecha_sola']);
  $T->setValue('hora',                $acc['hora_accidente']);
  $T->setValue('estado',              $acc['estado']);
  $T->setValue('comisaria',           $acc['comisaria']);
  $T->setValue('nro_informe_policial',$acc['nro_informe_policial']);
  $T->setValue('sentido',             $acc['sentido']);
  $T->setValue('secuencia',           $acc['secuencia']);
  $T->setValue('involucrados',        $involucradosTxt);
  $T->setValue('descripcion',         '');
  $T->saveAs($fileName);
} else {
  $doc = new PhpWord();
  $sec = $doc->addSection();
  $sec->addTitle('Reporte de Accidente', 1);
  $sec->addText('SIDPOL: '.$acc['sidpol'], ['bold'=>true]);
  $sec->addText('Registro SIDPOL: '.$acc['registro_sidpol']);
  $sec->addText('Comisaria: '.$acc['comisaria']);
  $sec->addText('Lugar: '.$acc['lugar']);
  $sec->addText('Referencia: '.$acc['referencia']);
  $sec->addText('Fecha: '.$acc['fecha_sola'].'   Hora: '.$acc['hora_accidente']);
  $sec->addText('Estado: '.$acc['estado']);
  if (!empty($acc['nro_informe_policial'])) $sec->addText('Nro Informe Policial: '.$acc['nro_informe_policial']);
  if (!empty($acc['sentido']))              $sec->addText('Sentido: '.$acc['sentido']);
  if (!empty($acc['secuencia']))            $sec->addText('Secuencia: '.$acc['secuencia']);
  $sec->addTextBreak(1);
  $sec->addText('Involucrados:', ['bold'=>true]);
  foreach (explode("\n", $involucradosTxt) as $l) { $sec->addText($l); }
  IOFactory::createWriter($doc, 'Word2007')->save($fileName);
}

while (ob_get_level()) { @ob_end_clean(); }
if (headers_sent($fileSent, $lineSent)) {
  @unlink($fileName);
  http_response_code(500);
  exit('No se pudo iniciar la descarga del DOCX porque ya habia salida previa en ' . $fileSent . ':' . $lineSent);
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.basename($fileName).'"');
header('Content-Length: '.filesize($fileName));
readfile($fileName);
@unlink($fileName);
exit;
