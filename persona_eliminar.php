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
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id');
}

$context = $service->detailContext($id);
$row = $context['row'];
$references = $context['references'];
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'persona_listar.php';
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
    try {
        $service->delete($id);
        header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'ok=deleted');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Eliminar persona</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--danger:#fecaca}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:760px;margin:24px auto;padding:0 12px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.small{color:var(--muted);font-size:12px}.warn{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.refs{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:11px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1 style="margin-top:0;">Eliminar persona</h1>
    <div class="small">Registro #<?= (int) $row['id'] ?></div>
    <p>Estas por eliminar a <strong><?= h(trim((string) (($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ', ' . ($row['nombres'] ?? '')))) ?></strong>.</p>
    <p class="small">Documento: <?= h(trim((string) (($row['tipo_doc'] ?? '') . ' ' . ($row['num_doc'] ?? '')))) ?></p>
    <?php if ($error !== ''): ?><div class="warn"><?= h($error) ?></div><?php endif; ?>

    <?php if ($references !== []): ?>
      <div class="warn">No se puede eliminar esta persona porque aun esta vinculada a otros modulos.</div>
      <div class="refs"><?php foreach ($references as $ref): ?><span class="pill"><?= h($ref['label']) ?>: <?= (int) $ref['count'] ?></span><?php endforeach; ?></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;"><a class="btn" href="persona_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Ver ficha</a><a class="btn" href="<?= h($returnTo) ?>">Volver</a></div>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
        <input type="hidden" name="confirm" value="1">
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;"><a class="btn" href="persona_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Ver ficha</a><a class="btn" href="<?= h($returnTo) ?>">Cancelar</a><button class="btn danger" type="submit">Si, eliminar</button></div>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
