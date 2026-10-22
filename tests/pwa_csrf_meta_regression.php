<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/app.php';
use App\Support\PwaSupport;
$before = $_SESSION ?? [];
$oldGet = $_GET;
try {
    $_SESSION['user'] = ['id' => 1, 'rol' => 'viewer'];
    $_SESSION['rbac_csrf'] = bin2hex(random_bytes(32));
    $_GET = [];
    $html = PwaSupport::inject('<!doctype html><html><head><title>Test</title></head><body></body></html>');
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $nodes = (new DOMXPath($dom))->query('//meta[@name="csrf-token"]');
    if ($nodes->length !== 1 || $nodes->item(0)->getAttribute('content') !== $_SESSION['rbac_csrf']) {
        throw new RuntimeException('El navegador debe poder leer el token CSRF desde la etiqueta meta.');
    }
    echo "PASS: token CSRF válido en el HTML entregado al navegador.\n";
} finally {
    $_SESSION = $before;
    $_GET = $oldGet;
}
