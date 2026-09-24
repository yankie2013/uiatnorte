<?php
use App\Support\Access;
use App\Support\WorkspacePage as Page;
(function (PDO $pdo, int $id): void {
    // Older production schemas do not yet store the user's grade.
    // Keep identity/permissions intact and omit only this optional label.
    $grade = 'grado';
    try {
        $pdo->query('SELECT grado FROM usuarios WHERE 1=0');
    } catch (\PDOException $error) {
        if ((string) $error->getCode() !== '42S22'
            && !str_contains($error->getMessage(), 'no such column: grado')) {
            throw $error;
        }
        $grade = 'NULL';
    }
    $qualifiedGrade = $grade === 'NULL' ? 'NULL' : 'u.grado';
    $users=$pdo->query("SELECT id,nombre,$grade AS grado,rol FROM usuarios WHERE activo=1 AND rol IN ('jefe_emi','adjunto') ORDER BY nombre")->fetchAll();
    $responsibleLabel = static fn($name, $grade = null) => trim(trim((string)$grade).' '.trim((string)$name)) ?: 'Sin asignar';
    $s=$pdo->prepare("SELECT a.*,u.nombre responsable,$qualifiedGrade responsable_grado FROM accidentes a LEFT JOIN usuarios u ON u.id=a.responsable_id WHERE a.id=?");$s->execute([$id]);$a=$s->fetch();
    if(!$a || ($a['eliminado_en'] && !Access::admin())){Page::notice('Expediente no disponible.',true);return;}
    $manager=Access::admin() || (Access::role()==='jefe_emi' && (int)$a['responsable_id']===Access::id());
    echo '<div class="state-workspace-grid">';
    echo '<section class="state-block state-block-case"><h2>Expediente #'.$id.' · '.Page::escape($a['registro_sidpol']??'Sin SIDPOL').'</h2><p>'.Page::escape($a['lugar']).'</p><p>Responsable: <strong>'.Page::escape($responsibleLabel($a['responsable'], $a['responsable_grado'])).'</strong></p><div class="actions"><a href="accidente_vista_tabs.php?accidente_id='.$id.'">Abrir expediente</a><a href="gestion_expedientes.php">Buscador general</a></div>';
    if($a['eliminado_en'])echo '<p class="notice error">Eliminado el '.Page::escape($a['eliminado_en']).': '.Page::escape($a['motivo_eliminacion']).'</p>';
    echo '<p>Ubicación: <strong>'.($a['ubicacion_verificada']?'Verificada':'Pendiente de verificación').'</strong></p>';
    if(!$a['ubicacion_verificada'] && Access::canEdit($id) && $a['latitud']!==null && $a['longitud']!==null){echo '<form method="post" action="expediente_estado.php?id='.$id.'">';Page::token();echo '<input type="hidden" name="action" value="verificar_ubicacion"><button>Confirmar ubicación verificada</button></form>';}
    echo '<p>Estado actual: <strong>'.Page::escape($a['estado']).'</strong></p>';
    if (!$a['eliminado_en'] && Access::canEdit($id)) {
        echo '<form method="post" action="expediente_estado.php?id='.$id.'">';
        Page::token();
        echo '<input type="hidden" name="action" value="cambiar_estado"><label>Estado del expediente<select name="estado" required>';
        foreach (['Pendiente','Con diligencias','Resuelto','Desestimado'] as $state) echo '<option '.($a['estado']===$state?'selected':'').'>'.Page::escape($state).'</option>';
        echo '</select></label><button>Guardar estado</button></form>';
    }
    echo '</section>';
    $s=$pdo->prepare("SELECT c.*,u.nombre,$qualifiedGrade AS grado FROM expediente_colaboradores c JOIN usuarios u ON u.id=c.usuario_id WHERE c.accidente_id=? AND c.revocado_en IS NULL");$s->execute([$id]);$collabs=$s->fetchAll();
    echo '<section class="state-block state-block-collaborators"><h2>Adjuntos con colaboración activa</h2><p>Al revocar la colaboración se conserva el historial y el acceso de consulta.</p>';
    if(!$collabs)echo '<p class="muted">Sin colaboradores activos.</p>';
    foreach($collabs as $c){echo '<p>'.Page::escape($responsibleLabel($c['nombre'], $c['grado'])).'</p>';if($manager && !$a['eliminado_en']){echo '<form method="post" action="expediente_estado.php?id='.$id.'">';Page::token();echo '<input type="hidden" name="action" value="revocar"><input type="hidden" name="usuario_id" value="'.$c['usuario_id'].'"><button>Revocar colaboración</button></form>';}}
    if($manager && !$a['eliminado_en']){echo '<form method="post" action="expediente_estado.php?id='.$id.'">';Page::token();echo '<input type="hidden" name="action" value="compartir"><label>Compartir con ADJUNTO<select name="usuario_id" required><option value="">Seleccionar</option>';foreach($users as $u)if($u['rol']==='adjunto')echo '<option value="'.$u['id'].'">'.Page::escape($responsibleLabel($u['nombre'], $u['grado'])).'</option>';echo '</select></label><button>Compartir expediente</button></form>';}
    echo '</section><section class="state-block state-block-transfers"><h2>Transferencias</h2><p>El responsable cambia cuando el destinatario acepta. Al aceptar, se cierran las colaboraciones anteriores.</p>';
    $s=$pdo->prepare("SELECT t.*,u.nombre destino,$qualifiedGrade destino_grado FROM expediente_transferencias t JOIN usuarios u ON u.id=t.destino_id WHERE t.accidente_id=? ORDER BY t.id DESC");$s->execute([$id]);$transfers=$s->fetchAll();$pending=false;
    foreach($transfers as $t){echo '<p><strong>'.Page::escape($responsibleLabel($t['destino'], $t['destino_grado'])).'</strong> · '.Page::escape($t['estado']).' · '.Page::escape($t['creado_en']).'<br>'.Page::escape($t['motivo']).'</p>';if($t['estado']==='pendiente'){$pending=true;if(!$a['eliminado_en'] && ((int)$t['destino_id']===Access::id() || $manager)){echo '<form method="post" action="expediente_estado.php?id='.$id.'">';Page::token();$action=(int)$t['destino_id']===Access::id()?'aceptar':'cancelar_transferencia';echo '<input type="hidden" name="action" value="'.$action.'"><button>'.($action==='aceptar'?'Aceptar transferencia':'Cancelar transferencia').'</button></form>';}}}
    if($manager && !$pending && !$a['eliminado_en']){echo '<form method="post" action="expediente_estado.php?id='.$id.'">';Page::token();echo '<input type="hidden" name="action" value="transferir"><label>Derivar a JEFE EMI<select name="usuario_id" required><option value="">Seleccionar</option>';foreach($users as $u)if($u['rol']==='jefe_emi' && (int)$u['id']!==(int)$a['responsable_id'])echo '<option value="'.$u['id'].'">'.Page::escape($responsibleLabel($u['nombre'], $u['grado'])).'</option>';echo '</select></label><label>Motivo<textarea name="motivo" required></textarea></label><button>Solicitar transferencia</button></form>';}
    echo '</section>';
    echo '</div>';
    if(Access::admin()){
        echo '<section><h2>Administración</h2>';
        if(!$a['eliminado_en']){echo '<details id="cambiar-responsable"><summary>Cambiar responsable</summary><form method="post" action="expediente_estado.php?id='.$id.'">';Page::token();echo '<input type="hidden" name="action" value="reasignar"><label>Nuevo responsable — JEFE EMI<select name="usuario_id" required><option value="">Seleccionar responsable</option>';foreach($users as $u)if($u['rol']==='jefe_emi' && (int)$u['id']!==(int)$a['responsable_id'])echo '<option value="'.$u['id'].'">'.Page::escape($responsibleLabel($u['nombre'], $u['grado'])).'</option>';echo '</select></label><label>Motivo<textarea name="motivo" required></textarea></label><button>Reasignar</button></form></details>';}
        echo '<form method="post" action="expediente_estado.php?id='.$id.'">';Page::token();$action=$a['eliminado_en']?'restaurar':'eliminar';echo '<input type="hidden" name="action" value="'.$action.'"><p>La eliminación es recuperable y conserva los documentos e historial.</p><label>Motivo para '.$action.'<textarea name="motivo" required></textarea></label><button class="danger">'.ucfirst($action).' expediente</button></form></section>';
    }
    $s=$pdo->prepare('SELECT a.*,u.nombre FROM auditoria a LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE a.accidente_id=? ORDER BY a.id DESC LIMIT 100');$s->execute([$id]);
    echo '<section><h2>Historial reciente</h2><p class="muted">Últimos 100 eventos. El historial completo se conserva en la base de datos.</p>';
    foreach($s as $event){echo '<details><summary>'.Page::escape($event['registrado_en'].' · '.($event['nombre']??'Migración histórica').' · '.$event['accion'].' · '.$event['tabla']).'</summary><p>'.Page::escape($event['motivo']).'</p><pre>Antes: '.Page::escape($event['antes'])."\nDespués: ".Page::escape($event['despues']).'</pre></details>';}
    echo '</section>';

})($pdo, (int)$accidente_id);
