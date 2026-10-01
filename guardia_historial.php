<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
if (\App\Support\Access::role() !== 'guardia') {
    http_response_code(403);
    exit('Esta sección corresponde al comandante de guardia.');
}
// Conserva los enlaces al historial anterior y abre el navegador compartido.
$params = $_GET;
if (isset($params['distrito']) && preg_match('/^(\d+)-(\d+)-(\d+)$/D', (string)$params['distrito'], $codes)) {
    $st = $pdo->prepare('SELECT nombre FROM ubigeo_distrito WHERE cod_dep=? AND cod_prov=? AND cod_dist=?');
    $st->execute(array_slice($codes, 1));
    $params['distrito'] = $st->fetchColumn() ?: '';
}
if (!empty($params['comisaria_id']) && empty($params['distrito'])) $params['ver_todos'] = '1';
$query = http_build_query($params);
header('Location: accidente_listar.php'.($query !== '' ? '?'.$query : ''));
exit;
