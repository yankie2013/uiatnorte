<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\VehiculoRepository;
use App\Services\VehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

$vehiculoService = new VehiculoService(new VehiculoRepository($pdo));
$listado = $vehiculoService->listado((string) ($_GET['q'] ?? ''), (int) ($_GET['page'] ?? 1), 12);

$q = $listado['q'];
$page = $listado['page'];
$total = $listado['total'];
$pages = $listado['pages'];
$rows = $listado['rows'];
$qs = $listado['qs'];
$base = 'vehiculo_listar.php';
$returnTo = $_SERVER['REQUEST_URI'] ?? 'vehiculo_listar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UIAT Norte - Vehiculos</title>
<link rel="stylesheet" href="style_gian.css">
<style>
  .toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap}
  .search{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .search input{width:280px;max-width:60vw}
  table{width:100%;border-collapse:collapse;border-radius:14px;overflow:hidden}
  thead th{text-align:left}
  tbody td{vertical-align:top}
  .actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
  .pager{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}
  .pager .page{padding:6px 10px;border-radius:10px;border:1px solid rgba(0,0,0,.08);text-decoration:none;color:inherit;background:rgba(255,255,255,.75)}
  .pager .page.active{font-weight:800;box-shadow:0 0 0 3px rgba(var(--g-brand1),.20) inset;border-color:rgba(var(--g-brand1),.50)}
  @media (prefers-color-scheme: dark){
    .pager .page{background:rgba(17,24,39,.65);border-color:rgba(255,255,255,.12)}
  }
  .muted{color:rgba(var(--g-muted),1);font-size:12px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card" style="max-width:1250px;">
    <div class="hdr">
      <div class="ttl">Vehiculos - Listado</div>
      <span class="pill">Estilo GIAN</span>
    </div>

    <div class="body">
      <?php if(($_GET['msg'] ?? '') === 'creado'): ?><div class="msg ok">Vehiculo registrado correctamente.</div><?php endif; ?>
      <?php if(($_GET['msg'] ?? '') === 'eliminado'): ?><div class="msg ok">Vehiculo eliminado correctamente.</div><?php endif; ?>
      <?php if(($_GET['msg'] ?? '') === 'editado'): ?><div class="msg ok">Vehiculo actualizado correctamente.</div><?php endif; ?>

      <div class="toolbar">
        <form class="search" method="get">
          <input type="search" name="q" placeholder="Buscar por placa, marca, modelo, tipo, categoria o color" value="<?= h($q) ?>">
          <button class="btn small" type="submit">Buscar</button>
          <?php if($q !== ''): ?><a class="btn small sec" href="vehiculo_listar.php">Limpiar</a><?php endif; ?>
        </form>

        <div class="row">
          <a class="btn small" href="vehiculo_nuevo.php">Nuevo vehiculo</a>
          <a class="btn small sec" href="index.php">Volver al panel</a>
        </div>
      </div>

      <?php if(!$rows): ?>
        <div class="msg err">No se encontraron registros.</div>
      <?php else: ?>
        <div style="overflow:auto">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Placa</th>
                <th>Marca / Modelo</th>
                <th>Categoria</th>
                <th>Tipo</th>
                <th>Carroceria</th>
                <th>Anio</th>
                <th>Color</th>
                <th class="text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td>
                    <strong><?= h($r['placa']) ?></strong>
                    <?php if(!empty($r['serie_vin']) || !empty($r['nro_motor'])): ?>
                      <div class="muted">
                        <?= !empty($r['serie_vin']) ? 'VIN: ' . h($r['serie_vin']) : '' ?>
                        <?= !empty($r['serie_vin']) && !empty($r['nro_motor']) ? ' - ' : '' ?>
                        <?= !empty($r['nro_motor']) ? 'Motor: ' . h($r['nro_motor']) : '' ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= h($r['marca']) ?><br>
                    <span class="muted"><?= h($r['modelo']) ?></span>
                  </td>
                  <td><?= h($r['categoria'] ?: '-') ?></td>
                  <td><?= h($r['tipo'] ?: '-') ?></td>
                  <td><?= h($r['carroceria'] ?: '-') ?></td>
                  <td><?= h($r['anio'] ?: '-') ?></td>
                  <td><?= h($r['color'] ?: '-') ?></td>
                  <td class="text-right">
                    <div class="actions">
                      <a class="btn small sec" href="vehiculo_leer.php?id=<?= (int)$r['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Ver</a>
                      <a class="btn small" href="vehiculo_editar.php?id=<?= (int)$r['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a>
                      <a class="btn small sec" href="vehiculo_eliminar.php?id=<?= (int)$r['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="pager">
          <?php if($page > 1): ?>
            <a class="page" href="<?= $base . '?page=1' . $qs ?>">Primero</a>
            <a class="page" href="<?= $base . '?page=' . ($page - 1) . $qs ?>">Anterior</a>
          <?php endif; ?>
          <?php $win = 2; $pmin = max(1, $page - $win); $pmax = min($pages, $page + $win); ?>
          <?php for($p = $pmin; $p <= $pmax; $p++): ?>
            <a class="page <?= $p === $page ? 'active' : '' ?>" href="<?= $base . '?page=' . $p . $qs ?>"><?= $p ?></a>
          <?php endfor; ?>
          <?php if($page < $pages): ?>
            <a class="page" href="<?= $base . '?page=' . ($page + 1) . $qs ?>">Siguiente</a>
            <a class="page" href="<?= $base . '?page=' . $pages . $qs ?>">Ultimo</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="foot">
      <span class="hint">UIAT Norte - Vehiculos</span>
      <span class="hint">Total: <?= (int)$total ?> - Pagina <?= (int)$page ?>/<?= (int)$pages ?></span>
    </div>
  </div>
</div>
</body>
</html>
