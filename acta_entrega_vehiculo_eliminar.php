<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\ActaRepository;
use App\Services\ActaService;

function h($v): string { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8'); }
$repo = new ActaRepository($pdo);
$service = new ActaService($repo);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$row = $repo->find($id);
if (!$row) { http_response_code(404); exit('Acta no encontrada'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $service->delete($id);
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>window.parent.postMessage({type:"acta.deleted"},"*")</script><body style="font:14px system-ui;padding:20px">Acta eliminada...</body>';
            exit;
        }
        header('Location: accidente_vista_tabs.php?accidente_id=' . (int) $row['accidente_id'] . '&tab=documentos');
        exit;
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Eliminar acta</title><style>body{background:#f6f7fb;font:14px system-ui;padding:20px;color:#172033}.box{max-width:650px;margin:auto;background:white;border:1px solid #ddd;border-radius:14px;padding:20px}.btn{padding:9px 13px;border:1px solid #ddd;border-radius:9px;background:white;text-decoration:none;color:#172033;font-weight:700;cursor:pointer}.danger{background:#b42318;color:white;border-color:#b42318}.actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}</style></head><body><div class="box"><h1>Eliminar acta #<?= $id ?></h1><?php if($error): ?><p><?= h($error) ?></p><?php endif; ?><p>Se eliminara el acta de entrega del vehiculo <strong><?= h($row['orden_participacion'] . ' - ' . $row['placa']) ?></strong>.</p><form method="post" class="actions"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>"><?php if($embed): ?><button type="button" class="btn" onclick="window.parent.postMessage({type:'acta.close'},'*')">Cancelar</button><?php endif; ?><button class="btn danger">Eliminar</button></form></div></body></html>
