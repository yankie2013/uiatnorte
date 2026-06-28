<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoVehiculoRepository;
use InvalidArgumentException;

final class DocumentoVehiculoService
{
    private const FIELDS = [
        'numero_propiedad',
        'titulo_propiedad',
        'partida_propiedad',
        'sede_propiedad',
        'numero_soat',
        'aseguradora_soat',
        'vigente_soat',
        'vencimiento_soat',
        'numero_revision',
        'certificadora_revision',
        'vigente_revision',
        'vencimiento_revision',
        'numero_peritaje',
        'fecha_peritaje',
        'perito_peritaje',
        'sistema_electrico_peritaje',
        'sistema_frenos_peritaje',
        'sistema_direccion_peritaje',
        'sistema_transmision_peritaje',
        'sistema_suspension_peritaje',
        'planta_motriz_peritaje',
        'otros_peritaje',
        'danos_peritaje',
        'imagen_peritaje_path',
        'imagen_peritaje_nombre',
        'imagen_peritaje_mime',
        'imagen_peritaje_size',
    ];

    public function __construct(private DocumentoVehiculoRepository $repository)
    {
    }

    public function contextoNuevo(int $involucradoVehiculoId): ?array
    {
        return $this->repository->involucradoInfo($involucradoVehiculoId);
    }

    public function contextoEditar(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function crear(int $involucradoVehiculoId, array $input, array $files = []): int
    {
        $context = $this->repository->involucradoInfo($involucradoVehiculoId);
        if ($context === null) {
            throw new InvalidArgumentException('No se encontró el involucrado de vehículo.');
        }

        $payload = $this->payload($input, (int) ($context['vehiculo_id'] ?? 0), $involucradoVehiculoId);
        $this->validatePeritajeImageUpload($files['imagen_peritaje'] ?? null);
        $id = $this->repository->create($payload);
        $image = $this->storePeritajeImage($id, $files['imagen_peritaje'] ?? null);
        if ($image !== null) {
            $payload[':imagen_peritaje_path'] = $image['path'];
            $payload[':imagen_peritaje_nombre'] = $image['name'];
            $payload[':imagen_peritaje_mime'] = $image['mime'];
            $payload[':imagen_peritaje_size'] = $image['size'];
            $this->repository->update($id, $payload);
        }
        return $id;
    }

    public function actualizar(int $id, array $input, array $files = []): void
    {
        $documento = $this->repository->find($id);
        if ($documento === null) {
            throw new InvalidArgumentException('Documento no encontrado.');
        }

        $payload = $this->payload($input, (int) ($documento['vehiculo_id'] ?? 0), (int) $documento['invol_id']);
        $payload[':imagen_peritaje_path'] = $documento['imagen_peritaje_path'] ?? null;
        $payload[':imagen_peritaje_nombre'] = $documento['imagen_peritaje_nombre'] ?? null;
        $payload[':imagen_peritaje_mime'] = $documento['imagen_peritaje_mime'] ?? null;
        $payload[':imagen_peritaje_size'] = $documento['imagen_peritaje_size'] ?? null;
        $this->validatePeritajeImageUpload($files['imagen_peritaje'] ?? null);
        $image = $this->storePeritajeImage($id, $files['imagen_peritaje'] ?? null);
        if ($image !== null) {
            $this->deleteStoredFile((string) ($documento['imagen_peritaje_path'] ?? ''));
            $payload[':imagen_peritaje_path'] = $image['path'];
            $payload[':imagen_peritaje_nombre'] = $image['name'];
            $payload[':imagen_peritaje_mime'] = $image['mime'];
            $payload[':imagen_peritaje_size'] = $image['size'];
        }
        $this->repository->update($id, $payload);
    }

    public function eliminar(int $id): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('Documento no encontrado.');
        }

        $this->repository->delete($id);
    }

    public function mergeOld(array $base, array $input): array
    {
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $base[$field] = trim((string) $input[$field]);
            }
        }

        if (array_key_exists('vehiculo_id', $input)) {
            $base['vehiculo_id'] = trim((string) $input['vehiculo_id']);
        }

        return $base;
    }

    public function emptyForm(): array
    {
        $form = ['vehiculo_id' => ''];
        foreach (self::FIELDS as $field) {
            $form[$field] = '';
        }
        return $form;
    }

    private function payload(array $input, int $vehiculoIdDefault, int $involucradoVehiculoId): array
    {
        $vehiculoId = trim((string) ($input['vehiculo_id'] ?? ''));
        $vehiculoId = $vehiculoId === '' ? ($vehiculoIdDefault > 0 ? $vehiculoIdDefault : null) : (int) $vehiculoId;

        return [
            ':involucrado_vehiculo_id' => $involucradoVehiculoId,
            ':vehiculo_id' => $vehiculoId,
            ':numero_propiedad' => $this->nullableTrim($input['numero_propiedad'] ?? null),
            ':titulo_propiedad' => $this->nullableTrim($input['titulo_propiedad'] ?? null),
            ':partida_propiedad' => $this->nullableTrim($input['partida_propiedad'] ?? null),
            ':sede_propiedad' => $this->nullableTrim($input['sede_propiedad'] ?? null),
            ':numero_soat' => $this->nullableTrim($input['numero_soat'] ?? null),
            ':aseguradora_soat' => $this->nullableTrim($input['aseguradora_soat'] ?? null),
            ':vigente_soat' => $this->nullableTrim($input['vigente_soat'] ?? null),
            ':vencimiento_soat' => $this->nullableTrim($input['vencimiento_soat'] ?? null),
            ':numero_revision' => $this->nullableTrim($input['numero_revision'] ?? null),
            ':certificadora_revision' => $this->nullableTrim($input['certificadora_revision'] ?? null),
            ':vigente_revision' => $this->nullableTrim($input['vigente_revision'] ?? null),
            ':vencimiento_revision' => $this->nullableTrim($input['vencimiento_revision'] ?? null),
            ':numero_peritaje' => $this->nullableTrim($input['numero_peritaje'] ?? null),
            ':fecha_peritaje' => $this->nullableTrim($input['fecha_peritaje'] ?? null),
            ':perito_peritaje' => $this->nullableTrim($input['perito_peritaje'] ?? null),
            ':sistema_electrico_peritaje' => $this->nullableTrim($input['sistema_electrico_peritaje'] ?? null),
            ':sistema_frenos_peritaje' => $this->nullableTrim($input['sistema_frenos_peritaje'] ?? null),
            ':sistema_direccion_peritaje' => $this->nullableTrim($input['sistema_direccion_peritaje'] ?? null),
            ':sistema_transmision_peritaje' => $this->nullableTrim($input['sistema_transmision_peritaje'] ?? null),
            ':sistema_suspension_peritaje' => $this->nullableTrim($input['sistema_suspension_peritaje'] ?? null),
            ':planta_motriz_peritaje' => $this->nullableTrim($input['planta_motriz_peritaje'] ?? null),
            ':otros_peritaje' => $this->nullableTrim($input['otros_peritaje'] ?? null),
            ':danos_peritaje' => $this->nullableMultiline($input['danos_peritaje'] ?? null),
            ':imagen_peritaje_path' => $this->nullableTrim($input['imagen_peritaje_path'] ?? null),
            ':imagen_peritaje_nombre' => $this->nullableTrim($input['imagen_peritaje_nombre'] ?? null),
            ':imagen_peritaje_mime' => $this->nullableTrim($input['imagen_peritaje_mime'] ?? null),
            ':imagen_peritaje_size' => $this->nullableInt($input['imagen_peritaje_size'] ?? null),
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableMultiline(mixed $value): ?string
    {
        $value = str_replace(["\r\n", "\r"], "\n", (string) ($value ?? ''));
        $lines = array_filter(array_map(static fn(string $line): string => trim($line), explode("\n", $value)), static fn(string $line): bool => $line !== '');
        return $lines === [] ? null : implode("\n", $lines);
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : max(0, (int) $value);
    }

    private function validatePeritajeImageUpload(mixed $upload): void
    {
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }

        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen del peritaje supera el tamano permitido.',
                UPLOAD_ERR_PARTIAL => 'La imagen del peritaje no se cargo completamente.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
                UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir la imagen del peritaje en disco.',
                UPLOAD_ERR_EXTENSION => 'La carga de la imagen del peritaje fue detenida por una extension de PHP.',
                default => 'No se pudo procesar la imagen del peritaje.',
            });
        }

        $size = (int) ($upload['size'] ?? 0);
        if ($size <= 0) {
            throw new InvalidArgumentException('La imagen del peritaje esta vacia.');
        }
        if ($size > 10 * 1024 * 1024) {
            throw new InvalidArgumentException('La imagen del peritaje debe pesar como maximo 10 MB.');
        }

        $mime = $this->detectImageMime((string) ($upload['tmp_name'] ?? ''));
        if (!isset($this->allowedImageExtensions()[$mime])) {
            throw new InvalidArgumentException('La imagen del peritaje debe ser JPG, PNG, WEBP o GIF.');
        }
    }

    private function storePeritajeImage(int $documentoId, mixed $upload): ?array
    {
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $tmp = (string) ($upload['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('No se pudo validar la imagen del peritaje subida.');
        }

        $mime = $this->detectImageMime($tmp);
        $extension = $this->allowedImageExtensions()[$mime] ?? null;
        if ($extension === null) {
            throw new InvalidArgumentException('La imagen del peritaje debe ser JPG, PNG, WEBP o GIF.');
        }

        $basePath = defined('UIAT_BASE_PATH') ? UIAT_BASE_PATH : dirname(__DIR__, 2);
        $relativeDir = 'uploads/peritajes/documento_vehiculo_' . $documentoId;
        $absoluteDir = $basePath . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new InvalidArgumentException('No se pudo crear la carpeta para la imagen del peritaje.');
        }

        $fileName = 'peritaje_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $absolutePath = $absoluteDir . '/' . $fileName;
        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new InvalidArgumentException('No se pudo guardar la imagen del peritaje.');
        }

        return [
            'path' => $relativeDir . '/' . $fileName,
            'name' => trim((string) ($upload['name'] ?? 'imagen_peritaje')) ?: 'imagen_peritaje',
            'mime' => $mime,
            'size' => (int) ($upload['size'] ?? 0),
        ];
    }

    private function detectImageMime(string $path): string
    {
        $imageInfo = @getimagesize($path);
        if (!is_array($imageInfo)) {
            return '';
        }

        return match ((int) ($imageInfo[2] ?? 0)) {
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_WEBP => 'image/webp',
            IMAGETYPE_GIF => 'image/gif',
            default => '',
        };
    }

    private function allowedImageExtensions(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
    }

    private function deleteStoredFile(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return;
        }

        $basePath = defined('UIAT_BASE_PATH') ? UIAT_BASE_PATH : dirname(__DIR__, 2);
        $absolutePath = realpath($basePath . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
        $uploadsPath = realpath($basePath . '/uploads/peritajes');
        if ($absolutePath !== false && $uploadsPath !== false && str_starts_with($absolutePath, $uploadsPath . DIRECTORY_SEPARATOR) && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
