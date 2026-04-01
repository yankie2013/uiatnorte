<?php
require __DIR__ . '/vendor/autoload.php';
if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && file_exists(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require_once __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}
$tpl = new \PhpOffice\PhpWord\TemplateProcessor(__DIR__ . '/plantillas/notificacion_abogado.docx');
$rows = [
  [
    'citacion_motivo' => 'Rendir manifestacion',
    'citacion_detalle_persona' => ', de Teofilo Alfredo PARIZACA AMANQUI, efectivo policial',
    'citacion_fecha' => '01ABR2026',
    'citacion_hora' => '09:00',
    'citacion_lugar' => 'Carretera Panamericana Norte km. 42 (alt. garita control SUNAT) sede de la Unidad de Investigacion de Accidentes de Transito-Lima Norte',
  ],
  [
    'citacion_motivo' => 'Rendir manifestacion',
    'citacion_detalle_persona' => ', de Teofilo Alfredo PARIZACA AMANQUI, efectivo policial',
    'citacion_fecha' => '01ABR2026',
    'citacion_hora' => '09:00',
    'citacion_lugar' => 'Carretera Panamericana Norte km. 42 (alt. garita control SUNAT) sede de la Unidad de Investigacion de Accidentes de Transito-Lima Norte',
  ],
  [
    'citacion_motivo' => 'Rendir manifestacion',
    'citacion_detalle_persona' => ', de Luis Antonio BARRERA BARRETO, familiar más cercano',
    'citacion_fecha' => '15ABR2026',
    'citacion_hora' => '15:00',
    'citacion_lugar' => 'Carretera Panamericana Norte km. 42 (alt. garita control SUNAT) sede de la Unidad de Investigacion de Accidentes de Transito-Lima Norte',
  ],
];
$tpl->cloneBlock('citaciones', count($rows), true, false, $rows);
$out = __DIR__ . '/tmp_notif_multi.docx';
@unlink($out);
$tpl->saveAs($out);
$zip = new ZipArchive();
if ($zip->open($out) !== true) { echo "zip-open-fail\n"; exit(1); }
$doc = $zip->getFromName('word/document.xml');
$zip->close();
echo 'size=' . filesize($out) . PHP_EOL;
foreach (['15ABR2026', '01ABR2026', '15:00', '09:00'] as $needle) {
  echo $needle . ' => ' . (strpos((string)$doc, $needle) === false ? 'false' : 'true') . PHP_EOL;
}
