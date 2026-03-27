<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\InvolucradoVehiculoRepository;
use InvalidArgumentException;
use PDOException;

final class InvolucradoVehiculoService
{
    private const UT_OPTIONS = ['UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7'];
    private const TIPO_OPTIONS = ['Unidad','Combinado vehicular 1','Combinado vehicular 2','Fugado'];

    public function __construct(private InvolucradoVehiculoRepository $repository)
    {
    }

    public function buscarVehiculos(string $q): array
    {
        $rows = $this->repository->buscarVehiculos(trim($q));
        foreach ($rows as &$row) {
            $row['texto'] = $row['placa'] . (!empty($row['color']) ? '  ' . $row['color'] : '') . (!empty($row['anio']) ? ' (' . $row['anio'] . ')' : '');
        }
        return $rows;
    }

    public function crearCategoria(array $input): array
    {
        $codigo = trim((string) ($input['codigo'] ?? ''));
        $descripcion = trim((string) ($input['descripcion'] ?? '')) ?: null;
        if ($codigo === '') {
            throw new InvalidArgumentException('Código requerido');
        }
        $id = $this->repository->createCategoria($codigo, $descripcion);
        return ['id' => $id, 'nombre' => $codigo . '  ' . ($descripcion ?? '')];
    }

    public function crearTipo(array $input): array
    {
        $categoriaId = (int) ($input['categoria_id'] ?? 0);
        $codigo = trim((string) ($input['codigo'] ?? ''));
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $descripcion = trim((string) ($input['descripcion'] ?? '')) ?: null;
        if ($categoriaId <= 0 || $codigo === '' || $nombre === '') {
            throw new InvalidArgumentException('Categoría, código y nombre son requeridos');
        }
        $id = $this->repository->createTipo($categoriaId, $codigo, $nombre, $descripcion);
        return ['id' => $id, 'nombre' => $codigo . '  ' . $nombre];
    }

    public function crearCarroceria(array $input): array
    {
        $tipoId = (int) ($input['tipo_id'] ?? 0);
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $descripcion = trim((string) ($input['descripcion'] ?? '')) ?: null;
        if ($tipoId <= 0 || $nombre === '') {
            throw new InvalidArgumentException('Tipo y nombre son requeridos');
        }
        $id = $this->repository->createCarroceria($tipoId, $nombre, $descripcion);
        return ['id' => $id, 'nombre' => $nombre];
    }

    public function crearMarca(array $input): array
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $pais = trim((string) ($input['pais_origen'] ?? '')) ?: null;
        if ($nombre === '') {
            throw new InvalidArgumentException('Nombre requerido');
        }
        $id = $this->repository->createMarca($nombre, $pais);
        return ['id' => $id, 'nombre' => $nombre];
    }

    public function crearModelo(array $input): array
    {
        $marcaId = (int) ($input['marca_id'] ?? 0);
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($marcaId <= 0 || $nombre === '') {
            throw new InvalidArgumentException('Marca y nombre son requeridos');
        }
        $id = $this->repository->createModelo($marcaId, $nombre);
        return ['id' => $id, 'nombre' => $nombre];
    }

    public function crearVehiculo(array $input): array
    {
        $placa = mb_strtoupper(preg_replace('/\s+/u',' ', trim((string) ($input['placa'] ?? ''))) ?: '', 'UTF-8');
        if ($placa === '') {
            throw new InvalidArgumentException('La placa es obligatoria');
        }

        try {
            $id = $this->repository->createVehiculo([
                'placa' => $placa,
                'serie_vin' => $this->nullableString($input['serie_vin'] ?? null),
                'nro_motor' => $this->nullableString($input['nro_motor'] ?? null),
                'categoria_id' => $this->nullableInt($input['categoria_id'] ?? null),
                'tipo_id' => $this->nullableInt($input['tipo_id'] ?? null),
                'carroceria_id' => $this->nullableInt($input['carroceria_id'] ?? null),
                'marca_id' => $this->nullableInt($input['marca_id'] ?? null),
                'modelo_id' => $this->nullableInt($input['modelo_id'] ?? null),
                'anio' => $this->nullableInt($input['anio'] ?? null),
                'color' => $this->nullableString($input['color'] ?? null),
                'largo_mm' => $this->nullableString($input['largo_mm'] ?? null),
                'ancho_mm' => $this->nullableString($input['ancho_mm'] ?? null),
                'alto_mm' => $this->nullableString($input['alto_mm'] ?? null),
                'notas' => $this->nullableString($input['notas'] ?? null),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException('La placa ya existe.');
            }
            throw $e;
        }

        $texto = $placa
          . ($this->nullableString($input['color'] ?? null) ? '  ' . $this->nullableString($input['color'] ?? null) : '')
          . ($this->nullableInt($input['anio'] ?? null) ? ' (' . $this->nullableInt($input['anio'] ?? null) . ')' : '');

        return ['id' => $id, 'texto' => $texto];
    }

    public function sugerirOrdenParticipacion(int $accidenteId): string
    {
        if ($accidenteId <= 0) {
            return self::UT_OPTIONS[0];
        }

        $usados = $this->repository->ordenesParticipacionUsadas($accidenteId);
        foreach (self::UT_OPTIONS as $option) {
            if (!in_array($option, $usados, true)) {
                return $option;
            }
        }

        return self::UT_OPTIONS[0];
    }

    public function registrar(array $input): array
    {
        $accidenteId = (int) ($input['accidente_id'] ?? 0);
        $vehiculoId = (int) ($input['vehiculo_id'] ?? 0);
        $tipo = trim((string) ($input['tipo'] ?? 'Unidad'));
        $orden = trim((string) ($input['orden_participacion'] ?? $this->sugerirOrdenParticipacion($accidenteId)));
        $observaciones = trim((string) ($input['observaciones'] ?? '')) ?: null;
        $next = (int) ($input['next'] ?? 0);

        if ($accidenteId <= 0) {
            throw new InvalidArgumentException('Selecciona un accidente.');
        }
        if ($vehiculoId <= 0) {
            throw new InvalidArgumentException('Selecciona un vehículo.');
        }
        if (!in_array($tipo, self::TIPO_OPTIONS, true)) {
            throw new InvalidArgumentException('Selecciona el tipo (Unidad / Combinado vehicular 1 / Combinado vehicular 2 / Fugado).');
        }
        if (!in_array($orden, self::UT_OPTIONS, true)) {
            throw new InvalidArgumentException('Orden de participación inválido.');
        }

        try {
            $this->repository->createInvolucradoVehiculo($accidenteId, $vehiculoId, $orden, $tipo, $observaciones);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException('Ese vehículo ya está vinculado a este accidente.');
            }
            throw $e;
        }

        return [
            'accidente_id' => $accidenteId,
            'next' => $next,
        ];
    }

    public function actualizar(int $id, array $input): void
    {
        $vehiculoId = (int) ($input['vehiculo_id'] ?? 0);
        $tipo = trim((string) ($input['tipo'] ?? 'Unidad'));
        $observaciones = trim((string) ($input['observaciones'] ?? '')) ?: null;

        if ($vehiculoId <= 0) {
            throw new InvalidArgumentException('Selecciona un vehículo.');
        }
        if ($tipo === '') {
            throw new InvalidArgumentException('Selecciona el tipo.');
        }
        if (!in_array($tipo, self::TIPO_OPTIONS, true)) {
            throw new InvalidArgumentException('Tipo inválido.');
        }

        try {
            $this->repository->updateInvolucradoVehiculo($id, $vehiculoId, $tipo, $observaciones);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException('Ese vehículo ya está vinculado a este accidente.');
            }
            throw $e;
        }
    }

    public function tipoOptions(): array
    {
        return self::TIPO_OPTIONS;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }
}
