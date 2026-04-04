ALTER TABLE documentos_recibidos
    ADD COLUMN fecha_recepcion DATE NULL AFTER fecha,
    ADD COLUMN fecha_documento DATE NULL AFTER fecha_recepcion;

UPDATE documentos_recibidos
   SET fecha_recepcion = COALESCE(fecha_recepcion, fecha, CURDATE()),
       fecha_documento = COALESCE(fecha_documento, fecha)
 WHERE fecha_recepcion IS NULL
    OR fecha_documento IS NULL;
