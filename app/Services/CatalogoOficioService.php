<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\CatalogoOficioRepository;
use InvalidArgumentException;

final class CatalogoOficioService
{
    public function __construct(private CatalogoOficioRepository $repository)
    {
    }

    public function entidades(): array
    {
        return $this->repository->entidades();
    }

    public function listadoEntidades(array $filters = []): array
    {
        return $this->repository->searchEntidades($filters);
    }

    public function tiposEntidad(): array
    {
        return $this->repository->tiposEntidad();
    }

    public function categoriasEntidad(): array
    {
        return $this->repository->categoriasEntidad();
    }

    public function entidadDetalle(int $id): ?array
    {
        return $this->repository->findEntidad($id);
    }

    public function enlaceInteresCategorias(): array
    {
        return $this->repository->enlaceInteresCategorias();
    }

    public function listadoEnlacesInteres(array $filters = []): array
    {
        return $this->repository->searchEnlacesInteres($filters);
    }

    public function enlaceInteresDetalle(int $id): ?array
    {
        return $this->repository->findEnlaceInteres($id);
    }

    public function enlaceInteresDefault(?array $row = null): array
    {
        return [
            'categoria' => $row['categoria'] ?? 'OTROS',
            'nombre' => $row['nombre'] ?? '',
            'url' => $row['url'] ?? '',
            'descripcion' => $row['descripcion'] ?? '',
            'orden' => isset($row['orden']) ? (int) $row['orden'] : 0,
            'activo' => isset($row['activo']) ? (int) $row['activo'] : 1,
        ];
    }

    public function saveEnlaceInteres(array $input, ?int $id = null): int
    {
        $payload = $this->payloadEnlaceInteres($input);
        if ($id === null) {
            return $this->repository->createEnlaceInteres($payload);
        }
        if ($this->repository->findEnlaceInteres($id) === null) {
            throw new InvalidArgumentException('Enlace no encontrado.');
        }
        $this->repository->updateEnlaceInteres($id, $payload);
        return $id;
    }

    public function entidadDefault(?array $row = null): array
    {
        return [
            'tipo' => $row['tipo'] ?? 'PUBLICA',
            'categoria' => $row['categoria'] ?? '',
            'nombre' => $row['nombre'] ?? '',
            'siglas' => $row['siglas'] ?? '',
            'direccion' => $row['direccion'] ?? '',
            'telefono' => $row['telefono'] ?? '',
            'telefono_fijo' => $row['telefono_fijo'] ?? ($row['telefono'] ?? ''),
            'telefono_movil' => $row['telefono_movil'] ?? '',
            'correo' => $row['correo'] ?? '',
            'pagina_web' => $row['pagina_web'] ?? '',
        ];
    }

    public function saveEntidad(array $input, ?int $id = null): int
    {
        $payload = $this->payloadEntidad($input);
        if ($id === null) {
            return $this->repository->createEntidad($payload);
        }
        if ($this->repository->findEntidad($id) === null) {
            throw new InvalidArgumentException('Entidad no encontrada.');
        }
        $this->repository->updateEntidad($id, $payload);
        return $id;
    }

    public function asuntoDefault(array $preset = []): array
    {
        return [
            'entidad_id' => $preset['entidad_id'] ?? '',
            'tipo' => $preset['tipo'] ?? 'SOLICITAR',
            'nombre' => '',
            'detalle' => '',
            'orden' => 0,
        ];
    }

    public function createAsunto(array $input): int
    {
        $payload = $this->payloadAsunto($input);
        return $this->repository->createAsunto($payload);
    }

    public function subentidadDefault(array $preset = []): array
    {
        return [
            'entidad_id' => $preset['entidad_id'] ?? '',
            'nombre' => '',
            'siglas' => '',
            'tipo' => 'OFICINA',
            'codigo' => '',
            'pagina_web' => '',
            'direccion' => '',
            'telefono' => '',
            'correo' => '',
            'parent_id' => '',
        ];
    }

    public function subentidadesPorEntidad(int $entidadId): array
    {
        if ($entidadId <= 0) {
            return [];
        }
        return $this->repository->subentidadesByEntidad($entidadId);
    }

    public function createSubentidad(array $input): int
    {
        $payload = $this->payloadSubentidad($input);
        return $this->repository->createSubentidad($payload);
    }

    public function personaEntidadDefault(array $preset = []): array
    {
        return [
            'entidad_id' => $preset['entidad_id'] ?? '',
            'nombres' => '',
            'apellido_paterno' => '',
            'apellido_materno' => '',
            'telefono' => '',
            'direccion' => '',
            'pagina_web' => '',
            'correo' => '',
            'observacion' => '',
        ];
    }

    public function createPersonaEntidad(array $input): int
    {
        $payload = $this->payloadPersonaEntidad($input);
        return $this->repository->createPersonaEntidad($payload);
    }

    public function gradoCargoDefault(array $preset = []): array
    {
        return [
            'tipo' => $preset['tipo'] ?? 'CARGO',
            'nombre' => '',
            'abreviatura' => '',
            'orden' => $preset['orden'] ?? 0,
            'activo' => isset($preset['activo']) ? (int) $preset['activo'] : 1,
        ];
    }

    public function createGradoCargo(array $input): int
    {
        $payload = $this->payloadGradoCargo($input);
        return $this->repository->createGradoCargo($payload);
    }

    public function oficialAnoDefault(): array
    {
        return [
            'anio' => date('Y'),
            'nombre' => '',
            'decreto' => '',
            'vigente' => 0,
        ];
    }

    public function createOficialAno(array $input): int
    {
        $payload = $this->payloadOficialAno($input);
        return $this->repository->createOficialAno($payload);
    }

    private function payloadEntidad(array $input): array
    {
        $tipo = strtoupper(trim((string) ($input['tipo'] ?? 'PUBLICA')));
        $validTypes = $this->repository->tiposEntidad();
        if (!in_array($tipo, $validTypes, true)) {
            $tipo = 'PUBLICA';
        }

        $categoria = strtoupper(trim((string) ($input['categoria'] ?? '')));
        $validCategories = $this->repository->categoriasEntidad();
        if ($categoria !== '' && !in_array($categoria, $validCategories, true)) {
            $categoria = 'OTRA';
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        $correo = trim((string) ($input['correo'] ?? ''));
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo no tiene un formato valido.');
        }

        $telefonoFijo = $this->nullableTrim($input['telefono_fijo'] ?? null);
        $telefonoMovil = $this->nullableTrim($input['telefono_movil'] ?? null);
        $telefonoLegacy = $this->nullableTrim($input['telefono'] ?? null);
        $telefonoPrincipal = $telefonoFijo ?? $telefonoMovil ?? $telefonoLegacy;

        return [
            'tipo' => $tipo,
            'categoria' => $categoria !== '' ? $categoria : null,
            'nombre' => $nombre,
            'siglas' => $this->nullableTrim($input['siglas'] ?? null),
            'direccion' => $this->nullableTrim($input['direccion'] ?? null),
            'telefono' => $telefonoPrincipal,
            'telefono_fijo' => $telefonoFijo,
            'telefono_movil' => $telefonoMovil,
            'correo' => $correo !== '' ? $correo : null,
            'pagina_web' => $this->nullableTrim($input['pagina_web'] ?? null),
        ];
    }

    private function payloadEnlaceInteres(array $input): array
    {
        $categoria = strtoupper(trim((string) ($input['categoria'] ?? 'OTROS')));
        if (!in_array($categoria, $this->repository->enlaceInteresCategorias(), true)) {
            $categoria = 'OTROS';
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        $url = trim((string) ($input['url'] ?? ''));
        if ($url === '') {
            throw new InvalidArgumentException('La URL es obligatoria.');
        }
        if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('La URL no tiene un formato valido.');
        }

        return [
            'categoria' => $categoria,
            'nombre' => $nombre,
            'url' => $url,
            'descripcion' => $this->nullableTrim($input['descripcion'] ?? null),
            'orden' => max(0, (int) ($input['orden'] ?? 0)),
            'activo' => ((int) ($input['activo'] ?? 1)) === 1 ? 1 : 0,
        ];
    }

    private function payloadAsunto(array $input): array
    {
        $entidadId = (int) ($input['entidad_id'] ?? 0);
        if ($entidadId <= 0 || $this->repository->findEntidad($entidadId) === null) {
            throw new InvalidArgumentException('Selecciona una entidad valida.');
        }

        $tipo = strtoupper(trim((string) ($input['tipo'] ?? 'SOLICITAR')));
        if (!in_array($tipo, ['SOLICITAR', 'REMITIR'], true)) {
            throw new InvalidArgumentException('Tipo invalido.');
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        return [
            'entidad_id' => $entidadId,
            'tipo' => $tipo,
            'nombre' => $nombre,
            'detalle' => $this->nullableTrim($input['detalle'] ?? null),
            'orden' => (int) ($input['orden'] ?? 0),
        ];
    }

    private function payloadSubentidad(array $input): array
    {
        $entidadId = (int) ($input['entidad_id'] ?? 0);
        if ($entidadId <= 0 || $this->repository->findEntidad($entidadId) === null) {
            throw new InvalidArgumentException('Selecciona una entidad valida.');
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        $correo = trim((string) ($input['correo'] ?? ''));
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo no tiene un formato valido.');
        }

        $tipo = strtoupper(trim((string) ($input['tipo'] ?? 'OFICINA')));
        $validTypes = ['SEDE', 'GERENCIA', 'DIRECCION', 'OFICINA', 'UNIDAD', 'DEPARTAMENTO', 'AREA', 'OTRA'];
        if (!in_array($tipo, $validTypes, true)) {
            $tipo = 'OFICINA';
        }

        $parentId = ($input['parent_id'] ?? '') === '' ? null : (int) $input['parent_id'];
        if ($parentId !== null) {
            $allowed = false;
            foreach ($this->repository->subentidadesByEntidad($entidadId) as $item) {
                if ((int) $item['id'] === $parentId) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                throw new InvalidArgumentException('La subentidad padre no pertenece a la entidad seleccionada.');
            }
        }

        return [
            'entidad_id' => $entidadId,
            'nombre' => $nombre,
            'siglas' => $this->nullableTrim($input['siglas'] ?? null),
            'tipo' => $tipo,
            'codigo' => $this->nullableTrim($input['codigo'] ?? null),
            'pagina_web' => $this->nullableTrim($input['pagina_web'] ?? null),
            'direccion' => $this->nullableTrim($input['direccion'] ?? null),
            'telefono' => $this->nullableTrim($input['telefono'] ?? null),
            'correo' => $correo !== '' ? $correo : null,
            'parent_id' => $parentId,
        ];
    }

    private function payloadPersonaEntidad(array $input): array
    {
        $entidadId = (int) ($input['entidad_id'] ?? 0);
        if ($entidadId <= 0 || $this->repository->findEntidad($entidadId) === null) {
            throw new InvalidArgumentException('Selecciona una entidad valida.');
        }

        $nombres = trim((string) ($input['nombres'] ?? ''));
        $apellidoPaterno = trim((string) ($input['apellido_paterno'] ?? ''));
        if ($nombres === '' || $apellidoPaterno === '') {
            throw new InvalidArgumentException('Nombres y apellido paterno son obligatorios.');
        }

        $correo = trim((string) ($input['correo'] ?? ''));
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo no tiene un formato valido.');
        }

        return [
            'entidad_id' => $entidadId,
            'nombres' => $nombres,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $this->nullableTrim($input['apellido_materno'] ?? null),
            'telefono' => $this->nullableTrim($input['telefono'] ?? null),
            'direccion' => $this->nullableTrim($input['direccion'] ?? null),
            'pagina_web' => $this->nullableTrim($input['pagina_web'] ?? null),
            'correo' => $correo !== '' ? $correo : null,
            'observacion' => $this->nullableTrim($input['observacion'] ?? null),
        ];
    }

    private function payloadGradoCargo(array $input): array
    {
        $tipo = strtoupper(trim((string) ($input['tipo'] ?? 'CARGO')));
        if (!in_array($tipo, ['GRADO', 'CARGO'], true)) {
            $tipo = 'CARGO';
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        return [
            'tipo' => $tipo,
            'nombre' => $nombre,
            'abreviatura' => $this->nullableTrim($input['abreviatura'] ?? null),
            'orden' => (int) ($input['orden'] ?? 0),
            'activo' => !empty($input['activo']) ? 1 : 0,
        ];
    }

    private function payloadOficialAno(array $input): array
    {
        $anio = trim((string) ($input['anio'] ?? ''));
        if (!preg_match('/^\d{4}$/', $anio)) {
            throw new InvalidArgumentException('Ano invalido.');
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        return [
            'anio' => (int) $anio,
            'nombre' => $nombre,
            'decreto' => $this->nullableTrim($input['decreto'] ?? null),
            'vigente' => !empty($input['vigente']) ? 1 : 0,
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }
}
