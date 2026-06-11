<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/app.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

$root = dirname(__DIR__, 2);
$docsDir = $root . '/docs';
$templatePaths = [];
foreach ([$root . '/plantillas', $root] as $dir) {
    foreach (glob($dir . '/*.docx') ?: [] as $path) {
        $templatePaths[realpath($path) ?: $path] = $path;
    }
}
ksort($templatePaths, SORT_NATURAL | SORT_FLAG_CASE);

$skipDirs = ['vendor', 'vendor_old', 'PHPWord-1.4.0', 'dompdf', 'google', 'storage', 'tmp', 'uploads', 'documentos_generados', '.git'];
$phpFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $first = explode('/', $relative)[0] ?? '';
    if (in_array($first, $skipDirs, true) || $relative === 'docs/scripts/generar_guia_marcadores_word.php') {
        continue;
    }
    $phpFiles[$relative] = file_get_contents($file->getPathname()) ?: '';
}

function marker_description(string $marker): string
{
    $parts = preg_split('/_+/', strtolower($marker)) ?: [$marker];
    $dictionary = [
        'acc' => 'accidente', 'doc' => 'documento', 'num' => 'numero', 'nro' => 'numero',
        'apep' => 'apellido paterno', 'apem' => 'apellido materno', 'cond' => 'conductor',
        'prop' => 'propietario', 'rep' => 'representante', 'veh' => 'vehiculo', 'ut1' => 'UT-1',
        'ut2' => 'UT-2', 'fam' => 'familiar', 'fal' => 'fallecido', 'efec' => 'efectivo',
        'abog' => 'abogado', 'obs' => 'observaciones', 'dom' => 'domicilio', 'fecnac' => 'fecha de nacimiento',
        'nacim' => 'fecha de nacimiento', 'cel' => 'celular', 'correo' => 'correo', 'email' => 'correo',
        'rml' => 'reconocimiento medico legal', 'lc' => 'licencia de conducir', 'occ' => 'occiso',
        'prot' => 'protocolo', 'lev' => 'levantamiento', 'manif' => 'manifestacion', 'oficio' => 'oficio',
        'sidpol' => 'SIDPOL', 'vin' => 'VIN', 'ruc' => 'RUC', 'dni' => 'DNI', 'cip' => 'CIP',
    ];
    $words = array_map(static fn(string $part): string => $dictionary[$part] ?? $part, $parts);
    return ucfirst(implode(' / ', $words));
}

function template_markers(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }
    $xml = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (!preg_match('#^word/(document|header\d*|footer\d*|footnotes|endnotes|comments)\.xml$#', $name)) {
            continue;
        }
        $xml .= (string) $zip->getFromIndex($i);
    }
    $zip->close();
    $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    preg_match_all('/\$\{([^}${]+)\}/u', $text, $matches);
    $markers = array_values(array_unique(array_map('trim', $matches[1] ?? [])));
    sort($markers, SORT_NATURAL | SORT_FLAG_CASE);
    return $markers;
}

function code_markers(string $source): array
{
    $markers = [];
    preg_match_all('/->setValue\s*\(\s*[\'"]([A-Za-z0-9_.:-]+)[\'"]/u', $source, $literal);
    $markers = array_merge($markers, $literal[1] ?? []);

    foreach (['values', 'vars', 'markers', 'marcadores', 'replacements'] as $arrayName) {
        if (!preg_match_all('/\$' . preg_quote($arrayName, '/') . '\s*=\s*\[(.*?)\];/su', $source, $blocks)) {
            continue;
        }
        foreach ($blocks[1] as $block) {
            preg_match_all('/[\'"]([A-Za-z0-9_.:-]+)[\'"]\s*=>/u', $block, $keys);
            $markers = array_merge($markers, $keys[1] ?? []);
        }
    }
    $markers = array_values(array_unique(array_filter($markers, static fn(string $v): bool => $v !== '')));
    sort($markers, SORT_NATURAL | SORT_FLAG_CASE);
    return $markers;
}

function related_generators(string $templateName, array $phpFiles): array
{
    $matches = [];
    foreach ($phpFiles as $relative => $source) {
        if (stripos($source, $templateName) !== false) {
            $matches[$relative] = code_markers($source);
        }
    }
    ksort($matches, SORT_NATURAL | SORT_FLAG_CASE);
    return $matches;
}

function marker_prefix_groups(array $markers): array
{
    $groups = [];
    foreach ($markers as $marker) {
        $prefix = explode('_', $marker, 2)[0] ?: 'otros';
        $groups[$prefix][] = $marker;
    }
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
    return $groups;
}

$inventory = [];
$allTemplateMarkers = [];
$allCodeMarkers = [];
foreach ($templatePaths as $path) {
    $name = basename($path);
    $markers = template_markers($path);
    $generators = related_generators($name, $phpFiles);
    $supported = [];
    foreach ($generators as $generatorMarkers) {
        $supported = array_merge($supported, $generatorMarkers);
    }
    $supported = array_values(array_unique($supported));
    sort($supported, SORT_NATURAL | SORT_FLAG_CASE);
    $availableNotPresent = array_values(array_diff($supported, $markers));
    $presentNotDetected = array_values(array_diff($markers, $supported));
    $inventory[] = [
        'template' => str_replace('\\', '/', substr($path, strlen($root) + 1)),
        'markers_present' => $markers,
        'generators' => array_keys($generators),
        'markers_supported_by_related_code' => $supported,
        'markers_available_not_present' => $availableNotPresent,
        'markers_present_not_detected_in_related_code' => $presentNotDetected,
    ];
    $allTemplateMarkers = array_merge($allTemplateMarkers, $markers);
    $allCodeMarkers = array_merge($allCodeMarkers, $supported);
}
$allTemplateMarkers = array_values(array_unique($allTemplateMarkers));
$allCodeMarkers = array_values(array_unique($allCodeMarkers));
sort($allTemplateMarkers, SORT_NATURAL | SORT_FLAG_CASE);
sort($allCodeMarkers, SORT_NATURAL | SORT_FLAG_CASE);
$actaMarkers = code_markers($phpFiles['acta_entrega_vehiculo_descargar.php'] ?? '');

$jsonPath = $docsDir . '/inventario_marcadores_word.json';
file_put_contents($jsonPath, json_encode([
    'generated_at' => date(DATE_ATOM),
    'summary' => [
        'templates' => count($inventory),
        'unique_markers_present' => count($allTemplateMarkers),
        'unique_markers_supported' => count($allCodeMarkers),
    ],
    'templates' => $inventory,
    'planned_templates' => [
        [
            'template' => 'plantillas/acta_entrega_vehiculo.docx',
            'status' => 'Pendiente de subir',
            'generator' => 'acta_entrega_vehiculo_descargar.php',
            'markers_supported' => $actaMarkers,
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$md = [];
$md[] = '# Guia de marcadores para plantillas Word';
$md[] = '';
$md[] = 'Generada: ' . date('d/m/Y H:i');
$md[] = '';
$md[] = '## Como usar los marcadores';
$md[] = '';
$md[] = '- Inserta cada marcador en Word con el formato `${nombre_marcador}`.';
$md[] = '- Conserva el marcador completo en una sola linea y con la misma escritura.';
$md[] = '- **Presente:** fue encontrado dentro de la plantilla DOCX.';
$md[] = '- **Disponible:** un generador PHP relacionado sabe completar el marcador, aunque no necesariamente este insertado en el DOCX.';
$md[] = '- Los marcadores dinamicos construidos por concatenacion pueden no aparecer como disponibles en este inventario automatico.';
$md[] = '';
$md[] = '## Resumen';
$md[] = '';
$md[] = '- Plantillas revisadas: ' . count($inventory);
$md[] = '- Marcadores unicos presentes: ' . count($allTemplateMarkers);
$md[] = '- Marcadores unicos detectados en generadores relacionados: ' . count($allCodeMarkers);
$md[] = '';
foreach ($inventory as $item) {
    $md[] = '## ' . $item['template'];
    $md[] = '';
    $md[] = '**Generadores relacionados:** ' . ($item['generators'] ? implode(', ', array_map(static fn($v) => '`' . $v . '`', $item['generators'])) : 'No detectado automaticamente');
    $md[] = '';
    $md[] = '**Marcadores presentes (' . count($item['markers_present']) . '):**';
    $md[] = '';
    foreach (marker_prefix_groups($item['markers_present']) as $prefix => $markers) {
        $md[] = '- `' . $prefix . '_*`: ' . implode(', ', array_map(static fn($v) => '`${' . $v . '}`', $markers));
    }
    if (!$item['markers_present']) {
        $md[] = '- Ninguno detectado.';
    }
    $md[] = '';
    $md[] = '**Disponibles en codigo pero no presentes (' . count($item['markers_available_not_present']) . '):**';
    $md[] = '';
    foreach (marker_prefix_groups($item['markers_available_not_present']) as $prefix => $markers) {
        $md[] = '- `' . $prefix . '_*`: ' . implode(', ', array_map(static fn($v) => '`${' . $v . '}`', $markers));
    }
    if (!$item['markers_available_not_present']) {
        $md[] = '- Ninguno detectado.';
    }
    $md[] = '';
}
$md[] = '## Plantilla pendiente: plantillas/acta_entrega_vehiculo.docx';
$md[] = '';
$md[] = '**Generador:** `acta_entrega_vehiculo_descargar.php`';
$md[] = '';
$md[] = '**Marcadores disponibles (' . count($actaMarkers) . '):**';
$md[] = '';
foreach (marker_prefix_groups($actaMarkers) as $prefix => $markers) {
    $md[] = '- `' . $prefix . '_*`: ' . implode(', ', array_map(static fn($v) => '`${' . $v . '}`', $markers));
}
$md[] = '';
$mdPath = $docsDir . '/GUIA_MARCADORES_WORD.md';
file_put_contents($mdPath, implode(PHP_EOL, $md) . PHP_EOL);

$phpWord = new PhpWord();
$phpWord->getSettings()->setThemeFontLang(new Language('es-PE'));
$phpWord->setDefaultFontName('Calibri');
$phpWord->setDefaultFontSize(9);
$section = $phpWord->addSection([
    'paperSize' => 'Letter',
    'marginTop' => Converter::inchToTwip(0.72),
    'marginRight' => Converter::inchToTwip(0.72),
    'marginBottom' => Converter::inchToTwip(0.72),
    'marginLeft' => Converter::inchToTwip(0.72),
]);
$phpWord->addTitleStyle(1, ['name' => 'Calibri', 'size' => 16, 'bold' => true, 'color' => '1F4D78'], ['spaceBefore' => 220, 'spaceAfter' => 100]);
$phpWord->addTitleStyle(2, ['name' => 'Calibri', 'size' => 12, 'bold' => true, 'color' => '2E74B5'], ['spaceBefore' => 160, 'spaceAfter' => 70]);
$phpWord->addTitleStyle(3, ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => '1F4D78'], ['spaceBefore' => 90, 'spaceAfter' => 40]);
$phpWord->addParagraphStyle('Body', ['spaceAfter' => 70, 'lineHeight' => 1.15]);
$phpWord->addParagraphStyle('Marker', ['spaceAfter' => 25, 'lineHeight' => 1.05]);
$phpWord->addFontStyle('MarkerFont', ['name' => 'Consolas', 'size' => 8, 'color' => '7A3E00']);

$header = $section->addHeader();
$header->addText('UIAT Norte | Guia de marcadores Word', ['size' => 8, 'color' => '667085']);
$footer = $section->addFooter();
$footer->addPreserveText('Pagina {PAGE} de {NUMPAGES}', ['size' => 8, 'color' => '667085'], ['alignment' => Jc::END]);

$section->addText('GUIA DE MARCADORES PARA PLANTILLAS WORD', ['size' => 22, 'bold' => true, 'color' => '17365D'], ['spaceAfter' => 80]);
$section->addText('Inventario de marcadores presentes y disponibles en los generadores del sistema UIAT Norte', ['size' => 11, 'color' => '475467'], ['spaceAfter' => 180]);
$summary = $section->addTable(['borderSize' => 5, 'borderColor' => 'D0D5DD', 'cellMargin' => 90]);
foreach ([
    ['Plantillas revisadas', count($inventory)],
    ['Marcadores unicos presentes', count($allTemplateMarkers)],
    ['Marcadores unicos soportados detectados', count($allCodeMarkers)],
    ['Fecha de generacion', date('d/m/Y H:i')],
] as $line) {
    $summary->addRow();
    $summary->addCell(3400, ['bgColor' => 'E8EEF5'])->addText((string) $line[0], ['bold' => true, 'size' => 9]);
    $summary->addCell(5600)->addText((string) $line[1], ['size' => 9]);
}

$section->addTitle('Como usar esta guia', 1);
foreach ([
    'Inserta los marcadores en Word con el formato ${nombre_marcador}.',
    'Presente significa que el marcador fue encontrado dentro del DOCX.',
    'Disponible significa que un generador PHP relacionado sabe completarlo, aunque aun no este insertado en la plantilla.',
    'No cambies mayusculas, guiones bajos ni el nombre del marcador.',
    'Los marcadores dinamicos construidos por concatenacion pueden requerir revision manual.',
] as $text) {
    $section->addListItem($text, 0, ['size' => 9], 'Body');
}

$section->addTitle('Indice de plantillas', 1);
$indexTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'D0D5DD', 'cellMargin' => 70]);
$indexTable->addRow();
foreach ([['Plantilla', 5000], ['Presentes', 1300], ['Disponibles extra', 1800], ['Generadores', 1600]] as [$label, $width]) {
    $indexTable->addCell($width, ['bgColor' => 'D9EAF7'])->addText($label, ['bold' => true, 'size' => 8]);
}
foreach ($inventory as $item) {
    $indexTable->addRow();
    $indexTable->addCell(5000)->addText($item['template'], ['size' => 7.5]);
    $indexTable->addCell(1300)->addText((string) count($item['markers_present']), ['size' => 7.5]);
    $indexTable->addCell(1800)->addText((string) count($item['markers_available_not_present']), ['size' => 7.5]);
    $indexTable->addCell(1600)->addText((string) count($item['generators']), ['size' => 7.5]);
}

foreach ($inventory as $item) {
    $section->addPageBreak();
    $section->addTitle($item['template'], 1);
    $section->addText(
        'Generadores relacionados: ' . ($item['generators'] ? implode(', ', $item['generators']) : 'No detectado automaticamente'),
        ['size' => 8.5, 'italic' => true, 'color' => '475467'],
        'Body'
    );
    foreach ([
        'Marcadores presentes en la plantilla' => $item['markers_present'],
        'Marcadores disponibles en codigo pero no presentes' => $item['markers_available_not_present'],
    ] as $heading => $markers) {
        $section->addTitle($heading . ' (' . count($markers) . ')', 2);
        if (!$markers) {
            $section->addText('Ninguno detectado.', ['italic' => true, 'color' => '667085'], 'Body');
            continue;
        }
        foreach (marker_prefix_groups($markers) as $prefix => $groupMarkers) {
            $section->addTitle($prefix . '_*', 3);
            $table = $section->addTable(['borderSize' => 3, 'borderColor' => 'D0D5DD', 'cellMargin' => 55]);
            $table->addRow();
            $table->addCell(3900, ['bgColor' => 'EEF3F8'])->addText('Marcador', ['bold' => true, 'size' => 8]);
            $table->addCell(5700, ['bgColor' => 'EEF3F8'])->addText('Descripcion orientativa', ['bold' => true, 'size' => 8]);
            foreach ($groupMarkers as $marker) {
                $table->addRow();
                $table->addCell(3900)->addText('${' . $marker . '}', 'MarkerFont', 'Marker');
                $table->addCell(5700)->addText(marker_description($marker), ['size' => 7.5], 'Marker');
            }
        }
    }
}

$section->addPageBreak();
$section->addTitle('Marcadores de Acta de entrega de vehiculo', 1);
$section->addText('Esta plantilla aun no existe en el repositorio. Los siguientes marcadores ya estan soportados por acta_entrega_vehiculo_descargar.php y pueden insertarse en acta_entrega_vehiculo.docx.', ['size' => 9], 'Body');
foreach (marker_prefix_groups($actaMarkers) as $prefix => $markers) {
    $section->addTitle($prefix . '_*', 3);
    $table = $section->addTable(['borderSize' => 3, 'borderColor' => 'D0D5DD', 'cellMargin' => 55]);
    foreach ($markers as $marker) {
        $table->addRow();
        $table->addCell(3900)->addText('${' . $marker . '}', 'MarkerFont', 'Marker');
        $table->addCell(5700)->addText(marker_description($marker), ['size' => 7.5], 'Marker');
    }
}

$docxPath = $docsDir . '/Guia_Marcadores_Plantillas_Word_Actualizada.docx';
$writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($docxPath);

echo "Generados:\n- {$docxPath}\n- {$mdPath}\n- {$jsonPath}\n";
