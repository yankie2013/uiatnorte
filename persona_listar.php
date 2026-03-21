<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\PersonaRepository;
use App\Services\PersonaService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new PersonaService(new PersonaRepository($pdo));
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$data = $service->listado($q, $page, 12);
$rows = $data['rows'];
$ok = trim((string) ($_GET['ok'] ?? ''));
$err = trim((string) ($_GET['err'] ?? ''));
$returnTo = $_SERVER['REQUEST_URI'] ?? 'persona_listar.php';

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Listado de personas</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1240px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.small{color:var(--muted);font-size:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:980px}th,td{padding:12px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;background:rgba(148,163,184,.08)}tbody tr:hover{background:rgba(37,99,235,.05)}.stack-actions{display:flex;gap:8px;flex-wrap:wrap}.search{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.search input{min-width:280px;max-width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}.pager{display:flex;gap:8px;justify-content:flex-end;align-items:center;margin-top:14px;flex-wrap:wrap}.page-link{padding:8px 12px;border-radius:10px;border:1px solid var(--border);text-decoration:none;color:var(--text);background:var(--card)}.page-link.active{border-color:var(--primary);font-weight:700}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1 style="margin:0 0 6px">Personas <span class="badge">Listado</span></h1><div class="small">Total: <?= (int) $data['total'] ?> registro(s)</div></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="index.php">Panel</a><a class="btn primary" href="persona_nuevo.php">Nueva persona</a></div>
  </div>

  <?php if ($ok !== ''): ?><div class="ok"><?php $messages=['created'=>'Persona registrada correctamente.','updated'=>'Persona actualizada correctamente.','deleted'=>'Persona eliminada correctamente.']; echo h($messages[$ok] ?? 'Operacion realizada correctamente.'); ?></div><?php endif; ?>
  <?php if ($err !== ''): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:14px;">
    <form class="search" method="get">
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="Buscar por documento o nombres">
      <button class="btn" type="submit">Buscar</button>
      <?php if ($q !== ''): ?><a class="btn" href="persona_listar.php">Limpiar</a><?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Documento</th><th>Apellidos y nombres</th><th>Sexo</th><th>Fecha nac.</th><th>Edad</th><th>Contacto</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="8" style="text-align:center;padding:24px;" class="small">No se encontraron personas.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td>#<?= (int) $row['id'] ?></td>
            <td><?= h(trim((string) (($row['tipo_doc'] ?? '') . ' ' . ($row['num_doc'] ?? '')))) ?></td>
            <td><?= h(trim((string) (($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ', ' . ($row['nombres'] ?? '')))) ?></td>
            <td><?= h((string) (($row['sexo'] ?? '') !== '' ? $row['sexo'] : '-')) ?></td>
            <td><?= h((string) (($row['fecha_nacimiento'] ?? '') !== '' ? $row['fecha_nacimiento'] : '-')) ?></td>
            <td><?= h((string) (($row['edad'] ?? '') !== '' ? $row['edad'] : '-')) ?></td>
            <td><?= h((string) (($row['celular'] ?? '') !== '' ? $row['celular'] : 'Sin celular')) ?><br><span class="small"><?= h((string) (($row['email'] ?? '') !== '' ? $row['email'] : 'Sin email')) ?></span></td>
            <td><div class="stack-actions"><a class="btn" href="persona_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Ver</a><a class="btn" href="persona_editar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a><a class="btn danger" href="persona_eliminar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a></div></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pager">
    <?php $base='persona_listar.php'; $qs=$q!=='' ? '&q='.rawurlencode($q) : ''; if ((int) $data['page'] > 1): ?>
      <a class="page-link" href="<?= $base . '?page=1' . $qs ?>">Primero</a>
      <a class="page-link" href="<?= $base . '?page=' . ((int) $data['page'] - 1) . $qs ?>">Anterior</a>
    <?php endif; ?>
    <?php for ($p=max(1, (int) $data['page'] - 2); $p<=min((int) $data['pages'], (int) $data['page'] + 2); $p++): ?>
      <a class="page-link <?= $p === (int) $data['page'] ? 'active' : '' ?>" href="<?= $base . '?page=' . $p . $qs ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ((int) $data['page'] < (int) $data['pages']): ?>
      <a class="page-link" href="<?= $base . '?page=' . ((int) $data['page'] + 1) . $qs ?>">Siguiente</a>
      <a class="page-link" href="<?= $base . '?page=' . (int) $data['pages'] . $qs ?>">Ultimo</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
