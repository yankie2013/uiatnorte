<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AccidenteRepository;
use InvalidArgumentException;

final class AccidenteService
{
    private const SIMPLE_CATALOGS = [
        'fiscalia' => ['table' => 'fiscalia', 'col' => 'nombre'],
        'modalidad' => ['table' => 'modalidad_accidente', 'col' => 'nombre'],
        'consecuencia' => ['table' => 'consecuencia_accidente', 'col' => 'nombre'],
    ];

    public function __construct(private AccidenteRepository $repository)
    {
    }

    public function createComisaria(array $input): array
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $dep = substr((string) ($input['cod_dep'] ?? ''), 0, 2);
        $prov = substr((string) ($input['cod_prov'] ?? ''), 0, 2);
        $dist = substr((string) ($input['cod_dist'] ?? ''), 0, 2);

        if ($nombre === '') {
            throw new InvalidArgumentException('Nombre de comisaría requerido');
        }

        $id = $this->repository->findComisariaIdByNombre($nombre);
        if ($id === null) {
            $id = $this->repository->createComisaria($nombre);
        }

        $mapeada = false;
        if ($dep !== '' && $prov !== '' && $dist !== '' && $this->repository->distritoExists($dep, $prov, $dist)) {
            $this->repository->mapComisariaToDistrito($id, $dep, $prov, $dist);
            $mapeada = true;
        }

        return ['id' => $id, 'label' => $nombre, 'type' => 'comisaria', 'mapeada' => $mapeada];
    }

    public function createFiscal(array $input): array
    {
        $fiscaliaId = (int) ($input['fiscalia_id'] ?? 0);
        $nombres = trim((string) ($input['nombres'] ?? ''));
        $apellidoPaterno = trim((string) ($input['apellido_paterno'] ?? '')) ?: null;
        $apellidoMaterno = trim((string) ($input['apellido_materno'] ?? '')) ?: null;
        $cargo = trim((string) ($input['cargo'] ?? '')) ?: null;
        $telefono = trim((string) ($input['telefono'] ?? '')) ?: null;

        if ($fiscaliaId <= 0 || $nombres === '') {
            throw new InvalidArgumentException('Fiscalía y nombres son requeridos');
        }

        $id = $this->repository->createFiscal($fiscaliaId, $nombres, $apellidoPaterno, $apellidoMaterno, $cargo, $telefono);
        $label = trim($nombres . ' ' . ($apellidoPaterno ?? '') . ' ' . ($apellidoMaterno ?? ''));

        return ['id' => $id, 'label' => $label, 'type' => 'fiscal'];
    }

    public function createSimpleCatalog(string $type, string $nombre): array
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('Nombre requerido');
        }

        if (!isset(self::SIMPLE_CATALOGS[$type])) {
            throw new InvalidArgumentException('Tipo no permitido');
        }

        $table = self::SIMPLE_CATALOGS[$type]['table'];
        $column = self::SIMPLE_CATALOGS[$type]['col'];
        $id = $this->repository->findCatalogIdByName($table, $column, $nombre);

        if ($id === null) {
            $id = $this->repository->createCatalogItem($table, $column, $nombre);
        }

        return ['id' => $id, 'label' => $nombre, 'type' => $type];
    }

    public function registerAccidente(array $input): array
    {
        $payload = $this->normalizePayload($input);
        $this->validatePayload($payload);

        $accidenteId = $this->repository->transaction(function (AccidenteRepository $repository) use ($payload): int {
            $accidenteId = $repository->insertAccidente($payload);
            $repository->attachModalidades($accidenteId, $payload['modalidad_ids']);
            $repository->attachConsecuencias($accidenteId, $payload['consecuencia_ids']);
            $repository->updateSidpol($accidenteId, $this->generatedSidpol($accidenteId));
            return $accidenteId;
        });

        return [
            'id' => $accidenteId,
            'sidpol' => $this->generatedSidpol($accidenteId),
        ];
    }

    public function updateAccidente(int $accidenteId, array $input): array
    {
        $payload = $this->normalizePayload($input);
        $this->validatePayload($payload);

        $this->repository->transaction(function (AccidenteRepository $repository) use ($accidenteId, $payload): void {
            $repository->updateAccidente($accidenteId, $payload);
            $repository->syncModalidades($accidenteId, $payload['modalidad_ids']);
            $repository->syncConsecuencias($accidenteId, $payload['consecuencia_ids']);
        });

        return [
            'id' => $accidenteId,
            'sidpol' => $this->generatedSidpol($accidenteId),
        ];
    }

    public function generatedSidpol(int $accidenteId): string
    {
        return str_pad((string) $accidenteId, 8, '0', STR_PAD_LEFT);
    }

    private function normalizePayload(array $input): array
    {
        $codDep = str_pad(substr((string) ($input['cod_dep'] ?? ''), 0, 2), 2, '0', STR_PAD_LEFT);
        $codProv = str_pad(substr((string) ($input['cod_prov'] ?? ''), 0, 2), 2, '0', STR_PAD_LEFT);
        $codDist = str_pad(substr((string) ($input['cod_dist'] ?? ''), 0, 2), 2, '0', STR_PAD_LEFT);

        return [
            'registro_sidpol' => trim((string) ($input['registro_sidpol'] ?? '')) ?: null,
            'lugar' => trim((string) ($input['lugar'] ?? '')),
            'referencia' => trim((string) ($input['referencia'] ?? '')),
            'cod_dep' => $codDep,
            'cod_prov' => $codProv,
            'cod_dist' => $codDist,
            'comisaria_id' => ($input['comisaria_id'] ?? '') !== '' ? (int) $input['comisaria_id'] : null,
            'fecha_accidente' => trim((string) ($input['fecha_accidente'] ?? '')) ?: null,
            'fecha_comunicacion' => trim((string) ($input['fecha_comunicacion'] ?? '')) ?: null,
            'fecha_intervencion' => trim((string) ($input['fecha_intervencion'] ?? '')) ?: null,
            'comunicante_nombre' => trim((string) ($input['comunicante_nombre'] ?? '')) ?: null,
            'comunicante_telefono' => trim((string) ($input['comunicante_telefono'] ?? '')) ?: null,
            'comunicacion_decreto' => trim((string) ($input['comunicacion_decreto'] ?? '')) ?: null,
            'comunicacion_oficio' => trim((string) ($input['comunicacion_oficio'] ?? '')) ?: null,
            'comunicacion_carpeta_nro' => trim((string) ($input['comunicacion_carpeta_nro'] ?? '')) ?: null,
            'fiscalia_id' => ($input['fiscalia_id'] ?? '') !== '' ? (int) $input['fiscalia_id'] : null,
            'fiscal_id' => ($input['fiscal_id'] ?? '') !== '' ? (int) $input['fiscal_id'] : null,
            'nro_informe_policial' => trim((string) ($input['nro_informe_policial'] ?? '')) ?: null,
            'sentido' => trim((string) ($input['sentido'] ?? '')) ?: null,
            'secuencia' => trim((string) ($input['secuencia'] ?? '')) ?: null,
            'estado' => (string) ($input['estado'] ?? 'Pendiente'),
            'modalidad_ids' => array_values(array_filter(array_map('intval', $input['modalidad_ids'] ?? []), static fn (int $id): bool => $id > 0)),
            'consecuencia_ids' => array_values(array_filter(array_map('intval', $input['consecuencia_ids'] ?? []), static fn (int $id): bool => $id > 0)),
        ];
    }

    private function validatePayload(array $payload): void
    {
        if ($payload['lugar'] === '' || !$payload['cod_dep'] || !$payload['cod_prov'] || !$payload['cod_dist'] || !$payload['fecha_accidente']) {
            throw new InvalidArgumentException('Completa los campos obligatorios (*).');
        }

        if ($payload['modalidad_ids'] === [] || $payload['consecuencia_ids'] === []) {
            throw new InvalidArgumentException('Selecciona al menos una Modalidad y una Consecuencia.');
        }

        if (!(ctype_digit($payload['cod_dep']) && strlen($payload['cod_dep']) === 2)
            || !(ctype_digit($payload['cod_prov']) && strlen($payload['cod_prov']) === 2)
            || !(ctype_digit($payload['cod_dist']) && strlen($payload['cod_dist']) === 2)) {
            throw new InvalidArgumentException('Selecciona un Distrito válido.');
        }

        if (!$this->repository->distritoExists($payload['cod_dep'], $payload['cod_prov'], $payload['cod_dist'])) {
            throw new InvalidArgumentException('Selecciona un Distrito válido.');
        }

        if (!$payload['comisaria_id'] || $payload['comisaria_id'] <= 0) {
            throw new InvalidArgumentException('Selecciona una Comisaría.');
        }

        if (!$this->repository->comisariaMappedToDistrito($payload['comisaria_id'], $payload['cod_dep'], $payload['cod_prov'], $payload['cod_dist'])) {
            throw new InvalidArgumentException('La comisaría no pertenece al distrito seleccionado.');
        }

        if ($payload['fiscal_id'] && !$this->repository->fiscalBelongsToFiscalia($payload['fiscal_id'], $payload['fiscalia_id'])) {
            throw new InvalidArgumentException('El fiscal seleccionado no pertenece a la fiscalía elegida.');
        }
    }
}
