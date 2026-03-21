<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\VehiculoRepository;
use App\Services\VehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($s)
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function ref_label($table)
{
    static $map = [
        'diligencias_conductor' => 'Diligencias del conductor',
        'documento_vehiculo' => 'Documentos de vehiculo',
        'involucrados_personas' => 'Involucrados personas',
        'involucrados_vehiculos' => 'Involucrados vehiculos',
    ];

    return $map[$table] ?? $table;
}

$service = new VehiculoService(new VehiculoRepository($pdo));
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: vehiculo_listar.php');
    exit;
}

$context = $service->contextoEliminacion($id);
if ($context === null) {
    header('Location: vehiculo_listar.php');
    exit;
}

$veh = $context['vehiculo'];
$references = $context['references'];
$canDelete = $context['can_delete'];
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'vehiculo_listar.php';
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'si') {
    try {
        $service->eliminar($id);
        header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'msg=eliminado');
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
        $context = $service->contextoEliminacion($id) ?? $context;
        $references = $context['references'];
        $canDelete = $context['can_delete'];
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UIAT Norte - Eliminar vehiculo</title>
<link rel="stylesheet" href="style_gian.css">
<style>
  .refs{margin-top:14px;padding-left:20px}
  .refs li{margin:6px 0}
  .warn{margin-top:12px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card" style="max-width:760px;">
    <div class="hdr">
      <div class="ttl">Eliminar vehiculo</div>
      <span class="pill">Estilo GIAN</span>
    </div>
    <div class="body">
      <?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>

      <div class="msg err">
        Estas a punto de eliminar el siguiente vehiculo:
        <br>Placa: <strong><?= h($veh['placa']) ?></strong>
        <?php if ($veh['marca'] || $veh['modelo']): ?>
          <br>Marca/Modelo: <?= h(trim($veh['marca'] . ' ' . $veh['modelo'])) ?>
        <?php endif; ?>
      </div>

      <?php if ($canDelete): ?>
        <div class="msg ok warn">No se detectaron referencias activas por <code>vehiculo_id</code>. Se puede eliminar.</div>
      <?php else: ?>
        <div class="msg err warn">Este vehiculo no puede eliminarse porque todavia esta vinculado en otros modulos.</div>
        <ul class="refs">
          <?php foreach ($references as $table => $count): ?>
            <li><strong><?= h(ref_label($table)) ?>:</strong> <?= (int) $count ?> registro(s)</li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form method="post" class="row" style="justify-content:flex-end; gap:10px;">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
        <a class="btn sec" href="<?= h($returnTo) ?>">Cancelar</a>
        <a class="btn sec" href="vehiculo_leer.php?id=<?= $id ?>&return_to=<?= urlencode($returnTo) ?>">Ver ficha</a>
        <?php if ($canDelete): ?>
          <button class="btn" type="submit" name="confirm" value="si">Si, eliminar</button>
        <?php endif; ?>
      </form>
    </div>
    <div class="foot">
      <span class="hint">UIAT Norte - Vehiculos</span>
      <span class="hint">Confirmacion de eliminacion</span>
    </div>
  </div>
</div>
</body>
</html>
