<?php
header('Content-Type: text/plain; charset=utf-8');

$autoloadMain = __DIR__.'/vendor/autoload.php';
$autoloadFallback = __DIR__.'/PHPWord-1.4.0/vendor/autoload.php';
$tpl = __DIR__.'/plantillas/oficio_peritaje.docx';
$tmpDir = __DIR__.'/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }

$lines = [];
$lines[] = '=== DIAGNOSTICO OFICIO PERITAJE ===';
$lines[] = 'PHP=' . PHP_VERSION;
$lines[] = 'DIR=' . __DIR__;
$lines[] = 'ext-zip=' . (extension_loaded('zip') ? 'OK' : 'FALTA');
$lines[] = 'ext-xml=' . (extension_loaded('xml') ? 'OK' : 'FALTA');
$lines[] = 'ext-gd=' . (extension_loaded('gd') ? 'OK' : 'FALTA');
$lines[] = 'autoload.main=' . (file_exists($autoloadMain) ? 'SI' : 'NO');
$lines[] = 'autoload.fallback=' . (file_exists($autoloadFallback) ? 'SI' : 'NO');
$lines[] = 'tpl=' . (file_exists($tpl) ? 'SI' : 'NO') . ' -> ' . $tpl;
$lines[] = 'tmp writable=' . (is_writable($tmpDir) ? 'SI' : 'NO') . ' -> ' . $tmpDir;

try {
  if (file_exists($autoloadMain)) { require $autoloadMain; }
  if (!class_exists(\PhpOffice\PhpWord\PhpWord::class) && file_exists($autoloadFallback)) {
    require $autoloadFallback;
  }
  $lines[] = 'PhpWord=' . (class_exists(\PhpOffice\PhpWord\PhpWord::class) ? 'OK' : 'NO');
} catch (Throwable $e) {
  $lines[] = 'AUTOLOAD_FAIL=' . $e->getMessage();
}

try {
  require __DIR__.'/db.php';
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->query('SELECT 1');
  $lines[] = 'DB=OK';
} catch (Throwable $e) {
  $lines[] = 'DB=ERROR -> ' . $e->getMessage();
}

if (class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
  try {
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $sec = $phpWord->addSection();
    $sec->addText('PHPWord OK - prueba minima');
    $file = tempnam($tmpDir, 'peritaje_diag_');
    if ($file === false) {
      throw new RuntimeException('No se pudo crear el archivo temporal.');
    }
    $phpWord->save($file, 'Word2007');
    $lines[] = 'DOCX_TEST=OK';
    $lines[] = 'DOCX_TMP=' . $file;
    @unlink($file);
  } catch (Throwable $e) {
    $lines[] = 'DOCX_TEST=ERROR -> ' . $e->getMessage();
  }
}

$lines[] = 'FIN';
echo implode("\n", $lines) . "\n";
