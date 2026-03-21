<?php
require __DIR__ . '/auth.php';
require_login();

header('Content-Type: text/html; charset=utf-8');

$target = 'doc_lc_leer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        exit('Falta id de licencia.');
    }
    ?>
    <!doctype html>
    <html lang="es">
    <head><meta charset="utf-8"><title>Redirigiendo...</title></head>
    <body>
      <form id="forward" method="post" action="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="do" value="del">
        <input type="hidden" name="embed" value="<?= htmlspecialchars((string) ($_POST['embed'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars((string) ($_POST['return_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </form>
      <script>document.getElementById('forward').submit();</script>
    </body>
    </html>
    <?php
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id de licencia.');
}

$params = ['id' => $id];
if (isset($_GET['embed']) && $_GET['embed'] !== '') {
    $params['embed'] = $_GET['embed'];
}
if (isset($_GET['return_to']) && $_GET['return_to'] !== '') {
    $params['return_to'] = $_GET['return_to'];
}

header('Location: ' . $target . '?' . http_build_query($params), true, 302);
exit;
