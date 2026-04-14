<?php
declare(strict_types=1);

/* ===========================================================
   WORD: INFORME COMBINADO VEHICULAR
   Entrada: ?accidente_id=ID  (acepta ?id)
   Opcional: ?vehiculo_inv_id=ID para limitar a una unidad.
   Si no se indica unidad, toma el par Combinado vehicular 1/2
   de la misma UT; si no existe, usa las dos primeras unidades.
   Si existe /plantillas/word_informe_combinado_vehiculo.docx,
   usa esa plantilla. Si no existe, genera DOCX directo con PhpWord.
   =========================================================== */

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

$accidenteId = (int) ($_GET['accidente_id'] ?? $_GET['id'] ?? 0);
$vehiculoInvId = (int) ($_GET['vehiculo_inv_id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta ?accidente_id');
}

function fetch_one(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
}

function fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function textv($value, string $fallback = '-'): string
{
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $value : $fallback;
}

function name_person(array $row, string $prefix = ''): string
{
    return textv(trim(
        (string) ($row[$prefix . 'apellido_paterno'] ?? '') . ' ' .
        (string) ($row[$prefix . 'apellido_materno'] ?? '') . ' ' .
        (string) ($row[$prefix . 'nombres'] ?? '')
    ));
}

function date_pe($value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '-';
    }
    $ts = strtotime($value);
    if (!$ts) {
        return $value;
    }
    static $mes = [
        1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN',
        7 => 'JUL', 8 => 'AGO', 9 => 'SET', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
    ];
    return date('d', $ts) . $mes[(int) date('n', $ts)] . date('Y', $ts);
}

function time_pe($value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? date('H:i', $ts) : $value;
}

function age_from($birthDate, $referenceDate = null): string
{
    $birthDate = trim((string) ($birthDate ?? ''));
    if ($birthDate === '') {
        return '-';
    }
    $birth = strtotime($birthDate);
    if (!$birth) {
        return '-';
    }
    try {
        $birthDt = new DateTime(date('Y-m-d', $birth));
        $refDt = $referenceDate ? new DateTime(date('Y-m-d', strtotime((string) $referenceDate))) : new DateTime('today');
        return (string) $birthDt->diff($refDt)->y;
    } catch (Throwable $e) {
        return '-';
    }
}

function join_es(array $items): string
{
    $items = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $items), static fn($v) => $v !== ''));
    $count = count($items);
    if ($count === 0) {
        return '-';
    }
    if ($count === 1) {
        return $items[0];
    }
    if ($count === 2) {
        return $items[0] . ' y ' . $items[1];
    }
    return implode(', ', array_slice($items, 0, -1)) . ' y ' . $items[$count - 1];
}

function add_heading($section, string $text, int $level = 1): void
{
    $style = $level === 1
        ? ['bold' => true, 'size' => 14, 'color' => '9A6A00']
        : ['bold' => true, 'size' => 11, 'color' => '1F2937'];
    $section->addText($text, $style, ['spaceAfter' => 120]);
}

function add_pairs($section, array $pairs): void
{
    $table = $section->addTable('PairTable');
    foreach ($pairs as $label => $value) {
        $table->addRow();
        $table->addCell(3600, ['bgColor' => 'F3F4F6'])->addText((string) $label, ['bold' => true, 'size' => 9]);
        $table->addCell(6200)->addText(textv($value), ['size' => 9]);
    }
    $section->addTextBreak(1);
}

function add_records($section, string $emptyText, array $records, array $fieldMap): void
{
    if ($records === []) {
        $section->addText($emptyText, ['italic' => true, 'size' => 9, 'color' => '666666']);
        $section->addTextBreak(1);
        return;
    }

    foreach ($records as $index => $record) {
        if (count($records) > 1) {
            $section->addText('Registro #' . ($index + 1), ['bold' => true, 'size' => 10]);
        }
        $pairs = [];
        foreach ($fieldMap as $label => $field) {
            $value = is_callable($field) ? $field($record) : ($record[$field] ?? '');
            $pairs[$label] = $value;
        }
        add_pairs($section, $pairs);
    }
}

function load_modalidades(PDO $pdo, int $accidenteId): string
{
    try {
        $rows = fetch_all($pdo, "
            SELECT m.nombre
              FROM accidente_modalidad am
              JOIN modalidad_accidente m ON m.id = am.modalidad_id
             WHERE am.accidente_id = :id
             ORDER BY m.nombre
        ", [':id' => $accidenteId]);
        return join_es(array_column($rows, 'nombre'));
    } catch (Throwable $e) {
        return '-';
    }
}

function load_consecuencias(PDO $pdo, int $accidenteId, array $accidente): string
{
    try {
        $rows = fetch_all($pdo, "
            SELECT c.nombre
              FROM accidente_consecuencia ac
              JOIN consecuencia_accidente c ON c.id = ac.consecuencia_id
             WHERE ac.accidente_id = :id
             ORDER BY c.nombre
        ", [':id' => $accidenteId]);
        $text = join_es(array_column($rows, 'nombre'));
        return $text !== '-' ? $text : textv($accidente['consecuencia'] ?? '');
    } catch (Throwable $e) {
        return textv($accidente['consecuencia'] ?? '');
    }
}

function load_abogados(PDO $pdo, int $accidenteId, ?int $personaId): array
{
    if (!$personaId) {
        return [];
    }
    $rows = fetch_all($pdo, 'SELECT * FROM abogados WHERE accidente_id = :a AND persona_id = :p ORDER BY id DESC', [
        ':a' => $accidenteId,
        ':p' => $personaId,
    ]);
    if ($rows !== []) {
        return $rows;
    }
    return fetch_all($pdo, 'SELECT * FROM abogados WHERE persona_id = :p ORDER BY id DESC', [':p' => $personaId]);
}

function load_person_docs(PDO $pdo, int $accidenteId, ?int $personaId): array
{
    if (!$personaId) {
        return ['lc' => [], 'rml' => [], 'dosaje' => [], 'manifestacion' => []];
    }

    return [
        'lc' => fetch_all($pdo, 'SELECT * FROM documento_lc WHERE persona_id = :p ORDER BY id DESC', [':p' => $personaId]),
        'rml' => fetch_all($pdo, 'SELECT * FROM documento_rml WHERE persona_id = :p AND accidente_id = :a ORDER BY fecha DESC, id DESC', [
            ':p' => $personaId,
            ':a' => $accidenteId,
        ]),
        'dosaje' => fetch_all($pdo, 'SELECT * FROM documento_dosaje WHERE persona_id = :p ORDER BY fecha_extraccion DESC, id DESC', [':p' => $personaId]),
        'manifestacion' => fetch_all($pdo, 'SELECT * FROM Manifestacion WHERE persona_id = :p AND accidente_id = :a ORDER BY fecha DESC, horario_inicio DESC, id DESC', [
            ':p' => $personaId,
            ':a' => $accidenteId,
        ]),
    ];
}

function load_conductores(PDO $pdo, int $accidenteId, array $vehicle, bool $singleVehicle): array
{
    $vehiculoId = (int) ($vehicle['vehiculo_id'] ?? 0);
    if ($vehiculoId <= 0) {
        return [];
    }

    $rows = fetch_all($pdo, "
        SELECT ip.id AS involucrado_persona_id, ip.orden_persona, ip.vehiculo_id, ip.lesion, ip.observaciones AS participacion_observaciones,
               pr.Nombre AS rol_nombre,
               p.*
          FROM involucrados_personas ip
          JOIN personas p ON p.id = ip.persona_id
     LEFT JOIN participacion_persona pr ON pr.Id = ip.rol_id
         WHERE ip.accidente_id = :a
           AND ip.vehiculo_id = :v
           AND LOWER(COALESCE(pr.Nombre, '')) LIKE '%conduc%'
         ORDER BY ip.id ASC
    ", [':a' => $accidenteId, ':v' => $vehiculoId]);

    if ($rows === [] && $singleVehicle) {
        $rows = fetch_all($pdo, "
            SELECT ip.id AS involucrado_persona_id, ip.orden_persona, ip.vehiculo_id, ip.lesion, ip.observaciones AS participacion_observaciones,
                   pr.Nombre AS rol_nombre,
                   p.*
              FROM involucrados_personas ip
              JOIN personas p ON p.id = ip.persona_id
         LEFT JOIN participacion_persona pr ON pr.Id = ip.rol_id
             WHERE ip.accidente_id = :a
               AND LOWER(COALESCE(pr.Nombre, '')) LIKE '%conduc%'
             ORDER BY ip.id ASC
        ", [':a' => $accidenteId]);
    }

    return $rows;
}

function load_vehiculo_docs(PDO $pdo, array $vehicle): array
{
    $ivId = (int) ($vehicle['iv_id'] ?? 0);
    $vehiculoId = (int) ($vehicle['vehiculo_id'] ?? 0);
    return fetch_all($pdo, "
        SELECT *
          FROM documento_vehiculo
         WHERE involucrado_vehiculo_id = :iv
            OR vehiculo_id = :veh
         ORDER BY id DESC
    ", [':iv' => $ivId, ':veh' => $vehiculoId]);
}

function load_propietarios(PDO $pdo, int $accidenteId, int $vehiculoInvId): array
{
    return fetch_all($pdo, "
        SELECT pv.*,
               pn.tipo_doc AS nat_tipo_doc, pn.num_doc AS nat_num_doc, pn.apellido_paterno AS nat_apellido_paterno,
               pn.apellido_materno AS nat_apellido_materno, pn.nombres AS nat_nombres, pn.domicilio AS nat_domicilio,
               pn.celular AS nat_celular, pn.email AS nat_email,
               pr.tipo_doc AS rep_tipo_doc, pr.num_doc AS rep_num_doc, pr.apellido_paterno AS rep_apellido_paterno,
               pr.apellido_materno AS rep_apellido_materno, pr.nombres AS rep_nombres, pr.domicilio AS rep_domicilio,
               pr.celular AS rep_celular, pr.email AS rep_email
          FROM propietario_vehiculo pv
     LEFT JOIN personas pn ON pn.id = pv.propietario_persona_id
     LEFT JOIN personas pr ON pr.id = pv.representante_persona_id
         WHERE pv.accidente_id = :a
           AND pv.vehiculo_inv_id = :iv
         ORDER BY pv.id DESC
    ", [':a' => $accidenteId, ':iv' => $vehiculoInvId]);
}

function first_row(array $rows): array
{
    return $rows[0] ?? [];
}

function pick_combined_vehicles(array $vehicles): array
{
    $groups = [];
    foreach ($vehicles as $vehicle) {
        $tipo = (string) ($vehicle['involucrado_tipo'] ?? '');
        if ($tipo !== 'Combinado vehicular 1' && $tipo !== 'Combinado vehicular 2') {
            continue;
        }

        $orden = (string) ($vehicle['orden_participacion'] ?? '');
        if (!isset($groups[$orden])) {
            $groups[$orden] = [];
        }
        $groups[$orden][$tipo] = $vehicle;
    }

    foreach ($groups as $group) {
        if (isset($group['Combinado vehicular 1'], $group['Combinado vehicular 2'])) {
            return [$group['Combinado vehicular 1'], $group['Combinado vehicular 2']];
        }
    }

    return array_slice($vehicles, 0, 2);
}

function doc_type_number(array $row, string $typeKey = 'tipo_doc', string $numKey = 'num_doc'): string
{
    return trim(textv($row[$typeKey] ?? '', '') . ' ' . textv($row[$numKey] ?? '', ''));
}

function set_marker(array &$markers, string $key, $value): void
{
    $markers[$key] = textv($value, '');
}

function set_marker_aliases(array &$markers, array $prefixes, string $suffix, $value): void
{
    foreach ($prefixes as $prefix) {
        set_marker($markers, $prefix . $suffix, $value);
    }
}

function fill_vehicle_template_markers(
    array &$markers,
    array $prefixes,
    array $vehicle,
    array $vehicleDoc,
    array $driver,
    array $driverDocs,
    array $driverLawyer,
    array $owner,
    array $ownerLawyer,
    array $accidente
): void {
    $vehicleCategory = trim(textv($vehicle['categoria_codigo'] ?? '', '') . ' ' . textv($vehicle['categoria_descripcion'] ?? '', ''));
    $vehicleType = trim(textv($vehicle['tipo_codigo'] ?? '', '') . ' ' . textv($vehicle['tipo_nombre'] ?? '', ''));
    $vehicleMeasures = trim(textv($vehicle['largo_mm'] ?? '', '') . ' / ' . textv($vehicle['ancho_mm'] ?? '', '') . ' / ' . textv($vehicle['alto_mm'] ?? '', ''));

    foreach ([
        'veh_orden' => $vehicle['orden_participacion'] ?? '',
        'veh_placa' => $vehicle['placa'] ?? '',
        'veh_marca' => $vehicle['marca_nombre'] ?? '',
        'veh_modelo' => $vehicle['modelo_nombre'] ?? '',
        'veh_categoria' => $vehicleCategory,
        'veh_tipo' => $vehicleType,
        'veh_carroceria' => $vehicle['carroceria_nombre'] ?? '',
        'veh_color' => $vehicle['color'] ?? '',
        'veh_anio' => $vehicle['anio'] ?? '',
        'veh_serie_vin' => $vehicle['serie_vin'] ?? '',
        'veh_nro_motor' => $vehicle['nro_motor'] ?? '',
        'veh_medidas' => $vehicleMeasures,
        'veh_tipo_accidente' => $vehicle['involucrado_tipo'] ?? '',
        'veh_observaciones' => $vehicle['involucrado_observaciones'] ?? ($vehicle['notas'] ?? ''),
        'docv_num_propiedad' => $vehicleDoc['numero_propiedad'] ?? '',
        'docv_titulo_propiedad' => $vehicleDoc['titulo_propiedad'] ?? '',
        'docv_partida_propiedad' => $vehicleDoc['partida_propiedad'] ?? '',
        'docv_sede_propiedad' => $vehicleDoc['sede_propiedad'] ?? '',
        'docv_num_soat' => $vehicleDoc['numero_soat'] ?? '',
        'docv_aseguradora_soat' => $vehicleDoc['aseguradora_soat'] ?? '',
        'docv_vigente_soat' => date_pe($vehicleDoc['vigente_soat'] ?? ''),
        'docv_vencimiento_soat' => date_pe($vehicleDoc['vencimiento_soat'] ?? ''),
        'docv_num_revision' => $vehicleDoc['numero_revision'] ?? '',
        'docv_certificadora_revision' => $vehicleDoc['certificadora_revision'] ?? '',
        'docv_vigente_revision' => date_pe($vehicleDoc['vigente_revision'] ?? ''),
        'docv_vencimiento_revision' => date_pe($vehicleDoc['vencimiento_revision'] ?? ''),
        'docv_num_peritaje' => $vehicleDoc['numero_peritaje'] ?? '',
        'docv_fecha_peritaje' => date_pe($vehicleDoc['fecha_peritaje'] ?? ''),
        'docv_perito_peritaje' => $vehicleDoc['perito_peritaje'] ?? '',
        'docv_sistema_electrico_peritaje' => $vehicleDoc['sistema_electrico_peritaje'] ?? '',
        'docv_sistema_frenos_peritaje' => $vehicleDoc['sistema_frenos_peritaje'] ?? '',
        'docv_sistema_direccion_peritaje' => $vehicleDoc['sistema_direccion_peritaje'] ?? '',
        'docv_sistema_transmision_peritaje' => $vehicleDoc['sistema_transmision_peritaje'] ?? '',
        'docv_sistema_suspension_peritaje' => $vehicleDoc['sistema_suspension_peritaje'] ?? '',
        'docv_planta_motriz_peritaje' => $vehicleDoc['planta_motriz_peritaje'] ?? '',
        'docv_otros_peritaje' => $vehicleDoc['otros_peritaje'] ?? '',
        'docv_danos_peritaje' => $vehicleDoc['danos_peritaje'] ?? '',
    ] as $suffix => $value) {
        set_marker_aliases($markers, $prefixes, $suffix, $value);
    }

    foreach ([
        'cond_nombre' => name_person($driver),
        'cond_rol' => $driver['rol_nombre'] ?? '',
        'cond_lesion' => $driver['lesion'] ?? '',
        'cond_doc_tipo' => $driver['tipo_doc'] ?? '',
        'cond_doc_num' => $driver['num_doc'] ?? '',
        'cond_doc' => doc_type_number($driver),
        'cond_sexo' => $driver['sexo'] ?? '',
        'cond_fecha_nacimiento' => date_pe($driver['fecha_nacimiento'] ?? ''),
        'cond_edad' => age_from($driver['fecha_nacimiento'] ?? ''),
        'cond_edad_accidente' => age_from($driver['fecha_nacimiento'] ?? '', $accidente['fecha_accidente'] ?? null),
        'cond_estado_civil' => $driver['estado_civil'] ?? '',
        'cond_nacionalidad' => $driver['nacionalidad'] ?? '',
        'cond_nacimiento' => trim(textv($driver['departamento_nac'] ?? '', '') . ' / ' . textv($driver['provincia_nac'] ?? '', '') . ' / ' . textv($driver['distrito_nac'] ?? '', '')),
        'cond_domicilio' => $driver['domicilio'] ?? '',
        'cond_domicilio_ubigeo' => trim(textv($driver['domicilio_departamento'] ?? '', '') . ' / ' . textv($driver['domicilio_provincia'] ?? '', '') . ' / ' . textv($driver['domicilio_distrito'] ?? '', '')),
        'cond_ocupacion' => $driver['ocupacion'] ?? '',
        'cond_grado_instruccion' => $driver['grado_instruccion'] ?? '',
        'cond_padre' => $driver['nombre_padre'] ?? '',
        'cond_madre' => $driver['nombre_madre'] ?? '',
        'cond_celular' => $driver['celular'] ?? '',
        'cond_email' => $driver['email'] ?? '',
        'cond_observaciones' => $driver['participacion_observaciones'] ?? '',
    ] as $suffix => $value) {
        set_marker_aliases($markers, $prefixes, $suffix, $value);
    }

    $lc = first_row($driverDocs['lc'] ?? []);
    $rml = first_row($driverDocs['rml'] ?? []);
    $dosaje = first_row($driverDocs['dosaje'] ?? []);
    $manifestacion = first_row($driverDocs['manifestacion'] ?? []);
    foreach ([
        'lc_clase' => $lc['clase'] ?? '',
        'lc_categoria' => $lc['categoria'] ?? '',
        'lc_numero' => $lc['numero'] ?? '',
        'lc_expedido_por' => $lc['expedido_por'] ?? '',
        'lc_vigente_desde' => date_pe($lc['vigente_desde'] ?? ''),
        'lc_vigente_hasta' => date_pe($lc['vigente_hasta'] ?? ''),
        'lc_restricciones' => $lc['restricciones'] ?? '',
        'rml_numero' => $rml['numero'] ?? '',
        'rml_fecha' => date_pe($rml['fecha'] ?? ''),
        'rml_incapacidad' => $rml['incapacidad_medico'] ?? '',
        'rml_atencion' => $rml['atencion_facultativo'] ?? '',
        'rml_observaciones' => $rml['observaciones'] ?? '',
        'dosaje_numero' => $dosaje['numero'] ?? '',
        'dosaje_registro' => $dosaje['numero_registro'] ?? '',
        'dosaje_fecha' => date_pe($dosaje['fecha_extraccion'] ?? ''),
        'dosaje_hora' => time_pe($dosaje['fecha_extraccion'] ?? ''),
        'dosaje_resultado_cual' => $dosaje['resultado_cualitativo'] ?? '',
        'dosaje_resultado_cuant' => $dosaje['resultado_cuantitativo'] ?? '',
        'dosaje_lectura_cuant' => $dosaje['leer_cuantitativo'] ?? '',
        'dosaje_observaciones' => $dosaje['observaciones'] ?? '',
        'man_fecha' => date_pe($manifestacion['fecha'] ?? ''),
        'man_hora_inicio' => time_pe($manifestacion['horario_inicio'] ?? ''),
        'man_hora_termino' => time_pe($manifestacion['hora_termino'] ?? ''),
        'man_modalidad' => $manifestacion['modalidad'] ?? '',
    ] as $suffix => $value) {
        set_marker_aliases($markers, $prefixes, $suffix, $value);
    }

    foreach ([
        'cond_abog_nombre' => name_person($driverLawyer),
        'cond_abog_condicion' => $driverLawyer['condicion'] ?? '',
        'cond_abog_colegiatura' => $driverLawyer['colegiatura'] ?? '',
        'cond_abog_registro' => $driverLawyer['registro'] ?? '',
        'cond_abog_casilla' => $driverLawyer['casilla_electronica'] ?? '',
        'cond_abog_domicilio_procesal' => $driverLawyer['domicilio_procesal'] ?? '',
        'cond_abog_celular' => $driverLawyer['celular'] ?? '',
        'cond_abog_email' => $driverLawyer['email'] ?? '',
    ] as $suffix => $value) {
        set_marker_aliases($markers, $prefixes, $suffix, $value);
    }

    $ownerNaturalName = name_person($owner, 'nat_');
    $ownerNaturalDoc = doc_type_number($owner, 'nat_tipo_doc', 'nat_num_doc');
    $ownerDisplayName = ($owner['tipo_propietario'] ?? '') === 'JURIDICA'
        ? textv($owner['razon_social'] ?? '', '')
        : $ownerNaturalName;
    $ownerDisplayDoc = ($owner['tipo_propietario'] ?? '') === 'JURIDICA'
        ? textv($owner['ruc'] ?? '', '')
        : $ownerNaturalDoc;

    foreach ([
        'prop_tipo' => $owner['tipo_propietario'] ?? '',
        'prop_nombre' => $ownerDisplayName,
        'prop_doc' => $ownerDisplayDoc,
        'prop_nat_nombre' => $ownerNaturalName,
        'prop_nat_doc' => $ownerNaturalDoc,
        'prop_nat_domicilio' => $owner['nat_domicilio'] ?? '',
        'prop_nat_celular' => $owner['nat_celular'] ?? '',
        'prop_nat_email' => $owner['nat_email'] ?? '',
        'prop_ruc' => $owner['ruc'] ?? '',
        'prop_razon_social' => $owner['razon_social'] ?? '',
        'prop_domicilio_fiscal' => $owner['domicilio_fiscal'] ?? '',
        'prop_rol_legal' => $owner['rol_legal'] ?? '',
        'prop_rep_nombre' => name_person($owner, 'rep_'),
        'prop_rep_doc' => doc_type_number($owner, 'rep_tipo_doc', 'rep_num_doc'),
        'prop_rep_domicilio' => $owner['rep_domicilio'] ?? '',
        'prop_rep_celular' => $owner['rep_celular'] ?? '',
        'prop_rep_email' => $owner['rep_email'] ?? '',
        'prop_observaciones' => $owner['observaciones'] ?? '',
        'prop_abog_nombre' => name_person($ownerLawyer),
        'prop_abog_condicion' => $ownerLawyer['condicion'] ?? '',
        'prop_abog_colegiatura' => $ownerLawyer['colegiatura'] ?? '',
        'prop_abog_registro' => $ownerLawyer['registro'] ?? '',
        'prop_abog_casilla' => $ownerLawyer['casilla_electronica'] ?? '',
        'prop_abog_domicilio_procesal' => $ownerLawyer['domicilio_procesal'] ?? '',
        'prop_abog_celular' => $ownerLawyer['celular'] ?? '',
        'prop_abog_email' => $ownerLawyer['email'] ?? '',
    ] as $suffix => $value) {
        set_marker_aliases($markers, $prefixes, $suffix, $value);
    }
}

function build_template_markers(PDO $pdo, int $accidenteId, array $accidente, array $vehicles): array
{
    $markers = [];
    foreach ([
        'titulo_informe' => 'INFORME POLICIAL - COMBINADO VEHICULAR',
        'generado_fecha' => date('d/m/Y H:i'),
        'acc_id' => $accidente['id'] ?? '',
        'acc_sidpol' => $accidente['sidpol'] ?? '',
        'acc_registro_sidpol' => $accidente['registro_sidpol'] ?? '',
        'acc_nro_informe_policial' => $accidente['nro_informe_policial'] ?? '',
        'acc_fecha' => date_pe($accidente['fecha_accidente'] ?? ''),
        'acc_hora' => time_pe($accidente['fecha_accidente'] ?? ''),
        'acc_fecha_comunicacion' => date_pe($accidente['fecha_comunicacion'] ?? ''),
        'acc_hora_comunicacion' => time_pe($accidente['fecha_comunicacion'] ?? ''),
        'acc_fecha_intervencion' => date_pe($accidente['fecha_intervencion'] ?? ''),
        'acc_hora_intervencion' => time_pe($accidente['fecha_intervencion'] ?? ''),
        'acc_lugar' => $accidente['lugar'] ?? '',
        'acc_referencia' => $accidente['referencia'] ?? '',
        'acc_distrito' => $accidente['distrito_nombre'] ?? '',
        'acc_provincia' => $accidente['prov_nombre'] ?? '',
        'acc_departamento' => $accidente['dep_nombre'] ?? '',
        'acc_comisaria' => $accidente['comisaria_nombre'] ?? '',
        'acc_fiscalia' => $accidente['fiscalia_nombre'] ?? '',
        'acc_fiscal' => $accidente['fiscal_nombre'] ?? '',
        'acc_modalidad' => load_modalidades($pdo, $accidenteId),
        'acc_consecuencia' => load_consecuencias($pdo, $accidenteId, $accidente),
        'acc_estado' => $accidente['estado'] ?? '',
        'acc_sentido' => $accidente['sentido'] ?? '',
        'acc_secuencia' => $accidente['secuencia'] ?? '',
    ] as $key => $value) {
        set_marker($markers, $key, $value);
    }

    $singleVehicle = count($vehicles) === 1;
    for ($i = 1; $i <= 2; $i++) {
        $vehicle = $vehicles[$i - 1] ?? [];
        $prefixes = ['v' . $i . '_'];
        if ($i === 1) {
            $prefixes[] = '';
        }
        $vehicleDoc = $vehicle !== [] ? first_row(load_vehiculo_docs($pdo, $vehicle)) : [];
        $driver = $vehicle !== [] ? first_row(load_conductores($pdo, $accidenteId, $vehicle, $singleVehicle)) : [];
        $driverDocs = $driver !== [] ? load_person_docs($pdo, $accidenteId, (int) ($driver['id'] ?? 0)) : ['lc' => [], 'rml' => [], 'dosaje' => [], 'manifestacion' => []];
        $driverLawyer = $driver !== [] ? first_row(load_abogados($pdo, $accidenteId, (int) ($driver['id'] ?? 0))) : [];
        $owners = $vehicle !== [] ? load_propietarios($pdo, $accidenteId, (int) ($vehicle['iv_id'] ?? 0)) : [];
        $owner = first_row($owners);
        $ownerLawyers = [];
        if (!empty($owner['propietario_persona_id'])) {
            $ownerLawyers = array_merge($ownerLawyers, load_abogados($pdo, $accidenteId, (int) $owner['propietario_persona_id']));
        }
        if (!empty($owner['representante_persona_id']) && (int) $owner['representante_persona_id'] !== (int) ($owner['propietario_persona_id'] ?? 0)) {
            $ownerLawyers = array_merge($ownerLawyers, load_abogados($pdo, $accidenteId, (int) $owner['representante_persona_id']));
        }
        fill_vehicle_template_markers($markers, $prefixes, $vehicle, $vehicleDoc, $driver, $driverDocs, $driverLawyer, $owner, first_row($ownerLawyers), $accidente);
    }

    return $markers;
}

function save_template_docx(string $templatePath, string $tmpPath, array $markers): void
{
    $template = new TemplateProcessor($templatePath);
    foreach ($markers as $key => $value) {
        $template->setValue($key, $value);
    }
    $template->saveAs($tmpPath);
}

$accidente = fetch_one($pdo, "
    SELECT a.*,
           d.nombre AS dep_nombre,
           p.nombre AS prov_nombre,
           u.nombre AS distrito_nombre,
           c.nombre AS comisaria_nombre,
           fa.nombre AS fiscalia_nombre,
           TRIM(CONCAT(COALESCE(fi.nombres, ''), ' ', COALESCE(fi.apellido_paterno, ''), ' ', COALESCE(fi.apellido_materno, ''))) AS fiscal_nombre
      FROM accidentes a
 LEFT JOIN ubigeo_departamento d ON d.cod_dep = a.cod_dep
 LEFT JOIN ubigeo_provincia p ON p.cod_dep = a.cod_dep AND p.cod_prov = a.cod_prov
 LEFT JOIN ubigeo_distrito u ON u.cod_dep = a.cod_dep AND u.cod_prov = a.cod_prov AND u.cod_dist = a.cod_dist
 LEFT JOIN comisarias c ON c.id = a.comisaria_id
 LEFT JOIN fiscalia fa ON fa.id = a.fiscalia_id
 LEFT JOIN fiscales fi ON fi.id = a.fiscal_id
     WHERE a.id = :id
     LIMIT 1
", [':id' => $accidenteId]);
if ($accidente === []) {
    http_response_code(404);
    exit('Accidente no encontrado');
}

$vehicleParams = [':a' => $accidenteId];
$vehicleFilter = '';
if ($vehiculoInvId > 0) {
    $vehicleFilter = ' AND iv.id = :iv';
    $vehicleParams[':iv'] = $vehiculoInvId;
}
$vehicles = fetch_all($pdo, "
    SELECT iv.id AS iv_id, iv.orden_participacion, iv.tipo AS involucrado_tipo, iv.observaciones AS involucrado_observaciones,
           v.id AS vehiculo_id, v.placa, v.serie_vin, v.nro_motor, v.anio, v.color, v.largo_mm, v.ancho_mm, v.alto_mm, v.notas,
           cv.codigo AS categoria_codigo, cv.descripcion AS categoria_descripcion,
           tv.codigo AS tipo_codigo, tv.nombre AS tipo_nombre,
           car.nombre AS carroceria_nombre,
           mar.nombre AS marca_nombre,
           modv.nombre AS modelo_nombre
      FROM involucrados_vehiculos iv
      JOIN vehiculos v ON v.id = iv.vehiculo_id
 LEFT JOIN categoria_vehiculos cv ON cv.id = v.categoria_id
 LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
 LEFT JOIN carroceria_vehiculo car ON car.id = v.carroceria_id
 LEFT JOIN marcas_vehiculo mar ON mar.id = v.marca_id
 LEFT JOIN modelos_vehiculo modv ON modv.id = v.modelo_id
     WHERE iv.accidente_id = :a {$vehicleFilter}
     ORDER BY FIELD(iv.orden_participacion, 'UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7'), iv.id ASC
", $vehicleParams);

if ($vehicles === []) {
    http_response_code(404);
    exit('No hay vehiculos involucrados para este accidente.');
}
if ($vehiculoInvId <= 0) {
    $vehicles = pick_combined_vehicles($vehicles);
}

$infpolRaw = trim((string) ($accidente['nro_informe_policial'] ?? ''));
$infpol = $infpolRaw !== '' ? $infpolRaw : (string) ($accidente['id'] ?? '0');
$infpol = preg_replace('/\s+/', '_', $infpol);
$infpol = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $infpol);
$filename = 'INFORME_COMBINADO_VEHICULAR_' . $infpol . '_' . date('Ymd_His') . '.docx';
$templatePath = __DIR__ . '/plantillas/word_informe_combinado_vehiculo.docx';
$useTemplate = is_file($templatePath) && (string) ($_GET['sin_plantilla'] ?? '') !== '1';
if ($useTemplate) {
    if (!class_exists(TemplateProcessor::class)) {
        http_response_code(500);
        exit('TemplateProcessor no esta disponible para usar la plantilla DOCX.');
    }

    $tmp = tempnam($tmpDir, 'infcombvt_');
    if ($tmp === false) {
        http_response_code(500);
        exit('No se pudo crear el archivo temporal.');
    }

    save_template_docx($templatePath, $tmp, build_template_markers($pdo, $accidenteId, $accidente, $vehicles));

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
$phpWord->addTableStyle('PairTable', [
    'borderSize' => 6,
    'borderColor' => 'D9DEE8',
    'cellMargin' => 90,
    'width' => 100 * 50,
    'unit' => 'pct',
]);

$section = $phpWord->addSection([
    'marginTop' => 900,
    'marginRight' => 900,
    'marginBottom' => 900,
    'marginLeft' => 900,
]);

$section->addText('INFORME POLICIAL - COMBINADO VEHICULAR', ['bold' => true, 'size' => 16, 'color' => '9A6A00'], ['alignment' => 'center']);
$section->addText('UIAT Norte', ['bold' => true, 'size' => 11], ['alignment' => 'center']);
$section->addTextBreak(1);

add_heading($section, '1. Datos generales del accidente');
add_pairs($section, [
    'Accidente ID' => $accidente['id'] ?? '',
    'SIDPOL' => $accidente['sidpol'] ?? '',
    'Registro SIDPOL' => $accidente['registro_sidpol'] ?? '',
    'Informe policial' => $accidente['nro_informe_policial'] ?? '',
    'Fecha del accidente' => date_pe($accidente['fecha_accidente'] ?? ''),
    'Hora del accidente' => time_pe($accidente['fecha_accidente'] ?? ''),
    'Fecha de comunicacion' => date_pe($accidente['fecha_comunicacion'] ?? ''),
    'Hora de comunicacion' => time_pe($accidente['fecha_comunicacion'] ?? ''),
    'Fecha de intervencion' => date_pe($accidente['fecha_intervencion'] ?? ''),
    'Hora de intervencion' => time_pe($accidente['fecha_intervencion'] ?? ''),
    'Lugar' => $accidente['lugar'] ?? '',
    'Referencia' => $accidente['referencia'] ?? '',
    'Distrito' => $accidente['distrito_nombre'] ?? '',
    'Provincia' => $accidente['prov_nombre'] ?? '',
    'Departamento' => $accidente['dep_nombre'] ?? '',
    'Comisaria' => $accidente['comisaria_nombre'] ?? '',
    'Fiscalia' => $accidente['fiscalia_nombre'] ?? '',
    'Fiscal' => $accidente['fiscal_nombre'] ?? '',
    'Modalidad' => load_modalidades($pdo, $accidenteId),
    'Consecuencia' => load_consecuencias($pdo, $accidenteId, $accidente),
    'Estado' => $accidente['estado'] ?? '',
    'Sentido' => $accidente['sentido'] ?? '',
    'Secuencia' => $accidente['secuencia'] ?? '',
]);

$singleVehicle = count($vehicles) === 1;
$vehicleNumber = 1;
foreach ($vehicles as $vehicle) {
    $vehicleTitle = textv($vehicle['orden_participacion'] ?? '') . ' - ' . textv($vehicle['placa'] ?? 'SIN PLACA');
    add_heading($section, '2.' . $vehicleNumber . ' Vehiculo del combinado: ' . $vehicleTitle);
    add_pairs($section, [
        'Orden de participacion' => $vehicle['orden_participacion'] ?? '',
        'Placa' => $vehicle['placa'] ?? '',
        'Marca' => $vehicle['marca_nombre'] ?? '',
        'Modelo' => $vehicle['modelo_nombre'] ?? '',
        'Categoria' => trim(textv($vehicle['categoria_codigo'] ?? '', '') . ' ' . textv($vehicle['categoria_descripcion'] ?? '', '')),
        'Tipo' => trim(textv($vehicle['tipo_codigo'] ?? '', '') . ' ' . textv($vehicle['tipo_nombre'] ?? '', '')),
        'Carroceria' => $vehicle['carroceria_nombre'] ?? '',
        'Color' => $vehicle['color'] ?? '',
        'Anio' => $vehicle['anio'] ?? '',
        'Serie VIN' => $vehicle['serie_vin'] ?? '',
        'Numero de motor' => $vehicle['nro_motor'] ?? '',
        'Largo / ancho / alto' => trim(textv($vehicle['largo_mm'] ?? '', '') . ' / ' . textv($vehicle['ancho_mm'] ?? '', '') . ' / ' . textv($vehicle['alto_mm'] ?? '', '')),
        'Tipo en accidente' => $vehicle['involucrado_tipo'] ?? '',
        'Observaciones' => $vehicle['involucrado_observaciones'] ?? ($vehicle['notas'] ?? ''),
    ]);

    add_heading($section, 'Conductores vinculados', 2);
    $conductors = load_conductores($pdo, $accidenteId, $vehicle, $singleVehicle);
    if ($conductors === []) {
        $section->addText('Sin conductor registrado para esta unidad.', ['italic' => true, 'size' => 9, 'color' => '666666']);
        $section->addTextBreak(1);
    }

    foreach ($conductors as $driverIndex => $driver) {
        $personaId = (int) ($driver['id'] ?? 0);
        $driverDocs = load_person_docs($pdo, $accidenteId, $personaId);
        $driverLawyers = load_abogados($pdo, $accidenteId, $personaId);

        $section->addText('Conductor #' . ($driverIndex + 1) . ': ' . name_person($driver), ['bold' => true, 'size' => 10, 'color' => '374151']);
        add_pairs($section, [
            'Rol' => $driver['rol_nombre'] ?? 'Conductor',
            'Lesion' => $driver['lesion'] ?? '',
            'Tipo y numero de documento' => trim(textv($driver['tipo_doc'] ?? '', '') . ' ' . textv($driver['num_doc'] ?? '', '')),
            'Sexo' => $driver['sexo'] ?? '',
            'Fecha de nacimiento' => date_pe($driver['fecha_nacimiento'] ?? ''),
            'Edad actual' => age_from($driver['fecha_nacimiento'] ?? ''),
            'Edad al accidente' => age_from($driver['fecha_nacimiento'] ?? '', $accidente['fecha_accidente'] ?? null),
            'Estado civil' => $driver['estado_civil'] ?? '',
            'Nacionalidad' => $driver['nacionalidad'] ?? '',
            'Nacimiento' => trim(textv($driver['departamento_nac'] ?? '', '') . ' / ' . textv($driver['provincia_nac'] ?? '', '') . ' / ' . textv($driver['distrito_nac'] ?? '', '')),
            'Domicilio' => $driver['domicilio'] ?? '',
            'Domicilio ubigeo' => trim(textv($driver['domicilio_departamento'] ?? '', '') . ' / ' . textv($driver['domicilio_provincia'] ?? '', '') . ' / ' . textv($driver['domicilio_distrito'] ?? '', '')),
            'Ocupacion' => $driver['ocupacion'] ?? '',
            'Grado de instruccion' => $driver['grado_instruccion'] ?? '',
            'Padre' => $driver['nombre_padre'] ?? '',
            'Madre' => $driver['nombre_madre'] ?? '',
            'Celular' => $driver['celular'] ?? '',
            'Email' => $driver['email'] ?? '',
            'Observaciones de participacion' => $driver['participacion_observaciones'] ?? '',
            'Notas de persona' => $driver['notas'] ?? '',
        ]);

        $section->addText('Licencia de conducir', ['bold' => true, 'size' => 10]);
        add_records($section, 'Sin licencia registrada.', $driverDocs['lc'], [
            'Clase' => 'clase',
            'Categoria' => 'categoria',
            'Numero' => 'numero',
            'Expedido por' => 'expedido_por',
            'Vigente desde' => static fn($r) => date_pe($r['vigente_desde'] ?? ''),
            'Vigente hasta' => static fn($r) => date_pe($r['vigente_hasta'] ?? ''),
            'Restricciones' => 'restricciones',
        ]);

        $section->addText('Certificado medico legal (RML)', ['bold' => true, 'size' => 10]);
        add_records($section, 'Sin RML registrado.', $driverDocs['rml'], [
            'Numero' => 'numero',
            'Fecha' => static fn($r) => date_pe($r['fecha'] ?? ''),
            'Incapacidad medico legal' => 'incapacidad_medico',
            'Atencion facultativa' => 'atencion_facultativo',
            'Observaciones' => 'observaciones',
        ]);

        $section->addText('Dosaje etilico', ['bold' => true, 'size' => 10]);
        add_records($section, 'Sin dosaje registrado.', $driverDocs['dosaje'], [
            'Numero' => 'numero',
            'Numero de registro' => 'numero_registro',
            'Fecha de extraccion' => static fn($r) => date_pe($r['fecha_extraccion'] ?? '') . ' ' . time_pe($r['fecha_extraccion'] ?? ''),
            'Resultado cualitativo' => 'resultado_cualitativo',
            'Resultado cuantitativo' => 'resultado_cuantitativo',
            'Lectura cuantitativa' => 'leer_cuantitativo',
            'Observaciones' => 'observaciones',
        ]);

        $section->addText('Manifestacion', ['bold' => true, 'size' => 10]);
        add_records($section, 'Sin manifestacion registrada.', $driverDocs['manifestacion'], [
            'Fecha' => static fn($r) => date_pe($r['fecha'] ?? ''),
            'Hora de inicio' => static fn($r) => time_pe($r['horario_inicio'] ?? ''),
            'Hora de termino' => static fn($r) => time_pe($r['hora_termino'] ?? ''),
            'Modalidad' => 'modalidad',
        ]);

        $section->addText('Abogado del conductor', ['bold' => true, 'size' => 10]);
        add_records($section, 'Sin abogado registrado para el conductor.', $driverLawyers, [
            'Condicion' => 'condicion',
            'Nombre' => static fn($r) => name_person($r),
            'Colegiatura' => 'colegiatura',
            'Registro' => 'registro',
            'Casilla electronica' => 'casilla_electronica',
            'Domicilio procesal' => 'domicilio_procesal',
            'Celular' => 'celular',
            'Email' => 'email',
        ]);
    }

    add_heading($section, 'Documentos del vehiculo', 2);
    add_records($section, 'Sin documentos vehiculares registrados.', load_vehiculo_docs($pdo, $vehicle), [
        'Tarjeta - numero de propiedad' => 'numero_propiedad',
        'Tarjeta - titulo' => 'titulo_propiedad',
        'Tarjeta - partida' => 'partida_propiedad',
        'Tarjeta - sede' => 'sede_propiedad',
        'SOAT - numero' => 'numero_soat',
        'SOAT - aseguradora' => 'aseguradora_soat',
        'SOAT - vigente desde' => static fn($r) => date_pe($r['vigente_soat'] ?? ''),
        'SOAT - vencimiento' => static fn($r) => date_pe($r['vencimiento_soat'] ?? ''),
        'CITV - numero' => 'numero_revision',
        'CITV - certificadora' => 'certificadora_revision',
        'CITV - vigente desde' => static fn($r) => date_pe($r['vigente_revision'] ?? ''),
        'CITV - vencimiento' => static fn($r) => date_pe($r['vencimiento_revision'] ?? ''),
        'Peritaje - numero' => 'numero_peritaje',
        'Peritaje - fecha' => static fn($r) => date_pe($r['fecha_peritaje'] ?? ''),
        'Peritaje - perito' => 'perito_peritaje',
        'Peritaje - sistema electrico' => 'sistema_electrico_peritaje',
        'Peritaje - frenos' => 'sistema_frenos_peritaje',
        'Peritaje - direccion' => 'sistema_direccion_peritaje',
        'Peritaje - transmision' => 'sistema_transmision_peritaje',
        'Peritaje - suspension' => 'sistema_suspension_peritaje',
        'Peritaje - planta motriz' => 'planta_motriz_peritaje',
        'Peritaje - otros' => 'otros_peritaje',
        'Peritaje - danos' => 'danos_peritaje',
    ]);

    add_heading($section, 'Propietario del vehiculo', 2);
    $owners = load_propietarios($pdo, $accidenteId, (int) $vehicle['iv_id']);
    if ($owners === []) {
        $section->addText('Sin propietario registrado para esta unidad.', ['italic' => true, 'size' => 9, 'color' => '666666']);
        $section->addTextBreak(1);
    }
    foreach ($owners as $ownerIndex => $owner) {
        $section->addText('Propietario #' . ($ownerIndex + 1), ['bold' => true, 'size' => 10]);
        add_pairs($section, [
            'Tipo de propietario' => $owner['tipo_propietario'] ?? '',
            'Propietario natural' => name_person($owner, 'nat_'),
            'Documento propietario natural' => trim(textv($owner['nat_tipo_doc'] ?? '', '') . ' ' . textv($owner['nat_num_doc'] ?? '', '')),
            'Domicilio propietario natural' => $owner['nat_domicilio'] ?? '',
            'Celular propietario natural' => $owner['nat_celular'] ?? '',
            'Email propietario natural' => $owner['nat_email'] ?? '',
            'RUC' => $owner['ruc'] ?? '',
            'Razon social' => $owner['razon_social'] ?? '',
            'Domicilio fiscal' => $owner['domicilio_fiscal'] ?? '',
            'Rol legal' => $owner['rol_legal'] ?? '',
            'Representante legal' => name_person($owner, 'rep_'),
            'Documento representante' => trim(textv($owner['rep_tipo_doc'] ?? '', '') . ' ' . textv($owner['rep_num_doc'] ?? '', '')),
            'Domicilio representante' => $owner['rep_domicilio'] ?? '',
            'Celular representante' => $owner['rep_celular'] ?? '',
            'Email representante' => $owner['rep_email'] ?? '',
            'Observaciones' => $owner['observaciones'] ?? '',
        ]);

        $ownerLawyers = [];
        if (!empty($owner['propietario_persona_id'])) {
            $ownerLawyers = array_merge($ownerLawyers, load_abogados($pdo, $accidenteId, (int) $owner['propietario_persona_id']));
        }
        if (!empty($owner['representante_persona_id']) && (int) $owner['representante_persona_id'] !== (int) ($owner['propietario_persona_id'] ?? 0)) {
            $ownerLawyers = array_merge($ownerLawyers, load_abogados($pdo, $accidenteId, (int) $owner['representante_persona_id']));
        }

        $section->addText('Abogado del propietario o representante', ['bold' => true, 'size' => 10]);
        add_records($section, 'Sin abogado registrado para propietario o representante.', $ownerLawyers, [
            'Condicion' => 'condicion',
            'Nombre' => static fn($r) => name_person($r),
            'Colegiatura' => 'colegiatura',
            'Registro' => 'registro',
            'Casilla electronica' => 'casilla_electronica',
            'Domicilio procesal' => 'domicilio_procesal',
            'Celular' => 'celular',
            'Email' => 'email',
        ]);
    }

    $vehicleNumber++;
}

$tmp = tempnam($tmpDir, 'infcombv_');
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
