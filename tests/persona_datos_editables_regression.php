<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
use App\Repositories\PersonaRepository;
use App\Services\PersonaService;
$p=App\Database\Database::connection();
App\Support\Auth::startSession();$sessionBefore=$_SESSION;$p->beginTransaction();
function personaCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
try{
 $actor=$p->query("SELECT * FROM usuarios WHERE activo=1 AND rol='admin' LIMIT 1")->fetch();$_SESSION['user']=$actor;$p->exec('SET @actor_id='.(int)$actor['id']);
 $service=new PersonaService(new PersonaRepository($p));
 $id=$service->create(['tipo_doc'=>'OTRO','nombres'=>'Prueba','apellido_paterno'=>'Identidad','apellido_materno'=>'Vacía']);
 $service->update($id,['fecha_nacimiento'=>'1990-01-02','nombre_padre'=>'Padre Prueba','domicilio'=>'Dirección nueva','celular'=>'999111222','numero_hijos'=>2]);
 $person=$service->find($id);personaCheck($person['fecha_nacimiento']==='1990-01-02','Completa nacimiento vacío.');personaCheck((int)$person['numero_hijos']===2,'Completa número de hijos.');
 $service->update($id,['estado_civil'=>'Casado','grado_instruccion'=>'Superior','numero_hijos'=>3,'domicilio'=>'Otra dirección','email'=>'prueba@example.com']);
 personaCheck((int)$service->find($id)['numero_hijos']===3,'Actualiza datos variables.');
 $blocked=false;try{$service->update($id,['fecha_nacimiento'=>'1991-01-02']);}catch(InvalidArgumentException $e){$blocked=true;}personaCheck($blocked,'Nacimiento ya registrado fijo en servicio.');
 $blocked=false;try{$p->exec("UPDATE personas SET nombre_padre='Otro padre' WHERE id=".$id);}catch(PDOException $e){$blocked=true;}personaCheck($blocked,'Identidad protegida también en SQL.');
 $linked=$p->query('SELECT p.id FROM personas p JOIN involucrados_personas ip ON ip.persona_id=p.id WHERE ip.snapshot_guardado=1 LIMIT 1')->fetchColumn();
 personaCheck((bool)$linked,'Se requiere persona con snapshot.');
 $s=$p->prepare('SELECT * FROM involucrados_personas WHERE persona_id=? ORDER BY id');$s->execute([$linked]);$before=$s->fetchAll(PDO::FETCH_ASSOC);
 $service->update((int)$linked,['domicilio'=>'Dirección actualizada de prueba','celular'=>'900123456','numero_hijos'=>4]);
 $s->execute([$linked]);personaCheck($before===$s->fetchAll(PDO::FETCH_ASSOC),'Actualizar persona conserva copias históricas.');
 echo "PASS: completar identidad vacía, bloquear identidad registrada, editar datos variables y conservar snapshots.\n";
}finally{$p->rollBack();$_SESSION=$sessionBefore;}
