<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\PropietarioVehiculoRepository;
use App\Services\PropietarioVehiculoService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new PropietarioVehiculoService(new PropietarioVehiculoRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta accidente_id');
}

$ok = trim((string) ($_GET['ok'] ?? ''));
$err = trim((string) ($_GET['err'] ?? ''));
$rows = $service->listado($accidenteId);
$ctx = $service->formContext($accidenteId);
$returnTo = $_SERVER['REQUEST_URI'] ?? ('propietario_vehiculo_listar.php?accidente_id=' . $accidenteId);

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Propietarios de vehiculo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#ca8a04;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#facc15;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1240px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(202,138,4,.12);color:var(--primary);border:1px solid rgba(202,138,4,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#111827;border-color:transparent}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.small{color:var(--muted);font-size:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:1080px}th,td{padding:12px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;background:rgba(148,163,184,.08)}tbody tr:hover{background:rgba(202,138,4,.05)}.stack-actions{display:flex;gap:8px;flex-wrap:wrap}.pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:11px}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Propietarios de vehiculo <span class="badge">Listado</span></h1>
      <div class="small">Accidente ID: <?= (int) $accidenteId ?> - <?= count($rows) ?> registro(s)</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= (int) $accidenteId ?>">Volver al accidente</a>
      <a class="btn primary" href="propietario_vehiculo_nuevo.php?accidente_id=<?= (int) $accidenteId ?>">Nuevo propietario</a>
    </div>
  </div>

  <?php if ($ok !== ''): ?><div class="ok"><?php $messages=['created'=>'Registro creado correctamente.','updated'=>'Registro actualizado correctamente.','deleted'=>'Registro eliminado correctamente.']; echo h($messages[$ok] ?? 'Operacion realizada correctamente.'); ?></div><?php endif; ?>
  <?php if ($err !== ''): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <?php if ($ctx['accidente']): ?>
    <div class="card" style="margin-bottom:14px;"><strong>Accidente #<?= (int) $ctx['accidente']['id'] ?></strong><div class="small" style="margin-top:4px;">SIDPOL: <?= h((string) ($ctx['accidente']['sidpol'] ?? 'Sin SIDPOL')) ?> - Registro: <?= h((string) ($ctx['accidente']['registro_sidpol'] ?? 'Sin registro')) ?> - Fecha: <?= h((string) ($ctx['accidente']['fecha_accidente'] ?? 'Sin fecha')) ?> - Lugar: <?= h((string) ($ctx['accidente']['lugar'] ?? 'Sin lugar')) ?></div></div>
  <?php endif; ?>

  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Vehiculo</th><th>Tipo</th><th>Propietario / Razon social</th><th>Documento / RUC</th><th>Representante</th><th>Rol legal</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="8" style="text-align:center;padding:24px;" class="small">No hay propietarios registrados para este accidente.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php $vehiculo = trim((string) (($row['orden_participacion'] ?? '') !== '' ? $row['orden_participacion'] . ' - ' : '') . ($row['placa'] ?? 'SIN PLACA')); if (($row['tipo_propietario'] ?? '') === 'NATURAL') { $principal = trim((string) (($row['ap_nat'] ?? '') . ' ' . ($row['am_nat'] ?? '') . ' ' . ($row['no_nat'] ?? ''))); $doc = trim((string) (($row['tipo_doc_nat'] ?? '') . ' ' . ($row['dni_nat'] ?? ''))); $representante = '-'; } else { $principal = (string) ($row['razon_social'] ?? 'Sin razon social'); $doc = (string) ($row['ruc'] ?? ''); $representante = trim((string) (($row['ap_rep'] ?? '') . ' ' . ($row['am_rep'] ?? '') . ' ' . ($row['no_rep'] ?? ''))); } ?>
          <tr>
            <td>#<?= (int) $row['id'] ?></td>
            <td><?= h($vehiculo) ?></td>
            <td><span class="pill"><?= h((string) ($row['tipo_propietario'] ?? '')) ?></span></td>
            <td><?= h($principal !== '' ? $principal : 'Sin propietario') ?></td>
            <td><?= h($doc !== '' ? $doc : '-') ?></td>
            <td><?= h($representante !== '' ? $representante : '-') ?></td>
            <td><?= h((string) (($row['rol_legal'] ?? '') !== '' ? $row['rol_legal'] : '-')) ?></td>
            <td><div class="stack-actions"><a class="btn" href="propietario_vehiculo_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Ver</a><a class="btn" href="propietario_vehiculo_editar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a><a class="btn danger" href="propietario_vehiculo_eliminar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a></div></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
