ALTER TABLE documentos_recibidos
    ADD COLUMN siglas_documento VARCHAR(100) NULL AFTER numero_documento,
    ADD INDEX idx_documentos_recibidos_siglas (siglas_documento);
