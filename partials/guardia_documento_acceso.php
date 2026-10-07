<?php
// Contexto limitado al borrador de guardia y al conductor seleccionado.
if (\App\Support\Access::role()==='guardia') {
 $caseId=(int)($_GET['accidente_id']??$_POST['accidente_id']??0);
 $script=basename($_SERVER['SCRIPT_NAME']);
 $check=$pdo->prepare('SELECT rbac_guardia_draft(?)');$check->execute([$caseId]);
 $allowed=(bool)$check->fetchColumn();
 if(str_starts_with($script,'doc_lc_')){
  $personId=(int)($_POST['persona_id']??$_GET['persona_id']??0);
  if($script==='doc_lc_editar.php'){
   $st=$pdo->prepare('SELECT persona_id FROM documento_lc WHERE id=?');$st->execute([(int)($_GET['id']??$_POST['id']??0)]);$existingPerson=(int)$st->fetchColumn();
   if($personId && $personId!==$existingPerson)$allowed=false;
   $personId=$existingPerson;
   $st=$pdo->prepare('SELECT COUNT(*) FROM involucrados_personas WHERE persona_id=? AND accidente_id<>?');$st->execute([$personId,$caseId]);
   if($st->fetchColumn()){
    $st=$pdo->prepare("SELECT COUNT(*) FROM documento_lc lc JOIN comunicaciones_guardia c ON c.accidente_id=? WHERE lc.id=? AND lc.creado_en>=c.registrado_en AND EXISTS(SELECT 1 FROM auditoria au WHERE au.tabla='documento_lc' AND au.registro_id=lc.id AND au.accion='INSERT' AND au.usuario_id=?)");
    $st->execute([$caseId,(int)($_GET['id']??$_POST['id']??0),\App\Support\Access::id()]);
    if(!$st->fetchColumn())$allowed=false;
   }
  }
  $st=$pdo->prepare("SELECT COUNT(*) FROM involucrados_personas ip JOIN participacion_persona r ON r.Id=ip.rol_id WHERE ip.accidente_id=? AND ip.persona_id=? AND LOWER(r.Nombre)='conductor'");$st->execute([$caseId,$personId]);$allowed=$allowed && (bool)$st->fetchColumn();
 }else{
  $involId=(int)($_GET['invol_id']??$_POST['involucrado_vehiculo_id']??0);
  if($script==='documento_vehiculo_editar.php'){
   $st=$pdo->prepare('SELECT involucrado_vehiculo_id FROM documento_vehiculo_activos WHERE id=?');$st->execute([(int)($_GET['id']??$_POST['id']??0)]);$existingInvol=(int)$st->fetchColumn();
   if($involId && $involId!==$existingInvol)$allowed=false;
   $involId=$existingInvol;
  }
  $st=$pdo->prepare('SELECT COUNT(*) FROM involucrados_vehiculos_activos WHERE id=? AND accidente_id=?');$st->execute([$involId,$caseId]);$allowed=$allowed && (bool)$st->fetchColumn();
  if((string)($_GET['section']??$_POST['section']??'')!=='soat')$allowed=false;
 }
 if(!$allowed){http_response_code(403);exit('Documento no disponible para este registro de guardia.');}
 $pdo->exec('SET @guardia_document_case='.$caseId);
}
