<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\DocumentoRecibidoRepository;
use App\Services\DocumentoRecibidoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

$service = new DocumentoRecibidoService(new DocumentoRecibidoRepository($pdo));
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$row = $service->detalle($id);
if(!$row){
    http_response_code(404);
    exit('Documento no encontrado');
}
$ctx = $service->formContext(!empty($row['accidente_id']) ? (int)$row['accidente_id'] : null);
$data = $service->defaultData($row);
$errores = [];

if($_SERVER['REQUEST_METHOD']==='POST'){
    $data = $service->defaultData($_POST);
    try {
        $service->actualizar($id, $_POST);
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>try{ window.parent.postMessage({type:"documento_recibido.saved"}, "*"); }catch(_){ }</script><body style="font:13px Inter,sans-serif;padding:16px">Guardado...</body>';
            exit;
        }
        header('Location: documento_recibido_ver.php?id=' . $id);
        exit;
    } catch (Throwable $e) {
        $errores[] = $e->getMessage();
        $ctx = $service->formContext(($data['accidente_id'] !== '' ? (int) $data['accidente_id'] : null));
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editar Documento Recibido</title>
<style>
:root{--bg:#fafafa;--panel:#ffffff;--text:#111827;--muted:#6b7280;--border:#e5e7eb;--primary:#0b84ff;--radius:10px;--gap:14px;--max-width:980px}
@media (prefers-color-scheme: dark){:root{--bg:#0b1220;--panel:#0f1724;--text:#e6eef8;--muted:#9aa7bf;--border:#243041;--primary:#4ea8ff}}
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;background:var(--bg);color:var(--text);padding:20px}.wrap{max-width:var(--max-width);margin:18px auto;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:20px}form{display:grid;grid-template-columns:1fr 1fr;gap:var(--gap)}.full{grid-column:1/-1}label{display:block;font-weight:700;margin-bottom:6px}input[type=text],input[type=date],select,textarea{width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text)}textarea{min-height:120px}.actions{grid-column:1/-1;display:flex;justify-content:space-between;margin-top:8px}.btn{padding:8px 12px;border-radius:8px;text-decoration:none;font-weight:700;border:1px solid var(--border);background:transparent;color:var(--text)}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.error{background:#fff0f0;padding:10px;border-radius:8px;border:1px solid #f5c2c2;color:#8a1f1f;margin-bottom:12px}@media (max-width:800px){form{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
<h1 style="margin-top:0">Editar Documento Recibido #<?= (int)$id ?></h1>
<?php if($errores): ?><div class="error"><?php foreach($errores as $e): ?>- <?= h($e) ?><br><?php endforeach; ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>"><input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
<div><label>Accidente</label><select name="accidente_id"><option value="">(ninguno)</option><?php foreach($ctx['accidentes'] as $a): ?><option value="<?= h($a['id']) ?>" <?= ((string)$data['accidente_id']===(string)$a['id'])?'selected':'' ?>><?= h($a['id']) ?> - <?= h($a['sidpol'] ?? '') ?><?= !empty($a['lugar']) ? (' - '.h($a['lugar'])) : '' ?></option><?php endforeach; ?></select></div>
<div><label>Fecha</label><input type="date" name="fecha" value="<?= h($data['fecha']) ?>"></div>
<div class="full"><label>Asunto</label><input type="text" name="asunto" value="<?= h($data['asunto']) ?>"></div>
<div><label>Entidad / Persona</label><input type="text" name="entidad_persona" value="<?= h($data['entidad_persona']) ?>"></div>
<div><label>Tipo de documento</label><input type="text" name="tipo_documento" value="<?= h($data['tipo_documento']) ?>"></div>
<div><label>Numero de documento</label><input type="text" name="numero_documento" value="<?= h($data['numero_documento']) ?>"></div>
<div><label>Estado</label><select name="estado"><option value="">(ninguno)</option><?php foreach($ctx['estados'] as $estado): ?><option value="<?= h($estado) ?>" <?= ($data['estado']===$estado)?'selected':'' ?>><?= h($estado) ?></option><?php endforeach; ?></select></div>
<div class="full"><label>Contenido</label><textarea name="contenido"><?= h($data['contenido']) ?></textarea></div>
<div><label>Referencia a oficio</label><select name="referencia_oficio_id"><option value="">(ninguno)</option><?php foreach($ctx['oficios'] as $o): ?><option value="<?= h($o['id']) ?>" <?= ((string)$data['referencia_oficio_id']===(string)$o['id'])?'selected':'' ?>><?= h($service->oficioLabel($o, $ctx['asuntos'])) ?></option><?php endforeach; ?></select></div>
<div class="actions"><?php if ($embed): ?><button type="button" class="btn" onclick="try{window.parent&&window.parent.postMessage({type:'documento_recibido.close'},'*');}catch(e){}">Cancelar</button><?php else: ?><a class="btn" href="documento_recibido_ver.php?id=<?= (int)$id ?>">Cancelar</a><?php endif; ?><button class="btn primary" type="submit">Guardar cambios</button></div>
</form></div></body></html>
