<?php
declare(strict_types=1);
// Entirely fictional, in-memory database. Does not bootstrap or read .env.
require_once dirname(__DIR__) . '/app/Api/ReadApi.php';
use App\Api\ReadApi;
use App\Api\ApiError;
$n = 0;
function check(bool $value): void { global $n; $n++; if (!$value) throw new RuntimeException('Assertion ' . $n); }
function denied(callable $fn, int $status): void { try { $fn(); } catch (ApiError $e) { check($e->status === $status); return; } throw new RuntimeException('Expected denial'); }
$p = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec("CREATE TABLE accidentes(id INTEGER, sidpol TEXT, registro_sidpol TEXT, fecha_accidente TEXT, estado TEXT, responsable_id INTEGER, eliminado_en TEXT);
CREATE VIEW accidentes_activos AS SELECT * FROM accidentes WHERE eliminado_en IS NULL;
CREATE TABLE expediente_colaboradores(accidente_id INTEGER, usuario_id INTEGER, revocado_en TEXT);
CREATE TABLE expediente_transferencias(accidente_id INTEGER,destino_id INTEGER,tipo TEXT,estado TEXT);
CREATE TABLE diligencias_pendientes(id INTEGER,accidente_id INTEGER,tipo_diligencia_id INTEGER,estado TEXT,eliminado_en TEXT);
CREATE VIEW diligencias_pendientes_activos AS SELECT * FROM diligencias_pendientes WHERE eliminado_en IS NULL;
INSERT INTO accidentes VALUES(1,'FIC-001','TEST-001','2000-01-01','Pendiente',10,NULL),(2,'FIC-002','TEST-002','2000-01-01','Resuelto',20,NULL),(3,'FIC-003','TEST-003','2000-01-01','Pendiente',10,'2000-01-01');
INSERT INTO expediente_colaboradores VALUES(1,11,NULL),(2,11,'2000-01-01');
INSERT INTO expediente_transferencias VALUES(2,12,'archivo','aceptada'),(1,12,'archivo','pendiente');
INSERT INTO diligencias_pendientes VALUES(1,1,1,'Pendiente',NULL),(2,2,1,'Realizado',NULL),(3,1,1,'Cancelado','2000-01-01');");
$api = new ReadApi($p);
$chief = ['id'=>10,'rol'=>'jefe_emi'];
$admin = ['id'=>99,'rol'=>'admin'];
check(count($api->handle('buscar_expedientes',['q'=>'FIC'], $chief)['datos']) === 1);
check(count($api->handle('buscar_expedientes',['q'=>'FIC'], $admin)['datos']) === 2);
check($api->handle('consultar_expediente',['expediente_id'=>'1'],$chief)['datos']['id'] === 1);
foreach (['2','3','999'] as $id) denied(fn()=>$api->handle('consultar_expediente',['expediente_id'=>$id],$chief),404);
check(count($api->handle('consultar_diligencias',['expediente_id'=>'1'],$chief)['datos'])===1);
denied(fn()=>$api->handle('consultar_diligencias',['expediente_id'=>'2'],$chief),404);
check($api->handle('consultar_expediente',['expediente_id'=>'1'],['id'=>11,'rol'=>'adjunto'])['datos']['id'] === 1);
$p->exec("UPDATE expediente_colaboradores SET revocado_en='2000-01-01'");
denied(fn()=>$api->handle('consultar_expediente',['expediente_id'=>'1'],['id'=>11,'rol'=>'adjunto']),404);
foreach (['secretaria','administracion'] as $role) {
 check($api->handle('consultar_expediente',['expediente_id'=>'2'],['id'=>12,'rol'=>$role])['datos']['id']===2);
 denied(fn()=>$api->handle('consultar_expediente',['expediente_id'=>'1'],['id'=>12,'rol'=>$role]),404);
}
foreach (['viewer','editor','guardia','unknown'] as $role) check($api->handle('buscar_expedientes',['q'=>'FIC'],['id'=>10,'rol'=>$role])['datos']===[]);
foreach (['1 OR 1=1','0','-1','01',[], '2147483648'] as $id) denied(fn()=>$api->handle('consultar_expediente',['expediente_id'=>$id],$admin),422);
foreach ([['q'=>"FIC' OR 1=1 --"],['q'=>'%%%'],['q'=>[]],['q'=>'FI'],['q'=>'FIC','limite'=>'51'],['q'=>'FIC','usuario_id'=>'99']] as $query) denied(fn()=>$api->handle('buscar_expedientes',$query,$admin),422);
$page = $api->handle('buscar_expedientes',['q'=>'FIC','limite'=>'1'],$admin);
check($page['siguiente_id']===1);
check($api->handle('buscar_expedientes',['q'=>'FIC','despues_id'=>'1'],$admin)['datos'][0]['id']===2);
check(array_keys($api->handle('consultar_expediente',['expediente_id'=>'1'],$admin)['datos'])===['id','sidpol','registro_sidpol','fecha_accidente','estado']);
$user = $chief + ['activo'=>1,'must_change_password'=>0,'auth_version'=>4];
$session = ['user'=>['id'=>10,'auth_version'=>4],'api_authenticated_at'=>1000,'api_last_seen'=>1000];
check(ReadApi::authenticate($session,$user,1100)['id']===10);
denied(fn()=>ReadApi::authenticate($session,false,1100),401);
foreach (['activo'=>0,'must_change_password'=>1,'auth_version'=>5,'id'=>20] as $key=>$value) denied(fn()=>ReadApi::authenticate($session,array_replace($user,[$key=>$value]),1100),401);
denied(fn()=>ReadApi::authenticate($session,$user,1900),401);
denied(fn()=>ReadApi::authenticate(array_replace($session,['api_last_seen'=>29799]),$user,29800),401);
denied(fn()=>ReadApi::authenticate(['user'=>$session['user']],$user,1100),401);
denied(fn()=>ReadApi::authenticate($session,array_replace($user,['rol'=>'guardia']),1100),403);
echo "$n comprobaciones correctas; datos ficticios en memoria.\n";
