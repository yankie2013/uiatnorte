<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI.'); }
require dirname(__DIR__, 2) . '/bootstrap/app.php';
$pdo = App\Database\Database::connection();
$st = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citacion' AND COLUMN_NAME = 'estado_realizacion'");
if (!(int) $st->fetchColumn()) {
    $pdo->exec("ALTER TABLE citacion ADD COLUMN estado_realizacion ENUM('pendiente','realizada','no-realizada') NULL DEFAULT NULL");
}
echo "Estado de citaciones disponible.\n";
