ALTER TABLE oficio_entidad
  ADD COLUMN categoria VARCHAR(50) NULL AFTER tipo,
  ADD COLUMN telefono_fijo VARCHAR(50) NULL AFTER telefono,
  ADD COLUMN telefono_movil VARCHAR(50) NULL AFTER telefono_fijo;

UPDATE oficio_entidad
SET telefono_fijo = telefono
WHERE COALESCE(telefono, '') <> ''
  AND COALESCE(telefono_fijo, '') = '';
