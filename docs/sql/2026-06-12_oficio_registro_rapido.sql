-- Permite registrar oficios rapidos sin entidad ni asunto catalogado.
ALTER TABLE oficios
  MODIFY entidad_id_destino INT NULL,
  MODIFY asunto_id INT NULL;
