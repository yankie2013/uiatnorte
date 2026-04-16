<?php
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$_SESSION['last_keepalive_at'] = time();

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
