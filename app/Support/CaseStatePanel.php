<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

final class CaseStatePanel
{
    /** Render atomically: a failed state query must not truncate the case page. */
    public static function render(PDO $pdo, int $accidente_id): array
    {
        $level = ob_get_level();
        ob_start();
        try {
            require dirname(__DIR__) . '/Views/expedientes/estado.php';
            return ['html' => (string) ob_get_clean(), 'error' => null];
        } catch (Throwable $error) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            error_log('[case-state-panel] expediente=' . $accidente_id . ' ' . $error);
            return [
                'html' => '<p role="alert">No se pudo cargar Estado y colaboración. Sus acciones no están disponibles hasta corregir el error del servidor.</p>',
                'error' => $error->getMessage(),
            ];
        }
    }
}
