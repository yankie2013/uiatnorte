<?php
// MOSTRAR ERRORES (luego lo puedes quitar)
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";

echo "Ruta actual (__DIR__): " . __DIR__ . "\n\n";

// 1) Verificar autoload
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "ERROR: No se encontró $autoload\n";
    echo "Asegúrate de haber ejecutado 'composer require google/apiclient' en esta carpeta o de subir la carpeta vendor.\n";
    exit;
}
require $autoload;

// 2) Verificar credentials.json
$credPath = __DIR__ . '/google/credentials.json';
if (!file_exists($credPath)) {
    echo "ERROR: No se encontró $credPath\n";
    echo "Sube aquí el archivo credentials.json que descargaste de Google Cloud.\n";
    exit;
}

$client = new Google_Client();
$client->setAuthConfig($credPath);
$client->setRedirectUri('https://korkaystore.com/uiatnorte/google_oauth_callback.php');
$client->setScopes(Google_Service_Calendar::CALENDAR);
$client->setAccessType('offline');

// Si NO viene el parámetro "code", no es un regreso de Google
if (!isset($_GET['code'])) {
    echo "Este script debe ser llamado por Google con el parámetro 'code'.\n";
    echo "Para iniciar el proceso, visita primero google_auth_start.php (ver paso siguiente).\n";
    exit;
}

echo "Recibido code=" . htmlspecialchars($_GET['code']) . "\n\n";

// Intercambiar code -> token
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    echo "Error al obtener el token:\n";
    print_r($token);
    exit;
}

// Guardar token
$tokenPath = __DIR__ . '/google/token.json';
file_put_contents($tokenPath, json_encode($token));

echo "Token guardado correctamente en:\n$tokenPath\n\n";
echo "Ya puedes cerrar esta ventana y volver al sistema UIAT NORTE.\n";