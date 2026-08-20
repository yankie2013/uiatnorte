<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentoRecibidoRepository
{
    private array $columnExistsCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function accidentes(): array
    {
        $sidpolColumn = $this->columnExists('accidentes', 'registro_sidpol') ? 'registro_sidpol' : ($this->columnExists('accidentes', 'sidpol') ? 'sidpol' : null);
        $parts = ['id'];
        if ($sidpolColumn) {
            $parts[] = "`{$sidpolColumn}` AS sidpol";
        }
        if ($this->columnExists('accidentes', 'lugar')) {
            $parts[] = 'lugar';
        }
        $sql = 'SELECT ' . implode(',', $parts) . ' FROM accidentes ORDER BY id DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function oficiosByAccidente(?int $accidenteId): array
    {
        $hasContenido = $this->columnExists('oficios', 'contenido');
        $hasEntidadDestino = $this->columnExists('oficios', 'entidad_id_destino') && $this->tableExists('oficio_entidad');
        $cols = 'o.id, o.numero, o.anio, o.asunto_id, o.motivo, o.referencia_texto';
        if ($hasContenido) {
            $cols .= ', o.contenido';
        }
        $cols .= $hasEntidadDestino ? ", COALESCE(e.nombre, '') AS entidad_destino" : ", '' AS entidad_destino";
        $join = $hasEntidadDestino ? ' LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino' : '';

        if ($accidenteId && $this->columnExists('oficios', 'accidente_id')) {
            $st = $this->pdo->prepare("SELECT {$cols} FROM oficios o{$join} WHERE o.accidente_id = ? ORDER BY o.id DESC");
            $st->execute([$accidenteId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if ($rows !== []) {
                return $rows;
            }
        }

        return $this->pdo->query("SELECT {$cols} FROM oficios o{$join} ORDER BY o.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function asuntosByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare("SELECT id, nombre, detalle FROM oficio_asunto WHERE id IN ({$placeholders})");
        $st->execute($ids);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach ($rows as $row) {
            $items[(int) $row['id']] = $row;
        }
        return $items;
    }

    public function distinctTipos(): array
    {
        return $this->pdo->query('SELECT DISTINCT tipo_documento FROM documentos_recibidos ORDER BY tipo_documento')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function distinctCategorias(): array
    {
        $queries = [];
        if ($this->columnExists('documentos_recibidos', 'categoria')) {
            $queries[] = "SELECT categoria COLLATE utf8mb4_unicode_ci AS categoria FROM documentos_recibidos WHERE categoria IS NOT NULL AND categoria <> ''";
        }
        if ($this->columnExists('oficios', 'categoria')) {
            $queries[] = "SELECT categoria COLLATE utf8mb4_unicode_ci AS categoria FROM oficios WHERE categoria IS NOT NULL AND categoria <> ''";
        }
        if ($queries === []) {
            return [];
        }

        return $this->pdo->query('SELECT DISTINCT categoria FROM (' . implode(' UNION ALL ', $queries) . ') categorias_compartidas ORDER BY categoria')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function distinctEntidades(): array
    {
        return $this->distinctColumnValues('entidad_persona');
    }

    public function distinctNumerosDocumento(): array
    {
        return $this->distinctColumnValues('numero_documento');
    }

    public function search(array $filters): array
    {
        $fechaRecepcionExpr = $this->resolvedDateExpression('dr', 'fecha_recepcion', 'fecha');
        $fechaDocumentoExpr = $this->resolvedDateExpression('dr', 'fecha_documento', 'fecha');

        $sql = "SELECT dr.*,
                       {$fechaRecepcionExpr} AS fecha_recepcion_resuelta,
                       {$fechaDocumentoExpr} AS fecha_documento_resuelta,
                       a.lugar AS accidente_lugar,
                       o.numero AS oficio_numero,
                       o.anio AS oficio_anio";
        $sidpolColumn = $this->columnExists('accidentes', 'registro_sidpol') ? 'registro_sidpol' : ($this->columnExists('accidentes', 'sidpol') ? 'sidpol' : null);
        if ($sidpolColumn) {
            $sql .= ", a.`{$sidpolColumn}` AS accidente_sidpol";
        } else {
            $sql .= ', NULL AS accidente_sidpol';
        }
        $sql .= " FROM documentos_recibidos dr
                  LEFT JOIN accidentes a ON a.id = dr.accidente_id
                  LEFT JOIN oficios o ON o.id = dr.referencia_oficio_id";

        $where = [];
        $params = [];
        if (!empty($filters['accidente_id'])) {
            $where[] = 'dr.accidente_id = ?';
            $params[] = (int) $filters['accidente_id'];
        }
        if (!empty($filters['tipo_documento'])) {
            $where[] = 'dr.tipo_documento LIKE ?';
            $params[] = '%' . $filters['tipo_documento'] . '%';
        }
        if (!empty($filters['estado'])) {
            $where[] = 'dr.estado = ?';
            $params[] = $filters['estado'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(dr.asunto LIKE ? OR dr.entidad_persona LIKE ? OR dr.numero_documento LIKE ? OR dr.contenido LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY COALESCE({$fechaRecepcionExpr}, '9999-12-31') DESC, dr.id DESC";

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $fechaRecepcionExpr = $this->resolvedDateExpression('dr', 'fecha_recepcion', 'fecha');
        $fechaDocumentoExpr = $this->resolvedDateExpression('dr', 'fecha_documento', 'fecha');

        $sql = "SELECT dr.*,
                       {$fechaRecepcionExpr} AS fecha_recepcion_resuelta,
                       {$fechaDocumentoExpr} AS fecha_documento_resuelta
                  FROM documentos_recibidos dr
                 WHERE dr.id = ?
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['anexos'] = $this->anexosByDocumento($id);
        return $row;
    }

    public function create(array $payload): int
    {
        $this->pdo->beginTransaction();
        try {
            $data = $this->persistenceData($payload);
            $columns = array_keys($data);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $sql = 'INSERT INTO documentos_recibidos (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
            $st = $this->pdo->prepare($sql);
            $st->execute(array_values($data));
            $id = (int) $this->pdo->lastInsertId();
            $this->replaceAnexos($id, $payload['anexos'] ?? []);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function update(int $id, array $payload): void
    {
        $this->pdo->beginTransaction();
        try {
            $data = $this->persistenceData($payload);
            $sets = [];
            foreach (array_keys($data) as $column) {
                $sets[] = $column . '=?';
            }

            $sql = 'UPDATE documentos_recibidos SET ' . implode(', ', $sets) . ' WHERE id=? LIMIT 1';
            $st = $this->pdo->prepare($sql);
            $st->execute([...array_values($data), $id]);
            $this->replaceAnexos($id, $payload['anexos'] ?? []);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM documentos_recibidos WHERE id=? LIMIT 1');
        $st->execute([$id]);
    }

    private function persistenceData(array $payload): array
    {
        $data = [
            'accidente_id' => $payload['accidente_id'] ?? null,
            'asunto' => $payload['asunto'] ?? null,
            'entidad_persona' => $payload['entidad_persona'] ?? null,
            'tipo_documento' => $payload['tipo_documento'] ?? null,
            'numero_documento' => $payload['numero_documento'] ?? null,
        ];

        if ($this->columnExists('documentos_recibidos', 'categoria')) {
            $data['categoria'] = $payload['categoria'] ?? null;
        }

        if ($this->columnExists('documentos_recibidos', 'fecha_recepcion')) {
            $data['fecha_recepcion'] = $payload['fecha_recepcion'] ?? null;
        }
        if ($this->columnExists('documentos_recibidos', 'fecha_documento')) {
            $data['fecha_documento'] = $payload['fecha_documento'] ?? null;
        }
        if ($this->columnExists('documentos_recibidos', 'fecha')) {
            $data['fecha'] = $payload['fecha'] ?? null;
        }

        $data['contenido'] = $payload['contenido'] ?? null;
        $data['referencia_oficio_id'] = $payload['referencia_oficio_id'] ?? null;
        $data['estado'] = $payload['estado'] ?? null;

        return $data;
    }

    private function anexosByDocumento(int $documentoId): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, descripcion, orden
               FROM documentos_recibidos_anexos
              WHERE documento_recibido_id = ?
              ORDER BY orden, id'
        );
        $st->execute([$documentoId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function replaceAnexos(int $documentoId, array $anexos): void
    {
        $delete = $this->pdo->prepare('DELETE FROM documentos_recibidos_anexos WHERE documento_recibido_id = ?');
        $delete->execute([$documentoId]);

        if ($anexos === []) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO documentos_recibidos_anexos (documento_recibido_id, descripcion, orden)
             VALUES (?, ?, ?)'
        );
        foreach (array_values($anexos) as $index => $descripcion) {
            $insert->execute([$documentoId, $descripcion, $index + 1]);
        }
    }

    private function resolvedDateExpression(string $alias, string $preferredColumn, string $fallbackColumn): string
    {
        if ($this->columnExists('documentos_recibidos', $preferredColumn)) {
            return "{$alias}.{$preferredColumn}";
        }
        if ($this->columnExists('documentos_recibidos', $fallbackColumn)) {
            return "{$alias}.{$fallbackColumn}";
        }
        return 'NULL';
    }

    private function distinctColumnValues(string $column): array
    {
        $allowed = ['entidad_persona', 'numero_documento'];
        if (!in_array($column, $allowed, true) || !$this->columnExists('documentos_recibidos', $column)) {
            return [];
        }

        return $this->pdo->query("SELECT DISTINCT `{$column}` FROM documentos_recibidos WHERE `{$column}` IS NOT NULL AND `{$column}` <> '' ORDER BY `{$column}`")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    private function columnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$table, $column]);
        $this->columnExistsCache[$cacheKey] = (int) $st->fetchColumn() > 0;

        return $this->columnExistsCache[$cacheKey];
    }

    private function tableExists(string $table): bool
    {
        $cacheKey = $table . '.*';
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        $this->columnExistsCache[$cacheKey] = (int) $st->fetchColumn() > 0;

        return $this->columnExistsCache[$cacheKey];
    }
}
