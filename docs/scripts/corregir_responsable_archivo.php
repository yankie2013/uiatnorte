<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/bootstrap/app.php';
$p=App\Database\Database::connection();
$admin=(int)$p->query("SELECT id FROM usuarios WHERE rol='admin' AND activo=1 ORDER BY id LIMIT 1")->fetchColumn();
if(!$admin)throw new RuntimeException('Se requiere una identidad administrativa activa para registrar la corrección.');
$p->beginTransaction();
try {
    $p->exec('SET @actor_id='.$admin.', @rbac_assignment=1');
    $rows=$p->query("SELECT a.id,t.origen_id FROM accidentes a JOIN usuarios actual ON actual.id=a.responsable_id JOIN expediente_transferencias t ON t.id=(SELECT MAX(x.id) FROM expediente_transferencias x WHERE x.accidente_id=a.id AND x.tipo='archivo' AND x.estado='aceptada') JOIN usuarios jefe ON jefe.id=t.origen_id WHERE actual.rol IN ('secretaria','administracion') AND a.responsable_id=t.destino_id AND jefe.rol='jefe_emi' FOR UPDATE")->fetchAll();
    foreach($rows as $row){
        $p->prepare('UPDATE accidentes SET responsable_id=? WHERE id=?')->execute([$row['origen_id'],$row['id']]);
        $p->prepare("INSERT INTO auditoria(usuario_id,tabla,registro_id,accidente_id,accion,motivo) VALUES(?,'accidentes',?,?,'corregir_responsable_archivo','Se conserva al JEFE EMI que derivó la investigación; la aceptación de Archivo permanece en expediente_transferencias.')")->execute([$admin,$row['id'],$row['id']]);
    }
    $p->commit();
    echo 'Responsables de archivo corregidos: '.count($rows)."\n";
} catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
finally{$p->exec('SET @actor_id=NULL,@rbac_assignment=NULL');}
