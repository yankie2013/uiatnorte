<?php
declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use PDO;

final class DiligenciaActas
{
    public const TYPES = [
        'entrega' => ['label' => 'Entrega de vehículo', 'table' => 'actas_activos', 'form' => 'acta_entrega_vehiculo_form.php'],
        'visualizacion' => ['label' => 'Visualización de video', 'table' => 'actas_visualizacion_activos', 'form' => 'acta_visualizacion_form.php'],
        'recepcion' => ['label' => 'Recepción de video', 'table' => 'actas_recepcion_video', 'form' => 'acta_recepcion_video_form.php'],
    ];

    public static function options(PDO $pdo, int $caseId): array
    {
        $options = [];
        foreach (self::TYPES as $type => $config) {
            $query = $pdo->prepare("SELECT * FROM {$config['table']} WHERE accidente_id=? ORDER BY id DESC");
            $query->execute([$caseId]);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $json = json_decode((string) ($row['datos_json'] ?? ''), true) ?: [];
                $date = $row['fecha_entrega'] ?? $row['fecha_visualizacion'] ?? $json['fecha_acta'] ?? '';
                $key = $type . ':' . $row['id'];
                $options[$key] = [
                    'type' => $type,
                    'id' => (int) $row['id'],
                    'label' => $config['label'] . ' #' . $row['id'] . ($date !== '' ? ' · ' . $date : ''),
                    'form' => $config['form'],
                ];
            }
        }
        return $options;
    }

    public static function selected(PDO $pdo, int $diligenciaId): array
    {
        $query = $pdo->prepare('SELECT tipo, acta_id FROM diligencias_actas WHERE diligencia_id=?');
        $query->execute([$diligenciaId]);
        return array_map(static fn(array $row): string => $row['tipo'] . ':' . $row['acta_id'], $query->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function attach(PDO $pdo, int $id, int $caseId, string $type, int $actaId): void
    {
        DiligenciaDocumentLink::validate($pdo, $id, $caseId);
        Access::requireEdit($caseId);
        $key = $type . ':' . $actaId;
        if (!isset(self::options($pdo, $caseId)[$key])) {
            throw new InvalidArgumentException('El acta no pertenece a este expediente.');
        }
        $query = $pdo->prepare('INSERT INTO diligencias_actas (diligencia_id,tipo,acta_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE acta_id=VALUES(acta_id)');
        $query->execute([$id, $type, $actaId]);
    }

    public static function sync(PDO $pdo, int $id, int $caseId, array $keys): void
    {
        DiligenciaDocumentLink::validate($pdo, $id, $caseId);
        Access::requireEdit($caseId);
        $options = self::options($pdo, $caseId);
        if (array_diff($keys, array_keys($options))) {
            throw new InvalidArgumentException('Selecciona actas de este expediente.');
        }
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('DELETE FROM diligencias_actas WHERE diligencia_id=?');
            $query->execute([$id]);
            $insert = $pdo->prepare('INSERT INTO diligencias_actas (diligencia_id,tipo,acta_id) VALUES (?,?,?)');
            foreach (array_unique($keys) as $key) {
                $insert->execute([$id, $options[$key]['type'], $options[$key]['id']]);
            }
            if ($ownsTransaction) $pdo->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
