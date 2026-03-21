<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\ItpRepository;
use App\Services\ItpService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new ItpService(new ItpRepository($pdo));
$id = (int) ($_GET['id'] ?? $_GET['itp_id'] ?? $_POST['id'] ?? $_POST['itp_id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id');
}

$detail = $service->detalle($id);
if ($detail === null) {
    http_response_code(404);
    exit('ITP no encontrado.');
}
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'itp_listar.php?accidente_id=' . (int) $detail['accidente_id'];
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
    try {
        $service->delete($id);
        header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'ok=' . urlencode('ITP eliminado correctamente.'));
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
<title>Eliminar ITP</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--danger:#b91c1c;--gold:#b68b1f}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--danger:#fecaca;--gold:#e6c97d}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:760px;margin:24px auto;padding:0 12px}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.danger{color:var(--danger)}.small{color:var(--muted);font-size:12px}h1{color:var(--gold);margin-top:0}
</style>
</head>
<body>
<div class="wrap"><div class="card"><h1>Eliminar ITP</h1><?php if($error!==''): ?><p class="small" style="color:var(--danger)"><?= h($error) ?></p><?php endif; ?><p>Estas por eliminar el ITP <strong>#<?= (int) $id ?></strong>.</p><p class="small">Accidente #<?= (int) $detail['accidente_id'] ?> - SIDPOL: <?= h((string) ($detail['registro_sidpol'] ?? '-')) ?> - Fecha ITP: <?= h((string) (($detail['fecha_itp'] ?? '') !== '' ? $detail['fecha_itp'] : '-')) ?></p><p>Esta accion no se puede deshacer.</p><form method="post" style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px;"><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="return_to" value="<?= h($returnTo) ?>"><input type="hidden" name="confirm" value="1"><a class="btn" href="<?= h($returnTo) ?>">Cancelar</a><button class="btn danger" type="submit">Si, eliminar</button></form></div></div>
</body>
</html>
