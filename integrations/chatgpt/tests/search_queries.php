<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
foreach(['OAuthCore','CaseQueries','Consent','SessionBridge','CameraDrafts','OfficeQueries','OfficeDrafts','McpKernel','HttpKernel'] as $c)require dirname(__DIR__).'/src/'.$c.'.php';
use Uiat\Connector\{Actor,CaseQueries,Users,McpKernel,OAuthCore,State,Audit};
$n=0;function okSearch(bool $v,string $s):void{global $n;if(!$v)throw new RuntimeException($s);$n++;}
function deniesSearch(callable $f,string $s):void{try{$f();}catch(Throwable $e){okSearch(true,$s);return;}throw new RuntimeException('Se esperaba rechazo: '.$s);}
$native=($argv[1]??'')==='--mariadb-ficticia';
if($native){
 $settings=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
 if(!preg_match('~^mysql:unix_socket=[^;]+;dbname=(uiat_web_t_[a-f0-9]{12});charset=utf8mb4$~D',$settings['dsn'],$match)||$settings['reader']!==$match[1].'_r')throw new RuntimeException('Solo fixture aislada.');
 $db=new PDO($settings['dsn'],$settings['reader'],$settings['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
}else{
 $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec(file_get_contents(__DIR__.'/office_fixture.sql'));$db->exec(file_get_contents(__DIR__.'/search_fixture.sql'));
}
// SQLite approximation only. Native MariaDB/utf8mb4_unicode_ci is covered separately by HTTPS fixture tests.
if(!$native)$db->sqliteCreateFunction('LOWER',static fn($v)=>strtolower(strtr((string)$v,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','ñ'=>'n','Ñ'=>'n'])),1);
$q=new CaseQueries($db,true,true);$users=new Users($db);$chief=$users->active(10);$admin=$users->active(99);$adj=$users->active(11);$archive=$users->active(12);
$ids=static fn($a,string $s,string $type='sidpol',array $f=[])=>array_map('intval',array_column($q->buscar($a,$s,50,0,$type,$f)['registros'],'id'));
okSearch($ids($chief,'FIC')===[1,4,5],'SIDPOL compatible');
okSearch($ids($chief,'Jose','persona')===[1],'nombre sin tilde');
okSearch($ids($chief,'JOSÉ','persona')===[1],'nombre mayúscula');
okSearch($ids($chief,'Fuente','persona')===[1,4],'apellido');
okSearch($ids($chief,'pena jose','persona')===[1],'orden libre y apellidos');
okSearch($ids($chief,'Jose Bravo','persona')===[],'términos en una misma persona');
okSearch($ids($chief,'Revocada','persona')===[],'enlace de persona eliminado');
okSearch($ids($chief,"O'Connor",'persona')===[5],'apóstrofo literal');
okSearch($ids($chief,"Jose' OR 'x",'persona')===[],'comillas no alteran SQL');
okSearch($ids($chief,'TEST001','placa')===[1],'placa sin guion');
okSearch($ids($chief,'test-001','placa')===[1],'placa sin distinción de mayúsculas');
okSearch($ids($chief,'XYZ','placa')===[4,5],'placa parcial');
okSearch($ids($chief,'AJENO001','placa')===[],'no filtra vehículo ajeno');
okSearch($ids($chief,'OCULTO001','placa')===[],'vehículo eliminado');
okSearch($ids($chief,'Distrito Ficticio','distrito')===[1,5],'distrito por ubigeo completo');
okSearch($ids($chief,'Distrito Cruzado','distrito')===[],'ubigeo no mezcla provincias');
okSearch($ids($chief,'ensayo','distrito')===[4],'distrito parcial');
okSearch($ids($chief,'Prueba','comisaria')===[1,5],'comisaría');
okSearch($ids($chief,'Flores','comisaria')===[4],'otra comisaría');
foreach(['persona'=>'Jose','placa'=>'AJENO001','distrito'=>'Ficticio','comisaria'=>'Prueba','todos'=>'Jose'] as $type=>$term){okSearch(!in_array(2,$ids($chief,$term,$type),true),'ajeno excluido '.$type);okSearch(!in_array(3,$ids($admin,$term,$type),true),'eliminado excluido '.$type);}
okSearch($ids($admin,'Jose','persona')===[1,2],'admin ve activos autorizados');
okSearch($ids($adj,'Fuente','persona')===[1],'colaborador vigente');
if(!$native){$db->exec("UPDATE expediente_colaboradores SET revocado_en='2000-01-01'");okSearch($ids($adj,'Fuente','persona')===[],'revocación efectiva');}
okSearch($ids($archive,'Jose','persona')===[2],'archivo aceptado solamente');
okSearch($ids(new Actor(0,'admin',4),'Jose','todos')===[],'identidad no válida');
okSearch($ids(new Actor(13,'guardia',4),'Jose','todos')===[],'rol no autorizado');
okSearch($ids($chief,'Fuente','persona',['distrito'=>'Ficticio'])===[1],'persona y distrito');
okSearch($ids($chief,'Fuente','persona',['placa'=>'XYZ123','comisaria'=>'Flores'])===[4],'persona placa y comisaría');
okSearch($ids($chief,'Fuente','persona',['placa'=>'AJENO001'])===[],'filtros no mezclan expedientes');
okSearch($ids($chief,'Prueba','todos')===[1,5],'búsqueda todos');
okSearch($ids($chief,'José Fuente','todos')===[1],'todos con varios términos');
$page=$q->buscar($chief,'FIC',1,0);okSearch(count($page['registros'])===1&&$page['siguiente_id']===1,'primera página');$next=$q->buscar($chief,'FIC',1,$page['siguiente_id']);okSearch((int)$next['registros'][0]['id']===4&&$next['siguiente_id']===4,'cursor sin duplicados');
$page=$q->buscar($chief,'Fuente',1,0,'persona');$next=$q->buscar($chief,'Fuente',1,$page['siguiente_id'],'persona');okSearch((int)$next['registros'][0]['id']===4&&$next['siguiente_id']===null,'paginación de personas');
foreach($q->buscar($chief,'Fuente',50,0,'persona')['registros'] as $row)okSearch(array_keys($row)===['id','sidpol','registro_sidpol','fecha_accidente','estado'],'respuesta mínima sin identidad de terceros');
foreach(['ab',' Jose','Jose ','%%%','___',"x;DROP",str_repeat('x',101),'a b c d e f g h i','---'] as $bad)deniesSearch(fn()=>$q->buscar($chief,$bad),'consulta inválida');
deniesSearch(fn()=>$q->buscar($chief,'Jose',20,0,'num_doc'),'tipo cerrado');deniesSearch(fn()=>$q->buscar($chief,'Jose',20,0,'persona',['usuario_id'=>'99']),'filtros cerrados');deniesSearch(fn()=>$q->buscar($chief,'ABC/123',20,0,'placa'),'placa inválida');deniesSearch(fn()=>$q->buscar($chief,'Jose',51),'límite');deniesSearch(fn()=>$q->buscar($chief,'Jose',20,-1),'cursor');
echo "$n comprobaciones de búsqueda correctas; solo datos ficticios.\n";
