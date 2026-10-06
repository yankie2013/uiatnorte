<?php
// Reutilizado por ambas migraciones; conserva la auditoría existente.
if (PHP_SAPI !== 'cli' || !isset($p)) { http_response_code(404); exit; }
$actor = "EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1)";
$editor = "EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1 AND (id=OLD.creado_por OR id=(CASE WHEN OLD.gestion=1 THEN OLD.encargado_id ELSE (SELECT responsable_id FROM accidentes WHERE id=OLD.accidente_id AND eliminado_en IS NULL) END)))";
$valid = " IF NEW.gestion=1 THEN
IF NEW.comisaria_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM comisarias WHERE id=NEW.comisaria_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Comisaría inválida'; END IF;
IF NEW.encargado_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM usuarios u JOIN usuarios actor ON actor.id=@actor_id WHERE u.id=NEW.encargado_id AND u.activo=1 AND u.rol='jefe_emi' AND TRIM(COALESCE(u.unidad,''))=TRIM(COALESCE(actor.unidad,''))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Encargado fuera de la unidad'; END IF;
IF NEW.accidente_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM accidentes WHERE id=NEW.accidente_id AND NEW.encargado_id IS NOT NULL AND NEW.comisaria_id IS NOT NULL AND responsable_id=NEW.encargado_id AND comisaria_id=NEW.comisaria_id AND eliminado_en IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expediente fuera de los filtros seleccionados'; END IF;
END IF;";
$profile = "JSON_OBJECT('nombre',u.nombre,'grado',u.grado,'cip',u.cip,'cargo',u.cargo,'unidad',u.unidad,'telefono',u.telefono,'email',u.email)";
$gestionProfile = "(SELECT $profile FROM usuarios u WHERE u.id=NEW.encargado_id)";
$emptyProfile = "JSON_OBJECT('nombre','','grado','','cip','','cargo','','unidad',COALESCE((SELECT unidad FROM usuarios WHERE id=@actor_id),''),'telefono','','email','')";
$p->exec('DROP TRIGGER IF EXISTS rbac_oficios_insert');
$p->exec("CREATE TRIGGER rbac_oficios_insert BEFORE INSERT ON oficios FOR EACH ROW BEGIN IF COALESCE(@rbac_migration,0)<>1 THEN
IF NOT ($actor AND (NEW.gestion=1 OR COALESCE(rbac_case(NEW.accidente_id),0))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sin permiso para registrar el oficio'; END IF;
$valid
SET NEW.creado_por=@actor_id;
IF NEW.gestion=1 THEN SET NEW.responsable_documento=COALESCE($gestionProfile,$emptyProfile);
ELSE SET NEW.responsable_documento=(SELECT $profile FROM accidentes a JOIN usuarios u ON u.id=a.responsable_id WHERE a.id=NEW.accidente_id); END IF;
END IF; END");
$p->exec('DROP TRIGGER IF EXISTS rbac_oficios_update');
$p->exec("CREATE TRIGGER rbac_oficios_update BEFORE UPDATE ON oficios FOR EACH ROW BEGIN IF COALESCE(@rbac_migration,0)<>1 THEN
IF NOT ($editor) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo el registrante o encargado asignado puede editar'; END IF;
IF NOT (NEW.creado_por <=> OLD.creado_por) OR NEW.gestion<>OLD.gestion THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Autoría y origen inmutables'; END IF;
IF NOT (NEW.responsable_documento <=> OLD.responsable_documento) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La identidad histórica del documento se conserva'; END IF;
$valid
IF NEW.gestion=1 AND NOT (NEW.encargado_id <=> OLD.encargado_id) THEN SET NEW.responsable_documento=COALESCE($gestionProfile,$emptyProfile); END IF;
END IF; END");
