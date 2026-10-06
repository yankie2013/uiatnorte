<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use App\Support\Access;

/** Read-only dashboard. Every measure counts accident records, never joined activities. */
final class DashboardRepository
{
    public function __construct(private PDO $pdo) {}

    public function years(bool $institutional = false): array
    {
        $scope = $institutional ? '1=1' : Access::workspacePredicate('a');
        return array_map('intval', $this->pdo->query(
            "SELECT DISTINCT YEAR(a.fecha_accidente) FROM accidentes_activos a WHERE a.fecha_accidente >= '1000-01-01' AND $scope ORDER BY 1 DESC"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    public function snapshot(?int $year, bool $institutional = false): array
    {
        $where = ' WHERE (' . ($institutional ? '1=1' : Access::workspacePredicate('a')) . ')';
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
        $archive = $read("SELECT
            COALESCE(SUM(EXISTS (SELECT 1 FROM expediente_transferencias t WHERE t.accidente_id=a.id AND t.tipo='archivo' AND t.estado='aceptada')),0) AS accepted,
            COALESCE(SUM(EXISTS (SELECT 1 FROM expediente_transferencias t WHERE t.accidente_id=a.id AND t.tipo='archivo' AND t.estado='pendiente')),0) AS pending
            FROM accidentes_activos a $where")[0];
        return ['counts' => $counts, 'total' => (int)$summary['total'], 'priority' => (int)$summary['priority'],
                'archive' => array_map('intval', $archive),
                'undated' => (int)$summary['undated'], 'first_date' => $summary['first_date'], 'last_date' => $summary['last_date'],
                'timeline' => $timeline, 'districts' => $districts, 'recent' => $recent];
    }

    /** Panorama institucional para Secretaría y Administración; no altera permisos de expedientes. */
    public function archiveSnapshot(?int $year): array
    {
        $where = ' WHERE 1=1';
        $params = [];
        if ($year !== null) {
            $where .= ' AND a.fecha_accidente >= ? AND a.fecha_accidente < ?';
            $params = ["$year-01-01 00:00:00", ($year + 1) . '-01-01 00:00:00'];
        }
        $archiveAccepted = "EXISTS (SELECT 1 FROM expediente_transferencias t WHERE t.accidente_id=a.id AND t.tipo='archivo' AND t.estado='aceptada')";
        $archivePending = "EXISTS (SELECT 1 FROM expediente_transferencias t WHERE t.accidente_id=a.id AND t.tipo='archivo' AND t.estado='pendiente')";
        $archivePendingMine = "EXISTS (SELECT 1 FROM expediente_transferencias t WHERE t.accidente_id=a.id AND t.tipo='archivo' AND t.estado='pendiente' AND t.destino_id=" . Access::id() . ')';
        $summary = $this->pdo->prepare("SELECT COUNT(*) total,
            COALESCE(SUM(NOT $archiveAccepted),0) vigentes,
            COALESCE(SUM(a.estado='Resuelto'),0) resueltos,
            COALESCE(SUM(a.estado IN ('Pendiente','Con diligencias')),0) pendientes,
            COALESCE(SUM($archiveAccepted),0) archivados,
            COALESCE(SUM($archivePending),0) por_aceptar,
            COALESCE(SUM($archivePendingMine),0) por_aceptar_mios
            FROM accidentes_activos a $where");
        $summary->execute($params);
        $counts = array_map('intval', $summary->fetch(PDO::FETCH_ASSOC) ?: []);

        $cases = $this->pdo->prepare("SELECT a.id,a.registro_sidpol,a.creado_en,a.estado,
            COALESCE(NULLIF(d.nombre,''),'Sin distrito') distrito
            FROM accidentes_activos a
            LEFT JOIN (SELECT cod_dep,cod_prov,cod_dist,MIN(nombre) nombre FROM ubigeo_distrito GROUP BY cod_dep,cod_prov,cod_dist) d
              ON d.cod_dep=a.cod_dep AND d.cod_prov=a.cod_prov AND d.cod_dist=a.cod_dist
            $where");
        $cases->execute($params);
        $caseRows = $cases->fetchAll(PDO::FETCH_ASSOC);
        $byId = array_column($caseRows, null, 'id');
        $events = [];
        foreach ($caseRows as $case) {
            if (empty($case['creado_en'])) continue;
            $events[] = ['id' => (int)$case['id'], 'sidpol' => $case['registro_sidpol'], 'district' => $case['distrito'],
                'kind' => 'Registrado', 'at' => $case['creado_en']];
        }
        if ($byId) {
            $audit = $this->pdo->query("SELECT accidente_id,accion,motivo,antes,despues,registrado_en
                FROM auditoria WHERE tabla='accidentes' AND accion IN ('UPDATE','cambiar_estado') ORDER BY id");
            $seen = [];
            foreach ($audit->fetchAll(PDO::FETCH_ASSOC) as $change) {
                $id = (int)$change['accidente_id'];
                if (!isset($byId[$id])) continue;
                $before = json_decode((string)($change['antes'] ?? ''), true);
                $after = json_decode((string)($change['despues'] ?? ''), true);
                $resolved = ($change['accion'] === 'cambiar_estado' && $change['motivo'] === 'Resuelto')
                    || (is_array($after) && ($after['estado'] ?? '') === 'Resuelto' && (!is_array($before) || ($before['estado'] ?? '') !== 'Resuelto'));
                if (!$resolved) continue;
                $key = $id . '|' . $change['registrado_en'];
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $events[] = ['id' => $id, 'sidpol' => $byId[$id]['registro_sidpol'], 'district' => $byId[$id]['distrito'],
                    'kind' => 'Resuelto', 'at' => $change['registrado_en']];
            }
            $archives = $this->pdo->query("SELECT accidente_id,resuelto_en FROM expediente_transferencias
                WHERE tipo='archivo' AND estado='aceptada' AND resuelto_en IS NOT NULL ORDER BY resuelto_en");
            foreach ($archives->fetchAll(PDO::FETCH_ASSOC) as $archive) {
                $id = (int)$archive['accidente_id'];
                if (!isset($byId[$id])) continue;
                $events[] = ['id' => $id, 'sidpol' => $byId[$id]['registro_sidpol'], 'district' => $byId[$id]['distrito'],
                    'kind' => 'Archivado', 'at' => $archive['resuelto_en']];
            }
        }
        usort($events, static fn(array $a, array $b): int => strcmp((string)$b['at'], (string)$a['at']) ?: ($b['id'] <=> $a['id']));
        $history = [];
        $knownResolved = [];
        foreach ($events as $event) {
            if ($event['kind'] === 'Resuelto') $knownResolved[$event['id']] = true;
            $period = substr((string)$event['at'], 0, 7);
            if (!preg_match('/^\\d{4}-\\d{2}$/D', $period)) continue;
            $history[$period] ??= ['Registrado' => 0, 'Resuelto' => 0, 'Archivado' => 0];
            $history[$period][$event['kind']]++;
        }
        ksort($history);
        $undatedResolved = 0;
        foreach ($caseRows as $case) {
            if ($case['estado'] === 'Resuelto' && !isset($knownResolved[(int)$case['id']])) $undatedResolved++;
        }
        return ['counts' => $counts, 'history' => $history, 'undated_resolved' => $undatedResolved,
            'events' => array_slice($events, 0, 12)];
    }
}
