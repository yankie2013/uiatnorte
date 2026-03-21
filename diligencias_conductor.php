<?php
require __DIR__ . '/auth.php';
require_login();

header('Content-Type: text/html; charset=utf-8');

$target = 'diligencias_conductor_editar.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ?>
    <!doctype html>
    <html lang="es">
    <head><meta charset="utf-8"><title>Redirigiendo...</title></head>
    <body>
      <form id="forward" method="post" action="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($_POST as $key => $value): ?>
          <?php if (is_array($value)) continue; ?>
          <input type="hidden" name="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
      </form>
      <script>document.getElementById('forward').submit();</script>
    </body>
    </html>
    <?php
    exit;
}

$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . $target . ($query !== '' ? ('?' . $query) : ''), true, 302);
exit;
