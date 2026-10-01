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
        $snapshot = $this->snapshotDatos($personaId, $accidenteId, $input);

        $this->repository->createInvolucrado([
            'accidente_id' => $accidenteId,
            'persona_id' => $personaId,
            'rol_id' => $rolId,
            'vehiculo_id' => $vehiculoId,
            'lesion' => $lesion,
            'observaciones' => $observaciones,
            'orden_persona' => $ordenPersona,
            ...$snapshot,
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
        $actual = $this->repository->involucradoById($involucradoId);
        if (!$actual) {
            throw new InvalidArgumentException('El participante ya no está disponible.');
        }
        $snapshot = $this->snapshotDatos($personaId, (int) $actual['accidente_id'], $input);

        $this->repository->updateInvolucrado($involucradoId, [
            'persona_id' => $personaId,
            'rol_id' => $rolId,
            'vehiculo_id' => $vehiculoId,
            'lesion' => $lesion,
            'observaciones' => $observaciones,
            'orden_persona' => $ordenPersona,
            ...$snapshot,
        ]);
    }

    private function resolverReglasRol(int $rolId, ?int $vehiculoId, string $ordenIn): array
    {
        $rol = $this->repository->rolById($rolId) ?: ['req' => 0, 'Nombre' => ''];
        $requiereVehiculo = (int) ($rol['req'] ?? 0);
        if ($requiereVehiculo && !$vehiculoId) {
            throw new InvalidArgumentException('Este rol requiere seleccionar un veh�culo.');
        }
        if (!$requiereVehiculo) {
            $vehiculoId = null;
        }

        $ordenPersona = null;
        $nombreRol = mb_strtolower(trim((string) ($rol['Nombre'] ?? '')), 'UTF-8');
       $allowOrden = preg_match('/peat(?:o|\x{00F3})n|pasajero|ocupante|testigo/u', $nombreRol) === 1;
        if ($allowOrden && $ordenIn !== '' && preg_match('/^[A-Z]$/', $ordenIn)) {
            $ordenPersona = $ordenIn;
        }

        return [$vehiculoId, $ordenPersona];
    }

    private function snapshotDatos(int $personaId, int $accidenteId, array $input): array
    {
        $base = $this->repository->personaSnapshot($personaId, $accidenteId);
        if (!$base) {
            throw new InvalidArgumentException('No se encontraron los datos de la persona o del accidente.');
        }
        $fields = [
            'estado_civil' => 'estado_civil_snapshot',
            'grado_instruccion' => 'grado_instruccion_snapshot',
            'numero_hijos' => 'numero_hijos_snapshot',
            'domicilio' => 'domicilio_snapshot',
            'domicilio_departamento' => 'domicilio_departamento_snapshot',
            'domicilio_provincia' => 'domicilio_provincia_snapshot',
            'domicilio_distrito' => 'domicilio_distrito_snapshot',
            'celular' => 'celular_snapshot',
            'email' => 'email_snapshot',
        ];
        $snapshot = ['edad_snapshot' => $base['edad_calculada'] === null ? null : (int) $base['edad_calculada']];
        foreach ($fields as $source => $target) {
            $value = array_key_exists($source, $input) ? trim((string) $input[$source]) : (string) ($base[$source] ?? '');
            if ($source === 'numero_hijos') {
                $value = $value === '' ? null : filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
                if ($value === false) {
                    throw new InvalidArgumentException('El número de hijos debe ser un número entre 0 y 99.');
                }
            } elseif ($source === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('El correo electrónico no es válido.');
            } else {
                $value = $value === '' ? null : $value;
            }
            $snapshot[$target] = $value;
        }
        return $snapshot;
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
