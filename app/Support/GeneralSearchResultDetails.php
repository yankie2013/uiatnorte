<?php
declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use PDO;

/** Enrich only the already selected page of general search results. */
final class GeneralSearchResultDetails
{
    public static function load(PDO $pdo, array $caseIds): array
    {
        $caseIds = array_values(array_unique(array_filter(array_map('intval', $caseIds), static fn(int $id): bool => $id > 0)));
        if (count($caseIds) > 50) throw new InvalidArgumentException('La página supera los 50 expedientes.');
        $result = ['participants' => [], 'vehicles' => []];
        if (!$caseIds) return $result;
        $marks = implode(',', array_fill(0, count($caseIds), '?'));
        $statement = $pdo->prepare("SELECT iv.id, iv.accidente_id, iv.vehiculo_id, iv.orden_participacion, v.placa
            FROM involucrados_vehiculos iv
            JOIN vehiculos v ON v.id = iv.vehiculo_id
            WHERE iv.accidente_id IN ($marks) AND iv.eliminado_en IS NULL
            ORDER BY iv.accidente_id, iv.orden_participacion, iv.id");
        $statement->execute($caseIds);
        $vehicleMap = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $vehicle) {
            $case = (int) $vehicle['accidente_id'];
            $result['vehicles'][$case][] = $vehicle;
            $vehicleMap[$case][(int) $vehicle['vehiculo_id']][] = $vehicle;
        }
        $statement = $pdo->prepare("SELECT ip.id, ip.accidente_id, ip.vehiculo_id,
                p.nombres, p.apellido_paterno, p.apellido_materno, pp.Nombre AS rol
            FROM involucrados_personas ip
            JOIN personas p ON p.id = ip.persona_id
            LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id
            WHERE ip.accidente_id IN ($marks) AND ip.eliminado_en IS NULL
            ORDER BY ip.accidente_id, pp.Orden, ip.id");
        $statement->execute($caseIds);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $person) {
            $case = (int) $person['accidente_id'];
            $person['vehicles'] = $vehicleMap[$case][(int) ($person['vehiculo_id'] ?? 0)] ?? [];
            $result['participants'][$case][] = $person;
        }
        return $result;
    }
}
