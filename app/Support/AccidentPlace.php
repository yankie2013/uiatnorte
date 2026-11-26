<?php
declare(strict_types=1);
namespace App\Support;

use App\Database\Database;
use PDO;

/** Presentation-only accident location; never used to replace the stored address. */
final class AccidentPlace
{
    private static array $districts = [];

    public static function format(?string $place, ?string $district): string
    {
        $place = trim((string) $place);
        $district = trim((string) $district);
        if ($district === '') return $place;
        if ($place === '') return 'Distrito de ' . $district;
        if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($district, '/') . '(?![\p{L}\p{N}])/iu', $place)) return $place;
        return $place . ', distrito de ' . $district;
    }

    public static function fromRecord(array $record, string $key = 'lugar', ?int $caseId = null): string
    {
        $district = '';
        foreach (['accidente_distrito', 'acc_distrito', 'distrito_ubicacion', 'distrito_nombre', 'distrito_nom', 'dist_nombre', 'distrito'] as $field) {
            $value = $record[$field] ?? '';
            if (is_string($value) && preg_match('/\p{L}/u', $value)) {$district = $value; break;}
        }
        if ($district === '') {
            $caseId ??= (int) ($record['accidente_id'] ?? $record['acc_id'] ?? 0);
            if ($caseId > 0) {
                if (!array_key_exists($caseId, self::$districts)) {
                    $stmt = Database::connection()->prepare('SELECT d.nombre FROM accidentes_activos a LEFT JOIN ubigeo_distrito d ON d.cod_dep=a.cod_dep AND d.cod_prov=a.cod_prov AND d.cod_dist=a.cod_dist WHERE a.id=?');
                    $stmt->execute([$caseId]);
                    self::$districts[$caseId] = (string) ($stmt->fetchColumn() ?: '');
                }
                $district = self::$districts[$caseId];
            } elseif (!empty($record['cod_dep']) && !empty($record['cod_prov']) && !empty($record['cod_dist'])) {
                $cacheKey = implode('-', [$record['cod_dep'], $record['cod_prov'], $record['cod_dist']]);
                if (!array_key_exists($cacheKey, self::$districts)) {
                    $stmt = Database::connection()->prepare('SELECT nombre FROM ubigeo_distrito WHERE cod_dep=? AND cod_prov=? AND cod_dist=?');
                    $stmt->execute([$record['cod_dep'], $record['cod_prov'], $record['cod_dist']]);
                    self::$districts[$cacheKey] = (string) ($stmt->fetchColumn() ?: '');
                }
                $district = self::$districts[$cacheKey];
            }
        }
        return self::format((string) ($record[$key] ?? ''), $district);
    }
}
