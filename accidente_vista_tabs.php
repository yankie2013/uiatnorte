<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\PersonaRepository;
use App\Repositories\AbogadoRepository;
use App\Repositories\AccidenteRepository;
use App\Repositories\DiligenciaPendienteRepository;
use App\Repositories\OficioRepository;
use App\Repositories\PolicialIntervinienteRepository;
use App\Repositories\PropietarioVehiculoRepository;
use App\Repositories\VehiculoRepository;
use App\Services\AbogadoService;
use App\Services\AccidenteService;
use App\Services\DiligenciaPendienteService;
use App\Services\OficioService;
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
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

function fecha_hora_simple(?string $value): string
{
    if (!$value || !strtotime($value)) {
        return '—';
    }
    return date('d/m/Y H:i', strtotime($value));
}

function join_con_y(array $items): string
{
    $items = array_values(array_filter(array_map(static fn($item) => trim((string) $item), $items)));
    $count = count($items);

    if ($count === 0) {
        return '—';
    }
    if ($count === 1) {
        return h($items[0]);
    }
    if ($count === 2) {
        return h($items[0]) . ' y ' . h($items[1]);
    }

    $escaped = array_map('h', $items);
    return implode(', ', array_slice($escaped, 0, -1)) . ' y ' . end($escaped);
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

function human_label(string $key): string
{
    static $map = [
        'tipo_doc' => 'Tipo de documento',
        'num_doc' => 'Número de documento',
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
        'numero_peritaje' => 'Número',
        'fecha_peritaje' => 'Fecha',
        'perito_peritaje' => 'Perito',
        'danos_peritaje' => 'Daños observados',
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

function oficio_icon(array $row): string
{
    $haystack = mb_strtolower(trim((string) (($row['asunto_nombre'] ?? '') . ' ' . ($row['asunto_detalle'] ?? ''))), 'UTF-8');

    if ($haystack === '') {
        return '📄';
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

    return '📄';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

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

            $payload = [
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

            $personaService->update($personaId, $payload);
            json_response(['ok' => true, 'message' => 'Persona actualizada correctamente.']);
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
                'vehiculo_inv_id' => (int) ($registro['vehiculo_inv_id'] ?? 0),
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

$accidenteInfo = safe_query_one(
    $pdo,
    "SELECT a.*,
            d.nombre AS dep_nom,
            p.nombre AS prov_nom,
            t.nombre AS dist_nom,
            c.nombre AS comisaria_nom,
            fa.nombre AS fiscalia_nom,
            CONCAT(fi.nombres,' ',fi.apellido_paterno,' ',fi.apellido_materno) AS fiscal_nom
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
$consConcat = $consecuencias ? implode(' → ', array_map('h', $consecuencias)) : '—';

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

$documentosRecibidos = safe_query_all(
    $pdo,
    "SELECT dr.*,
            COALESCE(o.numero, '') AS oficio_numero,
            COALESCE(o.anio, '') AS oficio_anio
       FROM documentos_recibidos dr
  LEFT JOIN oficios o ON o.id = dr.referencia_oficio_id
      WHERE dr.accidente_id = ?
   ORDER BY COALESCE(dr.fecha, '9999-12-31') DESC, dr.id DESC",
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
        'fields' => ['numero_peritaje', 'fecha_peritaje', 'perito_peritaje', ['key' => 'danos_peritaje', 'class' => 'span-2']],
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
              <?php if ($documentoVehiculoId > 0): ?>
                <a class="btn-shell js-inline-open" href="documento_vehiculo_editar.php?id=<?= $documentoVehiculoId ?>&embed=1&return_to=<?= $returnToEncoded ?>" data-workbench="<?= h($workbenchId) ?>" data-frame="<?= h($frameId) ?>" data-title="Documento de vehículo">Editar documento</a>
                <span class="chip-simple">Documento #<?= $documentoVehiculoId ?><?= $documentoVehiculoCount > 1 ? ' · ' . $documentoVehiculoCount . ' registro(s)' : '' ?></span>
              <?php elseif ($involucradoVehiculoId > 0): ?>
                <a class="btn-shell js-inline-open" href="documento_vehiculo_nuevo.php?invol_id=<?= $involucradoVehiculoId ?>&embed=1&return_to=<?= $returnToEncoded ?>" data-workbench="<?= h($workbenchId) ?>" data-frame="<?= h($frameId) ?>" data-title="Documento de vehículo">+ Nuevo documento</a>
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

$propietarioNaturalEditFields = [
    ['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
];

$propietarioJuridicaEditFields = [
    ['name' => 'ruc', 'label' => 'RUC', 'required' => true, 'maxlength' => 11],
    ['name' => 'rol_legal', 'label' => 'Rol legal'],
    ['name' => 'razon_social', 'label' => 'Razón social', 'required' => true, 'class' => 'span-2'],
    ['name' => 'domicilio_fiscal', 'label' => 'Domicilio fiscal', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
    ['name' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea', 'rows' => 3, 'class' => 'span-2'],
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
            "SELECT id, fecha, horario_inicio, hora_termino, modalidad, observaciones
               FROM Manifestacion
              WHERE persona_id = ? AND accidente_id = ?
           ORDER BY COALESCE(fecha, '9999-12-31') DESC, id DESC",
            [$personaId, $accidente_id]
        ) : [],
        'occ' => $personaId > 0 ? safe_query_all(
            $pdo,
            "SELECT id, fecha_levantamiento, hora_levantamiento, lugar_levantamiento, numero_protocolo, observaciones_levantamiento
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
    --muted:#66758c;
    --ink:#162033;
    --title-blue:#2f5f96;
    --title-blue-soft:#5a79a3;
    --gold:#9a7a1b;
    --gold-soft:#fff8e2;
    --blue:#3257a8;
    --chip:#eef2f8;
  }
  body{background:linear-gradient(180deg,#f7f9fc 0%,#eef3fa 100%);color:var(--ink);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
  .page{max-width:1380px;margin:10px auto;padding:0 10px 14px}
  .topbar{display:flex;justify-content:space-between;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:6px}
  .title-wrap h1{margin:0;font-size:18px;font-weight:900;letter-spacing:-.02em;color:var(--title-blue)}
  .title-wrap p{margin:0;color:var(--muted);font-size:11px}
  .top-actions{display:flex;gap:6px;flex-wrap:wrap}
  .btn-shell{display:inline-flex;align-items:center;gap:5px;padding:6px 9px;border-radius:9px;border:1px solid var(--line);background:var(--card);color:var(--ink);text-decoration:none;font-weight:700;font-size:11px;line-height:1.1;box-shadow:0 5px 14px rgba(17,24,39,.05)}
  .panel{background:rgba(255,255,255,.92);border:1px solid var(--line);border-radius:18px;padding:8px;box-shadow:0 10px 26px rgba(17,24,39,.08);backdrop-filter:blur(8px)}
  .summary-stack{display:grid;gap:5px;margin-bottom:6px}
  .summary-pill{background:#f2f4f8;border:1px dashed var(--line);border-radius:11px;padding:7px 10px;font-size:12px;font-weight:700;line-height:1.25}
  .summary-pill strong{color:#8b6a12;display:inline-block;min-width:150px}
  .section-title{margin:0 0 5px;color:var(--title-blue);font-weight:900;font-size:13px;letter-spacing:.01em}
  .general-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:6px}
  .ident-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:6px}
  .g-3{grid-column:span 3}.g-4{grid-column:span 4}.g-6{grid-column:span 6}.g-12{grid-column:span 12}
  .data-card{background:#f7f8fb;border:1px solid var(--line);border-radius:11px;padding:6px 9px;min-height:54px}
  .data-card.highlight{border-color:#dfb94d;background:linear-gradient(180deg,#fffdf7 0%,#fff7df 100%)}
  .data-card.centered{text-align:center;display:flex;flex-direction:column;justify-content:center}
  .data-card .label{font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:#8b6a12;margin-bottom:2px}
  .data-card .value{font-size:12px;line-height:1.18;font-weight:800;word-break:break-word}
  .data-card .value.status-pendiente{color:#c81e1e}
  .data-card .value.status-resuelto{color:#19734d}
  .data-card .value.status-diligencias{color:#9a6a00}
  .quick-status-select{width:100%;max-width:170px;margin:0 auto;border:1px solid #d5ddeb;border-radius:10px;background:#fff;padding:5px 9px;font-size:12px;font-weight:900;color:var(--ink);text-align:center;text-align-last:center}
  .quick-status-select:focus{outline:none;border-color:#d6b44c;box-shadow:0 0 0 3px rgba(214,180,76,.16)}
  .module-status-select{border:1px solid #d5ddeb;border-radius:999px;background:#fff;padding:5px 10px;font-size:11px;font-weight:900;line-height:1.1;color:var(--ink);min-width:126px;text-align:center;text-align-last:center}
  .module-status-select:focus{outline:none;border-color:#d6b44c;box-shadow:0 0 0 3px rgba(214,180,76,.14)}
  .module-status-select.status-borrador{border-color:#cbd5e1;background:#f8fafc;color:#475569}
  .module-status-select.status-firmado{border-color:#93c5fd;background:#eff6ff;color:#1d4ed8}
  .module-status-select.status-enviado{border-color:#86efac;background:#ecfdf5;color:#166534}
  .module-status-select.status-anulado{border-color:#fca5a5;background:#fff1f2;color:#b91c1c}
  .module-status-select.status-archivado{border-color:#d8b4fe;background:#faf5ff;color:#7c3aed}
  .line-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}
  .line-card{background:#f7f8fb;border:1px solid var(--line);border-radius:11px;padding:6px 9px;font-size:12px;font-weight:800;line-height:1.2}
  .line-card strong{color:#8b6a12}
  .tabs-shell{margin-top:10px}
  .tabs-toolbar{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:0 0 6px}
  .tabs-header{display:flex;gap:6px;overflow:auto;padding-bottom:6px}
  .tabs-header .nav-link{border:1px solid var(--line);background:#eef2f8;color:#3f4e68;border-radius:10px;padding:7px 9px;font-weight:800;font-size:12px;line-height:1.1;white-space:nowrap}
  .tabs-header .nav-link.active{background:linear-gradient(180deg,#fff5cf 0%,#ffe7a0 100%);border-color:#e7c75c;color:#6f5410}
  .tabs-header .nav-link.tab-driver{border-color:#44f7b2;background:linear-gradient(180deg,#f2fff9 0%,#ecfff7 100%);color:#0e7a5a;box-shadow:0 0 0 1px rgba(68,247,178,.08) inset}
  .tabs-header .nav-link.tab-driver.active{background:linear-gradient(180deg,#dcfff0 0%,#c4ffe6 100%);border-color:#22e39d;color:#0a7f57;box-shadow:0 0 0 1px rgba(34,227,157,.18) inset, 0 8px 18px rgba(34,227,157,.16)}
  .tabs-header .nav-link.tab-herido{border-color:#f2d15e;background:linear-gradient(180deg,#fffdf1 0%,#fff9df 100%);color:#9a7300;box-shadow:0 0 0 1px rgba(242,209,94,.08) inset}
  .tabs-header .nav-link.tab-herido.active{background:linear-gradient(180deg,#fff1b8 0%,#ffe89a 100%);border-color:#e0ba36;color:#8a6500;box-shadow:0 0 0 1px rgba(224,186,54,.16) inset, 0 8px 18px rgba(224,186,54,.16)}
  .tabs-header .nav-link.tab-occiso{border-color:#efb0b0;background:linear-gradient(180deg,#fff6f6 0%,#fff0f0 100%);color:#8f2121}
  .tabs-header .nav-link.tab-occiso.active{background:linear-gradient(180deg,#ffe3e3 0%,#ffcaca 100%);border-color:#df6a6a;color:#8f1111;box-shadow:0 0 0 1px rgba(223,106,106,.12) inset, 0 8px 18px rgba(185,28,28,.10)}
  .tabs-header .tab-sub{display:block;font-size:9px;font-weight:700;opacity:.75;margin-top:1px}
  .tab-panel{position:relative;overflow:hidden;background:rgba(255,255,255,.94);border:1px solid var(--line);border-radius:16px;padding:11px}
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
  .person-title h2{margin:0;font-size:17px;font-weight:900;line-height:1.15;color:var(--title-blue);letter-spacing:-.01em;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .person-title p{margin:3px 0 0;color:var(--muted);font-weight:700;font-size:12px}
  .person-name-copy{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
  .person-quick-actions{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
  .copy-name-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:30px;padding:0 10px;border:1px solid #cfd8e7;border-radius:999px;background:#fff;color:#40506d;font-size:12px;font-weight:900;line-height:1;box-shadow:0 6px 16px rgba(17,24,39,.06);transition:background .16s ease,border-color .16s ease,color .16s ease,transform .16s ease}
  .copy-name-btn:hover{background:#f6f9ff;border-color:#b8cae7;color:#234a84}
  .copy-name-btn.is-copied{background:#e8f7ef;border-color:#86d6a4;color:#166534}
  .quick-pill-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:30px;padding:0 10px;border:1px solid #cfd8e7;border-radius:999px;background:#fff;color:#40506d;font-size:12px;font-weight:900;line-height:1;text-decoration:none;box-shadow:0 6px 16px rgba(17,24,39,.06);transition:background .16s ease,border-color .16s ease,color .16s ease,transform .16s ease}
  .quick-pill-btn:hover{background:#f6f9ff;border-color:#b8cae7;color:#234a84}
  .quick-pill-btn.whatsapp{border-color:#9fe0b7;background:#f0fff5;color:#178248}
  .quick-pill-btn.whatsapp:hover{background:#25d366;color:#fff;border-color:#25d366}
  .quick-pill-btn.download{border-color:#d7c07b;background:#fff8df;color:#7f5a00}
  .quick-pill-btn.download:hover{background:#f0c654;color:#4d3600;border-color:#f0c654}
  .chip-row{display:flex;flex-wrap:wrap;gap:6px}
  .chip-role,.chip-status,.chip-simple{display:inline-flex;align-items:center;padding:3px 7px;border-radius:999px;border:1px solid var(--line);font-size:10px;font-weight:900;background:#fff;line-height:1.1}
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
  .section-block h3{margin:0 0 5px;font-size:12px;font-weight:900;color:var(--title-blue);letter-spacing:.01em}
  .field-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}
  .field-card{background:#f7f9fc;border:1px solid var(--line);border-radius:11px;padding:7px 9px}
  .field-card.span-2{grid-column:span 2}
  .field-label{font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:#8b6a12;margin-bottom:3px}
  .edit-label{display:block;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:var(--title-blue-soft);margin-bottom:3px}
  .field-value{font-size:12px;line-height:1.28;font-weight:800;word-break:break-word}
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
  .general-checkbox{display:flex;align-items:flex-start;gap:6px;padding:6px 8px;border:1px solid var(--line);border-radius:10px;background:#fff;font-size:11px;font-weight:800;line-height:1.25}
  .general-checkbox input{margin-top:2px}
  .general-help{font-size:10px;color:var(--muted);font-weight:700}
  .general-inline-note{font-size:10px;color:var(--muted);font-weight:700}
  .inline-edit-error{display:none;padding:8px 10px;border:1px solid #f1b3b3;background:#fff1f1;color:#aa2222;border-radius:10px;font-size:11px;font-weight:800}
  .inline-edit-error.is-visible{display:block}
  .edit-field{padding:6px 8px}
  .edit-control{width:100%;border:1px solid #d5ddeb;border-radius:9px;background:#fff;padding:7px 9px;font-size:12px;font-weight:700;color:var(--ink);line-height:1.25}
  .edit-control:focus{outline:none;border-color:#d6b44c;box-shadow:0 0 0 3px rgba(214,180,76,.16)}
  textarea.edit-control{min-height:76px;resize:vertical}
  .editable-form[hidden],.editable-view[hidden]{display:none}
  .module-grid{display:grid;gap:6px}
  .module-card{background:#f7f9fc;border:1px solid var(--line);border-radius:13px;padding:9px 11px}
  .module-card header{display:flex;justify-content:space-between;gap:6px;align-items:flex-start;flex-wrap:wrap;margin-bottom:4px}
  .module-card h4{margin:0;font-size:14px;font-weight:900;line-height:1.15;color:#8b6a12;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .module-card p{margin:0;color:var(--muted);font-weight:700;font-size:11px;line-height:1.25}
  .module-card-controls{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
  .module-meta{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
  .module-title-copy{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
  .module-toggle-btn{width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;border:1px solid #d7dfec;background:#fff;color:#8b6a12;font-size:18px;font-weight:900;line-height:1;box-shadow:0 6px 14px rgba(17,24,39,.05);transition:.18s ease}
  .module-toggle-btn:hover{border-color:#d6b44c;background:#fff8e7}
  .module-toggle-btn[aria-expanded="true"]{background:#d6b44c;border-color:#d6b44c;color:#fff}
  .module-card-panel{margin-top:8px}
  .module-card-panel[hidden]{display:none}
  .module-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
  .diligencia-card{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:start}
  .diligencia-main{display:grid;gap:6px;min-width:0}
  .diligencia-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:start}
  .diligencia-side{display:grid;gap:6px;justify-items:end;align-content:start}
  .diligencia-content-row{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
  .diligencia-content{flex:1 1 420px;margin:0;color:var(--muted);font-weight:800;font-size:11px;line-height:1.4}
  .diligencia-actions{display:flex;gap:6px;flex-wrap:wrap;align-items:center;justify-content:flex-end}
  .diligencia-status-select{min-width:130px;padding:6px 30px 6px 10px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);font-size:12px;font-weight:900;line-height:1.1;box-shadow:0 6px 16px rgba(17,24,39,.06)}
  .diligencia-status-select.status-pendiente{border-color:#f0b8b8;background:#fff3f3;color:#b42318}
  .diligencia-status-select.status-resuelto{border-color:#b9e2c5;background:#effcf3;color:#157347}
  .diligencia-inline-fields{display:grid;gap:6px}
  .diligencia-inline-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}
  .diligencia-inline-box{padding:7px 9px;border:1px solid var(--line);border-radius:10px;background:#fff}
  .diligencia-inline-box strong{display:block;margin:0 0 3px;color:#8b6a12;font-size:9px;line-height:1.15;text-transform:uppercase;letter-spacing:.05em}
  .diligencia-inline-box div{font-size:12px;font-weight:800;color:var(--ink);line-height:1.35}
  .empty-state{padding:18px 12px;text-align:center;color:var(--muted);font-weight:800;font-size:12px}
  .inner-tabs{display:flex;gap:5px;overflow:auto;padding-bottom:5px;margin:8px 0 6px}
  .inner-tabs .nav-link{border:1px solid var(--line);background:#f4f7fb;color:#47556d;border-radius:9px;padding:6px 8px;font-size:11px;font-weight:800;line-height:1.05;white-space:nowrap}
  .inner-tabs .nav-link.active{background:#fff7de;border-color:#e7c75c;color:#755811}
  .inner-tabs .tab-mini{display:block;font-size:9px;font-weight:700;opacity:.72;margin-top:1px}
  .inner-panel{border:1px solid var(--line);border-radius:13px;background:#fbfcfe;padding:8px}
  .record-stack{display:grid;gap:6px}
  .record-card{border:1px solid var(--line);border-radius:11px;background:#fff;padding:8px 9px}
  .record-card h5{margin:0 0 4px;font-size:12px;font-weight:900;line-height:1.2;color:#8b6a12}
  .record-card p{margin:0;color:var(--muted);font-size:11px;line-height:1.3;font-weight:700}
  .record-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
  .record-chipline{display:flex;gap:5px;flex-wrap:wrap;margin-top:5px}
  .inline-workbench{margin:0 0 8px;border:1px solid #d8e0ed;border-radius:13px;background:#f7f9fd;overflow:hidden}
  .inline-workbench[hidden]{display:none}
  .inline-head{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:7px 9px;border-bottom:1px solid var(--line);background:#eef3fb}
  .inline-head strong{font-size:11px;letter-spacing:.03em;text-transform:uppercase;color:#51627d}
  .inline-frame{width:100%;height:520px;border:0;background:#fff}
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
    .general-checkbox-grid{grid-template-columns:1fr}
    .field-card.span-2{grid-column:span 1}
    .editable-toolbar{align-items:flex-start}
    .person-title h2{font-size:16px}
    .tabs-header .nav-link{padding:6px 8px;font-size:11px}
    .tabs-header .tab-sub{font-size:9px}
    .inner-tabs .nav-link{padding:5px 7px;font-size:10px}
    .inline-frame{height:460px}
    .data-card{min-height:auto}
    .diligencia-content-row{flex-direction:column;align-items:stretch}
    .diligencia-actions{justify-content:flex-start}
    .diligencia-side{justify-items:start}
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
            <div class="data-card g-6">
              <div class="label">Comunicante</div>
              <div class="value"><?= fmt($A['comunicante_nombre'] ?? '') ?></div>
            </div>
            <div class="data-card g-6">
              <div class="label">Tel. comunicante</div>
              <div class="value"><?= fmt($A['comunicante_telefono'] ?? '') ?></div>
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
            <div class="general-edit-card g-6">
              <label for="acc-comunicante">Comunicante</label>
              <input class="edit-control" id="acc-comunicante" type="text" name="comunicante_nombre" maxlength="120" value="<?= h((string) ($accidenteBase['comunicante_nombre'] ?? '')) ?>">
            </div>
            <div class="general-edit-card g-6">
              <label for="acc-comunicante-tel">Tel. comunicante</label>
              <input class="edit-control" id="acc-comunicante-tel" type="text" name="comunicante_telefono" maxlength="20" value="<?= h((string) ($accidenteBase['comunicante_telefono'] ?? '')) ?>">
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
    <div class="tabs-toolbar">
      <a class="btn-shell" href="involucrados_personas_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo persona involucrada</a>
      <a class="btn-shell" href="involucrados_vehiculos_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo vehículo involucrado</a>
    </div>
    <div class="tabs-header nav nav-tabs flex-nowrap" id="accTabs" role="tablist">
      <?php $tabIndex = 0; ?>
      <?php foreach ($personas as $persona): ?>
        <?php $tabId = 'persona-' . (int) $persona['involucrado_id']; ?>
        <button class="nav-link <?= h(person_tab_tone_class($persona)) ?> <?= $tabIndex === 0 ? 'active' : '' ?>" id="<?= h($tabId) ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= h($tabId) ?>" type="button" role="tab">
          <?= h(tab_person_short_name($persona)) ?>
          <span class="tab-sub"><?= h(tab_person_label($persona)) ?></span>
        </button>
        <?php $tabIndex++; ?>
      <?php endforeach; ?>
      <?php
        $fixedTabs = [
            ['id' => 'efectivo-policial', 'label' => 'Efectivo policial', 'count' => count($policias)],
            ['id' => 'propietario-vehiculo', 'label' => 'Propietario vehículo', 'count' => count($propietarios)],
            ['id' => 'familiar-fallecido', 'label' => 'Familiar fallecido', 'count' => count($familiares)],
            ['id' => 'abogados', 'label' => 'Abogados', 'count' => count($abogados)],
            ['id' => 'documentos', 'label' => 'Documentos', 'count' => count($oficios) + count($documentosRecibidos)],
            ['id' => 'diligencias-pendientes', 'label' => 'Diligencias pendientes', 'count' => count($diligencias)],
        ];
      ?>
      <?php foreach ($fixedTabs as $fixed): ?>
        <button class="nav-link <?= $tabIndex === 0 ? 'active' : '' ?>" id="<?= h($fixed['id']) ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= h($fixed['id']) ?>" type="button" role="tab">
          <?= h($fixed['label']) ?>
          <span class="tab-sub"><?= h((string) $fixed['count']) ?> registro(s)</span>
        </button>
        <?php $tabIndex++; ?>
      <?php endforeach; ?>
    </div>

    <div class="tab-content mt-2">
      <?php $paneIndex = 0; ?>
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
        <div class="tab-pane fade <?= $paneIndex === 0 ? 'show active' : '' ?>" id="<?= h($tabId) ?>" role="tabpanel">
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
                      <?php if ($manifestDownloadUrl !== ''): ?>
                        <a class="quick-pill-btn download" href="<?= h($manifestDownloadUrl) ?>" aria-label="Descargar manifestacion" title="Descargar manifestacion">DOCX</a>
                      <?php endif; ?>
                    </span>
                  </span>
                  <?php if (person_heading_suffix($persona) !== ''): ?><span style="font-size:.68em;font-weight:800;color:#6b7a92;letter-spacing:0;"><?= h(person_heading_suffix($persona)) ?></span><?php endif; ?>
                </h2>
                <p><?= h(tab_person_label($persona)) ?><?php if (!empty($persona['orden_participacion'])): ?> · <?= h((string) $persona['orden_participacion']) ?><?php endif; ?></p>
              </div>
              <div class="chip-row">
                <?php if (!empty($persona['rol_nombre'])): ?><span class="<?= h(role_chip_class((string) $persona['rol_nombre'])) ?>"><?= h((string) $persona['rol_nombre']) ?></span><?php endif; ?>
                <?php if (!empty($persona['lesion'])): ?><span class="<?= h(lesion_chip_class((string) $persona['lesion'])) ?>"><?= h((string) $persona['lesion']) ?></span><?php endif; ?>
                <span class="chip-simple"><?= !empty($persona['vehiculo_id']) ? 'Con vehículo' : 'Sin vehículo' ?></span>
                <?php if (!empty($persona['veh_chip_text'])): ?><span class="chip-simple">Vehículo <?= h((string) $persona['veh_chip_text']) ?></span><?php endif; ?>
              </div>
            </div>

            <div class="action-row">
              <a class="btn-shell" href="persona_leer.php?id=<?= (int) $persona['persona_id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver persona</a>
              <a class="btn-shell" href="involucrados_personas_editar.php?id=<?= (int) $persona['involucrado_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Editar participación</a>
              <?php if ($hasComboVehiculos): ?>
                <?php foreach ($comboVehiculos as $comboVehiculo): ?>
                  <a class="btn-shell" href="vehiculo_leer.php?id=<?= (int) ($comboVehiculo['veh_id'] ?? 0) ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver vehículo <?= h((string) ($comboVehiculo['veh_numero'] ?? '')) ?></a>
                <?php endforeach; ?>
              <?php elseif ($isDriver && !empty($persona['veh_id'])): ?>
                <a class="btn-shell" href="vehiculo_leer.php?id=<?= (int) $persona['veh_id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver vehículo</a>
              <?php endif; ?>
            </div>

            <div class="inner-tabs nav nav-tabs flex-nowrap" id="<?= h($personPaneId) ?>" role="tablist">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-persona" type="button" role="tab">
                Persona
                <span class="tab-mini">Ficha principal</span>
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
                      <div class="record-actions" style="margin-top:0">
                        <a class="btn-shell" href="persona_leer.php?id=<?= (int) $persona['persona_id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver ficha completa</a>
                      </div>
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

                    <div class="section-block">
                      <h3>Participación en el accidente</h3>
                      <div class="field-grid"><?= render_field_cards($persona, $involucradoFields) ?></div>
                    </div>
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
                            <a class="btn-shell" href="vehiculo_leer.php?id=<?= (int) ($comboVehiculo['veh_id'] ?? 0) ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver ficha vehículo <?= h((string) ($comboVehiculo['veh_numero'] ?? '')) ?></a>
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
                          <?php if ($hasComboVehiculos): ?>
                            <?php foreach ($comboVehiculos as $comboVehiculo): ?>
                              <a class="btn-shell" href="vehiculo_leer.php?id=<?= (int) ($comboVehiculo['veh_id'] ?? 0) ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver ficha vehículo <?= h((string) ($comboVehiculo['veh_numero'] ?? '')) ?></a>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <a class="btn-shell" href="vehiculo_leer.php?id=<?= (int) $persona['veh_id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver ficha completa</a>
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
                      <a class="btn-shell js-inline-open" href="documento_manifestacion_nuevo.php?persona_id=<?= (int) $persona['persona_id'] ?>&rol_id=<?= (int) ($persona['rol_id'] ?? 0) ?>&accidente_id=<?= (int) $accidente_id ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="Manifestación">+ Nueva manifestación</a>
                    </div>
                    <?php if (!$extras['man']): ?>
                      <div class="empty-state">No hay manifestaciones registradas para esta persona en este accidente.</div>
                    <?php else: ?>
                      <div class="record-stack">
                        <?php foreach ($extras['man'] as $man): ?>
                          <article class="record-card">
                            <h5><?= h((string) (($man['modalidad'] ?? '') !== '' ? $man['modalidad'] : 'Sin modalidad')) ?> · <?= h(fecha_simple($man['fecha'] ?? null)) ?></h5>
                            <div class="record-chipline">
                              <span class="chip-simple"><?= h((string) (($man['horario_inicio'] ?? '') !== '' ? substr((string) $man['horario_inicio'], 0, 5) : '--:--')) ?>–<?= h((string) (($man['hora_termino'] ?? '') !== '' ? substr((string) $man['hora_termino'], 0, 5) : '--:--')) ?></span>
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
                    <div class="record-actions" style="margin-top:0">
                      <a class="btn-shell js-inline-open" href="documento_occiso_nuevo.php?persona_id=<?= (int) $persona['persona_id'] ?>&personaId=<?= (int) $persona['persona_id'] ?>&rol_id=<?= (int) ($persona['rol_id'] ?? 0) ?>&accidente_id=<?= (int) $accidente_id ?>&accidenteId=<?= (int) $accidente_id ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="Documento de occiso">+ Nuevo documento de occiso</a>
                    </div>
                    <?php if (!$extras['occ']): ?>
                      <div class="empty-state">No hay documentos de occiso para esta persona en este accidente.</div>
                    <?php else: ?>
                      <div class="record-stack">
                        <?php foreach ($extras['occ'] as $occ): ?>
                          <article class="record-card">
                            <h5><?= h((string) (($occ['lugar_levantamiento'] ?? '') !== '' ? $occ['lugar_levantamiento'] : 'Sin lugar')) ?></h5>
                            <div class="record-chipline">
                              <span class="chip-simple"><?= h(fecha_simple($occ['fecha_levantamiento'] ?? null)) ?> <?= h((string) (($occ['hora_levantamiento'] ?? '') !== '' ? substr((string) $occ['hora_levantamiento'], 0, 5) : '')) ?></span>
                              <span class="chip-simple">Prot. <?= h((string) (($occ['numero_protocolo'] ?? '') !== '' ? $occ['numero_protocolo'] : '—')) ?></span>
                            </div>
                            <?php if (!empty($occ['observaciones_levantamiento'])): ?><p><?= nl2br(h((string) $occ['observaciones_levantamiento'])) ?></p><?php endif; ?>
                            <div class="record-actions">
                              <a class="btn-shell" href="documento_occiso_ver.php?id=<?= (int) $occ['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                              <a class="btn-shell js-inline-open" href="documento_occiso_editar.php?id=<?= (int) $occ['id'] ?>&embed=1&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>" data-workbench="workbench-<?= (int) $persona['involucrado_id'] ?>" data-frame="workbench-frame-<?= (int) $persona['involucrado_id'] ?>" data-title="Documento de occiso">Editar</a>
                            </div>
                          </article>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php $paneIndex++; ?>
      <?php endforeach; ?>

      <div class="tab-pane fade <?= $paneIndex === 0 ? 'show active' : '' ?>" id="efectivo-policial" role="tabpanel">
        <div class="tab-panel">
          <div class="module-actions" style="margin-bottom:8px;">
            <a class="btn-shell" href="policial_interviniente_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo efectivo policial</a>
            <a class="btn-shell" href="policial_interviniente_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
          </div>
          <?php if (!$policias): ?>
            <div class="empty-state">No hay efectivos policiales registrados para este accidente.</div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($policias as $row): ?>
                <?php
                  $policiaWa = preg_replace('/\D+/', '', (string) ($row['celular'] ?? ''));
                  $policiaWhatsAppMsg = whatsapp_contact_message($modalidades, $A['fecha_accidente'] ?? null, $A['lugar'] ?? null);
                  $policiaManifestUrl = 'marcador_manifestacion_policia.php?policia_id=' . (int) $row['id'] . '&accidente_id=' . (int) $accidente_id . '&download=1';
                ?>
                <article class="module-card" data-collapsible-card>
                  <header>
                    <div>
                      <h4>
                        <span class="module-title-copy">
                          <span><?= h(person_label($row)) ?></span>
                          <button type="button" class="copy-name-btn js-copy-name" data-copy-text="<?= h(person_label($row)) ?>" aria-label="Copiar nombre" title="Copiar nombre">Copiar</button>
                          <span class="person-quick-actions">
                            <?php if ($policiaWa !== ''): ?>
                              <a class="quick-pill-btn whatsapp" href="https://wa.me/<?= h($policiaWa) ?>?text=<?= rawurlencode($policiaWhatsAppMsg) ?>" target="_blank" rel="noopener" aria-label="Abrir WhatsApp" title="Abrir WhatsApp">WA</a>
                            <?php endif; ?>
                            <a class="quick-pill-btn download" href="<?= h($policiaManifestUrl) ?>" aria-label="Descargar manifestacion" title="Descargar manifestacion">DOCX</a>
                          </span>
                        </span>
                      </h4>
                      <p><?= h(trim((string) (($row['grado_policial'] ?? '-') . ' · CIP ' . ($row['cip'] ?? '-')))) ?></p>
                    </div>
                    <div class="module-card-controls">
                      <span class="chip-simple">Registro #<?= (int) $row['id'] ?></span>
                      <button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button>
                    </div>
                  </header>
                  <div class="module-meta">
                    <span class="chip-simple"><?= h((string) (($row['tipo_doc'] ?? 'DOC') . ' ' . ($row['num_doc'] ?? '—'))) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['dependencia_policial'] ?? '') !== '' ? $row['dependencia_policial'] : 'Sin dependencia')) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['rol_funcion'] ?? '') !== '' ? $row['rol_funcion'] : 'Sin rol / función')) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['celular'] ?? '') !== '' ? $row['celular'] : 'Sin celular')) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['email'] ?? '') !== '' ? $row['email'] : 'Sin email')) ?></span>
                  </div>
                  <div class="editable-shell" data-edit-shell="policia-<?= (int) $row['id'] ?>">
                    <div class="editable-toolbar">
                      <div class="record-actions" style="margin-top:0">
                        <a class="btn-shell" href="policial_interviniente_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                      </div>
                      <div class="editable-actions">
                        <button type="button" class="btn-shell js-edit-start" data-shell="policia-<?= (int) $row['id'] ?>">Editar</button>
                        <div class="editable-actions" data-edit-actions="policia-<?= (int) $row['id'] ?>" hidden>
                          <button type="button" class="btn-shell js-edit-cancel" data-shell="policia-<?= (int) $row['id'] ?>">Cancelar</button>
                          <button type="submit" class="btn-shell btn-primary" form="policia-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                        </div>
                      </div>
                    </div>

                    <div class="module-card-panel js-card-panel" hidden>
                      <?php if (!empty($row['observaciones'])): ?><p style="margin-top:10px;"><?= nl2br(h((string) $row['observaciones'])) ?></p><?php endif; ?>
                      <div class="inline-edit-error" id="policia-inline-error-<?= (int) $row['id'] ?>"></div>

                      <div class="editable-view" data-edit-view="policia-<?= (int) $row['id'] ?>">
                        <?php foreach ($policiaPersonaSections as $sectionTitle => $sectionFields): ?>
                          <div class="section-block">
                            <h3><?= h($sectionTitle) ?></h3>
                            <div class="field-grid"><?= render_field_cards($row, $sectionFields) ?></div>
                          </div>
                        <?php endforeach; ?>
                        <div class="section-block">
                          <h3>Registro policial</h3>
                          <div class="field-grid"><?= render_field_cards($row, ['grado_policial', 'cip', ['key' => 'dependencia_policial', 'class' => 'span-2'], 'rol_funcion', ['key' => 'observaciones', 'class' => 'span-2']]) ?></div>
                        </div>
                      </div>

                      <form class="editable-form js-inline-ajax-form js-persona-inline-form" id="policia-inline-form-<?= (int) $row['id'] ?>" data-shell="policia-<?= (int) $row['id'] ?>" data-error="policia-inline-error-<?= (int) $row['id'] ?>" method="post" hidden>
                        <input type="hidden" name="action" value="save_policial_inline">
                        <input type="hidden" name="policial_id" value="<?= (int) $row['id'] ?>">
                        <input type="hidden" name="persona_id" value="<?= (int) ($row['persona_id'] ?? 0) ?>">
                        <input type="hidden" name="foto_path" value="<?= h((string) ($row['foto_path'] ?? '')) ?>">
                        <input type="hidden" name="api_fuente" value="<?= h((string) ($row['api_fuente'] ?? '')) ?>">
                        <input type="hidden" name="api_ref" value="<?= h((string) ($row['api_ref'] ?? '')) ?>">

                        <?php foreach ($personaEditSections as $sectionTitle => $sectionFields): ?>
                          <div class="section-block">
                            <h3><?= h($sectionTitle) ?></h3>
                            <div class="field-grid"><?= render_editable_fields($row, $sectionFields, 'policia-' . (int) $row['id']) ?></div>
                          </div>
                        <?php endforeach; ?>
                        <div class="section-block">
                          <h3>Registro policial</h3>
                          <div class="field-grid"><?= render_editable_fields($row, $policialRecordEditFields, 'policia-reg-' . (int) $row['id']) ?></div>
                        </div>
                      </form>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php $paneIndex++; ?>

      <div class="tab-pane fade <?= $paneIndex === 0 ? 'show active' : '' ?>" id="propietario-vehiculo" role="tabpanel">
        <div class="tab-panel">
          <div class="module-actions" style="margin-bottom:8px;">
            <a class="btn-shell" href="propietario_vehiculo_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo propietario</a>
            <a class="btn-shell" href="propietario_vehiculo_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
          </div>
          <?php if (!$propietarios): ?>
            <div class="empty-state">No hay propietarios de vehículo registrados para este accidente.</div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($propietarios as $row): ?>
                <?php
                  $ownerRecord = project_prefixed_record($row, 'owner_');
                  $repRecord = project_prefixed_record($row, 'rep_');
                  $principal = (string) ($row['tipo_propietario'] ?? '') === 'NATURAL'
                    ? trim((string) (($ownerRecord['nombres'] ?? '') . ' ' . ($ownerRecord['apellido_paterno'] ?? '') . ' ' . ($ownerRecord['apellido_materno'] ?? '')))
                    : (string) ($row['razon_social'] ?? 'Sin razón social');
                  $principalDoc = (string) ($row['tipo_propietario'] ?? '') === 'NATURAL'
                    ? trim((string) (((string) ($ownerRecord['tipo_doc'] ?? '') !== '' ? person_doc_label((string) ($ownerRecord['tipo_doc'] ?? '')) . ' ' : '') . ($ownerRecord['num_doc'] ?? '')))
                    : trim((string) ('RUC ' . ($row['ruc'] ?? '')));
                  $representante = trim((string) (($repRecord['nombres'] ?? '') . ' ' . ($repRecord['apellido_paterno'] ?? '') . ' ' . ($repRecord['apellido_materno'] ?? '')));
                  $propietarioCelular = (string) (($row['tipo_propietario'] ?? '') === 'JURIDICA'
                    ? ($repRecord['celular'] ?? '')
                    : ($ownerRecord['celular'] ?? ''));
                  $propietarioWa = preg_replace('/\D+/', '', $propietarioCelular);
                  $propietarioWhatsAppMsg = whatsapp_contact_message($modalidades, $A['fecha_accidente'] ?? null, $A['lugar'] ?? null);
                  $propietarioManifestUrl = 'marcador_manifestacion_propietario.php?propietario_id=' . (int) $row['id'] . '&accidente_id=' . (int) $accidente_id . '&download=1';
                ?>
                <article class="module-card" data-collapsible-card>
                  <header>
                    <div>
                      <h4>
                        <span class="module-title-copy">
                          <span><?= h($principal !== '' ? $principal : 'Sin propietario') ?></span>
                          <?php if ($principal !== ''): ?><button type="button" class="copy-name-btn js-copy-name" data-copy-text="<?= h($principal) ?>" aria-label="Copiar nombre" title="Copiar nombre">Copiar</button><?php endif; ?>
                          <?php if ($propietarioWa !== ''): ?>
                            <span class="person-quick-actions">
                              <a class="quick-pill-btn whatsapp" href="https://wa.me/<?= h($propietarioWa) ?>?text=<?= rawurlencode($propietarioWhatsAppMsg) ?>" target="_blank" rel="noopener" aria-label="Abrir WhatsApp" title="Abrir WhatsApp">WA</a>
                              <a class="quick-pill-btn download" href="<?= h($propietarioManifestUrl) ?>" aria-label="Descargar manifestacion" title="Descargar manifestacion">DOCX</a>
                            </span>
                          <?php else: ?>
                            <span class="person-quick-actions">
                              <a class="quick-pill-btn download" href="<?= h($propietarioManifestUrl) ?>" aria-label="Descargar manifestacion" title="Descargar manifestacion">DOCX</a>
                            </span>
                          <?php endif; ?>
                        </span>
                      </h4>
                      <p><?= h(trim((string) (($row['orden_participacion'] ?? '') . ' · Placa ' . ($row['placa'] ?? 'SIN PLACA')))) ?></p>
                    </div>
                    <div class="module-card-controls">
                      <span class="chip-simple"><?= h((string) ($row['tipo_propietario'] ?? '')) ?></span>
                      <button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button>
                    </div>
                  </header>
                  <div class="module-meta">
                    <span class="chip-simple"><?= h($principalDoc !== '' ? $principalDoc : 'Sin documento') ?></span>
                    <span class="chip-simple"><?= h((string) (($row['rol_legal'] ?? '') !== '' ? $row['rol_legal'] : 'Sin rol legal')) ?></span>
                    <?php if ($representante !== ''): ?><span class="chip-simple">Representante: <?= h($representante) ?></span><?php endif; ?>
                  </div>
                  <div class="editable-shell" data-edit-shell="propietario-<?= (int) $row['id'] ?>">
                    <div class="editable-toolbar">
                      <div class="record-actions" style="margin-top:0">
                        <a class="btn-shell" href="propietario_vehiculo_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                      </div>
                      <div class="editable-actions">
                        <button type="button" class="btn-shell js-edit-start" data-shell="propietario-<?= (int) $row['id'] ?>">Editar</button>
                        <div class="editable-actions" data-edit-actions="propietario-<?= (int) $row['id'] ?>" hidden>
                          <button type="button" class="btn-shell js-edit-cancel" data-shell="propietario-<?= (int) $row['id'] ?>">Cancelar</button>
                          <button type="submit" class="btn-shell btn-primary" form="propietario-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                        </div>
                      </div>
                    </div>

                    <div class="module-card-panel js-card-panel" hidden>
                      <?php if (!empty($row['domicilio_fiscal'])): ?><p style="margin-top:10px;">Domicilio fiscal: <?= nl2br(h((string) $row['domicilio_fiscal'])) ?></p><?php endif; ?>
                      <?php if (!empty($row['observaciones'])): ?><p style="margin-top:10px;"><?= nl2br(h((string) $row['observaciones'])) ?></p><?php endif; ?>
                    <div class="inline-edit-error" id="propietario-inline-error-<?= (int) $row['id'] ?>"></div>

                    <div class="editable-view" data-edit-view="propietario-<?= (int) $row['id'] ?>">
                      <?php if ((string) ($row['tipo_propietario'] ?? '') === 'NATURAL' && trim((string) ($ownerRecord['nombres'] ?? '')) !== ''): ?>
                        <?php foreach ($policiaPersonaSections as $sectionTitle => $sectionFields): ?>
                          <div class="section-block">
                            <h3><?= h('Propietario · ' . $sectionTitle) ?></h3>
                            <div class="field-grid"><?= render_field_cards($ownerRecord, $sectionFields) ?></div>
                          </div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                      <?php if ($representante !== ''): ?>
                        <?php foreach ($policiaPersonaSections as $sectionTitle => $sectionFields): ?>
                          <div class="section-block">
                            <h3><?= h('Representante · ' . $sectionTitle) ?></h3>
                            <div class="field-grid"><?= render_field_cards($repRecord, $sectionFields) ?></div>
                          </div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                      <div class="section-block">
                        <h3>Registro propietario</h3>
                        <div class="field-grid"><?= render_field_cards($row, ['tipo_propietario', 'rol_legal', ['key' => 'ruc', 'class' => 'span-2'], ['key' => 'razon_social', 'class' => 'span-2'], ['key' => 'domicilio_fiscal', 'class' => 'span-2'], ['key' => 'observaciones', 'class' => 'span-2']]) ?></div>
                      </div>
                    </div>

                    <form class="editable-form js-inline-ajax-form js-persona-inline-form" id="propietario-inline-form-<?= (int) $row['id'] ?>" data-shell="propietario-<?= (int) $row['id'] ?>" data-error="propietario-inline-error-<?= (int) $row['id'] ?>" method="post" hidden>
                      <input type="hidden" name="action" value="save_propietario_inline">
                      <input type="hidden" name="propietario_id" value="<?= (int) $row['id'] ?>">
                      <?php if ((string) ($row['tipo_propietario'] ?? '') === 'JURIDICA'): ?>
                        <input type="hidden" name="foto_path" value="<?= h((string) ($repRecord['foto_path'] ?? '')) ?>">
                        <input type="hidden" name="api_fuente" value="<?= h((string) ($repRecord['api_fuente'] ?? '')) ?>">
                        <input type="hidden" name="api_ref" value="<?= h((string) ($repRecord['api_ref'] ?? '')) ?>">
                        <div class="section-block">
                          <h3>Empresa</h3>
                          <div class="field-grid"><?= render_editable_fields($row, $propietarioJuridicaEditFields, 'prop-jur-' . (int) $row['id']) ?></div>
                        </div>
                        <?php foreach ($personaEditSections as $sectionTitle => $sectionFields): ?>
                          <div class="section-block">
                            <h3><?= h('Representante · ' . $sectionTitle) ?></h3>
                            <div class="field-grid"><?= render_editable_fields($repRecord, $sectionFields, 'prop-rep-' . (int) $row['id']) ?></div>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <input type="hidden" name="foto_path" value="<?= h((string) ($ownerRecord['foto_path'] ?? '')) ?>">
                        <input type="hidden" name="api_fuente" value="<?= h((string) ($ownerRecord['api_fuente'] ?? '')) ?>">
                        <input type="hidden" name="api_ref" value="<?= h((string) ($ownerRecord['api_ref'] ?? '')) ?>">
                        <?php foreach ($personaEditSections as $sectionTitle => $sectionFields): ?>
                          <div class="section-block">
                            <h3><?= h('Propietario · ' . $sectionTitle) ?></h3>
                            <div class="field-grid"><?= render_editable_fields($ownerRecord, $sectionFields, 'prop-nat-' . (int) $row['id']) ?></div>
                          </div>
                        <?php endforeach; ?>
                        <div class="section-block">
                          <h3>Registro propietario</h3>
                          <div class="field-grid"><?= render_editable_fields($row, $propietarioNaturalEditFields, 'prop-reg-' . (int) $row['id']) ?></div>
                        </div>
                      <?php endif; ?>
                    </form>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php $paneIndex++; ?>

      <div class="tab-pane fade <?= $paneIndex === 0 ? 'show active' : '' ?>" id="familiar-fallecido" role="tabpanel">
        <div class="tab-panel">
          <div class="module-actions" style="margin-bottom:8px;">
            <a class="btn-shell" href="familiar_fallecido_nuevo.php?accidente_id=<?= (int) $accidente_id ?>">Nuevo familiar</a>
            <a class="btn-shell" href="familiar_fallecido_listar.php?accidente_id=<?= (int) $accidente_id ?>">Ver listado completo</a>
          </div>
          <?php if (!$familiares): ?>
            <div class="empty-state">No hay familiares de fallecidos registrados para este accidente.</div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($familiares as $row): ?>
                <?php
                  $famRecord = project_prefixed_record($row, 'fam_');
                  $fallRecord = project_prefixed_record($row, 'fall_');
                  $nombreFamiliar = trim((string) (($famRecord['nombres'] ?? '') . ' ' . ($famRecord['apellido_paterno'] ?? '') . ' ' . ($famRecord['apellido_materno'] ?? '')));
                  $nombreFallecido = trim((string) (($fallRecord['nombres'] ?? '') . ' ' . ($fallRecord['apellido_paterno'] ?? '') . ' ' . ($fallRecord['apellido_materno'] ?? '')));
                  $familiarWa = preg_replace('/\D+/', '', (string) ($famRecord['celular'] ?? ''));
                  $familiarWhatsAppMsg = whatsapp_contact_message($modalidades, $A['fecha_accidente'] ?? null, $A['lugar'] ?? null);
                  $familiarManifestUrl = 'marcador_manifestacion_familiar.php?fam_id=' . (int) $row['id'];
                ?>
                <article class="module-card" data-collapsible-card>
                  <header>
                    <div>
                      <h4>
                        <span class="module-title-copy">
                          <span><?= h($nombreFamiliar !== '' ? $nombreFamiliar : 'Sin familiar asociado') ?></span>
                          <?php if ($nombreFamiliar !== ''): ?><button type="button" class="copy-name-btn js-copy-name" data-copy-text="<?= h($nombreFamiliar) ?>" aria-label="Copiar nombre" title="Copiar nombre">Copiar</button><?php endif; ?>
                          <span class="person-quick-actions">
                            <?php if ($familiarWa !== ''): ?>
                              <a class="quick-pill-btn whatsapp" href="https://wa.me/<?= h($familiarWa) ?>?text=<?= rawurlencode($familiarWhatsAppMsg) ?>" target="_blank" rel="noopener" aria-label="Abrir WhatsApp" title="Abrir WhatsApp">WA</a>
                            <?php endif; ?>
                            <a class="quick-pill-btn download" href="<?= h($familiarManifestUrl) ?>" aria-label="Descargar manifestacion" title="Descargar manifestacion">DOCX</a>
                          </span>
                        </span>
                      </h4>
                      <p>Familiar de <?= h($nombreFallecido !== '' ? $nombreFallecido : 'Sin fallecido asociado') ?></p>
                    </div>
                    <div class="module-card-controls">
                      <span class="chip-simple"><?= h((string) (($row['parentesco'] ?? '') !== '' ? $row['parentesco'] : 'Sin parentesco')) ?></span>
                      <button type="button" class="module-toggle-btn js-card-toggle" aria-expanded="false" aria-label="Mostrar detalle" title="Mostrar detalle">+</button>
                    </div>
                  </header>
                  <div class="module-meta">
                    <span class="chip-simple"><?= h(trim((string) (((string) ($famRecord['tipo_doc'] ?? '') !== '' ? person_doc_label((string) ($famRecord['tipo_doc'] ?? '')) . ' ' : '') . ($famRecord['num_doc'] ?? '')))) ?></span>
                    <span class="chip-simple"><?= h((string) (($famRecord['celular'] ?? '') !== '' ? $famRecord['celular'] : 'Sin celular')) ?></span>
                    <span class="chip-simple"><?= h((string) (($famRecord['email'] ?? '') !== '' ? $famRecord['email'] : 'Sin email')) ?></span>
                    <span class="chip-simple">Fallecido: <?= h(trim((string) (((string) ($fallRecord['tipo_doc'] ?? '') !== '' ? person_doc_label((string) ($fallRecord['tipo_doc'] ?? '')) . ' ' : '') . ($fallRecord['num_doc'] ?? '')))) ?></span>
                  </div>
                  <?php if ($nombreFamiliar !== ''): ?>
                    <div class="editable-shell" data-edit-shell="familiar-persona-<?= (int) $row['id'] ?>">
                      <div class="editable-toolbar">
                        <div class="record-actions" style="margin-top:0">
                          <a class="btn-shell" href="familiar_fallecido_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                        </div>
                        <div class="editable-actions">
                          <button type="button" class="btn-shell js-edit-start" data-shell="familiar-persona-<?= (int) $row['id'] ?>">Editar</button>
                          <div class="editable-actions" data-edit-actions="familiar-persona-<?= (int) $row['id'] ?>" hidden>
                            <button type="button" class="btn-shell js-edit-cancel" data-shell="familiar-persona-<?= (int) $row['id'] ?>">Cancelar</button>
                            <button type="submit" class="btn-shell btn-primary" form="familiar-inline-form-<?= (int) $row['id'] ?>">Guardar</button>
                          </div>
                        </div>
                      </div>

                      <div class="module-card-panel js-card-panel" hidden>
                      <?php if (!empty($row['observaciones'])): ?><p style="margin-top:10px;"><?= nl2br(h((string) $row['observaciones'])) ?></p><?php endif; ?>
                      <div class="inline-edit-error" id="familiar-inline-error-<?= (int) $row['id'] ?>"></div>

                      <div class="editable-view" data-edit-view="familiar-persona-<?= (int) $row['id'] ?>">
                        <?php foreach ($policiaPersonaSections as $sectionTitle => $sectionFields): ?>
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

                        <?php foreach ($personaEditSections as $sectionTitle => $sectionFields): ?>
                          <div class="section-block">
                            <h3><?= h('Familiar · ' . $sectionTitle) ?></h3>
                            <div class="field-grid"><?= render_editable_fields($famRecord, $sectionFields, 'familiar-' . (int) $row['id']) ?></div>
                          </div>
                        <?php endforeach; ?>
                      </form>
                      </div>
                    </div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php $paneIndex++; ?>

      <div class="tab-pane fade <?= $paneIndex === 0 ? 'show active' : '' ?>" id="abogados" role="tabpanel">
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
      <?php $paneIndex++; ?>

      <div class="tab-pane fade <?= $paneIndex === 0 ? 'show active' : '' ?>" id="documentos" role="tabpanel">
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
                        $docEstado = trim((string) ($row['estado'] ?? ''));
                        $docEstadoClass = mb_strtolower($docEstado, 'UTF-8') === 'revisado'
                          ? 'chip-status-ok'
                          : (mb_strtolower($docEstado, 'UTF-8') === 'archivado' ? 'chip-testigo' : 'chip-status-warn');
                      ?>
                      <article class="module-card">
                        <header>
                          <div>
                            <h4><?= h((string) (($row['asunto'] ?? '') !== '' ? $row['asunto'] : 'Documento recibido #' . (int) $row['id'])) ?></h4>
                            <p><?= h((string) (($row['entidad_persona'] ?? '') !== '' ? $row['entidad_persona'] : 'Sin entidad / persona')) ?></p>
                          </div>
                          <span class="chip-simple <?= h($docEstadoClass) ?>"><?= h($docEstado !== '' ? $docEstado : 'Sin estado') ?></span>
                        </header>
                        <div class="module-meta">
                          <span class="chip-simple"><?= h((string) (($row['tipo_documento'] ?? '') !== '' ? $row['tipo_documento'] : 'Sin tipo')) ?></span>
                          <span class="chip-simple">N° <?= h((string) (($row['numero_documento'] ?? '') !== '' ? $row['numero_documento'] : '—')) ?></span>
                          <span class="chip-simple">Fecha: <?= h(fecha_simple($row['fecha'] ?? null)) ?></span>
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
      <?php $paneIndex++; ?>

      <div class="tab-pane fade <?= $paneIndex === 0 ? 'show active' : '' ?>" id="diligencias-pendientes" role="tabpanel">
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
    </div>
  </div>
</div>

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

    document.querySelectorAll('#accTabs, .inner-tabs').forEach((nav) => {
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
        button.textContent = expanded ? '−' : '+';
        button.title = expanded ? 'Ocultar detalle' : 'Mostrar detalle';
        button.setAttribute('aria-label', expanded ? 'Ocultar detalle' : 'Mostrar detalle');
      });
    }

    function resetCollapsibleCards(scope) {
      (scope || document).querySelectorAll('[data-collapsible-card]').forEach((card) => {
        setCollapsibleCardState(card, false);
      });
    }

    document.querySelectorAll('.js-card-toggle').forEach((button) => {
      button.addEventListener('click', () => {
        const card = button.closest('[data-collapsible-card]');
        if (!card) return;

        const expanded = button.getAttribute('aria-expanded') === 'true';
        setCollapsibleCardState(card, !expanded);
      });
    });

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
