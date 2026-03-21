<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\VehiculoRepository;
use App\Services\VehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function fmtDate($s){ return $s ? date('Y-m-d H:i', strtotime($s)) : '-'; }
function labelize($col){
  static $map = [
    'placa' => 'Placa',
    'serie_vin' => 'Serie VIN',
    'nro_motor' => 'Nro motor',
    'anio' => 'Anio',
    'color' => 'Color',
    'largo_mm' => 'Largo (m)',
    'ancho_mm' => 'Ancho (m)',
    'alto_mm' => 'Alto (m)',
    'notas' => 'Notas',
    'creado_en' => 'Creado',
    'actualizado_en' => 'Actualizado',
  ];
  return $map[$col] ?? ucwords(str_replace('_', ' ', $col));
}

$vehiculoService = new VehiculoService(new VehiculoRepository($pdo));
$id = (int) g('id', 0);
$placa = (string) g('placa', '');
$returnTo = (string) g('return_to', 'vehiculo_listar.php');
$selfUrl = $_SERVER['REQUEST_URI'] ?? ('vehiculo_leer.php?id=' . $id);

$detalle = $vehiculoService->detalle($id, $placa);
$v = $detalle['vehiculo'] ?? null;
$cntInv = $detalle['accidentes_vinculados'] ?? null;

$placaTxt = $v['placa'] ?? '';
$marcaModelo = trim(
    ($v['marca_nombre'] ?? '')
    . (($v['modelo_nombre'] ?? '') ? (' / ' . $v['modelo_nombre']) : '')
    . (($v['anio'] ?? '') ? (' - ' . $v['anio']) : '')
);

$clasificacionParts = [];
if(!empty($v['cat_codigo']) || !empty($v['cat_desc'])){
  $clasificacionParts[] = trim(($v['cat_codigo'] ?? '') . (($v['cat_desc'] ?? '') ? (' - ' . $v['cat_desc']) : ''));
}
if(!empty($v['tipo_codigo']) || !empty($v['tipo_nombre'])){
  $clasificacionParts[] = trim(($v['tipo_codigo'] ?? '') . (($v['tipo_nombre'] ?? '') ? (' - ' . $v['tipo_nombre']) : ''));
}
if(!empty($v['carroceria_nombre'])) $clasificacionParts[] = $v['carroceria_nombre'];
$clasificacion = $clasificacionParts ? implode(' / ', $clasificacionParts) : '-';

$dimensiones = '-';
if($v && ($v['largo_mm'] !== null || $v['ancho_mm'] !== null || $v['alto_mm'] !== null)){
  $L = $v['largo_mm'] !== null ? rtrim(rtrim((string)$v['largo_mm'], '0'), '.') : '-';
  $A = $v['ancho_mm'] !== null ? rtrim(rtrim((string)$v['ancho_mm'], '0'), '.') : '-';
  $H = $v['alto_mm'] !== null ? rtrim(rtrim((string)$v['alto_mm'], '0'), '.') : '-';
  $dimensiones = "$L x $A x $H m";
}

$excluir = [
  'id','placa','categoria_id','tipo_id','carroceria_id','marca_id','modelo_id','anio',
  'largo_mm','ancho_mm','alto_mm'
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= $v ? 'Ficha de vehiculo #' . (int)$v['id'] : 'Buscar vehiculo' ?> | UIAT Norte</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{color-scheme:light dark}
  body{margin:0;font-family:Inter,system-ui,sans-serif;font-size:13px}
  .wrap{max-width:1000px;margin:20px auto;padding:0 12px}
  .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px;flex-wrap:wrap}
  .title h1{margin:0;font-size:18px;font-weight:800}
  .badge{padding:2px 6px;border-radius:999px;background:rgba(127,127,127,.12);border:1px solid rgba(127,127,127,.25);font-size:10px;font-weight:700}
  .actions{display:flex;gap:8px;flex-wrap:wrap}
  .btn{padding:6px 10px;border-radius:10px;border:1px solid rgba(127,127,127,.25);text-decoration:none;font-weight:700;font-size:12px;color:inherit;background:transparent}
  .btn.primary{background:#0d6efd;color:#fff;border-color:#0b5ed7}
  .btn.danger{color:#b91c1c}
  .card{border:1px solid rgba(127,127,127,.25);border-radius:16px;padding:14px;box-shadow:0 4px 14px rgba(0,0,0,.12);background:rgba(255,255,255,.85)}
  @media (prefers-color-scheme: dark){ .card{background:rgba(255,255,255,.05)} }
  .header{margin-bottom:10px}
  .placa{font-size:18px;font-weight:900;letter-spacing:.3px}
  .sub{font-size:13px;opacity:.85}
  .grid{display:grid;gap:8px;grid-template-columns:repeat(4,1fr)}
  @media(max-width:960px){.grid{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:720px){.grid{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:520px){.grid{grid-template-columns:1fr}}
  .field{border:1px solid rgba(127,127,127,.25);border-radius:12px;padding:8px 10px;font-size:12px;background:rgba(127,127,127,.06)}
  @media (prefers-color-scheme: dark){ .field{background:rgba(255,255,255,.04)} }
  .label{font-size:11px;font-weight:700;opacity:.75;margin-bottom:2px}
  .val{font-weight:600;font-size:12px;word-break:break-word}
  .full,.wide{grid-column:1/-1}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div class="title">
      <h1><?= $v ? 'Ficha de vehiculo' : 'Buscar vehiculo' ?></h1>
      <?php if($v): ?><span class="badge">ID #<?= (int)$v['id'] ?></span><?php endif; ?>
    </div>
    <div class="actions">
      <a class="btn" href="<?= h($returnTo) ?>">Volver</a>
      <?php if($v): ?>
        <a class="btn primary" href="vehiculo_editar.php?id=<?= (int)$v['id'] ?>&return_to=<?= urlencode($selfUrl) ?>">Editar</a>
        <a class="btn danger" href="vehiculo_eliminar.php?id=<?= (int)$v['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if(!$v): ?>
    <div class="card">
      <form method="get" style="display:grid;gap:8px;grid-template-columns:1fr auto;">
        <input type="text" name="placa" placeholder="Buscar por placa (ABC123)" style="padding:8px 10px;border-radius:10px;border:1px solid rgba(127,127,127,.25);">
        <button class="btn primary" type="submit">Buscar</button>
      </form>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="header">
        <div class="placa">Placa: <?= h($placaTxt ?: '-') ?></div>
        <div class="sub"><?= h($marcaModelo ?: '-') ?></div>
      </div>

      <div class="grid">
        <div class="field full">
          <div class="label">Clasificacion</div>
          <div class="val"><?= h($clasificacion) ?></div>
        </div>

        <div class="field full">
          <div class="label">Dimensiones</div>
          <div class="val"><?= h($dimensiones) ?></div>
        </div>

        <?php foreach($v as $col => $val): ?>
          <?php
          if(in_array($col, $excluir, true)) continue;
          if(in_array($col, ['cat_codigo','cat_desc','tipo_codigo','tipo_nombre','carroceria_nombre','marca_nombre','modelo_nombre'], true)) continue;
          $display = in_array($col, ['creado_en','actualizado_en'], true) ? fmtDate($val) : $val;
          $isWide = in_array($col, ['notas'], true);
          ?>
          <div class="field <?= $isWide ? 'wide' : '' ?>">
            <div class="label"><?= h(labelize($col)) ?></div>
            <div class="val"><?= ($display !== '' && $display !== null) ? nl2br(h((string)$display)) : '-' ?></div>
          </div>
        <?php endforeach; ?>

        <?php if($cntInv !== null): ?>
          <div class="field">
            <div class="label">Accidentes vinculados</div>
            <div class="val"><?= (int)$cntInv ?></div>
          </div>
          <div class="field">
            <div class="label">Acciones</div>
            <div class="val">
              <a class="btn" href="involucrados_vehiculos_listar.php?q=<?= urlencode($v['placa'] ?? '') ?>">Ver en listados</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
