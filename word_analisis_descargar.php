<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/word_filename_helper.php';

use App\Services\AnalisisWordService;
use App\Support\Access;

ini_set('display_errors', '0');
$case=(int)($_GET['accidente_id']??0);
$section=(string)($_GET['seccion']??'');
if ($case<=0 || !isset(AnalisisWordService::TITLES[$section])) {
    http_response_code(400);exit('Selecciona un expediente y un apartado de análisis válidos.');
}
Access::requireWorkspaceCase($case);
$file=false;
try {
    [$data,$context]=AnalisisWordService::load($pdo,$case,$section);
    $file=tempnam(sys_get_temp_dir(),'uiat_analysis_');
    if($file===false)throw new RuntimeException('No se pudo crear el documento temporal.');
    (new AnalisisWordService())->generate($section,$data,$context,$file);
    $name=uiat_docx_filename(['ANALISIS',$section,'SIDPOL',$context['accidente']['registro_sidpol']?:$case], 'ANALISIS_' . $section . '_' . $case);
    while(ob_get_level())ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="'.$name.'"');
    header('Content-Length: '.filesize($file));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
} catch (Throwable $e) {
    error_log('Exportación de análisis '.$section.' / '.$case.': '.$e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo generar el Word. Verifica que el apartado esté guardado y que su plantilla esté instalada.';
} finally { if($file!==false && is_file($file))unlink($file); }
