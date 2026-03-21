<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ItpRepository;
use InvalidArgumentException;

final class ItpService
{
    public function __construct(private ItpRepository $repository)
    {
    }

    public function defaultData(?array $row = null, ?int $accidenteId = null): array
    {
        return [
            'accidente_id' => $row['accidente_id'] ?? ($accidenteId ?: ''),
            'fecha_itp' => $row['fecha_itp'] ?? date('Y-m-d'),
            'hora_itp' => $row['hora_itp'] ?? date('H:i'),
            'ocurrencia_policial' => $row['ocurrencia_policial'] ?? '',
            'llegada_lugar' => $row['llegada_lugar'] ?? '',
            'localizacion_unidades' => $row['localizacion_unidades'] ?? '',
            'forma_via' => $row['forma_via'] ?? '',
            'punto_referencia' => $row['punto_referencia'] ?? '',
            'ubicacion_gps' => $row['ubicacion_gps'] ?? '',
            'descripcion_via1' => $row['descripcion_via1'] ?? '',
            'configuracion_via1' => $row['configuracion_via1'] ?? '',
            'material_via1' => $row['material_via1'] ?? '',
            'senializacion_via1' => $row['senializacion_via1'] ?? '',
            'ordenamiento_via1' => $row['ordenamiento_via1'] ?? '',
            'iluminacion_via1' => $row['iluminacion_via1'] ?? '',
            'visibilidad_via1' => $row['visibilidad_via1'] ?? '',
            'intensidad_via1' => $row['intensidad_via1'] ?? '',
            'fluidez_via1' => $row['fluidez_via1'] ?? '',
            'medidas_via1' => $row['medidas_via1'] ?? '',
            'observaciones_via1' => $row['observaciones_via1'] ?? '',
            'via2_flag' => $this->via2Flag($row),
            'descripcion_via2' => $row['descripcion_via2'] ?? '',
            'configuracion_via2' => $row['configuracion_via2'] ?? '',
            'material_via2' => $row['material_via2'] ?? '',
            'senializacion_via2' => $row['senializacion_via2'] ?? '',
            'ordenamiento_via2' => $row['ordenamiento_via2'] ?? '',
            'iluminacion_via2' => $row['iluminacion_via2'] ?? '',
            'visibilidad_via2' => $row['visibilidad_via2'] ?? '',
            'intensidad_via2' => $row['intensidad_via2'] ?? '',
            'fluidez_via2' => $row['fluidez_via2'] ?? '',
            'medidas_via2' => $row['medidas_via2'] ?? '',
            'observaciones_via2' => $row['observaciones_via2'] ?? '',
            'evidencia_biologica' => $row['evidencia_biologica'] ?? '',
            'evidencia_fisica' => $row['evidencia_fisica'] ?? '',
            'evidencia_material' => $row['evidencia_material'] ?? '',
        ];
    }

    public function formContext(?int $accidenteId = null): array
    {
        return [
            'accidente' => ($accidenteId !== null && $accidenteId > 0) ? $this->repository->accidenteHeader($accidenteId) : null,
            'accidentes' => ($accidenteId !== null && $accidenteId > 0) ? [] : $this->repository->accidentesDisponibles(),
        ];
    }

    public function detalle(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Falta id.');
        }
        return $this->repository->detail($id);
    }

    public function listado(array $filters): array
    {
        return $this->repository->list([
            'accidente_id' => (int) ($filters['accidente_id'] ?? 0),
            'q' => trim((string) ($filters['q'] ?? '')),
        ]);
    }

    public function create(array $input): int
    {
        $payload = $this->payload($input, null);
        return $this->repository->create($payload);
    }

    public function update(int $id, array $input): void
    {
        $current = $this->repository->find($id);
        if ($current === null) {
            throw new InvalidArgumentException('ITP no encontrado.');
        }
        $payload = $this->payload(array_merge($current, $input), $id);
        unset($payload[':accidente_id']);
        $this->repository->update($id, $payload);
    }

    public function delete(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('ITP no encontrado.');
        }
        $this->repository->delete($id);
    }

    private function payload(array $input, ?int $id): array
    {
        $accidenteId = (int) ($input['accidente_id'] ?? 0);
        if ($id === null) {
            if ($accidenteId <= 0) {
                throw new InvalidArgumentException('Debe seleccionar un accidente valido.');
            }
            if ($this->repository->accidenteHeader($accidenteId) === null) {
                throw new InvalidArgumentException('El accidente indicado no existe.');
            }
        }

        $via2Flag = (int) ($input['via2_flag'] ?? 0) === 1;

        return [
            ':accidente_id' => $accidenteId,
            ':fecha_itp' => $this->nullableDate($input['fecha_itp'] ?? null),
            ':hora_itp' => $this->nullableTime($input['hora_itp'] ?? null),
            ':ocurrencia_policial' => $this->nullableTrim($input['ocurrencia_policial'] ?? null),
            ':llegada_lugar' => $this->nullableTrim($input['llegada_lugar'] ?? null),
            ':localizacion_unidades' => $this->nullableTrim($input['localizacion_unidades'] ?? null),
            ':forma_via' => $this->nullableTrim($input['forma_via'] ?? null),
            ':punto_referencia' => $this->nullableTrim($input['punto_referencia'] ?? null),
            ':ubicacion_gps' => $this->nullableTrim($input['ubicacion_gps'] ?? null),
            ':descripcion_via1' => $this->nullableTrim($input['descripcion_via1'] ?? null),
            ':configuracion_via1' => $this->nullableTrim($input['configuracion_via1'] ?? null),
            ':material_via1' => $this->nullableTrim($input['material_via1'] ?? null),
            ':senializacion_via1' => $this->nullableTrim($input['senializacion_via1'] ?? null),
            ':ordenamiento_via1' => $this->nullableTrim($input['ordenamiento_via1'] ?? null),
            ':iluminacion_via1' => $this->nullableTrim($input['iluminacion_via1'] ?? null),
            ':visibilidad_via1' => $this->nullableTrim($input['visibilidad_via1'] ?? null),
            ':intensidad_via1' => $this->nullableTrim($input['intensidad_via1'] ?? null),
            ':fluidez_via1' => $this->nullableTrim($input['fluidez_via1'] ?? null),
            ':medidas_via1' => $this->nullableTrim($input['medidas_via1'] ?? null),
            ':observaciones_via1' => $this->nullableTrim($input['observaciones_via1'] ?? null),
            ':descripcion_via2' => $via2Flag ? $this->nullableTrim($input['descripcion_via2'] ?? null) : null,
            ':configuracion_via2' => $via2Flag ? $this->nullableTrim($input['configuracion_via2'] ?? null) : null,
            ':material_via2' => $via2Flag ? $this->nullableTrim($input['material_via2'] ?? null) : null,
            ':senializacion_via2' => $via2Flag ? $this->nullableTrim($input['senializacion_via2'] ?? null) : null,
            ':ordenamiento_via2' => $via2Flag ? $this->nullableTrim($input['ordenamiento_via2'] ?? null) : null,
            ':iluminacion_via2' => $via2Flag ? $this->nullableTrim($input['iluminacion_via2'] ?? null) : null,
            ':visibilidad_via2' => $via2Flag ? $this->nullableTrim($input['visibilidad_via2'] ?? null) : null,
            ':intensidad_via2' => $via2Flag ? $this->nullableTrim($input['intensidad_via2'] ?? null) : null,
            ':fluidez_via2' => $via2Flag ? $this->nullableTrim($input['fluidez_via2'] ?? null) : null,
            ':medidas_via2' => $via2Flag ? $this->nullableTrim($input['medidas_via2'] ?? null) : null,
            ':observaciones_via2' => $via2Flag ? $this->nullableTrim($input['observaciones_via2'] ?? null) : null,
            ':evidencia_biologica' => $this->nullableTrim($input['evidencia_biologica'] ?? null),
            ':evidencia_fisica' => $this->nullableTrim($input['evidencia_fisica'] ?? null),
            ':evidencia_material' => $this->nullableTrim($input['evidencia_material'] ?? null),
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            throw new InvalidArgumentException('Fecha de ITP invalida.');
        }
        return $text;
    }

    private function nullableTime(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $text)) {
            throw new InvalidArgumentException('Hora de ITP invalida.');
        }
        return $text;
    }

    private function via2Flag(?array $row): int
    {
        if ($row === null) {
            return 0;
        }
        foreach ([
            'descripcion_via2','configuracion_via2','material_via2','senializacion_via2','ordenamiento_via2',
            'iluminacion_via2','visibilidad_via2','intensidad_via2','fluidez_via2','medidas_via2','observaciones_via2'
        ] as $field) {
            if (!empty($row[$field])) {
                return 1;
            }
        }
        return 0;
    }
}
