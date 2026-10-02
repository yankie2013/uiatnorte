CREATE TABLE IF NOT EXISTS actas_recepcion_video (
  id INT NOT NULL AUTO_INCREMENT,
  accidente_id INT NOT NULL,
  datos_json LONGTEXT NOT NULL,
  creado_por INT NOT NULL,
  actualizado_por INT NOT NULL,
  creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_actas_recepcion_video_accidente (accidente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
