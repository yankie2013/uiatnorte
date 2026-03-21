<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentoDosajeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function personaById(int $personaId): ?array
    {
        $st = $this->pdo->prepare('SELECT id, num_doc, nombres, apellido_paterno, apellido_materno FROM personas WHERE id = ? LIMIT 1');
        $st->execute([$personaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT d.*, p.num_doc, p.nombres, p.apellido_paterno, p.apellido_materno
                  FROM documento_dosaje d
             LEFT JOIN personas p ON p.id = d.persona_id
                 WHERE d.id = ?
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $sql = "INSERT INTO documento_dosaje
            (persona_id, numero, numero_registro, fecha_extraccion,
             resultado_cualitativo, resultado_cuantitativo, leer_cuantitativo,
             observaciones, creado_en, actualizado_en)
            VALUES
            (:persona_id, :numero, :numero_registro, :fecha_extraccion,
             :resultado_cualitativo, :resultado_cuantitativo, :leer_cuantitativo,
             :observaciones, NOW(), NOW())";
        $st = $this->pdo->prepare($sql);
        $st->execute($payload);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $payload[':id'] = $id;
        $sql = "UPDATE documento_dosaje
                   SET numero = :numero,
                       numero_registro = :numero_registro,
                       fecha_extraccion = :fecha_extraccion,
                       resultado_cualitativo = :resultado_cualitativo,
                       resultado_cuantitativo = :resultado_cuantitativo,
                       leer_cuantitativo = :leer_cuantitativo,
                       observaciones = :observaciones,
                       actualizado_en = NOW()
                 WHERE id = :id
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute($payload);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM documento_dosaje WHERE id = ?');
        $st->execute([$id]);
    }

    public function search(string $q, int $personaId): array
    {
        $sql = "SELECT d.id, d.numero, d.fecha_extraccion, p.nombres, p.apellido_paterno, p.apellido_materno
                  FROM documento_dosaje d
                  JOIN personas p ON p.id = d.persona_id";
        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(d.numero LIKE ? OR p.nombres LIKE ? OR p.apellido_paterno LIKE ? OR p.apellido_materno LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }

        if ($personaId > 0) {
            $where[] = 'd.persona_id = ?';
            $params[] = $personaId;
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY d.id DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
