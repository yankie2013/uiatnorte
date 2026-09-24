-- Ejecutar solo en la base de desarrollo antes de probar las altas de catálogo.
-- Los registros ya existentes permanecerán sin autoría atribuida.
CREATE TABLE IF NOT EXISTS catalogo_aportaciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tabla VARCHAR(64) NOT NULL,
  registro_id BIGINT UNSIGNED NOT NULL,
  usuario_id INT NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalogo_aportacion_registro (tabla, registro_id),
  KEY ix_catalogo_aportacion_usuario (usuario_id),
  CONSTRAINT fk_catalogo_aportacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
