<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\OficioRepository;
use InvalidArgumentException;

final class OficioService
{
    private const ESTADOS = ['BORRADOR', 'FIRMADO', 'ENVIADO', 'ANULADO', 'ARCHIVADO'];
    private const TIPOS = ['SOLICITAR', 'REMITIR'];

    public function __construct(private OficioRepository $repository)
    {
    }

    public function formContext(?int $preselectedAccidenteId = null): array
    {
        $oficialAnos = $this->repository->oficialAnos();
        $defaultOficial = '';
        foreach ($oficialAnos as $item) {
            if ((int) ($item['vigente'] ?? 0) === 1) {
                $defaultOficial = (string) $item['id'];
                break;
            }
        }
        if ($defaultOficial === '' && $oficialAnos !== []) {
            $defaultOficial = (string) $oficialAnos[0]['id'];
        }

        return [
            'entidades' => $this->repository->entidades(),
            'oficial_anos' => $oficialAnos,
            'oficial_ano_default' => $defaultOficial,
            'grado_cargo' => $this->repository->gradoCargo(),
            'accidentes' => $this->repository->accidentes(),
            'tipos' => self::TIPOS,
            'estados' => self::ESTADOS,
            'preselected_accidente_id' => $preselectedAccidenteId,
        ];
    }

    public function listado(array $filters): array
    {
        return [
            'rows' => $this->repository->search($filters),
            'entidades' => $this->repository->entidades(),
            'estados' => self::ESTADOS,
        ];
    }

    public function detalle(int $id): ?array
    {
        return $this->repository->detail($id);
    }

    public function oficio(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function create(array $input): array
    {
        $payload = $this->payload($input, null);
        $id = $this->repository->create($payload);
        return ['id' => $id, 'numero' => $payload['numero'], 'anio' => $payload['anio']];
    }

    public function update(int $id, array $input): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Oficio no encontrado.');
        }
        $payload = $this->payload($input, $id);
        $this->repository->update($id, $payload);
    }

    public function delete(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Oficio no encontrado.');
        }
        $this->repository->delete($id);
    }

    public function changeEstado(int $id, string $estado): void
    {
        $estado = strtoupper(trim($estado));
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado inválido.');
        }
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Oficio no encontrado.');
        }
        $this->repository->updateEstado($id, $estado);
    }

    public function defaultData(?array $row = null, ?int $preAccidenteId = null): array
    {
        return [
            'accidente_id' => $row['accidente_id'] ?? ($preAccidenteId ?: ''),
            'anio_oficio' => $row['anio'] ?? date('Y'),
            'numero_oficio' => $row['numero'] ?? '',
            'fecha_emision' => $row['fecha_emision'] ?? date('Y-m-d'),
            'oficial_ano_id' => $row['oficial_ano_id'] ?? '',
            'entidad_id' => $row['entidad_id_destino'] ?? '',
            'subentidad_id' => $row['subentidad_destino_id'] ?? '',
            'grado_cargo_id' => $row['grado_cargo_id'] ?? '',
            'persona_id' => $row['persona_destino_id'] ?? '',
            'tipo' => $this->asuntoTipo($row['asunto_id'] ?? null) ?: 'SOLICITAR',
            'asunto_id' => $row['asunto_id'] ?? '',
            'motivo' => $row['motivo'] ?? '',
            'referencia_texto' => $row['referencia_texto'] ?? '',
            'involucrado_vehiculo_id' => $row['involucrado_vehiculo_id'] ?? '',
            'involucrado_persona_id' => $row['involucrado_persona_id'] ?? '',
            'estado' => $row['estado'] ?? 'BORRADOR',
        ];
    }

    public function nextNumero(int $anio): int
    {
        return $this->repository->nextNumero($anio);
    }

    public function subentidades(int $entidadId): array
    {
        return $this->repository->subentidadesByEntidad($entidadId);
    }

    public function personas(int $entidadId): array
    {
        return $this->repository->personasByEntidad($entidadId);
    }

    public function asuntos(int $entidadId, string $tipo): array
    {
        $tipo = strtoupper(trim($tipo));
        if (!in_array($tipo, self::TIPOS, true)) {
            $tipo = 'SOLICITAR';
        }
        return $this->repository->asuntosByEntidadTipo($entidadId, $tipo);
    }

    public function asuntoInfo(int $id): ?array
    {
        return $this->repository->asuntoInfo($id);
    }

    public function asuntoVariantes(int $id): array
    {
        return $this->repository->asuntoVariantes($id);
    }

    public function gradoCargo(): array
    {
        return $this->repository->gradoCargo();
    }

    public function vehiculosAccidente(int $accidenteId): array
    {
        return $this->repository->vehiculosByAccidente($accidenteId);
    }

    public function fallecidosAccidente(int $accidenteId): array
    {
        return $this->repository->fallecidosByAccidente($accidenteId);
    }

    public function accidenteIdBySidpol(string $sidpol): ?int
    {
        return $this->repository->accidenteIdBySidpol(trim($sidpol));
    }

    private function asuntoTipo(mixed $asuntoId): ?string
    {
        $id = (int) $asuntoId;
        if ($id <= 0) {
            return null;
        }
        $info = $this->repository->asuntoInfo($id);
        return $info['tipo'] ?? null;
    }

    private function payload(array $input, ?int $excludeId): array
    {
        $fecha = trim((string) ($input['fecha_emision'] ?? ''));
        $anio = (int) ($input['anio_oficio'] ?? 0);
        $numeroIn = trim((string) ($input['numero_oficio'] ?? ''));
        $accidenteId = (int) ($input['accidente_id'] ?? 0);
        $entidadId = (int) ($input['entidad_id'] ?? 0);
        $subentidadId = ($input['subentidad_id'] ?? '') !== '' ? (int) $input['subentidad_id'] : null;
        $personaId = ($input['persona_id'] ?? '') !== '' ? (int) $input['persona_id'] : null;
        $gradoCargoId = ($input['grado_cargo_id'] ?? '') !== '' ? (int) $input['grado_cargo_id'] : null;
        $tipo = strtoupper(trim((string) ($input['tipo'] ?? 'SOLICITAR')));
        $asuntoId = (int) ($input['asunto_id'] ?? 0);
        $motivo = trim((string) ($input['motivo'] ?? ''));
        $referencia = trim((string) ($input['referencia_texto'] ?? ''));
        $oficialAnoId = (int) ($input['oficial_ano_id'] ?? 0);
        $vehiculoId = ($input['involucrado_vehiculo_id'] ?? '') !== '' ? (int) $input['involucrado_vehiculo_id'] : null;
        $personaInvId = ($input['involucrado_persona_id'] ?? '') !== '' ? (int) $input['involucrado_persona_id'] : null;
        $estado = strtoupper(trim((string) ($input['estado'] ?? 'BORRADOR')));

        if ($fecha === '') {
            throw new InvalidArgumentException('La fecha de emisión es obligatoria.');
        }
        if ($accidenteId <= 0) {
            throw new InvalidArgumentException('Debes seleccionar el accidente asociado.');
        }
        if ($entidadId <= 0) {
            throw new InvalidArgumentException('Selecciona la entidad destino.');
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException('Tipo de asunto inválido.');
        }
        if ($asuntoId <= 0) {
            throw new InvalidArgumentException('Selecciona el asunto.');
        }
        $asuntoInfo = $this->repository->asuntoInfo($asuntoId);
        if ($asuntoInfo === null) {
            throw new InvalidArgumentException('El asunto seleccionado no existe.');
        }
        if (strtoupper((string) ($asuntoInfo['tipo'] ?? '')) !== $tipo) {
            throw new InvalidArgumentException('El asunto no corresponde al tipo seleccionado.');
        }
        if ($oficialAnoId <= 0) {
            throw new InvalidArgumentException('Selecciona el nombre oficial del año.');
        }
        if ($motivo === '') {
            throw new InvalidArgumentException('El motivo es obligatorio.');
        }
        if ($anio <= 0) {
            $anio = (int) date('Y', strtotime($fecha));
        }
        $numero = $numeroIn !== '' ? (int) $numeroIn : $this->repository->nextNumero($anio);
        if ($numero <= 0) {
            throw new InvalidArgumentException('Número de oficio inválido.');
        }
        if ($this->repository->numeroExists($anio, $numero, $excludeId)) {
            throw new InvalidArgumentException("El número {$numero} para el año {$anio} ya existe.");
        }
        if (!in_array($estado, self::ESTADOS, true)) {
            $estado = 'BORRADOR';
        }

        return [
            'accidente_id' => $accidenteId,
            'involucrado_vehiculo_id' => $vehiculoId,
            'numero' => $numero,
            'anio' => $anio,
            'fecha_emision' => $fecha,
            'entidad_id_destino' => $entidadId,
            'subentidad_destino_id' => $subentidadId,
            'persona_destino_id' => $personaId,
            'grado_cargo_id' => $gradoCargoId,
            'asunto_id' => $asuntoId,
            'motivo' => $motivo,
            'referencia_texto' => $referencia !== '' ? $referencia : null,
            'oficial_ano_id' => $oficialAnoId,
            'estado' => $estado,
            'involucrado_persona_id' => $personaInvId,
        ];
    }
}
