<?php
declare(strict_types=1);

namespace App\Support;

use App\Repositories\DiligenciaPendienteRepository;
use App\Services\DiligenciaPendienteService;
use InvalidArgumentException;
use PDO;

final class DiligenciaDocumentLink
{
    public static function optionsForAction(string $action): array
    {
        return match ($action) {
            'Solicitar', 'Remitir' => ['oficio' => 'Oficio'],
            'Citar' => ['citacion' => 'Citación'],
            'Realizar' => ['actas' => 'Actas'],
            default => [],
        };
    }

    public static function validate(PDO $pdo, int $id, int $accidenteId): void
    {
        if ($id <= 0) return;
        $repository = new DiligenciaPendienteRepository($pdo);
        $row = $repository->find($id);
        if (!$row || $accidenteId <= 0 || (int) $row['accidente_id'] !== $accidenteId) {
            throw new InvalidArgumentException('La diligencia no pertenece a este expediente.');
        }
    }

    public static function attach(PDO $pdo, int $id, int $accidenteId, string $kind, int $documentId): void
    {
        if (!in_array($kind, ['oficio', 'citacion'], true) || $id <= 0 || $documentId <= 0) {
            throw new InvalidArgumentException('Vínculo de documento inválido.');
        }
        self::validate($pdo, $id, $accidenteId);
        $table = $kind === 'oficio' ? 'oficios_activos' : 'citacion_activos';
        $check = $pdo->prepare("SELECT id FROM {$table} WHERE id = ? AND accidente_id = ?");
        $check->execute([$documentId, $accidenteId]);
        if (!$check->fetchColumn()) {
            throw new InvalidArgumentException('El documento no pertenece a este expediente.');
        }
        $service = new DiligenciaPendienteService(new DiligenciaPendienteRepository($pdo));
        $detail = $service->detalle($id);
        $data = $service->defaultData($detail['row']);
        $data['citacion_id'] = $detail['citacion_ids'];
        if ($kind === 'oficio') {
            $data['oficio_id'] = $documentId;
        } else {
            $data['citacion_id'][] = $documentId;
        }
        $service->actualizar($id, $data);
    }

    public static function finish(int $id, bool $embed): never
    {
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>window.parent.postMessage({type:"diligencia.saved"},location.origin);</script>';
        } else {
            header('Location: diligenciapendiente_ver.php?id=' . $id);
        }
        exit;
    }
}
