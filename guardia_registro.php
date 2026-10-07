<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Support\Access;
use App\Support\WorkspacePage as Page;
$id=(int)($_GET['accidente_id']??0);
$st=$pdo->prepare('SELECT c.*,a.registro_sidpol,a.lugar,a.fecha_accidente,a.referencia,a.fecha_comunicacion,a.fecha_intervencion FROM comunicaciones_guardia c JOIN accidentes a ON a.id=c.accidente_id WHERE a.id=? AND c.creado_por=? AND c.eliminado_en IS NULL AND a.eliminado_en IS NULL');
$st->execute([$id,Access::id()]);$record=$st->fetch();
if(Access::role()!=='guardia'||!$record){http_response_code(403);exit('Registro no disponible para este usuario.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{Access::checkCsrf();(new App\Services\GuardiaService($pdo))->deliverDraft($id,(int)($_POST['jefe_id']??0));header('Location: guardia_registro.php?accidente_id='.$id.'&entregado=1');exit;}
 catch(Throwable $e){$error=$e->getMessage();}
}
Page::start('Registro Nuevo Accidente', 'assets/css/guardia-registro.css', null, true);Page::notice($error,true);
$st=$pdo->prepare('SELECT rbac_guardia_draft(?)');$st->execute([$id]);$editable=(bool)$st->fetchColumn();
$step=(string)($_GET['paso']??'vehiculos');
$steps=['datos'=>'Datos del accidente','vehiculos'=>'Registrar vehículos','personas'=>'Registrar personas','entrega'=>'Revisar y entregar'];
if(!isset($steps[$step]))$step='vehiculos';
echo '<div class="guardia-layout"><aside class="guardia-navigation"><div class="guardia-case"><strong>'.Page::escape($record['registro_sidpol']?:'Accidente #'.$id).'</strong><p>'.Page::escape($record['lugar']).'</p></div><nav aria-label="Pasos del registro">';
$number=0;
foreach($steps as $key=>$label){$number++;echo '<a '.($key===$step?'aria-current="step"':'').' href="?accidente_id='.$id.'&paso='.$key.'"><span>'.$number.'</span>'.Page::escape($label).'</a>';}
echo '</nav><p class="muted">Dispones de 12 horas desde el registro inicial para completar los datos antes de la entrega.</p></aside><div class="guardia-content">';
if(!$editable)Page::notice($record['jefe_id']?'Registro enviado al JEFE EMI y en espera de recepción. Los vehículos y personas registrados se conservaron.':'El plazo de 12 horas para completar este registro terminó. Solicita apoyo al administrador.');
$st=$pdo->prepare('SELECT v.placa,t.nombre tipo_vehiculo,iv.tipo participacion FROM involucrados_vehiculos_activos iv JOIN vehiculos v ON v.id=iv.vehiculo_id LEFT JOIN tipos_vehiculo t ON t.id=v.tipo_id WHERE iv.accidente_id=? ORDER BY iv.id');$st->execute([$id]);$vehicles=$st->fetchAll();
$st=$pdo->prepare("SELECT CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno) nombre FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id WHERE ip.accidente_id=?");$st->execute([$id]);$people=$st->fetchAll();
$return='guardia_registro.php?accidente_id='.$id.'&paso='.$step;
echo '<section><h2>'.Page::escape($steps[$step]).'</h2>';
if($step==='datos'){
 if($editable)echo '<iframe class="guardia-form" id="guardia-form" title="Datos del accidente" src="accidente_nuevo.php?draft_id='.$id.'"></iframe>';
}elseif($step==='vehiculos'){

}elseif($step==='personas'){
 echo '<p>Registra conductores, pasajeros y peatones. Selecciona el vehículo y la participación que correspondan.</p><ul>';
 foreach($people as $person)echo '<li>👤 '.Page::escape($person['nombre']).'</li>';
 echo '</ul><div class="actions"><a class="button" href="?accidente_id='.$id.'&paso=entrega">Revisar y entregar →</a></div>';
}else{
 echo '<h3>Vehículos registrados</h3><ul>';
 foreach($vehicles as $vehicle)echo '<li>'.Page::escape($vehicle['placa']?:'Sin placa').'</li>';
 echo '</ul><h3>Personas registradas</h3><ul>';
 foreach($people as $person)echo '<li>'.Page::escape($person['nombre']).'</li>';
 echo '</ul>';
 if($editable){
 echo '<p>Revisa lo registrado antes de asignar la investigación: <strong>'.count($vehicles).' vehículos · '.count($people).' personas</strong>.</p><p>Al entregar, el expediente quedará en espera de recepción hasta que el JEFE EMI lo acepte.</p><form method="post">';Page::token();echo '<label>JEFE EMI destinatario<select name="jefe_id" required><option value="">Seleccionar</option>';
 foreach($pdo->query("SELECT id,nombre,grado FROM usuarios WHERE activo=1 AND rol='jefe_emi' ORDER BY nombre") as $u)echo '<option value="'.(int)$u['id'].'">'.Page::escape(trim(($u['grado']??'').' '.$u['nombre'])).'</option>';
 echo '</select></label><button>Entregar expediente</button></form>';
 }
}
if($editable && in_array($step,['vehiculos','personas'],true)){
 $formPage=$step==='vehiculos'?'involucrados_vehiculos_nuevo.php':'involucrados_personas_nuevo.php';
 echo '<iframe class="guardia-form" id="guardia-form" title="'.Page::escape($steps[$step]).'" src="'.$formPage.'?accidente_id='.$id.'&amp;guided_embed=1&amp;return_to='.rawurlencode($return).'"></iframe>';
}
if($step==='vehiculos'){
 echo '<div class="guardia-registered"><h3>Vehículos registrados</h3>';
 if(!$vehicles)echo '<p class="muted">Todavía no hay vehículos registrados.</p>';
 else{
  echo '<div class="guardia-vehicle-cards">';
  foreach($vehicles as $vehicle){
   $type=$vehicle['tipo_vehiculo']?:'Sin registrar';
   $normalized=mb_strtolower($type,'UTF-8');
   $icon=match(true){
    str_contains($normalized,'bicicleta')=>'🚲',
    str_contains($normalized,'vmp')=>'🛴',
    str_contains($normalized,'trimoto')=>'🛺',
    str_contains($normalized,'motocicleta') || str_contains($normalized,'moto')=>'🏍️',
    str_contains($normalized,'remolque') || str_contains($normalized,'camión') || $normalized==='camion'=>'🚛',
    str_contains($normalized,'bus') || str_contains($normalized,'bús')=>'🚌',
    str_contains($normalized,'pickup')=>'🛻',
    str_contains($normalized,'camioneta') || str_contains($normalized,'suv') || str_contains($normalized,'multipropósito')=>'🚙',
    default=>'🚗'
   };
   $plate=str_starts_with($vehicle['placa'],'SPLACA')?'Sin placa':($vehicle['placa']?:'Sin placa');
   echo '<article class="guardia-vehicle-card"><span class="guardia-vehicle-icon" aria-hidden="true">'.$icon.'</span><div><strong>'.Page::escape($plate).'</strong><p>'.Page::escape($type).'</p><span class="guardia-participation">'.Page::escape($vehicle['participacion']?:'Sin registrar').'</span></div></article>';
  }
  echo '</div>';
 }
 echo '</div>';
}
echo '</section></div></div>';
if(!$editable)echo '<a href="guardia.php">Volver a comunicaciones</a>';
echo '<script src="assets/js/guardia-registro.js"></script>';
Page::end();
