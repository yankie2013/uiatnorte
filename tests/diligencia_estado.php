<?php
declare(strict_types=1);
// Reuse the isolated SQLite fixture; this never reads or changes the application's database.
require __DIR__.'/diligencias_inbox_repository.php';
$property->setValue(null,$p);
$_SESSION['user']=['id'=>1,'rol'=>'jefe_emi'];
$p->sqliteCreateFunction('JSON_CONTAINS',static fn($json,$value)=>(int)in_array(json_decode((string)$value,true),json_decode((string)$json,true)?:[],true));
$p->sqliteCreateFunction('JSON_LENGTH',static fn($json)=>count(json_decode((string)$json,true)?:[]));
$p->exec("ALTER TABLE diligencias_pendientes ADD COLUMN oficio_id INTEGER;
ALTER TABLE diligencias_pendientes ADD COLUMN citacion_id INTEGER;
ALTER TABLE diligencias_pendientes ADD COLUMN citacion_ids TEXT;
ALTER TABLE diligencias_pendientes ADD COLUMN documento_realizado TEXT;
ALTER TABLE diligencias_pendientes ADD COLUMN documentos_recibidos TEXT;
CREATE TABLE oficios_activos(id INTEGER PRIMARY KEY,accidente_id INTEGER,estado TEXT);
CREATE TABLE citacion(id INTEGER PRIMARY KEY,accidente_id INTEGER,estado_realizacion TEXT);
CREATE VIEW citacion_activos AS SELECT id,accidente_id FROM citacion;
CREATE TABLE documentos_recibidos_activos(id INTEGER PRIMARY KEY,accidente_id INTEGER,referencia_oficio_id INTEGER);
INSERT INTO oficios_activos VALUES(10,101,'BORRADOR'),(11,101,'ENVIADO'),(12,101,'ANULADO'),(99,103,'ENVIADO');
INSERT INTO citacion VALUES(20,101,'pendiente'),(21,101,'realizada'),(22,101,'suspendida'),(24,101,'cancelada'),(25,101,'realizada'),(26,101,'pendiente'),(27,101,'realizada'),(28,101,'realizada'),(29,101,'realizada'),(31,101,'pendiente');
INSERT INTO documentos_recibidos_activos VALUES(1,101,11),(2,103,10);
DELETE FROM diligencias_pendientes;");
$insert=$p->prepare("INSERT INTO diligencias_pendientes(id,accidente_id,estado,contenido,tipo_diligencia_id,creado_por,oficio_id,citacion_id,citacion_ids,documento_realizado,documentos_recibidos) VALUES(?,101,?,'Prueba de estado',1,2,?,?,?,?,?)");
// Stored state, office, citation, additional citations, document text, reply text, expected state.
$cases=[
 1=>['Pendiente',10,null,null,null,null,'En proceso'],
 2=>['Pendiente',null,20,null,null,null,'En proceso'],
 3=>['Pendiente',11,null,null,null,null,'Realizado'],
 4=>['Pendiente',null,21,null,null,null,'Realizado'],
 5=>['Realizado',null,null,null,null,null,'Realizado'],
 6=>['Pendiente',null,22,null,null,null,'Pendiente'],
 7=>['Pendiente',null,23,null,null,null,'Pendiente'],
 8=>['Pendiente',99,null,null,null,null,'Pendiente'],
 9=>['Cancelado',11,null,null,null,null,'Cancelado'],
 10=>['Pendiente',null,24,null,null,null,'Pendiente'],
 11=>['Pendiente',null,null,null,'Acta registrada',null,'En proceso'],
 12=>['Pendiente',null,null,null,null,'Respuesta recibida','Realizado'],
 13=>['Pendiente',12,null,null,null,null,'Pendiente'],
 14=>['Pendiente',null,25,'[25,26]',null,null,'En proceso'],
 15=>['Pendiente',null,27,'[27,28]',null,null,'Realizado'],
 16=>['Pendiente',null,29,'[29,30]',null,null,'Pendiente'],
 17=>['Realizado',null,22,null,null,null,'Pendiente'],
 18=>['Pendiente',null,31,'[30,31]',null,null,'En proceso'],
];
foreach($cases as $id=>$case)$insert->execute([$id,...array_slice($case,0,6)]);
$repo=new App\Repositories\DiligenciaInboxRepository($p);
foreach($cases as $id=>$case){
 $found=$repo->find($id);
 if($case[6]==='Cancelado'){$check($found===null,'Canceled excluded');continue;}
 $check($found && $found['estado']===$case[6],'Effective state for task '.$id);
}
$counts=$repo->counts();
$check(array_sum($counts)===17,'Counters exclude canceled');
foreach(App\Repositories\DiligenciaInboxRepository::STATES as $state){
 $result=$repo->search('',$state);
 $check($result['total']===$counts[$state],'Filtered count '.$state);
 foreach($result['rows'] as $row)$check($row['estado']===$state,'Filtered row '.$state);
}
// Re-evaluate live after changes; metadata may be cached, but evidence must never be cached.
$p->exec("UPDATE citacion SET estado_realizacion='realizada' WHERE id=20");
$check($repo->find(2)['estado']==='Realizado','Citation completion updates immediately');
$p->exec("UPDATE citacion SET estado_realizacion='suspendida' WHERE id=20");
$check($repo->find(2)['estado']==='Pendiente','Suspension reopens task');
$p->exec("DELETE FROM citacion WHERE id=20");
$check($repo->find(2)['estado']==='Pendiente','Deleted citation reopens task');
$p->exec("INSERT INTO documentos_recibidos_activos VALUES(3,101,10)");
$check($repo->find(1)['estado']==='Realizado','Own office response completes task');
$p->exec("DELETE FROM documentos_recibidos_activos WHERE id=3");
$check($repo->find(1)['estado']==='En proceso','Removing response returns linked office to processing');
$p->exec("INSERT INTO oficios_activos VALUES(13,101,'ENVIADO'); UPDATE diligencias_pendientes SET oficio_id=13 WHERE id=7");
$check($repo->find(7)['estado']==='En proceso','Valid office remains processing even with deleted citation');
$property->setValue(null,null);unset($_SESSION['user']);
echo "PASS: calculated states, own-document scope, multiple citations, responses, reopening and matching filters/counters.\n";
