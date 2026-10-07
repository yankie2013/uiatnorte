<?php
use App\Support\WorkspacePage as Page;
$licences=(new App\Repositories\DocumentoLcRepository($pdo))->listByPersona((int)$person['persona_id']);
$lc=$licences[0]??null;
$st=$pdo->prepare('SELECT COUNT(*) FROM involucrados_personas WHERE persona_id=? AND accidente_id<>?');$st->execute([$person['persona_id'],$id]);$shared=(bool)$st->fetchColumn();
if($lc && $shared){
 $st=$pdo->prepare("SELECT COUNT(*) FROM documento_lc lc JOIN comunicaciones_guardia c ON c.accidente_id=? WHERE lc.id=? AND lc.creado_en>=c.registrado_en AND EXISTS(SELECT 1 FROM auditoria au WHERE au.tabla='documento_lc' AND au.registro_id=lc.id AND au.accion='INSERT' AND au.usuario_id=?)");
 $st->execute([$id,$lc['id'],\App\Support\Access::id()]);
 if($st->fetchColumn())$shared=false;
}
$context='&accidente_id='.$id.'&embed=1&return_to='.rawurlencode($return);
echo '<div class="guardia-document-actions">';
if($editable){
 $url=$lc && !$shared?'doc_lc_editar.php?id='.(int)$lc['id']:'doc_lc_nuevo.php?persona_id='.(int)$person['persona_id'];
 echo '<a class="button guardia-document-open guardia-document-license" data-title="Licencia de conducir" href="'.Page::escape($url.$context).'"><span class="guardia-document-icon" aria-hidden="true">🪪</span><span class="guardia-document-label">'.($lc && !$shared?'Ver / editar licencia':($lc?'Añadir licencia':'Licencia de conducir')).'</span><span class="guardia-document-arrow" aria-hidden="true">↗</span></a>';
}
echo '<span class="guardia-document-status'.($lc?' guardia-document-complete':'').'">'.($lc?'<span aria-hidden="true">✓</span> Licencia registrada':'Licencia pendiente').'</span>';
foreach(($group['ids']??array_filter([(int)$person['vehiculo_id']])) as $vehicleId){
 $detail=$vehicleDetails[$vehicleId]??null;
 if(!$detail)continue;
 $st=$pdo->prepare('SELECT id,numero_soat,aseguradora_soat FROM documento_vehiculo_activos WHERE involucrado_vehiculo_id=? ORDER BY id DESC LIMIT 1');$st->execute([$detail['invol_id']]);$doc=$st->fetch();
 $hasSoat=$doc && ($doc['numero_soat']||$doc['aseguradora_soat']);
 $plateLabel=str_starts_with($detail['placa'],'SPLACA')?'Sin placa':$detail['placa'];
 if($editable){
  $url=$doc?'documento_vehiculo_editar.php?id='.(int)$doc['id']:'documento_vehiculo_nuevo.php?invol_id='.(int)$detail['invol_id'];
  echo '<a class="button guardia-document-open guardia-document-soat" data-title="SOAT · '.Page::escape($plateLabel).'" href="'.Page::escape($url.'&section=soat'.$context).'"><span class="guardia-document-icon" aria-hidden="true">🛡️</span><span class="guardia-document-label">SOAT · '.Page::escape($plateLabel).'</span><span class="guardia-document-arrow" aria-hidden="true">↗</span></a>';
 }
 echo '<span class="guardia-document-status'.($hasSoat?' guardia-document-complete':'').'">'.($hasSoat?'<span aria-hidden="true">✓</span> ':'').Page::escape($plateLabel).' · '.($hasSoat?'SOAT registrado':'SOAT pendiente').'</span>';
}
echo '</div>';
