<?php
declare(strict_types=1);
namespace App\Support;

final class CitacionMotivo
{
    public static function texto(array $citacion): string
    {
        $motivo = trim((string)($citacion['motivo'] ?? ''));
        return $motivo !== '' ? $motivo : trim((string)($citacion['tipo_diligencia'] ?? ''));
    }
}
