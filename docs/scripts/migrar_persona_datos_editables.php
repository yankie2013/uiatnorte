<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$options=getopt('', ['database:', 'apply']);
require __DIR__.'/../../db.php';
$database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if(($options['database']??'')!==$database){fwrite(STDERR,"Use --database=NOMBRE_REAL para confirmar la base de destino.\n");exit(2);}
if(!isset($options['apply'])){echo "Destino: $database. PLAN: permitir completar identidad vacía y actualizar datos variables sin reescribir copias históricas. Sin cambios.\n";exit;}
require __DIR__.'/persona_identidad_trigger.php';
echo "Regla de datos personales instalada en $database. No se modificaron personas ni copias históricas.\n";
