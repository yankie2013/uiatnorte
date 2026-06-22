<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
if ($accidenteId <= 0) {
    header('Location: accidente_listar.php');
    exit;
}

$st = $pdo->prepare('SELECT id, sidpol, registro_sidpol, lugar FROM accidentes WHERE id = ? LIMIT 1');
$st->execute([$accidenteId]);
$accidente = $st->fetch(PDO::FETCH_ASSOC);
if (!$accidente) {
    header('Location: accidente_listar.php');
    exit;
}

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Accidente creado | UIAT Norte</title>
<link rel="stylesheet" href="assets/accidente.css?v=<?= (int) filemtime(__DIR__ . '/assets/accidente.css') ?>">
<style>
  .created-box{max-width:760px;margin:42px auto;padding:24px;border:1px solid #d9e2ec;border-radius:8px;background:#fff}
  .created-box h1{margin:0 0 10px;font-size:24px}
  .created-box p{margin:8px 0;color:#46515f}
  .created-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
  iframe.download-frame{display:none;width:0;height:0;border:0}
</style>
</head>
<body>
<div class="wrap">
  <section class="created-box">
    <h1>Accidente creado</h1>
    <p>Se esta descargando el ZIP con la estructura inicial de carpetas.</p>
    <p><strong>SIDPOL:</strong> <?= h($accidente['registro_sidpol'] ?: $accidente['sidpol']) ?> &nbsp; <strong>Lugar:</strong> <?= h($accidente['lugar']) ?></p>
    <div class="created-actions">
      <a class="btn primary" href="accidente_vista_tabs.php?accidente_id=<?= (int) $accidenteId ?>">Abrir expediente</a>
      <a class="btn" href="accidente_carpeta_zip.php?accidente_id=<?= (int) $accidenteId ?>">Descargar ZIP otra vez</a>
      <a class="btn" href="accidente_nuevo.php">Registrar otro</a>
    </div>
  </section>
</div>
<iframe class="download-frame" src="accidente_carpeta_zip.php?accidente_id=<?= (int) $accidenteId ?>"></iframe>
</body>
</html>
