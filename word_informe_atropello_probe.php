<?php
header('Content-Type: text/plain; charset=utf-8');

$autoloadMain = __DIR__.'/vendor/autoload.php';
$autoloadFallback = __DIR__.'/PHPWord-1.4.0/vendor/autoload.php';
$tpl = __DIR__.'/plantillas/informe_atropello.docx';
$tmpDir = __DIR__.'/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }

$lines = [];
$lines[] = '=== PROBE INFORME ATROPELLO ===';
$lines[] = 'PHP_VERSION=' . PHP_VERSION;
$lines[] = '__DIR__=' . __DIR__;
$lines[] = 'vendor/autoload.php=' . (is_file($autoloadMain) ? 'OK' : 'MISSING');
$lines[] = 'PHPWord fallback autoload=' . (is_file($autoloadFallback) ? 'OK' : 'MISSING');
$lines[] = 'plantillas/informe_atropello.docx=' . (is_file($tpl) ? 'OK' : 'MISSING');
$lines[] = 'tmp writable=' . (is_writable($tmpDir) ? 'YES' : 'NO');

try {
    if (is_file($autoloadMain)) { require $autoloadMain; }
    if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && is_file($autoloadFallback)) {
        require $autoloadFallback;
    }
    $lines[] = 'TemplateProcessor=' . (class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) ? 'OK' : 'MISSING');
} catch (Throwable $e) {
    $lines[] = 'AUTOLOAD_FAIL=' . $e->getMessage();
}

try {
    require __DIR__ . '/db.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('SET NAMES utf8mb4');
    $pdo->query('SELECT 1');
    $lines[] = 'DB=OK';
} catch (Throwable $e) {
    $lines[] = 'DB=FAIL -> ' . $e->getMessage();
}

$accidenteId = isset($_GET['accidente_id']) ? (int) $_GET['accidente_id'] : (int) ($_GET['id'] ?? 0);
if ($accidenteId > 0 && isset($pdo)) {
    try {
        $st = $pdo->prepare('SELECT id, sidpol, fecha_accidente FROM accidentes WHERE id=? LIMIT 1');
        $st->execute([$accidenteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $lines[] = $row ? ('ACCIDENTE=' . $accidenteId . ' FOUND sidpol=' . ($row['sidpol'] ?? '')) : ('ACCIDENTE=' . $accidenteId . ' NOT FOUND');
    } catch (Throwable $e) {
        $lines[] = 'ACCIDENTE_QUERY_FAIL=' . $e->getMessage();
    }
}

$lines[] = 'DONE';
echo implode("\n", $lines);
