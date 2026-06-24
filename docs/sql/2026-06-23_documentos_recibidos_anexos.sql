CREATE TABLE IF NOT EXISTS documentos_recibidos_anexos (
    id INT NOT NULL AUTO_INCREMENT,
    documento_recibido_id INT NOT NULL,
    descripcion VARCHAR(1000) NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_documento_recibido_anexo (documento_recibido_id, orden),
    CONSTRAINT fk_documento_recibido_anexo
        FOREIGN KEY (documento_recibido_id) REFERENCES documentos_recibidos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
