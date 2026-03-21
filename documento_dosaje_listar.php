<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
include __DIR__ . '/_boton_volver.php';

use App\Repositories\DocumentoDosajeRepository;
use App\Services\DocumentoDosajeService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

$service = new DocumentoDosajeService(new DocumentoDosajeRepository($pdo));
$q = trim((string)($_GET['q'] ?? ''));
$persona_id = (int)($_GET['persona_id'] ?? 0);
$rows = $service->listado($q, $persona_id);
$returnTo = 'documento_dosaje_listar.php' . ($persona_id ? '?persona_id=' . $persona_id : '');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Dosajes - Listado</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
.wrap{max-width:1100px;margin:20px auto;padding:0 14px}
.bar{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap}
.note{color:var(--muted);margin-bottom:10px}
.card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.01));border:1px solid var(--line);border-radius:var(--r);padding:12px;box-shadow:0 10px 26px rgba(0,0,0,.22)}
.filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
.filters input{min-width:240px}
.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse}
th,td{border-bottom:1px solid var(--line);padding:10px 8px;text-align:left;vertical-align:top}
th{color:var(--muted);font-size:12px}
.actions{display:flex;gap:6px;flex-wrap:wrap}
.empty{padding:16px 8px;color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <h1>Dosajes</h1>
    <a class="btn small" href="documento_dosaje_nuevo.php<?= $persona_id ? '?persona_id=' . $persona_id : '' ?>">Nuevo</a>
  </div>

  <div class="card">
    <form class="filters" method="get">
      <input name="q" value="<?= h($q) ?>" placeholder="Buscar por numero o persona">
      <?php if($persona_id): ?><input type="hidden" name="persona_id" value="<?= (int)$persona_id ?>"><?php endif; ?>
      <button class="btn" type="submit">Buscar</button>
    </form>

    <?php if($persona_id): ?>
      <div class="note">Filtrando por persona #<?= (int)$persona_id ?>.</div>
    <?php endif; ?>

    <?php if(!$rows): ?>
      <div class="empty">No hay dosajes registrados con los filtros actuales.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Numero</th>
              <th>Fecha extraccion</th>
              <th>Persona</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <?php $persona = trim(($r['apellido_paterno'] ?? '') . ' ' . ($r['apellido_materno'] ?? '') . ', ' . ($r['nombres'] ?? '')); ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= h($r['numero']) ?></td>
              <td><?= h($r['fecha_extraccion']) ?></td>
              <td><?= h($persona ?: '-') ?></td>
              <td class="actions">
                <a class="btn micro" href="documento_dosaje_ver.php?id=<?= (int)$r['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Ver</a>
                <a class="btn micro" href="documento_dosaje_editar.php?id=<?= (int)$r['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a>
                <a class="btn micro danger" href="documento_dosaje_eliminar.php?id=<?= (int)$r['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
