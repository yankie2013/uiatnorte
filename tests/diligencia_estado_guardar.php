<?php
declare(strict_types=1);
// In-memory fixture only, including read-state regression cases.
require __DIR__.'/diligencia_estado.php';
$property->setValue(null,$p);
$_SESSION['user']=['id'=>1,'rol'=>'jefe_emi'];
$p->exec('ALTER TABLE diligencias_pendientes ADD COLUMN actualizado_en TEXT');
$p->sqliteCreateFunction('rbac_case',static function($case)use($p){
 if(($_SESSION['user']['rol']??'')==='admin')return 1;
 $s=$p->prepare('SELECT responsable_id FROM accidentes_activos WHERE id=?');$s->execute([$case]);
 return (int)((int)$s->fetchColumn()===(int)($_SESSION['user']['id']??0));
});
$p->exec("INSERT INTO diligencias_pendientes(id,accidente_id,estado,contenido,tipo_diligencia_id,creado_por) VALUES(1000,101,'Pendiente','Estado manual',1,2),(1001,103,'Pendiente','Ajena',1,1)");
$repo=new App\Repositories\DiligenciaInboxRepository($p);
foreach(['En proceso','Realizado','Pendiente'] as $next){
 $saved=$repo->changeState(1000,$next);
 $check($saved['estado']===$next,'Returned saved state '.$next);
 $check($p->query('SELECT estado FROM diligencias_pendientes WHERE id=1000')->fetchColumn()===$next,'Persisted state '.$next);
}
$check($p->query('SELECT actualizado_en FROM diligencias_pendientes WHERE id=1000')->fetchColumn()!==null,'Save timestamp');
$denied=false;try{$repo->changeState(1001,'Realizado');}catch(RuntimeException $e){$denied=$e->getCode()===404;}
$check($denied,'Foreign task ID rejected');
$_SESSION['user']=['id'=>2,'rol'=>'jefe_emi'];$denied=false;
try{$repo->changeState(1000,'Realizado');}catch(RuntimeException $e){$denied=$e->getCode()===404;}
$check($denied,'Second JEFE EMI cannot change first case');
$_SESSION['user']=['id'=>1,'rol'=>'secretaria'];$denied=false;
try{$repo->changeState(1000,'Realizado');}catch(RuntimeException $e){$denied=$e->getCode()===403;}
$check($denied,'Read-only role blocked');
$_SESSION['user']=['id'=>1,'rol'=>'jefe_emi'];$denied=false;
try{$repo->changeState(1000,'injected');}catch(InvalidArgumentException $e){$denied=true;}
$check($denied,'Invalid state rejected');
$p->exec("UPDATE diligencias_pendientes SET estado='Realizado' WHERE id=1");
$denied=false;try{$repo->changeState(1,'Pendiente');}catch(RuntimeException $e){$denied=$e->getCode()===409;}
$check($denied && $p->query('SELECT estado FROM diligencias_pendientes WHERE id=1')->fetchColumn()==='Realizado','Conflicting automatic state rolled back');
$p->beginTransaction();$denied=false;
try{$repo->changeState(1,'Pendiente');}catch(RuntimeException $e){$denied=$e->getCode()===409;}
$check($denied && $p->inTransaction() && $p->query('SELECT estado FROM diligencias_pendientes WHERE id=1')->fetchColumn()==='Realizado','Nested savepoint preserves prior state');
$p->rollBack();
$_POST=['_csrf'=>'invalid'];$denied=false;
try{App\Support\Access::checkCsrf();}catch(RuntimeException $e){$denied=true;}
$check($denied,'Bad CSRF rejected');
$_POST=['_csrf'=>App\Support\Access::csrf()];App\Support\Access::checkCsrf();
$property->setValue(null,null);unset($_SESSION['user']);$_POST=[];
echo "PASS: state persistence, timestamp, assignment, roles, conflicts, transactions and CSRF.\n";
