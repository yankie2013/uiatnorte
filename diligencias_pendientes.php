<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DiligenciaInboxRepository;
use App\Support\WorkspacePage as Page;

header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
$fragment = is_string($_GET['fragment']??null) ? $_GET['fragment'] : '';
if (!DiligenciaInboxRepository::allowed()) {
    http_response_code(403);
    if ($fragment !== '') { header('Content-Type: application/json; charset=utf-8'); exit(json_encode(['error'=>'No tienes acceso a esta bandeja.'],JSON_UNESCAPED_UNICODE)); }
    exit('Esta bandeja está disponible para el JEFE EMI y el administrador.');
}
if (($_SERVER['REQUEST_METHOD']??'GET') !== 'GET') { http_response_code(405); header('Allow: GET'); exit('Esta pantalla es de consulta.'); }
$repository = new DiligenciaInboxRepository($pdo);
$render = static function(string $view, array $variables): string {
    extract($variables, EXTR_SKIP);
    ob_start();
    try { require __DIR__.'/app/Views/diligencias_inbox/'.$view.'.php'; return (string)ob_get_contents(); }
    finally { ob_end_clean(); }
};
$error = ''; $q = ''; $state = ''; $result = ['rows'=>[],'total'=>0,'page'=>1,'pages'=>1,'selected'=>null];
$selected = null; $detailHtml = ''; $listHtml = '';
try {
    foreach (['q','estado','page','seleccion','id','fragment','locate'] as $key) {
        if (isset($_GET[$key]) && !is_string($_GET[$key])) throw new InvalidArgumentException('Filtro inválido.');
    }
    $q = trim($_GET['q']??'');
    $state = trim($_GET['estado']??'');
    if (strlen($q)>200 || ($state!=='' && !in_array($state,DiligenciaInboxRepository::STATES,true))) throw new InvalidArgumentException('Revisa el texto y el estado de búsqueda.');
    if ($fragment==='detail') {
        $taskId = filter_var($_GET['id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 0;
        $selected = $repository->find($taskId);
        if (!$selected) { http_response_code(404); throw new RuntimeException('Diligencia no disponible.'); }
        $detailHtml = $render('detail',compact('pdo','repository','selected'));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['detailHtml'=>$detailHtml,'selectedId'=>(int)$selected['id']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        exit;
    }
    if (!in_array($fragment,['','list'],true)) throw new InvalidArgumentException('Solicitud inválida.');
    $page = filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]) ?: 1;
    $selectedId = filter_var($_GET['seleccion']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]) ?: 0;
    $result = $repository->search($q,$state,$page,$selectedId,($_GET['locate']??'')==='1');
    $selected = $result['selected'];
    $detailHtml = $render('detail',compact('pdo','repository','selected'));
    $listHtml = $render('list',compact('result','selected','q','state'));
    if ($fragment==='list') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['listHtml'=>$listHtml,'detailHtml'=>$detailHtml,'selectedId'=>(int)($selected['id']??0),'total'=>$result['total'],'page'=>$result['page']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        exit;
    }
} catch (Throwable $e) {
    if ($e instanceof InvalidArgumentException) { http_response_code(422); $error=$e->getMessage(); }
    elseif (http_response_code()===404) $error='La diligencia ya no está disponible en tu bandeja.';
    else { http_response_code(500); error_log('Diligence inbox read failed: '.$e->getMessage()); $error='No pudimos cargar las diligencias. Intenta nuevamente.'; }
    if ($fragment!=='') { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error'=>$error],JSON_UNESCAPED_UNICODE); exit; }
}
Page::start('Diligencias pendientes','assets/css/diligencias-inbox.css?v='.filemtime(__DIR__.'/assets/css/diligencias-inbox.css'),null,true,$result['total'].' '.($result['total']===1?'diligencia':'diligencias'));
?>
<p id="inbox-status" role="status" aria-live="polite"><?= Page::escape($error) ?></p>
<?php if ($error): ?><a class="inbox-retry" href="diligencias_pendientes.php">Volver a intentar</a><?php endif; ?>
<div class="inbox-layout" data-selected-id="<?= (int)($selected['id']??0) ?>">
  <aside class="inbox-detail-pane" aria-label="Expediente de la diligencia seleccionada">
    <button class="inbox-mobile-back" type="button">← Volver a la lista</button>
    <div id="inbox-detail" tabindex="-1"><?= $detailHtml ?></div>
  </aside>
  <div class="inbox-list-pane" aria-label="Lista de diligencias" tabindex="0"><div id="inbox-list"><?= $listHtml ?></div></div>
</div>
<dialog id="inbox-task-dialog" class="inbox-task-dialog" aria-labelledby="inbox-modal-title">
  <header class="inbox-modal-header"><h2 id="inbox-modal-title">Consulta de diligencia pendiente</h2><button type="button" class="inbox-modal-close" aria-label="Cerrar consulta">×</button></header>
  <p id="inbox-modal-status" role="status">Cargando diligencia…</p>
  <iframe id="inbox-task-frame" title="Consulta de la diligencia seleccionada" hidden></iframe>
</dialog>
<script src="assets/js/diligencias-inbox.js?v=<?= filemtime(__DIR__.'/assets/js/diligencias-inbox.js') ?>" defer></script>
<?php Page::end(); ?>
