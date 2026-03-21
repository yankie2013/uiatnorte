<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\PolicialIntervinienteRepository;
use App\Services\PolicialIntervinienteService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new PolicialIntervinienteService(new PolicialIntervinienteRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
$ok = trim((string) ($_GET['ok'] ?? ''));
$err = trim((string) ($_GET['err'] ?? ''));
$filtered = $accidenteId > 0;
$rows = $service->listado($filtered ? $accidenteId : null);
$ctx = $filtered ? $service->formContext($accidenteId) : ['accidente' => null];
$returnTo = $_SERVER['REQUEST_URI'] ?? ($filtered ? ('policial_interviniente_listar.php?accidente_id=' . $accidenteId) : 'policial_interviniente_listar.php');

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Intervinientes policiales</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#0284c7;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#7dd3fc;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1280px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(2,132,199,.12);color:var(--primary);border:1px solid rgba(2,132,199,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#082f49;border-color:transparent}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.small{color:var(--muted);font-size:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:1120px}th,td{padding:12px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;background:rgba(148,163,184,.08)}tbody tr:hover{background:rgba(2,132,199,.05)}.stack-actions{display:flex;gap:8px;flex-wrap:wrap}.pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:11px}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Intervinientes policiales <span class="badge"><?= $filtered ? 'Accidente' : 'Global' ?></span></h1>
      <div class="small"><?= $filtered ? 'Accidente ID: ' . (int) $accidenteId : 'Vista general de todos los registros' ?> - <?= count($rows) ?> registro(s)</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php if ($filtered): ?>
        <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= (int) $accidenteId ?>">Volver al accidente</a>
        <a class="btn" href="policial_interviniente_listar.php">Ver todos</a>
        <a class="btn primary" href="policial_interviniente_nuevo.php?accidente_id=<?= (int) $accidenteId ?>">Nuevo interviniente</a>
      <?php else: ?>
        <a class="btn" href="accidente_listar.php">Volver a accidentes</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($ok !== ''): ?><div class="ok"><?php $messages=['created'=>'Registro creado correctamente.','updated'=>'Registro actualizado correctamente.','deleted'=>'Registro eliminado correctamente.']; echo h($messages[$ok] ?? 'Operacion realizada correctamente.'); ?></div><?php endif; ?>
  <?php if ($err !== ''): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <?php if ($ctx['accidente']): ?>
    <div class="card" style="margin-bottom:14px;"><strong>Accidente #<?= (int) $ctx['accidente']['id'] ?></strong><div class="small" style="margin-top:4px;">SIDPOL: <?= h((string) ($ctx['accidente']['sidpol'] ?? 'Sin SIDPOL')) ?> - Registro: <?= h((string) ($ctx['accidente']['registro_sidpol'] ?? 'Sin registro')) ?> - Fecha: <?= h((string) ($ctx['accidente']['fecha_accidente'] ?? 'Sin fecha')) ?> - Lugar: <?= h((string) ($ctx['accidente']['lugar'] ?? 'Sin lugar')) ?></div></div>
  <?php endif; ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <?php if (!$filtered): ?><th>Accidente</th><?php endif; ?>
          <th>Documento</th>
          <th>Interviniente</th>
          <th>Grado</th>
          <th>CIP</th>
          <th>Dependencia</th>
          <th>Rol / funcion</th>
          <th>Contacto</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="<?= $filtered ? 9 : 10 ?>" style="text-align:center;padding:24px;" class="small">No hay intervinientes policiales registrados.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php $nombre = trim((string) (($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ' ' . ($row['nombres'] ?? ''))); ?>
          <tr>
            <td>#<?= (int) $row['id'] ?></td>
            <?php if (!$filtered): ?>
              <td><a class="btn" href="policial_interviniente_listar.php?accidente_id=<?= (int) $row['accidente_id'] ?>">#<?= (int) $row['accidente_id'] ?></a><div class="small" style="margin-top:6px;">SIDPOL: <?= h((string) (($row['sidpol'] ?? '') !== '' ? $row['sidpol'] : '-')) ?><br>Fecha: <?= h((string) (($row['fecha_accidente'] ?? '') !== '' ? $row['fecha_accidente'] : '-')) ?></div></td>
            <?php endif; ?>
            <td><span class="pill"><?= h((string) (($row['tipo_doc'] ?? '') !== '' ? $row['tipo_doc'] : 'DOC')) ?></span><div style="margin-top:6px;"><?= h((string) (($row['num_doc'] ?? '') !== '' ? $row['num_doc'] : '-')) ?></div></td>
            <td><?= h($nombre !== '' ? $nombre : 'Sin nombre') ?></td>
            <td><?= h((string) (($row['grado_policial'] ?? '') !== '' ? $row['grado_policial'] : '-')) ?></td>
            <td><?= h((string) (($row['cip'] ?? '') !== '' ? $row['cip'] : '-')) ?></td>
            <td><?= h((string) (($row['dependencia_policial'] ?? '') !== '' ? $row['dependencia_policial'] : '-')) ?></td>
            <td><?= h((string) (($row['rol_funcion'] ?? '') !== '' ? $row['rol_funcion'] : '-')) ?></td>
            <td><?= h((string) (($row['celular'] ?? '') !== '' ? $row['celular'] : 'Sin celular')) ?><br><span class="small"><?= h((string) (($row['email'] ?? '') !== '' ? $row['email'] : 'Sin email')) ?></span></td>
            <td><div class="stack-actions"><a class="btn" href="policial_interviniente_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Ver</a><a class="btn" href="policial_interviniente_editar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a><a class="btn danger" href="policial_interviniente_eliminar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a><a class="btn" href="marcador_manifestacion_policia.php?policia_id=<?= (int) $row['id'] ?>&accidente_id=<?= (int) $row['accidente_id'] ?>&download=1">Manifestacion</a></div></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
