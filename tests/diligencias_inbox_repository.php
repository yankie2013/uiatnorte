<?php
declare(strict_types=1);
// Synthetic in-memory database only. Never opens the application's configured database.
require dirname(__DIR__).'/bootstrap/app.php';
use App\Database\Database;
use App\Repositories\DiligenciaInboxRepository;
$p = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$p->sqliteCreateFunction('CONCAT_WS',static fn($separator,...$parts)=>implode($separator,array_filter($parts,static fn($part)=>$part!==null)));
$property = new ReflectionProperty(Database::class,'connection');$property->setValue(null,$p);
$p->exec("CREATE TABLE accidentes (id INTEGER PRIMARY KEY,responsable_id INTEGER,registro_sidpol TEXT,lugar TEXT,fecha_accidente TEXT,comisaria_id INTEGER,cod_dep TEXT,cod_prov TEXT,cod_dist TEXT,eliminado_en TEXT);
CREATE VIEW accidentes_activos AS SELECT * FROM accidentes WHERE eliminado_en IS NULL;
CREATE TABLE diligencias_pendientes (id INTEGER PRIMARY KEY,accidente_id INTEGER,estado TEXT,contenido TEXT,tipo_diligencia_id INTEGER,creado_por INTEGER);
CREATE VIEW diligencias_pendientes_activos AS SELECT * FROM diligencias_pendientes;
CREATE TABLE tipo_diligencia(id INTEGER PRIMARY KEY,nombre TEXT);
CREATE TABLE comisarias(id INTEGER PRIMARY KEY,nombre TEXT);
CREATE TABLE ubigeo_distrito(cod_dep TEXT,cod_prov TEXT,cod_dist TEXT,nombre TEXT,PRIMARY KEY(cod_dep,cod_prov,cod_dist));
CREATE TABLE involucrados_personas_activos(accidente_id INTEGER,persona_id INTEGER);
CREATE TABLE personas(id INTEGER PRIMARY KEY,nombres TEXT,apellido_paterno TEXT,apellido_materno TEXT);
CREATE TABLE involucrados_vehiculos_activos(accidente_id INTEGER,vehiculo_id INTEGER);
CREATE TABLE vehiculos(id INTEGER PRIMARY KEY,placa TEXT);
INSERT INTO comisarias VALUES(1,'Comisaría Uno'),(2,'Comisaría Dos');
INSERT INTO ubigeo_distrito VALUES('15','01','01','Independencia'),('15','01','02','Comas');
INSERT INTO tipo_diligencia VALUES(1,'Peritaje vehicular'),(2,'Declaración');
INSERT INTO accidentes VALUES(101,1,'SID-A','Av. Norte',NULL,1,'15','01','01',NULL),(102,1,'SID-B','Av. Sur',NULL,2,'15','01','02',NULL),(103,2,'SID-C','Av. Ajena',NULL,1,'15','01','01',NULL),(104,1,'SID-D','Eliminado',NULL,1,'15','01','01','2026-01-01');
INSERT INTO diligencias_pendientes VALUES(1,101,'Pendiente','Medir vía',1,2),(2,101,'En proceso','Examinar auto',1,2),(3,102,'Pendiente','Recibir declaración',2,1),(4,103,'Pendiente','Caso de otro jefe',1,1),(5,101,'Realizado','Hecho',1,1),(6,101,'Cancelado','Cancelado',1,1),(7,104,'Pendiente','Caso eliminado',1,1);
INSERT INTO personas VALUES(1,'Ana','Rojas','Paz'),(2,'Luis','Rojas','Paz'),(3,'Julia','Rojas','Paz');
INSERT INTO involucrados_personas_activos VALUES(101,1),(101,2),(101,3);
INSERT INTO vehiculos VALUES(1,'ABC123'),(2,'ABC456'),(3,'ABC789');
INSERT INTO involucrados_vehiculos_activos VALUES(101,1),(101,2),(101,3);");
$s=$p->prepare("INSERT INTO diligencias_pendientes VALUES(?,102,'Pendiente','Medición',1,2)");
for($id=100;$id<145;$id++)$s->execute([$id]);
$repo=new DiligenciaInboxRepository($p);
$count=0;
$check=static function(bool $condition,string $message)use(&$count):void{$count++;if(!$condition)throw new RuntimeException($message);};
$_SESSION['user']=['id'=>1,'rol'=>'jefe_emi'];
$r=$repo->search('','');
$check($r['total']===49,'Both pending states across all assigned cases');
$check(count($r['rows'])===20 && (int)$r['selected']['id']===144,'First selection and stable order');
$all=[];for($page=1;$page<=$r['pages'];$page++)$all=array_merge($all,$repo->search('','',$page)['rows']);
$ids=array_column($all,'id');
$check(count($ids)===count(array_unique($ids)),'No duplicates between pages');
$check(count(array_unique(array_column($all,'accidente_id')))===2,'Multiple cases in same inbox');
$check($repo->find(1)!==null,'Task created by another user on own case must be included');
$check($repo->find(4)===null,'Foreign case cannot be read through manipulated task ID');
$check($repo->find(5)!==null && $repo->find(6)===null && $repo->find(7)===null,'Completed included; canceled and deleted cases excluded');
foreach(['Rojas','ABC','SID-A','101','Independencia','Comisaría Uno','Medir vía'] as $q){
 $matches=$repo->search($q,'');
 $expected=$q==='Medir vía'?1:3;
 $check($matches['total']===$expected,'Search and deduplication: '.$q);
}
$check($repo->search('','En proceso')['total']===1,'State count');
$check($repo->search('','Pendiente')['total']===47,'Pending state count');
$check($repo->search('no matches','')['selected']===null,'Empty search selection');
$r=$repo->search('Peritaje','',1,1,true);
$check((int)$r['selected']['id']===1 && $r['page']===3,'Retain selection after filtering and locate correct page');
$r=$repo->search('Declaración','',1,1,true);
$check((int)$r['selected']['id']===3,'Select first row if old selection filtered out');
$check($repo->search('','',1,4,true)['selected']['accidente_id']!==103,'Foreign selection parameter cannot expand scope');
$_SESSION['user']=['id'=>2,'rol'=>'jefe_emi'];
$check($repo->search('','')['total']===1 && $repo->find(1)===null && $repo->find(4)!==null,'Second JEFE EMI isolated from first');
$_SESSION['user']=['id'=>3,'rol'=>'admin'];
$check($repo->search('','')['total']===50,'Admin keeps existing full scope');
foreach(['adjunto','secretaria','administracion','guardia','viewer','editor'] as $role){
 $_SESSION['user']=['id'=>1,'rol'=>$role];$blocked=false;
 try{$repo->search('','');}catch(RuntimeException $e){$blocked=true;}
 $check($blocked,'No new access granted to '.$role);
}
$_SESSION['user']=['id'=>1,'rol'=>'jefe_emi'];$blocked=false;
try{$repo->search('','Cancelado');}catch(InvalidArgumentException $e){$blocked=true;}
$check($blocked,'Invalid inbox state rejected');
$property->setValue(null,null);unset($_SESSION['user']);
echo "PASS: $count checks, two JEFE EMI, assignment, filters, counts, duplicates, selection and permissions.\n";
