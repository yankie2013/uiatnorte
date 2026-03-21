<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

$credPath = __DIR__ . '/google/credentials.json';
if (!file_exists($credPath)) {
    die("No se encontró credentials.json en: $credPath");
}

$client = new Google_Client();
$client->setAuthConfig($credPath);
$client->setRedirectUri('https://korkaystore.com/uiatnorte/google_oauth_callback.php');
$client->setScopes(Google_Service_Calendar::CALENDAR);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

// Genera la URL de autorización y redirige a Google
$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit;