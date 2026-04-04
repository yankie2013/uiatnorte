<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoRecibidoRepository;
use InvalidArgumentException;

final class DocumentoRecibidoService
{
    private const ESTADOS = ['Pendiente', 'Revisado', 'Archivado'];

    public function __construct(private DocumentoRecibidoRepository $repository)
    {
    }

    public function formContext(?int $accidenteId = null): array
    {
        $oficios = $this->repository->oficiosByAccidente($accidenteId);
        $asuntoIds = [];
        foreach ($oficios as $oficio) {
            if (!empty($oficio['asunto_id'])) {
                $asuntoIds[] = (int) $oficio['asunto_id'];
            }
        }

        return [
            'accidentes' => $this->repository->accidentes(),
            'oficios' => $oficios,
            'asuntos' => $this->repository->asuntosByIds($asuntoIds),
            'estados' => self::ESTADOS,
        ];
    }

    public function listado(array $filters): array
    {
        return [
            'rows' => $this->repository->search($filters),
            'accidentes' => $this->repository->accidentes(),
            'tipos' => array_filter($this->repository->distinctTipos(), static fn($v) => (string) $v !== ''),
            'estados' => self::ESTADOS,
        ];
    }

    public function detalle(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function crear(array $input): int
    {
        $input['fecha_recepcion'] = date('Y-m-d');
        return $this->repository->create($this->payload($input));
    }

    public function actualizar(int $id, array $input): void
    {
        $actual = $this->repository->find($id);
        if ($actual === null) {
            throw new InvalidArgumentException('Documento recibido no encontrado.');
        }
        $input['fecha_recepcion'] = (string) ($actual['fecha_recepcion'] ?? $actual['fecha_recepcion_resuelta'] ?? $actual['fecha'] ?? date('Y-m-d'));
        $this->repository->update($id, $this->payload($input));
    }

    public function eliminar(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Documento recibido no encontrado.');
        }
        $this->repository->delete($id);
    }

    public function oficioLabel(array $oficio, array $asuntos): string
    {
        $label = (!empty($oficio['numero']) || !empty($oficio['anio']))
            ? ('Oficio ' . ($oficio['numero'] ?? '?') . '/' . ($oficio['anio'] ?? '?'))
            : ('Oficio #' . $oficio['id']);

        $snippet = '';
        $asuntoId = (int) ($oficio['asunto_id'] ?? 0);
        if ($asuntoId > 0 && !empty($asuntos[$asuntoId]['nombre'])) {
            $snippet = $asuntos[$asuntoId]['nombre'];
        } elseif (!empty($oficio['motivo'])) {
            $snippet = $oficio['motivo'];
        } elseif (!empty($oficio['referencia_texto'])) {
            $snippet = $oficio['referencia_texto'];
        } elseif (!empty($oficio['contenido'])) {
            $snippet = $oficio['contenido'];
        }

        return $label . ($snippet !== '' ? ' - ' . mb_strimwidth(strip_tags((string) $snippet), 0, 120, '...') : '');
    }

    public function defaultData(?array $row = null): array
    {
        $today = date('Y-m-d');
        $fechaRecepcion = $row['fecha_recepcion'] ?? $row['fecha_recepcion_resuelta'] ?? null;
        $fechaDocumento = $row['fecha_documento'] ?? $row['fecha_documento_resuelta'] ?? null;
        $fechaLegacy = $row['fecha'] ?? null;

        return [
            'accidente_id' => $row['accidente_id'] ?? '',
            'asunto' => $row['asunto'] ?? '',
            'entidad_persona' => $row['entidad_persona'] ?? '',
            'tipo_documento' => $row['tipo_documento'] ?? '',
            'numero_documento' => $row['numero_documento'] ?? '',
            'fecha_recepcion' => $fechaRecepcion ?? ($row === null ? $today : ($fechaLegacy ?? $today)),
            'fecha_documento' => $fechaDocumento ?? ($fechaLegacy ?? ''),
            'contenido' => $row['contenido'] ?? '',
            'referencia_oficio_id' => $row['referencia_oficio_id'] ?? '',
            'estado' => $row['estado'] ?? '',
        ];
    }

    private function payload(array $input): array
    {
        $estado = trim((string) ($input['estado'] ?? ''));
        $fechaRecepcion = $this->nullable($input['fecha_recepcion'] ?? date('Y-m-d')) ?? date('Y-m-d');
        $fechaDocumento = $this->nullable($input['fecha_documento'] ?? null);

        if ($estado !== '' && !in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado invalido.');
        }

        return [
            'accidente_id' => ($input['accidente_id'] ?? '') !== '' ? (int) $input['accidente_id'] : null,
            'asunto' => $this->nullable($input['asunto'] ?? null),
            'entidad_persona' => $this->nullable($input['entidad_persona'] ?? null),
            'tipo_documento' => $this->nullable($input['tipo_documento'] ?? null),
            'numero_documento' => $this->nullable($input['numero_documento'] ?? null),
            'fecha_recepcion' => $fechaRecepcion,
            'fecha_documento' => $fechaDocumento,
            'fecha' => $fechaDocumento ?? $fechaRecepcion,
            'contenido' => $this->nullable($input['contenido'] ?? null),
            'referencia_oficio_id' => ($input['referencia_oficio_id'] ?? '') !== '' ? (int) $input['referencia_oficio_id'] : null,
            'estado' => $estado !== '' ? $estado : null,
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
