<?php

namespace App\Repositories;

use PDO;
use Throwable;

final class CitacionRepository
{
    private array $columnCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    private function personasSource(): string
    {
        // The legacy view only includes owners linked to a natural person.
        return "(SELECT fuente, fuente_id, accidente_id, tipo_doc, num_doc, nombres,
                        apellido_paterno, apellido_materno, domicilio, fecha_nacimiento, relacion, extra
                 FROM v_accidente_personas_vinculadas WHERE fuente <> 'PRO'
                 UNION ALL
                 SELECT 'PRO', pv.id, pv.accidente_id,
                        CASE WHEN pv.tipo_propietario = 'JURIDICA' THEN 'RUC' ELSE p.tipo_doc END,
                        CASE WHEN pv.tipo_propietario = 'JURIDICA' THEN pv.ruc ELSE p.num_doc END,
                        CASE WHEN pv.tipo_propietario = 'JURIDICA' THEN pv.razon_social ELSE p.nombres END,
                        CASE WHEN pv.tipo_propietario = 'JURIDICA' THEN '' ELSE p.apellido_paterno END,
                        CASE WHEN pv.tipo_propietario = 'JURIDICA' THEN '' ELSE COALESCE(p.apellido_materno, '') END,
                        CASE WHEN pv.tipo_propietario = 'JURIDICA' THEN pv.domicilio_fiscal
                             ELSE IF(pv.snapshot_guardado = 1, pv.domicilio_nat_snapshot, p.domicilio) END,
                        CASE WHEN pv.tipo_propietario = 'JURIDICA' THEN NULL ELSE p.fecha_nacimiento END,
                        'Propietario del vehículo',
                        CONCAT_WS(' · ', NULLIF(pv.rol_legal, ''),
                            CASE WHEN pv.tipo_propietario = 'JURIDICA' AND rep.id IS NOT NULL
                                 THEN CONCAT('Representante: ', CONCAT_WS(' ', rep.nombres, rep.apellido_paterno, rep.apellido_materno)) END)
                 FROM propietario_vehiculo_activos pv
                 LEFT JOIN personas p ON p.id = pv.propietario_persona_id
                 LEFT JOIN personas rep ON rep.id = pv.representante_persona_id
                 WHERE pv.tipo_propietario = 'JURIDICA' OR p.id IS NOT NULL)";
    }

    public function personasVinculadas(int $accidenteId): array
    {
        $source = $this->personasSource();
        $sql = "SELECT fuente, fuente_id,
                       CONCAT(COALESCE(nombres,''), ' ', COALESCE(apellido_paterno,''), ' ', COALESCE(apellido_materno,'')) AS nombre,
                       relacion, tipo_doc, num_doc, extra,
                       nombres, apellido_paterno, apellido_materno, domicilio, fecha_nacimiento
                FROM $source vinculadas
                WHERE accidente_id = ?
                ORDER BY relacion, apellido_paterno, apellido_materno, nombres";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function personaVinculada(int $accidenteId, string $fuente, int $fuenteId): ?array
    {
        $source = $this->personasSource();
        $sql = "SELECT * FROM $source vinculadas
                WHERE accidente_id = ? AND fuente = ? AND fuente_id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId, $fuente, $fuenteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function oficiosByAccidente(int $accidenteId): array
    {
        $st = $this->pdo->prepare('SELECT id, numero, anio FROM oficios_activos WHERE accidente_id = ? ORDER BY id DESC LIMIT 300');
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function accidenteResumen(int $accidenteId): ?array
    {
        $sql = "SELECT id,
                       COALESCE(registro_sidpol, '') AS registro_sidpol,
                       fecha_accidente,
                       COALESCE(lugar, '') AS lugar,
                       COALESCE(referencia, '') AS referencia
                FROM accidentes_activos
                WHERE id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createFromView(int $accidenteId, string $fuente, int $fuenteId, array $payload): int
    {
        $source = $this->personasSource();
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
                FROM $source v
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
                FROM citacion_activos c
                LEFT JOIN oficios_activos o ON o.id = c.oficio_id
                WHERE c.id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateCalendarSync(int $id, array $payload): void
    {
        $this->ensureCalendarColumns();

        $sql = "UPDATE citacion
                   SET google_calendar_event_id = ?,
                       google_calendar_event_link = ?,
                       google_calendar_sync_status = ?,
                       google_calendar_synced_at = ?,
                       google_calendar_last_error = ?
                 WHERE id = ?";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['event_id'] ?? null,
            $payload['event_link'] ?? null,
            $payload['sync_status'] ?? 'pendiente',
            $payload['synced_at'] ?? null,
            $payload['last_error'] ?? null,
            $id,
        ]);
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

    public function updateOutcome(int $accidenteId, array $ids, string $outcome): void
    {
        if (!in_array($outcome, ['realizada', 'no-realizada', 'pendiente'], true)) {
            throw new \InvalidArgumentException('Estado de citación no válido.');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || count($ids) > 500 || min($ids) <= 0) {
            throw new \InvalidArgumentException('Selecciona una tarjeta de citación válida.');
        }
        if (!$this->columnExists('citacion', 'estado_realizacion')) {
            throw new \RuntimeException('Falta actualizar la base de datos: ejecuta php docs/scripts/migrar_citacion_estado.php');
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare("SELECT c.* FROM citacion_activos c WHERE c.accidente_id = ? AND c.id IN ($marks) FOR UPDATE");
            $st->execute(array_merge([$accidenteId], $ids));
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== count($ids)) {
                throw new \RuntimeException('La tarjeta ya no está disponible en este expediente.');
            }
            $keys = [];
            foreach ($rows as $row) {
                $keys[] = json_encode([$row['fecha'], $row['hora'], \App\Support\CitacionMotivo::texto($row), trim((string) $row['lugar'])]);
            }
            if (count(array_unique($keys)) !== 1) {
                throw new \RuntimeException('Solo se puede cambiar una tarjeta por vez.');
            }
            $st = $this->pdo->prepare("UPDATE citacion SET estado_realizacion = ? WHERE accidente_id = ? AND id IN ($marks)");
            $st->execute(array_merge([$outcome, $accidenteId], $ids));
            if ($ownsTransaction) $this->pdo->commit();
        } catch (Throwable $e) {
            if ($ownsTransaction) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function searchByAccidente(int $accidenteId, array $filters): array
    {
        $outcomeSelect = $this->columnExists('citacion', 'estado_realizacion')
            ? '(SELECT base.estado_realizacion FROM citacion base WHERE base.id = c.id)'
            : 'NULL';
        $sql = "SELECT c.*, $outcomeSelect AS estado_realizacion, CASE c.fuente
                    WHEN 'INV' THEN (SELECT ip.persona_id FROM involucrados_personas_activos ip WHERE ip.id=c.fuente_id AND ip.accidente_id=c.accidente_id)
                    WHEN 'PNP' THEN (SELECT pi.persona_id FROM policial_interviniente_activos pi WHERE pi.id=c.fuente_id AND pi.accidente_id=c.accidente_id)
                    WHEN 'PRO' THEN (SELECT CASE WHEN pv.tipo_propietario = 'JURIDICA' THEN pv.representante_persona_id ELSE pv.propietario_persona_id END FROM propietario_vehiculo_activos pv WHERE pv.id=c.fuente_id AND pv.accidente_id=c.accidente_id)
                    WHEN 'FAM' THEN (SELECT ff.familiar_persona_id FROM familiar_fallecido_activos ff WHERE ff.id=c.fuente_id AND ff.accidente_id=c.accidente_id)
                    END AS persona_id, o.numero AS oficio_num, o.anio AS oficio_anio
                FROM citacion_activos c
                LEFT JOIN oficios_activos o ON o.id = c.oficio_id
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

    private function ensureCalendarColumns(): void
    {
        $required = [
            'google_calendar_event_id' => "ALTER TABLE citacion ADD COLUMN google_calendar_event_id VARCHAR(255) NULL AFTER oficio_id",
            'google_calendar_event_link' => "ALTER TABLE citacion ADD COLUMN google_calendar_event_link TEXT NULL AFTER google_calendar_event_id",
            'google_calendar_sync_status' => "ALTER TABLE citacion ADD COLUMN google_calendar_sync_status VARCHAR(30) NULL AFTER google_calendar_event_link",
            'google_calendar_synced_at' => "ALTER TABLE citacion ADD COLUMN google_calendar_synced_at DATETIME NULL AFTER google_calendar_sync_status",
            'google_calendar_last_error' => "ALTER TABLE citacion ADD COLUMN google_calendar_last_error TEXT NULL AFTER google_calendar_synced_at",
        ];

        foreach ($required as $column => $sql) {
            if ($this->columnExists('citacion', $column)) {
                continue;
            }

            try {
                $this->pdo->exec($sql);
            } catch (Throwable) {
                // Another request may have added the column concurrently.
            }
            unset($this->columnCache['citacion.' . $column]);
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$table, $column]);
        $exists = (int) $st->fetchColumn() > 0;
        $this->columnCache[$cacheKey] = $exists;
        return $exists;
    }
}
