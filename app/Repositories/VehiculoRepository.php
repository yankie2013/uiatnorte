<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class VehiculoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function categorias(): array
    {
        return $this->pdo->query('SELECT id, codigo, descripcion FROM categoria_vehiculos ORDER BY codigo')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcas(): array
    {
        return $this->pdo->query('SELECT id, nombre FROM marcas_vehiculo ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function modelos(): array
    {
        return $this->pdo->query('SELECT id, marca_id, nombre FROM modelos_vehiculo ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function tipos(): array
    {
        return $this->pdo->query('SELECT id, categoria_id, codigo, nombre FROM tipos_vehiculo ORDER BY codigo')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function carrocerias(): array
    {
        return $this->pdo->query('SELECT id, tipo_id, nombre FROM carroceria_vehiculo ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM vehiculos WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDeleteSummary(int $id): ?array
    {
        $sql = "SELECT v.id, v.placa, COALESCE(m.nombre,'') AS marca, COALESCE(mo.nombre,'') AS modelo
                  FROM vehiculos v
             LEFT JOIN marcas_vehiculo m ON m.id = v.marca_id
             LEFT JOIN modelos_vehiculo mo ON mo.id = v.modelo_id
                 WHERE v.id = :id
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDetailById(int $id): ?array
    {
        $sql = $this->detailSelectSql() . ' WHERE v.id = :id LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDetailByNormalizedPlaca(string $placa): ?array
    {
        $sql = $this->detailSelectSql() . " WHERE UPPER(REPLACE(v.placa,' ','')) = :placa LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':placa' => $placa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function searchCount(string $q): int
    {
        [$where, $args] = $this->searchWhere($q);
        $sql = "SELECT COUNT(*) AS c
                  FROM vehiculos v
             LEFT JOIN categoria_vehiculos c ON c.id = v.categoria_id
             LEFT JOIN marcas_vehiculo m ON m.id = v.marca_id
             LEFT JOIN modelos_vehiculo mo ON mo.id = v.modelo_id
             LEFT JOIN tipos_vehiculo t ON t.id = v.tipo_id
             LEFT JOIN carroceria_vehiculo ca ON ca.id = v.carroceria_id
                {$where}";
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        return (int) ($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    }

    public function searchRows(string $q, int $limit, int $offset): array
    {
        [$where, $args] = $this->searchWhere($q);
        $sql = "SELECT
                    v.id, v.placa, v.anio, v.color,
                    v.serie_vin, v.nro_motor,
                    COALESCE(m.nombre,'') AS marca,
                    COALESCE(mo.nombre,'') AS modelo,
                    TRIM(CONCAT_WS(' – ', c.codigo, NULLIF(c.descripcion,''))) AS categoria,
                    TRIM(CONCAT_WS(' – ', t.codigo, NULLIF(t.nombre,''))) AS tipo,
                    COALESCE(ca.nombre,'') AS carroceria
                  FROM vehiculos v
             LEFT JOIN categoria_vehiculos c ON c.id = v.categoria_id
             LEFT JOIN marcas_vehiculo m ON m.id = v.marca_id
             LEFT JOIN modelos_vehiculo mo ON mo.id = v.modelo_id
             LEFT JOIN tipos_vehiculo t ON t.id = v.tipo_id
             LEFT JOIN carroceria_vehiculo ca ON ca.id = v.carroceria_id
                {$where}
              ORDER BY v.id DESC
                 LIMIT :lim OFFSET :off";

        $st = $this->pdo->prepare($sql);
        foreach ($args as $key => $value) {
            $st->bindValue($key, $value, PDO::PARAM_STR);
        }
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findIdByPlaca(string $placa, ?int $excludeId = null): ?int
    {
        $sql = 'SELECT id FROM vehiculos WHERE placa = :placa';
        $params = [':placa' => $placa];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $id = $st->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function categoriaExists(int $id): bool
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM categoria_vehiculos WHERE id = :id');
        $st->execute([':id' => $id]);
        return (int) $st->fetchColumn() > 0;
    }

    public function marcaExists(int $id): bool
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM marcas_vehiculo WHERE id = :id');
        $st->execute([':id' => $id]);
        return (int) $st->fetchColumn() > 0;
    }

    public function modeloMarcaId(int $id): ?int
    {
        $st = $this->pdo->prepare('SELECT marca_id FROM modelos_vehiculo WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $value = $st->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    public function tipoCategoriaId(int $id): ?int
    {
        $st = $this->pdo->prepare('SELECT categoria_id FROM tipos_vehiculo WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $value = $st->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    public function carroceriaTipoId(int $id): ?int
    {
        $st = $this->pdo->prepare('SELECT tipo_id FROM carroceria_vehiculo WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $value = $st->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    public function countAccidentesVinculados(int $vehiculoId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM involucrados_vehiculos WHERE vehiculo_id = :vehiculo_id');
        $st->execute([':vehiculo_id' => $vehiculoId]);
        return (int) $st->fetchColumn();
    }

    public function referenceCounts(int $vehiculoId): array
    {
        $sql = "SELECT TABLE_NAME
                  FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND COLUMN_NAME = 'vehiculo_id'
                   AND TABLE_NAME <> 'vehiculos'
              GROUP BY TABLE_NAME
              ORDER BY TABLE_NAME";
        $tables = $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $counts = [];
        foreach ($tables as $table) {
            $quoted = str_replace('`', '``', (string) $table);
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$quoted}` WHERE vehiculo_id = :vehiculo_id");
            $stmt->execute([':vehiculo_id' => $vehiculoId]);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) {
                $counts[(string) $table] = $count;
            }
        }

        return $counts;
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM vehiculos WHERE id = :id');
        $st->execute([':id' => $id]);
    }

    public function create(array $payload): int
    {
        $sql = "INSERT INTO vehiculos
            (placa, serie_vin, nro_motor,
             categoria_id, tipo_id, carroceria_id,
             marca_id, modelo_id,
             anio, color,
             largo_mm, ancho_mm, alto_mm,
             notas, creado_en, actualizado_en)
            VALUES
            (:placa, :serie_vin, :nro_motor,
             :categoria_id, :tipo_id, :carroceria_id,
             :marca_id, :modelo_id,
             :anio, :color,
             :largo_mm, :ancho_mm, :alto_mm,
             :notas, NOW(), NOW())";

        $st = $this->pdo->prepare($sql);
        $st->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $payload[':id'] = $id;

        $sql = "UPDATE vehiculos SET
            placa = :placa,
            serie_vin = :serie_vin,
            nro_motor = :nro_motor,
            categoria_id = :categoria_id,
            tipo_id = :tipo_id,
            carroceria_id = :carroceria_id,
            marca_id = :marca_id,
            modelo_id = :modelo_id,
            anio = :anio,
            color = :color,
            largo_mm = :largo_mm,
            ancho_mm = :ancho_mm,
            alto_mm = :alto_mm,
            notas = :notas,
            actualizado_en = NOW()
          WHERE id = :id";

        $st = $this->pdo->prepare($sql);
        $st->execute($payload);
    }

    private function detailSelectSql(): string
    {
        return "SELECT v.*,
                       cv.codigo AS cat_codigo,
                       cv.descripcion AS cat_desc,
                       tv.codigo AS tipo_codigo,
                       tv.nombre AS tipo_nombre,
                       car.nombre AS carroceria_nombre,
                       m.nombre AS marca_nombre,
                       mo.nombre AS modelo_nombre
                  FROM vehiculos v
             LEFT JOIN categoria_vehiculos cv ON cv.id = v.categoria_id
             LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
             LEFT JOIN carroceria_vehiculo car ON car.id = v.carroceria_id
             LEFT JOIN marcas_vehiculo m ON m.id = v.marca_id
             LEFT JOIN modelos_vehiculo mo ON mo.id = v.modelo_id";
    }

    private function searchWhere(string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return ['WHERE 1=1', []];
        }

        return [
            "WHERE (
                v.placa LIKE :q
                OR m.nombre LIKE :q
                OR mo.nombre LIKE :q
                OR t.nombre LIKE :q
                OR t.codigo LIKE :q
                OR c.codigo LIKE :q
                OR c.descripcion LIKE :q
                OR v.color LIKE :q
                OR v.nro_motor LIKE :q
                OR v.serie_vin LIKE :q
            )",
            [':q' => '%' . $q . '%'],
        ];
    }
}
