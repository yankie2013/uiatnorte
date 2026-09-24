<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Support\Access;
use App\Support\WorkspacePage as Page;
Page::start('Expedientes en espera de recepción');
$where=Access::admin()?'1=1':'t.destino_id=?';
$s=$pdo->prepare("SELECT t.*,a.registro_sidpol,a.lugar,u.nombre origen,u.grado origen_grado,d.nombre destino,d.grado destino_grado
 FROM expediente_transferencias t JOIN accidentes_activos a ON a.id=t.accidente_id
 JOIN usuarios u ON u.id=t.origen_id JOIN usuarios d ON d.id=t.destino_id
 WHERE t.estado='pendiente' AND $where ORDER BY t.creado_en,t.id");
$s->execute(Access::admin()?[]:[Access::id()]);$rows=$s->fetchAll();
echo '<section><p>La responsabilidad cambia cuando el JEFE EMI destinatario acepta la transferencia.</p>';
if(!$rows)echo '<p>No hay expedientes pendientes de recepción.</p>';
foreach($rows as $row){
    $id=(int)$row['accidente_id'];
    echo '<article class="reception-item"><h2>Expediente '.Page::escape($row['registro_sidpol']?:'#'.$id).'</h2><p>'.Page::escape($row['lugar']).'</p><p>De: '.Page::escape(trim($row['origen_grado'].' '.$row['origen'])).'<br>Para: '.Page::escape(trim($row['destino_grado'].' '.$row['destino'])).'</p><p>'.Page::escape($row['motivo']).' · '.Page::escape($row['creado_en']).'</p><div class="actions"><a href="gestion_expedientes.php?id='.$id.'">Ver expediente</a><a href="accidente_vista_tabs.php?accidente_id='.$id.'&tab=estado">Ver transferencia</a></div>';
    if(Access::role()==='jefe_emi' && (int)$row['destino_id']===Access::id()){
        echo '<form method="post" action="expediente_estado.php?id='.$id.'">';Page::token();
        echo '<input type="hidden" name="action" value="aceptar"><button>Aceptar recepción</button></form>';
    }
    echo '</article>';
}
echo '</section>';Page::end();
