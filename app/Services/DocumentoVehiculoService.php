<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoVehiculoRepository;
use InvalidArgumentException;

final class DocumentoVehiculoService
{
    private const FIELDS = [
        'numero_propiedad',
        'titulo_propiedad',
        'partida_propiedad',
        'sede_propiedad',
        'numero_soat',
        'aseguradora_soat',
        'vigente_soat',
        'vencimiento_soat',
        'numero_revision',
        'certificadora_revision',
        'vigente_revision',
        'vencimiento_revision',
        'numero_peritaje',
        'fecha_peritaje',
        'perito_peritaje',
        'danos_peritaje',
    ];

    public function __construct(private DocumentoVehiculoRepository $repository)
    {
    }

    public function contextoNuevo(int $involucradoVehiculoId): ?array
    {
        return $this->repository->involucradoInfo($involucradoVehiculoId);
    }

    public function contextoEditar(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function crear(int $involucradoVehiculoId, array $input): int
    {
        $context = $this->repository->involucradoInfo($involucradoVehiculoId);
        if ($context === null) {
            throw new InvalidArgumentException('No se encontró el involucrado de vehículo.');
        }

        $payload = $this->payload($input, (int) ($context['vehiculo_id'] ?? 0), $involucradoVehiculoId);
        return $this->repository->create($payload);
    }

    public function actualizar(int $id, array $input): void
    {
        $documento = $this->repository->find($id);
        if ($documento === null) {
            throw new InvalidArgumentException('Documento no encontrado.');
        }

        $payload = $this->payload($input, (int) ($documento['vehiculo_id'] ?? 0), (int) $documento['invol_id']);
        $this->repository->update($id, $payload);
    }

    public function eliminar(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Documento no encontrado.');
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

        if (array_key_exists('vehiculo_id', $input)) {
            $base['vehiculo_id'] = trim((string) $input['vehiculo_id']);
        }

        return $base;
    }

    public function emptyForm(): array
    {
        $form = ['vehiculo_id' => ''];
        foreach (self::FIELDS as $field) {
            $form[$field] = '';
        }
        return $form;
    }

    private function payload(array $input, int $vehiculoIdDefault, int $involucradoVehiculoId): array
    {
        $vehiculoId = trim((string) ($input['vehiculo_id'] ?? ''));
        $vehiculoId = $vehiculoId === '' ? ($vehiculoIdDefault > 0 ? $vehiculoIdDefault : null) : (int) $vehiculoId;

        return [
            ':involucrado_vehiculo_id' => $involucradoVehiculoId,
            ':vehiculo_id' => $vehiculoId,
            ':numero_propiedad' => $this->nullableTrim($input['numero_propiedad'] ?? null),
            ':titulo_propiedad' => $this->nullableTrim($input['titulo_propiedad'] ?? null),
            ':partida_propiedad' => $this->nullableTrim($input['partida_propiedad'] ?? null),
            ':sede_propiedad' => $this->nullableTrim($input['sede_propiedad'] ?? null),
            ':numero_soat' => $this->nullableTrim($input['numero_soat'] ?? null),
            ':aseguradora_soat' => $this->nullableTrim($input['aseguradora_soat'] ?? null),
            ':vigente_soat' => $this->nullableTrim($input['vigente_soat'] ?? null),
            ':vencimiento_soat' => $this->nullableTrim($input['vencimiento_soat'] ?? null),
            ':numero_revision' => $this->nullableTrim($input['numero_revision'] ?? null),
            ':certificadora_revision' => $this->nullableTrim($input['certificadora_revision'] ?? null),
            ':vigente_revision' => $this->nullableTrim($input['vigente_revision'] ?? null),
            ':vencimiento_revision' => $this->nullableTrim($input['vencimiento_revision'] ?? null),
            ':numero_peritaje' => $this->nullableTrim($input['numero_peritaje'] ?? null),
            ':fecha_peritaje' => $this->nullableTrim($input['fecha_peritaje'] ?? null),
            ':perito_peritaje' => $this->nullableTrim($input['perito_peritaje'] ?? null),
            ':danos_peritaje' => $this->nullableMultiline($input['danos_peritaje'] ?? null),
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableMultiline(mixed $value): ?string
    {
        $value = str_replace(["\r\n", "\r"], "\n", (string) ($value ?? ''));
        $lines = array_filter(array_map(static fn(string $line): string => trim($line), explode("\n", $value)), static fn(string $line): bool => $line !== '');
        return $lines === [] ? null : implode("\n", $lines);
    }
}
