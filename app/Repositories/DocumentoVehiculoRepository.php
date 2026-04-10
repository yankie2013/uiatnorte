<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentoVehiculoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function involucradoInfo(int $involucradoVehiculoId): ?array
    {
        $sql = "SELECT iv.id AS invol_id, iv.vehiculo_id, v.placa, v.color, v.anio
                  FROM involucrados_vehiculos iv
             LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id
                 WHERE iv.id = :id
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $involucradoVehiculoId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT dv.*, iv.id AS invol_id, iv.vehiculo_id, v.placa, v.color, v.anio
                  FROM documento_vehiculo dv
                  JOIN involucrados_vehiculos iv ON iv.id = dv.involucrado_vehiculo_id
             LEFT JOIN vehiculos v ON v.id = dv.vehiculo_id
                 WHERE dv.id = :id
                 LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $sql = "INSERT INTO documento_vehiculo
            (involucrado_vehiculo_id, vehiculo_id,
             numero_propiedad, titulo_propiedad, partida_propiedad, sede_propiedad,
             numero_soat, aseguradora_soat, vigente_soat, vencimiento_soat,
             numero_revision, certificadora_revision, vigente_revision, vencimiento_revision,
             numero_peritaje, fecha_peritaje, perito_peritaje,
             sistema_electrico_peritaje, sistema_frenos_peritaje, sistema_direccion_peritaje,
             sistema_transmision_peritaje, sistema_suspension_peritaje, planta_motriz_peritaje,
             otros_peritaje, danos_peritaje)
            VALUES
            (:involucrado_vehiculo_id, :vehiculo_id,
             :numero_propiedad, :titulo_propiedad, :partida_propiedad, :sede_propiedad,
             :numero_soat, :aseguradora_soat, :vigente_soat, :vencimiento_soat,
             :numero_revision, :certificadora_revision, :vigente_revision, :vencimiento_revision,
             :numero_peritaje, :fecha_peritaje, :perito_peritaje,
             :sistema_electrico_peritaje, :sistema_frenos_peritaje, :sistema_direccion_peritaje,
             :sistema_transmision_peritaje, :sistema_suspension_peritaje, :planta_motriz_peritaje,
             :otros_peritaje, :danos_peritaje)";
        $st = $this->pdo->prepare($sql);
        $st->execute($payload);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        unset($payload[':involucrado_vehiculo_id']);
        $payload[':id'] = $id;
        $sql = "UPDATE documento_vehiculo SET
                  vehiculo_id = :vehiculo_id,
                  numero_propiedad = :numero_propiedad,
                  titulo_propiedad = :titulo_propiedad,
                  partida_propiedad = :partida_propiedad,
                  sede_propiedad = :sede_propiedad,
                  numero_soat = :numero_soat,
                  aseguradora_soat = :aseguradora_soat,
                  vigente_soat = :vigente_soat,
                  vencimiento_soat = :vencimiento_soat,
                  numero_revision = :numero_revision,
                  certificadora_revision = :certificadora_revision,
                  vigente_revision = :vigente_revision,
                  vencimiento_revision = :vencimiento_revision,
                  numero_peritaje = :numero_peritaje,
                  fecha_peritaje = :fecha_peritaje,
                  perito_peritaje = :perito_peritaje,
                  sistema_electrico_peritaje = :sistema_electrico_peritaje,
                  sistema_frenos_peritaje = :sistema_frenos_peritaje,
                  sistema_direccion_peritaje = :sistema_direccion_peritaje,
                  sistema_transmision_peritaje = :sistema_transmision_peritaje,
                  sistema_suspension_peritaje = :sistema_suspension_peritaje,
                  planta_motriz_peritaje = :planta_motriz_peritaje,
                  otros_peritaje = :otros_peritaje,
                  danos_peritaje = :danos_peritaje,
                  actualizado_en = NOW()
                WHERE id = :id";
        $st = $this->pdo->prepare($sql);
        $st->execute($payload);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM documento_vehiculo WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
    }
}
