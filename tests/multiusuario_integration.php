<?php
/** Pruebas en una copia temporal aislada. No modifica las filas de la base de trabajo. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
ob_start();
require dirname(__DIR__).'/bootstrap/app.php';
use App\Database\Database;
use App\Support\Access;
use App\Services\ExpedienteAccessService;
use App\Services\GuardiaService;
$p=Database::connection();$original=$p->query('SELECT DATABASE()')->fetchColumn();$test='uiat_rbac_test_'.bin2hex(random_bytes(4));
\App\Support\Auth::startSession();
$checks=0;
function check(bool $value,string $message):void{global $checks;if(!$value)throw new RuntimeException('FAIL: '.$message);$checks++;echo "OK $message\n";}
function deny(callable $fn,string $message):void {try{$fn();}catch(Throwable $e){check(true,$message);return;}throw new RuntimeException('FAIL allowed: '.$message);}
function actor(PDO $p,int $id):void{$s=$p->prepare('SELECT id,nombre,rol,email FROM usuarios WHERE id=?');$s->execute([$id]);$_SESSION['user']=$s->fetch();$p->exec('SET @actor_id='.$id);}
function user(PDO $p,string $role):int{$p->prepare('INSERT INTO usuarios(nombre,email,rol,pass_hash,activo) VALUES(?,?,?,?,1)')->execute(['Prueba '.$role,bin2hex(random_bytes(4)).'@test.invalid',$role,password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);return (int)$p->lastInsertId();}
try{
    $p->exec("CREATE DATABASE `$test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach($p->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table){$p->exec("CREATE TABLE `$test`.`$table` LIKE `$original`.`$table`");$p->exec("INSERT INTO `$test`.`$table` SELECT * FROM `$original`.`$table`");}
    $p->exec("USE `$test`");
    require dirname(__DIR__).'/docs/scripts/migrar_multiusuario.php';
    actor($p,1);$other=user($p,'jefe_emi');$adj=user($p,'adjunto');$guard=user($p,'guardia');$secretary=user($p,'secretaria');
    actor($p,$other);
    foreach (['fiscalia','modalidad_accidente','consecuencia_accidente','comisarias','marcas_vehiculo'] as $catalog) {
        $p->prepare("INSERT INTO `$catalog` (nombre) VALUES (?)")->execute(['Catálogo de prueba '.bin2hex(random_bytes(4))]);
        $catalogId=(int)$p->lastInsertId();
        check($catalogId>0,"JEFE EMI agrega $catalog");
        deny(fn()=>$p->exec("UPDATE `$catalog` SET nombre='Cambio prohibido' WHERE id=$catalogId"),"JEFE EMI no edita $catalog");
        deny(fn()=>$p->exec("DELETE FROM `$catalog` WHERE id=$catalogId"),"JEFE EMI no elimina $catalog");
    }
    $fiscaliaId=(int)$p->query('SELECT MIN(id) FROM fiscalia')->fetchColumn();
    $p->prepare('INSERT INTO fiscales(fiscalia_id,nombres,apellido_paterno,apellido_materno) VALUES (?,?,?,?)')->execute([$fiscaliaId,'Fiscal','De','Prueba']);
    $fiscalId=(int)$p->lastInsertId();
    check($fiscalId>0,'JEFE EMI agrega fiscal desde +');
    deny(fn()=>$p->exec("UPDATE fiscales SET nombres='No autorizado' WHERE id=$fiscalId"),'JEFE EMI no edita fiscal');
    deny(fn()=>$p->exec("DELETE FROM fiscales WHERE id=$fiscalId"),'JEFE EMI no elimina fiscal');
    $p->exec('SET @actor_id=0');
    deny(fn()=>$p->exec("INSERT INTO fiscalia(nombre) VALUES ('Anónimo')"),'anónimo no agrega catálogos');
    actor($p,1);
    // Expediente heredado sin responsable: origen 0 debe aparecer y poder aceptarse.
    $p->exec('SET @rbac_migration=1');
    $unassigned=(int)$p->query('SELECT MIN(id) FROM accidentes')->fetchColumn();
    $p->exec("UPDATE accidentes SET responsable_id=NULL WHERE id=$unassigned");
    $p->exec('SET @rbac_migration=NULL');
    (new ExpedienteAccessService($p))->change($unassigned,'transferir',$other,'Asignación inicial de prueba');
    $st=$p->prepare("SELECT t.id FROM expediente_transferencias t JOIN accidentes_activos a ON a.id=t.accidente_id LEFT JOIN usuarios u ON u.id=t.origen_id JOIN usuarios d ON d.id=t.destino_id WHERE t.estado='pendiente' AND t.destino_id=?");
    $st->execute([$other]);check((bool)$st->fetchColumn(),'recepción incluye expediente sin responsable anterior');
    actor($p,$other);
    (new ExpedienteAccessService($p))->change($unassigned,'aceptar',0,'');
    check(Access::canEdit($unassigned),'destinatario acepta expediente sin responsable anterior');
    actor($p,1);
    (new ExpedienteAccessService($p))->change($unassigned,'reasignar',2,'Restablecer fixture');
    $case=(int)$p->query('SELECT MIN(id) FROM accidentes WHERE responsable_id=2')->fetchColumn();
    $dashboard = new \App\Repositories\DashboardRepository($p);
    $workspaceIds = function () use ($p): array {
        return array_map('intval', $p->query('SELECT a.id FROM accidentes_activos a WHERE ' . Access::workspacePredicate())->fetchAll(PDO::FETCH_COLUMN));
    };
    $totalActive = (int)$p->query('SELECT COUNT(*) FROM accidentes_activos')->fetchColumn();
    check($dashboard->snapshot(null)['total'] === $totalActive, 'administrador conserva resumen institucional');
    actor($p,$other);
    $empty = $dashboard->snapshot(null);
    check($empty['total'] === 0 && !$empty['recent'] && !$empty['timeline'] && !$empty['districts'] && !$dashboard->years(), 'nuevo JEFE tiene resumen, gráficos y años vacíos');
    check(!$workspaceIds(), 'nuevo JEFE no recibe expedientes ajenos en lista o mapa');
    check((int)$p->query('SELECT COUNT(*) FROM accidentes_activos')->fetchColumn() === $totalActive, 'consulta general conserva todos los expedientes');
    actor($p,2);
    check(in_array($case,$workspaceIds(),true), 'responsable ve su expediente en espacio personal');
    actor($p,2);check(Access::canEdit($case),'responsable edita');
    $p->prepare('UPDATE accidentes SET referencia=? WHERE id=?')->execute(['Prueba aislada',$case]);
    actor($p,$other);check(!Access::canEdit($case),'otro JEFE solo consulta');deny(fn()=>$p->exec("UPDATE accidentes SET lugar='Sin permiso' WHERE id=$case"),'SQL directo de otro JEFE bloqueado');
    actor($p,$adj);deny(fn()=>$p->exec("UPDATE accidentes SET lugar='Sin permiso' WHERE id=$case"),'adjunto sin compartir bloqueado');
    actor($p,$other);$stateService=new ExpedienteAccessService($p);
    deny(fn()=>$stateService->change($case,'cambiar_estado',0,'Resuelto'),'otro JEFE no cambia estado desde gestión');
    actor($p,2);
    deny(fn()=>$stateService->change($case,'cambiar_estado',0,'Inventado'),'gestión rechaza estado inválido');
    $stateService->change($case,'cambiar_estado',0,'Con diligencias');
    check($p->query("SELECT estado FROM accidentes WHERE id=$case")->fetchColumn()==='Con diligencias','responsable actualiza estado en gestión');
    actor($p,2);$service=new ExpedienteAccessService($p);$service->change($case,'compartir',$adj,'');
    actor($p,$adj);check(Access::canEdit($case),'adjunto compartido edita');check($workspaceIds() === [$case] && $dashboard->snapshot(null)['total'] === 1,'adjunto ve solo el expediente compartido sin duplicarlo');$p->exec("UPDATE accidentes SET referencia='Adjunto' WHERE id=$case");
    deny(fn()=>$p->exec("DELETE FROM accidentes WHERE id=$case"),'adjunto no elimina expediente');
    $s=$p->query("SELECT id FROM involucrados_personas WHERE accidente_id=$case LIMIT 1");$ip=(int)$s->fetchColumn();if($ip)deny(fn()=>$p->exec("DELETE FROM involucrados_personas WHERE id=$ip"),'adjunto no elimina registros relacionados');
    actor($p,2);$service->change($case,'revocar',$adj,'');actor($p,$adj);check(!Access::canEdit($case),'revocación inmediata');check(!$workspaceIds() && $dashboard->snapshot(null)['total'] === 0,'revocación retira el expediente del espacio personal');
    actor($p,2);$service->change($case,'compartir',$adj,'');$service->change($case,'transferir',$other,'Derivación de prueba');check(Access::canEdit($case),'origen conserva responsabilidad mientras pendiente');
    actor($p,$other);check(!Access::canEdit($case),'destino aún no edita antes de aceptar');check(!$workspaceIds(),'derivación pendiente no cuenta como responsabilidad aceptada');$service->change($case,'aceptar',0,'');check(Access::canEdit($case),'destino edita después de aceptar');check(in_array($case,$workspaceIds(),true),'derivación aceptada aparece en espacio del destino');
    actor($p,2);check(!Access::canEdit($case),'origen pierde edición');check(!in_array($case,$workspaceIds(),true),'origen deja de contar expediente transferido');actor($p,$adj);check(!Access::canEdit($case),'transferencia cierra colaboraciones');
    actor($p,$secretary);deny(fn()=>$p->exec("UPDATE accidentes SET lugar='Secretaría' WHERE id=$case"),'secretaría no edita');
    actor($p,$guard);$guardia=new GuardiaService($p);
    $input=['fecha_llamada'=>date('Y-m-d\TH:i'),'lugar'=>'Lugar de prueba','descripcion'=>'Comunicación inicial','cod_dep'=>'15','cod_prov'=>'01','cod_dist'=>'01'];
    $call=$guardia->save(0,$input);$newcase=$guardia->assign($call,$other);check($newcase>0,'guardia abre y asigna expediente');
    $guardia->save($call,$input+['referencia'=>'Corrección posterior']);check(true,'guardia corrige después de asignar dentro del plazo');
    deny(fn()=>$p->exec("UPDATE accidentes SET lugar='Guardia' WHERE id=$newcase"),'guardia no edita investigación');
    deny(fn()=>$guardia->assign($call,$other),'asignación duplicada rechazada');
    $p->exec('SET @rbac_migration=1');$p->exec("UPDATE comunicaciones_guardia SET registrado_en=DATE_SUB(NOW(),INTERVAL 12 HOUR) WHERE id=$call");$p->exec('SET @rbac_migration=NULL');
    deny(fn()=>$guardia->save($call,$input),'12 horas exactas: edición bloqueada');
    deny(fn()=>$p->exec("UPDATE comunicaciones_guardia SET lugar='Burlar plazo' WHERE id=$call"),'SQL directo respeta 12 horas');
    deny(fn()=>$p->exec("UPDATE comunicaciones_guardia SET registrado_en=NOW() WHERE id=$call"),'no reinicia el reloj');
    actor($p,1);$guardia->save($call,$input);check(true,'administrador puede corregir fuera del plazo');
    $service->change($case,'eliminar',0,'Prueba recuperable');check(!(bool)$p->query("SELECT COUNT(*) FROM accidentes_activos WHERE id=$case")->fetchColumn(),'eliminado excluido de consultas activas');check((bool)$p->query("SELECT COUNT(*) FROM accidentes WHERE id=$case")->fetchColumn(),'expediente eliminado conserva datos');
    $service->change($case,'restaurar',0,'Restauración de prueba');check((bool)$p->query("SELECT COUNT(*) FROM accidentes_activos WHERE id=$case")->fetchColumn(),'restauración conserva ID');
    check((int)$p->query("SELECT COUNT(*) FROM auditoria WHERE accidente_id=$case AND usuario_id IS NOT NULL")->fetchColumn()>3,'auditoría registra actores y cambios');
    actor($p,$guard);
    $call2=$guardia->save(0,$input);
    $p->exec('SET @rbac_migration=1');$p->exec("UPDATE comunicaciones_guardia SET registrado_en=DATE_SUB(NOW(),INTERVAL 13 HOUR) WHERE id=$call2");$p->exec('SET @rbac_migration=NULL');
    $lateCase=$guardia->assign($call2,$other);check($lateCase>0,'guardia asigna una llamada pendiente después de 12 horas sin editar su contenido');
    actor($p,$other);
    $p->exec("UPDATE accidentes SET latitud=-12.05,longitud=-77.04 WHERE id=$case");$service->change($case,'verificar_ubicacion',0,'');
    check((bool)$p->query("SELECT ubicacion_verificada FROM accidentes WHERE id=$case")->fetchColumn(),'responsable verifica ubicación');
    $p->exec("UPDATE accidentes SET latitud=-12.06 WHERE id=$case");check(!(bool)$p->query("SELECT ubicacion_verificada FROM accidentes WHERE id=$case")->fetchColumn(),'cambiar coordenadas exige nueva verificación');
    actor($p,1);$service->change($case,'eliminar',0,'Verificar documentos ocultos');
    check(!(bool)$p->query("SELECT COUNT(*) FROM oficios_activos WHERE accidente_id=$case")->fetchColumn(),'documentos del expediente eliminado excluidos de consultas');
    $service->change($case,'restaurar',0,'Final de prueba');
    require_once dirname(__DIR__).'/vendor/autoload.php';
    $GLOBALS['accidenteId']=$case;
    $template=new \App\Support\ResponsibleTemplateProcessor(dirname(__DIR__).'/plantillas/citacion_diligencia.docx');
    $doc=tempnam(sys_get_temp_dir(),'uiat_doc_test_');$template->saveAs($doc);$zip=new ZipArchive();$zip->open($doc);$content='';for($i=0;$i<$zip->numFiles;$i++){if(preg_match('~^word/(document|header\d*|footer\d*)\.xml$~',$zip->getNameIndex($i))){$dom=new DOMDocument();$dom->loadXML($zip->getFromIndex($i));$content.=$dom->textContent;}}$zip->close();unlink($doc);
    check(!str_contains(strtoupper($content),'MERINO') && str_contains($content,'Prueba jefe_emi'),'plantilla nueva utiliza el responsable y elimina la firma fija');
    check(!str_contains($content,'986571975'),'plantilla nueva no publica el teléfono fijo anterior');
    $GLOBALS['row']=['responsable_documento'=>json_encode(['nombre'=>'Responsable histórico','grado'=>'ST1.PNP','unidad'=>'DEPIAT'])];
    $template=new \App\Support\ResponsibleTemplateProcessor(dirname(__DIR__).'/plantillas/citacion_diligencia.docx');$doc=tempnam(sys_get_temp_dir(),'uiat_doc_test_');$template->saveAs($doc);$zip=new ZipArchive();$zip->open($doc);$content='';for($i=0;$i<$zip->numFiles;$i++){if(preg_match('~^word/(document|header\d*|footer\d*)\.xml$~',$zip->getNameIndex($i))){$dom=new DOMDocument();$dom->loadXML($zip->getFromIndex($i));$content.=$dom->textContent;}}$zip->close();unlink($doc);
    check(str_contains($content,'Responsable histórico'),'documento conserva su identidad histórica tras transferencia');
    require __DIR__.'/acceso_cip_cases.php';
    echo "PASS: $checks comprobaciones\n";
}finally{
    $p->exec('SET @rbac_migration=NULL,@rbac_assignment=NULL,@rbac_guardia_assign=NULL');$p->exec("USE `$original`");$p->exec("DROP DATABASE IF EXISTS `$test`");
    session_destroy();
}
