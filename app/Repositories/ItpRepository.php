<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ItpRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function accidenteHeader(int $accidenteId): ?array
    {
        $st = $this->pdo->prepare('SELECT id, registro_sidpol, fecha_accidente, lugar FROM accidentes WHERE id = ? LIMIT 1');
        $st->execute([$accidenteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function accidentesDisponibles(int $limit = 500): array
    {
        $st = $this->pdo->prepare('SELECT id, registro_sidpol, fecha_accidente, lugar FROM accidentes ORDER BY fecha_accidente DESC, id DESC LIMIT ?');
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT i.*, `señalizacion_via1` AS senializacion_via1, `señalizacion_via2` AS senializacion_via2
                FROM itp i
                WHERE i.id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function detail(int $id): ?array
    {
        $sql = "SELECT i.*, `señalizacion_via1` AS senializacion_via1, `señalizacion_via2` AS senializacion_via2,
                       a.id AS accidente_id, a.registro_sidpol, a.fecha_accidente, a.lugar
                FROM itp i
                JOIN accidentes a ON a.id = i.accidente_id
                WHERE i.id = ?
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function list(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['accidente_id'])) {
            $where[] = 'i.accidente_id = :accidente_id';
            $params[':accidente_id'] = (int) $filters['accidente_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(a.registro_sidpol LIKE :q OR a.lugar LIKE :q OR i.forma_via LIKE :q OR i.punto_referencia LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $sql = "SELECT i.id, i.accidente_id, i.fecha_itp, i.hora_itp, i.forma_via, i.punto_referencia, i.ubicacion_gps,
                       a.registro_sidpol, a.fecha_accidente, a.lugar
                FROM itp i
                JOIN accidentes a ON a.id = i.accidente_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.id DESC
                LIMIT 500";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $payload): int
    {
        $sql = "INSERT INTO itp (
                    accidente_id, fecha_itp, hora_itp, ocurrencia_policial, llegada_lugar, localizacion_unidades,
                    forma_via, punto_referencia, ubicacion_gps,
                    descripcion_via1, configuracion_via1, material_via1, `señalizacion_via1`,
                    ordenamiento_via1, iluminacion_via1, visibilidad_via1, intensidad_via1, fluidez_via1,
                    medidas_via1, observaciones_via1,
                    descripcion_via2, configuracion_via2, material_via2, `señalizacion_via2`,
                    ordenamiento_via2, iluminacion_via2, visibilidad_via2, intensidad_via2, fluidez_via2,
                    medidas_via2, observaciones_via2,
                    evidencia_biologica, evidencia_fisica, evidencia_material
                ) VALUES (
                    :accidente_id, :fecha_itp, :hora_itp, :ocurrencia_policial, :llegada_lugar, :localizacion_unidades,
                    :forma_via, :punto_referencia, :ubicacion_gps,
                    :descripcion_via1, :configuracion_via1, :material_via1, :senializacion_via1,
                    :ordenamiento_via1, :iluminacion_via1, :visibilidad_via1, :intensidad_via1, :fluidez_via1,
                    :medidas_via1, :observaciones_via1,
                    :descripcion_via2, :configuracion_via2, :material_via2, :senializacion_via2,
                    :ordenamiento_via2, :iluminacion_via2, :visibilidad_via2, :intensidad_via2, :fluidez_via2,
                    :medidas_via2, :observaciones_via2,
                    :evidencia_biologica, :evidencia_fisica, :evidencia_material
                )";
        $st = $this->pdo->prepare($sql);
        $st->execute($payload);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sql = "UPDATE itp SET
                    fecha_itp = :fecha_itp,
                    hora_itp = :hora_itp,
                    ocurrencia_policial = :ocurrencia_policial,
                    llegada_lugar = :llegada_lugar,
                    localizacion_unidades = :localizacion_unidades,
                    forma_via = :forma_via,
                    punto_referencia = :punto_referencia,
                    ubicacion_gps = :ubicacion_gps,
                    descripcion_via1 = :descripcion_via1,
                    configuracion_via1 = :configuracion_via1,
                    material_via1 = :material_via1,
                    `señalizacion_via1` = :senializacion_via1,
                    ordenamiento_via1 = :ordenamiento_via1,
                    iluminacion_via1 = :iluminacion_via1,
                    visibilidad_via1 = :visibilidad_via1,
                    intensidad_via1 = :intensidad_via1,
                    fluidez_via1 = :fluidez_via1,
                    medidas_via1 = :medidas_via1,
                    observaciones_via1 = :observaciones_via1,
                    descripcion_via2 = :descripcion_via2,
                    configuracion_via2 = :configuracion_via2,
                    material_via2 = :material_via2,
                    `señalizacion_via2` = :senializacion_via2,
                    ordenamiento_via2 = :ordenamiento_via2,
                    iluminacion_via2 = :iluminacion_via2,
                    visibilidad_via2 = :visibilidad_via2,
                    intensidad_via2 = :intensidad_via2,
                    fluidez_via2 = :fluidez_via2,
                    medidas_via2 = :medidas_via2,
                    observaciones_via2 = :observaciones_via2,
                    evidencia_biologica = :evidencia_biologica,
                    evidencia_fisica = :evidencia_fisica,
                    evidencia_material = :evidencia_material
                WHERE id = :id";
        $payload[':id'] = $id;
        $st = $this->pdo->prepare($sql);
        $st->execute($payload);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM itp WHERE id = ? LIMIT 1');
        $st->execute([$id]);
    }
}
