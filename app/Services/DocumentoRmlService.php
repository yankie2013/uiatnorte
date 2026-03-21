<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoRmlRepository;
use InvalidArgumentException;

final class DocumentoRmlService
{
    public function __construct(private DocumentoRmlRepository $repository)
    {
    }

    public function persona(int $personaId): ?array
    {
        return $personaId > 0 ? $this->repository->personaById($personaId) : null;
    }

    public function detalle(int $id): ?array
    {
        return $this->repository->findWithPersona($id);
    }

    public function editarContexto(int $id): ?array
    {
        $row = $this->repository->find($id);
        if ($row === null) {
            return null;
        }

        return [
            'row' => $row,
            'persona' => !empty($row['persona_id']) ? $this->repository->personaById((int) $row['persona_id']) : null,
        ];
    }

    public function listado(int $personaId): array
    {
        return $this->repository->search($personaId);
    }

    public function crear(array $input): int
    {
        return $this->repository->create($this->payload($input, true));
    }

    public function actualizar(int $id, array $input): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('No encontrado');
        }

        $this->repository->update($id, $this->payload($input, false));
    }

    public function eliminar(int $id): ?int
    {
        $row = $this->repository->find($id);
        if ($row === null) {
            throw new InvalidArgumentException('No encontrado');
        }

        $personaId = (int) ($row['persona_id'] ?? 0);
        $this->repository->delete($id);
        return $personaId > 0 ? $personaId : null;
    }

    private function payload(array $input, bool $requirePersona): array
    {
        $personaId = (int) ($input['persona_id'] ?? 0);
        $numero = trim((string) ($input['numero'] ?? ''));
        $fecha = trim((string) ($input['fecha'] ?? ''));
        $incap = $this->validarCampo($input['incapacidad_medico'] ?? '');
        $aten = $this->validarCampo($input['atencion_facultativo'] ?? '');
        $obs = trim((string) ($input['observaciones'] ?? ''));

        if ($requirePersona && $personaId <= 0) {
            throw new InvalidArgumentException('Selecciona la persona.');
        }
        if ($numero === '') {
            throw new InvalidArgumentException('Ingresa el número.');
        }
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('Fecha inválida (YYYY-MM-DD).');
        }
        if ($incap === false || $aten === false) {
            throw new InvalidArgumentException('Incapacidad médico y Atención facultativo deben ser número de días o "No requiere".');
        }

        return [
            $personaId > 0 ? $personaId : null,
            $numero,
            $fecha,
            $incap ?: null,
            $aten ?: null,
            $obs !== '' ? $obs : null,
        ];
    }

    private function validarCampo(mixed $value): string|false
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (strcasecmp($value, 'No requiere') === 0) {
            return 'No requiere';
        }
        if (ctype_digit($value)) {
            return $value;
        }
        return false;
    }
}
