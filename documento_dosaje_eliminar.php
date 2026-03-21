<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoDosajeRepository;
use App\Services\DocumentoDosajeService;

header('Content-Type: text/html; charset=utf-8');

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

$service = new DocumentoDosajeService(new DocumentoDosajeRepository($pdo));
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$return = (string)($_GET['return_to'] ?? ($_POST['return_to'] ?? ''));
if($id <= 0){
    http_response_code(400);
    exit('ID invalido');
}

$detalle = $service->detalle($id);
if(!$detalle){
    http_response_code(404);
    exit('Registro no encontrado');
}
$row = $detalle['row'];
$return = $return !== '' ? $return : ('documento_dosaje_listar.php' . (!empty($row['persona_id']) ? ('?persona_id=' . (int)$row['persona_id']) : ''));
$persona = trim(($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ', ' . ($row['nombres'] ?? ''));
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1'){
    try {
        $service->eliminar($id);
        header('Location: ' . append_query($return, ['msg' => 'eliminado']));
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
<title>Eliminar Dosaje</title>
<link rel="stylesheet" href="style_mushu.css">
<style>
.wrap{max-width:760px;margin:20px auto;padding:0 14px}.card{padding:16px}.err{background:#3f1012;color:#ffd6d6;border:1px solid #7a1616;padding:10px 12px;border-radius:10px;margin-bottom:12px}.actions{display:flex;gap:8px;justify-content:flex-end;margin-top:14px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Eliminar Dosaje #<?= (int)$id ?></h1>
  <?php if($error): ?><div class="err">Error: <?= h($error) ?></div><?php endif; ?>
  <div class="card">
    <p>Se eliminara este dosaje de forma permanente.</p>
    <p><strong>Persona:</strong> <?= h($persona ?: '-') ?> - DNI <?= h($row['num_doc'] ?? '-') ?></p>
    <p><strong>Numero:</strong> <?= h($row['numero'] ?: '-') ?></p>
    <p><strong>Fecha extraccion:</strong> <?= h($row['fecha_extraccion'] ?: '-') ?></p>
    <form method="post" class="actions">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <input type="hidden" name="return_to" value="<?= h($return) ?>">
      <input type="hidden" name="confirm" value="1">
      <a class="btn" href="<?= h($return) ?>">Cancelar</a>
      <button class="btn primary" type="submit">Eliminar</button>
    </form>
  </div>
</div>
</body>
</html>
