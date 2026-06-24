<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

function carpeta_zip_text(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($converted !== false) {
        $value = $converted;
    }

    $value = preg_replace('/[^A-Za-z0-9]+/', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
}

function carpeta_zip_slug(string $value, string $fallback): string
{
    $value = carpeta_zip_text($value);
    if ($value === '') {
        $value = $fallback;
    }

    $value = str_replace(' ', '_', $value);
    $value = preg_replace('/_+/', '_', $value) ?? '';
    return trim($value, '_') ?: $fallback;
}

function carpeta_zip_modalidad(array $modalidades): string
{
    $prioridad = ['choque', 'atropello', 'despiste'];
    $normalizadas = [];

    foreach ($modalidades as $modalidad) {
        $limpia = strtolower(carpeta_zip_text((string) ($modalidad['nombre'] ?? '')));
        if ($limpia !== '') {
            $normalizadas[] = $limpia;
        }
    }

    $elegidas = [];
    foreach ($prioridad as $tipo) {
        foreach ($normalizadas as $modalidad) {
            if (str_contains($modalidad, $tipo)) {
                $elegidas[] = ucfirst($tipo);
                break;
            }
        }
    }

    if ($elegidas !== []) {
        return implode('_', $elegidas);
    }

    return isset($normalizadas[0]) ? ucwords($normalizadas[0]) : 'Accidente';
}

function carpeta_zip_lugar_resumido(string $lugar): string
{
    $texto = strtolower(carpeta_zip_text($lugar));
    if ($texto === '') {
        return 'Sin_lugar';
    }

    $stopwords = [
        'av', 'avenida', 'calle', 'jr', 'jiron', 'carretera', 'altura', 'interseccion',
        'con', 'la', 'el', 'los', 'las', 'de', 'del', 'en', 'ex', 'urb', 'urbanizacion',
        'aa', 'hh', 'asent', 'humano', 'via',
    ];

    $tokens = array_values(array_filter(explode(' ', $texto), static function (string $token) use ($stopwords): bool {
        return $token !== '' && !in_array($token, $stopwords, true);
    }));

    if ($tokens === []) {
        $tokens = array_values(array_filter(explode(' ', $texto)));
    }

    $tokens = array_slice($tokens, 0, 5);
    $resumen = ucwords(implode(' ', $tokens));
    return carpeta_zip_slug($resumen, 'Sin_lugar');
}

$accidenteId = (int) ($_GET['accidente_id'] ?? $_GET['id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Accidente no especificado.');
}

$st = $pdo->prepare('SELECT id, sidpol, registro_sidpol, lugar FROM accidentes WHERE id = ? LIMIT 1');
$st->execute([$accidenteId]);
$accidente = $st->fetch(PDO::FETCH_ASSOC);
if (!$accidente) {
    http_response_code(404);
    exit('Accidente no encontrado.');
}

$st = $pdo->prepare(
    'SELECT m.nombre
       FROM accidente_modalidad am
       JOIN modalidad_accidente m ON m.id = am.modalidad_id
      WHERE am.accidente_id = ?
      ORDER BY am.modalidad_id'
);
$st->execute([$accidenteId]);
$modalidades = $st->fetchAll(PDO::FETCH_ASSOC);

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('La extensión ZIP de PHP no está disponible.');
}

$numeroSidpol = trim((string) ($accidente['registro_sidpol'] ?? ''));
if ($numeroSidpol === '') {
    $numeroSidpol = trim((string) ($accidente['sidpol'] ?? ''));
}
if ($numeroSidpol === '') {
    $numeroSidpol = 'ACC-' . $accidenteId;
}

$modalidad = carpeta_zip_modalidad($modalidades);
$lugar = carpeta_zip_lugar_resumido((string) ($accidente['lugar'] ?? ''));
$folderName = substr(carpeta_zip_slug($numeroSidpol . '_' . $modalidad . '_' . $lugar, 'Accidente_' . $accidenteId), 0, 120);

$tmp = tempnam(sys_get_temp_dir(), 'carpeta_accidente_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo preparar el archivo temporal.');
}

$zipPath = $tmp . '.zip';
@rename($tmp, $zipPath);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    @unlink($zipPath);
    http_response_code(500);
    exit('No se pudo crear el ZIP.');
}

$subcarpetas = [
    'Investigado',
    'Fallecido',
    'Oficios',
    'Fotos y videos',
    'Actas',
];

$zip->addEmptyDir($folderName);
foreach ($subcarpetas as $subcarpeta) {
    $zip->addEmptyDir($folderName . '/' . $subcarpeta);
}

$zip->addFromString(
    $folderName . '/LEEME.txt',
    "Estructura inicial del accidente\n"
    . "SIDPOL: " . $numeroSidpol . "\n"
    . "Modalidad: " . str_replace('_', ', ', $modalidad) . "\n"
    . "Lugar: " . (string) ($accidente['lugar'] ?? '') . "\n"
    . "Expediente: accidente_vista_tabs.php?accidente_id=" . $accidenteId . "\n"
);

$closed = $zip->close();
if (!$closed || !is_file($zipPath) || filesize($zipPath) <= 0) {
    @unlink($zipPath);
    http_response_code(500);
    exit('No se pudo finalizar el ZIP.');
}

while (ob_get_level()) {
    @ob_end_clean();
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $folderName . '.zip"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
@unlink($zipPath);
exit;
