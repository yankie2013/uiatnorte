<?php
header('Content-Type: text/plain; charset=utf-8');

try {
  if (is_file(__DIR__.'/vendor/autoload.php')) { require __DIR__.'/vendor/autoload.php'; }
  if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && is_file(__DIR__.'/PHPWord-1.4.0/vendor/autoload.php')) {
    require __DIR__.'/PHPWord-1.4.0/vendor/autoload.php';
  }
  require __DIR__.'/db.php';
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("SET NAMES utf8mb4");
} catch (Throwable $e) {
  echo 'BOOT FAIL: '.$e->getMessage();
  exit;
}

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Settings;

if (!class_exists(TemplateProcessor::class)) {
  echo "PhpWord no disponible\n";
  exit;
}

$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }
Settings::setTempDir($tmpDir);

$tpl = __DIR__.'/plantillas/informe_atropello.docx';
echo 'TPL: '.(is_file($tpl) ? 'OK' : 'MISSING')."\n";
if (!is_file($tpl)) {
  exit;
}

try {
  $tp = new TemplateProcessor($tpl);
  $tp->setValue('TEST', 'OK');
  $tmp = tempnam($tmpDir, 'tplcheck_');
  if ($tmp === false) {
    echo "TMP FAIL\n";
    exit;
  }
  $tp->saveAs($tmp);
  echo "TemplateProcessor: OK\n";
  echo 'Saved: '.$tmp."\n";
  @unlink($tmp);
} catch (Throwable $e) {
  echo 'TemplateProcessor ERROR: '.$e->getMessage()."\n";
}
