<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\DocumentoRecibidoRepository;
use App\Services\DocumentoRecibidoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=''){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

$service = new DocumentoRecibidoService(new DocumentoRecibidoRepository($pdo));
$id = (int)g('id', 0);
$embed = (int) g('embed', 0) === 1;
$row = $service->detalle($id);
if(!$row){
    http_response_code(404);
    exit('Documento no encontrado');
}
$returnTo = g('return_to', '');
if ($returnTo === '') {
    $returnTo = 'documento_recibido_listar.php';
    if (!empty($row['accidente_id'])) {
        $returnTo .= '?accidente_id=' . urlencode((string)$row['accidente_id']);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Documento Recibido</title>
<style>
:root{--bg:#f3f7fb;--panel:#fff;--soft:#f7fafd;--text:#172033;--muted:#66758a;--border:#d8e2ee;--primary:#0f9f91;--primary-dark:#087d73}
@media(prefers-color-scheme:dark){:root{--bg:#0b1220;--panel:#111b2e;--soft:#0e1728;--text:#e8eef8;--muted:#9aa9bd;--border:#30405a;--primary:#14b8a6;--primary-dark:#0f9488}}
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;padding:18px;background:var(--bg);color:var(--text)}body.is-embed{padding:14px;background:var(--soft)}
.wrap{max-width:900px;margin:0 auto;background:var(--panel);border:1px solid var(--border);border-radius:14px;padding:20px}body.is-embed .wrap{border:0;padding:10px 12px;background:transparent}
.head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:15px}.eyebrow{margin:0 0 4px;color:var(--primary-dark);font-size:12px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}h1{margin:0;font-size:1.25rem}.state{padding:6px 10px;border-radius:999px;background:rgba(15,159,145,.12);color:var(--primary-dark);font-size:12px;font-weight:800}
.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.card{padding:12px;border:1px solid var(--border);border-radius:11px;background:var(--panel)}.card.full{grid-column:1/-1}.lbl{font-size:11px;color:var(--muted);font-weight:800;letter-spacing:.04em;text-transform:uppercase;margin-bottom:5px}.val{font-size:14px;line-height:1.5;overflow-wrap:anywhere}.annex-list{margin:4px 0 0;padding-left:22px}.annex-list li+li{margin-top:6px}
.actions{display:flex;gap:8px;justify-content:flex-end;margin-top:16px;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;padding:9px 13px;border-radius:9px;border:1px solid var(--border);text-decoration:none;color:var(--text);background:var(--panel);font:inherit;font-weight:750;cursor:pointer}.btn.primary{background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;border-color:transparent}.btn.danger{color:#b42318}
@media(max-width:620px){body,body.is-embed{padding:8px}.wrap,body.is-embed .wrap{padding:8px}.detail-grid{grid-template-columns:1fr}.card.full{grid-column:auto}.head{align-items:center}.actions .btn{flex:1}}
</style>
</head>
<body class="<?= $embed ? 'is-embed' : '' ?>">
<div class="wrap">
<div class="head"><div><p class="eyebrow">Documentos recibidos</p><h1>Documento recibido #<?= (int)$row['id'] ?></h1></div><span class="state"><?= h($row['estado']) ?: 'Sin estado' ?></span></div>
<div class="detail-grid">
  <div class="card full"><div class="lbl">Asunto</div><div class="val"><?= h($row['asunto']) ?: '-' ?></div></div>
  <div class="card"><div class="lbl">Entidad o persona remitente</div><div class="val"><?= h($row['entidad_persona']) ?: '-' ?></div></div>
  <div class="card"><div class="lbl">Tipo de documento</div><div class="val"><?= h($row['tipo_documento']) ?: '-' ?></div></div>
  <div class="card"><div class="lbl">Número de documento y siglas</div><div class="val"><?= h($row['numero_documento']) ?: '-' ?></div></div>
  <div class="card"><div class="lbl">Fecha del documento</div><div class="val"><?= h($row['fecha_documento_resuelta'] ?? $row['fecha_documento'] ?? $row['fecha'] ?? '') ?: '-' ?></div></div>
  <div class="card"><div class="lbl">Fecha de recepción</div><div class="val"><?= h($row['fecha_recepcion_resuelta'] ?? $row['fecha_recepcion'] ?? $row['fecha'] ?? '') ?: '-' ?></div></div>
  <div class="card"><div class="lbl">Accidente</div><div class="val"><?= !empty($row['accidente_id']) ? ('#' . (int)$row['accidente_id']) : '-' ?></div></div>
  <div class="card"><div class="lbl">Oficio relacionado</div><div class="val"><?= !empty($row['referencia_oficio_id']) ? ('#' . (int)$row['referencia_oficio_id']) : '-' ?></div></div>
  <div class="card full"><div class="lbl">Contenido</div><div class="val"><?= !empty($row['contenido']) ? nl2br(h($row['contenido'])) : '-' ?></div></div>
  <div class="card full"><div class="lbl">Anexos remitidos</div><div class="val"><?php if (!empty($row['anexos'])): ?><ol class="annex-list"><?php foreach ($row['anexos'] as $anexo): ?><li><?= nl2br(h($anexo['descripcion'] ?? '')) ?></li><?php endforeach; ?></ol><?php else: ?>-<?php endif; ?></div></div>
</div>
<div class="actions">
<?php if ($embed): ?>
  <button class="btn" type="button" onclick="try{window.parent&&window.parent.postMessage({type:'documento_recibido.close'},'*');}catch(e){}">Cerrar</button>
<?php else: ?>
  <a class="btn" href="<?= h($returnTo) ?>">Volver</a>
<?php endif; ?>
  <a class="btn primary" href="documento_recibido_editar.php?id=<?= (int)$row['id'] ?>&embed=<?= $embed ? 1 : 0 ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a>
  <a class="btn danger" href="documento_recibido_eliminar.php?id=<?= (int)$row['id'] ?>&embed=<?= $embed ? 1 : 0 ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
</div>
</div>
</body>
</html>
