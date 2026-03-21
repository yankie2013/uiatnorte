<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoOccisoRepository;
use InvalidArgumentException;

final class DocumentoOccisoService
{
    private const FIELDS = [
        'fecha_levantamiento',
        'hora_levantamiento',
        'lugar_levantamiento',
        'posicion_cuerpo_levantamiento',
        'lesiones_levantamiento',
        'presuntivo_levantamiento',
        'legista_levantamiento',
        'cmp_legista',
        'observaciones_levantamiento',
        'numero_pericial',
        'fecha_pericial',
        'hora_pericial',
        'observaciones_pericial',
        'numero_protocolo',
        'fecha_protocolo',
        'hora_protocolo',
        'lesiones_protocolo',
        'presuntivo_protocolo',
        'dosaje_protocolo',
        'toxicologico_protocolo',
        'nosocomio_epicrisis',
        'numero_historia_epicrisis',
        'tratamiento_epicrisis',
        'hora_alta_epicrisis',
    ];

    public function __construct(private DocumentoOccisoRepository $repository)
    {
    }

    public function detalle(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function listado(int $personaId, int $accidenteId): array
    {
        return $this->repository->list($personaId, $accidenteId);
    }

    public function personaOptions(): array
    {
        return $this->repository->personaOptions();
    }

    public function accidenteOptions(): array
    {
        return $this->repository->accidenteOptions();
    }

    public function crear(array $input): int
    {
        $personaId = (int) ($input['persona_id'] ?? 0);
        $accidenteId = (int) ($input['accidente_id'] ?? 0);
        if ($personaId <= 0 || $accidenteId <= 0) {
            throw new InvalidArgumentException('Selecciona persona y accidente');
        }
        return $this->repository->create($personaId, $accidenteId, $this->payload($input));
    }

    public function actualizar(int $id, array $input): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('No encontrado');
        }
        $this->repository->update($id, $this->payload($input));
    }

    public function eliminar(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('No encontrado');
        }
        $this->repository->delete($id);
    }

    public function mergeOld(array $base, array $input): array
    {
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $base[$field] = trim((string) $input[$field]);
            }
        }
        if (array_key_exists('persona_id', $input)) {
            $base['persona_id'] = (int) $input['persona_id'];
        }
        if (array_key_exists('accidente_id', $input)) {
            $base['accidente_id'] = (int) $input['accidente_id'];
        }
        return $base;
    }

    private function payload(array $input): array
    {
        $payload = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            $payload[$field] = $value === '' ? null : $value;
        }
        return $payload;
    }
}
