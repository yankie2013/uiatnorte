ALTER TABLE documento_vehiculo
    ADD COLUMN imagen_peritaje_path VARCHAR(255) NULL AFTER danos_peritaje,
    ADD COLUMN imagen_peritaje_nombre VARCHAR(255) NULL AFTER imagen_peritaje_path,
    ADD COLUMN imagen_peritaje_mime VARCHAR(100) NULL AFTER imagen_peritaje_nombre,
    ADD COLUMN imagen_peritaje_size INT UNSIGNED NULL AFTER imagen_peritaje_mime;
