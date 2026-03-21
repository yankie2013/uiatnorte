<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class FamiliarFallecidoRepository
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

    public function fallecidosByAccidente(int $accidenteId): array
    {
        $sql = "SELECT ip.id AS inv_id,
                       ip.persona_id,
                       p.num_doc,
                       p.apellido_paterno,
                       p.apellido_materno,
                       p.nombres
                FROM involucrados_personas ip
                JOIN personas p ON p.id = ip.persona_id
                WHERE ip.accidente_id = ?
                  AND (
                    LOWER(COALESCE(ip.lesion, '')) LIKE '%falle%'
                    OR LOWER(COALESCE(ip.lesion, '')) = 'fallecido'
                    OR LOWER(COALESCE(ip.lesion, '')) = 'fallecida'
                  )
                ORDER BY p.apellido_paterno, p.apellido_materno, p.nombres";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function personaByDocument(string $tipo, string $doc): ?array
    {
        $st = $this->pdo->prepare('SELECT id, tipo_doc, num_doc, apellido_paterno, apellido_materno, nombres, fecha_nacimiento, domicilio, celular, email FROM personas WHERE tipo_doc = ? AND num_doc = ? LIMIT 1');
        $st->execute([$tipo, $doc]);
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

    public function listByAccidente(int $accidenteId): array
    {
        $sql = "SELECT ff.id,
                       ff.accidente_id,
                       ff.fallecido_inv_id,
                       ff.familiar_persona_id,
                       ff.parentesco,
                       ff.observaciones,
                       ip.persona_id AS fallecido_persona_id,
                       pf.num_doc AS dni_fall,
                       pf.apellido_paterno AS ap_fall,
                       pf.apellido_materno AS am_fall,
                       pf.nombres AS no_fall,
                       pr.id AS familiar_persona_id_real,
                       pr.tipo_doc AS tipo_doc_fam,
                       pr.num_doc AS dni_fam,
                       pr.apellido_paterno AS ap_fam,
                       pr.apellido_materno AS am_fam,
                       pr.nombres AS no_fam,
                       pr.celular AS cel_fam,
                       pr.email AS em_fam,
                       pr.domicilio AS dom_fam
                FROM familiar_fallecido ff
                JOIN involucrados_personas ip ON ip.id = ff.fallecido_inv_id
                JOIN personas pf ON pf.id = ip.persona_id
                JOIN personas pr ON pr.id = ff.familiar_persona_id
                WHERE ff.accidente_id = ?
                ORDER BY ff.id ASC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM familiar_fallecido WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function detail(int $id): ?array
    {
        $sql = "SELECT ff.id,
                       ff.accidente_id,
                       ff.fallecido_inv_id,
                       ff.familiar_persona_id,
                       ff.parentesco,
                       ff.observaciones,
                       a.sidpol,
                       a.registro_sidpol,
                       a.lugar,
                       a.fecha_accidente,
                       ip.persona_id AS fallecido_persona_id,
                       pf.tipo_doc AS tipo_doc_fall,
                       pf.num_doc AS dni_fall,
                       pf.apellido_paterno AS ap_fall,
                       pf.apellido_materno AS am_fall,
                       pf.nombres AS no_fall,
                       pr.id AS fam_id,
                       pr.tipo_doc AS tipo_doc_fam,
                       pr.num_doc AS dni_fam,
                       pr.apellido_paterno AS ap_fam,
                       pr.apellido_materno AS am_fam,
                       pr.nombres AS no_fam,
                       pr.fecha_nacimiento AS fecha_nacimiento_fam,
                       pr.domicilio AS dom_fam,
                       pr.celular AS cel_fam,
                       pr.email AS em_fam
                FROM familiar_fallecido ff
                JOIN accidentes a ON a.id = ff.accidente_id
                JOIN involucrados_personas ip ON ip.id = ff.fallecido_inv_id
                JOIN personas pf ON pf.id = ip.persona_id
                JOIN personas pr ON pr.id = ff.familiar_persona_id
                WHERE ff.id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existsDuplicate(int $accidenteId, int $fallecidoInvId, int $familiarPersonaId, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM familiar_fallecido WHERE accidente_id = ? AND fallecido_inv_id = ? AND familiar_persona_id = ?';
        $params = [$accidenteId, $fallecidoInvId, $familiarPersonaId];
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
        $sql = 'INSERT INTO familiar_fallecido (accidente_id, fallecido_inv_id, familiar_persona_id, parentesco, observaciones) VALUES (?, ?, ?, ?, ?)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['accidente_id'],
            $payload['fallecido_inv_id'],
            $payload['familiar_persona_id'],
            $payload['parentesco'],
            $payload['observaciones'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sql = 'UPDATE familiar_fallecido SET fallecido_inv_id = ?, familiar_persona_id = ?, parentesco = ?, observaciones = ? WHERE id = ? LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['fallecido_inv_id'],
            $payload['familiar_persona_id'],
            $payload['parentesco'],
            $payload['observaciones'],
            $id,
        ]);
    }

    public function delete(int $id, int $accidenteId): void
    {
        $st = $this->pdo->prepare('DELETE FROM familiar_fallecido WHERE id = ? AND accidente_id = ? LIMIT 1');
        $st->execute([$id, $accidenteId]);
    }
}
