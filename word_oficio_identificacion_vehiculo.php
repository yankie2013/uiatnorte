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

$template = __DIR__ . '/plantillas/oficio_identificacion_vehiculo-DEPPIRV.docx';
if (!is_file($template)) {
    http_response_code(404);
    exit('Falta subir la plantilla: plantillas/oficio_identificacion_vehiculo-DEPPIRV.docx');
}

$oficioId = (int) ($_GET['oficio_id'] ?? 0);
if ($oficioId <= 0) {
    http_response_code(400);
    exit('Falta oficio_id.');
}

function identveh_clean(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

function identveh_fecha_larga(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return date('j', $time) . ' de ' . $months[(int) date('n', $time) - 1] . ' de ' . date('Y', $time);
}

function identveh_fecha_abrev(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];
    return strtoupper(date('d', $time) . $months[(int) date('n', $time)] . date('Y', $time));
}

function identveh_normalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
    ]);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET NAMES utf8mb4');

$st = $pdo->prepare("
SELECT o.*,
       e.nombre AS entidad_nombre, COALESCE(e.siglas,'') AS entidad_siglas,
       a.nombre AS asunto_nombre, COALESCE(a.detalle,'') AS asunto_detalle,
       ac.registro_sidpol, ac.sidpol, ac.lugar, ac.referencia, ac.fecha_accidente,
       c.nombre AS comisaria_nombre,
       ao.nombre AS nombre_oficial_ano,
       gc.nombre AS grado_cargo_nombre, gc.abreviatura AS grado_cargo_abrev,
       iv.orden_participacion AS veh_orden, iv.tipo AS veh_participacion,
       v.placa AS veh_placa, v.serie_vin AS veh_serie_vin, v.nro_motor AS veh_nro_motor,
       v.anio AS veh_anio, v.color AS veh_color,
       mv.nombre AS veh_marca, modv.nombre AS veh_modelo,
       tv.codigo AS veh_tipo_codigo, tv.nombre AS veh_tipo,
       cv.codigo AS veh_categoria, cv.descripcion AS veh_categoria_descripcion
FROM oficios o
LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino
LEFT JOIN oficio_asunto a ON a.id = o.asunto_id
LEFT JOIN accidentes ac ON ac.id = o.accidente_id
LEFT JOIN comisarias c ON c.id = ac.comisaria_id
LEFT JOIN oficio_oficial_ano ao ON ao.id = o.oficial_ano_id
LEFT JOIN grado_cargo gc ON gc.id = o.grado_cargo_id
LEFT JOIN involucrados_vehiculos iv ON iv.id = o.involucrado_vehiculo_id
LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id
LEFT JOIN marcas_vehiculo mv ON mv.id = v.marca_id
LEFT JOIN modelos_vehiculo modv ON modv.id = v.modelo_id
LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
LEFT JOIN categoria_vehiculos cv ON cv.id = v.categoria_id
WHERE o.id = ?
LIMIT 1");
$st->execute([$oficioId]);
$oficio = $st->fetch(PDO::FETCH_ASSOC);
if (!$oficio) {
    http_response_code(404);
    exit('Oficio no encontrado.');
}

$match = identveh_normalize(($oficio['asunto_nombre'] ?? '') . ' ' . ($oficio['asunto_detalle'] ?? ''));
if (!str_contains($match, 'identificacion') || !str_contains($match, 'vehiculo')) {
    http_response_code(422);
    exit('Este oficio no corresponde al asunto Identificacion de vehiculo.');
}
if (empty($oficio['involucrado_vehiculo_id'])) {
    http_response_code(422);
    exit('Selecciona el vehiculo involucrado para generar este oficio.');
}

$accidenteLugar = identveh_clean(($oficio['lugar'] ?? '') . (($oficio['referencia'] ?? '') !== '' ? ' - ' . $oficio['referencia'] : ''));
$firmaGrado = identveh_clean($oficio['grado_cargo_abrev'] ?: $oficio['grado_cargo_nombre'] ?: 'ST3.PNP');

$values = [
    'nombre_oficial_ano' => $oficio['nombre_oficial_ano'] ?? '',
    'oficio_numero' => $oficio['numero'] ?? '',
    'oficio_anio' => $oficio['anio'] ?? '',
    'oficio_fecha' => identveh_fecha_larga($oficio['fecha_emision'] ?? null),
    'oficio_fecha_abrev' => identveh_fecha_abrev($oficio['fecha_emision'] ?? null),
    'entidad_nombre' => $oficio['entidad_nombre'] ?? '',
    'entidad_siglas' => $oficio['entidad_siglas'] ?? '',
    'asunto_nombre' => $oficio['asunto_nombre'] ?? '',
    'motivo' => $oficio['motivo'] ?? '',
    'referencia_texto' => $oficio['referencia_texto'] ?? '',
    'accidente_sidpol' => $oficio['registro_sidpol'] ?: ($oficio['sidpol'] ?? ''),
    'accidente_fecha' => identveh_fecha_larga($oficio['fecha_accidente'] ?? null),
    'accidente_fecha_abrev' => identveh_fecha_abrev($oficio['fecha_accidente'] ?? null),
    'accidente_lugar' => $accidenteLugar,
    'comisaria_nombre' => $oficio['comisaria_nombre'] ?? '',
    'veh_orden' => $oficio['veh_orden'] ?? '',
    'veh_participacion' => $oficio['veh_participacion'] ?? '',
    'veh_placa' => $oficio['veh_placa'] ?? '',
    'veh_serie_vin' => $oficio['veh_serie_vin'] ?? '',
    'veh_nro_motor' => $oficio['veh_nro_motor'] ?? '',
    'veh_anio' => $oficio['veh_anio'] ?? '',
    'veh_color' => $oficio['veh_color'] ?? '',
    'veh_marca' => $oficio['veh_marca'] ?? '',
    'veh_modelo' => $oficio['veh_modelo'] ?? '',
    'veh_tipo_codigo' => $oficio['veh_tipo_codigo'] ?? '',
    'veh_tipo' => $oficio['veh_tipo'] ?? '',
    'veh_categoria' => $oficio['veh_categoria'] ?? '',
    'veh_categoria_descripcion' => $oficio['veh_categoria_descripcion'] ?? '',
    'investigador_grado' => $firmaGrado,
    'investigador_nombre' => 'Giancarlo MERINO SANCHO',
    'firma_grado' => $firmaGrado,
    'firma_nombre' => 'Giancarlo MERINO SANCHO',
    'firma_cargo' => 'Instructor UIAT Norte',
];

$tpl = new TemplateProcessor($template);
foreach ($values as $key => $value) {
    $tpl->setValue($key, htmlspecialchars(identveh_clean($value), ENT_QUOTES, 'UTF-8'));
}

if (isset($_GET['vars']) && method_exists($tpl, 'getVariables')) {
    header('Content-Type: text/plain; charset=utf-8');
    $vars = $tpl->getVariables();
    sort($vars);
    echo implode(PHP_EOL, $vars) . PHP_EOL;
    exit;
}

$tmp = tempnam(sys_get_temp_dir(), 'ident_vehiculo_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear temporal DOCX.');
}
$tpl->saveAs($tmp);

while (ob_get_level()) {
    ob_end_clean();
}

$filename = 'Identificacion_Vehiculo_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($oficio['veh_placa'] ?? 'vehiculo')) . '_' . preg_replace('/[^0-9]/', '', (string) ($oficio['numero'] ?? $oficioId)) . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($tmp);
@unlink($tmp);
exit;
