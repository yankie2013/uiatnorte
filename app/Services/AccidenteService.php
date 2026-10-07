<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AccidenteRepository;
use InvalidArgumentException;

final class AccidenteService
{
    private const TIPOS_REGISTRO = ['Carpeta', 'Intervencion'];

    private const SIMPLE_CATALOGS = [
        'fiscalia' => ['table' => 'fiscalia', 'col' => 'nombre'],
        'modalidad' => ['table' => 'modalidad_accidente', 'col' => 'nombre'],
        'consecuencia' => ['table' => 'consecuencia_accidente', 'col' => 'nombre'],
    ];

    public function __construct(private AccidenteRepository $repository)
    {
    }

    private const DISTRITOS_LIMA_NORTE = [
        '01' => ['02', '06', '10', '12', '17', '25', '35', '39'],
        '04' => ['01', '02', '03', '04', '05', '06', '07'],
    ];

    private function registraEnLimaNorte(): bool
    {
        $unit = mb_strtoupper(trim((string)(\App\Support\Auth::user()['unidad'] ?? '')), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $unit) === 'DEPIAT NORTE';
    }

    public function distritosRegistro(string $dep, string $prov): array
    {
        if ($this->registraEnLimaNorte() && ($dep !== '15' || !isset(self::DISTRITOS_LIMA_NORTE[$prov]))) return [];
        $districts = $this->repository->distritos($dep, $prov);
        return $this->registraEnLimaNorte()
            ? array_values(array_filter($districts, static fn(array $district): bool => in_array((string)$district['cod_dist'], self::DISTRITOS_LIMA_NORTE[$prov], true)))
            : $districts;
    }

    public function fiscaliasSeleccion(): array
    {
        return array_map(static fn(array $row): array => $row + ['selection'=>\App\Support\FiscaliaSelection::describe($row['nombre'])], $this->repository->fiscalias());
    }

    public function fiscalesSeleccion(int $id, ?string $number = null): array
    {
        return $this->repository->fiscalesByFiscalia($id, $number);
    }

    public function fiscaliaConFiscal(?int $id, ?int $fiscal): ?int
    {
        if (!$id || !$fiscal) return $id;
        $source = $this->repository->fiscaliaDeFiscal($fiscal);
        if ($source === $id) return $id;
        $selection = [];
        foreach ($this->fiscaliasSeleccion() as $row) $selection[(int)$row['id']] = $row['selection'];
        if ($source && isset($selection[$id], $selection[$source]) && \App\Support\FiscaliaSelection::sameDispatch($selection[$id], $selection[$source])) return $source;
        throw new InvalidArgumentException('El fiscal seleccionado no pertenece a la fiscalía elegida.');
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

        $officeName = array_key_exists('fiscalia_numero', $input) ? $this->nombreFiscaliaRegistro($fiscaliaId, $input['fiscalia_numero']) : null;
        if ($officeName !== null) {
            $fiscaliaId = $this->repository->findCatalogIdByName('fiscalia', 'nombre', $officeName)
                ?? $this->repository->createCatalogItem('fiscalia', 'nombre', $officeName);
        }
        $id = $this->repository->createFiscal($fiscaliaId, $nombres, $apellidoPaterno, $apellidoMaterno, $cargo, $telefono);
        $label = trim($nombres . ' ' . ($apellidoPaterno ?? '') . ' ' . ($apellidoMaterno ?? ''));

        return ['id' => $id, 'label' => $label, 'type' => 'fiscal', 'fiscalia' => $officeName !== null ? ['id'=>$fiscaliaId, 'nombre'=>$officeName, 'selection'=>\App\Support\FiscaliaSelection::describe($officeName)] : null];
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

    public function nombreFiscaliaRegistro(?int $id, mixed $number): ?string
    {
        $number = trim((string)$number);
        if ($number !== '' && !in_array($number, ['1', '2', '3'], true)) throw new InvalidArgumentException('El número de fiscalía debe ser 1°, 2° o 3°. Puede dejarse vacío.');
        if (!$id) return null;
        foreach ($this->fiscaliasSeleccion() as $office) {
            if ((int)$office['id'] !== $id) continue;
            $selection = $office['selection'];
            if ($selection === null) return null;
            $name = \App\Support\FiscaliaSelection::name($selection['office'], $selection['dispatch'], $number === '' ? 0 : (int)$number);
            if (mb_strlen($name) > 150) throw new InvalidArgumentException('El nombre completo de la fiscalía es demasiado largo.');
            return $name;
        }
        throw new InvalidArgumentException('Selecciona una fiscalía válida.');
    }

    public function registerAccidente(array $input): array
    {
        $payload = $this->normalizePayload($input);
        $payload['fiscalia_id'] = $this->fiscaliaConFiscal($payload['fiscalia_id'], $payload['fiscal_id']);
        $this->validatePayload($payload);
        $officeName = array_key_exists('fiscalia_numero', $input) ? $this->nombreFiscaliaRegistro($payload['fiscalia_id'], $input['fiscalia_numero']) : null;
        if ($officeName !== null && $payload['fiscal_id']) {
            foreach ($this->fiscaliasSeleccion() as $office) {
                if ((int)$office['id'] !== $payload['fiscalia_id']) continue;
                if (!\App\Support\FiscaliaSelection::sameDispatch($office['selection'], \App\Support\FiscaliaSelection::describe($officeName))) {
                    throw new InvalidArgumentException('El fiscal debe coincidir con el despacho y el número de fiscalía seleccionados.');
                }
            }
        }

        $accidenteId = $this->repository->transaction(function (AccidenteRepository $repository) use ($payload, $officeName): int {
            if ($officeName !== null) {
                $payload['fiscalia_id'] = $repository->findCatalogIdByName('fiscalia', 'nombre', $officeName)
                    ?? $repository->createCatalogItem('fiscalia', 'nombre', $officeName);
            }
            $accidenteId = $repository->insertAccidente($payload);
            if (\App\Support\Access::role()==='guardia') $repository->registerGuardiaDraft($accidenteId, $payload);
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

    public function updateGuardiaDraft(int $id, array $input): array
    {
        $pdo = \App\Database\Database::connection();
        return $this->repository->transaction(function ($repository) use ($pdo, $id, $input): array {
            $lock=$pdo->prepare('SELECT id FROM comunicaciones_guardia WHERE accidente_id=? FOR UPDATE');
            $lock->execute([$id]);
            $check=$pdo->prepare('SELECT rbac_guardia_draft(?)');$check->execute([$id]);
            if (\App\Support\Access::role() !== 'guardia' || !$check->fetchColumn()) {
                throw new \RuntimeException('El registro ya no permite correcciones.');
            }
            $payload=$this->normalizePayload($input);
            $payload['fiscalia_id']=$this->fiscaliaConFiscal($payload['fiscalia_id'], $payload['fiscal_id']);
            $this->validatePayload($payload);
            $name=$this->nombreFiscaliaRegistro($payload['fiscalia_id'], $input['fiscalia_numero'] ?? '');
            if ($name !== null) {
                if ($payload['fiscal_id']) {
                    foreach($this->fiscaliasSeleccion() as $office) {
                        if((int)$office['id']===$payload['fiscalia_id'] && !\App\Support\FiscaliaSelection::sameDispatch($office['selection'], \App\Support\FiscaliaSelection::describe($name)))throw new InvalidArgumentException('El fiscal debe coincidir con el despacho y el número de fiscalía seleccionados.');
                    }
                }
                $payload['fiscalia_id']=$repository->findCatalogIdByName('fiscalia','nombre',$name) ?? $repository->createCatalogItem('fiscalia','nombre',$name);
            }
            $repository->updateAccidente($id,$payload);
            $repository->syncModalidades($id,$payload['modalidad_ids']);
            $repository->syncConsecuencias($id,$payload['consecuencia_ids']);
            return ['id'=>$id,'sidpol'=>$this->generatedSidpol($id)];
        });
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

        $tipoRegistro = trim((string) ($input['tipo_registro'] ?? ''));

        return [
            'registro_sidpol' => trim((string) ($input['registro_sidpol'] ?? '')) ?: null,
            'tipo_registro' => in_array($tipoRegistro, self::TIPOS_REGISTRO, true) ? $tipoRegistro : null,
            'lugar' => trim((string) ($input['lugar'] ?? '')),
            'referencia' => trim((string) ($input['referencia'] ?? '')),
            'latitud' => $this->normalizeCoordinate($input['latitud'] ?? null),
            'longitud' => $this->normalizeCoordinate($input['longitud'] ?? null),
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

        if ($this->registraEnLimaNorte() && ($payload['cod_dep'] !== '15' || !in_array($payload['cod_dist'], self::DISTRITOS_LIMA_NORTE[$payload['cod_prov']] ?? [], true))) {
            throw new InvalidArgumentException('Selecciona un distrito de la jurisdicción de DEPIAT NORTE.');
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

        if ($payload['fiscalia_id']) {
            $expectedOffice = \App\Support\FiscaliaSelection::officeForDistrict($payload['cod_dep'], $payload['cod_prov'], $payload['cod_dist']);
            $found = false;
            foreach ($this->fiscaliasSeleccion() as $office) {
                if ((int)$office['id'] !== $payload['fiscalia_id']) continue;
                $found = true;
                if ($expectedOffice !== null && ($office['selection']['office'] ?? null) !== $expectedOffice) {
                    throw new InvalidArgumentException('Selecciona una fiscalía correspondiente al distrito del accidente.');
                }
            }
            if (!$found) throw new InvalidArgumentException('Selecciona una fiscalía válida.');
        }

        if ($payload['fiscal_id'] && !$this->repository->fiscalBelongsToFiscalia($payload['fiscal_id'], $payload['fiscalia_id'])) {
            throw new InvalidArgumentException('El fiscal seleccionado no pertenece a la fiscalía elegida.');
        }

        if ($payload['tipo_registro'] !== null && !in_array($payload['tipo_registro'], self::TIPOS_REGISTRO, true)) {
            throw new InvalidArgumentException('Selecciona un tipo de registro válido.');
        }

        if (($payload['latitud'] === null) xor ($payload['longitud'] === null)) {
            throw new InvalidArgumentException('La georreferencia debe incluir latitud y longitud.');
        }

        if ($payload['latitud'] !== null && ($payload['latitud'] < -90 || $payload['latitud'] > 90)) {
            throw new InvalidArgumentException('La latitud debe estar entre -90 y 90.');
        }

        if ($payload['longitud'] !== null && ($payload['longitud'] < -180 || $payload['longitud'] > 180)) {
            throw new InvalidArgumentException('La longitud debe estar entre -180 y 180.');
        }
    }

    private function normalizeCoordinate(mixed $value): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $raw);
        if (!is_numeric($normalized)) {
            throw new InvalidArgumentException('Las coordenadas deben tener formato numérico válido.');
        }

        return round((float) $normalized, 7);
    }
}
