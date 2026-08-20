ALTER TABLE actas_visualizacion_discos
  ADD COLUMN oficio_id INT NULL AFTER acta_visualizacion_id,
  ADD COLUMN tipo_medio VARCHAR(20) NULL AFTER numero,
  ADD COLUMN capacidad VARCHAR(100) NULL AFTER numero_serie,
  ADD KEY idx_acta_visualizacion_disco_oficio (oficio_id);
