<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function append_query(string $url, array $params): string {
    $frag = '';
    if (str_contains($url, '#')) {
        [$url, $frag] = explode('#', $url, 2);
        $frag = '#' . $frag;
    }
    $query = http_build_query(array_filter($params, static fn($v) => $v !== null && $v !== ''));
    if ($query === '') return $url . $frag;
    return $url . (str_contains($url, '?') ? '&' : '?') . $query . $frag;
}

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    http_response_code(400);
    exit('ID invalido');
}

$sql = "
SELECT ip.id, ip.accidente_id, ip.lesion, ip.observaciones,
       a.fecha_accidente, a.lugar,
       p.num_doc, p.nombres, p.apellido_paterno, p.apellido_materno,
       v.placa, v.color,
       r.Nombre AS rol_nombre
FROM involucrados_personas ip
JOIN accidentes a            ON a.id = ip.accidente_id
JOIN personas  p             ON p.id = ip.persona_id
LEFT JOIN vehiculos v        ON v.id = ip.vehiculo_id
JOIN participacion_persona r ON r.Id = ip.rol_id
WHERE ip.id = ?
LIMIT 1";
$st = $pdo->prepare($sql);
$st->execute([$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    exit('Registro no encontrado');
}

$returnTo = (string)($_GET['return_to'] ?? ($_POST['return_to'] ?? ($_GET['return'] ?? ($_POST['return'] ?? ''))));
if ($returnTo === '') {
    $returnTo = 'involucrados_personas_listar.php?accidente_id=' . (int)$row['accidente_id'];
}

$persona = trim(($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ', ' . ($row['nombres'] ?? ''));
$vehiculo = trim((string)($row['placa'] ?? ''));
if (!empty($row['color'])) {
    $vehiculo .= ($vehiculo !== '' ? ' - ' : '') . $row['color'];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
    try {
        $delete = $pdo->prepare("DELETE FROM involucrados_personas WHERE id = ? LIMIT 1");
        $delete->execute([$id]);
        header('Location: ' . append_query($returnTo, ['msg' => 'eliminado']));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Eliminar Involucrado</title>
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
  <h1 style="margin-top:0">Eliminar involucrado de persona #<?= (int)$id ?></h1>
  <?php if($error): ?><div class="err">Error: <?= h($error) ?></div><?php endif; ?>
  <p>Se eliminara este involucrado del accidente de forma permanente.</p>

  <div class="meta">
    <div class="row"><div class="lbl">Persona</div><div class="val"><?= h($persona) ?> - DNI <?= h($row['num_doc'] ?? '-') ?></div></div>
    <div class="row"><div class="lbl">Rol</div><div class="val"><?= h($row['rol_nombre'] ?? '-') ?></div></div>
    <div class="row"><div class="lbl">Vehiculo</div><div class="val"><?= h($vehiculo !== '' ? $vehiculo : '-') ?></div></div>
    <div class="row"><div class="lbl">Accidente</div><div class="val">#<?= (int)$row['accidente_id'] ?> - <?= h($row['fecha_accidente'] ?? '-') ?> - <?= h($row['lugar'] ?? '-') ?></div></div>
  </div>

  <form method="post" class="actions">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <input type="hidden" name="confirm" value="1">
    <a class="btn" href="<?= h($returnTo) ?>">Cancelar</a>
    <button class="btn primary" type="submit">Eliminar</button>
  </form>
</div>
</body>
</html>
