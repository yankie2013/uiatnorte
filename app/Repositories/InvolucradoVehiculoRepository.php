<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class InvolucradoVehiculoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function accidentes(): array
    {
        $sql = "SELECT id, CONCAT('#',id,'  ',DATE_FORMAT(fecha_accidente,'%Y-%m-%d %H:%i'),'  ',COALESCE(lugar,'')) AS nom
                  FROM accidentes ORDER BY id DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categorias(): array
    {
        $sql = "SELECT id, CONCAT(codigo,'  ',descripcion) AS nombre FROM categoria_vehiculos ORDER BY codigo";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcas(): array
    {
        return $this->pdo->query('SELECT id, nombre FROM marcas_vehiculo ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarVehiculos(string $q): array
    {
        $q = trim($q);
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $q));
        $buscarSinPlaca = in_array($normalized, ['SINPLACA', 'NOPLACA', 'SIN'], true);

        $st = $this->pdo->prepare(
            "SELECT id, placa, color, anio
               FROM vehiculos
              WHERE placa LIKE :raw
                 OR UPPER(REPLACE(REPLACE(placa, '-', ''), ' ', '')) LIKE :normalized
                 OR (:buscar_sin_placa = 1 AND placa LIKE 'SPLACA%')
              ORDER BY placa
              LIMIT 50"
        );
        $st->execute([
            ':raw' => '%' . $q . '%',
            ':normalized' => '%' . $normalized . '%',
            ':buscar_sin_placa' => $buscarSinPlaca ? 1 : 0,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function modelosPorMarca(int $marcaId): array
    {
        $st = $this->pdo->prepare('SELECT id, nombre FROM modelos_vehiculo WHERE marca_id=? ORDER BY nombre');
        $st->execute([$marcaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function tiposPorCategoria(int $categoriaId): array
    {
        $st = $this->pdo->prepare("SELECT id, CONCAT(codigo,'  ',nombre) AS nombre FROM tipos_vehiculo WHERE categoria_id=? ORDER BY codigo");
        $st->execute([$categoriaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function carroceriasPorTipo(int $tipoId): array
    {
        $st = $this->pdo->prepare('SELECT id, nombre FROM carroceria_vehiculo WHERE tipo_id=? ORDER BY nombre');
        $st->execute([$tipoId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCategoria(string $codigo, ?string $descripcion): int
    {
        $st = $this->pdo->prepare('INSERT INTO categoria_vehiculos(codigo,descripcion,creado_en) VALUES (?,?,NOW())');
        $st->execute([$codigo, $descripcion]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createTipo(int $categoriaId, string $codigo, string $nombre, ?string $descripcion): int
    {
        $st = $this->pdo->prepare('INSERT INTO tipos_vehiculo(categoria_id,codigo,nombre,descripcion,creado_en) VALUES (?,?,?,?,NOW())');
        $st->execute([$categoriaId, $codigo, $nombre, $descripcion]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createCarroceria(int $tipoId, string $nombre, ?string $descripcion): int
    {
        $st = $this->pdo->prepare('INSERT INTO carroceria_vehiculo(tipo_id,nombre,descripcion,creado_en) VALUES (?,?,?,NOW())');
        $st->execute([$tipoId, $nombre, $descripcion]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createMarca(string $nombre, ?string $pais): int
    {
        $st = $this->pdo->prepare('INSERT INTO marcas_vehiculo(nombre,pais_origen,creado_en) VALUES (?,?,NOW())');
        $st->execute([$nombre, $pais]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createModelo(int $marcaId, string $nombre): int
    {
        $st = $this->pdo->prepare('INSERT INTO modelos_vehiculo(marca_id,nombre,creado_en) VALUES (?,?,NOW())');
        $st->execute([$marcaId, $nombre]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createVehiculo(array $payload): int
    {
        $st = $this->pdo->prepare("INSERT INTO vehiculos
      (placa,serie_vin,nro_motor,categoria_id,tipo_id,carroceria_id,marca_id,modelo_id,anio,color,largo_mm,ancho_mm,alto_mm,notas,creado_en)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $st->execute([
            $payload['placa'],
            $payload['serie_vin'],
            $payload['nro_motor'],
            $payload['categoria_id'],
            $payload['tipo_id'],
            $payload['carroceria_id'],
            $payload['marca_id'],
            $payload['modelo_id'],
            $payload['anio'],
            $payload['color'],
            $payload['largo_mm'],
            $payload['ancho_mm'],
            $payload['alto_mm'],
            $payload['notas'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function ordenesParticipacionUsadas(int $accidenteId): array
    {
        $st = $this->pdo->prepare('SELECT orden_participacion FROM involucrados_vehiculos WHERE accidente_id=?');
        $st->execute([$accidenteId]);
        return array_map(fn($r) => (string) $r['orden_participacion'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function createInvolucradoVehiculo(int $accidenteId, int $vehiculoId, string $orden, string $tipo, ?string $observaciones): int
    {
        $st = $this->pdo->prepare('INSERT INTO involucrados_vehiculos(accidente_id,vehiculo_id,orden_participacion,tipo,observaciones) VALUES (?,?,?,?,?)');
        $st->execute([$accidenteId, $vehiculoId, $orden, $tipo, $observaciones]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createInvolucradosVehiculo(array $rows): array
    {
        $ids = [];
        $st = $this->pdo->prepare('INSERT INTO involucrados_vehiculos(accidente_id,vehiculo_id,orden_participacion,tipo,observaciones) VALUES (?,?,?,?,?)');

        $started = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }

        try {
            foreach ($rows as $row) {
                $st->execute([
                    $row['accidente_id'],
                    $row['vehiculo_id'],
                    $row['orden_participacion'],
                    $row['tipo'],
                    $row['observaciones'],
                ]);
                $ids[] = (int) $this->pdo->lastInsertId();
            }

            if ($started) {
                $this->pdo->commit();
            }

            return $ids;
        } catch (\Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function involucradoById(int $id): ?array
    {
        $sql = "SELECT iv.*, 
                       v.placa, v.color, v.anio,
                       a.id AS a_id, a.lugar, a.fecha_accidente
                  FROM involucrados_vehiculos iv
                  JOIN vehiculos v  ON v.id = iv.vehiculo_id
                  JOIN accidentes a ON a.id = iv.accidente_id
                 WHERE iv.id=?
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateInvolucradoVehiculo(int $id, int $vehiculoId, string $tipo, ?string $observaciones): void
    {
        $st = $this->pdo->prepare('UPDATE involucrados_vehiculos SET vehiculo_id=?, tipo=?, observaciones=? WHERE id=?');
        $st->execute([$vehiculoId, $tipo, $observaciones, $id]);
    }

    public function documentosVehiculo(int $involucradoVehiculoId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM documento_vehiculo WHERE involucrado_vehiculo_id=? ORDER BY id DESC');
        $st->execute([$involucradoVehiculoId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
