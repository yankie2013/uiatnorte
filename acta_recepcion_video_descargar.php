<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/word_filename_helper.php';

use App\Repositories\ActaRecepcionVideoRepository;
use App\Services\ActaRecepcionVideoService;
use App\Support\Access;
use PhpOffice\PhpWord\TemplateProcessor;

$id = (int) ($_GET['id'] ?? 0);
$repo = new ActaRecepcionVideoRepository($pdo);
$row = $id > 0 ? $repo->find($id) : null;
if (!$row) { http_response_code(404); exit('Acta no encontrada.'); }
Access::requireWorkspaceCase((int) $row['accidente_id']);
$template = __DIR__ . '/plantillas/acta_recepcion_video.docx';
if (!is_file($template)) { http_response_code(500); exit('Falta la plantilla del acta de recepción de video.'); }

try {
    $accident = $repo->accident((int) $row['accidente_id']);
    if (!$accident) throw new RuntimeException('Accidente no encontrado.');
    $data = ActaRecepcionVideoService::validated(array_merge(
        ActaRecepcionVideoService::defaults($accident, []),
        $row['datos']
    ));
    $processor = new TemplateProcessor($template);
    foreach (ActaRecepcionVideoService::documentValues($data) as $key => $value) {
        $processor->setValue($key, htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
    $processor->setImageValue('sello_depiat_norte', [
        'path' => __DIR__ . '/assets/img/sello_depiat_norte.png',
        'width' => 118,
        'height' => 118,
        'ratio' => false,
    ]);
    $tmp = tempnam(sys_get_temp_dir(), 'acta_video_');
    if ($tmp === false) throw new RuntimeException('No se pudo preparar el archivo Word.');
    $processor->saveAs($tmp);
    $filename = uiat_docx_filename(['acta_recepcion_video', $row['accidente_id'], $data['fecha_acta']], 'acta_recepcion_video');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
} catch (Throwable $e) {
    error_log('Acta recepción video: ' . $e->getMessage());
    if (!headers_sent()) http_response_code(500);
    exit('No se pudo generar el documento Word.');
}
