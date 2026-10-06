<?php
namespace App\Support;

use App\Services\OficioService;

final class OficioSaveResponse
{
    public static function destination(OficioService $service, int $id, int $caseId, bool $gestion, string $action): string
    {
        $params = $gestion || $caseId <= 0 ? [] : ['accidente_id'=>$caseId];
        if ($action === 'download') {
            $url = $service->downloadUrlForOficio($id);
            if ($url !== '') {
                $token = bin2hex(random_bytes(16));
                $_SESSION['oficio_pending_downloads'][$token] = $url;
                $params['download_saved'] = $token;
            } else {
                $params['msg'] = 'Oficio guardado. Esta plantilla no tiene una descarga configurada.';
            }
        }
        return 'oficios_listar.php' . ($params ? '?' . http_build_query($params) : '');
    }

    public static function redirect(OficioService $service, int $id, int $caseId, bool $gestion, bool $embed, string $action): never
    {
        $url = self::destination($service, $id, $caseId, $gestion, $action);
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>window.top.location.href=' . json_encode($url, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
        } else {
            header('Location: ' . $url, true, 303);
        }
        exit;
    }
}
