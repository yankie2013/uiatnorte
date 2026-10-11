<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options=getopt('', ['database:','apply']);
require __DIR__.'/../../db.php';
if (($options['database'] ?? '') !== $pdo->query('SELECT DATABASE()')->fetchColumn()) { fwrite(STDERR,"Confirme --database=NOMBRE_REAL.\n");exit(2); }
if (!isset($options['apply'])) { echo "PLAN: agregar datos de recepción de videos presentados por las partes.\n";exit; }
foreach (['fecha_recibido'=>'DATE NULL','tipo_documento_recibido'=>'VARCHAR(30) NULL','presentado_por'=>'VARCHAR(255) NULL'] as $name=>$type) {
    if (!$pdo->query("SHOW COLUMNS FROM actas_visualizacion_discos LIKE '$name'")->fetch()) $pdo->exec("ALTER TABLE actas_visualizacion_discos ADD COLUMN `$name` $type");
}
echo "Datos de presentaciones directas disponibles; registros existentes conservados.\n";
