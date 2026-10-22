<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/app.php';
use App\Support\CitacionNotificacion as Notification;
function assertNotification(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$first = ['id' => 1, 'persona_id' => 10, 'fecha' => '2026-10-29', 'hora' => '10:00:00', 'motivo' => 'Visualización de video'];
$second = array_replace($first, ['id' => 2, 'persona_id' => 20]);
$group = [$first, $second];
$records = [1 => ['contenido_hash' => Notification::hash($first)]];
$own = Notification::forLawyer($group, 10);
assertNotification(count($own) === 1 && $own[0]['id'] === 1, 'Seleccionar la citación del representado.');
assertNotification(Notification::isNotified($own, $records), 'Mostrar check sin exigir notificación de otro participante.');
assertNotification(!Notification::isNotified(Notification::forLawyer($group, 20), $records), 'No marcar a otro representado como notificado.');
$changed = array_replace($first, ['fecha' => '2026-10-30']);
assertNotification(!Notification::isNotified([$changed], $records), 'La reprogramación requiere nuevo documento.');
assertNotification(count(Notification::forLawyer($group, 30)) === 2, 'Permitir notificar diligencias de otros participantes del expediente.');
assertNotification(!Notification::isNotified([], $records), 'Sin citaciones no hay notificación.');
echo "PASS: notificación por representado, múltiples participantes y reprogramación.\n";
