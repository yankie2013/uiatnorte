<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Support\Access;
use App\Support\WorkspacePage as Page;
use App\Services\GuardiaService;
$id=(int)($_GET['id']??$_POST['id']??0);$error='';$service=new GuardiaService($pdo);
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {Access::checkCsrf();$action=(string)($_POST['action']??'guardar');
        if($action==='asignar'){$case=$service->assign($id,(int)($_POST['jefe_id']??0));header('Location: guardia.php?id='.$id.'&ok=1');exit;}
        elseif(in_array($action,['eliminar','restaurar'],true)){
            if(!Access::admin() || trim((string)($_POST['motivo']??''))==='')throw new RuntimeException('Solo administrador, con motivo de eliminación.');
            if($action==='eliminar')$pdo->prepare('UPDATE comunicaciones_guardia SET eliminado_en=NOW(),eliminado_por=?,motivo_eliminacion=? WHERE id=?')->execute([Access::id(),trim($_POST['motivo']),$id]);
            else {$pdo->prepare('UPDATE comunicaciones_guardia SET eliminado_en=NULL,eliminado_por=NULL,motivo_eliminacion=NULL WHERE id=?')->execute([$id]);$pdo->prepare("INSERT INTO auditoria(usuario_id,tabla,registro_id,accion,motivo) VALUES(?,'comunicaciones_guardia',?,'restaurar',?)")->execute([Access::id(),$id,trim($_POST['motivo'])]);}header('Location: guardia.php?ok=1');exit;
        } else {$id=$service->save($id,$_POST);header('Location: guardia.php?id='.$id.'&ok=1');exit;}
    }catch(Throwable $e){$error=$e->getMessage();}
}
Page::start('Comunicaciones de guardia');Page::notice($error,true);if(isset($_GET['ok']))Page::notice('Operación guardada.');
$canCreate=in_array(Access::role(),['admin','guardia'],true);
$row=[];
if($id){$s=$pdo->prepare('SELECT c.*,u.nombre autor, j.nombre jefe, DATE_ADD(c.registrado_en,INTERVAL 12 HOUR) limite FROM comunicaciones_guardia c JOIN usuarios u ON u.id=c.creado_por LEFT JOIN usuarios j ON j.id=c.jefe_id WHERE c.id=?'.(Access::admin()?'':' AND c.eliminado_en IS NULL'));$s->execute([$id]);$row=$s->fetch();if(!$row){Page::notice('Comunicación no encontrada.',true);Page::end();exit;}}
$edit=($id && Access::guardEditable($row,Access::id(),Access::role())) || (!$id && $canCreate && isset($_GET['nueva']));
if($id || $edit){
    echo '<section><h2>'.($id?'Comunicación #'.$id:'Nueva comunicación').'</h2>';
    if($id){echo '<p>Registró: '.Page::escape($row['autor']).' · '.Page::escape($row['registrado_en']).'</p><p>Edición de guardia hasta: <strong>'.Page::escape($row['limite']).'</strong> (hora de Lima).</p>';if($row['accidente_id'])echo '<p>Asignado a '.Page::escape($row['jefe']).' · <a href="gestion_expedientes.php?id='.$row['accidente_id'].'">Expediente #'.$row['accidente_id'].'</a></p><p class="muted">Las correcciones de esta comunicación conservan lo recibido en la llamada; no sobrescriben la investigación del JEFE EMI.</p>';}
    if($edit){
        $data=$error!==''?array_merge($row,$_POST):$row;
        echo '<form method="post">';Page::token();echo '<input type="hidden" name="action" value="guardar"><div class="grid">';
        $fields=['fecha_llamada'=>'Fecha y hora de la llamada','comunicante'=>'Nombre del comunicante','telefono'=>'Teléfono','lugar'=>'Lugar comunicado','referencia'=>'Referencia','tipo_hecho'=>'Tipo de hecho preliminar','latitud'=>'Latitud aproximada','longitud'=>'Longitud aproximada'];
        foreach($fields as $f=>$label){$value=$data[$f]??'';$type='text';if($f==='fecha_llamada'){$type='datetime-local';$value=$value?date('Y-m-d\TH:i',strtotime($value)):date('Y-m-d\TH:i');}if(in_array($f,['latitud','longitud'],true))$type='number';echo '<label>'.$label.'<input name="'.$f.'" type="'.$type.'" '.($type==='number'?'step="any" ':'').(in_array($f,['fecha_llamada','lugar'],true)?'required ':'').'value="'.Page::escape($value).'"></label>';}
        echo '</div>';
        foreach(['descripcion'=>'Descripción breve de la comunicación','vehiculos'=>'Vehículos y placas conocidos','afectados'=>'Personas o afectados conocidos'] as $f=>$label)echo '<label>'.$label.'<textarea name="'.$f.'" '.($f==='descripcion'?'required':'').'>'.Page::escape($data[$f]??'').'</textarea></label>';
        echo '<div class="grid"><label>Departamento<select id="g-dep" name="cod_dep"><option value="">Por determinar</option>';
        foreach($pdo->query('SELECT cod_dep,nombre FROM ubigeo_departamento ORDER BY nombre') as $d)echo '<option value="'.Page::escape($d['cod_dep']).'" '.(($data['cod_dep']??'')===$d['cod_dep']?'selected':'').'>'.Page::escape($d['nombre']).'</option>';echo '</select></label><label>Provincia<select id="g-prov" name="cod_prov"></select></label><label>Distrito<select id="g-dist" name="cod_dist"></select></label></div><p class="muted">El distrito puede quedar pendiente al registrar la llamada; es necesario para abrir el expediente al asignarlo.</p><button>Guardar comunicación</button></form>';
        $provs=$pdo->query('SELECT cod_dep,cod_prov,nombre FROM ubigeo_provincia ORDER BY nombre')->fetchAll();$dists=$pdo->query('SELECT cod_dep,cod_prov,cod_dist,nombre FROM ubigeo_distrito ORDER BY nombre')->fetchAll();
        echo '<script>const gp='.json_encode($provs,JSON_HEX_TAG|JSON_HEX_AMP).',gd='.json_encode($dists,JSON_HEX_TAG|JSON_HEX_AMP).';const dep=document.getElementById("g-dep"),prov=document.getElementById("g-prov"),dist=document.getElementById("g-dist");function fill(el,rows,key,value){el.replaceChildren(new Option("Por determinar",""));rows.forEach(r=>el.add(new Option(r.nombre,r[key],false,r[key]===value)));}function districts(value=""){fill(dist,gd.filter(r=>r.cod_dep===dep.value&&r.cod_prov===prov.value),"cod_dist",value);}function provinces(value="",dv=""){fill(prov,gp.filter(r=>r.cod_dep===dep.value),"cod_prov",value);districts(dv);}dep.onchange=()=>provinces();prov.onchange=()=>districts();provinces('.json_encode($data['cod_prov']??'').','.json_encode($data['cod_dist']??'').');</script>';
    } else {
        foreach(['fecha_llamada'=>'Llamada','comunicante'=>'Comunicante','telefono'=>'Teléfono','lugar'=>'Lugar','referencia'=>'Referencia','tipo_hecho'=>'Tipo preliminar','descripcion'=>'Descripción','vehiculos'=>'Vehículos','afectados'=>'Afectados','latitud'=>'Latitud preliminar','longitud'=>'Longitud preliminar'] as $f=>$label)echo '<p><strong>'.$label.':</strong> '.nl2br(Page::escape($row[$f]??'—')).'</p>';
        echo '<p class="notice">Consulta. La edición está reservada al autor durante las primeras 12 horas y al administrador.</p>';
    }
    echo '</section>';
    if($id && !$row['eliminado_en'] && !$row['accidente_id'] && $canCreate && (Access::admin() || (int)$row['creado_por']===Access::id())){
        echo '<section><h2>Asignar a JEFE EMI</h2><p>Se abre un único expediente con los datos iniciales. La hora real del accidente queda pendiente de verificación.</p><form method="post">';Page::token();echo '<input type="hidden" name="action" value="asignar"><label>Responsable<select name="jefe_id" required><option value="">Seleccionar</option>';
        foreach($pdo->query("SELECT id,nombre FROM usuarios WHERE activo=1 AND rol='jefe_emi' ORDER BY nombre") as $u)echo '<option value="'.$u['id'].'">'.Page::escape($u['nombre']).'</option>';echo '</select></label><button>Asignar y abrir expediente</button></form></section>';
    }
    if($id && Access::admin()){$operation=$row['eliminado_en']?'restaurar':'eliminar';echo '<section><details><summary>'.ucfirst($operation).' comunicación</summary><form method="post">';Page::token();echo '<input type="hidden" name="action" value="'.$operation.'"><label>Motivo<input name="motivo" required></label><button class="danger">'.ucfirst($operation).' comunicación</button></form></details></section>';}
    echo '<a href="guardia.php">Volver a comunicaciones</a>';
} else {
    if($canCreate)echo '<a class="button" href="guardia.php?nueva=1">Registrar llamada</a>';
    $deleted=Access::admin() && isset($_GET['eliminadas']);
    if(Access::admin())echo '<p><a href="guardia.php">Activas</a> · <a href="guardia.php?eliminadas=1">Eliminadas</a></p>';
    echo '<section><div class="table-scroll"><table><thead><tr><th>Registro</th><th>Lugar</th><th>Comunicante</th><th>Estado</th><th></th></tr></thead><tbody>';
    $page=max(1,(int)($_GET['pagina']??1));$offset=($page-1)*50;$rows=$pdo->query('SELECT c.*,u.nombre jefe FROM comunicaciones_guardia c LEFT JOIN usuarios u ON u.id=c.jefe_id WHERE c.eliminado_en IS '.($deleted?'NOT NULL':'NULL').' ORDER BY c.id DESC LIMIT 51 OFFSET '.$offset)->fetchAll();$more=count($rows)>50;
    foreach(array_slice($rows,0,50) as $c)echo '<tr><td>#'.$c['id'].'<br>'.Page::escape($c['registrado_en']).'</td><td>'.Page::escape($c['lugar']).'</td><td>'.Page::escape($c['comunicante']).'</td><td>'.Page::escape($c['jefe']??'Pendiente de asignación').'</td><td><a href="guardia.php?id='.$c['id'].'">Abrir</a></td></tr>';
    if(!$rows)echo '<tr><td colspan="5">Todavía no hay comunicaciones registradas.</td></tr>';echo '</tbody></table></div><div class="actions">';if($page>1)echo '<a href="?'.($deleted?'eliminadas=1&amp;':'').'pagina='.($page-1).'">Anterior</a>';if($more)echo '<a href="?'.($deleted?'eliminadas=1&amp;':'').'pagina='.($page+1).'">Siguiente</a>';echo '</div></section>';
}
Page::end();
