<?php
declare(strict_types=1);

namespace App\Support;

use App\Database\Database;

final class CalendarAccess
{
    // La conexión Google existente pertenece al JEFE EMI identificado por este CIP.
    private const OWNER_CIP = '31486778';

    public static function ownsConnectedCalendar(): bool
    {
        if (Access::role() !== 'jefe_emi' || Access::id() <= 0) return false;
        $st = Database::connection()->prepare('SELECT 1 FROM usuarios WHERE id=? AND cip=? AND activo=1 LIMIT 1');
        $st->execute([Access::id(), self::OWNER_CIP]);
        return (bool) $st->fetchColumn();
    }
}
