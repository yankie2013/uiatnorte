<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options=getopt('', ['database:', 'apply']);
require __DIR__.'/../../db.php';
$database=$pdo->query('SELECT DATABASE()')->fetchColumn();
if (($options['database']??'')!==$database) { fwrite(STDERR,"Confirme --database=NOMBRE_REAL.\n"); exit(2); }
if (!isset($options['apply'])) { echo "PLAN: conservar últimos expedientes abiertos por usuario. Use --apply.\n"; exit; }
$pdo->exec('CREATE TABLE IF NOT EXISTS usuario_accidentes_recientes (usuario_id INT NOT NULL, accidente_id INT NOT NULL, abierto_en DATETIME(6) NOT NULL, PRIMARY KEY(usuario_id,accidente_id), INDEX idx_recientes(usuario_id,abierto_en)) ENGINE=InnoDB');
echo "Historial persistente de expedientes disponible.\n";
