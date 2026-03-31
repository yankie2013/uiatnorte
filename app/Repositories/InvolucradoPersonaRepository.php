<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class InvolucradoPersonaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function accidentes(): array
    {
        $sql = "SELECT id, CONCAT('#',id,' - ',DATE_FORMAT(fecha_accidente,'%Y-%m-%d %H:%i'),' - ',COALESCE(lugar,'')) AS nom
                  FROM accidentes
              ORDER BY id DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function accidenteFecha(int $accidenteId): ?string
    {
        $st = $this->pdo->prepare('SELECT fecha_accidente FROM accidentes WHERE id=?');
        $st->execute([$accidenteId]);
        $value = $st->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public function roles(): array
    {
        $sql = "SELECT Id AS id, Nombre AS nombre, COALESCE(RequiereVehiculo,0) AS req_veh
                  FROM participacion_persona
                 WHERE Activo=1
              ORDER BY Orden, Nombre";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rolById(int $rolId): ?array
    {
        $st = $this->pdo->prepare('SELECT COALESCE(RequiereVehiculo,0) AS req, Nombre FROM participacion_persona WHERE Id=?');
        $st->execute([$rolId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function personaByDni(string $dni): ?array
    {
        $st = $this->pdo->prepare('SELECT id,num_doc,nombres,apellido_paterno,apellido_materno,sexo,edad,fecha_nacimiento FROM personas WHERE num_doc=? LIMIT 1');
        $st->execute([$dni]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function personaByDniBasic(string $dni): ?array
    {
        $st = $this->pdo->prepare('SELECT id, num_doc, nombres, apellido_paterno, apellido_materno, sexo, fecha_nacimiento FROM personas WHERE num_doc=? LIMIT 1');
        $st->execute([$dni]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createPersona(array $payload): int
    {
        $st = $this->pdo->prepare('INSERT INTO personas(num_doc,nombres,apellido_paterno,apellido_materno,sexo,edad,creado_en) VALUES (?,?,?,?,?,?,NOW())');
        $st->execute([
            $payload['num_doc'],
            $payload['nombres'],
            $payload['apellido_paterno'],
            $payload['apellido_materno'],
            $payload['sexo'],
            $payload['edad'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function personaIdByDni(string $dni): ?int
    {
        $st = $this->pdo->prepare('SELECT id FROM personas WHERE num_doc=? LIMIT 1');
        $st->execute([$dni]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function personaFechaNacimiento(int $personaId): ?string
    {
        $st = $this->pdo->prepare('SELECT fecha_nacimiento FROM personas WHERE id=?');
        $st->execute([$personaId]);
        $value = $st->fetchColumn();
        return $value === false || $value === null ? null : (string) $value;
    }

    public function updatePersonaEdad(int $personaId, int $edad): void
    {
        $st = $this->pdo->prepare('UPDATE personas SET edad=? WHERE id=?');
        $st->execute([$edad, $personaId]);
    }

    public function vehiculosPorAccidente(int $accidenteId): array
    {
        $sql = "SELECT iv.orden_participacion, iv.tipo,
                       v.id, v.placa, v.color, v.anio
                  FROM involucrados_vehiculos iv
                  JOIN vehiculos v ON v.id=iv.vehiculo_id
                 WHERE iv.accidente_id=?
              ORDER BY iv.orden_participacion,
                       FIELD(iv.tipo, 'Combinado vehicular 1', 'Combinado vehicular 2', 'Unidad', 'Fugado'),
                       v.placa";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $used = [];

        foreach ($rows as $index => $row) {
            if (isset($used[$index])) {
                continue;
            }

            if ((string) $row['tipo'] === 'Combinado vehicular 1') {
                $pairIndex = $this->findCombinadoPairIndex($rows, (string) $row['orden_participacion'], $index);
                if ($pairIndex !== null) {
                    $pair = $rows[$pairIndex];
                    $used[$pairIndex] = true;

                    $placa1 = $this->displayPlaca((string) $row['placa']);
                    $placa2 = $this->displayPlaca((string) $pair['placa']);
                    $items[] = [
                        'id' => (int) $row['id'],
                        'ids' => [(int) $row['id'], (int) $pair['id']],
                        'placa' => $placa1 . ' + ' . $placa2,
                        'color' => null,
                        'anio' => null,
                        'tipo' => 'Combinado vehicular',
                        'orden_participacion' => (string) $row['orden_participacion'],
                        't' => trim((string) $row['orden_participacion']) . ' - ' . $placa1 . ' + ' . $placa2,
                    ];
                    continue;
                }
            }

            $placa = $this->displayPlaca((string) $row['placa']);
            $texto = trim((string) $row['orden_participacion']) . ' - ' . $placa
                . (!empty($row['color']) ? ' - ' . $row['color'] : '')
                . (!empty($row['anio']) ? ' (' . $row['anio'] . ')' : '');

            $items[] = [
                'id' => (int) $row['id'],
                'ids' => [(int) $row['id']],
                'placa' => $placa,
                'color' => $row['color'],
                'anio' => $row['anio'],
                'tipo' => $row['tipo'],
                'orden_participacion' => (string) $row['orden_participacion'],
                't' => $texto,
            ];
        }

        return $items;
    }

    private function findCombinadoPairIndex(array $rows, string $ordenParticipacion, int $currentIndex): ?int
    {
        foreach ($rows as $index => $row) {
            if ($index === $currentIndex) {
                continue;
            }
            if ((string) ($row['orden_participacion'] ?? '') !== $ordenParticipacion) {
                continue;
            }
            if ((string) ($row['tipo'] ?? '') !== 'Combinado vehicular 2') {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function displayPlaca(string $placa): string
    {
        return str_starts_with($placa, 'SPLACA') ? 'SIN PLACA' : $placa;
    }

    public function createInvolucrado(array $payload): int
    {
        $st = $this->pdo->prepare('INSERT INTO involucrados_personas(accidente_id,persona_id,rol_id,vehiculo_id,lesion,observaciones,orden_persona) VALUES (?,?,?,?,?,?,?)');
        $st->execute([
            $payload['accidente_id'],
            $payload['persona_id'],
            $payload['rol_id'],
            $payload['vehiculo_id'],
            $payload['lesion'],
            $payload['observaciones'],
            $payload['orden_persona'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function involucradoById(int $involucradoId): ?array
    {
        $sql = "SELECT ip.id, ip.accidente_id, ip.persona_id, ip.rol_id, ip.vehiculo_id, ip.lesion, ip.observaciones,
                       ip.orden_persona,
                       a.fecha_accidente, a.lugar,
                       p.num_doc, p.nombres, p.apellido_paterno, p.apellido_materno, p.sexo, p.fecha_nacimiento
                  FROM involucrados_personas ip
                  JOIN accidentes a ON a.id = ip.accidente_id
                  JOIN personas   p ON p.id = ip.persona_id
                 WHERE ip.id=? LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$involucradoId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateInvolucrado(int $involucradoId, array $payload): void
    {
        $st = $this->pdo->prepare("UPDATE involucrados_personas
                         SET persona_id=?, rol_id=?, vehiculo_id=?, lesion=?, observaciones=?, orden_persona=?
                         WHERE id=? LIMIT 1");
        $st->execute([
            $payload['persona_id'],
            $payload['rol_id'],
            $payload['vehiculo_id'],
            $payload['lesion'],
            $payload['observaciones'],
            $payload['orden_persona'],
            $involucradoId,
        ]);
    }

    public function licenciasPersona(int $personaId): array
    {
        $sql = "SELECT id, clase, categoria, numero, vigente_desde, vigente_hasta
                  FROM documento_lc
                 WHERE persona_id=?
              ORDER BY COALESCE(vigente_hasta,'9999-12-31') DESC, id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$personaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rmlPersona(int $personaId): array
    {
        $sql = "SELECT id, numero, fecha, incapacidad_medico, atencion_facultativo, observaciones
                  FROM documento_rml WHERE persona_id=? ORDER BY COALESCE(fecha,'9999-12-31') DESC, id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$personaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function dosajePersona(int $personaId): array
    {
        $sql = "SELECT id, numero, numero_registro, fecha_extraccion,
                       resultado_cualitativo, resultado_cuantitativo
                  FROM documento_dosaje
                 WHERE persona_id=?
              ORDER BY COALESCE(fecha_extraccion,'9999-12-31 23:59:59') DESC, id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$personaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function manifestacionesPersona(int $personaId, int $accidenteId): array
    {
        $sql = "SELECT id, fecha, horario_inicio, hora_termino, modalidad
                  FROM Manifestacion
                 WHERE persona_id=? AND accidente_id=?
              ORDER BY COALESCE(fecha,'9999-12-31') DESC, COALESCE(horario_inicio,'23:59:59') DESC, id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$personaId, $accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function occisosPersona(int $personaId, int $accidenteId): array
    {
        $sql = "SELECT id, fecha_levantamiento, hora_levantamiento, lugar_levantamiento, numero_protocolo
                  FROM documento_occiso
                 WHERE persona_id=? AND accidente_id=?
              ORDER BY COALESCE(fecha_levantamiento,'9999-12-31') DESC,
                       COALESCE(hora_levantamiento,'23:59:59') DESC,
                       id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([$personaId, $accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
