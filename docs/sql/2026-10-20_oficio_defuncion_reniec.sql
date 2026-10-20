CREATE TABLE IF NOT EXISTS oficio_defuncion_reniec (
 oficio_id INT NOT NULL PRIMARY KEY,
 datos LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO oficio_entidad (tipo, categoria, nombre, siglas, direccion, telefono, correo, pagina_web, creado_en, actualizado_en)
SELECT 'PUBLICA', 'REGISTRO_PUBLICO', 'REGISTROS NACIONAL DE IDENTIFICACIÓN Y ESTADO CIVIL', 'RENIEC', '', '', '', '', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM oficio_entidad WHERE UPPER(siglas)='RENIEC');
INSERT INTO oficio_asunto (entidad_id, tipo, nombre, detalle, orden, activo, creado_en, actualizado_en)
SELECT id, 'SOLICITAR', 'Inscripción defunción RENIEC', 'Solicita la inscripción de la Partida de Defunción de persona fallecida; por motivo que se indica.', 0, 1, NOW(), NOW()
FROM oficio_entidad WHERE UPPER(siglas)='RENIEC'
AND NOT EXISTS (SELECT 1 FROM oficio_asunto WHERE nombre LIKE '%defunción%RENIEC%' OR nombre LIKE '%defuncion%RENIEC%') LIMIT 1;

UPDATE oficio_entidad SET nombre='REGISTROS NACIONAL DE IDENTIFICACIÓN Y ESTADO CIVIL' WHERE UPPER(siglas)='RENIEC';
