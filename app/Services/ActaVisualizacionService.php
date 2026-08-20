<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActaVisualizacionRepository;
use InvalidArgumentException;

final class ActaVisualizacionService
{
    public function __construct(private ActaVisualizacionRepository $repository) {}

    public function context(int $accidenteId): array
    {
        $accident = $this->repository->accident($accidenteId);
        if (!$accident) throw new InvalidArgumentException('Accidente no encontrado.');
        return ['accident'=>$accident,'participants'=>$this->repository->participants($accidenteId),'offices'=>$this->repository->cameraOffices($accidenteId)];
    }

    public function save(?int $id, int $accidenteId, array $input): int
    {
        $ctx = $this->context($accidenteId);
        $date = trim((string) ($input['fecha_visualizacion'] ?? ''));
        $time = trim((string) ($input['hora_inicio'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new InvalidArgumentException('Completa una fecha y hora de inicio validas.');
        }
        $participantMap = [];
        foreach ($ctx['participants'] as $row) $participantMap[$row['fuente'].':'.$row['fuente_id']] = $row;
        $participants = [];
        foreach ((array) ($input['participantes'] ?? []) as $key) if (isset($participantMap[$key])) $participants[] = $participantMap[$key];
        if ($participants === []) throw new InvalidArgumentException('Selecciona al menos una persona considerada en el acta.');

        $officeMap = [];
        foreach ($ctx['offices'] as $office) $officeMap[(int)$office['oficio_id']] = $office;
        $documentsByKey = [];

        $disks = [];
        foreach ((array) ($input['discos'] ?? []) as $disk) {
            $officeId = (int)($disk['oficio_id'] ?? 0);
            if (!isset($officeMap[$officeId])) throw new InvalidArgumentException('Selecciona un oficio de cámaras que tenga documento recibido relacionado.');
            $mediumType = strtoupper(trim((string)($disk['tipo_medio'] ?? '')));
            if (!in_array($mediumType, ['DISCO','USB'], true)) throw new InvalidArgumentException('Indica si el medio recibido es DISCO o USB.');
            $files = [];
            foreach ((array) ($disk['archivos'] ?? []) as $file) {
                $name = trim((string) ($file['nombre_archivo'] ?? ''));
                if ($name === '') continue;
                $descriptions = [];
                foreach ((array) ($file['descripciones'] ?? []) as $description) {
                    $timeDescription = trim((string) ($description['tiempo'] ?? ''));
                    $detail = trim((string) ($description['detalle'] ?? ''));
                    if ($timeDescription === '' && $detail === '') continue;
                    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $timeDescription) || $detail === '') {
                        throw new InvalidArgumentException('Cada descripcion del video debe tener hora, minuto, segundo y detalle.');
                    }
                    $descriptions[] = ['tiempo'=>$timeDescription,'detalle'=>$detail,'captura_path'=>null];
                }
                if ($descriptions === []) throw new InvalidArgumentException('Cada archivo debe tener al menos un momento observado.');
                $files[] = [
                    'nombre_archivo'=>$name,
                    'tipo_archivo'=>trim((string)($file['tipo_archivo']??'')) ?: null,
                    'peso'=>trim((string)($file['peso']??'')) ?: null,
                    'duracion'=>trim((string)($file['duracion']??'')) ?: null,
                    'observaciones'=>trim((string)($file['observaciones']??'')) ?: null,
                    'descripciones'=>$descriptions,
                ];
            }
            $brand = trim((string) ($disk['marca'] ?? ''));
            $serial = trim((string) ($disk['numero_serie'] ?? ''));
            $capacity = trim((string)($disk['capacidad'] ?? ''));
            if ($brand === '' || $serial === '' || $capacity === '') throw new InvalidArgumentException('Completa marca, número de serie y capacidad de cada DISCO o USB.');
            if ($files === []) throw new InvalidArgumentException('Cada DISCO o USB debe contener al menos un archivo.');
            $disks[] = ['oficio_id'=>$officeId,'tipo_medio'=>$mediumType,'marca'=>$brand,'numero_serie'=>$serial,'capacidad'=>$capacity,'observaciones'=>trim((string)($disk['observaciones']??'')) ?: null,'archivos'=>$files];
            $office = $officeMap[$officeId];
            $documentsByKey['OFICIO:'.$officeId] = $office['documento'];
            foreach ($office['respuestas'] as $response) $documentsByKey['RESPUESTA:'.$response['fuente_id']] = $response;
        }
        if ($disks === []) throw new InvalidArgumentException('Agrega al menos un DISCO o USB a uno de los oficios disponibles.');
        return $this->repository->save($id, [
            'accidente_id'=>$accidenteId,'fecha_visualizacion'=>$date,'hora_inicio'=>$time,
            'observaciones'=>trim((string)($input['observaciones']??'')) ?: null,
            'estado'=>in_array(($input['estado']??''),['Pendiente','Realizada','Anulada'],true)?$input['estado']:'Pendiente',
        ], $participants, array_values($documentsByKey), $disks);
    }
}
