<?php
// Ejecutar desde CLI; extiende únicamente los cuatro permisos de documentos.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../db.php';
foreach(['documento_lc','documento_vehiculo'] as $table){
 foreach(['insert','update'] as $event){
  $name='rbac_'.$table.'_'.$event;
  $row=$pdo->query('SHOW CREATE TRIGGER `'.$name.'`')->fetch(PDO::FETCH_ASSOC);
  $sql=$row['SQL Original Statement'];
  if(str_contains($sql,'@guardia_document_case'))continue;
  $draft='rbac_guardia_draft(@guardia_document_case)';
  if($table==='documento_lc'){
   $draft.=" AND EXISTS(SELECT 1 FROM involucrados_personas ip JOIN participacion_persona r ON r.Id=ip.rol_id WHERE ip.accidente_id=@guardia_document_case AND ip.persona_id=NEW.persona_id AND LOWER(r.Nombre)='conductor')";
   if($event==='update')$draft.=' AND NEW.persona_id=OLD.persona_id AND NOT EXISTS(SELECT 1 FROM involucrados_personas WHERE persona_id=OLD.persona_id AND accidente_id<>@guardia_document_case)';
  }else{
   $draft.=' AND EXISTS(SELECT 1 FROM involucrados_vehiculos_activos iv WHERE iv.id=NEW.involucrado_vehiculo_id AND iv.accidente_id=@guardia_document_case AND iv.vehiculo_id=NEW.vehiculo_id)';
   if($event==='update')$draft.=' AND NEW.involucrado_vehiculo_id=OLD.involucrado_vehiculo_id AND NEW.vehiculo_id=OLD.vehiculo_id';
  }
  $sql=preg_replace_callback('/IF NOT COALESCE\(\((.*?)\),0\) THEN/s',static fn($m)=>'IF NOT COALESCE((('.$m[1].') OR ('.$draft.')),0) THEN',$sql,1,$count);
  if($count!==1)throw new RuntimeException('No se reconoció el permiso '.$name);
  $pdo->exec('DROP TRIGGER `'.$name.'`');
  try{$pdo->exec($sql);}catch(Throwable $e){$pdo->exec($row['SQL Original Statement']);throw $e;}
 }
}
echo "Permisos de documentos de guardia actualizados.\n";
