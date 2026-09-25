<?php
/** Incluido exclusivamente por la migración CLI. Protege también el SQL de las pantallas heredadas. */
if (PHP_SAPI !== 'cli' || !isset($p)) { http_response_code(404); exit; }
foreach(['rbac_admin','rbac_case','rbac_person','rbac_vehicle'] as $f) $p->exec("DROP FUNCTION IF EXISTS $f");
$p->exec("CREATE FUNCTION rbac_admin() RETURNS BOOLEAN READS SQL DATA RETURN EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1 AND rol='admin')");
$p->exec("CREATE FUNCTION rbac_case(case_id INT) RETURNS BOOLEAN READS SQL DATA RETURN EXISTS(SELECT 1 FROM accidentes a JOIN usuarios u ON u.id=@actor_id AND u.activo=1 WHERE a.id=case_id AND a.eliminado_en IS NULL AND (u.rol='admin' OR (u.rol='jefe_emi' AND a.responsable_id=u.id) OR (u.rol='adjunto' AND EXISTS(SELECT 1 FROM expediente_colaboradores c WHERE c.accidente_id=a.id AND c.usuario_id=u.id AND c.revocado_en IS NULL))))");
// Un dato de identidad compartido no se modifica si afecta expedientes fuera del permiso del actor.
$p->exec("CREATE FUNCTION rbac_person(person_id INT) RETURNS BOOLEAN READS SQL DATA BEGIN
DECLARE total INT DEFAULT 0; DECLARE denied INT DEFAULT 0;
SELECT COUNT(*),COALESCE(SUM(NOT rbac_case(x.accidente_id)),0) INTO total,denied FROM (
SELECT accidente_id FROM involucrados_personas WHERE persona_id=person_id UNION SELECT accidente_id FROM policial_interviniente WHERE persona_id=person_id UNION SELECT accidente_id FROM abogados WHERE persona_id=person_id UNION SELECT accidente_id FROM familiar_fallecido WHERE familiar_persona_id=person_id UNION SELECT accidente_id FROM propietario_vehiculo WHERE propietario_persona_id=person_id OR representante_persona_id=person_id) x;
RETURN rbac_admin() OR (denied=0 AND (total>0 OR EXISTS(SELECT 1 FROM personas WHERE id=person_id AND creado_por=@actor_id)) AND EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1 AND rol IN ('jefe_emi','adjunto')));
END");
$p->exec("CREATE FUNCTION rbac_vehicle(vehicle_id INT) RETURNS BOOLEAN READS SQL DATA RETURN rbac_admin() OR (EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1 AND rol IN ('jefe_emi','adjunto')) AND NOT EXISTS(SELECT 1 FROM involucrados_vehiculos WHERE vehiculo_id=vehicle_id AND NOT rbac_case(accidente_id)) AND (EXISTS(SELECT 1 FROM involucrados_vehiculos WHERE vehiculo_id=vehicle_id) OR EXISTS(SELECT 1 FROM vehiculos WHERE id=vehicle_id AND creado_por=@actor_id)))");
$tables=$p->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
$internal=['accidente_modalidad','accidente_consecuencia','actas_visualizacion_participantes','actas_visualizacion_documentos','actas_visualizacion_discos','actas_visualizacion_archivos','actas_visualizacion_descripciones'];
$skip=['app_migrations','auditoria','api_persona_cache'];
$operational="EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1 AND rol IN ('admin','jefe_emi','adjunto'))";
foreach($tables as $table) {
    if(in_array($table,$skip,true)) continue;
    $columns=$p->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    $has=fn($c)=>in_array($c,$columns,true);
    foreach(['INSERT'=>'NEW','UPDATE'=>'NEW','DELETE'=>'OLD'] as $event=>$row) {
        $guard=''; $extra=''; $case='NULL';
        if($table==='accidentes') {
            $case="$row.id";
            if($event==='INSERT') {
                $guard="(rbac_admin() OR EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1 AND rol='jefe_emi') OR (@rbac_guardia_assign=1 AND EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1 AND rol='guardia')))";
                $extra="SET NEW.creado_por=@actor_id; IF COALESCE(@rbac_guardia_assign,0)=0 THEN SET NEW.responsable_id=@actor_id; END IF; SET NEW.asignado_en=NOW();";
            } elseif($event==='UPDATE') {
                $guard="(rbac_admin() OR (OLD.eliminado_en IS NULL AND (EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1 AND rol='jefe_emi' AND id=OLD.responsable_id) OR EXISTS(SELECT 1 FROM expediente_colaboradores c JOIN usuarios u ON u.id=c.usuario_id WHERE c.accidente_id=OLD.id AND c.usuario_id=@actor_id AND c.revocado_en IS NULL AND u.activo=1 AND u.rol='adjunto'))))";
                $guard .= " OR (COALESCE(@rbac_assignment,0)=1 AND OLD.eliminado_en IS NULL AND EXISTS(SELECT 1 FROM expediente_transferencias t JOIN usuarios u ON u.id=t.destino_id WHERE t.accidente_id=OLD.id AND t.origen_id=COALESCE(OLD.responsable_id,0) AND t.destino_id=@actor_id AND t.estado='pendiente' AND u.activo=1 AND u.rol='jefe_emi'))";
                $extra="IF NOT (NEW.creado_por <=> OLD.creado_por) OR NOT (NEW.creado_en <=> OLD.creado_en) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La autoría y fecha original son inmutables'; END IF;
IF (NOT (NEW.responsable_id <=> OLD.responsable_id) OR NOT (NEW.asignado_en <=> OLD.asignado_en)) AND COALESCE(@rbac_assignment,0)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Use el flujo de transferencia'; END IF;
IF (NOT (NEW.eliminado_en <=> OLD.eliminado_en) OR NOT (NEW.eliminado_por <=> OLD.eliminado_por)) AND NOT rbac_admin() THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo el administrador elimina registros'; END IF;
IF NOT (NEW.latitud <=> OLD.latitud) OR NOT (NEW.longitud <=> OLD.longitud) THEN SET NEW.ubicacion_verificada=0; END IF;
IF NEW.ubicacion_verificada=1 AND (NEW.latitud IS NULL OR NEW.longitud IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No puede verificar sin coordenadas'; END IF;
IF NEW.primera_actuacion_en IS NULL AND COALESCE(@rbac_assignment,0)=0 THEN SET NEW.primera_actuacion_en=NOW(); END IF;";
            } else { $guard='FALSE'; } // El expediente se elimina lógicamente; nunca se destruye en cascada.
        } elseif($table==='comunicaciones_guardia') {
            if($event==='INSERT') {
                $guard="(rbac_admin() OR EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1 AND rol='guardia'))";
                $extra='SET NEW.creado_por=@actor_id; SET NEW.registrado_en=NOW();';
            } elseif($event==='UPDATE') {
                $guard="(rbac_admin() OR (OLD.eliminado_en IS NULL AND OLD.creado_por=@actor_id AND (NOW()<DATE_ADD(OLD.registrado_en,INTERVAL 12 HOUR) OR COALESCE(@rbac_guardia_assign,0)=1) AND EXISTS(SELECT 1 FROM usuarios WHERE id=@actor_id AND activo=1 AND rol='guardia')))";
                $extra="IF NOT rbac_admin() AND NOW()>=DATE_ADD(OLD.registrado_en,INTERVAL 12 HOUR) AND (NOT (NEW.fecha_llamada <=> OLD.fecha_llamada) OR NOT (NEW.comunicante <=> OLD.comunicante) OR NOT (NEW.telefono <=> OLD.telefono) OR NOT (NEW.lugar <=> OLD.lugar) OR NOT (NEW.referencia <=> OLD.referencia) OR NOT (NEW.descripcion <=> OLD.descripcion) OR NOT (NEW.tipo_hecho <=> OLD.tipo_hecho) OR NOT (NEW.vehiculos <=> OLD.vehiculos) OR NOT (NEW.afectados <=> OLD.afectados) OR NOT (NEW.latitud <=> OLD.latitud) OR NOT (NEW.longitud <=> OLD.longitud) OR NOT (NEW.cod_dep <=> OLD.cod_dep) OR NOT (NEW.cod_prov <=> OLD.cod_prov) OR NOT (NEW.cod_dist <=> OLD.cod_dist)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Plazo de edición de 12 horas vencido'; END IF;
IF NOT (NEW.registrado_en <=> OLD.registrado_en) OR NOT (NEW.creado_por <=> OLD.creado_por) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No puede reiniciar el plazo de 12 horas'; END IF;
IF (NOT (NEW.jefe_id <=> OLD.jefe_id) OR NOT (NEW.accidente_id <=> OLD.accidente_id) OR NOT (NEW.asignado_en <=> OLD.asignado_en)) AND COALESCE(@rbac_guardia_assign,0)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Use la asignación al JEFE EMI'; END IF;
IF NOT (NEW.eliminado_en <=> OLD.eliminado_en) AND NOT rbac_admin() THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solo administrador elimina'; END IF;";
            } else $guard='FALSE';
            $case="$row.accidente_id";
        } elseif(in_array($table,['expediente_colaboradores','expediente_transferencias'],true)) {
            $guard='COALESCE(@rbac_assignment,0)=1'; $case="$row.accidente_id";
        } elseif($table==='personas' || $table==='vehiculos') {
            $func=$table==='personas'?'rbac_person':'rbac_vehicle';
            $guard=$event==='INSERT'?$operational:"$func(OLD.id)";
            if($event==='INSERT') $extra='SET NEW.creado_por=@actor_id;';
            if($event==='UPDATE') $extra="IF NOT (NEW.creado_por <=> OLD.creado_por) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Autoría inmutable'; END IF;";
        } elseif($has('accidente_id')) {
            $case="$row.accidente_id"; $guard="rbac_case($case)";
            if($event==='UPDATE') $guard.=' AND rbac_case(OLD.accidente_id)';
        } elseif($has('acta_visualizacion_id')) {
            $case="(SELECT accidente_id FROM actas_visualizacion WHERE id=$row.acta_visualizacion_id)";$guard="rbac_case($case)";
            if($event==='UPDATE') $guard.=' AND rbac_case((SELECT accidente_id FROM actas_visualizacion WHERE id=OLD.acta_visualizacion_id))';
        } elseif($table==='actas_visualizacion_archivos' || $table==='actas_visualizacion_descripciones') {
            $path=$table==='actas_visualizacion_archivos'?"SELECT a.accidente_id FROM actas_visualizacion a JOIN actas_visualizacion_discos d ON d.acta_visualizacion_id=a.id WHERE d.id=%s.disco_id":"SELECT a.accidente_id FROM actas_visualizacion a JOIN actas_visualizacion_discos d ON d.acta_visualizacion_id=a.id JOIN actas_visualizacion_archivos f ON f.disco_id=d.id WHERE f.id=%s.archivo_id";
            $case='('.sprintf($path,$row).')';$guard="rbac_case($case)";
            if($event==='UPDATE')$guard.=' AND rbac_case(('.sprintf($path,'OLD').'))';
        } elseif($has('involucrado_vehiculo_id')) {
            $case="(SELECT accidente_id FROM involucrados_vehiculos WHERE id=$row.involucrado_vehiculo_id)";$guard="rbac_case($case)";
            if($event==='UPDATE')$guard.=' AND rbac_case((SELECT accidente_id FROM involucrados_vehiculos WHERE id=OLD.involucrado_vehiculo_id))';
        } elseif($has('persona_id')) {
            $guard="rbac_person($row.persona_id)";
            if($event==='UPDATE') $guard.=' AND rbac_person(OLD.persona_id)';
        } else {
            $guard='rbac_admin()'; // Catálogos y cuentas: administración.
        }
        if($table==='usuarios' && $event==='UPDATE') {
            $unchanged=[];
            foreach($columns as $c) if(!in_array($c,['pass_hash','must_change_password','auth_version','actualizado_en'],true)) $unchanged[]="(NEW.`$c` <=> OLD.`$c`)";
            $guard="($guard) OR (COALESCE(@rbac_password_change,0)=1 AND OLD.id=@actor_id AND OLD.activo=1 AND OLD.must_change_password=1 AND NEW.must_change_password=0 AND NEW.auth_version=OLD.auth_version+1 AND ".implode(' AND ',$unchanged).")";
            $extra.=" IF NOT (NEW.pass_hash <=> OLD.pass_hash) THEN SET NEW.auth_version=OLD.auth_version+1; END IF;";
        }
        if($has('creado_por') && !in_array($table,['accidentes','comunicaciones_guardia','personas','vehiculos'],true)) {
            if($event==='INSERT')$extra.=' SET NEW.creado_por=@actor_id;';
            if($event==='UPDATE')$extra.=" IF NOT (NEW.creado_por <=> OLD.creado_por) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Autoría inmutable'; END IF;";
        }
        if($has('responsable_documento')) {
            if($event==='INSERT') $extra .= " SET NEW.responsable_documento=(SELECT JSON_OBJECT('nombre',u.nombre,'grado',u.grado,'cip',u.cip,'cargo',u.cargo,'unidad',u.unidad,'telefono',u.telefono,'email',u.email) FROM accidentes a JOIN usuarios u ON u.id=a.responsable_id WHERE a.id=NEW.accidente_id);";
            if($event==='UPDATE') $extra .= " IF NOT (NEW.responsable_documento <=> OLD.responsable_documento) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La identidad histórica del documento se conserva'; END IF;";
        }
        if($event==='DELETE' && !in_array($table,$internal,true)) $guard="($guard) AND rbac_admin()";
        $name='rbac_'.substr($table,0,42).'_'.strtolower($event);
        $p->exec("DROP TRIGGER IF EXISTS `$name`");
        $p->exec("CREATE TRIGGER `$name` BEFORE $event ON `$table` FOR EACH ROW BEGIN IF COALESCE(@rbac_migration,0)<>1 THEN IF NOT COALESCE(($guard),0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sin permiso para modificar este registro'; END IF; $extra END IF; END");
        // Auditoría de valores exactos, excluyendo credenciales.
        $pairs=[];foreach($columns as $c) if($c!=='pass_hash')$pairs[]= $p->quote($c).",%s.`$c`";
        $json=fn($r)=>'JSON_OBJECT('.implode(',',array_map(fn($v)=>sprintf($v,$r),$pairs)).')';
        $before=$event==='INSERT'?'NULL':$json('OLD');$after=$event==='DELETE'?'NULL':$json('NEW');
        $id=$has('id')?"$row.id":($has('Id')?"$row.Id":'NULL');
        $audit='audit_'.substr($table,0,42).'_'.strtolower($event);
        $p->exec("DROP TRIGGER IF EXISTS `$audit`");
        $p->exec("CREATE TRIGGER `$audit` AFTER $event ON `$table` FOR EACH ROW BEGIN IF COALESCE(@rbac_migration,0)<>1 THEN INSERT INTO auditoria(usuario_id,tabla,registro_id,accidente_id,accion,antes,despues) VALUES(@actor_id,".$p->quote($table).",$id,$case,'$event',$before,$after); END IF; END");
    }
}
