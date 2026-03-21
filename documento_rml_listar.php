<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoRmlRepository;
use App\Services\DocumentoRmlService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

$service = new DocumentoRmlService(new DocumentoRmlRepository($pdo));
$persona_id = (int) g('persona_id', 0);
$ok = (int) g('ok', 0);
$msg = g('msg', '');
$per = $service->persona($persona_id);
$rows = $service->listado($persona_id);
$returnTo = $_SERVER['REQUEST_URI'] ?? ('documento_rml_listar.php' . ($persona_id ? '?persona_id=' . $persona_id : ''));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>RML - Listado</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{color-scheme: light dark;--bg:#0b1020;--panel:#0f1628;--line:#22304a;--ink:#e8eefc;--muted:#9aa4b2;--chip:#121a30;--r:14px}
@media (prefers-color-scheme: light){:root{--bg:#f6f7fb;--panel:#ffffff;--line:#e5e7eb;--ink:#0f172a;--muted:#64748b;--chip:#f3f4f9}}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(900px 500px at 8% -10%, rgba(136,170,255,.18), transparent 60%),radial-gradient(900px 500px at 92% -10%, rgba(136,170,255,.14), transparent 50%),var(--bg);color:var(--ink);font:13px/1.45 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto}.wrap{max-width:980px;margin:20px auto;padding:0 14px}h1{margin:0 0 10px;font-size:22px;font-weight:900;letter-spacing:.2px;display:flex;align-items:center;gap:8px}.badge{font-size:11px;border:1px solid var(--line);background:var(--chip);border-radius:999px;padding:2px 8px;color:var(--muted);font-weight:800}.note{color:var(--muted);margin-bottom:10px;font-size:12px}.card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.01));border:1px solid var(--line);border-radius:var(--r);padding:12px;box-shadow:0 10px 26px rgba(0,0,0,.22)}.btn{padding:8px 12px;border-radius:10px;border:1px solid var(--line);background:var(--chip);color:inherit;text-decoration:none;font-weight:800;cursor:pointer;height:34px}.btn.small{padding:6px 10px;height:32px;font-size:12.5px}.btn.micro{padding:3px 8px;height:auto;font-size:11.5px;border-radius:999px}.bar{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px}.ok{background:#0c3f2d;color:#c6ffe3;border:1px solid #167a59;padding:8px 10px;border-radius:10px;margin:10px 0;font-size:13px}.empty{padding:14px 8px;color:var(--muted)}table{width:100%;border-collapse:collapse;font-size:13px}th, td{border-bottom:1px solid var(--line);padding:8px;background:var(--panel)}th{color:var(--muted);text-align:left}tr:nth-child(even) td{background:rgba(255,255,255,.02)}.table-wrap{overflow:auto}
</style>
</head>
<body><div class="wrap">
  <div class="bar">
    <h1>RML - Listado <span class="badge">documento_rml</span></h1>
    <div><a class="btn small" href="documento_rml_nuevo.php<?= $persona_id ? '?persona_id=' . $persona_id : '' ?>">Nuevo</a></div>
  </div>
  <div class="card">
    <?php if($ok || $msg === 'eliminado'): ?><div class="ok"><?= $msg === 'eliminado' ? 'Documento eliminado correctamente.' : 'Operacion realizada correctamente.' ?></div><?php endif; ?>
    <?php if($per): ?><div class="note">Persona: <?= h($per['nom']) ?> - DNI: <?= h($per['num_doc']) ?></div><?php endif; ?>
    <?php if(!$rows): ?>
      <div class="empty">No hay registros.</div>
    <?php else: ?>
      <div class="table-wrap"><table><thead><tr><th>ID</th><th>Persona</th><th>Numero</th><th>Fecha</th><th>Incap. medico</th><th>Atenc. facultativo</th><th>Acciones</th></tr></thead><tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h(($r['per_nom'] ?: ('ID ' . $r['persona_id'])) . (!empty($r['num_doc']) ? ' - DNI ' . $r['num_doc'] : '')) ?></td>
          <td><?= h($r['numero']) ?></td>
          <td><?= h($r['fecha']) ?></td>
          <td><?= h($r['incapacidad_medico'] ?: 'No requiere') ?></td>
          <td><?= h($r['atencion_facultativo'] ?: 'No requiere') ?></td>
          <td>
            <a class="btn micro" href="documento_rml_leer.php?id=<?= (int)$r['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Ver</a>
            <a class="btn micro" href="documento_rml_editar.php?id=<?= (int)$r['id'] ?>">Editar</a>
            <a class="btn micro" href="documento_rml_eliminar.php?id=<?= (int)$r['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
</div></body></html>
