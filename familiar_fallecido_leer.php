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
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id');
}

$row = $service->detalle($id);
if ($row === null) {
    http_response_code(404);
    exit('Registro no encontrado.');
}
$returnTo = trim((string) ($_GET['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'familiar_fallecido_listar.php?accidente_id=' . (int) $row['accidente_id'];
}
$selfUrl = $_SERVER['REQUEST_URI'] ?? ('familiar_fallecido_leer.php?id=' . $id . '&return_to=' . urlencode($returnTo));
$ok = trim((string) ($_GET['ok'] ?? ''));

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Detalle de familiar de fallecido</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#dc2626;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#f87171;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:980px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(220,38,38,.12);color:var(--primary);border:1px solid rgba(220,38,38,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.field{padding:12px;border:1px solid var(--border);border-radius:14px;background:rgba(148,163,184,.08)}.label{font-size:12px;color:var(--muted);font-weight:700;margin-bottom:4px}.value{font-weight:700;word-break:break-word}.small{color:var(--muted);font-size:12px}.actions{display:flex;gap:10px;flex-wrap:wrap}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:14px}@media(max-width:820px){.c6{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Familiar de fallecido <span class="badge">Detalle</span></h1>
      <div class="small">Registro #<?= (int) $id ?> - Accidente ID: <?= (int) $row['accidente_id'] ?></div>
    </div>
    <div class="actions">
      <a class="btn" href="<?= h($returnTo) ?>">Volver</a>
      <a class="btn" href="familiar_fallecido_editar.php?id=<?= (int) $id ?>&return_to=<?= urlencode($selfUrl) ?>">Editar</a>
      <a class="btn danger" href="familiar_fallecido_eliminar.php?id=<?= (int) $id ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
    </div>
  </div>

  <?php if ($ok !== ''): ?><div class="ok"><?= h($ok === 'updated' ? 'Registro actualizado correctamente.' : 'Operacion realizada correctamente.') ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:14px;">
    <strong>Accidente #<?= (int) $row['accidente_id'] ?></strong>
    <div class="small" style="margin-top:4px;">
      SIDPOL: <?= h((string) ($row['sidpol'] ?? 'Sin SIDPOL')) ?>
      - Registro: <?= h((string) ($row['registro_sidpol'] ?? 'Sin registro')) ?>
      - Fecha: <?= h((string) ($row['fecha_accidente'] ?? 'Sin fecha')) ?>
      - Lugar: <?= h((string) ($row['lugar'] ?? 'Sin lugar')) ?>
    </div>
  </div>

  <div class="card">
    <div class="grid">
      <div class="c6 field">
        <div class="label">Fallecido</div>
        <div class="value"><?= h(trim((string) (($row['ap_fall'] ?? '') . ' ' . ($row['am_fall'] ?? '') . ' ' . ($row['no_fall'] ?? '')))) ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Documento del fallecido</div>
        <div class="value"><?= h(trim((string) (($row['tipo_doc_fall'] ?? '') . ' ' . ($row['dni_fall'] ?? '')))) ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Familiar</div>
        <div class="value"><?= h(trim((string) (($row['ap_fam'] ?? '') . ' ' . ($row['am_fam'] ?? '') . ' ' . ($row['no_fam'] ?? '')))) ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Documento del familiar</div>
        <div class="value"><?= h(trim((string) (($row['tipo_doc_fam'] ?? '') . ' ' . ($row['dni_fam'] ?? '')))) ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Celular</div>
        <div class="value"><?= h((string) (($row['cel_fam'] ?? '') !== '' ? $row['cel_fam'] : 'Sin celular')) ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Email</div>
        <div class="value"><?= h((string) (($row['em_fam'] ?? '') !== '' ? $row['em_fam'] : 'Sin email')) ?></div>
      </div>
      <div class="c12 field">
        <div class="label">Domicilio</div>
        <div class="value"><?= h((string) (($row['dom_fam'] ?? '') !== '' ? $row['dom_fam'] : 'Sin domicilio')) ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Parentesco</div>
        <div class="value"><?= h((string) (($row['parentesco'] ?? '') !== '' ? $row['parentesco'] : 'Sin parentesco')) ?></div>
      </div>
      <div class="c12 field">
        <div class="label">Observaciones</div>
        <div class="value"><?= nl2br(h((string) (($row['observaciones'] ?? '') !== '' ? $row['observaciones'] : 'Sin observaciones'))) ?></div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
