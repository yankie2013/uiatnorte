<?php
require_once __DIR__ . '/../bootstrap/app.php';
use App\Support\AccidentPlace;

$expect = static function ($actual, $expected): void {
    if ($actual !== $expected) throw new RuntimeException('Lugar del accidente incorrecto: ' . var_export([$actual, $expected], true));
};
$expect(AccidentPlace::format('Carretera Panamericana Norte 19+869', 'Los Olivos'), 'Carretera Panamericana Norte 19+869, distrito de Los Olivos');
$expect(AccidentPlace::format('Av. Perú, distrito de LOS OLIVOS', 'Los Olivos'), 'Av. Perú, distrito de LOS OLIVOS');
$expect(AccidentPlace::format('Av. Olivos', 'Los Olivos'), 'Av. Olivos, distrito de Los Olivos');
$expect(AccidentPlace::format('Av. Perú', ''), 'Av. Perú');
$expect(AccidentPlace::format('', 'Ancón'), 'Distrito de Ancón');
$record = ['lugar' => 'Av. Perú', 'distrito_nombre' => 'Los Olivos'];
$expect(AccidentPlace::fromRecord($record), 'Av. Perú, distrito de Los Olivos');
$expect($record['lugar'], 'Av. Perú'); // Los formularios siguen editando la dirección original.
$expect(AccidentPlace::fromRecord(['acc_lugar' => 'Av. Perú', 'accidente_distrito' => 'Los Olivos'], 'acc_lugar'), 'Av. Perú, distrito de Los Olivos');

// Comprueba que la misma salida llega intacta al contenido de un DOCX.
if (class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
    $file = tempnam(sys_get_temp_dir(), 'uiat-place-');
    try {
        $word = new \PhpOffice\PhpWord\PhpWord();
        $word->addSection()->addText(AccidentPlace::fromRecord($record));
        \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($file);
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) throw new RuntimeException('No se pudo inspeccionar Word.');
        $xml = $zip->getFromName('word/document.xml'); $zip->close();
        if (!str_contains((string) $xml, 'distrito de Los Olivos')) throw new RuntimeException('Distrito ausente en Word.');
    } finally {unlink($file);}
}
echo "OK: lugar con distrito, sin duplicación, dirección original conservada y salida Word.\n";
