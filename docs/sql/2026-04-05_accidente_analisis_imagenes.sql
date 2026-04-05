CREATE TABLE IF NOT EXISTS accidente_analisis_imagenes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    accidente_id INT NOT NULL,
    seccion ENUM('danos', 'lesiones') NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL,
    archivo_path VARCHAR(255) NOT NULL,
    archivo_nombre VARCHAR(255) DEFAULT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size INT UNSIGNED DEFAULT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_accidente_analisis_imagenes_accidente (accidente_id),
    KEY idx_accidente_analisis_imagenes_section_order (accidente_id, seccion, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
