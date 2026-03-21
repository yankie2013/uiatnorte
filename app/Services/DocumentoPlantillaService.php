<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoPlantillaRepository;
use InvalidArgumentException;

final class DocumentoPlantillaService
{
    public function __construct(private DocumentoPlantillaRepository $repository)
    {
    }

    public function oficioRemitirData(int $oficioId): array
    {
        $oficio = $this->repository->oficioRemitirById($oficioId);
        if ($oficio === null) {
            throw new InvalidArgumentException('Oficio no encontrado.');
        }

        $accidenteId = (int) ($oficio['accidente_id'] ?? 0);
        $modalidad = $this->listaEspanol($this->explodePipe($this->repository->accidenteModalidad($accidenteId)));
        $personas = $this->repository->involucradosPersonasByAccidente($accidenteId);
        $vehiculos = $this->repository->involucradosVehiculosByAccidente($accidenteId);

        return [
            'filename' => 'Oficio_Remitir_' . ($oficio['numero'] ?? $oficioId) . '-' . ($oficio['anio'] ?? date('Y')) . '.docx',
            'values' => [
                'numero' => (string) ($oficio['numero'] ?? ''),
                'anio' => (string) ($oficio['anio'] ?? ''),
                'fecha_emision' => $this->formatDate($oficio['fecha_emision'] ?? null),
                'asunto' => (string) ($oficio['asunto_texto'] ?? $oficio['asunto'] ?? $oficio['asunto_nombre'] ?? ''),
                'motivo' => (string) ($oficio['motivo'] ?? ''),
                'nombre_oficial_ano' => (string) ($oficio['nombre_oficial_ano'] ?? ''),
                'oficial_ano' => (string) ($oficio['nombre_oficial_ano'] ?? ''),
                'grado_cargo' => (string) ($oficio['grado_cargo_nombre'] ?? ''),
                'referencia_texto' => (string) ($oficio['referencia_texto'] ?? ''),
                'persona_destino' => $this->personaDestino($oficio),
                'entidad_destino' => trim((string) ($oficio['entidad_nombre'] ?? $oficio['entidad_siglas'] ?? '')),
                'subentidad_destino' => (string) ($oficio['subentidad_nombre'] ?? ''),
                'accidente_lugar' => (string) ($oficio['acc_lugar'] ?? ''),
                'accidente_fecha' => $this->formatDateTime($oficio['fecha_accidente'] ?? null),
                'accidente_fecha_abrev' => $this->fechaAbrev($oficio['fecha_accidente'] ?? null),
                'accidente_hora' => $this->extractHour($oficio['fecha_accidente'] ?? null),
                'accidente_referencia' => (string) ($oficio['acc_referencia'] ?? ''),
                'carpeta' => (string) ($oficio['folder'] ?? $oficio['carpeta'] ?? ''),
                'accidente_modalidad' => $modalidad,
                'comisaria_nombre' => (string) ($oficio['comisaria_nombre'] ?? ''),
                'person_list' => $this->personasList($personas),
                'vehiculo_list' => $this->vehiculosList($vehiculos),
            ],
        ];
    }

    public function citacionData(int $citacionId): array
    {
        $citacion = $this->repository->citacionById($citacionId);
        if ($citacion === null) {
            throw new InvalidArgumentException('Citación no encontrada.');
        }

        $personaInfo = ['celular' => '', 'email' => '', 'edad' => $citacion['persona_edad'] ?? ''];
        $personaId = isset($citacion['persona_id']) ? (int) $citacion['persona_id'] : 0;
        if ($personaId > 0) {
            $persona = $this->repository->personaById($personaId);
            if ($persona) {
                $personaInfo = array_merge($personaInfo, $persona);
            }
        }
        if (($personaInfo['celular'] ?? '') === '' && ($personaInfo['email'] ?? '') === '' && !empty($citacion['persona_doc_num'])) {
            $byDoc = $this->repository->personaByDoc((string) $citacion['persona_doc_num']);
            if ($byDoc) {
                $personaInfo = array_merge($personaInfo, $byDoc);
            }
        }

        $accidenteId = (int) ($citacion['accidente_id'] ?? 0);
        $modalidad = $this->listaEspanol($this->explodePipe($this->repository->accidenteModalidad($accidenteId)));
        $oficio = '';
        if (!empty($citacion['oficio_num']) && !empty($citacion['oficio_anio'])) {
            $oficio = $citacion['oficio_num'] . '/' . $citacion['oficio_anio'];
        }

        $apellidos = trim((string) (($citacion['persona_apep'] ?? '') . ' ' . ($citacion['persona_apem'] ?? '')));

        return [
            'filename' => 'Citacion_' . $citacionId . (!empty($citacion['persona_apep']) ? '_' . preg_replace('~\s+~', '_', (string) $citacion['persona_apep']) : '') . '.docx',
            'values' => [
                'persona_nombres' => (string) ($citacion['persona_nombres'] ?? ''),
                'persona_apellidos' => $apellidos,
                'persona_nombre_completo' => trim((string) (($citacion['persona_nombres'] ?? '') . ' ' . $apellidos)),
                'persona_doc' => trim((string) (($citacion['persona_doc_tipo'] ?? '') . ' ' . ($citacion['persona_doc_num'] ?? ''))),
                'persona_domicilio' => (string) ($citacion['persona_domicilio'] ?? ''),
                'persona_edad' => (string) ($personaInfo['edad'] ?? $citacion['persona_edad'] ?? ''),
                'persona_celular' => (string) ($personaInfo['celular'] ?? ''),
                'persona_email' => (string) ($personaInfo['email'] ?? ''),
                'cit_en_calidad' => (string) ($citacion['en_calidad'] ?? ''),
                'cit_tipo_diligencia' => (string) ($citacion['tipo_diligencia'] ?? ''),
                'cit_fecha_larga' => $this->fechaLarga($citacion['fecha'] ?? null),
                'cit_fecha' => $this->fechaAbrev($citacion['fecha'] ?? null),
                'cit_hora' => substr((string) ($citacion['hora'] ?? ''), 0, 5),
                'cit_lugar' => (string) ($citacion['lugar'] ?? ''),
                'cit_motivo' => (string) ($citacion['motivo'] ?? ''),
                'cit_orden' => (string) ($citacion['orden_citacion'] ?? ''),
                'cit_oficio' => $oficio,
                'accidente_sidpol' => (string) ($citacion['registro_sidpol'] ?? ''),
                'accidente_fecha_larga' => $this->fechaLarga($citacion['fecha_accidente'] ?? null),
                'accidente_fecha' => $this->fechaAbrev($citacion['fecha_accidente'] ?? null),
                'accidente_hora' => $this->repository->accidenteHora($accidenteId, $citacion['fecha_accidente'] ?? null),
                'accidente_lugar' => (string) ($citacion['acc_lugar'] ?? ''),
                'accidente_modalidad' => $modalidad,
                'comisaria_nombre' => (string) ($citacion['comisaria_nombre'] ?? ''),
                'fiscalia_nombre' => (string) ($citacion['fiscalia_nombre'] ?? ''),
            ],
        ];
    }

    private function personaDestino(array $oficio): string
    {
        if (!empty($oficio['ppe_nombres']) || !empty($oficio['ppe_apep']) || !empty($oficio['ppe_apem'])) {
            return trim((string) (($oficio['ppe_nombres'] ?? '') . ' ' . ($oficio['ppe_apep'] ?? '') . ' ' . ($oficio['ppe_apem'] ?? '')));
        }

        if (!empty($oficio['persona_destino_id'])) {
            $persona = $this->repository->personaById((int) $oficio['persona_destino_id']);
            if ($persona !== null) {
                $text = trim((string) (($persona['nombres'] ?? '') . ' ' . ($persona['apellido_paterno'] ?? '') . ' ' . ($persona['apellido_materno'] ?? '')));
                if (!empty($persona['num_doc'])) {
                    $text .= ' - DNI: ' . $persona['num_doc'];
                }
                return trim($text);
            }
        }

        return trim((string) ($oficio['persona_destino_text'] ?? ''));
    }

    private function personasList(array $personas): string
    {
        $lines = [];
        foreach ($personas as $persona) {
            $nombre = trim((string) (($persona['nombres'] ?? '') . ' ' . ($persona['apellido_paterno'] ?? '') . ' ' . ($persona['apellido_materno'] ?? '')));
            $dni = trim((string) ($persona['num_doc'] ?? ''));
            $rol = trim((string) ($persona['rol_nombre'] ?? $persona['orden_persona'] ?? ''));
            $lesion = trim((string) ($persona['lesion'] ?? ''));
            $observaciones = trim((string) ($persona['observaciones'] ?? ''));

            $parts = [];
            if ($nombre !== '') {
                $parts[] = $nombre . ($dni !== '' ? ' (DNI: ' . $dni . ')' : '');
            }
            if ($rol !== '') {
                $parts[] = 'Rol: ' . $rol;
            }
            if ($lesion !== '') {
                $parts[] = 'Lesión: ' . $lesion;
            }
            if ($observaciones !== '') {
                $parts[] = 'Obs: ' . $observaciones;
            }
            if ($parts !== []) {
                $lines[] = implode(' - ', $parts);
            }
        }

        return $lines !== [] ? implode("\n", $lines) : 'No hay personas involucradas registradas';
    }

    private function vehiculosList(array $vehiculos): string
    {
        $lines = [];
        foreach ($vehiculos as $vehiculo) {
            $parts = [];
            foreach ([
                'placa' => 'Placa',
                'marca' => 'Marca',
                'modelo' => 'Modelo',
                'tipo' => 'Tipo',
                'observaciones' => 'Obs',
            ] as $key => $label) {
                $value = trim((string) ($vehiculo[$key] ?? ''));
                if ($value !== '') {
                    $parts[] = $label . ': ' . $value;
                }
            }
            if ($parts !== []) {
                $lines[] = implode(' - ', $parts);
            }
        }

        return $lines !== [] ? implode("\n", $lines) : 'No hay vehículos involucrados registrados';
    }

    private function formatDate(?string $value): string
    {
        if (!$value) {
            return '';
        }
        $time = strtotime($value);
        return $time ? date('d/m/Y', $time) : '';
    }

    private function formatDateTime(?string $value): string
    {
        if (!$value) {
            return '';
        }
        $time = strtotime($value);
        return $time ? date('d/m/Y H:i', $time) : '';
    }

    private function extractHour(?string $value): string
    {
        if (!$value) {
            return '';
        }
        $time = strtotime($value);
        return $time ? date('H:i', $time) : '';
    }

    private function fechaLarga(?string $value): string
    {
        if (!$value) {
            return '';
        }
        $time = strtotime($value);
        if (!$time) {
            return '';
        }
        $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return date('j', $time) . ' de ' . $months[(int) date('n', $time) - 1] . ' de ' . date('Y', $time);
    }

    private function fechaAbrev(?string $value): string
    {
        if (!$value) {
            return '';
        }
        $time = strtotime($value);
        if (!$time) {
            return '';
        }
        $months = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];
        return strtoupper(date('d', $time) . $months[(int) date('n', $time) - 1] . date('Y', $time));
    }

    private function explodePipe(string $value): array
    {
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode('||', $value)), static fn (string $item): bool => $item !== ''));
    }

    private function listaEspanol(array $items): string
    {
        $count = count($items);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $items[0];
        }
        if ($count === 2) {
            return $items[0] . ' y ' . $items[1];
        }
        return implode(', ', array_slice($items, 0, $count - 1)) . ' y ' . $items[$count - 1];
    }
}
