<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$options=getopt('', ['database:', 'apply']);
require __DIR__.'/../../db.php';
$database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if(($options['database']??'')!==$database){fwrite(STDERR,"Use --database=NOMBRE_REAL para confirmar la base de destino.\n");exit(2);}
if(!isset($options['apply'])){echo "Destino: $database. PLAN: catálogo de hospitales y lugar de fallecimiento por participante. Sin cambios.\n";exit;}
$pdo->exec("CREATE TABLE IF NOT EXISTS guardia_hospitales(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,nombre VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,creado_por INT NULL,creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS guardia_persona_fallecimiento(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,accidente_id INT NOT NULL,involucrado_persona_id INT NOT NULL UNIQUE,tipo ENUM('lugar_hechos','hospital') NOT NULL,hospital_id INT NULL,observaciones TEXT NULL,creado_por INT NULL,creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT guardia_fall_acc FOREIGN KEY(accidente_id) REFERENCES accidentes(id),CONSTRAINT guardia_fall_ip FOREIGN KEY(involucrado_persona_id) REFERENCES involucrados_personas(id),CONSTRAINT guardia_fall_hospital FOREIGN KEY(hospital_id) REFERENCES guardia_hospitales(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
// Reutiliza el generador institucional para instalar permisos y auditoría.
// Sus guardas específicas se conservan también en futuras reinstalaciones.
$p=$pdo;require __DIR__.'/multiusuario_triggers.php';
echo "Catálogo de hospitales y documentos de fallecimiento instalados.\n";
