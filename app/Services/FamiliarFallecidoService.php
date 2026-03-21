<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\FamiliarFallecidoRepository;
use InvalidArgumentException;

final class FamiliarFallecidoService
{
    private const TIPOS_DOC = ['DNI', 'CE', 'PAS', 'OTRO'];

    public function __construct(private FamiliarFallecidoRepository $repository)
    {
    }

    public function formContext(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            throw new InvalidArgumentException('Falta accidente_id.');
        }

        return [
            'accidente' => $this->repository->accidenteHeader($accidenteId),
            'fallecidos' => $this->repository->fallecidosByAccidente($accidenteId),
            'tipos_doc' => self::TIPOS_DOC,
        ];
    }

    public function defaultData(?array $row = null, ?int $accidenteId = null): array
    {
        return [
            'accidente_id' => $row['accidente_id'] ?? ($accidenteId ?: ''),
            'fallecido_inv_id' => $row['fallecido_inv_id'] ?? '',
            'familiar_persona_id' => $row['familiar_persona_id'] ?? ($row['fam_id'] ?? ''),
            'tipo_doc' => $row['tipo_doc_fam'] ?? 'DNI',
            'num_doc' => $row['dni_fam'] ?? '',
            'nombre_familiar' => trim((string) (($row['ap_fam'] ?? '') . ' ' . ($row['am_fam'] ?? '') . ' ' . ($row['no_fam'] ?? ''))),
            'domicilio' => $row['dom_fam'] ?? '',
            'celular' => $row['cel_fam'] ?? '',
            'email' => $row['em_fam'] ?? '',
            'parentesco' => $row['parentesco'] ?? '',
            'observaciones' => $row['observaciones'] ?? '',
        ];
    }

    public function personaPorDocumento(string $tipo, string $doc): ?array
    {
        [$tipo, $doc] = $this->validatedDocument($tipo, $doc);
        return $this->repository->personaByDocument($tipo, $doc);
    }

    public function personaPorId(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID invalido.');
        }
        return $this->repository->personaById($id);
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
        $this->repository->updatePersonaContact((int) $payload['familiar_persona_id'], $payload['celular'], $payload['email']);
        return $this->repository->create($payload);
    }

    public function update(int $id, array $input): void
    {
        $current = $this->repository->find($id);
        if ($current === null) {
            throw new InvalidArgumentException('Registro no encontrado.');
        }
        $payload = $this->payload($input, $id);
        $this->repository->updatePersonaContact((int) $payload['familiar_persona_id'], $payload['celular'], $payload['email']);
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
            'fallecido_inv_id' => (int) ($input['fallecido_inv_id'] ?? 0),
            'familiar_persona_id' => (int) ($input['familiar_persona_id'] ?? 0),
            'parentesco' => $this->nullableTrim($input['parentesco'] ?? null),
            'observaciones' => $this->nullableTrim($input['observaciones'] ?? null),
            'celular' => $this->nullableTrim($input['celular'] ?? null),
            'email' => $this->nullableTrim($input['email'] ?? null),
        ];

        if ($payload['accidente_id'] <= 0) {
            throw new InvalidArgumentException('Falta accidente_id.');
        }
        if ($payload['fallecido_inv_id'] <= 0) {
            throw new InvalidArgumentException('Selecciona a la persona fallecida.');
        }
        if ($payload['familiar_persona_id'] <= 0) {
            throw new InvalidArgumentException('Selecciona o crea al familiar.');
        }
        if ($payload['email'] !== null && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El email no es valido.');
        }
        if ($this->repository->existsDuplicate($payload['accidente_id'], $payload['fallecido_inv_id'], $payload['familiar_persona_id'], $excludeId)) {
            throw new InvalidArgumentException('Este familiar ya fue asociado a ese fallecido en este accidente.');
        }

        return $payload;
    }

    private function validatedDocument(string $tipo, string $doc): array
    {
        $tipo = strtoupper(trim($tipo));
        $doc = trim($doc);
        if (!in_array($tipo, self::TIPOS_DOC, true)) {
            throw new InvalidArgumentException('Tipo de documento no valido.');
        }
        $ok = match ($tipo) {
            'DNI' => (bool) preg_match('/^\d{8}$/', $doc),
            'CE' => (bool) preg_match('/^\d{9,10}$/', $doc),
            'PAS' => (bool) preg_match('/^[A-Za-z0-9]{6,12}$/', $doc),
            'OTRO' => strlen($doc) >= 6 && strlen($doc) <= 20,
            default => false,
        };
        if (!$ok) {
            throw new InvalidArgumentException('Documento invalido para ' . $tipo . '.');
        }
        return [$tipo, $doc];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }
}
