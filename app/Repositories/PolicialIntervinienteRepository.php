<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PolicialIntervinienteRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function accidenteHeader(int $accidenteId): ?array
    {
        $st = $this->pdo->prepare('SELECT id, sidpol, registro_sidpol, lugar, fecha_accidente FROM accidentes WHERE id = ? LIMIT 1');
        $st->execute([$accidenteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function personaByDni(string $dni): ?array
    {
        $sql = "SELECT id, tipo_doc, num_doc, apellido_paterno, apellido_materno, nombres, fecha_nacimiento, domicilio, celular, email
                FROM personas
                WHERE tipo_doc='DNI' AND num_doc = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$dni]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function personaById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT id, tipo_doc, num_doc, apellido_paterno, apellido_materno, nombres, fecha_nacimiento, domicilio, celular, email FROM personas WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updatePersonaContact(int $id, ?string $celular, ?string $email): void
    {
        $st = $this->pdo->prepare('UPDATE personas SET celular = ?, email = ? WHERE id = ?');
        $st->execute([$celular, $email, $id]);
    }

    public function existsDuplicate(int $accidenteId, int $personaId, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM policial_interviniente WHERE accidente_id = ? AND persona_id = ?';
        $params = [$accidenteId, $personaId];
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
        $sql = 'INSERT INTO policial_interviniente (accidente_id, persona_id, grado_policial, cip, dependencia_policial, rol_funcion, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['accidente_id'],
            $payload['persona_id'],
            $payload['grado_policial'],
            $payload['cip'],
            $payload['dependencia_policial'],
            $payload['rol_funcion'],
            $payload['observaciones'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sql = 'UPDATE policial_interviniente SET persona_id = ?, grado_policial = ?, cip = ?, dependencia_policial = ?, rol_funcion = ?, observaciones = ? WHERE id = ? LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['persona_id'],
            $payload['grado_policial'],
            $payload['cip'],
            $payload['dependencia_policial'],
            $payload['rol_funcion'],
            $payload['observaciones'],
            $id,
        ]);
    }

    public function delete(int $id, int $accidenteId): void
    {
        $st = $this->pdo->prepare('DELETE FROM policial_interviniente WHERE id = ? AND accidente_id = ? LIMIT 1');
        $st->execute([$id, $accidenteId]);
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM policial_interviniente WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function detail(int $id): ?array
    {
        $sql = "SELECT pi.*, p.tipo_doc, p.num_doc, p.apellido_paterno, p.apellido_materno, p.nombres, p.fecha_nacimiento, p.domicilio, p.celular, p.email,
                       a.id AS accidente_id, a.sidpol, a.registro_sidpol, a.lugar, a.fecha_accidente
                FROM policial_interviniente pi
                JOIN personas p ON p.id = pi.persona_id
                JOIN accidentes a ON a.id = pi.accidente_id
                WHERE pi.id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listByAccidente(int $accidenteId): array
    {
        $sql = "SELECT pi.id, pi.accidente_id, pi.persona_id, pi.grado_policial, pi.cip, pi.dependencia_policial, pi.rol_funcion, pi.observaciones,
                       p.tipo_doc, p.num_doc, p.apellido_paterno, p.apellido_materno, p.nombres, p.celular, p.email
                FROM policial_interviniente pi
                JOIN personas p ON p.id = pi.persona_id
                WHERE pi.accidente_id = ?
                ORDER BY pi.id ASC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAll(): array
    {
        $sql = "SELECT pi.id, pi.accidente_id, pi.persona_id, pi.grado_policial, pi.cip, pi.dependencia_policial, pi.rol_funcion,
                       p.tipo_doc, p.num_doc, p.apellido_paterno, p.apellido_materno, p.nombres, p.celular, p.email,
                       a.sidpol, a.registro_sidpol, a.lugar, a.fecha_accidente
                FROM policial_interviniente pi
                JOIN personas p ON p.id = pi.persona_id
                JOIN accidentes a ON a.id = pi.accidente_id
                ORDER BY a.fecha_accidente DESC, pi.id DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
