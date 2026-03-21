<?php

namespace App\Repositories;

use PDO;

final class CitacionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function personasVinculadas(int $accidenteId): array
    {
        $sql = "SELECT fuente, fuente_id,
                       CONCAT(COALESCE(nombres,''), ' ', COALESCE(apellido_paterno,''), ' ', COALESCE(apellido_materno,'')) AS nombre,
                       relacion, tipo_doc, num_doc, extra,
                       nombres, apellido_paterno, apellido_materno, domicilio, fecha_nacimiento
                FROM v_accidente_personas_vinculadas
                WHERE accidente_id = ?
                ORDER BY relacion, apellido_paterno, apellido_materno, nombres";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function personaVinculada(int $accidenteId, string $fuente, int $fuenteId): ?array
    {
        $sql = "SELECT * FROM v_accidente_personas_vinculadas
                WHERE accidente_id = ? AND fuente = ? AND fuente_id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId, $fuente, $fuenteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function oficiosByAccidente(int $accidenteId): array
    {
        $st = $this->pdo->prepare('SELECT id, numero, anio FROM oficios WHERE accidente_id = ? ORDER BY id DESC LIMIT 300');
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createFromView(int $accidenteId, string $fuente, int $fuenteId, array $payload): int
    {
        $sql = "INSERT INTO citacion (
                    accidente_id, fuente, fuente_id,
                    persona_nombres, persona_apep, persona_apem,
                    persona_doc_tipo, persona_doc_num, persona_domicilio, persona_edad,
                    en_calidad, tipo_diligencia, fecha, hora, lugar, motivo,
                    orden_citacion, oficio_id
                )
                SELECT
                    v.accidente_id, v.fuente, v.fuente_id,
                    v.nombres, v.apellido_paterno, v.apellido_materno,
                    v.tipo_doc, v.num_doc, v.domicilio,
                    CASE WHEN v.fecha_nacimiento IS NULL THEN NULL
                         ELSE TIMESTAMPDIFF(YEAR, v.fecha_nacimiento, ?) END,
                    ?, ?, ?, ?, ?, ?, ?, ?
                FROM v_accidente_personas_vinculadas v
                WHERE v.accidente_id = ? AND v.fuente = ? AND v.fuente_id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['fecha'],
            $payload['en_calidad'],
            $payload['tipo_diligencia'],
            $payload['fecha'],
            $payload['hora'],
            $payload['lugar'],
            $payload['motivo'],
            $payload['orden_citacion'],
            $payload['oficio_id'],
            $accidenteId,
            $fuente,
            $fuenteId,
        ]);
        if ($st->rowCount() !== 1) {
            throw new \RuntimeException('No se pudo insertar la citacion. Verifica que la persona pertenezca al accidente.');
        }
        return (int) $this->pdo->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT c.*, o.numero AS oficio_num, o.anio AS oficio_anio
                FROM citacion c
                LEFT JOIN oficios o ON o.id = c.oficio_id
                WHERE c.id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(int $id, array $payload): void
    {
        $sql = "UPDATE citacion SET
                    persona_nombres = ?, persona_apep = ?, persona_apem = ?,
                    persona_doc_tipo = ?, persona_doc_num = ?, persona_domicilio = ?, persona_edad = ?,
                    en_calidad = ?, tipo_diligencia = ?, fecha = ?, hora = ?,
                    lugar = ?, motivo = ?, orden_citacion = ?, oficio_id = ?
                WHERE id = ?";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['persona_nombres'],
            $payload['persona_apep'],
            $payload['persona_apem'],
            $payload['persona_doc_tipo'],
            $payload['persona_doc_num'],
            $payload['persona_domicilio'],
            $payload['persona_edad'],
            $payload['en_calidad'],
            $payload['tipo_diligencia'],
            $payload['fecha'],
            $payload['hora'],
            $payload['lugar'],
            $payload['motivo'],
            $payload['orden_citacion'],
            $payload['oficio_id'],
            $id,
        ]);
    }

    public function delete(int $id, ?int $accidenteId = null): void
    {
        if ($accidenteId !== null) {
            $st = $this->pdo->prepare('DELETE FROM citacion WHERE id = ? AND accidente_id = ?');
            $st->execute([$id, $accidenteId]);
            return;
        }
        $st = $this->pdo->prepare('DELETE FROM citacion WHERE id = ?');
        $st->execute([$id]);
    }

    public function searchByAccidente(int $accidenteId, array $filters): array
    {
        $sql = "SELECT c.*, o.numero AS oficio_num, o.anio AS oficio_anio
                FROM citacion c
                LEFT JOIN oficios o ON o.id = c.oficio_id
                WHERE c.accidente_id = ?";
        $params = [$accidenteId];

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $sql .= " AND (
                        c.persona_nombres LIKE ? OR c.persona_apep LIKE ? OR c.persona_apem LIKE ? OR
                        c.persona_doc_num LIKE ? OR c.en_calidad LIKE ? OR c.tipo_diligencia LIKE ? OR
                        c.lugar LIKE ? OR c.motivo LIKE ?
                     )";
            for ($i = 0; $i < 8; $i++) {
                $params[] = $like;
            }
        }
        if (!empty($filters['desde'])) {
            $sql .= ' AND c.fecha >= ?';
            $params[] = $filters['desde'];
        }
        if (!empty($filters['hasta'])) {
            $sql .= ' AND c.fecha <= ?';
            $params[] = $filters['hasta'];
        }

        $sql .= ' ORDER BY c.fecha DESC, c.hora DESC, c.orden_citacion ASC, c.id DESC LIMIT 500';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
