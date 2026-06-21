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
use PhpOffice\PhpWord\Element\TextRun;

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

function caratula_clean(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    return trim($text);
}

function caratula_line_clean(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text;
    return trim($text);
}

function caratula_upper(mixed $value): string
{
    $text = caratula_clean($value);
    return function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
}

function caratula_norm(string $text): string
{
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    $text = strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
        'Ã¡' => 'a', 'Ã©' => 'e', 'Ã­' => 'i', 'Ã³' => 'o', 'Ãº' => 'u', 'Ã±' => 'n',
    ]);
    return preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
}

function caratula_fecha_abrev(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];
    return date('d', $time) . $months[(int) date('n', $time)] . date('Y', $time);
}

function caratula_fecha_larga(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre'];
    return date('d', $time) . ' de ' . $months[(int) date('n', $time)] . ' de ' . date('Y', $time);
}

function caratula_hora(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    return $time ? date('H:i', $time) : '';
}

function caratula_anio(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    return $time ? date('Y', $time) : date('Y');
}

function caratula_edad(?string $birthDate, ?string $referenceDate, mixed $fallback = ''): string
{
    if ($birthDate) {
        try {
            return (string) (new DateTime($birthDate))->diff(new DateTime($referenceDate ?: 'now'))->y;
        } catch (Throwable) {
        }
    }
    return caratula_clean($fallback);
}

function caratula_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $st->execute([$table]);
    return (bool) $st->fetchColumn();
}

function caratula_column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $st->execute([$table, $column]);
    return (bool) $st->fetchColumn();
}

function caratula_persona_nombre(array $row): string
{
    return caratula_clean(implode(' ', array_filter([
        $row['nombres'] ?? '',
        $row['apellido_paterno'] ?? '',
        $row['apellido_materno'] ?? '',
    ], static fn($part): bool => trim((string) $part) !== '')));
}

function caratula_placa_visible(?string $placa): string
{
    $placa = caratula_clean($placa);
    if ($placa === '') {
        return 'SIN PLACA';
    }
    return str_starts_with($placa, 'SPLACA') ? 'SIN PLACA' : $placa;
}

function caratula_vehiculo_line(array $row): string
{
    $parts = [];
    $tipo = caratula_clean($row['veh_tipo'] ?? $row['veh_carroceria'] ?? '');
    if ($tipo !== '') {
        $parts[] = $tipo;
    }
    $parts[] = caratula_placa_visible($row['veh_placa'] ?? '');
    return caratula_clean(implode(' ', $parts));
}

function caratula_participante_line(array $row, ?string $fechaAccidente, bool $withVehicle = false): string
{
    $nombre = caratula_upper(caratula_persona_nombre($row));
    $edad = caratula_edad($row['fecha_nacimiento'] ?? null, $fechaAccidente, $row['edad'] ?? '');
    $celular = caratula_clean($row['celular'] ?? '');
    $lesion = caratula_clean($row['lesion'] ?? '');

    $suffix = [];
    if ($edad !== '') {
        $suffix[] = $edad . ' años';
    }
    if ($lesion !== '') {
        $suffix[] = $lesion;
    }
    if ($celular !== '') {
        $suffix[] = 'Cel. ' . $celular;
    }

    $line = $nombre;
    if ($suffix !== []) {
        $line .= ' (' . implode(' / ', $suffix) . ')';
    }
    if ($withVehicle) {
        $vehicle = caratula_vehiculo_line($row);
        if ($vehicle !== '') {
            $line = $vehicle . "\n" . $line;
        }
    }
    return caratula_line_clean($line);
}

function caratula_nombre_edad(array $row, ?string $fechaAccidente): string
{
    $nombre = caratula_upper(caratula_persona_nombre($row));
    $edad = caratula_edad($row['fecha_nacimiento'] ?? null, $fechaAccidente, $row['edad'] ?? '');
    $line = $edad !== '' ? ($nombre . ' (' . $edad . ')') : $nombre;
    if (str_contains(caratula_norm((string) ($row['lesion'] ?? '')), 'fallec')) {
        $line .= ' - FALLECIDO';
    }
    return $line;
}

function caratula_persona_bloque(array $row, ?string $fechaAccidente, bool $withVehicle = false): string
{
    $lines = [];
    if ($withVehicle) {
        $vehicle = caratula_vehiculo_line($row);
        if ($vehicle !== '') {
            $lines[] = $vehicle;
        }
    }
    $lines[] = caratula_nombre_edad($row, $fechaAccidente);
    $celular = caratula_clean($row['celular'] ?? '');
    $lines[] = $celular !== '' ? ('Cel. ' . $celular) : 'Cel.';
    return caratula_join_lines($lines);
}

function caratula_bloque_etiquetado(string $label, array $entries): string
{
    if ($entries === []) {
        return $label . ':';
    }

    $indent = str_repeat(' ', strlen($label) + 2);
    $blocks = [];
    foreach ($entries as $entry) {
        $entry = caratula_line_clean($entry);
        if ($entry === '') {
            continue;
        }
        $blocks[] = $label . ': ' . str_replace("\n", "\n" . $indent, $entry);
    }

    return $blocks !== [] ? implode("\n\n", $blocks) : ($label . ':');
}

function caratula_text_run(string $text): TextRun
{
    $run = new TextRun();
    $baseStyle = ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => '000000'];
    $fallecidoStyle = ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => 'FF0000'];
    $lines = preg_split('/\R/u', $text) ?: [''];

    foreach ($lines as $lineIndex => $line) {
        if ($lineIndex > 0) {
            $run->addTextBreak();
        }

        $parts = preg_split('/(FALLECIDO)/u', (string) $line, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts ?: [(string) $line] as $part) {
            if ($part === '') {
                continue;
            }
            $run->addText($part, $part === 'FALLECIDO' ? $fallecidoStyle : $baseStyle);
        }
    }

    return $run;
}

function caratula_join_lines(array $lines): string
{
    $lines = array_values(array_filter(array_map('caratula_line_clean', $lines), static fn(string $line): bool => $line !== ''));
    return implode("\n", $lines);
}

$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta accidente_id.');
}

$template = __DIR__ . '/plantillas/caratula.docx';
if (!is_file($template)) {
    http_response_code(500);
    exit('Sube la plantilla como plantillas/caratula.docx para generar la caratula.');
}

$fiscalCargoSelect = caratula_column_exists($pdo, 'fiscales', 'cargo') ? 'fi.cargo' : "''";
$fiscalTelefonoSelect = caratula_column_exists($pdo, 'fiscales', 'telefono') ? 'fi.telefono' : "''";
$sql = "
SELECT a.*,
       d.nombre AS departamento_nombre,
       p.nombre AS provincia_nombre,
       t.nombre AS distrito_nombre,
       c.nombre AS comisaria_nombre,
       fa.nombre AS fiscalia_nombre,
       CONCAT(fi.nombres,' ',fi.apellido_paterno,' ',fi.apellido_materno) AS fiscal_nombre,
       {$fiscalCargoSelect} AS fiscal_cargo,
       {$fiscalTelefonoSelect} AS fiscal_telefono
  FROM accidentes a
  LEFT JOIN ubigeo_departamento d ON d.cod_dep = a.cod_dep
  LEFT JOIN ubigeo_provincia p ON p.cod_dep = a.cod_dep AND p.cod_prov = a.cod_prov
  LEFT JOIN ubigeo_distrito t ON t.cod_dep = a.cod_dep AND t.cod_prov = a.cod_prov AND t.cod_dist = a.cod_dist
  LEFT JOIN comisarias c ON c.id = a.comisaria_id
  LEFT JOIN fiscalia fa ON fa.id = a.fiscalia_id
  LEFT JOIN fiscales fi ON fi.id = a.fiscal_id
 WHERE a.id = ?
 LIMIT 1";
$st = $pdo->prepare($sql);
$st->execute([$accidenteId]);
$accidente = $st->fetch(PDO::FETCH_ASSOC);
if (!$accidente) {
    http_response_code(404);
    exit('Accidente no encontrado.');
}

$sqlPersonas = "
SELECT ip.id AS involucrado_id,
       ip.rol_id,
       ip.vehiculo_id,
       ip.lesion,
       ip.orden_persona,
       p.nombres,
       p.apellido_paterno,
       p.apellido_materno,
       p.fecha_nacimiento,
       p.edad,
       p.celular,
       COALESCE(pp.Nombre, '') AS rol_nombre,
       COALESCE(pp.Orden, 999) AS rol_orden,
       iv.orden_participacion,
       v.placa AS veh_placa,
       COALESCE(tv.nombre, '') AS veh_tipo,
       COALESCE(car.nombre, car.descripcion, '') AS veh_carroceria,
       COALESCE(mar.nombre, '') AS veh_marca,
       COALESCE(modv.nombre, '') AS veh_modelo
  FROM involucrados_personas ip
  JOIN personas p ON p.id = ip.persona_id
  LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id
  LEFT JOIN involucrados_vehiculos iv ON iv.accidente_id = ip.accidente_id AND iv.vehiculo_id = ip.vehiculo_id
  LEFT JOIN vehiculos v ON v.id = ip.vehiculo_id
  LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
  LEFT JOIN carroceria_vehiculo car ON car.id = v.carroceria_id
  LEFT JOIN marcas_vehiculo mar ON mar.id = v.marca_id
  LEFT JOIN modelos_vehiculo modv ON modv.id = v.modelo_id
 WHERE ip.accidente_id = ?
 ORDER BY
       CASE COALESCE(iv.orden_participacion, '')
           WHEN 'UT-1' THEN 1 WHEN 'UT-2' THEN 2 WHEN 'UT-3' THEN 3
           WHEN 'UT-4' THEN 4 WHEN 'UT-5' THEN 5 WHEN 'UT-6' THEN 6
           ELSE 99
       END,
       COALESCE(pp.Orden, 999),
       COALESCE(ip.orden_persona, 'Z'),
       p.apellido_paterno,
       p.apellido_materno,
       p.nombres";
$st = $pdo->prepare($sqlPersonas);
$st->execute([$accidenteId]);
$personas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$familiaresRows = [];
if (caratula_table_exists($pdo, 'familiar_fallecido')) {
    $sqlFamiliares = "
    SELECT pr.nombres,
           pr.apellido_paterno,
           pr.apellido_materno,
           pr.fecha_nacimiento,
           pr.edad,
           pr.celular
      FROM familiar_fallecido ff
      JOIN personas pr ON pr.id = ff.familiar_persona_id
     WHERE ff.accidente_id = ?
     ORDER BY ff.id ASC";
    $st = $pdo->prepare($sqlFamiliares);
    $st->execute([$accidenteId]);
    $familiaresRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$fechaAccidente = (string) ($accidente['fecha_accidente'] ?? '');
$conductores = [];
$conductoresBloque = [];
$peatones = [];
$peatonesBloque = [];
$pasajeros = [];
$pasajerosBloque = [];
$ocupantes = [];
$ocupantesBloque = [];
$otros = [];
$otrosBloque = [];
$familiares = [];
$familiaresBloque = [];
$fallecidos = [];
$heridos = [];
$ilesos = [];

foreach ($personas as $persona) {
    $rolNorm = caratula_norm((string) ($persona['rol_nombre'] ?? ''));
    $lesionNorm = caratula_norm((string) ($persona['lesion'] ?? ''));
    $line = caratula_participante_line($persona, $fechaAccidente, false);

    if (str_contains($rolNorm, 'conduc')) {
        $conductores[] = caratula_participante_line($persona, $fechaAccidente, true);
        $conductoresBloque[] = caratula_persona_bloque($persona, $fechaAccidente, true);
    } elseif (str_contains($rolNorm, 'peaton')) {
        $peatones[] = $line;
        $peatonesBloque[] = caratula_persona_bloque($persona, $fechaAccidente, false);
    } elseif (str_contains($rolNorm, 'pasaj')) {
        $pasajeros[] = $line;
        $pasajerosBloque[] = caratula_persona_bloque($persona, $fechaAccidente, false);
    } elseif (str_contains($rolNorm, 'ocup')) {
        $ocupantes[] = $line;
        $ocupantesBloque[] = caratula_persona_bloque($persona, $fechaAccidente, false);
    } else {
        $otros[] = $line;
        $otrosBloque[] = caratula_persona_bloque($persona, $fechaAccidente, false);
    }

    if (str_contains($lesionNorm, 'fallec')) {
        $fallecidos[] = $line;
    } elseif (str_contains($lesionNorm, 'herid') || str_contains($lesionNorm, 'lesion')) {
        $heridos[] = $line;
    } elseif (str_contains($lesionNorm, 'iles')) {
        $ilesos[] = $line;
    }
}

foreach ($familiaresRows as $familiar) {
    $familiares[] = caratula_participante_line($familiar, $fechaAccidente, false);
    $familiaresBloque[] = caratula_persona_bloque($familiar, $fechaAccidente, false);
}

$participantesBloque = implode("\n\n", array_filter([
    caratula_bloque_etiquetado('CONDUCTOR', $conductoresBloque),
    caratula_bloque_etiquetado('PEATÓN', $peatonesBloque),
    $pasajerosBloque !== [] ? caratula_bloque_etiquetado('PASAJERO', $pasajerosBloque) : '',
    $ocupantesBloque !== [] ? caratula_bloque_etiquetado('OCUPANTE', $ocupantesBloque) : '',
    caratula_bloque_etiquetado('FAMILIAR', $familiaresBloque),
    $otrosBloque !== [] ? caratula_bloque_etiquetado('OTROS', $otrosBloque) : '',
], static fn(string $block): bool => trim($block) !== ''));

$lugarCompleto = caratula_clean(($accidente['lugar'] ?? '') . (($accidente['referencia'] ?? '') !== '' ? ' - ' . $accidente['referencia'] : ''));
$ubicacion = caratula_clean(implode(' / ', array_filter([
    $accidente['departamento_nombre'] ?? '',
    $accidente['provincia_nombre'] ?? '',
    $accidente['distrito_nombre'] ?? '',
])));
$fiscalNombre = caratula_clean($accidente['fiscal_nombre'] ?? '');
$fiscalCargo = caratula_clean($accidente['fiscal_cargo'] ?? '');
if ($fiscalCargo !== '' && $fiscalNombre !== '') {
    $fiscalNombre = $fiscalCargo . ' ' . $fiscalNombre;
}

$values = [
    'accidente_id' => $accidente['id'] ?? '',
    'accidente_informe_numero' => $accidente['nro_informe_policial'] ?? '',
    'informe_numero' => $accidente['nro_informe_policial'] ?? '',
    'accidente_sidpol' => $accidente['registro_sidpol'] ?: ($accidente['sidpol'] ?? ''),
    'sidpol' => $accidente['registro_sidpol'] ?: ($accidente['sidpol'] ?? ''),
    'accidente_lugar' => $lugarCompleto,
    'lugar' => $lugarCompleto,
    'accidente_fecha' => caratula_fecha_abrev($fechaAccidente),
    'accidente_fecha_larga' => caratula_fecha_larga($fechaAccidente),
    'fecha' => caratula_fecha_abrev($fechaAccidente),
    'accidente_hora' => caratula_hora($fechaAccidente),
    'hora' => caratula_hora($fechaAccidente),
    'accidente_anio' => caratula_anio($fechaAccidente),
    'anio' => caratula_anio($fechaAccidente),
    'accidente_ubicacion' => $ubicacion,
    'comisaria_nombre' => $accidente['comisaria_nombre'] ?? '',
    'fiscalia_nombre' => $accidente['fiscalia_nombre'] ?? '',
    'fiscal_nombre' => $fiscalNombre,
    'fiscal_cargo' => $accidente['fiscal_cargo'] ?? '',
    'fiscal_telefono' => $accidente['fiscal_telefono'] ?? '',
    'conductores_resumen' => caratula_join_lines($conductores),
    'conductor_resumen' => caratula_join_lines($conductores),
    'peatones_resumen' => caratula_join_lines($peatones),
    'peaton_resumen' => caratula_join_lines($peatones),
    'pasajeros_resumen' => caratula_join_lines($pasajeros),
    'ocupantes_resumen' => caratula_join_lines($ocupantes),
    'otros_involucrados_resumen' => caratula_join_lines($otros),
    'familiares_resumen' => caratula_join_lines($familiares),
    'familiar_resumen' => caratula_join_lines($familiares),
    'participantes_bloque_caratula' => $participantesBloque,
    'caratula_participantes_bloque' => $participantesBloque,
    'fallecidos_resumen' => caratula_join_lines($fallecidos),
    'heridos_resumen' => caratula_join_lines($heridos),
    'ilesos_resumen' => caratula_join_lines($ilesos),
    'involucrados_resumen' => caratula_join_lines(array_merge($conductores, $peatones, $pasajeros, $ocupantes, $otros)),
    'conductores_cantidad' => (string) count($conductores),
    'peatones_cantidad' => (string) count($peatones),
    'pasajeros_cantidad' => (string) count($pasajeros),
    'ocupantes_cantidad' => (string) count($ocupantes),
    'familiares_cantidad' => (string) count($familiares),
    'fallecidos_cantidad' => (string) count($fallecidos),
    'heridos_cantidad' => (string) count($heridos),
    'ilesos_cantidad' => (string) count($ilesos),
];

$tpl = new TemplateProcessor($template);
foreach ($values as $key => $value) {
    if (in_array($key, ['participantes_bloque_caratula', 'caratula_participantes_bloque'], true)) {
        continue;
    }
    $tpl->setValue($key, caratula_line_clean($value));
}
$tpl->setComplexValue('participantes_bloque_caratula', caratula_text_run($participantesBloque));
$tpl->setComplexValue('caratula_participantes_bloque', caratula_text_run($participantesBloque));

if (method_exists($tpl, 'cloneBlock')) {
    $blocks = [
        'CONDUCTORES' => $conductores,
        'PEATONES' => $peatones,
        'PASAJEROS' => $pasajeros,
        'OCUPANTES' => $ocupantes,
        'OTROS_INVOLUCRADOS' => $otros,
        'FAMILIARES' => $familiares,
        'FALLECIDOS' => $fallecidos,
        'HERIDOS' => $heridos,
        'ILESOS' => $ilesos,
    ];
    foreach ($blocks as $block => $lines) {
        try {
            $tpl->cloneBlock($block, count($lines), true, true);
            foreach (array_values($lines) as $index => $line) {
                $tpl->setValue(strtolower($block) . '_linea#' . ($index + 1), caratula_line_clean($line));
            }
        } catch (Throwable) {
        }
    }
}

if (isset($_GET['vars']) && method_exists($tpl, 'getVariables')) {
    header('Content-Type: text/plain; charset=utf-8');
    echo implode(PHP_EOL, $tpl->getVariables());
    exit;
}

$filename = 'caratula_accidente_' . (int) $accidente['id'] . '_' . date('Ymd_His') . '.docx';
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$tpl->saveAs('php://output');
exit;
