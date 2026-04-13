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
$acc_pref = (int) g('accidente_id', 0);
$per_pref = (int) g('persona_id', 0);
$rol_pref = g('rol_id', '');
$embed = g('embed', '0') === '1';
$returnTo = g('return_to', '');
$downloadUrl = g('download_url', '');
if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $downloadUrl) || str_starts_with($downloadUrl, '//')) {
    $downloadUrl = '';
}
$selfUrl = 'documento_manifestacion_nuevo.php?persona_id=' . (int) $per_pref
    . '&accidente_id=' . (int) $acc_pref
    . '&rol_id=' . urlencode((string) $rol_pref)
    . '&embed=' . ($embed ? '1' : '0');
if ($returnTo !== '') {
    $selfUrl .= '&return_to=' . urlencode($returnTo);
}
if ($downloadUrl !== '') {
    $selfUrl .= '&download_url=' . urlencode($downloadUrl);
}
$ctx = $service->contextoNuevo($acc_pref, $per_pref);
$acc_label = $ctx['acc_label'];
$per_label = $ctx['per_label'];
$modalidades = $ctx['modalidades'];
$contextMissing = $acc_pref <= 0 || $per_pref <= 0 || $acc_label === '' || $per_label === '';
$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acc_pref = (int) ($_POST['accidente_id'] ?? $acc_pref);
    $per_pref = (int) ($_POST['persona_id'] ?? $per_pref);
    try {
        $service->crear($_POST);
        $ok = 'Manifestacion guardada';
        if ($embed) {
            $downloadUrlJs = json_encode($downloadUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            echo "<script>(function(){try{var url={$downloadUrlJs};if(url){var doc=parent.document;var frame=doc.getElementById('manifestacion-download-frame');if(!frame){frame=doc.createElement('iframe');frame.id='manifestacion-download-frame';frame.style.display='none';doc.body.appendChild(frame);}frame.src=url;parent.postMessage({type:'manifestacion.download_started'}, '*');setTimeout(function(){try{parent.location.reload();}catch(e){}},1200);}else{parent.postMessage({type:'manifestacion.saved'}, '*');parent.location.reload();}}catch(e){}})();</script>";
            exit;
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
    $ctx = $service->contextoNuevo($acc_pref, $per_pref);
    $acc_label = $ctx['acc_label'];
    $per_label = $ctx['per_label'];
    $modalidades = $ctx['modalidades'];
    $contextMissing = $acc_pref <= 0 || $per_pref <= 0 || $acc_label === '' || $per_label === '';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nueva Manifestacion</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
</head>
<body>
<div class="wrap">
  <div class="bar">
    <h1>Nueva Manifestacion<?= $rol_pref ? ' - ' . h($rol_pref) : '' ?></h1>
    <?php if (!$embed): ?><a class="btn small" href="<?= $returnTo ? h($returnTo) : 'index.php' ?>">Volver</a><?php endif; ?>
  </div>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <?php if ($ok && !$embed): ?><div class="ok"><?= h($ok) ?></div><?php endif; ?>
  <?php if ($contextMissing): ?><div class="err">Selecciona un accidente y una persona validos antes de registrar la manifestacion.</div><?php endif; ?>

  <form method="post" action="<?= h($selfUrl) ?>" class="card">
    <input type="hidden" name="accidente_id" value="<?= (int) $acc_pref ?>">
    <input type="hidden" name="persona_id" value="<?= (int) $per_pref ?>">
    <div class="grid">
      <div class="col-6"><label>Accidente</label><input type="text" class="input" value="<?= h($acc_label) ?>" readonly></div>
      <div class="col-6"><label>Persona</label><input type="text" class="input" value="<?= h($per_label) ?>" readonly></div>
      <div class="col-4"><label>Fecha</label><input type="date" name="fecha" required value="<?= h($_POST['fecha'] ?? '') ?>"></div>
      <div class="col-4"><label>Hora inicio</label><input type="time" name="horario_inicio" required value="<?= h($_POST['horario_inicio'] ?? '') ?>"></div>
      <div class="col-4"><label>Hora termino</label><input type="time" name="hora_termino" required value="<?= h($_POST['hora_termino'] ?? '') ?>"></div>
      <div class="col-6"><label>Modalidad</label><select name="modalidad" required><option value="">Selecciona</option><?php foreach($modalidades as $opt): ?><option value="<?= h($opt) ?>" <?= (($_POST['modalidad'] ?? '') === $opt) ? 'selected' : '' ?>><?= h($opt) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="actions"><button class="btn" type="reset">Limpiar</button><button class="btn primary" type="submit" <?= $contextMissing ? 'disabled aria-disabled="true"' : '' ?>>Guardar</button></div>
  </form>
</div>
</body>
</html>
