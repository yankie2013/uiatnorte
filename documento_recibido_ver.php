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
body{font-family:Inter,system-ui;padding:20px;background:#f6f7fb;color:#111}
.wrap{max-width:900px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px}
.row{margin:10px 0}.lbl{font-size:12px;color:#6b7280;font-weight:700}.val{font-size:14px;line-height:1.45}
.actions{display:flex;gap:8px;justify-content:flex-end;margin-top:16px;flex-wrap:wrap}
.btn{padding:8px 12px;border-radius:8px;border:1px solid #d1d5db;text-decoration:none;color:#111;background:#fff}
.btn.primary{background:#0b84ff;color:#fff;border-color:#0b84ff}
</style>
</head>
<body>
<div class="wrap">
<h1 style="margin-top:0">Documento recibido #<?= (int)$row['id'] ?></h1>
<div class="row"><div class="lbl">Asunto</div><div class="val"><?= h($row['asunto']) ?: '-' ?></div></div>
<div class="row"><div class="lbl">Entidad / Persona</div><div class="val"><?= h($row['entidad_persona']) ?: '-' ?></div></div>
<div class="row"><div class="lbl">Tipo de documento</div><div class="val"><?= h($row['tipo_documento']) ?: '-' ?></div></div>
<div class="row"><div class="lbl">Numero de documento</div><div class="val"><?= h($row['numero_documento']) ?: '-' ?></div></div>
<div class="row"><div class="lbl">Fecha de recepcion</div><div class="val"><?= h($row['fecha_recepcion_resuelta'] ?? $row['fecha_recepcion'] ?? $row['fecha'] ?? '') ?: '-' ?></div></div>
<div class="row"><div class="lbl">Fecha del documento</div><div class="val"><?= h($row['fecha_documento_resuelta'] ?? $row['fecha_documento'] ?? $row['fecha'] ?? '') ?: '-' ?></div></div>
<div class="row"><div class="lbl">Accidente</div><div class="val"><?= !empty($row['accidente_id']) ? ('#' . (int)$row['accidente_id']) : '-' ?></div></div>
<div class="row"><div class="lbl">Referencia a oficio</div><div class="val"><?= !empty($row['referencia_oficio_id']) ? ('#' . (int)$row['referencia_oficio_id']) : '-' ?></div></div>
<div class="row"><div class="lbl">Estado</div><div class="val"><?= h($row['estado']) ?: '-' ?></div></div>
<div class="row"><div class="lbl">Contenido</div><div class="val"><?= !empty($row['contenido']) ? nl2br(h($row['contenido'])) : '-' ?></div></div>
<div class="actions"><a class="btn" href="<?= h($returnTo) ?>">Volver</a><a class="btn primary" href="documento_recibido_editar.php?id=<?= (int)$row['id'] ?>">Editar</a><a class="btn" href="documento_recibido_eliminar.php?id=<?= (int)$row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a></div>
</div>
</body>
</html>
