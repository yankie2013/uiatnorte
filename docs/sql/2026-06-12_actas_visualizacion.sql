CREATE TABLE IF NOT EXISTS actas_visualizacion (
  id INT NOT NULL AUTO_INCREMENT,
  accidente_id INT NOT NULL,
  fecha_visualizacion DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  observaciones TEXT NULL,
  estado ENUM('Pendiente','Realizada','Anulada') NOT NULL DEFAULT 'Pendiente',
  creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_acta_visualizacion_accidente (accidente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actas_visualizacion_participantes (
  id INT NOT NULL AUTO_INCREMENT,
  acta_visualizacion_id INT NOT NULL,
  fuente VARCHAR(30) NOT NULL,
  fuente_id INT NOT NULL,
  nombre VARCHAR(255) NOT NULL,
  condicion VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_acta_visualizacion_participante (acta_visualizacion_id, fuente, fuente_id),
  KEY idx_acta_visualizacion_participante_acta (acta_visualizacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actas_visualizacion_documentos (
  id INT NOT NULL AUTO_INCREMENT,
  acta_visualizacion_id INT NOT NULL,
  fuente ENUM('OFICIO','RESPUESTA') NOT NULL,
  fuente_id INT NOT NULL,
  descripcion VARCHAR(500) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_acta_visualizacion_documento (acta_visualizacion_id, fuente, fuente_id),
  KEY idx_acta_visualizacion_documento_acta (acta_visualizacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actas_visualizacion_discos (
  id INT NOT NULL AUTO_INCREMENT,
  acta_visualizacion_id INT NOT NULL,
  numero INT NOT NULL,
  marca VARCHAR(120) NULL,
  numero_serie VARCHAR(180) NULL,
  observaciones VARCHAR(500) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_acta_visualizacion_disco (acta_visualizacion_id, numero),
  KEY idx_acta_visualizacion_disco_acta (acta_visualizacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actas_visualizacion_archivos (
  id INT NOT NULL AUTO_INCREMENT,
  disco_id INT NOT NULL,
  nombre_archivo VARCHAR(255) NOT NULL,
  tipo_archivo VARCHAR(120) NULL,
  peso VARCHAR(80) NULL,
  duracion VARCHAR(80) NULL,
  observaciones VARCHAR(500) NULL,
  PRIMARY KEY (id),
  KEY idx_acta_visualizacion_archivo_disco (disco_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actas_visualizacion_descripciones (
  id INT NOT NULL AUTO_INCREMENT,
  archivo_id INT NOT NULL,
  orden INT NOT NULL,
  tiempo TIME NOT NULL,
  detalle TEXT NOT NULL,
  captura_path VARCHAR(500) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_acta_visualizacion_descripcion (archivo_id, orden),
  KEY idx_acta_visualizacion_descripcion_archivo (archivo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
