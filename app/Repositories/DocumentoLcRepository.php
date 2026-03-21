<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentoLcRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function personaById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT id, num_doc, apellido_paterno, apellido_materno, nombres FROM personas WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function personaByDocument(string $number): ?array
    {
        $st = $this->pdo->prepare('SELECT id, num_doc, apellido_paterno, apellido_materno, nombres FROM personas WHERE num_doc = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$number]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM documento_lc WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listByPersona(int $personaId): array
    {
        $st = $this->pdo->prepare('SELECT id, persona_id, clase, categoria, numero, expedido_por, vigente_desde, vigente_hasta, restricciones FROM documento_lc WHERE persona_id = ? ORDER BY id DESC');
        $st->execute([$personaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existsDuplicate(int $personaId, string $numero, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM documento_lc WHERE persona_id = ? AND numero = ?';
        $params = [$personaId, $numero];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    public function create(array $payload): int
    {
        $st = $this->pdo->prepare('INSERT INTO documento_lc (persona_id, clase, categoria, numero, expedido_por, vigente_desde, vigente_hasta, restricciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([
            $payload['persona_id'],
            $payload['clase'],
            $payload['categoria'],
            $payload['numero'],
            $payload['expedido_por'],
            $payload['vigente_desde'],
            $payload['vigente_hasta'],
            $payload['restricciones'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $st = $this->pdo->prepare('UPDATE documento_lc SET clase = ?, categoria = ?, numero = ?, expedido_por = ?, vigente_desde = ?, vigente_hasta = ?, restricciones = ? WHERE id = ? LIMIT 1');
        $st->execute([
            $payload['clase'],
            $payload['categoria'],
            $payload['numero'],
            $payload['expedido_por'],
            $payload['vigente_desde'],
            $payload['vigente_hasta'],
            $payload['restricciones'],
            $id,
        ]);
    }

    public function delete(int $id, int $personaId): void
    {
        $st = $this->pdo->prepare('DELETE FROM documento_lc WHERE id = ? AND persona_id = ? LIMIT 1');
        $st->execute([$id, $personaId]);
    }
}
