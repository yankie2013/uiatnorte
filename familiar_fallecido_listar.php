<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\FamiliarFallecidoRepository;
use App\Services\FamiliarFallecidoService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new FamiliarFallecidoService(new FamiliarFallecidoRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta accidente_id');
}

$ok = trim((string) ($_GET['ok'] ?? ''));
$err = trim((string) ($_GET['err'] ?? ''));
$rows = $service->listado($accidenteId);
$ctx = $service->formContext($accidenteId);
$returnTo = $_SERVER['REQUEST_URI'] ?? ('familiar_fallecido_listar.php?accidente_id=' . $accidenteId);

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Familiares de fallecidos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#dc2626;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#f87171;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1240px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(220,38,38,.12);color:var(--primary);border:1px solid rgba(220,38,38,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.small{color:var(--muted);font-size:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:1120px}th,td{padding:12px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;background:rgba(148,163,184,.08)}tbody tr:hover{background:rgba(220,38,38,.05)}.stack-actions{display:flex;gap:8px;flex-wrap:wrap}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Familiares de fallecidos <span class="badge">Listado</span></h1>
      <div class="small">Accidente ID: <?= (int) $accidenteId ?> - <?= count($rows) ?> registro(s)</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= (int) $accidenteId ?>">Volver al accidente</a>
      <a class="btn primary" href="familiar_fallecido_nuevo.php?accidente_id=<?= (int) $accidenteId ?>">Nuevo familiar</a>
    </div>
  </div>

  <?php if ($ok !== ''): ?>
    <div class="ok"><?php
      $messages = [
        'created' => 'Registro creado correctamente.',
        'updated' => 'Registro actualizado correctamente.',
        'deleted' => 'Registro eliminado correctamente.',
      ];
      echo h($messages[$ok] ?? 'Operacion realizada correctamente.');
    ?></div>
  <?php endif; ?>
  <?php if ($err !== ''): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <?php if ($ctx['accidente']): ?>
    <div class="card" style="margin-bottom:14px;">
      <strong>Accidente #<?= (int) $ctx['accidente']['id'] ?></strong>
      <div class="small" style="margin-top:4px;">
        SIDPOL: <?= h((string) ($ctx['accidente']['sidpol'] ?? 'Sin SIDPOL')) ?>
        - Registro: <?= h((string) ($ctx['accidente']['registro_sidpol'] ?? 'Sin registro')) ?>
        - Fecha: <?= h((string) ($ctx['accidente']['fecha_accidente'] ?? 'Sin fecha')) ?>
        - Lugar: <?= h((string) ($ctx['accidente']['lugar'] ?? 'Sin lugar')) ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Fallecido</th>
          <th>Documento</th>
          <th>Familiar</th>
          <th>Documento familiar</th>
          <th>Parentesco</th>
          <th>Contacto</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="8" style="text-align:center;padding:24px;" class="small">No hay familiares registrados para este accidente.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
          $fallecido = trim((string) (($row['ap_fall'] ?? '') . ' ' . ($row['am_fall'] ?? '') . ' ' . ($row['no_fall'] ?? '')));
          $familiar = trim((string) (($row['ap_fam'] ?? '') . ' ' . ($row['am_fam'] ?? '') . ' ' . ($row['no_fam'] ?? '')));
          $contacto = trim((string) ($row['cel_fam'] ?? ''));
          if (($row['em_fam'] ?? '') !== '') {
            $contacto .= $contacto !== '' ? ' - ' . $row['em_fam'] : $row['em_fam'];
          }
          ?>
          <tr>
            <td>#<?= (int) $row['id'] ?></td>
            <td><?= h($fallecido !== '' ? $fallecido : 'Sin fallecido') ?></td>
            <td><?= h((string) (($row['dni_fall'] ?? '') !== '' ? $row['dni_fall'] : '-')) ?></td>
            <td><?= h($familiar !== '' ? $familiar : 'Sin familiar') ?></td>
            <td><?= h(trim((string) (($row['tipo_doc_fam'] ?? '') . ' ' . ($row['dni_fam'] ?? '')))) ?></td>
            <td><?= h((string) (($row['parentesco'] ?? '') !== '' ? $row['parentesco'] : '-')) ?></td>
            <td><?= h($contacto !== '' ? $contacto : 'Sin contacto') ?></td>
            <td>
              <div class="stack-actions">
                <a class="btn" href="familiar_fallecido_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Ver</a>
                <a class="btn" href="familiar_fallecido_editar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a>
                <a class="btn" href="marcador_manifestacion_familiar.php?fam_id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">Manifestacion</a>
                <a class="btn danger" href="familiar_fallecido_eliminar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
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
