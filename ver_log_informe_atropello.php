<?php
header('Content-Type: text/plain; charset=utf-8');

$log = __DIR__.'/logs/informe_atropello_error.log';
$maxLines = isset($_GET['lines']) ? max(1, min(300, (int) $_GET['lines'])) : 80;

if (!is_file($log)) {
    echo "No existe: $log\n";
    exit;
}

$lines = @file($log, FILE_IGNORE_NEW_LINES);
if (!is_array($lines)) {
    echo "No se pudo leer el log.\n";
    exit;
}

$tail = array_slice($lines, -$maxLines);
echo '=== TAIL informe_atropello_error.log ===' . "\n";
echo 'Lineas mostradas: ' . count($tail) . "\n\n";
echo implode("\n", $tail);
