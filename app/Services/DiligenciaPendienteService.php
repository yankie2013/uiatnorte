<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DiligenciaPendienteRepository;
use InvalidArgumentException;

final class DiligenciaPendienteService
{
    private const ESTADOS = ['Pendiente', 'En proceso', 'Realizado', 'Cancelado'];

    public function __construct(private DiligenciaPendienteRepository $repository)
    {
    }

    public function formContext(?int $accidenteId = null): array
    {
        $oficios = $this->repository->oficiosByAccidente($accidenteId);
        $citaciones = $this->repository->citacionesByAccidente($accidenteId);

        return [
            'tipos' => $this->repository->tipoOptions(),
            'oficios' => array_map([$this, 'formatOficio'], $oficios),
            'citaciones' => array_map([$this, 'formatCitacion'], $citaciones),
            'estados' => self::ESTADOS,
        ];
    }

    public function detalle(int $id): ?array
    {
        $row = $this->repository->find($id);
        if ($row === null) {
            return null;
        }

        $citacionIds = $this->extractCitacionIds($row);
        $oficioLabels = !empty($row['oficio_id']) ? $this->repository->oficioLabels([(int) $row['oficio_id']]) : [];
        $citacionLabels = $this->repository->citacionLabels($citacionIds);

        return [
            'row' => $row,
            'tipo_nombre' => !empty($row['tipo_diligencia_id']) ? $this->repository->tipoNombreById((int) $row['tipo_diligencia_id']) : null,
            'citacion_ids' => $citacionIds,
            'oficio_label' => !empty($row['oficio_id']) ? ($oficioLabels[(int) $row['oficio_id']] ?? ('Oficio #' . $row['oficio_id'])) : '',
            'citaciones_labels' => $citacionLabels,
            'ctx' => $this->formContext(!empty($row['accidente_id']) ? (int) $row['accidente_id'] : null),
        ];
    }

    public function listado(array $filters, int $page, int $perPage): array
    {
        $result = $this->repository->search($filters, $page, $perPage);
        $rows = $result['rows'];

        $oficioIds = [];
        $citacionIds = [];
        foreach ($rows as $row) {
            if (!empty($row['oficio_id'])) {
                $oficioIds[] = (int) $row['oficio_id'];
            }
            $citacionIds = array_merge($citacionIds, $this->extractCitacionIds($row));
        }

        return [
            'rows' => $rows,
            'total' => (int) $result['total'],
            'tipos' => $this->repository->tipoOptions(),
            'estados' => self::ESTADOS,
            'oficios_labels' => $this->repository->oficioLabels($oficioIds),
            'citaciones_labels' => $this->repository->citacionLabels($citacionIds),
        ];
    }

    public function crear(array $input): int
    {
        [$payload, $citacionIds] = $this->payload($input, true);
        return $this->repository->create($payload, $citacionIds);
    }

    public function actualizar(int $id, array $input): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Diligencia no encontrada.');
        }

        [$payload, $citacionIds] = $this->payload($input, false);
        $this->repository->update($id, $payload, $citacionIds);
    }

    public function eliminar(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Diligencia no encontrada.');
        }
        $this->repository->delete($id);
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado inválido.');
        }
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Diligencia no encontrada.');
        }
        $this->repository->updateEstado($id, $estado);
    }

    public function defaultData(?array $row = null): array
    {
        return [
            'accidente_id' => $row['accidente_id'] ?? '',
            'tipo_diligencia_id' => $row['tipo_diligencia_id'] ?? '',
            'contenido' => $row['contenido'] ?? '',
            'estado' => $row['estado'] ?? 'Pendiente',
            'oficio_id' => $row['oficio_id'] ?? '',
            'citacion_id' => $this->extractCitacionIds($row ?? []),
            'documento_realizado' => $row['documento_realizado'] ?? '',
            'documentos_recibidos' => $row['documentos_recibidos'] ?? '',
        ];
    }

    private function payload(array $input, bool $requireAccidente): array
    {
        $errors = [];

        $accidenteId = (int) ($input['accidente_id'] ?? 0);
        $contenido = trim((string) ($input['contenido'] ?? ''));
        $tipoId = (int) ($input['tipo_diligencia_id'] ?? 0);
        if ($tipoId <= 0 && $contenido !== '') {
            $inferredTipo = $this->inferTipoFromContenido($contenido);
            if ($inferredTipo !== null) {
                $tipoId = (int) $inferredTipo['id'];
            }
        }
        $estado = trim((string) ($input['estado'] ?? 'Pendiente'));
        $oficioId = (int) ($input['oficio_id'] ?? 0);
        $documentoRealizado = trim((string) ($input['documento_realizado'] ?? ''));
        $documentosRecibidos = trim((string) ($input['documentos_recibidos'] ?? ''));
        $citacionIds = $this->sanitizeCitacionIds($input['citacion_id'] ?? []);

        if ($requireAccidente && $accidenteId <= 0) {
            $errors[] = 'No se indicó el accidente asociado.';
        }
        if (!in_array($estado, self::ESTADOS, true)) {
            $errors[] = 'Estado inválido.';
        }
        if (mb_strlen($contenido) > 5000) {
            $errors[] = 'Contenido demasiado largo (máx. 5000 caracteres).';
        }
        if (mb_strlen($documentoRealizado) > 255) {
            $errors[] = "El texto de 'Documento realizado' no debe superar 255 caracteres.";
        }
        if (mb_strlen($documentosRecibidos) > 2000) {
            $errors[] = "El texto de 'Documentos recibidos' no debe superar 2000 caracteres.";
        }

        $tipoNombre = $tipoId > 0 ? (string) ($this->repository->tipoNombreById($tipoId) ?? '') : '';
        if ($tipoId > 0 && $tipoNombre === '') {
            $errors[] = 'El tipo de diligencia seleccionado no existe.';
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        return [[
            'accidente_id' => $accidenteId > 0 ? $accidenteId : null,
            'tipo_diligencia_id' => $tipoId > 0 ? $tipoId : null,
            'tipo_diligencia' => $tipoNombre,
            'contenido' => $contenido !== '' ? $contenido : null,
            'estado' => $estado,
            'oficio_id' => $oficioId > 0 ? $oficioId : null,
            'citacion_id' => $citacionIds[0] ?? null,
            'documento_realizado' => $documentoRealizado !== '' ? $documentoRealizado : null,
            'documentos_recibidos' => $documentosRecibidos !== '' ? $documentosRecibidos : null,
        ], $citacionIds];
    }

    private function inferTipoFromContenido(string $contenido): ?array
    {
        $text = $this->normalizeForMatch($contenido);
        if ($text === '') {
            return null;
        }

        $tipos = $this->repository->tipoOptions();
        $best = null;
        $bestScore = 0;

        foreach ($tipos as $tipo) {
            $name = $this->normalizeForMatch((string) ($tipo['nombre'] ?? ''));
            if ($name === '') {
                continue;
            }

            $score = 0;
            foreach (preg_split('/\s+/', $name) ?: [] as $token) {
                if (mb_strlen($token) >= 4 && str_contains($text, $token)) {
                    $score += 2;
                }
            }

            $score += $this->keywordScore($name, $text);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $tipo;
            }
        }

        return $bestScore >= 3 ? $best : null;
    }

    private function keywordScore(string $tipoNombre, string $text): int
    {
        $rules = [
            'protocolo de necropsia' => ['necropsia', 'morgue', 'cadaver', 'occiso', 'protocolo'],
            'peritaje de constatacion de danos' => ['peritaje', 'constatacion', 'danos', 'vehiculo'],
            'camaras de video' => ['camara', 'camaras', 'video vigilancia', 'vigilancia publica', 'vigilancia privada', 'recabado los videos'],
            'manifestacion' => ['declaracion testimonial', 'declaracion', 'manifestacion', 'testigo', 'testigos'],
            'informe policial' => ['informe policial', 'resultado de investigacion'],
            'identificacion de cadaver' => ['identifiquese', 'identificacion', 'identifiquese plenamente', 'occiso', 'cadaver'],
            'resultado de dosaje etilico' => ['dosaje', 'etilico', 'alcoholemia'],
            'inspeccion tecnico policial' => ['inspeccion tecnico policial', 'inspeccion', 'lugar de los hechos'],
            'visualizacion de video' => ['visualizacion', 'transcripcion', 'videos', 'camara de video vigilancia'],
            'croquis demostrativo' => ['croquis', 'demostrativo'],
            'oficio solicitar' => ['solicitar', 'oficio solicitar'],
            'oficio remitir' => ['remitir', 'oficio remitir'],
        ];

        $score = 0;
        foreach ($rules as $typeNeedle => $needles) {
            if (!str_contains($tipoNombre, $typeNeedle)) {
                continue;
            }
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    $score += str_contains($needle, ' ') ? 4 : 2;
                }
            }
        }

        return $score;
    }

    private function normalizeForMatch(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $from = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
        $to = ['a', 'e', 'i', 'o', 'u', 'u', 'n'];
        $value = str_replace($from, $to, $value);
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?: '';
        return preg_replace('/\s+/', ' ', $value) ?: '';
    }

    private function sanitizeCitacionIds(mixed $value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $items = [];
        foreach ($value as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $items[] = $id;
            }
        }

        return array_values(array_unique($items));
    }

    private function extractCitacionIds(array $row): array
    {
        $ids = [];

        if (!empty($row['citacion_ids'])) {
            $decoded = json_decode((string) $row['citacion_ids'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $id = (int) $item;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            }
        }

        if ($ids === [] && !empty($row['citacion_id'])) {
            $id = (int) $row['citacion_id'];
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function formatOficio(array $row): array
    {
        $label = '';
        if (!empty($row['numero'])) {
            $label = 'N° ' . $row['numero'];
        }
        if (!empty($row['anio'])) {
            $label .= ($label !== '' ? ' ' : '') . '(' . $row['anio'] . ')';
        }
        if (!empty($row['motivo'])) {
            $label .= ($label !== '' ? ' - ' : '') . mb_strimwidth((string) $row['motivo'], 0, 80, '...');
        } elseif (!empty($row['referencia_texto'])) {
            $label .= ($label !== '' ? ' - ' : '') . mb_strimwidth((string) $row['referencia_texto'], 0, 80, '...');
        }

        $row['label'] = $label !== '' ? $label : ('Oficio #' . $row['id']);
        return $row;
    }

    private function formatCitacion(array $row): array
    {
        $label = '';
        if (!empty($row['numero'])) {
            $label = (string) $row['numero'];
        }
        if (!empty($row['texto'])) {
            $label .= ($label !== '' ? ' - ' : '') . mb_strimwidth((string) $row['texto'], 0, 120, '...');
        }

        $row['label'] = $label !== '' ? $label : ('Citación #' . $row['id']);
        return $row;
    }
}
