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
        return ['accident'=>$accident,'participants'=>$this->repository->participants($accidenteId),'documents'=>$this->repository->cameraDocuments($accidenteId)];
    }

    public function save(?int $id, int $accidenteId, array $input, array $uploads = []): int
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

        $documentMap = [];
        foreach ($ctx['documents'] as $row) $documentMap[$row['fuente'].':'.$row['fuente_id']] = $row;
        $documents = [];
        foreach ((array) ($input['documentos'] ?? []) as $key) if (isset($documentMap[$key])) $documents[] = $documentMap[$key];

        $disks = [];
        foreach ((array) ($input['discos'] ?? []) as $disk) {
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
                    $capturePath = trim((string) ($description['captura_path'] ?? '')) ?: null;
                    $token = trim((string) ($description['captura_token'] ?? ''));
                    if ($token !== '') $capturePath = $this->storeCapture($uploads, $token) ?: $capturePath;
                    $descriptions[] = ['tiempo'=>$timeDescription,'detalle'=>$detail,'captura_path'=>$capturePath];
                }
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
            if ($brand === '' && $serial === '' && $files === []) continue;
            if ($serial === '') throw new InvalidArgumentException('Cada disco registrado debe tener numero de serie.');
            $disks[] = ['marca'=>$brand ?: null,'numero_serie'=>$serial,'observaciones'=>trim((string)($disk['observaciones']??'')) ?: null,'archivos'=>$files];
        }
        return $this->repository->save($id, [
            'accidente_id'=>$accidenteId,'fecha_visualizacion'=>$date,'hora_inicio'=>$time,
            'observaciones'=>trim((string)($input['observaciones']??'')) ?: null,
            'estado'=>in_array(($input['estado']??''),['Pendiente','Realizada','Anulada'],true)?$input['estado']:'Pendiente',
        ], $participants, $documents, $disks);
    }

    private function storeCapture(array $uploads, string $token): ?string
    {
        $error = $uploads['error'][$token] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) return null;
        if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException('No se pudo cargar una de las capturas.');
        $tmp = (string) ($uploads['tmp_name'][$token] ?? '');
        $size = (int) ($uploads['size'][$token] ?? 0);
        if ($size <= 0 || $size > 10 * 1024 * 1024) throw new InvalidArgumentException('Cada captura debe pesar como maximo 10 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($extensions[$mime])) throw new InvalidArgumentException('Las capturas deben ser JPG, PNG o WEBP.');
        $relativeDir = 'uploads/actas_visualizacion/capturas';
        $absoluteDir = dirname(__DIR__, 2) . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new InvalidArgumentException('No se pudo preparar la carpeta para capturas.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmp, $absoluteDir . '/' . $filename)) throw new InvalidArgumentException('No se pudo guardar una de las capturas.');
        return $relativeDir . '/' . $filename;
    }
}
