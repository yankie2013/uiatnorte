<?php
$path = __DIR__ . '/plantillas/notificacion_abogado.docx';
$tmp = __DIR__ . '/plantillas/notificacion_abogado.fixutf8.tmp.docx';
$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "No se pudo abrir la plantilla\n");
    exit(1);
}
$doc = $zip->getFromName('word/document.xml');
if (!is_string($doc) || $doc === '') {
    fwrite(STDERR, "No se pudo leer document.xml\n");
    exit(1);
}
$doc = str_replace('programado para el día', 'programado para el dia', $doc, $count);
if ($count < 1) {
    fwrite(STDERR, "No se encontró el texto a corregir\n");
    exit(1);
}
@unlink($tmp);
$out = new ZipArchive();
if ($out->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "No se pudo crear el docx temporal\n");
    exit(1);
}
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    $data = $zip->getFromIndex($i);
    if ($name === false || $data === false) continue;
    $normalized = str_replace('\\', '/', ltrim($name, '/\\'));
    if ($normalized === 'word/document.xml') {
        $data = $doc;
    }
    $out->addFromString($normalized, $data);
}
$zip->close();
$out->close();
if (!unlink($path)) {
    fwrite(STDERR, "No se pudo reemplazar plantilla\n");
    exit(1);
}
if (!rename($tmp, $path)) {
    fwrite(STDERR, "No se pudo mover plantilla corregida\n");
    exit(1);
}
echo "fixed-template-utf8\n";
