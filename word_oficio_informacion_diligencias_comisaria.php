<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_errors.log');

function dilig_clean(mixed $value, bool $keepLines = false): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    if ($keepLines) {
        $lines = preg_split('/\R/u', $text) ?: [];
        return implode("\n", array_values(array_filter(array_map(
            static fn(string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? $line),
            $lines
        ), static fn(string $line): bool => $line !== '')));
    }
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function dilig_normalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Ã¡' => 'a', 'Ã©' => 'e', 'Ã­' => 'i', 'Ã³' => 'o', 'Ãº' => 'u', 'Ã¼' => 'u', 'Ã±' => 'n',
    ]);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text);
}

function dilig_fecha_larga(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return date('j', $time) . ' de ' . $months[(int) date('n', $time) - 1] . ' de ' . date('Y', $time);
}

function dilig_fecha_abrev(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];
    return strtoupper(date('d', $time) . $months[(int) date('n', $time)] . date('Y', $time));
}

function dilig_numbered_lines(string $text): string
{
    $lines = preg_split('/\R/u', dilig_clean($text, true)) ?: [];
    $result = [];
    foreach ($lines as $line) {
        $line = preg_replace('/^\s*(?:[-*•]|\d+[.)-])\s*/u', '', $line) ?? $line;
        if (trim($line) !== '') {
            $result[] = (count($result) + 1) . '. ' . trim($line);
        }
    }
    return implode("\n", $result);
}

function dilig_inline_lines(string $text): string
{
    $lines = preg_split('/\R/u', dilig_clean($text, true)) ?: [];
    $lines = array_map(
        static fn(string $line): string => rtrim(trim($line), " \t\n\r\0\x0B,;"),
        $lines
    );
    $lines = array_values(array_filter($lines, static fn(string $line): bool => $line !== ''));

    return implode(', ', $lines);
}

function dilig_extract_from_motivo(string $motivo, string $detalle): string
{
    $lines = preg_split('/\R/u', trim($motivo)) ?: [];
    $detalleNorm = dilig_normalize($detalle);
    $result = [];

    foreach ($lines as $line) {
        $clean = dilig_clean($line);
        if ($clean === '') {
            continue;
        }
        if ($detalleNorm !== '' && dilig_normalize($clean) === $detalleNorm) {
            continue;
        }
        $result[] = $clean;
    }

    return implode("\n", $result);
}

$oficioId = (int) ($_GET['oficio_id'] ?? 0);
if ($oficioId <= 0) {
    http_response_code(400);
    exit('Falta oficio_id.');
}

$template = __DIR__ . '/plantillas/oficio_informacion_diligencias_comisaria.docx';
if (!is_file($template)) {
    http_response_code(500);
    exit('No se encuentra la plantilla: plantillas/oficio_informacion_diligencias_comisaria.docx');
}

$st = $pdo->prepare("
SELECT o.*, e.nombre AS entidad_nombre, COALESCE(e.siglas, '') AS entidad_siglas,
       s.nombre AS asunto_nombre, COALESCE(s.detalle, '') AS asunto_detalle,
       se.nombre AS subentidad_nombre,
       pe.nombres AS destino_nombres, pe.apellido_paterno AS destino_apep, COALESCE(pe.apellido_materno, '') AS destino_apem,
       ac.registro_sidpol, ac.sidpol, ac.lugar, ac.referencia AS accidente_referencia, ac.sentido, ac.fecha_accidente,
       c.nombre AS comisaria_nombre, f.nombre AS fiscalia_nombre,
       ao.nombre AS nombre_oficial_ano, gc.nombre AS grado_cargo_nombre, gc.abreviatura AS grado_cargo_abrev
FROM oficios o
LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino
LEFT JOIN oficio_asunto s ON s.id = o.asunto_id
LEFT JOIN oficio_subentidad se ON se.id = o.subentidad_destino_id
LEFT JOIN oficio_persona_entidad pe ON pe.id = o.persona_destino_id
LEFT JOIN accidentes ac ON ac.id = o.accidente_id
LEFT JOIN comisarias c ON c.id = ac.comisaria_id
LEFT JOIN fiscalia f ON f.id = ac.fiscalia_id
LEFT JOIN oficio_oficial_ano ao ON ao.id = o.oficial_ano_id
LEFT JOIN grado_cargo gc ON gc.id = o.grado_cargo_id
WHERE o.id = ?
LIMIT 1");
$st->execute([$oficioId]);
$oficio = $st->fetch(PDO::FETCH_ASSOC);
if (!$oficio) {
    http_response_code(404);
    exit('Oficio no encontrado.');
}

$match = dilig_normalize(($oficio['asunto_nombre'] ?? '') . ' ' . ($oficio['asunto_detalle'] ?? ''));
$isInformacionDiligencias = str_contains($match, 'informacion') && str_contains($match, 'diligenc');
if (!$isInformacionDiligencias) {
    http_response_code(422);
    exit('Este oficio no corresponde al asunto Informacion de diligencias.');
}
$diligenciasTexto = dilig_clean($oficio['diligencias_solicitadas'] ?? '', true);
if ($diligenciasTexto === '') {
    $diligenciasTexto = dilig_extract_from_motivo(
        (string) ($oficio['motivo'] ?? ''),
        (string) ($oficio['asunto_detalle'] ?? '')
    );
}
if ($diligenciasTexto === '') {
    http_response_code(422);
    exit('El oficio no tiene diligencias solicitadas registradas.');
}

$st = $pdo->prepare("
SELECT iv.orden_participacion, iv.tipo AS participacion, v.placa, v.color, v.anio,
       mv.nombre AS marca, modv.nombre AS modelo, tv.nombre AS tipo_vehiculo
FROM involucrados_vehiculos iv
LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id
LEFT JOIN marcas_vehiculo mv ON mv.id = v.marca_id
LEFT JOIN modelos_vehiculo modv ON modv.id = v.modelo_id
LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
WHERE iv.accidente_id = ?
ORDER BY iv.orden_participacion, iv.id");
$st->execute([(int) $oficio['accidente_id']]);
$vehiculos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
$vehiculosLineas = [];
foreach ($vehiculos as $index => $row) {
    $parts = array_filter([
        $row['orden_participacion'] ?? '',
        $row['tipo_vehiculo'] ?? '',
        ($row['placa'] ?? '') !== '' ? 'placa ' . $row['placa'] : '',
        $row['marca'] ?? '',
        $row['modelo'] ?? '',
        $row['color'] ?? '',
    ], static fn(mixed $value): bool => dilig_clean($value) !== '');
    $vehiculosLineas[] = ($index + 1) . '. ' . dilig_clean(implode(' - ', $parts));
}

$st = $pdo->prepare("
SELECT p.nombres, p.apellido_paterno, p.apellido_materno, p.tipo_doc, p.num_doc, p.fecha_nacimiento,
       p.edad, p.sexo, p.estado_civil, p.domicilio, p.ocupacion, p.celular, p.email,
       ip.rol_id, ip.vehiculo_id, ip.lesion
FROM involucrados_personas ip
JOIN personas p ON p.id = ip.persona_id
WHERE ip.accidente_id = ?
ORDER BY ip.id");
$st->execute([(int) $oficio['accidente_id']]);
$personas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
$personasLineas = [];
$fallecidos = [];
foreach ($personas as $index => $row) {
    $nombre = dilig_clean(($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? ''));
    $documento = dilig_clean(($row['tipo_doc'] ?? '') . ' ' . ($row['num_doc'] ?? ''));
    $parts = array_filter([
        $nombre,
        $documento !== '' ? $documento : '',
        ($row['edad'] ?? '') !== '' ? $row['edad'] . ' anos' : '',
        ($row['rol_id'] ?? '') !== '' ? 'rol ' . $row['rol_id'] : '',
    ], static fn(mixed $value): bool => dilig_clean($value) !== '');
    $personasLineas[] = ($index + 1) . '. ' . dilig_clean(implode(' - ', $parts));
    if (str_contains(dilig_normalize((string) ($row['lesion'] ?? '')), 'fallec')) {
        $fallecidos[] = $row;
    }
}

$fallecidosLineas = [];
foreach ($fallecidos as $index => $row) {
    $nombre = dilig_clean(($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? ''));
    $documento = dilig_clean(($row['tipo_doc'] ?? '') . ' ' . ($row['num_doc'] ?? ''));
    $parts = array_filter([
        $nombre,
        $documento,
        ($row['edad'] ?? '') !== '' ? $row['edad'] . ' anos' : '',
        $row['lesion'] ?? '',
    ], static fn(mixed $value): bool => dilig_clean($value) !== '');
    $fallecidosLineas[] = ($index + 1) . '. ' . dilig_clean(implode(' - ', $parts));
}
$fallecido = $fallecidos[0] ?? [];

$st = $pdo->prepare('SELECT ma.nombre FROM accidente_modalidad am JOIN modalidad_accidente ma ON ma.id = am.modalidad_id WHERE am.accidente_id = ? ORDER BY ma.id');
$st->execute([(int) $oficio['accidente_id']]);
$modalidades = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
$st = $pdo->prepare('SELECT ca.nombre FROM accidente_consecuencia ac JOIN consecuencia_accidente ca ON ca.id = ac.consecuencia_id WHERE ac.accidente_id = ? ORDER BY ca.id');
$st->execute([(int) $oficio['accidente_id']]);
$consecuencias = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

$destinoPersona = dilig_clean(($oficio['destino_nombres'] ?? '') . ' ' . ($oficio['destino_apep'] ?? '') . ' ' . ($oficio['destino_apem'] ?? ''));
if ($destinoPersona === '') {
    $destinoPersona = dilig_clean($oficio['persona_destino_manual'] ?? '');
}
$fechaAccidente = $oficio['fecha_accidente'] ?? null;
$lugarCompleto = dilig_clean(($oficio['lugar'] ?? '') . (($oficio['accidente_referencia'] ?? '') !== '' ? ' - ' . $oficio['accidente_referencia'] : ''));

$values = [
    'nombre_oficial_ano' => $oficio['nombre_oficial_ano'] ?? '',
    'oficio_numero' => $oficio['numero'] ?? '',
    'oficio_anio' => $oficio['anio'] ?? '',
    'oficio_fecha' => dilig_fecha_larga($oficio['fecha_emision'] ?? null),
    'oficio_motivo' => $oficio['motivo'] ?? '',
    'oficio_referencia' => $oficio['referencia_texto'] ?? '',
    'oficio_entidad_nombre' => $oficio['entidad_nombre'] ?? '',
    'oficio_entidad_siglas' => $oficio['entidad_siglas'] ?? '',
    'oficio_subentidad_nombre' => $oficio['subentidad_nombre'] ?? '',
    'oficio_persona_destino' => $destinoPersona,
    'oficio_grado_cargo' => dilig_clean(($oficio['grado_cargo_nombre'] ?? '') . ' ' . ($oficio['grado_cargo_abrev'] ?? '')),
    'grado_cargo_nombre' => $oficio['grado_cargo_nombre'] ?? '',
    'asunto_nombre' => $oficio['asunto_nombre'] ?? '',
    'asunto_detalle' => $oficio['asunto_detalle'] ?? '',
    'diligencias_solicitadas' => dilig_inline_lines($diligenciasTexto),
    'diligencias_solicitadas_numeradas' => dilig_numbered_lines($diligenciasTexto),
    'diligencias_cantidad' => count(preg_split('/\R/u', $diligenciasTexto) ?: []),
    'accidente_id' => $oficio['accidente_id'] ?? '',
    'accidente_sidpol' => $oficio['registro_sidpol'] ?: ($oficio['sidpol'] ?? ''),
    'accidente_fecha' => dilig_fecha_larga($fechaAccidente),
    'accidente_fecha_abrev' => dilig_fecha_abrev($fechaAccidente),
    'accidente_hora' => $fechaAccidente && strtotime($fechaAccidente) ? date('H:i', strtotime($fechaAccidente)) : '',
    'accidente_lugar' => $oficio['lugar'] ?? '',
    'accidente_lugar_completo' => $lugarCompleto,
    'accidente_referencia' => $oficio['accidente_referencia'] ?? '',
    'accidente_sentido' => $oficio['sentido'] ?? '',
    'accidente_modalidades' => implode(', ', array_map('dilig_clean', $modalidades)),
    'accidente_consecuencias' => implode(', ', array_map('dilig_clean', $consecuencias)),
    'comisaria_nombre' => $oficio['comisaria_nombre'] ?? '',
    'fiscalia_nombre' => $oficio['fiscalia_nombre'] ?? '',
    'vehiculos_involucrados' => implode("\n", $vehiculosLineas),
    'vehiculos_cantidad' => count($vehiculos),
    'personas_involucradas' => implode("\n", $personasLineas),
    'personas_cantidad' => count($personas),
    'fallecido_nombre_completo' => dilig_clean(($fallecido['nombres'] ?? '') . ' ' . ($fallecido['apellido_paterno'] ?? '') . ' ' . ($fallecido['apellido_materno'] ?? '')),
    'fallecido_nombres' => $fallecido['nombres'] ?? '',
    'fallecido_apellidos' => dilig_clean(($fallecido['apellido_paterno'] ?? '') . ' ' . ($fallecido['apellido_materno'] ?? '')),
    'fallecido_tipo_doc' => $fallecido['tipo_doc'] ?? '',
    'fallecido_num_doc' => $fallecido['num_doc'] ?? '',
    'fallecido_documento' => dilig_clean(($fallecido['tipo_doc'] ?? '') . ' ' . ($fallecido['num_doc'] ?? '')),
    'fallecido_fecha_nacimiento' => dilig_fecha_larga($fallecido['fecha_nacimiento'] ?? null),
    'fallecido_fecha_nacimiento_abrev' => dilig_fecha_abrev($fallecido['fecha_nacimiento'] ?? null),
    'fallecido_edad' => $fallecido['edad'] ?? '',
    'fallecido_sexo' => $fallecido['sexo'] ?? '',
    'fallecido_estado_civil' => $fallecido['estado_civil'] ?? '',
    'fallecido_domicilio' => $fallecido['domicilio'] ?? '',
    'fallecido_ocupacion' => $fallecido['ocupacion'] ?? '',
    'fallecido_celular' => $fallecido['celular'] ?? '',
    'fallecido_email' => $fallecido['email'] ?? '',
    'fallecido_lesion' => $fallecido['lesion'] ?? '',
    'fallecidos_involucrados' => implode("\n", $fallecidosLineas),
    'fallecidos_cantidad' => count($fallecidos),
];

$tpl = new TemplateProcessor($template);
foreach ($values as $key => $value) {
    $tpl->setValue($key, dilig_clean($value, in_array($key, ['diligencias_solicitadas_numeradas', 'vehiculos_involucrados', 'personas_involucradas', 'fallecidos_involucrados'], true)));
}
if (isset($_GET['vars']) && method_exists($tpl, 'getVariables')) {
    header('Content-Type: text/plain; charset=utf-8');
    $variables = $tpl->getVariables();
    sort($variables);
    exit(implode(PHP_EOL, $variables) . PHP_EOL);
}

$tmp = tempnam(sys_get_temp_dir(), 'dilig_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear el archivo temporal.');
}
$tpl->saveAs($tmp);
while (ob_get_level()) {
    ob_end_clean();
}
$filename = 'Oficio_Informacion_Diligencias_' . preg_replace('/[^0-9]/', '', (string) ($oficio['numero'] ?? $oficioId)) . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($tmp);
@unlink($tmp);
exit;
