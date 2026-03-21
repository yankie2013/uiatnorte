<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoOccisoRepository;
use App\Services\DocumentoOccisoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

$service = new DocumentoOccisoService(new DocumentoOccisoRepository($pdo));
$id = (int) g('id', 0);
$embed = (int) g('embed', 0);
$return_to = g('return_to', '');
if ($id <= 0) {
    http_response_code(400);
    exit('ID invalido');
}

$it = $service->detalle($id);
if (!$it) {
    http_response_code(404);
    exit('No encontrado');
}

function P($row, $k){ return h($row[$k] ?? ''); }
function persona_lbl($row){
    $txt = trim(($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? ''));
    return $txt !== '' ? $txt : ('ID ' . $row['persona_id']);
}
function acc_lbl($row){
    $parts = [];
    if (!empty($row['registro_sidpol'])) $parts[] = $row['registro_sidpol'];
    $parts[] = !empty($row['fecha_accidente']) ? date('Y-m-d H:i', strtotime($row['fecha_accidente'])) : 's/f';
    if (!empty($row['lugar_accidente'])) $parts[] = $row['lugar_accidente'];
    return '#' . $row['accidente_id'] . ' - ' . implode(' - ', $parts);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Occiso - Ver #<?= (int)$id ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
  .rowv{display:grid;grid-template-columns:180px 1fr;gap:8px;margin:6px 0}
  .mut{opacity:.7}
  .card{padding:22px !important;border-radius:14px !important;background:rgba(255,255,255,0.03) !important;border:1px solid rgba(255,255,255,0.06) !important}
  .card .pad{padding:10px 6px !important}
  .summary-box{border:1px solid rgba(255,255,255,0.05);background:rgba(255,255,255,0.03);padding:12px 14px;border-radius:10px;white-space:pre-wrap;text-align:justify;line-height:1.45;font-size:14px}
  @media(max-width:920px){.grid2{grid-template-columns:1fr !important}.rowv{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <a class="btn small" href="<?= $return_to ? h($return_to) : 'javascript:history.back()' ?>"><?= $embed ? 'Cerrar' : 'Volver' ?></a>
    <span></span>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn small" href="documento_occiso_eliminar.php?id=<?= $id ?>&embed=<?= $embed ?>&return_to=<?= urlencode($return_to) ?>">Eliminar</a>
      <a class="btn small" href="documento_occiso_editar.php?id=<?= $id ?>&embed=<?= $embed ?>&return_to=<?= urlencode($return_to) ?>">Editar</a>
    </div>
  </div>
  <h1>Occiso - Ver <span class="badge">#<?= (int)$id ?></span></h1>

  <div class="card"><div class="pad">
    <div class="rowv"><div class="mut">Persona</div><div><?= h(persona_lbl($it)) ?></div></div>
    <div class="rowv"><div class="mut">Accidente</div><div><?= h(acc_lbl($it)) ?></div></div>
  </div></div>

  <div class="grid2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
    <div class="card"><div class="pad">
      <h3>Levantamiento</h3>
      <div class="rowv"><div class="mut">Fecha</div><div><?= P($it,'fecha_levantamiento') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Hora</div><div><?= P($it,'hora_levantamiento') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Lugar</div><div><?= P($it,'lugar_levantamiento') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Posicion</div><div><?= P($it,'posicion_cuerpo_levantamiento') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Presuntivo</div><div><?= P($it,'presuntivo_levantamiento') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Legista</div><div><?= P($it,'legista_levantamiento') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">CMP</div><div><?= P($it,'cmp_legista') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Observaciones</div><div class="summary-box"><?= nl2br(h($it['observaciones_levantamiento'] ?? '-')) ?></div></div>
      <div class="rowv"><div class="mut">Lesiones</div><div class="summary-box"><?= nl2br(h($it['lesiones_levantamiento'] ?? '-')) ?></div></div>
    </div></div>

    <div class="card"><div class="pad">
      <h3>Protocolo</h3>
      <div class="rowv"><div class="mut">N&ordm;</div><div><?= P($it,'numero_protocolo') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Fecha</div><div><?= P($it,'fecha_protocolo') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Hora</div><div><?= P($it,'hora_protocolo') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Presuntivo</div><div><?= P($it,'presuntivo_protocolo') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Dosaje</div><div><?= P($it,'dosaje_protocolo') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Toxicologico</div><div><?= P($it,'toxicologico_protocolo') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Lesiones</div><div class="summary-box"><?= nl2br(h($it['lesiones_protocolo'] ?? '-')) ?></div></div>
      <h3 style="margin-top:14px">Pericial</h3>
      <div class="rowv"><div class="mut">N&ordm; pericial</div><div><?= P($it,'numero_pericial') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Fecha</div><div><?= P($it,'fecha_pericial') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Hora</div><div><?= P($it,'hora_pericial') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Obs. pericial</div><div class="summary-box"><?= nl2br(h($it['observaciones_pericial'] ?? '-')) ?></div></div>
      <h3 style="margin-top:14px">Epicrisis</h3>
      <div class="rowv"><div class="mut">Nosocomio</div><div><?= P($it,'nosocomio_epicrisis') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">N&ordm; Historia</div><div><?= P($it,'numero_historia_epicrisis') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Hora alta</div><div><?= P($it,'hora_alta_epicrisis') ?: '-' ?></div></div>
      <div class="rowv"><div class="mut">Tratamiento</div><div class="summary-box"><?= nl2br(h($it['tratamiento_epicrisis'] ?? '-')) ?></div></div>
    </div></div>
  </div>
</div>
</body>
</html>
