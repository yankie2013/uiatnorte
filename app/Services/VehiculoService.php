<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\VehiculoRepository;
use InvalidArgumentException;
use PDOException;

final class VehiculoService
{
    public function __construct(private VehiculoRepository $repository)
    {
    }

    public function catalogos(): array
    {
        return [
            'categorias' => $this->repository->categorias(),
            'marcas' => $this->repository->marcas(),
            'modelos' => $this->repository->modelos(),
            'tipos' => $this->repository->tipos(),
            'carrocerias' => $this->repository->carrocerias(),
        ];
    }

    public function listado(string $query, int $page, int $limit = 12): array
    {
        $q = trim($query);
        $page = max(1, $page);
        $limit = max(1, $limit);

        $total = $this->repository->searchCount($q);
        $pages = max(1, (int) ceil($total / $limit));
        if ($page > $pages) {
            $page = $pages;
        }

        $offset = ($page - 1) * $limit;
        $rows = $this->repository->searchRows($q, $limit, $offset);

        return [
            'q' => $q,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'total' => $total,
            'pages' => $pages,
            'rows' => $rows,
            'qs' => $q !== '' ? '&q=' . rawurlencode($q) : '',
        ];
    }

    public function detalle(int $id, string $placa): ?array
    {
        $vehiculo = null;

        if ($id > 0) {
            $vehiculo = $this->repository->findDetailById($id);
        } else {
            $placa = $this->normalizeLookupPlate($placa);
            if ($placa !== '') {
                $vehiculo = $this->repository->findDetailByNormalizedPlaca($placa);
            }
        }

        if ($vehiculo === null) {
            return null;
        }

        return [
            'vehiculo' => $vehiculo,
            'accidentes_vinculados' => $this->repository->countAccidentesVinculados((int) $vehiculo['id']),
        ];
    }

    public function contextoEliminacion(int $id): ?array
    {
        $vehiculo = $this->repository->findDeleteSummary($id);
        if ($vehiculo === null) {
            return null;
        }

        $references = $this->repository->referenceCounts($id);
        $total = array_sum($references);

        return [
            'vehiculo' => $vehiculo,
            'references' => $references,
            'reference_total' => $total,
            'can_delete' => $total === 0,
        ];
    }

    public function eliminar(int $id): void
    {
        $context = $this->contextoEliminacion($id);
        if ($context === null) {
            throw new InvalidArgumentException('Vehículo no encontrado.');
        }

        if (!$context['can_delete']) {
            $parts = [];
            foreach ($context['references'] as $table => $count) {
                $parts[] = $table . ': ' . $count;
            }
            throw new InvalidArgumentException('No se puede eliminar porque el vehículo todavía tiene referencias en: ' . implode(', ', $parts) . '.');
        }

        $this->repository->delete($id);
    }

    public function crear(array $input): int
    {
        [$payload] = $this->validatedPayload($input);

        try {
            return $this->repository->create($payload);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && stripos($e->getMessage(), 'placa') !== false) {
                throw new InvalidArgumentException('Ya existe un vehículo con esa placa.');
            }
            throw $e;
        }
    }

    public function actualizar(int $id, array $input): void
    {
        if ($id <= 0 || $this->repository->find($id) === null) {
            throw new InvalidArgumentException('Vehículo no encontrado.');
        }

        [$payload] = $this->validatedPayload($input, $id);

        try {
            $this->repository->update($id, $payload);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && stripos($e->getMessage(), 'placa') !== false) {
                throw new InvalidArgumentException('Ya existe un vehículo con esa placa.');
            }
            throw $e;
        }
    }

    public function buscarExistentePorPlaca(string $placa, ?int $excludeId = null): ?int
    {
        $placa = $this->normalizePlate($placa);
        if ($placa === '') {
            return null;
        }

        return $this->repository->findIdByPlaca($placa, $excludeId);
    }

    public function oldInput(array $input = [], ?array $defaults = null): array
    {
        $base = [
            'placa' => '',
            'serie_vin' => '',
            'nro_motor' => '',
            'categoria_id' => '',
            'tipo_id' => '',
            'carroceria_id' => '',
            'marca_id' => '',
            'modelo_id' => '',
            'anio' => '',
            'color' => '',
            'largo_mm' => '',
            'ancho_mm' => '',
            'alto_mm' => '',
            'notas' => '',
        ];

        if ($defaults !== null) {
            foreach ($base as $key => $value) {
                $base[$key] = (string) ($defaults[$key] ?? '');
            }
        }

        foreach ($base as $key => $_) {
            if (array_key_exists($key, $input)) {
                $base[$key] = trim((string) $input[$key]);
            }
        }

        return $base;
    }

    private function validatedPayload(array $input, ?int $currentId = null): array
    {
        $old = $this->oldInput($input);
        $errors = [];

        $placa = $this->normalizePlate($old['placa']);
        if ($placa === '') {
            $errors[] = 'La placa es requerida.';
        }

        $categoriaId = $this->nullableInt($old['categoria_id']);
        $tipoId = $this->nullableInt($old['tipo_id']);
        $carroceriaId = $this->nullableInt($old['carroceria_id']);
        $marcaId = $this->nullableInt($old['marca_id']);
        $modeloId = $this->nullableInt($old['modelo_id']);
        $anio = $this->nullableYear($old['anio'], $errors);
        $largo = $this->nullableDecimal($old['largo_mm'], 'largo_mm', $errors);
        $ancho = $this->nullableDecimal($old['ancho_mm'], 'ancho_mm', $errors);
        $alto = $this->nullableDecimal($old['alto_mm'], 'alto_mm', $errors);

        if ($categoriaId === null) {
            $errors[] = 'Selecciona la categoría.';
        } elseif (!$this->repository->categoriaExists($categoriaId)) {
            $errors[] = 'La categoría seleccionada no existe.';
        }

        if ($marcaId === null) {
            $errors[] = 'Selecciona la marca.';
        } elseif (!$this->repository->marcaExists($marcaId)) {
            $errors[] = 'La marca seleccionada no existe.';
        }

        if ($modeloId !== null) {
            $modeloMarcaId = $this->repository->modeloMarcaId($modeloId);
            if ($modeloMarcaId === null) {
                $errors[] = 'El modelo seleccionado no existe.';
            } elseif ($marcaId !== null && $modeloMarcaId !== $marcaId) {
                $errors[] = 'El modelo no pertenece a la marca seleccionada.';
            }
        }

        if ($tipoId !== null) {
            $tipoCategoriaId = $this->repository->tipoCategoriaId($tipoId);
            if ($tipoCategoriaId === null) {
                $errors[] = 'El tipo seleccionado no existe.';
            } elseif ($categoriaId !== null && $tipoCategoriaId !== $categoriaId) {
                $errors[] = 'El tipo no pertenece a la categoría seleccionada.';
            }
        }

        if ($carroceriaId !== null) {
            $carroceriaTipoId = $this->repository->carroceriaTipoId($carroceriaId);
            if ($carroceriaTipoId === null) {
                $errors[] = 'La carrocería seleccionada no existe.';
            } elseif ($tipoId !== null && $carroceriaTipoId !== $tipoId) {
                $errors[] = 'La carrocería no pertenece al tipo seleccionado.';
            }
        }

        $existingId = $placa !== '' ? $this->repository->findIdByPlaca($placa, $currentId) : null;
        if ($existingId !== null) {
            $errors[] = 'Ya existe un vehículo con esa placa.';
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return [[
            ':placa' => $placa,
            ':serie_vin' => $this->nullableString($old['serie_vin']),
            ':nro_motor' => $this->nullableString($old['nro_motor']),
            ':categoria_id' => $categoriaId,
            ':tipo_id' => $tipoId,
            ':carroceria_id' => $carroceriaId,
            ':marca_id' => $marcaId,
            ':modelo_id' => $modeloId,
            ':anio' => $anio,
            ':color' => $this->nullableString($old['color']),
            ':largo_mm' => $largo,
            ':ancho_mm' => $ancho,
            ':alto_mm' => $alto,
            ':notas' => $this->nullableString($old['notas']),
        ], $old];
    }

    private function normalizePlate(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';
        return mb_strtoupper($value, 'UTF-8');
    }

    private function normalizeLookupPlate(string $value): string
    {
        $value = preg_replace('/\s+/u', '', trim($value)) ?: '';
        return mb_strtoupper($value, 'UTF-8');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableYear(string $value, array &$errors): ?int
    {
        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value)) {
            $errors[] = 'Año inválido.';
            return null;
        }

        $year = (int) $value;
        if ($year < 1900 || $year > 2100) {
            $errors[] = 'Año inválido.';
            return null;
        }

        return $year;
    }

    private function nullableDecimal(string $value, string $field, array &$errors): ?float
    {
        if ($value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            $errors[] = "Valor numérico inválido en {$field}.";
            return null;
        }

        return (float) $normalized;
    }
}
