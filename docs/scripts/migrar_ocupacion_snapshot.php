<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['database:', 'apply']);
require __DIR__.'/../../db.php';
$database = $pdo->query('SELECT DATABASE()')->fetchColumn();
if (($options['database'] ?? '') !== $database) { fwrite(STDERR,"Confirme --database=NOMBRE_REAL.\n"); exit(2); }
if ($pdo->query("SHOW COLUMNS FROM involucrados_personas LIKE 'ocupacion_snapshot'")->fetch()) { echo "Ocupación disponible.\n"; exit; }
if (!isset($options['apply'])) { echo "PLAN: agregar ocupación por accidente. Use --apply.\n"; exit; }
$pdo->exec('ALTER TABLE involucrados_personas ADD COLUMN ocupacion_snapshot VARCHAR(255) NULL');
echo "Ocupación por accidente instalada.\n";
