<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../db.php';
$row=$pdo->query('SHOW CREATE FUNCTION rbac_guardia_draft')->fetch(PDO::FETCH_ASSOC);
$original=$row['Create Function'];
$sql=str_replace(['AND a.responsable_id IS NULL ','AND c.jefe_id IS NULL '],'',$original);
if($sql!==$original){
 $pdo->exec('DROP FUNCTION rbac_guardia_draft');
 try{$pdo->exec($sql);}catch(Throwable $e){$pdo->exec($original);throw $e;}
}
foreach(['involucrados_personas'=>['update'],'involucrados_vehiculos'=>['update'],'accidente_modalidad'=>['update','delete'],'accidente_consecuencia'=>['update','delete']] as $table=>$events){
 foreach($events as $event){
  $name='rbac_'.$table.'_'.$event;
  $row=$pdo->query('SHOW CREATE TRIGGER `'.$name.'`')->fetch(PDO::FETCH_ASSOC);$original=$row['SQL Original Statement'];
  if(str_contains($original,'rbac_guardia_draft'))continue;
  $draft=$event==='delete'?'rbac_guardia_draft(OLD.accidente_id)':'rbac_guardia_draft(NEW.accidente_id) AND NEW.accidente_id=OLD.accidente_id';
  $sql=preg_replace_callback('/IF NOT COALESCE\(\((.*?)\),0\) THEN/s',static fn($m)=>'IF NOT COALESCE((('.$m[1].') OR ('.$draft.')),0) THEN',$original,1,$count);
  if($count!==1)throw new RuntimeException('Permiso no reconocido: '.$name);
  $pdo->exec('DROP TRIGGER `'.$name.'`');
  try{$pdo->exec($sql);}catch(Throwable $e){$pdo->exec($original);throw $e;}
 }
}
echo "Edición de guardia disponible durante las 12 horas originales.\n";
