<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Repositories\DocumentoPlantillaRepository;
use App\Services\DocumentoPlantillaService;
use PhpOffice\PhpWord\TemplateProcessor;

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_errors.log');

$oficioId = isset($_GET['oficio_id']) ? (int) $_GET['oficio_id'] : 0;
if ($oficioId <= 0) {
    http_response_code(400);
    exit('Falta oficio_id');
}

$service = new DocumentoPlantillaService(new DocumentoPlantillaRepository($pdo));

try {
    $data = $service->oficioRemitirData($oficioId);
} catch (Throwable $e) {
    http_response_code($e instanceof \InvalidArgumentException ? 404 : 500);
    exit($e->getMessage());
}

$templatePath = __DIR__ . '/plantillas/oficio_remitir_diligencia.docx';
if (!file_exists($templatePath)) {
    http_response_code(500);
    exit('No se encuentra la plantilla: plantillas/oficio_remitir_diligencia.docx');
}

$tpl = new TemplateProcessor($templatePath);
foreach ($data['values'] as $key => $value) {
    $tpl->setValue($key, htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
}

$tmp = tempnam(sys_get_temp_dir(), 'docx_');
$tpl->saveAs($tmp);
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $data['filename'] . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($tmp);
@unlink($tmp);
exit;
