INSERT INTO oficio_entidad (tipo, categoria, nombre, siglas, direccion, telefono, correo, pagina_web, creado_en, actualizado_en)
SELECT 'PUBLICA', 'REGISTRO_PUBLICO', 'Superintendencia Nacional de los Registros Publicos', 'SUNARP', '', '', '', '', NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM oficio_entidad
  WHERE UPPER(COALESCE(siglas,'')) = 'SUNARP'
     OR nombre LIKE '%Registros Publicos%'
     OR nombre LIKE '%Registros Públicos%'
);

SET @sunarp_entidad_id := (
  SELECT id FROM oficio_entidad
  WHERE UPPER(COALESCE(siglas,'')) = 'SUNARP'
     OR nombre LIKE '%Registros Publicos%'
     OR nombre LIKE '%Registros Públicos%'
  ORDER BY id ASC
  LIMIT 1
);

INSERT INTO oficio_asunto (entidad_id, tipo, nombre, detalle, orden, activo, creado_en, actualizado_en)
SELECT @sunarp_entidad_id, 'SOLICITAR', 'Historial de transferencias vehiculares', 'Solicita historial de transferencias vehiculares ante SUNARP.', 0, 1, NOW(), NOW()
WHERE @sunarp_entidad_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM oficio_asunto
    WHERE entidad_id = @sunarp_entidad_id
      AND tipo = 'SOLICITAR'
      AND (
        nombre LIKE '%Historial%transferencia%'
        OR detalle LIKE '%Historial%transferencia%'
        OR nombre LIKE '%SUNARP%'
        OR detalle LIKE '%SUNARP%'
      )
  );

INSERT INTO oficio_asunto (entidad_id, tipo, nombre, detalle, orden, activo, creado_en, actualizado_en)
SELECT e.id, 'SOLICITAR', 'Informacion de diligencias', 'Solicita informacion respecto a diligencias realizadas o pendientes.', 0, 1, NOW(), NOW()
FROM oficio_entidad e
WHERE (
    UPPER(COALESCE(e.siglas,'')) LIKE '%COM%'
    OR UPPER(COALESCE(e.nombre,'')) LIKE '%COMISARIA%'
    OR UPPER(COALESCE(e.nombre,'')) LIKE '%COMISARÍA%'
  )
  AND NOT EXISTS (
    SELECT 1 FROM oficio_asunto a
    WHERE a.entidad_id = e.id
      AND a.tipo = 'SOLICITAR'
      AND (
        (a.nombre LIKE '%Informacion%' OR a.nombre LIKE '%Información%')
        AND a.nombre LIKE '%diligenc%'
      )
  );

INSERT INTO oficio_asunto (entidad_id, tipo, nombre, detalle, orden, activo, creado_en, actualizado_en)
SELECT e.id, 'SOLICITAR', 'Identificacion de cadaver', 'Solicita identificacion de cadaver ante UTANFOR.', 0, 1, NOW(), NOW()
FROM oficio_entidad e
WHERE (
    UPPER(COALESCE(e.siglas,'')) LIKE '%UTANFOR%'
    OR UPPER(COALESCE(e.nombre,'')) LIKE '%TANATOLOG%'
  )
  AND NOT EXISTS (
    SELECT 1 FROM oficio_asunto a
    WHERE a.tipo = 'SOLICITAR'
      AND a.nombre LIKE '%Identificacion%cadaver%'
  );

UPDATE oficio_asunto a
JOIN oficio_entidad e ON (
    UPPER(COALESCE(e.siglas,'')) LIKE '%UTANFOR%'
    OR UPPER(COALESCE(e.nombre,'')) LIKE '%TANATOLOG%'
  )
SET a.entidad_id = e.id,
    a.detalle = 'Solicita identificacion de cadaver ante UTANFOR.',
    a.actualizado_en = NOW()
WHERE a.tipo = 'SOLICITAR'
  AND a.nombre LIKE '%Identificacion%cadaver%';

INSERT INTO oficio_entidad (tipo, categoria, nombre, siglas, direccion, telefono, correo, pagina_web, creado_en, actualizado_en)
SELECT 'PUBLICA', 'POLICIALES', 'DEPPIRV NORTE', 'DEPPIRV NORTE', '', '', '', '', NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM oficio_entidad
  WHERE UPPER(COALESCE(siglas,'')) LIKE '%DEPPIRV%'
     OR UPPER(COALESCE(nombre,'')) LIKE '%DEPPIRV%'
);

SET @deppirv_entidad_id := (
  SELECT id FROM oficio_entidad
  WHERE UPPER(COALESCE(siglas,'')) LIKE '%DEPPIRV%'
     OR UPPER(COALESCE(nombre,'')) LIKE '%DEPPIRV%'
  ORDER BY id ASC
  LIMIT 1
);

INSERT INTO oficio_asunto (entidad_id, tipo, nombre, detalle, orden, activo, creado_en, actualizado_en)
SELECT @deppirv_entidad_id, 'SOLICITAR', 'Identificacion de vehiculo', 'Solicita identificacion de vehiculo ante DEPPIRV NORTE.', 0, 1, NOW(), NOW()
WHERE @deppirv_entidad_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM oficio_asunto
    WHERE tipo = 'SOLICITAR'
      AND nombre LIKE '%Identificacion%vehiculo%'
  );

UPDATE oficio_asunto a
JOIN oficio_entidad e ON (
    UPPER(COALESCE(e.siglas,'')) LIKE '%DEPPIRV%'
    OR UPPER(COALESCE(e.nombre,'')) LIKE '%DEPPIRV%'
  )
SET a.entidad_id = e.id,
    a.detalle = 'Solicita identificacion de vehiculo ante DEPPIRV NORTE.',
    a.actualizado_en = NOW()
WHERE a.tipo = 'SOLICITAR'
  AND a.nombre LIKE '%Identificacion%vehiculo%';
