<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Support\Access;
use App\Support\WorkspacePage as Page;
$id=(int)($_GET['accidente_id']??0);
$st=$pdo->prepare('SELECT c.*,a.registro_sidpol,a.lugar FROM comunicaciones_guardia c JOIN accidentes a ON a.id=c.accidente_id WHERE a.id=? AND c.creado_por=? AND c.eliminado_en IS NULL AND a.eliminado_en IS NULL');
$st->execute([$id,Access::id()]);$record=$st->fetch();
if(Access::role()!=='guardia'||!$record){http_response_code(403);exit('Registro no disponible para este usuario.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{Access::checkCsrf();(new App\Services\GuardiaService($pdo))->deliverDraft($id,(int)($_POST['jefe_id']??0));header('Location: guardia_registro.php?accidente_id='.$id.'&entregado=1');exit;}
 catch(Throwable $e){$error=$e->getMessage();}
}
Page::start('Registro guiado de guardia');Page::notice($error,true);
$st=$pdo->prepare('SELECT rbac_guardia_draft(?)');$st->execute([$id]);$editable=(bool)$st->fetchColumn();
$step=(string)($_GET['paso']??'vehiculos');
$steps=['vehiculos'=>'2 · Vehículos','personas'=>'3 · Personas','entrega'=>'4 · Revisar y entregar'];
if(!isset($steps[$step]))$step='vehiculos';
echo '<section><h2>'.Page::escape($record['registro_sidpol']?:'Accidente #'.$id).'</h2><p>'.Page::escape($record['lugar']).'</p><p>✓ 1 · Datos del accidente guardados</p><nav class="actions">';
foreach($steps as $key=>$label)echo '<a '.($key===$step?'aria-current="step"':'').' href="?accidente_id='.$id.'&paso='.$key.'">'.Page::escape($label).'</a>';
echo '</nav></section>';
if(!$editable){Page::notice($record['jefe_id']?'Registro enviado al JEFE EMI y en espera de recepción. Los vehículos y personas registrados se conservaron.':'El plazo de 12 horas para completar este registro terminó. Solicita apoyo al administrador.');echo '<a href="guardia.php">Volver a comunicaciones</a>';Page::end();exit;}
$st=$pdo->prepare('SELECT v.placa FROM involucrados_vehiculos iv JOIN vehiculos v ON v.id=iv.vehiculo_id WHERE iv.accidente_id=?');$st->execute([$id]);$vehicles=$st->fetchAll();
$st=$pdo->prepare("SELECT CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno) nombre FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id WHERE ip.accidente_id=?");$st->execute([$id]);$people=$st->fetchAll();
$return='guardia_registro.php?accidente_id='.$id.'&paso='.$step;
echo '<section><h2>'.Page::escape($steps[$step]).'</h2>';
if($step==='vehiculos'){
 echo '<p>Agrega cada vehículo involucrado con el formulario habitual. Busca su placa antes de crear uno nuevo.</p><ul>';
 foreach($vehicles as $v)echo '<li>🚗 '.Page::escape($v['placa']?:'Sin placa').'</li>';
 echo '</ul><div class="actions"><a class="button" href="involucrados_vehiculos_nuevo.php?accidente_id='.$id.'&return_to='.rawurlencode($return).'">+ Agregar vehículo</a><a class="button" href="?accidente_id='.$id.'&paso=personas">Continuar con personas →</a></div><p>Si no hay vehículos identificados, puedes continuar.</p>';
}elseif($step==='personas'){
 echo '<p>Registra conductores, pasajeros y peatones. Selecciona el vehículo y la participación que correspondan.</p><ul>';
 foreach($people as $person)echo '<li>👤 '.Page::escape($person['nombre']).'</li>';
 echo '</ul><div class="actions"><a class="button" href="involucrados_personas_nuevo.php?accidente_id='.$id.'&return_to='.rawurlencode($return).'">+ Agregar persona</a><a class="button" href="?accidente_id='.$id.'&paso=entrega">Revisar y entregar →</a></div>';
}else{
 echo '<p>Revisa lo registrado antes de asignar la investigación: <strong>'.count($vehicles).' vehículos · '.count($people).' personas</strong>.</p><p>Al entregar, el expediente quedará en espera de recepción hasta que el JEFE EMI lo acepte.</p><form method="post">';Page::token();echo '<label>JEFE EMI destinatario<select name="jefe_id" required><option value="">Seleccionar</option>';
 foreach($pdo->query("SELECT id,nombre,grado FROM usuarios WHERE activo=1 AND rol='jefe_emi' ORDER BY nombre") as $u)echo '<option value="'.(int)$u['id'].'">'.Page::escape(trim(($u['grado']??'').' '.$u['nombre'])).'</option>';
 echo '</select></label><button>Entregar expediente</button></form>';
}
echo '</section><p>Dispones de 12 horas desde el registro inicial para completar los datos antes de la entrega.</p>';
Page::end();
