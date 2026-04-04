<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\CatalogoOficioRepository;
use App\Services\CatalogoOficioService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new CatalogoOficioService(new CatalogoOficioRepository($pdo));
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'categoria' => trim((string) ($_GET['categoria'] ?? '')),
    'solo_activos' => (string) ($_GET['solo_activos'] ?? '1'),
];
$rows = $service->listadoEnlacesInteres($filters);
$categorias = $service->enlaceInteresCategorias();

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Enlaces de interes</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--accent:#0f766e;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--accent:#2dd4bf;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:linear-gradient(180deg,var(--page),#eef3fb);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1360px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.small{font-size:12px;color:var(--muted)}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.filters{display:grid;grid-template-columns:2fr 1fr 1fr auto auto;gap:10px;align-items:end}.filters input,.filters select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px}.link-card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:16px;display:flex;flex-direction:column;gap:12px;box-shadow:0 18px 36px rgba(15,23,42,.06)}.link-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}.pill{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800;background:rgba(15,118,110,.12);color:var(--accent);border:1px solid rgba(15,118,110,.18)}.status{font-size:11px;font-weight:800}.status.off{color:var(--danger)}.status.on{color:var(--ok)}.link-title{font-size:18px;font-weight:900;line-height:1.2}.link-url{font-size:12px;color:var(--primary);word-break:break-all}.link-desc{color:var(--muted);min-height:42px}.link-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:auto}.empty{padding:28px;text-align:center;color:var(--muted);background:var(--card);border:1px dashed var(--border);border-radius:16px}@media(max-width:980px){.filters{grid-template-columns:1fr}.filters .btn{width:100%}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Enlaces de interes <span class="badge">Consulta rapida</span></h1>
      <div class="small">Guarda accesos utiles como licencia de conducir, record de conductor, consulta vehicular, SOAT, AFOCAT, SIDPOL, ESINPOL, correo PNP y otros sistemas.</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="index.php">Panel</a>
      <a class="btn primary" href="enlace_interes_nuevo.php">Nuevo enlace</a>
    </div>
  </div>

  <div class="card" style="margin-bottom:14px;">
    <form class="filters" method="get">
      <div>
        <label class="small" for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= h($filters['q']) ?>" placeholder="Nombre, categoria, descripcion o URL">
      </div>
      <div>
        <label class="small" for="categoria">Categoria</label>
        <select id="categoria" name="categoria">
          <option value="">Todas</option>
          <?php foreach ($categorias as $item): ?>
            <option value="<?= h($item) ?>" <?= $filters['categoria'] === $item ? 'selected' : '' ?>><?= h($item) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="small" for="solo_activos">Estado</label>
        <select id="solo_activos" name="solo_activos">
          <option value="1" <?= $filters['solo_activos'] === '1' ? 'selected' : '' ?>>Solo activos</option>
          <option value="0" <?= $filters['solo_activos'] === '0' ? 'selected' : '' ?>>Todos</option>
        </select>
      </div>
      <button class="btn" type="submit">Buscar</button>
      <a class="btn" href="enlaces_interes_listar.php">Limpiar</a>
    </form>
  </div>

  <?php if ($rows === []): ?>
    <div class="empty">Todavia no hay enlaces registrados. Puedes crear el primero desde el boton <strong>Nuevo enlace</strong>.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($rows as $row): ?>
        <article class="link-card">
          <div class="link-top">
            <span class="pill"><?= h((string) ($row['categoria'] ?? 'OTROS')) ?></span>
            <span class="status <?= ((int) ($row['activo'] ?? 0)) === 1 ? 'on' : 'off' ?>"><?= ((int) ($row['activo'] ?? 0)) === 1 ? 'Activo' : 'Inactivo' ?></span>
          </div>
          <div class="link-title"><?= h((string) ($row['nombre'] ?? '')) ?></div>
          <div class="link-url"><?= h((string) ($row['url'] ?? '')) ?></div>
          <div class="link-desc"><?= h((string) (($row['descripcion'] ?? '') !== '' ? $row['descripcion'] : 'Sin descripcion adicional.')) ?></div>
          <div class="small">Orden: <?= (int) ($row['orden'] ?? 0) ?></div>
          <div class="link-actions">
            <a class="btn primary" href="<?= h((string) ($row['url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer">Abrir</a>
            <a class="btn" href="enlace_interes_editar.php?id=<?= (int) ($row['id'] ?? 0) ?>">Editar</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
