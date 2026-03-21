<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PolicialIntervinienteRepository;
use InvalidArgumentException;

final class PolicialIntervinienteService
{
    public function __construct(private PolicialIntervinienteRepository $repository)
    {
    }

    public function formContext(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            throw new InvalidArgumentException('Falta accidente_id.');
        }
        return [
            'accidente' => $this->repository->accidenteHeader($accidenteId),
        ];
    }

    public function defaultData(?array $row = null, ?int $accidenteId = null): array
    {
        return [
            'accidente_id' => $row['accidente_id'] ?? ($accidenteId ?: ''),
            'persona_id' => $row['persona_id'] ?? '',
            'num_doc' => $row['num_doc'] ?? '',
            'nombre_persona' => trim((string) (($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ' ' . ($row['nombres'] ?? ''))),
            'domicilio' => $row['domicilio'] ?? '',
            'celular' => $row['celular'] ?? '',
            'email' => $row['email'] ?? '',
            'grado_policial' => $row['grado_policial'] ?? '',
            'cip' => $row['cip'] ?? '',
            'dependencia_policial' => $row['dependencia_policial'] ?? '',
            'rol_funcion' => $row['rol_funcion'] ?? 'INTERVINIENTE',
            'observaciones' => $row['observaciones'] ?? '',
        ];
    }

    public function personaPorDni(string $dni): ?array
    {
        $dni = trim($dni);
        if (!preg_match('/^\d{8}$/', $dni)) {
            throw new InvalidArgumentException('DNI invalido.');
        }
        return $this->repository->personaByDni($dni);
    }

    public function personaPorId(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID invalido.');
        }
        return $this->repository->personaById($id);
    }

    public function listado(?int $accidenteId = null): array
    {
        if ($accidenteId !== null && $accidenteId > 0) {
            return $this->repository->listByAccidente($accidenteId);
        }
        return $this->repository->listAll();
    }

    public function detalle(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Falta id.');
        }
        return $this->repository->detail($id);
    }

    public function create(array $input): int
    {
        $payload = $this->payload($input, null);
        $this->repository->updatePersonaContact((int) $payload['persona_id'], $payload['celular'], $payload['email']);
        return $this->repository->create($payload);
    }

    public function update(int $id, array $input): void
    {
        $current = $this->repository->find($id);
        if ($current === null) {
            throw new InvalidArgumentException('Registro no encontrado.');
        }
        $payload = $this->payload($input, $id);
        $this->repository->updatePersonaContact((int) $payload['persona_id'], $payload['celular'], $payload['email']);
        $this->repository->update($id, $payload);
    }

    public function delete(int $id, int $accidenteId): void
    {
        if ($id <= 0 || $accidenteId <= 0) {
            throw new InvalidArgumentException('Parametros invalidos.');
        }
        $row = $this->repository->find($id);
        if ($row === null) {
            throw new InvalidArgumentException('Registro no encontrado.');
        }
        if ((int) ($row['accidente_id'] ?? 0) !== $accidenteId) {
            throw new InvalidArgumentException('El registro no pertenece a este accidente.');
        }
        $this->repository->delete($id, $accidenteId);
    }

    private function payload(array $input, ?int $excludeId): array
    {
        $payload = [
            'accidente_id' => (int) ($input['accidente_id'] ?? 0),
            'persona_id' => (int) ($input['persona_id'] ?? 0),
            'grado_policial' => trim((string) ($input['grado_policial'] ?? '')),
            'cip' => trim((string) ($input['cip'] ?? '')),
            'dependencia_policial' => trim((string) ($input['dependencia_policial'] ?? '')),
            'rol_funcion' => $this->nullableTrim($input['rol_funcion'] ?? 'INTERVINIENTE') ?? 'INTERVINIENTE',
            'observaciones' => $this->nullableTrim($input['observaciones'] ?? null),
            'celular' => $this->nullableTrim($input['celular'] ?? null),
            'email' => $this->nullableTrim($input['email'] ?? null),
        ];

        if ($payload['accidente_id'] <= 0) {
            throw new InvalidArgumentException('Falta el accidente.');
        }
        if ($payload['persona_id'] <= 0) {
            throw new InvalidArgumentException('Debes seleccionar o crear la persona.');
        }
        if ($payload['grado_policial'] === '') {
            throw new InvalidArgumentException('Ingresa el grado policial.');
        }
        if ($payload['cip'] === '') {
            throw new InvalidArgumentException('Ingresa el CIP.');
        }
        if ($payload['dependencia_policial'] === '') {
            throw new InvalidArgumentException('Ingresa la dependencia policial.');
        }
        if ($payload['email'] !== null && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El email no es valido.');
        }
        if ($this->repository->existsDuplicate($payload['accidente_id'], $payload['persona_id'], $excludeId)) {
            throw new InvalidArgumentException('Esta persona ya fue registrada en este accidente.');
        }

        return $payload;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }
}
