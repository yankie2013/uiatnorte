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
    'tipo' => trim((string) ($_GET['tipo'] ?? '')),
    'categoria' => trim((string) ($_GET['categoria'] ?? '')),
];
$rows = $service->listadoEntidades($filters);
$tiposEntidad = $service->tiposEntidad();
$categoriasEntidad = $service->categoriasEntidad();

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Prontuario de entidades</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1320px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.small{color:var(--muted);font-size:12px}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.filters{display:grid;grid-template-columns:2fr 1fr 1fr auto auto;gap:10px;align-items:end}.filters input,.filters select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:1180px}th,td{padding:12px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;background:rgba(148,163,184,.08)}tbody tr:hover{background:rgba(37,99,235,.05)}.stack-actions{display:flex;gap:8px;flex-wrap:wrap}.pill{display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18)}.contact-line{display:block;margin-bottom:4px}.empty{text-align:center;padding:24px;color:var(--muted)}@media(max-width:980px){.filters{grid-template-columns:1fr}.filters .btn{width:100%}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Prontuario de entidades <span class="badge">Directorio</span></h1>
      <div class="small">Consulta y administracion de comisarias, fiscalias, municipalidades, empresas y otras entidades.</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="index.php">Panel</a>
      <a class="btn primary" href="oficio_entidad_nuevo.php">Nueva entidad</a>
    </div>
  </div>

  <div class="card" style="margin-bottom:14px;">
    <form class="filters" method="get">
      <div>
        <label class="small" for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= h($filters['q']) ?>" placeholder="Nombre, siglas, direccion, correo o telefono">
      </div>
      <div>
        <label class="small" for="tipo">Naturaleza</label>
        <select id="tipo" name="tipo">
          <option value="">Todas</option>
          <?php foreach ($tiposEntidad as $item): ?>
            <option value="<?= h($item) ?>" <?= $filters['tipo'] === $item ? 'selected' : '' ?>><?= h($item) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="small" for="categoria">Categoria</label>
        <select id="categoria" name="categoria">
          <option value="">Todas</option>
          <?php foreach ($categoriasEntidad as $item): ?>
            <option value="<?= h($item) ?>" <?= $filters['categoria'] === $item ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $item)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit">Buscar</button>
      <a class="btn" href="oficio_entidades_listar.php">Limpiar</a>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Entidad</th>
          <th>Clasificacion</th>
          <th>Contacto</th>
          <th>Direccion</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="6" class="empty">No se encontraron entidades.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
          $categoria = trim((string) ($row['categoria'] ?? ''));
          $siglas = trim((string) ($row['siglas'] ?? ''));
          $telefonoFijo = trim((string) ($row['telefono_fijo'] ?? ''));
          $telefonoMovil = trim((string) ($row['telefono_movil'] ?? ''));
          $telefono = trim((string) ($row['telefono'] ?? ''));
          ?>
          <tr>
            <td>#<?= (int) ($row['id'] ?? 0) ?></td>
            <td>
              <strong><?= h((string) ($row['nombre'] ?? '')) ?></strong>
              <?php if ($siglas !== ''): ?><br><span class="small"><?= h($siglas) ?></span><?php endif; ?>
            </td>
            <td>
              <span class="pill"><?= h((string) ($row['tipo'] ?? '')) ?></span>
              <?php if ($categoria !== ''): ?><br><span class="small"><?= h(str_replace('_', ' ', $categoria)) ?></span><?php endif; ?>
            </td>
            <td>
              <?php if ($telefonoFijo !== ''): ?><span class="contact-line"><strong>Fijo:</strong> <?= h($telefonoFijo) ?></span><?php endif; ?>
              <?php if ($telefonoMovil !== ''): ?><span class="contact-line"><strong>Movil:</strong> <?= h($telefonoMovil) ?></span><?php endif; ?>
              <?php if ($telefono !== '' && $telefono !== $telefonoFijo && $telefono !== $telefonoMovil): ?><span class="contact-line"><strong>Tel.:</strong> <?= h($telefono) ?></span><?php endif; ?>
              <?php if (trim((string) ($row['correo'] ?? '')) !== ''): ?><span class="contact-line"><strong>Correo:</strong> <?= h((string) $row['correo']) ?></span><?php endif; ?>
              <?php if (trim((string) ($row['pagina_web'] ?? '')) !== ''): ?><span class="contact-line"><strong>Web:</strong> <?= h((string) $row['pagina_web']) ?></span><?php endif; ?>
            </td>
            <td><?= h((string) (($row['direccion'] ?? '') !== '' ? $row['direccion'] : 'Sin direccion registrada')) ?></td>
            <td>
              <div class="stack-actions">
                <a class="btn" href="oficio_entidad_editar.php?id=<?= (int) ($row['id'] ?? 0) ?>">Editar</a>
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
