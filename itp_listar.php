<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\ItpRepository;
use App\Services\ItpService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new ItpService(new ItpRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$rows = $service->listado(['accidente_id' => $accidenteId, 'q' => $q]);
$ok = trim((string) ($_GET['ok'] ?? ''));
$err = trim((string) ($_GET['err'] ?? ''));
$returnTo = $_SERVER['REQUEST_URI'] ?? ('itp_listar.php' . ($accidenteId > 0 ? '?accidente_id=' . $accidenteId : ''));

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Listado ITP</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c;--ok:#166534;--gold:#b68b1f}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca;--ok:#bbf7d0;--gold:#e6c97d}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1220px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}h1{color:var(--gold);margin:0}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px;margin-bottom:14px}.small{color:var(--muted);font-size:12px}.ok{background:rgba(22,163,74,.12);padding:10px;border-radius:10px;margin-bottom:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.search{display:flex;gap:8px;align-items:center;flex-wrap:wrap}input{padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:980px}th,td{padding:12px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}th{font-size:12px;color:var(--gold);text-transform:uppercase;letter-spacing:.03em;background:rgba(148,163,184,.08)}tbody tr:hover{background:rgba(37,99,235,.05)}.stack-actions{display:flex;gap:8px;flex-wrap:wrap}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1>ITP <span class="badge">Listado</span></h1>
      <div class="small"><?= count($rows) ?> registro(s)</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php if ($accidenteId > 0): ?>
        <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= urlencode((string) $accidenteId) ?>">Datos generales SIDPOL</a>
      <?php endif; ?>
      <a class="btn primary" href="itp_nuevo.php<?= $accidenteId > 0 ? '?accidente_id=' . $accidenteId : '' ?>">Nuevo ITP</a>
    </div>
  </div>

  <?php if ($ok !== ''): ?><div class="ok"><?= h($ok) ?></div><?php endif; ?>
  <?php if ($err !== ''): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <form method="get" class="search">
      <input type="number" name="accidente_id" min="1" placeholder="ID accidente" value="<?= $accidenteId > 0 ? (int) $accidenteId : '' ?>">
      <input type="text" name="q" placeholder="SIDPOL, lugar, forma de via o referencia" value="<?= h($q) ?>">
      <button class="btn" type="submit">Filtrar</button>
      <a class="btn" href="itp_listar.php">Limpiar</a>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Accidente</th>
          <th>Fecha ITP</th>
          <th>Forma via</th>
          <th>Punto referencia</th>
          <th>GPS</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="7" style="text-align:center;padding:24px;" class="small">No se encontraron registros.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td>#<?= (int) $row['id'] ?></td>
            <td>
              <strong>Accidente #<?= (int) $row['accidente_id'] ?></strong>
              <div class="small" style="margin-top:4px;">
                SIDPOL: <?= h((string) (($row['registro_sidpol'] ?? '') !== '' ? $row['registro_sidpol'] : '-')) ?>
                - Fecha: <?= h((string) (($row['fecha_accidente'] ?? '') !== '' ? $row['fecha_accidente'] : '-')) ?>
                - Lugar: <?= h((string) (($row['lugar'] ?? '') !== '' ? $row['lugar'] : '-')) ?>
              </div>
            </td>
            <td>
              <?= h((string) (($row['fecha_itp'] ?? '') !== '' ? $row['fecha_itp'] : '-')) ?><br>
              <span class="small"><?= h((string) (($row['hora_itp'] ?? '') !== '' ? $row['hora_itp'] : '-')) ?></span>
            </td>
            <td><?= h((string) (($row['forma_via'] ?? '') !== '' ? $row['forma_via'] : '-')) ?></td>
            <td><?= h((string) (($row['punto_referencia'] ?? '') !== '' ? $row['punto_referencia'] : '-')) ?></td>
            <td>
              <?php if (!empty($row['ubicacion_gps'])): ?>
                <a href="https://www.google.com/maps?q=<?= urlencode((string) $row['ubicacion_gps']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string) $row['ubicacion_gps']) ?></a>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td>
              <div class="stack-actions">
                <a class="btn" href="itp_ver.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Ver</a>
                <a class="btn" href="itp_editar.php?id=<?= (int) $row['id'] ?>">Editar</a>
                <form method="post" action="itp_eliminar.php" style="display:inline;" onsubmit="return confirm('Eliminar este ITP?');">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                  <button class="btn danger" type="submit">Eliminar</button>
                </form>
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
