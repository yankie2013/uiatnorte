<?php
declare(strict_types=1);
// CLI-only, idempotent catalog/schema migration; does not create any oficio.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/bootstrap/app.php';
$pdo = App\Database\Database::connection();
$pdo->exec('SET @rbac_migration=1');
try {
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/sql/2026-10-20_oficio_defuncion_reniec.sql'));
    echo "Migración de inscripción de defunción RENIEC aplicada.\n";
} finally {
    $pdo->exec('SET @rbac_migration=0');
}
