<?php
// google_calendar.php
require __DIR__ . '/vendor/autoload.php';

const GCAL_CITACIONES_ID = '7ntbm9hablm4jm0lbi1sv2o7a4@group.calendar.google.com';

function gc_get_client() {
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
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        } else {
            throw new RuntimeException('Token de Google expirado y sin refresh token.');
        }
    }

    return $client;
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

    // Construir inicio y fin
    // Fecha: Y-m-d, Hora: H:i → 2025-11-15T09:00:00
    $startDateTime = $fecha . 'T' . $hora . ':00';
    $startTs = strtotime($fecha . ' ' . $hora);
    $endTs   = $startTs + $duracion * 60;
    $endDateTime = date('Y-m-d\TH:i:s', $endTs);

    $client  = gc_get_client();
    $service = new Google_Service_Calendar($client);

    $event = new Google_Service_Calendar_Event([
        'summary'     => $titulo,
        'location'    => $lugar,
        'description' => $descripcion,
        'start' => [
            'dateTime' => $startDateTime,
            'timeZone' => 'America/Lima',
        ],
        'end' => [
            'dateTime' => $endDateTime,
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
