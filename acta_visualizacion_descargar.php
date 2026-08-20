<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/word_filename_helper.php';

use App\Repositories\ActaVisualizacionRepository;
use PhpOffice\PhpWord\TemplateProcessor;

function avv_date_long(string $date): string
{
    $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $time = strtotime($date);
    return $time ? date('j', $time) . ' de ' . $months[(int) date('n', $time) - 1] . ' de ' . date('Y', $time) : '';
}

function avv_date_abbrev(string $date): string
{
    $months = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
    $time = strtotime($date);
    return $time ? date('d', $time) . $months[(int) date('n', $time) - 1] . date('Y', $time) : '';
}

function avv_join(array $rows, callable $formatter): string
{
    return implode("\n", array_values(array_filter(array_map($formatter, $rows))));
}

function avv_names(array $rows): string
{
    return implode(', ', array_values(array_filter(array_map(static fn(array $row): string => trim((string) ($row['nombre'] ?? '')), $rows))));
}

function avv_normalize_description_spacing(string $docxPath): void
{
    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) return;
    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false) {
        $zip->close();
        return;
    }
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $remove = [];
    foreach ($xpath->query('//w:p') as $paragraph) {
        if (str_contains($paragraph->textContent, '__UIAT_QUITAR_PARRAFO__')) $remove[] = $paragraph;
    }
    foreach ($remove as $paragraph) $paragraph->parentNode->removeChild($paragraph);

    foreach ($xpath->query('//w:p') as $timeParagraph) {
        if (!str_starts_with(trim($timeParagraph->textContent), 'Tiempo observado:')) continue;
        $previous = $timeParagraph->previousSibling;
        $empty = [];
        while (
            $previous instanceof DOMElement
            && $previous->localName === 'p'
            && trim($previous->textContent) === ''
            && $xpath->query('.//w:pict | .//w:drawing', $previous)->length === 0
        ) {
            $empty[] = $previous;
            $previous = $previous->previousSibling;
        }
        foreach (array_slice($empty, 1) as $paragraph) {
            $paragraph->parentNode->removeChild($paragraph);
        }
    }
    $zip->addFromString('word/document.xml', $dom->saveXML());
    $zip->close();
}

$id = (int) ($_GET['id'] ?? 0);
$repo = new ActaVisualizacionRepository($pdo);
$row = $repo->find($id);
if (!$row) {
    http_response_code(404);
    exit('Acta de visualizacion no encontrada');
}

$template = is_file(__DIR__ . '/plantillas/acta_visualizacion_video.docx')
    ? __DIR__ . '/plantillas/acta_visualizacion_video.docx'
    : __DIR__ . '/acta_visualizacion_video.docx';
if (!is_file($template)) {
    http_response_code(500);
    exit('Falta la plantilla acta_visualizacion_video.docx');
}

$accident = $repo->accident((int) $row['accidente_id']) ?? [];
$participants = $row['participantes'] ?? [];
$documents = $row['documentos'] ?? [];
$disks = $row['discos'] ?? [];
$bySource = static fn(string $source): array => array_values(array_filter($participants, static fn(array $item): bool => ($item['fuente'] ?? '') === $source));
$involved = $bySource('INVOLUCRADO');
$family = $bySource('FAMILIAR');
$owners = $bySource('PROPIETARIO');
$lawyers = $bySource('ABOGADO');
$offices = array_values(array_filter($documents, static fn(array $item): bool => ($item['fuente'] ?? '') === 'OFICIO'));
$responses = array_values(array_filter($documents, static fn(array $item): bool => ($item['fuente'] ?? '') === 'RESPUESTA'));

$participantsDetail = avv_join($participants, static fn(array $item): string => trim((string) ($item['nombre'] ?? '')) . ' (' . trim((string) ($item['condicion'] ?? 'Participante')) . ')');
$documentsDetail = avv_join($documents, static fn(array $item): string => trim((string) ($item['descripcion'] ?? '')));
$officesDetail = avv_join($offices, static fn(array $item): string => trim((string) ($item['descripcion'] ?? '')));
$responsesDetail = avv_join($responses, static fn(array $item): string => trim((string) ($item['descripcion'] ?? '')));
$cameraOfficesById = [];
foreach ($repo->cameraOffices((int)$row['accidente_id']) as $cameraOffice) {
    $cameraOfficesById[(int)$cameraOffice['oficio_id']] = $cameraOffice;
}
$officeOrder = [];
foreach ($disks as $disk) {
    $officeId = (int)($disk['oficio_id'] ?? 0);
    if ($officeId > 0 && isset($cameraOfficesById[$officeId]) && !in_array($officeId, $officeOrder, true)) $officeOrder[] = $officeId;
}
$officeNarratives = [];
foreach ($officeOrder as $officeIndex => $officeId) {
    $office = $cameraOfficesById[$officeId];
    $responseLabels = array_values(array_filter(array_map(static function(array $response): string {
        $number = trim((string)($response['numero_completo'] ?? ''));
        $entity = trim((string)($response['entidad'] ?? ''));
        return trim(($number !== '' ? 'documento N° '.$number : 'documento recibido').($entity !== '' ? ' remitido por '.$entity : ''));
    }, $office['respuestas'] ?? [])));
    $officeFiles = 0;
    foreach ($disks as $disk) if ((int)($disk['oficio_id'] ?? 0) === $officeId) $officeFiles += count($disk['archivos'] ?? []);
    $request = rtrim(trim((string)($office['solicitud'] ?? '')), '.');
    $officeNarratives[] = 'OFICIO '.($officeIndex + 1).'. Con '.($office['numero_completo'] ?? 'el oficio indicado')
        .', se solicitó a '.($office['entidad_destino'] ?: 'la entidad destinataria')
        .($request !== '' ? ' '.lcfirst($request) : ' las grabaciones de sus cámaras de videovigilancia')
        .($responseLabels ? ', recibiéndose '.implode(' y ', $responseLabels) : '')
        .', mediante '.($officeFiles === 1 ? 'el cual se remitió un archivo de video' : 'los cuales se remitieron '.$officeFiles.' archivos de video').'.';
}
$filesTotal = 0;
$diskLines = [];
$fileLines = [];
$videoDescriptions = [];
foreach ($disks as $diskIndex => $disk) {
    $number = (int) ($disk['numero'] ?? ($diskIndex + 1));
    $diskLetter = chr(65 + ($diskIndex % 26));
    $mediumType = strtoupper(trim((string)($disk['tipo_medio'] ?? ''))) ?: 'DISCO';
    $diskHeader = "{$diskLetter}. {$mediumType} {$number}, marca " . (trim((string) ($disk['marca'] ?? '')) ?: 'no consignada')
        . ', serie N° ' . (trim((string) ($disk['numero_serie'] ?? '')) ?: 'no consignada')
        . ', capacidad ' . (trim((string) ($disk['capacidad'] ?? '')) ?: 'no consignada')
        . (trim((string) ($disk['observaciones'] ?? '')) !== '' ? ', ' . trim((string) $disk['observaciones']) : '');
    $diskLines[] = $diskHeader;
    foreach (($disk['archivos'] ?? []) as $fileIndex => $file) {
        $filesTotal++;
        $letter = chr(97 + ($fileIndex % 26));
        $line = "{$letter}) " . (trim((string) ($file['nombre_archivo'] ?? '')) ?: 'Archivo sin nombre');
        foreach (['tipo_archivo' => 'archivo tipo', 'peso' => 'tamaño', 'duracion' => 'duración'] as $field => $label) {
            if (trim((string) ($file[$field] ?? '')) !== '') $line .= ', ' . $label . ' ' . trim((string) $file[$field]);
        }
        if (trim((string) ($file['observaciones'] ?? '')) !== '') $line .= ', ' . trim((string) $file['observaciones']);
        $fileLines[] = $line;
        $descriptions = $file['descripciones'] ?? [];
        if ($descriptions === []) $descriptions = [['tiempo'=>'','detalle'=>'','captura_path'=>'']];
        foreach ($descriptions as $descriptionIndex => $description) {
            $videoDescriptions[] = [
                'disco_encabezado' => $fileIndex === 0 && $descriptionIndex === 0 ? $diskHeader : '',
                'archivo_encabezado' => $descriptionIndex === 0 ? $line : '',
                'tiempo' => substr((string) ($description['tiempo'] ?? ''), 0, 8),
                'detalle' => trim((string) ($description['detalle'] ?? '')),
                'captura_path' => trim((string) ($description['captura_path'] ?? '')),
            ];
        }
    }
}

$actDate = (string) ($row['fecha_visualizacion'] ?? '');
$actTime = substr((string) ($row['hora_inicio'] ?? ''), 0, 5);
$accidentDateTime = (string) ($accident['fecha_accidente'] ?? '');
$district = 'Santa Rosa';
$unit = 'Unidad de Investigación de Accidentes de Tránsito Norte';
$instructorName = 'Giancarlo Jorge MERINO SANCHO';
$instructorGrade = 'ST3.PNP';
$instructorCip = '';
$instructor = trim(implode(' ', array_filter([$instructorGrade, $instructorName])));
$fiscalName = trim((string) ($accident['fiscal'] ?? ''));
$fiscalOffice = trim((string) ($accident['fiscalia'] ?? ''));
$accidentPlace = trim((string) ($accident['lugar'] ?? ''));
$accidentReference = trim((string) ($accident['referencia'] ?? ''));
$accidentLocation = implode(', ', array_filter([$accidentPlace, $accidentReference]));

$actPresentation = "En el distrito de {$district}, siendo las {$actTime} horas del " . avv_date_long($actDate)
    . ", en la oficina de trabajo de la {$unit}, presente ante el instructor"
    . ($instructor !== '' ? " {$instructor}" : '')
    . ". Participan en la diligencia: " . ($participantsDetail ?: 'participantes no consignados')
    . ", con la finalidad de participar en el deslacrado, visualización, transcripción y lacrado del video relacionado con el accidente de tránsito ocurrido el "
    . avv_date_long($accidentDateTime) . (date('H:i', strtotime($accidentDateTime) ?: 0) !== '00:00' ? ', a las ' . date('H:i', strtotime($accidentDateTime)) . ' horas' : '')
    . ($accidentLocation !== '' ? ", en {$accidentLocation}" : '') . '.';
$fiscalParagraph = $fiscalName !== ''
    ? "Se deja constancia que en la diligencia interviene el representante del Ministerio Público {$fiscalName}"
        . (trim((string) ($accident['fiscal_cargo'] ?? '')) !== '' ? ', ' . trim((string) $accident['fiscal_cargo']) : '')
        . ($fiscalOffice !== '' ? " de {$fiscalOffice}" : '') . '.'
    : 'Representante del Ministerio Público no consignado.';
$structuredOfficesDetail = implode("\n\n", $officeNarratives);
$officeParagraph = $structuredOfficesDetail !== ''
    ? "Se deja constancia de los oficios y respuestas vinculados con cámaras de videovigilancia:\n\n{$structuredOfficesDetail}"
    : ($documentsDetail !== ''
    ? "Se deja constancia de los oficios y respuestas vinculados con cámaras de videovigilancia:\n{$documentsDetail}"
    : 'No se seleccionaron oficios ni respuestas vinculados con cámaras de videovigilancia.');
$diskParagraph = $diskLines
    ? 'Seguidamente se procede con el deslacrado y visualización de los medios de almacenamiento, detallándose los discos, archivos y momentos observados:'
    : 'No se registraron discos para la diligencia.';

$values = [
    'acta_visualizacion_id' => $row['id'], 'acta_visualizacion_estado' => $row['estado'] ?? '',
    'acta_visualizacion_fecha' => avv_date_long($actDate), 'acta_visualizacion_fecha_corta' => ($actDate ? date('d/m/Y', strtotime($actDate)) : ''),
    'acta_visualizacion_fecha_abrev' => avv_date_abbrev($actDate), 'acta_visualizacion_hora_inicio' => $actTime,
    'acta_visualizacion_observaciones' => $row['observaciones'] ?? '',
    'acta_presentacion' => $actPresentation, 'ministerio_publico_parrafo' => $fiscalParagraph,
    'desarrollo_diligencia' => "Desarrollo de la diligencia:\n{$officeParagraph}\n{$diskParagraph}",
    'diligencia_oficios_parrafo' => $officeParagraph, 'diligencia_discos_parrafo' => $diskParagraph,
    'diligencia_archivos_detalle' => '',
    'descripciones_video_detalle' => avv_join($videoDescriptions, static fn(array $item): string => trim($item['disco_encabezado'] . "\n" . $item['archivo_encabezado'] . "\nTiempo observado: " . $item['tiempo'] . "\n" . $item['detalle'])),
    'unidad_nombre' => $unit, 'lugar_diligencia' => $district,
    'instructor_nombre' => $instructorName, 'instructor_grado' => $instructorGrade, 'instructor_cip' => $instructorCip,
    'accidente_id' => $accident['id'] ?? '', 'accidente_sidpol' => $accident['registro_sidpol'] ?? '',
    'accidente_fecha' => avv_date_long($accidentDateTime), 'accidente_fecha_corta' => ($accidentDateTime ? date('d/m/Y', strtotime($accidentDateTime)) : ''),
    'accidente_fecha_abrev' => avv_date_abbrev($accidentDateTime), 'accidente_hora' => ($accidentDateTime ? date('H:i', strtotime($accidentDateTime)) : ''),
    'accidente_lugar' => $accidentPlace, 'accidente_referencia' => $accidentReference, 'accidente_distrito' => $accident['accidente_distrito'] ?? '',
    'fiscal_nombre' => $fiscalName, 'fiscal_cargo' => $accident['fiscal_cargo'] ?? '', 'fiscal_telefono' => $accident['fiscal_telefono'] ?? '', 'fiscalia_nombre' => $fiscalOffice,
    'participantes_nombres' => avv_names($participants), 'participantes_detalle' => $participantsDetail,
    'parte_investigada' => avv_names($involved), 'parte_agraviada' => avv_names($family),
    'familiares_detalle' => avv_join($family, static fn(array $item): string => trim((string) $item['nombre']) . ' (' . trim((string) $item['condicion']) . ')'),
    'propietarios_detalle' => avv_join($owners, static fn(array $item): string => trim((string) $item['nombre']) . ' (' . trim((string) $item['condicion']) . ')'),
    'abogados_detalle' => avv_join($lawyers, static fn(array $item): string => trim((string) $item['nombre']) . ' (' . trim((string) $item['condicion']) . ')'),
    'oficios_camaras_detalle' => $officesDetail, 'respuestas_camaras_detalle' => $responsesDetail, 'documentos_camaras_detalle' => $documentsDetail,
    'discos_detalle' => implode("\n", $diskLines), 'archivos_detalle' => implode("\n", $fileLines),
    'cantidad_discos' => count($disks), 'cantidad_archivos' => $filesTotal,
    'cantidad_descripciones_video' => count($videoDescriptions),
];
for ($i = 1; $i <= 10; $i++) {
    $disk = $disks[$i - 1] ?? [];
    $values["disco_{$i}_numero"] = $disk['numero'] ?? '';
    $values["disco_{$i}_tipo"] = $disk['tipo_medio'] ?? 'DISCO';
    $values["disco_{$i}_marca"] = $disk['marca'] ?? '';
    $values["disco_{$i}_serie"] = $disk['numero_serie'] ?? '';
    $values["disco_{$i}_capacidad"] = $disk['capacidad'] ?? '';
    $values["disco_{$i}_observaciones"] = $disk['observaciones'] ?? '';
    $values["disco_{$i}_archivos"] = isset($disk['archivos']) ? avv_join($disk['archivos'], static fn(array $file): string => trim((string) ($file['nombre_archivo'] ?? ''))) : '';
}
for ($i = 1; $i <= 50; $i++) {
    $description = $videoDescriptions[$i - 1] ?? [];
    $values["descripcion_{$i}_tiempo"] = $description['tiempo'] ?? '';
    $values["descripcion_{$i}_detalle"] = $description['detalle'] ?? '';
}

$tpl = new TemplateProcessor($template);
$tpl->cloneBlock('DESCRIPCIONES_VIDEO', count($videoDescriptions), true, true);
foreach ($values as $key => $value) {
    $tpl->setValue($key, htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}
foreach ($videoDescriptions as $descriptionIndex => $description) {
    $i = $descriptionIndex + 1;
    $tpl->setValue("disco_encabezado#{$i}", $description['disco_encabezado'] !== '' ? htmlspecialchars((string) $description['disco_encabezado'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '__UIAT_QUITAR_PARRAFO__');
    $tpl->setValue("archivo_encabezado#{$i}", $description['archivo_encabezado'] !== '' ? htmlspecialchars((string) $description['archivo_encabezado'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '__UIAT_QUITAR_PARRAFO__');
    $tpl->setValue("descripcion_tiempo#{$i}", $description['tiempo'] !== '' ? 'Tiempo observado: ' . htmlspecialchars((string) $description['tiempo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '');
    $tpl->setValue("descripcion_detalle#{$i}", htmlspecialchars((string) $description['detalle'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $tpl->setValue("descripcion_captura#{$i}", '__UIAT_QUITAR_PARRAFO__');
}
$tmp = tempnam(sys_get_temp_dir(), 'acta_visualizacion_');
$tpl->saveAs($tmp);
avv_normalize_description_spacing($tmp);
$filename = uiat_docx_filename(['acta_visualizacion_video', $row['id'], $actDate], 'acta_visualizacion_video');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
