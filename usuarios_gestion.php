<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Support\Access;use App\Support\WorkspacePage as Page;
if(!Access::admin()){http_response_code(403);exit('Acceso exclusivo del administrador.');}
$error='';$id=(int)($_GET['id']??$_POST['id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        Access::checkCsrf();$role=(string)($_POST['rol']??'');$active=(int)($_POST['activo']??0);
        if(!in_array($role,['admin','jefe_emi','adjunto','secretaria','guardia'],true))throw new RuntimeException('Perfil inválido.');
        if($id===Access::id() && ($role!=='admin'||!$active))throw new RuntimeException('No puede quitarse su propio acceso administrativo.');
        $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT id FROM usuarios WHERE id=? FOR UPDATE');$s->execute([$id]);if(!$s->fetch())throw new RuntimeException('Usuario no encontrado.');
        $s=$pdo->prepare('SELECT COUNT(*) FROM accidentes WHERE responsable_id=? AND eliminado_en IS NULL');$s->execute([$id]);
        if((!in_array($role,['jefe_emi','admin'],true)||!$active) && $s->fetchColumn())throw new RuntimeException('Transfiera sus expedientes antes de desactivar al usuario o cambiar su perfil.');
        $cip=trim((string)($_POST['cip']??''));
        if($cip!=='') {
            $cip=\App\Support\PasswordPolicy::cip($cip);
            $s=$pdo->prepare('SELECT id FROM usuarios WHERE cip=? AND id<>?');$s->execute([$cip,$id]);
            if($s->fetch())throw new RuntimeException('El CIP ya pertenece a otro usuario.');
        }
        $name=trim((string)($_POST['nombre']??''));if($name==='')throw new RuntimeException('Indique el nombre completo.');
        $pdo->prepare('UPDATE usuarios SET nombre=?,rol=?,activo=?,grado=?,cip=?,cargo=?,unidad=?,telefono=? WHERE id=?')->execute([$name,$role,$active?1:0,trim($_POST['grado']??''),$cip!==''?$cip:null,trim($_POST['cargo']??''),trim($_POST['unidad']??''),trim($_POST['telefono']??''),$id]);
        $password=(string)($_POST['clave']??'');if($password!==''){\App\Support\PasswordPolicy::validate($password,$cip);$pdo->prepare('UPDATE usuarios SET pass_hash=?,must_change_password=1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);}
        $pdo->commit();header('Location: usuarios_gestion.php?id='.$id.'&ok=1');exit;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}
Page::start('Usuarios y perfiles');Page::notice($error,true);if(isset($_GET['ok']))Page::notice('Perfil actualizado. Los permisos se aplican en la siguiente petición.');
echo '<div class="actions"><a class="button" href="usuarios_nuevo.php">Crear usuario</a><a href="usuarios_gestion.php">Ver todos</a></div>';
if($id){$s=$pdo->prepare('SELECT id,email,nombre,rol,activo,grado,cip,cargo,unidad,telefono FROM usuarios WHERE id=?');$s->execute([$id]);$u=$s->fetch();if($u){echo '<section><h2>'.Page::escape($u['email']).'</h2><form method="post">';Page::token();echo '<div class="grid">';foreach(['nombre'=>'Nombre completo','grado'=>'Grado','cip'=>'CIP','telefono'=>'Teléfono de contacto','cargo'=>'Cargo','unidad'=>'Unidad'] as $f=>$label)echo '<label>'.$label.'<input name="'.$f.'" value="'.Page::escape($u[$f]).'" '.($f==='nombre'?'required':'').'></label>';echo '<label>Perfil<select name="rol">';foreach(Access::ROLES as $key=>$label)if(!in_array($key,['editor','viewer'],true))echo '<option value="'.$key.'" '.($key===$u['rol']?'selected':'').'>'.$label.'</option>';echo '</select></label><label>Estado<select name="activo"><option value="1" '.($u['activo']?'selected':'').'>Activo</option><option value="0" '.(!$u['activo']?'selected':'').'>Inactivo</option></select></label><label>Restablecer contraseña (opcional; deberá cambiarla al ingresar)<input type="password" name="clave" minlength="10" autocomplete="new-password"></label></div><button>Guardar perfil</button></form></section>';}}
echo '<section><div class="table-scroll"><table><thead><tr><th>Nombre</th><th>CIP</th><th>Perfil</th><th>Cargo y unidad</th><th>Estado</th><th></th></tr></thead><tbody>';
foreach($pdo->query('SELECT id,nombre,cip,rol,cargo,unidad,activo FROM usuarios ORDER BY nombre') as $u)echo '<tr><td>'.Page::escape($u['nombre']).'</td><td>'.Page::escape($u['cip']??'Sin registrar').'</td><td>'.Page::escape(Access::ROLES[$u['rol']]??$u['rol']).'</td><td>'.Page::escape($u['cargo'].' · '.$u['unidad']).'</td><td>'.($u['activo']?'Activo':'Inactivo').'</td><td><a href="?id='.$u['id'].'">Editar</a></td></tr>';echo '</tbody></table></div></section>';Page::end();
