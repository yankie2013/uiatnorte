<?php
declare(strict_types=1);
namespace App\Support;

final class CitacionWhatsApp
{
    public static function salutation(?\DateTimeImmutable $now = null): string
    {
        $now = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('America/Lima')))->setTimezone(new \DateTimeZone('America/Lima'));
        $minutes = (int) $now->format('H') * 60 + (int) $now->format('i');
        return $minutes >= 60 && $minutes < 720 ? 'Buenos días' : ($minutes >= 720 && $minutes <= 1080 ? 'Buenas tardes' : 'Buenas noches');
    }

    public static function message(string $greeting, array $accident, string $modality): string
    {
        $identity = preg_replace('/^(?:Hola|Buenos días|Buenas tardes|Buenas noches), le saluda\s*/u', '', $greeting);
        $context = 'accidente de tránsito';
        $modality = trim(str_replace('||', ', ', $modality));
        if ($modality !== '') $context .= ' (' . mb_strtolower($modality, 'UTF-8') . ')';
        if (!empty($accident['fecha_accidente']) && ($stamp = strtotime($accident['fecha_accidente'])) !== false) {
            $context .= ', ocurrido el *' . date('d/m/Y', $stamp) . ', a horas ' . date('H:i', $stamp) . '*';
        }
        if (!empty($accident['lugar'])) $context .= ', en ' . trim($accident['lugar']);
        if (!empty($accident['registro_sidpol'])) $context .= ', registrado en el sistema SIDPOL con el N.° *' . trim($accident['registro_sidpol']) . '*';
        return self::salutation() . ', le saluda el *' . $identity . "*, de la Unidad Especializada de Investigación de Accidentes de Tránsito – Lima Norte.\n"
            . 'Por medio del presente, se le remite la *citación policial* correspondiente a las diligencias de investigación relacionadas con el ' . $context . ".\n"
            . "Se le solicita revisar el documento adjunto, en el cual se precisa la fecha, hora y lugar de su concurrencia.\n"
            . '*Agradeceré confirmar la recepción del presente mensaje.*';
    }

    public static function link(string $phone, string $message): string
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if (strlen($phone) === 9) $phone = '51' . $phone;
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }
}
