<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoLcRepository;
use InvalidArgumentException;

final class DocumentoLcService
{
    private const CATEGORIAS = [
        'A' => ['I', 'IIa', 'IIb', 'IIIa', 'IIIb', 'IIIc', 'IV'],
        'B' => ['IIb', 'IIc'],
        'C' => [],
    ];

    public function __construct(private DocumentoLcRepository $repository)
    {
    }

    public function buscarPersona(?int $personaId, ?string $dni = null): ?array
    {
        if (($personaId ?? 0) > 0) {
            return $this->repository->personaById((int) $personaId);
        }
        $dni = preg_replace('/\D+/', '', (string) ($dni ?? ''));
        if ($dni === '') {
            return null;
        }
        return $this->repository->personaByDocument($dni);
    }

    public function contextoPersona(int $personaId): array
    {
        $persona = $this->repository->personaById($personaId);
        if ($persona === null) {
            throw new InvalidArgumentException('La persona indicada no existe.');
        }
        return [
            'persona' => $persona,
            'licencias' => $this->repository->listByPersona($personaId),
        ];
    }

    public function detalle(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Falta id.');
        }
        return $this->repository->find($id);
    }

    public function defaultData(?array $row = null): array
    {
        return [
            'clase' => $row['clase'] ?? '',
            'categoria' => $row['categoria'] ?? '',
            'numero' => $row['numero'] ?? '',
            'expedido_por' => $row['expedido_por'] ?? '',
            'vigente_desde' => $row['vigente_desde'] ?? '',
            'vigente_hasta' => $row['vigente_hasta'] ?? '',
            'restricciones' => $row['restricciones'] ?? '',
        ];
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
            throw new InvalidArgumentException('La licencia no existe.');
        }
        $payload = $this->payload(array_merge($current, $input), $id);
        if ((int) $payload['persona_id'] !== (int) $current['persona_id']) {
            throw new InvalidArgumentException('No se puede cambiar la persona asociada a esta licencia.');
        }
        $this->repository->update($id, $payload);
    }

    public function delete(int $id, int $personaId): void
    {
        if ($id <= 0 || $personaId <= 0) {
            throw new InvalidArgumentException('Solicitud invalida.');
        }
        $row = $this->repository->find($id);
        if ($row === null || (int) ($row['persona_id'] ?? 0) !== $personaId) {
            throw new InvalidArgumentException('La licencia no existe o no pertenece a la persona indicada.');
        }
        $this->repository->delete($id, $personaId);
    }

    public function categoriasPorClase(string $clase): array
    {
        return self::CATEGORIAS[$clase] ?? [];
    }

    private function payload(array $input, ?int $excludeId): array
    {
        $personaId = (int) ($input['persona_id'] ?? 0);
        $clase = strtoupper(trim((string) ($input['clase'] ?? '')));
        $categoria = trim((string) ($input['categoria'] ?? ''));
        $numero = trim((string) ($input['numero'] ?? ''));
        $expedidoPor = $this->nullableTrim($input['expedido_por'] ?? null);
        $vigenteDesde = $this->nullableDate($input['vigente_desde'] ?? null, 'Vigente desde');
        $vigenteHasta = $this->nullableDate($input['vigente_hasta'] ?? null, 'Vigente hasta');
        $restricciones = $this->nullableTrim($input['restricciones'] ?? null);

        if ($personaId <= 0) {
            throw new InvalidArgumentException('Falta seleccionar la persona.');
        }
        if ($this->repository->personaById($personaId) === null) {
            throw new InvalidArgumentException('La persona indicada no existe.');
        }
        if (!in_array($clase, ['A', 'B', 'C'], true)) {
            throw new InvalidArgumentException('Seleccione la clase de licencia.');
        }
        if ($numero === '') {
            throw new InvalidArgumentException('Ingrese el numero de licencia.');
        }

        $categorias = self::CATEGORIAS[$clase];
        if ($categorias !== []) {
            if ($categoria === '' || !in_array($categoria, $categorias, true)) {
                throw new InvalidArgumentException('La categoria seleccionada no es valida para la clase ' . $clase . '.');
            }
        } else {
            $categoria = null;
        }

        if ($vigenteDesde !== null && $vigenteHasta !== null && $vigenteHasta < $vigenteDesde) {
            throw new InvalidArgumentException('La fecha de vigencia final no puede ser anterior a la inicial.');
        }
        if ($this->repository->existsDuplicate($personaId, $numero, $excludeId)) {
            throw new InvalidArgumentException('Ya existe una licencia con ese numero para la misma persona.');
        }

        return [
            'persona_id' => $personaId,
            'clase' => $clase,
            'categoria' => $categoria,
            'numero' => $numero,
            'expedido_por' => $expedidoPor,
            'vigente_desde' => $vigenteDesde,
            'vigente_hasta' => $vigenteHasta,
            'restricciones' => $restricciones,
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function nullableDate(mixed $value, string $label): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            throw new InvalidArgumentException('Fecha invalida en ' . $label . '.');
        }
        return $text;
    }
}
