<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AbogadoRepository;
use InvalidArgumentException;

final class AbogadoService
{
    public function __construct(private AbogadoRepository $repository)
    {
    }

    public function formContext(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            throw new InvalidArgumentException('Falta accidente_id.');
        }

        return [
            'accidente' => $this->repository->accidenteHeader($accidenteId),
            'personas' => $this->repository->personaOptionsByAccidente($accidenteId),
        ];
    }

    public function defaultData(?array $row = null, ?int $accidenteId = null): array
    {
        return [
            'accidente_id' => $row['accidente_id'] ?? ($accidenteId ?: ''),
            'persona_id' => $row['persona_id'] ?? '',
            'nombres' => $row['nombres'] ?? '',
            'apellido_paterno' => $row['apellido_paterno'] ?? '',
            'apellido_materno' => $row['apellido_materno'] ?? '',
            'colegiatura' => $row['colegiatura'] ?? '',
            'registro' => $row['registro'] ?? '',
            'casilla_electronica' => $row['casilla_electronica'] ?? '',
            'domicilio_procesal' => $row['domicilio_procesal'] ?? '',
            'celular' => $row['celular'] ?? '',
            'email' => $row['email'] ?? '',
        ];
    }

    public function listado(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            throw new InvalidArgumentException('Falta accidente_id.');
        }

        return $this->repository->listByAccidente($accidenteId);
    }

    public function detalle(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Falta id.');
        }

        return $this->repository->detail($id);
    }

    public function abogado(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Falta id.');
        }

        return $this->repository->find($id);
    }

    public function create(array $input): int
    {
        $payload = $this->payload($input);
        $this->assertPersonaPerteneceAlAccidente((int) $payload['accidente_id'], (int) $payload['persona_id']);
        return $this->repository->create($payload);
    }

    public function update(int $id, array $input): void
    {
        $current = $this->repository->find($id);
        if ($current === null) {
            throw new InvalidArgumentException('Abogado no encontrado.');
        }

        $payload = $this->payload($input);
        $this->assertPersonaPerteneceAlAccidente((int) $payload['accidente_id'], (int) $payload['persona_id']);
        $this->repository->update($id, $payload);
    }

    public function delete(int $id, int $accidenteId): void
    {
        if ($id <= 0 || $accidenteId <= 0) {
            throw new InvalidArgumentException('Parametros invalidos.');
        }

        $row = $this->repository->find($id);
        if ($row === null) {
            throw new InvalidArgumentException('No se encontro el abogado.');
        }
        if ((int) ($row['accidente_id'] ?? 0) !== $accidenteId) {
            throw new InvalidArgumentException('El abogado no pertenece a este accidente.');
        }

        $this->repository->delete($id, $accidenteId);
    }

    private function payload(array $input): array
    {
        $payload = [
            'accidente_id' => (int) ($input['accidente_id'] ?? 0),
            'persona_id' => (int) ($input['persona_id'] ?? 0),
            'nombres' => trim((string) ($input['nombres'] ?? '')),
            'apellido_paterno' => trim((string) ($input['apellido_paterno'] ?? '')),
            'apellido_materno' => $this->nullableTrim($input['apellido_materno'] ?? null),
            'colegiatura' => trim((string) ($input['colegiatura'] ?? '')),
            'registro' => $this->nullableTrim($input['registro'] ?? null),
            'casilla_electronica' => $this->nullableTrim($input['casilla_electronica'] ?? null),
            'domicilio_procesal' => $this->nullableTrim($input['domicilio_procesal'] ?? null),
            'celular' => $this->nullableTrim($input['celular'] ?? null),
            'email' => $this->nullableTrim($input['email'] ?? null),
        ];

        if ($payload['accidente_id'] <= 0) {
            throw new InvalidArgumentException('Falta el accidente.');
        }
        if ($payload['persona_id'] <= 0) {
            throw new InvalidArgumentException('Selecciona a quien representa el abogado.');
        }
        if ($payload['nombres'] === '') {
            throw new InvalidArgumentException('Ingresa los nombres del abogado.');
        }
        if ($payload['apellido_paterno'] === '') {
            throw new InvalidArgumentException('Ingresa el apellido paterno.');
        }
        if ($payload['colegiatura'] === '') {
            throw new InvalidArgumentException('Ingresa la colegiatura.');
        }
        if ($payload['email'] !== null && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El email no es valido.');
        }

        return $payload;
    }

    private function assertPersonaPerteneceAlAccidente(int $accidenteId, int $personaId): void
    {
        foreach ($this->repository->personaOptionsByAccidente($accidenteId) as $persona) {
            if ((int) ($persona['id'] ?? 0) === $personaId) {
                return;
            }
        }

        throw new InvalidArgumentException('La persona seleccionada no pertenece al accidente.');
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }
}
