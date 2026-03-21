<?php
require __DIR__ . '/auth.php';
require_login();

header('Content-Type: text/html; charset=utf-8');

$target = 'Dato_General_accidente.php';
$params = [];
if (isset($_GET['accidente_id']) && $_GET['accidente_id'] !== '') {
    $params['accidente_id'] = $_GET['accidente_id'];
}
if (isset($_GET['sidpol']) && $_GET['sidpol'] !== '') {
    $params['sidpol'] = $_GET['sidpol'];
}
if (isset($_GET['embed']) && $_GET['embed'] !== '') {
    $params['embed'] = $_GET['embed'];
}

if ($params !== []) {
    $target .= '?' . http_build_query($params);
}

header('Location: ' . $target, true, 302);
exit;
