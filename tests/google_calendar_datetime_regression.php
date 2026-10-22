<?php
declare(strict_types=1);

require dirname(__DIR__) . '/google_calendar.php';

function checkCalendarRange(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$previousTimezone = date_default_timezone_get();
try {
    // El resultado debe ser el mismo aunque producción utilice otra zona horaria.
    date_default_timezone_set('Asia/Tokyo');
    $expected = ['start' => '2026-10-29T10:00:00-05:00', 'end' => '2026-10-29T11:00:00-05:00'];
    foreach (['10:00', '10:00:00'] as $hora) {
        checkCalendarRange(gc_rango_evento_citacion('2026-10-29', $hora, 60) === $expected, 'Normaliza la hora: ' . $hora);
    }
    $range = gc_rango_evento_citacion('2026-12-31', '23:30:15', 60);
    checkCalendarRange($range['end'] === '2027-01-01T00:30:15-05:00', 'Conserva segundos y cambia de año.');
    foreach ([['2026-02-30', '10:00', 60], ['2026-10-29', '24:00', 60], ['2026-10-29', '10:00:00:00', 60], ['2026-10-29', '10:00', 0]] as $input) {
        try {
            gc_rango_evento_citacion(...$input);
        } catch (InvalidArgumentException $error) {
            continue;
        }
        throw new RuntimeException('Debe rechazar un rango inválido: ' . json_encode($input));
    }
    echo "PASS: horas de formulario y MySQL, zona horaria, cambio de año y valores inválidos.\n";
} finally {
    date_default_timezone_set($previousTimezone);
}
