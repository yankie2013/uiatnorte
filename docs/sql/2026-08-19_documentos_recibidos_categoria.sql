ALTER TABLE documentos_recibidos
    ADD COLUMN categoria VARCHAR(100) NULL AFTER tipo_documento,
    ADD INDEX idx_documentos_recibidos_categoria (categoria);
