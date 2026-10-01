<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require __DIR__ . '/vendor/autoload.php';

if (!\App\Support\CalendarAccess::ownsConnectedCalendar()) {
    http_response_code(403);
    exit('Solo el propietario puede vincular este Google Calendar.');
}

$expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
unset($_SESSION['google_oauth_state']);
$receivedState = (string) ($_GET['state'] ?? '');
if ($expectedState === '' || !hash_equals($expectedState, $receivedState)) {
    http_response_code(400);
    exit('La autorización de Google venció. Iníciala de nuevo.');
}

$code = (string) ($_GET['code'] ?? '');
if ($code === '') {
    http_response_code(400);
    exit('Google no devolvió un código de autorización.');
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
$token = $client->fetchAccessTokenWithAuthCode($code);
if (!is_array($token) || isset($token['error'])) {
    http_response_code(502);
    exit('No se pudo completar la autorización de Google.');
}

$tokenPath = __DIR__ . '/google/token.json';
$previous = is_file($tokenPath) ? json_decode((string) file_get_contents($tokenPath), true) : null;
if (empty($token['refresh_token']) && is_array($previous) && !empty($previous['refresh_token'])) {
    $token['refresh_token'] = $previous['refresh_token'];
}
if (file_put_contents($tokenPath, json_encode($token), LOCK_EX) === false) {
    http_response_code(500);
    exit('No se pudo guardar la conexión de Google Calendar.');
}
header('Location: citacion_rapida.php');
exit;
