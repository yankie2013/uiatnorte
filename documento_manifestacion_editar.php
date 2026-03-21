<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoManifestacionRepository;
use App\Services\DocumentoManifestacionService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

$service = new DocumentoManifestacionService(new DocumentoManifestacionRepository($pdo));
$id = (int) g('id', 0);
$embed = g('embed', '0') === '1';
$returnTo = g('return_to', '');
$ctx = $service->detalle($id);
if (!$ctx) {
    http_response_code(404);
    exit('Manifestacion no encontrada');
}

$row = $ctx['row'];
$acc_label = $ctx['acc_label'];
$per_label = $ctx['per_label'];
$modalidades = $ctx['modalidades'];
$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $service->actualizar($id, $_POST);
        $ok = 'Manifestacion actualizada';
        if ($embed) {
            echo "<script>try{parent.postMessage({type:'manifestacion.saved'}, '*');}catch(e){}</script>";
            exit;
        }
        $ctx = $service->detalle($id) ?: $ctx;
        $row = $ctx['row'];
        $acc_label = $ctx['acc_label'];
        $per_label = $ctx['per_label'];
    } catch (Throwable $e) {
        $err = $e->getMessage();
        $row = array_merge($row, [
            'fecha' => $_POST['fecha'] ?? ($row['fecha'] ?? ''),
            'horario_inicio' => $_POST['horario_inicio'] ?? ($row['horario_inicio'] ?? ''),
            'hora_termino' => $_POST['hora_termino'] ?? ($row['hora_termino'] ?? ''),
            'modalidad' => $_POST['modalidad'] ?? ($row['modalidad'] ?? ''),
        ]);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar Manifestacion</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
</head>
<body>
<div class="wrap">
  <div class="bar">
    <h1>Editar Manifestacion</h1>
    <a class="btn small" href="<?= $returnTo ? h($returnTo) : 'javascript:history.back()' ?>">Cerrar</a>
  </div>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <?php if ($ok && !$embed): ?><div class="ok"><?= h($ok) ?></div><?php endif; ?>

  <form method="post" class="card">
    <div class="grid">
      <div class="col-6"><label>Accidente</label><input type="text" value="<?= h($acc_label) ?>" readonly></div>
      <div class="col-6"><label>Persona</label><input type="text" value="<?= h($per_label) ?>" readonly></div>
      <div class="col-4"><label>Fecha</label><input type="date" name="fecha" required value="<?= h($row['fecha'] ?? '') ?>"></div>
      <div class="col-4"><label>Hora inicio</label><input type="time" name="horario_inicio" required value="<?= h($row['horario_inicio'] ?? '') ?>"></div>
      <div class="col-4"><label>Hora termino</label><input type="time" name="hora_termino" required value="<?= h($row['hora_termino'] ?? '') ?>"></div>
      <div class="col-6"><label>Modalidad</label><select name="modalidad" required><option value="">Selecciona</option><?php foreach($modalidades as $opt): ?><option value="<?= h($opt) ?>" <?= (($row['modalidad'] ?? '') === $opt) ? 'selected' : '' ?>><?= h($opt) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="actions"><a class="btn" href="<?= $returnTo ? h($returnTo) : 'javascript:history.back()' ?>">Cancelar</a><button class="btn primary" type="submit">Guardar cambios</button></div>
  </form>
</div>
</body>
</html>
