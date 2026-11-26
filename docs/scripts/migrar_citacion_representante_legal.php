<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../bootstrap/app.php';

$pdo = \App\Database\Database::connection();
$column = $pdo->query("SHOW FULL COLUMNS FROM citacion LIKE 'en_calidad'")->fetch(PDO::FETCH_ASSOC);
if (!$column) throw new RuntimeException('No existe citacion.en_calidad.');
$type = (string) $column['Type'];
if (!str_starts_with(strtolower($type), 'enum(')) {
    throw new RuntimeException('Tipo de columna inesperado. Revisar antes de modificar.');
}
$value = $pdo->quote('Representante legal');
if (str_contains($type, $value)) { echo "Representante legal ya está habilitado.\n"; exit; }
// Append the new value, preserving all existing values and their enum indexes.
$newType = substr($type, 0, -1) . ',' . $value . ')';
$collation = (string) $column['Collation'];
if (!preg_match('/^[a-zA-Z0-9_]+$/', $collation)) throw new RuntimeException('Collation inesperada.');
$sql = "ALTER TABLE citacion MODIFY COLUMN en_calidad $newType COLLATE $collation";
$sql .= $column['Null'] === 'YES' ? ' NULL' : ' NOT NULL';
if ($column['Default'] !== null) $sql .= ' DEFAULT ' . $pdo->quote($column['Default']);
$sql .= ' COMMENT ' . $pdo->quote((string) $column['Comment']);
if (!in_array('--apply', $argv, true)) {
    echo "Pendiente: habilitar Representante legal en citacion.en_calidad. Ejecuta con --apply.\n";
    exit;
}
$pdo->exec($sql);
echo "Representante legal habilitado sin cambiar los valores existentes.\n";
