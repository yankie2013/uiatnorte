<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PropietarioVehiculoRepository;
use InvalidArgumentException;

final class PropietarioVehiculoService
{
    private const TIPOS = ['NATURAL', 'JURIDICA'];

    public function __construct(private PropietarioVehiculoRepository $repository)
    {
    }

    public function formContext(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            throw new InvalidArgumentException('Falta accidente_id.');
        }

        return [
            'accidente' => $this->repository->accidenteHeader($accidenteId),
            'vehiculos' => $this->repository->vehiculosByAccidente($accidenteId),
            'tipos' => self::TIPOS,
        ];
    }

    public function defaultData(?array $row = null, ?int $accidenteId = null): array
    {
        return [
            'accidente_id' => $row['accidente_id'] ?? ($accidenteId ?: ''),
            'vehiculo_inv_id' => $row['vehiculo_inv_id'] ?? '',
            'tipo_propietario' => $row['tipo_propietario'] ?? 'NATURAL',
            'propietario_persona_id' => $row['propietario_persona_id'] ?? '',
            'representante_persona_id' => $row['representante_persona_id'] ?? '',
            'ruc' => $row['ruc'] ?? '',
            'razon_social' => $row['razon_social'] ?? '',
            'domicilio_fiscal' => $row['domicilio_fiscal'] ?? '',
            'rol_legal' => $row['rol_legal'] ?? 'Representante legal',
            'observaciones' => $row['observaciones'] ?? '',
            'celular_nat' => $row['cel_nat'] ?? '',
            'email_nat' => $row['em_nat'] ?? '',
            'celular_rep' => $row['cel_rep'] ?? '',
            'email_rep' => $row['em_rep'] ?? '',
        ];
    }

    public function personaPorDni(string $dni): ?array
    {
        $dni = trim($dni);
        if (!preg_match('/^\d{8}$/', $dni)) {
            throw new InvalidArgumentException('DNI invalido.');
        }
        return $this->repository->personaByDni($dni);
    }

    public function personaPorId(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID invalido.');
        }
        return $this->repository->personaById($id);
    }

    public function buscarPersonas(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            throw new InvalidArgumentException('Escribe al menos 2 caracteres.');
        }
        return $this->repository->searchPersonas($query);
    }

    public function listado(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            throw new InvalidArgumentException('Falta accidente_id.');
        }
        return $this->repository->listByAccidente($accidenteId);
    }

    public function detalle(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Falta id.');
        }
        return $this->repository->detail($id);
    }

    public function create(array $input): int
    {
        $payload = $this->payload($input, null);
        $this->syncContacts($payload);
        return $this->repository->create($payload);
    }

    public function update(int $id, array $input): void
    {
        $current = $this->repository->find($id);
        if ($current === null) {
            throw new InvalidArgumentException('Registro no encontrado.');
        }
        $payload = $this->payload($input, $id);
        $this->syncContacts($payload);
        $this->repository->update($id, $payload);
    }

    public function delete(int $id, int $accidenteId): void
    {
        if ($id <= 0 || $accidenteId <= 0) {
            throw new InvalidArgumentException('Parametros invalidos.');
        }
        $row = $this->repository->find($id);
        if ($row === null) {
            throw new InvalidArgumentException('Registro no encontrado.');
        }
        if ((int) ($row['accidente_id'] ?? 0) !== $accidenteId) {
            throw new InvalidArgumentException('El registro no pertenece a este accidente.');
        }
        $this->repository->delete($id, $accidenteId);
    }

    private function payload(array $input, ?int $excludeId): array
    {
        $payload = [
            'accidente_id' => (int) ($input['accidente_id'] ?? 0),
            'vehiculo_inv_id' => (int) ($input['vehiculo_inv_id'] ?? 0),
            'tipo_propietario' => strtoupper(trim((string) ($input['tipo_propietario'] ?? 'NATURAL'))),
            'propietario_persona_id' => (int) ($input['propietario_persona_id'] ?? 0),
            'ruc' => trim((string) ($input['ruc'] ?? '')),
            'razon_social' => $this->nullableTrim($input['razon_social'] ?? null),
            'domicilio_fiscal' => $this->nullableTrim($input['domicilio_fiscal'] ?? null),
            'rol_legal' => $this->nullableTrim($input['rol_legal'] ?? null),
            'representante_persona_id' => (int) ($input['representante_persona_id'] ?? 0),
            'observaciones' => $this->nullableTrim($input['observaciones'] ?? null),
            'celular_nat' => $this->nullableTrim($input['celular_nat'] ?? null),
            'email_nat' => $this->nullableTrim($input['email_nat'] ?? null),
            'celular_rep' => $this->nullableTrim($input['celular_rep'] ?? null),
            'email_rep' => $this->nullableTrim($input['email_rep'] ?? null),
        ];

        if ($payload['accidente_id'] <= 0) {
            throw new InvalidArgumentException('Falta el accidente.');
        }
        if ($payload['vehiculo_inv_id'] <= 0) {
            throw new InvalidArgumentException('Selecciona el vehiculo.');
        }
        if (!$this->repository->vehiculoBelongsAccidente($payload['accidente_id'], $payload['vehiculo_inv_id'])) {
            throw new InvalidArgumentException('El vehiculo no pertenece al accidente.');
        }
        if (!in_array($payload['tipo_propietario'], self::TIPOS, true)) {
            throw new InvalidArgumentException('Tipo de propietario invalido.');
        }

        if ($payload['tipo_propietario'] === 'NATURAL') {
            if ($payload['propietario_persona_id'] <= 0) {
                throw new InvalidArgumentException('Selecciona o crea el propietario.');
            }
            $payload['ruc'] = '';
            $payload['razon_social'] = null;
            $payload['domicilio_fiscal'] = null;
            $payload['rol_legal'] = null;
            $payload['representante_persona_id'] = 0;
        } else {
            if (!preg_match('/^\d{11}$/', $payload['ruc'])) {
                throw new InvalidArgumentException('RUC invalido (11 digitos).');
            }
            if ($payload['razon_social'] === null) {
                throw new InvalidArgumentException('Ingresa la razon social.');
            }
            if ($payload['representante_persona_id'] <= 0) {
                throw new InvalidArgumentException('Selecciona o crea el representante legal.');
            }
            if ($payload['rol_legal'] === null) {
                $payload['rol_legal'] = 'Representante legal';
            }
            $payload['propietario_persona_id'] = 0;
        }

        if ($payload['email_nat'] !== null && !filter_var($payload['email_nat'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El email del propietario no es valido.');
        }
        if ($payload['email_rep'] !== null && !filter_var($payload['email_rep'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El email del representante no es valido.');
        }
        if ($this->repository->existsDuplicate($payload['accidente_id'], $payload['vehiculo_inv_id'], $payload['tipo_propietario'], $payload['propietario_persona_id'], $payload['ruc'], $excludeId)) {
            throw new InvalidArgumentException('Este propietario ya fue asociado a ese vehiculo en este accidente.');
        }

        return $payload;
    }

    private function syncContacts(array $payload): void
    {
        if ($payload['tipo_propietario'] === 'NATURAL' && $payload['propietario_persona_id'] > 0) {
            $this->repository->updatePersonaContact($payload['propietario_persona_id'], $payload['celular_nat'], $payload['email_nat']);
        }
        if ($payload['tipo_propietario'] === 'JURIDICA' && $payload['representante_persona_id'] > 0) {
            $this->repository->updatePersonaContact($payload['representante_persona_id'], $payload['celular_rep'], $payload['email_rep']);
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }
}
