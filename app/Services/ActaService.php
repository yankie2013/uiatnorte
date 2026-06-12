<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActaRepository;
use InvalidArgumentException;

final class ActaService
{
    public const TIPO_ENTREGA_VEHICULO = 'Acta de entrega de vehiculo';
    public const ESTADOS = ['Pendiente', 'Realizada', 'Anulada'];

    public function __construct(private ActaRepository $repository)
    {
    }

    public function context(int $accidenteId): array
    {
        return [
            'vehicles' => $this->repository->actaVehicleOptions($accidenteId),
        ];
    }

    public function defaults(?array $row = null, ?int $accidenteId = null): array
    {
        $start = date('H:i');
        return [
            'accidente_id' => $row['accidente_id'] ?? $accidenteId ?? '',
            'tipo' => $row['tipo'] ?? self::TIPO_ENTREGA_VEHICULO,
            'involucrado_vehiculo_id' => $row['involucrado_vehiculo_id'] ?? '',
            'conductor_involucrado_persona_id' => $row['conductor_involucrado_persona_id'] ?? '',
            'propietario_vehiculo_id' => $row['propietario_vehiculo_id'] ?? '',
            'fecha_entrega' => $row['fecha_entrega'] ?? date('Y-m-d'),
            'hora_inicio' => substr((string) ($row['hora_inicio'] ?? $start), 0, 5),
            'hora_culminacion' => substr((string) ($row['hora_culminacion'] ?? date('H:i', strtotime($start . ' +20 minutes'))), 0, 5),
            'estado' => $row['estado'] ?? 'Pendiente',
            'observaciones' => $row['observaciones'] ?? '',
        ];
    }

    public function create(array $input): int
    {
        return $this->repository->create($this->payload($input, null));
    }

    public function update(int $id, array $input): void
    {
        $current = $this->repository->find($id);
        if ($current === null) {
            throw new InvalidArgumentException('Acta no encontrada.');
        }
        $this->repository->update($id, $this->payload($input, $current));
    }

    public function delete(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Acta no encontrada.');
        }
        $this->repository->delete($id);
    }

    private function payload(array $input, ?array $current): array
    {
        $accidenteId = (int) ($input['accidente_id'] ?? 0);
        $vehicleId = (int) ($input['involucrado_vehiculo_id'] ?? 0);
        $conductorId = $this->repository->automaticConductor($accidenteId, $vehicleId) ?? 0;
        $ownerId = $this->repository->automaticOwner($accidenteId, $vehicleId) ?? 0;
        $useConductorAsOwner = (int) ($input['usar_conductor_como_propietario'] ?? 0) === 1;
        $date = (string) ($current['fecha_entrega'] ?? date('Y-m-d'));
        $start = trim((string) ($input['hora_inicio'] ?? ''));
        $end = $start !== '' ? date('H:i', strtotime($start . ' +20 minutes')) : '';
        $state = (string) ($current['estado'] ?? 'Pendiente');

        if ($accidenteId <= 0 || $vehicleId <= 0 || $conductorId <= 0) {
            throw new InvalidArgumentException('El vehiculo seleccionado no tiene un conductor registrado.');
        }
        if (!$this->repository->vehicleBelongs($accidenteId, $vehicleId)) {
            throw new InvalidArgumentException('El vehiculo no pertenece al caso.');
        }
        if (!$this->repository->conductorBelongs($accidenteId, $conductorId, $vehicleId)) {
            throw new InvalidArgumentException('El conductor seleccionado no corresponde al vehiculo.');
        }
        if ($ownerId <= 0 && !$useConductorAsOwner) {
            throw new InvalidArgumentException('El vehiculo no tiene propietario registrado. Confirma si deseas consignar al conductor como propietario.');
        }
        if ($ownerId > 0) {
            if (!$this->repository->ownerBelongs($accidenteId, $ownerId, $vehicleId)) {
                throw new InvalidArgumentException('El propietario seleccionado no corresponde al vehiculo.');
            }
            if (!$this->repository->juridicalOwnerHasRepresentative($ownerId)) {
                throw new InvalidArgumentException('La persona juridica debe tener un representante legal registrado.');
            }
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
            throw new InvalidArgumentException('Completa una fecha y horas validas.');
        }
        if (strtotime($date . ' ' . $end) <= strtotime($date . ' ' . $start)) {
            throw new InvalidArgumentException('La hora de culminacion debe ser posterior a la hora de inicio.');
        }
        if (!in_array($state, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado invalido.');
        }

        return [
            'accidente_id' => $accidenteId,
            'tipo' => self::TIPO_ENTREGA_VEHICULO,
            'involucrado_vehiculo_id' => $vehicleId,
            'conductor_involucrado_persona_id' => $conductorId,
            'propietario_vehiculo_id' => $ownerId > 0 ? $ownerId : null,
            'fecha_entrega' => $date,
            'hora_inicio' => $start,
            'hora_culminacion' => $end,
            'estado' => $state,
            'observaciones' => $current['observaciones'] ?? null,
        ];
    }
}
