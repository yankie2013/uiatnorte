<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\ComisariaRepository;
use App\Services\ComisariaService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new ComisariaService(new ComisariaRepository($pdo));
$q = trim((string) ($_GET['q'] ?? ''));
$rows = $service->listado($q);
$ok = trim((string) ($_GET['ok'] ?? ''));
$err = trim((string) ($_GET['err'] ?? ''));
$returnTo = $_SERVER['REQUEST_URI'] ?? 'comisarias_listar.php';

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Listado de comisarias</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1240px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.small{color:var(--muted);font-size:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:960px}th,td{padding:12px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;background:rgba(148,163,184,.08)}tbody tr:hover{background:rgba(37,99,235,.05)}.stack-actions{display:flex;gap:8px;flex-wrap:wrap}.search{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.search input{min-width:320px;max-width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}.pill{display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700}.pill.on{background:rgba(22,163,74,.12);color:var(--ok)}.pill.off{background:rgba(148,163,184,.18);color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1 style="margin:0 0 6px">Comisarias <span class="badge">Listado</span></h1><div class="small">Total: <?= count($rows) ?> registro(s)</div></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="index.php">Panel</a><a class="btn primary" href="comisarias_nuevo.php">Nueva comisaria</a></div>
  </div>

  <?php if ($ok !== ''): ?><div class="ok"><?php $messages=['created'=>'Comisaria registrada correctamente.','updated'=>'Comisaria actualizada correctamente.','deleted'=>'Comisaria eliminada correctamente.']; echo h($messages[$ok] ?? 'Operacion realizada correctamente.'); ?></div><?php endif; ?>
  <?php if ($err !== ''): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:14px;">
    <form class="search" method="get">
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="Buscar por nombre, tipo, direccion, telefono o correo">
      <button class="btn" type="submit">Buscar</button>
      <?php if ($q !== ''): ?><a class="btn" href="comisarias_listar.php">Limpiar</a><?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Contacto</th><th>Ubicacion</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="7" style="text-align:center;padding:24px;" class="small">No se encontraron comisarias.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td>#<?= (int) $row['id'] ?></td>
            <td><strong><?= h((string) $row['nombre']) ?></strong><br><span class="small"><?= h((string) (($row['notas'] ?? '') !== '' ? $row['notas'] : 'Sin notas')) ?></span></td>
            <td><?= h((string) (($row['tipo'] ?? '') !== '' ? $row['tipo'] : '-')) ?></td>
            <td><?= h((string) (($row['telefono'] ?? '') !== '' ? $row['telefono'] : 'Sin telefono')) ?><br><span class="small"><?= h((string) (($row['correo'] ?? '') !== '' ? $row['correo'] : 'Sin correo')) ?></span></td>
            <td><?= h((string) (($row['direccion'] ?? '') !== '' ? $row['direccion'] : 'Sin direccion')) ?><br><span class="small"><?php if (($row['lat'] ?? '') !== '' || ($row['lon'] ?? '') !== ''): ?><?= h(trim((string) (($row['lat'] ?? '-') . ', ' . ($row['lon'] ?? '-')))) ?><?php else: ?>Sin coordenadas<?php endif; ?></span></td>
            <td><span class="pill <?= !empty($row['activo']) ? 'on' : 'off' ?>"><?= !empty($row['activo']) ? 'Activo' : 'Inactivo' ?></span></td>
            <td>
              <div class="stack-actions">
                <a class="btn" href="comisarias_editar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a>
                <a class="btn danger" href="comisarias_eliminar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
