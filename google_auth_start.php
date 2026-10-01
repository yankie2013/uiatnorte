<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require __DIR__ . '/vendor/autoload.php';

if (!\App\Support\CalendarAccess::ownsConnectedCalendar()) {
    http_response_code(403);
    exit('Solo el propietario puede vincular este Google Calendar.');
}

$credPath = __DIR__ . '/google/credentials.json';
if (!is_file($credPath)) {
    http_response_code(503);
    exit('Falta la configuración de Google Calendar.');
}

$client = new Google_Client();
$client->setAuthConfig($credPath);
$client->setRedirectUri('https://korkaystore.com/uiatnorte/google_oauth_callback.php');
$client->setScopes(Google_Service_Calendar::CALENDAR);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;
$client->setState($state);
header('Location: ' . $client->createAuthUrl());
exit;
