<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}
if (!class_exists(\PhpOffice\PhpWord\PhpWord::class) && is_file(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

if (!class_exists(PhpWord::class) || !class_exists(IOFactory::class)) {
    http_response_code(500);
    exit('PhpWord no esta disponible para generar el DOCX.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET NAMES utf8mb4');

$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0775, true);
}
Settings::setTempDir($tmpDir);
Settings::setOutputEscapingEnabled(true);

$accidenteId = (int) ($_GET['accidente_id'] ?? $_GET['id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta ?accidente_id');
}

function fetch_one_doc(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
}

function fetch_all_doc(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function safe_fetch_one_doc(PDO $pdo, string $sql, array $params = []): array
{
    try {
        return fetch_one_doc($pdo, $sql, $params);
    } catch (Throwable $e) {
        return [];
    }
}

function safe_fetch_all_doc(PDO $pdo, string $sql, array $params = []): array
{
    try {
        return fetch_all_doc($pdo, $sql, $params);
    } catch (Throwable $e) {
        return [];
    }
}

function compact_text_doc($value): string
{
    return preg_replace('/\s+/u', ' ', trim((string) ($value ?? ''))) ?: '';
}

function textv_doc($value, string $fallback = '-'): string
{
    $text = compact_text_doc($value);
    return $text !== '' ? $text : $fallback;
}

function title_text_doc($value): string
{
    $text = compact_text_doc($value);
    if ($text === '') {
        return '';
    }

    return mb_convert_case(mb_strtolower($text, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

function upper_text_doc($value): string
{
    $text = compact_text_doc($value);
    return $text !== '' ? mb_strtoupper($text, 'UTF-8') : '';
}

function list_item_case_doc(string $item, bool $capitalize = false): string
{
    $item = compact_text_doc($item);
    if ($item === '') {
        return '';
    }

    $item = mb_strtolower($item, 'UTF-8');
    if (!$capitalize) {
        return $item;
    }

    return mb_strtoupper(mb_substr($item, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($item, 1, null, 'UTF-8');
}

function join_con_y_doc(array $items): string
{
    $items = array_values(array_filter(array_map(static fn($item): string => compact_text_doc((string) $item), $items)));
    $count = count($items);

    if ($count === 0) {
        return '-';
    }
    if ($count === 1) {
        return list_item_case_doc($items[0], true);
    }
    if ($count === 2) {
        return list_item_case_doc($items[0], true) . ' y ' . list_item_case_doc($items[1]);
    }

    $formatted = [];
    foreach ($items as $index => $item) {
        $formatted[] = list_item_case_doc($item, $index === 0);
    }

    return implode(', ', array_slice($formatted, 0, -1)) . ' y ' . end($formatted);
}

function unique_text_values_doc(array $values): array
{
    $unique = [];
    $seen = [];

    foreach ($values as $value) {
        $text = compact_text_doc($value);
        if ($text === '') {
            continue;
        }

        $hash = mb_strtolower($text, 'UTF-8');
        if (isset($seen[$hash])) {
            continue;
        }

        $seen[$hash] = true;
        $unique[] = $text;
    }

    return $unique;
}

function join_location_parts_doc(array $values): string
{
    $parts = unique_text_values_doc($values);
    return $parts !== [] ? implode(', ', $parts) : '';
}

function fecha_doc($value): string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return '-';
    }

    $time = strtotime($text);
    if (!$time) {
        return $text;
    }

    static $months = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
    return date('d', $time) . $months[(int) date('n', $time) - 1] . date('Y', $time);
}

function fecha_hora_aprox_doc($value): string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return '-';
    }

    $time = strtotime($text);
    if (!$time) {
        return $text;
    }

    return fecha_doc($text) . '; ' . date('H:i', $time) . ' horas, aprox.';
}

function vehiculo_placa_visible_doc($placa): string
{
    $placa = compact_text_doc($placa);
    if ($placa === '') {
        return '';
    }

    return str_starts_with($placa, 'SPLACA') ? 'SIN PLACA' : $placa;
}

function full_name_doc(array $row): string
{
    $parts = array_filter([
        title_text_doc($row['nombres'] ?? ''),
        upper_text_doc($row['apellido_paterno'] ?? ''),
        upper_text_doc($row['apellido_materno'] ?? ''),
    ], static fn(string $item): bool => $item !== '');

    return $parts !== [] ? implode(' ', $parts) : '';
}

function role_key_doc(array $row): string
{
    return mb_strtolower(compact_text_doc($row['rol_nombre'] ?? ''), 'UTF-8');
}

function is_conductor_doc(array $row): bool
{
    $role = role_key_doc($row);
    return $role !== '' && (str_contains($role, 'conductor') || str_contains($role, 'chofer'));
}

function needs_occ_doc(array $row): bool
{
    return mb_strtolower(compact_text_doc($row['lesion'] ?? ''), 'UTF-8') === 'fallecido';
}

function person_summary_priority_doc(array $row): int
{
    if (needs_occ_doc($row)) {
        return 0;
    }
    if (is_conductor_doc($row)) {
        return 1;
    }

    $role = role_key_doc($row);
    if (str_contains($role, 'pasaj')) {
        return 2;
    }
    if (str_contains($role, 'ocup')) {
        return 3;
    }
    if (str_contains($role, 'peat')) {
        return 4;
    }

    return 5;
}

function participant_intervention_text_doc(array $row): string
{
    $name = full_name_doc($row);
    if ($name === '') {
        $name = 'Persona sin identificar';
    }

    $text = $name;
    $edad = compact_text_doc($row['edad'] ?? '');
    if ($edad !== '') {
        $text .= ' (' . $edad . ')';
    }

    $nacionalidad = title_text_doc($row['nacionalidad'] ?? '');
    if ($nacionalidad !== '') {
        $text .= ', de nacionalidad ' . $nacionalidad;
    }

    return $text . '.';
}

function person_relato_doc(array $row, bool $includeRoleLabel = false): string
{
    $name = full_name_doc($row);
    if ($name === '') {
        $name = 'Persona sin identificar';
    }

    $prefix = needs_occ_doc($row) ? 'Q.E.V.F. ' : '';
    $text = $prefix . $name;

    $edad = compact_text_doc($row['edad'] ?? '');
    if ($edad !== '') {
        $text .= ' (' . $edad . ')';
    }

    if ($includeRoleLabel) {
        $role = title_text_doc($row['rol_nombre'] ?? '');
        if ($role !== '') {
            $text = $role . ' ' . $text;
        }
    }

    return $text;
}

function vehicle_relato_doc(array $vehicle): string
{
    $type = title_text_doc($vehicle['veh_tipo'] ?? 'Vehiculo');
    $type = $type !== '' ? $type : 'Vehiculo';
    $plate = compact_text_doc($vehicle['veh_placa'] ?? '');
    if ($plate === '') {
        return $type . ' sin placa registrada';
    }

    return $type . ' con placa de rodaje ' . $plate;
}

function summary_unit_heading_doc(array $summaryUnit): string
{
    $ut = compact_text_doc($summaryUnit['ut'] ?? 'UT');
    $vehicles = $summaryUnit['vehiculos'] ?? [];

    if ($vehicles === []) {
        return $ut . ':';
    }

    if (count($vehicles) > 1) {
        $plates = array_values(array_filter(array_map(
            static fn(array $vehicle): string => compact_text_doc($vehicle['veh_placa'] ?? ''),
            $vehicles
        )));
        return $ut . ' Combinado vehicular de placa ' . implode('/', $plates) . ':';
    }

    $vehicle = $vehicles[0];
    $type = title_text_doc($vehicle['veh_tipo'] ?? 'Vehiculo');
    $plate = compact_text_doc($vehicle['veh_placa'] ?? '');

    return trim($ut . ' ' . $type . ($plate !== '' ? ' con placa ' . $plate : '')) . ':';
}

function summary_role_heading_doc(array $row): string
{
    $role = title_text_doc($row['rol_nombre'] ?? 'Participante');
    $lesion = mb_strtolower(compact_text_doc($row['lesion'] ?? ''), 'UTF-8');

    if (str_contains($lesion, 'fall') && str_contains(mb_strtolower($role, 'UTF-8'), 'peat')) {
        return 'Peaton: OCCISO';
    }
    if (str_contains($lesion, 'fall') && !str_contains(mb_strtolower($role, 'UTF-8'), 'occiso')) {
        return $role . ': OCCISO';
    }

    return rtrim($role, ':') . ':';
}

function ut_sort_index_doc($value): int
{
    $value = compact_text_doc($value);
    if (preg_match('/^UT-(\d+)$/i', $value, $matches)) {
        return (int) $matches[1];
    }

    return 999;
}

function summary_lines_doc(array $summaryUnits, array $summaryPeatones, array $summaryOtrosSinUnidad): array
{
    $lines = [];

    foreach ($summaryUnits as $summaryUnit) {
        $lines[] = summary_unit_heading_doc($summaryUnit);
        $unitPeople = array_values($summaryUnit['personas'] ?? []);
        usort($unitPeople, static function (array $a, array $b): int {
            $aDriver = is_conductor_doc($a) ? 0 : 1;
            $bDriver = is_conductor_doc($b) ? 0 : 1;
            if ($aDriver !== $bDriver) {
                return $aDriver <=> $bDriver;
            }

            $priority = person_summary_priority_doc($a) <=> person_summary_priority_doc($b);
            if ($priority !== 0) {
                return $priority;
            }

            return strcmp((string) ($a['orden_persona'] ?? 'Z'), (string) ($b['orden_persona'] ?? 'Z'));
        });

        foreach ($unitPeople as $person) {
            $lines[] = summary_role_heading_doc($person);
            $lines[] = participant_intervention_text_doc($person);
        }
        $lines[] = '';
    }

    foreach ($summaryPeatones as $person) {
        $lines[] = summary_role_heading_doc($person);
        $lines[] = participant_intervention_text_doc($person);
        $lines[] = '';
    }

    foreach ($summaryOtrosSinUnidad as $person) {
        $lines[] = summary_role_heading_doc($person);
        $lines[] = participant_intervention_text_doc($person);
        $lines[] = '';
    }

    $lines = array_values(array_filter($lines, static fn(string $line): bool => trim($line) !== ''));
    return $lines !== [] ? $lines : ['-'];
}

function summary_relato_doc(array $summaryUnits, array $summaryPeatones, array $summaryOtrosSinUnidad): string
{
    $parts = [];

    foreach ($summaryUnits as $summaryUnit) {
        $vehicles = array_values($summaryUnit['vehiculos'] ?? []);
        $unitPeople = array_values($summaryUnit['personas'] ?? []);
        usort($unitPeople, static function (array $a, array $b): int {
            $aDriver = is_conductor_doc($a) ? 0 : 1;
            $bDriver = is_conductor_doc($b) ? 0 : 1;
            if ($aDriver !== $bDriver) {
                return $aDriver <=> $bDriver;
            }

            $priority = person_summary_priority_doc($a) <=> person_summary_priority_doc($b);
            if ($priority !== 0) {
                return $priority;
            }

            return strcmp((string) ($a['orden_persona'] ?? 'Z'), (string) ($b['orden_persona'] ?? 'Z'));
        });

        $driver = null;
        foreach ($unitPeople as $person) {
            if (is_conductor_doc($person)) {
                $driver = $person;
                break;
            }
        }

        foreach ($vehicles as $vehicle) {
            $phrase = vehicle_relato_doc($vehicle);
            if ($driver !== null) {
                $phrase .= ', conducido por ' . person_relato_doc($driver);
            } else {
                $phrase .= ', sin conductor registrado';
            }
            $parts[] = $phrase;
        }
    }

    foreach ($summaryPeatones as $person) {
        $parts[] = 'en agravio del peaton ' . person_relato_doc($person);
    }

    foreach ($summaryOtrosSinUnidad as $person) {
        $parts[] = 'en agravio de ' . person_relato_doc($person, true);
    }

    return $parts !== [] ? implode(', ', $parts) : '-';
}

function csv_lines_doc($value): array
{
    $items = array_values(array_filter(array_map(
        static fn($item): string => compact_text_doc((string) $item),
        explode(',', (string) ($value ?? ''))
    )));

    return $items !== [] ? $items : ['-'];
}

function itp_value_doc(array $itp, string $field): string
{
    $variants = [$field];
    if (str_contains($field, 'senializacion')) {
        $variants[] = str_replace('senializacion', 'señalizacion', $field);
        $variants[] = str_replace('senializacion', 'seÃ±alizacion', $field);
    }

    foreach ($variants as $variant) {
        if (array_key_exists($variant, $itp)) {
            return textv_doc($itp[$variant]);
        }
    }

    return '-';
}

function itp_has_via2_doc(array $itp): bool
{
    foreach ([
        'descripcion_via2', 'configuracion_via2', 'material_via2', 'senializacion_via2', 'ordenamiento_via2',
        'iluminacion_via2', 'visibilidad_via2', 'intensidad_via2', 'fluidez_via2', 'medidas_via2', 'observaciones_via2',
    ] as $field) {
        if (itp_value_doc($itp, $field) !== '-') {
            return true;
        }
    }

    return false;
}

function append_table_value_doc($cell, $value): void
{
    $lines = [];

    if (is_array($value)) {
        foreach ($value as $line) {
            $text = compact_text_doc($line);
            if ($text !== '') {
                $lines[] = $text;
            }
        }
    } else {
        $text = trim((string) ($value ?? ''));
        if ($text !== '') {
            $parts = preg_split('/\R/u', $text) ?: [];
            foreach ($parts as $part) {
                $line = compact_text_doc($part);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }
    }

    if ($lines === []) {
        $cell->addText('-', ['size' => 9]);
        return;
    }

    foreach ($lines as $line) {
        $cell->addText($line, ['size' => 9], ['spaceAfter' => 0]);
    }
}

function add_heading_doc($section, string $text, int $level = 1): void
{
    $style = $level === 1
        ? ['bold' => true, 'size' => 14, 'color' => '9A6A00']
        : ['bold' => true, 'size' => 11, 'color' => '1F2937'];
    $section->addText($text, $style, ['spaceAfter' => 120]);
}

function add_pairs_doc($section, array $pairs): void
{
    $table = $section->addTable('PairTableDescripcionAnalitica');
    foreach ($pairs as $label => $value) {
        $table->addRow();
        $table->addCell(3400, ['bgColor' => 'F3F4F6'])->addText((string) $label, ['bold' => true, 'size' => 9]);
        $valueCell = $table->addCell(6400);
        append_table_value_doc($valueCell, $value);
    }
    $section->addTextBreak(1);
}

function set_marker_doc(array &$markers, string $key, $value): void
{
    $text = trim((string) ($value ?? ''));
    $markers[$key] = $text !== '' ? $text : '-';
}

function save_template_doc_da(string $templatePath, string $tmpPath, array $markers): void
{
    $template = new TemplateProcessor($templatePath);
    foreach ($markers as $key => $value) {
        $template->setValue($key, (string) $value);
    }
    $template->saveAs($tmpPath);
}

$accidente = fetch_one_doc($pdo, "
    SELECT a.*,
           d.nombre AS departamento_nombre,
           p.nombre AS provincia_nombre,
           t.nombre AS distrito_nombre,
           c.nombre AS comisaria_nombre,
           fa.nombre AS fiscalia_nombre,
           CONCAT(fi.nombres, ' ', fi.apellido_paterno, ' ', fi.apellido_materno) AS fiscal_nombre
      FROM accidentes a
 LEFT JOIN ubigeo_departamento d ON d.cod_dep = a.cod_dep
 LEFT JOIN ubigeo_provincia p ON p.cod_dep = a.cod_dep AND p.cod_prov = a.cod_prov
 LEFT JOIN ubigeo_distrito t ON t.cod_dep = a.cod_dep AND t.cod_prov = a.cod_prov AND t.cod_dist = a.cod_dist
 LEFT JOIN comisarias c ON c.id = a.comisaria_id
 LEFT JOIN fiscalia fa ON fa.id = a.fiscalia_id
 LEFT JOIN fiscales fi ON fi.id = a.fiscal_id
     WHERE a.id = :id
     LIMIT 1
", [':id' => $accidenteId]);

if ($accidente === []) {
    http_response_code(404);
    exit('Accidente no encontrado.');
}

$modalidadesRows = safe_fetch_all_doc($pdo, "
    SELECT m.nombre
      FROM accidente_modalidad am
      JOIN modalidad_accidente m ON m.id = am.modalidad_id
     WHERE am.accidente_id = :id
  ORDER BY m.nombre
", [':id' => $accidenteId]);

$consecuenciasRows = safe_fetch_all_doc($pdo, "
    SELECT c.nombre
      FROM accidente_consecuencia ac
      JOIN consecuencia_accidente c ON c.id = ac.consecuencia_id
     WHERE ac.accidente_id = :id
  ORDER BY c.nombre
", [':id' => $accidenteId]);

$modalidades = array_column($modalidadesRows, 'nombre');
$consecuencias = array_column($consecuenciasRows, 'nombre');

$vehiculosRows = safe_fetch_all_doc($pdo, "
    SELECT iv.id AS inv_vehiculo_id,
           iv.orden_participacion,
           iv.tipo AS veh_participacion,
           v.id AS vehiculo_id,
           v.placa AS veh_placa,
           tv.nombre AS veh_tipo
      FROM involucrados_vehiculos iv
      JOIN vehiculos v ON v.id = iv.vehiculo_id
 LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
     WHERE iv.accidente_id = :id
  ORDER BY FIELD(iv.orden_participacion, 'UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7'), iv.id ASC
", [':id' => $accidenteId]);

foreach ($vehiculosRows as &$vehiculoRow) {
    $vehiculoRow['veh_placa'] = vehiculo_placa_visible_doc($vehiculoRow['veh_placa'] ?? '');
}
unset($vehiculoRow);

$personas = safe_fetch_all_doc($pdo, "
    SELECT ip.id AS involucrado_id,
           ip.persona_id,
           ip.vehiculo_id,
           ip.rol_id,
           ip.lesion,
           ip.orden_persona,
           p.nombres,
           p.apellido_paterno,
           p.apellido_materno,
           p.edad,
           p.nacionalidad,
           pr.Nombre AS rol_nombre,
           iv.id AS inv_vehiculo_id,
           iv.orden_participacion
      FROM involucrados_personas ip
      JOIN personas p ON p.id = ip.persona_id
 LEFT JOIN participacion_persona pr ON pr.Id = ip.rol_id
 LEFT JOIN involucrados_vehiculos iv ON iv.accidente_id = ip.accidente_id AND iv.vehiculo_id = ip.vehiculo_id
     WHERE ip.accidente_id = :id
  ORDER BY FIELD(iv.orden_participacion, 'UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7'), ip.id ASC
", [':id' => $accidenteId]);

$summaryUnits = [];
foreach ($vehiculosRows as $vehiculoRow) {
    $ut = compact_text_doc($vehiculoRow['orden_participacion'] ?? '');
    if ($ut === '') {
        continue;
    }

    $summaryUnits[$ut] ??= ['ut' => $ut, 'vehiculos' => [], 'personas' => []];
    $summaryUnits[$ut]['vehiculos'][(int) ($vehiculoRow['inv_vehiculo_id'] ?? 0)] = $vehiculoRow;
}

foreach ($personas as $persona) {
    $ut = compact_text_doc($persona['orden_participacion'] ?? '');
    $hasVehicle = (int) ($persona['vehiculo_id'] ?? 0) > 0;
    if ($ut !== '' && $hasVehicle) {
        $summaryUnits[$ut] ??= ['ut' => $ut, 'vehiculos' => [], 'personas' => []];
        $summaryUnits[$ut]['personas'][] = $persona;
    }
}

uasort($summaryUnits, static fn(array $a, array $b): int => ut_sort_index_doc($a['ut'] ?? '') <=> ut_sort_index_doc($b['ut'] ?? ''));
foreach ($summaryUnits as &$summaryUnit) {
    $summaryUnit['vehiculos'] = array_values($summaryUnit['vehiculos']);
    usort($summaryUnit['personas'], static function (array $a, array $b): int {
        $priority = person_summary_priority_doc($a) <=> person_summary_priority_doc($b);
        if ($priority !== 0) {
            return $priority;
        }

        return strcmp((string) ($a['orden_persona'] ?? 'Z'), (string) ($b['orden_persona'] ?? 'Z'));
    });
}
unset($summaryUnit);

$summaryPeatones = [];
$summaryOtrosSinUnidad = [];
foreach ($personas as $persona) {
    $ut = compact_text_doc($persona['orden_participacion'] ?? '');
    $hasVehicle = (int) ($persona['vehiculo_id'] ?? 0) > 0;
    if ($ut !== '' && $hasVehicle) {
        continue;
    }

    if (str_contains(role_key_doc($persona), 'peat')) {
        $summaryPeatones[] = $persona;
        continue;
    }

    $summaryOtrosSinUnidad[] = $persona;
}

$sortSummaryPeople = static function (array &$rows): void {
    usort($rows, static function (array $a, array $b): int {
        $priority = person_summary_priority_doc($a) <=> person_summary_priority_doc($b);
        if ($priority !== 0) {
            return $priority;
        }

        return strcmp((string) ($a['orden_persona'] ?? 'Z'), (string) ($b['orden_persona'] ?? 'Z'));
    });
};
$sortSummaryPeople($summaryPeatones);
$sortSummaryPeople($summaryOtrosSinUnidad);

$itp = safe_fetch_one_doc($pdo, "
    SELECT i.*
      FROM itp i
     WHERE i.accidente_id = :id
  ORDER BY i.id DESC
     LIMIT 1
", [':id' => $accidenteId]);

$comisaria = compact_text_doc($accidente['comisaria_nombre'] ?? '');
if ($comisaria !== '' && mb_stripos($comisaria, 'comisaria', 0, 'UTF-8') === false) {
    $comisaria = 'Comisaria PNP ' . $comisaria;
}

$claseViaZonaParts = unique_text_values_doc([
    title_text_doc($itp['forma_via'] ?? ''),
    title_text_doc($itp['configuracion_via1'] ?? ''),
]);
if ($claseViaZonaParts === []) {
    $claseViaZonaParts = unique_text_values_doc([
        title_text_doc($itp['descripcion_via1'] ?? ''),
    ]);
}
$claseViaZona = $claseViaZonaParts !== [] ? implode('-', $claseViaZonaParts) : '-';

$generalPairs = [
    '1. Clase de accidente' => join_con_y_doc($modalidades),
    '2. Consecuencia' => join_con_y_doc($consecuencias),
    '3. Lugar y jurisdiccion policial' => join_location_parts_doc([
        compact_text_doc($accidente['lugar'] ?? ''),
        title_text_doc($accidente['distrito_nombre'] ?? ''),
        $comisaria,
    ]) ?: '-',
    '4. Fecha y hora del accidente' => fecha_hora_aprox_doc($accidente['fecha_accidente'] ?? ''),
    '5. Fecha y hora de comunicacion' => fecha_hora_aprox_doc($accidente['fecha_comunicacion'] ?? ''),
    '6. Fecha y hora de intervencion' => fecha_hora_aprox_doc($accidente['fecha_intervencion'] ?? ''),
    '7. Unidades participantes' => summary_lines_doc($summaryUnits, $summaryPeatones, $summaryOtrosSinUnidad),
    '8. Clase de via y zona' => $claseViaZona,
    '9. Fiscalia' => textv_doc($accidente['fiscalia_nombre'] ?? ''),
    '10. Fiscal a cargo' => textv_doc($accidente['fiscal_nombre'] ?? ''),
    '11. Sentido' => textv_doc($accidente['sentido'] ?? ''),
    '12. Secuencia' => textv_doc($accidente['secuencia'] ?? ''),
];

$markers = [];
foreach ([
    'accidente_id' => $accidenteId,
    'acc_sidpol' => textv_doc($accidente['sidpol'] ?? ''),
    'acc_registro_sidpol' => textv_doc($accidente['registro_sidpol'] ?? ''),
    'acc_nro_informe_policial' => textv_doc($accidente['nro_informe_policial'] ?? ''),
    'acc_clase_accidente' => $generalPairs['1. Clase de accidente'],
    'acc_consecuencia' => $generalPairs['2. Consecuencia'],
    'acc_lugar_jurisdiccion_policial' => $generalPairs['3. Lugar y jurisdiccion policial'],
    'acc_fecha_hora_accidente' => $generalPairs['4. Fecha y hora del accidente'],
    'acc_fecha_hora_comunicacion' => $generalPairs['5. Fecha y hora de comunicacion'],
    'acc_fecha_hora_intervencion' => $generalPairs['6. Fecha y hora de intervencion'],
    'acc_unidades_participantes' => implode("\n", summary_lines_doc($summaryUnits, $summaryPeatones, $summaryOtrosSinUnidad)),
    'acc_unidades_participantes_relato' => summary_relato_doc($summaryUnits, $summaryPeatones, $summaryOtrosSinUnidad),
    'acc_clase_via_zona' => $generalPairs['8. Clase de via y zona'],
    'acc_fiscalia' => $generalPairs['9. Fiscalia'],
    'acc_fiscal_cargo' => $generalPairs['10. Fiscal a cargo'],
    'acc_sentido' => $generalPairs['11. Sentido'],
    'acc_secuencia' => $generalPairs['12. Secuencia'],
    'itp_id' => $itp !== [] ? (string) ($itp['id'] ?? '') : '-',
    'itp_fecha' => $itp !== [] ? fecha_doc($itp['fecha_itp'] ?? '') : '-',
    'itp_hora' => $itp !== [] ? textv_doc($itp['hora_itp'] ?? '') : '-',
    'itp_forma_via' => $itp !== [] ? textv_doc($itp['forma_via'] ?? '') : '-',
    'itp_punto_referencia' => $itp !== [] ? textv_doc($itp['punto_referencia'] ?? '') : '-',
    'itp_ubicacion_gps' => $itp !== [] ? textv_doc($itp['ubicacion_gps'] ?? '') : '-',
    'itp_localizacion_unidades' => $itp !== [] ? implode("\n", csv_lines_doc($itp['localizacion_unidades'] ?? '')) : '-',
    'itp_ocurrencia_policial' => $itp !== [] ? textv_doc($itp['ocurrencia_policial'] ?? '') : '-',
    'itp_llegada_lugar' => $itp !== [] ? textv_doc($itp['llegada_lugar'] ?? '') : '-',
    'itp_via1_descripcion' => $itp !== [] ? textv_doc($itp['descripcion_via1'] ?? '') : '-',
    'itp_via1_configuracion' => $itp !== [] ? textv_doc($itp['configuracion_via1'] ?? '') : '-',
    'itp_via1_material' => $itp !== [] ? textv_doc($itp['material_via1'] ?? '') : '-',
    'itp_via1_senializacion' => $itp !== [] ? itp_value_doc($itp, 'senializacion_via1') : '-',
    'itp_via1_ordenamiento' => $itp !== [] ? textv_doc($itp['ordenamiento_via1'] ?? '') : '-',
    'itp_via1_iluminacion' => $itp !== [] ? textv_doc($itp['iluminacion_via1'] ?? '') : '-',
    'itp_via1_visibilidad' => $itp !== [] ? textv_doc($itp['visibilidad_via1'] ?? '') : '-',
    'itp_via1_intensidad' => $itp !== [] ? textv_doc($itp['intensidad_via1'] ?? '') : '-',
    'itp_via1_fluidez' => $itp !== [] ? textv_doc($itp['fluidez_via1'] ?? '') : '-',
    'itp_via1_medidas' => $itp !== [] ? implode("\n", csv_lines_doc($itp['medidas_via1'] ?? '')) : '-',
    'itp_via1_observaciones' => $itp !== [] ? implode("\n", csv_lines_doc($itp['observaciones_via1'] ?? '')) : '-',
    'itp_via2_descripcion' => $itp !== [] ? textv_doc($itp['descripcion_via2'] ?? '') : '-',
    'itp_via2_configuracion' => $itp !== [] ? textv_doc($itp['configuracion_via2'] ?? '') : '-',
    'itp_via2_material' => $itp !== [] ? textv_doc($itp['material_via2'] ?? '') : '-',
    'itp_via2_senializacion' => $itp !== [] ? itp_value_doc($itp, 'senializacion_via2') : '-',
    'itp_via2_ordenamiento' => $itp !== [] ? textv_doc($itp['ordenamiento_via2'] ?? '') : '-',
    'itp_via2_iluminacion' => $itp !== [] ? textv_doc($itp['iluminacion_via2'] ?? '') : '-',
    'itp_via2_visibilidad' => $itp !== [] ? textv_doc($itp['visibilidad_via2'] ?? '') : '-',
    'itp_via2_intensidad' => $itp !== [] ? textv_doc($itp['intensidad_via2'] ?? '') : '-',
    'itp_via2_fluidez' => $itp !== [] ? textv_doc($itp['fluidez_via2'] ?? '') : '-',
    'itp_via2_medidas' => $itp !== [] ? implode("\n", csv_lines_doc($itp['medidas_via2'] ?? '')) : '-',
    'itp_via2_observaciones' => $itp !== [] ? implode("\n", csv_lines_doc($itp['observaciones_via2'] ?? '')) : '-',
    'itp_evidencia_biologica' => $itp !== [] ? textv_doc($itp['evidencia_biologica'] ?? '') : '-',
    'itp_evidencia_fisica' => $itp !== [] ? textv_doc($itp['evidencia_fisica'] ?? '') : '-',
    'itp_evidencia_material' => $itp !== [] ? textv_doc($itp['evidencia_material'] ?? '') : '-',
] as $key => $value) {
    set_marker_doc($markers, $key, $value);
}

$filename = 'informe_descripcion_analitica_accidente_' . $accidenteId . '.docx';
$templatePath = __DIR__ . '/plantillas/word_informe_descripcion_analitica.docx';
$useTemplate = is_file($templatePath) && (string) ($_GET['sin_plantilla'] ?? '') !== '1';

if ($useTemplate) {
    if (!class_exists(TemplateProcessor::class)) {
        http_response_code(500);
        exit('TemplateProcessor no esta disponible para usar la plantilla DOCX.');
    }

    $tmp = tempnam($tmpDir, 'datpl_');
    if ($tmp === false) {
        http_response_code(500);
        exit('No se pudo crear el archivo temporal.');
    }

    save_template_doc_da($templatePath, $tmp, $markers);

    while (ob_get_level()) {
        @ob_end_clean();
    }
    if (headers_sent($fileSent, $lineSent)) {
        @unlink($tmp);
        http_response_code(500);
        exit('No se pudo iniciar la descarga del DOCX porque ya habia salida previa en ' . $fileSent . ':' . $lineSent);
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Arial');
$phpWord->setDefaultFontSize(10);
$phpWord->addTableStyle('PairTableDescripcionAnalitica', [
    'borderSize' => 6,
    'borderColor' => 'D1D5DB',
    'cellMargin' => 80,
]);

$section = $phpWord->addSection([
    'marginTop' => 900,
    'marginBottom' => 900,
    'marginLeft' => 900,
    'marginRight' => 900,
]);

$section->addText('INFORME DE DESCRIPCION ANALITICA', ['bold' => true, 'size' => 15], ['align' => 'center', 'spaceAfter' => 120]);

add_pairs_doc($section, [
    'Accidente' => '#' . $accidenteId,
    'SIDPOL' => textv_doc($accidente['sidpol'] ?? ''),
    'Registro SIDPOL' => textv_doc($accidente['registro_sidpol'] ?? ''),
    'Nro. informe policial' => textv_doc($accidente['nro_informe_policial'] ?? ''),
]);

add_heading_doc($section, 'II. DATOS DE LA INTERVENCION');
add_pairs_doc($section, $generalPairs);

add_heading_doc($section, 'III. INFORME TECNICO POLICIAL (ITP)');
if ($itp === []) {
    $section->addText('Sin ITP registrado para este accidente.', ['italic' => true, 'size' => 10, 'color' => '666666']);
} else {
    add_pairs_doc($section, [
        'Registro ITP' => '#' . (int) ($itp['id'] ?? 0),
        'Fecha ITP' => fecha_doc($itp['fecha_itp'] ?? ''),
        'Hora ITP' => textv_doc($itp['hora_itp'] ?? ''),
        'Forma de la via' => textv_doc($itp['forma_via'] ?? ''),
        'Punto de referencia' => textv_doc($itp['punto_referencia'] ?? ''),
        'Ubicacion GPS' => textv_doc($itp['ubicacion_gps'] ?? ''),
        'Localizacion de unidades' => csv_lines_doc($itp['localizacion_unidades'] ?? ''),
        'Ocurrencia policial' => textv_doc($itp['ocurrencia_policial'] ?? ''),
        'Llegada al lugar' => textv_doc($itp['llegada_lugar'] ?? ''),
    ]);

    add_heading_doc($section, 'Via 1', 2);
    add_pairs_doc($section, [
        'Descripcion' => textv_doc($itp['descripcion_via1'] ?? ''),
        'Configuracion' => textv_doc($itp['configuracion_via1'] ?? ''),
        'Material' => textv_doc($itp['material_via1'] ?? ''),
        'Senializacion' => itp_value_doc($itp, 'senializacion_via1'),
        'Ordenamiento' => textv_doc($itp['ordenamiento_via1'] ?? ''),
        'Iluminacion' => textv_doc($itp['iluminacion_via1'] ?? ''),
        'Visibilidad' => textv_doc($itp['visibilidad_via1'] ?? ''),
        'Intensidad' => textv_doc($itp['intensidad_via1'] ?? ''),
        'Fluidez' => textv_doc($itp['fluidez_via1'] ?? ''),
        'Medidas' => csv_lines_doc($itp['medidas_via1'] ?? ''),
        'Observaciones' => csv_lines_doc($itp['observaciones_via1'] ?? ''),
    ]);

    if (itp_has_via2_doc($itp)) {
        add_heading_doc($section, 'Via 2', 2);
        add_pairs_doc($section, [
            'Descripcion' => textv_doc($itp['descripcion_via2'] ?? ''),
            'Configuracion' => textv_doc($itp['configuracion_via2'] ?? ''),
            'Material' => textv_doc($itp['material_via2'] ?? ''),
            'Senializacion' => itp_value_doc($itp, 'senializacion_via2'),
            'Ordenamiento' => textv_doc($itp['ordenamiento_via2'] ?? ''),
            'Iluminacion' => textv_doc($itp['iluminacion_via2'] ?? ''),
            'Visibilidad' => textv_doc($itp['visibilidad_via2'] ?? ''),
            'Intensidad' => textv_doc($itp['intensidad_via2'] ?? ''),
            'Fluidez' => textv_doc($itp['fluidez_via2'] ?? ''),
            'Medidas' => csv_lines_doc($itp['medidas_via2'] ?? ''),
            'Observaciones' => csv_lines_doc($itp['observaciones_via2'] ?? ''),
        ]);
    }

    add_heading_doc($section, 'Evidencias', 2);
    add_pairs_doc($section, [
        'Evidencia biologica' => textv_doc($itp['evidencia_biologica'] ?? ''),
        'Evidencia fisica' => textv_doc($itp['evidencia_fisica'] ?? ''),
        'Evidencia material' => textv_doc($itp['evidencia_material'] ?? ''),
    ]);
}

$tmp = tempnam($tmpDir, 'daacc_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear el archivo temporal.');
}

IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

while (ob_get_level()) {
    @ob_end_clean();
}

if (headers_sent($fileSent, $lineSent)) {
    @unlink($tmp);
    http_response_code(500);
    exit('No se pudo iniciar la descarga del DOCX porque ya habia salida previa en ' . $fileSent . ':' . $lineSent);
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;