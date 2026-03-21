<?php
// exportar_word.php
// Utilidad de diagnostico para confirmar que PhpWord puede generar y descargar un DOCX.

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

if (is_file(__DIR__.'/vendor/autoload.php')) { require __DIR__.'/vendor/autoload.php'; }
if (!class_exists(\PhpOffice\PhpWord\PhpWord::class) && is_file(__DIR__.'/PHPWord-1.4.0/vendor/autoload.php')) {
    require __DIR__.'/PHPWord-1.4.0/vendor/autoload.php';
}

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

if (!class_exists(PhpWord::class)) {
    http_response_code(500);
    exit('PhpWord no esta disponible para la prueba de exportacion.');
}

$tmpDir = __DIR__.'/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }
Settings::setTempDir($tmpDir);

try {
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    $section->addTitle('Prueba de PHPWord', 1);
    $section->addText('La generacion de documentos DOCX esta operativa en este entorno.', ['bold' => true, 'size' => 13]);
    $section->addText('Fecha de generacion: ' . date('Y-m-d H:i:s'));
    $section->addText('Ruta de trabajo temporal: ' . $tmpDir);

    $tmp = tempnam($tmpDir, 'phpword_test_');
    if ($tmp === false) {
        throw new RuntimeException('No se pudo crear el archivo temporal.');
    }
    IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

    $fileName = 'reporte_prueba_phpword_' . date('Ymd_His') . '.docx';
    while (ob_get_level()) { @ob_end_clean(); }
    if (headers_sent($fileSent, $lineSent)) {
        @unlink($tmp);
        throw new RuntimeException('Ya existia salida previa en ' . $fileSent . ':' . $lineSent);
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error: ' . $e->getMessage();
}
