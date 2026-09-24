-- PRE-FLIGHT DE SOLO LECTURA para comparar producción con el código multiusuario.
-- No crea, altera ni actualiza tablas, vistas, usuarios ni expedientes.

-- 1. Tablas base y auxiliares que usa la migración multiusuario.
SELECT e.nombre AS objeto,
       CASE WHEN t.table_name IS NULL THEN 'FALTA' ELSE t.table_type END AS estado
FROM (
  SELECT 'usuarios' nombre UNION ALL SELECT 'accidentes'
  UNION ALL SELECT 'expediente_colaboradores' UNION ALL SELECT 'expediente_transferencias'
  UNION ALL SELECT 'comunicaciones_guardia' UNION ALL SELECT 'auditoria'
  UNION ALL SELECT 'app_migrations' UNION ALL SELECT 'actas'
  UNION ALL SELECT 'actas_visualizacion' UNION ALL SELECT 'actas_visualizacion_participantes'
  UNION ALL SELECT 'actas_visualizacion_documentos' UNION ALL SELECT 'actas_visualizacion_discos'
  UNION ALL SELECT 'actas_visualizacion_archivos' UNION ALL SELECT 'actas_visualizacion_descripciones'
  UNION ALL SELECT 'diligencias_pendientes' UNION ALL SELECT 'documento_vehiculo'
) e
LEFT JOIN information_schema.tables t
  ON t.table_schema = DATABASE() AND t.table_name = e.nombre
ORDER BY e.nombre;

-- 2. Columnas nuevas que necesitan usuarios, accidentes y documentos.
SELECT e.tabla, e.columna,
       IF(c.column_name IS NULL, 'FALTA', 'OK') AS estado
FROM (
  SELECT 'usuarios' tabla, 'grado' columna UNION ALL SELECT 'usuarios','cip'
  UNION ALL SELECT 'usuarios','cargo' UNION ALL SELECT 'usuarios','unidad'
  UNION ALL SELECT 'usuarios','telefono' UNION ALL SELECT 'usuarios','must_change_password'
  UNION ALL SELECT 'usuarios','auth_version'
  UNION ALL SELECT 'accidentes','responsable_id' UNION ALL SELECT 'accidentes','creado_por'
  UNION ALL SELECT 'accidentes','asignado_en' UNION ALL SELECT 'accidentes','primera_actuacion_en'
  UNION ALL SELECT 'accidentes','eliminado_en' UNION ALL SELECT 'accidentes','eliminado_por'
  UNION ALL SELECT 'accidentes','motivo_eliminacion' UNION ALL SELECT 'accidentes','ubicacion_verificada'
  UNION ALL SELECT 'personas','creado_por' UNION ALL SELECT 'vehiculos','creado_por'
  UNION ALL SELECT 'oficios','responsable_documento' UNION ALL SELECT 'actas','responsable_documento'
  UNION ALL SELECT 'actas_visualizacion','responsable_documento' UNION ALL SELECT 'citacion','responsable_documento'
  UNION ALL SELECT 'Manifestacion','responsable_documento'
) e
LEFT JOIN information_schema.columns c
  ON c.table_schema = DATABASE() AND c.table_name = e.tabla AND c.column_name = e.columna
ORDER BY e.tabla, e.columna;

-- 3. Vistas operativas que filtran expedientes eliminados y sus documentos.
SELECT e.nombre AS vista,
       IF(v.table_name IS NULL, 'FALTA', 'OK') AS estado
FROM (
  SELECT 'accidentes_activos' nombre UNION ALL SELECT 'abogados_activos'
  UNION ALL SELECT 'accidente_analisis_imagenes_activos' UNION ALL SELECT 'accidente_consecuencia_activos'
  UNION ALL SELECT 'accidente_modalidad_activos' UNION ALL SELECT 'citacion_activos'
  UNION ALL SELECT 'diligencias_pendientes_activos' UNION ALL SELECT 'documento_occiso_activos'
  UNION ALL SELECT 'documento_rml_activos' UNION ALL SELECT 'documentos_recibidos_activos'
  UNION ALL SELECT 'familiar_fallecido_activos' UNION ALL SELECT 'involucrados_personas_activos'
  UNION ALL SELECT 'involucrados_vehiculos_activos' UNION ALL SELECT 'itp_activos'
  UNION ALL SELECT 'Manifestacion_activos' UNION ALL SELECT 'oficios_activos'
  UNION ALL SELECT 'policial_interviniente_activos' UNION ALL SELECT 'propietario_vehiculo_activos'
  UNION ALL SELECT 'actas_activos' UNION ALL SELECT 'actas_visualizacion_activos'
  UNION ALL SELECT 'documento_vehiculo_activos'
) e
LEFT JOIN information_schema.views v
  ON v.table_schema = DATABASE() AND v.table_name = e.nombre
ORDER BY e.nombre;

-- 4. Índice único CIP, funciones y disparadores instalados.
SELECT 'uq_usuarios_cip' AS objeto,
       IF(COUNT(*) > 0, 'OK', 'FALTA') AS estado
FROM information_schema.statistics
WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND index_name = 'uq_usuarios_cip';

SELECT routine_name AS objeto, routine_type AS tipo
FROM information_schema.routines
WHERE routine_schema = DATABASE() AND routine_name IN ('rbac_admin','rbac_case','rbac_person','rbac_vehicle')
ORDER BY routine_name;

SELECT trigger_name AS objeto, event_object_table AS tabla, event_manipulation AS evento
FROM information_schema.triggers
WHERE trigger_schema = DATABASE() AND trigger_name LIKE 'rbac_%'
ORDER BY event_object_table, trigger_name;
