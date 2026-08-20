ALTER TABLE oficios
    ADD COLUMN categoria VARCHAR(100) NULL AFTER asunto_id,
    ADD INDEX idx_oficios_categoria (categoria);
