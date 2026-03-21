<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\InvolucradoPersonaRepository;
use InvalidArgumentException;
use PDOException;

final class InvolucradoPersonaService
{
    public function __construct(private InvolucradoPersonaRepository $repository)
    {
    }

    public function buscarPersona(string $dni, int $accidenteId = 0): ?array
    {
        $dni = preg_replace('/\D/', '', $dni) ?? '';
        if ($dni === '') {
            return null;
        }

        $persona = $this->repository->personaByDni($dni);
        if (!$persona) {
            return null;
        }

        $accidenteFecha = $accidenteId > 0 ? $this->repository->accidenteFecha($accidenteId) : null;
        if ($accidenteFecha && !empty($persona['fecha_nacimiento'])) {
            $persona['edad_calculada'] = $this->edadAFecha((string) $persona['fecha_nacimiento'], $accidenteFecha);
        }

        return $persona;
    }

    public function buscarPersonaBasica(string $dni): ?array
    {
        $dni = preg_replace('/\D/', '', $dni) ?? '';
        if ($dni === '') {
            return null;
        }

        return $this->repository->personaByDniBasic($dni);
    }

    public function crearPersona(array $input): array
    {
        $dni = preg_replace('/\D/', '', (string) ($input['num_doc'] ?? '')) ?? '';
        $nombres = trim((string) ($input['nombres'] ?? ''));
        $apellidoPaterno = trim((string) ($input['apellido_paterno'] ?? ''));
        $apellidoMaterno = trim((string) ($input['apellido_materno'] ?? '')) ?: null;
        $sexo = trim((string) ($input['sexo'] ?? '')) ?: null;
        $edad = ($input['edad'] ?? '') === '' ? null : (int) $input['edad'];

        if ($dni === '' || $nombres === '' || $apellidoPaterno === '') {
            throw new InvalidArgumentException('DNI, nombres y ap. paterno son requeridos');
        }

        try {
            $id = $this->repository->createPersona([
                'num_doc' => $dni,
                'nombres' => $nombres,
                'apellido_paterno' => $apellidoPaterno,
                'apellido_materno' => $apellidoMaterno,
                'sexo' => $sexo,
                'edad' => $edad,
            ]);

            return [
                'id' => $id,
                'label' => trim($nombres . ' ' . $apellidoPaterno . ' ' . ($apellidoMaterno ?? '')),
                'dup' => 0,
            ];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $id = $this->repository->personaIdByDni($dni);
            if ($id === null) {
                throw $e;
            }

            return [
                'id' => $id,
                'label' => trim($nombres . ' ' . $apellidoPaterno . ' ' . ($apellidoMaterno ?? '')),
                'dup' => 1,
            ];
        }
    }

    public function registrar(array $input): array
    {
        $accidenteId = (int) ($input['accidente_id'] ?? 0);
        $personaId = (int) ($input['persona_id'] ?? 0);
        $rolId = (int) ($input['rol_id'] ?? 0);
        $vehiculoId = ($input['vehiculo_id'] ?? '') === '' ? null : (int) $input['vehiculo_id'];
        $lesion = trim((string) ($input['lesion'] ?? 'Ileso')) ?: 'Ileso';
        $observaciones = trim((string) ($input['observaciones'] ?? '')) ?: null;
        $next = (int) ($input['next'] ?? 0);
        $ordenIn = strtoupper(trim((string) ($input['orden_persona'] ?? '')));

        if ($accidenteId <= 0) {
            throw new InvalidArgumentException('Selecciona un accidente.');
        }
        if ($personaId <= 0) {
            throw new InvalidArgumentException('Busca o crea una persona.');
        }
        if ($rolId <= 0) {
            throw new InvalidArgumentException('Selecciona un rol.');
        }

        [$vehiculoId, $ordenPersona] = $this->resolverReglasRol($rolId, $vehiculoId, $ordenIn);
        $this->sincronizarEdadPersona($personaId, $accidenteId);

        $this->repository->createInvolucrado([
            'accidente_id' => $accidenteId,
            'persona_id' => $personaId,
            'rol_id' => $rolId,
            'vehiculo_id' => $vehiculoId,
            'lesion' => $lesion,
            'observaciones' => $observaciones,
            'orden_persona' => $ordenPersona,
        ]);

        return [
            'accidente_id' => $accidenteId,
            'next' => $next,
        ];
    }

    public function actualizar(int $involucradoId, array $input): void
    {
        $personaId = (int) ($input['persona_id'] ?? 0);
        $rolId = (int) ($input['rol_id'] ?? 0);
        $vehiculoId = ($input['vehiculo_id'] ?? '') === '' ? null : (int) $input['vehiculo_id'];
        $lesion = trim((string) ($input['lesion'] ?? '')) ?: null;
        $observaciones = trim((string) ($input['observaciones'] ?? '')) ?: null;
        $ordenIn = strtoupper(trim((string) ($input['orden_persona'] ?? '')));
        $dni = preg_replace('/\D/', '', (string) ($input['dni'] ?? '')) ?? '';

        if ($personaId <= 0 && $dni !== '') {
            $personaId = (int) ($this->repository->personaIdByDni($dni) ?? 0);
        }

        if ($personaId <= 0) {
            throw new InvalidArgumentException('Selecciona / busca una persona por DNI.');
        }
        if ($rolId <= 0) {
            throw new InvalidArgumentException('Selecciona un rol.');
        }

        [$vehiculoId, $ordenPersona] = $this->resolverReglasRol($rolId, $vehiculoId, $ordenIn);

        $this->repository->updateInvolucrado($involucradoId, [
            'persona_id' => $personaId,
            'rol_id' => $rolId,
            'vehiculo_id' => $vehiculoId,
            'lesion' => $lesion,
            'observaciones' => $observaciones,
            'orden_persona' => $ordenPersona,
        ]);
    }

    private function resolverReglasRol(int $rolId, ?int $vehiculoId, string $ordenIn): array
    {
        $rol = $this->repository->rolById($rolId) ?: ['req' => 0, 'Nombre' => ''];
        $requiereVehiculo = (int) ($rol['req'] ?? 0);
        if ($requiereVehiculo && !$vehiculoId) {
            throw new InvalidArgumentException('Este rol requiere seleccionar un vehículo.');
        }
        if (!$requiereVehiculo) {
            $vehiculoId = null;
        }

        $ordenPersona = null;
        $nombreRol = mb_strtolower(trim((string) ($rol['Nombre'] ?? '')), 'UTF-8');
        $allowOrden = preg_match('/peat(ó|o)n|pasajero|ocupante|testigo/u', $nombreRol) === 1;
        if ($allowOrden && $ordenIn !== '' && preg_match('/^[A-Z]$/', $ordenIn)) {
            $ordenPersona = $ordenIn;
        }

        return [$vehiculoId, $ordenPersona];
    }

    private function sincronizarEdadPersona(int $personaId, int $accidenteId): void
    {
        $fechaAccidente = $this->repository->accidenteFecha($accidenteId);
        $fechaNacimiento = $this->repository->personaFechaNacimiento($personaId);
        $edad = $this->edadAFecha($fechaNacimiento, $fechaAccidente);
        if ($edad !== null) {
            $this->repository->updatePersonaEdad($personaId, $edad);
        }
    }

    private function edadAFecha(?string $fechaNac, ?string $referencia): ?int
    {
        if (!$fechaNac || !$referencia) {
            return null;
        }

        try {
            $nacimiento = new \DateTime(substr($fechaNac, 0, 10));
            $ref = new \DateTime($referencia);
            return $nacimiento->diff($ref)->y;
        } catch (\Exception) {
            return null;
        }
    }
}
