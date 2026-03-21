<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentoRecibidoRepository
{
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
        $cols = 'id, numero, anio, asunto_id, motivo, referencia_texto';
        if ($hasContenido) {
            $cols .= ', contenido';
        }

        if ($accidenteId && $this->columnExists('oficios', 'accidente_id')) {
            $st = $this->pdo->prepare("SELECT {$cols} FROM oficios WHERE accidente_id = ? ORDER BY id DESC");
            $st->execute([$accidenteId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if ($rows !== []) {
                return $rows;
            }
        }

        return $this->pdo->query("SELECT {$cols} FROM oficios ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
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
        return $this->pdo->query("SELECT DISTINCT tipo_documento FROM documentos_recibidos ORDER BY tipo_documento")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function search(array $filters): array
    {
        $sql = "SELECT dr.*, a.lugar AS accidente_lugar, o.numero AS oficio_numero, o.anio AS oficio_anio";
        $sidpolColumn = $this->columnExists('accidentes', 'registro_sidpol') ? 'registro_sidpol' : ($this->columnExists('accidentes', 'sidpol') ? 'sidpol' : null);
        if ($sidpolColumn) {
            $sql .= ", a.`{$sidpolColumn}` AS accidente_sidpol";
        } else {
            $sql .= ", NULL AS accidente_sidpol";
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
        $sql .= ' ORDER BY dr.fecha DESC, dr.id DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM documentos_recibidos WHERE id = ? LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $sql = "INSERT INTO documentos_recibidos
            (accidente_id, asunto, entidad_persona, tipo_documento, numero_documento, fecha, contenido, referencia_oficio_id, estado)
            VALUES (?,?,?,?,?,?,?,?,?)";
        $st = $this->pdo->prepare($sql);
        $st->execute($payload);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sql = "UPDATE documentos_recibidos
                   SET accidente_id=?, asunto=?, entidad_persona=?, tipo_documento=?, numero_documento=?, fecha=?, contenido=?, referencia_oficio_id=?, estado=?
                 WHERE id=? LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([...$payload, $id]);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM documentos_recibidos WHERE id=? LIMIT 1');
        $st->execute([$id]);
    }

    private function columnExists(string $table, string $column): bool
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$table, $column]);
        return (int) $st->fetchColumn() > 0;
    }
}
