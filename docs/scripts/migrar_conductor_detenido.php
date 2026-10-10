<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['database:', 'apply']);
require __DIR__.'/../../db.php';
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if (($options['database'] ?? '') !== $database) { fwrite(STDERR, "Confirme --database=NOMBRE_REAL.\n"); exit(2); }
$check = $pdo->query("SHOW COLUMNS FROM involucrados_personas LIKE 'detenido'")->fetch();
if ($check) { echo "Campo detenido ya disponible.\n"; exit; }
if (!isset($options['apply'])) { echo "PLAN: agregar detenido nullable a la participación. Ejecute con --apply.\n"; exit; }
$pdo->exec('ALTER TABLE involucrados_personas ADD COLUMN detenido TINYINT(1) NULL DEFAULT NULL');
echo "Campo detenido instalado; registros existentes conservados sin información.\n";
