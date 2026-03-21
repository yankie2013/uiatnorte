<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && file_exists(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require_once __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}

use App\Repositories\DocumentoPlantillaRepository;
use App\Services\DocumentoPlantillaService;
use PhpOffice\PhpWord\TemplateProcessor;

header('Content-Type: text/html; charset=utf-8');

$citacionId = isset($_GET['citacion_id']) ? (int) $_GET['citacion_id'] : 0;
if ($citacionId <= 0) {
    http_response_code(400);
    exit('Falta citacion_id');
}

if (!class_exists(TemplateProcessor::class)) {
    http_response_code(500);
    exit('PhpWord no esta disponible para generar el DOCX.');
}

$service = new DocumentoPlantillaService(new DocumentoPlantillaRepository($pdo));

try {
    $data = $service->citacionData($citacionId);
} catch (Throwable $e) {
    http_response_code($e instanceof \InvalidArgumentException ? 404 : 500);
    exit($e->getMessage());
}

$templatePath = __DIR__ . '/plantillas/citacion_diligencia.docx';
if (!file_exists($templatePath)) {
    http_response_code(500);
    exit('No se encuentra la plantilla citacion_diligencia.docx');
}

function docx_text(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    return $text;
}

$tpl = new TemplateProcessor($templatePath);
foreach ($data['values'] as $key => $value) {
    $tpl->setValue($key, docx_text($value));
}

$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0775, true);
}
$tmp = tempnam($tmpDir, 'cit_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear un archivo temporal para el DOCX.');
}
$tpl->saveAs($tmp);

$filename = (string) ($data['filename'] ?? ('Citacion_' . $citacionId . '.docx'));
$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: ('Citacion_' . $citacionId . '.docx');
if (!str_ends_with(strtolower($filename), '.docx')) {
    $filename .= '.docx';
}

while (ob_get_level()) {
    ob_end_clean();
}
if (headers_sent()) {
    @unlink($tmp);
    http_response_code(500);
    exit('No se pudo iniciar la descarga del DOCX porque ya habia salida previa.');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($tmp);
@unlink($tmp);
exit;
