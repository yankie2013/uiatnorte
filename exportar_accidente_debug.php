<?php
/* exportar_accidente_debug.php
 * Diagnostico de exportacion DOCX para accidentes.
 * Uso: exportar_accidente_debug.php?id=18
 */
header('Content-Type: text/plain; charset=utf-8');

$autoloadMain = __DIR__.'/vendor/autoload.php';
$autoloadFallback = __DIR__.'/PHPWord-1.4.0/vendor/autoload.php';
$tmpDir = __DIR__.'/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }

$lines = [];
$lines[] = '=== DEBUG PHPWORD / UIAT NORTE ===';
$lines[] = 'PHP=' . PHP_VERSION;
$lines[] = 'mbstring=' . (extension_loaded('mbstring') ? 'OK' : 'FALTA');
$lines[] = 'xml=' . (extension_loaded('xml') ? 'OK' : 'FALTA');
$lines[] = 'zip=' . (class_exists('ZipArchive') ? 'OK' : 'NO');
$lines[] = 'autoload.main=' . (file_exists($autoloadMain) ? 'OK' : 'NO');
$lines[] = 'autoload.fallback=' . (file_exists($autoloadFallback) ? 'OK' : 'NO');
$lines[] = 'tmp=' . $tmpDir . ' writable=' . (is_writable($tmpDir) ? 'SI' : 'NO');

try {
  if (file_exists($autoloadMain)) { require $autoloadMain; }
  if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && file_exists($autoloadFallback)) {
    require $autoloadFallback;
  }
  $lines[] = 'TemplateProcessor=' . (class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) ? 'OK' : 'NO');
  $lines[] = 'PhpWord=' . (class_exists(\PhpOffice\PhpWord\PhpWord::class) ? 'OK' : 'NO');
} catch (Throwable $e) {
  $lines[] = 'AUTOLOAD_FAIL=' . $e->getMessage();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$lines[] = 'ID=' . $id;
if ($id <= 0) {
  echo implode("\n", $lines) . "\nFalta ?id=\n";
  exit;
}

try {
  require __DIR__.'/db.php';
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('SET NAMES utf8mb4');

  $sql = "SELECT
            a.id,
            a.sidpol,
            a.registro_sidpol,
            a.lugar,
            a.fecha_accidente,
            DATE_FORMAT(a.fecha_accidente,'%H:%i') AS hora_accidente,
            a.estado,
            a.nro_informe_policial,
            c.nombre AS comisaria
          FROM accidentes a
          LEFT JOIN comisarias c ON c.id=a.comisaria_id
          WHERE a.id=?";
  $st = $pdo->prepare($sql);
  $st->execute([$id]);
  $acc = $st->fetch(PDO::FETCH_ASSOC);
  if (!$acc) {
    echo implode("\n", $lines) . "\nACCIDENTE=NO ENCONTRADO\n";
    exit;
  }
  $lines[] = 'ACCIDENTE=OK sidpol=' . ($acc['sidpol'] ?? '');
} catch (Throwable $e) {
  echo implode("\n", $lines) . "\nDB_FAIL=" . $e->getMessage() . "\n";
  exit;
}

if (class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
  try {
    $pw = new \PhpOffice\PhpWord\PhpWord();
    $s  = $pw->addSection();
    $s->addTitle('Debug exportacion accidente', 1);
    $s->addText('SIDPOL: ' . ($acc['sidpol'] ?? ''));
    $s->addText('Lugar: ' . ($acc['lugar'] ?? ''));
    $file = tempnam($tmpDir, 'acc_debug_');
    if ($file === false) {
      throw new RuntimeException('No se pudo crear archivo temporal');
    }
    \PhpOffice\PhpWord\IOFactory::createWriter($pw, 'Word2007')->save($file);
    $lines[] = 'DOCX_TEST=OK';
    $lines[] = 'DOCX_TMP=' . $file;
    @unlink($file);
  } catch (Throwable $e) {
    $lines[] = 'DOCX_TEST=FAIL ' . $e->getMessage();
  }
}

$lines[] = 'FIN';
echo implode("\n", $lines) . "\n";
