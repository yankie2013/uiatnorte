<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\PersonaRepository;
use App\Repositories\AbogadoRepository;
use App\Repositories\AccidenteRepository;
use App\Repositories\DiligenciaPendienteRepository;
use App\Repositories\DocumentoManifestacionRepository;
use App\Repositories\ItpRepository;
use App\Repositories\OficioRepository;
use App\Repositories\InvolucradoPersonaRepository;
use App\Repositories\PolicialIntervinienteRepository;
use App\Repositories\PropietarioVehiculoRepository;
use App\Repositories\VehiculoRepository;
use App\Services\AbogadoService;
use App\Services\AccidenteService;
use App\Services\DiligenciaPendienteService;
use App\Services\DocumentoManifestacionService;
use App\Services\ItpService;
use App\Services\OficioService;
use App\Services\InvolucradoPersonaService;
use App\Services\PersonaService;
use App\Services\PolicialIntervinienteService;
use App\Services\PropietarioVehiculoService;
use App\Services\VehiculoService;

header('Content-Type: text/html; charset=utf-8');

if (!isset($pdo) && isset($db) && $db instanceof PDO) {
    $pdo = $db;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

$accidenteRepo = new AccidenteRepository($pdo);
$accidenteService = new AccidenteService($accidenteRepo);
$oficioService = new OficioService(new OficioRepository($pdo));
$oficioEstados = $oficioService->formContext()['estados'] ?? ['BORRADOR', 'FIRMADO', 'ENVIADO', 'ANULADO', 'ARCHIVADO'];

function h($value): string
{
    return htmlspecialchars(normalize_mojibake((string) $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalize_mojibake(string $value): string
{
    if ($value === '' || !preg_match('/[ÃÂâ]/u', $value)) {
        return $value;
    }

    $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
        return $converted;
    }

    return $value;
}

function fmt($value): string
{
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? h($value) : '—';
}

function full_name(array $row): string
{
    $name = trim(
        (string) ($row['nombres'] ?? '') . ' '
        . (string) ($row['apellido_paterno'] ?? '') . ' '
        . (string) ($row['apellido_materno'] ?? '')
    );
    return preg_replace('/\s+/u', ' ', $name) ?: '';
}

function person_label(array $row): string
{
    $name = full_name($row);
    return $name !== '' ? $name : ('Persona #' . (int) ($row['persona_id'] ?? 0));
}

function person_doc_label(?string $tipoDoc): string
{
    $tipoDoc = mb_strtoupper(trim((string) ($tipoDoc ?? '')), 'UTF-8');

    return match ($tipoDoc) {
        'DNI' => 'DNI',
        'CE' => 'Carnet de extranjería',
        'PAS' => 'Pasaporte',
        'OTRO' => 'Documento',
        default => $tipoDoc !== '' ? $tipoDoc : 'Documento',
    };
}

function person_heading_suffix(array $row): string
{
    $parts = [];

    $numDoc = trim((string) ($row['num_doc'] ?? ''));
    if ($numDoc !== '') {
        $parts[] = person_doc_label((string) ($row['tipo_doc'] ?? '')) . ' ' . $numDoc;
    }

    $edad = trim((string) ($row['edad'] ?? ''));
    if ($edad !== '') {
        $parts[] = $edad . ' años';
    }

    if ($parts === []) {
        return '';
    }

    return ' (' . implode(' · ', $parts) . ')';
}

function person_heading_meta(array $row): string
{
    return trim(person_heading_suffix($row), ' ()');
}

function fecha_hora_corta_esp(?string $value): string
{
    if (!$value || !strtotime($value)) {
        return '—';
    }

    $months = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
    $time = strtotime($value);

    return date('d', $time) . $months[(int) date('n', $time) - 1] . date('Y H:i', $time);
}

function fecha_simple(?string $value): string
{
    if (!$value || !strtotime($value)) {
        return '—';
    }
    return date('d/m/Y', strtotime($value));
}

function fecha_generales_ley(?string $value): string
{
    if (!$value || !strtotime($value)) {
        return '';
    }

    $months = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
    $time = strtotime($value);

    return date('d', $time) . $months[(int) date('n', $time) - 1] . date('Y', $time);
}

function fecha_hora_simple(?string $value): string
{
    if (!$value || !strtotime($value)) {
        return '—';
    }
    return date('d/m/Y H:i', strtotime($value));
}

function format_list_item_case(string $item, bool $capitalize = false): string
{
    $item = preg_replace('/\s+/u', ' ', trim($item)) ?? trim($item);
    if ($item === '') {
        return '';
    }

    $item = mb_strtolower($item, 'UTF-8');
    if (!$capitalize) {
        return $item;
    }

    return mb_strtoupper(mb_substr($item, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($item, 1, null, 'UTF-8');
}

function join_con_y(array $items): string
{
    $items = array_values(array_filter(array_map(static fn($item) => preg_replace('/\s+/u', ' ', trim((string) $item)) ?? trim((string) $item), $items)));
    $count = count($items);

    if ($count === 0) {
        return '—';
    }
    if ($count === 1) {
        return h(format_list_item_case($items[0], true));
    }
    if ($count === 2) {
        return h(format_list_item_case($items[0], true)) . ' y ' . h(format_list_item_case($items[1]));
    }

    $escaped = [];
    foreach ($items as $index => $item) {
        $escaped[] = h(format_list_item_case($item, $index === 0));
    }
    return implode(', ', array_slice($escaped, 0, -1)) . ' y ' . end($escaped);
}

function compact_text(?string $value): string
{
    return preg_replace('/\s+/u', ' ', trim((string) ($value ?? ''))) ?: '';
}

function lower_text(string $value): string
{
    return mb_strtolower(compact_text($value), 'UTF-8');
}

function title_text(string $value): string
{
    $text = compact_text($value);
    if ($text === '') {
        return '';
    }

    return mb_convert_case(mb_strtolower($text, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

function upper_text(string $value): string
{
    $text = compact_text($value);
    return $text !== '' ? mb_strtoupper($text, 'UTF-8') : '';
}

function person_name_generales_ley(array $row): string
{
    $parts = array_filter([
        title_text((string) ($row['nombres'] ?? '')),
        upper_text((string) ($row['apellido_paterno'] ?? '')),
        upper_text((string) ($row['apellido_materno'] ?? '')),
    ], static fn(string $item): bool => $item !== '');

    return $parts !== [] ? implode(' ', $parts) : '';
}

function unique_text_values(array $values): array
{
    $unique = [];
    $seen = [];

    foreach ($values as $value) {
        $text = compact_text((string) $value);
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

function join_location_parts(array $values): string
{
    $parts = unique_text_values($values);
    return $parts !== [] ? implode(', ', $parts) : '';
}

function persona_generales_ley_text(array $row): string
{
    $name = person_name_generales_ley($row);
    if ($name === '') {
        $name = 'Persona sin identificar';
    }

    $clauses = [];

    $edad = compact_text((string) ($row['edad'] ?? ''));
    $nameWithAge = $name;
    if ($edad !== '') {
        $nameWithAge .= ' (' . $edad . ')';
    }

    $fechaNacimiento = fecha_generales_ley((string) ($row['fecha_nacimiento'] ?? ''));
    if ($fechaNacimiento !== '') {
        $clauses[] = 'fecha de nacimiento ' . $fechaNacimiento;
    }

    $naturalDe = title_text((string) ($row['departamento_nac'] ?? ''));
    if ($naturalDe !== '') {
        $clauses[] = 'natural de ' . $naturalDe;
    }

    $estadoCivil = lower_text((string) ($row['estado_civil'] ?? ''));
    if ($estadoCivil !== '') {
        $clauses[] = 'estado civil ' . $estadoCivil;
    }

    $gradoInstruccion = lower_text((string) ($row['grado_instruccion'] ?? ''));
    if ($gradoInstruccion !== '') {
        $clauses[] = 'grado de instrucción ' . $gradoInstruccion;
    }

    $tipoDoc = compact_text((string) ($row['tipo_doc'] ?? ''));
    $numDoc = compact_text((string) ($row['num_doc'] ?? ''));
    if ($numDoc !== '') {
        $docLabel = $tipoDoc !== '' ? mb_strtoupper($tipoDoc, 'UTF-8') : 'DOCUMENTO';
        $clauses[] = 'con ' . $docLabel . ' N° ' . $numDoc;
    }

    $padre = title_text((string) ($row['nombre_padre'] ?? ''));
    $madre = title_text((string) ($row['nombre_madre'] ?? ''));
    $sexo = mb_strtoupper(compact_text((string) ($row['sexo'] ?? '')), 'UTF-8');
    $filiacion = $sexo === 'F' ? 'hija' : 'hijo';
    if ($padre !== '' && $madre !== '') {
        $clauses[] = $filiacion . ' de don ' . $padre . ' y doña ' . $madre;
    } elseif ($padre !== '') {
        $clauses[] = $filiacion . ' de don ' . $padre;
    } elseif ($madre !== '') {
        $clauses[] = $filiacion . ' de doña ' . $madre;
    }

    $domicilioBase = title_text((string) ($row['domicilio'] ?? ''));
    $domicilioUbicacion = join_location_parts([
        title_text((string) ($row['domicilio_distrito'] ?? '')),
        title_text((string) ($row['domicilio_provincia'] ?? '')),
        title_text((string) ($row['domicilio_departamento'] ?? '')),
    ]);
    $domicilio = join_location_parts([$domicilioBase, $domicilioUbicacion]);
    if ($domicilio !== '') {
        $clauses[] = ($sexo === 'F' ? 'domiciliada en ' : 'domiciliado en ') . $domicilio;
    }

    $texto = '<span class="persona-story-name">' . h($nameWithAge) . '</span>';
    if ($clauses !== []) {
        $texto .= ' ' . h(implode(', ', $clauses)) . '.';
    }

    return $texto;
}

function persona_generales_ley_copy_text(array $row): string
{
    $html = persona_generales_ley_text($row);
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return $text !== '' ? (preg_replace('/\s+/u', ' ', $text) ?: $text) : '—';
}

function render_persona_story_block(array $row, string $title = 'Generales de ley'): string
{
    $html = '<div class="persona-story-block">';
    $html .= '<div class="persona-story-head">';
    $html .= '<h3 class="persona-story-title">' . h($title) . '</h3>';
    $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h(persona_generales_ley_copy_text($row)) . '" aria-label="Copiar generales de ley" title="Copiar generales de ley">⧉</button>';
    $html .= '</div>';
    $html .= '<p class="persona-story-text">' . persona_generales_ley_text($row) . '</p>';
    $html .= '</div>';

    return $html;
}

function join_con_y_text(array $items): string
{
    $items = array_values(array_filter(array_map(static fn($item) => preg_replace('/\s+/u', ' ', trim((string) $item)) ?? trim((string) $item), $items)));
    $count = count($items);

    if ($count === 0) {
        return '';
    }
    if ($count === 1) {
        return format_list_item_case($items[0], true);
    }
    if ($count === 2) {
        return format_list_item_case($items[0], true) . ' y ' . format_list_item_case($items[1]);
    }

    $formatted = [];
    foreach ($items as $index => $item) {
        $formatted[] = format_list_item_case($item, $index === 0);
    }

    return implode(', ', array_slice($formatted, 0, -1)) . ' y ' . end($formatted);
}

function fecha_hora_aprox_text(?string $value): string
{
    if (!$value || !strtotime($value)) {
        return '—';
    }

    $time = strtotime($value);
    return fecha_generales_ley($value) . '; ' . date('H:i', $time) . ' horas, aprox.';
}

function participant_intervention_text(array $row): string
{
    $name = person_name_generales_ley($row);
    if ($name === '') {
        $name = 'Persona sin identificar';
    }

    $text = $name;
    $edad = compact_text((string) ($row['edad'] ?? ''));
    if ($edad !== '') {
        $text .= ' (' . $edad . ')';
    }

    $nacionalidad = title_text((string) ($row['nacionalidad'] ?? ''));
    if ($nacionalidad !== '') {
        $text .= ', de nacionalidad ' . $nacionalidad;
    }

    return $text . '.';
}

function summary_unit_heading(array $summaryUnit): string
{
    $ut = compact_text((string) ($summaryUnit['ut'] ?? 'UT'));
    $vehicles = $summaryUnit['vehiculos'] ?? [];

    if ($vehicles === []) {
        return $ut . ':';
    }

    if (count($vehicles) > 1) {
        $plates = array_values(array_filter(array_map(static fn(array $vehicle): string => compact_text((string) ($vehicle['veh_placa'] ?? '')), $vehicles)));
        return $ut . ' Combinado vehicular de placa ' . implode('/', $plates) . ':';
    }

    $vehicle = $vehicles[0];
    $type = title_text((string) ($vehicle['veh_tipo'] ?? 'Vehículo'));
    $plate = compact_text((string) ($vehicle['veh_placa'] ?? ''));
    return trim($ut . ' ' . $type . ($plate !== '' ? ' con placa ' . $plate : '')) . ':';
}

function summary_role_heading(array $row): string
{
    $role = title_text((string) ($row['rol_nombre'] ?? 'Participante'));
    $lesion = mb_strtolower(compact_text((string) ($row['lesion'] ?? '')), 'UTF-8');
    if (str_contains($lesion, 'fall') && str_contains(mb_strtolower($role, 'UTF-8'), 'peat')) {
        return 'Peatón: OCCISO';
    }
    if (str_contains($lesion, 'fall') && !str_contains(mb_strtolower($role, 'UTF-8'), 'occiso')) {
        return $role . ': OCCISO';
    }

    return rtrim($role, ':') . ':';
}

function summary_units_intervention_html(array $summaryUnits, array $summaryPeatones, array $summaryOtrosSinUnidad): string
{
    $parts = [];

    foreach ($summaryUnits as $summaryUnit) {
        $chunk = '<div class="intervention-unit-block">';
        $chunk .= '<div class="intervention-unit-title"><strong>' . h(summary_unit_heading($summaryUnit)) . '</strong></div>';
        foreach (($summaryUnit['personas'] ?? []) as $person) {
            $chunk .= '<div class="intervention-unit-role"><strong>' . h(summary_role_heading($person)) . '</strong></div>';
            $chunk .= '<div class="intervention-unit-person">' . h(participant_intervention_text($person)) . '</div>';
        }
        $chunk .= '</div>';
        $parts[] = $chunk;
    }

    foreach ($summaryPeatones as $person) {
        $chunk = '<div class="intervention-unit-block">';
        $chunk .= '<div class="intervention-unit-role"><strong>' . h(summary_role_heading($person)) . '</strong></div>';
        $chunk .= '<div class="intervention-unit-person">' . h(participant_intervention_text($person)) . '</div>';
        $chunk .= '</div>';
        $parts[] = $chunk;
    }

    foreach ($summaryOtrosSinUnidad as $person) {
        $chunk = '<div class="intervention-unit-block">';
        $chunk .= '<div class="intervention-unit-role"><strong>' . h(summary_role_heading($person)) . '</strong></div>';
        $chunk .= '<div class="intervention-unit-person">' . h(participant_intervention_text($person)) . '</div>';
        $chunk .= '</div>';
        $parts[] = $chunk;
    }

    return $parts !== [] ? implode('', $parts) : '—';
}

function summary_units_intervention_text(array $summaryUnits, array $summaryPeatones, array $summaryOtrosSinUnidad): string
{
    $parts = [];

    foreach ($summaryUnits as $summaryUnit) {
        $chunk = [summary_unit_heading($summaryUnit)];
        foreach (($summaryUnit['personas'] ?? []) as $person) {
            $chunk[] = summary_role_heading($person);
            $chunk[] = participant_intervention_text($person);
        }
        $parts[] = implode("\n", $chunk);
    }

    foreach ($summaryPeatones as $person) {
        $parts[] = summary_role_heading($person) . "\n" . participant_intervention_text($person);
    }

    foreach ($summaryOtrosSinUnidad as $person) {
        $parts[] = summary_role_heading($person) . "\n" . participant_intervention_text($person);
    }

    return $parts !== [] ? implode("\n", $parts) : '—';
}

function safe_query_all(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function safe_query_one(PDO $pdo, string $sql, array $params = []): ?array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function safe_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$table, $column]);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function role_chip_class(string $role): string
{
    $roleKey = mb_strtolower(trim($role), 'UTF-8');
    if ($roleKey === '') {
        return 'chip-role';
    }
    if (str_contains($roleKey, 'conduct')) {
        return 'chip-role chip-conductor';
    }
    if (str_contains($roleKey, 'peat')) {
        return 'chip-role chip-peaton';
    }
    if (str_contains($roleKey, 'pasaj')) {
        return 'chip-role chip-pasajero';
    }
    if (str_contains($roleKey, 'ocup')) {
        return 'chip-role chip-ocupante';
    }
    if (str_contains($roleKey, 'testig')) {
        return 'chip-role chip-testigo';
    }
    return 'chip-role';
}

function lesion_chip_class(string $lesion): string
{
    $lesion = mb_strtolower(trim($lesion), 'UTF-8');
    if ($lesion === '') {
        return 'chip-status';
    }
    if (str_contains($lesion, 'iles')) {
        return 'chip-status chip-status-ok';
    }
    if (str_contains($lesion, 'heri')) {
        return 'chip-status chip-status-warn';
    }
    if (str_contains($lesion, 'fall')) {
        return 'chip-status chip-status-danger';
    }
    return 'chip-status';
}

function tab_person_label(array $row): string
{
    $class = !empty($row['vehiculo_id']) ? 'Con vehículo' : 'Sin vehículo';
    $role = trim((string) ($row['rol_nombre'] ?? 'Sin rol'));
    return $class . ' · ' . $role;
}

function tab_person_short_name(array $row): string
{
    $name = trim((string) ($row['nombres'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $name = trim((string) ($row['apellido_paterno'] ?? ''));
    return $name !== '' ? $name : ('P' . (int) ($row['persona_id'] ?? 0));
}

function is_conductor(array $row): bool
{
    $role = mb_strtolower(trim((string) ($row['rol_nombre'] ?? '')), 'UTF-8');
    if ($role === '') {
        return false;
    }
    return str_contains($role, 'conductor') || str_contains($role, 'chofer');
}

function role_key(array $row): string
{
    return mb_strtolower(trim((string) ($row['rol_nombre'] ?? '')), 'UTF-8');
}

function needs_lc(array $row): bool
{
    return is_conductor($row);
}

function needs_rml(array $row): bool
{
    $role = role_key($row);
    $lesion = mb_strtolower(trim((string) ($row['lesion'] ?? '')), 'UTF-8');
    return str_contains($role, 'conductor') || str_contains($lesion, 'herido');
}

function needs_dos(array $row): bool
{
    $role = role_key($row);
    return str_contains($role, 'conductor')
        || str_contains($role, 'peat')
        || str_contains($role, 'pasaj')
        || str_contains($role, 'ocup');
}

function needs_man(array $row): bool
{
    return (int) ($row['rol_id'] ?? 0) > 0;
}

function needs_occ(array $row): bool
{
    $lesion = mb_strtolower(trim((string) ($row['lesion'] ?? '')), 'UTF-8');
    return $lesion === 'fallecido';
}

function needs_herido(array $row): bool
{
    $lesion = mb_strtolower(trim((string) ($row['lesion'] ?? '')), 'UTF-8');
    return str_contains($lesion, 'heri');
}

function whatsapp_contact_message(array $modalidades, ?string $fechaAccidente, ?string $lugarAccidente): string
{
    $inicio = html_entity_decode(
        'Buen d&iacute;a le saluda ST3.PNP Giancarlo MERINO SANCHO de la UIAT NORTE, a cargo de la investigaci&oacute;n por el accidente de tr&aacute;nsito ',
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
    $medio = html_entity_decode(', suscitado el d&iacute;a ', ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $inicio
        . join_con_y($modalidades)
        . $medio
        . fecha_simple($fechaAccidente)
        . ' en '
        . ($lugarAccidente ?? 'el lugar del accidente')
        . '.';
}

function person_tab_tone_class(array $row): string
{
    if (needs_occ($row)) {
        return 'tab-occiso';
    }
    if (is_conductor($row)) {
        return 'tab-driver';
    }
    if (needs_herido($row)) {
        return 'tab-herido';
    }
    return '';
}

function person_panel_tone_class(array $row): string
{
    if (needs_occ($row)) {
        return 'occiso-panel';
    }
    if (is_conductor($row)) {
        return 'driver-panel';
    }
    if (needs_herido($row)) {
        return 'herido-panel';
    }
    return '';
}

function es_participacion_combinada(?string $tipo): bool
{
    return in_array((string) ($tipo ?? ''), ['Combinado vehicular 1', 'Combinado vehicular 2'], true);
}

function vehiculo_placa_visible(?string $placa): string
{
    $placa = trim((string) ($placa ?? ''));
    if ($placa === '') {
        return '';
    }

    return str_starts_with($placa, 'SPLACA') ? 'SIN PLACA' : $placa;
}

function ut_sort_index(?string $value): int
{
    $value = trim((string) ($value ?? ''));
    if (preg_match('/^UT-(\d+)$/i', $value, $matches)) {
        return (int) $matches[1];
    }

    return 999;
}

function summary_letter(int $index): string
{
    $index = max(0, $index);
    $label = '';

    do {
        $label = chr(65 + ($index % 26)) . $label;
        $index = intdiv($index, 26) - 1;
    } while ($index >= 0);

    return $label;
}

function person_summary_priority(array $row): int
{
    if (needs_occ($row)) {
        return 0;
    }
    if (is_conductor($row)) {
        return 1;
    }

    $role = role_key($row);
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

function vehicle_summary_record(array $row): array
{
    return [
        'inv_vehiculo_id' => $row['inv_vehiculo_id'] ?? null,
        'accidente_id' => $row['accidente_id'] ?? null,
        'veh_numero' => $row['veh_numero'] ?? '',
        'veh_participacion' => $row['veh_participacion'] ?? '',
        'orden_participacion' => $row['orden_participacion'] ?? '',
        'veh_id' => $row['veh_id'] ?? null,
        'veh_categoria_id' => $row['veh_categoria_id'] ?? null,
        'veh_tipo_id' => $row['veh_tipo_id'] ?? null,
        'veh_carroceria_id' => $row['veh_carroceria_id'] ?? null,
        'veh_marca_id' => $row['veh_marca_id'] ?? null,
        'veh_modelo_id' => $row['veh_modelo_id'] ?? null,
        'veh_placa' => vehiculo_placa_visible((string) ($row['veh_placa'] ?? '')),
        'veh_serie_vin' => $row['veh_serie_vin'] ?? null,
        'veh_nro_motor' => $row['veh_nro_motor'] ?? null,
        'veh_categoria' => $row['veh_categoria'] ?? null,
        'veh_tipo' => $row['veh_tipo'] ?? null,
        'veh_carroceria' => $row['veh_carroceria'] ?? null,
        'veh_marca' => $row['veh_marca'] ?? null,
        'veh_modelo' => $row['veh_modelo'] ?? null,
        'veh_anio' => $row['veh_anio'] ?? null,
        'veh_color' => $row['veh_color'] ?? null,
        'veh_largo_mm' => $row['veh_largo_mm'] ?? null,
        'veh_ancho_mm' => $row['veh_ancho_mm'] ?? null,
        'veh_alto_mm' => $row['veh_alto_mm'] ?? null,
        'veh_notas' => $row['veh_notas'] ?? null,
        'veh_creado_en' => $row['veh_creado_en'] ?? null,
        'veh_actualizado_en' => $row['veh_actualizado_en'] ?? null,
    ];
}

function human_label(string $key): string
{
    static $map = [
        'tipo_doc' => 'Tipo de documento',
        'num_doc' => 'Número de documento',
        'rol_nombre' => 'Rol',
        'lesion' => 'Lesión',
        'orden_participacion' => 'UT',
        'veh_participacion' => 'Tipo de unidad',
        'involucrado_observaciones' => 'Observaciones de participación',
        'apellido_paterno' => 'Apellido paterno',
        'apellido_materno' => 'Apellido materno',
        'nombres' => 'Nombres',
        'sexo' => 'Sexo',
        'fecha_nacimiento' => 'Fecha de nacimiento',
        'edad' => 'Edad',
        'estado_civil' => 'Estado civil',
        'nacionalidad' => 'Nacionalidad',
        'departamento_nac' => 'Departamento de nacimiento',
        'provincia_nac' => 'Provincia de nacimiento',
        'distrito_nac' => 'Distrito de nacimiento',
        'domicilio' => 'Domicilio',
        'ocupacion' => 'Ocupación',
        'grado_instruccion' => 'Grado de instrucción',
        'nombre_padre' => 'Nombre del padre',
        'nombre_madre' => 'Nombre de la madre',
        'celular' => 'Celular',
        'email' => 'Email',
        'notas' => 'Notas',
        'creado_en' => 'Creado en',
        'foto_path' => 'Foto',
        'api_fuente' => 'Fuente API',
        'api_ref' => 'Referencia API',
        'persona_creado_en' => 'Persona creada en',
        'domicilio_departamento' => 'Departamento de domicilio',
        'domicilio_provincia' => 'Provincia de domicilio',
        'domicilio_distrito' => 'Distrito de domicilio',
        'rol_nombre' => 'Rol',
        'orden_persona' => 'Orden persona',
        'lesion' => 'Lesión',
        'involucrado_observaciones' => 'Observaciones del involucrado',
        'involucrado_creado_en' => 'Involucrado creado en',
        'involucrado_actualizado_en' => 'Involucrado actualizado en',
        'orden_participacion' => 'Unidad de tránsito',
        'veh_participacion' => 'Participación del vehículo',
        'veh_combo_placas' => 'Placas de la unidad combinada',
        'inv_vehiculo_observaciones' => 'Obs. vehículo involucrado',
        'numero_propiedad' => 'Número',
        'titulo_propiedad' => 'Título',
        'partida_propiedad' => 'Partida',
        'sede_propiedad' => 'Sede',
        'numero_soat' => 'Número',
        'aseguradora_soat' => 'Aseguradora',
        'vigente_soat' => 'Vigente desde',
        'vencimiento_soat' => 'Vence',
        'numero_revision' => 'Número',
        'certificadora_revision' => 'Certificadora',
        'vigente_revision' => 'Vigente desde',
        'vencimiento_revision' => 'Vence',
        'expedido_por' => 'Expedido por',
        'vigente_hasta' => 'Vigente hasta',
        'resultado_cualitativo' => 'Resultado cualitativo',
        'resultado_cuantitativo' => 'Resultado cuantitativo',
        'fecha_extraccion' => 'Fecha de extracción',
        'numero_registro' => 'Número de registro',
        'horario_inicio' => 'Hora de inicio',
        'hora_termino' => 'Hora de término',
        'parentesco' => 'Parentesco',
        'tipo_propietario' => 'Tipo de propietario',
        'rol_legal' => 'Rol legal',
        'ruc' => 'RUC',
        'razon_social' => 'Razón social',
        'domicilio_fiscal' => 'Domicilio fiscal',
        'colegiatura' => 'Colegiatura',
        'registro' => 'Registro',
        'casilla_electronica' => 'Casilla electrónica',
        'domicilio_procesal' => 'Domicilio procesal',
        'persona_rep_nom' => 'Representado',
        'condicion_representado' => 'Condición representado',
        'entidad' => 'Entidad',
        'asunto_nombre' => 'Asunto',
        'asunto_detalle' => 'Detalle del asunto',
        'referencia_texto' => 'Referencia',
        'motivo' => 'Motivo',
        'fecha_emision' => 'Fecha de emisión',
        'veh_ut' => 'UT vinculada',
        'persona_nombre' => 'Persona vinculada',
        'numero_peritaje' => 'Número',
        'fecha_peritaje' => 'Fecha',
        'perito_peritaje' => 'Perito',
        'sistema_electrico_peritaje' => 'Sistema electrico',
        'sistema_frenos_peritaje' => 'Sistema de frenos',
        'sistema_direccion_peritaje' => 'Sistema de direccion',
        'sistema_transmision_peritaje' => 'Sistema de transmision',
        'sistema_suspension_peritaje' => 'Sistema de suspension',
        'planta_motriz_peritaje' => 'Planta motriz',
        'otros_peritaje' => 'Otros',
        'danos_peritaje' => 'Daños constatados',
        'fecha_levantamiento' => 'Fecha',
        'hora_levantamiento' => 'Hora',
        'lugar_levantamiento' => 'Lugar',
        'posicion_cuerpo_levantamiento' => 'Posición del cuerpo',
        'lesiones_levantamiento' => 'Lesiones',
        'presuntivo_levantamiento' => 'Diagnóstico presuntivo',
        'legista_levantamiento' => 'Médico legista',
        'cmp_legista' => 'CMP legista',
        'observaciones_levantamiento' => 'Observaciones',
        'numero_pericial' => 'Número pericial',
        'fecha_pericial' => 'Fecha pericial',
        'hora_pericial' => 'Hora pericial',
        'observaciones_pericial' => 'Observaciones periciales',
        'numero_protocolo' => 'Número de protocolo',
        'fecha_protocolo' => 'Fecha de protocolo',
        'hora_protocolo' => 'Hora de protocolo',
        'lesiones_protocolo' => 'Lesiones del protocolo',
        'presuntivo_protocolo' => 'Presuntivo del protocolo',
        'dosaje_protocolo' => 'Dosaje',
        'toxicologico_protocolo' => 'Toxicológico',
        'nosocomio_epicrisis' => 'Nosocomio',
        'numero_historia_epicrisis' => 'Nro. historia clínica',
        'tratamiento_epicrisis' => 'Tratamiento',
        'hora_alta_epicrisis' => 'Hora de alta',
        'veh_placa' => 'Placa',
        'veh_serie_vin' => 'Serie VIN',
        'veh_nro_motor' => 'Nro. motor',
        'veh_categoria' => 'Categoría',
        'veh_tipo' => 'Tipo',
        'veh_carroceria' => 'Carrocería',
        'veh_marca' => 'Marca',
        'veh_modelo' => 'Modelo',
        'veh_anio' => 'Año',
        'veh_color' => 'Color',
        'veh_largo_mm' => 'Largo (mm)',
        'veh_ancho_mm' => 'Ancho (mm)',
        'veh_alto_mm' => 'Alto (mm)',
        'veh_notas' => 'Notas del vehículo',
        'veh_creado_en' => 'Vehículo creado en',
        'veh_actualizado_en' => 'Vehículo actualizado en',
        'fecha_itp' => 'Fecha ITP',
        'hora_itp' => 'Hora ITP',
        'forma_via' => 'Forma de la vía',
        'punto_referencia' => 'Punto de referencia',
        'ubicacion_gps' => 'Ubicación GPS',
        'localizacion_unidades' => 'Localización de unidades',
        'ocurrencia_policial' => 'Ocurrencia policial',
        'llegada_lugar' => 'Llegada al lugar',
        'descripcion_via1' => 'Descripción',
        'configuracion_via1' => 'Configuración',
        'material_via1' => 'Material',
        'senializacion_via1' => 'Señalización',
        'ordenamiento_via1' => 'Ordenamiento',
        'iluminacion_via1' => 'Iluminación',
        'visibilidad_via1' => 'Visibilidad',
        'intensidad_via1' => 'Intensidad',
        'fluidez_via1' => 'Fluidez',
        'medidas_via1' => 'Medidas',
        'observaciones_via1' => 'Observaciones',
        'descripcion_via2' => 'Descripción',
        'configuracion_via2' => 'Configuración',
        'material_via2' => 'Material',
        'senializacion_via2' => 'Señalización',
        'ordenamiento_via2' => 'Ordenamiento',
        'iluminacion_via2' => 'Iluminación',
        'visibilidad_via2' => 'Visibilidad',
        'intensidad_via2' => 'Intensidad',
        'fluidez_via2' => 'Fluidez',
        'medidas_via2' => 'Medidas',
        'observaciones_via2' => 'Observaciones',
        'evidencia_biologica' => 'Evidencia biológica',
        'evidencia_fisica' => 'Evidencia física',
        'evidencia_material' => 'Evidencia material',
    ];

    if (isset($map[$key])) {
        return $map[$key];
    }

    return ucwords(str_replace('_', ' ', $key));
}

function field_html(string $key, mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    if (str_ends_with($key, '_en') || str_starts_with($key, 'fecha_')) {
        $text = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return h(fecha_simple($text));
        }
        if (strtotime($text)) {
            return h(fecha_hora_simple($text));
        }
    }

    if ($key === 'foto_path') {
        return '<a href="' . h((string) $value) . '" target="_blank" rel="noopener">' . h((string) $value) . '</a>';
    }

    $text = trim((string) $value);
    if (in_array($key, ['veh_placa', 'veh_combo_placas'], true)) {
        $text = vehiculo_placa_visible($text);
    }

    return nl2br(h($text !== '' ? $text : '—'));
}

function render_field_cards(array $record, array $fields): string
{
    $html = '';
    foreach ($fields as $field) {
        $key = is_array($field) ? (string) ($field['key'] ?? '') : (string) $field;
        if ($key === '') {
            continue;
        }
        $class = is_array($field) ? (string) ($field['class'] ?? '') : '';
        $html .= '<div class="field-card ' . h($class) . '">';
        $html .= '<div class="field-label">' . h(human_label($key)) . '</div>';
        $html .= '<div class="field-value">' . field_html($key, $record[$key] ?? null) . '</div>';
        $html .= '</div>';
    }
    return $html;
}

function render_csv_list_html(?string $value): string
{
    $items = array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn($item): bool => $item !== ''));
    if ($items === []) {
        return '—';
    }

    $html = '<ul class="itp-list">';
    foreach ($items as $item) {
        $html .= '<li>' . h($item) . '</li>';
    }
    $html .= '</ul>';

    return $html;
}

function record_has_any_content(array $record, array $fields): bool
{
    foreach ($fields as $field) {
        $key = is_array($field) ? (string) ($field['key'] ?? '') : (string) $field;
        if ($key === '') {
            continue;
        }

        $value = $record[$key] ?? null;
        if ($value === null) {
            continue;
        }

        if (trim((string) $value) !== '') {
            return true;
        }
    }

    return false;
}

function project_prefixed_record(array $row, string $prefix): array
{
    $projected = [];
    foreach ($row as $key => $value) {
        if (str_starts_with((string) $key, $prefix)) {
            $projected[substr((string) $key, strlen($prefix))] = $value;
        }
    }
    return $projected;
}

function render_summary_field_sections(array $record, array $sections): string
{
    $html = '';
    foreach ($sections as $title => $fields) {
        if (!record_has_any_content($record, $fields)) {
            continue;
        }

        $html .= '<div class="section-block">';
        $html .= '<h3>' . h((string) $title) . '</h3>';
        $html .= '<div class="field-grid">' . render_field_cards($record, $fields) . '</div>';
        $html .= '</div>';
    }

    return $html;
}

function vehicle_measure_text(mixed $value): string
{
    $raw = compact_text((string) $value);
    if ($raw === '' || !is_numeric($raw)) {
        return '—';
    }

    $number = (float) $raw;
    $meters = $number > 20 ? ($number / 1000) : $number;

    return number_format($meters, 2, '.', '') . ' m.';
}

function vehicle_doc_date_text(?string $value): string
{
    return fecha_generales_ley($value);
}

function vehicle_revision_status(?string $expiresAt): string
{
    if (!$expiresAt || !strtotime($expiresAt)) {
        return '';
    }

    return strtotime(date('Y-m-d')) <= strtotime(date('Y-m-d', strtotime($expiresAt))) ? 'VIGENTE' : 'VENCIDO';
}

function vehicle_document_sentences(array $document): array
{
    $sentences = [];

    $numeroPropiedad = compact_text((string) ($document['numero_propiedad'] ?? ''));
    $sedePropiedad = title_text((string) ($document['sede_propiedad'] ?? ''));
    $partidaPropiedad = compact_text((string) ($document['partida_propiedad'] ?? ''));
    $tituloPropiedad = compact_text((string) ($document['titulo_propiedad'] ?? ''));
    if ($numeroPropiedad !== '' || $sedePropiedad !== '' || $partidaPropiedad !== '' || $tituloPropiedad !== '') {
        $parts = [];
        if ($numeroPropiedad !== '') {
            $parts[] = 'Tarjeta de propiedad N° ' . $numeroPropiedad;
        } else {
            $parts[] = 'Tarjeta de propiedad';
        }
        if ($sedePropiedad !== '') {
            $parts[] = 'oficina registral ' . $sedePropiedad;
        }
        if ($partidaPropiedad !== '') {
            $parts[] = 'partida registral N° ' . $partidaPropiedad;
        }
        if ($tituloPropiedad !== '') {
            $parts[] = 'titulo N° ' . $tituloPropiedad;
        }
        $sentences[] = implode(', ', $parts) . '.';
    }

    $numeroSoat = compact_text((string) ($document['numero_soat'] ?? ''));
    $aseguradoraSoat = title_text((string) ($document['aseguradora_soat'] ?? ''));
    $vigenteSoat = vehicle_doc_date_text((string) ($document['vigente_soat'] ?? ''));
    $venceSoat = vehicle_doc_date_text((string) ($document['vencimiento_soat'] ?? ''));
    if ($numeroSoat !== '' || $aseguradoraSoat !== '' || $vigenteSoat !== '' || $venceSoat !== '') {
        $parts = [];
        if ($numeroSoat !== '') {
            $parts[] = 'Certificado SOAT N° ' . $numeroSoat;
        } else {
            $parts[] = 'Certificado SOAT';
        }
        if ($aseguradoraSoat !== '') {
            $parts[] = 'de la empresa aseguradora "' . $aseguradoraSoat . '"';
        }
        if ($vigenteSoat !== '' || $venceSoat !== '') {
            $vigencia = 'con fecha de vigencia';
            if ($vigenteSoat !== '') {
                $vigencia .= ' desde ' . $vigenteSoat;
            }
            if ($venceSoat !== '') {
                $vigencia .= ' hasta el ' . $venceSoat;
            }
            $parts[] = $vigencia;
        }
        $sentences[] = implode(', ', $parts) . '.';
    }

    $numeroRevision = compact_text((string) ($document['numero_revision'] ?? ''));
    $certificadoraRevision = title_text((string) ($document['certificadora_revision'] ?? ''));
    $vigenteRevision = vehicle_doc_date_text((string) ($document['vigente_revision'] ?? ''));
    $venceRevision = vehicle_doc_date_text((string) ($document['vencimiento_revision'] ?? ''));
    $estadoRevision = vehicle_revision_status((string) ($document['vencimiento_revision'] ?? ''));
    if ($numeroRevision !== '' || $certificadoraRevision !== '' || $vigenteRevision !== '' || $venceRevision !== '' || $estadoRevision !== '') {
        $parts = [];
        if ($numeroRevision !== '') {
            $parts[] = 'Certificado de inspección técnica vehicular N° ' . $numeroRevision;
        } else {
            $parts[] = 'Certificado de inspección técnica vehicular';
        }
        if ($certificadoraRevision !== '') {
            $parts[] = 'de la empresa certificadora ' . $certificadoraRevision;
        }
        if ($vigenteRevision !== '' || $venceRevision !== '') {
            $fechas = [];
            if ($vigenteRevision !== '') {
                $fechas[] = 'fecha de expedición ' . $vigenteRevision;
            }
            if ($venceRevision !== '') {
                $fechas[] = 'vencimiento ' . $venceRevision;
            }
            $parts[] = implode(' y ', $fechas);
        }
        if ($estadoRevision !== '') {
            $parts[] = 'estado actual ' . $estadoRevision;
        }
        $sentences[] = implode(', ', $parts) . '.';
    }

    return $sentences;
}

function render_vehicle_peritaje_components_story(array $documents): string
{
    $labels = [
        'sistema_electrico_peritaje' => 'Sistema electrico',
        'sistema_frenos_peritaje' => 'Sistema de frenos',
        'sistema_direccion_peritaje' => 'Sistema de direccion',
        'sistema_transmision_peritaje' => 'Sistema de transmision',
        'sistema_suspension_peritaje' => 'Sistema de suspension',
        'planta_motriz_peritaje' => 'Planta motriz',
        'otros_peritaje' => 'Otros',
    ];

    $blocks = [];
    foreach ($documents as $document) {
        $rows = [];
        foreach ($labels as $field => $label) {
            $value = compact_text((string) ($document[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $rows[] = ['label' => $label, 'value' => $value];
        }

        $damages = compact_text((string) ($document['danos_peritaje'] ?? ''));
        if ($rows === [] && $damages === '') {
            continue;
        }

        $blocks[] = ['rows' => $rows, 'damages' => $damages];
    }

    if ($blocks === []) {
        return '';
    }

    $html = '';
    foreach ($blocks as $index => $block) {
        $html .= '<div class="vehicle-peritaje-story-block">';
        $html .= '<h3 class="vehicle-docs-story-title">' . h(($index + 6) . '. Peritaje') . '</h3>';
        if ($block['rows'] !== []) {
            $html .= '<div class="vehicle-story-list">';
            foreach ($block['rows'] as $row) {
                $html .= '<div class="vehicle-story-row">';
                $html .= '<span class="vehicle-story-key">- ' . h($row['label']) . '</span>';
                $html .= '<span class="vehicle-story-sep">:</span>';
                $html .= '<span class="vehicle-story-value-wrap">';
                $html .= '<span class="vehicle-story-value">' . h($row['value']) . '</span>';
                $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($row['value']) . '" aria-label="Copiar peritaje" title="Copiar peritaje">⧉</button>';
                $html .= '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        if ($block['damages'] !== '') {
            $html .= '<div class="vehicle-docs-story-list" style="margin-top:8px">';
            $html .= '<div class="vehicle-docs-story-item">';
            $html .= '<span class="vehicle-docs-story-text">- Daños constatados: ' . h($block['damages']) . '</span>';
            $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($block['damages']) . '" aria-label="Copiar daños constatados" title="Copiar daños constatados">⧉</button>';
            $html .= '</div></div>';
        }
        $html .= '</div>';
    }

    return $html;
}

function render_analysis_peritaje_story(array $record): string
{
    $labels = [
        'sistema_electrico_peritaje' => 'Sistema electrico',
        'sistema_frenos_peritaje' => 'Sistema de frenos',
        'sistema_direccion_peritaje' => 'Sistema de direccion',
        'sistema_transmision_peritaje' => 'Sistema de transmision',
        'sistema_suspension_peritaje' => 'Sistema de suspension',
        'planta_motriz_peritaje' => 'Planta motriz',
    ];

    $rows = [];
    foreach ($labels as $field => $label) {
        $value = compact_text((string) ($record[$field] ?? ''));
        if ($value === '') {
            continue;
        }
        $rows[] = ['label' => $label, 'value' => $value];
    }

    $damages = compact_text((string) ($record['danos_peritaje'] ?? ''));
    if ($rows === [] && $damages === '') {
        return '<div class="summary-empty">No hay peritaje registrado para este conductor.</div>';
    }

    $html = '<div class="vehicle-docs-story-block">';
    $html .= '<h3 class="vehicle-docs-story-title">6. Peritaje</h3>';
    if ($rows !== []) {
        $html .= '<div class="vehicle-story-list">';
        foreach ($rows as $row) {
            $html .= '<div class="vehicle-story-row">';
            $html .= '<span class="vehicle-story-key">- ' . h($row['label']) . '</span>';
            $html .= '<span class="vehicle-story-sep">:</span>';
            $html .= '<span class="vehicle-story-value-wrap">';
            $html .= '<span class="vehicle-story-value">' . h($row['value']) . '</span>';
            $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($row['value']) . '" aria-label="Copiar peritaje" title="Copiar peritaje">⧉</button>';
            $html .= '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    if ($damages !== '') {
        $html .= '<div class="vehicle-docs-story-list" style="margin-top:8px">';
        $html .= '<div class="vehicle-docs-story-item">';
        $html .= '<span class="vehicle-docs-story-text">- Daños constatados: ' . h($damages) . '</span>';
        $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($damages) . '" aria-label="Copiar daños constatados" title="Copiar daños constatados">⧉</button>';
        $html .= '</div></div>';
    }
    $html .= '</div>';

    return $html;
}

function render_vehicle_documents_story(array $documents): string
{
    $sentences = [];
    foreach ($documents as $document) {
        $sentences = array_merge($sentences, vehicle_document_sentences($document));
    }

    if ($sentences === []) {
        return '<div class="summary-empty">No hay documentos registrados para este vehículo.</div>';
    }

    $html = '<div class="vehicle-docs-story-block">';
    $html .= '<h3 class="vehicle-docs-story-title">5. Documentos</h3>';
    $html .= '<div class="vehicle-docs-story-list">';
    foreach ($sentences as $sentence) {
        $html .= '<div class="vehicle-docs-story-item">';
        $html .= '<span class="vehicle-docs-story-text">- ' . h($sentence) . '</span>';
        $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($sentence) . '" aria-label="Copiar documento" title="Copiar documento">⧉</button>';
        $html .= '</div>';
    }
    $html .= render_vehicle_peritaje_components_story($documents);
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function render_vehicle_story_block(array $vehicle): string
{
    $vehicleType = title_text((string) ($vehicle['veh_tipo'] ?? ''));
    if ($vehicleType === '') {
        $vehicleType = 'Vehículo';
    }

    $plate = compact_text((string) ($vehicle['veh_placa'] ?? ''));
    $title = $vehicleType . ($plate !== '' ? ' de placa de rodaje ' . $plate : ' sin placa registrada');

    $sections = [
        'Características' => [
            'Categoría' => title_text((string) ($vehicle['veh_categoria'] ?? '')),
            'Color' => title_text((string) ($vehicle['veh_color'] ?? '')),
            'Modelo' => compact_text((string) ($vehicle['veh_modelo'] ?? '')),
            'Año fab.' => compact_text((string) ($vehicle['veh_anio'] ?? '')),
            'Carrocería' => title_text((string) ($vehicle['veh_carroceria'] ?? '')),
        ],
        'Medidas' => [
            'Longitud' => vehicle_measure_text($vehicle['veh_largo_mm'] ?? ''),
            'Ancho' => vehicle_measure_text($vehicle['veh_ancho_mm'] ?? ''),
            'Altura' => vehicle_measure_text($vehicle['veh_alto_mm'] ?? ''),
        ],
    ];

    $html = '<div class="vehicle-story-block">';
    $html .= '<h3 class="vehicle-story-title">' . h($title) . '</h3>';

    $sectionIndex = 1;
    foreach ($sections as $label => $rows) {
        $availableRows = array_filter($rows, static fn(string $value): bool => $value !== '' && $value !== '—');
        if ($availableRows === []) {
            continue;
        }

        $html .= '<div class="vehicle-story-section">';
        $html .= '<h4 class="vehicle-story-subtitle">' . $sectionIndex . '. ' . h($label) . '</h4>';
        $html .= '<div class="vehicle-story-list">';
        foreach ($availableRows as $rowLabel => $rowValue) {
            $html .= '<div class="vehicle-story-row">';
            $html .= '<span class="vehicle-story-key">- ' . h($rowLabel) . '</span>';
            $html .= '<span class="vehicle-story-sep">:</span>';
            $html .= '<span class="vehicle-story-value-wrap">';
            $html .= '<span class="vehicle-story-value">' . h($rowValue) . '</span>';
            $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($rowValue) . '" aria-label="Copiar valor" title="Copiar valor">⧉</button>';
            $html .= '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        $sectionIndex++;
    }

    $html .= '</div>';

    return $html;
}

function license_narrative_text(array $person, array $license): string
{
    $name = person_name_generales_ley($person);
    if ($name === '') {
        $name = person_label($person);
    }

    $number = upper_text((string) ($license['numero'] ?? ''));
    $class = upper_text((string) ($license['clase'] ?? ''));
    $category = upper_text((string) ($license['categoria'] ?? ''));
    $issuedBy = compact_text((string) ($license['expedido_por'] ?? ''));
    if ($issuedBy === '') {
        $issuedBy = 'Ministerio de Transporte y Comunicaciones';
    }

    $details = [];
    if ($number !== '') {
        $details[] = 'N° ' . $number;
    }
    if ($class !== '' || $category !== '') {
        $classCategory = [];
        if ($class !== '') {
            $classCategory[] = 'clase ' . $class;
        }
        if ($category !== '') {
            $classCategory[] = 'categoría ' . $category;
        }
        $details[] = 'en la ' . implode(' ', $classCategory);
    }

    $startsAt = fecha_generales_ley((string) ($license['vigente_desde'] ?? ''));
    $endsAt = fecha_generales_ley((string) ($license['vigente_hasta'] ?? ''));
    if ($startsAt !== '' || $endsAt !== '') {
        $validity = 'vigente';
        if ($startsAt !== '') {
            $validity .= ' desde ' . $startsAt;
        }
        if ($endsAt !== '') {
            $validity .= ' hasta el ' . $endsAt;
        }
        $details[] = $validity;
    }

    $text = 'Revisado en el sistema de licencia de conducir del MTC https://licencias-tramite.mtc.gob.pe/rmLB_Consulta.aspx, se obtuvo como información que a la consulta a la persona de ' . $name . ', “Sí” registra licencia de conducir expedida por el ' . $issuedBy;
    if ($details !== []) {
        $text .= ', ' . implode(', ', $details);
    }

    return $text . '.';
}

function render_license_narrative_block(array $person, array $licenses): string
{
    if ($licenses === []) {
        return '';
    }

    $html = '<div class="license-story-block">';
    foreach ($licenses as $index => $license) {
        $text = license_narrative_text($person, $license);
        $html .= '<div class="license-story-item">';
        $html .= '<h4 class="license-story-title">' . h(($index + 1) . ') Licencia de conducir') . '</h4>';
        $html .= '<div class="license-story-line">';
        $html .= '<p class="license-story-text">' . h($text) . '</p>';
        $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($text) . '" aria-label="Copiar licencia" title="Copiar licencia">⧉</button>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

function rml_narrative_value(?string $value): string
{
    $text = lower_text((string) ($value ?? ''));
    return $text !== '' ? $text : 'no requiere';
}

function rml_narrative_clause(?string $value, string $label): string
{
    $text = rml_narrative_value($value);
    $numeric = str_replace(',', '.', $text);
    if (is_numeric($numeric)) {
        $days = (float) $numeric;
        $dayText = abs($days - 1.0) < 0.001 ? 'día' : 'días';
        return $text . ' ' . $dayText . ' de ' . $label;
    }

    return $text . ' ' . $label;
}

function rml_narrative_text(array $rml): string
{
    $number = upper_text((string) ($rml['numero'] ?? ''));
    $atencion = rml_narrative_clause((string) ($rml['atencion_facultativo'] ?? ''), 'atención facultativa');
    $incapacidad = rml_narrative_clause((string) ($rml['incapacidad_medico'] ?? ''), 'incapacidad médico legal');
    $observaciones = compact_text((string) ($rml['observaciones'] ?? ''));

    $text = 'N° ';
    $text .= $number !== '' ? $number : '—';
    $text .= ', donde se certifica ' . $atencion . ', ' . $incapacidad;

    if ($observaciones !== '' && !in_array($observaciones, ['—', '-'], true)) {
        $text .= ', con la observación: ' . $observaciones;
    }

    return $text . '.';
}

function render_rml_narrative_block(array $rmls, int $startIndex = 3): string
{
    if ($rmls === []) {
        return '';
    }

    $html = '<div class="rml-story-block">';
    foreach ($rmls as $index => $rml) {
        $text = rml_narrative_text($rml);
        $html .= '<div class="rml-story-item">';
        $html .= '<h4 class="rml-story-title">' . h(($startIndex + $index) . ') Certificado Médico Legal') . '</h4>';
        $html .= '<div class="rml-story-line">';
        $html .= '<p class="rml-story-text">' . h($text) . '</p>';
        $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($text) . '" aria-label="Copiar certificado médico legal" title="Copiar certificado médico legal">⧉</button>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

function number_0_99_spanish(int $number): string
{
    $number = max(0, min(99, $number));
    $units = ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
    $special = [
        10 => 'diez',
        11 => 'once',
        12 => 'doce',
        13 => 'trece',
        14 => 'catorce',
        15 => 'quince',
        16 => 'dieciséis',
        17 => 'diecisiete',
        18 => 'dieciocho',
        19 => 'diecinueve',
        20 => 'veinte',
        21 => 'veintiuno',
        22 => 'veintidós',
        23 => 'veintitrés',
        24 => 'veinticuatro',
        25 => 'veinticinco',
        26 => 'veintiséis',
        27 => 'veintisiete',
        28 => 'veintiocho',
        29 => 'veintinueve',
        30 => 'treinta',
        40 => 'cuarenta',
        50 => 'cincuenta',
        60 => 'sesenta',
        70 => 'setenta',
        80 => 'ochenta',
        90 => 'noventa',
    ];

    if ($number < 10) {
        return $units[$number];
    }
    if ($number < 30) {
        return $special[$number];
    }

    $tens = (int) floor($number / 10) * 10;
    $rest = $number % 10;

    return $special[$tens] . ($rest > 0 ? ' y ' . $units[$rest] : '');
}

function dosaje_cuantitativo_text(?string $value): string
{
    $raw = str_replace(',', '.', compact_text((string) ($value ?? '')));
    if ($raw === '' || !is_numeric($raw)) {
        return '';
    }

    $quantity = round((float) $raw, 2);
    $grams = (int) floor($quantity);
    $centigrams = (int) round(($quantity - $grams) * 100);
    if ($centigrams === 100) {
        $grams++;
        $centigrams = 0;
    }

    $gramsText = number_0_99_spanish($grams) . ' ' . ($grams === 1 ? 'gramo' : 'gramos');
    $centigramsText = number_0_99_spanish($centigrams) . ' ' . ($centigrams === 1 ? 'centigramo' : 'centigramos');

    return $gramsText . ' ' . $centigramsText . ' de alcohol por litro de sangre (' . number_format($quantity, 2, '.', '') . ' g/L)';
}

function dosaje_narrative_text(array $dosaje): string
{
    $date = fecha_generales_ley((string) ($dosaje['fecha_extraccion'] ?? ''));
    $number = upper_text((string) ($dosaje['numero'] ?? ''));
    $registry = upper_text((string) ($dosaje['numero_registro'] ?? ''));
    $quantitative = dosaje_cuantitativo_text((string) ($dosaje['resultado_cuantitativo'] ?? ''));
    $qualitative = compact_text((string) ($dosaje['resultado_cualitativo'] ?? ''));

    $text = 'Fue sometido a la pericia de Dosaje Etílico, por personal médico de la unidad desconcentrada de dosaje etílico';
    if ($date !== '') {
        $text .= ', el ' . $date;
    }

    if ($number !== '') {
        $text .= ', donde expidieron el Certificado N° ' . $number;
    } else {
        $text .= ', donde expidieron el Certificado de Dosaje Etílico';
    }
    if ($registry !== '') {
        $text .= ' (registro N° ' . $registry . ')';
    }

    if ($quantitative !== '') {
        $text .= ', con el resultado ' . $quantitative;
    } elseif ($qualitative !== '') {
        $text .= ', con resultado ' . $qualitative;
    }

    return $text . '.';
}

function render_dosaje_narrative_block(array $dosajes): string
{
    if ($dosajes === []) {
        return '';
    }

    $html = '<div class="dosage-story-block">';
    foreach ($dosajes as $index => $dosaje) {
        $text = dosaje_narrative_text($dosaje);
        $html .= '<div class="dosage-story-item">';
        $html .= '<h4 class="dosage-story-title">' . h(($index + 2) . ') Dosaje etílico') . '</h4>';
        $html .= '<div class="dosage-story-line">';
        $html .= '<p class="dosage-story-text">' . h($text) . '</p>';
        $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($text) . '" aria-label="Copiar dosaje" title="Copiar dosaje">⧉</button>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

function simple_time_text(?string $value): string
{
    $value = compact_text((string) ($value ?? ''));
    if ($value === '') {
        return '';
    }

    return substr($value, 0, 5);
}

function abogado_nombre_text(array $abogado): string
{
    return trim((string) preg_replace('/\s+/u', ' ', implode(' ', array_filter([
        title_text((string) ($abogado['nombres'] ?? '')),
        upper_text((string) ($abogado['apellido_paterno'] ?? '')),
        upper_text((string) ($abogado['apellido_materno'] ?? '')),
    ], static fn(string $item): bool => $item !== ''))));
}

function manifestacion_abogado_clause(array $abogados): string
{
    if ($abogados === []) {
        return '';
    }

    $clauses = [];
    foreach ($abogados as $abogado) {
        $name = abogado_nombre_text($abogado);
        if ($name === '') {
            continue;
        }

        $parts = [$name];
        $colegiatura = upper_text((string) ($abogado['colegiatura'] ?? ''));
        $registro = upper_text((string) ($abogado['registro'] ?? ''));
        $domicilio = compact_text((string) ($abogado['domicilio_procesal'] ?? ''));
        $casilla = compact_text((string) ($abogado['casilla_electronica'] ?? ''));

        if ($colegiatura !== '') {
            $parts[] = 'con colegiatura N° ' . $colegiatura;
        }
        if ($registro !== '') {
            $parts[] = 'registro N° ' . $registro;
        }
        if ($domicilio !== '') {
            $parts[] = 'domicilio procesal en ' . $domicilio;
        }
        if ($casilla !== '') {
            $parts[] = 'casilla electrónica ' . $casilla;
        }

        $clauses[] = implode(', ', $parts);
    }

    if ($clauses === []) {
        return '';
    }

    return ' con la participación de su abogado defensor ' . implode('; ', $clauses);
}

function manifestacion_fiscal_clause(array $context): string
{
    $fiscal = compact_text((string) ($context['fiscal_nombre'] ?? ''));
    $cargo = compact_text((string) ($context['fiscal_cargo'] ?? ''));
    $fiscalia = compact_text((string) ($context['fiscalia_nombre'] ?? ''));

    if ($fiscal === '' && $fiscalia === '') {
        return '';
    }

    $clause = ' con la participación del representante del Ministerio Público';
    if ($fiscal !== '') {
        $clause .= ', ' . $fiscal;
    }
    if ($cargo !== '') {
        $clause .= ', ' . $cargo;
    }
    if ($fiscalia !== '') {
        $clause .= ' de ' . $fiscalia;
    }

    return $clause;
}

function manifestacion_narrative_text(array $manifestacion, array $abogados, array $context): string
{
    $date = fecha_generales_ley((string) ($manifestacion['fecha'] ?? ''));
    $start = simple_time_text((string) ($manifestacion['horario_inicio'] ?? ''));
    $end = simple_time_text((string) ($manifestacion['hora_termino'] ?? ''));
    $modalidad = compact_text((string) ($manifestacion['modalidad'] ?? ''));

    $text = 'Se recepcionó la manifestación';
    if ($modalidad !== '') {
        $text .= ' bajo la modalidad de ' . $modalidad;
    }
    if ($date !== '') {
        $text .= ', el ' . $date;
    }
    if ($start !== '' || $end !== '') {
        $text .= ', desde las ' . ($start !== '' ? $start : '--:--') . ' horas';
        if ($end !== '') {
            $text .= ' hasta las ' . $end . ' horas';
        }
    }

    $text .= manifestacion_abogado_clause($abogados);
    $text .= manifestacion_fiscal_clause($context);

    return $text . '.';
}

function render_manifestacion_narrative_block(array $manifestaciones, array $abogados, array $context): string
{
    if ($manifestaciones === []) {
        return '';
    }

    $html = '<div class="manifestation-story-block">';
    foreach ($manifestaciones as $index => $manifestacion) {
        $text = manifestacion_narrative_text($manifestacion, $abogados, $context);
        $html .= '<div class="manifestation-story-item">';
        $html .= '<h4 class="manifestation-story-title">' . h(($index + 3) . ') Manifestación') . '</h4>';
        $html .= '<div class="manifestation-story-line">';
        $html .= '<p class="manifestation-story-text">' . h($text) . '</p>';
        $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($text) . '" aria-label="Copiar manifestación" title="Copiar manifestación">⧉</button>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

function abogado_narrative_text(array $abogado): string
{
    $name = abogado_nombre_text($abogado);
    $parts = $name !== '' ? [$name] : [];
    $colegiatura = upper_text((string) ($abogado['colegiatura'] ?? ''));
    $registro = upper_text((string) ($abogado['registro'] ?? ''));
    $casilla = compact_text((string) ($abogado['casilla_electronica'] ?? ''));
    $domicilio = compact_text((string) ($abogado['domicilio_procesal'] ?? ''));
    $celular = compact_text((string) ($abogado['celular'] ?? ''));
    $email = compact_text((string) ($abogado['email'] ?? ''));

    if ($colegiatura !== '') {
        $parts[] = 'colegiatura N° ' . $colegiatura;
    }
    if ($registro !== '') {
        $parts[] = 'registro N° ' . $registro;
    }
    if ($casilla !== '') {
        $parts[] = 'casilla electrónica ' . $casilla;
    }
    if ($domicilio !== '') {
        $parts[] = 'domicilio procesal en ' . $domicilio;
    }
    if ($celular !== '') {
        $parts[] = 'celular ' . $celular;
    }
    if ($email !== '') {
        $parts[] = 'correo ' . $email;
    }

    if ($parts === []) {
        return 'Registra abogado defensor sin datos complementarios.';
    }

    return ($name !== '' ? 'Registra como abogado defensor a ' : 'Registra abogado defensor con ') . implode(', ', $parts) . '.';
}

function render_summary_abogado_block(array $abogados): string
{
    if ($abogados === []) {
        return '';
    }

    $html = '<div class="section-block"><h3>Abogado defensor</h3><div class="summary-doc-stack">';
    foreach ($abogados as $index => $abogado) {
        $text = abogado_narrative_text($abogado);
        $html .= '<div class="manifestation-story-item">';
        $html .= '<h4 class="manifestation-story-title">' . h(($index + 1) . ') Abogado defensor') . '</h4>';
        $html .= '<div class="manifestation-story-line">';
        $html .= '<p class="manifestation-story-text">' . h($text) . '</p>';
        $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($text) . '" aria-label="Copiar abogado" title="Copiar abogado">⧉</button>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div></div>';

    return $html;
}

function narrative_lines(?string $value): array
{
    $text = compact_text(str_replace(["\r\n", "\r"], "\n", (string) ($value ?? '')));
    if ($text === '') {
        return [];
    }

    $items = preg_split('/\n+/u', trim((string) ($value ?? ''))) ?: [];
    $clean = [];
    foreach ($items as $item) {
        $item = trim((string) preg_replace('/^[\-\x{2022}\*\s]+/u', '', (string) $item));
        if ($item !== '') {
            $clean[] = $item;
        }
    }

    return $clean;
}

function occiso_levantamiento_fiscal_clause(array $context): string
{
    $fiscal = compact_text((string) ($context['fiscal_nombre'] ?? ''));
    $cargo = compact_text((string) ($context['fiscal_cargo'] ?? ''));
    $fiscalia = compact_text((string) ($context['fiscalia_nombre'] ?? ''));

    if ($fiscal === '' && $fiscalia === '') {
        return '';
    }

    $clause = 'el representante del Ministerio Público';
    if ($fiscal !== '') {
        $clause .= ', ' . $fiscal;
    }
    if ($cargo !== '') {
        $clause .= ', ' . $cargo;
    }
    if ($fiscalia !== '') {
        $clause .= ' de ' . $fiscalia;
    }

    return $clause;
}

function occiso_levantamiento_text(array $occiso, array $context): string
{
    $date = fecha_generales_ley((string) ($occiso['fecha_levantamiento'] ?? ''));
    $time = simple_time_text((string) ($occiso['hora_levantamiento'] ?? ''));
    $place = compact_text((string) ($occiso['lugar_levantamiento'] ?? ''));
    $position = compact_text((string) ($occiso['posicion_cuerpo_levantamiento'] ?? ''));
    $legista = compact_text((string) ($occiso['legista_levantamiento'] ?? ''));
    $cmp = upper_text((string) ($occiso['cmp_legista'] ?? ''));
    $fiscal = occiso_levantamiento_fiscal_clause($context);

    $text = 'Diligencia llevada a cabo';
    if ($date !== '') {
        $text .= ' el ' . $date;
    }
    if ($time !== '') {
        $text .= ', a las ' . $time . ' horas';
    }
    if ($place !== '') {
        $text .= ' en ' . $place;
    }

    $participants = ['por el personal policial de la UIAT Norte'];
    if ($legista !== '') {
        $legistaText = 'el médico legista ' . $legista;
        if ($cmp !== '') {
            $legistaText .= ' con CMP N° ' . $cmp;
        }
        $participants[] = $legistaText;
    }
    if ($fiscal !== '') {
        $participants[] = $fiscal;
    }

    if ($participants !== []) {
        $text .= ', ' . implode(', ', array_slice($participants, 0, -1));
        $text .= count($participants) > 1 ? ' y ' . end($participants) : $participants[0];
    }
    if ($position !== '') {
        $text .= ', con el cuerpo en posición ' . $position;
    }

    return $text . '.';
}

function render_occiso_levantamiento_narrative_block(array $occisos, array $context, int $startIndex = 2): string
{
    if ($occisos === []) {
        return '';
    }

    $html = '<div class="occiso-story-block">';
    foreach ($occisos as $index => $occiso) {
        $text = occiso_levantamiento_text($occiso, $context);
        $lesiones = narrative_lines((string) ($occiso['lesiones_levantamiento'] ?? ''));
        $diagnostico = compact_text((string) ($occiso['presuntivo_levantamiento'] ?? ''));
        $copyText = $text;
        if ($lesiones !== []) {
            $copyText .= "\n\nDescripción de lesiones:\n- " . implode("\n- ", $lesiones);
        }
        if ($diagnostico !== '') {
            $copyText .= "\n\nDiagnóstico presuntivo de muerte\n" . $diagnostico;
        }

        $html .= '<div class="occiso-story-item">';
        $html .= '<h4 class="occiso-story-title">' . h(($startIndex + $index) . ') Acta de levantamiento de cadáver') . '</h4>';
        $html .= '<div class="occiso-story-line">';
        $html .= '<p class="occiso-story-text">' . h($text) . '</p>';
        $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($copyText) . '" aria-label="Copiar levantamiento de cadáver" title="Copiar levantamiento de cadáver">⧉</button>';
        $html .= '</div>';
        if ($lesiones !== []) {
            $html .= '<div class="occiso-story-section"><h5>Descripción de lesiones:</h5><ul>';
            foreach ($lesiones as $lesion) {
                $html .= '<li>' . h($lesion) . '</li>';
            }
            $html .= '</ul></div>';
        }
        if ($diagnostico !== '') {
            $html .= '<div class="occiso-story-section"><h5>Diagnóstico presuntivo de muerte</h5><p>' . h($diagnostico) . '</p></div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

function occiso_pericia_has_content(array $occiso): bool
{
    return compact_text((string) ($occiso['numero_pericial'] ?? '')) !== ''
        || compact_text((string) ($occiso['fecha_pericial'] ?? '')) !== ''
        || compact_text((string) ($occiso['observaciones_pericial'] ?? '')) !== '';
}

function occiso_pericia_text(array $occiso, array $person): string
{
    $number = upper_text((string) ($occiso['numero_pericial'] ?? ''));
    $date = fecha_generales_ley((string) ($occiso['fecha_pericial'] ?? ''));
    $observaciones = compact_text((string) ($occiso['observaciones_pericial'] ?? ''));
    $name = person_name_generales_ley($person);
    if ($name === '') {
        $name = person_label($person);
    }

    $text = '';
    if ($number !== '') {
        $text .= 'N° ' . $number;
    } else {
        $text .= 'Informe pericial';
    }
    if ($date !== '') {
        $text .= ' de fecha ' . $date;
    }

    $text .= ', expedido por la DITANFOR-Lima';
    if ($name !== '') {
        $text .= ', el cual se adjunta al presente correspondiente a Q.E.V.F. ' . $name;
    } else {
        $text .= ', el cual se adjunta al presente';
    }

    if ($observaciones !== '' && !in_array($observaciones, ['—', '-'], true)) {
        $text .= ', con la observación: ' . $observaciones;
    }

    return $text . '.';
}

function render_occiso_pericia_narrative_block(array $occisos, array $person, int $startIndex = 3): string
{
    if ($occisos === []) {
        return '';
    }

    $html = '<div class="occiso-story-block">';
    foreach ($occisos as $index => $occiso) {
        if (!occiso_pericia_has_content($occiso)) {
            continue;
        }

        $text = occiso_pericia_text($occiso, $person);
        $html .= '<div class="occiso-story-item">';
        $html .= '<h4 class="occiso-story-title">' . h(($startIndex + $index) . ') Informe pericial de recepción de cadáver') . '</h4>';
        $html .= '<div class="occiso-story-line">';
        $html .= '<p class="occiso-story-text">' . h($text) . '</p>';
        $html .= '<button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="' . h($text) . '" aria-label="Copiar informe pericial de recepción de cadáver" title="Copiar informe pericial de recepción de cadáver">⧉</button>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

function render_summary_manifestacion_block(array $manifestaciones, array $abogados, array $context): string
{
    if ($manifestaciones === []) {
        return '';
    }

    return '<div class="section-block"><h3>Manifestaciones</h3><div class="summary-doc-stack">' .
        render_manifestacion_narrative_block($manifestaciones, $abogados, $context) .
        '</div></div>';
}

function summary_records_for_person_ids(array $recordsByPersona, array $personIds): array
{
    $records = [];
    $seen = [];
    foreach ($personIds as $personId) {
        $personId = (int) $personId;
        if ($personId <= 0 || empty($recordsByPersona[$personId])) {
            continue;
        }

        foreach ($recordsByPersona[$personId] as $index => $record) {
            $key = array_key_exists('id', $record) ? (string) $record['id'] : $personId . ':' . $index;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $records[] = $record;
        }
    }

    return $records;
}

function render_participant_manifestation_section(
    array $manifestaciones,
    int $personaId,
    int $accidenteId,
    string $workbenchId,
    string $frameId,
    string $returnTo,
    string $emptyText = 'No hay manifestaciones registradas para esta persona en este accidente.',
    bool $renderLocalWorkbench = false,
    string $downloadUrl = ''
): string {
    if ($personaId <= 0) {
        return '<div class="empty-state">No hay persona vinculada para registrar manifestación.</div>';
    }

    $returnParam = urlencode($returnTo);
    $downloadParam = $downloadUrl !== '' ? '&download_url=' . urlencode($downloadUrl) : '';
    $html = '<div class="section-block">';
    $html .= '<h3>Manifestaciones</h3>';
    $html .= '<div class="record-actions" style="margin-top:0;margin-bottom:8px">';
    $html .= '<a class="btn-shell js-inline-open" href="documento_manifestacion_nuevo.php?persona_id=' . $personaId . '&accidente_id=' . $accidenteId . '&embed=1&return_to=' . $returnParam . $downloadParam . '" data-workbench="' . h($workbenchId) . '" data-frame="' . h($frameId) . '" data-title="Manifestación">+ Nueva manifestación</a>';
    if ($downloadUrl !== '') {
        $html .= '<a class="btn-shell btn-docx" href="' . h($downloadUrl) . '">DOCX</a>';
    }
    $html .= '</div>';
    if ($renderLocalWorkbench) {
        $html .= '<div class="inline-workbench" id="' . h($workbenchId) . '" hidden>';
        $html .= '<div class="inline-head">';
        $html .= '<strong>Manifestación</strong>';
        $html .= '<button type="button" class="btn-shell js-inline-close" data-workbench="' . h($workbenchId) . '" data-frame="' . h($frameId) . '">Cerrar</button>';
        $html .= '</div>';
        $html .= '<iframe class="inline-frame" id="' . h($frameId) . '" src="about:blank" loading="lazy"></iframe>';
        $html .= '</div>';
    }

    if ($manifestaciones === []) {
        $html .= '<div class="empty-state">' . h($emptyText) . '</div>';
    } else {
        $html .= '<div class="record-stack">';
        foreach ($manifestaciones as $manifestacion) {
            $title = compact_text((string) ($manifestacion['modalidad'] ?? ''));
            if ($title === '') {
                $title = 'Sin modalidad';
            }
            $start = simple_time_text((string) ($manifestacion['horario_inicio'] ?? ''));
            $end = simple_time_text((string) ($manifestacion['hora_termino'] ?? ''));
            $timeText = ($start !== '' ? $start : '--:--') . ' - ' . ($end !== '' ? $end : '--:--');

            $html .= '<article class="record-card">';
            $html .= '<h5>' . h($title . ' · ' . fecha_simple($manifestacion['fecha'] ?? null)) . '</h5>';
            $html .= '<div class="record-chipline"><span class="chip-simple">' . h($timeText) . '</span></div>';
            $html .= '<div class="record-actions">';
            $html .= '<a class="btn-shell js-inline-open" href="documento_manifestacion_editar.php?id=' . (int) ($manifestacion['id'] ?? 0) . '&embed=1&return_to=' . $returnParam . '" data-workbench="' . h($workbenchId) . '" data-frame="' . h($frameId) . '" data-title="Manifestación">Ver / Editar</a>';
            $html .= '<button type="button" class="btn-shell danger js-manifestacion-delete" data-manifestacion-id="' . (int) ($manifestacion['id'] ?? 0) . '">Eliminar X</button>';
            $html .= '</div>';
            $html .= '</article>';
        }
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

function render_summary_vehicle_block(array $vehicle, array $documents, string $title, array $vehicleFields, array $docSections): string
{
    $subtitleParts = [];
    if (!empty($vehicle['veh_participacion'])) {
        $subtitleParts[] = (string) $vehicle['veh_participacion'];
    }
    if (!empty($vehicle['veh_placa'])) {
        $subtitleParts[] = (string) $vehicle['veh_placa'];
    }

    $html = '<div class="summary-subcard" data-collapsible-card>';
    $html .= '<div class="summary-header"><div><h4>' . h($title) . '</h4>';
    $html .= '<p>' . h($subtitleParts !== [] ? implode(' · ', $subtitleParts) : 'Vehículo vinculado') . '</p></div>';
    $html .= '<div class="module-card-controls"><button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button></div></div>';
    $html .= '<div class="module-card-panel js-card-panel" hidden>';
    $html .= render_vehicle_story_block($vehicle);
    $html .= '<div class="section-block"><h3>Documentos del vehículo</h3>';
    $html .= render_vehicle_documents_story($documents);

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function render_summary_person_block(
    array $person,
    array $extras,
    string $title,
    array $personSections,
    array $lcFields,
    array $rmlFields,
    array $dosajeFields,
    array $manifestacionFields,
    array $occLevantamientoFields,
    array $occPericialFields,
    array $occProtocoloFields,
    array $occEpicrisisFields
): string {
    $html = '<article class="record-card summary-person-card" data-collapsible-card>';
    $html .= '<div class="summary-header"><div><h5 class="summary-person-name">' . h($title . ' · ' . person_label($person)) . '</h5>';
    $html .= '<p>' . h(tab_person_label($person)) . person_heading_suffix($person) . '</p></div>';
    $html .= '<div class="summary-chipline">';
    $html .= '<span class="' . h(role_chip_class((string) ($person['rol_nombre'] ?? ''))) . '">' . h((string) (($person['rol_nombre'] ?? '') !== '' ? $person['rol_nombre'] : 'Sin rol')) . '</span>';
    if ((string) ($person['lesion'] ?? '') !== '') {
        $html .= '<span class="' . h(lesion_chip_class((string) ($person['lesion'] ?? ''))) . '">' . h((string) $person['lesion']) . '</span>';
    }
    if ((string) ($person['orden_participacion'] ?? '') !== '') {
        $html .= '<span class="chip-simple">' . h((string) $person['orden_participacion']) . '</span>';
    }
    if ((string) ($person['veh_chip_text'] ?? '') !== '') {
        $html .= '<span class="chip-simple">' . h((string) $person['veh_chip_text']) . '</span>';
    }
    $html .= '<button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button>';
    $html .= '</div></div>';
    $html .= '<div class="module-card-panel js-card-panel" hidden>';
    $html .= '<div class="section-block">';
    $html .= render_persona_story_block($person, 'Generales de ley');
    $html .= '</div>';
    $html .= render_summary_abogado_block($extras['abogados'] ?? []);

    if (!empty($extras['lc'])) {
        $html .= '<div class="section-block"><h3>Licencias</h3><div class="summary-doc-stack">';
        if (is_conductor($person)) {
            $html .= render_license_narrative_block($person, $extras['lc']);
        } else {
            foreach ($extras['lc'] as $index => $row) {
                $html .= '<div class="summary-subcard"><div class="summary-header"><div><h4>' . h('Licencia #' . ($index + 1)) . '</h4></div></div>';
                $html .= '<div class="field-grid">' . render_field_cards($row, $lcFields) . '</div></div>';
            }
        }
        $html .= '</div></div>';
    }

    if (!empty($extras['dos'])) {
        $html .= '<div class="section-block"><h3>Dosajes</h3><div class="summary-doc-stack">';
        $html .= render_dosaje_narrative_block($extras['dos']);
        $html .= '</div></div>';
    }

    if (!empty($extras['rml'])) {
        $html .= '<div class="section-block"><h3>RML</h3><div class="summary-doc-stack">';
        $html .= render_rml_narrative_block($extras['rml'], 3);
        $html .= '</div></div>';
    }

    if (!empty($extras['man'])) {
        $html .= render_summary_manifestacion_block(
            $extras['man'],
            $extras['abogados'] ?? [],
            $extras['manifestacion_context'] ?? []
        );
    }

    if (!empty($extras['occ'])) {
        $html .= '<div class="section-block"><h3>Documentos de occiso</h3><div class="summary-doc-stack">';
        $html .= render_occiso_levantamiento_narrative_block($extras['occ'], $extras['manifestacion_context'] ?? [], 2);
        $html .= render_occiso_pericia_narrative_block($extras['occ'], $person, 2 + count($extras['occ']));
        foreach ($extras['occ'] as $index => $row) {
            $remainingSections = render_summary_field_sections($row, [
                'Protocolo' => $occProtocoloFields,
                'Epicrisis' => $occEpicrisisFields,
            ]);
            if ($remainingSections !== '') {
                $html .= '<div class="summary-subcard"><div class="summary-header"><div><h4>' . h('Otros documentos de occiso #' . ($index + 1)) . '</h4></div></div>';
                $html .= $remainingSections;
                $html .= '</div>';
            }
        }
        $html .= '</div></div>';
    }

    $html .= '</div>';
    $html .= '</article>';

    return $html;
}

function oficio_status_class(?string $estado): string
{
    $estado = mb_strtolower(trim((string) ($estado ?? '')), 'UTF-8');

    return match ($estado) {
        'borrador' => 'status-borrador',
        'firmado' => 'status-firmado',
        'enviado' => 'status-enviado',
        'anulado' => 'status-anulado',
        'archivado' => 'status-archivado',
        default => '',
    };
}

function document_icon_from_text(?string $text): string
{
    $haystack = mb_strtolower(trim((string) ($text ?? '')), 'UTF-8');

    if ($haystack === '') {
        return '📁';
    }
    if (str_contains($haystack, 'protocolo')) {
        return '💀';
    }
    if (str_contains($haystack, 'peritaje')) {
        return '🚗';
    }
    if (str_contains($haystack, 'camara') || str_contains($haystack, 'cámara')) {
        return '📷';
    }
    if (str_contains($haystack, 'otros documentos') || str_contains($haystack, 'otro documento')) {
        return '📁';
    }

    return '📁';
}

function oficio_icon(array $row): string
{
    return document_icon_from_text((string) (($row['asunto_nombre'] ?? '') . ' ' . ($row['asunto_detalle'] ?? '')));
}

function documento_recibido_icon(array $row): string
{
    return document_icon_from_text((string) (($row['tipo_documento'] ?? '') . ' ' . ($row['asunto'] ?? '') . ' ' . ($row['contenido'] ?? '')));
}

function render_editable_fields(array $record, array $fields, string $idPrefix = ''): string
{
    $html = '';

    foreach ($fields as $field) {
        $name = (string) ($field['name'] ?? '');
        if ($name === '') {
            continue;
        }

        $valueKey = (string) ($field['value_key'] ?? $name);
        $label = (string) ($field['label'] ?? human_label($valueKey));
        $type = (string) ($field['type'] ?? 'text');
        $class = trim('field-card edit-field ' . (string) ($field['class'] ?? ''));
        $value = (string) ($record[$valueKey] ?? ($field['value'] ?? ''));
        $inputId = $idPrefix !== '' ? $idPrefix . '-' . $name : $name;
        $attrs = [];

        if (!empty($field['required'])) {
            $attrs[] = 'required';
        }
        if (!empty($field['readonly'])) {
            $attrs[] = 'readonly';
        }
        if (!empty($field['disabled'])) {
            $attrs[] = 'disabled';
        }
        if (!empty($field['maxlength'])) {
            $attrs[] = 'maxlength="' . (int) $field['maxlength'] . '"';
        }
        if (!empty($field['rows'])) {
            $attrs[] = 'rows="' . (int) $field['rows'] . '"';
        }
        if (!empty($field['step'])) {
            $attrs[] = 'step="' . h((string) $field['step']) . '"';
        }
        if (!empty($field['inputmode'])) {
            $attrs[] = 'inputmode="' . h((string) $field['inputmode']) . '"';
        }
        if (array_key_exists('placeholder', $field)) {
            $attrs[] = 'placeholder="' . h((string) $field['placeholder']) . '"';
        }

        $html .= '<div class="' . h($class) . '">';
        $html .= '<label class="edit-label" for="' . h($inputId) . '">' . h($label) . '</label>';

        if ($type === 'textarea') {
            $html .= '<textarea class="edit-control" id="' . h($inputId) . '" name="' . h($name) . '" ' . implode(' ', $attrs) . '>' . h($value) . '</textarea>';
        } elseif ($type === 'select') {
            $html .= '<select class="edit-control" id="' . h($inputId) . '" name="' . h($name) . '" ' . implode(' ', $attrs) . '>';
            foreach ((array) ($field['options'] ?? []) as $optionValue => $optionLabel) {
                $selected = (string) $optionValue === $value ? ' selected' : '';
                $html .= '<option value="' . h((string) $optionValue) . '"' . $selected . '>' . h((string) $optionLabel) . '</option>';
            }
            $html .= '</select>';
        } else {
            $html .= '<input class="edit-control" type="' . h($type) . '" id="' . h($inputId) . '" name="' . h($name) . '" value="' . h($value) . '" ' . implode(' ', $attrs) . '>';
        }

        $html .= '</div>';
    }

    return $html;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function safe_table_exists(PDO $pdo, string $table): bool
{
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function analysis_media_table_name(): string
{
    return 'accidente_analisis_imagenes';
}

function analysis_media_sections(): array
{
    return [
        'danos' => 'danos',
        'lesiones' => 'lesiones',
    ];
}

function normalize_uploaded_files(array $fileBag): array
{
    $normalized = [];
    $names = (array) ($fileBag['name'] ?? []);
    $tmpNames = (array) ($fileBag['tmp_name'] ?? []);
    $errors = (array) ($fileBag['error'] ?? []);
    $sizes = (array) ($fileBag['size'] ?? []);
    $types = (array) ($fileBag['type'] ?? []);

    foreach ($names as $index => $name) {
        $normalized[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($tmpNames[$index] ?? ''),
            'error' => (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($sizes[$index] ?? 0),
            'type' => (string) ($types[$index] ?? ''),
        ];
    }

    return $normalized;
}

function upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_OK => '',
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño permitido.',
        UPLOAD_ERR_PARTIAL => 'La imagen no se cargó completamente.',
        UPLOAD_ERR_NO_FILE => 'No se recibió ninguna imagen.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir la imagen en disco.',
        UPLOAD_ERR_EXTENSION => 'La carga de la imagen fue detenida por una extensión de PHP.',
        default => 'No se pudo procesar la imagen subida.',
    };
}

function analysis_original_extension(string $fileName): string
{
    $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
    return preg_replace('/[^a-z0-9]/', '', $extension) ?: '';
}

function analysis_is_heic_like_upload(string $fileName, string $mimeType): bool
{
    $extension = analysis_original_extension($fileName);
    if (in_array($extension, ['heic', 'heif'], true)) {
        return true;
    }

    $mimeType = strtolower(trim($mimeType));
    return in_array($mimeType, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true);
}

function analysis_try_convert_heic_to_jpeg(string $sourcePath, string $destPath): bool
{
    $tempScript = tempnam(sys_get_temp_dir(), 'heic_convert_');
    if ($tempScript === false) {
        return false;
    }

    $scriptPath = $tempScript . '.ps1';
    @rename($tempScript, $scriptPath);

    $script = <<<'PS1'
Add-Type -AssemblyName PresentationCore
$src = $args[0]
$dst = $args[1]
$input = [System.IO.File]::OpenRead($src)
try {
    $decoder = [System.Windows.Media.Imaging.BitmapDecoder]::Create(
        $input,
        [System.Windows.Media.Imaging.BitmapCreateOptions]::PreservePixelFormat,
        [System.Windows.Media.Imaging.BitmapCacheOption]::OnLoad
    )
} finally {
    $input.Close()
}
$encoder = New-Object System.Windows.Media.Imaging.JpegBitmapEncoder
$encoder.QualityLevel = 92
$encoder.Frames.Add($decoder.Frames[0])
$output = [System.IO.File]::Open($dst, [System.IO.FileMode]::Create, [System.IO.FileAccess]::Write)
try {
    $encoder.Save($output)
} finally {
    $output.Close()
}
PS1;

    if (@file_put_contents($scriptPath, $script) === false) {
        @unlink($scriptPath);
        return false;
    }

    $command = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -File '
        . escapeshellarg($scriptPath) . ' '
        . escapeshellarg($sourcePath) . ' '
        . escapeshellarg($destPath);

    $output = [];
    $exitCode = 1;
    @exec($command, $output, $exitCode);
    @unlink($scriptPath);

    return $exitCode === 0 && is_file($destPath) && filesize($destPath) > 0;
}

function analysis_store_uploaded_images(PDO $pdo, int $accidenteId, string $section, array $uploadedFiles): int
{
    if ($accidenteId <= 0) {
        throw new InvalidArgumentException('Accidente no encontrado.');
    }

    $sections = analysis_media_sections();
    if (!isset($sections[$section])) {
        throw new InvalidArgumentException('Sección de análisis no válida.');
    }

    if (!safe_table_exists($pdo, analysis_media_table_name())) {
        throw new RuntimeException('La tabla de imágenes de análisis no existe aún. Ejecuta la migración correspondiente.');
    }

    $files = array_values(array_filter(
        normalize_uploaded_files($uploadedFiles),
        static fn(array $file): bool => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ));

    if ($files === []) {
        throw new InvalidArgumentException('Selecciona al menos una imagen para subir.');
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . analysis_media_table_name() . ' WHERE accidente_id = ? AND seccion = ?');
    $countStmt->execute([$accidenteId, $section]);
    $currentCount = (int) $countStmt->fetchColumn();
    if ($currentCount >= 5) {
        throw new InvalidArgumentException('Ya alcanzaste el máximo de 5 imágenes para esta sección.');
    }
    if ($currentCount + count($files) > 5) {
        throw new InvalidArgumentException('Solo puedes guardar hasta 5 imágenes por sección.');
    }

    $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $maxBytes = 10 * 1024 * 1024;
    $targetDir = __DIR__ . '/uploads/analisis/accidente_' . $accidenteId . '/' . $section;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('No se pudo crear la carpeta de destino para las imágenes.');
    }

    $finfo = class_exists('finfo') ? new \finfo(FILEINFO_MIME_TYPE) : null;
    $savedPaths = [];
    $inserted = 0;

    $pdo->beginTransaction();
    try {
        $insertStmt = $pdo->prepare(
            'INSERT INTO ' . analysis_media_table_name() . ' (accidente_id, seccion, sort_order, archivo_path, archivo_nombre, mime_type, file_size, creado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        foreach ($files as $offset => $file) {
            $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(upload_error_message($errorCode));
            }
            if ((int) ($file['size'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Una de las imágenes está vacía.');
            }
            if ((int) ($file['size'] ?? 0) > $maxBytes) {
                throw new InvalidArgumentException('Cada imagen debe pesar como máximo 10 MB.');
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new InvalidArgumentException('No se pudo validar una de las imágenes subidas.');
            }

            $sortOrder = $currentCount + $offset + 1;
            $safeBaseName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($file['name'] ?? 'imagen')) ?: 'imagen';
            $mimeType = '';
            if ($finfo instanceof \finfo) {
                $mimeType = strtolower((string) $finfo->file($tmpName));
            } elseif (function_exists('mime_content_type')) {
                $mimeType = strtolower((string) mime_content_type($tmpName));
            }
            $originalName = (string) ($file['name'] ?? 'imagen');

            if ($mimeType === '') {
                $mimeType = match (analysis_original_extension($originalName)) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    'heic' => 'image/heic',
                    'heif' => 'image/heif',
                    default => '',
                };
            }

            if (analysis_is_heic_like_upload($originalName, $mimeType)) {
                $fileName = sprintf('%02d_%s.jpg', $sortOrder, bin2hex(random_bytes(8)));
                $absolutePath = $targetDir . '/' . $fileName;
                $relativePath = 'uploads/analisis/accidente_' . $accidenteId . '/' . $section . '/' . $fileName;

                if (!analysis_try_convert_heic_to_jpeg($tmpName, $absolutePath)) {
                    throw new InvalidArgumentException('La imagen HEIC/HEIF no se pudo convertir automáticamente. Intenta con JPG o PNG.');
                }

                $savedPaths[] = $absolutePath;
                $insertStmt->execute([
                    $accidenteId,
                    $section,
                    $sortOrder,
                    $relativePath,
                    $safeBaseName,
                    'image/jpeg',
                    is_file($absolutePath) ? (int) filesize($absolutePath) : (int) ($file['size'] ?? 0),
                ]);
                $inserted++;
                continue;
            }

            if (!isset($allowedMimeToExt[$mimeType])) {
                throw new InvalidArgumentException('Solo se permiten imágenes JPG, PNG, WEBP, GIF o HEIC.');
            }

            $extension = $allowedMimeToExt[$mimeType];
            $fileName = sprintf('%02d_%s.%s', $sortOrder, bin2hex(random_bytes(8)), $extension);
            $absolutePath = $targetDir . '/' . $fileName;
            $relativePath = 'uploads/analisis/accidente_' . $accidenteId . '/' . $section . '/' . $fileName;

            if (!move_uploaded_file($tmpName, $absolutePath)) {
                throw new RuntimeException('No se pudo guardar una de las imágenes en disco.');
            }

            $savedPaths[] = $absolutePath;
            $insertStmt->execute([
                $accidenteId,
                $section,
                $sortOrder,
                $relativePath,
                $safeBaseName,
                $mimeType,
                (int) ($file['size'] ?? 0),
            ]);
            $inserted++;
        }

        $pdo->commit();
        return $inserted;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($savedPaths as $savedPath) {
            if (is_file($savedPath)) {
                @unlink($savedPath);
            }
        }
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'save_analysis_images') {
        try {
            $accidenteId = (int) ($_POST['accidente_id'] ?? 0);
            $section = trim((string) ($_POST['section'] ?? ''));
            $saved = analysis_store_uploaded_images($pdo, $accidenteId, $section, $_FILES['images'] ?? []);
            json_response([
                'ok' => true,
                'message' => $saved === 1 ? 'Imagen guardada correctamente.' : 'Imágenes guardadas correctamente.',
                'saved' => $saved,
            ]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_accidente_estado_inline') {
        try {
            $accidenteId = (int) ($_POST['accidente_id'] ?? 0);
            $estado = trim((string) ($_POST['estado'] ?? 'Pendiente'));
            if ($accidenteId <= 0) {
                throw new InvalidArgumentException('Accidente no encontrado.');
            }

            $acc = $accidenteRepo->accidenteById($accidenteId);
            if (!$acc) {
                throw new InvalidArgumentException('Accidente no encontrado.');
            }

            $result = $accidenteService->updateAccidente($accidenteId, [
                'registro_sidpol' => $acc['registro_sidpol'] ?? '',
                'lugar' => $acc['lugar'] ?? '',
                'referencia' => $acc['referencia'] ?? '',
                'cod_dep' => $acc['cod_dep'] ?? '',
                'cod_prov' => $acc['cod_prov'] ?? '',
                'cod_dist' => $acc['cod_dist'] ?? '',
                'comisaria_id' => $acc['comisaria_id'] ?? '',
                'fecha_accidente' => $acc['fecha_accidente'] ?? '',
                'fecha_comunicacion' => $acc['fecha_comunicacion'] ?? '',
                'fecha_intervencion' => $acc['fecha_intervencion'] ?? '',
                'comunicante_nombre' => $acc['comunicante_nombre'] ?? '',
                'comunicante_telefono' => $acc['comunicante_telefono'] ?? '',
                'comunicacion_decreto' => $acc['comunicacion_decreto'] ?? '',
                'comunicacion_oficio' => $acc['comunicacion_oficio'] ?? '',
                'comunicacion_carpeta_nro' => $acc['comunicacion_carpeta_nro'] ?? '',
                'fiscalia_id' => $acc['fiscalia_id'] ?? '',
                'fiscal_id' => $acc['fiscal_id'] ?? '',
                'nro_informe_policial' => $acc['nro_informe_policial'] ?? '',
                'sentido' => $acc['sentido'] ?? '',
                'secuencia' => $acc['secuencia'] ?? '',
                'modalidad_ids' => $accidenteRepo->modalidadIdsForAccidente($accidenteId),
                'consecuencia_ids' => $accidenteRepo->consecuenciaIdsForAccidente($accidenteId),
                'estado' => $estado,
            ]);

            json_response(['ok' => true, 'message' => 'Estado actualizado correctamente.', 'id' => $result['id']]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_accidente_inline') {
        try {
            $accidenteId = (int) ($_POST['accidente_id'] ?? 0);
            if ($accidenteId <= 0) {
                throw new InvalidArgumentException('Accidente no encontrado.');
            }

            $result = $accidenteService->updateAccidente($accidenteId, [
                'registro_sidpol' => $_POST['registro_sidpol'] ?? '',
                'lugar' => $_POST['lugar'] ?? '',
                'referencia' => $_POST['referencia'] ?? '',
                'cod_dep' => $_POST['cod_dep'] ?? '',
                'cod_prov' => $_POST['cod_prov'] ?? '',
                'cod_dist' => $_POST['cod_dist'] ?? '',
                'comisaria_id' => $_POST['comisaria_id'] ?? '',
                'fecha_accidente' => $_POST['fecha_accidente'] ?? '',
                'fecha_comunicacion' => $_POST['fecha_comunicacion'] ?? '',
                'fecha_intervencion' => $_POST['fecha_intervencion'] ?? '',
                'comunicante_nombre' => $_POST['comunicante_nombre'] ?? '',
                'comunicante_telefono' => $_POST['comunicante_telefono'] ?? '',
                'comunicacion_decreto' => $_POST['comunicacion_decreto'] ?? '',
                'comunicacion_oficio' => $_POST['comunicacion_oficio'] ?? '',
                'comunicacion_carpeta_nro' => $_POST['comunicacion_carpeta_nro'] ?? '',
                'fiscalia_id' => $_POST['fiscalia_id'] ?? '',
                'fiscal_id' => $_POST['fiscal_id'] ?? '',
                'nro_informe_policial' => $_POST['nro_informe_policial'] ?? '',
                'sentido' => $_POST['sentido'] ?? '',
                'secuencia' => $_POST['secuencia'] ?? '',
                'modalidad_ids' => $_POST['modalidad_ids'] ?? [],
                'consecuencia_ids' => $_POST['consecuencia_ids'] ?? [],
                'estado' => $_POST['estado'] ?? 'Pendiente',
            ]);

            json_response(['ok' => true, 'message' => 'Accidente actualizado correctamente.', 'id' => $result['id']]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_persona_inline') {
        try {
            $personaId = (int) ($_POST['persona_id'] ?? 0);
            if ($personaId <= 0) {
                throw new InvalidArgumentException('Persona no encontrada.');
            }

            $personaService = new PersonaService(new PersonaRepository($pdo));
            $current = $personaService->find($personaId);
            if ($current === null) {
                throw new InvalidArgumentException('Persona no encontrada.');
            }

            $payload = [];
            foreach ([
                'tipo_doc', 'num_doc', 'apellido_paterno', 'apellido_materno', 'nombres',
                'sexo', 'fecha_nacimiento', 'estado_civil', 'nacionalidad',
                'departamento_nac', 'provincia_nac', 'distrito_nac',
                'domicilio', 'domicilio_departamento', 'domicilio_provincia', 'domicilio_distrito',
                'ocupacion', 'grado_instruccion', 'nombre_padre', 'nombre_madre',
                'celular', 'email', 'notas', 'foto_path', 'api_fuente', 'api_ref',
            ] as $field) {
                $payload[$field] = array_key_exists($field, $_POST) ? $_POST[$field] : ($current[$field] ?? '');
            }
            $payload['tipo_doc'] = $payload['tipo_doc'] !== '' ? $payload['tipo_doc'] : 'DNI';
            $payload['nacionalidad'] = $payload['nacionalidad'] !== '' ? $payload['nacionalidad'] : 'PERUANA';

            $personaService->update($personaId, $payload);
            json_response(['ok' => true, 'message' => 'Persona actualizada correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_participacion_inline') {
        try {
            $involucradoId = (int) ($_POST['involucrado_id'] ?? 0);
            if ($involucradoId <= 0) {
                throw new InvalidArgumentException('Participación no encontrada.');
            }

            $involucradoRepo = new InvolucradoPersonaRepository($pdo);
            $registro = $involucradoRepo->involucradoById($involucradoId);
            if ($registro === null) {
                throw new InvalidArgumentException('Participación no encontrada.');
            }

            $involucradoService = new InvolucradoPersonaService($involucradoRepo);
            $involucradoService->actualizar($involucradoId, [
                'persona_id' => (int) ($registro['persona_id'] ?? 0),
                'rol_id' => (int) ($_POST['rol_id'] ?? ($registro['rol_id'] ?? 0)),
                'vehiculo_id' => ($_POST['vehiculo_id'] ?? '') === '' ? null : (int) $_POST['vehiculo_id'],
                'lesion' => $_POST['lesion'] ?? ($registro['lesion'] ?? ''),
                'observaciones' => $_POST['observaciones'] ?? ($registro['observaciones'] ?? ''),
                'orden_persona' => strtoupper(trim((string) ($_POST['orden_persona'] ?? ($registro['orden_persona'] ?? '')))),
                'dni' => $registro['num_doc'] ?? '',
            ]);

            json_response(['ok' => true, 'message' => 'Participación actualizada correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_policial_inline') {
        try {
            $policialId = (int) ($_POST['policial_id'] ?? 0);
            if ($policialId <= 0) {
                throw new InvalidArgumentException('Registro policial no encontrado.');
            }

            $policialService = new PolicialIntervinienteService(new PolicialIntervinienteRepository($pdo));
            $personaService = new PersonaService(new PersonaRepository($pdo));

            $registro = $policialService->detalle($policialId);
            if ($registro === null) {
                throw new InvalidArgumentException('Registro policial no encontrado.');
            }

            $personaId = (int) ($registro['persona_id'] ?? 0);
            if ($personaId <= 0) {
                throw new InvalidArgumentException('Persona no encontrada.');
            }

            $current = $personaService->find($personaId);
            if ($current === null) {
                throw new InvalidArgumentException('Persona no encontrada.');
            }

            $personaPayload = [
                'tipo_doc' => $_POST['tipo_doc'] ?? 'DNI',
                'num_doc' => $_POST['num_doc'] ?? '',
                'apellido_paterno' => $_POST['apellido_paterno'] ?? '',
                'apellido_materno' => $_POST['apellido_materno'] ?? '',
                'nombres' => $_POST['nombres'] ?? '',
                'sexo' => $_POST['sexo'] ?? '',
                'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
                'estado_civil' => $_POST['estado_civil'] ?? '',
                'nacionalidad' => $_POST['nacionalidad'] ?? '',
                'departamento_nac' => $_POST['departamento_nac'] ?? '',
                'provincia_nac' => $_POST['provincia_nac'] ?? '',
                'distrito_nac' => $_POST['distrito_nac'] ?? '',
                'domicilio' => $_POST['domicilio'] ?? '',
                'domicilio_departamento' => $_POST['domicilio_departamento'] ?? '',
                'domicilio_provincia' => $_POST['domicilio_provincia'] ?? '',
                'domicilio_distrito' => $_POST['domicilio_distrito'] ?? '',
                'ocupacion' => $_POST['ocupacion'] ?? '',
                'grado_instruccion' => $_POST['grado_instruccion'] ?? '',
                'nombre_padre' => $_POST['nombre_padre'] ?? '',
                'nombre_madre' => $_POST['nombre_madre'] ?? '',
                'celular' => $_POST['celular'] ?? '',
                'email' => $_POST['email'] ?? '',
                'notas' => $_POST['notas'] ?? '',
                'foto_path' => $_POST['foto_path'] ?? ($current['foto_path'] ?? ''),
                'api_fuente' => $_POST['api_fuente'] ?? ($current['api_fuente'] ?? ''),
                'api_ref' => $_POST['api_ref'] ?? ($current['api_ref'] ?? ''),
            ];
            $personaService->update($personaId, $personaPayload);

            $policialService->update($policialId, [
                'accidente_id' => (int) ($registro['accidente_id'] ?? 0),
                'persona_id' => $personaId,
                'grado_policial' => $_POST['grado_policial'] ?? '',
                'cip' => $_POST['cip'] ?? '',
                'dependencia_policial' => $_POST['dependencia_policial'] ?? '',
                'rol_funcion' => $_POST['rol_funcion'] ?? '',
                'observaciones' => $_POST['observaciones'] ?? '',
                'celular' => $_POST['celular'] ?? '',
                'email' => $_POST['email'] ?? '',
            ]);

            json_response(['ok' => true, 'message' => 'Efectivo policial actualizado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_policial_record_inline') {
        try {
            $policialId = (int) ($_POST['policial_id'] ?? 0);
            if ($policialId <= 0) {
                throw new InvalidArgumentException('Registro policial no encontrado.');
            }

            $policialService = new PolicialIntervinienteService(new PolicialIntervinienteRepository($pdo));
            $registro = $policialService->detalle($policialId);
            if ($registro === null) {
                throw new InvalidArgumentException('Registro policial no encontrado.');
            }

            $policialService->update($policialId, [
                'accidente_id' => (int) ($registro['accidente_id'] ?? 0),
                'persona_id' => (int) ($registro['persona_id'] ?? 0),
                'grado_policial' => $_POST['grado_policial'] ?? '',
                'cip' => $_POST['cip'] ?? '',
                'dependencia_policial' => $_POST['dependencia_policial'] ?? '',
                'rol_funcion' => $_POST['rol_funcion'] ?? '',
                'observaciones' => $_POST['observaciones'] ?? '',
            ]);

            json_response(['ok' => true, 'message' => 'Registro policial actualizado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'delete_manifestacion_inline') {
        try {
            $manifestacionId = (int) ($_POST['manifestacion_id'] ?? 0);
            if ($manifestacionId <= 0) {
                throw new InvalidArgumentException('Manifestación no encontrada.');
            }

            $manifestacionService = new DocumentoManifestacionService(new DocumentoManifestacionRepository($pdo));
            $manifestacionService->eliminar($manifestacionId);

            json_response(['ok' => true, 'message' => 'Manifestación eliminada correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_vehiculo_inline') {
        try {
            $vehiculoId = (int) ($_POST['vehiculo_id'] ?? 0);
            if ($vehiculoId <= 0) {
                throw new InvalidArgumentException('Vehículo no encontrado.');
            }

            $vehiculoService = new VehiculoService(new VehiculoRepository($pdo));
            $vehiculoService->actualizar($vehiculoId, $_POST);
            json_response(['ok' => true, 'message' => 'Vehículo actualizado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_propietario_inline') {
        try {
            $propietarioId = (int) ($_POST['propietario_id'] ?? 0);
            if ($propietarioId <= 0) {
                throw new InvalidArgumentException('Propietario no encontrado.');
            }

            $propietarioService = new PropietarioVehiculoService(new PropietarioVehiculoRepository($pdo));
            $personaService = new PersonaService(new PersonaRepository($pdo));

            $registro = $propietarioService->detalle($propietarioId);
            if ($registro === null) {
                throw new InvalidArgumentException('Propietario no encontrado.');
            }

            $tipo = mb_strtoupper(trim((string) ($registro['tipo_propietario'] ?? 'NATURAL')), 'UTF-8');
            $personaId = $tipo === 'JURIDICA'
                ? (int) ($registro['representante_persona_id'] ?? 0)
                : (int) ($registro['propietario_persona_id'] ?? 0);

            if ($personaId > 0) {
                $current = $personaService->find($personaId);
                if ($current === null) {
                    throw new InvalidArgumentException('Persona no encontrada.');
                }

                $personaPayload = [
                    'tipo_doc' => $_POST['tipo_doc'] ?? 'DNI',
                    'num_doc' => $_POST['num_doc'] ?? '',
                    'apellido_paterno' => $_POST['apellido_paterno'] ?? '',
                    'apellido_materno' => $_POST['apellido_materno'] ?? '',
                    'nombres' => $_POST['nombres'] ?? '',
                    'sexo' => $_POST['sexo'] ?? '',
                    'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
                    'estado_civil' => $_POST['estado_civil'] ?? '',
                    'nacionalidad' => $_POST['nacionalidad'] ?? '',
                    'departamento_nac' => $_POST['departamento_nac'] ?? '',
                    'provincia_nac' => $_POST['provincia_nac'] ?? '',
                    'distrito_nac' => $_POST['distrito_nac'] ?? '',
                    'domicilio' => $_POST['domicilio'] ?? '',
                    'domicilio_departamento' => $_POST['domicilio_departamento'] ?? '',
                    'domicilio_provincia' => $_POST['domicilio_provincia'] ?? '',
                    'domicilio_distrito' => $_POST['domicilio_distrito'] ?? '',
                    'ocupacion' => $_POST['ocupacion'] ?? '',
                    'grado_instruccion' => $_POST['grado_instruccion'] ?? '',
                    'nombre_padre' => $_POST['nombre_padre'] ?? '',
                    'nombre_madre' => $_POST['nombre_madre'] ?? '',
                    'celular' => $_POST['celular'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'notas' => $_POST['notas'] ?? '',
                    'foto_path' => $_POST['foto_path'] ?? ($current['foto_path'] ?? ''),
                    'api_fuente' => $_POST['api_fuente'] ?? ($current['api_fuente'] ?? ''),
                    'api_ref' => $_POST['api_ref'] ?? ($current['api_ref'] ?? ''),
                ];
                $personaService->update($personaId, $personaPayload);
            }

            $propietarioService->update($propietarioId, [
                'accidente_id' => (int) ($registro['accidente_id'] ?? 0),
                'vehiculo_inv_id' => (int) ($_POST['vehiculo_inv_id'] ?? ($registro['vehiculo_inv_id'] ?? 0)),
                'tipo_propietario' => $tipo,
                'propietario_persona_id' => (int) ($registro['propietario_persona_id'] ?? 0),
                'representante_persona_id' => (int) ($registro['representante_persona_id'] ?? 0),
                'ruc' => $_POST['ruc'] ?? ($registro['ruc'] ?? ''),
                'razon_social' => $_POST['razon_social'] ?? ($registro['razon_social'] ?? ''),
                'domicilio_fiscal' => $_POST['domicilio_fiscal'] ?? ($registro['domicilio_fiscal'] ?? ''),
                'rol_legal' => $_POST['rol_legal'] ?? ($registro['rol_legal'] ?? ''),
                'observaciones' => $_POST['observaciones'] ?? ($registro['observaciones'] ?? ''),
                'celular_nat' => $tipo === 'NATURAL' ? ($_POST['celular'] ?? '') : '',
                'email_nat' => $tipo === 'NATURAL' ? ($_POST['email'] ?? '') : '',
                'celular_rep' => $tipo === 'JURIDICA' ? ($_POST['celular'] ?? '') : '',
                'email_rep' => $tipo === 'JURIDICA' ? ($_POST['email'] ?? '') : '',
            ]);

            json_response(['ok' => true, 'message' => 'Propietario actualizado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_propietario_record_inline') {
        try {
            $propietarioId = (int) ($_POST['propietario_id'] ?? 0);
            if ($propietarioId <= 0) {
                throw new InvalidArgumentException('Propietario no encontrado.');
            }

            $propietarioService = new PropietarioVehiculoService(new PropietarioVehiculoRepository($pdo));
            $registro = $propietarioService->detalle($propietarioId);
            if ($registro === null) {
                throw new InvalidArgumentException('Propietario no encontrado.');
            }

            $tipo = mb_strtoupper(trim((string) ($registro['tipo_propietario'] ?? 'NATURAL')), 'UTF-8');
            $propietarioService->update($propietarioId, [
                'accidente_id' => (int) ($registro['accidente_id'] ?? 0),
                'vehiculo_inv_id' => (int) ($_POST['vehiculo_inv_id'] ?? ($registro['vehiculo_inv_id'] ?? 0)),
                'tipo_propietario' => $tipo,
                'propietario_persona_id' => (int) ($registro['propietario_persona_id'] ?? 0),
                'representante_persona_id' => (int) ($registro['representante_persona_id'] ?? 0),
                'ruc' => $_POST['ruc'] ?? ($registro['ruc'] ?? ''),
                'razon_social' => $_POST['razon_social'] ?? ($registro['razon_social'] ?? ''),
                'domicilio_fiscal' => $_POST['domicilio_fiscal'] ?? ($registro['domicilio_fiscal'] ?? ''),
                'rol_legal' => $_POST['rol_legal'] ?? ($registro['rol_legal'] ?? ''),
                'observaciones' => $_POST['observaciones'] ?? ($registro['observaciones'] ?? ''),
                'celular_nat' => $registro['cel_nat'] ?? '',
                'email_nat' => $registro['em_nat'] ?? '',
                'celular_rep' => $registro['cel_rep'] ?? '',
                'email_rep' => $registro['em_rep'] ?? '',
            ]);

            json_response(['ok' => true, 'message' => 'Registro propietario actualizado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_abogado_inline') {
        try {
            $abogadoId = (int) ($_POST['abogado_id'] ?? 0);
            if ($abogadoId <= 0) {
                throw new InvalidArgumentException('Abogado no encontrado.');
            }

            $abogadoService = new AbogadoService(new AbogadoRepository($pdo));
            $registro = $abogadoService->detalle($abogadoId);
            if ($registro === null) {
                throw new InvalidArgumentException('Abogado no encontrado.');
            }

            $abogadoService->update($abogadoId, [
                'accidente_id' => (int) ($registro['accidente_id'] ?? 0),
                'persona_id' => $_POST['persona_id'] ?? ($registro['persona_id'] ?? 0),
                'nombres' => $_POST['nombres'] ?? '',
                'apellido_paterno' => $_POST['apellido_paterno'] ?? '',
                'apellido_materno' => $_POST['apellido_materno'] ?? '',
                'colegiatura' => $_POST['colegiatura'] ?? '',
                'registro' => $_POST['registro'] ?? '',
                'casilla_electronica' => $_POST['casilla_electronica'] ?? '',
                'domicilio_procesal' => $_POST['domicilio_procesal'] ?? '',
                'celular' => $_POST['celular'] ?? '',
                'email' => $_POST['email'] ?? '',
            ]);

            json_response(['ok' => true, 'message' => 'Abogado actualizado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_itp_inline') {
        try {
            $itpId = (int) ($_POST['itp_id'] ?? 0);
            if ($itpId <= 0) {
                throw new InvalidArgumentException('ITP no encontrado.');
            }

            $itpService = new ItpService(new ItpRepository($pdo));
            $registro = $itpService->detalle($itpId);
            if ($registro === null) {
                throw new InvalidArgumentException('ITP no encontrado.');
            }

            $via2Flag = (int) ($_POST['via2_flag'] ?? 0) === 1 ? 1 : 0;

            $itpService->update($itpId, [
                'accidente_id' => (int) ($registro['accidente_id'] ?? 0),
                'fecha_itp' => $_POST['fecha_itp'] ?? '',
                'hora_itp' => $_POST['hora_itp'] ?? '',
                'ocurrencia_policial' => $_POST['ocurrencia_policial'] ?? '',
                'llegada_lugar' => $_POST['llegada_lugar'] ?? '',
                'localizacion_unidades' => $_POST['localizacion_unidades'] ?? '',
                'forma_via' => $_POST['forma_via'] ?? '',
                'punto_referencia' => $_POST['punto_referencia'] ?? '',
                'ubicacion_gps' => $_POST['ubicacion_gps'] ?? '',
                'descripcion_via1' => $_POST['descripcion_via1'] ?? '',
                'configuracion_via1' => $_POST['configuracion_via1'] ?? '',
                'material_via1' => $_POST['material_via1'] ?? '',
                'senializacion_via1' => $_POST['senializacion_via1'] ?? '',
                'ordenamiento_via1' => $_POST['ordenamiento_via1'] ?? '',
                'iluminacion_via1' => $_POST['iluminacion_via1'] ?? '',
                'visibilidad_via1' => $_POST['visibilidad_via1'] ?? '',
                'intensidad_via1' => $_POST['intensidad_via1'] ?? '',
                'fluidez_via1' => $_POST['fluidez_via1'] ?? '',
                'medidas_via1' => $_POST['medidas_via1'] ?? '',
                'observaciones_via1' => $_POST['observaciones_via1'] ?? '',
                'via2_flag' => $via2Flag,
                'descripcion_via2' => $_POST['descripcion_via2'] ?? '',
                'configuracion_via2' => $_POST['configuracion_via2'] ?? '',
                'material_via2' => $_POST['material_via2'] ?? '',
                'senializacion_via2' => $_POST['senializacion_via2'] ?? '',
                'ordenamiento_via2' => $_POST['ordenamiento_via2'] ?? '',
                'iluminacion_via2' => $_POST['iluminacion_via2'] ?? '',
                'visibilidad_via2' => $_POST['visibilidad_via2'] ?? '',
                'intensidad_via2' => $_POST['intensidad_via2'] ?? '',
                'fluidez_via2' => $_POST['fluidez_via2'] ?? '',
                'medidas_via2' => $_POST['medidas_via2'] ?? '',
                'observaciones_via2' => $_POST['observaciones_via2'] ?? '',
                'evidencia_biologica' => $_POST['evidencia_biologica'] ?? '',
                'evidencia_fisica' => $_POST['evidencia_fisica'] ?? '',
                'evidencia_material' => $_POST['evidencia_material'] ?? '',
            ]);

            json_response(['ok' => true, 'message' => 'ITP actualizado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_diligencia_inline') {
        try {
            $diligenciaId = (int) ($_POST['diligencia_id'] ?? 0);
            if ($diligenciaId <= 0) {
                throw new InvalidArgumentException('Diligencia no encontrada.');
            }

            $diligenciaService = new DiligenciaPendienteService(new DiligenciaPendienteRepository($pdo));
            $detail = $diligenciaService->detalle($diligenciaId);
            if ($detail === null) {
                throw new InvalidArgumentException('Diligencia no encontrada.');
            }

            $current = $detail['row'] ?? [];
            $diligenciaService->actualizar($diligenciaId, [
                'accidente_id' => (int) ($current['accidente_id'] ?? 0),
                'tipo_diligencia_id' => (int) ($_POST['tipo_diligencia_id'] ?? ($current['tipo_diligencia_id'] ?? 0)),
                'contenido' => $_POST['contenido'] ?? '',
                'estado' => $_POST['estado'] ?? ($current['estado'] ?? 'Pendiente'),
                'oficio_id' => $_POST['oficio_id'] ?? ($current['oficio_id'] ?? ''),
                'citacion_id' => !empty($current['citacion_id']) ? [(int) $current['citacion_id']] : [],
                'documento_realizado' => $_POST['documento_realizado'] ?? '',
                'documentos_recibidos' => $_POST['documentos_recibidos'] ?? '',
            ]);

            json_response(['ok' => true, 'message' => 'Diligencia actualizada correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_diligencia_estado_inline') {
        try {
            $diligenciaId = (int) ($_POST['diligencia_id'] ?? 0);
            if ($diligenciaId <= 0) {
                throw new InvalidArgumentException('Diligencia no encontrada.');
            }

            $estadoUi = trim((string) ($_POST['estado'] ?? 'Pendiente'));
            $estadoReal = $estadoUi === 'Resuelto' ? 'Realizado' : 'Pendiente';

            $diligenciaService = new DiligenciaPendienteService(new DiligenciaPendienteRepository($pdo));
            $diligenciaService->cambiarEstado($diligenciaId, $estadoReal);

            json_response(['ok' => true, 'message' => 'Estado de la diligencia actualizado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    if ($action === 'save_oficio_estado_inline') {
        try {
            $oficioId = (int) ($_POST['oficio_id'] ?? 0);
            $estado = trim((string) ($_POST['estado'] ?? 'BORRADOR'));
            if ($oficioId <= 0) {
                throw new InvalidArgumentException('Oficio no encontrado.');
            }

            $oficioService->changeEstado($oficioId, $estado);
            json_response(['ok' => true, 'message' => 'Estado del oficio actualizado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}

if (isset($_GET['ajax'])) {
    $ajax = trim((string) $_GET['ajax']);

    if ($ajax === 'prov') {
        $dep = substr((string) ($_GET['dep'] ?? ''), 0, 2);
        json_response(['ok' => true, 'data' => $accidenteRepo->provinciasByDepartamento($dep)]);
    }

    if ($ajax === 'dist') {
        $dep = substr((string) ($_GET['dep'] ?? ''), 0, 2);
        $prov = substr((string) ($_GET['prov'] ?? ''), 0, 2);
        json_response(['ok' => true, 'data' => $accidenteRepo->distritos($dep, $prov)]);
    }

    if ($ajax === 'comisarias_dist') {
        $dep = substr((string) ($_GET['dep'] ?? ''), 0, 2);
        $prov = substr((string) ($_GET['prov'] ?? ''), 0, 2);
        $dist = substr((string) ($_GET['dist'] ?? ''), 0, 2);
        json_response(['ok' => true, 'data' => $accidenteRepo->comisariasByDistrito($dep, $prov, $dist)]);
    }

    if ($ajax === 'fiscales') {
        $fiscaliaId = (int) ($_GET['fiscalia_id'] ?? 0);
        json_response(['ok' => true, 'data' => $accidenteRepo->fiscalesByFiscalia($fiscaliaId)]);
    }

    if ($ajax === 'fiscal_info') {
        $fiscalId = (int) ($_GET['fiscal_id'] ?? 0);
        json_response(['ok' => true, 'data' => $accidenteRepo->fiscalTelefono($fiscalId)]);
    }
}

$paramSidpol = trim((string) ($_GET['sidpol'] ?? ''));
$paramId = (int) ($_GET['accidente_id'] ?? 0);

$accidente = null;
if ($paramSidpol !== '') {
    $accidente = safe_query_one($pdo, "SELECT * FROM accidentes WHERE sidpol = ? LIMIT 1", [$paramSidpol]);
}
if (!$accidente && $paramId > 0) {
    $accidente = safe_query_one($pdo, "SELECT * FROM accidentes WHERE id = ? LIMIT 1", [$paramId]);
}
if (!$accidente) {
    $accidente = safe_query_one($pdo, "SELECT * FROM accidentes ORDER BY id DESC LIMIT 1");
}
if (!$accidente) {
    exit('No hay accidentes registrados.');
}

$accidente_id = (int) $accidente['id'];
$abogadoInlineService = new AbogadoService(new AbogadoRepository($pdo));
$abogadoInlineContext = $abogadoInlineService->formContext($accidente_id);
$accidenteBase = $accidenteRepo->accidenteById($accidente_id) ?: $accidente;
$deps = $accidenteRepo->departamentos();
$fiscaliasCatalog = $accidenteRepo->fiscalias();
$modalidadesCatalog = $accidenteRepo->modalidades();
$consecuenciasCatalog = $accidenteRepo->consecuencias();
$modSel = $accidenteRepo->modalidadIdsForAccidente($accidente_id);
$conSel = $accidenteRepo->consecuenciaIdsForAccidente($accidente_id);

$provs = !empty($accidenteBase['cod_dep']) ? $accidenteRepo->provinciasByDepartamento((string) $accidenteBase['cod_dep']) : [];
$dists = (!empty($accidenteBase['cod_dep']) && !empty($accidenteBase['cod_prov']))
    ? $accidenteRepo->distritos((string) $accidenteBase['cod_dep'], (string) $accidenteBase['cod_prov'])
    : [];
$comis = (!empty($accidenteBase['cod_dep']) && !empty($accidenteBase['cod_prov']) && !empty($accidenteBase['cod_dist']))
    ? $accidenteRepo->comisariasByDistrito((string) $accidenteBase['cod_dep'], (string) $accidenteBase['cod_prov'], (string) $accidenteBase['cod_dist'])
    : [];
$comisIds = array_map(static fn(array $x): int => (int) $x['id'], $comis);
if (!empty($accidenteBase['comisaria_id']) && !in_array((int) $accidenteBase['comisaria_id'], $comisIds, true)) {
    $row = $accidenteRepo->comisariaById((int) $accidenteBase['comisaria_id']);
    if ($row) {
        $row['_fuera'] = 1;
        array_unshift($comis, $row);
    }
}
$fiscalesDeFiscalia = !empty($accidenteBase['fiscalia_id']) ? $accidenteRepo->fiscalesByFiscalia((int) $accidenteBase['fiscalia_id']) : [];
$fiscalTelData = !empty($accidenteBase['fiscal_id']) ? $accidenteRepo->fiscalTelefono((int) $accidenteBase['fiscal_id']) : [];

$fiscalCargoSelect = safe_column_exists($pdo, 'fiscales', 'cargo') ? 'fi.cargo' : "''";
$accidenteInfo = safe_query_one(
    $pdo,
    "SELECT a.*,
            d.nombre AS dep_nom,
            p.nombre AS prov_nom,
            t.nombre AS dist_nom,
            c.nombre AS comisaria_nom,
            fa.nombre AS fiscalia_nom,
            CONCAT(fi.nombres,' ',fi.apellido_paterno,' ',fi.apellido_materno) AS fiscal_nom,
            {$fiscalCargoSelect} AS fiscal_cargo
       FROM accidentes a
  LEFT JOIN ubigeo_departamento d ON d.cod_dep = a.cod_dep
  LEFT JOIN ubigeo_provincia p ON p.cod_dep = a.cod_dep AND p.cod_prov = a.cod_prov
  LEFT JOIN ubigeo_distrito t ON t.cod_dep = a.cod_dep AND t.cod_prov = a.cod_prov AND t.cod_dist = a.cod_dist
  LEFT JOIN comisarias c ON c.id = a.comisaria_id
  LEFT JOIN fiscalia fa ON fa.id = a.fiscalia_id
  LEFT JOIN fiscales fi ON fi.id = a.fiscal_id
      WHERE a.id = ?
      LIMIT 1",
    [$accidente_id]
);
$A = $accidenteInfo ?: $accidente;

$ubicacion = implode(' / ', array_values(array_filter([
    $A['dep_nom'] ?? '',
    $A['prov_nom'] ?? '',
    $A['dist_nom'] ?? '',
])));

$rowsMods = safe_query_all(
    $pdo,
    "SELECT m.nombre
       FROM accidente_modalidad am
       JOIN modalidad_accidente m ON m.id = am.modalidad_id
      WHERE am.accidente_id = ?
   ORDER BY am.id",
    [$accidente_id]
);
if ($rowsMods === []) {
    $rowsMods = safe_query_all(
        $pdo,
        "SELECT m.nombre
           FROM accidente_modalidad am
           JOIN modalidad_accidente m ON m.id = am.modalidad_id
          WHERE am.accidente_id = ?
       ORDER BY am.modalidad_id",
        [$accidente_id]
    );
}
$rowsCons = safe_query_all(
    $pdo,
    "SELECT c.nombre
       FROM accidente_consecuencia ac
       JOIN consecuencia_accidente c ON c.id = ac.consecuencia_id
      WHERE ac.accidente_id = ?
   ORDER BY ac.id",
    [$accidente_id]
);
if ($rowsCons === []) {
    $rowsCons = safe_query_all(
        $pdo,
        "SELECT c.nombre
           FROM accidente_consecuencia ac
           JOIN consecuencia_accidente c ON c.id = ac.consecuencia_id
          WHERE ac.accidente_id = ?
       ORDER BY ac.consecuencia_id",
        [$accidente_id]
    );
}

$modalidades = array_column($rowsMods, 'nombre');
$consecuencias = array_column($rowsCons, 'nombre');
$modsConcat = join_con_y($modalidades);
$consConcat = join_con_y($consecuencias);

$personas = safe_query_all(
    $pdo,
    "SELECT
            ip.id AS involucrado_id,
            ip.accidente_id,
            ip.persona_id,
            ip.rol_id,
            ip.orden_persona,
            ip.vehiculo_id,
            ip.lesion,
            ip.observaciones AS involucrado_observaciones,
            ip.creado_en AS involucrado_creado_en,
            ip.actualizado_en AS involucrado_actualizado_en,
            p.*,
            COALESCE(pp.Nombre, '') AS rol_nombre,
            COALESCE(pp.RequiereVehiculo, 0) AS rol_requiere_vehiculo,
            COALESCE(pp.Orden, 999) AS rol_orden,
            iv.id AS inv_vehiculo_id,
            iv.orden_participacion,
            iv.tipo AS veh_participacion,
            iv.observaciones AS inv_vehiculo_observaciones,
            v.id AS veh_id,
            v.categoria_id AS veh_categoria_id,
            v.tipo_id AS veh_tipo_id,
            v.carroceria_id AS veh_carroceria_id,
            v.marca_id AS veh_marca_id,
            v.modelo_id AS veh_modelo_id,
            v.placa AS veh_placa,
            v.serie_vin AS veh_serie_vin,
            v.nro_motor AS veh_nro_motor,
            TRIM(CONCAT_WS(' - ', cv.codigo, cv.descripcion)) AS veh_categoria,
            TRIM(CONCAT_WS(' - ', tv.codigo, tv.nombre)) AS veh_tipo,
            COALESCE(car.nombre, '') AS veh_carroceria,
            COALESCE(mar.nombre, '') AS veh_marca,
            COALESCE(modv.nombre, '') AS veh_modelo,
            v.anio AS veh_anio,
            v.color AS veh_color,
            v.largo_mm AS veh_largo_mm,
            v.ancho_mm AS veh_ancho_mm,
            v.alto_mm AS veh_alto_mm,
            v.notas AS veh_notas,
            v.creado_en AS veh_creado_en,
            v.actualizado_en AS veh_actualizado_en
       FROM involucrados_personas ip
       JOIN personas p ON p.id = ip.persona_id
  LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id
  LEFT JOIN involucrados_vehiculos iv ON iv.accidente_id = ip.accidente_id AND iv.vehiculo_id = ip.vehiculo_id
  LEFT JOIN vehiculos v ON v.id = ip.vehiculo_id
  LEFT JOIN categoria_vehiculos cv ON cv.id = v.categoria_id
  LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
  LEFT JOIN carroceria_vehiculo car ON car.id = v.carroceria_id
  LEFT JOIN marcas_vehiculo mar ON mar.id = v.marca_id
  LEFT JOIN modelos_vehiculo modv ON modv.id = v.modelo_id
      WHERE ip.accidente_id = ?
   ORDER BY
            CASE COALESCE(iv.orden_participacion, '')
                WHEN 'UT-1' THEN 1
                WHEN 'UT-2' THEN 2
                WHEN 'UT-3' THEN 3
                WHEN 'UT-4' THEN 4
                WHEN 'UT-5' THEN 5
                WHEN 'UT-6' THEN 6
                WHEN 'UT-7' THEN 7
                ELSE 99
            END,
            CASE WHEN COALESCE(ip.vehiculo_id, 0) > 0 THEN 0 ELSE 1 END,
            COALESCE(pp.Orden, 999),
            COALESCE(ip.orden_persona, 'Z'),
            p.apellido_paterno,
            p.apellido_materno,
            p.nombres",
    [$accidente_id]
);

$comboVehiculosRows = safe_query_all(
    $pdo,
    "SELECT
            iv.id AS inv_vehiculo_id,
            iv.accidente_id,
            iv.orden_participacion,
            iv.tipo AS veh_participacion,
            v.id AS veh_id,
            v.categoria_id AS veh_categoria_id,
            v.tipo_id AS veh_tipo_id,
            v.carroceria_id AS veh_carroceria_id,
            v.marca_id AS veh_marca_id,
            v.modelo_id AS veh_modelo_id,
            v.placa AS veh_placa,
            v.serie_vin AS veh_serie_vin,
            v.nro_motor AS veh_nro_motor,
            TRIM(CONCAT_WS(' - ', cv.codigo, cv.descripcion)) AS veh_categoria,
            TRIM(CONCAT_WS(' - ', tv.codigo, tv.nombre)) AS veh_tipo,
            COALESCE(car.nombre, '') AS veh_carroceria,
            COALESCE(mar.nombre, '') AS veh_marca,
            COALESCE(modv.nombre, '') AS veh_modelo,
            v.anio AS veh_anio,
            v.color AS veh_color,
            v.largo_mm AS veh_largo_mm,
            v.ancho_mm AS veh_ancho_mm,
            v.alto_mm AS veh_alto_mm,
            v.notas AS veh_notas,
            v.creado_en AS veh_creado_en,
            v.actualizado_en AS veh_actualizado_en
       FROM involucrados_vehiculos iv
       JOIN vehiculos v ON v.id = iv.vehiculo_id
  LEFT JOIN categoria_vehiculos cv ON cv.id = v.categoria_id
  LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
  LEFT JOIN carroceria_vehiculo car ON car.id = v.carroceria_id
  LEFT JOIN marcas_vehiculo mar ON mar.id = v.marca_id
  LEFT JOIN modelos_vehiculo modv ON modv.id = v.modelo_id
      WHERE iv.accidente_id = ?
        AND iv.tipo IN ('Combinado vehicular 1', 'Combinado vehicular 2')
   ORDER BY
            CASE COALESCE(iv.orden_participacion, '')
                WHEN 'UT-1' THEN 1
                WHEN 'UT-2' THEN 2
                WHEN 'UT-3' THEN 3
                WHEN 'UT-4' THEN 4
                WHEN 'UT-5' THEN 5
                WHEN 'UT-6' THEN 6
                WHEN 'UT-7' THEN 7
                ELSE 99
            END,
            FIELD(iv.tipo, 'Combinado vehicular 1', 'Combinado vehicular 2'),
            v.placa",
    [$accidente_id]
);

$comboVehiculosPorUnidad = [];
foreach ($comboVehiculosRows as $comboVehiculo) {
    $ut = trim((string) ($comboVehiculo['orden_participacion'] ?? ''));
    if ($ut === '') {
        continue;
    }

    $comboVehiculo['veh_numero'] = (string) ($comboVehiculo['veh_participacion'] ?? '') === 'Combinado vehicular 2' ? '2' : '1';
    $comboVehiculo['veh_placa'] = vehiculo_placa_visible((string) ($comboVehiculo['veh_placa'] ?? ''));
    $comboVehiculosPorUnidad[$ut][] = $comboVehiculo;
}

$vehiculoInvIds = [];
foreach ($personas as $persona) {
    if (!empty($persona['inv_vehiculo_id'])) {
        $vehiculoInvIds[] = (int) $persona['inv_vehiculo_id'];
    }
}
foreach ($comboVehiculosRows as $comboVehiculo) {
    if (!empty($comboVehiculo['inv_vehiculo_id'])) {
        $vehiculoInvIds[] = (int) $comboVehiculo['inv_vehiculo_id'];
    }
}
$vehiculoInvIds = array_values(array_unique(array_filter($vehiculoInvIds)));

$docVehiculoPorInvolucrado = [];
$docVehiculoCantidadPorInvolucrado = [];
$docVehiculoTodosPorInvolucrado = [];
if ($vehiculoInvIds !== []) {
    $placeholders = implode(',', array_fill(0, count($vehiculoInvIds), '?'));
    $docVehiculoRows = safe_query_all(
        $pdo,
        "SELECT *
           FROM documento_vehiculo
          WHERE involucrado_vehiculo_id IN ($placeholders)
       ORDER BY involucrado_vehiculo_id, id DESC",
        $vehiculoInvIds
    );

    foreach ($docVehiculoRows as $docVehiculo) {
        $involucradoVehiculoId = (int) ($docVehiculo['involucrado_vehiculo_id'] ?? 0);
        if ($involucradoVehiculoId <= 0) {
            continue;
        }

        $docVehiculoCantidadPorInvolucrado[$involucradoVehiculoId] = ($docVehiculoCantidadPorInvolucrado[$involucradoVehiculoId] ?? 0) + 1;
        $docVehiculoTodosPorInvolucrado[$involucradoVehiculoId][] = $docVehiculo;
        if (!isset($docVehiculoPorInvolucrado[$involucradoVehiculoId])) {
            $docVehiculoPorInvolucrado[$involucradoVehiculoId] = $docVehiculo;
        }
    }
}

foreach ($personas as &$persona) {
    $persona['veh_chip_text'] = '';
    if (!empty($persona['veh_placa'])) {
        $persona['veh_chip_text'] = vehiculo_placa_visible((string) $persona['veh_placa']);
    }

    if (!es_participacion_combinada($persona['veh_participacion'] ?? null)) {
        continue;
    }

    $ut = trim((string) ($persona['orden_participacion'] ?? ''));
    if ($ut === '' || empty($comboVehiculosPorUnidad[$ut])) {
        continue;
    }

    $placas = [];
    foreach ($comboVehiculosPorUnidad[$ut] as $comboVehiculo) {
        $placa = trim((string) ($comboVehiculo['veh_placa'] ?? ''));
        if ($placa !== '') {
            $placas[] = $placa;
        }
    }

    if ($placas) {
        $persona['veh_combo_placas'] = implode(' + ', $placas);
        $persona['veh_chip_text'] = $ut . ' - ' . $persona['veh_combo_placas'];
    }
}
unset($persona);

$participacionRolOptions = ['' => 'Selecciona'];
$participacionRoles = safe_query_all(
    $pdo,
    "SELECT Id AS id, Nombre AS nombre
       FROM participacion_persona
      WHERE Activo = 1
   ORDER BY Orden, Nombre"
);
foreach ($participacionRoles as $rolOption) {
    $rolId = (string) ($rolOption['id'] ?? '');
    if ($rolId !== '') {
        $participacionRolOptions[$rolId] = (string) ($rolOption['nombre'] ?? '');
    }
}

$participacionVehiculoOptions = ['' => 'Sin vehículo'];
$participacionVehiculos = safe_query_all(
    $pdo,
    "SELECT iv.orden_participacion, iv.tipo, v.id, v.placa, v.color, v.anio
       FROM involucrados_vehiculos iv
       JOIN vehiculos v ON v.id = iv.vehiculo_id
      WHERE iv.accidente_id = ?
   ORDER BY
            CASE COALESCE(iv.orden_participacion, '')
                WHEN 'UT-1' THEN 1
                WHEN 'UT-2' THEN 2
                WHEN 'UT-3' THEN 3
                WHEN 'UT-4' THEN 4
                WHEN 'UT-5' THEN 5
                WHEN 'UT-6' THEN 6
                WHEN 'UT-7' THEN 7
                ELSE 99
            END,
            FIELD(iv.tipo, 'Combinado vehicular 1', 'Combinado vehicular 2', 'Unidad', 'Fugado'),
            v.placa",
    [$accidente_id]
);
foreach ($participacionVehiculos as $vehiculoOption) {
    $vehiculoId = (string) ($vehiculoOption['id'] ?? '');
    if ($vehiculoId === '') {
        continue;
    }

    $vehiculoPlaca = vehiculo_placa_visible((string) ($vehiculoOption['placa'] ?? ''));
    if ($vehiculoPlaca === '') {
        $vehiculoPlaca = 'SIN PLACA';
    }
    $participacionVehiculoOptions[$vehiculoId] = trim((string) (($vehiculoOption['orden_participacion'] ?? '') !== '' ? $vehiculoOption['orden_participacion'] . ' - ' : '') . $vehiculoPlaca . (!empty($vehiculoOption['color']) ? ' - ' . $vehiculoOption['color'] : '') . (!empty($vehiculoOption['anio']) ? ' (' . $vehiculoOption['anio'] . ')' : ''));
}

$policias = safe_query_all(
    $pdo,
    "SELECT pi.*,
            p.tipo_doc,
            p.num_doc,
            p.apellido_paterno,
            p.apellido_materno,
            p.nombres,
            p.sexo,
            p.fecha_nacimiento,
            p.edad,
            p.estado_civil,
            p.nacionalidad,
            p.departamento_nac,
            p.provincia_nac,
            p.distrito_nac,
            p.domicilio,
            p.domicilio_departamento,
            p.domicilio_provincia,
            p.domicilio_distrito,
            p.ocupacion,
            p.grado_instruccion,
            p.nombre_padre,
            p.nombre_madre,
            p.celular,
            p.email,
            p.notas,
            p.foto_path,
            p.api_fuente,
            p.api_ref,
            p.creado_en AS persona_creado_en
       FROM policial_interviniente pi
       JOIN personas p ON p.id = pi.persona_id
      WHERE pi.accidente_id = ?
   ORDER BY pi.id ASC",
    [$accidente_id]
);

$propietarios = safe_query_all(
    $pdo,
    "SELECT pv.*,
            iv.orden_participacion,
            v.placa,
            pn.tipo_doc AS owner_tipo_doc,
            pn.num_doc AS owner_num_doc,
            pn.apellido_paterno AS owner_apellido_paterno,
            pn.apellido_materno AS owner_apellido_materno,
            pn.nombres AS owner_nombres,
            pn.sexo AS owner_sexo,
            pn.fecha_nacimiento AS owner_fecha_nacimiento,
            pn.edad AS owner_edad,
            pn.estado_civil AS owner_estado_civil,
            pn.nacionalidad AS owner_nacionalidad,
            pn.departamento_nac AS owner_departamento_nac,
            pn.provincia_nac AS owner_provincia_nac,
            pn.distrito_nac AS owner_distrito_nac,
            pn.domicilio AS owner_domicilio,
            pn.domicilio_departamento AS owner_domicilio_departamento,
            pn.domicilio_provincia AS owner_domicilio_provincia,
            pn.domicilio_distrito AS owner_domicilio_distrito,
            pn.ocupacion AS owner_ocupacion,
            pn.grado_instruccion AS owner_grado_instruccion,
            pn.nombre_padre AS owner_nombre_padre,
            pn.nombre_madre AS owner_nombre_madre,
            pn.celular AS owner_celular,
            pn.email AS owner_email,
            pn.notas AS owner_notas,
            pn.foto_path AS owner_foto_path,
            pn.api_fuente AS owner_api_fuente,
            pn.api_ref AS owner_api_ref,
            pn.creado_en AS owner_persona_creado_en,
            pr.tipo_doc AS rep_tipo_doc,
            pr.num_doc AS rep_num_doc,
            pr.apellido_paterno AS rep_apellido_paterno,
            pr.apellido_materno AS rep_apellido_materno,
            pr.nombres AS rep_nombres,
            pr.sexo AS rep_sexo,
            pr.fecha_nacimiento AS rep_fecha_nacimiento,
            pr.edad AS rep_edad,
            pr.estado_civil AS rep_estado_civil,
            pr.nacionalidad AS rep_nacionalidad,
            pr.departamento_nac AS rep_departamento_nac,
            pr.provincia_nac AS rep_provincia_nac,
            pr.distrito_nac AS rep_distrito_nac,
            pr.domicilio AS rep_domicilio,
            pr.domicilio_departamento AS rep_domicilio_departamento,
            pr.domicilio_provincia AS rep_domicilio_provincia,
            pr.domicilio_distrito AS rep_domicilio_distrito,
            pr.ocupacion AS rep_ocupacion,
            pr.grado_instruccion AS rep_grado_instruccion,
            pr.nombre_padre AS rep_nombre_padre,
            pr.nombre_madre AS rep_nombre_madre,
            pr.celular AS rep_celular,
            pr.email AS rep_email,
            pr.notas AS rep_notas,
            pr.foto_path AS rep_foto_path,
            pr.api_fuente AS rep_api_fuente,
            pr.api_ref AS rep_api_ref,
            pr.creado_en AS rep_persona_creado_en
       FROM propietario_vehiculo pv
       JOIN involucrados_vehiculos iv ON iv.id = pv.vehiculo_inv_id
       JOIN vehiculos v ON v.id = iv.vehiculo_id
  LEFT JOIN personas pn ON pn.id = pv.propietario_persona_id
  LEFT JOIN personas pr ON pr.id = pv.representante_persona_id
      WHERE pv.accidente_id = ?
   ORDER BY
            CASE COALESCE(iv.orden_participacion, '')
                WHEN 'UT-1' THEN 1
                WHEN 'UT-2' THEN 2
                WHEN 'UT-3' THEN 3
                WHEN 'UT-4' THEN 4
                WHEN 'UT-5' THEN 5
                WHEN 'UT-6' THEN 6
                WHEN 'UT-7' THEN 7
                ELSE 99
            END,
            pv.id ASC",
    [$accidente_id]
);

$propietarioVehiculoInlineOptions = safe_query_all(
    $pdo,
    "SELECT iv.id AS inv_id, iv.orden_participacion, v.placa
       FROM involucrados_vehiculos iv
       JOIN vehiculos v ON v.id = iv.vehiculo_id
      WHERE iv.accidente_id = ?
   ORDER BY
            CASE COALESCE(iv.orden_participacion, '')
                WHEN 'UT-1' THEN 1
                WHEN 'UT-2' THEN 2
                WHEN 'UT-3' THEN 3
                WHEN 'UT-4' THEN 4
                WHEN 'UT-5' THEN 5
                WHEN 'UT-6' THEN 6
                WHEN 'UT-7' THEN 7
                ELSE 99
            END,
            v.placa",
    [$accidente_id]
);

$familiares = safe_query_all(
    $pdo,
    "SELECT ff.*,
            ip.persona_id AS fallecido_persona_id,
            pf.tipo_doc AS fall_tipo_doc,
            pf.num_doc AS fall_num_doc,
            pf.apellido_paterno AS fall_apellido_paterno,
            pf.apellido_materno AS fall_apellido_materno,
            pf.nombres AS fall_nombres,
            pf.sexo AS fall_sexo,
            pf.fecha_nacimiento AS fall_fecha_nacimiento,
            pf.edad AS fall_edad,
            pf.estado_civil AS fall_estado_civil,
            pf.nacionalidad AS fall_nacionalidad,
            pf.departamento_nac AS fall_departamento_nac,
            pf.provincia_nac AS fall_provincia_nac,
            pf.distrito_nac AS fall_distrito_nac,
            pf.domicilio AS fall_domicilio,
            pf.domicilio_departamento AS fall_domicilio_departamento,
            pf.domicilio_provincia AS fall_domicilio_provincia,
            pf.domicilio_distrito AS fall_domicilio_distrito,
            pf.ocupacion AS fall_ocupacion,
            pf.grado_instruccion AS fall_grado_instruccion,
            pf.nombre_padre AS fall_nombre_padre,
            pf.nombre_madre AS fall_nombre_madre,
            pf.celular AS fall_celular,
            pf.email AS fall_email,
            pf.notas AS fall_notas,
            pf.foto_path AS fall_foto_path,
            pf.api_fuente AS fall_api_fuente,
            pf.api_ref AS fall_api_ref,
            pf.creado_en AS fall_persona_creado_en,
            pr.tipo_doc AS fam_tipo_doc,
            pr.num_doc AS fam_num_doc,
            pr.apellido_paterno AS fam_apellido_paterno,
            pr.apellido_materno AS fam_apellido_materno,
            pr.nombres AS fam_nombres,
            pr.sexo AS fam_sexo,
            pr.fecha_nacimiento AS fam_fecha_nacimiento,
            pr.edad AS fam_edad,
            pr.estado_civil AS fam_estado_civil,
            pr.nacionalidad AS fam_nacionalidad,
            pr.departamento_nac AS fam_departamento_nac,
            pr.provincia_nac AS fam_provincia_nac,
            pr.distrito_nac AS fam_distrito_nac,
            pr.domicilio AS fam_domicilio,
            pr.domicilio_departamento AS fam_domicilio_departamento,
            pr.domicilio_provincia AS fam_domicilio_provincia,
            pr.domicilio_distrito AS fam_domicilio_distrito,
            pr.ocupacion AS fam_ocupacion,
            pr.grado_instruccion AS fam_grado_instruccion,
            pr.nombre_padre AS fam_nombre_padre,
            pr.nombre_madre AS fam_nombre_madre,
            pr.celular AS fam_celular,
            pr.email AS fam_email,
            pr.notas AS fam_notas,
            pr.foto_path AS fam_foto_path,
            pr.api_fuente AS fam_api_fuente,
            pr.api_ref AS fam_api_ref,
            pr.creado_en AS fam_persona_creado_en
       FROM familiar_fallecido ff
       JOIN involucrados_personas ip ON ip.id = ff.fallecido_inv_id
       JOIN personas pf ON pf.id = ip.persona_id
       JOIN personas pr ON pr.id = ff.familiar_persona_id
      WHERE ff.accidente_id = ?
   ORDER BY ff.id ASC",
    [$accidente_id]
);

$abogados = safe_query_all(
    $pdo,
    "SELECT a.*,
            TRIM(CONCAT(COALESCE(pr.apellido_paterno,''), ' ', COALESCE(pr.apellido_materno,''), ', ', COALESCE(pr.nombres,''))) AS persona_rep_nom,
            COALESCE(prr.roles, '') AS condicion_representado
       FROM abogados a
  LEFT JOIN personas pr ON pr.id = a.persona_id
  LEFT JOIN (
        SELECT accidente_id, persona_id, GROUP_CONCAT(DISTINCT rol ORDER BY rol SEPARATOR ', ') AS roles
          FROM (
                SELECT ip.accidente_id, ip.persona_id, COALESCE(pp.Nombre, 'Involucrado') AS rol
                  FROM involucrados_personas ip
             LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id

                UNION ALL

                SELECT pv.accidente_id, pv.propietario_persona_id AS persona_id, 'Propietario vehiculo' AS rol
                  FROM propietario_vehiculo pv

                UNION ALL

                SELECT ff.accidente_id, ff.familiar_persona_id AS persona_id, 'Familiar fallecido' AS rol
                  FROM familiar_fallecido ff
          ) roles_base
      GROUP BY accidente_id, persona_id
   ) prr ON prr.accidente_id = a.accidente_id AND prr.persona_id = a.persona_id
      WHERE a.accidente_id = ?
   ORDER BY a.apellido_paterno, a.apellido_materno, a.nombres, a.id",
    [$accidente_id]
);

$abogadosPorPersona = [];
foreach ($abogados as $abogado) {
    $personaRepresentadaId = (int) ($abogado['persona_id'] ?? 0);
    if ($personaRepresentadaId <= 0) {
        continue;
    }
    $abogadosPorPersona[$personaRepresentadaId][] = $abogado;
}

$manifestacionesPorPersona = [];
$manifestacionesAccidente = safe_query_all(
    $pdo,
    "SELECT id, persona_id, fecha, horario_inicio, hora_termino, modalidad, '' AS observaciones
       FROM Manifestacion
      WHERE accidente_id = ?
   ORDER BY COALESCE(fecha, '9999-12-31') DESC, id DESC",
    [$accidente_id]
);
foreach ($manifestacionesAccidente as $manifestacionAccidente) {
    $manifestacionPersonaId = (int) ($manifestacionAccidente['persona_id'] ?? 0);
    if ($manifestacionPersonaId <= 0) {
        continue;
    }
    $manifestacionesPorPersona[$manifestacionPersonaId][] = $manifestacionAccidente;
}

$oficios = safe_query_all(
    $pdo,
    "SELECT o.id,
            o.numero,
            o.anio,
            o.fecha_emision,
            o.estado,
            o.referencia_texto,
            o.motivo,
            COALESCE(e.siglas, e.nombre) AS entidad,
            COALESCE(a2.nombre, '') AS asunto_nombre,
            COALESCE(a2.detalle, '') AS asunto_detalle,
            COALESCE(iv.orden_participacion, '') AS veh_ut,
            COALESCE(v.placa, '') AS veh_placa,
            o.involucrado_persona_id AS inv_per_id,
            TRIM(CONCAT(COALESCE(p.apellido_paterno, ''), ' ', COALESCE(p.apellido_materno, ''), ' ', COALESCE(p.nombres, ''))) AS persona_nombre
       FROM oficios o
  LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino
  LEFT JOIN oficio_asunto a2 ON a2.id = o.asunto_id
  LEFT JOIN involucrados_vehiculos iv ON iv.id = o.involucrado_vehiculo_id
  LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id
  LEFT JOIN involucrados_personas ip ON ip.id = o.involucrado_persona_id
  LEFT JOIN personas p ON p.id = ip.persona_id
      WHERE o.accidente_id = ?
   ORDER BY o.fecha_emision DESC, o.id DESC",
            [$accidente_id]
);

$documentoFechaRecepcionExpr = safe_column_exists($pdo, 'documentos_recibidos', 'fecha_recepcion')
    ? 'dr.fecha_recepcion'
    : 'dr.fecha';
$documentoFechaDocumentoExpr = safe_column_exists($pdo, 'documentos_recibidos', 'fecha_documento')
    ? 'dr.fecha_documento'
    : 'dr.fecha';

$documentosRecibidos = safe_query_all(
    $pdo,
    "SELECT dr.*,
            {$documentoFechaRecepcionExpr} AS fecha_recepcion_resuelta,
            {$documentoFechaDocumentoExpr} AS fecha_documento_resuelta,
            COALESCE(o.numero, '') AS oficio_numero,
            COALESCE(o.anio, '') AS oficio_anio
       FROM documentos_recibidos dr
  LEFT JOIN oficios o ON o.id = dr.referencia_oficio_id
      WHERE dr.accidente_id = ?
   ORDER BY COALESCE({$documentoFechaRecepcionExpr}, '9999-12-31') DESC, dr.id DESC",
    [$accidente_id]
);

$itps = safe_query_all(
    $pdo,
    "SELECT i.*,
            a.registro_sidpol,
            a.fecha_accidente,
            a.lugar
       FROM itp i
  LEFT JOIN accidentes a ON a.id = i.accidente_id
      WHERE i.accidente_id = ?
   ORDER BY COALESCE(i.fecha_itp, '9999-12-31') DESC,
            COALESCE(i.hora_itp, '23:59:59') DESC,
            i.id DESC",
    [$accidente_id]
);

$diligencias = safe_query_all(
    $pdo,
    "SELECT dp.*,
            td.nombre AS tipo_nombre
       FROM diligencias_pendientes dp
  LEFT JOIN tipo_diligencia td ON td.id = dp.tipo_diligencia_id
      WHERE dp.accidente_id = ?
   ORDER BY dp.creado_en DESC, dp.id DESC",
    [$accidente_id]
);

$diligenciaOficioOptions = ['' => 'Sin oficio relacionado'];
foreach ($oficios as $oficioOption) {
    $label = trim((string) ('Oficio N° ' . ($oficioOption['numero'] ?? '—') . '/' . ($oficioOption['anio'] ?? '—')));
    if (!empty($oficioOption['asunto_nombre'])) {
        $label .= ' · ' . trim((string) $oficioOption['asunto_nombre']);
    }
    $diligenciaOficioOptions[(string) ($oficioOption['id'] ?? '')] = $label;
}

$diligenciaDocumentoRecibidoOptions = ['' => 'Sin documento recibido'];
foreach ($documentosRecibidos as $documentoRecibido) {
    $labelParts = [];
    if (!empty($documentoRecibido['tipo_documento'])) {
        $labelParts[] = (string) $documentoRecibido['tipo_documento'];
    }
    if (!empty($documentoRecibido['numero_documento'])) {
        $labelParts[] = (string) $documentoRecibido['numero_documento'];
    }
    if (!empty($documentoRecibido['asunto'])) {
        $labelParts[] = (string) $documentoRecibido['asunto'];
    }
    $label = trim((string) implode(' · ', array_filter($labelParts, static fn($item): bool => trim((string) $item) !== '')));
    if ($label === '') {
        $label = 'Documento recibido #' . (int) ($documentoRecibido['id'] ?? 0);
    }
    $diligenciaDocumentoRecibidoOptions[$label] = $label;
}

$diligenciasPendientesSolo = array_values(array_filter(
    $diligencias,
    static fn(array $row): bool => trim((string) ($row['estado'] ?? 'Pendiente')) !== 'Realizado'
));
$diligenciasRealizadas = array_values(array_filter(
    $diligencias,
    static fn(array $row): bool => trim((string) ($row['estado'] ?? 'Pendiente')) === 'Realizado'
));

$renderDiligenciaCards = static function (array $items) use ($diligenciaDocumentoRecibidoOptions, $diligenciaOficioOptions): string {
    ob_start();
    if (!$items): ?>
      <div class="empty-state">No hay diligencias en esta pestaña.</div>
    <?php else: ?>
      <div class="module-grid">
        <?php foreach ($items as $row): ?>
          <?php
            $estadoDiligenciaRaw = trim((string) ($row['estado'] ?? 'Pendiente'));
            $estadoDiligenciaUi = $estadoDiligenciaRaw === 'Realizado' ? 'Resuelto' : 'Pendiente';
            $documentoRecibidoValue = trim((string) ($row['documentos_recibidos'] ?? ''));
            $diligenciaDocOptions = $diligenciaDocumentoRecibidoOptions;
            if ($documentoRecibidoValue !== '' && !array_key_exists($documentoRecibidoValue, $diligenciaDocOptions)) {
                $diligenciaDocOptions[$documentoRecibidoValue] = $documentoRecibidoValue;
            }
          ?>
          <article class="module-card">
            <div class="editable-shell" data-edit-shell="diligencia-<?= (int) $row['id'] ?>">
              <div class="diligencia-card">
                <div class="diligencia-main">
                  <div class="diligencia-head">
                    <div>
                      <h4>Diligencia #<?= (int) $row['id'] ?></h4>
                      <p><?= h((string) (($row['tipo_nombre'] ?? '') !== '' ? $row['tipo_nombre'] : (($row['tipo_diligencia'] ?? '') !== '' ? $row['tipo_diligencia'] : 'Sin tipo'))) ?></p>
                      <div class="module-meta" style="margin-top:6px">
                        <?php if (!empty($row['oficio_id'])): ?><span class="chip-simple">Oficio #<?= (int) $row['oficio_id'] ?></span><?php endif; ?>
                        <?php if (!empty($row['citacion_id'])): ?><span class="chip-simple">Citación #<?= (int) $row['citacion_id'] ?></span><?php endif; ?>
                        <?php if (!empty($row['creado_en'])): ?><span class="chip-simple">Creada: <?= h(fecha_hora_simple($row['creado_en'])) ?></span><?php endif; ?>
                      </div>
                    </div>
                    <div class="diligencia-side">
                      <select class="diligencia-status-select js-quick-diligencia-status <?= $estadoDiligenciaUi === 'Resuelto' ? 'status-resuelto' : 'status-pendiente' ?>" data-diligencia-id="<?= (int) $row['id'] ?>" data-prev="<?= h($estadoDiligenciaUi) ?>" aria-label="Estado de la diligencia">
                        <option value="Pendiente" <?= $estadoDiligenciaUi === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="Resuelto" <?= $estadoDiligenciaUi === 'Resuelto' ? 'selected' : '' ?>>Resuelto</option>
                      </select>
                      <div class="diligencia-actions">
                        <a class="btn-shell" href="diligenciapendiente_ver.php?id=<?= (int) $row['id'] ?>">Ver</a>
                        <button type="button" class="btn-shell js-edit-start" data-shell="diligencia-<?= (int) $row['id'] ?>">Editar</button>
                        <a class="btn-shell" href="diligenciapendiente_eliminar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . ((int) ($row['accidente_id'] ?? 0)))) ?>">Eliminar</a>
                        <div class="editable-actions" data-edit-actions="diligencia-<?= (int) $row['id'] ?>" hidden>
                          <button type="button" class="btn-shell js-edit-cancel" data-shell="diligencia-<?= (int) $row['id'] ?>">Cancelar</button>
                          <button type="submit" class="btn-shell btn-primary" form="diligencia-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="inline-edit-error" id="diligencia-inline-error-<?= (int) $row['id'] ?>"></div>
                  <div class="editable-view" data-edit-view="diligencia-<?= (int) $row['id'] ?>">
                    <div class="diligencia-inline-fields">
                      <div class="diligencia-inline-row">
                        <div class="diligencia-inline-box">
                          <strong>Contenido</strong>
                          <div><?= !empty($row['contenido']) ? nl2br(h((string) $row['contenido'])) : '—' ?></div>
                        </div>
                        <div class="diligencia-inline-box">
                          <strong>Documento realizado</strong>
                          <div><?= h((string) (($row['documento_realizado'] ?? '') !== '' ? $row['documento_realizado'] : '—')) ?></div>
                        </div>
                      </div>
                      <div class="diligencia-inline-row">
                        <div class="diligencia-inline-box">
                          <strong>Documento recibido</strong>
                          <div><?= h($documentoRecibidoValue !== '' ? $documentoRecibidoValue : '—') ?></div>
                        </div>
                        <div class="diligencia-inline-box">
                          <strong>Oficio relacionado</strong>
                          <div><?= !empty($row['oficio_id']) && isset($diligenciaOficioOptions[(string) $row['oficio_id']]) ? h($diligenciaOficioOptions[(string) $row['oficio_id']]) : '—' ?></div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <form class="editable-form js-inline-ajax-form" id="diligencia-inline-form-<?= (int) $row['id'] ?>" data-shell="diligencia-<?= (int) $row['id'] ?>" data-error="diligencia-inline-error-<?= (int) $row['id'] ?>" method="post" hidden>
                    <input type="hidden" name="action" value="save_diligencia_inline">
                    <input type="hidden" name="diligencia_id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="tipo_diligencia_id" value="<?= (int) ($row['tipo_diligencia_id'] ?? 0) ?>">
                    <input type="hidden" name="estado" value="<?= h($estadoDiligenciaRaw) ?>">
                    <div class="section-block">
                      <h3>Edición rápida</h3>
                      <div class="field-grid">
                        <?= render_editable_fields($row, [
                            ['name' => 'contenido', 'label' => 'Contenido', 'type' => 'textarea', 'rows' => 4, 'class' => 'span-2'],
                            ['name' => 'documento_realizado', 'label' => 'Documento realizado', 'class' => 'span-2'],
                            ['name' => 'documentos_recibidos', 'label' => 'Documento recibido', 'type' => 'select', 'options' => $diligenciaDocOptions, 'class' => 'span-2'],
                            ['name' => 'oficio_id', 'label' => 'Oficio relacionado', 'type' => 'select', 'options' => $diligenciaOficioOptions, 'class' => 'span-2'],
                        ], 'diligencia-' . (int) $row['id']) ?>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif;
    return (string) ob_get_clean();
};

$personaSections = [
    'Identidad' => [
        'tipo_doc', 'num_doc', 'apellido_paterno', 'apellido_materno', 'nombres',
        'sexo', 'fecha_nacimiento', 'edad', 'estado_civil', 'nacionalidad',
    ],
    'Nacimiento y Contacto' => [
        'departamento_nac', 'provincia_nac', 'distrito_nac',
        ['key' => 'domicilio', 'class' => 'span-2'],
        'domicilio_departamento', 'domicilio_provincia', 'domicilio_distrito',
        'celular', 'email',
    ],
    'Perfil Complementario' => [
        'ocupacion', 'grado_instruccion',
        ['key' => 'nombre_padre', 'class' => 'span-2'],
        ['key' => 'nombre_madre', 'class' => 'span-2'],
        ['key' => 'notas', 'class' => 'span-2'],
        ['key' => 'foto_path', 'class' => 'span-2'],
        'api_fuente', 'api_ref', 'creado_en',
    ],
];

$involucradoFields = [
    'rol_nombre', 'orden_persona', 'lesion', 'orden_participacion', 'veh_participacion',
    ['key' => 'involucrado_observaciones', 'class' => 'span-2'],
    ['key' => 'inv_vehiculo_observaciones', 'class' => 'span-2'],
    'involucrado_creado_en', 'involucrado_actualizado_en',
];

$participacionEditFields = [
    ['name' => 'rol_id', 'label' => 'Rol', 'type' => 'select', 'required' => true, 'options' => $participacionRolOptions],
    ['name' => 'vehiculo_id', 'label' => 'Vehículo', 'type' => 'select', 'options' => $participacionVehiculoOptions],
    ['name' => 'lesion', 'label' => 'Lesión', 'type' => 'select', 'options' => ['Ileso' => 'Ileso', 'Herido' => 'Herido', 'Fallecido' => 'Fallecido']],
    ['name' => 'orden_persona', 'label' => 'Orden persona', 'type' => 'select', 'options' => array_merge(['' => '—'], array_combine(range('A', 'Z'), range('A', 'Z')))],
    ['name' => 'observaciones', 'value_key' => 'involucrado_observaciones', 'label' => 'Observaciones', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
];

$vehiculoFields = [
    'veh_placa', 'veh_marca', 'veh_modelo', 'veh_anio', 'veh_color',
    'veh_categoria', 'veh_tipo', 'veh_carroceria',
    ['key' => 'veh_serie_vin', 'class' => 'span-2'],
    ['key' => 'veh_nro_motor', 'class' => 'span-2'],
    'veh_largo_mm', 'veh_ancho_mm', 'veh_alto_mm',
    ['key' => 'veh_notas', 'class' => 'span-2'],
    'veh_creado_en', 'veh_actualizado_en',
];

$docVehiculoSections = [
    'propiedad' => [
        'label' => 'Tarjeta de propiedad',
        'fields' => ['numero_propiedad', 'titulo_propiedad', 'partida_propiedad', 'sede_propiedad'],
    ],
    'soat' => [
        'label' => 'SOAT',
        'fields' => ['numero_soat', 'aseguradora_soat', 'vigente_soat', 'vencimiento_soat'],
    ],
    'peritaje' => [
        'label' => 'Peritaje',
        'fields' => [
            'numero_peritaje', 'fecha_peritaje', 'perito_peritaje',
            'sistema_electrico_peritaje', 'sistema_frenos_peritaje', 'sistema_direccion_peritaje',
            'sistema_transmision_peritaje', 'sistema_suspension_peritaje', 'planta_motriz_peritaje',
            ['key' => 'otros_peritaje', 'class' => 'span-2'],
            ['key' => 'danos_peritaje', 'class' => 'span-2'],
        ],
    ],
    'revision' => [
        'label' => 'Revisión técnica',
        'fields' => ['numero_revision', 'certificadora_revision', 'vigente_revision', 'vencimiento_revision'],
    ],
];

$renderVehiculoSubtabs = static function (
    array $vehiculoRecord,
    ?array $documentoVehiculo,
    int $documentoVehiculoCount,
    string $tabPrefix,
    string $workbenchId,
    string $frameId,
    string $returnTo,
    array $vehiculoFields,
    array $docVehiculoSections,
    ?array $unidadRecord = null
): string {
    $documentoVehiculoId = (int) ($documentoVehiculo['id'] ?? 0);
    $involucradoVehiculoId = (int) ($vehiculoRecord['inv_vehiculo_id'] ?? 0);
    $vehiculoNumero = trim((string) ($vehiculoRecord['veh_numero'] ?? ''));
    $vehiculoTitulo = $vehiculoNumero !== '' ? 'Vehículo ' . $vehiculoNumero : 'Vehículo vinculado al conductor';
    $returnToEncoded = urlencode($returnTo);

    ob_start();
    ?>
    <div class="inner-tabs nav nav-tabs flex-nowrap" id="<?= h($tabPrefix) ?>-subtabs" role="tablist">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#<?= h($tabPrefix) ?>-resumen" type="button" role="tab">
        Resumen
        <span class="tab-mini">Ficha del vehículo</span>
      </button>
      <?php foreach ($docVehiculoSections as $slug => $section): ?>
        <?php $sectionHasData = $documentoVehiculoId > 0 && record_has_any_content($documentoVehiculo ?? [], $section['fields']); ?>
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($tabPrefix) ?>-<?= h($slug) ?>" type="button" role="tab">
          <?= h((string) $section['label']) ?>
          <span class="tab-mini"><?= $sectionHasData ? '1 registro(s)' : '0 registro(s)' ?></span>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="tab-content mt-2">
      <div class="tab-pane fade show active" id="<?= h($tabPrefix) ?>-resumen" role="tabpanel">
        <div class="inner-panel">
          <?php if ($unidadRecord !== null): ?>
            <div class="section-block" style="margin-top:0">
              <h3>Unidad combinada vinculada al conductor</h3>
              <div class="field-grid"><?= render_field_cards($unidadRecord, ['orden_participacion', ['key' => 'veh_combo_placas', 'class' => 'span-2']]) ?></div>
            </div>
          <?php endif; ?>
          <div class="section-block" style="margin-top:0">
            <h3><?= h($vehiculoTitulo) ?></h3>
            <div class="field-grid"><?= render_field_cards($vehiculoRecord, $vehiculoFields) ?></div>
          </div>
        </div>
      </div>

      <?php foreach ($docVehiculoSections as $slug => $section): ?>
        <?php $sectionHasData = $documentoVehiculoId > 0 && record_has_any_content($documentoVehiculo ?? [], $section['fields']); ?>
        <div class="tab-pane fade" id="<?= h($tabPrefix) ?>-<?= h($slug) ?>" role="tabpanel">
          <div class="inner-panel">
            <div class="module-actions" style="margin-bottom:8px;">
              <?php if ($slug === 'peritaje' && $involucradoVehiculoId > 0): ?>
                <a class="btn-shell btn-peritaje" href="oficio_peritaje_express.php?accidente_id=<?= (int) ($vehiculoRecord['accidente_id'] ?? 0) ?>&invol_id=<?= $involucradoVehiculoId ?>&return_to=<?= $returnToEncoded ?>">Generar oficio peritaje</a>
              <?php endif; ?>
              <?php if ($documentoVehiculoId > 0): ?>
                <a class="btn-shell js-inline-open" href="documento_vehiculo_editar.php?id=<?= $documentoVehiculoId ?>&section=<?= urlencode((string) $slug) ?>&embed=1&return_to=<?= $returnToEncoded ?>" data-workbench="<?= h($workbenchId) ?>" data-frame="<?= h($frameId) ?>" data-title="Documento de vehículo">Editar documento</a>
                <span class="chip-simple">Documento #<?= $documentoVehiculoId ?><?= $documentoVehiculoCount > 1 ? ' · ' . $documentoVehiculoCount . ' registro(s)' : '' ?></span>
              <?php elseif ($involucradoVehiculoId > 0): ?>
                <a class="btn-shell js-inline-open" href="documento_vehiculo_nuevo.php?invol_id=<?= $involucradoVehiculoId ?>&section=<?= urlencode((string) $slug) ?>&embed=1&return_to=<?= $returnToEncoded ?>" data-workbench="<?= h($workbenchId) ?>" data-frame="<?= h($frameId) ?>" data-title="Documento de vehículo">+ Nuevo documento</a>
              <?php endif; ?>
            </div>

            <?php if ($sectionHasData): ?>
              <div class="section-block" style="margin-top:0">
                <h3><?= h((string) $section['label']) ?></h3>
                <div class="field-grid"><?= render_field_cards($documentoVehiculo ?? [], $section['fields']) ?></div>
              </div>
            <?php elseif ($documentoVehiculoId > 0): ?>
              <div class="empty-state">El documento existe, pero esta sección aún no tiene datos registrados.</div>
            <?php else: ?>
              <div class="empty-state">No hay <?= h(mb_strtolower((string) $section['label'], 'UTF-8')) ?> registrada para este vehículo.</div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
};

$personaEditSections = [
    'Identidad' => [
        ['name' => 'tipo_doc', 'type' => 'select', 'required' => true, 'options' => ['DNI' => 'DNI', 'CE' => 'CE', 'PAS' => 'PAS', 'OTRO' => 'OTRO']],
        ['name' => 'num_doc', 'required' => true, 'maxlength' => 20],
        ['name' => 'apellido_paterno', 'required' => true],
        ['name' => 'apellido_materno', 'required' => true],
        ['name' => 'nombres', 'required' => true],
        ['name' => 'sexo', 'type' => 'select', 'required' => true, 'options' => ['' => 'Selecciona', 'M' => 'Masculino', 'F' => 'Femenino']],
        ['name' => 'fecha_nacimiento', 'type' => 'date', 'required' => true],
        ['name' => 'edad_preview', 'value_key' => 'edad', 'label' => 'Edad', 'type' => 'number', 'readonly' => true],
        ['name' => 'estado_civil', 'type' => 'select', 'options' => ['' => 'Selecciona', 'Soltero' => 'Soltero', 'Casado' => 'Casado', 'Viudo' => 'Viudo', 'Divorciado' => 'Divorciado', 'Conviviente' => 'Conviviente']],
        ['name' => 'nacionalidad'],
    ],
    'Nacimiento y Contacto' => [
        ['name' => 'departamento_nac'],
        ['name' => 'provincia_nac'],
        ['name' => 'distrito_nac'],
        ['name' => 'domicilio', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
        ['name' => 'domicilio_departamento'],
        ['name' => 'domicilio_provincia'],
        ['name' => 'domicilio_distrito'],
        ['name' => 'celular'],
        ['name' => 'email', 'type' => 'email'],
    ],
    'Perfil Complementario' => [
        ['name' => 'ocupacion'],
        ['name' => 'grado_instruccion'],
        ['name' => 'nombre_padre', 'class' => 'span-2'],
        ['name' => 'nombre_madre', 'class' => 'span-2'],
        ['name' => 'notas', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
    ],
];

$policiaPersonaSections = [
    'Identidad' => [
        'tipo_doc', 'num_doc', 'apellido_paterno', 'apellido_materno', 'nombres',
        'sexo', 'fecha_nacimiento', 'edad', 'estado_civil', 'nacionalidad',
    ],
    'Nacimiento y Contacto' => [
        'departamento_nac', 'provincia_nac', 'distrito_nac',
        ['key' => 'domicilio', 'class' => 'span-2'],
        'domicilio_departamento', 'domicilio_provincia', 'domicilio_distrito',
        'celular', 'email',
    ],
    'Perfil Complementario' => [
        'ocupacion', 'grado_instruccion',
        ['key' => 'nombre_padre', 'class' => 'span-2'],
        ['key' => 'nombre_madre', 'class' => 'span-2'],
        ['key' => 'notas', 'class' => 'span-2'],
        ['key' => 'foto_path', 'class' => 'span-2'],
        'api_fuente', 'api_ref', 'persona_creado_en',
    ],
];

$policiaPersonaTabSections = [
    'Datos personales' => array_merge($policiaPersonaSections['Identidad'], $policiaPersonaSections['Nacimiento y Contacto']),
    'Datos complementarios' => $policiaPersonaSections['Perfil Complementario'],
];

$policiaPersonaEditSections = [
    'Datos personales' => array_merge($personaEditSections['Identidad'], $personaEditSections['Nacimiento y Contacto']),
    'Datos complementarios' => $personaEditSections['Perfil Complementario'],
];

$abogadoSections = [
    'Datos personales' => [
        'apellido_paterno', 'apellido_materno', 'nombres',
    ],
    'Registro profesional' => [
        'colegiatura', 'registro', 'casilla_electronica',
    ],
    'Contacto y dirección' => [
        'celular', 'email',
        ['key' => 'domicilio_procesal', 'class' => 'span-2'],
    ],
];

$policialRecordEditFields = [
    ['name' => 'grado_policial', 'label' => 'Grado policial', 'required' => true],
    ['name' => 'cip', 'label' => 'CIP', 'required' => true],
    ['name' => 'dependencia_policial', 'label' => 'Dependencia policial', 'required' => true, 'class' => 'span-2'],
    ['name' => 'rol_funcion', 'label' => 'Rol / función'],
    ['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
];

$propietarioVehiculoSelectOptions = ['' => 'Selecciona'];
foreach ($propietarioVehiculoInlineOptions as $vehiculoOption) {
    $optionId = (string) ($vehiculoOption['inv_id'] ?? '');
    if ($optionId === '') {
        continue;
    }

    $optionPlaca = vehiculo_placa_visible((string) ($vehiculoOption['placa'] ?? ''));
    if ($optionPlaca === '') {
        $optionPlaca = 'SIN PLACA';
    }
    $propietarioVehiculoSelectOptions[$optionId] = trim((string) (($vehiculoOption['orden_participacion'] ?? '') !== '' ? $vehiculoOption['orden_participacion'] . ' - ' : '') . $optionPlaca);
}

$propietarioNaturalEditFields = [
    ['name' => 'vehiculo_inv_id', 'label' => 'Vehículo del accidente', 'type' => 'select', 'required' => true, 'options' => $propietarioVehiculoSelectOptions, 'class' => 'span-2'],
    ['name' => 'tipo_propietario', 'label' => 'Tipo de propietario', 'readonly' => true],
    ['name' => 'orden_participacion', 'label' => 'Unidad de tránsito', 'readonly' => true],
    ['name' => 'placa', 'label' => 'Placa', 'readonly' => true],
    ['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
];

$propietarioJuridicaEditFields = [
    ['name' => 'vehiculo_inv_id', 'label' => 'Vehículo del accidente', 'type' => 'select', 'required' => true, 'options' => $propietarioVehiculoSelectOptions, 'class' => 'span-2'],
    ['name' => 'tipo_propietario', 'label' => 'Tipo de propietario', 'readonly' => true],
    ['name' => 'ruc', 'label' => 'RUC', 'required' => true, 'maxlength' => 11],
    ['name' => 'rol_legal', 'label' => 'Rol legal'],
    ['name' => 'razon_social', 'label' => 'Razón social', 'required' => true, 'class' => 'span-2'],
    ['name' => 'domicilio_fiscal', 'label' => 'Domicilio fiscal', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
    ['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
];

$propietarioRecordViewFields = [
    'tipo_propietario',
    'rol_legal',
    'orden_participacion',
    'placa',
    ['key' => 'ruc', 'class' => 'span-2'],
    ['key' => 'razon_social', 'class' => 'span-2'],
    ['key' => 'domicilio_fiscal', 'class' => 'span-2'],
    ['key' => 'observaciones', 'class' => 'span-2'],
];

$abogadoEditSections = [
    'Datos del abogado' => [
        ['name' => 'persona_id', 'label' => 'Representa a', 'type' => 'select', 'required' => true, 'options' => array_reduce(
            $abogadoInlineContext['personas'] ?? [],
            static function (array $carry, array $persona): array {
                $carry[(string) ($persona['id'] ?? '')] = trim((string) (($persona['nombre'] ?? '') . (($persona['roles'] ?? '') !== '' ? ' - ' . $persona['roles'] : '')));
                return $carry;
            },
            ['' => 'Selecciona']
        )],
        ['name' => 'colegiatura', 'label' => 'Colegiatura', 'required' => true],
        ['name' => 'apellido_paterno', 'label' => 'Apellido paterno', 'required' => true],
        ['name' => 'apellido_materno', 'label' => 'Apellido materno'],
        ['name' => 'nombres', 'label' => 'Nombres', 'required' => true],
        ['name' => 'registro', 'label' => 'Registro'],
        ['name' => 'casilla_electronica', 'label' => 'Casilla electrónica'],
        ['name' => 'celular', 'label' => 'Celular'],
        ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
        ['name' => 'domicilio_procesal', 'label' => 'Domicilio procesal', 'class' => 'span-2'],
    ],
];

$vehiculoRepo = new VehiculoRepository($pdo);
$vehiculoService = new VehiculoService($vehiculoRepo);
$vehiculoCatalogos = $vehiculoService->catalogos();
$vehiculoCategoriasOptions = ['' => '(Selecciona)'];
foreach ($vehiculoCatalogos['categorias'] as $categoria) {
    $vehiculoCategoriasOptions[(string) $categoria['id']] = trim((string) ($categoria['codigo'] . (!empty($categoria['descripcion']) ? ' - ' . $categoria['descripcion'] : '')));
}
$vehiculoMarcasOptions = ['' => '(Selecciona)'];
foreach ($vehiculoCatalogos['marcas'] as $marca) {
    $vehiculoMarcasOptions[(string) $marca['id']] = (string) $marca['nombre'];
}

$personaExtras = [];
foreach ($personas as $persona) {
    $personaId = (int) ($persona['persona_id'] ?? 0);
    $invId = (int) ($persona['involucrado_id'] ?? 0);
    $personaExtras[$invId] = [
        'lc' => $personaId > 0 ? safe_query_all(
            $pdo,
            "SELECT id, clase, categoria, numero, expedido_por, vigente_desde, vigente_hasta, restricciones
               FROM documento_lc
              WHERE persona_id = ?
           ORDER BY COALESCE(vigente_hasta, '9999-12-31') DESC, id DESC",
            [$personaId]
        ) : [],
        'rml' => $personaId > 0 ? safe_query_all(
            $pdo,
            "SELECT id, numero, fecha, incapacidad_medico, atencion_facultativo, observaciones
               FROM documento_rml
              WHERE persona_id = ?
           ORDER BY COALESCE(fecha, '9999-12-31') DESC, id DESC",
            [$personaId]
        ) : [],
        'dos' => $personaId > 0 ? safe_query_all(
            $pdo,
            "SELECT id, numero, numero_registro, fecha_extraccion, resultado_cualitativo, resultado_cuantitativo, observaciones
               FROM documento_dosaje
              WHERE persona_id = ?
           ORDER BY COALESCE(fecha_extraccion, '9999-12-31 23:59:59') DESC, id DESC",
            [$personaId]
        ) : [],
        'man' => $personaId > 0 ? safe_query_all(
            $pdo,
            "SELECT id, fecha, horario_inicio, hora_termino, modalidad, '' AS observaciones
               FROM Manifestacion
              WHERE persona_id = ? AND accidente_id = ?
           ORDER BY COALESCE(fecha, '9999-12-31') DESC, id DESC",
            [$personaId, $accidente_id]
        ) : [],
        'abogados' => $abogadosPorPersona[$personaId] ?? [],
        'manifestacion_context' => [
            'fiscalia_nombre' => $A['fiscalia_nombre'] ?? ($A['fiscalia_nom'] ?? ''),
            'fiscal_nombre' => $A['fiscal_nombre'] ?? ($A['fiscal_nom'] ?? ''),
            'fiscal_cargo' => $A['fiscal_cargo'] ?? '',
        ],
        'occ' => $personaId > 0 ? safe_query_all(
            $pdo,
            "SELECT id,
                    fecha_levantamiento, hora_levantamiento, lugar_levantamiento,
                    posicion_cuerpo_levantamiento, lesiones_levantamiento, presuntivo_levantamiento,
                    legista_levantamiento, cmp_legista, observaciones_levantamiento,
                    numero_pericial, fecha_pericial, hora_pericial, observaciones_pericial,
                    numero_protocolo, fecha_protocolo, hora_protocolo, lesiones_protocolo,
                    presuntivo_protocolo, dosaje_protocolo, toxicologico_protocolo,
                    nosocomio_epicrisis, numero_historia_epicrisis, tratamiento_epicrisis, hora_alta_epicrisis
               FROM documento_occiso
              WHERE persona_id = ? AND accidente_id = ?
           ORDER BY COALESCE(fecha_levantamiento, '9999-12-31') DESC, id DESC",
            [$personaId, $accidente_id]
        ) : [],
        'show_lc' => needs_lc($persona),
        'show_rml' => needs_rml($persona),
        'show_dos' => needs_dos($persona),
        'show_man' => needs_man($persona),
        'show_occ' => needs_occ($persona),
    ];
}

$analysisVehiclesByInvId = [];
foreach ($personas as $persona) {
    $invVehiculoId = (int) ($persona['inv_vehiculo_id'] ?? 0);
    if ($invVehiculoId <= 0 || isset($analysisVehiclesByInvId[$invVehiculoId])) {
        continue;
    }

    $analysisVehiclesByInvId[$invVehiculoId] = [
        'involucrado_vehiculo_id' => $invVehiculoId,
        'veh_id' => (int) ($persona['veh_id'] ?? 0),
        'orden_participacion' => trim((string) ($persona['orden_participacion'] ?? '')),
        'veh_participacion' => trim((string) ($persona['veh_participacion'] ?? '')),
        'veh_placa' => vehiculo_placa_visible((string) ($persona['veh_placa'] ?? '')),
        'veh_marca' => trim((string) ($persona['veh_marca'] ?? '')),
        'veh_modelo' => trim((string) ($persona['veh_modelo'] ?? '')),
        'veh_color' => trim((string) ($persona['veh_color'] ?? '')),
    ];
}
foreach ($comboVehiculosRows as $comboVehiculo) {
    $invVehiculoId = (int) ($comboVehiculo['inv_vehiculo_id'] ?? 0);
    if ($invVehiculoId <= 0 || isset($analysisVehiclesByInvId[$invVehiculoId])) {
        continue;
    }

    $analysisVehiclesByInvId[$invVehiculoId] = [
        'involucrado_vehiculo_id' => $invVehiculoId,
        'veh_id' => (int) ($comboVehiculo['veh_id'] ?? 0),
        'orden_participacion' => trim((string) ($comboVehiculo['orden_participacion'] ?? '')),
        'veh_participacion' => trim((string) ($comboVehiculo['veh_participacion'] ?? '')),
        'veh_placa' => vehiculo_placa_visible((string) ($comboVehiculo['veh_placa'] ?? '')),
        'veh_marca' => trim((string) ($comboVehiculo['veh_marca'] ?? '')),
        'veh_modelo' => trim((string) ($comboVehiculo['veh_modelo'] ?? '')),
        'veh_color' => trim((string) ($comboVehiculo['veh_color'] ?? '')),
    ];
}
$analysisDamageByInvId = [];
foreach ($analysisVehiclesByInvId as $invVehiculoId => $vehicle) {
    $documents = $docVehiculoTodosPorInvolucrado[$invVehiculoId] ?? [];
    $damageDocument = null;
    foreach ($documents as $document) {
        if (
            trim((string) ($document['danos_peritaje'] ?? '')) !== ''
            || trim((string) ($document['sistema_electrico_peritaje'] ?? '')) !== ''
            || trim((string) ($document['sistema_frenos_peritaje'] ?? '')) !== ''
            || trim((string) ($document['sistema_direccion_peritaje'] ?? '')) !== ''
            || trim((string) ($document['sistema_transmision_peritaje'] ?? '')) !== ''
            || trim((string) ($document['sistema_suspension_peritaje'] ?? '')) !== ''
            || trim((string) ($document['planta_motriz_peritaje'] ?? '')) !== ''
            || trim((string) ($document['otros_peritaje'] ?? '')) !== ''
        ) {
            $damageDocument = $document;
            break;
        }
    }

    if ($damageDocument === null) {
        continue;
    }

    $analysisDamageByInvId[$invVehiculoId] = [
        'veh_id' => (int) ($vehicle['veh_id'] ?? 0),
        'orden_participacion' => (string) ($vehicle['orden_participacion'] ?? ''),
        'veh_participacion' => (string) ($vehicle['veh_participacion'] ?? ''),
        'veh_placa' => (string) ($vehicle['veh_placa'] ?? ''),
        'veh_marca' => (string) ($vehicle['veh_marca'] ?? ''),
        'veh_modelo' => (string) ($vehicle['veh_modelo'] ?? ''),
        'veh_color' => (string) ($vehicle['veh_color'] ?? ''),
        'numero_peritaje' => trim((string) ($damageDocument['numero_peritaje'] ?? '')),
        'fecha_peritaje' => $damageDocument['fecha_peritaje'] ?? null,
        'sistema_electrico_peritaje' => trim((string) ($damageDocument['sistema_electrico_peritaje'] ?? '')),
        'sistema_frenos_peritaje' => trim((string) ($damageDocument['sistema_frenos_peritaje'] ?? '')),
        'sistema_direccion_peritaje' => trim((string) ($damageDocument['sistema_direccion_peritaje'] ?? '')),
        'sistema_transmision_peritaje' => trim((string) ($damageDocument['sistema_transmision_peritaje'] ?? '')),
        'sistema_suspension_peritaje' => trim((string) ($damageDocument['sistema_suspension_peritaje'] ?? '')),
        'planta_motriz_peritaje' => trim((string) ($damageDocument['planta_motriz_peritaje'] ?? '')),
        'otros_peritaje' => trim((string) ($damageDocument['otros_peritaje'] ?? '')),
        'danos_peritaje' => trim((string) ($damageDocument['danos_peritaje'] ?? '')),
    ];
}

$analysisDriverRows = [];
foreach ($personas as $persona) {
    if (!is_conductor($persona)) {
        continue;
    }

    $invVehiculoId = (int) ($persona['inv_vehiculo_id'] ?? 0);
    $damageRow = $analysisDamageByInvId[$invVehiculoId] ?? null;
    $vehiculoLabel = trim((string) ($persona['veh_chip_text'] ?? ''));
    if ($vehiculoLabel === '') {
        $vehiculoLabel = vehiculo_placa_visible((string) ($persona['veh_placa'] ?? ''));
    }
    if ($vehiculoLabel === '') {
        $vehiculoLabel = 'Sin vehículo registrado';
    }

    $analysisDriverRows[] = [
        'nombre' => person_label($persona),
        'vehiculo' => $vehiculoLabel,
        'peritaje' => $damageRow,
        'danos' => trim((string) ($damageRow['danos_peritaje'] ?? '')),
    ];
}

$analysisFallecidoRows = [];
foreach ($personas as $persona) {
    if (!needs_occ($persona)) {
        continue;
    }

    $extras = $personaExtras[(int) ($persona['involucrado_id'] ?? 0)] ?? ['occ' => []];
    $lesiones = [];
    foreach (($extras['occ'] ?? []) as $occ) {
        $lesionProtocolo = trim((string) ($occ['lesiones_protocolo'] ?? ''));
        $lesionLevantamiento = trim((string) ($occ['lesiones_levantamiento'] ?? ''));
        if ($lesionProtocolo !== '') {
            $lesiones[] = $lesionProtocolo;
        }
        if ($lesionLevantamiento !== '') {
            $lesiones[] = $lesionLevantamiento;
        }
    }
    $lesiones = array_values(array_unique(array_filter($lesiones)));

$analysisFallecidoRows[] = [
        'nombre' => person_label($persona),
        'lesiones' => $lesiones !== [] ? implode(' | ', $lesiones) : trim((string) ($persona['lesion'] ?? '')),
    ];
}

$analysisTabCount = count($analysisDriverRows) + count($analysisFallecidoRows);
$analysisMediaBySection = [
    'danos' => [],
    'lesiones' => [],
];
if (safe_table_exists($pdo, analysis_media_table_name())) {
    $analysisMediaRows = safe_query_all(
        $pdo,
        'SELECT id, accidente_id, seccion, sort_order, archivo_path, archivo_nombre, mime_type, file_size, creado_en
           FROM ' . analysis_media_table_name() . '
          WHERE accidente_id = ?
       ORDER BY seccion ASC, sort_order ASC, id ASC',
        [$accidente_id]
    );
    foreach ($analysisMediaRows as $mediaRow) {
        $section = trim((string) ($mediaRow['seccion'] ?? ''));
        if (!isset($analysisMediaBySection[$section])) {
            continue;
        }
        $analysisMediaBySection[$section][] = $mediaRow;
    }
}

$summaryAccidentSections = [
    'Identificación' => ['registro_sidpol', 'nro_informe_policial', 'estado', 'folder', ['key' => 'comisaria_nombre', 'class' => 'span-2']],
    'Fechas' => ['fecha_accidente', 'fecha_comunicacion', 'fecha_intervencion'],
    'Ubicación' => [['key' => 'lugar', 'class' => 'span-2'], ['key' => 'ubicacion_accidente', 'class' => 'span-2'], ['key' => 'referencia', 'class' => 'span-2']],
    'Autoridades' => [['key' => 'fiscalia_nombre', 'class' => 'span-2'], ['key' => 'fiscal_nombre', 'class' => 'span-2']],
    'Comunicación' => ['comunicante_nombre', 'comunicante_telefono', 'comunicacion_decreto', ['key' => 'comunicacion_oficio', 'class' => 'span-2'], ['key' => 'comunicacion_carpeta_nro', 'class' => 'span-2']],
    'Descripción' => [['key' => 'sentido', 'class' => 'span-2'], ['key' => 'secuencia', 'class' => 'span-4']],
];
$summaryAccidentRecord = $A;
$summaryAccidentRecord['comisaria_nombre'] = $A['comisaria_nombre'] ?? ($A['comisaria_nom'] ?? ($A['comisaria'] ?? ''));
$summaryAccidentRecord['fiscalia_nombre'] = $A['fiscalia_nombre'] ?? ($A['fiscalia_nom'] ?? '');
$summaryAccidentRecord['fiscal_nombre'] = $A['fiscal_nombre'] ?? ($A['fiscal_nom'] ?? '');
$summaryManifestacionContext = [
    'fiscalia_nombre' => $summaryAccidentRecord['fiscalia_nombre'],
    'fiscal_nombre' => $summaryAccidentRecord['fiscal_nombre'],
    'fiscal_cargo' => $A['fiscal_cargo'] ?? '',
];
$summaryAccidentRecord['ubicacion_accidente'] = trim((string) implode(' / ', array_filter([
    $A['departamento_nombre'] ?? '',
    $A['provincia_nombre'] ?? '',
    $A['distrito_nombre'] ?? '',
])));

$summaryPersonSections = $policiaPersonaSections;
$summaryPersonSections['Participación'] = [
    'rol_nombre',
    'lesion',
    'orden_participacion',
    ['key' => 'veh_participacion', 'class' => 'span-2'],
    ['key' => 'involucrado_observaciones', 'class' => 'span-2'],
];
$summaryLcFields = ['clase', 'categoria', 'numero', ['key' => 'expedido_por', 'class' => 'span-2'], 'vigente_desde', 'vigente_hasta', ['key' => 'restricciones', 'class' => 'span-4']];
$summaryRmlFields = ['numero', 'fecha', 'incapacidad_medico', 'atencion_facultativo', ['key' => 'observaciones', 'class' => 'span-4']];
$summaryDosajeFields = ['numero', 'numero_registro', 'fecha_extraccion', 'resultado_cualitativo', 'resultado_cuantitativo', ['key' => 'observaciones', 'class' => 'span-4']];
$summaryManifestacionFields = ['fecha', 'horario_inicio', 'hora_termino', 'modalidad', ['key' => 'observaciones', 'class' => 'span-4']];
$summaryVehiculoFields = [
    'veh_placa', 'veh_marca', 'veh_modelo', 'veh_anio', 'veh_color',
    'veh_categoria', 'veh_tipo', 'veh_carroceria',
    ['key' => 'veh_serie_vin', 'class' => 'span-2'],
    ['key' => 'veh_nro_motor', 'class' => 'span-2'],
    'veh_largo_mm', 'veh_ancho_mm', 'veh_alto_mm',
    ['key' => 'veh_notas', 'class' => 'span-2'],
];
$summaryDocVehiculoSections = [
    'propiedad' => [
        'label' => 'Tarjeta de propiedad',
        'fields' => ['numero_propiedad', 'titulo_propiedad', 'partida_propiedad', 'sede_propiedad'],
    ],
    'soat' => [
        'label' => 'SOAT',
        'fields' => ['numero_soat', 'aseguradora_soat', 'vigente_soat', 'vencimiento_soat'],
    ],
    'peritaje' => [
        'label' => 'Peritaje',
        'fields' => [
            'numero_peritaje', 'fecha_peritaje', 'perito_peritaje',
            'sistema_electrico_peritaje', 'sistema_frenos_peritaje', 'sistema_direccion_peritaje',
            'sistema_transmision_peritaje', 'sistema_suspension_peritaje', 'planta_motriz_peritaje',
            ['key' => 'otros_peritaje', 'class' => 'span-2'],
            ['key' => 'danos_peritaje', 'class' => 'span-2'],
        ],
    ],
    'revision' => [
        'label' => 'Revisión técnica',
        'fields' => ['numero_revision', 'certificadora_revision', 'vigente_revision', 'vencimiento_revision'],
    ],
];
$summaryOccLevantamientoFields = [
    'fecha_levantamiento', 'hora_levantamiento', ['key' => 'lugar_levantamiento', 'class' => 'span-2'],
    'posicion_cuerpo_levantamiento', ['key' => 'lesiones_levantamiento', 'class' => 'span-2'],
    'presuntivo_levantamiento', 'legista_levantamiento', 'cmp_legista',
    ['key' => 'observaciones_levantamiento', 'class' => 'span-4'],
];
$summaryOccPericialFields = ['numero_pericial', 'fecha_pericial', 'hora_pericial', ['key' => 'observaciones_pericial', 'class' => 'span-4']];
$summaryOccProtocoloFields = [
    'numero_protocolo', 'fecha_protocolo', 'hora_protocolo',
    ['key' => 'lesiones_protocolo', 'class' => 'span-2'],
    'presuntivo_protocolo', 'dosaje_protocolo', 'toxicologico_protocolo',
];
$summaryOccEpicrisisFields = [
    ['key' => 'nosocomio_epicrisis', 'class' => 'span-2'],
    'numero_historia_epicrisis',
    'hora_alta_epicrisis',
    ['key' => 'tratamiento_epicrisis', 'class' => 'span-4'],
];
$summaryOwnerRecordFields = ['tipo_propietario', 'rol_legal', ['key' => 'ruc', 'class' => 'span-2'], ['key' => 'razon_social', 'class' => 'span-2'], ['key' => 'domicilio_fiscal', 'class' => 'span-2'], ['key' => 'observaciones', 'class' => 'span-2']];
$summaryFamiliarRecordFields = ['parentesco', ['key' => 'observaciones', 'class' => 'span-4']];
$summaryOficioFields = ['numero', 'anio', 'fecha_emision', 'estado', ['key' => 'entidad', 'class' => 'span-2'], ['key' => 'asunto_nombre', 'class' => 'span-2'], ['key' => 'referencia_texto', 'class' => 'span-4'], ['key' => 'motivo', 'class' => 'span-4'], 'veh_ut', 'veh_placa', ['key' => 'persona_nombre', 'class' => 'span-2']];

$summaryUnits = [];
foreach ($comboVehiculosRows as $comboVehiculo) {
    $ut = trim((string) ($comboVehiculo['orden_participacion'] ?? ''));
    if ($ut === '') {
        continue;
    }

    $summaryUnits[$ut] ??= ['ut' => $ut, 'vehiculos' => [], 'personas' => []];
    $summaryUnits[$ut]['vehiculos'][(int) ($comboVehiculo['inv_vehiculo_id'] ?? 0)] = $comboVehiculo;
}
foreach ($personas as $persona) {
    $ut = trim((string) ($persona['orden_participacion'] ?? ''));
    $hasVehicle = (int) ($persona['vehiculo_id'] ?? 0) > 0;

    if ($ut !== '' && $hasVehicle) {
        $summaryUnits[$ut] ??= ['ut' => $ut, 'vehiculos' => [], 'personas' => []];
        $summaryUnits[$ut]['personas'][] = $persona;

        $invVehiculoId = (int) ($persona['inv_vehiculo_id'] ?? 0);
        if ($invVehiculoId > 0 && !isset($summaryUnits[$ut]['vehiculos'][$invVehiculoId])) {
            $summaryUnits[$ut]['vehiculos'][$invVehiculoId] = vehicle_summary_record($persona);
        }
    }
}
uasort($summaryUnits, static fn(array $a, array $b): int => ut_sort_index($a['ut'] ?? '') <=> ut_sort_index($b['ut'] ?? ''));
foreach ($summaryUnits as &$summaryUnit) {
    $summaryUnit['vehiculos'] = array_values($summaryUnit['vehiculos']);
    usort($summaryUnit['vehiculos'], static function (array $a, array $b): int {
        return strcmp((string) ($a['veh_numero'] ?? ''), (string) ($b['veh_numero'] ?? ''));
    });
    usort($summaryUnit['personas'], static function (array $a, array $b): int {
        $priority = person_summary_priority($a) <=> person_summary_priority($b);
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
    $ut = trim((string) ($persona['orden_participacion'] ?? ''));
    $hasVehicle = (int) ($persona['vehiculo_id'] ?? 0) > 0;
    if ($ut !== '' && $hasVehicle) {
        continue;
    }

    if (str_contains(role_key($persona), 'peat')) {
        $summaryPeatones[] = $persona;
        continue;
    }

    $summaryOtrosSinUnidad[] = $persona;
}
$sortSummaryPeople = static function (array &$rows): void {
    usort($rows, static function (array $a, array $b): int {
        $priority = person_summary_priority($a) <=> person_summary_priority($b);
        if ($priority !== 0) {
            return $priority;
        }

        return strcmp((string) ($a['orden_persona'] ?? 'Z'), (string) ($b['orden_persona'] ?? 'Z'));
    });
};
$sortSummaryPeople($summaryPeatones);
$sortSummaryPeople($summaryOtrosSinUnidad);

$summaryBlocksCount = 1
    + count($summaryUnits)
    + (count($summaryPeatones) > 0 ? 1 : 0)
    + (count($summaryOtrosSinUnidad) > 0 ? 1 : 0)
    + (count($policias) > 0 ? 1 : 0)
    + (count($familiares) > 0 ? 1 : 0)
    + (count($propietarios) > 0 ? 1 : 0)
    + (count($abogados) > 0 ? 1 : 0)
    + (count($oficios) > 0 ? 1 : 0);

$itpResumen = $itps[0] ?? [];
$resumenComisaria = compact_text((string) ($summaryAccidentRecord['comisaria_nombre'] ?? ''));
if ($resumenComisaria !== '' && mb_stripos($resumenComisaria, 'comisaria', 0, 'UTF-8') === false) {
    $resumenComisaria = 'Comisaría PNP ' . $resumenComisaria;
}
$resumenLugarJurisdiccion = join_location_parts([
    compact_text((string) ($A['lugar'] ?? '')),
    $resumenComisaria,
]);
$claseViaZonaParts = unique_text_values([
    title_text((string) ($itpResumen['forma_via'] ?? '')),
    title_text((string) ($itpResumen['configuracion_via1'] ?? '')),
]);
if ($claseViaZonaParts === []) {
    $claseViaZonaParts = unique_text_values([
        title_text((string) ($itpResumen['descripcion_via1'] ?? '')),
    ]);
}
$resumenClaseViaZona = $claseViaZonaParts !== [] ? implode('-', $claseViaZonaParts) : '—';
$resumenInterventionRows = [
    ['index' => '1.', 'label' => 'SIDPOL N°', 'value' => compact_text((string) ($A['sidpol'] ?? '')) ?: '—', 'copy_text' => compact_text((string) ($A['sidpol'] ?? '')) ?: '—'],
    ['index' => '2.', 'label' => 'Informe N°', 'value' => compact_text((string) ($A['nro_informe_policial'] ?? '')) ?: '—', 'copy_text' => compact_text((string) ($A['nro_informe_policial'] ?? '')) ?: '—'],
    ['index' => '3.', 'label' => 'Decreto N°', 'value' => compact_text((string) ($A['comunicacion_decreto'] ?? '')) ?: '—', 'copy_text' => compact_text((string) ($A['comunicacion_decreto'] ?? '')) ?: '—'],
    ['index' => '4.', 'label' => 'Carpeta Fiscal N°', 'value' => compact_text((string) ($A['comunicacion_carpeta_nro'] ?? '')) ?: '—', 'copy_text' => compact_text((string) ($A['comunicacion_carpeta_nro'] ?? '')) ?: '—'],
    ['index' => '5.', 'label' => 'Clase de accidente', 'value' => join_con_y_text($modalidades) ?: '—', 'copy_text' => join_con_y_text($modalidades) ?: '—'],
    ['index' => '6.', 'label' => 'Consecuencia', 'value' => join_con_y_text($consecuencias) ?: '—', 'copy_text' => join_con_y_text($consecuencias) ?: '—'],
    ['index' => '7.', 'label' => 'Lugar y jurisdicción policial', 'value' => $resumenLugarJurisdiccion !== '' ? $resumenLugarJurisdiccion : '—', 'copy_text' => $resumenLugarJurisdiccion !== '' ? $resumenLugarJurisdiccion : '—'],
    ['index' => '8.', 'label' => 'Fecha y hora del accidente', 'value' => fecha_hora_aprox_text($A['fecha_accidente'] ?? null), 'copy_text' => fecha_hora_aprox_text($A['fecha_accidente'] ?? null)],
    ['index' => '9.', 'label' => 'Fecha y hora de comunicación', 'value' => fecha_hora_aprox_text($A['fecha_comunicacion'] ?? null), 'copy_text' => fecha_hora_aprox_text($A['fecha_comunicacion'] ?? null)],
    ['index' => '10.', 'label' => 'Fecha y hora de intervención', 'value' => fecha_hora_aprox_text($A['fecha_intervencion'] ?? null), 'copy_text' => fecha_hora_aprox_text($A['fecha_intervencion'] ?? null)],
    ['index' => '11.', 'label' => 'Unidades participantes', 'value_html' => summary_units_intervention_html($summaryUnits, $summaryPeatones, $summaryOtrosSinUnidad), 'copy_text' => summary_units_intervention_text($summaryUnits, $summaryPeatones, $summaryOtrosSinUnidad)],
    ['index' => '12.', 'label' => 'Clase de vía y zona', 'value' => $resumenClaseViaZona, 'copy_text' => $resumenClaseViaZona],
    ['index' => '13.', 'label' => 'Fiscalía', 'value' => compact_text((string) ($summaryAccidentRecord['fiscalia_nombre'] ?? '')) ?: '—', 'copy_text' => compact_text((string) ($summaryAccidentRecord['fiscalia_nombre'] ?? '')) ?: '—'],
    ['index' => '14.', 'label' => 'Fiscal a cargo', 'value' => compact_text((string) ($summaryAccidentRecord['fiscal_nombre'] ?? '')) ?: '—', 'copy_text' => compact_text((string) ($summaryAccidentRecord['fiscal_nombre'] ?? '')) ?: '—'],
    ['index' => '15.', 'label' => 'Sentido', 'value' => compact_text((string) ($A['sentido'] ?? '')) ?: '—', 'copy_text' => compact_text((string) ($A['sentido'] ?? '')) ?: '—'],
    ['index' => '16.', 'label' => 'Secuencia', 'value' => compact_text((string) ($A['secuencia'] ?? '')) ?: '—', 'copy_text' => compact_text((string) ($A['secuencia'] ?? '')) ?: '—'],
];

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Accidente · Vista por pestañas</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{
    --page:#f5f7fb;
    --card:#ffffff;
    --line:#d9e1ee;
    --muted:#6f7b8e;
    --ink:#243246;
    --title-blue:#3a628f;
    --title-blue-soft:#617792;
    --gold:#9a7a1b;
    --gold-soft:#fff8e2;
    --blue:#3257a8;
    --chip:#eef2f8;
    --page-grad-start:#f7f9fc;
    --page-grad-end:#eef3fa;
  }
  html[data-theme-resolved="dark"]{
    --page:#0b1220;
    --card:#0f172a;
    --line:#263247;
    --muted:#94a3b8;
    --ink:#e5edf8;
    --title-blue:#93c5fd;
    --title-blue-soft:#bfd4ef;
    --gold:#f0c654;
    --gold-soft:#2a2312;
    --blue:#60a5fa;
    --chip:#131d33;
    --page-grad-start:#0a1220;
    --page-grad-end:#101a2d;
  }
  body{background:linear-gradient(180deg,var(--page-grad-start) 0%,var(--page-grad-end) 100%);color:var(--ink);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
  .page{max-width:1380px;margin:10px auto;padding:0 10px 14px}
  .topbar{display:flex;justify-content:space-between;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:6px}
  .title-wrap h1{margin:0;font-size:18px;font-weight:800;letter-spacing:-.02em;color:var(--title-blue)}
  .title-wrap p{margin:0;color:var(--muted);font-size:11px}
  .top-actions{display:flex;gap:6px;flex-wrap:wrap}
  .btn-shell{display:inline-flex;align-items:center;gap:5px;padding:6px 9px;border-radius:9px;border:1px solid var(--line);background:var(--card);color:var(--ink);text-decoration:none;font-weight:700;font-size:11px;line-height:1.1;box-shadow:0 5px 14px rgba(17,24,39,.05)}
  .btn-shell.btn-nuevo{border-color:#0b9f98;background:linear-gradient(180deg,#0b8f8b 0%,#08a7a0 42%,#08cfc6 100%);box-shadow:0 0 0 1px rgba(11,159,152,.28),0 0 16px rgba(8,167,160,.24),0 8px 18px rgba(6,95,92,.18);color:#fff;font-family:"Palatino Linotype","Book Antiqua",Georgia,serif;font-size:11.5px;letter-spacing:.02em;text-shadow:0 1px 0 rgba(0,0,0,.12)}
  .btn-shell.btn-nuevo:hover{border-color:#087f79;background:linear-gradient(180deg,#087f79 0%,#09938d 42%,#06bbb3 100%);color:#fff;box-shadow:0 0 0 1px rgba(8,127,121,.34),0 0 18px rgba(8,167,160,.28),0 10px 20px rgba(6,95,92,.22)}
  .btn-shell.btn-citacion{border-color:#60a5fa;background:linear-gradient(180deg,#f8fbff 0%,#edf5ff 100%);box-shadow:0 0 0 1px rgba(96,165,250,.18),0 8px 18px rgba(59,130,246,.12);color:#1d4ed8}
  .btn-shell.btn-citacion:hover{border-color:#3b82f6;background:#e0efff;color:#1e40af}
  .btn-shell.btn-peritaje{border-color:#ff9f43;background:linear-gradient(180deg,#fffaf3 0%,#fff1df 100%);box-shadow:0 0 0 1px rgba(255,159,67,.24),0 0 14px rgba(255,140,0,.22),0 8px 18px rgba(255,140,0,.12);color:#c2410c}
  .btn-shell.btn-peritaje:hover{border-color:#ff7a00;background:#ffedd5;color:#9a3412;box-shadow:0 0 0 1px rgba(255,122,0,.32),0 0 18px rgba(255,122,0,.28),0 10px 20px rgba(255,122,0,.16)}
  .btn-shell.btn-necropsia{border-color:#14b8a6;background:linear-gradient(180deg,#f1fffd 0%,#dcfdf7 100%);box-shadow:0 0 0 1px rgba(20,184,166,.22),0 0 14px rgba(13,148,136,.18),0 8px 18px rgba(15,118,110,.12);color:#0f766e}
  .btn-shell.btn-necropsia:hover{border-color:#0d9488;background:#ccfbf1;color:#115e59;box-shadow:0 0 0 1px rgba(13,148,136,.3),0 0 18px rgba(13,148,136,.22),0 10px 20px rgba(15,118,110,.15)}
  .btn-shell.btn-docx{border-color:#a855f7;border-radius:8px;background:linear-gradient(180deg,#faf5ff 0%,#f3e8ff 100%);box-shadow:0 0 0 1px rgba(168,85,247,.34),0 0 16px rgba(168,85,247,.35),0 8px 18px rgba(109,40,217,.14);color:#6d28d9;text-shadow:0 0 10px rgba(168,85,247,.22)}
  .btn-shell.btn-docx:hover{border-color:#d946ef;background:linear-gradient(180deg,#f5d0fe 0%,#e9d5ff 100%);box-shadow:0 0 0 1px rgba(217,70,239,.44),0 0 22px rgba(217,70,239,.42),0 10px 22px rgba(109,40,217,.2);color:#581c87}
  .panel{background:rgba(255,255,255,.92);border:1px solid var(--line);border-radius:18px;padding:8px;box-shadow:0 10px 26px rgba(17,24,39,.08);backdrop-filter:blur(8px)}
  .summary-stack{display:grid;gap:5px;margin-bottom:6px}
  .summary-pill{background:#f2f4f8;border:1px dashed var(--line);border-radius:11px;padding:7px 10px;font-size:12px;font-weight:600;line-height:1.25;color:#425166}
  .summary-pill strong{color:#8b6a12;display:inline-block;min-width:150px}
  .section-title{margin:0 0 5px;color:var(--title-blue);font-weight:800;font-size:13px;letter-spacing:.01em}
  .general-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:6px}
  .ident-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:6px}
  .g-3{grid-column:span 3}.g-4{grid-column:span 4}.g-6{grid-column:span 6}.g-12{grid-column:span 12}
  .data-card{background:#f7f8fb;border:1px solid var(--line);border-radius:11px;padding:6px 9px;min-height:54px}
  .data-card.highlight{border-color:#dfb94d;background:linear-gradient(180deg,#fffdf7 0%,#fff7df 100%)}
  .data-card.centered{text-align:center;display:flex;flex-direction:column;justify-content:center}
  .data-card .label{font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:#8b6a12;margin-bottom:2px}
  .data-card .value{font-size:12px;line-height:1.18;font-weight:700;word-break:break-word;color:#2d3c52}
  .data-card .value.status-pendiente{color:#c81e1e}
  .data-card .value.status-resuelto{color:#19734d}
  .data-card .value.status-diligencias{color:#9a6a00}
  .quick-status-select{width:100%;max-width:170px;margin:0 auto;border:1px solid #d5ddeb;border-radius:10px;background:#fff;padding:5px 9px;font-size:12px;font-weight:700;color:#314157;text-align:center;text-align-last:center}
  .quick-status-select:focus{outline:none;border-color:#d6b44c;box-shadow:0 0 0 3px rgba(214,180,76,.16)}
  .module-status-select{border:1px solid #d5ddeb;border-radius:999px;background:#fff;padding:5px 10px;font-size:11px;font-weight:700;line-height:1.1;color:#314157;min-width:126px;text-align:center;text-align-last:center}
  .module-status-select:focus{outline:none;border-color:#d6b44c;box-shadow:0 0 0 3px rgba(214,180,76,.14)}
  .module-status-select.status-borrador{border-color:#cbd5e1;background:#f8fafc;color:#475569}
  .module-status-select.status-firmado{border-color:#93c5fd;background:#eff6ff;color:#1d4ed8}
  .module-status-select.status-enviado{border-color:#86efac;background:#ecfdf5;color:#166534}
  .module-status-select.status-anulado{border-color:#fca5a5;background:#fff1f2;color:#b91c1c}
  .module-status-select.status-archivado{border-color:#d8b4fe;background:#faf5ff;color:#7c3aed}
  .line-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}
  .line-card{background:#f7f8fb;border:1px solid var(--line);border-radius:11px;padding:6px 9px;font-size:12px;font-weight:700;line-height:1.2;color:#314157}
  .line-card strong{color:#8b6a12}
  .tabs-shell{margin-top:10px}
  .tabs-toolbar{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:0 0 6px}
  .tabs-header{display:flex;gap:6px;overflow:auto;padding-bottom:6px}
  .tabs-header .nav-link{border:1px solid var(--line);background:#eef2f8;color:#4a5870;border-radius:10px;padding:7px 9px;font-weight:700;font-size:12px;line-height:1.1;white-space:nowrap}
  .tabs-header .nav-link.active{background:linear-gradient(180deg,#fff5cf 0%,#ffe7a0 100%);border-color:#e7c75c;color:#6f5410}
  .main-tabs{gap:10px;padding:8px 6px 10px;margin-bottom:8px;border-bottom:1px solid rgba(188,198,216,.7)}
  .main-tabs .nav-link{
    position:relative;
    overflow:hidden;
    min-width:140px;
    border-radius:18px;
    padding:13px 18px 12px;
    border:1px solid #d7dfec;
    background:
      linear-gradient(180deg,rgba(255,255,255,.95) 0%,rgba(241,245,252,.94) 100%);
    color:#44546d;
    box-shadow:
      0 12px 28px rgba(17,24,39,.07),
      inset 0 1px 0 rgba(255,255,255,.88);
    transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease, color .18s ease;
  }
  .main-tabs .nav-link::before{
    content:"";
    position:absolute;
    inset:0 auto 0 0;
    width:5px;
    border-radius:18px 0 0 18px;
    background:linear-gradient(180deg,#7c93c6 0%,#516b9f 100%);
    opacity:.72;
  }
  .main-tabs .nav-link::after{
    content:"";
    position:absolute;
    inset:auto 16px 8px;
    height:3px;
    border-radius:999px;
    background:linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.88) 50%,rgba(255,255,255,0) 100%);
    opacity:0;
    transform:scaleX(.55);
    transition:opacity .18s ease, transform .18s ease;
  }
  .main-tabs .nav-link:hover{
    transform:translateY(-2px);
    border-color:#c5d2e5;
    color:#22344f;
    box-shadow:
      0 16px 34px rgba(17,24,39,.11),
      0 0 0 1px rgba(148,163,184,.08) inset;
  }
  .main-tabs .nav-link.active{
    color:#2c3b51;
    border-color:transparent;
    background:
      linear-gradient(135deg,rgba(255,255,255,.98) 0%,rgba(240,247,255,.96) 38%,rgba(230,241,255,.98) 100%);
    box-shadow:
      0 18px 38px rgba(37,99,235,.14),
      0 0 0 1px rgba(255,255,255,.55) inset;
  }
  .main-tabs .nav-link.active::after{
    opacity:1;
    transform:scaleX(1);
  }
  .main-tabs .nav-link.tab-itp::before{background:linear-gradient(180deg,#0f766e 0%,#14b8a6 100%)}
  .main-tabs .nav-link.tab-participantes::before{background:linear-gradient(180deg,#2563eb 0%,#60a5fa 100%)}
  .main-tabs .nav-link.tab-documentos::before{background:linear-gradient(180deg,#b7791f 0%,#f6c453 100%)}
  .main-tabs .nav-link.tab-diligencias::before{background:linear-gradient(180deg,#7c3aed 0%,#a78bfa 100%)}
  .main-tabs .nav-link.tab-analisis::before{background:linear-gradient(180deg,#0f766e 0%,#22c55e 100%)}
  .main-tabs .nav-link.tab-itp.active{
    background:linear-gradient(135deg,#f2fffc 0%,#dbfaf3 48%,#c8f4ec 100%);
    box-shadow:0 18px 38px rgba(20,184,166,.17), 0 0 0 1px rgba(255,255,255,.58) inset;
  }
  .main-tabs .nav-link.tab-participantes.active{
    background:linear-gradient(135deg,#f5f9ff 0%,#e5f0ff 48%,#d8e8ff 100%);
    box-shadow:0 18px 38px rgba(37,99,235,.16), 0 0 0 1px rgba(255,255,255,.58) inset;
  }
  .main-tabs .nav-link.tab-documentos.active{
    background:linear-gradient(135deg,#fffaf0 0%,#ffefc1 52%,#ffe39c 100%);
    box-shadow:0 18px 38px rgba(217,119,6,.18), 0 0 0 1px rgba(255,255,255,.52) inset;
  }
  .main-tabs .nav-link.tab-diligencias.active{
    background:linear-gradient(135deg,#faf5ff 0%,#f0e8ff 50%,#e5d8ff 100%);
    box-shadow:0 18px 38px rgba(124,58,237,.16), 0 0 0 1px rgba(255,255,255,.56) inset;
  }
  .main-tabs .nav-link.tab-analisis.active{
    background:linear-gradient(135deg,#f3fff7 0%,#e0fbe8 50%,#cbf7d8 100%);
    box-shadow:0 18px 38px rgba(34,197,94,.16), 0 0 0 1px rgba(255,255,255,.56) inset;
  }
  .main-tabs .main-tab-title{
    display:block;
    margin-bottom:3px;
    font-size:16px;
    font-weight:800;
    letter-spacing:-.02em;
  }
  .main-tabs .tab-sub{
    display:flex;
    align-items:center;
    gap:6px;
    font-size:11px;
    font-weight:700;
    letter-spacing:.01em;
    opacity:.72;
  }
  .main-tabs .tab-sub::before{
    content:"";
    width:7px;
    height:7px;
    border-radius:999px;
    background:currentColor;
    opacity:.4;
    flex:0 0 auto;
  }
  .tabs-header .nav-link.tab-driver{border-color:#44f7b2;background:linear-gradient(180deg,#f2fff9 0%,#ecfff7 100%);color:#0e7a5a;box-shadow:0 0 0 1px rgba(68,247,178,.08) inset}
  .tabs-header .nav-link.tab-driver.active{background:linear-gradient(180deg,#dcfff0 0%,#c4ffe6 100%);border-color:#22e39d;color:#0a7f57;box-shadow:0 0 0 1px rgba(34,227,157,.18) inset, 0 8px 18px rgba(34,227,157,.16)}
  .tabs-header .nav-link.tab-herido{border-color:#f2d15e;background:linear-gradient(180deg,#fffdf1 0%,#fff9df 100%);color:#9a7300;box-shadow:0 0 0 1px rgba(242,209,94,.08) inset}
  .tabs-header .nav-link.tab-herido.active{background:linear-gradient(180deg,#fff1b8 0%,#ffe89a 100%);border-color:#e0ba36;color:#8a6500;box-shadow:0 0 0 1px rgba(224,186,54,.16) inset, 0 8px 18px rgba(224,186,54,.16)}
  .tabs-header .nav-link.tab-occiso{border-color:#efb0b0;background:linear-gradient(180deg,#fff6f6 0%,#fff0f0 100%);color:#8f2121}
  .tabs-header .nav-link.tab-occiso.active{background:linear-gradient(180deg,#ffe3e3 0%,#ffcaca 100%);border-color:#df6a6a;color:#8f1111;box-shadow:0 0 0 1px rgba(223,106,106,.12) inset, 0 8px 18px rgba(185,28,28,.10)}
  .tabs-header .tab-sub{display:block;font-size:9px;font-weight:700;opacity:.75;margin-top:1px}
  .participant-tabs{margin-bottom:6px}
  .participant-tabs .nav-link{border:1px solid var(--line);background:#eef2f8;color:#4a5870;border-radius:10px;padding:7px 9px;font-weight:700;font-size:12px;line-height:1.1;white-space:nowrap}
  .participant-tabs .nav-link.active{background:linear-gradient(180deg,#fff5cf 0%,#ffe7a0 100%);border-color:#e7c75c;color:#6f5410}
  .participant-tabs .nav-link.tab-driver{border-color:#44f7b2;background:linear-gradient(180deg,#f2fff9 0%,#ecfff7 100%);color:#0e7a5a;box-shadow:0 0 0 1px rgba(68,247,178,.08) inset}
  .participant-tabs .nav-link.tab-driver.active{background:linear-gradient(180deg,#dcfff0 0%,#c4ffe6 100%);border-color:#22e39d;color:#0a7f57;box-shadow:0 0 0 1px rgba(34,227,157,.18) inset, 0 8px 18px rgba(34,227,157,.16)}
  .participant-tabs .nav-link.tab-herido{border-color:#f2d15e;background:linear-gradient(180deg,#fffdf1 0%,#fff9df 100%);color:#9a7300;box-shadow:0 0 0 1px rgba(242,209,94,.08) inset}
  .participant-tabs .nav-link.tab-herido.active{background:linear-gradient(180deg,#fff1b8 0%,#ffe89a 100%);border-color:#e0ba36;color:#8a6500;box-shadow:0 0 0 1px rgba(224,186,54,.16) inset, 0 8px 18px rgba(224,186,54,.16)}
  .participant-tabs .nav-link.tab-occiso{border-color:#efb0b0;background:linear-gradient(180deg,#fff6f6 0%,#fff0f0 100%);color:#8f2121}
  .participant-tabs .nav-link.tab-occiso.active{background:linear-gradient(180deg,#ffe3e3 0%,#ffcaca 100%);border-color:#df6a6a;color:#8f1111;box-shadow:0 0 0 1px rgba(223,106,106,.12) inset, 0 8px 18px rgba(185,28,28,.10)}
  .participant-tabs .tab-sub{display:block;font-size:9px;font-weight:700;opacity:.75;margin-top:1px}
  .tab-panel{position:relative;overflow:hidden;background:rgba(255,255,255,.94);border:1px solid var(--line);border-radius:16px;padding:11px}
  .tab-panel::before,.tab-panel::after{z-index:0}
  .tab-panel > *{position:relative;z-index:1}
  .tab-panel.driver-panel{
    border-color:#44f7b2;
    background:
      linear-gradient(180deg,rgba(241,255,250,.98) 0%,rgba(255,255,255,.96) 42%,rgba(248,255,252,.96) 100%);
    box-shadow:
      0 12px 30px rgba(17,24,39,.08),
      0 0 0 1px rgba(68,247,178,.18) inset,
      0 0 22px rgba(55,255,179,.24),
      0 0 44px rgba(55,255,179,.14);
  }
  .tab-panel.driver-panel::before{
    content:"";
    position:absolute;
    inset:-40% auto auto -18%;
    width:58%;
    height:180%;
    transform:rotate(18deg);
    background:linear-gradient(180deg,rgba(255,255,255,.0) 0%,rgba(255,255,255,.34) 35%,rgba(255,255,255,.08) 72%,rgba(255,255,255,0) 100%);
    filter:blur(4px);
    pointer-events:none;
    animation:driverSheen 7.5s ease-in-out infinite;
  }
  .tab-panel.driver-panel::after{
    content:"";
    position:absolute;
    inset:0;
    border-radius:inherit;
    pointer-events:none;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.86), inset 0 -1px 0 rgba(55,255,179,.16);
  }
  .tab-panel.driver-panel .person-title h2{
    text-shadow:0 1px 0 rgba(255,255,255,.55);
  }
  .tab-panel.herido-panel{
    border-color:#e0ba36;
    background:
      linear-gradient(180deg,rgba(255,252,235,.98) 0%,rgba(255,255,255,.96) 42%,rgba(255,252,244,.96) 100%);
    box-shadow:
      0 12px 30px rgba(17,24,39,.08),
      0 0 0 1px rgba(224,186,54,.14) inset,
      0 0 20px rgba(224,186,54,.14);
  }
  .tab-panel.herido-panel::after{
    content:"";
    position:absolute;
    inset:0;
    border-radius:inherit;
    pointer-events:none;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.86), inset 0 -1px 0 rgba(224,186,54,.14);
  }
  .tab-panel.occiso-panel{
    border-color:#e06a6a;
    background:
      linear-gradient(180deg,rgba(255,245,245,.98) 0%,rgba(255,255,255,.96) 42%,rgba(255,248,248,.96) 100%);
    box-shadow:
      0 12px 30px rgba(17,24,39,.08),
      0 0 0 1px rgba(224,106,106,.16) inset,
      0 0 20px rgba(220,38,38,.16);
  }
  .tab-panel.occiso-panel::after{
    content:"";
    position:absolute;
    inset:0;
    border-radius:inherit;
    pointer-events:none;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.86), inset 0 -1px 0 rgba(220,38,38,.14);
  }
  @keyframes driverSheen{
    0%, 100%{transform:translateX(-18%) rotate(18deg);opacity:.52}
    50%{transform:translateX(138%) rotate(18deg);opacity:.82}
  }
  .person-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap;margin-bottom:8px}
  .person-title h2{margin:0;font-size:17px;font-weight:800;line-height:1.15;color:var(--title-blue);letter-spacing:-.01em;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .person-title p{margin:3px 0 0;color:var(--muted);font-weight:600;font-size:12px}
  .person-name-copy{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
  .person-quick-actions{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
  .copy-name-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:30px;padding:0 10px;border:1px solid #cfd8e7;border-radius:999px;background:#fff;color:#4d5b72;font-size:12px;font-weight:700;line-height:1;box-shadow:0 6px 16px rgba(17,24,39,.06);transition:background .16s ease,border-color .16s ease,color .16s ease,transform .16s ease}
  .copy-name-btn:hover{background:#f6f9ff;border-color:#b8cae7;color:#234a84}
  .copy-name-btn.is-copied{background:#e8f7ef;border-color:#86d6a4;color:#166534}
  .quick-pill-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:30px;padding:0 10px;border:1px solid #cfd8e7;border-radius:999px;background:#fff;color:#4d5b72;font-size:12px;font-weight:700;line-height:1;text-decoration:none;box-shadow:0 6px 16px rgba(17,24,39,.06);transition:background .16s ease,border-color .16s ease,color .16s ease,transform .16s ease}
  .quick-pill-btn:hover{background:#f6f9ff;border-color:#b8cae7;color:#234a84}
  .quick-pill-btn.whatsapp{border-color:#9fe0b7;background:#f0fff5;color:#178248}
  .quick-pill-btn.whatsapp:hover{background:#25d366;color:#fff;border-color:#25d366}
  .quick-pill-btn.download{border-color:#a855f7;border-radius:8px;background:linear-gradient(180deg,#faf5ff 0%,#f3e8ff 100%);box-shadow:0 0 0 1px rgba(168,85,247,.32),0 0 16px rgba(168,85,247,.34),0 8px 18px rgba(109,40,217,.14);color:#6d28d9;text-shadow:0 0 10px rgba(168,85,247,.22)}
  .quick-pill-btn.download:hover{background:linear-gradient(180deg,#f5d0fe 0%,#e9d5ff 100%);color:#581c87;border-color:#d946ef;box-shadow:0 0 0 1px rgba(217,70,239,.44),0 0 22px rgba(217,70,239,.42),0 10px 22px rgba(109,40,217,.2)}
  .chip-row{display:flex;flex-wrap:wrap;gap:6px}
  .chip-role,.chip-status,.chip-simple{display:inline-flex;align-items:center;padding:3px 7px;border-radius:999px;border:1px solid var(--line);font-size:10px;font-weight:700;background:#fff;line-height:1.1}
  .chip-conductor{background:#e8fbef;border-color:#b7e6c3;color:#19734d}
  .chip-peaton{background:#e8f1ff;border-color:#bed3ff;color:#2157c5}
  .chip-pasajero{background:#f2f3f7;border-color:#d7dce5;color:#374151}
  .chip-ocupante{background:#f4ebff;border-color:#ddc8ff;color:#6b3cc6}
  .chip-testigo{background:#fff0df;border-color:#ffd7ae;color:#a04f00}
  .chip-status-ok{background:#e8fbef;border-color:#b7e6c3;color:#19734d}
  .chip-status-warn{background:#fff7db;border-color:#ffe08a;color:#996700}
  .chip-status-danger{background:#ffe8e8;border-color:#ffb6b6;color:#c81e1e}
  .action-row{display:flex;gap:8px;flex-wrap:wrap}
  .section-block{margin-top:8px}
  .section-block h3{margin:0 0 5px;font-size:12px;font-weight:800;color:var(--title-blue);letter-spacing:.01em}
  .persona-story-block{padding:14px 16px;border:1px solid #f2c7c7;border-radius:14px;background:linear-gradient(180deg,#fffdfd 0%,#fff6f6 100%);box-shadow:0 10px 24px rgba(127,29,29,.06)}
  .persona-story-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0 0 8px}
  .persona-story-block h3.persona-story-title{margin:0;font-size:14px !important;font-weight:900;line-height:1.1;color:#111827 !important;letter-spacing:-.02em}
  .persona-story-block p.persona-story-text{margin:0;color:#111827 !important;font-size:14px !important;line-height:1.5;font-weight:500}
  .persona-story-name{display:inline-block;padding:0 6px;background:rgba(200,30,30,.12);font-weight:900}
  .vehicle-story-block{padding:14px 16px;border:1px solid #f2c7c7;border-radius:14px;background:linear-gradient(180deg,#fffdfd 0%,#fff6f6 100%);box-shadow:0 10px 24px rgba(127,29,29,.06)}
  .vehicle-story-title{margin:0 0 12px;font-size:14px;font-weight:900;line-height:1.15;color:#c81e1e;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:5px}
  .vehicle-story-section + .vehicle-story-section{margin-top:14px}
  .vehicle-story-subtitle{margin:0 0 8px;font-size:14px;font-weight:900;line-height:1.15;color:#c81e1e;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .vehicle-story-list{display:grid;gap:4px}
  .vehicle-story-row{display:grid;grid-template-columns:minmax(150px,220px) 18px minmax(0,1fr);gap:10px;align-items:start}
  .vehicle-story-key,.vehicle-story-sep,.vehicle-story-value{font-size:14px;line-height:1.3}
  .vehicle-story-key{font-weight:700;color:#111827}
  .vehicle-story-sep{font-weight:900;text-align:center;color:#111827}
  .vehicle-story-value{font-weight:500;color:#111827}
  .vehicle-story-value-wrap{display:inline-flex;align-items:flex-start;gap:8px;flex-wrap:wrap}
  .copy-inline-btn{min-width:24px;height:24px;padding:0 6px;border-radius:8px;font-size:12px;box-shadow:0 4px 10px rgba(17,24,39,.06)}
  .vehicle-docs-story-block{padding:10px 0 0}
  .vehicle-docs-story-title{margin:0 0 8px;font-size:14px;font-weight:900;line-height:1.15;color:#111827;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .vehicle-docs-story-list{display:grid;gap:6px}
  .vehicle-docs-story-item{display:flex;align-items:flex-start;gap:8px}
  .vehicle-docs-story-text{margin:0;color:#111827;font-size:14px;line-height:1.45;font-weight:500;flex:1}
  .license-story-block{display:grid;gap:10px}
  .license-story-item{padding:10px 12px;border:1px solid #f2c7c7;border-radius:8px;background:linear-gradient(180deg,#fffdfd 0%,#fff6f6 100%)}
  .license-story-title{margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:900;line-height:1.2;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .license-story-line{display:flex;align-items:flex-start;gap:8px}
  .license-story-text{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600;flex:1}
  .dosage-story-block{display:grid;gap:10px}
  .dosage-story-item{padding:10px 12px;border:1px solid #f2c7c7;border-radius:8px;background:linear-gradient(180deg,#fffdfd 0%,#fff6f6 100%)}
  .dosage-story-title{margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:900;line-height:1.2;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .dosage-story-line{display:flex;align-items:flex-start;gap:8px}
  .dosage-story-text{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600;flex:1}
  .rml-story-block{display:grid;gap:10px}
  .rml-story-item{padding:10px 12px;border:1px solid #f2c7c7;border-radius:8px;background:linear-gradient(180deg,#fffdfd 0%,#fff6f6 100%)}
  .rml-story-title{margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:900;line-height:1.2;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .rml-story-line{display:flex;align-items:flex-start;gap:8px}
  .rml-story-text{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600;flex:1}
  .occiso-story-block{display:grid;gap:10px}
  .occiso-story-item{padding:10px 12px;border:1px solid #f2c7c7;border-radius:8px;background:linear-gradient(180deg,#fffdfd 0%,#fff6f6 100%)}
  .occiso-story-title{margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:900;line-height:1.2;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .occiso-story-line{display:flex;align-items:flex-start;gap:8px}
  .occiso-story-text{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600;flex:1}
  .occiso-story-section{margin-top:14px;color:#e11d1d}
  .occiso-story-section h5{margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:800;line-height:1.25;text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:3px}
  .occiso-story-section ul{margin:0;padding-left:18px;display:grid;gap:4px}
  .occiso-story-section li,.occiso-story-section p{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600}
  .manifestation-story-block{display:grid;gap:10px}
  .manifestation-story-item{padding:10px 12px;border:1px solid #f2c7c7;border-radius:8px;background:linear-gradient(180deg,#fffdfd 0%,#fff6f6 100%)}
  .manifestation-story-title{margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:900;line-height:1.2;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .manifestation-story-line{display:flex;align-items:flex-start;gap:8px}
  .manifestation-story-text{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600;flex:1}
  .field-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}
  .field-card{background:#f7f9fc;border:1px solid var(--line);border-radius:11px;padding:7px 9px}
  .field-card.span-2{grid-column:span 2}
  .field-card.span-4{grid-column:span 4}
  .field-label{font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:#8b6a12;margin-bottom:3px}
  .edit-label{display:block;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:var(--title-blue-soft);margin-bottom:3px}
  .field-value{font-size:12px;line-height:1.28;font-weight:700;word-break:break-word;color:#2b3950}
  .editable-shell{display:grid;gap:8px}
  .editable-toolbar{display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap}
  .editable-actions{display:flex;gap:6px;flex-wrap:wrap}
  .editable-actions[hidden]{display:none}
  .btn-shell.btn-primary{background:linear-gradient(180deg,#fff1bc 0%,#ffd86e 100%);border-color:#e2ba47;color:#5f4700}
  .general-edit-shell{display:grid;gap:8px}
  .general-edit-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:6px}
  .general-edit-card{background:#f7f9fc;border:1px solid var(--line);border-radius:12px;padding:8px 10px}
  .general-edit-card.g-3{grid-column:span 3}
  .general-edit-card.g-4{grid-column:span 4}
  .general-edit-card.g-6{grid-column:span 6}
  .general-edit-card.g-9{grid-column:span 9}
  .general-edit-card.g-12{grid-column:span 12}
  .general-edit-card label{display:block;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:var(--title-blue-soft);margin:0 0 4px}
  .general-edit-card .edit-control{font-size:12px}
  .general-checkbox-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}
  .general-checkbox{display:flex;align-items:flex-start;gap:6px;padding:6px 8px;border:1px solid var(--line);border-radius:10px;background:#fff;font-size:11px;font-weight:700;line-height:1.25;color:#36455b}
  .general-checkbox input{margin-top:2px}
  .general-help{font-size:10px;color:var(--muted);font-weight:700}
  .general-inline-note{font-size:10px;color:var(--muted);font-weight:700}
  .inline-edit-error{display:none;padding:8px 10px;border:1px solid #f1b3b3;background:#fff1f1;color:#aa2222;border-radius:10px;font-size:11px;font-weight:800}
  .inline-edit-error.is-visible{display:block}
  .edit-field{padding:6px 8px}
  .edit-control{width:100%;border:1px solid #d5ddeb;border-radius:9px;background:#fff;padding:7px 9px;font-size:12px;font-weight:600;color:#314157;line-height:1.25}
  .edit-control:focus{outline:none;border-color:#d6b44c;box-shadow:0 0 0 3px rgba(214,180,76,.16)}
  textarea.edit-control{min-height:76px;resize:vertical}
  .editable-form[hidden],.editable-view[hidden]{display:none}
  .module-grid{display:grid;gap:6px}
  .module-card{background:#f7f9fc;border:1px solid var(--line);border-radius:13px;padding:9px 11px}
  .module-card header{display:flex;justify-content:space-between;gap:6px;align-items:flex-start;flex-wrap:wrap;margin-bottom:4px}
  .module-card h4{margin:0;font-size:14px;font-weight:800;line-height:1.15;color:#8b6a12;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .module-card p{margin:0;color:var(--muted);font-weight:600;font-size:11px;line-height:1.25}
  .module-card h4.license-story-title{display:block;margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:900;line-height:1.2;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .module-card p.license-story-text{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600;flex:1}
  .module-card h4.dosage-story-title{display:block;margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:900;line-height:1.2;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .module-card p.dosage-story-text{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600;flex:1}
  .module-card h4.rml-story-title{display:block;margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:900;line-height:1.2;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .module-card p.rml-story-text{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600;flex:1}
  .module-card h4.occiso-story-title{display:block;margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:900;line-height:1.2;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .module-card p.occiso-story-text{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600;flex:1}
  .module-card h4.manifestation-story-title{display:block;margin:0 0 6px;color:#e11d1d;font-size:10pt;font-weight:900;line-height:1.2;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .module-card p.manifestation-story-text{margin:0;color:#e11d1d;font-size:10pt;line-height:1.45;font-weight:600;flex:1}
  .module-card-controls{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
  .module-meta{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
  .module-title-copy{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
  .module-toggle-btn{width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;border:1px solid #d7dfec;background:#fff;color:#8b6a12;font-size:18px;font-weight:700;line-height:1;box-shadow:0 6px 14px rgba(17,24,39,.05);transition:.18s ease}
  .module-toggle-btn:hover{border-color:#d6b44c;background:#fff8e7}
  .module-toggle-btn[aria-expanded="true"]{background:#d6b44c;border-color:#d6b44c;color:#fff}
  .module-card-panel{margin-top:8px}
  .module-card-panel[hidden]{display:none}
  .module-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
  .summary-sheet{display:grid;gap:10px}
  .summary-block-card{
    position:relative;
    border-color:#d7cae8;
    background:linear-gradient(180deg,#ffffff 0%,#fbf8ff 100%);
    box-shadow:0 14px 32px rgba(91,44,160,.08), 0 10px 22px rgba(181,138,24,.07);
    overflow:hidden;
  }
  .summary-block-card::before{
    content:"";
    position:absolute;
    inset:0 0 auto 0;
    height:4px;
    background:linear-gradient(90deg,#5b2ca0 0%,#8b5cf6 36%,#d4a93a 72%,#f0c654 100%);
  }
  .summary-block-card > header{
    margin:-9px -11px 10px;
    padding:14px 14px 10px;
    background:linear-gradient(180deg,rgba(107,58,198,.06) 0%,rgba(240,198,84,.05) 100%);
    border-bottom:1px solid rgba(171,142,207,.28);
  }
  .summary-subcard{border:1px solid var(--line);border-radius:13px;background:#fbfcfe;padding:10px}
  .summary-block-title{
    margin:0;
    font-size:22px;
    font-weight:900;
    line-height:1.04;
    letter-spacing:-.03em;
    background:linear-gradient(135deg,#4d248f 0%,#7c3aed 32%,#b07b14 74%,#f0c654 100%);
    -webkit-background-clip:text;
    background-clip:text;
    color:transparent;
  }
  .summary-block-card--intervention > header{padding-bottom:8px}
  .intervention-main-title{
    font-size:18px;
    font-weight:900;
    line-height:1.05;
    letter-spacing:-.02em;
    color:#111111 !important;
    background:none !important;
    -webkit-background-clip:initial !important;
    background-clip:initial !important;
  }
  .intervention-subtitle{
    margin:4px 0 0;
    font-size:16px;
    font-weight:900;
    line-height:1.08;
    color:#111111;
    text-decoration:underline;
    text-decoration-thickness:2px;
    text-underline-offset:3px;
  }
  .intervention-sheet{display:grid;gap:6px;color:#111111}
  .intervention-row{
    display:grid;
    grid-template-columns:44px minmax(220px, 390px) 18px minmax(0,1fr);
    gap:8px;
    align-items:start;
  }
  .intervention-index,
  .intervention-label,
  .intervention-sep,
  .intervention-value{
    font-size:14px;
    line-height:1.28;
    color:#111111;
  }
  .intervention-index,
  .intervention-sep{font-weight:800}
  .intervention-label{font-weight:500}
  .intervention-value{font-weight:500;min-width:0}
  .intervention-value-wrap{display:inline-flex;align-items:flex-start;gap:8px;max-width:100%}
  .intervention-value-main{min-width:0}
  .intervention-value strong{font-weight:900}
  .intervention-unit-block + .intervention-unit-block{margin-top:4px}
  .intervention-unit-title,
  .intervention-unit-role,
  .intervention-unit-person{
    font-size:14px;
    line-height:1.28;
    color:#111111;
  }
  .intervention-unit-title + .intervention-unit-role,
  .intervention-unit-person + .intervention-unit-role{margin-top:2px}
  .intervention-unit-person{padding-left:0}
  .summary-header{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap;margin-bottom:6px}
  .summary-person-name,
  .summary-person-card .summary-person-name,
  .summary-subcard .summary-person-name{
    margin:0;
    font-size:16px;
    font-weight:900;
    line-height:1.08;
    letter-spacing:-.02em;
    color:#7b1e3a !important;
  }
  .summary-header p{margin:3px 0 0;color:var(--muted);font-size:11px;font-weight:600}
  .summary-chipline{display:flex;gap:6px;flex-wrap:wrap}
  .summary-person-card{display:grid;gap:8px}
  .summary-doc-stack{display:grid;gap:8px}
  .summary-empty{padding:12px;border:1px dashed var(--line);border-radius:12px;background:rgba(148,163,184,.06);color:var(--muted);font-size:12px;font-weight:600}
  .analysis-two-cols{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
  .analysis-two-cols .module-card{height:100%}
  .analysis-upload{margin-top:10px;padding:10px;border:1px dashed #c9d5e6;border-radius:14px;background:linear-gradient(180deg,#fbfdff 0%,#f4f8fd 100%)}
  .analysis-upload-form{display:grid;gap:8px}
  .analysis-upload label{display:block;margin:0 0 8px;font-size:11px;font-weight:800;color:var(--title-blue);letter-spacing:.01em}
  .analysis-upload-input{display:block;width:100%;font-size:12px;font-weight:600;color:var(--ink)}
  .analysis-upload-hint{margin:8px 0 0;font-size:11px;color:var(--muted);font-weight:600}
  .analysis-upload-status{font-size:11px;font-weight:700;color:var(--muted)}
  .analysis-preview{margin-top:10px;border:1px solid var(--line);border-radius:14px;background:#fff;padding:8px;min-height:180px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;overflow:hidden}
  .analysis-preview[hidden]{display:none}
  .analysis-preview img{display:block;max-width:100%;max-height:320px;border-radius:10px;object-fit:contain}
  .analysis-inline-slider{margin-top:10px;border:1px solid var(--line);border-radius:16px;background:#fff;padding:10px;display:grid;gap:10px}
  .analysis-inline-stage{position:relative;min-height:420px;border-radius:14px;background:linear-gradient(180deg,#fbfdff 0%,#eef4fb 100%);display:flex;align-items:center;justify-content:center;overflow:hidden}
  .analysis-inline-image{display:block;max-width:100%;max-height:560px;object-fit:contain;border-radius:12px}
  .analysis-inline-nav{position:absolute;top:50%;transform:translateY(-50%);width:42px;height:42px;border-radius:999px;border:1px solid rgba(15,23,42,.12);background:rgba(255,255,255,.9);color:#1f2937;font-size:22px;font-weight:800;line-height:1;display:inline-flex;align-items:center;justify-content:center}
  .analysis-inline-nav:hover{background:#fff}
  .analysis-inline-prev{left:12px}
  .analysis-inline-next{right:12px}
  .analysis-inline-meta{display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap}
  .analysis-image-order{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;background:#ecfdf5;border:1px solid #a7f3d0;color:#166534;font-size:10px;font-weight:800}
  .analysis-image-name{font-size:11px;font-weight:700;color:var(--muted);word-break:break-word}
  .diligencia-card{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:start}
  .diligencia-main{display:grid;gap:6px;min-width:0}
  .diligencia-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:start}
  .diligencia-side{display:grid;gap:6px;justify-items:end;align-content:start}
  .diligencia-content-row{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
  .diligencia-content{flex:1 1 420px;margin:0;color:#5f6d82;font-weight:600;font-size:11px;line-height:1.4}
  .diligencia-actions{display:flex;gap:6px;flex-wrap:wrap;align-items:center;justify-content:flex-end}
  .diligencia-status-select{min-width:130px;padding:6px 30px 6px 10px;border-radius:999px;border:1px solid var(--line);background:#fff;color:#314157;font-size:12px;font-weight:700;line-height:1.1;box-shadow:0 6px 16px rgba(17,24,39,.06)}
  .diligencia-status-select.status-pendiente{border-color:#f0b8b8;background:#fff3f3;color:#b42318}
  .diligencia-status-select.status-resuelto{border-color:#b9e2c5;background:#effcf3;color:#157347}
  .diligencia-inline-fields{display:grid;gap:6px}
  .diligencia-inline-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}
  .diligencia-inline-box{padding:7px 9px;border:1px solid var(--line);border-radius:10px;background:#fff}
  .diligencia-inline-box strong{display:block;margin:0 0 3px;color:#8b6a12;font-size:9px;line-height:1.15;text-transform:uppercase;letter-spacing:.05em}
  .diligencia-inline-box div{font-size:12px;font-weight:700;color:#2d3c52;line-height:1.35}
  .empty-state{padding:18px 12px;text-align:center;color:var(--muted);font-weight:700;font-size:12px}
  .inner-tabs{display:flex;gap:5px;overflow:auto;padding-bottom:5px;margin:8px 0 6px}
  .inner-tabs .nav-link{border:1px solid var(--line);background:#f4f7fb;color:#506078;border-radius:9px;padding:6px 8px;font-size:11px;font-weight:700;line-height:1.05;white-space:nowrap}
  .inner-tabs .nav-link.active{background:#fff7de;border-color:#e7c75c;color:#755811}
  .inner-tabs .tab-mini{display:block;font-size:9px;font-weight:700;opacity:.72;margin-top:1px}
  .inner-panel{border:1px solid var(--line);border-radius:13px;background:#fbfcfe;padding:8px}
  .record-stack{display:grid;gap:6px}
  .record-card{border:1px solid var(--line);border-radius:11px;background:#fff;padding:8px 9px}
  .record-card h5{margin:0 0 4px;font-size:12px;font-weight:800;line-height:1.2;color:#8b6a12}
  .record-card p{margin:0;color:var(--muted);font-size:11px;line-height:1.3;font-weight:600}
  .record-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
  .record-chipline{display:flex;gap:5px;flex-wrap:wrap;margin-top:5px}
  .itp-list{margin:0;padding-left:18px;color:#2d3b50;font-size:12px;font-weight:700;line-height:1.35}
  .itp-list li + li{margin-top:4px}
  .itp-builder{border:1px dashed var(--line);border-radius:12px;padding:10px;background:#fbfcfe}
  .itp-builder-list{display:flex;flex-direction:column;gap:8px;margin-top:10px}
  .itp-builder-item{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--line);border-radius:10px;background:rgba(148,163,184,.08)}
  .itp-builder-item span{font-size:12px;font-weight:700;line-height:1.35;color:#334257}
  .itp-builder-row{display:flex;gap:8px;flex-wrap:wrap}
  .itp-builder-row .edit-control{flex:1}
  .itp-builder-row .btn-shell{flex:0 0 auto}
  .inline-workbench{margin:0 0 8px;border:1px solid #d8e0ed;border-radius:13px;background:#f7f9fd;overflow:hidden}
  .inline-workbench[hidden]{display:none}
  .inline-head{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:7px 9px;border-bottom:1px solid var(--line);background:#eef3fb}
  .inline-head strong{font-size:11px;letter-spacing:.03em;text-transform:uppercase;color:#51627d}
  .inline-frame{width:100%;height:520px;border:0;background:#fff}
  html[data-theme-resolved="dark"] .btn-shell{
    background:#10192c;
    color:var(--ink);
    border-color:#32415a;
    box-shadow:0 8px 18px rgba(2,6,23,.26);
  }
  html[data-theme-resolved="dark"] .btn-shell.btn-citacion{
    background:linear-gradient(180deg,#0f1b33 0%,#13233f 100%);
    border-color:#3b82f6;
    color:#93c5fd;
  }
  html[data-theme-resolved="dark"] .btn-shell.btn-peritaje{
    background:linear-gradient(180deg,#2a1d12 0%,#352414 100%);
    border-color:#f59e0b;
    color:#fdba74;
  }
  html[data-theme-resolved="dark"] .btn-shell.btn-necropsia{
    background:linear-gradient(180deg,#112723 0%,#14312c 100%);
    border-color:#14b8a6;
    color:#5eead4;
  }
  html[data-theme-resolved="dark"] .btn-shell.btn-docx{
    background:linear-gradient(180deg,#2e1065 0%,#4c1d95 100%);
    border-color:#c084fc;
    box-shadow:0 0 0 1px rgba(192,132,252,.35),0 0 18px rgba(192,132,252,.45),0 10px 22px rgba(88,28,135,.35);
    color:#f5d0fe;
    text-shadow:0 0 12px rgba(245,208,254,.32);
  }
  html[data-theme-resolved="dark"] .btn-shell.btn-docx:hover{
    background:linear-gradient(180deg,#4c1d95 0%,#6b21a8 100%);
    border-color:#e879f9;
    box-shadow:0 0 0 1px rgba(232,121,249,.42),0 0 24px rgba(232,121,249,.48),0 12px 24px rgba(88,28,135,.42);
    color:#fff;
  }
  html[data-theme-resolved="dark"] .panel,
  html[data-theme-resolved="dark"] .tab-panel{
    background:rgba(15,23,42,.92);
    border-color:#2a3852;
    box-shadow:0 14px 30px rgba(2,6,23,.34);
  }
  html[data-theme-resolved="dark"] .summary-pill,
  html[data-theme-resolved="dark"] .data-card,
  html[data-theme-resolved="dark"] .line-card,
  html[data-theme-resolved="dark"] .field-card,
  html[data-theme-resolved="dark"] .general-edit-card,
  html[data-theme-resolved="dark"] .module-card,
  html[data-theme-resolved="dark"] .summary-subcard,
  html[data-theme-resolved="dark"] .inner-panel,
  html[data-theme-resolved="dark"] .record-card,
  html[data-theme-resolved="dark"] .itp-builder,
  html[data-theme-resolved="dark"] .inline-workbench{
    background:#111b30;
    border-color:#2a3852;
    color:var(--ink);
  }
  html[data-theme-resolved="dark"] .data-card.highlight{
    background:linear-gradient(180deg,#2b2412 0%,#3a2f14 100%);
    border-color:#d6b44c;
  }
  html[data-theme-resolved="dark"] .quick-status-select,
  html[data-theme-resolved="dark"] .module-status-select,
  html[data-theme-resolved="dark"] .edit-control,
  html[data-theme-resolved="dark"] .diligencia-status-select,
  html[data-theme-resolved="dark"] .diligencia-inline-box,
  html[data-theme-resolved="dark"] .general-checkbox,
  html[data-theme-resolved="dark"] .copy-name-btn,
  html[data-theme-resolved="dark"] .quick-pill-btn,
  html[data-theme-resolved="dark"] .module-toggle-btn,
  html[data-theme-resolved="dark"] .chip-role,
  html[data-theme-resolved="dark"] .chip-status,
  html[data-theme-resolved="dark"] .chip-simple{
    background:#0f172a;
    color:var(--ink);
    border-color:#32415a;
  }
  html[data-theme-resolved="dark"] .quick-pill-btn.download{
    background:linear-gradient(180deg,#2e1065 0%,#4c1d95 100%);
    border-color:#c084fc;
    box-shadow:0 0 0 1px rgba(192,132,252,.35),0 0 18px rgba(192,132,252,.45),0 10px 22px rgba(88,28,135,.35);
    color:#f5d0fe;
    text-shadow:0 0 12px rgba(245,208,254,.32);
  }
  html[data-theme-resolved="dark"] .quick-pill-btn.download:hover{
    background:linear-gradient(180deg,#4c1d95 0%,#6b21a8 100%);
    border-color:#e879f9;
    box-shadow:0 0 0 1px rgba(232,121,249,.42),0 0 24px rgba(232,121,249,.48),0 12px 24px rgba(88,28,135,.42);
    color:#fff;
  }
  html[data-theme-resolved="dark"] .tabs-header .nav-link,
  html[data-theme-resolved="dark"] .participant-tabs .nav-link,
  html[data-theme-resolved="dark"] .inner-tabs .nav-link{
    background:#152036;
    color:#c4d2e7;
    border-color:#31415c;
  }
  html[data-theme-resolved="dark"] .tabs-header .nav-link.active,
  html[data-theme-resolved="dark"] .participant-tabs .nav-link.active,
  html[data-theme-resolved="dark"] .inner-tabs .nav-link.active{
    background:linear-gradient(180deg,#3a3115 0%,#5a4a1d 100%);
    border-color:#d6b44c;
    color:#fff2bf;
    box-shadow:0 10px 22px rgba(240,198,84,.14), 0 0 0 1px rgba(255,255,255,.04) inset;
  }
  html[data-theme-resolved="dark"] .tabs-header .nav-link.tab-driver.active,
  html[data-theme-resolved="dark"] .participant-tabs .nav-link.tab-driver.active{
    background:linear-gradient(180deg,#12342e 0%,#165247 100%);
    border-color:#2dd4bf;
    color:#d1fae5;
    box-shadow:0 10px 22px rgba(45,212,191,.14), 0 0 0 1px rgba(255,255,255,.04) inset;
  }
  html[data-theme-resolved="dark"] .tabs-header .nav-link.tab-herido.active,
  html[data-theme-resolved="dark"] .participant-tabs .nav-link.tab-herido.active{
    background:linear-gradient(180deg,#4d3d12 0%,#6e5618 100%);
    border-color:#f0c654;
    color:#fff4c7;
    box-shadow:0 10px 22px rgba(240,198,84,.16), 0 0 0 1px rgba(255,255,255,.04) inset;
  }
  html[data-theme-resolved="dark"] .tabs-header .nav-link.tab-occiso.active,
  html[data-theme-resolved="dark"] .participant-tabs .nav-link.tab-occiso.active{
    background:linear-gradient(180deg,#4a1818 0%,#681f1f 100%);
    border-color:#f87171;
    color:#ffe2e2;
    box-shadow:0 10px 22px rgba(248,113,113,.16), 0 0 0 1px rgba(255,255,255,.04) inset;
  }
  html[data-theme-resolved="dark"] .main-tabs{
    border-bottom-color:#2a3852;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link{
    border-color:#31415c;
    background:linear-gradient(180deg,rgba(20,30,50,.96) 0%,rgba(16,24,39,.98) 100%);
    color:#d4deec;
    box-shadow:0 14px 28px rgba(2,6,23,.28), inset 0 1px 0 rgba(255,255,255,.04);
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link:hover{
    color:#f8fbff;
    border-color:#4b638b;
    box-shadow:0 18px 34px rgba(2,6,23,.34);
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.active{
    color:#f8fbff;
    box-shadow:0 18px 38px rgba(2,6,23,.42), 0 0 0 1px rgba(255,255,255,.04) inset;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.tab-itp.active{
    background:linear-gradient(135deg,#103b36 0%,#14554c 52%,#177265 100%);
    color:#e8fffb;
    box-shadow:0 18px 38px rgba(20,184,166,.18), 0 0 0 1px rgba(255,255,255,.04) inset;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.tab-participantes.active{
    background:linear-gradient(135deg,#18335f 0%,#214982 52%,#2f68ad 100%);
    color:#eef6ff;
    box-shadow:0 18px 38px rgba(59,130,246,.20), 0 0 0 1px rgba(255,255,255,.04) inset;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.tab-documentos.active{
    background:linear-gradient(135deg,#4b3511 0%,#6d4b15 52%,#95681d 100%);
    color:#fff5d1;
    box-shadow:0 18px 38px rgba(217,119,6,.20), 0 0 0 1px rgba(255,255,255,.04) inset;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.tab-diligencias.active{
    background:linear-gradient(135deg,#37205d 0%,#503084 52%,#6d47b3 100%);
    color:#f5eeff;
    box-shadow:0 18px 38px rgba(124,58,237,.20), 0 0 0 1px rgba(255,255,255,.04) inset;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.tab-analisis.active{
    background:linear-gradient(135deg,#123b2d 0%,#18553f 52%,#22875e 100%);
    color:#edfff4;
    box-shadow:0 18px 38px rgba(34,197,94,.20), 0 0 0 1px rgba(255,255,255,.04) inset;
  }
  html[data-theme-resolved="dark"] .tab-panel.driver-panel{
    background:linear-gradient(180deg,rgba(10,44,37,.95) 0%,rgba(15,23,42,.96) 52%,rgba(11,35,31,.96) 100%);
  }
  html[data-theme-resolved="dark"] .tab-panel.herido-panel{
    background:linear-gradient(180deg,rgba(54,42,12,.96) 0%,rgba(15,23,42,.96) 52%,rgba(43,35,13,.96) 100%);
  }
  html[data-theme-resolved="dark"] .tab-panel.occiso-panel{
    background:linear-gradient(180deg,rgba(58,18,18,.96) 0%,rgba(15,23,42,.96) 52%,rgba(48,18,18,.96) 100%);
  }
  html[data-theme-resolved="dark"] .inline-head{
    background:#162238;
    border-bottom-color:#2a3852;
  }
  html[data-theme-resolved="dark"] .inline-head strong{
    color:#b7c7df;
  }
  html[data-theme-resolved="dark"] .inline-frame{
    background:#0b1220;
  }
  html[data-theme-resolved="dark"] .person-title h2,
  html[data-theme-resolved="dark"] .section-title,
  html[data-theme-resolved="dark"] .section-block h3{
    color:#93c5fd;
  }
  html[data-theme-resolved="dark"] .persona-story-block{
    background:linear-gradient(180deg,#2d1418 0%,#241015 100%);
    border-color:#7f1d1d;
    box-shadow:0 16px 30px rgba(2,6,23,.28);
  }
  html[data-theme-resolved="dark"] .vehicle-story-block{
    background:linear-gradient(180deg,#2d1418 0%,#241015 100%);
    border-color:#7f1d1d;
    box-shadow:0 16px 30px rgba(2,6,23,.28);
  }
  html[data-theme-resolved="dark"] .persona-story-block h3.persona-story-title,
  html[data-theme-resolved="dark"] .persona-story-block p.persona-story-text{
    color:#f8fafc !important;
  }
  html[data-theme-resolved="dark"] .vehicle-story-title,
  html[data-theme-resolved="dark"] .vehicle-story-subtitle,
  html[data-theme-resolved="dark"] .vehicle-story-key,
  html[data-theme-resolved="dark"] .vehicle-story-sep,
  html[data-theme-resolved="dark"] .vehicle-story-value{
    color:#fecaca;
  }
  html[data-theme-resolved="dark"] .vehicle-docs-story-title,
  html[data-theme-resolved="dark"] .vehicle-docs-story-text{
    color:#fecaca;
  }
  html[data-theme-resolved="dark"] .license-story-item{
    background:linear-gradient(180deg,#2d1418 0%,#241015 100%);
    border-color:#7f1d1d;
  }
  html[data-theme-resolved="dark"] .dosage-story-item{
    background:linear-gradient(180deg,#2d1418 0%,#241015 100%);
    border-color:#7f1d1d;
  }
  html[data-theme-resolved="dark"] .rml-story-item{
    background:linear-gradient(180deg,#2d1418 0%,#241015 100%);
    border-color:#7f1d1d;
  }
  html[data-theme-resolved="dark"] .occiso-story-item{
    background:linear-gradient(180deg,#2d1418 0%,#241015 100%);
    border-color:#7f1d1d;
  }
  html[data-theme-resolved="dark"] .manifestation-story-item{
    background:linear-gradient(180deg,#2d1418 0%,#241015 100%);
    border-color:#7f1d1d;
  }
  html[data-theme-resolved="dark"] .license-story-title,
  html[data-theme-resolved="dark"] .license-story-text,
  html[data-theme-resolved="dark"] .dosage-story-title,
  html[data-theme-resolved="dark"] .dosage-story-text,
  html[data-theme-resolved="dark"] .rml-story-title,
  html[data-theme-resolved="dark"] .rml-story-text,
  html[data-theme-resolved="dark"] .occiso-story-title,
  html[data-theme-resolved="dark"] .occiso-story-text,
  html[data-theme-resolved="dark"] .occiso-story-section,
  html[data-theme-resolved="dark"] .occiso-story-section h5,
  html[data-theme-resolved="dark"] .occiso-story-section li,
  html[data-theme-resolved="dark"] .occiso-story-section p,
  html[data-theme-resolved="dark"] .manifestation-story-title,
  html[data-theme-resolved="dark"] .manifestation-story-text{
    color:#fecaca;
  }
  html[data-theme-resolved="dark"] .persona-story-name{
    background:rgba(248,113,113,.18);
  }
  html[data-theme-resolved="dark"] .title-wrap h1{
    color:#8ec1ff;
    font-weight:800;
  }
  html[data-theme-resolved="dark"] .data-card .value,
  html[data-theme-resolved="dark"] .field-value,
  html[data-theme-resolved="dark"] .diligencia-inline-box div,
  html[data-theme-resolved="dark"] .itp-list,
  html[data-theme-resolved="dark"] .itp-builder-item span{
    color:#d6e0ee;
    font-weight:700;
  }
  html[data-theme-resolved="dark"] #itp .field-value,
  html[data-theme-resolved="dark"] #itp .itp-list,
  html[data-theme-resolved="dark"] #itp .itp-builder-item span{
    color:#b8c4d3;
    font-weight:600;
    line-height:1.42;
  }
  html[data-theme-resolved="dark"] .line-card,
  html[data-theme-resolved="dark"] .person-title p,
  html[data-theme-resolved="dark"] .module-card p,
  html[data-theme-resolved="dark"] .record-card p,
  html[data-theme-resolved="dark"] .diligencia-content{
    color:#9fb1ca;
    font-weight:600;
  }
  html[data-theme-resolved="dark"] .person-title h2,
  html[data-theme-resolved="dark"] .section-title,
  html[data-theme-resolved="dark"] .section-block h3,
  html[data-theme-resolved="dark"] .module-card h4,
  html[data-theme-resolved="dark"] .record-card h5{
    font-weight:800;
  }
  html[data-theme-resolved="dark"] .main-tabs .main-tab-title{
    font-weight:800;
    color:#dce6f5;
  }
  html[data-theme-resolved="dark"] .main-tabs .tab-sub{
    font-weight:700;
    color:#9fb1ca;
    opacity:.72;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link{
    color:#c7d3e2;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link:hover{
    color:#e4edf8;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.active{
    color:#dbe7f5;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.tab-itp.active{
    color:#d9f8f2;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.tab-participantes.active{
    color:#dce9f8;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.tab-documentos.active{
    color:#f6e8bd;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.tab-diligencias.active{
    color:#e8dcf9;
  }
  html[data-theme-resolved="dark"] .main-tabs .nav-link.tab-analisis.active{
    color:#ddfce7;
  }
  html[data-theme-resolved="dark"] .tabs-header .nav-link,
  html[data-theme-resolved="dark"] .participant-tabs .nav-link,
  html[data-theme-resolved="dark"] .inner-tabs .nav-link{
    font-weight:700;
  }
  html[data-theme-resolved="dark"] .copy-name-btn,
  html[data-theme-resolved="dark"] .quick-pill-btn,
  html[data-theme-resolved="dark"] .chip-role,
  html[data-theme-resolved="dark"] .chip-status,
  html[data-theme-resolved="dark"] .chip-simple,
  html[data-theme-resolved="dark"] .module-toggle-btn,
  html[data-theme-resolved="dark"] .quick-status-select,
  html[data-theme-resolved="dark"] .module-status-select,
  html[data-theme-resolved="dark"] .diligencia-status-select{
    color:#c9d5e4;
    font-weight:700;
  }
  html[data-theme-resolved="dark"] .field-label,
  html[data-theme-resolved="dark"] .edit-label,
  html[data-theme-resolved="dark"] .general-edit-card label,
  html[data-theme-resolved="dark"] .data-card .label,
  html[data-theme-resolved="dark"] .line-card strong,
  html[data-theme-resolved="dark"] .record-card h5,
  html[data-theme-resolved="dark"] .module-card h4,
  html[data-theme-resolved="dark"] .diligencia-inline-box strong,
  html[data-theme-resolved="dark"] .summary-pill strong{
    color:#f0c654;
  }
  html[data-theme-resolved="dark"] .module-card h4.license-story-title,
  html[data-theme-resolved="dark"] .module-card p.license-story-text,
  html[data-theme-resolved="dark"] .module-card h4.dosage-story-title,
  html[data-theme-resolved="dark"] .module-card p.dosage-story-text,
  html[data-theme-resolved="dark"] .module-card h4.rml-story-title,
  html[data-theme-resolved="dark"] .module-card p.rml-story-text,
  html[data-theme-resolved="dark"] .module-card h4.occiso-story-title,
  html[data-theme-resolved="dark"] .module-card p.occiso-story-text,
  html[data-theme-resolved="dark"] .module-card h4.manifestation-story-title,
  html[data-theme-resolved="dark"] .module-card p.manifestation-story-text{
    color:#fecaca;
  }
  html[data-theme-resolved="dark"] .module-card p,
  html[data-theme-resolved="dark"] .record-card p,
  html[data-theme-resolved="dark"] .person-title p,
  html[data-theme-resolved="dark"] .summary-header p,
  html[data-theme-resolved="dark"] .general-help,
  html[data-theme-resolved="dark"] .general-inline-note,
  html[data-theme-resolved="dark"] .empty-state{
    color:#9fb1ca;
  }
  html[data-theme-resolved="dark"] .summary-empty{
    background:rgba(15,23,42,.74);
    color:#9fb1ca;
  }
  html[data-theme-resolved="dark"] .summary-block-card{
    border-color:#3c3252;
    background:linear-gradient(180deg,#111827 0%,#161126 100%);
    box-shadow:0 18px 38px rgba(7,10,20,.42), 0 0 0 1px rgba(124,58,237,.08) inset;
  }
  html[data-theme-resolved="dark"] .summary-block-card::before{
    background:linear-gradient(90deg,#7c3aed 0%,#a78bfa 34%,#d4a93a 74%,#f0c654 100%);
  }
  html[data-theme-resolved="dark"] .summary-block-card > header{
    background:linear-gradient(180deg,rgba(124,58,237,.12) 0%,rgba(240,198,84,.06) 100%);
    border-bottom-color:rgba(98,82,135,.55);
  }
  html[data-theme-resolved="dark"] .summary-block-title{
    background:linear-gradient(135deg,#b794ff 0%,#d6c4ff 30%,#e0b648 72%,#f7d97d 100%);
    -webkit-background-clip:text;
    background-clip:text;
    color:transparent;
  }
  html[data-theme-resolved="dark"] .intervention-main-title,
  html[data-theme-resolved="dark"] .intervention-subtitle,
  html[data-theme-resolved="dark"] .intervention-index,
  html[data-theme-resolved="dark"] .intervention-label,
  html[data-theme-resolved="dark"] .intervention-sep,
  html[data-theme-resolved="dark"] .intervention-value,
  html[data-theme-resolved="dark"] .intervention-unit-title,
  html[data-theme-resolved="dark"] .intervention-unit-role,
  html[data-theme-resolved="dark"] .intervention-unit-person{
    color:#f3f4f6 !important;
  }
  html[data-theme-resolved="dark"] .summary-person-name,
  html[data-theme-resolved="dark"] .summary-person-card .summary-person-name,
  html[data-theme-resolved="dark"] .summary-subcard .summary-person-name{
    color:#d9879e !important;
  }
  html[data-theme-resolved="dark"] .general-checkbox{
    background:#0f172a;
  }
  html[data-theme-resolved="dark"] .edit-control:focus,
  html[data-theme-resolved="dark"] .quick-status-select:focus,
  html[data-theme-resolved="dark"] .module-status-select:focus{
    box-shadow:0 0 0 3px rgba(240,198,84,.18);
  }
  @media (max-width:1200px){
    .page{max-width:1200px}
    .field-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
  }
  @media (max-width:980px){
    .g-3,.g-4,.g-6{grid-column:span 6}
    .ident-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .general-edit-card.g-3,.general-edit-card.g-4,.general-edit-card.g-6,.general-edit-card.g-9{grid-column:span 6}
    .line-grid{grid-template-columns:1fr}
    .field-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .general-grid{gap:5px}
    .diligencia-card{grid-template-columns:1fr}
    .diligencia-head{grid-template-columns:1fr}
    .diligencia-side{justify-items:start}
    .diligencia-inline-row{grid-template-columns:1fr}
  }
  @media (max-width:720px){
    .page{padding:0 8px 16px}
    .topbar{margin-bottom:8px}
    .top-actions{gap:6px}
    .btn-shell{padding:6px 8px;font-size:11px}
    .panel,.tab-panel{padding:8px}
    .summary-pill{padding:8px 9px;font-size:12px}
    .summary-pill strong{display:block;min-width:0;margin-bottom:4px}
    .g-3,.g-4,.g-6,.g-12{grid-column:span 12}
    .ident-grid{grid-template-columns:1fr}
    .general-edit-card.g-3,.general-edit-card.g-4,.general-edit-card.g-6,.general-edit-card.g-9,.general-edit-card.g-12{grid-column:span 12}
    .field-grid{grid-template-columns:1fr}
    .analysis-two-cols{grid-template-columns:1fr}
    .analysis-preview{grid-template-columns:1fr}
    .analysis-inline-stage{min-height:280px}
    .analysis-inline-image{max-height:360px}
    .general-checkbox-grid{grid-template-columns:1fr}
    .field-card.span-2{grid-column:span 1}
    .editable-toolbar{align-items:flex-start}
    .person-title h2{font-size:16px}
    .tabs-header .nav-link{padding:6px 8px;font-size:11px}
    .tabs-header .tab-sub{font-size:9px}
    .main-tabs{gap:8px;padding:6px 2px 10px}
    .main-tabs .nav-link{min-width:128px;padding:11px 14px 11px;border-radius:16px}
    .main-tabs .main-tab-title{font-size:14px}
    .main-tabs .tab-sub{font-size:10px}
    .participant-tabs .nav-link{padding:6px 8px;font-size:11px}
    .participant-tabs .tab-sub{font-size:9px}
    .inner-tabs .nav-link{padding:5px 7px;font-size:10px}
    .inline-frame{height:460px}
    .data-card{min-height:auto}
    .diligencia-content-row{flex-direction:column;align-items:stretch}
    .diligencia-actions{justify-content:flex-start}
    .diligencia-side{justify-items:start}
    .persona-story-block{padding:12px}
    .persona-story-block h3.persona-story-title{font-size:14px !important}
    .persona-story-block p.persona-story-text{font-size:14px !important}
    .vehicle-story-block{padding:12px}
    .vehicle-story-title{font-size:14px}
    .vehicle-story-subtitle{font-size:14px}
    .intervention-main-title{font-size:16px}
    .intervention-subtitle{font-size:15px}
    .intervention-row{grid-template-columns:40px minmax(140px,1fr) 16px minmax(0,1.2fr);gap:6px}
    .intervention-index,.intervention-label,.intervention-sep,.intervention-value,.intervention-unit-title,.intervention-unit-role,.intervention-unit-person{font-size:13px}
    .vehicle-story-row{grid-template-columns:minmax(110px,1fr) 14px minmax(0,1fr);gap:6px}
    .vehicle-story-key,.vehicle-story-sep,.vehicle-story-value{font-size:14px}
    .vehicle-docs-story-title,.vehicle-docs-story-text{font-size:14px}
  }
  @media (prefers-reduced-motion: reduce){
    .tab-panel.driver-panel::before{animation:none}
  }
</style>
</head>
<body>
<div class="page">
  <div class="topbar">
    <div class="title-wrap">
      <h1>Vista por pestañas del accidente</h1>
      <p>Accidente #<?= (int) $accidente_id ?> · SIDPOL <?= fmt($A['sidpol'] ?? '') ?></p>
    </div>
    <div class="top-actions">
      <a class="btn-shell" href="Dato_General_accidente.php?accidente_id=<?= (int) $accidente_id ?>">Volver a datos generales</a>
      <a class="btn-shell" href="accidente_listar.php">Volver al listado</a>
    </div>
  </div>

  <div class="panel">
    <div class="general-edit-shell" data-edit-shell="general-accidente">
      <div class="editable-toolbar">
        <div class="general-inline-note">Dato general del accidente</div>
        <div class="editable-actions">
          <button type="button" class="btn-shell js-edit-start" data-shell="general-accidente">Editar</button>
          <div class="editable-actions" data-edit-actions="general-accidente" hidden>
            <button type="button" class="btn-shell js-edit-cancel" data-shell="general-accidente">Cancelar</button>
            <button type="submit" class="btn-shell btn-primary" form="accidente-inline-form">Guardar</button>
          </div>
        </div>
      </div>

      <div class="inline-edit-error" id="accidente-inline-error"></div>

      <div class="editable-view" data-edit-view="general-accidente">
        <div class="summary-stack">
          <div class="summary-pill"><strong>Modalidades:</strong> <?= $modsConcat ?></div>
          <div class="summary-pill"><strong>Consecuencias:</strong> <?= $consConcat ?></div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Identificación</h2>
          <div class="ident-grid">
            <div class="data-card highlight centered">
              <div class="label">Registro SIDPOL</div>
              <div class="value"><?= fmt($A['registro_sidpol'] ?? '') ?></div>
            </div>
            <?php
              $estadoKey = mb_strtolower(trim((string) ($A['estado'] ?? '')), 'UTF-8');
              $estadoClass = $estadoKey === 'pendiente'
                ? 'status-pendiente'
                : ($estadoKey === 'resuelto'
                    ? 'status-resuelto'
                    : ($estadoKey === 'con diligencias' ? 'status-diligencias' : ''));
            ?>
            <div class="data-card centered">
              <div class="label">Estado</div>
              <select class="quick-status-select value <?= h($estadoClass) ?> js-quick-status" data-accidente-id="<?= (int) $accidente_id ?>" data-prev="<?= h((string) ($A['estado'] ?? 'Pendiente')) ?>">
                <?php foreach (['Pendiente', 'Resuelto', 'Con diligencias'] as $estadoOpt): ?>
                  <option value="<?= h($estadoOpt) ?>" <?= (string) ($A['estado'] ?? 'Pendiente') === $estadoOpt ? 'selected' : '' ?>><?= h($estadoOpt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="data-card centered">
              <div class="label">N° informe policial</div>
              <div class="value"><?= fmt($A['nro_informe_policial'] ?? '') ?></div>
            </div>
            <div class="data-card centered">
              <div class="label">Comisaría</div>
              <div class="value"><?= fmt($A['comisaria_nom'] ?? '') ?></div>
            </div>
            <div class="data-card centered">
              <div class="label">N° folder</div>
              <div class="value"><?= fmt($A['folder'] ?? '') ?></div>
            </div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Fechas</h2>
          <div class="line-grid">
            <div class="line-card"><strong>Accidente:</strong> <?= h(fecha_hora_corta_esp($A['fecha_accidente'] ?? null)) ?></div>
            <div class="line-card"><strong>Comunicación:</strong> <?= h(fecha_hora_corta_esp($A['fecha_comunicacion'] ?? null)) ?></div>
            <div class="line-card"><strong>Intervención:</strong> <?= h(fecha_hora_corta_esp($A['fecha_intervencion'] ?? null)) ?></div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Ubicación</h2>
          <div class="general-grid">
            <div class="data-card g-4">
              <div class="label">Lugar</div>
              <div class="value"><?= fmt($A['lugar'] ?? '') ?></div>
            </div>
            <div class="data-card g-4">
              <div class="label">Ubicación</div>
              <div class="value"><?= $ubicacion !== '' ? h($ubicacion) : '—' ?></div>
            </div>
            <div class="data-card g-4">
              <div class="label">Referencia</div>
              <div class="value"><?= fmt($A['referencia'] ?? '') ?></div>
            </div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Autoridades</h2>
          <div class="general-grid">
            <div class="data-card g-6">
              <div class="label">Fiscalía</div>
              <div class="value"><?= fmt($A['fiscalia_nom'] ?? '') ?></div>
            </div>
            <div class="data-card g-6">
              <div class="label">Fiscal a cargo</div>
              <div class="value"><?= fmt($A['fiscal_nom'] ?? '') ?></div>
            </div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Comunicación</h2>
          <div class="general-grid">
            <div class="data-card g-4">
              <div class="label">Comunicante</div>
              <div class="value"><?= fmt($A['comunicante_nombre'] ?? '') ?></div>
            </div>
            <div class="data-card g-4">
              <div class="label">Tel. comunicante</div>
              <div class="value"><?= fmt($A['comunicante_telefono'] ?? '') ?></div>
            </div>
            <div class="data-card g-4">
              <div class="label">Decreto</div>
              <div class="value"><?= fmt($A['comunicacion_decreto'] ?? '') ?></div>
            </div>
            <div class="data-card g-6">
              <div class="label">Oficio</div>
              <div class="value"><?= fmt($A['comunicacion_oficio'] ?? '') ?></div>
            </div>
            <div class="data-card g-6">
              <div class="label">Carpeta N°</div>
              <div class="value"><?= fmt($A['comunicacion_carpeta_nro'] ?? '') ?></div>
            </div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Descripción</h2>
          <div class="general-grid">
            <div class="data-card g-6">
              <div class="label">Sentido / dirección</div>
              <div class="value"><?= fmt($A['sentido'] ?? '') ?></div>
            </div>
            <div class="data-card g-6">
              <div class="label">Secuencia de eventos</div>
              <div class="value"><?= fmt($A['secuencia'] ?? '') ?></div>
            </div>
          </div>
        </div>
      </div>

      <form class="editable-form js-inline-ajax-form js-accidente-inline-form" id="accidente-inline-form" data-shell="general-accidente" data-error="accidente-inline-error" method="post" hidden>
        <input type="hidden" name="action" value="save_accidente_inline">
        <input type="hidden" name="accidente_id" value="<?= (int) $accidente_id ?>">

        <div class="section-block" style="margin-top:0">
          <h2 class="section-title">Clasificación del evento</h2>
          <div class="general-edit-grid">
            <div class="general-edit-card g-6">
              <label>Modalidades</label>
              <div class="general-checkbox-grid">
                <?php foreach ($modalidadesCatalog as $item): ?>
                  <label class="general-checkbox">
                    <input type="checkbox" name="modalidad_ids[]" value="<?= (int) $item['id'] ?>" <?= in_array((int) $item['id'], $modSel, true) ? 'checked' : '' ?>>
                    <span><?= h((string) $item['nombre']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="general-edit-card g-6">
              <label>Consecuencias</label>
              <div class="general-checkbox-grid">
                <?php foreach ($consecuenciasCatalog as $item): ?>
                  <label class="general-checkbox">
                    <input type="checkbox" name="consecuencia_ids[]" value="<?= (int) $item['id'] ?>" <?= in_array((int) $item['id'], $conSel, true) ? 'checked' : '' ?>>
                    <span><?= h((string) $item['nombre']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Identificación</h2>
          <div class="general-edit-grid">
            <div class="general-edit-card g-3">
              <label for="acc-registro-sidpol">Registro SIDPOL</label>
              <input class="edit-control" id="acc-registro-sidpol" type="text" name="registro_sidpol" maxlength="50" value="<?= h((string) ($accidenteBase['registro_sidpol'] ?? '')) ?>">
            </div>
            <div class="general-edit-card g-3">
              <label for="acc-estado">Estado</label>
              <select class="edit-control" id="acc-estado" name="estado">
                <?php foreach (['Pendiente', 'Resuelto', 'Con diligencias'] as $estadoOpt): ?>
                  <option value="<?= h($estadoOpt) ?>" <?= (string) ($accidenteBase['estado'] ?? 'Pendiente') === $estadoOpt ? 'selected' : '' ?>><?= h($estadoOpt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="general-edit-card g-3">
              <label for="acc-nro-informe">N° informe policial</label>
              <input class="edit-control" id="acc-nro-informe" type="text" name="nro_informe_policial" maxlength="40" value="<?= h((string) ($accidenteBase['nro_informe_policial'] ?? '')) ?>">
            </div>
            <div class="general-edit-card g-3">
              <label for="acc-comisaria">Comisaría</label>
              <select class="edit-control" id="acc-comisaria" name="comisaria_id" data-current="<?= h((string) ($accidenteBase['comisaria_id'] ?? '')) ?>" <?= empty($comis) ? 'disabled' : '' ?>>
                <option value="">-- Selecciona --</option>
                <?php foreach ($comis as $item): ?>
                  <?php $label = $item['nombre'] . (!empty($item['_fuera']) ? ' (fuera del distrito)' : ''); ?>
                  <option value="<?= (int) $item['id'] ?>" <?= (int) ($accidenteBase['comisaria_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Fechas</h2>
          <div class="general-edit-grid">
            <div class="general-edit-card g-4">
              <label for="acc-fecha-accidente">Fecha y hora del accidente</label>
              <input class="edit-control" id="acc-fecha-accidente" type="datetime-local" name="fecha_accidente" value="<?= h((string) ($accidenteBase['fecha_accidente'] ?? '')) ?>" required>
            </div>
            <div class="general-edit-card g-4">
              <label for="acc-fecha-comunicacion">Comunicación</label>
              <input class="edit-control" id="acc-fecha-comunicacion" type="datetime-local" name="fecha_comunicacion" value="<?= h((string) ($accidenteBase['fecha_comunicacion'] ?? '')) ?>">
            </div>
            <div class="general-edit-card g-4">
              <label for="acc-fecha-intervencion">Intervención</label>
              <input class="edit-control" id="acc-fecha-intervencion" type="datetime-local" name="fecha_intervencion" value="<?= h((string) ($accidenteBase['fecha_intervencion'] ?? '')) ?>">
            </div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Ubicación</h2>
          <div class="general-edit-grid">
            <div class="general-edit-card g-6">
              <label for="acc-lugar">Lugar</label>
              <input class="edit-control" id="acc-lugar" type="text" name="lugar" maxlength="200" value="<?= h((string) ($accidenteBase['lugar'] ?? '')) ?>" required>
            </div>
            <div class="general-edit-card g-3">
              <label for="acc-dep">Departamento</label>
              <select class="edit-control" id="acc-dep" name="cod_dep" required>
                <option value="">-- Selecciona --</option>
                <?php foreach ($deps as $item): ?>
                  <option value="<?= h((string) $item['cod_dep']) ?>" <?= (string) ($accidenteBase['cod_dep'] ?? '') === (string) $item['cod_dep'] ? 'selected' : '' ?>><?= h((string) $item['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="general-edit-card g-3">
              <label for="acc-prov">Provincia</label>
              <select class="edit-control" id="acc-prov" name="cod_prov" data-current="<?= h((string) ($accidenteBase['cod_prov'] ?? '')) ?>" required>
                <option value="">-- Selecciona --</option>
                <?php foreach ($provs as $item): ?>
                  <option value="<?= h((string) $item['cod_prov']) ?>" <?= (string) ($accidenteBase['cod_prov'] ?? '') === (string) $item['cod_prov'] ? 'selected' : '' ?>><?= h((string) $item['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="general-edit-card g-3">
              <label for="acc-dist">Distrito</label>
              <select class="edit-control" id="acc-dist" name="cod_dist" data-current="<?= h((string) ($accidenteBase['cod_dist'] ?? '')) ?>" required>
                <option value="">-- Selecciona --</option>
                <?php foreach ($dists as $item): ?>
                  <option value="<?= h((string) $item['cod_dist']) ?>" <?= (string) ($accidenteBase['cod_dist'] ?? '') === (string) $item['cod_dist'] ? 'selected' : '' ?>><?= h((string) $item['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="general-edit-card g-9">
              <label for="acc-referencia">Referencia</label>
              <input class="edit-control" id="acc-referencia" type="text" name="referencia" maxlength="200" value="<?= h((string) ($accidenteBase['referencia'] ?? '')) ?>">
            </div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Autoridades</h2>
          <div class="general-edit-grid">
            <div class="general-edit-card g-4">
              <label for="acc-fiscalia">Fiscalía</label>
              <select class="edit-control" id="acc-fiscalia" name="fiscalia_id">
                <option value="">-- Selecciona --</option>
                <?php foreach ($fiscaliasCatalog as $item): ?>
                  <option value="<?= (int) $item['id'] ?>" <?= (int) ($accidenteBase['fiscalia_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= h((string) $item['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="general-edit-card g-4">
              <label for="acc-fiscal">Fiscal a cargo</label>
              <select class="edit-control" id="acc-fiscal" name="fiscal_id" data-current="<?= h((string) ($accidenteBase['fiscal_id'] ?? '')) ?>">
                <option value="">-- Selecciona --</option>
                <?php foreach ($fiscalesDeFiscalia as $item): ?>
                  <option value="<?= (int) $item['id'] ?>" <?= (int) ($accidenteBase['fiscal_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= h((string) $item['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="general-edit-card g-4">
              <label for="acc-fiscal-tel">Tel. fiscal</label>
              <input class="edit-control" id="acc-fiscal-tel" type="text" value="<?= h((string) ($fiscalTelData['telefono'] ?? '')) ?>" readonly>
            </div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Comunicación</h2>
          <div class="general-edit-grid">
            <div class="general-edit-card g-4">
              <label for="acc-comunicante">Comunicante</label>
              <input class="edit-control" id="acc-comunicante" type="text" name="comunicante_nombre" maxlength="120" value="<?= h((string) ($accidenteBase['comunicante_nombre'] ?? '')) ?>">
            </div>
            <div class="general-edit-card g-4">
              <label for="acc-comunicante-tel">Tel. comunicante</label>
              <input class="edit-control" id="acc-comunicante-tel" type="text" name="comunicante_telefono" maxlength="20" value="<?= h((string) ($accidenteBase['comunicante_telefono'] ?? '')) ?>">
            </div>
            <div class="general-edit-card g-4">
              <label for="acc-comunicacion-decreto">Decreto</label>
              <input class="edit-control" id="acc-comunicacion-decreto" type="text" name="comunicacion_decreto" maxlength="120" value="<?= h((string) ($accidenteBase['comunicacion_decreto'] ?? '')) ?>">
            </div>
            <div class="general-edit-card g-6">
              <label for="acc-comunicacion-oficio">Oficio</label>
              <input class="edit-control" id="acc-comunicacion-oficio" type="text" name="comunicacion_oficio" maxlength="120" value="<?= h((string) ($accidenteBase['comunicacion_oficio'] ?? '')) ?>">
            </div>
            <div class="general-edit-card g-6">
              <label for="acc-comunicacion-carpeta">Carpeta N°</label>
              <input class="edit-control" id="acc-comunicacion-carpeta" type="text" name="comunicacion_carpeta_nro" maxlength="120" value="<?= h((string) ($accidenteBase['comunicacion_carpeta_nro'] ?? '')) ?>">
            </div>
          </div>
        </div>

        <div class="section-block">
          <h2 class="section-title">Descripción</h2>
          <div class="general-edit-grid">
            <div class="general-edit-card g-6">
              <label for="acc-sentido">Sentido / dirección</label>
              <input class="edit-control" id="acc-sentido" type="text" name="sentido" maxlength="100" value="<?= h((string) ($accidenteBase['sentido'] ?? '')) ?>">
            </div>
            <div class="general-edit-card g-6">
              <label for="acc-secuencia">Secuencia de eventos</label>
              <textarea class="edit-control" id="acc-secuencia" name="secuencia" rows="5"><?= h((string) ($accidenteBase['secuencia'] ?? '')) ?></textarea>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="tabs-shell">
    <div class="tabs-header main-tabs nav nav-tabs flex-nowrap" id="accTabs" role="tablist">
      <?php
        $mainTabs = [
            ['id' => 'resumen-integral', 'label' => 'RESUMEN', 'count' => $summaryBlocksCount],
            ['id' => 'itp', 'label' => 'ITP', 'count' => count($itps)],
            ['id' => 'participantes', 'label' => 'Participantes', 'count' => count($personas) + count($policias) + count($propietarios) + count($familiares) + count($abogados)],
            ['id' => 'documentos', 'label' => 'Documentos', 'count' => count($oficios) + count($documentosRecibidos)],
            ['id' => 'diligencias-pendientes', 'label' => 'DILIGENCIAS PENDIENTES', 'count' => count($diligencias)],
            ['id' => 'analisis', 'label' => 'Analisis', 'count' => $analysisTabCount],
        ];
      ?>
      <?php foreach ($mainTabs as $index => $tab): ?>
        <button class="nav-link tab-<?= h((string) $tab['id']) ?> <?= $index === 0 ? 'active' : '' ?>" id="<?= h($tab['id']) ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= h($tab['id']) ?>" type="button" role="tab">
          <span class="main-tab-title"><?= h($tab['label']) ?></span>
          <span class="tab-sub"><?= h((string) $tab['count']) ?> registro(s)</span>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="tab-content mt-2">
      <div class="tab-pane fade show active" id="resumen-integral" role="tabpanel">
        <div class="tab-panel">
          <div class="summary-sheet">
            <article class="module-card summary-block-card summary-block-card--intervention">
              <header>
                <div>
                  <h4 class="summary-block-title intervention-main-title">II. DATOS DE LA INTERVENCIÓN</h4>
                  <p class="intervention-subtitle">A. SITUACIÓN</p>
                </div>
              </header>
              <div class="intervention-sheet">
                <?php foreach ($resumenInterventionRows as $row): ?>
                  <div class="intervention-row">
                    <div class="intervention-index"><?= h((string) $row['index']) ?></div>
                    <div class="intervention-label"><?= h((string) $row['label']) ?></div>
                    <div class="intervention-sep">:</div>
                    <div class="intervention-value">
                      <span class="intervention-value-wrap">
                        <span class="intervention-value-main"><?= $row['value_html'] ?? h((string) ($row['value'] ?? '—')) ?></span>
                        <button type="button" class="copy-name-btn copy-inline-btn js-copy-name" data-copy-text="<?= h((string) ($row['copy_text'] ?? ($row['value'] ?? '—'))) ?>" aria-label="Copiar valor" title="Copiar valor">⧉</button>
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </article>

            <?php foreach ($summaryUnits as $summaryUnit): ?>
              <?php
                $unitPeople = $summaryUnit['personas'];
                $unitDrivers = array_values(array_filter($unitPeople, static fn(array $row): bool => is_conductor($row)));
                $unitCompanions = array_values(array_filter($unitPeople, static fn(array $row): bool => !is_conductor($row)));
              ?>
              <article class="module-card summary-block-card">
                <header>
                  <div>
                    <h4 class="summary-block-title"><?= h((string) $summaryUnit['ut']) ?></h4>
                    <p><?= h(count($summaryUnit['vehiculos']) . ' vehículo(s) · ' . count($unitPeople) . ' participante(s)') ?></p>
                  </div>
                  <div class="module-card-controls">
                    <span class="chip-simple"><?= h((string) $summaryUnit['ut']) ?></span>
                  </div>
                </header>

                <?php foreach ($summaryUnit['vehiculos'] as $vehIndex => $summaryVehicle): ?>
                  <?php
                    $vehTitle = count($summaryUnit['vehiculos']) > 1 ? 'Vehículo ' . summary_letter($vehIndex) : 'Vehículo';
                    $vehDocs = $docVehiculoTodosPorInvolucrado[(int) ($summaryVehicle['inv_vehiculo_id'] ?? 0)] ?? [];
                  ?>
                  <?= render_summary_vehicle_block($summaryVehicle, $vehDocs, $vehTitle, $summaryVehiculoFields, $summaryDocVehiculoSections) ?>
                <?php endforeach; ?>

                <?php if ($unitDrivers !== []): ?>
                  <div class="section-block">
                    <h3>Conductores</h3>
                    <div class="summary-doc-stack">
                      <?php foreach ($unitDrivers as $driverIndex => $summaryPerson): ?>
                        <?php $summaryExtras = $personaExtras[(int) ($summaryPerson['involucrado_id'] ?? 0)] ?? ['lc'=>[],'rml'=>[],'dos'=>[],'man'=>[],'occ'=>[]]; ?>
                        <?= render_summary_person_block(
                            $summaryPerson,
                            $summaryExtras,
                            count($unitDrivers) > 1 ? 'Conductor ' . summary_letter($driverIndex) : 'Conductor',
                            $summaryPersonSections,
                            $summaryLcFields,
                            $summaryRmlFields,
                            $summaryDosajeFields,
                            $summaryManifestacionFields,
                            $summaryOccLevantamientoFields,
                            $summaryOccPericialFields,
                            $summaryOccProtocoloFields,
                            $summaryOccEpicrisisFields
                        ) ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if ($unitCompanions !== []): ?>
                  <div class="section-block">
                    <h3>Ocupantes / pasajeros</h3>
                    <div class="summary-doc-stack">
                      <?php foreach ($unitCompanions as $companionIndex => $summaryPerson): ?>
                        <?php
                          $summaryExtras = $personaExtras[(int) ($summaryPerson['involucrado_id'] ?? 0)] ?? ['lc'=>[],'rml'=>[],'dos'=>[],'man'=>[],'occ'=>[]];
                          $companionRole = trim((string) ($summaryPerson['rol_nombre'] ?? 'Participante'));
                          $companionTitle = ($companionRole !== '' ? $companionRole : 'Participante') . ' ' . summary_letter($companionIndex);
                        ?>
                        <?= render_summary_person_block(
                            $summaryPerson,
                            $summaryExtras,
                            $companionTitle,
                            $summaryPersonSections,
                            $summaryLcFields,
                            $summaryRmlFields,
                            $summaryDosajeFields,
                            $summaryManifestacionFields,
                            $summaryOccLevantamientoFields,
                            $summaryOccPericialFields,
                            $summaryOccProtocoloFields,
                            $summaryOccEpicrisisFields
                        ) ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>

            <?php if ($summaryPeatones !== []): ?>
              <article class="module-card summary-block-card">
                <header>
                  <div>
                    <h4 class="summary-block-title">Peatones</h4>
                    <p><?= h(count($summaryPeatones)) ?> registro(s) vinculados al accidente.</p>
                  </div>
                </header>
                <div class="summary-doc-stack">
                  <?php foreach ($summaryPeatones as $peatonIndex => $summaryPerson): ?>
                    <?php $summaryExtras = $personaExtras[(int) ($summaryPerson['involucrado_id'] ?? 0)] ?? ['lc'=>[],'rml'=>[],'dos'=>[],'man'=>[],'occ'=>[]]; ?>
                    <?= render_summary_person_block(
                        $summaryPerson,
                        $summaryExtras,
                        'Peatón ' . summary_letter($peatonIndex),
                        $summaryPersonSections,
                        $summaryLcFields,
                        $summaryRmlFields,
                        $summaryDosajeFields,
                        $summaryManifestacionFields,
                        $summaryOccLevantamientoFields,
                        $summaryOccPericialFields,
                        $summaryOccProtocoloFields,
                        $summaryOccEpicrisisFields
                    ) ?>
                  <?php endforeach; ?>
                </div>
              </article>
            <?php endif; ?>

            <?php if ($summaryOtrosSinUnidad !== []): ?>
              <article class="module-card summary-block-card">
                <header>
                  <div>
                    <h4 class="summary-block-title">Otros involucrados sin unidad</h4>
                    <p>Personas vinculadas al accidente que no están asociadas a una UT o al bloque de peatones.</p>
                  </div>
                </header>
                <div class="summary-doc-stack">
                  <?php foreach ($summaryOtrosSinUnidad as $otherIndex => $summaryPerson): ?>
                    <?php $summaryExtras = $personaExtras[(int) ($summaryPerson['involucrado_id'] ?? 0)] ?? ['lc'=>[],'rml'=>[],'dos'=>[],'man'=>[],'occ'=>[]]; ?>
                    <?= render_summary_person_block(
                        $summaryPerson,
                        $summaryExtras,
                        'Involucrado ' . summary_letter($otherIndex),
                        $summaryPersonSections,
                        $summaryLcFields,
                        $summaryRmlFields,
                        $summaryDosajeFields,
                        $summaryManifestacionFields,
                        $summaryOccLevantamientoFields,
                        $summaryOccPericialFields,
                        $summaryOccProtocoloFields,
                        $summaryOccEpicrisisFields
                    ) ?>
                  <?php endforeach; ?>
                </div>
              </article>
            <?php endif; ?>

            <?php if ($policias !== []): ?>
              <article class="module-card summary-block-card">
                <header>
                  <div>
                    <h4 class="summary-block-title">Efectivos policiales</h4>
                    <p><?= h(count($policias)) ?> registro(s) vinculados al accidente.</p>
                  </div>
                </header>
                <div class="summary-doc-stack">
                  <?php foreach ($policias as $row): ?>
                    <?php
                      $summaryPoliciaPersonaId = (int) ($row['persona_id'] ?? 0);
                      $summaryPoliciaAbogados = summary_records_for_person_ids($abogadosPorPersona, [$summaryPoliciaPersonaId]);
                      $summaryPoliciaManifestaciones = summary_records_for_person_ids($manifestacionesPorPersona, [$summaryPoliciaPersonaId]);
                    ?>
                    <div class="summary-subcard" data-collapsible-card>
                      <div class="summary-header">
                        <div>
                          <h4 class="summary-person-name"><?= h(person_label($row)) ?></h4>
                          <p><?= h(trim((string) (($row['grado_policial'] ?? '-') . ' · CIP ' . ($row['cip'] ?? '-')))) ?></p>
                        </div>
                        <div class="summary-chipline">
                          <span class="chip-simple"><?= h((string) (($row['rol_funcion'] ?? '') !== '' ? $row['rol_funcion'] : 'Sin rol / función')) ?></span>
                          <button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button>
                        </div>
                      </div>
                      <div class="module-card-panel js-card-panel" hidden>
                        <div class="section-block">
                          <?= render_persona_story_block($row, 'Generales de ley') ?>
                        </div>
                        <div class="section-block">
                          <h3>Registro policial</h3>
                          <div class="field-grid"><?= render_field_cards($row, ['grado_policial', 'cip', ['key' => 'dependencia_policial', 'class' => 'span-2'], 'rol_funcion', ['key' => 'observaciones', 'class' => 'span-2']]) ?></div>
                        </div>
                        <?= render_summary_abogado_block($summaryPoliciaAbogados) ?>
                        <?= render_summary_manifestacion_block($summaryPoliciaManifestaciones, $summaryPoliciaAbogados, $summaryManifestacionContext) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>
            <?php endif; ?>

            <?php if ($familiares !== []): ?>
              <article class="module-card summary-block-card">
                <header>
                  <div>
                    <h4 class="summary-block-title">Familiares de fallecidos</h4>
                    <p>Bloque de familiares vinculados a personas fallecidas dentro del accidente.</p>
                  </div>
                </header>
                <div class="summary-doc-stack">
                  <?php foreach ($familiares as $row): ?>
                    <?php
                      $famRecord = project_prefixed_record($row, 'fam_');
                      $fallRecord = project_prefixed_record($row, 'fall_');
                      $summaryFamiliarPersonaId = (int) ($row['familiar_persona_id'] ?? 0);
                      $summaryFamiliarAbogados = summary_records_for_person_ids($abogadosPorPersona, [$summaryFamiliarPersonaId]);
                      $summaryFamiliarManifestaciones = summary_records_for_person_ids($manifestacionesPorPersona, [$summaryFamiliarPersonaId]);
                    ?>
                    <div class="summary-subcard" data-collapsible-card>
                      <div class="summary-header">
                        <div>
                          <h4 class="summary-person-name"><?= h(person_label($famRecord)) ?></h4>
                          <p>Familiar de <?= h(person_label($fallRecord)) ?></p>
                        </div>
                        <div class="summary-chipline">
                          <span class="chip-simple"><?= h((string) (($row['parentesco'] ?? '') !== '' ? $row['parentesco'] : 'Sin parentesco')) ?></span>
                          <button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button>
                        </div>
                      </div>
                      <div class="module-card-panel js-card-panel" hidden>
                        <div class="section-block">
                          <?= render_persona_story_block($famRecord, 'Generales de ley del familiar') ?>
                        </div>
                        <div class="section-block">
                          <h3>Registro familiar</h3>
                          <div class="field-grid"><?= render_field_cards($row, $summaryFamiliarRecordFields) ?></div>
                        </div>
                        <?= render_summary_abogado_block($summaryFamiliarAbogados) ?>
                        <?= render_summary_manifestacion_block($summaryFamiliarManifestaciones, $summaryFamiliarAbogados, $summaryManifestacionContext) ?>
                        <div class="section-block">
                          <?= render_persona_story_block($fallRecord, 'Generales de ley del fallecido') ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>
            <?php endif; ?>

            <?php if ($propietarios !== []): ?>
              <article class="module-card summary-block-card">
                <header>
                  <div>
                    <h4 class="summary-block-title">Propietarios de vehículos</h4>
                    <p>Información del propietario natural o jurídico y su representante, según corresponda.</p>
                  </div>
                </header>
                <div class="summary-doc-stack">
                  <?php foreach ($propietarios as $row): ?>
                    <?php
                      $ownerRecord = project_prefixed_record($row, 'owner_');
                      $repRecord = project_prefixed_record($row, 'rep_');
                      $principalName = (string) ($row['tipo_propietario'] ?? '') === 'NATURAL'
                        ? person_label($ownerRecord)
                        : ((string) ($row['razon_social'] ?? '') !== '' ? (string) $row['razon_social'] : 'Sin razón social');
                      $summaryPropietarioPersonaIds = (string) ($row['tipo_propietario'] ?? '') === 'JURIDICA'
                        ? [(int) ($row['representante_persona_id'] ?? 0), (int) ($row['propietario_persona_id'] ?? 0)]
                        : [(int) ($row['propietario_persona_id'] ?? 0), (int) ($row['representante_persona_id'] ?? 0)];
                      $summaryPropietarioAbogados = summary_records_for_person_ids($abogadosPorPersona, $summaryPropietarioPersonaIds);
                      $summaryPropietarioManifestaciones = summary_records_for_person_ids($manifestacionesPorPersona, $summaryPropietarioPersonaIds);
                    ?>
                    <div class="summary-subcard" data-collapsible-card>
                      <div class="summary-header">
                        <div>
                          <h4 class="summary-person-name"><?= h($principalName) ?></h4>
                          <p><?= h(trim((string) (($row['orden_participacion'] ?? '') . ' · Placa ' . vehiculo_placa_visible((string) ($row['placa'] ?? ''))))) ?></p>
                        </div>
                        <div class="summary-chipline">
                          <span class="chip-simple"><?= h((string) (($row['tipo_propietario'] ?? '') !== '' ? $row['tipo_propietario'] : 'Sin tipo')) ?></span>
                          <button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button>
                        </div>
                      </div>
                      <div class="module-card-panel js-card-panel" hidden>
                      <?php if ((string) ($row['tipo_propietario'] ?? '') === 'NATURAL'): ?>
                        <div class="section-block">
                          <?= render_persona_story_block($ownerRecord, 'Generales de ley del propietario') ?>
                        </div>
                      <?php endif; ?>
                      <?php if (trim((string) ($repRecord['nombres'] ?? '')) !== ''): ?>
                        <div class="section-block">
                          <?= render_persona_story_block($repRecord, 'Generales de ley del representante') ?>
                        </div>
                      <?php endif; ?>
                      <div class="section-block">
                        <h3>Registro propietario</h3>
                        <div class="field-grid"><?= render_field_cards($row, $summaryOwnerRecordFields) ?></div>
                      </div>
                      <?= render_summary_abogado_block($summaryPropietarioAbogados) ?>
                      <?= render_summary_manifestacion_block($summaryPropietarioManifestaciones, $summaryPropietarioAbogados, $summaryManifestacionContext) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>
            <?php endif; ?>

            <?php if ($abogados !== []): ?>
              <article class="module-card summary-block-card">
                <header>
                  <div>
                    <h4 class="summary-block-title">Abogados</h4>
                    <p>Abogados vinculados al accidente y a las personas representadas.</p>
                  </div>
                </header>
                <div class="summary-doc-stack">
                  <?php foreach ($abogados as $row): ?>
                    <div class="summary-subcard" data-collapsible-card>
                      <div class="summary-header">
                        <div>
                          <h4 class="summary-person-name"><?= h(trim((string) (($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ', ' . ($row['nombres'] ?? '')))) ?></h4>
                          <p><?= h((string) (($row['persona_rep_nom'] ?? '') !== '' ? $row['persona_rep_nom'] : 'Sin persona representada')) ?></p>
                        </div>
                        <div class="summary-chipline">
                          <span class="chip-simple"><?= h((string) (($row['colegiatura'] ?? '') !== '' ? $row['colegiatura'] : 'Sin colegiatura')) ?></span>
                          <span class="chip-simple"><?= h((string) (($row['registro'] ?? '') !== '' ? $row['registro'] : 'Sin registro')) ?></span>
                          <button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button>
                        </div>
                      </div>
                      <div class="module-card-panel js-card-panel" hidden>
                      <?= render_summary_field_sections($row, $abogadoSections) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>
            <?php endif; ?>

            <?php if ($oficios !== []): ?>
              <article class="module-card summary-block-card">
                <header>
                  <div>
                    <h4 class="summary-block-title">Oficios realizados</h4>
                    <p>Relación de oficios emitidos y su vínculo con unidades, personas o asuntos del accidente.</p>
                  </div>
                </header>
                <div class="summary-doc-stack">
                  <?php foreach ($oficios as $row): ?>
                    <div class="summary-subcard" data-collapsible-card>
                      <div class="summary-header">
                        <div>
                          <h4><?= h('Oficio N° ' . ($row['numero'] ?? '—') . '/' . ($row['anio'] ?? '—')) ?></h4>
                          <p><?= h(fecha_simple($row['fecha_emision'] ?? null)) ?></p>
                        </div>
                        <div class="summary-chipline">
                          <span class="chip-simple"><?= h((string) (($row['estado'] ?? '') !== '' ? $row['estado'] : 'Sin estado')) ?></span>
                          <button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button>
                        </div>
                      </div>
                      <div class="module-card-panel js-card-panel" hidden>
                      <div class="field-grid"><?= render_field_cards($row, $summaryOficioFields) ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="itp" role="tabpanel">
        <div class="tab-panel">
          <?php
            $itpGeneralFields = ['fecha_itp', 'hora_itp', 'forma_via', ['key' => 'punto_referencia', 'class' => 'span-2'], ['key' => 'ubicacion_gps', 'class' => 'span-2']];
            $itpViaSimpleFields = ['configuracion_via1', 'material_via1', 'senializacion_via1', 'ordenamiento_via1', 'iluminacion_via1', 'visibilidad_via1', 'intensidad_via1', 'fluidez_via1'];
            $itpVia2SimpleFields = ['configuracion_via2', 'material_via2', 'senializacion_via2', 'ordenamiento_via2', 'iluminacion_via2', 'visibilidad_via2', 'intensidad_via2', 'fluidez_via2'];
            $itpEditGeneralFields = [
                ['name' => 'fecha_itp', 'type' => 'date'],
                ['name' => 'hora_itp', 'type' => 'time'],
                ['name' => 'forma_via'],
                ['name' => 'punto_referencia', 'class' => 'span-2'],
                ['name' => 'ubicacion_gps', 'class' => 'span-2'],
            ];
            $itpEditVia1Fields = [
                ['name' => 'descripcion_via1', 'type' => 'textarea', 'rows' => 4, 'class' => 'span-2'],
                ['name' => 'configuracion_via1'],
                ['name' => 'material_via1'],
                ['name' => 'senializacion_via1'],
                ['name' => 'ordenamiento_via1'],
                ['name' => 'iluminacion_via1'],
                ['name' => 'visibilidad_via1'],
                ['name' => 'intensidad_via1'],
                ['name' => 'fluidez_via1'],
            ];
            $itpEditVia2Fields = [
                ['name' => 'descripcion_via2', 'type' => 'textarea', 'rows' => 4, 'class' => 'span-2'],
                ['name' => 'configuracion_via2'],
                ['name' => 'material_via2'],
                ['name' => 'senializacion_via2'],
                ['name' => 'ordenamiento_via2'],
                ['name' => 'iluminacion_via2'],
                ['name' => 'visibilidad_via2'],
                ['name' => 'intensidad_via2'],
                ['name' => 'fluidez_via2'],
            ];
            $itpEditEvidenceFields = [
                ['name' => 'evidencia_biologica', 'type' => 'textarea', 'rows' => 4, 'class' => 'span-2'],
                ['name' => 'evidencia_fisica', 'type' => 'textarea', 'rows' => 4, 'class' => 'span-2'],
                ['name' => 'evidencia_material', 'type' => 'textarea', 'rows' => 4, 'class' => 'span-2'],
            ];
          ?>
          <div class="module-actions" style="margin-bottom:8px;">
            <a class="btn-shell" href="itp_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo ITP</a>
            <a class="btn-shell" href="itp_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
            <a class="btn-shell" href="Dato_General_accidente.php?accidente_id=<?= (int) $accidente_id ?>">Datos generales SIDPOL</a>
          </div>
          <?php if (!$itps): ?>
            <div class="empty-state">No hay registros ITP para este accidente.</div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($itps as $row): ?>
                <?php
                  $hasVia2 = false;
                  foreach ([
                    'descripcion_via2', 'configuracion_via2', 'material_via2', 'senializacion_via2', 'ordenamiento_via2',
                    'iluminacion_via2', 'visibilidad_via2', 'intensidad_via2', 'fluidez_via2', 'medidas_via2', 'observaciones_via2'
                  ] as $field) {
                    if (!empty($row[$field])) {
                        $hasVia2 = true;
                        break;
                    }
                  }
                  $itpShell = 'itp-' . (int) $row['id'];
                ?>
                <article class="module-card">
                  <header>
                    <div>
                      <h4>ITP #<?= (int) $row['id'] ?></h4>
                      <p>
                        <?= h((string) (($row['fecha_itp'] ?? '') !== '' ? fecha_simple((string) $row['fecha_itp']) : 'Sin fecha')) ?>
                        <?php if (!empty($row['hora_itp'])): ?> · <?= h((string) $row['hora_itp']) ?><?php endif; ?>
                      </p>
                    </div>
                    <span class="chip-simple">SIDPOL <?= h((string) (($row['registro_sidpol'] ?? '') !== '' ? $row['registro_sidpol'] : '—')) ?></span>
                  </header>
                  <div class="module-meta">
                    <span class="chip-simple"><?= h((string) (($row['forma_via'] ?? '') !== '' ? $row['forma_via'] : 'Sin forma de vía')) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['punto_referencia'] ?? '') !== '' ? $row['punto_referencia'] : 'Sin punto de referencia')) ?></span>
                    <?php if (!empty($row['ubicacion_gps'])): ?><a class="chip-simple" href="https://www.google.com/maps?q=<?= urlencode((string) $row['ubicacion_gps']) ?>" target="_blank" rel="noopener">GPS</a><?php endif; ?>
                  </div>
                  <div class="editable-shell" data-edit-shell="<?= h($itpShell) ?>">
                    <div class="editable-toolbar">
                      <div class="record-actions" style="margin-top:0">
                        <a class="btn-shell" href="itp_ver.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                        <a class="btn-shell" href="itp_eliminar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Eliminar</a>
                      </div>
                      <div class="editable-actions">
                        <button type="button" class="btn-shell js-edit-start" data-shell="<?= h($itpShell) ?>">Editar</button>
                        <div class="editable-actions" data-edit-actions="<?= h($itpShell) ?>" hidden>
                          <button type="button" class="btn-shell js-edit-cancel" data-shell="<?= h($itpShell) ?>">Cancelar</button>
                          <button type="submit" class="btn-shell btn-primary" form="itp-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                        </div>
                      </div>
                    </div>

                    <div class="inline-edit-error" id="itp-inline-error-<?= (int) $row['id'] ?>"></div>

                    <div class="editable-view" data-edit-view="<?= h($itpShell) ?>">
                      <div class="section-block">
                        <h3>Datos generales</h3>
                        <div class="field-grid"><?= render_field_cards($row, $itpGeneralFields) ?></div>
                        <div class="field-grid" style="margin-top:6px;">
                          <div class="field-card span-4">
                            <div class="field-label">Ocurrencia policial</div>
                            <div class="field-value"><?= nl2br(h((string) (($row['ocurrencia_policial'] ?? '') !== '' ? $row['ocurrencia_policial'] : '—'))) ?></div>
                          </div>
                          <div class="field-card span-2">
                            <div class="field-label">Localización de unidades</div>
                            <div class="field-value"><?= render_csv_list_html((string) ($row['localizacion_unidades'] ?? '')) ?></div>
                          </div>
                          <div class="field-card span-2">
                            <div class="field-label">Llegada al lugar</div>
                            <div class="field-value"><?= nl2br(h((string) (($row['llegada_lugar'] ?? '') !== '' ? $row['llegada_lugar'] : '—'))) ?></div>
                          </div>
                        </div>
                      </div>
                      <div class="section-block">
                        <h3>Vía 1</h3>
                        <div class="field-grid">
                          <div class="field-card span-2">
                            <div class="field-label">Descripción</div>
                            <div class="field-value"><?= nl2br(h((string) (($row['descripcion_via1'] ?? '') !== '' ? $row['descripcion_via1'] : '—'))) ?></div>
                          </div>
                          <?= render_field_cards($row, $itpViaSimpleFields) ?>
                          <div class="field-card span-2">
                            <div class="field-label">Medidas</div>
                            <div class="field-value"><?= render_csv_list_html((string) ($row['medidas_via1'] ?? '')) ?></div>
                          </div>
                          <div class="field-card span-2">
                            <div class="field-label">Observaciones</div>
                            <div class="field-value"><?= render_csv_list_html((string) ($row['observaciones_via1'] ?? '')) ?></div>
                          </div>
                        </div>
                      </div>
                      <?php if ($hasVia2): ?>
                        <div class="section-block">
                          <h3>Vía 2</h3>
                          <div class="field-grid">
                            <div class="field-card span-2">
                              <div class="field-label">Descripción</div>
                              <div class="field-value"><?= nl2br(h((string) (($row['descripcion_via2'] ?? '') !== '' ? $row['descripcion_via2'] : '—'))) ?></div>
                            </div>
                            <?= render_field_cards($row, $itpVia2SimpleFields) ?>
                            <div class="field-card span-2">
                              <div class="field-label">Medidas</div>
                              <div class="field-value"><?= render_csv_list_html((string) ($row['medidas_via2'] ?? '')) ?></div>
                            </div>
                            <div class="field-card span-2">
                              <div class="field-label">Observaciones</div>
                              <div class="field-value"><?= render_csv_list_html((string) ($row['observaciones_via2'] ?? '')) ?></div>
                            </div>
                          </div>
                        </div>
                      <?php endif; ?>
                      <div class="section-block">
                        <h3>Evidencias</h3>
                        <div class="field-grid">
                          <div class="field-card span-2">
                            <div class="field-label">Evidencia biológica</div>
                            <div class="field-value"><?= nl2br(h((string) (($row['evidencia_biologica'] ?? '') !== '' ? $row['evidencia_biologica'] : '—'))) ?></div>
                          </div>
                          <div class="field-card span-2">
                            <div class="field-label">Evidencia física</div>
                            <div class="field-value"><?= nl2br(h((string) (($row['evidencia_fisica'] ?? '') !== '' ? $row['evidencia_fisica'] : '—'))) ?></div>
                          </div>
                          <div class="field-card span-2">
                            <div class="field-label">Evidencia material</div>
                            <div class="field-value"><?= nl2br(h((string) (($row['evidencia_material'] ?? '') !== '' ? $row['evidencia_material'] : '—'))) ?></div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <form class="editable-form js-inline-ajax-form" id="itp-inline-form-<?= (int) $row['id'] ?>" data-shell="<?= h($itpShell) ?>" data-error="itp-inline-error-<?= (int) $row['id'] ?>" method="post" hidden>
                      <input type="hidden" name="action" value="save_itp_inline">
                      <input type="hidden" name="itp_id" value="<?= (int) $row['id'] ?>">
                      <div class="section-block">
                        <h3>Datos generales</h3>
                        <div class="field-grid"><?= render_editable_fields($row, $itpEditGeneralFields, 'itp-general-' . (int) $row['id']) ?></div>
                        <div class="field-grid" style="margin-top:6px;">
                          <div class="field-card edit-field span-4">
                            <label class="edit-label" for="itp-general-ocurrencia-<?= (int) $row['id'] ?>">Ocurrencia policial</label>
                            <textarea class="edit-control" id="itp-general-ocurrencia-<?= (int) $row['id'] ?>" name="ocurrencia_policial" rows="6"><?= h((string) ($row['ocurrencia_policial'] ?? '')) ?></textarea>
                          </div>
                          <div class="field-card edit-field span-2">
                            <label class="edit-label" for="itp-general-localizacion-<?= (int) $row['id'] ?>">Localización de unidades</label>
                            <textarea class="edit-control" id="itp-general-localizacion-<?= (int) $row['id'] ?>" name="localizacion_unidades" rows="5"><?= h((string) ($row['localizacion_unidades'] ?? '')) ?></textarea>
                          </div>
                          <div class="field-card edit-field span-2">
                            <label class="edit-label" for="itp-general-llegada-<?= (int) $row['id'] ?>">Llegada al lugar</label>
                            <textarea class="edit-control" id="itp-general-llegada-<?= (int) $row['id'] ?>" name="llegada_lugar" rows="5"><?= h((string) ($row['llegada_lugar'] ?? '')) ?></textarea>
                          </div>
                        </div>
                      </div>
                      <div class="section-block">
                        <h3>Vía 1</h3>
                        <div class="field-grid"><?= render_editable_fields($row, $itpEditVia1Fields, 'itp-via1-' . (int) $row['id']) ?></div>
                        <div class="section-block">
                          <h3>Medidas</h3>
                          <div class="itp-builder measurebox js-itp-measurebox" data-values="<?= h((string) ($row['medidas_via1'] ?? '')) ?>">
                            <div class="itp-builder-row">
                              <input class="edit-control m-name" type="text" placeholder="Que mides">
                              <input class="edit-control m-value" type="text" placeholder="Valor">
                              <button type="button" class="btn-shell m-add">Agregar</button>
                            </div>
                            <div class="itp-builder-list measure-list"></div>
                            <input type="hidden" name="medidas_via1" value="<?= h((string) ($row['medidas_via1'] ?? '')) ?>">
                          </div>
                        </div>
                        <div class="section-block">
                          <h3>Observaciones</h3>
                          <div class="itp-builder tagbox js-itp-tagbox" data-values="<?= h((string) ($row['observaciones_via1'] ?? '')) ?>">
                            <div class="itp-builder-list tag-items"></div>
                            <div class="itp-builder-row">
                              <input class="edit-control" type="text" placeholder="Agregar observación">
                              <button type="button" class="btn-shell">Agregar</button>
                            </div>
                            <input type="hidden" name="observaciones_via1" value="<?= h((string) ($row['observaciones_via1'] ?? '')) ?>">
                          </div>
                        </div>
                      </div>
                      <input type="hidden" name="via2_flag" value="<?= $hasVia2 ? '1' : '0' ?>" class="js-itp-via2-flag">
                      <div class="record-actions" style="margin-top:0;margin-bottom:6px;">
                        <button type="button" class="btn-shell js-itp-via2-add" <?= $hasVia2 ? 'hidden' : '' ?>>+ Añadir vía 2</button>
                        <button type="button" class="btn-shell js-itp-via2-remove" <?= $hasVia2 ? '' : 'hidden' ?>>Quitar vía 2</button>
                      </div>
                      <div class="js-itp-via2-section" <?= $hasVia2 ? '' : 'hidden' ?>>
                        <div class="section-block">
                          <h3>Vía 2</h3>
                          <div class="field-grid"><?= render_editable_fields($row, $itpEditVia2Fields, 'itp-via2-' . (int) $row['id']) ?></div>
                          <div class="section-block">
                            <h3>Medidas</h3>
                            <div class="itp-builder measurebox js-itp-measurebox" data-values="<?= h((string) ($row['medidas_via2'] ?? '')) ?>">
                              <div class="itp-builder-row">
                                <input class="edit-control m-name" type="text" placeholder="Que mides">
                                <input class="edit-control m-value" type="text" placeholder="Valor">
                                <button type="button" class="btn-shell m-add">Agregar</button>
                              </div>
                              <div class="itp-builder-list measure-list"></div>
                              <input type="hidden" name="medidas_via2" value="<?= h((string) ($row['medidas_via2'] ?? '')) ?>">
                            </div>
                          </div>
                          <div class="section-block">
                            <h3>Observaciones</h3>
                            <div class="itp-builder tagbox js-itp-tagbox" data-values="<?= h((string) ($row['observaciones_via2'] ?? '')) ?>">
                              <div class="itp-builder-list tag-items"></div>
                              <div class="itp-builder-row">
                                <input class="edit-control" type="text" placeholder="Agregar observación">
                                <button type="button" class="btn-shell">Agregar</button>
                              </div>
                              <input type="hidden" name="observaciones_via2" value="<?= h((string) ($row['observaciones_via2'] ?? '')) ?>">
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="section-block">
                        <h3>Evidencias</h3>
                        <div class="field-grid"><?= render_editable_fields($row, $itpEditEvidenceFields, 'itp-evid-' . (int) $row['id']) ?></div>
                      </div>
                    </form>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="tab-pane fade" id="participantes" role="tabpanel">
        <div class="tab-panel">
          <div class="tabs-toolbar">
            <a class="btn-shell" href="involucrados_personas_listar.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo persona involucrada</a>
            <a class="btn-shell" href="involucrados_vehiculos_listar.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo vehículo involucrado</a>
          </div>
          <div class="inline-workbench" id="participantes-workbench" hidden>
            <div class="inline-head">
              <strong id="participantes-workbench-title">Formulario</strong>
              <button type="button" class="btn-shell js-inline-close" data-workbench="participantes-workbench" data-frame="participantes-workbench-frame">Cerrar</button>
            </div>
            <iframe class="inline-frame" id="participantes-workbench-frame" src="about:blank" loading="lazy"></iframe>
          </div>
          <?php
            $participantFixedTabs = [
                ['id' => 'efectivo-policial', 'label' => '👮 Efectivo policial', 'count' => count($policias)],
                ['id' => 'propietario-vehiculo', 'label' => '🚗 Propietario vehículo', 'count' => count($propietarios)],
                ['id' => 'familiar-fallecido', 'label' => '💀 Familiar fallecido', 'count' => count($familiares)],
                ['id' => 'abogados', 'label' => '⚖️ Abogados', 'count' => count($abogados)],
            ];
            $occLevantamientoFields = [
                'fecha_levantamiento', 'hora_levantamiento', ['key' => 'lugar_levantamiento', 'class' => 'span-2'],
                'posicion_cuerpo_levantamiento', ['key' => 'lesiones_levantamiento', 'class' => 'span-2'],
                'presuntivo_levantamiento', 'legista_levantamiento', 'cmp_legista',
                ['key' => 'observaciones_levantamiento', 'class' => 'span-4'],
            ];
            $occPericialFields = [
                'numero_pericial', 'fecha_pericial', 'hora_pericial',
                ['key' => 'observaciones_pericial', 'class' => 'span-4'],
            ];
            $occProtocoloFields = [
                'numero_protocolo', 'fecha_protocolo', 'hora_protocolo',
                ['key' => 'lesiones_protocolo', 'class' => 'span-2'],
                'presuntivo_protocolo', 'dosaje_protocolo', 'toxicologico_protocolo',
            ];
            $occEpicrisisFields = [
                ['key' => 'nosocomio_epicrisis', 'class' => 'span-2'],
                'numero_historia_epicrisis', 'hora_alta_epicrisis',
                ['key' => 'tratamiento_epicrisis', 'class' => 'span-4'],
            ];
          ?>
          <div class="tabs-header participant-tabs nav nav-tabs flex-nowrap" id="participantes-tabs" role="tablist">
            <?php $participantTabIndex = 0; ?>
            <?php foreach ($personas as $persona): ?>
              <?php $tabId = 'persona-' . (int) $persona['involucrado_id']; ?>
              <button class="nav-link <?= h(person_tab_tone_class($persona)) ?> <?= $participantTabIndex === 0 ? 'active' : '' ?>" id="<?= h($tabId) ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= h($tabId) ?>" type="button" role="tab">
                <?= h(tab_person_short_name($persona)) ?>
                <span class="tab-sub"><?= h(tab_person_label($persona)) ?></span>
              </button>
              <?php $participantTabIndex++; ?>
            <?php endforeach; ?>
            <?php foreach ($participantFixedTabs as $tab): ?>
              <button class="nav-link <?= $participantTabIndex === 0 ? 'active' : '' ?>" id="<?= h($tab['id']) ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= h($tab['id']) ?>" type="button" role="tab">
                <?= h($tab['label']) ?>
                <span class="tab-sub"><?= h((string) $tab['count']) ?> registro(s)</span>
              </button>
              <?php $participantTabIndex++; ?>
            <?php endforeach; ?>
          </div>

          <div class="tab-content mt-2">
            <?php $participantPaneIndex = 0; ?>
      <?php foreach ($personas as $persona): ?>
        <?php
          $tabId = 'persona-' . (int) $persona['involucrado_id'];
          $isDriver = is_conductor($persona);
          $comboVehiculos = [];
          if ($isDriver && es_participacion_combinada($persona['veh_participacion'] ?? null) && !empty($persona['orden_participacion'])) {
              $comboVehiculos = $comboVehiculosPorUnidad[trim((string) $persona['orden_participacion'])] ?? [];
          }
          $hasComboVehiculos = count($comboVehiculos) > 1;
          $singleDocVehiculo = null;
          $singleDocVehiculoCount = 0;
          if (!$hasComboVehiculos && !empty($persona['inv_vehiculo_id'])) {
              $singleInvVehiculoId = (int) $persona['inv_vehiculo_id'];
              $singleDocVehiculo = $docVehiculoPorInvolucrado[$singleInvVehiculoId] ?? null;
              $singleDocVehiculoCount = (int) ($docVehiculoCantidadPorInvolucrado[$singleInvVehiculoId] ?? 0);
          }
          $extras = $personaExtras[(int) $persona['involucrado_id']] ?? ['lc'=>[],'rml'=>[],'dos'=>[],'man'=>[],'occ'=>[],'show_lc'=>false,'show_rml'=>false,'show_dos'=>false,'show_man'=>false,'show_occ'=>false];
          $wa = preg_replace('/\D+/', '', (string) ($persona['celular'] ?? ''));
          $whatsAppMsg = whatsapp_contact_message($modalidades, $A['fecha_accidente'] ?? null, $A['lugar'] ?? null);
          $manifestDownloadUrl = '';
          if (!empty($extras['show_man']) && (int) $persona['involucrado_id'] > 0 && (int) $accidente_id > 0) {
              $manifestDownloadUrl = 'marcador_manifestacion_investigado.php?involucrado_id=' . (int) $persona['involucrado_id'] . '&accidente_id=' . (int) $accidente_id . '&download=1';
          }
          $personPaneId = 'person-pane-' . (int) $persona['involucrado_id'];
          $returnToTabs = $_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id);
        ?>
        <div class="tab-pane fade <?= $participantPaneIndex === 0 ? 'show active' : '' ?>" id="<?= h($tabId) ?>" role="tabpanel">
          <div class="tab-panel <?= h(person_panel_tone_class($persona)) ?>">
            <div class="person-hero">
              <div class="person-title">
                <h2>
                  <span class="person-name-copy">
                    <span><?= h(person_label($persona)) ?></span>
                    <button type="button" class="copy-name-btn js-copy-name" data-copy-text="<?= h(person_label($persona)) ?>" aria-label="Copiar nombre" title="Copiar nombre">Copiar</button>
                    <span class="person-quick-actions">
                      <?php if ($wa): ?>
                        <a class="quick-pill-btn whatsapp" href="https://wa.me/<?= h($wa) ?>?text=<?= rawurlencode($whatsAppMsg) ?>" target="_blank" rel="noopener" aria-label="Abrir WhatsApp" title="Abrir WhatsApp">WA</a>
                      <?php endif; ?>
                      <a class="btn-shell btn-citacion" href="citacion_rapida.php?accidente_id=<?= (int) $accidente_id ?>&persona=<?= urlencode('INV:' . (int) $persona['involucrado_id']) ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Citación rápida</a>
                    </span>
                  </span>
                </h2>
                <p><?php if (person_heading_meta($persona) !== ''): ?><?= h(person_heading_meta($persona)) ?> · <?php endif; ?><?= h(tab_person_label($persona)) ?><?php if (!empty($persona['orden_participacion'])): ?> · <?= h((string) $persona['orden_participacion']) ?><?php endif; ?></p>
              </div>
              <div class="chip-row">
                <?php if (!empty($persona['rol_nombre'])): ?><span class="<?= h(role_chip_class((string) $persona['rol_nombre'])) ?>"><?= h((string) $persona['rol_nombre']) ?></span><?php endif; ?>
                <?php if (!empty($persona['lesion'])): ?><span class="<?= h(lesion_chip_class((string) $persona['lesion'])) ?>"><?= h((string) $persona['lesion']) ?></span><?php endif; ?>
                <span class="chip-simple"><?= !empty($persona['vehiculo_id']) ? 'Con vehículo' : 'Sin vehículo' ?></span>
                <?php if (!empty($persona['veh_chip_text'])): ?><span class="chip-simple">Vehículo <?= h((string) $persona['veh_chip_text']) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="inner-tabs nav nav-tabs flex-nowrap" id="<?= h($personPaneId) ?>" role="tablist">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-persona" type="button" role="tab">
                Persona
                <span class="tab-mini">Ficha principal</span>
              </button>
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-participacion" type="button" role="tab">
                Participación
                <span class="tab-mini">Accidente</span>
              </button>
              <?php if ($hasComboVehiculos): ?>
                <?php foreach ($comboVehiculos as $comboVehiculo): ?>
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-vehiculo-<?= h((string) ($comboVehiculo['veh_numero'] ?? '')) ?>" type="button" role="tab">
                    Vehículo <?= h((string) ($comboVehiculo['veh_numero'] ?? '')) ?>
                    <span class="tab-mini">Solo conductor</span>
                  </button>
                <?php endforeach; ?>
              <?php elseif ($isDriver && !empty($persona['veh_id'])): ?>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-vehiculo" type="button" role="tab">
                  Vehículo
                  <span class="tab-mini">Solo conductor</span>
                </button>
              <?php endif; ?>
              <?php if ($extras['show_lc']): ?>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-lc" type="button" role="tab">
                  Licencia
                  <span class="tab-mini"><?= count($extras['lc']) ?> registro(s)</span>
                </button>
              <?php endif; ?>
              <?php if ($extras['show_rml']): ?>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-rml" type="button" role="tab">
                  RML
                  <span class="tab-mini"><?= count($extras['rml']) ?> registro(s)</span>
                </button>
              <?php endif; ?>
              <?php if ($extras['show_dos']): ?>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-dos" type="button" role="tab">
                  Dosaje
                  <span class="tab-mini"><?= count($extras['dos']) ?> registro(s)</span>
                </button>
              <?php endif; ?>
              <?php if ($extras['show_man']): ?>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-man" type="button" role="tab">
                  Manifestación
                  <span class="tab-mini"><?= count($extras['man']) ?> registro(s)</span>
                </button>
              <?php endif; ?>
              <?php if ($extras['show_occ']): ?>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-occ" type="button" role="tab">
                  Occiso
                  <span class="tab-mini"><?= count($extras['occ']) ?> registro(s)</span>
                </button>
              <?php endif; ?>
            </div>

            <div class="inline-workbench" id="workbench-<?= (int) $persona['involucrado_id'] ?>" hidden>
              <div class="inline-head">
                <strong id="workbench-title-<?= (int) $persona['involucrado_id'] ?>">Formulario</strong>
                <button type="button" class="btn-shell js-inline-close" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>">Cerrar</button>
              </div>
              <iframe class="inline-frame" id="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" src="about:blank" loading="lazy"></iframe>
            </div>

            <div class="tab-content mt-2">
              <div class="tab-pane fade show active" id="<?= h($personPaneId) ?>-persona" role="tabpanel">
                <div class="inner-panel">
                  <div class="editable-shell" data-edit-shell="persona-<?= (int) $persona['involucrado_id'] ?>">
                    <div class="editable-toolbar">
                      <div></div>
                      <div class="editable-actions">
                        <button type="button" class="btn-shell js-edit-start" data-shell="persona-<?= (int) $persona['involucrado_id'] ?>">Editar persona</button>
                        <div class="editable-actions" data-edit-actions="persona-<?= (int) $persona['involucrado_id'] ?>" hidden>
                          <button type="button" class="btn-shell js-edit-cancel" data-shell="persona-<?= (int) $persona['involucrado_id'] ?>">Cancelar</button>
                          <button type="submit" class="btn-shell btn-primary" form="persona-inline-form-<?= (int) $persona['involucrado_id'] ?>">Guardar</button>
                        </div>
                      </div>
                    </div>

                    <div class="inline-edit-error" id="persona-inline-error-<?= (int) $persona['involucrado_id'] ?>"></div>

                    <div class="editable-view" data-edit-view="persona-<?= (int) $persona['involucrado_id'] ?>">
                      <?php foreach ($personaSections as $sectionTitle => $sectionFields): ?>
                        <div class="section-block">
                          <h3><?= h($sectionTitle) ?></h3>
                          <div class="field-grid"><?= render_field_cards($persona, $sectionFields) ?></div>
                        </div>
                      <?php endforeach; ?>
                    </div>

                    <form class="editable-form js-inline-ajax-form js-persona-inline-form" id="persona-inline-form-<?= (int) $persona['involucrado_id'] ?>" data-shell="persona-<?= (int) $persona['involucrado_id'] ?>" data-error="persona-inline-error-<?= (int) $persona['involucrado_id'] ?>" method="post" hidden>
                      <input type="hidden" name="action" value="save_persona_inline">
                      <input type="hidden" name="persona_id" value="<?= (int) $persona['persona_id'] ?>">
                      <input type="hidden" name="foto_path" value="<?= h((string) ($persona['foto_path'] ?? '')) ?>">
                      <input type="hidden" name="api_fuente" value="<?= h((string) ($persona['api_fuente'] ?? '')) ?>">
                      <input type="hidden" name="api_ref" value="<?= h((string) ($persona['api_ref'] ?? '')) ?>">

                      <?php foreach ($personaEditSections as $sectionTitle => $sectionFields): ?>
                        <div class="section-block">
                          <h3><?= h($sectionTitle) ?></h3>
                          <div class="field-grid"><?= render_editable_fields($persona, $sectionFields, 'persona-' . (int) $persona['involucrado_id']) ?></div>
                        </div>
                      <?php endforeach; ?>
                    </form>

                  </div>
                </div>
              </div>

              <div class="tab-pane fade" id="<?= h($personPaneId) ?>-participacion" role="tabpanel">
                <div class="inner-panel">
                  <div class="editable-shell" data-edit-shell="participacion-<?= (int) $persona['involucrado_id'] ?>">
                    <div class="editable-toolbar">
                      <div></div>
                      <div class="editable-actions">
                        <button type="button" class="btn-shell js-edit-start" data-shell="participacion-<?= (int) $persona['involucrado_id'] ?>">Editar participación</button>
                        <div class="editable-actions" data-edit-actions="participacion-<?= (int) $persona['involucrado_id'] ?>" hidden>
                          <button type="button" class="btn-shell js-edit-cancel" data-shell="participacion-<?= (int) $persona['involucrado_id'] ?>">Cancelar</button>
                          <button type="submit" class="btn-shell btn-primary" form="participacion-inline-form-<?= (int) $persona['involucrado_id'] ?>">Guardar</button>
                        </div>
                      </div>
                    </div>

                    <div class="inline-edit-error" id="participacion-inline-error-<?= (int) $persona['involucrado_id'] ?>"></div>

                    <div class="editable-view" data-edit-view="participacion-<?= (int) $persona['involucrado_id'] ?>">
                      <div class="section-block">
                        <h3>Participación en el accidente</h3>
                        <div class="field-grid"><?= render_field_cards($persona, $involucradoFields) ?></div>
                      </div>
                    </div>

                    <form class="editable-form js-inline-ajax-form" id="participacion-inline-form-<?= (int) $persona['involucrado_id'] ?>" data-shell="participacion-<?= (int) $persona['involucrado_id'] ?>" data-error="participacion-inline-error-<?= (int) $persona['involucrado_id'] ?>" method="post" hidden>
                      <input type="hidden" name="action" value="save_participacion_inline">
                      <input type="hidden" name="involucrado_id" value="<?= (int) $persona['involucrado_id'] ?>">
                      <div class="section-block">
                        <h3>Participación en el accidente</h3>
                        <div class="field-grid"><?= render_editable_fields($persona, $participacionEditFields, 'participacion-' . (int) $persona['involucrado_id']) ?></div>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              <?php if ($hasComboVehiculos): ?>
                <?php foreach ($comboVehiculos as $comboVehiculo): ?>
                  <?php
                    $comboInvVehiculoId = (int) ($comboVehiculo['inv_vehiculo_id'] ?? 0);
                    $comboDocumentoVehiculo = $docVehiculoPorInvolucrado[$comboInvVehiculoId] ?? null;
                    $comboDocumentoVehiculoCount = (int) ($docVehiculoCantidadPorInvolucrado[$comboInvVehiculoId] ?? 0);
                  ?>
                  <div class="tab-pane fade" id="<?= h($personPaneId) ?>-vehiculo-<?= h((string) ($comboVehiculo['veh_numero'] ?? '')) ?>" role="tabpanel">
                    <div class="inner-panel">
                      <div class="editable-shell">
                        <div class="editable-toolbar">
                          <div class="record-actions" style="margin-top:0">
                            <?php if (!empty($comboVehiculo['accidente_id']) && !empty($comboVehiculo['inv_vehiculo_id'])): ?>
                              <a class="btn-shell btn-peritaje" href="oficio_peritaje_express.php?accidente_id=<?= (int) $comboVehiculo['accidente_id'] ?>&invol_id=<?= (int) $comboVehiculo['inv_vehiculo_id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Generar oficio peritaje</a>
                            <?php endif; ?>
                          </div>
                        </div>

                        <div class="editable-view">
                          <?= $renderVehiculoSubtabs(
                              $comboVehiculo,
                              $comboDocumentoVehiculo,
                              $comboDocumentoVehiculoCount,
                              $personPaneId . '-vehiculo-' . (string) ($comboVehiculo['veh_numero'] ?? ''),
                              'workbench-' . (int) $persona['involucrado_id'],
                              'workbench-frame-' . (int) $persona['involucrado_id'],
                              $returnToTabs,
                              $vehiculoFields,
                              $docVehiculoSections,
                              $persona
                          ) ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php elseif ($isDriver && !empty($persona['veh_id'])): ?>
                <div class="tab-pane fade" id="<?= h($personPaneId) ?>-vehiculo" role="tabpanel">
                  <div class="inner-panel">
                    <div class="editable-shell" data-edit-shell="vehiculo-<?= (int) $persona['involucrado_id'] ?>">
                        <div class="editable-toolbar">
                          <div class="record-actions" style="margin-top:0">
                            <?php if (!empty($persona['accidente_id']) && !empty($persona['inv_vehiculo_id'])): ?>
                              <a class="btn-shell btn-peritaje" href="oficio_peritaje_express.php?accidente_id=<?= (int) $persona['accidente_id'] ?>&invol_id=<?= (int) $persona['inv_vehiculo_id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Generar oficio peritaje</a>
                            <?php endif; ?>
                          </div>
                        <?php if (!$hasComboVehiculos): ?>
                          <div class="editable-actions">
                            <button type="button" class="btn-shell js-edit-start" data-shell="vehiculo-<?= (int) $persona['involucrado_id'] ?>">Editar vehículo</button>
                            <div class="editable-actions" data-edit-actions="vehiculo-<?= (int) $persona['involucrado_id'] ?>" hidden>
                              <button type="button" class="btn-shell js-edit-cancel" data-shell="vehiculo-<?= (int) $persona['involucrado_id'] ?>">Cancelar</button>
                              <button type="submit" class="btn-shell btn-primary" form="vehiculo-inline-form-<?= (int) $persona['involucrado_id'] ?>">Guardar</button>
                            </div>
                          </div>
                        <?php endif; ?>
                      </div>

                      <div class="inline-edit-error" id="vehiculo-inline-error-<?= (int) $persona['involucrado_id'] ?>"></div>

                      <div class="editable-view" data-edit-view="vehiculo-<?= (int) $persona['involucrado_id'] ?>">
                        <?= $renderVehiculoSubtabs(
                            $persona,
                            $singleDocVehiculo,
                            $singleDocVehiculoCount,
                            $personPaneId . '-vehiculo',
                            'workbench-' . (int) $persona['involucrado_id'],
                            'workbench-frame-' . (int) $persona['involucrado_id'],
                            $returnToTabs,
                            $vehiculoFields,
                            $docVehiculoSections
                        ) ?>
                      </div>

                      <form class="editable-form js-inline-ajax-form js-veh-inline-form" id="vehiculo-inline-form-<?= (int) $persona['involucrado_id'] ?>" data-shell="vehiculo-<?= (int) $persona['involucrado_id'] ?>" data-error="vehiculo-inline-error-<?= (int) $persona['involucrado_id'] ?>" method="post" hidden>
                        <input type="hidden" name="action" value="save_vehiculo_inline">
                        <input type="hidden" name="vehiculo_id" value="<?= (int) $persona['veh_id'] ?>">

                        <div class="section-block" style="margin-top:0">
                          <h3>Vehículo vinculado al conductor</h3>
                          <div class="field-grid">
                            <?= render_editable_fields($persona, [
                                ['name' => 'placa', 'value_key' => 'veh_placa', 'label' => 'Placa', 'required' => true, 'maxlength' => 12],
                                ['name' => 'serie_vin', 'value_key' => 'veh_serie_vin', 'label' => 'Serie / VIN', 'class' => 'span-2'],
                                ['name' => 'nro_motor', 'value_key' => 'veh_nro_motor', 'label' => 'Nro. motor', 'class' => 'span-2'],
                                ['name' => 'anio', 'value_key' => 'veh_anio', 'label' => 'Año', 'maxlength' => 4, 'inputmode' => 'numeric'],
                                ['name' => 'categoria_id', 'value_key' => 'veh_categoria_id', 'label' => 'Categoría', 'type' => 'select', 'required' => true, 'options' => $vehiculoCategoriasOptions],
                            ], 'vehiculo-' . (int) $persona['involucrado_id']) ?>
                            <div class="field-card edit-field">
                              <label class="field-label" for="tipo_id_<?= (int) $persona['involucrado_id'] ?>">Tipo</label>
                              <select class="edit-control js-veh-tipo" id="tipo_id_<?= (int) $persona['involucrado_id'] ?>" name="tipo_id" data-current="<?= h((string) ($persona['veh_tipo_id'] ?? '')) ?>">
                                <option value="">(Selecciona una categoría primero)</option>
                              </select>
                            </div>
                            <div class="field-card edit-field">
                              <label class="field-label" for="carroceria_id_<?= (int) $persona['involucrado_id'] ?>">Carrocería</label>
                              <select class="edit-control js-veh-carroceria" id="carroceria_id_<?= (int) $persona['involucrado_id'] ?>" name="carroceria_id" data-current="<?= h((string) ($persona['veh_carroceria_id'] ?? '')) ?>">
                                <option value="">(Selecciona un tipo primero)</option>
                              </select>
                            </div>
                            <?= render_editable_fields($persona, [
                                ['name' => 'marca_id', 'value_key' => 'veh_marca_id', 'label' => 'Marca', 'type' => 'select', 'required' => true, 'options' => $vehiculoMarcasOptions],
                            ], 'vehiculo-' . (int) $persona['involucrado_id']) ?>
                            <div class="field-card edit-field">
                              <label class="field-label" for="modelo_id_<?= (int) $persona['involucrado_id'] ?>">Modelo</label>
                              <select class="edit-control js-veh-modelo" id="modelo_id_<?= (int) $persona['involucrado_id'] ?>" name="modelo_id" data-current="<?= h((string) ($persona['veh_modelo_id'] ?? '')) ?>">
                                <option value="">(Selecciona una marca primero)</option>
                              </select>
                            </div>
                            <?= render_editable_fields($persona, [
                                ['name' => 'color', 'value_key' => 'veh_color', 'label' => 'Color'],
                                ['name' => 'largo_mm', 'value_key' => 'veh_largo_mm', 'label' => 'Largo', 'inputmode' => 'decimal', 'step' => '0.01'],
                                ['name' => 'ancho_mm', 'value_key' => 'veh_ancho_mm', 'label' => 'Ancho', 'inputmode' => 'decimal', 'step' => '0.01'],
                                ['name' => 'alto_mm', 'value_key' => 'veh_alto_mm', 'label' => 'Alto', 'inputmode' => 'decimal', 'step' => '0.01'],
                                ['name' => 'notas', 'value_key' => 'veh_notas', 'label' => 'Notas', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
                            ], 'vehiculo-' . (int) $persona['involucrado_id']) ?>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($extras['show_lc']): ?>
                <div class="tab-pane fade" id="<?= h($personPaneId) ?>-lc" role="tabpanel">
                  <div class="inner-panel">
                    <div class="record-actions" style="margin-top:0">
                      <a class="btn-shell js-inline-open" href="doc_lc_nuevo.php?persona_id=<?= (int) $persona['persona_id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="Licencia de conducir">+ Nueva licencia</a>
                    </div>
                    <?php if (!$extras['lc']): ?>
                      <div class="empty-state">No hay licencias registradas para esta persona.</div>
                    <?php else: ?>
                      <div class="record-stack">
                        <?php foreach ($extras['lc'] as $lc): ?>
                          <article class="record-card">
                            <h5>Clase <?= h((string) ($lc['clase'] ?? '—')) ?><?php if (!empty($lc['categoria'])): ?> · Cat <?= h((string) $lc['categoria']) ?><?php endif; ?> · N° <?= h((string) ($lc['numero'] ?? '—')) ?></h5>
                            <p>Vigente: <?= h(fecha_simple($lc['vigente_desde'] ?? null)) ?> a <?= h(fecha_simple($lc['vigente_hasta'] ?? null)) ?></p>
                            <?php if (!empty($lc['expedido_por'])): ?><p>Expedido por: <?= h((string) $lc['expedido_por']) ?></p><?php endif; ?>
                            <?php if (!empty($lc['restricciones'])): ?><p><?= nl2br(h((string) $lc['restricciones'])) ?></p><?php endif; ?>
                            <div class="record-actions">
                              <a class="btn-shell js-inline-open" href="doc_lc_editar.php?id=<?= (int) $lc['id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="Licencia de conducir">Ver / Editar</a>
                            </div>
                          </article>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($extras['show_rml']): ?>
                <div class="tab-pane fade" id="<?= h($personPaneId) ?>-rml" role="tabpanel">
                  <div class="inner-panel">
                    <div class="record-actions" style="margin-top:0">
                      <a class="btn-shell js-inline-open" href="documento_rml_nuevo.php?persona_id=<?= (int) $persona['persona_id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="RML">+ Nuevo RML</a>
                    </div>
                    <?php if (!$extras['rml']): ?>
                      <div class="empty-state">No hay RML registrados para esta persona.</div>
                    <?php else: ?>
                      <div class="record-stack">
                        <?php foreach ($extras['rml'] as $rml): ?>
                          <article class="record-card">
                            <h5>N° <?= h((string) ($rml['numero'] ?? '—')) ?> · <?= h(fecha_simple($rml['fecha'] ?? null)) ?></h5>
                            <div class="record-chipline">
                              <span class="chip-simple">Incapacidad: <?= h((string) (($rml['incapacidad_medico'] ?? '') !== '' ? $rml['incapacidad_medico'] : '—')) ?></span>
                              <span class="chip-simple">Atención: <?= h((string) (($rml['atencion_facultativo'] ?? '') !== '' ? $rml['atencion_facultativo'] : '—')) ?></span>
                            </div>
                            <?php if (!empty($rml['observaciones'])): ?><p><?= nl2br(h((string) $rml['observaciones'])) ?></p><?php endif; ?>
                            <div class="record-actions">
                              <a class="btn-shell js-inline-open" href="documento_rml_editar.php?id=<?= (int) $rml['id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="RML">Ver / Editar</a>
                            </div>
                          </article>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($extras['show_dos']): ?>
                <div class="tab-pane fade" id="<?= h($personPaneId) ?>-dos" role="tabpanel">
                  <div class="inner-panel">
                    <div class="record-actions" style="margin-top:0">
                      <a class="btn-shell js-inline-open" href="documento_dosaje_nuevo.php?persona_id=<?= (int) $persona['persona_id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="Dosaje etílico">+ Nuevo dosaje</a>
                    </div>
                    <?php if (!$extras['dos']): ?>
                      <div class="empty-state">No hay dosajes registrados para esta persona.</div>
                    <?php else: ?>
                      <div class="record-stack">
                        <?php foreach ($extras['dos'] as $dos): ?>
                          <article class="record-card">
                            <h5>N° <?= h((string) ($dos['numero'] ?? '—')) ?> · Reg <?= h((string) ($dos['numero_registro'] ?? '—')) ?></h5>
                            <div class="record-chipline">
                              <span class="chip-simple"><?= h(fecha_hora_simple($dos['fecha_extraccion'] ?? null)) ?></span>
                              <span class="chip-simple"><?= h((string) (($dos['resultado_cualitativo'] ?? '') !== '' ? $dos['resultado_cualitativo'] : 'Sin resultado')) ?></span>
                              <?php if (!empty($dos['resultado_cuantitativo'])): ?><span class="chip-simple"><?= h((string) $dos['resultado_cuantitativo']) ?> g/L</span><?php endif; ?>
                            </div>
                            <?php if (!empty($dos['observaciones'])): ?><p><?= nl2br(h((string) $dos['observaciones'])) ?></p><?php endif; ?>
                            <div class="record-actions">
                              <a class="btn-shell js-inline-open" href="documento_dosaje_editar.php?id=<?= (int) $dos['id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="Dosaje etílico">Ver / Editar</a>
                            </div>
                          </article>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($extras['show_man']): ?>
                <div class="tab-pane fade" id="<?= h($personPaneId) ?>-man" role="tabpanel">
                  <div class="inner-panel">
                    <div class="record-actions" style="margin-top:0">
                      <a class="btn-shell js-inline-open" href="documento_manifestacion_nuevo.php?persona_id=<?= (int) $persona['persona_id'] ?>&rol_id=<?= (int) ($persona['rol_id'] ?? 0) ?>&accidente_id=<?= (int) $accidente_id ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?><?= $manifestDownloadUrl !== '' ? '&download_url=' . urlencode($manifestDownloadUrl) : '' ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="Manifestación">+ Nueva manifestación</a>
                      <?php if ($manifestDownloadUrl !== ''): ?>
                        <a class="btn-shell btn-docx" href="<?= h($manifestDownloadUrl) ?>">DOCX</a>
                      <?php endif; ?>
                    </div>
                    <?php if (!$extras['man']): ?>
                      <div class="empty-state">No hay manifestaciones registradas para esta persona en este accidente.</div>
                    <?php else: ?>
                      <div class="record-stack">
                        <?php foreach ($extras['man'] as $man): ?>
                          <article class="record-card">
                            <h5><?= h((string) (($man['modalidad'] ?? '') !== '' ? $man['modalidad'] : 'Sin modalidad')) ?> · <?= h(fecha_simple($man['fecha'] ?? null)) ?></h5>
                            <div class="record-chipline">
                              <span class="chip-simple"><?= h((string) (($man['horario_inicio'] ?? '') !== '' ? substr((string) $man['horario_inicio'], 0, 5) : '--:--')) ?> - <?= h((string) (($man['hora_termino'] ?? '') !== '' ? substr((string) $man['hora_termino'], 0, 5) : '--:--')) ?></span>
                            </div>
                            <?php if (!empty($man['observaciones'])): ?><p><?= nl2br(h((string) $man['observaciones'])) ?></p><?php endif; ?>
                            <div class="record-actions">
                              <a class="btn-shell js-inline-open" href="documento_manifestacion_editar.php?id=<?= (int) $man['id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="Manifestación">Ver / Editar</a>
                            </div>
                          </article>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($extras['show_occ']): ?>
                <div class="tab-pane fade" id="<?= h($personPaneId) ?>-occ" role="tabpanel">
                  <div class="inner-panel">
                    <?php
                      $occSectionTabsId = $personPaneId . '-occ-sections';
                      $occReturnTo = urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id));
                      $occSectionTabs = [
                          [
                              'id' => 'levantamiento',
                              'label' => 'Levantamiento',
                              'mini' => 'Acta',
                              'fields' => $occLevantamientoFields,
                              'empty' => 'No hay datos de levantamiento registrados.',
                              'always' => true,
                          ],
                          [
                              'id' => 'protocolo',
                              'label' => 'Protocolo de necropsia',
                              'mini' => 'Documento',
                              'fields' => $occProtocoloFields,
                              'empty' => 'No hay datos de protocolo de necropsia registrados.',
                              'always' => false,
                          ],
                          [
                              'id' => 'epicrisis',
                              'label' => 'Epicrisis',
                              'mini' => 'Clínico',
                              'fields' => $occEpicrisisFields,
                              'empty' => 'No hay datos de epicrisis registrados.',
                              'always' => false,
                          ],
                          [
                              'id' => 'pericial',
                              'label' => 'Pericial',
                              'mini' => 'Informe',
                              'fields' => $occPericialFields,
                              'empty' => 'No hay datos periciales registrados.',
                              'always' => false,
                          ],
                      ];
                    ?>
                    <div class="inner-tabs nav nav-tabs flex-nowrap" id="<?= h($occSectionTabsId) ?>-tabs" role="tablist">
                      <?php foreach ($occSectionTabs as $occSectionIndex => $occSection): ?>
                        <button class="nav-link <?= $occSectionIndex === 0 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#<?= h($occSectionTabsId . '-' . $occSection['id']) ?>" type="button" role="tab">
                          <?= h((string) $occSection['label']) ?>
                          <span class="tab-mini"><?= h((string) $occSection['mini']) ?></span>
                        </button>
                      <?php endforeach; ?>
                    </div>
                    <div class="tab-content mt-2">
                      <?php foreach ($occSectionTabs as $occSectionIndex => $occSection): ?>
                        <?php
                          $occSectionId = (string) $occSection['id'];
                          $occSectionFields = $occSection['fields'];
                          $occSectionAlways = (bool) ($occSection['always'] ?? false);
                          $occWorkbenchId = 'occiso-workbench-' . (int) $persona['involucrado_id'] . '-' . $occSectionId;
                          $occFrameId = 'occiso-frame-' . (int) $persona['involucrado_id'] . '-' . $occSectionId;
                        ?>
                        <div class="tab-pane fade <?= $occSectionIndex === 0 ? 'show active' : '' ?>" id="<?= h($occSectionTabsId . '-' . $occSectionId) ?>" role="tabpanel">
                          <div class="record-actions" style="margin-top:0">
                            <a class="btn-shell js-inline-open" href="documento_occiso_nuevo.php?persona_id=<?= (int) $persona['persona_id'] ?>&personaId=<?= (int) $persona['persona_id'] ?>&rol_id=<?= (int) ($persona['rol_id'] ?? 0) ?>&accidente_id=<?= (int) $accidente_id ?>&accidenteId=<?= (int) $accidente_id ?>&section=<?= h(urlencode($occSectionId)) ?>&embed=1&return_to=<?= $occReturnTo ?>" data-workbench="<?= h($occWorkbenchId) ?>" data-frame="<?= h($occFrameId) ?>" data-title="<?= h((string) $occSection['label']) ?>">+ Nuevo documento de occiso</a>
                            <a class="btn-shell btn-necropsia" href="oficio_protocolo_express.php?accidente_id=<?= (int) $accidente_id ?>&invol_id=<?= (int) $persona['involucrado_id'] ?>&return_to=<?= $occReturnTo ?>">Generar oficio necropsia</a>
                          </div>
                          <div class="inline-workbench" id="<?= h($occWorkbenchId) ?>" hidden>
                            <div class="inline-head">
                              <strong><?= h((string) $occSection['label']) ?></strong>
                              <button type="button" class="btn-shell js-inline-close" data-workbench="<?= h($occWorkbenchId) ?>" data-frame="<?= h($occFrameId) ?>">Cerrar</button>
                            </div>
                            <iframe class="inline-frame" id="<?= h($occFrameId) ?>" src="about:blank" loading="lazy"></iframe>
                          </div>
                          <?php if (!$extras['occ']): ?>
                            <div class="empty-state">No hay documentos de occiso para esta persona en este accidente.</div>
                          <?php else: ?>
                            <div class="record-stack">
                              <?php foreach ($extras['occ'] as $occ): ?>
                                <?php $occHasSectionContent = $occSectionAlways || record_has_any_content($occ, $occSectionFields); ?>
                                <article class="record-card">
                                  <h5><?= h((string) (($occ['lugar_levantamiento'] ?? '') !== '' ? $occ['lugar_levantamiento'] : 'Documento de occiso')) ?></h5>
                                  <div class="record-chipline">
                                    <span class="chip-simple"><?= h(fecha_simple($occ['fecha_levantamiento'] ?? null)) ?> <?= h((string) (($occ['hora_levantamiento'] ?? '') !== '' ? substr((string) $occ['hora_levantamiento'], 0, 5) : '')) ?></span>
                                    <span class="chip-simple">Prot. <?= h((string) (($occ['numero_protocolo'] ?? '') !== '' ? $occ['numero_protocolo'] : '—')) ?></span>
                                  </div>
                                  <?php if ($occHasSectionContent): ?>
                                    <div class="section-block">
                                      <h3><?= h((string) $occSection['label']) ?></h3>
                                      <div class="field-grid"><?= render_field_cards($occ, $occSectionFields) ?></div>
                                    </div>
                                  <?php else: ?>
                                    <div class="empty-state"><?= h((string) $occSection['empty']) ?></div>
                                  <?php endif; ?>
                                  <div class="record-actions">
                                    <a class="btn-shell" href="documento_occiso_ver.php?id=<?= (int) $occ['id'] ?>&return_to=<?= $occReturnTo ?>">Ver</a>
                                    <a class="btn-shell js-inline-open" href="documento_occiso_editar.php?id=<?= (int) $occ['id'] ?>&section=<?= h(urlencode($occSectionId)) ?>&embed=1&return_to=<?= $occReturnTo ?>" data-workbench="<?= h($occWorkbenchId) ?>" data-frame="<?= h($occFrameId) ?>" data-title="<?= h((string) $occSection['label']) ?>">Editar</a>
                                  </div>
                                </article>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php $participantPaneIndex++; ?>
      <?php endforeach; ?>

      <div class="tab-pane fade <?= $participantPaneIndex === 0 ? 'show active' : '' ?>" id="efectivo-policial" role="tabpanel">
        <div class="tab-panel">
          <div class="module-actions" style="margin-bottom:8px;">
            <a class="btn-shell" href="policial_interviniente_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo efectivo policial</a>
            <a class="btn-shell" href="policial_interviniente_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
          </div>
          <?php if (!$policias): ?>
            <div class="empty-state">No hay efectivos policiales registrados para este accidente.</div>
          <?php else: ?>
            <div class="inner-tabs nav nav-tabs flex-nowrap" id="efectivo-policial-personas-tabs" role="tablist">
              <?php foreach ($policias as $policiaIndex => $row): ?>
                <?php
                  $policiaTabId = 'policia-registro-' . (int) $row['id'];
                  $policiaGrado = trim((string) ($row['grado_policial'] ?? ''));
                ?>
                <button class="nav-link <?= $policiaIndex === 0 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#<?= h($policiaTabId) ?>" type="button" role="tab">
                  <?= h(person_label($row)) ?>
                  <span class="tab-mini"><?= h($policiaGrado !== '' ? $policiaGrado : 'Sin grado') ?></span>
                </button>
              <?php endforeach; ?>
            </div>

            <div class="tab-content mt-2">
              <?php foreach ($policias as $policiaIndex => $row): ?>
                <?php
                  $policiaTabId = 'policia-registro-' . (int) $row['id'];
                  $policiaPaneId = 'policia-detalle-' . (int) $row['id'];
                  $policiaWa = preg_replace('/\D+/', '', (string) ($row['celular'] ?? ''));
                  $policiaWhatsAppMsg = whatsapp_contact_message($modalidades, $A['fecha_accidente'] ?? null, $A['lugar'] ?? null);
                  $policiaManifestUrl = 'marcador_manifestacion_policia.php?policia_id=' . (int) $row['id'] . '&accidente_id=' . (int) $accidente_id . '&download=1';
                  $policiaPersonaId = (int) ($row['persona_id'] ?? 0);
                  $policiaManifestaciones = $manifestacionesPorPersona[$policiaPersonaId] ?? [];
                  $returnToParticipantes = $_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id);
                  $policiaGrado = trim((string) ($row['grado_policial'] ?? ''));
                ?>
                <div class="tab-pane fade <?= $policiaIndex === 0 ? 'show active' : '' ?>" id="<?= h($policiaTabId) ?>" role="tabpanel">
                  <div class="inner-panel">
                    <div class="person-hero">
                      <div class="person-title">
                        <h2>
                          <span class="person-name-copy">
                            <span><?= h(person_label($row)) ?></span>
                            <button type="button" class="copy-name-btn js-copy-name" data-copy-text="<?= h(person_label($row)) ?>" aria-label="Copiar nombre" title="Copiar nombre">Copiar</button>
                            <span class="person-quick-actions">
                              <?php if ($policiaWa !== ''): ?>
                                <a class="quick-pill-btn whatsapp" href="https://wa.me/<?= h($policiaWa) ?>?text=<?= rawurlencode($policiaWhatsAppMsg) ?>" target="_blank" rel="noopener" aria-label="Abrir WhatsApp" title="Abrir WhatsApp">WA</a>
                              <?php endif; ?>
                            </span>
                          </span>
                        </h2>
                        <p><?= h(trim((string) (($policiaGrado !== '' ? $policiaGrado : 'Sin grado') . ' · CIP ' . (($row['cip'] ?? '') !== '' ? $row['cip'] : '—')))) ?></p>
                      </div>
                      <div class="chip-row">
                        <span class="chip-simple">Registro #<?= (int) $row['id'] ?></span>
                        <span class="chip-simple"><?= h((string) (($row['dependencia_policial'] ?? '') !== '' ? $row['dependencia_policial'] : 'Sin dependencia')) ?></span>
                        <span class="chip-simple"><?= h((string) (($row['rol_funcion'] ?? '') !== '' ? $row['rol_funcion'] : 'Sin rol / función')) ?></span>
                      </div>
                    </div>

                    <div class="record-actions" style="margin-top:0">
                      <a class="btn-shell" href="policial_interviniente_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                      <a class="btn-shell btn-citacion" href="citacion_rapida.php?accidente_id=<?= (int) $accidente_id ?>&persona=<?= urlencode('PNP:' . (int) $row['id']) ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Citación rápida</a>
                    </div>

                    <div class="inner-tabs nav nav-tabs flex-nowrap" id="<?= h($policiaPaneId) ?>-tabs" role="tablist">
                      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#<?= h($policiaPaneId) ?>-persona" type="button" role="tab">
                        Datos personales
                        <span class="tab-mini">Persona</span>
                      </button>
                      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($policiaPaneId) ?>-registro" type="button" role="tab">
                        Registro policial
                        <span class="tab-mini">Efectivo</span>
                      </button>
                      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($policiaPaneId) ?>-manifestacion" type="button" role="tab">
                        Manifestación
                        <span class="tab-mini"><?= count($policiaManifestaciones) ?> registro(s)</span>
                      </button>
                    </div>

                    <div class="tab-content mt-2">
                      <div class="tab-pane fade show active" id="<?= h($policiaPaneId) ?>-persona" role="tabpanel">
                        <div class="editable-shell" data-edit-shell="policia-persona-<?= (int) $row['id'] ?>">
                          <div class="editable-toolbar">
                            <div></div>
                            <div class="editable-actions">
                              <button type="button" class="btn-shell js-edit-start" data-shell="policia-persona-<?= (int) $row['id'] ?>">Editar datos personales</button>
                              <div class="editable-actions" data-edit-actions="policia-persona-<?= (int) $row['id'] ?>" hidden>
                                <button type="button" class="btn-shell js-edit-cancel" data-shell="policia-persona-<?= (int) $row['id'] ?>">Cancelar</button>
                                <button type="submit" class="btn-shell btn-primary" form="policia-persona-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                              </div>
                            </div>
                          </div>

                          <div class="inline-edit-error" id="policia-persona-inline-error-<?= (int) $row['id'] ?>"></div>

                          <div class="editable-view" data-edit-view="policia-persona-<?= (int) $row['id'] ?>">
                            <?php foreach ($policiaPersonaTabSections as $sectionTitle => $sectionFields): ?>
                              <div class="section-block">
                                <h3><?= h($sectionTitle) ?></h3>
                                <div class="field-grid"><?= render_field_cards($row, $sectionFields) ?></div>
                              </div>
                            <?php endforeach; ?>
                          </div>

                          <form class="editable-form js-inline-ajax-form js-persona-inline-form" id="policia-persona-inline-form-<?= (int) $row['id'] ?>" data-shell="policia-persona-<?= (int) $row['id'] ?>" data-error="policia-persona-inline-error-<?= (int) $row['id'] ?>" method="post" hidden>
                            <input type="hidden" name="action" value="save_persona_inline">
                            <input type="hidden" name="persona_id" value="<?= (int) ($row['persona_id'] ?? 0) ?>">
                            <input type="hidden" name="foto_path" value="<?= h((string) ($row['foto_path'] ?? '')) ?>">
                            <input type="hidden" name="api_fuente" value="<?= h((string) ($row['api_fuente'] ?? '')) ?>">
                            <input type="hidden" name="api_ref" value="<?= h((string) ($row['api_ref'] ?? '')) ?>">

                            <?php foreach ($policiaPersonaEditSections as $sectionTitle => $sectionFields): ?>
                              <div class="section-block">
                                <h3><?= h($sectionTitle) ?></h3>
                                <div class="field-grid"><?= render_editable_fields($row, $sectionFields, 'policia-persona-' . (int) $row['id']) ?></div>
                              </div>
                            <?php endforeach; ?>
                          </form>
                        </div>
                      </div>

                      <div class="tab-pane fade" id="<?= h($policiaPaneId) ?>-registro" role="tabpanel">
                        <div class="editable-shell" data-edit-shell="policia-registro-<?= (int) $row['id'] ?>">
                          <div class="editable-toolbar">
                            <div></div>
                            <div class="editable-actions">
                              <button type="button" class="btn-shell js-edit-start" data-shell="policia-registro-<?= (int) $row['id'] ?>">Editar registro policial</button>
                              <div class="editable-actions" data-edit-actions="policia-registro-<?= (int) $row['id'] ?>" hidden>
                                <button type="button" class="btn-shell js-edit-cancel" data-shell="policia-registro-<?= (int) $row['id'] ?>">Cancelar</button>
                                <button type="submit" class="btn-shell btn-primary" form="policia-registro-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                              </div>
                            </div>
                          </div>

                          <div class="inline-edit-error" id="policia-registro-inline-error-<?= (int) $row['id'] ?>"></div>

                          <div class="editable-view" data-edit-view="policia-registro-<?= (int) $row['id'] ?>">
                            <div class="section-block">
                              <h3>Registro policial</h3>
                              <div class="field-grid"><?= render_field_cards($row, ['grado_policial', 'cip', ['key' => 'dependencia_policial', 'class' => 'span-2'], 'rol_funcion', ['key' => 'observaciones', 'class' => 'span-2']]) ?></div>
                            </div>
                          </div>

                          <form class="editable-form js-inline-ajax-form" id="policia-registro-inline-form-<?= (int) $row['id'] ?>" data-shell="policia-registro-<?= (int) $row['id'] ?>" data-error="policia-registro-inline-error-<?= (int) $row['id'] ?>" method="post" hidden>
                            <input type="hidden" name="action" value="save_policial_record_inline">
                            <input type="hidden" name="policial_id" value="<?= (int) $row['id'] ?>">
                            <div class="section-block">
                              <h3>Registro policial</h3>
                              <div class="field-grid"><?= render_editable_fields($row, $policialRecordEditFields, 'policia-reg-' . (int) $row['id']) ?></div>
                            </div>
                          </form>
                        </div>
                      </div>

                      <div class="tab-pane fade" id="<?= h($policiaPaneId) ?>-manifestacion" role="tabpanel">
                        <?= render_participant_manifestation_section($policiaManifestaciones, $policiaPersonaId, (int) $accidente_id, 'policia-manifestacion-workbench-' . (int) $row['id'], 'policia-manifestacion-frame-' . (int) $row['id'], $returnToParticipantes, 'No hay manifestaciones registradas para esta persona en este accidente.', true, $policiaManifestUrl) ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php $participantPaneIndex++; ?>

      <div class="tab-pane fade <?= $participantPaneIndex === 0 ? 'show active' : '' ?>" id="propietario-vehiculo" role="tabpanel">
        <div class="tab-panel">
          <div class="module-actions" style="margin-bottom:8px;">
            <a class="btn-shell" href="propietario_vehiculo_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo propietario</a>
            <a class="btn-shell" href="propietario_vehiculo_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
          </div>
          <?php if (!$propietarios): ?>
            <div class="empty-state">No hay propietarios de vehículo registrados para este accidente.</div>
          <?php else: ?>
            <div class="inner-tabs nav nav-tabs flex-nowrap" id="propietario-vehiculo-personas-tabs" role="tablist">
              <?php foreach ($propietarios as $propietarioIndex => $row): ?>
                <?php
                  $ownerRecord = project_prefixed_record($row, 'owner_');
                  $repRecord = project_prefixed_record($row, 'rep_');
                  $propietarioTipo = mb_strtoupper(trim((string) ($row['tipo_propietario'] ?? '')), 'UTF-8');
                  $principal = $propietarioTipo === 'NATURAL'
                    ? trim((string) (($ownerRecord['nombres'] ?? '') . ' ' . ($ownerRecord['apellido_paterno'] ?? '') . ' ' . ($ownerRecord['apellido_materno'] ?? '')))
                    : (string) ($row['razon_social'] ?? 'Sin razón social');
                  $propietarioPlaca = vehiculo_placa_visible((string) ($row['placa'] ?? ''));
                  if ($propietarioPlaca === '') {
                      $propietarioPlaca = 'SIN PLACA';
                  }
                  $propietarioTabId = 'propietario-registro-' . (int) $row['id'];
                ?>
                <button class="nav-link <?= $propietarioIndex === 0 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#<?= h($propietarioTabId) ?>" type="button" role="tab">
                  <?= h($principal !== '' ? $principal : 'Sin propietario') ?>
                  <span class="tab-mini">Placa <?= h($propietarioPlaca) ?></span>
                </button>
              <?php endforeach; ?>
            </div>

            <div class="tab-content mt-2">
              <?php foreach ($propietarios as $propietarioIndex => $row): ?>
                <?php
                  $ownerRecord = project_prefixed_record($row, 'owner_');
                  $repRecord = project_prefixed_record($row, 'rep_');
                  $propietarioTipo = mb_strtoupper(trim((string) ($row['tipo_propietario'] ?? '')), 'UTF-8');
                  $principal = $propietarioTipo === 'NATURAL'
                    ? trim((string) (($ownerRecord['nombres'] ?? '') . ' ' . ($ownerRecord['apellido_paterno'] ?? '') . ' ' . ($ownerRecord['apellido_materno'] ?? '')))
                    : (string) ($row['razon_social'] ?? 'Sin razón social');
                  $principalDoc = $propietarioTipo === 'NATURAL'
                    ? trim((string) (((string) ($ownerRecord['tipo_doc'] ?? '') !== '' ? person_doc_label((string) ($ownerRecord['tipo_doc'] ?? '')) . ' ' : '') . ($ownerRecord['num_doc'] ?? '')))
                    : ((string) ($row['ruc'] ?? '') !== '' ? trim((string) ('RUC ' . ($row['ruc'] ?? ''))) : '');
                  $representante = trim((string) (($repRecord['nombres'] ?? '') . ' ' . ($repRecord['apellido_paterno'] ?? '') . ' ' . ($repRecord['apellido_materno'] ?? '')));
                  $propietarioCelular = (string) ($propietarioTipo === 'JURIDICA'
                    ? ($repRecord['celular'] ?? '')
                    : ($ownerRecord['celular'] ?? ''));
	                  $propietarioWa = preg_replace('/\D+/', '', $propietarioCelular);
	                  $propietarioWhatsAppMsg = whatsapp_contact_message($modalidades, $A['fecha_accidente'] ?? null, $A['lugar'] ?? null);
	                  $propietarioManifestUrl = 'marcador_manifestacion_propietario.php?propietario_id=' . (int) $row['id'] . '&accidente_id=' . (int) $accidente_id . '&download=1';
	                  $propietarioPersonaId = $propietarioTipo === 'JURIDICA'
	                    ? (int) ($row['representante_persona_id'] ?? 0)
	                    : (int) ($row['propietario_persona_id'] ?? 0);
	                  if ($propietarioPersonaId <= 0) {
	                      $propietarioPersonaId = (int) ($row['propietario_persona_id'] ?? 0);
	                  }
	                  $propietarioManifestaciones = $manifestacionesPorPersona[$propietarioPersonaId] ?? [];
	                  $returnToParticipantes = $_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id);
	                  $propietarioPlaca = vehiculo_placa_visible((string) ($row['placa'] ?? ''));
	                  if ($propietarioPlaca === '') {
	                      $propietarioPlaca = 'SIN PLACA';
	                  }
	                  $propietarioRegistroRecord = $row;
	                  $propietarioRegistroRecord['placa'] = $propietarioPlaca;
	                  $propietarioPersonaEsRepresentante = (int) ($row['representante_persona_id'] ?? 0) > 0 && $propietarioPersonaId === (int) ($row['representante_persona_id'] ?? 0);
	                  $propietarioPersonaRecord = $propietarioPersonaEsRepresentante
	                    ? $repRecord
	                    : $ownerRecord;
	                  $propietarioPersonaRol = $propietarioPersonaEsRepresentante
	                    ? 'Representante'
	                    : 'Propietario';
	                  $propietarioRegistroEditFields = $propietarioTipo === 'JURIDICA'
	                    ? $propietarioJuridicaEditFields
	                    : $propietarioNaturalEditFields;
	                  $propietarioTabId = 'propietario-registro-' . (int) $row['id'];
	                  $propietarioPaneId = 'propietario-detalle-' . (int) $row['id'];
	                ?>
                <div class="tab-pane fade <?= $propietarioIndex === 0 ? 'show active' : '' ?>" id="<?= h($propietarioTabId) ?>" role="tabpanel">
                  <div class="inner-panel">
                    <div class="person-hero">
                      <div class="person-title">
                        <h2>
                          <span class="person-name-copy">
                            <span><?= h($principal !== '' ? $principal : 'Sin propietario') ?></span>
                            <?php if ($principal !== ''): ?><button type="button" class="copy-name-btn js-copy-name" data-copy-text="<?= h($principal) ?>" aria-label="Copiar nombre" title="Copiar nombre">Copiar</button><?php endif; ?>
                            <span class="person-quick-actions">
                              <?php if ($propietarioWa !== ''): ?>
                                <a class="quick-pill-btn whatsapp" href="https://wa.me/<?= h($propietarioWa) ?>?text=<?= rawurlencode($propietarioWhatsAppMsg) ?>" target="_blank" rel="noopener" aria-label="Abrir WhatsApp" title="Abrir WhatsApp">WA</a>
                              <?php endif; ?>
                            </span>
                          </span>
                        </h2>
                        <p><?= h(trim((string) (($row['orden_participacion'] ?? '') . ' · Placa ' . $propietarioPlaca))) ?></p>
                      </div>
                      <div class="chip-row">
                        <span class="chip-simple"><?= h($propietarioTipo !== '' ? $propietarioTipo : 'Sin tipo') ?></span>
                        <span class="chip-simple"><?= h($principalDoc !== '' ? $principalDoc : 'Sin documento') ?></span>
                        <?php if ($representante !== ''): ?><span class="chip-simple">Representante: <?= h($representante) ?></span><?php endif; ?>
                      </div>
                    </div>

                    <div class="record-actions" style="margin-top:0">
                      <a class="btn-shell" href="propietario_vehiculo_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                      <a class="btn-shell btn-citacion" href="citacion_rapida.php?accidente_id=<?= (int) $accidente_id ?>&persona=<?= urlencode('PRO:' . (int) $row['id']) ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Citación rápida</a>
                    </div>

                    <div class="inner-tabs nav nav-tabs flex-nowrap" id="<?= h($propietarioPaneId) ?>-tabs" role="tablist">
                      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#<?= h($propietarioPaneId) ?>-persona" type="button" role="tab">
                        Persona
                        <span class="tab-mini"><?= h($propietarioPersonaRol) ?></span>
                      </button>
                      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($propietarioPaneId) ?>-registro" type="button" role="tab">
                        Registro propietario
                        <span class="tab-mini">Vehículo</span>
                      </button>
                      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($propietarioPaneId) ?>-manifestacion" type="button" role="tab">
                        Manifestación
                        <span class="tab-mini"><?= count($propietarioManifestaciones) ?> registro(s)</span>
                      </button>
                    </div>

                    <div class="tab-content mt-2">
                      <div class="tab-pane fade show active" id="<?= h($propietarioPaneId) ?>-persona" role="tabpanel">
                        <?php if ($propietarioPersonaId <= 0): ?>
                          <div class="empty-state">No hay persona vinculada para este propietario.</div>
                        <?php else: ?>
                          <div class="editable-shell" data-edit-shell="propietario-persona-<?= (int) $row['id'] ?>">
                            <div class="editable-toolbar">
                              <div></div>
                              <div class="editable-actions">
                                <button type="button" class="btn-shell js-edit-start" data-shell="propietario-persona-<?= (int) $row['id'] ?>">Editar persona</button>
                                <div class="editable-actions" data-edit-actions="propietario-persona-<?= (int) $row['id'] ?>" hidden>
                                  <button type="button" class="btn-shell js-edit-cancel" data-shell="propietario-persona-<?= (int) $row['id'] ?>">Cancelar</button>
                                  <button type="submit" class="btn-shell btn-primary" form="propietario-persona-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                                </div>
                              </div>
                            </div>

                            <div class="inline-edit-error" id="propietario-persona-inline-error-<?= (int) $row['id'] ?>"></div>

                            <div class="editable-view" data-edit-view="propietario-persona-<?= (int) $row['id'] ?>">
                              <?php foreach ($policiaPersonaTabSections as $sectionTitle => $sectionFields): ?>
                                <div class="section-block">
                                  <h3><?= h($propietarioPersonaRol . ' · ' . $sectionTitle) ?></h3>
                                  <div class="field-grid"><?= render_field_cards($propietarioPersonaRecord, $sectionFields) ?></div>
                                </div>
                              <?php endforeach; ?>
                            </div>

                            <form class="editable-form js-inline-ajax-form js-persona-inline-form" id="propietario-persona-inline-form-<?= (int) $row['id'] ?>" data-shell="propietario-persona-<?= (int) $row['id'] ?>" data-error="propietario-persona-inline-error-<?= (int) $row['id'] ?>" method="post" hidden>
                              <input type="hidden" name="action" value="save_persona_inline">
                              <input type="hidden" name="persona_id" value="<?= (int) $propietarioPersonaId ?>">
                              <input type="hidden" name="foto_path" value="<?= h((string) ($propietarioPersonaRecord['foto_path'] ?? '')) ?>">
                              <input type="hidden" name="api_fuente" value="<?= h((string) ($propietarioPersonaRecord['api_fuente'] ?? '')) ?>">
                              <input type="hidden" name="api_ref" value="<?= h((string) ($propietarioPersonaRecord['api_ref'] ?? '')) ?>">

                              <?php foreach ($policiaPersonaEditSections as $sectionTitle => $sectionFields): ?>
                                <div class="section-block">
                                  <h3><?= h($propietarioPersonaRol . ' · ' . $sectionTitle) ?></h3>
                                  <div class="field-grid"><?= render_editable_fields($propietarioPersonaRecord, $sectionFields, 'propietario-persona-' . (int) $row['id']) ?></div>
                                </div>
                              <?php endforeach; ?>
                            </form>
                          </div>
                        <?php endif; ?>
                      </div>

                      <div class="tab-pane fade" id="<?= h($propietarioPaneId) ?>-registro" role="tabpanel">
                        <div class="editable-shell" data-edit-shell="propietario-registro-<?= (int) $row['id'] ?>">
                          <div class="editable-toolbar">
                            <div></div>
                            <div class="editable-actions">
                              <button type="button" class="btn-shell js-edit-start" data-shell="propietario-registro-<?= (int) $row['id'] ?>">Editar registro propietario</button>
                              <div class="editable-actions" data-edit-actions="propietario-registro-<?= (int) $row['id'] ?>" hidden>
                                <button type="button" class="btn-shell js-edit-cancel" data-shell="propietario-registro-<?= (int) $row['id'] ?>">Cancelar</button>
                                <button type="submit" class="btn-shell btn-primary" form="propietario-registro-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                              </div>
                            </div>
                          </div>

                          <div class="inline-edit-error" id="propietario-registro-inline-error-<?= (int) $row['id'] ?>"></div>

                          <div class="editable-view" data-edit-view="propietario-registro-<?= (int) $row['id'] ?>">
                            <div class="section-block">
                              <h3>Registro propietario</h3>
                              <div class="field-grid"><?= render_field_cards($propietarioRegistroRecord, $propietarioRecordViewFields) ?></div>
                            </div>
                          </div>

                          <form class="editable-form js-inline-ajax-form" id="propietario-registro-inline-form-<?= (int) $row['id'] ?>" data-shell="propietario-registro-<?= (int) $row['id'] ?>" data-error="propietario-registro-inline-error-<?= (int) $row['id'] ?>" method="post" hidden>
                            <input type="hidden" name="action" value="save_propietario_record_inline">
                            <input type="hidden" name="propietario_id" value="<?= (int) $row['id'] ?>">
                            <div class="section-block">
                              <h3>Registro propietario</h3>
                              <div class="field-grid"><?= render_editable_fields($row, $propietarioRegistroEditFields, 'propietario-reg-' . (int) $row['id']) ?></div>
                            </div>
                          </form>
                        </div>
                      </div>

                      <div class="tab-pane fade" id="<?= h($propietarioPaneId) ?>-manifestacion" role="tabpanel">
                        <?php if ($propietarioPersonaId <= 0): ?>
                          <div class="empty-state">No hay persona vinculada para registrar manifestaciones.</div>
                        <?php else: ?>
                          <?= render_participant_manifestation_section($propietarioManifestaciones, $propietarioPersonaId, (int) $accidente_id, 'propietario-manifestacion-workbench-' . (int) $row['id'], 'propietario-manifestacion-frame-' . (int) $row['id'], $returnToParticipantes, 'No hay manifestaciones registradas para esta persona en este accidente.', true, $propietarioManifestUrl) ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php $participantPaneIndex++; ?>

      <div class="tab-pane fade <?= $participantPaneIndex === 0 ? 'show active' : '' ?>" id="familiar-fallecido" role="tabpanel">
        <div class="tab-panel">
          <div class="module-actions" style="margin-bottom:8px;">
            <a class="btn-shell" href="familiar_fallecido_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo familiar</a>
            <a class="btn-shell" href="familiar_fallecido_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
          </div>
          <?php if (!$familiares): ?>
            <div class="empty-state">No hay familiares de fallecidos registrados para este accidente.</div>
          <?php else: ?>
            <div class="inner-tabs nav nav-tabs flex-nowrap" id="familiar-fallecido-personas-tabs" role="tablist">
              <?php foreach ($familiares as $familiarIndex => $row): ?>
                <?php
                  $famRecord = project_prefixed_record($row, 'fam_');
                  $fallRecord = project_prefixed_record($row, 'fall_');
                  $nombreFamiliar = trim((string) (($famRecord['nombres'] ?? '') . ' ' . ($famRecord['apellido_paterno'] ?? '') . ' ' . ($famRecord['apellido_materno'] ?? '')));
                  $nombreFallecido = trim((string) (($fallRecord['nombres'] ?? '') . ' ' . ($fallRecord['apellido_paterno'] ?? '') . ' ' . ($fallRecord['apellido_materno'] ?? '')));
                  $familiarTabId = 'familiar-registro-' . (int) $row['id'];
                ?>
                <button class="nav-link <?= $familiarIndex === 0 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#<?= h($familiarTabId) ?>" type="button" role="tab">
                  <?= h($nombreFamiliar !== '' ? $nombreFamiliar : 'Sin familiar asociado') ?>
                  <span class="tab-mini"><?= h((string) (($row['parentesco'] ?? '') !== '' ? $row['parentesco'] : 'Sin parentesco')) ?></span>
                </button>
              <?php endforeach; ?>
            </div>

            <div class="tab-content mt-2">
              <?php foreach ($familiares as $familiarIndex => $row): ?>
                <?php
                  $famRecord = project_prefixed_record($row, 'fam_');
                  $fallRecord = project_prefixed_record($row, 'fall_');
                  $nombreFamiliar = trim((string) (($famRecord['nombres'] ?? '') . ' ' . ($famRecord['apellido_paterno'] ?? '') . ' ' . ($famRecord['apellido_materno'] ?? '')));
                  $nombreFallecido = trim((string) (($fallRecord['nombres'] ?? '') . ' ' . ($fallRecord['apellido_paterno'] ?? '') . ' ' . ($fallRecord['apellido_materno'] ?? '')));
	                  $familiarWa = preg_replace('/\D+/', '', (string) ($famRecord['celular'] ?? ''));
	                  $familiarWhatsAppMsg = whatsapp_contact_message($modalidades, $A['fecha_accidente'] ?? null, $A['lugar'] ?? null);
	                  $familiarManifestUrl = 'marcador_manifestacion_familiar.php?fam_id=' . (int) $row['id'];
	                  $familiarPersonaId = (int) ($row['familiar_persona_id'] ?? 0);
	                  $familiarManifestaciones = $manifestacionesPorPersona[$familiarPersonaId] ?? [];
	                  $returnToParticipantes = $_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id);
	                  $familiarTabId = 'familiar-registro-' . (int) $row['id'];
	                  $familiarPaneId = 'familiar-detalle-' . (int) $row['id'];
	                ?>
                <div class="tab-pane fade <?= $familiarIndex === 0 ? 'show active' : '' ?>" id="<?= h($familiarTabId) ?>" role="tabpanel">
                  <div class="inner-panel">
                    <div class="person-hero">
                      <div class="person-title">
                        <h2>
                          <span class="person-name-copy">
                            <span><?= h($nombreFamiliar !== '' ? $nombreFamiliar : 'Sin familiar asociado') ?></span>
                            <?php if ($nombreFamiliar !== ''): ?><button type="button" class="copy-name-btn js-copy-name" data-copy-text="<?= h($nombreFamiliar) ?>" aria-label="Copiar nombre" title="Copiar nombre">Copiar</button><?php endif; ?>
                            <span class="person-quick-actions">
                              <?php if ($familiarWa !== ''): ?>
                                <a class="quick-pill-btn whatsapp" href="https://wa.me/<?= h($familiarWa) ?>?text=<?= rawurlencode($familiarWhatsAppMsg) ?>" target="_blank" rel="noopener" aria-label="Abrir WhatsApp" title="Abrir WhatsApp">WA</a>
                              <?php endif; ?>
                            </span>
                          </span>
                        </h2>
                        <p>Familiar de <?= h($nombreFallecido !== '' ? $nombreFallecido : 'Sin fallecido asociado') ?></p>
                      </div>
                      <div class="chip-row">
                        <span class="chip-simple"><?= h((string) (($row['parentesco'] ?? '') !== '' ? $row['parentesco'] : 'Sin parentesco')) ?></span>
                        <span class="chip-simple"><?= h(trim((string) (((string) ($famRecord['tipo_doc'] ?? '') !== '' ? person_doc_label((string) ($famRecord['tipo_doc'] ?? '')) . ' ' : '') . ($famRecord['num_doc'] ?? '')))) ?></span>
                        <span class="chip-simple">Fallecido: <?= h(trim((string) (((string) ($fallRecord['tipo_doc'] ?? '') !== '' ? person_doc_label((string) ($fallRecord['tipo_doc'] ?? '')) . ' ' : '') . ($fallRecord['num_doc'] ?? '')))) ?></span>
                      </div>
                    </div>

                    <div class="record-actions" style="margin-top:0">
                      <a class="btn-shell" href="familiar_fallecido_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                      <a class="btn-shell btn-citacion" href="citacion_rapida.php?accidente_id=<?= (int) $accidente_id ?>&persona=<?= urlencode('FAM:' . (int) $row['id']) ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Citación rápida</a>
                    </div>

                    <div class="inner-tabs nav nav-tabs flex-nowrap" id="<?= h($familiarPaneId) ?>-tabs" role="tablist">
                      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#<?= h($familiarPaneId) ?>-persona" type="button" role="tab">
                        Persona
                        <span class="tab-mini">Familiar</span>
                      </button>
                      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= h($familiarPaneId) ?>-manifestacion" type="button" role="tab">
                        Manifestación
                        <span class="tab-mini"><?= count($familiarManifestaciones) ?> registro(s)</span>
                      </button>
                    </div>

                    <div class="tab-content mt-2">
                      <div class="tab-pane fade show active" id="<?= h($familiarPaneId) ?>-persona" role="tabpanel">
                        <?php if ($familiarPersonaId <= 0): ?>
                          <div class="empty-state">No hay persona vinculada para este familiar.</div>
                        <?php else: ?>
                          <div class="editable-shell" data-edit-shell="familiar-persona-<?= (int) $row['id'] ?>">
                            <div class="editable-toolbar">
                              <div></div>
                              <div class="editable-actions">
                                <button type="button" class="btn-shell js-edit-start" data-shell="familiar-persona-<?= (int) $row['id'] ?>">Editar persona</button>
                                <div class="editable-actions" data-edit-actions="familiar-persona-<?= (int) $row['id'] ?>" hidden>
                                  <button type="button" class="btn-shell js-edit-cancel" data-shell="familiar-persona-<?= (int) $row['id'] ?>">Cancelar</button>
                                  <button type="submit" class="btn-shell btn-primary" form="familiar-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                                </div>
                              </div>
                            </div>

                            <div class="inline-edit-error" id="familiar-inline-error-<?= (int) $row['id'] ?>"></div>

                            <div class="editable-view" data-edit-view="familiar-persona-<?= (int) $row['id'] ?>">
                              <?php foreach ($policiaPersonaTabSections as $sectionTitle => $sectionFields): ?>
                                <div class="section-block">
                                  <h3><?= h('Familiar · ' . $sectionTitle) ?></h3>
                                  <div class="field-grid"><?= render_field_cards($famRecord, $sectionFields) ?></div>
                                </div>
                              <?php endforeach; ?>
                            </div>

                            <form class="editable-form js-inline-ajax-form js-persona-inline-form" id="familiar-inline-form-<?= (int) $row['id'] ?>" data-shell="familiar-persona-<?= (int) $row['id'] ?>" data-error="familiar-inline-error-<?= (int) $row['id'] ?>" method="post" hidden>
                              <input type="hidden" name="action" value="save_persona_inline">
                              <input type="hidden" name="persona_id" value="<?= (int) ($row['familiar_persona_id'] ?? 0) ?>">
                              <input type="hidden" name="foto_path" value="<?= h((string) ($famRecord['foto_path'] ?? '')) ?>">
                              <input type="hidden" name="api_fuente" value="<?= h((string) ($famRecord['api_fuente'] ?? '')) ?>">
                              <input type="hidden" name="api_ref" value="<?= h((string) ($famRecord['api_ref'] ?? '')) ?>">

                              <?php foreach ($policiaPersonaEditSections as $sectionTitle => $sectionFields): ?>
                                <div class="section-block">
                                  <h3><?= h('Familiar · ' . $sectionTitle) ?></h3>
                                  <div class="field-grid"><?= render_editable_fields($famRecord, $sectionFields, 'familiar-' . (int) $row['id']) ?></div>
                                </div>
                              <?php endforeach; ?>
                            </form>
                          </div>
                        <?php endif; ?>
                      </div>

                      <div class="tab-pane fade" id="<?= h($familiarPaneId) ?>-manifestacion" role="tabpanel">
                        <?php if ($familiarPersonaId <= 0): ?>
                          <div class="empty-state">No hay persona vinculada para registrar manifestaciones.</div>
                        <?php else: ?>
                          <?= render_participant_manifestation_section($familiarManifestaciones, $familiarPersonaId, (int) $accidente_id, 'familiar-manifestacion-workbench-' . (int) $row['id'], 'familiar-manifestacion-frame-' . (int) $row['id'], $returnToParticipantes, 'No hay manifestaciones registradas para esta persona en este accidente.', true, $familiarManifestUrl) ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php $participantPaneIndex++; ?>

      <div class="tab-pane fade <?= $participantPaneIndex === 0 ? 'show active' : '' ?>" id="abogados" role="tabpanel">
        <div class="tab-panel">
          <div class="module-actions" style="margin-bottom:8px;">
            <a class="btn-shell" href="abogado_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo abogado</a>
            <a class="btn-shell" href="abogado_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
          </div>
          <?php if (!$abogados): ?>
            <div class="empty-state">No hay abogados registrados para este accidente.</div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($abogados as $row): ?>
                <?php
                  $nombreAbogado = trim((string) (($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ', ' . ($row['nombres'] ?? '')));
                  $abogadoCelular = (string) ($row['celular'] ?? '');
                  $abogadoWa = preg_replace('/\D+/', '', $abogadoCelular);
                  $abogadoWhatsAppMsg = whatsapp_contact_message($modalidades, $A['fecha_accidente'] ?? null, $A['lugar'] ?? null);
                  $contactoAbogado = trim((string) ($row['celular'] ?? ''));
                  if (($row['email'] ?? '') !== '') {
                      $contactoAbogado .= $contactoAbogado !== '' ? ' · ' . $row['email'] : $row['email'];
                  }
                ?>
                <article class="module-card" data-collapsible-card>
                  <header>
                    <div>
                      <h4>
                        <span class="module-title-copy">
                          <span><?= h($nombreAbogado !== '' ? $nombreAbogado : 'Sin nombre') ?></span>
                          <?php if ($nombreAbogado !== ''): ?><button type="button" class="copy-name-btn js-copy-name" data-copy-text="<?= h($nombreAbogado) ?>" aria-label="Copiar nombre" title="Copiar nombre">Copiar</button><?php endif; ?>
                          <?php if ($abogadoWa !== ''): ?>
                            <span class="person-quick-actions">
                              <a class="quick-pill-btn whatsapp" href="https://wa.me/<?= h($abogadoWa) ?>?text=<?= rawurlencode($abogadoWhatsAppMsg) ?>" target="_blank" rel="noopener" aria-label="Abrir WhatsApp" title="Abrir WhatsApp">WA</a>
                            </span>
                          <?php endif; ?>
                        </span>
                      </h4>
                      <p><?= h((string) (($row['persona_rep_nom'] ?? '') !== '' ? $row['persona_rep_nom'] : 'Sin persona asociada')) ?></p>
                    </div>
                    <div class="module-card-controls">
                      <span class="chip-simple">Registro #<?= (int) $row['id'] ?></span>
                      <button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button>
                    </div>
                  </header>
                  <div class="module-meta">
                    <span class="chip-simple">Colegiatura: <?= h((string) (($row['colegiatura'] ?? '') !== '' ? $row['colegiatura'] : 'Sin colegiatura')) ?></span>
                    <span class="chip-simple">Registro: <?= h((string) (($row['registro'] ?? '') !== '' ? $row['registro'] : 'Sin registro')) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['condicion_representado'] ?? '') !== '' ? $row['condicion_representado'] : 'Sin condición')) ?></span>
                    <span class="chip-simple"><?= h($contactoAbogado !== '' ? $contactoAbogado : 'Sin contacto') ?></span>
                  </div>
                  <div class="editable-shell" data-edit-shell="abogado-<?= (int) $row['id'] ?>">
                    <div class="editable-toolbar">
                      <div class="record-actions" style="margin-top:0">
                        <a class="btn-shell btn-citacion" href="marcador_abogado.php?abogado_id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Notificaci&oacute;n</a>
                        <a class="btn-shell" href="abogado_ver.php?id=<?= (int) $row['id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                        <a class="btn-shell" href="abogado_eliminar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Eliminar</a>
                      </div>
                      <div class="editable-actions">
                        <button type="button" class="btn-shell js-edit-start" data-shell="abogado-<?= (int) $row['id'] ?>">Editar</button>
                        <div class="editable-actions" data-edit-actions="abogado-<?= (int) $row['id'] ?>" hidden>
                          <button type="button" class="btn-shell js-edit-cancel" data-shell="abogado-<?= (int) $row['id'] ?>">Cancelar</button>
                          <button type="submit" class="btn-shell btn-primary" form="abogado-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                        </div>
                      </div>
                    </div>

                    <div class="module-card-panel js-card-panel" hidden>
                    <?php if (!empty($row['casilla_electronica'])): ?><p style="margin-top:10px;">Casilla electrónica: <?= h((string) $row['casilla_electronica']) ?></p><?php endif; ?>
                    <?php if (!empty($row['domicilio_procesal'])): ?><p style="margin-top:10px;">Domicilio procesal: <?= nl2br(h((string) $row['domicilio_procesal'])) ?></p><?php endif; ?>
                    <div class="inline-edit-error" id="abogado-inline-error-<?= (int) $row['id'] ?>"></div>

                    <div class="editable-view" data-edit-view="abogado-<?= (int) $row['id'] ?>">
                      <?php foreach ($abogadoSections as $sectionTitle => $sectionFields): ?>
                        <div class="section-block">
                          <h3><?= h($sectionTitle) ?></h3>
                          <div class="field-grid"><?= render_field_cards($row, $sectionFields) ?></div>
                        </div>
                      <?php endforeach; ?>
                    </div>

                    <form class="editable-form js-inline-ajax-form" id="abogado-inline-form-<?= (int) $row['id'] ?>" data-shell="abogado-<?= (int) $row['id'] ?>" data-error="abogado-inline-error-<?= (int) $row['id'] ?>" method="post" hidden>
                      <input type="hidden" name="action" value="save_abogado_inline">
                      <input type="hidden" name="abogado_id" value="<?= (int) $row['id'] ?>">
                      <?php foreach ($abogadoEditSections as $sectionTitle => $sectionFields): ?>
                        <div class="section-block">
                          <h3><?= h($sectionTitle) ?></h3>
                          <div class="field-grid"><?= render_editable_fields($row, $sectionFields, 'abogado-' . (int) $row['id']) ?></div>
                        </div>
                      <?php endforeach; ?>
                    </form>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php $participantPaneIndex++; ?>

          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="documentos" role="tabpanel">
        <div class="tab-panel">
          <div class="inline-workbench" id="documentos-workbench" hidden>
            <div class="inline-head">
              <strong id="documentos-workbench-title">Formulario</strong>
              <button type="button" class="btn-shell js-inline-close" data-workbench="documentos-workbench" data-frame="documentos-workbench-frame">Cerrar</button>
            </div>
            <iframe class="inline-frame" id="documentos-workbench-frame" src="about:blank" loading="lazy"></iframe>
          </div>

          <div class="inner-tabs nav nav-tabs flex-nowrap" id="documentos-tabs" role="tablist">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#documentos-oficios" type="button" role="tab">
              Oficios
              <span class="tab-mini"><?= count($oficios) ?> registro(s)</span>
            </button>
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#documentos-recibidos" type="button" role="tab">
              Documentos recibidos
              <span class="tab-mini"><?= count($documentosRecibidos) ?> registro(s)</span>
            </button>
          </div>

          <div class="tab-content mt-2">
            <div class="tab-pane fade show active" id="documentos-oficios" role="tabpanel">
              <div class="inner-panel">
                <div class="module-actions" style="margin-bottom:8px;">
                  <a class="btn-shell js-inline-open" href="oficios_nuevo.php?accidente_id=<?= (int) $accidente_id ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="documentos-workbench" data-frame="documentos-workbench-frame" data-title="Nuevo oficio">+ Nuevo oficio</a>
                  <a class="btn-shell btn-peritaje" href="oficio_peritaje_express.php?accidente_id=<?= (int) $accidente_id ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Peritaje rápido</a>
                  <a class="btn-shell btn-necropsia" href="oficio_protocolo_express.php?accidente_id=<?= (int) $accidente_id ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Necropsia rapida</a>
                  <a class="btn-shell" href="oficios_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
                </div>

                <?php if (!$oficios): ?>
                  <div class="empty-state">No hay oficios registrados para este accidente.</div>
                <?php else: ?>
                  <div class="module-grid">
                    <?php foreach ($oficios as $row): ?>
                      <?php
                        $oficioIcon = oficio_icon($row);
                        $oficioEstadoClass = oficio_status_class((string) ($row['estado'] ?? ''));
                        $oficioPeritajeText = mb_strtolower((string) (($row['asunto_nombre'] ?? '') . ' ' . ($row['detalle'] ?? '')), 'UTF-8');
                        $oficioEsPeritaje = str_contains($oficioPeritajeText, 'peritaje de constat');
                        $oficioEsNecropsia = str_contains($oficioPeritajeText, 'protocolo de necropsia') || str_contains($oficioPeritajeText, 'necropsia') || str_contains($oficioPeritajeText, 'autopsia');
                      ?>
                      <article class="module-card">
                        <header>
                          <div>
                            <h4><?= h($oficioIcon) ?> Oficio <?= h((string) ($row['numero'] ?? '')) ?>/<?= h((string) ($row['anio'] ?? '')) ?></h4>
                            <p><?= h((string) (($row['entidad'] ?? '') !== '' ? $row['entidad'] : 'Sin entidad')) ?></p>
                          </div>
                          <select class="module-status-select <?= h($oficioEstadoClass) ?> js-quick-oficio-status" data-oficio-id="<?= (int) $row['id'] ?>" data-prev="<?= h((string) ($row['estado'] ?? 'BORRADOR')) ?>">
                            <?php foreach ($oficioEstados as $estadoOpt): ?>
                              <option value="<?= h((string) $estadoOpt) ?>" <?= (string) ($row['estado'] ?? 'BORRADOR') === (string) $estadoOpt ? 'selected' : '' ?>><?= h((string) $estadoOpt) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </header>
                        <div class="module-meta">
                          <span class="chip-simple"><?= h((string) (($row['asunto_nombre'] ?? '') !== '' ? $row['asunto_nombre'] : 'Sin asunto')) ?></span>
                          <span class="chip-simple">Fecha: <?= h(fecha_simple($row['fecha_emision'] ?? null)) ?></span>
                          <?php if (!empty($row['veh_placa'])): ?><span class="chip-simple"><?= h(trim((string) (($row['veh_ut'] ?? '') . ' · ' . ($row['veh_placa'] ?? '')))) ?></span><?php endif; ?>
                          <?php if (!empty(trim((string) ($row['persona_nombre'] ?? '')))): ?><span class="chip-simple"><?= h(trim((string) $row['persona_nombre'])) ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($row['referencia_texto'])): ?><p style="margin-top:10px;">Referencia: <?= nl2br(h((string) $row['referencia_texto'])) ?></p><?php endif; ?>
                        <?php if (!empty($row['motivo'])): ?><p style="margin-top:10px;"><?= nl2br(h((string) $row['motivo'])) ?></p><?php endif; ?>
                        <div class="module-actions">
                          <?php if ($oficioEsPeritaje): ?><a class="btn-shell btn-peritaje" href="oficio_peritaje.php?oficio_id=<?= (int) $row['id'] ?>">Descargar peritaje</a><?php endif; ?>
                          <?php if ($oficioEsNecropsia): ?><a class="btn-shell btn-necropsia" href="oficio_protocolo.php?oficio_id=<?= (int) $row['id'] ?><?= !empty($row['inv_per_id']) ? '&inv_id=' . urlencode((string) $row['inv_per_id']) : '' ?>">Descargar necropsia</a><?php endif; ?>
                          <a class="btn-shell" href="oficios_leer.php?id=<?= (int) $row['id'] ?>">Ver</a>
                          <a class="btn-shell js-inline-open" href="oficios_editar.php?id=<?= (int) $row['id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="documentos-workbench" data-frame="documentos-workbench-frame" data-title="Editar oficio">Editar</a>
                          <a class="btn-shell js-inline-open" href="oficios_eliminar.php?id=<?= (int) $row['id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="documentos-workbench" data-frame="documentos-workbench-frame" data-title="Eliminar oficio">Eliminar</a>
                        </div>
                      </article>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="tab-pane fade" id="documentos-recibidos" role="tabpanel">
              <div class="inner-panel">
                <div class="module-actions" style="margin-bottom:8px;">
                  <a class="btn-shell js-inline-open" href="documento_recibido_nuevo.php?accidente_id=<?= (int) $accidente_id ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="documentos-workbench" data-frame="documentos-workbench-frame" data-title="Nuevo documento recibido">+ Nuevo documento recibido</a>
                  <a class="btn-shell" href="documento_recibido_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
                </div>

                <?php if (!$documentosRecibidos): ?>
                  <div class="empty-state">No hay documentos recibidos registrados para este accidente.</div>
                <?php else: ?>
                  <div class="module-grid">
                    <?php foreach ($documentosRecibidos as $row): ?>
                      <?php
                        $documentoIcon = documento_recibido_icon($row);
                        $docEstado = trim((string) ($row['estado'] ?? ''));
                        $docEstadoClass = mb_strtolower($docEstado, 'UTF-8') === 'revisado'
                          ? 'chip-status-ok'
                          : (mb_strtolower($docEstado, 'UTF-8') === 'archivado' ? 'chip-testigo' : 'chip-status-warn');
                      ?>
                      <article class="module-card">
                        <header>
                          <div>
                            <h4><?= h($documentoIcon) ?> <?= h((string) (($row['asunto'] ?? '') !== '' ? $row['asunto'] : 'Documento recibido #' . (int) $row['id'])) ?></h4>
                            <p><?= h((string) (($row['entidad_persona'] ?? '') !== '' ? $row['entidad_persona'] : 'Sin entidad / persona')) ?></p>
                          </div>
                          <span class="chip-simple <?= h($docEstadoClass) ?>"><?= h($docEstado !== '' ? $docEstado : 'Sin estado') ?></span>
                        </header>
                        <div class="module-meta">
                          <span class="chip-simple"><?= h((string) (($row['tipo_documento'] ?? '') !== '' ? $row['tipo_documento'] : 'Sin tipo')) ?></span>
                          <span class="chip-simple">N° <?= h((string) (($row['numero_documento'] ?? '') !== '' ? $row['numero_documento'] : '—')) ?></span>
                          <span class="chip-simple">Recepcion: <?= h(fecha_simple($row['fecha_recepcion_resuelta'] ?? $row['fecha_recepcion'] ?? $row['fecha'] ?? null)) ?></span>
                          <span class="chip-simple">Documento: <?= h(fecha_simple($row['fecha_documento_resuelta'] ?? $row['fecha_documento'] ?? $row['fecha'] ?? null)) ?></span>
                          <?php if (!empty($row['oficio_numero']) || !empty($row['oficio_anio'])): ?><span class="chip-simple">Oficio <?= h((string) ($row['oficio_numero'] ?? '')) ?>/<?= h((string) ($row['oficio_anio'] ?? '')) ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($row['contenido'])): ?><p style="margin-top:10px;"><?= nl2br(h((string) $row['contenido'])) ?></p><?php endif; ?>
                        <div class="module-actions">
                          <a class="btn-shell" href="documento_recibido_ver.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                          <a class="btn-shell js-inline-open" href="documento_recibido_editar.php?id=<?= (int) $row['id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="documentos-workbench" data-frame="documentos-workbench-frame" data-title="Editar documento recibido">Editar</a>
                          <a class="btn-shell js-inline-open" href="documento_recibido_eliminar.php?id=<?= (int) $row['id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="documentos-workbench" data-frame="documentos-workbench-frame" data-title="Eliminar documento recibido">Eliminar</a>
                        </div>
                      </article>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="tab-pane fade" id="diligencias-pendientes" role="tabpanel">
        <div class="tab-panel">
          <div class="module-actions" style="margin-bottom:8px;">
            <a class="btn-shell" href="diligenciapendiente_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nueva diligencia</a>
            <a class="btn-shell" href="diligenciapendiente_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
          </div>
          <div class="inner-tabs nav nav-tabs flex-nowrap" id="diligencias-tabs" role="tablist">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#diligencias-todos" type="button" role="tab">
              Todos
              <span class="tab-mini"><?= count($diligencias) ?> registro(s)</span>
            </button>
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#diligencias-pendientes-lista" type="button" role="tab">
              Pendientes
              <span class="tab-mini"><?= count($diligenciasPendientesSolo) ?> registro(s)</span>
            </button>
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#diligencias-realizados" type="button" role="tab">
              Realizados
              <span class="tab-mini"><?= count($diligenciasRealizadas) ?> registro(s)</span>
            </button>
          </div>

          <div class="tab-content mt-2">
            <div class="tab-pane fade show active" id="diligencias-todos" role="tabpanel">
              <?= $renderDiligenciaCards($diligencias) ?>
            </div>
            <div class="tab-pane fade" id="diligencias-pendientes-lista" role="tabpanel">
              <?= $renderDiligenciaCards($diligenciasPendientesSolo) ?>
            </div>
            <div class="tab-pane fade" id="diligencias-realizados" role="tabpanel">
              <?= $renderDiligenciaCards($diligenciasRealizadas) ?>
            </div>
          </div>
        </div>
      </div>
      <div class="tab-pane fade" id="analisis" role="tabpanel">
        <div class="tab-panel">
          <div class="inner-tabs nav nav-tabs flex-nowrap" id="analisis-tabs" role="tablist">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#analisis-danos-lesiones" type="button" role="tab">
              analisis de daños y lesiones
              <span class="tab-mini"><?= $analysisTabCount ?> registro(s)</span>
            </button>
          </div>

          <div class="tab-content mt-2">
            <div class="tab-pane fade show active" id="analisis-danos-lesiones" role="tabpanel">
              <div class="analysis-two-cols">
                <article class="module-card">
                  <header>
                    <div>
                      <h4>Conductores</h4>
                      <p>Nombre del conductor, vehículo involucrado y daños que presenta.</p>
                    </div>
                  </header>
                  <?php if (!$analysisDriverRows): ?>
                    <div class="summary-empty">No hay conductores registrados para este accidente.</div>
                  <?php else: ?>
                    <div class="summary-doc-stack">
                      <?php foreach ($analysisDriverRows as $driver): ?>
                        <article class="record-card">
                          <h5><?= h((string) ($driver['nombre'] ?? 'Sin nombre')) ?></h5>
                          <div class="section-block">
                            <div class="field-grid">
                              <div class="field-card span-2">
                                <div class="field-label">Vehículo involucrado</div>
                                <div class="field-value"><?= h((string) ($driver['vehiculo'] ?? 'Sin vehículo registrado')) ?></div>
                              </div>
                              <div class="field-card span-2">
                                <div class="field-label">Peritaje</div>
                                <div class="field-value"><?= h((string) (($driver['peritaje']['numero_peritaje'] ?? '') !== '' ? ('N° ' . $driver['peritaje']['numero_peritaje']) : 'Sin número')) ?></div>
                              </div>
                            </div>
                          </div>
                          <div class="section-block">
                            <?= render_analysis_peritaje_story((array) ($driver['peritaje'] ?? [])) ?>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <div class="analysis-upload">
                    <form class="analysis-upload-form js-analysis-upload-form" data-preview-target="analysis-danos-preview">
                      <input type="hidden" name="action" value="save_analysis_images">
                      <input type="hidden" name="accidente_id" value="<?= (int) $accidente_id ?>">
                      <input type="hidden" name="section" value="danos">
                      <div>
                        <label for="analysis-danos-photo">Suba aqui la foto de los daños</label>
                        <input class="analysis-upload-input js-analysis-image-input" id="analysis-danos-photo" type="file" name="images[]" accept="image/*,.heic,.heif" multiple data-max-files="<?= max(0, 5 - count($analysisMediaBySection['danos'])) ?>" data-preview-target="analysis-danos-preview" <?= count($analysisMediaBySection['danos']) >= 5 ? 'disabled' : '' ?>>
                        <p class="analysis-upload-hint">Máximo 5 imágenes. Guardadas: <?= count($analysisMediaBySection['danos']) ?>/5. Admite JPG, PNG, WEBP, GIF y HEIC.</p>
                      </div>
                      <div class="analysis-upload-status" data-upload-message></div>
                      <div class="action-row">
                        <button type="submit" class="btn-shell" <?= count($analysisMediaBySection['danos']) >= 5 ? 'disabled' : '' ?>>Guardar imágenes</button>
                      </div>
                    </form>
                    <div class="analysis-preview" id="analysis-danos-preview" hidden></div>
                    <?php if ($analysisMediaBySection['danos'] !== []): ?>
                      <div class="analysis-inline-slider js-analysis-inline-slider">
                        <div class="analysis-inline-stage">
                          <button type="button" class="analysis-inline-nav analysis-inline-prev js-analysis-inline-prev" aria-label="Imagen anterior">‹</button>
                          <img class="analysis-inline-image js-analysis-inline-image" src="" alt="Imagen de daños">
                          <button type="button" class="analysis-inline-nav analysis-inline-next js-analysis-inline-next" aria-label="Imagen siguiente">›</button>
                        </div>
                        <div class="analysis-inline-meta">
                          <span class="analysis-image-order js-analysis-inline-order"></span>
                          <span class="analysis-image-name js-analysis-inline-caption"></span>
                        </div>
                        <div hidden>
                          <?php foreach ($analysisMediaBySection['danos'] as $mediaIndex => $media): ?>
                            <button
                              type="button"
                              class="js-analysis-inline-item"
                              data-index="<?= (int) $mediaIndex ?>"
                              data-src="<?= h((string) ($media['archivo_path'] ?? '')) ?>"
                              data-caption="<?= h((string) (($media['archivo_nombre'] ?? '') !== '' ? $media['archivo_nombre'] : 'Imagen guardada')) ?>"
                              data-order="<?= (int) ($media['sort_order'] ?? ($mediaIndex + 1)) ?>"
                            ></button>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                </article>

                <article class="module-card">
                  <header>
                    <div>
                      <h4>Fallecidos</h4>
                      <p>Nombres y lesiones registradas del participante fallecido.</p>
                    </div>
                  </header>
                  <?php if (!$analysisFallecidoRows): ?>
                    <div class="summary-empty">No hay personas fallecidas registradas para este accidente.</div>
                  <?php else: ?>
                    <div class="summary-doc-stack">
                      <?php foreach ($analysisFallecidoRows as $fallecido): ?>
                        <article class="record-card">
                          <h5><?= h((string) ($fallecido['nombre'] ?? 'Sin nombre')) ?></h5>
                          <div class="section-block">
                            <div class="field-grid">
                              <div class="field-card span-4">
                                <div class="field-label">Lesiones</div>
                                <div class="field-value"><?= nl2br(h((string) (($fallecido['lesiones'] ?? '') !== '' ? $fallecido['lesiones'] : 'Sin lesiones registradas'))) ?></div>
                              </div>
                            </div>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <div class="analysis-upload">
                    <form class="analysis-upload-form js-analysis-upload-form" data-preview-target="analysis-lesiones-preview">
                      <input type="hidden" name="action" value="save_analysis_images">
                      <input type="hidden" name="accidente_id" value="<?= (int) $accidente_id ?>">
                      <input type="hidden" name="section" value="lesiones">
                      <div>
                        <label for="analysis-lesiones-photo">Suba aqui la foto de las lesiones</label>
                        <input class="analysis-upload-input js-analysis-image-input" id="analysis-lesiones-photo" type="file" name="images[]" accept="image/*,.heic,.heif" multiple data-max-files="<?= max(0, 5 - count($analysisMediaBySection['lesiones'])) ?>" data-preview-target="analysis-lesiones-preview" <?= count($analysisMediaBySection['lesiones']) >= 5 ? 'disabled' : '' ?>>
                        <p class="analysis-upload-hint">Máximo 5 imágenes. Guardadas: <?= count($analysisMediaBySection['lesiones']) ?>/5. Admite JPG, PNG, WEBP, GIF y HEIC.</p>
                      </div>
                      <div class="analysis-upload-status" data-upload-message></div>
                      <div class="action-row">
                        <button type="submit" class="btn-shell" <?= count($analysisMediaBySection['lesiones']) >= 5 ? 'disabled' : '' ?>>Guardar imágenes</button>
                      </div>
                    </form>
                    <div class="analysis-preview" id="analysis-lesiones-preview" hidden></div>
                    <?php if ($analysisMediaBySection['lesiones'] !== []): ?>
                      <div class="analysis-inline-slider js-analysis-inline-slider">
                        <div class="analysis-inline-stage">
                          <button type="button" class="analysis-inline-nav analysis-inline-prev js-analysis-inline-prev" aria-label="Imagen anterior">‹</button>
                          <img class="analysis-inline-image js-analysis-inline-image" src="" alt="Imagen de lesiones">
                          <button type="button" class="analysis-inline-nav analysis-inline-next js-analysis-inline-next" aria-label="Imagen siguiente">›</button>
                        </div>
                        <div class="analysis-inline-meta">
                          <span class="analysis-image-order js-analysis-inline-order"></span>
                          <span class="analysis-image-name js-analysis-inline-caption"></span>
                        </div>
                        <div hidden>
                          <?php foreach ($analysisMediaBySection['lesiones'] as $mediaIndex => $media): ?>
                            <button
                              type="button"
                              class="js-analysis-inline-item"
                              data-index="<?= (int) $mediaIndex ?>"
                              data-src="<?= h((string) ($media['archivo_path'] ?? '')) ?>"
                              data-caption="<?= h((string) (($media['archivo_nombre'] ?? '') !== '' ? $media['archivo_nombre'] : 'Imagen guardada')) ?>"
                              data-order="<?= (int) ($media['sort_order'] ?? ($mediaIndex + 1)) ?>"
                            ></button>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                </article>
              </div>
            </div>
          </div>
        </div>
      </div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function () {
    const accId = <?= (int) $accidente_id ?>;
    const workbenchStates = new Map();
    const editStates = new Map();
    const VEH_CATALOGS = <?= json_encode($vehiculoCatalogos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function serializeForm(form) {
      if (!form) return '';
      const entries = [];
      const data = new FormData(form);
      for (const [key, value] of data.entries()) {
        entries.push([key, value instanceof File ? value.name : String(value)]);
      }
      return JSON.stringify(entries);
    }

    function getWorkbenchState(frame) {
      return workbenchStates.get(frame.id) || null;
    }

    function closeWorkbenchImmediate(workbench, frame) {
      frame.src = 'about:blank';
      workbench.hidden = true;
      workbenchStates.delete(frame.id);
    }

    function requestCloseWorkbench(workbench, frame) {
      const state = getWorkbenchState(frame);
      if (!state || !state.form || !state.dirty) {
        closeWorkbenchImmediate(workbench, frame);
        return;
      }

      const shouldSave = window.confirm('Hay cambios sin guardar. ¿Desea guardar los cambios?');
      if (shouldSave) {
        try {
          if (typeof state.form.requestSubmit === 'function') {
            state.form.requestSubmit();
          } else {
            state.form.submit();
          }
        } catch (error) {
          console.error(error);
        }
        return;
      }

      closeWorkbenchImmediate(workbench, frame);
    }

    function registerWorkbenchFrame(frame) {
      frame.addEventListener('load', () => {
        try {
          const doc = frame.contentDocument;
          const form = doc ? doc.querySelector('form') : null;
          const state = {
            frame,
            form,
            initial: form ? serializeForm(form) : '',
            dirty: false,
          };

          if (form) {
            const markDirty = () => {
              state.dirty = serializeForm(form) !== state.initial;
            };
            form.addEventListener('input', markDirty, true);
            form.addEventListener('change', markDirty, true);
            doc.addEventListener('keydown', (event) => {
              if (event.key !== 'Escape') return;
              event.preventDefault();
              event.stopPropagation();
              const workbench = frame.closest('.inline-workbench');
              if (!workbench) return;
              requestCloseWorkbench(workbench, frame);
            }, true);
          }

          workbenchStates.set(frame.id, state);
        } catch (error) {
          workbenchStates.delete(frame.id);
        }
      });
    }

    function visibleWorkbench() {
      const items = Array.from(document.querySelectorAll('.inline-workbench')).filter((item) => !item.hidden);
      return items.length ? items[items.length - 1] : null;
    }

    function activeEditShell() {
      const items = Array.from(document.querySelectorAll('.editable-form')).filter((item) => !item.hidden);
      return items.length ? items[items.length - 1] : null;
    }

    function optionNode(value, label, selected = false) {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = label;
      if (selected) option.selected = true;
      return option;
    }

    function fillSelect(select, items, placeholder, selectedValue, labelResolver) {
      select.innerHTML = '';
      select.appendChild(optionNode('', placeholder, selectedValue === ''));
      items.forEach((item) => {
        const value = String(item.id ?? '');
        select.appendChild(optionNode(value, labelResolver(item), value === selectedValue));
      });
      select.disabled = false;
    }

    function clearSelect(select, placeholder) {
      select.innerHTML = '';
      select.appendChild(optionNode('', placeholder, true));
      select.disabled = true;
    }

    function syncVehicleForm(form, preserveCurrent) {
      const category = form.querySelector('[name="categoria_id"]');
      const type = form.querySelector('.js-veh-tipo');
      const body = form.querySelector('.js-veh-carroceria');
      const brand = form.querySelector('[name="marca_id"]');
      const model = form.querySelector('.js-veh-modelo');
      if (!category || !type || !body || !brand || !model) return;

      const selectedType = preserveCurrent ? String(type.dataset.current || '') : String(type.value || '');
      const selectedBody = preserveCurrent ? String(body.dataset.current || '') : String(body.value || '');
      const selectedModel = preserveCurrent ? String(model.dataset.current || '') : String(model.value || '');
      const categoryId = String(category.value || '');
      const brandId = String(brand.value || '');

      if (categoryId === '') {
        clearSelect(type, '(Selecciona una categoría primero)');
        clearSelect(body, '(Selecciona un tipo primero)');
      } else {
        const types = VEH_CATALOGS.tipos.filter((item) => String(item.categoria_id) === categoryId);
        fillSelect(type, types, '(Selecciona)', selectedType, (item) => {
          return String(item.codigo || '') + (item.nombre ? ' - ' + item.nombre : '');
        });
        const typeId = String(type.value || '');
        if (typeId === '') {
          clearSelect(body, '(Selecciona un tipo primero)');
        } else {
          const bodies = VEH_CATALOGS.carrocerias.filter((item) => String(item.tipo_id) === typeId);
          fillSelect(body, bodies, '(Selecciona)', selectedBody, (item) => String(item.nombre || ''));
        }
      }

      if (brandId === '') {
        clearSelect(model, '(Selecciona una marca primero)');
      } else {
        const models = VEH_CATALOGS.modelos.filter((item) => String(item.marca_id) === brandId);
        fillSelect(model, models, '(Selecciona)', selectedModel, (item) => String(item.nombre || ''));
      }
    }

    function initVehicleInlineForm(form) {
      if (!form || form.dataset.vehicleBound === '1') return;
      form.dataset.vehicleBound = '1';

      const category = form.querySelector('[name="categoria_id"]');
      const type = form.querySelector('.js-veh-tipo');
      const brand = form.querySelector('[name="marca_id"]');

      if (category) {
        category.addEventListener('change', () => {
          const body = form.querySelector('.js-veh-carroceria');
          if (type) type.dataset.current = '';
          if (body) body.dataset.current = '';
          syncVehicleForm(form, false);
        });
      }

      if (type) {
        type.addEventListener('change', () => {
          const body = form.querySelector('.js-veh-carroceria');
          if (body) body.dataset.current = '';
          syncVehicleForm(form, false);
        });
      }

      if (brand) {
        brand.addEventListener('change', () => {
          const model = form.querySelector('.js-veh-modelo');
          if (model) model.dataset.current = '';
          syncVehicleForm(form, false);
        });
      }
    }

    async function fetchAccidenteJson(params) {
      const url = new URL(window.location.href);
      Object.entries(params).forEach(([key, value]) => {
        url.searchParams.set(key, value);
      });
      const response = await fetch(url.toString(), {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      return response.json();
    }

    function fillAccSelect(select, rows, valueKey, labelKey, placeholder, selectedValue, labelBuilder) {
      if (!select) return;
      select.innerHTML = '';
      select.appendChild(optionNode('', placeholder, selectedValue === ''));
      rows.forEach((row) => {
        const value = String(row[valueKey] ?? '');
        const label = typeof labelBuilder === 'function' ? labelBuilder(row) : String(row[labelKey] ?? '');
        select.appendChild(optionNode(value, label, value === selectedValue));
      });
      select.disabled = false;
    }

    function clearAccSelect(select, placeholder) {
      if (!select) return;
      select.innerHTML = '';
      select.appendChild(optionNode('', placeholder, true));
      select.disabled = true;
    }

    function initAccidenteInlineForm(form) {
      if (!form || form.dataset.accidenteBound === '1') return;
      form.dataset.accidenteBound = '1';

      const dep = form.querySelector('[name="cod_dep"]');
      const prov = form.querySelector('[name="cod_prov"]');
      const dist = form.querySelector('[name="cod_dist"]');
      const comisaria = form.querySelector('[name="comisaria_id"]');
      const fiscalia = form.querySelector('[name="fiscalia_id"]');
      const fiscal = form.querySelector('[name="fiscal_id"]');
      const fiscalTel = form.querySelector('#acc-fiscal-tel');

      const loadProvincias = async (preserveCurrent = false) => {
        const depValue = String(dep?.value || '');
        const selected = preserveCurrent ? String(prov?.dataset.current || prov?.value || '') : '';
        if (!depValue) {
          clearAccSelect(prov, '-- Selecciona --');
          clearAccSelect(dist, '-- Selecciona --');
          clearAccSelect(comisaria, '-- Selecciona --');
          return;
        }

        const json = await fetchAccidenteJson({ ajax: 'prov', dep: depValue });
        fillAccSelect(prov, json.data || [], 'cod_prov', 'nombre', '-- Selecciona --', selected);
        if (!preserveCurrent) {
          prov.dataset.current = '';
        }
      };

      const loadDistritos = async (preserveCurrent = false) => {
        const depValue = String(dep?.value || '');
        const provValue = String(prov?.value || '');
        const selected = preserveCurrent ? String(dist?.dataset.current || dist?.value || '') : '';
        if (!depValue || !provValue) {
          clearAccSelect(dist, '-- Selecciona --');
          clearAccSelect(comisaria, '-- Selecciona --');
          return;
        }

        const json = await fetchAccidenteJson({ ajax: 'dist', dep: depValue, prov: provValue });
        fillAccSelect(dist, json.data || [], 'cod_dist', 'nombre', '-- Selecciona --', selected);
        if (!preserveCurrent) {
          dist.dataset.current = '';
        }
      };

      const loadComisarias = async (preserveCurrent = false) => {
        const depValue = String(dep?.value || '');
        const provValue = String(prov?.value || '');
        const distValue = String(dist?.value || '');
        const selected = preserveCurrent ? String(comisaria?.dataset.current || comisaria?.value || '') : '';
        if (!depValue || !provValue || !distValue) {
          clearAccSelect(comisaria, '-- Selecciona --');
          return;
        }

        const json = await fetchAccidenteJson({ ajax: 'comisarias_dist', dep: depValue, prov: provValue, dist: distValue });
        fillAccSelect(comisaria, json.data || [], 'id', 'nombre', '-- Selecciona --', selected, (row) => {
          return String(row.nombre || '') + (row._fuera ? ' (fuera del distrito)' : '');
        });
        if (!preserveCurrent) {
          comisaria.dataset.current = '';
        }
      };

      const loadFiscales = async (preserveCurrent = false) => {
        const fiscaliaValue = String(fiscalia?.value || '');
        const selected = preserveCurrent ? String(fiscal?.dataset.current || fiscal?.value || '') : '';
        if (!fiscaliaValue) {
          clearAccSelect(fiscal, '-- Selecciona --');
          if (fiscalTel) fiscalTel.value = '';
          return;
        }

        const json = await fetchAccidenteJson({ ajax: 'fiscales', fiscalia_id: fiscaliaValue });
        fillAccSelect(fiscal, json.data || [], 'id', 'nombre', '-- Selecciona --', selected);
        if (!preserveCurrent) {
          fiscal.dataset.current = '';
        }
      };

      const loadFiscalInfo = async () => {
        const fiscalValue = String(fiscal?.value || '');
        if (!fiscalValue) {
          if (fiscalTel) fiscalTel.value = '';
          return;
        }

        const json = await fetchAccidenteJson({ ajax: 'fiscal_info', fiscal_id: fiscalValue });
        if (fiscalTel) {
          fiscalTel.value = String((json.data && json.data.telefono) || '');
        }
      };

      dep?.addEventListener('change', async () => {
        if (prov) prov.dataset.current = '';
        if (dist) dist.dataset.current = '';
        if (comisaria) comisaria.dataset.current = '';
        await loadProvincias(false);
        await loadDistritos(false);
        await loadComisarias(false);
      });

      prov?.addEventListener('change', async () => {
        if (dist) dist.dataset.current = '';
        if (comisaria) comisaria.dataset.current = '';
        await loadDistritos(false);
        await loadComisarias(false);
      });

      dist?.addEventListener('change', async () => {
        if (comisaria) comisaria.dataset.current = '';
        await loadComisarias(false);
      });

      fiscalia?.addEventListener('change', async () => {
        if (fiscal) fiscal.dataset.current = '';
        await loadFiscales(false);
        await loadFiscalInfo();
      });

      fiscal?.addEventListener('change', loadFiscalInfo);

      form.__accidenteLoaders = { loadProvincias, loadDistritos, loadComisarias, loadFiscales, loadFiscalInfo };
    }

    function initPersonaInlineForm(form) {
      if (!form || form.dataset.personaBound === '1') return;
      form.dataset.personaBound = '1';

      const birth = form.querySelector('[name="fecha_nacimiento"]');
      const age = form.querySelector('[name="edad_preview"]');
      if (!birth || !age) return;

      const updateAge = () => {
        if (!birth.value) {
          age.value = '';
          return;
        }
        const born = new Date(birth.value + 'T00:00:00');
        const today = new Date();
        let years = today.getFullYear() - born.getFullYear();
        const monthDiff = today.getMonth() - born.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < born.getDate())) {
          years -= 1;
        }
        age.value = years >= 0 ? String(years) : '';
      };

      birth.addEventListener('change', updateAge);
      birth.addEventListener('input', updateAge);
      updateAge();
    }

    function triggerHiddenSync(hidden) {
      if (!hidden) return;
      hidden.dispatchEvent(new Event('input', { bubbles: true }));
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function initItpTagBox(root) {
      if (!root || root.dataset.bound === '1') return;
      root.dataset.bound = '1';

      const items = root.querySelector('.tag-items');
      const input = root.querySelector('input[type="text"]');
      const button = root.querySelector('button');
      const hidden = root.querySelector('input[type="hidden"]');
      if (!items || !input || !button || !hidden) return;

      function arr() {
        return (hidden.value || '').split(',').map((item) => item.trim()).filter(Boolean);
      }

      function render() {
        items.innerHTML = '';
        arr().forEach((text, index) => {
          const chip = document.createElement('div');
          chip.className = 'itp-builder-item';
          const label = document.createElement('span');
          label.textContent = text;
          const del = document.createElement('button');
          del.type = 'button';
          del.className = 'btn-shell';
          del.textContent = 'Quitar';
          del.addEventListener('click', () => {
            const next = arr();
            next.splice(index, 1);
            hidden.value = next.join(', ');
            render();
            triggerHiddenSync(hidden);
          });
          chip.appendChild(label);
          chip.appendChild(del);
          items.appendChild(chip);
        });
      }

      function add() {
        let value = (input.value || '').trim();
        if (!value) return;
        value = value.replace(/,/g, ' ');
        const next = arr();
        next.push(value);
        hidden.value = next.join(', ');
        input.value = '';
        render();
        triggerHiddenSync(hidden);
      }

      button.addEventListener('click', add);
      input.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        add();
      });

      render();
    }

    function initItpMeasureBox(root) {
      if (!root || root.dataset.bound === '1') return;
      root.dataset.bound = '1';

      const name = root.querySelector('.m-name');
      const value = root.querySelector('.m-value');
      const button = root.querySelector('.m-add');
      const list = root.querySelector('.measure-list');
      const hidden = root.querySelector('input[type="hidden"]');
      if (!name || !value || !button || !list || !hidden) return;

      function arr() {
        return (hidden.value || '').split(',').map((item) => item.trim()).filter(Boolean);
      }

      function formatMeters(raw) {
        const clean = String(raw || '').replace(',', '.').replace(/[^\d.]/g, '');
        if (!clean) return '';
        const num = parseFloat(clean);
        if (Number.isNaN(num)) return '';
        let parts = num.toFixed(2).split('.');
        if (parts[0].length === 1) parts[0] = '0' + parts[0];
        return parts[0] + '.' + parts[1] + ' m';
      }

      function render() {
        list.innerHTML = '';
        arr().forEach((text, index) => {
          const row = document.createElement('div');
          row.className = 'itp-builder-item';
          const label = document.createElement('span');
          label.textContent = text;
          const del = document.createElement('button');
          del.type = 'button';
          del.className = 'btn-shell';
          del.textContent = 'Quitar';
          del.addEventListener('click', () => {
            const next = arr();
            next.splice(index, 1);
            hidden.value = next.join(', ');
            render();
            triggerHiddenSync(hidden);
          });
          row.appendChild(label);
          row.appendChild(del);
          list.appendChild(row);
        });
      }

      function add() {
        const left = (name.value || '').trim().replace(/,/g, ' ');
        const right = formatMeters(value.value || '');
        if (!left || !right) return;
        const next = arr();
        next.push('- ' + left + ' : ' + right);
        hidden.value = next.join(', ');
        name.value = '';
        value.value = '';
        render();
        triggerHiddenSync(hidden);
      }

      button.addEventListener('click', add);
      [name, value].forEach((input) => {
        input.addEventListener('keydown', (event) => {
          if (event.key !== 'Enter') return;
          event.preventDefault();
          add();
        });
      });

      render();
    }

    function initItpInlineForm(form) {
      if (!form || form.dataset.itpBound === '1') return;
      form.dataset.itpBound = '1';

      form.querySelectorAll('.js-itp-tagbox').forEach((root) => initItpTagBox(root));
      form.querySelectorAll('.js-itp-measurebox').forEach((root) => initItpMeasureBox(root));

      const via2Section = form.querySelector('.js-itp-via2-section');
      const via2Add = form.querySelector('.js-itp-via2-add');
      const via2Remove = form.querySelector('.js-itp-via2-remove');
      const via2Flag = form.querySelector('.js-itp-via2-flag');

      function syncVia2(enabled) {
        if (!via2Section || !via2Flag) return;
        via2Section.hidden = !enabled;
        via2Flag.value = enabled ? '1' : '0';
        if (via2Add) via2Add.hidden = enabled;
        if (via2Remove) via2Remove.hidden = !enabled;

        const controls = Array.from(via2Section.querySelectorAll('input, textarea, select, button'));
        controls.forEach((control) => {
          if (control === via2Flag) return;
          if (control.name === 'medidas_via2' || control.name === 'observaciones_via2') return;
          if (control.type === 'hidden') return;
          control.disabled = !enabled;
        });

        const hiddenMeasures = via2Section.querySelector('input[name="medidas_via2"]');
        const hiddenObs = via2Section.querySelector('input[name="observaciones_via2"]');
        if (hiddenMeasures) hiddenMeasures.disabled = !enabled;
        if (hiddenObs) hiddenObs.disabled = !enabled;
      }

      function clearVia2() {
        if (!via2Section) return;
        via2Section.querySelectorAll('input, textarea, select').forEach((control) => {
          if (control.classList.contains('m-name') || control.classList.contains('m-value')) {
            control.value = '';
            return;
          }
          if (control.type === 'hidden') {
            if (control.name === 'medidas_via2' || control.name === 'observaciones_via2') {
              control.value = '';
            }
            return;
          }
          control.value = '';
        });
        via2Section.querySelectorAll('.measure-list, .tag-items').forEach((node) => {
          node.innerHTML = '';
        });
      }

      via2Add?.addEventListener('click', () => {
        syncVia2(true);
        triggerHiddenSync(via2Flag);
      });

      via2Remove?.addEventListener('click', () => {
        clearVia2();
        syncVia2(false);
        triggerHiddenSync(via2Flag);
      });

      syncVia2(String(via2Flag?.value || '0') === '1');
    }

    function showInlineError(form, message) {
      const targetId = form.dataset.error;
      const node = targetId ? document.getElementById(targetId) : null;
      if (!node) return;
      if (!message) {
        node.textContent = '';
        node.classList.remove('is-visible');
        return;
      }
      node.textContent = message;
      node.classList.add('is-visible');
    }

    function getEditState(shellName) {
      return editStates.get(shellName) || null;
    }

    function openEditShell(shellName) {
      const shell = document.querySelector('[data-edit-shell="' + shellName + '"]');
      if (!shell) return;

      const form = shell.querySelector('.editable-form[data-shell="' + shellName + '"]');
      const view = shell.querySelector('[data-edit-view="' + shellName + '"]');
      const actions = shell.querySelector('[data-edit-actions="' + shellName + '"]');
      const opener = shell.querySelector('.js-edit-start[data-shell="' + shellName + '"]');
      if (!form || !view || !actions || !opener) return;

      let preparePromise = Promise.resolve();

      if (form.classList.contains('js-veh-inline-form')) {
        initVehicleInlineForm(form);
        syncVehicleForm(form, true);
      }
      if (form.classList.contains('js-accidente-inline-form')) {
        initAccidenteInlineForm(form);
        const loaders = form.__accidenteLoaders || null;
        if (loaders) {
          preparePromise = Promise.resolve()
            .then(() => loaders.loadProvincias(true))
            .then(() => loaders.loadDistritos(true))
            .then(() => loaders.loadComisarias(true))
            .then(() => loaders.loadFiscales(true))
            .then(() => loaders.loadFiscalInfo());
        }
      }
      if (form.classList.contains('js-persona-inline-form')) {
        initPersonaInlineForm(form);
      }
      if (form.id.startsWith('itp-inline-form-')) {
        initItpInlineForm(form);
      }

      view.hidden = true;
      form.hidden = false;
      actions.hidden = false;
      opener.hidden = true;
      showInlineError(form, '');

      const state = {
        shellName,
        shell,
        form,
        view,
        actions,
        opener,
        initial: '',
        dirty: false,
      };
      editStates.set(shellName, state);

      preparePromise.finally(() => {
        const latest = getEditState(shellName);
        if (!latest) return;
        latest.initial = serializeForm(form);
        latest.dirty = false;
      });

      const firstControl = form.querySelector('input:not([type="hidden"]), select, textarea');
      if (firstControl) firstControl.focus();
    }

    function closeEditShellImmediate(shellName) {
      const state = getEditState(shellName);
      if (!state) return;

      state.form.reset();
      if (state.form.classList.contains('js-veh-inline-form')) {
        syncVehicleForm(state.form, true);
      }
      if (state.form.classList.contains('js-accidente-inline-form')) {
        const loaders = state.form.__accidenteLoaders || null;
        if (loaders) {
          Promise.resolve()
            .then(() => loaders.loadProvincias(true))
            .then(() => loaders.loadDistritos(true))
            .then(() => loaders.loadComisarias(true))
            .then(() => loaders.loadFiscales(true))
            .then(() => loaders.loadFiscalInfo());
        }
      }
      if (state.form.classList.contains('js-persona-inline-form')) {
        initPersonaInlineForm(state.form);
        const birth = state.form.querySelector('[name="fecha_nacimiento"]');
        if (birth) {
          birth.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }

      state.view.hidden = false;
      state.form.hidden = true;
      state.actions.hidden = true;
      state.opener.hidden = false;
      showInlineError(state.form, '');
      editStates.delete(shellName);
    }

    function requestCloseEditShell(shellName) {
      const state = getEditState(shellName);
      if (!state || !state.form || !state.dirty) {
        closeEditShellImmediate(shellName);
        return;
      }

      const shouldSave = window.confirm('Hay cambios sin guardar. ¿Desea guardar los cambios?');
      if (shouldSave) {
        const saveButton = document.querySelector('[form="' + state.form.id + '"][type="submit"]');
        if (saveButton) {
          saveButton.click();
        } else if (typeof state.form.requestSubmit === 'function') {
          state.form.requestSubmit();
        } else {
          state.form.submit();
        }
        return;
      }

      closeEditShellImmediate(shellName);
    }

    document.querySelectorAll('#accTabs, .participant-tabs, .inner-tabs').forEach((nav) => {
      if (!nav.id) return;
      const storageKey = 'uiat_tab_' + accId + '_' + nav.id;
      nav.querySelectorAll('[data-bs-toggle="tab"]').forEach((button) => {
        button.addEventListener('shown.bs.tab', (event) => {
          resetCollapsibleCards(document);
          const target = event.target.getAttribute('data-bs-target');
          if (target) {
            localStorage.setItem(storageKey, target);
          }
        });
      });
      const saved = localStorage.getItem(storageKey);
      if (saved) {
        const trigger = nav.querySelector('[data-bs-target="' + saved + '"]');
        if (trigger) {
          new bootstrap.Tab(trigger).show();
        }
      }
    });

    document.querySelectorAll('.inline-frame').forEach((frame) => {
      registerWorkbenchFrame(frame);
    });

    document.querySelectorAll('.js-inline-open').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const workbench = document.getElementById(link.dataset.workbench);
        const frame = document.getElementById(link.dataset.frame);
        const title = workbench ? workbench.querySelector('strong') : null;
        if (!workbench || !frame) return;
        if (title) {
          title.textContent = link.dataset.title || 'Formulario';
        }
        frame.src = link.href;
        workbench.hidden = false;
        workbench.scrollIntoView({behavior: 'smooth', block: 'start'});
      });
    });

    document.querySelectorAll('.js-inline-close').forEach((button) => {
      button.addEventListener('click', () => {
        const workbench = document.getElementById(button.dataset.workbench);
        const frame = document.getElementById(button.dataset.frame);
        if (!workbench || !frame) return;
        requestCloseWorkbench(workbench, frame);
      });
    });

    document.querySelectorAll('.js-manifestacion-delete').forEach((button) => {
      button.addEventListener('click', async () => {
        const manifestacionId = String(button.dataset.manifestacionId || '');
        if (!manifestacionId) return;
        if (!window.confirm('¿Eliminar esta manifestación?')) return;

        button.disabled = true;
        try {
          const formData = new FormData();
          formData.append('action', 'delete_manifestacion_inline');
          formData.append('manifestacion_id', manifestacionId);

          const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          let data = null;
          try {
            data = await response.json();
          } catch (error) {
            data = null;
          }

          if (!response.ok || !data || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'No se pudo eliminar la manifestación.');
          }

          window.location.reload();
        } catch (error) {
          window.alert(error instanceof Error ? error.message : 'No se pudo eliminar la manifestación.');
        } finally {
          button.disabled = false;
        }
      });
    });

    window.addEventListener('message', (event) => {
      const payload = event.data || {};
      if (payload.type === 'docveh:created' || payload.type === 'docveh:updated') {
        window.location.reload();
      }
    });

    document.querySelectorAll('.js-quick-status').forEach((select) => {
      const paintStatus = () => {
        const value = String(select.value || '').toLowerCase();
        select.classList.remove('status-pendiente', 'status-resuelto', 'status-diligencias');
        if (value === 'pendiente') {
          select.classList.add('status-pendiente');
        } else if (value === 'resuelto') {
          select.classList.add('status-resuelto');
        } else if (value === 'con diligencias') {
          select.classList.add('status-diligencias');
        }
      };

      paintStatus();

      select.addEventListener('change', async () => {
        const previous = String(select.dataset.prev || '');
        const nextValue = String(select.value || '');
        paintStatus();
        select.disabled = true;

        try {
          const formData = new FormData();
          formData.append('action', 'save_accidente_estado_inline');
          formData.append('accidente_id', String(select.dataset.accidenteId || '0'));
          formData.append('estado', nextValue);

          const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          let data = null;
          try {
            data = await response.json();
          } catch (error) {
            data = null;
          }

          if (!response.ok || !data || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'No se pudo actualizar el estado.');
          }

          select.dataset.prev = nextValue;
        } catch (error) {
          select.value = previous;
          paintStatus();
          window.alert(error instanceof Error ? error.message : 'No se pudo actualizar el estado.');
        } finally {
          select.disabled = false;
        }
      });
    });

    document.querySelectorAll('.js-quick-oficio-status').forEach((select) => {
      const paintStatus = () => {
        const value = String(select.value || '').toLowerCase();
        select.classList.remove('status-borrador', 'status-firmado', 'status-enviado', 'status-anulado', 'status-archivado');
        if (value === 'borrador') {
          select.classList.add('status-borrador');
        } else if (value === 'firmado') {
          select.classList.add('status-firmado');
        } else if (value === 'enviado') {
          select.classList.add('status-enviado');
        } else if (value === 'anulado') {
          select.classList.add('status-anulado');
        } else if (value === 'archivado') {
          select.classList.add('status-archivado');
        }
      };

      paintStatus();

      select.addEventListener('change', async () => {
        const previous = String(select.dataset.prev || '');
        const nextValue = String(select.value || '');
        paintStatus();
        select.disabled = true;

        try {
          const formData = new FormData();
          formData.append('action', 'save_oficio_estado_inline');
          formData.append('oficio_id', String(select.dataset.oficioId || '0'));
          formData.append('estado', nextValue);

          const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          let data = null;
          try {
            data = await response.json();
          } catch (error) {
            data = null;
          }

          if (!response.ok || !data || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'No se pudo actualizar el estado del oficio.');
          }

          select.dataset.prev = nextValue;
        } catch (error) {
          select.value = previous;
          paintStatus();
          window.alert(error instanceof Error ? error.message : 'No se pudo actualizar el estado del oficio.');
        } finally {
          select.disabled = false;
        }
      });
    });

    document.querySelectorAll('.js-quick-diligencia-status').forEach((select) => {
      const paintStatus = () => {
        const value = String(select.value || '').toLowerCase();
        select.classList.remove('status-pendiente', 'status-resuelto');
        if (value === 'resuelto') {
          select.classList.add('status-resuelto');
        } else {
          select.classList.add('status-pendiente');
        }
      };

      paintStatus();

      select.addEventListener('change', async () => {
        const previous = String(select.dataset.prev || 'Pendiente');
        const nextValue = String(select.value || 'Pendiente');
        paintStatus();
        select.disabled = true;

        try {
          const formData = new FormData();
          formData.append('action', 'save_diligencia_estado_inline');
          formData.append('diligencia_id', String(select.dataset.diligenciaId || '0'));
          formData.append('estado', nextValue);

          const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          let data = null;
          try {
            data = await response.json();
          } catch (error) {
            data = null;
          }

          if (!response.ok || !data || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'No se pudo actualizar el estado de la diligencia.');
          }

          select.dataset.prev = nextValue;
          const shell = select.closest('.editable-shell');
          const hiddenEstado = shell ? shell.querySelector('input[name="estado"]') : null;
          if (hiddenEstado) {
            hiddenEstado.value = nextValue === 'Resuelto' ? 'Realizado' : 'Pendiente';
          }
        } catch (error) {
          select.value = previous;
          paintStatus();
          window.alert(error instanceof Error ? error.message : 'No se pudo actualizar el estado de la diligencia.');
        } finally {
          select.disabled = false;
        }
      });
    });

    document.querySelectorAll('.js-inline-ajax-form').forEach((form) => {
      const shellName = form.dataset.shell;
      if (!shellName) return;

      if (form.classList.contains('js-veh-inline-form')) {
        initVehicleInlineForm(form);
      }
      if (form.classList.contains('js-accidente-inline-form')) {
        initAccidenteInlineForm(form);
      }
      if (form.classList.contains('js-persona-inline-form')) {
        initPersonaInlineForm(form);
      }

      const markDirty = () => {
        const state = getEditState(shellName);
        if (!state) return;
        state.dirty = serializeForm(form) !== state.initial;
      };

      form.addEventListener('input', markDirty, true);
      form.addEventListener('change', markDirty, true);

      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        showInlineError(form, '');

        const submitter = event.submitter;
        if (submitter) submitter.disabled = true;

        try {
          const response = await fetch(window.location.href, {
            method: 'POST',
            body: new FormData(form),
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          let data = null;
          try {
            data = await response.json();
          } catch (error) {
            data = null;
          }

          if (!response.ok || !data || !data.ok) {
            showInlineError(form, (data && data.message) ? data.message : 'No se pudo guardar la información.');
            return;
          }

          window.location.reload();
        } catch (error) {
          showInlineError(form, 'No se pudo conectar con el servidor.');
        } finally {
          if (submitter) submitter.disabled = false;
        }
      });
    });

    document.querySelectorAll('.js-edit-start').forEach((button) => {
      button.addEventListener('click', () => {
        const card = button.closest('[data-collapsible-card]');
        if (card) {
          setCollapsibleCardState(card, true);
        }
        openEditShell(button.dataset.shell || '');
      });
    });

    document.querySelectorAll('.js-edit-cancel').forEach((button) => {
      button.addEventListener('click', () => {
        requestCloseEditShell(button.dataset.shell || '');
      });
    });

    document.querySelectorAll('.js-copy-name').forEach((button) => {
      button.addEventListener('click', async () => {
        const originalText = button.textContent;
        const text = String(button.dataset.copyText || '').trim();
        if (!text) return;

        try {
          if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            await navigator.clipboard.writeText(text);
          } else {
            const temp = document.createElement('textarea');
            temp.value = text;
            temp.setAttribute('readonly', 'readonly');
            temp.style.position = 'absolute';
            temp.style.left = '-9999px';
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
          }

          button.classList.add('is-copied');
          button.textContent = 'Copiado';
          window.setTimeout(() => {
            button.classList.remove('is-copied');
            button.textContent = originalText || 'Copiar';
          }, 1400);
        } catch (error) {
          button.textContent = 'Error';
          window.setTimeout(() => {
            button.textContent = originalText || 'Copiar';
          }, 1400);
        }
      });
    });

    function setCollapsibleCardState(card, expanded) {
      const panel = card.querySelector('.js-card-panel');
      if (!panel) return;

      panel.hidden = !expanded;

      card.querySelectorAll('.js-card-toggle').forEach((button) => {
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        button.textContent = expanded ? '-' : '+';
        button.title = expanded ? 'Ocultar detalle' : 'Mostrar detalle';
        button.setAttribute('aria-label', expanded ? 'Ocultar detalle' : 'Mostrar detalle');
      });
    }

    function applyNuevoButtonStyles(scope) {
      (scope || document).querySelectorAll('a.btn-shell, button.btn-shell').forEach((button) => {
        const text = (button.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (text.startsWith('nuevo ') || text.startsWith('+ nuevo ') || text.startsWith('nueva ') || text.startsWith('+ nueva ')) {
          button.classList.add('btn-nuevo');
        }
      });
    }

    function resetCollapsibleCards(scope) {
      (scope || document).querySelectorAll('[data-collapsible-card]').forEach((card) => {
        setCollapsibleCardState(card, false);
      });
    }

    function initAnalysisImagePreview(input) {
      if (!input || input.dataset.previewBound === '1') return;
      input.dataset.previewBound = '1';

      const previewId = input.dataset.previewTarget || '';
      const preview = previewId ? document.getElementById(previewId) : null;
      if (!preview) return;

      input.addEventListener('change', async () => {
        const files = Array.from(input.files || []);
        const maxFiles = Number(input.dataset.maxFiles || '0');
        preview.innerHTML = '';

        if (!files.length) {
          preview.hidden = true;
          return;
        }

        if (maxFiles > 0 && files.length > maxFiles) {
          window.alert('Solo puedes subir ' + maxFiles + ' imagen(es) adicional(es) en esta sección.');
          input.value = '';
          preview.hidden = true;
          return;
        }

        let imageFiles = files.filter((file) => {
          return file.type.startsWith('image/') || isHeicLikeClientFile(file);
        });
        if (!imageFiles.length) {
          preview.hidden = true;
          return;
        }

        try {
          imageFiles = await convertHeicFilesForClient(imageFiles);
        } catch (error) {
          window.alert(error instanceof Error ? error.message : 'No se pudo preparar la vista previa de las imágenes.');
          input.value = '';
          preview.hidden = true;
          return;
        }

        imageFiles.forEach((file) => {
          const image = document.createElement('img');
          image.alt = 'Vista previa';
          image.src = URL.createObjectURL(file);
          image.addEventListener('load', () => {
            URL.revokeObjectURL(image.src);
          }, { once: true });
          preview.appendChild(image);
        });
        preview.hidden = false;
      });
    }

    function isHeicLikeClientFile(file) {
      const name = String(file && file.name ? file.name : '').toLowerCase();
      const type = String(file && file.type ? file.type : '').toLowerCase();
      return name.endsWith('.heic')
        || name.endsWith('.heif')
        || ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'].includes(type);
    }

    async function convertSingleHeicClientFile(file) {
      if (!isHeicLikeClientFile(file)) {
        return file;
      }
      if (typeof window.heic2any !== 'function') {
        throw new Error('Este navegador no pudo cargar el convertidor HEIC. Intenta nuevamente o usa JPG/PNG.');
      }

      const result = await window.heic2any({
        blob: file,
        toType: 'image/jpeg',
        quality: 0.9,
      });
      const blob = Array.isArray(result) ? result[0] : result;
      const baseName = String(file.name || 'imagen').replace(/\.[^.]+$/u, '');
      return new File([blob], baseName + '.jpg', {
        type: 'image/jpeg',
        lastModified: Date.now(),
      });
    }

    async function convertHeicFilesForClient(files) {
      const converted = [];
      for (const file of files) {
        converted.push(await convertSingleHeicClientFile(file));
      }
      return converted;
    }

    document.querySelectorAll('.js-analysis-upload-form').forEach((form) => {
      const input = form.querySelector('.js-analysis-image-input');
      const message = form.querySelector('[data-upload-message]');
      const submitButton = form.querySelector('button[type="submit"]');
      if (input) {
        initAnalysisImagePreview(input);
      }

      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!input || !input.files || !input.files.length) {
          if (message) message.textContent = 'Selecciona al menos una imagen.';
          return;
        }

        if (message) message.textContent = 'Guardando imágenes...';
        if (submitButton) submitButton.disabled = true;

        try {
          const payload = new FormData();
          Array.from(form.querySelectorAll('input[type="hidden"]')).forEach((hidden) => {
            payload.append(hidden.name, hidden.value);
          });

          const convertedFiles = await convertHeicFilesForClient(Array.from(input.files || []));
          convertedFiles.forEach((file) => {
            payload.append('images[]', file, file.name);
          });

          const response = await fetch(window.location.href, {
            method: 'POST',
            body: payload,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          let data = null;
          try {
            data = await response.json();
          } catch (error) {
            data = null;
          }

          if (!response.ok || !data || !data.ok) {
            throw new Error((data && data.message) ? data.message : 'No se pudieron guardar las imágenes.');
          }

          if (message) message.textContent = data.message || 'Imágenes guardadas.';
          window.location.reload();
        } catch (error) {
          if (message) {
            message.textContent = error instanceof Error ? error.message : 'No se pudieron guardar las imágenes.';
          }
        } finally {
          if (submitButton) submitButton.disabled = false;
        }
      });
    });

    function initAnalysisInlineSlider(root) {
      if (!root || root.dataset.sliderBound === '1') return;
      root.dataset.sliderBound = '1';

      const items = Array.from(root.querySelectorAll('.js-analysis-inline-item')).map((item) => ({
        src: String(item.dataset.src || ''),
        caption: String(item.dataset.caption || ''),
        order: String(item.dataset.order || ''),
      }));
      const image = root.querySelector('.js-analysis-inline-image');
      const caption = root.querySelector('.js-analysis-inline-caption');
      const order = root.querySelector('.js-analysis-inline-order');
      const prev = root.querySelector('.js-analysis-inline-prev');
      const next = root.querySelector('.js-analysis-inline-next');
      if (!items.length || !image || !caption || !order) return;

      let index = 0;

      const render = () => {
        const total = items.length;
        index = ((index % total) + total) % total;
        const item = items[index];
        image.src = item.src || '';
        caption.textContent = (item.caption || 'Imagen guardada') + ' · ' + (index + 1) + '/' + total;
        order.textContent = 'Orden ' + (item.order || String(index + 1));
        if (prev) prev.hidden = total <= 1;
        if (next) next.hidden = total <= 1;
      };

      prev?.addEventListener('click', () => {
        index -= 1;
        render();
      });
      next?.addEventListener('click', () => {
        index += 1;
        render();
      });

      render();
    }

    document.querySelectorAll('.js-analysis-inline-slider').forEach((slider) => {
      initAnalysisInlineSlider(slider);
    });

    document.querySelectorAll('.js-card-toggle').forEach((button) => {
      button.addEventListener('click', () => {
        const card = button.closest('[data-collapsible-card]');
        if (!card) return;

        const expanded = button.getAttribute('aria-expanded') === 'true';
        setCollapsibleCardState(card, !expanded);
      });
    });

    applyNuevoButtonStyles(document);

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      const workbench = visibleWorkbench();
      if (workbench) {
        const frame = workbench.querySelector('.inline-frame');
        if (!frame) return;
        event.preventDefault();
        requestCloseWorkbench(workbench, frame);
        return;
      }

      const activeEdit = activeEditShell();
      if (!activeEdit) return;
      event.preventDefault();
      requestCloseEditShell(activeEdit.dataset.shell || '');
    });

    window.addEventListener('message', (event) => {
      const data = event.data || {};
      if (data.type === 'occiso.close' || data.type === 'oficio.close' || data.type === 'documento_recibido.close') {
        const workbench = visibleWorkbench();
        if (!workbench) return;
        const frame = workbench.querySelector('.inline-frame');
        if (!frame) return;
        closeWorkbenchImmediate(workbench, frame);
        return;
      }
      if (['lc.saved', 'rml.saved', 'dosaje.saved', 'manifestacion.saved', 'occiso.saved', 'occiso.updated', 'oficio.saved', 'oficio.deleted', 'documento_recibido.saved', 'documento_recibido.deleted'].includes(data.type)) {
        window.location.reload();
      }
    });

    document.querySelectorAll('.js-inline-close').forEach((button) => {
      button.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        const workbench = document.getElementById(button.dataset.workbench);
        const frame = document.getElementById(button.dataset.frame);
        if (!workbench || !frame) return;
        requestCloseWorkbench(workbench, frame);
      });
    });

    document.querySelectorAll('.js-edit-cancel').forEach((button) => {
      button.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        requestCloseEditShell(button.dataset.shell || '');
      });
    });
  })();
</script>
</body>
</html>
