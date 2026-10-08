<?php
/** Instalador compartido: identidad registrada fija; datos vacíos completables. */
if (PHP_SAPI !== 'cli' || !isset($pdo)) { http_response_code(404); exit; }
$conditions=[];
foreach (['num_doc','apellido_paterno','apellido_materno','nombres','fecha_nacimiento','departamento_nac','provincia_nac','distrito_nac','nombre_padre','nombre_madre'] as $field) {
    $conditions[]="(NULLIF(TRIM(OLD.`$field`),'') IS NOT NULL AND NOT (OLD.`$field` <=> NEW.`$field`))";
}
$conditions[]="(NULLIF(TRIM(OLD.num_doc),'') IS NOT NULL AND NOT (OLD.tipo_doc <=> NEW.tipo_doc))";
$pdo->exec('DROP TRIGGER IF EXISTS bu_personas_identidad_inmutable');
$pdo->exec("CREATE TRIGGER bu_personas_identidad_inmutable BEFORE UPDATE ON personas FOR EACH ROW BEGIN
 IF ".implode(' OR ',$conditions)." THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La identidad registrada es fija; solo se pueden completar los datos vacíos';
 END IF;
END");
