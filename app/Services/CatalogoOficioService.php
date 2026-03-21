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

    public function entidadDetalle(int $id): ?array
    {
        return $this->repository->findEntidad($id);
    }

    public function entidadDefault(?array $row = null): array
    {
        return [
            'tipo' => $row['tipo'] ?? 'PUBLICA',
            'nombre' => $row['nombre'] ?? '',
            'siglas' => $row['siglas'] ?? '',
            'direccion' => $row['direccion'] ?? '',
            'telefono' => $row['telefono'] ?? '',
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
        $validTypes = ['PUBLICA', 'PRIVADA', 'PERSONA_NATURAL', 'OTRA'];
        if (!in_array($tipo, $validTypes, true)) {
            $tipo = 'PUBLICA';
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        $correo = trim((string) ($input['correo'] ?? ''));
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo no tiene un formato valido.');
        }

        return [
            'tipo' => $tipo,
            'nombre' => $nombre,
            'siglas' => $this->nullableTrim($input['siglas'] ?? null),
            'direccion' => $this->nullableTrim($input['direccion'] ?? null),
            'telefono' => $this->nullableTrim($input['telefono'] ?? null),
            'correo' => $correo !== '' ? $correo : null,
            'pagina_web' => $this->nullableTrim($input['pagina_web'] ?? null),
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
