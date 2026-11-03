<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/OAuthCore.php';
require dirname(__DIR__).'/src/CaseQueries.php';
require dirname(__DIR__).'/src/IntegralQueries.php';
use Uiat\Connector\{Actor,CaseQueries,IntegralQueries};
$n=0;function checkIntegral(bool $v,string $s):void{global $n;if(!$v)throw new RuntimeException($s);$n++;}
function denyIntegral(callable $f,string $s):void{try{$f();}catch(Throwable){checkIntegral(true,$s);return;}throw new RuntimeException('Faltó rechazo: '.$s);}
$native=($argv[1]??'')==='--mariadb-ficticia';
if($native){
 $config=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
 if(!preg_match('~^mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=(uiat_integral_t_[a-f0-9]{12});charset=utf8mb4$~D',$config['dsn'],$m)||$config['reader']!==$m[1].'_r')throw new RuntimeException('Solo fixture aislada.');
 $db=new PDO($config['dsn'],$config['reader'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
}else{$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec(file_get_contents(__DIR__.'/integral_fixture.sql'));}
$q=new CaseQueries($db,true,true);$chief=new Actor(10,'jefe_emi',1);
foreach(array_keys(IntegralQueries::SECTIONS) as $section){
 $r=$q->integral($chief,1,$section);checkIntegral(count($r['registros'])===1,'Sección '.$section);
 checkIntegral($r['registros'][0]['fuente']['expediente_id']===1,'Fuente '.$section);
 checkIntegral($r['revision']['completitud_institucional_evaluada']===false,'No acredita completitud '.$section);
 denyIntegral(fn()=>$q->integral($chief,2,$section),'Caso ajeno '.$section);
 denyIntegral(fn()=>$q->integral($chief,999,$section),'Caso inexistente '.$section);
 denyIntegral(fn()=>$q->integral(new Actor(13,'guardia',1),1,$section),'Rol rechazado '.$section);
}
foreach([new Actor(11,'adjunto',1),new Actor(12,'secretaria',1),new Actor(99,'admin',1)] as $a)checkIntegral(count($q->integral($a,1,'ficha')['registros'])===1,'Rol autorizado');
foreach([new Actor(14,'adjunto',1),new Actor(15,'secretaria',1),new Actor(0,'admin',1)] as $a)denyIntegral(fn()=>$q->integral($a,1,'ficha'),'Revocado/pendiente/cero');
$p=$q->integral($chief,1,'participantes')['registros'][0];checkIntegral((int)$p['edad_snapshot']===29&&(int)$p['edad_actual_registrada']===30,'Edades distintas');
checkIntegral(in_array('fecha_nacimiento',$p['campos_vacios'],true),'Vacíos explícitos');checkIntegral($q->integral($chief,1,'ficha')['registros'][0]['lugar']==='Lugar ficticio','Devuelve lugar real registrado');
if($native){
 denyIntegral(fn()=>$db->exec("UPDATE accidentes_activos SET lugar='RECHAZADO' WHERE id=1"),'Sin escritura');
 denyIntegral(fn()=>$db->query('SELECT foto_path FROM personas'),'Sin rutas privadas');
 echo 'OK: '.$n." comprobaciones MariaDB integrales con cuenta limitada y datos ficticios.\n";exit;
}
$db->exec("INSERT INTO diligencias_pendientes_activos(id,accidente_id,estado) VALUES(4,1,'Pendiente'),(5,1,'En proceso');");
$r=$q->integral($chief,1,'diligencias',1);checkIntegral($r['siguiente_id']===1,'Cursor primera página');
$r=$q->integral($chief,1,'diligencias',1,1);checkIntegral($r['siguiente_id']===4&&(int)$r['registros'][0]['id']===4,'Cursor sin repetir');
$r=$q->integral($chief,1,'diligencias',1,4);checkIntegral($r['siguiente_id']===null&&(int)$r['registros'][0]['id']===5,'Última página');
$r=$q->integral($chief,1,'diligencias',1,5);checkIntegral($r['estado']==='sin_mas_registros','Vacío después de cursor');
$db->exec('DELETE FROM actas_visualizacion');checkIntegral($q->integral($chief,1,'visualizaciones')['estado']==='sin_registros','Sin registros no significa completos');
$st=$db->prepare('UPDATE documentos_recibidos_activos SET contenido=? WHERE id=1');$st->execute([str_repeat('á',4000)]);
$r=$q->integral($chief,1,'documentos_recibidos')['registros'][0];checkIntegral(strlen($r['contenido'])===6000&&$r['campos_recortados']===['contenido'],'Recorte explícito UTF8');
foreach([['otro',20,0],['ficha',0,0],['ficha',51,0],['ficha',20,-1],["ficha' OR 1=1",20,0]] as [$s,$l,$a])denyIntegral(fn()=>$q->integral($chief,1,$s,$l,$a),'Entrada inválida');
$db->exec("UPDATE expediente_colaboradores SET revocado_en='2026-01-01' WHERE usuario_id=11");denyIntegral(fn()=>$q->integral(new Actor(11,'adjunto',1),1,'ficha'),'Revocación efectiva');
echo 'OK: '.$n." comprobaciones integrales con datos ficticios.\n";
