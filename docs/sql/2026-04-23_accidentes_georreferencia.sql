ALTER TABLE accidentes
    ADD COLUMN latitud DECIMAL(10,7) NULL AFTER referencia,
    ADD COLUMN longitud DECIMAL(10,7) NULL AFTER latitud;
