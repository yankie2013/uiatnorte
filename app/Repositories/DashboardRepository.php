<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use App\Support\Access;

/** Read-only dashboard. Every measure counts accident records, never joined activities. */
final class DashboardRepository
{
    public function __construct(private PDO $pdo) {}

    public function years(): array
    {
        return array_map('intval', $this->pdo->query(
            "SELECT DISTINCT YEAR(a.fecha_accidente) FROM accidentes_activos a WHERE a.fecha_accidente >= '1000-01-01' AND " . Access::workspacePredicate('a') . " ORDER BY 1 DESC"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    public function snapshot(?int $year): array
    {
        $where = ' WHERE (' . Access::workspacePredicate('a') . ')';
        if ($year !== null) $where .= ' AND a.fecha_accidente >= ? AND a.fecha_accidente < ?';
        $params = $year === null ? [] : ["$year-01-01 00:00:00", ($year + 1) . '-01-01 00:00:00'];
        $read = function (string $sql) use ($params): array {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        };
        // Same normalization as accidente_listar.php; unknown states remain visible.
        $states = $read("SELECT COALESCE(NULLIF(TRIM(a.estado), ''), 'Pendiente') AS label, COUNT(*) AS total
                        FROM accidentes_activos a $where GROUP BY label");
        $counts = ['Pendiente' => 0, 'Resuelto' => 0, 'Con diligencias' => 0, 'Desestimado' => 0];
        foreach ($states as $state) $counts[$state['label']] = (int) $state['total'];
        $summary = $read("SELECT COUNT(*) AS total, SUM(a.priority = 1 AND COALESCE(NULLIF(TRIM(a.estado), ''), 'Pendiente') IN ('Pendiente', 'Con diligencias')) AS priority,
                         SUM(a.fecha_accidente IS NULL OR a.fecha_accidente < '1000-01-01') AS undated,
                         MIN(CASE WHEN a.fecha_accidente >= '1000-01-01' THEN a.fecha_accidente END) AS first_date, MAX(a.fecha_accidente) AS last_date
                         FROM accidentes_activos a $where")[0];
        $bucket = $year === null ? 'YEAR(a.fecha_accidente)' : 'MONTH(a.fecha_accidente)';
        $dateGuard = $where . " AND a.fecha_accidente >= '1000-01-01'";
        $timeline = $read("SELECT $bucket AS period, COUNT(*) AS total FROM accidentes_activos a $dateGuard GROUP BY period ORDER BY period");
        $districts = $read("SELECT COALESCE(NULLIF(d.nombre, ''), 'Sin distrito') AS label, COUNT(*) AS total
                           FROM accidentes_activos a
                           LEFT JOIN (SELECT cod_dep, cod_prov, cod_dist, MIN(nombre) AS nombre FROM ubigeo_distrito GROUP BY cod_dep, cod_prov, cod_dist) d
                             ON d.cod_dep = a.cod_dep AND d.cod_prov = a.cod_prov AND d.cod_dist = a.cod_dist
                           $where GROUP BY label ORDER BY total DESC, label ASC");
        $recent = $read("SELECT a.id, a.registro_sidpol, a.fecha_accidente, a.creado_en,
                        COALESCE(NULLIF(TRIM(a.estado), ''), 'Pendiente') AS estado,
                        COALESCE(NULLIF(c.nombre, ''), 'Sin comisaría') AS comisaria
                        FROM accidentes_activos a LEFT JOIN comisarias c ON c.id = a.comisaria_id $where
                        ORDER BY a.creado_en DESC, a.id DESC LIMIT 5");
        return ['counts' => $counts, 'total' => (int)$summary['total'], 'priority' => (int)$summary['priority'],
                'undated' => (int)$summary['undated'], 'first_date' => $summary['first_date'], 'last_date' => $summary['last_date'],
                'timeline' => $timeline, 'districts' => $districts, 'recent' => $recent];
    }
}
