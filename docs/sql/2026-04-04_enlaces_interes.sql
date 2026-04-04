CREATE TABLE IF NOT EXISTS enlace_interes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria VARCHAR(50) NOT NULL DEFAULT 'OTROS',
  nombre VARCHAR(150) NOT NULL,
  url VARCHAR(500) NOT NULL,
  descripcion TEXT NULL,
  orden INT NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_enlace_interes_categoria (categoria),
  KEY idx_enlace_interes_activo (activo),
  KEY idx_enlace_interes_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
