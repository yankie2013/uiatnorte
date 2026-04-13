<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoManifestacionRepository;
use InvalidArgumentException;

final class DocumentoManifestacionService
{
    private const MODALIDADES = ['Presencial', 'Virtual', 'Mixta'];

    public function __construct(private DocumentoManifestacionRepository $repository)
    {
    }

    public function contextoNuevo(int $accidenteId, int $personaId): array
    {
        return [
            'acc_label' => $accidenteId > 0 ? $this->repository->accidenteLabel($accidenteId) : '',
            'per_label' => $personaId > 0 ? $this->repository->personaLabel($personaId) : '',
            'modalidades' => self::MODALIDADES,
        ];
    }

    public function detalle(int $id): ?array
    {
        $row = $this->repository->find($id);
        if ($row === null) {
            return null;
        }
        return [
            'row' => $row,
            'acc_label' => !empty($row['accidente_id']) ? $this->repository->accidenteLabel((int) $row['accidente_id']) : '',
            'per_label' => !empty($row['persona_id']) ? $this->repository->personaLabel((int) $row['persona_id']) : '',
            'modalidades' => self::MODALIDADES,
        ];
    }

    public function crear(array $input): int
    {
        return $this->repository->create($this->payload($input, true));
    }

    public function actualizar(int $id, array $input): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Manifestación no encontrada.');
        }
        $payload = $this->payload($input, false);
        array_shift($payload);
        array_shift($payload);
        $this->repository->update($id, $payload);
    }

    public function eliminar(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Manifestacion no encontrada.');
        }

        $this->repository->delete($id);
    }

    private function payload(array $input, bool $withRelations): array
    {
        $accidenteId = (int) ($input['accidente_id'] ?? 0);
        $personaId = (int) ($input['persona_id'] ?? 0);
        $fecha = trim((string) ($input['fecha'] ?? ''));
        $inicio = trim((string) ($input['horario_inicio'] ?? ''));
        $termino = trim((string) ($input['hora_termino'] ?? ''));
        $modalidad = trim((string) ($input['modalidad'] ?? ''));

        if ($withRelations && (!$accidenteId || !$personaId || !$fecha || !$inicio || !$termino || !$modalidad)) {
            throw new InvalidArgumentException('Completa todos los campos.');
        }
        if (!$withRelations && (!$fecha || !$inicio || !$termino || !$modalidad)) {
            throw new InvalidArgumentException('Completa todos los campos.');
        }
        if (!in_array($modalidad, self::MODALIDADES, true)) {
            throw new InvalidArgumentException('Modalidad inválida.');
        }

        $payload = [$accidenteId, $personaId, $fecha, $inicio, $termino, $modalidad];
        return $payload;
    }
}
