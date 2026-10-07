<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Support\Access;
use App\Support\WorkspacePage as Page;
$id=(int)($_GET['accidente_id']??0);
$st=$pdo->prepare('SELECT c.*,a.registro_sidpol,a.lugar,a.fecha_accidente,a.referencia,a.fecha_comunicacion,a.fecha_intervencion,a.responsable_id FROM comunicaciones_guardia c JOIN accidentes a ON a.id=c.accidente_id WHERE a.id=? AND c.creado_por=? AND c.eliminado_en IS NULL AND a.eliminado_en IS NULL');
$st->execute([$id,Access::id()]);$record=$st->fetch();
if(Access::role()!=='guardia'||!$record){http_response_code(403);exit('Registro no disponible para este usuario.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  Access::checkCsrf();$service=new App\Services\GuardiaService($pdo);
  if(($_POST['accion']??'entregar')==='cancelar_envio')$service->cancelPendingDelivery($id,(int)($_POST['transferencia_id']??0));
  else $service->deliverDraft($id,(int)($_POST['jefe_id']??0));
  header('Location: guardia_registro.php?accidente_id='.$id.'&paso=entrega');exit;
 }
 catch(Throwable $e){$error=$e->getMessage();}
}
Page::start('Registro Nuevo Accidente', 'assets/css/guardia-registro.css', null, true);Page::notice($error,true);
$st=$pdo->prepare('SELECT rbac_guardia_draft(?)');$st->execute([$id]);$editable=(bool)$st->fetchColumn();
$step=(string)($_GET['paso']??'vehiculos');
$steps=['datos'=>'Datos del accidente','vehiculos'=>'Registrar vehículos','personas'=>'Registrar personas','documentos'=>'Documentos','entrega'=>'Revisar y entregar'];
if(!isset($steps[$step]))$step='vehiculos';
echo '<div class="guardia-layout"><aside class="guardia-navigation"><div class="guardia-case"><strong>'.Page::escape($record['registro_sidpol']?:'Accidente #'.$id).'</strong><p>'.Page::escape($record['lugar']).'</p></div><nav aria-label="Pasos del registro">';
$number=0;
foreach($steps as $key=>$label){$number++;echo '<a '.($key===$step?'aria-current="step"':'').' href="?accidente_id='.$id.'&paso='.$key.'"><span>'.$number.'</span>'.Page::escape($label).'</a>';}
echo '</nav><p class="muted">Dispones de 12 horas desde el registro inicial para completar los datos antes de la entrega.</p></aside><div class="guardia-content">';
if($editable && $record['jefe_id'])Page::notice('Registro enviado al JEFE EMI. Puedes corregir tus datos hasta completar las 12 horas desde el registro inicial.');
if(!$editable)Page::notice($record['jefe_id']?'Registro enviado al JEFE EMI y en espera de recepción. Los vehículos y personas registrados se conservaron.':'El plazo de 12 horas para completar este registro terminó. Solicita apoyo al administrador.');
$st=$pdo->prepare('SELECT iv.id invol_id,v.id,v.placa,t.nombre tipo_vehiculo,iv.tipo participacion FROM involucrados_vehiculos_activos iv JOIN vehiculos v ON v.id=iv.vehiculo_id LEFT JOIN tipos_vehiculo t ON t.id=v.tipo_id WHERE iv.accidente_id=? ORDER BY iv.id');$st->execute([$id]);$vehicles=$st->fetchAll();
$st=$pdo->prepare("SELECT CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno) nombre,ip.id involucrado_id,p.num_doc,ip.persona_id,ip.vehiculo_id,ip.lesion,ip.orden_persona,r.Nombre rol,v.placa,t.nombre tipo_vehiculo FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id LEFT JOIN participacion_persona r ON r.Id=ip.rol_id LEFT JOIN vehiculos v ON v.id=ip.vehiculo_id LEFT JOIN tipos_vehiculo t ON t.id=v.tipo_id WHERE ip.accidente_id=? ORDER BY ip.id");$st->execute([$id]);$people=$st->fetchAll();
$vehicleDetails=array_column($vehicles,null,'id');
$vehicleGroups=[];
if(in_array($step,['personas','documentos'],true)){
 foreach((new App\Repositories\InvolucradoPersonaRepository($pdo))->vehiculosPorAccidente($id) as $group){
  foreach($group['ids'] as $vehicleId)$vehicleGroups[$vehicleId]=$group;
 }
}
$return='guardia_registro.php?accidente_id='.$id.'&paso='.$step;
echo '<section><h2>'.Page::escape($step==='personas'?'Registra personas':$steps[$step]).'</h2>';
if($step==='datos'){
 if($editable)echo '<iframe class="guardia-form" id="guardia-form" title="Datos del accidente" src="accidente_nuevo.php?draft_id='.$id.'"></iframe>';
}elseif($step==='vehiculos'){

}elseif($step==='personas'){

}elseif($step==='documentos'){
 require __DIR__.'/partials/guardia_documentos_resumen.php';
}else{
 echo '<h3>Vehículos registrados</h3><ul>';
 foreach($vehicles as $vehicle)echo '<li>'.Page::escape($vehicle['placa']?:'Sin placa').'</li>';
 echo '</ul><h3>Personas registradas</h3><ul>';
 foreach($people as $person)echo '<li>'.Page::escape($person['nombre']).'</li>';
 echo '</ul>';
 if($record['jefe_id'])echo '<div class="guardia-sumilla-download"><a class="button" href="word_sumilla_guardia.php?accidente_id='.$id.'">📄 Descargar sumilla Word ↓</a><p>Incluye los datos generales, participantes y documentos registrados.</p></div>';
 if($record['jefe_id']){
  $st=$pdo->prepare("SELECT t.id,u.nombre,u.grado FROM expediente_transferencias t JOIN usuarios u ON u.id=t.destino_id WHERE t.accidente_id=? AND t.origen_id=? AND t.destino_id=? AND t.tipo='investigacion' AND t.estado='pendiente' ORDER BY t.id DESC LIMIT 1");$st->execute([$id,Access::id(),$record['jefe_id']]);$pending=$st->fetch();
  if($pending){
   echo '<div class="guardia-pending-delivery"><strong>📨 En espera de aceptación</strong><p>JEFE EMI: '.Page::escape(trim($pending['grado'].' '.$pending['nombre'])).'</p>';
   if($editable && !$record['responsable_id']){
    echo '<p>Puedes cancelar este envío y designar otro jefe dentro de las 12 horas originales. El envío se retirará de su bandeja de pendientes y quedará en el historial.</p><form method="post">';Page::token();
    echo '<input type="hidden" name="accion" value="cancelar_envio"><input type="hidden" name="transferencia_id" value="'.(int)$pending['id'].'"><button class="guardia-cancel-delivery" type="submit">Cancelar envío para designar otro jefe</button></form>';
   }
   echo '</div>';
  }else echo '<p>✓ El JEFE EMI ya recibió el expediente. La cancelación del envío no está disponible.</p>';
 }
 if($editable && !$record['jefe_id']){
 echo '<p>Revisa lo registrado antes de asignar la investigación: <strong>'.count($vehicles).' vehículos · '.count($people).' personas</strong>.</p><p>Al entregar, el expediente quedará en espera de recepción hasta que el JEFE EMI lo acepte.</p><form method="post">';Page::token();echo '<label>JEFE EMI destinatario<select name="jefe_id" required><option value="">Seleccionar</option>';
 foreach($pdo->query("SELECT id,nombre,grado FROM usuarios WHERE activo=1 AND rol='jefe_emi' ORDER BY nombre") as $u)echo '<option value="'.(int)$u['id'].'">'.Page::escape(trim(($u['grado']??'').' '.$u['nombre'])).'</option>';
 echo '</select></label><button>Entregar expediente</button></form>';
 }
}
if($editable && in_array($step,['vehiculos','personas'],true)){
 $formPage=$step==='vehiculos'?'involucrados_vehiculos_nuevo.php':'involucrados_personas_nuevo.php';
 echo '<iframe class="guardia-form" id="guardia-form" title="'.Page::escape($steps[$step]).'" src="'.$formPage.'?accidente_id='.$id.'&amp;guided_embed=1&amp;return_to='.rawurlencode($return).'"></iframe>';
}
if(in_array($step,['personas','documentos'],true)){
 echo '<div class="guardia-registered"><h3>Personas registradas <span class="guardia-person-count">'.count($people).'</span></h3>';
 if(!$people)echo '<p class="muted">Todavía no hay personas registradas.</p>';
 else{
  echo '<div class="guardia-person-cards">';
  foreach($people as $person){
   $role=$person['rol']?:'Sin rol registrado';
   $icon=match(mb_strtolower($role,'UTF-8')){'conductor'=>'🚘','peatón','peaton'=>'🚶',default=>'👤'};
   $status=match($person['lesion']){'Ileso'=>'ileso','Herido'=>'herido','Fallecido'=>'fallecido',default=>'pendiente'};
   $label=$person['lesion']?:'Sin registrar';
   $plate=$person['placa'];
   if($plate && str_starts_with($plate,'SPLACA'))$plate='Sin placa';
   $group=$vehicleGroups[(int)$person['vehiculo_id']]??null;
   $combined=$group && count($group['ids'])>1;
   $description=$role.' · Sin vehículo vinculado';
   if($person['vehiculo_id']){
    $description=$role.' de '.mb_strtolower($person['tipo_vehiculo']?:'vehículo','UTF-8').' de placa '.($plate?:'Sin placa');
    if($combined){
     $parts=[];
     foreach($group['ids'] as $vehicleId){
      $detail=$vehicleDetails[$vehicleId]??null;
      if(!$detail)continue;
      $partPlate=$detail['placa'];
      if(!$partPlate || str_starts_with($partPlate,'SPLACA'))$partPlate='Sin placa';
      $parts[]=mb_strtolower($detail['tipo_vehiculo']?:'vehículo','UTF-8').' de placa '.$partPlate;
     }
     $description=$role.' del combinado vehicular con '.implode(' y ',$parts);
    }
   }
   if($person['orden_persona'])$description.=' · '.$person['orden_persona'];
   echo '<article class="guardia-person-card guardia-person-card-'.$status.'"><div class="guardia-person-heading"><span class="guardia-person-icon" aria-hidden="true">'.$icon.'</span><div class="guardia-person-body"><div class="guardia-person-name"><strong>'.Page::escape($person['nombre']).'</strong><span class="guardia-person-document">'.Page::escape($person['num_doc']?'DNI: '.$person['num_doc']:'Documento sin registrar').'</span></div><div class="guardia-person-description"><span>'.Page::escape($description).'</span><span class="guardia-health guardia-health-'.$status.'">'.Page::escape($label).'</span></div></div></div>';
   if($step==='documentos' && mb_strtolower($role,'UTF-8')==='conductor'){require __DIR__.'/partials/guardia_documentos_botones.php';}
   if($step==='documentos' && $person['lesion']==='Fallecido'){require __DIR__.'/partials/guardia_fallecimiento_boton.php';}
   if($editable && $step==='personas')echo '<a class="guardia-edit-record" href="involucrados_personas_editar.php?id='.(int)$person['involucrado_id'].'&amp;return_to='.rawurlencode($return).'">✎ Corregir persona</a>';
   echo '</article>';

  }
  echo '</div>';
 }
 echo '</div>';
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
   echo '<article class="guardia-vehicle-card"><span class="guardia-vehicle-icon" aria-hidden="true">'.$icon.'</span><div><strong>'.Page::escape($plate).'</strong><p>'.Page::escape($type).'</p><span class="guardia-participation">'.Page::escape($vehicle['participacion']?:'Sin registrar').'</span>'.($editable?'<a class="guardia-edit-record" href="involucrados_vehiculos_editar.php?id='.(int)$vehicle['invol_id'].'&amp;return_to='.rawurlencode($return).'">✎ Corregir vehículo</a>':'').'</div></article>';
  }
  echo '</div>';
 }
 echo '</div>';
}
echo '</section></div></div>';
if(!$editable)echo '<a href="guardia.php">Volver a comunicaciones</a>';
if($step==='documentos')echo '<dialog id="guardia-document-dialog"><header><strong id="guardia-document-title">Documento</strong><button type="button" id="guardia-document-close">Cerrar ✕</button></header><iframe id="guardia-document-frame" title="Formulario de documento"></iframe></dialog>';
echo '<script src="assets/js/guardia-registro.js"></script>';
Page::end();
