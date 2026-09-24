<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AbogadoRepository
{
    private array $tableCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    private function activeTable(string $table): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $table)) {
            throw new \InvalidArgumentException('Tabla no válida.');
        }
        $active = $table . '_activos';
        if (!array_key_exists($active, $this->tableCache)) {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
            $st->execute([$active]);
            $this->tableCache[$active] = (int) $st->fetchColumn() > 0;
        }
        return '`' . ($this->tableCache[$active] ? $active : $table) . '`';
    }

    public function accidenteHeader(int $accidenteId): ?array
    {
        $accidentTable = $this->activeTable('accidentes');
        $st = $this->pdo->prepare("SELECT id, sidpol, lugar, fecha_accidente FROM {$accidentTable} WHERE id = ? LIMIT 1");
        $st->execute([$accidenteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function personaOptionsByAccidente(int $accidenteId): array
    {
        $personsTable = $this->activeTable('involucrados_personas');
        $ownersTable = $this->activeTable('propietario_vehiculo');
        $familyTable = $this->activeTable('familiar_fallecido');
        $sql = "SELECT persona_id AS id,
                       nombre,
                       GROUP_CONCAT(DISTINCT rol ORDER BY rol SEPARATOR ', ') AS roles
                FROM (
                    SELECT p.id AS persona_id,
                           TRIM(CONCAT(COALESCE(p.nombres,''), ' ', COALESCE(p.apellido_paterno,''), ' ', COALESCE(p.apellido_materno,''))) AS nombre,
                           COALESCE(pr.Nombre, 'Involucrado') AS rol
                    FROM {$personsTable} ip
                    JOIN personas p ON p.id = ip.persona_id
                    LEFT JOIN participacion_persona pr ON pr.Id = ip.rol_id
                    WHERE ip.accidente_id = ?

                    UNION ALL

                    SELECT p.id AS persona_id,
                           TRIM(CONCAT(COALESCE(p.nombres,''), ' ', COALESCE(p.apellido_paterno,''), ' ', COALESCE(p.apellido_materno,''))) AS nombre,
                           'Propietario vehiculo' AS rol
                    FROM {$ownersTable} pv
                    JOIN personas p ON p.id = pv.propietario_persona_id
                    WHERE pv.accidente_id = ?

                    UNION ALL

                    SELECT p.id AS persona_id,
                           TRIM(CONCAT(COALESCE(p.nombres,''), ' ', COALESCE(p.apellido_paterno,''), ' ', COALESCE(p.apellido_materno,''))) AS nombre,
                           'Familiar fallecido' AS rol
                    FROM {$familyTable} ff
                    JOIN personas p ON p.id = ff.familiar_persona_id
                    WHERE ff.accidente_id = ?
                ) base
                GROUP BY persona_id, nombre
                ORDER BY nombre";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId, $accidenteId, $accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listByAccidente(int $accidenteId): array
    {
        $abogadosTable = $this->activeTable('abogados');
        $personsTable = $this->activeTable('involucrados_personas');
        $ownersTable = $this->activeTable('propietario_vehiculo');
        $familyTable = $this->activeTable('familiar_fallecido');
        $sql = "SELECT a.*,
                       TRIM(CONCAT(COALESCE(pr.nombres,''), ' ', COALESCE(pr.apellido_paterno,''), ' ', COALESCE(pr.apellido_materno,''))) AS persona_rep_nom,
                       COALESCE(prr.roles, '') AS condicion_representado
                FROM {$abogadosTable} a
                LEFT JOIN personas pr ON pr.id = a.persona_id
                LEFT JOIN (
                    SELECT accidente_id, persona_id, GROUP_CONCAT(DISTINCT rol ORDER BY rol SEPARATOR ', ') AS roles
                    FROM (
                        SELECT ip.accidente_id, ip.persona_id, COALESCE(pp.Nombre, 'Involucrado') AS rol
                        FROM {$personsTable} ip
                        LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id

                        UNION ALL

                        SELECT pv.accidente_id, pv.propietario_persona_id AS persona_id, 'Propietario vehiculo' AS rol
                        FROM {$ownersTable} pv

                        UNION ALL

                        SELECT ff.accidente_id, ff.familiar_persona_id AS persona_id, 'Familiar fallecido' AS rol
                        FROM {$familyTable} ff
                    ) roles_base
                    GROUP BY accidente_id, persona_id
                ) prr ON prr.accidente_id = a.accidente_id AND prr.persona_id = a.persona_id
                WHERE a.accidente_id = ?
                ORDER BY a.apellido_paterno, a.apellido_materno, a.nombres, a.id";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $abogadosTable = $this->activeTable('abogados');
        $st = $this->pdo->prepare("SELECT * FROM {$abogadosTable} WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function detail(int $id): ?array
    {
        $abogadosTable = $this->activeTable('abogados');
        $personsTable = $this->activeTable('involucrados_personas');
        $ownersTable = $this->activeTable('propietario_vehiculo');
        $familyTable = $this->activeTable('familiar_fallecido');
        $sql = "SELECT a.*,
                       TRIM(CONCAT(COALESCE(pr.nombres,''), ' ', COALESCE(pr.apellido_paterno,''), ' ', COALESCE(pr.apellido_materno,''))) AS persona_rep_nom,
                       COALESCE(prr.roles, '') AS condicion_representado
                FROM {$abogadosTable} a
                LEFT JOIN personas pr ON pr.id = a.persona_id
                LEFT JOIN (
                    SELECT accidente_id, persona_id, GROUP_CONCAT(DISTINCT rol ORDER BY rol SEPARATOR ', ') AS roles
                    FROM (
                        SELECT ip.accidente_id, ip.persona_id, COALESCE(pp.Nombre, 'Involucrado') AS rol
                        FROM {$personsTable} ip
                        LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id

                        UNION ALL

                        SELECT pv.accidente_id, pv.propietario_persona_id AS persona_id, 'Propietario vehiculo' AS rol
                        FROM {$ownersTable} pv

                        UNION ALL

                        SELECT ff.accidente_id, ff.familiar_persona_id AS persona_id, 'Familiar fallecido' AS rol
                        FROM {$familyTable} ff
                    ) roles_base
                    GROUP BY accidente_id, persona_id
                ) prr ON prr.accidente_id = a.accidente_id AND prr.persona_id = a.persona_id
                WHERE a.id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $sql = "INSERT INTO abogados (
                    persona_id, accidente_id, nombres, apellido_paterno, apellido_materno,
                    colegiatura, registro, casilla_electronica, domicilio_procesal, celular, email,
                    creado_en, actualizado_en
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['persona_id'],
            $payload['accidente_id'],
            $payload['nombres'],
            $payload['apellido_paterno'],
            $payload['apellido_materno'],
            $payload['colegiatura'],
            $payload['registro'],
            $payload['casilla_electronica'],
            $payload['domicilio_procesal'],
            $payload['celular'],
            $payload['email'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sql = "UPDATE abogados SET
                    persona_id = ?,
                    accidente_id = ?,
                    nombres = ?,
                    apellido_paterno = ?,
                    apellido_materno = ?,
                    colegiatura = ?,
                    registro = ?,
                    casilla_electronica = ?,
                    domicilio_procesal = ?,
                    celular = ?,
                    email = ?,
                    actualizado_en = CURRENT_TIMESTAMP
                WHERE id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['persona_id'],
            $payload['accidente_id'],
            $payload['nombres'],
            $payload['apellido_paterno'],
            $payload['apellido_materno'],
            $payload['colegiatura'],
            $payload['registro'],
            $payload['casilla_electronica'],
            $payload['domicilio_procesal'],
            $payload['celular'],
            $payload['email'],
            $id,
        ]);
    }

    public function delete(int $id, int $accidenteId): void
    {
        $st = $this->pdo->prepare('DELETE FROM abogados WHERE id = ? AND accidente_id = ? LIMIT 1');
        $st->execute([$id, $accidenteId]);
    }
}
