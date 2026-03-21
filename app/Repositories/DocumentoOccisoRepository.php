<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentoOccisoRepository
{
    private const FIELDS = [
        'fecha_levantamiento',
        'hora_levantamiento',
        'lugar_levantamiento',
        'posicion_cuerpo_levantamiento',
        'lesiones_levantamiento',
        'presuntivo_levantamiento',
        'legista_levantamiento',
        'cmp_legista',
        'observaciones_levantamiento',
        'numero_pericial',
        'fecha_pericial',
        'hora_pericial',
        'observaciones_pericial',
        'numero_protocolo',
        'fecha_protocolo',
        'hora_protocolo',
        'lesiones_protocolo',
        'presuntivo_protocolo',
        'dosaje_protocolo',
        'toxicologico_protocolo',
        'nosocomio_epicrisis',
        'numero_historia_epicrisis',
        'tratamiento_epicrisis',
        'hora_alta_epicrisis',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT o.*,
                       p.nombres, p.apellido_paterno, p.apellido_materno,
                       a.fecha_accidente, a.lugar AS lugar_accidente, a.registro_sidpol
                  FROM documento_occiso o
             LEFT JOIN personas p ON p.id=o.persona_id
             LEFT JOIN accidentes a ON a.id=o.accidente_id
                 WHERE o.id=? LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $personaId, int $accidenteId, array $payload): int
    {
        $columns = implode(',', array_merge(['persona_id', 'accidente_id'], self::FIELDS));
        $placeholders = implode(',', array_fill(0, count(self::FIELDS) + 2, '?'));
        $sql = "INSERT INTO documento_occiso ({$columns}) VALUES ({$placeholders})";
        $params = [$personaId, $accidenteId];
        foreach (self::FIELDS as $field) {
            $params[] = $payload[$field] ?? null;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sets = implode(', ', array_map(static fn(string $field): string => "{$field}=?", self::FIELDS));
        $sql = "UPDATE documento_occiso SET {$sets} WHERE id=? LIMIT 1";
        $params = [];
        foreach (self::FIELDS as $field) {
            $params[] = $payload[$field] ?? null;
        }
        $params[] = $id;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM documento_occiso WHERE id=? LIMIT 1');
        $st->execute([$id]);
    }

    public function list(int $personaId, int $accidenteId): array
    {
        $sql = "SELECT o.id, o.persona_id, o.accidente_id,
                       o.fecha_levantamiento, o.hora_levantamiento, o.lugar_levantamiento,
                       o.numero_protocolo, o.fecha_protocolo, o.hora_protocolo,
                       p.nombres, p.apellido_paterno, p.apellido_materno,
                       a.fecha_accidente, a.lugar AS lugar_accidente, a.registro_sidpol
                  FROM documento_occiso o
             LEFT JOIN personas p ON p.id=o.persona_id
             LEFT JOIN accidentes a ON a.id=o.accidente_id";
        $where = [];
        $params = [];
        if ($personaId > 0) {
            $where[] = 'o.persona_id=?';
            $params[] = $personaId;
        }
        if ($accidenteId > 0) {
            $where[] = 'o.accidente_id=?';
            $params[] = $accidenteId;
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY COALESCE(o.fecha_levantamiento, '9999-12-31') DESC, o.id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function personaOptions(): array
    {
        $columns = $this->columns('personas');
        $select = ['id'];
        foreach (['apellido_paterno', 'apellido_materno', 'nombres', 'num_doc'] as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = "`{$column}`";
            }
        }
        $sql = 'SELECT ' . implode(',', $select) . ' FROM personas ORDER BY id DESC';
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach ($rows as $row) {
            $label = trim(implode(' ', array_filter([
                $row['apellido_paterno'] ?? '',
                $row['apellido_materno'] ?? '',
                $row['nombres'] ?? '',
            ])));
            if (!empty($row['num_doc'])) {
                $label .= ' (' . $row['num_doc'] . ')';
            }
            if ($label === '') {
                $label = 'ID ' . $row['id'];
            }
            $items[] = ['id' => (int) $row['id'], 'etiqueta' => $label];
        }
        return $items;
    }

    public function accidenteOptions(): array
    {
        $columns = $this->columns('accidentes');
        $pieces = ['id'];
        foreach (['registro_sidpol', 'fecha_accidente', 'lugar'] as $column) {
            if (in_array($column, $columns, true)) {
                $pieces[] = "`{$column}`";
            }
        }
        $sql = 'SELECT ' . implode(',', $pieces) . ' FROM accidentes ORDER BY id DESC';
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach ($rows as $row) {
            $parts = [];
            if (!empty($row['registro_sidpol'])) {
                $parts[] = $row['registro_sidpol'];
            }
            if (!empty($row['fecha_accidente'])) {
                $parts[] = date('Y-m-d', strtotime((string) $row['fecha_accidente']));
            }
            if (!empty($row['lugar'])) {
                $parts[] = (string) $row['lugar'];
            }
            $items[] = [
                'id' => (int) $row['id'],
                'etiqueta' => $parts !== [] ? implode(' · ', $parts) : ('Accidente #' . $row['id']),
            ];
        }
        return $items;
    }

    private function columns(string $table): array
    {
        $rows = $this->pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $row): string => strtolower((string) $row['Field']), $rows);
    }
}
