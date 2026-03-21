<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ComisariaRepository;
use InvalidArgumentException;

final class ComisariaService
{
    public function __construct(private ComisariaRepository $repository)
    {
    }

    public function defaultData(?array $row = null): array
    {
        return [
            'nombre' => $row['nombre'] ?? '',
            'tipo' => $row['tipo'] ?? '',
            'direccion' => $row['direccion'] ?? '',
            'telefono' => $row['telefono'] ?? '',
            'correo' => $row['correo'] ?? '',
            'lat' => $row['lat'] ?? '',
            'lon' => $row['lon'] ?? '',
            'notas' => $row['notas'] ?? '',
            'activo' => isset($row['activo']) ? (int) $row['activo'] : 1,
        ];
    }

    public function listado(string $query = ''): array
    {
        $rows = $this->repository->all();
        $query = trim(mb_strtolower($query));
        if ($query === '') {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($query): bool {
            $haystack = mb_strtolower(implode(' ', [
                $row['nombre'] ?? '',
                $row['tipo'] ?? '',
                $row['direccion'] ?? '',
                $row['telefono'] ?? '',
                $row['correo'] ?? '',
                $row['notas'] ?? '',
            ]));
            return str_contains($haystack, $query);
        }));
    }

    public function detalle(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Falta id.');
        }
        return $this->repository->find($id);
    }

    public function create(array $input): int
    {
        return $this->repository->create($this->payload($input));
    }

    public function update(int $id, array $input): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Comisaria no encontrada.');
        }
        $this->repository->update($id, $this->payload($input));
    }

    public function delete(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Comisaria no encontrada.');
        }
        $this->repository->delete($id);
    }

    private function payload(array $input): array
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $correo = trim((string) ($input['correo'] ?? ''));
        $lat = trim((string) ($input['lat'] ?? ''));
        $lon = trim((string) ($input['lon'] ?? ''));

        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es requerido.');
        }
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo no tiene un formato valido.');
        }
        if ($lat !== '' && !is_numeric($lat)) {
            throw new InvalidArgumentException('Latitud invalida.');
        }
        if ($lon !== '' && !is_numeric($lon)) {
            throw new InvalidArgumentException('Longitud invalida.');
        }

        return [
            'nombre' => $nombre,
            'tipo' => $this->nullableTrim($input['tipo'] ?? null),
            'direccion' => $this->nullableTrim($input['direccion'] ?? null),
            'telefono' => $this->nullableTrim($input['telefono'] ?? null),
            'correo' => $correo !== '' ? $correo : null,
            'lat' => $lat !== '' ? $lat : null,
            'lon' => $lon !== '' ? $lon : null,
            'notas' => $this->nullableTrim($input['notas'] ?? null),
            'activo' => !empty($input['activo']) ? 1 : 0,
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }
}
