<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentoRmlRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function personaById(int $personaId): ?array
    {
        $sql = "SELECT id, num_doc, CONCAT(apellido_paterno,' ',apellido_materno,', ',nombres) AS nom FROM personas WHERE id=? LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$personaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM documento_rml WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findWithPersona(int $id): ?array
    {
        $sql = "SELECT r.*, CONCAT(pe.apellido_paterno,' ',pe.apellido_materno,', ',pe.nombres) AS per_nom, pe.num_doc
                  FROM documento_rml r
             LEFT JOIN personas pe ON pe.id=r.persona_id
                 WHERE r.id=? LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO documento_rml (persona_id, numero, fecha, incapacidad_medico, atencion_facultativo, observaciones, creado_en) VALUES (?,?,?,?,?,?,NOW())';
        $st = $this->pdo->prepare($sql);
        $st->execute($payload);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sql = 'UPDATE documento_rml SET numero=?, fecha=?, incapacidad_medico=?, atencion_facultativo=?, observaciones=? WHERE id=?';
        $st = $this->pdo->prepare($sql);
        $st->execute([...$payload, $id]);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM documento_rml WHERE id=? LIMIT 1');
        $st->execute([$id]);
    }

    public function search(int $personaId): array
    {
        $sql = "SELECT r.*, CONCAT(pe.apellido_paterno,' ',pe.apellido_materno,', ',pe.nombres) AS per_nom, pe.num_doc
                  FROM documento_rml r
             LEFT JOIN personas pe ON pe.id = r.persona_id";
        $params = [];
        if ($personaId > 0) {
            $sql .= ' WHERE r.persona_id = ?';
            $params[] = $personaId;
        }
        $sql .= ' ORDER BY r.fecha DESC, r.id DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
