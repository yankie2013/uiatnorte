<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PropietarioVehiculoRepository
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

    public function vehiculosByAccidente(int $accidenteId): array
    {
        $sql = "SELECT iv.id AS inv_id, iv.orden_participacion, v.placa
                FROM involucrados_vehiculos iv
                JOIN vehiculos v ON v.id = iv.vehiculo_id
                WHERE iv.accidente_id = ?
                ORDER BY FIELD(iv.orden_participacion,'UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7'), v.placa";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function vehiculoBelongsAccidente(int $accidenteId, int $vehiculoInvId): bool
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM involucrados_vehiculos WHERE id = ? AND accidente_id = ?');
        $st->execute([$vehiculoInvId, $accidenteId]);
        return (int) $st->fetchColumn() > 0;
    }

    public function personaByDni(string $dni): ?array
    {
        $st = $this->pdo->prepare("SELECT id, tipo_doc, num_doc, apellido_paterno, apellido_materno, nombres, fecha_nacimiento, domicilio, celular, email FROM personas WHERE tipo_doc = 'DNI' AND num_doc = ? LIMIT 1");
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

    public function searchPersonas(string $query): array
    {
        $likeDni = $query . '%';
        $likeNom = '%' . $query . '%';
        $sql = "SELECT id, tipo_doc, num_doc, apellido_paterno, apellido_materno, nombres, fecha_nacimiento, domicilio, celular, email
                FROM personas
                WHERE (tipo_doc = 'DNI' AND num_doc LIKE ?)
                   OR CONCAT(COALESCE(apellido_paterno,''), ' ', COALESCE(apellido_materno,''), ' ', COALESCE(nombres,'')) LIKE ?
                ORDER BY apellido_paterno, apellido_materno, nombres
                LIMIT 20";
        $st = $this->pdo->prepare($sql);
        $st->execute([$likeDni, $likeNom]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePersonaContact(int $id, ?string $celular, ?string $email): void
    {
        $st = $this->pdo->prepare('UPDATE personas SET celular = ?, email = ? WHERE id = ?');
        $st->execute([$celular, $email, $id]);
    }

    public function existsDuplicate(int $accidenteId, int $vehiculoInvId, string $tipoPropietario, int $propietarioPersonaId, string $ruc, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM propietario_vehiculo
                WHERE accidente_id = ?
                  AND vehiculo_inv_id = ?
                  AND tipo_propietario = ?
                  AND IFNULL(propietario_persona_id,0) = ?
                  AND IFNULL(ruc,'') = ?";
        $params = [$accidenteId, $vehiculoInvId, $tipoPropietario, $propietarioPersonaId, $ruc];
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
        $sql = "INSERT INTO propietario_vehiculo
                (accidente_id, vehiculo_inv_id, tipo_propietario, propietario_persona_id,
                 ruc, razon_social, domicilio_fiscal, rol_legal, representante_persona_id, observaciones)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['accidente_id'],
            $payload['vehiculo_inv_id'],
            $payload['tipo_propietario'],
            $payload['propietario_persona_id'],
            $payload['ruc'],
            $payload['razon_social'],
            $payload['domicilio_fiscal'],
            $payload['rol_legal'],
            $payload['representante_persona_id'],
            $payload['observaciones'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sql = "UPDATE propietario_vehiculo SET
                  vehiculo_inv_id = ?,
                  tipo_propietario = ?,
                  propietario_persona_id = ?,
                  ruc = ?,
                  razon_social = ?,
                  domicilio_fiscal = ?,
                  rol_legal = ?,
                  representante_persona_id = ?,
                  observaciones = ?
                WHERE id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['vehiculo_inv_id'],
            $payload['tipo_propietario'],
            $payload['propietario_persona_id'],
            $payload['ruc'],
            $payload['razon_social'],
            $payload['domicilio_fiscal'],
            $payload['rol_legal'],
            $payload['representante_persona_id'],
            $payload['observaciones'],
            $id,
        ]);
    }

    public function delete(int $id, int $accidenteId): void
    {
        $st = $this->pdo->prepare('DELETE FROM propietario_vehiculo WHERE id = ? AND accidente_id = ? LIMIT 1');
        $st->execute([$id, $accidenteId]);
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM propietario_vehiculo WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function detail(int $id): ?array
    {
        $sql = "SELECT pv.*, a.id AS accidente, a.sidpol, a.registro_sidpol, a.lugar, a.fecha_accidente,
                       iv.orden_participacion, v.placa,
                       pn.tipo_doc AS tipo_doc_nat, pn.num_doc AS dni_nat, pn.apellido_paterno AS ap_nat, pn.apellido_materno AS am_nat, pn.nombres AS no_nat, pn.domicilio AS dom_nat, pn.celular AS cel_nat, pn.email AS em_nat,
                       pr.tipo_doc AS tipo_doc_rep, pr.num_doc AS dni_rep, pr.apellido_paterno AS ap_rep, pr.apellido_materno AS am_rep, pr.nombres AS no_rep, pr.domicilio AS dom_rep, pr.celular AS cel_rep, pr.email AS em_rep
                FROM propietario_vehiculo pv
                JOIN accidentes a ON a.id = pv.accidente_id
                JOIN involucrados_vehiculos iv ON iv.id = pv.vehiculo_inv_id
                JOIN vehiculos v ON v.id = iv.vehiculo_id
                LEFT JOIN personas pn ON pn.id = pv.propietario_persona_id
                LEFT JOIN personas pr ON pr.id = pv.representante_persona_id
                WHERE pv.id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listByAccidente(int $accidenteId): array
    {
        $sql = "SELECT pv.id, pv.accidente_id, pv.tipo_propietario, pv.ruc, pv.razon_social, pv.rol_legal,
                       pv.vehiculo_inv_id, iv.orden_participacion, v.placa,
                       pn.tipo_doc AS tipo_doc_nat, pn.num_doc AS dni_nat, pn.apellido_paterno AS ap_nat, pn.apellido_materno AS am_nat, pn.nombres AS no_nat,
                       pr.tipo_doc AS tipo_doc_rep, pr.num_doc AS dni_rep, pr.apellido_paterno AS ap_rep, pr.apellido_materno AS am_rep, pr.nombres AS no_rep
                FROM propietario_vehiculo pv
                JOIN involucrados_vehiculos iv ON iv.id = pv.vehiculo_inv_id
                JOIN vehiculos v ON v.id = iv.vehiculo_id
                LEFT JOIN personas pn ON pn.id = pv.propietario_persona_id
                LEFT JOIN personas pr ON pr.id = pv.representante_persona_id
                WHERE pv.accidente_id = ?
                ORDER BY iv.orden_participacion, pv.id";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
