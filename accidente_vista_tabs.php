<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\PersonaRepository;
use App\Repositories\VehiculoRepository;
use App\Services\PersonaService;
use App\Services\VehiculoService;

header('Content-Type: text/html; charset=utf-8');

if (!isset($pdo) && isset($db) && $db instanceof PDO) {
    $pdo = $db;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

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
        (string) ($row['apellido_paterno'] ?? '') . ' '
        . (string) ($row['apellido_materno'] ?? '') . ' '
        . (string) ($row['nombres'] ?? '')
    );
    return preg_replace('/\s+/u', ' ', $name) ?: '';
}

function person_label(array $row): string
{
    $name = full_name($row);
    return $name !== '' ? $name : ('Persona #' . (int) ($row['persona_id'] ?? 0));
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
        'inv_vehiculo_observaciones' => 'Obs. vehículo involucrado',
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
        $html .= '<label class="field-label" for="' . h($inputId) . '">' . h($label) . '</label>';

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

$policias = safe_query_all(
    $pdo,
    "SELECT pi.*,
            p.tipo_doc,
            p.num_doc,
            p.apellido_paterno,
            p.apellido_materno,
            p.nombres,
            p.celular,
            p.email
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
            pn.tipo_doc AS tipo_doc_nat,
            pn.num_doc AS dni_nat,
            pn.apellido_paterno AS ap_nat,
            pn.apellido_materno AS am_nat,
            pn.nombres AS no_nat,
            pr.tipo_doc AS tipo_doc_rep,
            pr.num_doc AS dni_rep,
            pr.apellido_paterno AS ap_rep,
            pr.apellido_materno AS am_rep,
            pr.nombres AS no_rep
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
            pf.num_doc AS dni_fall,
            pf.apellido_paterno AS ap_fall,
            pf.apellido_materno AS am_fall,
            pf.nombres AS no_fall,
            pr.tipo_doc AS tipo_doc_fam,
            pr.num_doc AS dni_fam,
            pr.apellido_paterno AS ap_fam,
            pr.apellido_materno AS am_fam,
            pr.nombres AS no_fam,
            pr.celular AS cel_fam,
            pr.email AS em_fam
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
    --gold:#9a7a1b;
    --gold-soft:#fff8e2;
    --blue:#3257a8;
    --chip:#eef2f8;
  }
  body{background:linear-gradient(180deg,#f7f9fc 0%,#eef3fa 100%);color:var(--ink);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
  .page{max-width:1380px;margin:12px auto;padding:0 10px 16px}
  .topbar{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px}
  .title-wrap h1{margin:0;font-size:20px;font-weight:900;letter-spacing:-.02em}
  .title-wrap p{margin:1px 0 0;color:var(--muted);font-size:12px}
  .top-actions{display:flex;gap:8px;flex-wrap:wrap}
  .btn-shell{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:9px;border:1px solid var(--line);background:var(--card);color:var(--ink);text-decoration:none;font-weight:700;font-size:12px;line-height:1.15;box-shadow:0 6px 18px rgba(17,24,39,.05)}
  .panel{background:rgba(255,255,255,.92);border:1px solid var(--line);border-radius:18px;padding:10px;box-shadow:0 10px 26px rgba(17,24,39,.08);backdrop-filter:blur(8px)}
  .summary-stack{display:grid;gap:6px;margin-bottom:8px}
  .summary-pill{background:#f2f4f8;border:1px dashed var(--line);border-radius:12px;padding:9px 11px;font-size:13px;font-weight:700;line-height:1.3}
  .summary-pill strong{color:#5d6c83;display:inline-block;min-width:150px}
  .section-title{margin:0 0 6px;color:#607089;font-weight:900;font-size:14px}
  .general-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:6px}
  .g-3{grid-column:span 3}.g-4{grid-column:span 4}.g-6{grid-column:span 6}.g-12{grid-column:span 12}
  .data-card{background:#f7f8fb;border:1px solid var(--line);border-radius:12px;padding:8px 10px;min-height:64px}
  .data-card.highlight{border-color:#dfb94d;background:linear-gradient(180deg,#fffdf7 0%,#fff7df 100%)}
  .data-card .label{font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;color:var(--gold);margin-bottom:3px}
  .data-card .value{font-size:13px;line-height:1.2;font-weight:800;word-break:break-word}
  .data-card .value.status-pendiente{color:#c81e1e}
  .data-card .value.status-resuelto{color:#19734d}
  .line-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}
  .line-card{background:#f7f8fb;border:1px solid var(--line);border-radius:12px;padding:8px 10px;font-size:13px;font-weight:800;line-height:1.25}
  .line-card strong{color:var(--gold)}
  .tabs-shell{margin-top:10px}
  .tabs-header{display:flex;gap:6px;overflow:auto;padding-bottom:6px}
  .tabs-header .nav-link{border:1px solid var(--line);background:#eef2f8;color:#3f4e68;border-radius:10px;padding:7px 9px;font-weight:800;font-size:12px;line-height:1.1;white-space:nowrap}
  .tabs-header .nav-link.active{background:linear-gradient(180deg,#fff5cf 0%,#ffe7a0 100%);border-color:#e7c75c;color:#6f5410}
  .tabs-header .tab-sub{display:block;font-size:9px;font-weight:700;opacity:.75;margin-top:1px}
  .tab-panel{background:rgba(255,255,255,.94);border:1px solid var(--line);border-radius:16px;padding:11px}
  .person-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap;margin-bottom:8px}
  .person-title h2{margin:0;font-size:17px;font-weight:900;line-height:1.15}
  .person-title p{margin:3px 0 0;color:var(--muted);font-weight:700;font-size:12px}
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
  .section-block{margin-top:10px}
  .section-block h3{margin:0 0 6px;font-size:13px;font-weight:900;color:#52627a}
  .field-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}
  .field-card{background:#f7f9fc;border:1px solid var(--line);border-radius:11px;padding:7px 9px}
  .field-card.span-2{grid-column:span 2}
  .field-label{font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;color:#6d7e96;margin-bottom:3px}
  .field-value{font-size:12px;line-height:1.28;font-weight:800;word-break:break-word}
  .editable-shell{display:grid;gap:8px}
  .editable-toolbar{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
  .editable-actions{display:flex;gap:6px;flex-wrap:wrap}
  .editable-actions[hidden]{display:none}
  .btn-shell.btn-primary{background:linear-gradient(180deg,#fff1bc 0%,#ffd86e 100%);border-color:#e2ba47;color:#5f4700}
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
  .module-card h4{margin:0;font-size:14px;font-weight:900;line-height:1.15}
  .module-card p{margin:0;color:var(--muted);font-weight:700;font-size:11px;line-height:1.25}
  .module-meta{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
  .module-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
  .empty-state{padding:18px 12px;text-align:center;color:var(--muted);font-weight:800;font-size:12px}
  .inner-tabs{display:flex;gap:5px;overflow:auto;padding-bottom:5px;margin:8px 0 6px}
  .inner-tabs .nav-link{border:1px solid var(--line);background:#f4f7fb;color:#47556d;border-radius:9px;padding:6px 8px;font-size:11px;font-weight:800;line-height:1.05;white-space:nowrap}
  .inner-tabs .nav-link.active{background:#fff7de;border-color:#e7c75c;color:#755811}
  .inner-tabs .tab-mini{display:block;font-size:9px;font-weight:700;opacity:.72;margin-top:1px}
  .inner-panel{border:1px solid var(--line);border-radius:13px;background:#fbfcfe;padding:8px}
  .record-stack{display:grid;gap:6px}
  .record-card{border:1px solid var(--line);border-radius:11px;background:#fff;padding:8px 9px}
  .record-card h5{margin:0 0 4px;font-size:12px;font-weight:900;line-height:1.2}
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
    .line-grid{grid-template-columns:1fr}
    .field-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .general-grid{gap:5px}
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
    .field-grid{grid-template-columns:1fr}
    .field-card.span-2{grid-column:span 1}
    .editable-toolbar{align-items:flex-start}
    .person-title h2{font-size:16px}
    .tabs-header .nav-link{padding:6px 8px;font-size:11px}
    .tabs-header .tab-sub{font-size:9px}
    .inner-tabs .nav-link{padding:5px 7px;font-size:10px}
    .inline-frame{height:460px}
    .data-card{min-height:auto}
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
    <div class="summary-stack">
      <div class="summary-pill"><strong>Modalidades:</strong> <?= $modsConcat ?></div>
      <div class="summary-pill"><strong>Consecuencias:</strong> <?= $consConcat ?></div>
    </div>

    <div class="section-block">
      <h2 class="section-title">Identificación</h2>
      <div class="general-grid">
        <div class="data-card highlight g-3">
          <div class="label">Registro SIDPOL</div>
          <div class="value"><?= fmt($A['registro_sidpol'] ?? '') ?></div>
        </div>
        <?php $estadoClass = mb_strtolower(trim((string) ($A['estado'] ?? '')), 'UTF-8') === 'pendiente' ? 'status-pendiente' : (mb_strtolower(trim((string) ($A['estado'] ?? '')), 'UTF-8') === 'resuelto' ? 'status-resuelto' : ''); ?>
        <div class="data-card g-3">
          <div class="label">Estado</div>
          <div class="value <?= h($estadoClass) ?>"><?= fmt($A['estado'] ?? '') ?></div>
        </div>
        <div class="data-card g-3">
          <div class="label">N° informe policial</div>
          <div class="value"><?= fmt($A['nro_informe_policial'] ?? '') ?></div>
        </div>
        <div class="data-card g-3">
          <div class="label">Comisaría</div>
          <div class="value"><?= fmt($A['comisaria_nom'] ?? '') ?></div>
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

  <div class="tabs-shell">
    <div class="tabs-header nav nav-tabs flex-nowrap" id="accTabs" role="tablist">
      <?php $tabIndex = 0; ?>
      <?php foreach ($personas as $persona): ?>
        <?php $tabId = 'persona-' . (int) $persona['involucrado_id']; ?>
        <button class="nav-link <?= $tabIndex === 0 ? 'active' : '' ?>" id="<?= h($tabId) ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= h($tabId) ?>" type="button" role="tab">
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
            ['id' => 'oficios', 'label' => 'Oficios', 'count' => count($oficios)],
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
          $extras = $personaExtras[(int) $persona['involucrado_id']] ?? ['lc'=>[],'rml'=>[],'dos'=>[],'man'=>[],'occ'=>[],'show_lc'=>false,'show_rml'=>false,'show_dos'=>false,'show_man'=>false,'show_occ'=>false];
          $wa = preg_replace('/\D+/', '', (string) ($persona['celular'] ?? ''));
          $whatsAppMsg = "Buen día le saluda ST3.PNP Giancarlo MERINO SANCHO de la UIAT NORTE, a cargo de la investigación por el accidente de tránsito " . join_con_y($modalidades) . ", suscitado el día " . fecha_simple($A['fecha_accidente'] ?? null) . " en " . ($A['lugar'] ?? 'el lugar del accidente') . ".";
          $personPaneId = 'person-pane-' . (int) $persona['involucrado_id'];
        ?>
        <div class="tab-pane fade <?= $paneIndex === 0 ? 'show active' : '' ?>" id="<?= h($tabId) ?>" role="tabpanel">
          <div class="tab-panel">
            <div class="person-hero">
              <div class="person-title">
                <h2><?= h(person_label($persona)) ?></h2>
                <p><?= h(tab_person_label($persona)) ?><?php if (!empty($persona['orden_participacion'])): ?> · <?= h((string) $persona['orden_participacion']) ?><?php endif; ?></p>
              </div>
              <div class="chip-row">
                <?php if (!empty($persona['rol_nombre'])): ?><span class="<?= h(role_chip_class((string) $persona['rol_nombre'])) ?>"><?= h((string) $persona['rol_nombre']) ?></span><?php endif; ?>
                <?php if (!empty($persona['lesion'])): ?><span class="<?= h(lesion_chip_class((string) $persona['lesion'])) ?>"><?= h((string) $persona['lesion']) ?></span><?php endif; ?>
                <span class="chip-simple"><?= !empty($persona['vehiculo_id']) ? 'Con vehículo' : 'Sin vehículo' ?></span>
                <?php if (!empty($persona['veh_placa'])): ?><span class="chip-simple">Vehículo <?= h((string) $persona['veh_placa']) ?></span><?php endif; ?>
              </div>
            </div>

            <div class="action-row">
              <a class="btn-shell" href="persona_leer.php?id=<?= (int) $persona['persona_id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver persona</a>
              <a class="btn-shell" href="involucrados_personas_editar.php?id=<?= (int) $persona['involucrado_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Editar participación</a>
              <?php if ($isDriver && !empty($persona['veh_id'])): ?>
                <a class="btn-shell" href="vehiculo_leer.php?id=<?= (int) $persona['veh_id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver vehículo</a>
              <?php endif; ?>
              <?php if ($wa): ?>
                <a class="btn-shell" href="https://wa.me/<?= h($wa) ?>?text=<?= rawurlencode($whatsAppMsg) ?>" target="_blank" rel="noopener">WhatsApp</a>
              <?php endif; ?>
            </div>

            <div class="inner-tabs nav nav-tabs flex-nowrap" id="<?= h($personPaneId) ?>" role="tablist">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#<?= h($personPaneId) ?>-persona" type="button" role="tab">
                Persona
                <span class="tab-mini">Ficha principal</span>
              </button>
              <?php if ($isDriver && !empty($persona['veh_id'])): ?>
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

              <?php if ($isDriver && !empty($persona['veh_id'])): ?>
                <div class="tab-pane fade" id="<?= h($personPaneId) ?>-vehiculo" role="tabpanel">
                  <div class="inner-panel">
                    <div class="editable-shell" data-edit-shell="vehiculo-<?= (int) $persona['involucrado_id'] ?>">
                      <div class="editable-toolbar">
                        <div class="record-actions" style="margin-top:0">
                          <a class="btn-shell" href="vehiculo_leer.php?id=<?= (int) $persona['veh_id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver ficha completa</a>
                        </div>
                        <div class="editable-actions">
                          <button type="button" class="btn-shell js-edit-start" data-shell="vehiculo-<?= (int) $persona['involucrado_id'] ?>">Editar vehículo</button>
                          <div class="editable-actions" data-edit-actions="vehiculo-<?= (int) $persona['involucrado_id'] ?>" hidden>
                            <button type="button" class="btn-shell js-edit-cancel" data-shell="vehiculo-<?= (int) $persona['involucrado_id'] ?>">Cancelar</button>
                            <button type="submit" class="btn-shell btn-primary" form="vehiculo-inline-form-<?= (int) $persona['involucrado_id'] ?>">Guardar</button>
                          </div>
                        </div>
                      </div>

                      <div class="inline-edit-error" id="vehiculo-inline-error-<?= (int) $persona['involucrado_id'] ?>"></div>

                      <div class="editable-view" data-edit-view="vehiculo-<?= (int) $persona['involucrado_id'] ?>">
                        <div class="section-block" style="margin-top:0">
                          <h3>Vehículo vinculado al conductor</h3>
                          <div class="field-grid"><?= render_field_cards($persona, $vehiculoFields) ?></div>
                        </div>
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
                      <a class="btn-shell" href="documento_occiso_nuevo.php?persona_id=<?= (int) $persona['persona_id'] ?>&personaId=<?= (int) $persona['persona_id'] ?>&rol_id=<?= (int) ($persona['rol_id'] ?? 0) ?>&accidente_id=<?= (int) $accidente_id ?>&accidenteId=<?= (int) $accidente_id ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">+ Nuevo documento de occiso</a>
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
                              <a class="btn-shell" href="documento_occiso_editar.php?id=<?= (int) $occ['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Editar</a>
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
          <?php if (!$policias): ?>
            <div class="empty-state">No hay efectivos policiales registrados para este accidente.</div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($policias as $row): ?>
                <article class="module-card">
                  <header>
                    <div>
                      <h4><?= h(person_label($row)) ?></h4>
                      <p><?= h(trim((string) (($row['grado_policial'] ?? '-') . ' · CIP ' . ($row['cip'] ?? '-')))) ?></p>
                    </div>
                    <span class="chip-simple">Registro #<?= (int) $row['id'] ?></span>
                  </header>
                  <div class="module-meta">
                    <span class="chip-simple"><?= h((string) (($row['tipo_doc'] ?? 'DOC') . ' ' . ($row['num_doc'] ?? '—'))) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['dependencia_policial'] ?? '') !== '' ? $row['dependencia_policial'] : 'Sin dependencia')) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['rol_funcion'] ?? '') !== '' ? $row['rol_funcion'] : 'Sin rol / función')) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['celular'] ?? '') !== '' ? $row['celular'] : 'Sin celular')) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['email'] ?? '') !== '' ? $row['email'] : 'Sin email')) ?></span>
                  </div>
                  <?php if (!empty($row['observaciones'])): ?><p style="margin-top:10px;"><?= nl2br(h((string) $row['observaciones'])) ?></p><?php endif; ?>
                  <div class="module-actions">
                    <a class="btn-shell" href="policial_interviniente_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                    <a class="btn-shell" href="policial_interviniente_editar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Editar</a>
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
          <?php if (!$propietarios): ?>
            <div class="empty-state">No hay propietarios de vehículo registrados para este accidente.</div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($propietarios as $row): ?>
                <?php
                  $principal = (string) ($row['tipo_propietario'] ?? '') === 'NATURAL'
                    ? trim((string) (($row['ap_nat'] ?? '') . ' ' . ($row['am_nat'] ?? '') . ' ' . ($row['no_nat'] ?? '')))
                    : (string) ($row['razon_social'] ?? 'Sin razón social');
                  $principalDoc = (string) ($row['tipo_propietario'] ?? '') === 'NATURAL'
                    ? trim((string) (($row['tipo_doc_nat'] ?? '') . ' ' . ($row['dni_nat'] ?? '')))
                    : trim((string) ('RUC ' . ($row['ruc'] ?? '')));
                  $representante = trim((string) (($row['ap_rep'] ?? '') . ' ' . ($row['am_rep'] ?? '') . ' ' . ($row['no_rep'] ?? '')));
                ?>
                <article class="module-card">
                  <header>
                    <div>
                      <h4><?= h($principal !== '' ? $principal : 'Sin propietario') ?></h4>
                      <p><?= h(trim((string) (($row['orden_participacion'] ?? '') . ' · Placa ' . ($row['placa'] ?? 'SIN PLACA')))) ?></p>
                    </div>
                    <span class="chip-simple"><?= h((string) ($row['tipo_propietario'] ?? '')) ?></span>
                  </header>
                  <div class="module-meta">
                    <span class="chip-simple"><?= h($principalDoc !== '' ? $principalDoc : 'Sin documento') ?></span>
                    <span class="chip-simple"><?= h((string) (($row['rol_legal'] ?? '') !== '' ? $row['rol_legal'] : 'Sin rol legal')) ?></span>
                    <?php if ($representante !== ''): ?><span class="chip-simple">Representante: <?= h($representante) ?></span><?php endif; ?>
                  </div>
                  <?php if (!empty($row['domicilio_fiscal'])): ?><p style="margin-top:10px;">Domicilio fiscal: <?= nl2br(h((string) $row['domicilio_fiscal'])) ?></p><?php endif; ?>
                  <?php if (!empty($row['observaciones'])): ?><p style="margin-top:10px;"><?= nl2br(h((string) $row['observaciones'])) ?></p><?php endif; ?>
                  <div class="module-actions">
                    <a class="btn-shell" href="propietario_vehiculo_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                    <a class="btn-shell" href="propietario_vehiculo_editar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Editar</a>
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
          <?php if (!$familiares): ?>
            <div class="empty-state">No hay familiares de fallecidos registrados para este accidente.</div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($familiares as $row): ?>
                <article class="module-card">
                  <header>
                    <div>
                      <h4><?= h(trim((string) (($row['ap_fam'] ?? '') . ' ' . ($row['am_fam'] ?? '') . ' ' . ($row['no_fam'] ?? '')))) ?></h4>
                      <p>Familiar de <?= h(trim((string) (($row['ap_fall'] ?? '') . ' ' . ($row['am_fall'] ?? '') . ' ' . ($row['no_fall'] ?? '')))) ?></p>
                    </div>
                    <span class="chip-simple"><?= h((string) (($row['parentesco'] ?? '') !== '' ? $row['parentesco'] : 'Sin parentesco')) ?></span>
                  </header>
                  <div class="module-meta">
                    <span class="chip-simple"><?= h(trim((string) (($row['tipo_doc_fam'] ?? '') . ' ' . ($row['dni_fam'] ?? '')))) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['cel_fam'] ?? '') !== '' ? $row['cel_fam'] : 'Sin celular')) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['email_fam'] ?? $row['em_fam'] ?? '') !== '' ? ($row['email_fam'] ?? $row['em_fam']) : 'Sin email')) ?></span>
                    <span class="chip-simple">Fallecido DNI: <?= h((string) (($row['dni_fall'] ?? '') !== '' ? $row['dni_fall'] : '—')) ?></span>
                  </div>
                  <?php if (!empty($row['observaciones'])): ?><p style="margin-top:10px;"><?= nl2br(h((string) $row['observaciones'])) ?></p><?php endif; ?>
                  <div class="module-actions">
                    <a class="btn-shell" href="familiar_fallecido_leer.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                    <a class="btn-shell" href="familiar_fallecido_editar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Editar</a>
                  </div>
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
                  $contactoAbogado = trim((string) ($row['celular'] ?? ''));
                  if (($row['email'] ?? '') !== '') {
                      $contactoAbogado .= $contactoAbogado !== '' ? ' · ' . $row['email'] : $row['email'];
                  }
                ?>
                <article class="module-card">
                  <header>
                    <div>
                      <h4><?= h($nombreAbogado !== '' ? $nombreAbogado : 'Sin nombre') ?></h4>
                      <p><?= h((string) (($row['persona_rep_nom'] ?? '') !== '' ? $row['persona_rep_nom'] : 'Sin persona asociada')) ?></p>
                    </div>
                    <span class="chip-simple">Registro #<?= (int) $row['id'] ?></span>
                  </header>
                  <div class="module-meta">
                    <span class="chip-simple">Colegiatura: <?= h((string) (($row['colegiatura'] ?? '') !== '' ? $row['colegiatura'] : 'Sin colegiatura')) ?></span>
                    <span class="chip-simple">Registro: <?= h((string) (($row['registro'] ?? '') !== '' ? $row['registro'] : 'Sin registro')) ?></span>
                    <span class="chip-simple"><?= h((string) (($row['condicion_representado'] ?? '') !== '' ? $row['condicion_representado'] : 'Sin condición')) ?></span>
                    <span class="chip-simple"><?= h($contactoAbogado !== '' ? $contactoAbogado : 'Sin contacto') ?></span>
                  </div>
                  <?php if (!empty($row['casilla_electronica'])): ?><p style="margin-top:10px;">Casilla electrónica: <?= h((string) $row['casilla_electronica']) ?></p><?php endif; ?>
                  <?php if (!empty($row['domicilio_procesal'])): ?><p style="margin-top:10px;">Domicilio procesal: <?= nl2br(h((string) $row['domicilio_procesal'])) ?></p><?php endif; ?>
                  <div class="module-actions">
                    <a class="btn-shell" href="abogado_ver.php?id=<?= (int) $row['id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Ver</a>
                    <a class="btn-shell" href="abogado_editar.php?id=<?= (int) $row['id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Editar</a>
                    <a class="btn-shell" href="abogado_eliminar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? ('accidente_vista_tabs.php?accidente_id=' . $accidente_id)) ?>">Eliminar</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php $paneIndex++; ?>

      <div class="tab-pane fade <?= $paneIndex === 0 ? 'show active' : '' ?>" id="oficios" role="tabpanel">
        <div class="tab-panel">
          <?php if (!$oficios): ?>
            <div class="empty-state">No hay oficios registrados para este accidente.</div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($oficios as $row): ?>
                <article class="module-card">
                  <header>
                    <div>
                      <h4>Oficio <?= h((string) ($row['numero'] ?? '')) ?>/<?= h((string) ($row['anio'] ?? '')) ?></h4>
                      <p><?= h((string) (($row['entidad'] ?? '') !== '' ? $row['entidad'] : 'Sin entidad')) ?></p>
                    </div>
                    <span class="chip-simple"><?= h((string) (($row['estado'] ?? '') !== '' ? $row['estado'] : 'Sin estado')) ?></span>
                  </header>
                  <div class="module-meta">
                    <span class="chip-simple"><?= h((string) (($row['asunto_nombre'] ?? '') !== '' ? $row['asunto_nombre'] : 'Sin asunto')) ?></span>
                    <span class="chip-simple">Fecha: <?= h(fecha_simple($row['fecha_emision'] ?? null)) ?></span>
                    <?php if (!empty($row['veh_placa'])): ?><span class="chip-simple"><?= h(trim((string) (($row['veh_ut'] ?? '') . ' · ' . $row['veh_placa']))) ?></span><?php endif; ?>
                    <?php if (!empty(trim((string) ($row['persona_nombre'] ?? '')))): ?><span class="chip-simple"><?= h(trim((string) $row['persona_nombre'])) ?></span><?php endif; ?>
                  </div>
                  <?php if (!empty($row['referencia_texto'])): ?><p style="margin-top:10px;">Referencia: <?= nl2br(h((string) $row['referencia_texto'])) ?></p><?php endif; ?>
                  <?php if (!empty($row['motivo'])): ?><p style="margin-top:10px;"><?= nl2br(h((string) $row['motivo'])) ?></p><?php endif; ?>
                  <div class="module-actions">
                    <a class="btn-shell" href="oficios_leer.php?id=<?= (int) $row['id'] ?>">Ver</a>
                    <a class="btn-shell" href="oficios_editar.php?id=<?= (int) $row['id'] ?>">Editar</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php $paneIndex++; ?>

      <div class="tab-pane fade <?= $paneIndex === 0 ? 'show active' : '' ?>" id="diligencias-pendientes" role="tabpanel">
        <div class="tab-panel">
          <?php if (!$diligencias): ?>
            <div class="empty-state">No hay diligencias pendientes registradas para este accidente.</div>
          <?php else: ?>
            <div class="module-grid">
              <?php foreach ($diligencias as $row): ?>
                <article class="module-card">
                  <header>
                    <div>
                      <h4>Diligencia #<?= (int) $row['id'] ?></h4>
                      <p><?= h((string) (($row['tipo_nombre'] ?? '') !== '' ? $row['tipo_nombre'] : (($row['tipo_diligencia'] ?? '') !== '' ? $row['tipo_diligencia'] : 'Sin tipo'))) ?></p>
                    </div>
                    <span class="chip-simple"><?= h((string) (($row['estado'] ?? '') !== '' ? $row['estado'] : 'Sin estado')) ?></span>
                  </header>
                  <div class="module-meta">
                    <?php if (!empty($row['oficio_id'])): ?><span class="chip-simple">Oficio #<?= (int) $row['oficio_id'] ?></span><?php endif; ?>
                    <?php if (!empty($row['citacion_id'])): ?><span class="chip-simple">Citación #<?= (int) $row['citacion_id'] ?></span><?php endif; ?>
                    <?php if (!empty($row['creado_en'])): ?><span class="chip-simple">Creada: <?= h(fecha_hora_simple($row['creado_en'])) ?></span><?php endif; ?>
                  </div>
                  <?php if (!empty($row['contenido'])): ?><p style="margin-top:10px;"><?= nl2br(h((string) $row['contenido'])) ?></p><?php endif; ?>
                  <?php if (!empty($row['documento_realizado'])): ?><p style="margin-top:10px;">Documento realizado: <?= h((string) $row['documento_realizado']) ?></p><?php endif; ?>
                  <?php if (!empty($row['documentos_recibidos'])): ?><p style="margin-top:10px;">Documentos recibidos: <?= nl2br(h((string) $row['documentos_recibidos'])) ?></p><?php endif; ?>
                  <div class="module-actions">
                    <a class="btn-shell" href="diligenciapendiente_ver.php?id=<?= (int) $row['id'] ?>">Ver</a>
                    <a class="btn-shell" href="diligenciapendiente_editar.php?id=<?= (int) $row['id'] ?>">Editar</a>
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

      if (form.classList.contains('js-veh-inline-form')) {
        initVehicleInlineForm(form);
        syncVehicleForm(form, true);
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
        initial: serializeForm(form),
        dirty: false,
      };
      editStates.set(shellName, state);

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

    document.querySelectorAll('.js-inline-ajax-form').forEach((form) => {
      const shellName = form.dataset.shell;
      if (!shellName) return;

      if (form.classList.contains('js-veh-inline-form')) {
        initVehicleInlineForm(form);
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
        openEditShell(button.dataset.shell || '');
      });
    });

    document.querySelectorAll('.js-edit-cancel').forEach((button) => {
      button.addEventListener('click', () => {
        requestCloseEditShell(button.dataset.shell || '');
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
      if (data.type === 'occiso.close') {
        const workbench = visibleWorkbench();
        if (!workbench) return;
        const frame = workbench.querySelector('.inline-frame');
        if (!frame) return;
        closeWorkbenchImmediate(workbench, frame);
        return;
      }
      if (['lc.saved', 'rml.saved', 'dosaje.saved', 'manifestacion.saved', 'occiso.saved', 'occiso.updated'].includes(data.type)) {
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
