<?php
declare(strict_types=1);
namespace App\Support;

final class CaseParticipantEmbed
{
    public static function active(): bool
    {
        return (string) ($_GET['case_embed'] ?? $_POST['case_embed'] ?? '') === '1';
    }

    public static function saved(): void
    {
        if (!self::active()) return;
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><p>Registro guardado.</p><script>parent.postMessage({type:"participant.saved"},location.origin)</script></html>';
        exit;
    }
}
