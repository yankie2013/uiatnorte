<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoDosajeRepository;
use App\Services\DocumentoDosajeService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=''){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

$service = new DocumentoDosajeService(new DocumentoDosajeRepository($pdo));
$id = (int) g('id', 0);
$embed = g('embed', '') !== '' ? 1 : 0;
$return_to = g('return_to', '');
if ($id <= 0) {
    http_response_code(400);
    exit('ID invalido');
}

$detalle = $service->detalle($id);
if (!$detalle) {
    http_response_code(404);
    exit('Registro no encontrado');
}
$row = $detalle['row'];
$persona = trim(($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ', ' . ($row['nombres'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Dosaje - Ver</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
  .hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:8px;flex-wrap:wrap}
  .ttl{font-weight:900;font-size:22px}
  .card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.01));border:1px solid var(--line);border-radius:var(--r);padding:12px;box-shadow:0 10px 26px rgba(0,0,0,.22)}
  .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:8px}
  .c12{grid-column:span 12}.c6{grid-column:span 6}
  @media(max-width:940px){.c6{grid-column:span 12}}
  .row{padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--chip)}
  .lbl{color:var(--muted);font-weight:800;font-size:12px;margin-bottom:4px}
  .actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <div class="ttl">Dosaje - Detalle</div>
    <a class="btn small" href="<?= $return_to ? h($return_to) : 'javascript:history.back()' ?>"><?= $embed ? 'Cerrar' : 'Volver' ?></a>
  </div>

  <div class="card">
    <div class="grid">
      <div class="c12">
        <div class="lbl">Persona</div>
        <div class="row"><?= h($persona ?: '-') ?> - DNI <?= h($row['num_doc'] ?? '-') ?></div>
      </div>
      <div class="c6"><div class="lbl">Numero</div><div class="row"><?= h($row['numero'] ?? '-') ?></div></div>
      <div class="c6"><div class="lbl">N&ordm; Registro</div><div class="row"><?= h($row['numero_registro'] ?? '-') ?></div></div>
      <div class="c6"><div class="lbl">Fecha extraccion</div><div class="row"><?= h($row['fecha_extraccion'] ?? '-') ?></div></div>
      <div class="c6"><div class="lbl">Resultado cualitativo</div><div class="row"><?= h($row['resultado_cualitativo'] ?? '-') ?></div></div>
      <div class="c6"><div class="lbl">Resultado cuantitativo (g/L)</div><div class="row"><?= h($row['resultado_cuantitativo'] ?? '-') ?></div></div>
      <div class="c6"><div class="lbl">Lectura cuantitativo</div><div class="row"><?= h($row['leer_cuantitativo'] ?? '-') ?></div></div>
      <div class="c12"><div class="lbl">Observaciones</div><div class="row"><?= nl2br(h($row['observaciones'] ?? '-')) ?></div></div>
    </div>

    <div class="actions">
      <a class="btn" href="documento_dosaje_editar.php?id=<?= (int)$row['id'] ?>&embed=<?= (int)$embed ?>&return_to=<?= urlencode($return_to) ?>">Editar</a>
      <a class="btn danger" href="documento_dosaje_eliminar.php?id=<?= (int)$row['id'] ?>&embed=<?= (int)$embed ?>&return_to=<?= urlencode($return_to) ?>">Eliminar</a>
    </div>
  </div>
</div>
</body>
</html>
