<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ActaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listByAccidente(int $accidenteId): array
    {
        $st = $this->pdo->prepare($this->detailSql() . ' WHERE ac.accidente_id = ? ORDER BY ac.fecha_entrega DESC, ac.hora_inicio DESC, ac.id DESC');
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare($this->detailSql() . ' WHERE ac.id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function vehicles(int $accidenteId): array
    {
        $st = $this->pdo->prepare(
            "SELECT iv.id, iv.vehiculo_id, iv.orden_participacion, v.placa, v.color, v.anio
               FROM involucrados_vehiculos iv
               JOIN vehiculos v ON v.id = iv.vehiculo_id
              WHERE iv.accidente_id = ?
           ORDER BY FIELD(iv.orden_participacion,'UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7'), v.placa"
        );
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function actaVehicleOptions(int $accidenteId): array
    {
        $rows = $this->vehicles($accidenteId);
        $options = [];
        $used = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (isset($used[$id])) {
                continue;
            }
            $detail = $this->deliveryVehicles($id);
            foreach ($detail as $component) {
                $used[(int) $component['involucrado_vehiculo_id']] = true;
            }
            if ($detail === []) {
                continue;
            }
            $primary = $detail[0];
            $options[] = [
                'id' => (int) $primary['involucrado_vehiculo_id'],
                'vehiculo_id' => (int) $primary['vehiculo_id'],
                'orden_participacion' => (string) $primary['orden_participacion'],
                'placa' => implode('/', array_filter(array_column($detail, 'placa'))),
                'color' => implode('/', array_filter(array_column($detail, 'color'))),
                'anio' => implode('/', array_filter(array_map('strval', array_column($detail, 'anio')))),
                'clase' => implode('/', array_filter(array_column($detail, 'clase'))),
                'es_combinado' => count($detail) > 1,
                'tiene_propietario' => $this->automaticOwner($accidenteId, (int) $primary['involucrado_vehiculo_id']) !== null,
            ];
        }
        return $options;
    }

    public function deliveryVehicles(int $involucradoVehiculoId): array
    {
        $selected = $this->vehicleDetail($involucradoVehiculoId);
        if ($selected === null) {
            return [];
        }
        if (!in_array((string) $selected['involucrado_tipo'], ['Combinado vehicular 1', 'Combinado vehicular 2'], true)) {
            return [$selected];
        }

        $otherType = $selected['involucrado_tipo'] === 'Combinado vehicular 1'
            ? 'Combinado vehicular 2'
            : 'Combinado vehicular 1';
        $st = $this->pdo->prepare(
            $this->vehicleDetailSql() .
            " WHERE iv.accidente_id=? AND iv.id<>? AND iv.tipo=?
              ORDER BY (iv.orden_participacion=?) DESC, iv.id ASC
              LIMIT 1"
        );
        $st->execute([
            (int) $selected['accidente_id'],
            $involucradoVehiculoId,
            $otherType,
            (string) $selected['orden_participacion'],
        ]);
        $pair = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        $vehicles = [$selected];
        if ($pair !== null) {
            if ($selected['involucrado_tipo'] === 'Combinado vehicular 2') {
                array_unshift($vehicles, $pair);
            } else {
                $vehicles[] = $pair;
            }
        }
        return $vehicles;
    }

    public function conductors(int $accidenteId): array
    {
        $st = $this->pdo->prepare(
            "SELECT ip.id, ip.vehiculo_id,
                    COALESCE((
                        SELECT primary_iv.vehiculo_id
                          FROM involucrados_vehiculos own_iv
                          JOIN involucrados_vehiculos primary_iv ON primary_iv.accidente_id=own_iv.accidente_id AND primary_iv.tipo='Combinado vehicular 1'
                         WHERE own_iv.accidente_id=ip.accidente_id AND own_iv.vehiculo_id=ip.vehiculo_id AND own_iv.tipo='Combinado vehicular 2'
                      ORDER BY (primary_iv.orden_participacion=own_iv.orden_participacion) DESC, primary_iv.id ASC
                         LIMIT 1
                    ), ip.vehiculo_id) AS acta_vehiculo_id,
                    p.tipo_doc, p.num_doc, p.nombres, p.apellido_paterno, p.apellido_materno
               FROM involucrados_personas ip
               JOIN personas p ON p.id = ip.persona_id
               JOIN participacion_persona pp ON pp.Id = ip.rol_id
              WHERE ip.accidente_id = ? AND LOWER(pp.Nombre) = 'conductor'
           ORDER BY p.apellido_paterno, p.apellido_materno, p.nombres"
        );
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function owners(int $accidenteId): array
    {
        $st = $this->pdo->prepare(
            "SELECT pv.id, pv.vehiculo_inv_id,
                    COALESCE((
                        SELECT primary_iv.id
                          FROM involucrados_vehiculos own_iv
                          JOIN involucrados_vehiculos primary_iv ON primary_iv.accidente_id=own_iv.accidente_id AND primary_iv.tipo='Combinado vehicular 1'
                         WHERE own_iv.id=pv.vehiculo_inv_id AND own_iv.tipo='Combinado vehicular 2'
                      ORDER BY (primary_iv.orden_participacion=own_iv.orden_participacion) DESC, primary_iv.id ASC
                         LIMIT 1
                    ), pv.vehiculo_inv_id) AS acta_vehiculo_inv_id,
                    pv.tipo_propietario, pv.ruc, pv.razon_social, pv.rol_legal,
                    p.tipo_doc, p.num_doc, p.nombres, p.apellido_paterno, p.apellido_materno,
                    rp.tipo_doc AS representante_tipo_doc, rp.num_doc AS representante_num_doc,
                    rp.nombres AS representante_nombres, rp.apellido_paterno AS representante_apellido_paterno,
                    rp.apellido_materno AS representante_apellido_materno
               FROM propietario_vehiculo pv
          LEFT JOIN personas p ON p.id = pv.propietario_persona_id
          LEFT JOIN personas rp ON rp.id = pv.representante_persona_id
              WHERE pv.accidente_id = ?
           ORDER BY pv.id"
        );
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO actas (accidente_id, tipo, involucrado_vehiculo_id, conductor_involucrado_persona_id, propietario_vehiculo_id, fecha_entrega, hora_inicio, hora_culminacion, estado, observaciones)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute($this->values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            'UPDATE actas SET accidente_id=?, tipo=?, involucrado_vehiculo_id=?, conductor_involucrado_persona_id=?, propietario_vehiculo_id=?, fecha_entrega=?, hora_inicio=?, hora_culminacion=?, estado=?, observaciones=? WHERE id=? LIMIT 1'
        );
        $st->execute([...$this->values($data), $id]);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM actas WHERE id=? LIMIT 1');
        $st->execute([$id]);
    }

    public function vehicleBelongs(int $accidenteId, int $involucradoVehiculoId): bool
    {
        return $this->exists('SELECT COUNT(*) FROM involucrados_vehiculos WHERE accidente_id=? AND id=?', [$accidenteId, $involucradoVehiculoId]);
    }

    public function conductorBelongs(int $accidenteId, int $involucradoPersonaId, int $involucradoVehiculoId): bool
    {
        $vehicleIds = array_column($this->deliveryVehicles($involucradoVehiculoId), 'vehiculo_id');
        if ($vehicleIds === []) return false;
        $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
        return $this->exists(
            "SELECT COUNT(*) FROM involucrados_personas ip
               JOIN participacion_persona pp ON pp.Id=ip.rol_id
              WHERE ip.accidente_id=? AND ip.id=? AND ip.vehiculo_id IN ({$placeholders}) AND LOWER(pp.Nombre)='conductor'",
            [$accidenteId, $involucradoPersonaId, ...$vehicleIds]
        );
    }

    public function ownerBelongs(int $accidenteId, int $ownerId, int $involucradoVehiculoId): bool
    {
        $vehicleIds = array_column($this->deliveryVehicles($involucradoVehiculoId), 'involucrado_vehiculo_id');
        if ($vehicleIds === []) return false;
        $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
        return $this->exists(
            "SELECT COUNT(*) FROM propietario_vehiculo WHERE accidente_id=? AND id=? AND vehiculo_inv_id IN ({$placeholders})",
            [$accidenteId, $ownerId, ...$vehicleIds]
        );
    }

    public function juridicalOwnerHasRepresentative(int $ownerId): bool
    {
        return $this->exists(
            "SELECT COUNT(*) FROM propietario_vehiculo
              WHERE id=? AND (tipo_propietario<>'JURIDICA' OR representante_persona_id>0)",
            [$ownerId]
        );
    }

    public function automaticConductor(int $accidenteId, int $involucradoVehiculoId): ?int
    {
        $vehicleIds = array_column($this->deliveryVehicles($involucradoVehiculoId), 'vehiculo_id');
        if ($vehicleIds === []) {
            return null;
        }
        $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
        $st = $this->pdo->prepare(
            "SELECT ip.id
               FROM involucrados_personas ip
               JOIN participacion_persona pp ON pp.Id=ip.rol_id
              WHERE ip.accidente_id=? AND ip.vehiculo_id IN ({$placeholders}) AND LOWER(pp.Nombre)='conductor'
           ORDER BY FIELD(ip.vehiculo_id, {$placeholders}), ip.id ASC
              LIMIT 1"
        );
        $st->execute([$accidenteId, ...$vehicleIds, ...$vehicleIds]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function automaticOwner(int $accidenteId, int $involucradoVehiculoId): ?int
    {
        $vehicleIds = array_column($this->deliveryVehicles($involucradoVehiculoId), 'involucrado_vehiculo_id');
        if ($vehicleIds === []) {
            return null;
        }
        $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
        $st = $this->pdo->prepare(
            "SELECT pv.id
               FROM propietario_vehiculo pv
              WHERE pv.accidente_id=? AND pv.vehiculo_inv_id IN ({$placeholders})
           ORDER BY FIELD(pv.vehiculo_inv_id, {$placeholders}), pv.id ASC
              LIMIT 1"
        );
        $st->execute([$accidenteId, ...$vehicleIds, ...$vehicleIds]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function actaVehicleResolution(int $accidenteId, int $involucradoVehiculoId): array
    {
        return [
            'conductor_id' => $this->automaticConductor($accidenteId, $involucradoVehiculoId),
            'propietario_id' => $this->automaticOwner($accidenteId, $involucradoVehiculoId),
        ];
    }

    private function exists(string $sql, array $params): bool
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    private function values(array $data): array
    {
        return [
            $data['accidente_id'], $data['tipo'], $data['involucrado_vehiculo_id'],
            $data['conductor_involucrado_persona_id'], $data['propietario_vehiculo_id'],
            $data['fecha_entrega'], $data['hora_inicio'], $data['hora_culminacion'],
            $data['estado'], $data['observaciones'],
        ];
    }

    private function detailSql(): string
    {
        return "SELECT ac.*, iv.vehiculo_id, iv.orden_participacion, v.placa, v.color, v.anio, v.serie_vin, v.nro_motor,
                       mv.nombre AS vehiculo_marca, modv.nombre AS vehiculo_modelo, tv.nombre AS vehiculo_tipo,
                       a.registro_sidpol, a.lugar, ud.nombre AS accidente_distrito,
                       cp.tipo_doc AS conductor_tipo_doc, cp.num_doc AS conductor_num_doc,
                       cp.nombres AS conductor_nombres, cp.apellido_paterno AS conductor_apellido_paterno, cp.apellido_materno AS conductor_apellido_materno,
                       cp.domicilio AS conductor_domicilio, cp.celular AS conductor_celular, cp.email AS conductor_email,
                       pv.tipo_propietario, pv.ruc, pv.razon_social, pv.domicilio_fiscal, pv.rol_legal,
                       pp.tipo_doc AS propietario_tipo_doc, pp.num_doc AS propietario_num_doc,
                       pp.nombres AS propietario_nombres, pp.apellido_paterno AS propietario_apellido_paterno, pp.apellido_materno AS propietario_apellido_materno,
                       pp.domicilio AS propietario_domicilio, pp.celular AS propietario_celular, pp.email AS propietario_email,
                       rp.tipo_doc AS representante_tipo_doc, rp.num_doc AS representante_num_doc,
                       rp.nombres AS representante_nombres, rp.apellido_paterno AS representante_apellido_paterno, rp.apellido_materno AS representante_apellido_materno,
                       rp.domicilio AS representante_domicilio, rp.celular AS representante_celular, rp.email AS representante_email
                  FROM actas ac
                  JOIN accidentes a ON a.id=ac.accidente_id
             LEFT JOIN ubigeo_distrito ud ON ud.cod_dep=a.cod_dep AND ud.cod_prov=a.cod_prov AND ud.cod_dist=a.cod_dist
                  JOIN involucrados_vehiculos iv ON iv.id=ac.involucrado_vehiculo_id
                  JOIN vehiculos v ON v.id=iv.vehiculo_id
             LEFT JOIN marcas_vehiculo mv ON mv.id=v.marca_id
             LEFT JOIN modelos_vehiculo modv ON modv.id=v.modelo_id
             LEFT JOIN tipos_vehiculo tv ON tv.id=v.tipo_id
                  JOIN involucrados_personas cip ON cip.id=ac.conductor_involucrado_persona_id
                  JOIN personas cp ON cp.id=cip.persona_id
             LEFT JOIN propietario_vehiculo pv ON pv.id=ac.propietario_vehiculo_id
             LEFT JOIN personas pp ON pp.id=pv.propietario_persona_id
             LEFT JOIN personas rp ON rp.id=pv.representante_persona_id";
    }

    private function vehicleDetail(int $involucradoVehiculoId): ?array
    {
        $st = $this->pdo->prepare($this->vehicleDetailSql() . ' WHERE iv.id=? LIMIT 1');
        $st->execute([$involucradoVehiculoId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function vehicleDetailSql(): string
    {
        return "SELECT iv.id AS involucrado_vehiculo_id, iv.accidente_id, iv.orden_participacion, iv.tipo AS involucrado_tipo,
                       v.id AS vehiculo_id, v.placa, v.color, v.anio, v.serie_vin, v.nro_motor,
                       v.largo_mm, v.ancho_mm, v.alto_mm,
                       cv.descripcion AS categoria, tv.nombre AS clase, car.nombre AS carroceria,
                       mv.nombre AS marca, modv.nombre AS modelo
                  FROM involucrados_vehiculos iv
                  JOIN vehiculos v ON v.id=iv.vehiculo_id
             LEFT JOIN categoria_vehiculos cv ON cv.id=v.categoria_id
             LEFT JOIN tipos_vehiculo tv ON tv.id=v.tipo_id
             LEFT JOIN carroceria_vehiculo car ON car.id=v.carroceria_id
             LEFT JOIN marcas_vehiculo mv ON mv.id=v.marca_id
             LEFT JOIN modelos_vehiculo modv ON modv.id=v.modelo_id";
    }
}
