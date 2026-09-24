<?php
/** Read-only production schema report. No migrations or record updates. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/bootstrap/app.php';

try {
    $pdo = \App\Database\Database::connection();
    echo "DIAGNÓSTICO MULTIUSUARIO — SOLO LECTURA\n";
    echo "Base: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
    $source = file_get_contents(dirname(__DIR__) . '/sql/preflight_esquema_multiusuario.sql');
    if ($source === false) throw new RuntimeException('No se pudo leer el preflight.');
    $sql = preg_replace('/^\s*--.*$/m', '', $source);
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') continue;
        if (!preg_match('/^SELECT\b/i', $statement)) throw new RuntimeException('El diagnóstico admite únicamente SELECT.');
        $rows = $pdo->query($statement)->fetchAll(PDO::FETCH_ASSOC);
        echo "\n";
        if (!$rows) { echo "Sin objetos encontrados en esta comprobación.\n"; continue; }
        echo implode(' | ', array_keys($rows[0])) . "\n";
        foreach ($rows as $row) echo implode(' | ', array_map(static fn($v) => (string) $v, $row)) . "\n";
    }
    echo "\nCOLUMNAS AUSENTES EN VISTAS EXISTENTES\n";
    $st = $pdo->query("SELECT b.table_name AS tabla, CONCAT(b.table_name, '_activos') AS vista, b.column_name AS columna
        FROM information_schema.columns b
        JOIN information_schema.views v ON v.table_schema = b.table_schema AND v.table_name = CONCAT(b.table_name, '_activos')
        LEFT JOIN information_schema.columns c ON c.table_schema = b.table_schema AND c.table_name = v.table_name AND c.column_name = b.column_name
        WHERE b.table_schema = DATABASE() AND c.column_name IS NULL
        ORDER BY b.table_name, b.ordinal_position");
    $missing = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($missing as $row) echo implode(' | ', $row) . "\n";
    if (!$missing) echo "Ninguna.\n";
    echo "\nFUNCIONES DE PERMISOS\n";
    $installed = $pdo->query('SELECT routine_name FROM information_schema.routines WHERE routine_schema = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['rbac_admin', 'rbac_case', 'rbac_person', 'rbac_vehicle'] as $routine) {
        echo $routine . ' | ' . (in_array($routine, $installed, true) ? 'OK' : 'FALTA') . "\n";
    }
    echo "\nTerminado. No se modificaron tablas, vistas, funciones ni registros.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Diagnóstico incompleto: " . $error->getMessage() . "\n");
    exit(1);
}
