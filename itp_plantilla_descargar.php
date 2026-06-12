<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/word_filename_helper.php';

$templates = [
    'simple' => [
        'path' => __DIR__ . '/plantillas/acta_itp_simple.docx',
        'filename' => 'acta_itp_simple',
    ],
    'interseccion' => [
        'path' => __DIR__ . '/plantillas/acta_itp_interseccion.docx',
        'filename' => 'acta_itp_interseccion',
    ],
];

$tipo = trim((string) ($_GET['tipo'] ?? ''));
if (!isset($templates[$tipo])) {
    http_response_code(400);
    exit('Tipo de plantilla ITP no valido.');
}

$template = $templates[$tipo];
if (!is_file($template['path'])) {
    http_response_code(404);
    exit('Plantilla ITP no encontrada.');
}

$filename = uiat_docx_filename([$template['filename']], $template['filename']);
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($template['path']));
header('X-Content-Type-Options: nosniff');
readfile($template['path']);
exit;
