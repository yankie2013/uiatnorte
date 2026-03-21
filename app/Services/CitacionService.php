<?php

namespace App\Services;

use App\Repositories\CitacionRepository;
use InvalidArgumentException;

final class CitacionService
{
    private const CALIDADES = [
        'Efectivo policial', 'Familiar mas cercano', 'Investigado', 'Testigo', 'Abogado',
        'Propietario del vehiculo', 'Conductor', 'Pasajero', 'Peaton', 'Relacionado'
    ];

    private const TIPOS = [
        'Toma de declaracion', 'Reconocimiento', 'Reconstruccion', 'Exhibicion de documentos',
        'Entrega de objetos', 'Ampliacion de declaracion'
    ];

    public function __construct(private CitacionRepository $repository)
    {
    }

    public function formContext(int $accidenteId): array
    {
        return [
            'personas' => $this->repository->personasVinculadas($accidenteId),
            'oficios' => $this->repository->oficiosByAccidente($accidenteId),
            'calidades' => self::CALIDADES,
            'tipos' => self::TIPOS,
        ];
    }

    public function defaultData(?array $row = null): array
    {
        return [
            'persona' => '',
            'persona_nombres' => $row['persona_nombres'] ?? '',
            'persona_apep' => $row['persona_apep'] ?? '',
            'persona_apem' => $row['persona_apem'] ?? '',
            'persona_doc_tipo' => $row['persona_doc_tipo'] ?? 'DNI',
            'persona_doc_num' => $row['persona_doc_num'] ?? '',
            'persona_domicilio' => $row['persona_domicilio'] ?? '',
            'persona_edad' => $row['persona_edad'] ?? '',
            'en_calidad' => $row['en_calidad'] ?? '',
            'tipo_diligencia' => $row['tipo_diligencia'] ?? '',
            'fecha' => $row['fecha'] ?? date('Y-m-d'),
            'hora' => isset($row['hora']) ? substr((string) $row['hora'], 0, 5) : '09:00',
            'lugar' => $row['lugar'] ?? '',
            'motivo' => $row['motivo'] ?? '',
            'orden_citacion' => $row['orden_citacion'] ?? 1,
            'oficio_id' => $row['oficio_id'] ?? '',
        ];
    }

    public function create(int $accidenteId, array $input): array
    {
        [$fuente, $fuenteId] = $this->parsePersonaSelector((string) ($input['persona'] ?? ''));
        $payload = $this->createPayload($input);
        $id = $this->repository->createFromView($accidenteId, $fuente, $fuenteId, $payload);
        $persona = $this->repository->personaVinculada($accidenteId, $fuente, $fuenteId);
        $fullName = trim((string) (($persona['nombres'] ?? '') . ' ' . ($persona['apellido_paterno'] ?? '') . ' ' . ($persona['apellido_materno'] ?? '')));

        return [
            'id' => $id,
            'fuente' => $fuente,
            'fuente_id' => $fuenteId,
            'nombre_persona' => $fullName !== '' ? $fullName : 'Persona citada',
            'payload' => $payload,
        ];
    }

    public function detail(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function update(int $id, array $input): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Citacion no encontrada.');
        }
        $this->repository->update($id, $this->updatePayload($input));
    }

    public function delete(int $id, ?int $accidenteId = null): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Citacion no encontrada.');
        }
        $this->repository->delete($id, $accidenteId);
    }

    public function listado(int $accidenteId, array $filters): array
    {
        return $this->repository->searchByAccidente($accidenteId, $filters);
    }

    public function calendarPayload(int $accidenteId, int $citacionId, array $createResult): array
    {
        $payload = $createResult['payload'];
        $titulo = 'Citacion - ' . $createResult['nombre_persona'];
        if ($payload['tipo_diligencia'] !== '') {
            $titulo .= ' - ' . $payload['tipo_diligencia'];
        }

        $lines = [
            'Accidente ID: ' . $accidenteId,
            'Citacion ID: ' . $citacionId,
            'Persona: ' . $createResult['nombre_persona'] . ' (' . $createResult['fuente'] . ':' . $createResult['fuente_id'] . ')',
            'En calidad de: ' . $payload['en_calidad'],
            'Tipo de diligencia: ' . $payload['tipo_diligencia'],
            'Lugar: ' . $payload['lugar'],
            'Motivo / Observaciones: ' . $payload['motivo'],
            'Orden de citacion: ' . $payload['orden_citacion'],
        ];
        if (!empty($payload['oficio_id'])) {
            $lines[] = 'Oficio que ordena (ID): ' . $payload['oficio_id'];
        }

        return [
            'fecha' => $payload['fecha'],
            'hora' => $payload['hora'],
            'titulo' => $titulo,
            'descripcion' => implode("\n", $lines),
            'lugar' => $payload['lugar'],
            'duracion' => 60,
        ];
    }

    private function parsePersonaSelector(string $value): array
    {
        $value = trim($value);
        if ($value === '' || !str_contains($value, ':')) {
            throw new InvalidArgumentException('Selecciona una persona.');
        }
        [$fuente, $id] = explode(':', $value, 2);
        $fuente = strtoupper(trim($fuente));
        $fuenteId = (int) trim($id);
        if (!in_array($fuente, ['INV', 'PNP', 'PRO', 'FAM'], true) || $fuenteId <= 0) {
            throw new InvalidArgumentException('Persona invalida.');
        }
        return [$fuente, $fuenteId];
    }

    private function createPayload(array $input): array
    {
        $payload = [
            'en_calidad' => trim((string) ($input['en_calidad'] ?? '')),
            'tipo_diligencia' => trim((string) ($input['tipo_diligencia'] ?? '')),
            'fecha' => trim((string) ($input['fecha'] ?? '')),
            'hora' => trim((string) ($input['hora'] ?? '')),
            'lugar' => trim((string) ($input['lugar'] ?? '')),
            'motivo' => trim((string) ($input['motivo'] ?? '')),
            'orden_citacion' => max(1, (int) ($input['orden_citacion'] ?? 1)),
            'oficio_id' => ($input['oficio_id'] ?? '') !== '' ? (int) $input['oficio_id'] : null,
        ];

        if ($payload['en_calidad'] === '') {
            throw new InvalidArgumentException('Selecciona la calidad.');
        }
        if ($payload['tipo_diligencia'] === '') {
            throw new InvalidArgumentException('Indica el tipo de diligencia.');
        }
        if ($payload['fecha'] === '') {
            throw new InvalidArgumentException('Indica la fecha.');
        }
        if ($payload['hora'] === '') {
            throw new InvalidArgumentException('Indica la hora.');
        }
        if ($payload['lugar'] === '') {
            throw new InvalidArgumentException('Indica el lugar.');
        }
        if ($payload['motivo'] === '') {
            throw new InvalidArgumentException('Indica el motivo.');
        }

        return $payload;
    }

    private function updatePayload(array $input): array
    {
        $payload = [
            'persona_nombres' => trim((string) ($input['persona_nombres'] ?? '')),
            'persona_apep' => trim((string) ($input['persona_apep'] ?? '')),
            'persona_apem' => trim((string) ($input['persona_apem'] ?? '')),
            'persona_doc_tipo' => trim((string) ($input['persona_doc_tipo'] ?? 'DNI')),
            'persona_doc_num' => trim((string) ($input['persona_doc_num'] ?? '')),
            'persona_domicilio' => trim((string) ($input['persona_domicilio'] ?? '')),
            'persona_edad' => ($input['persona_edad'] ?? '') !== '' ? trim((string) $input['persona_edad']) : null,
            'en_calidad' => trim((string) ($input['en_calidad'] ?? '')),
            'tipo_diligencia' => trim((string) ($input['tipo_diligencia'] ?? '')),
            'fecha' => trim((string) ($input['fecha'] ?? '')),
            'hora' => trim((string) ($input['hora'] ?? '')),
            'lugar' => trim((string) ($input['lugar'] ?? '')),
            'motivo' => trim((string) ($input['motivo'] ?? '')),
            'orden_citacion' => max(1, (int) ($input['orden_citacion'] ?? 1)),
            'oficio_id' => ($input['oficio_id'] ?? '') !== '' ? (int) $input['oficio_id'] : null,
        ];

        if ($payload['en_calidad'] === '') {
            throw new InvalidArgumentException('Selecciona "En calidad de".');
        }
        if ($payload['tipo_diligencia'] === '') {
            throw new InvalidArgumentException('Selecciona "Tipo de diligencia".');
        }
        if ($payload['fecha'] === '') {
            throw new InvalidArgumentException('Indica la fecha.');
        }
        if ($payload['hora'] === '') {
            throw new InvalidArgumentException('Indica la hora.');
        }
        if ($payload['lugar'] === '') {
            throw new InvalidArgumentException('Indica el lugar.');
        }
        if ($payload['motivo'] === '') {
            throw new InvalidArgumentException('Indica el motivo / observaciones.');
        }

        return $payload;
    }
}
