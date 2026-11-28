<?php
declare(strict_types=1);
// Importa los modelos del usuario sin conservar textos, fotografías ni firmas de ejemplo.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = $argv[1] ?? '';
if (!is_dir($source)) exit("Uso: php docs/scripts/preparar_plantillas_analisis.php /ruta/a/modelos\n");
$target = dirname(__DIR__, 2) . '/plantillas/analisis';
if (!is_dir($target)) mkdir($target, 0775, true);
$config = [
    'descripcion' => [6, [
        'titulo'=>0,'subtitulo'=>1,'intro'=>2,'apartado'=>4,'texto'=>5,
        'via_titulo'=>16,'via_texto'=>17,'lista'=>11,'lista_final'=>13,
        'atributo_configuracion'=>19,'atributo_material'=>20,'atributo_control'=>21,
        'atributo_ordenamiento'=>22,'atributo_maniobrabilidad'=>23,'atributo_iluminacion'=>24,
        'atributo_visibilidad'=>25,'atributo_intensidad'=>26,'atributo_fluidez'=>27,'pie'=>30,
        'clima_titulo'=>48,'clima_texto'=>49,'conflicto_titulo'=>51,'conflicto_subtitulo'=>52,
        'conflicto_lista'=>53,'conflicto_lista_final'=>56,'aproximacion_subtitulo'=>58,
        'aproximacion_titulo'=>60,'aproximacion_texto'=>61,'posiciones_titulo'=>66,
        'posicion_titulo'=>67,'posicion_texto'=>68,
    ]],
    'velocidad' => [7, ['titulo'=>0,'intro'=>1,'lista'=>3,'lista_final'=>9,'subtitulo'=>11,'texto'=>12]],
    'danos-lesiones' => [8, ['titulo'=>0,'subtitulo'=>2,'lista'=>4,'lista_final'=>7,'texto'=>9,'determinacion_titulo'=>13,'determinacion'=>15]],
    'evidencias' => [9, ['titulo'=>0,'subtitulo'=>2,'texto'=>3,'lista'=>5,'lista_final'=>8]],
    'integral' => [10, ['titulo'=>0,'texto'=>1]],
    'causalidad' => [11, ['titulo'=>0,'subtitulo'=>1,'apartado'=>2,'texto'=>3]],
    'infracciones' => [12, ['titulo'=>0,'subtitulo'=>1,'intro'=>2,'apartado'=>5,'texto'=>6,'adicional'=>41,'adicional_texto'=>42,'anexos'=>44,'anexo'=>45]],
];
$manifest = [];
foreach ($config as $section => [$number, $roles]) {
    $matches = glob(rtrim($source, '/') . '/' . $number . ' *.docx');
    if (count($matches) !== 1) throw new RuntimeException("Modelo no encontrado o ambiguo: $number");
    $original = $matches[0];
    $file = "$target/$section.docx";
    copy($original, $file);
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) throw new RuntimeException('No se pudo abrir el modelo.');
    $dom = new DOMDocument(); $dom->loadXML($zip->getFromName('word/document.xml'), LIBXML_NONET);
    $xp = new DOMXPath($dom); $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'; $xp->registerNamespace('w', $w);
    $body = $xp->query('/w:document/w:body')->item(0);
    $paragraphs = iterator_to_array($xp->query('w:p', $body));
    $sectionProperties = $xp->query('w:sectPr', $body)->item(0)->cloneNode(true);
    $patterns = [];
    foreach ($roles as $role => $index) {
        $p = $dom->createElementNS($w, 'w:p');
        $props = $xp->query('w:pPr', $paragraphs[$index])->item(0);
        if ($props) $p->appendChild($props->cloneNode(true));
        $run = $dom->createElementNS($w, 'w:r');
        $props = $xp->query('w:r/w:rPr', $paragraphs[$index])->item(0);
        if (!$props) $props = $xp->query('w:pPr/w:rPr', $paragraphs[$index])->item(0);
        if ($props) $run->appendChild($props->cloneNode(true));
        $text = $dom->createElementNS($w, 'w:t');
        // This static introduction is part of the format and must not be rewritten.
        $value = '${' . $role . '}';
        if ($section === 'velocidad' && $role === 'intro') {
            $value = ''; foreach ($xp->query('.//w:t', $paragraphs[$index]) as $node) $value .= $node->textContent;
        }
        $text->appendChild($dom->createTextNode($value)); $run->appendChild($text); $p->appendChild($run);
        $sdt = $dom->createElementNS($w, 'w:sdt'); $pr = $dom->createElementNS($w, 'w:sdtPr');
        $tag = $dom->createElementNS($w, 'w:tag'); $tag->setAttributeNS($w, 'w:val', $role); $pr->appendChild($tag); $sdt->appendChild($pr);
        $content = $dom->createElementNS($w, 'w:sdtContent'); $content->appendChild($p);
        // Blank paragraphs are intentional spacing in the reference, not disposable content.
        for ($next=$index+1; isset($paragraphs[$next]); $next++) {
            $blank=$paragraphs[$next];
            if (trim($blank->textContent)!=='' || $xp->query('.//w:drawing|.//w:pict|w:pPr/w:sectPr|w:pPr/w:numPr|w:pPr/w:pBdr', $blank)->length) break;
            $blankStyle=$xp->evaluate('string(w:pPr/w:pStyle/@w:val)', $blank);
            if (str_starts_with($blankStyle, 'Ttulo')) break;
            $content->appendChild($blank->cloneNode(true));
        }
        $sdt->appendChild($content); $patterns[] = $sdt;
    }
    while ($body->firstChild) $body->removeChild($body->firstChild);
    foreach ($patterns as $pattern) $body->appendChild($pattern);
    $body->appendChild($sectionProperties);
    $zip->addFromString('word/document.xml', $dom->saveXML());
    // Remove body pictures, OLE objects and comments from the sample; retain header watermarks.
    $rels = new DOMDocument(); $rels->loadXML($zip->getFromName('word/_rels/document.xml.rels'), LIBXML_NONET);
    $remove = [];
    foreach (iterator_to_array($rels->documentElement->childNodes) as $rel) {
        if (!$rel instanceof DOMElement) continue;
        if (preg_match('~/(image|oleObject|package|comments|commentsExtended|people)$~', $rel->getAttribute('Type'))) {
            if ($rel->getAttribute('TargetMode') !== 'External') $remove[] = 'word/' . ltrim($rel->getAttribute('Target'), '/');
            $rels->documentElement->removeChild($rel);
        }
    }
    $zip->addFromString('word/_rels/document.xml.rels', $rels->saveXML());
    $usedByFurniture = '';
    for ($i=0; $i<$zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('~word/_rels/(header|footer).*\.rels$~', $name)) $usedByFurniture .= $zip->getFromIndex($i);
    }
    foreach ($remove as $name) if (!str_contains($usedByFurniture, basename($name))) $zip->deleteName($name);
    // Avoid carrying the sample author's metadata and thumbnail into downloaded reports.
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Análisis del informe</dc:title><dc:creator>UIAT Norte</dc:creator></cp:coreProperties>');
    if ($zip->locateName('docProps/thumbnail.jpeg') !== false) $zip->deleteName('docProps/thumbnail.jpeg');
    // Remove relationships and content-type overrides pointing at removed parts.
    foreach (['_rels/.rels', '[Content_Types].xml'] as $part) {
        $xml = new DOMDocument(); $xml->loadXML($zip->getFromName($part), LIBXML_NONET);
        foreach (iterator_to_array($xml->documentElement->childNodes) as $entry) {
            if (!$entry instanceof DOMElement) continue;
            $path = ltrim($entry->getAttribute($part === '_rels/.rels' ? 'Target' : 'PartName'), '/');
            if ($path !== '' && $zip->locateName($path) === false) $xml->documentElement->removeChild($entry);
        }
        $zip->addFromString($part, $xml->saveXML());
    }
    $zip->close();
    $originalZip = new ZipArchive(); $originalZip->open($original);
    $manifest[$section] = ['modelo'=>basename($original),'sha256'=>hash_file('sha256',$original),
        'styles_sha256'=>hash('sha256',$originalZip->getFromName('word/styles.xml')),
        'numbering_sha256'=>hash('sha256',$originalZip->getFromName('word/numbering.xml')),
        'roles'=>$roles];
    $originalZip->close();
    echo "$section: modelo preparado.\n";
}
file_put_contents($target . '/modelos.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
