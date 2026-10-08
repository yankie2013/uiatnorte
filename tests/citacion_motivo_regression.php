<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
use App\Repositories\CitacionRepository;
use App\Services\CitacionService;
use App\Support\CitacionMotivo;
function checkMotivo(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$p=App\Database\Database::connection();
App\Support\Auth::startSession();
$sessionBefore=$_SESSION;
$p->beginTransaction();
try {
 $actor=$p->query("SELECT * FROM usuarios WHERE activo=1 AND rol='admin' ORDER BY id LIMIT 1")->fetch();
 checkMotivo((bool)$actor,'Se requiere un administrador activo.');
 $_SESSION['user']=$actor;$p->exec('SET @actor_id='.(int)$actor['id']);
 $person=$p->query('SELECT accidente_id,fuente,fuente_id FROM v_accidente_personas_vinculadas ORDER BY accidente_id DESC LIMIT 1')->fetch();
 checkMotivo((bool)$person,'Se requiere una persona vinculada.');
 $service=new CitacionService(new CitacionRepository($p));
 $motivo='Recibir la declaración respecto a los hechos investigados y revisar los documentos aportados.';
 $input=['persona'=>$person['fuente'].':'.$person['fuente_id'],'en_calidad'=>'Testigo','fecha'=>'2026-10-14','hora'=>'09:30','lugar'=>'Sede UIAT','motivo'=>$motivo];
 $result=$service->create((int)$person['accidente_id'],$input);
 $row=$service->detail($result['id']);
 checkMotivo($row['motivo']===$motivo,'Guarda citación sin tipo de diligencia.');
 $row['motivo']=trim(str_repeat('Motivo completo de la diligencia. ',8));
 $service->update($result['id'],$row);
 checkMotivo($service->detail($result['id'])['motivo']===$row['motivo'],'Editar conserva el motivo completo, incluso mayor a 120 caracteres.');
 $calendar=$service->calendarPayload((int)$person['accidente_id'],$result['id'],$result);
 checkMotivo(str_contains($calendar['titulo'],$motivo),'Calendar utiliza motivo.');
 checkMotivo(!str_contains($calendar['descripcion'],'Tipo de diligencia:'),'Calendar no duplica tipo.');
 checkMotivo(CitacionMotivo::texto(['motivo'=>'','tipo_diligencia'=>'Reconocimiento'])==='Reconocimiento','Conserva información anterior cuando no existe motivo.');
 checkMotivo(CitacionMotivo::texto(['motivo'=>'Declaración','tipo_diligencia'=>'Reconocimiento'])==='Declaración','El motivo tiene prioridad.');
 echo "PASS: crear y editar sin tipo, motivo largo, Calendar y registros anteriores.\n";
} finally {$p->rollBack();$_SESSION=$sessionBefore;}
