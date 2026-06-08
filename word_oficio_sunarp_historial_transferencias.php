<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && file_exists(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require_once __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}

use PhpOffice\PhpWord\TemplateProcessor;

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_errors.log');

if (!class_exists(TemplateProcessor::class)) {
    http_response_code(500);
    exit('PhpWord no esta disponible para generar el DOCX.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET NAMES utf8mb4');

function sunarp_clean(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function sunarp_fecha_larga(?string $date): string
{
    if (!$date) {
        return '';
    }
    $time = strtotime($date);
    if (!$time) {
        return '';
    }
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return date('d', $time) . ' de ' . $meses[(int) date('n', $time) - 1] . ' de ' . date('Y', $time);
}

function sunarp_fecha_abrev(?string $date): string
{
    if (!$date) {
        return '';
    }
    $time = strtotime($date);
    if (!$time) {
        return '';
    }
    $meses = [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];
    return strtoupper(date('d', $time) . $meses[(int) date('n', $time)] . date('Y', $time));
}

function sunarp_hora(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    return $time ? date('H:i', $time) : '';
}

function sunarp_edad(?string $birthDate, ?string $referenceDate, mixed $fallback = ''): string
{
    if ($birthDate) {
        try {
            return (string) (new DateTime($birthDate))->diff(new DateTime($referenceDate ?: 'now'))->y;
        } catch (Throwable) {
        }
    }
    return sunarp_clean($fallback);
}

function sunarp_join_es(array $items): string
{
    $items = array_values(array_filter(array_map('sunarp_clean', $items), static fn (string $item): bool => $item !== ''));
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
    return implode(', ', array_slice($items, 0, -1)) . ' y ' . $items[$count - 1];
}

function sunarp_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->execute([$table]);
    return (bool) $st->fetchColumn();
}

function sunarp_catalog_name(PDO $pdo, string $table, int $id, array $columns = ['nombre', 'descripcion', 'codigo']): string
{
    if ($id <= 0 || !sunarp_table_exists($pdo, $table)) {
        return '';
    }
    foreach ($columns as $column) {
        try {
            $st = $pdo->prepare("SELECT `$column` FROM `$table` WHERE id = ? LIMIT 1");
            $st->execute([$id]);
            $value = $st->fetchColumn();
            if ($value !== false && sunarp_clean($value) !== '') {
                return sunarp_clean($value);
            }
        } catch (Throwable) {
        }
    }
    return '';
}

function sunarp_normalize_match(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
    ]);
    return preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
}

$oficioId = isset($_GET['oficio_id']) ? (int) $_GET['oficio_id'] : 0;
if ($oficioId <= 0) {
    http_response_code(400);
    exit('Falta oficio_id.');
}

$template = __DIR__ . '/plantillas/oficio_sunarp_historial_transferencias.docx';
if (!is_file($template)) {
    http_response_code(500);
    exit('No se encuentra la plantilla: plantillas/oficio_sunarp_historial_transferencias.docx');
}

$sql = "
SELECT o.*,
       e.nombre AS entidad_nombre, COALESCE(e.siglas,'') AS entidad_siglas,
       a.nombre AS asunto_nombre, COALESCE(a.detalle,'') AS asunto_detalle,
       ac.registro_sidpol, ac.sidpol, ac.lugar, ac.referencia, ac.fecha_accidente, ac.sentido,
       c.nombre AS comisaria_nombre,
       ao.nombre AS nombre_oficial_ano,
       gc.nombre AS grado_cargo_nombre,
       gc.abreviatura AS grado_cargo_abrev
FROM oficios o
LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino
LEFT JOIN oficio_asunto a ON a.id = o.asunto_id
LEFT JOIN accidentes ac ON ac.id = o.accidente_id
LEFT JOIN comisarias c ON c.id = ac.comisaria_id
LEFT JOIN oficio_oficial_ano ao ON ao.id = o.oficial_ano_id
LEFT JOIN grado_cargo gc ON gc.id = o.grado_cargo_id
WHERE o.id = ?
LIMIT 1";
$st = $pdo->prepare($sql);
$st->execute([$oficioId]);
$oficio = $st->fetch(PDO::FETCH_ASSOC);
if (!$oficio) {
    http_response_code(404);
    exit('Oficio no encontrado.');
}

$matchText = sunarp_normalize_match((string) (($oficio['entidad_nombre'] ?? '') . ' ' . ($oficio['entidad_siglas'] ?? '') . ' ' . ($oficio['asunto_nombre'] ?? '') . ' ' . ($oficio['asunto_detalle'] ?? '') . ' ' . ($oficio['motivo'] ?? '')));
if (!str_contains($matchText, 'sunarp') && !(str_contains($matchText, 'historial') && str_contains($matchText, 'transferenc'))) {
    http_response_code(422);
    exit('Este oficio no corresponde a SUNARP / historial de transferencias.');
}

$involucradoVehiculoId = (int) ($oficio['involucrado_vehiculo_id'] ?? 0);
if ($involucradoVehiculoId <= 0) {
    http_response_code(422);
    exit('Selecciona el vehiculo involucrado para generar el oficio SUNARP.');
}

$st = $pdo->prepare("
SELECT iv.id AS inv_vehiculo_id, iv.orden_participacion, iv.vehiculo_id,
       v.placa, v.marca_id, v.modelo_id, v.color, v.anio
FROM involucrados_vehiculos iv
JOIN vehiculos v ON v.id = iv.vehiculo_id
WHERE iv.id = ? AND iv.accidente_id = ?
LIMIT 1");
$st->execute([$involucradoVehiculoId, (int) $oficio['accidente_id']]);
$vehiculo = $st->fetch(PDO::FETCH_ASSOC);
if (!$vehiculo) {
    http_response_code(404);
    exit('El vehiculo seleccionado no pertenece al accidente del oficio.');
}

$modalidades = [];
if (sunarp_table_exists($pdo, 'accidente_modalidad') && sunarp_table_exists($pdo, 'modalidad_accidente')) {
    $st = $pdo->prepare('SELECT ma.nombre FROM accidente_modalidad am JOIN modalidad_accidente ma ON ma.id = am.modalidad_id WHERE am.accidente_id = ? ORDER BY ma.id');
    $st->execute([(int) $oficio['accidente_id']]);
    $modalidades = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

$consecuencias = [];
if (sunarp_table_exists($pdo, 'accidente_consecuencia') && sunarp_table_exists($pdo, 'consecuencia_accidente')) {
    $st = $pdo->prepare('SELECT ca.nombre FROM accidente_consecuencia ac JOIN consecuencia_accidente ca ON ca.id = ac.consecuencia_id WHERE ac.accidente_id = ? ORDER BY ca.id');
    $st->execute([(int) $oficio['accidente_id']]);
    $consecuencias = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

$st = $pdo->prepare("
SELECT p.nombres, p.apellido_paterno, p.apellido_materno, p.tipo_doc, p.num_doc, p.fecha_nacimiento, p.edad
FROM involucrados_personas ip
JOIN personas p ON p.id = ip.persona_id
WHERE ip.accidente_id = ? AND ip.vehiculo_id = ?
ORDER BY CASE WHEN ip.rol_id = 1 THEN 0 ELSE 1 END, ip.id
LIMIT 1");
$st->execute([(int) $oficio['accidente_id'], (int) $vehiculo['vehiculo_id']]);
$conductor = $st->fetch(PDO::FETCH_ASSOC) ?: [];

$st = $pdo->prepare("
SELECT p.nombres, p.apellido_paterno, p.apellido_materno, p.fecha_nacimiento, p.edad
FROM involucrados_personas ip
JOIN personas p ON p.id = ip.persona_id
WHERE ip.accidente_id = ? AND ip.vehiculo_id <> ?
ORDER BY ip.id
LIMIT 4");
$st->execute([(int) $oficio['accidente_id'], (int) $vehiculo['vehiculo_id']]);
$agraviados = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $nombre = sunarp_clean(($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? ''));
    $edad = sunarp_edad($row['fecha_nacimiento'] ?? null, $oficio['fecha_accidente'] ?? null, $row['edad'] ?? '');
    $agraviados[] = $edad !== '' ? ($nombre . ' (' . $edad . ')') : $nombre;
}

$conductorNombre = sunarp_clean(($conductor['nombres'] ?? '') . ' ' . ($conductor['apellido_paterno'] ?? '') . ' ' . ($conductor['apellido_materno'] ?? ''));
$accidenteLugar = sunarp_clean(($oficio['lugar'] ?? '') . (($oficio['referencia'] ?? '') !== '' ? ' - ' . $oficio['referencia'] : ''));
$firmaGrado = sunarp_clean($oficio['grado_cargo_abrev'] ?: $oficio['grado_cargo_nombre'] ?: 'ST3.PNP');
$firmaNombre = 'Giancarlo MERINO SANCHO';

$values = [
    'nombre_oficial_ano' => $oficio['nombre_oficial_ano'] ?: 'Año de la recuperación y la consolidación de la economía peruana',
    'oficio_fecha' => sunarp_fecha_larga($oficio['fecha_emision'] ?? null),
    'oficio_numero' => $oficio['numero'] ?? '',
    'oficio_anio' => $oficio['anio'] ?? '',
    'oficio_entidad_nombre' => $oficio['entidad_nombre'] ?: 'SUPERINTENDENCIA NACIONAL DE LOS REGISTROS PUBLICOS',
    'oficio_entidad_siglas' => $oficio['entidad_siglas'] ?: 'SUNARP',
    'veh_placa' => $vehiculo['placa'] ?? '',
    'veh_marca' => sunarp_catalog_name($pdo, 'marcas_vehiculo', (int) ($vehiculo['marca_id'] ?? 0)),
    'veh_modelo' => sunarp_catalog_name($pdo, 'modelos_vehiculo', (int) ($vehiculo['modelo_id'] ?? 0)),
    'veh_color' => $vehiculo['color'] ?? '',
    'veh_orden' => $vehiculo['orden_participacion'] ?? '',
    'accidente_sidpol' => $oficio['registro_sidpol'] ?: ($oficio['sidpol'] ?? ''),
    'accidente_fecha_abrev' => sunarp_fecha_abrev($oficio['fecha_accidente'] ?? null),
    'accidente_hora' => sunarp_hora($oficio['fecha_accidente'] ?? null),
    'accidente_lugar' => $accidenteLugar,
    'accidente_modalidades' => sunarp_join_es($modalidades) ?: 'accidente de transito',
    'accidente_consecuencia' => sunarp_join_es($consecuencias) ?: 'por determinar',
    'comisaria_nombre' => $oficio['comisaria_nombre'] ?? '',
    'conductor_nombre' => $conductorNombre,
    'conductor_edad' => sunarp_edad($conductor['fecha_nacimiento'] ?? null, $oficio['fecha_accidente'] ?? null, $conductor['edad'] ?? ''),
    'agraviados_resumen' => sunarp_join_es($agraviados) ?: 'quienes resulten agraviados',
    'investigador_grado' => $firmaGrado,
    'investigador_nombre' => $firmaNombre,
    'investigador_celular' => '986571975',
    'firma_grado' => $firmaGrado,
    'firma_nombre' => $firmaNombre,
    'firma_cargo' => 'Instructor UIAT Norte',
];

$tpl = new TemplateProcessor($template);
foreach ($values as $key => $value) {
    $tpl->setValue($key, sunarp_clean($value));
}

if (isset($_GET['vars']) && method_exists($tpl, 'getVariables')) {
    header('Content-Type: text/plain; charset=utf-8');
    $docVars = $tpl->getVariables();
    sort($docVars);
    echo implode(PHP_EOL, $docVars) . PHP_EOL;
    exit;
}

$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0775, true);
}
$tmp = tempnam($tmpDir, 'sunarp_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear temporal DOCX.');
}
$tpl->saveAs($tmp);

while (ob_get_level()) {
    ob_end_clean();
}

$filename = 'Oficio_SUNARP_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($vehiculo['placa'] ?? 'vehiculo')) . '_' . preg_replace('/[^0-9]/', '', (string) ($oficio['numero'] ?? $oficioId)) . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($tmp);
@unlink($tmp);
exit;
