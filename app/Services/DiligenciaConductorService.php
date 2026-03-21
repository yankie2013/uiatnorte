<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DiligenciaConductorRepository;
use InvalidArgumentException;

final class DiligenciaConductorService
{
    public function __construct(private DiligenciaConductorRepository $repository)
    {
    }

    public function defaultData(?array $row = null, array $context = []): array
    {
        return [
            'accidente_id' => (int) ($context['accidente_id'] ?? $row['accidente_id'] ?? 0),
            'involucrado_persona_id' => (int) ($context['involucrado_persona_id'] ?? $row['involucrado_persona_id'] ?? 0),
            'persona_id' => (int) ($context['persona_id'] ?? $row['persona_id'] ?? 0),
            'vehiculo_id' => (int) ($context['vehiculo_id'] ?? $row['vehiculo_id'] ?? 0),
            'licencia_numero' => $row['licencia_numero'] ?? '',
            'licencia_categoria' => $row['licencia_categoria'] ?? '',
            'licencia_vencimiento' => $row['licencia_vencimiento'] ?? '',
            'dosaje_fecha' => $this->datetimeLocal($row['dosaje_fecha'] ?? null),
            'dosaje_resultado' => $row['dosaje_resultado'] ?? 'No realizado',
            'dosaje_gramos' => $row['dosaje_gramos'] ?? '',
            'recon_medico_fecha' => $this->datetimeLocal($row['recon_medico_fecha'] ?? null),
            'recon_medico_result' => $row['recon_medico_result'] ?? 'No realizado',
            'toxico_fecha' => $this->datetimeLocal($row['toxico_fecha'] ?? null),
            'toxico_resultado' => $row['toxico_resultado'] ?? 'No realizado',
            'toxico_sustancias' => $row['toxico_sustancias'] ?? '',
            'manif_inicio' => $this->datetimeLocal($row['manif_inicio'] ?? null),
            'manif_fin' => $this->datetimeLocal($row['manif_fin'] ?? null),
            'manif_duracion_min' => $row['manif_duracion_min'] ?? '',
        ];
    }

    public function detailContext(int $accidenteId, int $involucradoPersonaId, int $personaId, int $vehiculoId): array
    {
        if ($accidenteId <= 0 || $involucradoPersonaId <= 0) {
            throw new InvalidArgumentException('Faltan parametros requeridos.');
        }

        $row = $this->repository->findByAccidenteAndInvolucrado($accidenteId, $involucradoPersonaId);

        return [
            'row' => $row,
            'persona_nombre' => $this->repository->personaNombre($personaId),
            'vehiculo_placa' => $this->repository->vehiculoPlaca($vehiculoId),
        ];
    }

    public function save(array $input): void
    {
        $payload = $this->payload($input);
        $exists = $this->repository->findByAccidenteAndInvolucrado($payload['accidente_id'], $payload['involucrado_persona_id']) !== null;
        $this->repository->save($payload, $exists);
    }

    private function payload(array $input): array
    {
        $accidenteId = (int) ($input['accidente_id'] ?? 0);
        $involucradoPersonaId = (int) ($input['involucrado_persona_id'] ?? 0);
        if ($accidenteId <= 0 || $involucradoPersonaId <= 0) {
            throw new InvalidArgumentException('Faltan parametros requeridos.');
        }

        $dosajeResultado = $this->normalizeEnum((string) ($input['dosaje_resultado'] ?? 'No realizado'), ['Positivo', 'Negativo', 'No realizado']);
        $reconResult = $this->normalizeEnum((string) ($input['recon_medico_result'] ?? 'No realizado'), ['Apto', 'No apto', 'Observado', 'No realizado']);
        $toxicoResultado = $this->normalizeEnum((string) ($input['toxico_resultado'] ?? 'No realizado'), ['Positivo', 'Negativo', 'No realizado']);

        return [
            'accidente_id' => $accidenteId,
            'involucrado_persona_id' => $involucradoPersonaId,
            'persona_id' => (int) ($input['persona_id'] ?? 0),
            'vehiculo_id' => (int) ($input['vehiculo_id'] ?? 0),
            'licencia_numero' => trim((string) ($input['licencia_numero'] ?? '')),
            'licencia_categoria' => trim((string) ($input['licencia_categoria'] ?? '')),
            'licencia_vencimiento' => $this->nullableDate($input['licencia_vencimiento'] ?? null),
            'dosaje_fecha' => $this->nullableDateTime($input['dosaje_fecha'] ?? null),
            'dosaje_resultado' => $dosajeResultado,
            'dosaje_gramos' => ($input['dosaje_gramos'] ?? '') !== '' ? (float) $input['dosaje_gramos'] : null,
            'recon_medico_fecha' => $this->nullableDateTime($input['recon_medico_fecha'] ?? null),
            'recon_medico_result' => $reconResult,
            'toxico_fecha' => $this->nullableDateTime($input['toxico_fecha'] ?? null),
            'toxico_resultado' => $toxicoResultado,
            'toxico_sustancias' => trim((string) ($input['toxico_sustancias'] ?? '')),
            'manif_inicio' => $this->nullableDateTime($input['manif_inicio'] ?? null),
            'manif_fin' => $this->nullableDateTime($input['manif_fin'] ?? null),
            'manif_duracion_min' => ($input['manif_duracion_min'] ?? '') !== '' ? (int) $input['manif_duracion_min'] : null,
        ];
    }

    private function normalizeEnum(string $value, array $allowed): string
    {
        $trimmed = trim($value);
        foreach ($allowed as $option) {
            if (strcasecmp($trimmed, $option) === 0) {
                return $option;
            }
        }
        return $allowed[array_key_last($allowed)];
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function nullableDateTime(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        return str_replace('T', ' ', $text);
    }

    private function datetimeLocal(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
    }
}
