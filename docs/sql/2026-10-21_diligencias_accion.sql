ALTER TABLE diligencias_pendientes
    ADD COLUMN accion VARCHAR(20) NULL AFTER tipo_diligencia;

CREATE OR REPLACE VIEW diligencias_pendientes_activos AS
SELECT d.*
FROM diligencias_pendientes d
LEFT JOIN accidentes a ON a.id = d.accidente_id
WHERE d.accidente_id IS NULL OR a.eliminado_en IS NULL;
