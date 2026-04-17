<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}
if (!class_exists(\PhpOffice\PhpWord\PhpWord::class) && is_file(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

if (!class_exists(PhpWord::class) || !class_exists(IOFactory::class)) {
    http_response_code(500);
    exit('PhpWord no esta disponible para generar el DOCX.');
}

$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0775, true);
}
Settings::setTempDir($tmpDir);
Settings::setOutputEscapingEnabled(true);

function add_marker_table($section, array $rows): void
{
    $table = $section->addTable('MarkersTableDatosGenerales');
    $table->addRow();
    $table->addCell(3000, ['bgColor' => 'E5E7EB'])->addText('Marcador', ['bold' => true, 'size' => 9]);
    $table->addCell(2600, ['bgColor' => 'E5E7EB'])->addText('Seccion', ['bold' => true, 'size' => 9]);
    $table->addCell(4200, ['bgColor' => 'E5E7EB'])->addText('Descripcion', ['bold' => true, 'size' => 9]);

    foreach ($rows as $row) {
        $table->addRow();
        $table->addCell(3000)->addText('${' . (string) $row['marker'] . '}', ['size' => 9]);
        $table->addCell(2600)->addText((string) $row['section'], ['size' => 9]);
        $table->addCell(4200)->addText((string) $row['description'], ['size' => 9]);
    }
}

$rows = [
    ['marker' => 'accidente_id', 'section' => 'Cabecera', 'description' => 'ID interno del accidente'],
    ['marker' => 'acc_sidpol', 'section' => 'Cabecera', 'description' => 'SIDPOL del accidente'],
    ['marker' => 'acc_registro_sidpol', 'section' => 'Cabecera', 'description' => 'Registro SIDPOL'],
    ['marker' => 'acc_nro_informe_policial', 'section' => 'Cabecera', 'description' => 'Numero de informe policial'],

    ['marker' => 'acc_clase_accidente', 'section' => 'Datos intervencion', 'description' => 'Clase de accidente o modalidad'],
    ['marker' => 'acc_consecuencia', 'section' => 'Datos intervencion', 'description' => 'Consecuencia del accidente'],
    ['marker' => 'acc_lugar_jurisdiccion_policial', 'section' => 'Datos intervencion', 'description' => 'Lugar y jurisdiccion policial'],
    ['marker' => 'acc_fecha_hora_accidente', 'section' => 'Datos intervencion', 'description' => 'Fecha y hora del accidente'],
    ['marker' => 'acc_fecha_hora_comunicacion', 'section' => 'Datos intervencion', 'description' => 'Fecha y hora de comunicacion'],
    ['marker' => 'acc_fecha_hora_intervencion', 'section' => 'Datos intervencion', 'description' => 'Fecha y hora de intervencion'],
    ['marker' => 'acc_unidades_participantes', 'section' => 'Datos intervencion', 'description' => 'Bloque de unidades participantes con conductor, edad y nacionalidad'],
    ['marker' => 'acc_unidades_participantes_relato', 'section' => 'Datos intervencion', 'description' => 'Texto corrido de unidades participantes. Ej.: Omnibus con placa de rodaje ASY-920, conducido por Carlos Alberto ALARCON GRANDEZ (35), Automovil con placa de rodaje ABC-123, conducido por Q.E.V.F. Giancarlo MERINO SANCHO, en agravio del peaton Q.E.V.F. Miguel Angel BARRUETA HERNANDEZ (52)'],
    ['marker' => 'acc_clase_via_zona', 'section' => 'Datos intervencion', 'description' => 'Clase de via y zona'],
    ['marker' => 'acc_fiscalia', 'section' => 'Datos intervencion', 'description' => 'Fiscalia'],
    ['marker' => 'acc_fiscal_cargo', 'section' => 'Datos intervencion', 'description' => 'Fiscal a cargo'],
    ['marker' => 'acc_sentido', 'section' => 'Datos intervencion', 'description' => 'Sentido de circulacion'],
    ['marker' => 'acc_secuencia', 'section' => 'Datos intervencion', 'description' => 'Secuencia del evento'],

    ['marker' => 'itp_id', 'section' => 'ITP', 'description' => 'ID del ultimo ITP del accidente'],
    ['marker' => 'itp_fecha', 'section' => 'ITP', 'description' => 'Fecha del ITP'],
    ['marker' => 'itp_hora', 'section' => 'ITP', 'description' => 'Hora del ITP'],
    ['marker' => 'itp_forma_via', 'section' => 'ITP', 'description' => 'Forma de la via'],
    ['marker' => 'itp_punto_referencia', 'section' => 'ITP', 'description' => 'Punto de referencia'],
    ['marker' => 'itp_ubicacion_gps', 'section' => 'ITP', 'description' => 'Ubicacion GPS'],
    ['marker' => 'itp_localizacion_unidades', 'section' => 'ITP', 'description' => 'Lista de localizacion de unidades'],
    ['marker' => 'itp_ocurrencia_policial', 'section' => 'ITP', 'description' => 'Narracion de ocurrencia policial'],
    ['marker' => 'itp_llegada_lugar', 'section' => 'ITP', 'description' => 'Narracion de llegada al lugar'],

    ['marker' => 'itp_via1_descripcion', 'section' => 'ITP Via 1', 'description' => 'Descripcion de la via 1'],
    ['marker' => 'itp_via1_configuracion', 'section' => 'ITP Via 1', 'description' => 'Configuracion de la via 1'],
    ['marker' => 'itp_via1_material', 'section' => 'ITP Via 1', 'description' => 'Material de la via 1'],
    ['marker' => 'itp_via1_senializacion', 'section' => 'ITP Via 1', 'description' => 'Senializacion de la via 1'],
    ['marker' => 'itp_via1_ordenamiento', 'section' => 'ITP Via 1', 'description' => 'Ordenamiento de la via 1'],
    ['marker' => 'itp_via1_iluminacion', 'section' => 'ITP Via 1', 'description' => 'Iluminacion de la via 1'],
    ['marker' => 'itp_via1_visibilidad', 'section' => 'ITP Via 1', 'description' => 'Visibilidad de la via 1'],
    ['marker' => 'itp_via1_intensidad', 'section' => 'ITP Via 1', 'description' => 'Intensidad de la via 1'],
    ['marker' => 'itp_via1_fluidez', 'section' => 'ITP Via 1', 'description' => 'Fluidez de la via 1'],
    ['marker' => 'itp_via1_medidas', 'section' => 'ITP Via 1', 'description' => 'Lista de medidas de la via 1'],
    ['marker' => 'itp_via1_observaciones', 'section' => 'ITP Via 1', 'description' => 'Lista de observaciones de la via 1'],

    ['marker' => 'itp_via2_descripcion', 'section' => 'ITP Via 2', 'description' => 'Descripcion de la via 2'],
    ['marker' => 'itp_via2_configuracion', 'section' => 'ITP Via 2', 'description' => 'Configuracion de la via 2'],
    ['marker' => 'itp_via2_material', 'section' => 'ITP Via 2', 'description' => 'Material de la via 2'],
    ['marker' => 'itp_via2_senializacion', 'section' => 'ITP Via 2', 'description' => 'Senializacion de la via 2'],
    ['marker' => 'itp_via2_ordenamiento', 'section' => 'ITP Via 2', 'description' => 'Ordenamiento de la via 2'],
    ['marker' => 'itp_via2_iluminacion', 'section' => 'ITP Via 2', 'description' => 'Iluminacion de la via 2'],
    ['marker' => 'itp_via2_visibilidad', 'section' => 'ITP Via 2', 'description' => 'Visibilidad de la via 2'],
    ['marker' => 'itp_via2_intensidad', 'section' => 'ITP Via 2', 'description' => 'Intensidad de la via 2'],
    ['marker' => 'itp_via2_fluidez', 'section' => 'ITP Via 2', 'description' => 'Fluidez de la via 2'],
    ['marker' => 'itp_via2_medidas', 'section' => 'ITP Via 2', 'description' => 'Lista de medidas de la via 2'],
    ['marker' => 'itp_via2_observaciones', 'section' => 'ITP Via 2', 'description' => 'Lista de observaciones de la via 2'],

    ['marker' => 'itp_evidencia_biologica', 'section' => 'ITP Evidencias', 'description' => 'Evidencia biologica'],
    ['marker' => 'itp_evidencia_fisica', 'section' => 'ITP Evidencias', 'description' => 'Evidencia fisica'],
    ['marker' => 'itp_evidencia_material', 'section' => 'ITP Evidencias', 'description' => 'Evidencia material'],
];

$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Arial');
$phpWord->setDefaultFontSize(10);
$phpWord->addTableStyle('MarkersTableDatosGenerales', [
    'borderSize' => 6,
    'borderColor' => 'D1D5DB',
    'cellMargin' => 80,
]);

$section = $phpWord->addSection([
    'marginTop' => 900,
    'marginBottom' => 900,
    'marginLeft' => 900,
    'marginRight' => 900,
]);

$section->addText('LISTA DE MARCADORES - INFORME DE DATOS GENERALES', ['bold' => true, 'size' => 14], ['align' => 'center', 'spaceAfter' => 120]);
$section->addText('Este reporte se genera de forma directa con PhpWord. La siguiente lista resume los marcadores equivalentes sugeridos para una plantilla DOCX.', ['size' => 10], ['spaceAfter' => 120]);

add_marker_table($section, $rows);

$filename = 'marcadores_informe_datos_generales.docx';
$tmp = tempnam($tmpDir, 'mrdg_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear el archivo temporal.');
}

IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

while (ob_get_level()) {
    @ob_end_clean();
}

if (headers_sent($fileSent, $lineSent)) {
    @unlink($tmp);
    http_response_code(500);
    exit('No se pudo iniciar la descarga del DOCX porque ya habia salida previa en ' . $fileSent . ':' . $lineSent);
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
