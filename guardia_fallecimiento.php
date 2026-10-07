<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Support\Access;
use App\Support\WorkspacePage as Page;
$id=(int)($_GET['involucrado_id']??0);
$st=$pdo->prepare("SELECT ip.*,CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno) nombre FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id WHERE ip.id=? AND ip.lesion='Fallecido'");$st->execute([$id]);$person=$st->fetch();
if(!$person){http_response_code(404);exit('Persona fallecida no encontrada.');}
$case=(int)$person['accidente_id'];
$st=$pdo->prepare('SELECT rbac_guardia_draft(?)');$st->execute([$case]);$editable=Access::role()==='guardia'?(bool)$st->fetchColumn():Access::canEdit($case);
if(!$editable){http_response_code(403);exit('Este registro no está disponible para edición.');}
$st=$pdo->prepare('SELECT * FROM guardia_persona_fallecimiento WHERE involucrado_persona_id=?');$st->execute([$id]);$existing=$st->fetch();
$data=$existing?:['tipo'=>'','hospital_id'=>null,'observaciones'=>''];$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $data=['tipo'=>(string)($_POST['tipo']??''),'hospital_nombre'=>preg_replace('/\s+/u',' ',trim((string)($_POST['hospital_nombre']??''))),'observaciones'=>trim((string)($_POST['observaciones']??''))];
 try{
  Access::checkCsrf();
  if(!in_array($data['tipo'],['lugar_hechos','hospital'],true))throw new RuntimeException('Selecciona dónde falleció.');
  if($data['tipo']==='hospital' && ($data['hospital_nombre']==='' || mb_strlen($data['hospital_nombre'])>200))throw new RuntimeException('Indica el hospital o centro de salud (máximo 200 caracteres).');
  $pdo->beginTransaction();
  $st=$pdo->prepare('SELECT id FROM accidentes WHERE id=? FOR UPDATE');$st->execute([$case]);
  // Revalida el plazo bajo bloqueo antes de guardar.
  if(Access::role()==='guardia'){$st=$pdo->prepare('SELECT rbac_guardia_draft(?)');$st->execute([$case]);if(!$st->fetchColumn())throw new RuntimeException('El plazo de 12 horas terminó.');}
  $hospitalId=null;
  if($data['tipo']==='hospital'){
   $st=$pdo->prepare('SELECT id FROM guardia_hospitales WHERE nombre=?');$st->execute([$data['hospital_nombre']]);$hospitalId=$st->fetchColumn();
   if(!$hospitalId){
    try{$pdo->prepare('INSERT INTO guardia_hospitales(nombre) VALUES(?)')->execute([$data['hospital_nombre']]);$hospitalId=(int)$pdo->lastInsertId();}
    catch(PDOException $e){if($e->getCode()!=='23000')throw $e;$st->execute([$data['hospital_nombre']]);$hospitalId=$st->fetchColumn();if(!$hospitalId)throw $e;}
   }
  }
  if($existing)$pdo->prepare('UPDATE guardia_persona_fallecimiento SET tipo=?,hospital_id=?,observaciones=? WHERE involucrado_persona_id=?')->execute([$data['tipo'],$hospitalId,$data['observaciones']?:null,$id]);
  else $pdo->prepare('INSERT INTO guardia_persona_fallecimiento(accidente_id,involucrado_persona_id,tipo,hospital_id,observaciones) VALUES(?,?,?,?,?)')->execute([$case,$id,$data['tipo'],$hospitalId,$data['observaciones']?:null]);
  $pdo->commit();
  echo '<!doctype html><html lang="es"><meta charset="utf-8"><script>parent.postMessage({type:"fallecimiento.saved"},location.origin);</script><p>Datos de fallecimiento guardados.</p></html>';exit;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}
$hospitals=$pdo->query('SELECT id,nombre FROM guardia_hospitales ORDER BY nombre')->fetchAll();
$hospitalName=$data['hospital_nombre']??'';
foreach($hospitals as $hospital)if((int)$hospital['id']===(int)($data['hospital_id']??0))$hospitalName=$hospital['nombre'];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Datos de fallecimiento</title><link rel="stylesheet" href="assets/css/guardia-fallecimiento.css"></head><body><main>
<h1>Datos de fallecimiento</h1><p class="person-name"><?=Page::escape($person['nombre'])?></p>
<?php if($error): ?><p class="error"><?=Page::escape($error)?></p><?php endif; ?>
<form method="post"><?php Page::token(); ?>
<label for="tipo">¿Dónde falleció?</label><select name="tipo" id="tipo" required><option value="">Seleccionar</option><option value="lugar_hechos" <?=$data['tipo']==='lugar_hechos'?'selected':''?>>En el lugar de los hechos</option><option value="hospital" <?=$data['tipo']==='hospital'?'selected':''?>>En hospital o centro de salud</option></select>
<div id="hospital-field" hidden><label for="hospital_nombre">Hospital o centro de salud</label><input name="hospital_nombre" id="hospital_nombre" list="hospitales" value="<?=Page::escape($hospitalName)?>" maxlength="200" placeholder="Selecciona o escribe un nuevo lugar"><datalist id="hospitales"><?php foreach($hospitals as $hospital): ?><option value="<?=Page::escape($hospital['nombre'])?>"></option><?php endforeach; ?></datalist><p class="help">Un lugar nuevo quedará disponible al guardar.</p></div>
<label for="observaciones">Observaciones</label><textarea name="observaciones" id="observaciones" rows="3"><?=Page::escape($data['observaciones'])?></textarea><button type="submit">✓ Guardar información</button>
</form></main><script>
const type=document.getElementById('tipo'),field=document.getElementById('hospital-field'),hospital=document.getElementById('hospital_nombre');
function updateHospital(){field.hidden=type.value!=='hospital';hospital.required=type.value==='hospital';}type.addEventListener('change',updateHospital);updateHospital();
</script></body></html>
