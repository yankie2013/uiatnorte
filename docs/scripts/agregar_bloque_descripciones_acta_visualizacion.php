<?php
declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/plantillas/acta_visualizacion_video.docx';
$zip = new ZipArchive();
if ($zip->open($path) !== true) exit("No se pudo abrir {$path}\n");
$xml = $zip->getFromName('word/document.xml');
if ($xml === false) exit("No se encontro word/document.xml\n");

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = false;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
$target = null;
$oldBlock = [];
$insideBlock = false;
foreach ($xpath->query('//w:p') as $paragraph) {
    $text = '';
    foreach ($xpath->query('.//w:t', $paragraph) as $node) $text .= $node->textContent;
    if (str_contains($text, '${DESCRIPCIONES_VIDEO}')) $insideBlock = true;
    if ($insideBlock) $oldBlock[] = $paragraph;
    if (str_contains($text, '${/DESCRIPCIONES_VIDEO}')) $insideBlock = false;
    if (!$target && str_contains($text, 'diligencia_archivos_detalle')) {
        $target = $paragraph;
    }
}
$parent = $target?->parentNode ?? ($oldBlock[0]->parentNode ?? null);
$reference = $target?->nextSibling ?? ($oldBlock ? end($oldBlock)->nextSibling : null);
if (!$parent) exit("No se encontro el bloque de descripciones ni su punto de insercion\n");
foreach ($oldBlock as $paragraphToRemove) {
    $paragraphToRemove->parentNode->removeChild($paragraphToRemove);
}

$wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
$paragraph = static function (string $text, bool $bold = false) use ($dom, $wordNamespace): DOMElement {
    $p = $dom->createElementNS($wordNamespace, 'w:p');
    $pPr = $dom->createElementNS($wordNamespace, 'w:pPr');
    $pPr->appendChild($dom->createElementNS($wordNamespace, 'w:jc'))->setAttributeNS($wordNamespace, 'w:val', 'both');
    $p->appendChild($pPr);
    $r = $dom->createElementNS($wordNamespace, 'w:r');
    $rPr = $dom->createElementNS($wordNamespace, 'w:rPr');
    $fonts = $dom->createElementNS($wordNamespace, 'w:rFonts');
    $fonts->setAttributeNS($wordNamespace, 'w:ascii', 'Arial');
    $fonts->setAttributeNS($wordNamespace, 'w:hAnsi', 'Arial');
    $rPr->appendChild($fonts);
    if ($bold) $rPr->appendChild($dom->createElementNS($wordNamespace, 'w:b'));
    $r->appendChild($rPr);
    $t = $dom->createElementNS($wordNamespace, 'w:t');
    $t->appendChild($dom->createTextNode($text));
    $r->appendChild($t);
    $p->appendChild($r);
    return $p;
};

$nodes = [
    $paragraph('${DESCRIPCIONES_VIDEO}'),
    $paragraph('${disco_encabezado}', true),
    $paragraph('${archivo_encabezado}', true),
    $paragraph('${descripcion_tiempo}', true),
    $paragraph('${descripcion_detalle}'),
    $paragraph('${descripcion_captura}'),
    $paragraph(''),
    $paragraph('${/DESCRIPCIONES_VIDEO}'),
];
foreach ($nodes as $node) {
    $parent->insertBefore($node, $reference);
}

$zip->addFromString('word/document.xml', $dom->saveXML());
$zip->close();
echo "Bloque agregado en {$path}\n";
