<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DiligenciaPendienteRepository
{
    private array $columnCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function tipoOptions(): array
    {
        $sql = "SELECT id, nombre, LEFT(COALESCE(descripcion,''), 180) AS descripcion
                FROM tipo_diligencia
                ORDER BY nombre ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function tipoNombreById(int $id): ?string
    {
        $st = $this->pdo->prepare('SELECT nombre FROM tipo_diligencia WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $value = $st->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM diligencias_pendientes WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $payload, array $citacionIds): int
    {
        $sql = "INSERT INTO diligencias_pendientes
            (accidente_id, tipo_diligencia_id, tipo_diligencia, contenido, estado, oficio_id, citacion_id, documento_realizado, documentos_recibidos, creado_en, actualizado_en)
            VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['accidente_id'],
            $payload['tipo_diligencia_id'],
            $payload['tipo_diligencia'],
            $payload['contenido'],
            $payload['estado'],
            $payload['oficio_id'],
            $payload['citacion_id'],
            $payload['documento_realizado'],
            $payload['documentos_recibidos'],
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->syncCitacionIds($id, $citacionIds);
        return $id;
    }

    public function update(int $id, array $payload, array $citacionIds): void
    {
        $sql = "UPDATE diligencias_pendientes
                   SET tipo_diligencia_id = ?,
                       tipo_diligencia = ?,
                       contenido = ?,
                       estado = ?,
                       oficio_id = ?,
                       citacion_id = ?,
                       documento_realizado = ?,
                       documentos_recibidos = ?,
                       actualizado_en = NOW()
                 WHERE id = ?
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['tipo_diligencia_id'],
            $payload['tipo_diligencia'],
            $payload['contenido'],
            $payload['estado'],
            $payload['oficio_id'],
            $payload['citacion_id'],
            $payload['documento_realizado'],
            $payload['documentos_recibidos'],
            $id,
        ]);

        $this->syncCitacionIds($id, $citacionIds);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM diligencias_pendientes WHERE id = ? LIMIT 1');
        $st->execute([$id]);
    }

    public function updateEstado(int $id, string $estado): void
    {
        $sql = 'UPDATE diligencias_pendientes SET estado = ?';
        if ($this->columnExists('diligencias_pendientes', 'actualizado_en')) {
            $sql .= ', actualizado_en = NOW()';
        }
        $sql .= ' WHERE id = ? LIMIT 1';

        $st = $this->pdo->prepare($sql);
        $st->execute([$estado, $id]);
    }

    public function search(array $filters, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['estado'])) {
            $where[] = 'dp.estado = ?';
            $params[] = $filters['estado'];
        }

        if (!empty($filters['tipo'])) {
            $where[] = 'dp.tipo_diligencia_id = ?';
            $params[] = (int) $filters['tipo'];
        }

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $pieces = ['dp.contenido LIKE ?', 'td.nombre LIKE ?'];
            $params[] = $like;
            $params[] = $like;

            if ($this->columnExists('diligencias_pendientes', 'documento_realizado')) {
                $pieces[] = 'dp.documento_realizado LIKE ?';
                $params[] = $like;
            }
            if ($this->columnExists('diligencias_pendientes', 'documentos_recibidos')) {
                $pieces[] = 'dp.documentos_recibidos LIKE ?';
                $params[] = $like;
            }

            $where[] = '(' . implode(' OR ', $pieces) . ')';
        }

        if (!empty($filters['accidente_id']) && is_numeric((string) $filters['accidente_id']) && (int) $filters['accidente_id'] > 0) {
            $where[] = 'dp.accidente_id = ?';
            $params[] = (int) $filters['accidente_id'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
                     FROM diligencias_pendientes dp
                     LEFT JOIN tipo_diligencia td ON td.id = dp.tipo_diligencia_id
                     WHERE {$whereSql}";
        $countSt = $this->pdo->prepare($countSql);
        $countSt->execute($params);
        $total = (int) $countSt->fetchColumn();

        $sql = "SELECT dp.*, td.nombre AS tipo_nombre, td.descripcion AS tipo_descripcion
                FROM diligencias_pendientes dp
                LEFT JOIN tipo_diligencia td ON td.id = dp.tipo_diligencia_id
                WHERE {$whereSql}
                ORDER BY dp.creado_en DESC, dp.id DESC
                LIMIT ? OFFSET ?";
        $st = $this->pdo->prepare($sql);
        $bindIndex = 1;
        foreach ($params as $param) {
            $st->bindValue($bindIndex++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
        $st->bindValue($bindIndex, max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
        $st->execute();

        return [
            'rows' => $st->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    public function oficiosByAccidente(?int $accidenteId): array
    {
        $sql = 'SELECT id, numero, anio, motivo, referencia_texto, fecha_emision FROM oficios';
        $params = [];

        if ($accidenteId && $this->columnExists('oficios', 'accidente_id')) {
            $sql .= ' WHERE accidente_id = ?';
            $params[] = $accidenteId;
        }

        $sql .= ' ORDER BY id DESC LIMIT 500';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function citacionesByAccidente(?int $accidenteId): array
    {
        $columns = $this->tableColumns('citacion');
        if ($columns === []) {
            return [];
        }

        $select = ['id'];
        if (in_array('numero', $columns, true)) {
            $select[] = 'numero';
        }

        $textColumn = null;
        foreach (['resumen', 'asunto', 'descripcion', 'detalle', 'observaciones', 'motivo'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $textColumn = $candidate;
                break;
            }
        }
        if ($textColumn !== null) {
            $select[] = "{$textColumn} AS texto";
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM citacion';
        $params = [];
        if ($accidenteId && in_array('accidente_id', $columns, true)) {
            $sql .= ' WHERE accidente_id = ?';
            $params[] = $accidenteId;
        }

        $sql .= ' ORDER BY id DESC LIMIT 800';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function oficioLabels(array $ids): array
    {
        return $this->buildLabelsForTable('oficios', $ids);
    }

    public function citacionLabels(array $ids): array
    {
        return $this->buildLabelsForTable('citacion', $ids);
    }

    public function columnExists(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    private function syncCitacionIds(int $id, array $citacionIds): void
    {
        $citacionIds = array_values(array_unique(array_filter(array_map('intval', $citacionIds), static fn (int $value): bool => $value > 0)));

        if ($citacionIds !== [] && count($citacionIds) > 1 && !$this->columnExists('diligencias_pendientes', 'citacion_ids')) {
            $this->ensureCitacionIdsColumn();
            unset($this->columnCache['diligencias_pendientes']);
        }

        if (!$this->columnExists('diligencias_pendientes', 'citacion_ids')) {
            return;
        }

        $json = $citacionIds === [] ? null : json_encode($citacionIds, JSON_UNESCAPED_UNICODE);
        $st = $this->pdo->prepare('UPDATE diligencias_pendientes SET citacion_ids = ? WHERE id = ? LIMIT 1');
        $st->execute([$json, $id]);
    }

    private function ensureCitacionIdsColumn(): void
    {
        try {
            $this->pdo->exec('ALTER TABLE diligencias_pendientes ADD COLUMN citacion_ids JSON NULL AFTER citacion_id');
        } catch (\Throwable) {
        }
    }

    private function buildLabelsForTable(string $table, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $value): bool => $value > 0)));
        if ($ids === []) {
            return [];
        }

        $columns = $this->tableColumns($table);
        if ($columns === []) {
            return [];
        }

        $select = ['id'];
        $numberColumn = null;
        foreach (['numero', 'numero_oficio', 'num', 'nro'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $numberColumn = $candidate;
                break;
            }
        }
        if ($numberColumn !== null) {
            $select[] = "{$numberColumn} AS numero";
        }

        $hasAnio = in_array('anio', $columns, true);
        if ($hasAnio) {
            $select[] = 'anio';
        }

        $textColumn = null;
        foreach (['motivo', 'referencia_texto', 'resumen', 'asunto', 'descripcion', 'detalle', 'observaciones'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $textColumn = $candidate;
                break;
            }
        }
        if ($textColumn !== null) {
            $select[] = "{$textColumn} AS texto";
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT ' . implode(', ', $select) . " FROM `{$table}` WHERE id IN ({$placeholders})";
        $st = $this->pdo->prepare($sql);
        $st->execute($ids);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $label = '';
            if (!empty($row['numero'])) {
                $label = (string) $row['numero'];
            }
            if ($hasAnio && !empty($row['anio'])) {
                $label = trim($label . '/' . $row['anio'], '/');
            }
            if (!empty($row['texto'])) {
                $label .= ($label !== '' ? ' - ' : '') . mb_strimwidth((string) $row['texto'], 0, 120, '...');
            }
            $items[(int) $row['id']] = $label !== '' ? $label : ($table . ' #' . $row['id']);
        }

        return $items;
    }

    private function tableColumns(string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }

        $st = $this->pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        $this->columnCache[$table] = $st->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
        return $this->columnCache[$table];
    }
}
