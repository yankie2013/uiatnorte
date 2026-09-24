<?php
require_once __DIR__.'/auth.php';require_login();require_once __DIR__.'/db.php';
use App\Support\Access;
use App\Support\WorkspacePage as Page;
use App\Services\ExpedienteAccessService;
$id=(int)($_GET['id']??$_POST['id']??0);
$url='accidente_vista_tabs.php?accidente_id='.$id.'&tab=estado';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: '.$url);exit;}
try {
    Access::checkCsrf();
    $action=(string)($_POST['action']??'');
    $reason=trim((string)($action==='cambiar_estado'?($_POST['estado']??''):($_POST['motivo']??'')));
    (new ExpedienteAccessService($pdo))->change($id,$action,(int)($_POST['usuario_id']??0),$reason);
    header('Location: '.$url.'&gestion_ok=1');exit;
} catch(Throwable $e) {
    http_response_code(422);
    Page::start('No se pudo guardar el cambio');Page::notice($e->getMessage(),true);
    echo '<a href="'.Page::escape($url).'">Volver al estado del expediente</a>';
    Page::end();
}
