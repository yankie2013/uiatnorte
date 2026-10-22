<?php
// google_calendar.php
require __DIR__ . '/vendor/autoload.php';

const GCAL_CITACIONES_ID = '7ntbm9hablm4jm0lbi1sv2o7a4@group.calendar.google.com';

function gc_get_client() {
    if (!\App\Support\CalendarAccess::ownsConnectedCalendar()) {
        throw new RuntimeException('Este Google Calendar pertenece a otro usuario.');
    }
    $credPath  = __DIR__ . '/google/credentials.json';
    $tokenPath = __DIR__ . '/google/token.json';

    if (!file_exists($credPath)) {
        throw new RuntimeException("No se encontró credentials.json en $credPath");
    }
    if (!file_exists($tokenPath)) {
        throw new RuntimeException("No se encontró token.json en $tokenPath");
    }

    $client = new Google_Client();
    $client->setAuthConfig($credPath);
    $client->setAccessType('offline');
    $client->setScopes(Google_Service_Calendar::CALENDAR);

    $accessToken = json_decode(file_get_contents($tokenPath), true);
    $client->setAccessToken($accessToken);

    // Refrescar token si caduca
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $refreshed = $client->getAccessToken();
            if (empty($refreshed['refresh_token']) && !empty($accessToken['refresh_token'])) {
                $refreshed['refresh_token'] = $accessToken['refresh_token'];
            }
            file_put_contents($tokenPath, json_encode($refreshed), LOCK_EX);
        } else {
            throw new RuntimeException('Token de Google expirado y sin refresh token.');
        }
    }

    return $client;
}

/** Construye un rango RFC3339 a partir de horas del formulario o de MySQL. */
function gc_rango_evento_citacion(string $fecha, string $hora, int $duracion): array
{
    $fecha = trim($fecha);
    $hora = trim($hora);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha)
        || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/D', $hora)) {
        throw new InvalidArgumentException('Fecha u hora inválida para el evento.');
    }
    if ($duracion <= 0) {
        throw new InvalidArgumentException('La duración del evento debe ser mayor a cero.');
    }
    $format = strlen($hora) === 5 ? '!Y-m-d H:i' : '!Y-m-d H:i:s';
    $start = DateTimeImmutable::createFromFormat($format, $fecha . ' ' . $hora, new DateTimeZone('America/Lima'));
    $errors = DateTimeImmutable::getLastErrors();
    if ($start === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new InvalidArgumentException('Fecha u hora inválida para el evento.');
    }
    return [
        'start' => $start->format(DateTimeInterface::RFC3339),
        'end' => $start->modify('+' . $duracion . ' minutes')->format(DateTimeInterface::RFC3339),
    ];
}

/**
 * Crea un evento de citación en Google Calendar.
 *
 * Devuelve:
 * - event_id
 * - html_link
 */
function gc_crear_evento_citacion_detalle(array $opts): array {
    $fecha       = $opts['fecha'] ?? null;
    $hora        = $opts['hora'] ?? null;
    $titulo      = $opts['titulo'] ?? 'Citación';
    $descripcion = $opts['descripcion'] ?? '';
    $lugar       = $opts['lugar'] ?? '';
    $duracion    = isset($opts['duracion']) ? (int)$opts['duracion'] : 60;

    if (!$fecha || !$hora) {
        throw new InvalidArgumentException('Faltan fecha u hora para el evento.');
    }

    $rango = gc_rango_evento_citacion((string) $fecha, (string) $hora, $duracion);

    $client  = gc_get_client();
    $service = new Google_Service_Calendar($client);

    $event = new Google_Service_Calendar_Event([
        'summary'     => $titulo,
        'location'    => $lugar,
        'description' => $descripcion,
        'start' => [
            'dateTime' => $rango['start'],
            'timeZone' => 'America/Lima',
        ],
        'end' => [
            'dateTime' => $rango['end'],
            'timeZone' => 'America/Lima',
        ],
    ]);

    $calendarId   = GCAL_CITACIONES_ID;
    $createdEvent = $service->events->insert($calendarId, $event);

    return [
        'event_id' => (string) $createdEvent->getId(),
        'html_link' => (string) $createdEvent->getHtmlLink(),
    ];
}

function gc_crear_evento_citacion(array $opts) {
    $detail = gc_crear_evento_citacion_detalle($opts);
    return $detail['html_link'] ?? null;
}

function gc_eliminar_evento_citacion(string $eventId): void
{
    $eventId = trim($eventId);
    if ($eventId === '') {
        throw new InvalidArgumentException('Falta el identificador del evento de Google Calendar.');
    }

    $client = gc_get_client();
    $service = new Google_Service_Calendar($client);
    $service->events->delete(GCAL_CITACIONES_ID, $eventId);
}
