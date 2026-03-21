<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\DocumentoLcRepository;
use App\Services\DocumentoLcService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function lc_emit_saved_and_exit_view(string $message, int $personaId): void
{
    ?>
    <!doctype html>
    <html lang="es"><head><meta charset="utf-8"><title>Licencia eliminada</title></head><body>
    <script>
    try { window.parent && window.parent.postMessage({type:'lc.saved', persona_id:<?= json_encode($personaId) ?>}, '*'); } catch (e) {}
    </script>
    <?= h($message) ?>
    </body></html>
    <?php
    exit;
}

$service = new DocumentoLcService(new DocumentoLcRepository($pdo));
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id');
}

$row = $service->detalle($id);
if ($row === null) {
    http_response_code(404);
    exit('Licencia no encontrada.');
}
$personaContext = $service->contextoPersona((int) $row['persona_id']);
$persona = $personaContext['persona'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'del') {
    try {
        $service->delete($id, (int) $row['persona_id']);
        if ($embed) {
            lc_emit_saved_and_exit_view('Licencia eliminada correctamente.', (int) $row['persona_id']);
        }
        header('Location: doc_lc_nuevo.php?persona_id=' . (int) $row['persona_id'] . '&ok=deleted');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (!$embed) {
    include __DIR__ . '/sidebar.php';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Detalle de licencia de conducir</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:920px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.c4{grid-column:span 4}.field{padding:12px;border:1px solid var(--border);border-radius:14px;background:rgba(148,163,184,.08)}.label{font-size:12px;color:var(--muted);font-weight:700;margin-bottom:4px}.value{font-weight:700;word-break:break-word}.small{color:var(--muted);font-size:12px}.actions{display:flex;gap:10px;flex-wrap:wrap}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}@media(max-width:860px){.c6,.c4{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1 style="margin:0 0 6px">Licencia de conducir <span class="badge">Detalle</span></h1><div class="small">Registro #<?= (int) $id ?></div></div>
    <div class="actions"><a class="btn" href="doc_lc_nuevo.php?persona_id=<?= (int) $row['persona_id'] ?><?= $embed ? '&embed=1' : '' ?><?= $returnTo !== '' ? '&return_to=' . rawurlencode($returnTo) : '' ?>">Volver</a><a class="btn" href="doc_lc_editar.php?id=<?= (int) $id ?><?= $embed ? '&embed=1' : '' ?><?= $returnTo !== '' ? '&return_to=' . rawurlencode($returnTo) : '' ?>">Editar</a><form method="post" style="display:inline;" onsubmit="return confirm('Eliminar esta licencia?');"><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="do" value="del"><input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>"><input type="hidden" name="return_to" value="<?= h($returnTo) ?>"><button class="btn danger" type="submit">Eliminar</button></form></div>
  </div>

  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:14px;"><strong><?= h(trim((string) (($persona['apellido_paterno'] ?? '') . ' ' . ($persona['apellido_materno'] ?? '') . ', ' . ($persona['nombres'] ?? '')))) ?></strong><div class="small" style="margin-top:4px;">ID: <?= (int) $persona['id'] ?> - DNI: <?= h((string) ($persona['num_doc'] ?? '-')) ?></div></div>

  <div class="card">
    <div class="grid">
      <div class="c4 field"><div class="label">Clase</div><div class="value"><?= h((string) (($row['clase'] ?? '') !== '' ? $row['clase'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Categoria</div><div class="value"><?= h((string) (($row['categoria'] ?? '') !== '' ? $row['categoria'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Numero</div><div class="value"><?= h((string) (($row['numero'] ?? '') !== '' ? $row['numero'] : '-')) ?></div></div>
      <div class="c6 field"><div class="label">Expedido por</div><div class="value"><?= h((string) (($row['expedido_por'] ?? '') !== '' ? $row['expedido_por'] : '-')) ?></div></div>
      <div class="c3 field"><div class="label">Vigente desde</div><div class="value"><?= h((string) (($row['vigente_desde'] ?? '') !== '' ? $row['vigente_desde'] : '-')) ?></div></div>
      <div class="c3 field"><div class="label">Vigente hasta</div><div class="value"><?= h((string) (($row['vigente_hasta'] ?? '') !== '' ? $row['vigente_hasta'] : '-')) ?></div></div>
      <div class="c12 field"><div class="label">Restricciones</div><div class="value"><?= nl2br(h((string) (($row['restricciones'] ?? '') !== '' ? $row['restricciones'] : '-'))) ?></div></div>
    </div>
  </div>
</div>
</body>
</html>
