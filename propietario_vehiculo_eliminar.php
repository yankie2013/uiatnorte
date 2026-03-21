<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\PropietarioVehiculoRepository;
use App\Services\PropietarioVehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function append_query(string $url, array $params): string
{
    $frag = '';
    if (str_contains($url, '#')) {
        [$url, $frag] = explode('#', $url, 2);
        $frag = '#' . $frag;
    }
    $query = http_build_query(array_filter($params, static fn($v) => $v !== null && $v !== ''));
    if ($query === '') return $url . $frag;
    return $url . (str_contains($url, '?') ? '&' : '?') . $query . $frag;
}

$service = new PropietarioVehiculoService(new PropietarioVehiculoRepository($pdo));
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id');
}

$row = $service->detalle($id);
if ($row === null) {
    http_response_code(404);
    exit('Registro no encontrado.');
}

$accidenteId = (int)($row['accidente_id'] ?? ($_POST['accidente_id'] ?? 0));
$returnTo = trim((string)($_GET['return_to'] ?? ($_POST['return_to'] ?? '')));
if ($returnTo === '') {
    $returnTo = 'propietario_vehiculo_listar.php?accidente_id=' . $accidenteId;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
    try {
        $service->delete($id, $accidenteId);
        header('Location: ' . append_query($returnTo, ['ok' => 'deleted']));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$principal = ($row['tipo_propietario'] ?? '') === 'NATURAL'
    ? trim((string)(($row['ap_nat'] ?? '') . ' ' . ($row['am_nat'] ?? '') . ' ' . ($row['no_nat'] ?? '')))
    : (string)($row['razon_social'] ?? '');
$vehiculo = trim((string)((($row['orden_participacion'] ?? '') !== '' ? $row['orden_participacion'] . ' - ' : '') . ($row['placa'] ?? 'SIN PLACA')));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Eliminar propietario de vehiculo</title>
<style>
body{margin:0;background:#f6f7fb;color:#111827;font:14px/1.45 Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;padding:20px}
.wrap{max-width:760px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px}
.err{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;padding:10px 12px;border-radius:10px;margin-bottom:12px}
.meta{margin:10px 0;padding:12px;border:1px solid #e5e7eb;border-radius:12px;background:#fafafa}
.row{margin:6px 0}.lbl{font-size:12px;color:#6b7280;font-weight:700}.val{font-size:14px}
.actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:16px}
.btn{padding:8px 12px;border-radius:10px;border:1px solid #d1d5db;background:#fff;color:#111827;text-decoration:none;font-weight:600;cursor:pointer}
.btn.primary{background:#dc2626;border-color:#dc2626;color:#fff}
</style>
</head>
<body>
<div class="wrap">
  <h1 style="margin-top:0">Eliminar propietario de vehiculo #<?= (int)$id ?></h1>
  <?php if($error): ?><div class="err">Error: <?= h($error) ?></div><?php endif; ?>
  <p>Se eliminara este registro de forma permanente.</p>

  <div class="meta">
    <div class="row"><div class="lbl">Vehiculo</div><div class="val"><?= h($vehiculo !== '' ? $vehiculo : 'Sin vehiculo') ?></div></div>
    <div class="row"><div class="lbl">Tipo</div><div class="val"><?= h((string)($row['tipo_propietario'] ?? '-')) ?></div></div>
    <div class="row"><div class="lbl">Propietario</div><div class="val"><?= h($principal !== '' ? $principal : 'Sin propietario') ?></div></div>
  </div>

  <form method="post" class="actions">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="accidente_id" value="<?= (int)$accidenteId ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <input type="hidden" name="confirm" value="1">
    <a class="btn" href="<?= h($returnTo) ?>">Cancelar</a>
    <button class="btn primary" type="submit">Eliminar</button>
  </form>
</div>
</body>
</html>
