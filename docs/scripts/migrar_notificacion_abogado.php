<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI.'); }
require dirname(__DIR__, 2) . '/bootstrap/app.php';
$pdo = App\Database\Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS citacion_notificacion_abogado (
    citacion_id INT NOT NULL,
    abogado_id INT NOT NULL,
    contenido_hash CHAR(64) NOT NULL,
    documento_nombre VARCHAR(255) NOT NULL,
    fecha_notificacion DATE NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (citacion_id, abogado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Registro de notificaciones a abogados disponible.\n";
