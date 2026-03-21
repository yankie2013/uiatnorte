<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoOccisoRepository;
use App\Services\DocumentoOccisoService;

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
function persona_lbl(array $row): string {
    $txt = trim(($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? ''));
    return $txt !== '' ? $txt : ('ID ' . (int)($row['persona_id'] ?? 0));
}

$service = new DocumentoOccisoService(new DocumentoOccisoRepository($pdo));
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    http_response_code(400);
    exit('ID invalido');
}

$row = $service->detalle($id);
if (!$row) {
    http_response_code(404);
    exit('Documento no encontrado');
}

$returnTo = (string)($_GET['return_to'] ?? ($_POST['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'documento_occiso_list.php?persona_id=' . (int)($row['persona_id'] ?? 0) . '&accidente_id=' . (int)($row['accidente_id'] ?? 0);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
    try {
        $service->eliminar($id);
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
<title>Eliminar Documento de Occiso</title>
<link rel="stylesheet" href="style_mushu.css">
<style>
.wrap{max-width:760px;margin:20px auto;padding:0 14px}
.card{padding:16px}
.err{background:#3f1012;color:#ffd6d6;border:1px solid #7a1616;padding:10px 12px;border-radius:10px;margin-bottom:12px}
.row{margin:8px 0}.lbl{font-size:12px;opacity:.8;font-weight:700}
.actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:14px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Eliminar documento de occiso #<?= (int)$id ?></h1>
  <?php if($error): ?><div class="err">Error: <?= h($error) ?></div><?php endif; ?>
  <div class="card">
    <p>Se eliminara este documento de forma permanente.</p>
    <div class="row"><div class="lbl">Persona</div><div><?= h(persona_lbl($row)) ?></div></div>
    <div class="row"><div class="lbl">Accidente</div><div>#<?= (int)($row['accidente_id'] ?? 0) ?></div></div>
    <div class="row"><div class="lbl">Protocolo</div><div><?= h($row['numero_protocolo'] ?? '-') ?></div></div>
    <form method="post" class="actions">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
      <input type="hidden" name="confirm" value="1">
      <a class="btn" href="<?= h($returnTo) ?>">Cancelar</a>
      <button class="btn primary" type="submit">Eliminar</button>
    </form>
  </div>
</div>
</body>
</html>
