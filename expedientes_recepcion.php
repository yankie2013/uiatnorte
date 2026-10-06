<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Support\Access;
use App\Support\WorkspacePage as Page;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Access::checkCsrf();
    if (!in_array(Access::role(), ['jefe_emi','secretaria','administracion'], true)) {
        http_response_code(403);
        exit('Solo el destinatario puede aceptar la recepción.');
    }
    $ids = isset($_POST['aceptar_uno']) ? [$_POST['aceptar_uno']] : ($_POST['expedientes'] ?? []);
    if (!is_array($ids)) $ids = [];
    $ids = array_values(array_unique(array_filter(array_map(
        static fn($id) => is_scalar($id) ? (int)$id : 0, $ids
    ), static fn($id) => $id > 0)));
    $accepted = 0;
    $errors = [];
    $service = new \App\Services\ExpedienteAccessService($pdo);
    foreach ($ids as $id) {
        try {
            $service->change($id, 'aceptar', 0, '');
            $accepted++;
        } catch (Throwable $e) {
            $errors[] = 'Expediente #'.$id.': '.$e->getMessage();
        }
    }
    if (!$ids) $errors[] = 'Selecciona al menos un expediente.';
    $_SESSION['recepcion_resultado'] = ['accepted'=>$accepted, 'errors'=>$errors];
    header('Location: expedientes_recepcion.php', true, 303);
    exit;
}
Page::start('Expedientes en espera de recepción');
$result = $_SESSION['recepcion_resultado'] ?? null;
unset($_SESSION['recepcion_resultado']);
if ($result) {
    if ($result['accepted']) Page::notice('Recepción aceptada: '.$result['accepted'].' expediente(s). Ya están en tu espacio de trabajo.');
    foreach ($result['errors'] as $error) Page::notice($error, true);
}
$archiveRole=in_array(Access::role(), ['secretaria','administracion'], true);
$where=Access::admin()?'1=1':"t.destino_id=? AND t.tipo='".($archiveRole?'archivo':'investigacion')."'";
$s=$pdo->prepare("SELECT t.*,a.registro_sidpol,a.lugar,u.nombre origen,u.grado origen_grado,d.nombre destino,d.grado destino_grado
 FROM expediente_transferencias t JOIN accidentes_activos a ON a.id=t.accidente_id
 LEFT JOIN usuarios u ON u.id=t.origen_id JOIN usuarios d ON d.id=t.destino_id
 WHERE t.estado='pendiente' AND $where ORDER BY t.creado_en,t.id");
$s->execute(Access::admin()?[]:[Access::id()]);$rows=$s->fetchAll();
echo '<section><p>'.($archiveRole?'Solo aparecen expedientes enviados para archivo. Al aceptar se registra quién recibió el archivo; el JEFE EMI conserva la responsabilidad de la investigación.':'La responsabilidad cambia cuando el JEFE EMI destinatario acepta la transferencia.').'</p>';
if(!$rows)echo '<p>No hay expedientes pendientes de recepción.</p>';
if ($rows && in_array(Access::role(), ['jefe_emi','secretaria','administracion'], true)) {
    echo '<form method="post" id="recepcion-lote">';
    Page::token();
    echo '<label class="reception-select"><input type="checkbox" id="seleccionar-todos"> Seleccionar todos</label><button type="submit">Aceptar seleccionados</button><span id="seleccion-total" role="status">0 seleccionados</span></form>';
}
foreach($rows as $row){
    $id=(int)$row['accidente_id'];
    echo '<article class="reception-item"><h2>Expediente '.Page::escape($row['registro_sidpol']?:'#'.$id).'</h2><p>'.Page::escape($row['lugar']).'</p><p>Destino: '.($row['tipo']==='archivo'?'Archivo':'Investigación').'</p><p>De: '.Page::escape(trim(($row['origen_grado']??'').' '.($row['origen']??''))?:'Sin responsable anterior').'<br>Para: '.Page::escape(trim(($row['destino_grado']??'').' '.$row['destino'])).'</p>';
    if ($row['tipo']==='archivo') echo '<p><strong>Informe N°:</strong> '.Page::escape($row['informe_remision']?:'No registrado').'<br><strong>Oficio N°:</strong> '.Page::escape($row['oficio_remision']?:'No registrado').'</p>';
    echo '<p>'.Page::escape($row['motivo']).' · '.Page::escape($row['creado_en']).'</p>';
    if(in_array(Access::role(), ['jefe_emi','secretaria','administracion'], true) && (int)$row['destino_id']===Access::id()){
        echo '<label class="reception-select"><input type="checkbox" name="expedientes[]" value="'.$id.'" form="recepcion-lote" class="reception-check"> Seleccionar expediente '.Page::escape($row['registro_sidpol']?:'#'.$id).'</label>';
        echo '<form method="post">';Page::token();
        echo '<button name="aceptar_uno" value="'.$id.'">Aceptar recepción</button></form>';
    }
    echo '</article>';
}
echo '</section>';
?>
<style>
.gestion .reception-select{display:flex;align-items:center;gap:10px;margin:14px 0}
.gestion .reception-select input{width:18px;height:18px;margin:0}
.reception-item{border-top:1px solid #d5e3dd;padding:18px 0}
</style>
<script>
(()=>{
 const all=document.getElementById('seleccionar-todos');
 if(!all)return;
 const checks=[...document.querySelectorAll('.reception-check')];
 const status=document.getElementById('seleccion-total');
 const update=()=>{
  const count=checks.filter(c=>c.checked).length;
  all.checked=count===checks.length&&count>0;
  all.indeterminate=count>0&&count<checks.length;
  status.textContent=count+' seleccionados';
 };
 all.addEventListener('change',()=>{checks.forEach(c=>c.checked=all.checked);update();});
 checks.forEach(c=>c.addEventListener('change',update));
 update();
})();
</script>
<?php Page::end();
