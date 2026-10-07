<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../db.php';
$name='rbac_documento_lc_update';
$row=$pdo->query('SHOW CREATE TRIGGER `'.$name.'`')->fetch(PDO::FETCH_ASSOC);$original=$row['SQL Original Statement'];
$old='NOT EXISTS(SELECT 1 FROM involucrados_personas WHERE persona_id=OLD.persona_id AND accidente_id<>@guardia_document_case)';
$new="($old OR EXISTS(SELECT 1 FROM comunicaciones_guardia c WHERE c.accidente_id=@guardia_document_case AND OLD.creado_en>=c.registrado_en AND EXISTS(SELECT 1 FROM auditoria au WHERE au.tabla='documento_lc' AND au.registro_id=OLD.id AND au.accion='INSERT' AND au.usuario_id=@actor_id)))";
if(!str_contains($original,"au.registro_id=OLD.id")){
 $sql=str_replace($old,$new,$original,$count);
 if($count!==1)throw new RuntimeException('Permiso no reconocido');
 $pdo->exec('DROP TRIGGER `'.$name.'`');
 try{$pdo->exec($sql);}catch(Throwable $e){$pdo->exec($original);throw $e;}
}
echo "Edición de licencias propias habilitada.\n";
