-- Fix de normalizacion para la tabla personas
-- Uso:
-- 1. Haz backup de la tabla personas antes de ejecutar.
-- 2. Ejecuta este script completo en la base de datos de produccion.
-- 3. Si ya tienes registros afectados, corrige los apellidos manualmente luego.

-- Este script recrea la funcion y los triggers con una expresion segura
-- para espacios. Se usa [[:space:]]+ para evitar el error que convertia
-- letras "S" en espacios al guardar.

DROP FUNCTION IF EXISTS initcap_words;

DELIMITER $$
CREATE FUNCTION initcap_words(input_text TEXT)
RETURNS TEXT
DETERMINISTIC
BEGIN
  DECLARE i INT DEFAULT 1;
  DECLARE len INT DEFAULT 0;
  DECLARE result TEXT DEFAULT '';
  DECLARE ch VARCHAR(1);
  DECLARE upper_next BOOLEAN DEFAULT TRUE;

  IF input_text IS NULL THEN
    RETURN NULL;
  END IF;

  SET input_text = TRIM(REGEXP_REPLACE(COALESCE(input_text, ''), '[[:space:]]+', ' '));
  SET len = CHAR_LENGTH(input_text);

  WHILE i <= len DO
    SET ch = SUBSTRING(input_text, i, 1);

    IF ch = ' ' THEN
      SET result = CONCAT(result, ' ');
      SET upper_next = TRUE;
    ELSEIF upper_next THEN
      SET result = CONCAT(result, UPPER(ch));
      SET upper_next = FALSE;
    ELSE
      SET result = CONCAT(result, LOWER(ch));
      SET upper_next = FALSE;
    END IF;

    SET i = i + 1;
  END WHILE;

  RETURN result;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS bi_personas_norm;
DROP TRIGGER IF EXISTS bu_personas_norm;

DELIMITER $$
CREATE TRIGGER bi_personas_norm
BEFORE INSERT ON personas
FOR EACH ROW
BEGIN
  SET NEW.apellido_paterno = UPPER(TRIM(REGEXP_REPLACE(COALESCE(NEW.apellido_paterno, ''), '[[:space:]]+', ' ')));
  SET NEW.apellido_materno = UPPER(TRIM(REGEXP_REPLACE(COALESCE(NEW.apellido_materno, ''), '[[:space:]]+', ' ')));
  SET NEW.nombres = initcap_words(NEW.nombres);
END$$

CREATE TRIGGER bu_personas_norm
BEFORE UPDATE ON personas
FOR EACH ROW
BEGIN
  SET NEW.apellido_paterno = UPPER(TRIM(REGEXP_REPLACE(COALESCE(NEW.apellido_paterno, ''), '[[:space:]]+', ' ')));
  SET NEW.apellido_materno = UPPER(TRIM(REGEXP_REPLACE(COALESCE(NEW.apellido_materno, ''), '[[:space:]]+', ' ')));
  SET NEW.nombres = initcap_words(NEW.nombres);
END$$
DELIMITER ;

-- Verificacion rapida opcional:
-- SHOW TRIGGERS WHERE `Table` = 'personas';

-- Ejemplo de correccion manual de un registro afectado:
-- UPDATE personas
--    SET apellido_materno = 'SALDANIAGA'
--  WHERE id = 139;
