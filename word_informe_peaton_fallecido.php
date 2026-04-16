<?php
declare(strict_types=1);

/* ===========================================================
   WORD: INFORME PEATON FALLECIDO
   Entrada: ?accidente_id=ID  (acepta ?id)
   Opcional: ?persona_inv_id=ID para limitar a un peaton fallecido.
   Si existe /plantillas/word_informe_peaton_fallecido.docx,
   usa esa plantilla. Si no existe, genera DOCX directo con PhpWord.
   =========================================================== */

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/word_manifestaciones_helper.php';

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
$personaInvId = (int) ($_GET['persona_inv_id'] ?? $_GET['involucrado_persona_id'] ?? 0);
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
        (string) ($row[$prefix . 'nombres'] ?? '') . ' ' .
        (string) ($row[$prefix . 'apellido_paterno'] ?? '') . ' ' .
        (string) ($row[$prefix . 'apellido_materno'] ?? '')
    ));
}

function doc_type_number(array $row, string $typeKey = 'tipo_doc', string $numKey = 'num_doc'): string
{
    return trim(textv($row[$typeKey] ?? '', '') . ' ' . textv($row[$numKey] ?? '', ''));
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
    if ($count === 0) return '-';
    if ($count === 1) return $items[0];
    if ($count === 2) return $items[0] . ' y ' . $items[1];
    return implode(', ', array_slice($items, 0, -1)) . ' y ' . $items[$count - 1];
}

function first_row(array $rows): array
{
    return $rows[0] ?? [];
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

function load_peatones_fallecidos(PDO $pdo, int $accidenteId, int $personaInvId = 0): array
{
    $params = [':a' => $accidenteId];
    $personFilter = '';
    if ($personaInvId > 0) {
        $personFilter = ' AND ip.id = :ip';
        $params[':ip'] = $personaInvId;
    }

    return fetch_all($pdo, "
        SELECT ip.id AS involucrado_persona_id, ip.orden_persona, ip.vehiculo_id, ip.lesion,
               ip.observaciones AS participacion_observaciones,
               pr.Nombre AS rol_nombre,
               p.*
         FROM involucrados_personas ip
          JOIN personas p ON p.id = ip.persona_id
     LEFT JOIN participacion_persona pr ON pr.Id = ip.rol_id
         WHERE ip.accidente_id = :a
           AND LOWER(COALESCE(ip.lesion, '')) LIKE '%falle%'
           AND (LOWER(COALESCE(pr.Nombre, '')) LIKE '%peat%' OR ip.rol_id = 2 OR ip.vehiculo_id IS NULL OR ip.vehiculo_id = 0)
           {$personFilter}
         ORDER BY ip.id ASC
    ", $params);
}

function load_occiso_doc(PDO $pdo, int $accidenteId, ?int $personaId): array
{
    if (!$personaId) {
        return [];
    }
    return first_row(fetch_all($pdo, "
        SELECT *
          FROM documento_occiso
         WHERE accidente_id = :a
           AND persona_id = :p
         ORDER BY id DESC
    ", [':a' => $accidenteId, ':p' => $personaId]));
}

function load_familiar(PDO $pdo, int $accidenteId, ?int $fallecidoInvId): array
{
    if (!$fallecidoInvId) {
        return [];
    }
    return first_row(fetch_all($pdo, "
        SELECT ff.*,
               p.tipo_doc, p.num_doc, p.apellido_paterno, p.apellido_materno, p.nombres,
               p.sexo, p.fecha_nacimiento, p.edad, p.estado_civil, p.nacionalidad,
               p.departamento_nac, p.provincia_nac, p.distrito_nac,
               p.domicilio, p.domicilio_departamento, p.domicilio_provincia, p.domicilio_distrito,
               p.ocupacion, p.grado_instruccion, p.nombre_padre, p.nombre_madre,
               p.celular, p.email, p.notas, p.creado_en, p.foto_path, p.api_fuente, p.api_ref
          FROM familiar_fallecido ff
     LEFT JOIN personas p ON p.id = ff.familiar_persona_id
         WHERE ff.accidente_id = :a
           AND ff.fallecido_inv_id = :inv
         ORDER BY ff.id DESC
    ", [':a' => $accidenteId, ':inv' => $fallecidoInvId]));
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

function fill_fallecido_template_markers(array &$markers, array $prefixes, array $fallecido, array $fallecidoLawyer, array $occiso, array $familiar, array $familiarLawyer, array $accidente): void
{
    foreach ([
        'fall_nombre' => name_person($fallecido),
        'fall_rol' => $fallecido['rol_nombre'] ?? '',
        'fall_lesion' => $fallecido['lesion'] ?? '',
        'fall_doc_tipo' => $fallecido['tipo_doc'] ?? '',
        'fall_doc_num' => $fallecido['num_doc'] ?? '',
        'fall_doc' => doc_type_number($fallecido),
        'fall_sexo' => $fallecido['sexo'] ?? '',
        'fall_fecha_nacimiento' => date_pe($fallecido['fecha_nacimiento'] ?? ''),
        'fall_edad' => age_from($fallecido['fecha_nacimiento'] ?? ''),
        'fall_edad_registrada' => $fallecido['edad'] ?? '',
        'fall_edad_accidente' => age_from($fallecido['fecha_nacimiento'] ?? '', $accidente['fecha_accidente'] ?? null),
        'fall_estado_civil' => $fallecido['estado_civil'] ?? '',
        'fall_nacionalidad' => $fallecido['nacionalidad'] ?? '',
        'fall_nacimiento' => trim(textv($fallecido['departamento_nac'] ?? '', '') . ' / ' . textv($fallecido['provincia_nac'] ?? '', '') . ' / ' . textv($fallecido['distrito_nac'] ?? '', '')),
        'fall_domicilio' => $fallecido['domicilio'] ?? '',
        'fall_domicilio_ubigeo' => trim(textv($fallecido['domicilio_departamento'] ?? '', '') . ' / ' . textv($fallecido['domicilio_provincia'] ?? '', '') . ' / ' . textv($fallecido['domicilio_distrito'] ?? '', '')),
        'fall_ocupacion' => $fallecido['ocupacion'] ?? '',
        'fall_grado_instruccion' => $fallecido['grado_instruccion'] ?? '',
        'fall_padre' => $fallecido['nombre_padre'] ?? '',
        'fall_madre' => $fallecido['nombre_madre'] ?? '',
        'fall_celular' => $fallecido['celular'] ?? '',
        'fall_email' => $fallecido['email'] ?? '',
        'fall_notas' => $fallecido['notas'] ?? '',
        'fall_creado_en' => $fallecido['creado_en'] ?? '',
        'fall_foto_path' => $fallecido['foto_path'] ?? '',
        'fall_api_fuente' => $fallecido['api_fuente'] ?? '',
        'fall_api_ref' => $fallecido['api_ref'] ?? '',
        'fall_observaciones' => $fallecido['participacion_observaciones'] ?? '',
        'fall_abog_nombre' => name_person($fallecidoLawyer),
        'fall_abog_condicion' => $fallecidoLawyer['condicion'] ?? '',
        'fall_abog_colegiatura' => $fallecidoLawyer['colegiatura'] ?? '',
        'fall_abog_registro' => $fallecidoLawyer['registro'] ?? '',
        'fall_abog_casilla' => $fallecidoLawyer['casilla_electronica'] ?? '',
        'fall_abog_domicilio_procesal' => $fallecidoLawyer['domicilio_procesal'] ?? '',
        'fall_abog_celular' => $fallecidoLawyer['celular'] ?? '',
        'fall_abog_email' => $fallecidoLawyer['email'] ?? '',
        'occ_fecha_levantamiento' => date_pe($occiso['fecha_levantamiento'] ?? ''),
        'occ_hora_levantamiento' => time_pe($occiso['hora_levantamiento'] ?? ''),
        'occ_lugar_levantamiento' => $occiso['lugar_levantamiento'] ?? '',
        'occ_posicion_cuerpo' => $occiso['posicion_cuerpo_levantamiento'] ?? '',
        'occ_lesiones_levantamiento' => $occiso['lesiones_levantamiento'] ?? '',
        'occ_presuntivo_levantamiento' => $occiso['presuntivo_levantamiento'] ?? '',
        'occ_legista_levantamiento' => $occiso['legista_levantamiento'] ?? '',
        'occ_cmp_legista' => $occiso['cmp_legista'] ?? '',
        'occ_observaciones_levantamiento' => $occiso['observaciones_levantamiento'] ?? '',
        'occ_numero_pericial' => $occiso['numero_pericial'] ?? '',
        'occ_fecha_pericial' => date_pe($occiso['fecha_pericial'] ?? ''),
        'occ_hora_pericial' => time_pe($occiso['hora_pericial'] ?? ''),
        'occ_observaciones_pericial' => $occiso['observaciones_pericial'] ?? '',
        'occ_numero_protocolo' => $occiso['numero_protocolo'] ?? '',
        'occ_fecha_protocolo' => date_pe($occiso['fecha_protocolo'] ?? ''),
        'occ_hora_protocolo' => time_pe($occiso['hora_protocolo'] ?? ''),
        'occ_lesiones_protocolo' => $occiso['lesiones_protocolo'] ?? '',
        'occ_presuntivo_protocolo' => $occiso['presuntivo_protocolo'] ?? '',
        'occ_dosaje_protocolo' => $occiso['dosaje_protocolo'] ?? '',
        'occ_toxicologico_protocolo' => $occiso['toxicologico_protocolo'] ?? '',
        'occ_nosocomio_epicrisis' => $occiso['nosocomio_epicrisis'] ?? '',
        'occ_numero_historia_epicrisis' => $occiso['numero_historia_epicrisis'] ?? '',
        'occ_tratamiento_epicrisis' => $occiso['tratamiento_epicrisis'] ?? '',
        'occ_hora_alta_epicrisis' => time_pe($occiso['hora_alta_epicrisis'] ?? ''),
        'fam_parentesco' => $familiar['parentesco'] ?? '',
        'fam_nombre' => name_person($familiar),
        'fam_doc_tipo' => $familiar['tipo_doc'] ?? '',
        'fam_doc_num' => $familiar['num_doc'] ?? '',
        'fam_doc' => doc_type_number($familiar),
        'fam_sexo' => $familiar['sexo'] ?? '',
        'fam_fecha_nacimiento' => date_pe($familiar['fecha_nacimiento'] ?? ''),
        'fam_edad' => age_from($familiar['fecha_nacimiento'] ?? ''),
        'fam_edad_registrada' => $familiar['edad'] ?? '',
        'fam_estado_civil' => $familiar['estado_civil'] ?? '',
        'fam_nacionalidad' => $familiar['nacionalidad'] ?? '',
        'fam_nacimiento' => trim(textv($familiar['departamento_nac'] ?? '', '') . ' / ' . textv($familiar['provincia_nac'] ?? '', '') . ' / ' . textv($familiar['distrito_nac'] ?? '', '')),
        'fam_domicilio' => $familiar['domicilio'] ?? '',
        'fam_domicilio_ubigeo' => trim(textv($familiar['domicilio_departamento'] ?? '', '') . ' / ' . textv($familiar['domicilio_provincia'] ?? '', '') . ' / ' . textv($familiar['domicilio_distrito'] ?? '', '')),
        'fam_ocupacion' => $familiar['ocupacion'] ?? '',
        'fam_grado_instruccion' => $familiar['grado_instruccion'] ?? '',
        'fam_padre' => $familiar['nombre_padre'] ?? '',
        'fam_madre' => $familiar['nombre_madre'] ?? '',
        'fam_celular' => $familiar['celular'] ?? '',
        'fam_email' => $familiar['email'] ?? '',
        'fam_notas' => $familiar['notas'] ?? '',
        'fam_creado_en' => $familiar['creado_en'] ?? '',
        'fam_foto_path' => $familiar['foto_path'] ?? '',
        'fam_api_fuente' => $familiar['api_fuente'] ?? '',
        'fam_api_ref' => $familiar['api_ref'] ?? '',
        'fam_observaciones' => $familiar['observaciones'] ?? '',
        'fam_abog_nombre' => name_person($familiarLawyer),
        'fam_abog_condicion' => $familiarLawyer['condicion'] ?? '',
        'fam_abog_colegiatura' => $familiarLawyer['colegiatura'] ?? '',
        'fam_abog_registro' => $familiarLawyer['registro'] ?? '',
        'fam_abog_casilla' => $familiarLawyer['casilla_electronica'] ?? '',
        'fam_abog_domicilio_procesal' => $familiarLawyer['domicilio_procesal'] ?? '',
        'fam_abog_celular' => $familiarLawyer['celular'] ?? '',
        'fam_abog_email' => $familiarLawyer['email'] ?? '',
    ] as $suffix => $value) {
        set_marker_aliases($markers, $prefixes, $suffix, $value);
    }
}

function build_template_markers(PDO $pdo, int $accidenteId, array $accidente, array $peatones): array
{
    $markers = [];
    foreach ([
        'titulo_informe' => 'INFORME POLICIAL - PEATON FALLECIDO',
        'generado_fecha' => date('d/m/Y H:i'),
        'acc_id' => $accidente['id'] ?? '',
        'acc_sidpol' => $accidente['sidpol'] ?? '',
        'acc_registro_sidpol' => $accidente['registro_sidpol'] ?? '',
        'acc_nro_informe_policial' => $accidente['nro_informe_policial'] ?? '',
        'acc_fecha' => date_pe($accidente['fecha_accidente'] ?? ''),
        'acc_hora' => time_pe($accidente['fecha_accidente'] ?? ''),
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

    for ($i = 1; $i <= 7; $i++) {
        $fallecido = $peatones[$i - 1] ?? [];
        $prefixes = ['p' . $i . '_'];
        if ($i === 1) {
            $prefixes[] = '';
        }
        $fallecidoLawyer = $fallecido !== [] ? first_row(load_abogados($pdo, $accidenteId, (int) ($fallecido['id'] ?? 0))) : [];
        $occiso = $fallecido !== [] ? load_occiso_doc($pdo, $accidenteId, (int) ($fallecido['id'] ?? 0)) : [];
        $familiar = $fallecido !== [] ? load_familiar($pdo, $accidenteId, (int) ($fallecido['involucrado_persona_id'] ?? 0)) : [];
        $familiarLawyer = $familiar !== [] ? first_row(load_abogados($pdo, $accidenteId, (int) ($familiar['familiar_persona_id'] ?? 0))) : [];
        fill_fallecido_template_markers($markers, $prefixes, $fallecido, $fallecidoLawyer, $occiso, $familiar, $familiarLawyer, $accidente);
        word_manifestation_set_array($markers, $prefixes, 'fall_man', word_manifestation_first($pdo, $accidenteId, (int) ($fallecido['id'] ?? 0)));
        word_manifestation_set_array($markers, $prefixes, 'fam_man', word_manifestation_first($pdo, $accidenteId, (int) ($familiar['familiar_persona_id'] ?? 0)));
    }

    word_manifestation_fill_global_array($markers, $pdo, $accidenteId);

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

$peatones = load_peatones_fallecidos($pdo, $accidenteId, $personaInvId);
if ($peatones === []) {
    http_response_code(404);
    exit('No hay peaton fallecido registrado para este accidente.');
}

$infpolRaw = trim((string) ($accidente['nro_informe_policial'] ?? ''));
$infpol = $infpolRaw !== '' ? $infpolRaw : (string) ($accidente['id'] ?? '0');
$infpol = preg_replace('/\s+/', '_', $infpol);
$infpol = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $infpol);
$filename = 'INFORME_PEATON_FALLECIDO_' . $infpol . '_' . date('Ymd_His') . '.docx';
$templatePath = __DIR__ . '/plantillas/word_informe_peaton_fallecido.docx';
$useTemplate = is_file($templatePath) && (string) ($_GET['sin_plantilla'] ?? '') !== '1';
if ($useTemplate) {
    if (!class_exists(TemplateProcessor::class)) {
        http_response_code(500);
        exit('TemplateProcessor no esta disponible para usar la plantilla DOCX.');
    }

    $tmp = tempnam($tmpDir, 'infpeatft_');
    if ($tmp === false) {
        http_response_code(500);
        exit('No se pudo crear el archivo temporal.');
    }

    save_template_docx($templatePath, $tmp, build_template_markers($pdo, $accidenteId, $accidente, $peatones));

    while (ob_get_level()) { @ob_end_clean(); }
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

$section = $phpWord->addSection(['marginTop' => 900, 'marginRight' => 900, 'marginBottom' => 900, 'marginLeft' => 900]);
$section->addText('INFORME POLICIAL - PEATON FALLECIDO', ['bold' => true, 'size' => 16, 'color' => '9A6A00'], ['alignment' => 'center']);
$section->addText('UIAT Norte', ['bold' => true, 'size' => 11], ['alignment' => 'center']);
$section->addTextBreak(1);

add_heading($section, '1. Datos generales del accidente');
add_pairs($section, [
    'Accidente ID' => $accidente['id'] ?? '',
    'SIDPOL' => $accidente['sidpol'] ?? '',
    'Registro SIDPOL' => $accidente['registro_sidpol'] ?? '',
    'Informe policial' => $accidente['nro_informe_policial'] ?? '',
    'Fecha y hora del accidente' => date_pe($accidente['fecha_accidente'] ?? '') . ' ' . time_pe($accidente['fecha_accidente'] ?? ''),
    'Lugar' => $accidente['lugar'] ?? '',
    'Referencia' => $accidente['referencia'] ?? '',
    'Distrito / provincia / departamento' => textv($accidente['distrito_nombre'] ?? '') . ' / ' . textv($accidente['prov_nombre'] ?? '') . ' / ' . textv($accidente['dep_nombre'] ?? ''),
    'Comisaria' => $accidente['comisaria_nombre'] ?? '',
    'Fiscalia' => $accidente['fiscalia_nombre'] ?? '',
    'Fiscal' => $accidente['fiscal_nombre'] ?? '',
    'Modalidad' => load_modalidades($pdo, $accidenteId),
    'Consecuencia' => load_consecuencias($pdo, $accidenteId, $accidente),
    'Sentido' => $accidente['sentido'] ?? '',
    'Secuencia' => $accidente['secuencia'] ?? '',
]);

foreach ($peatones as $idx => $fallecido) {
    $occiso = load_occiso_doc($pdo, $accidenteId, (int) ($fallecido['id'] ?? 0));
    $familiar = load_familiar($pdo, $accidenteId, (int) ($fallecido['involucrado_persona_id'] ?? 0));
    $fallecidoLawyer = first_row(load_abogados($pdo, $accidenteId, (int) ($fallecido['id'] ?? 0)));
    $familiarLawyer = $familiar !== [] ? first_row(load_abogados($pdo, $accidenteId, (int) ($familiar['familiar_persona_id'] ?? 0))) : [];

    add_heading($section, '2.' . ($idx + 1) . ' Peaton fallecido: ' . name_person($fallecido));
    add_pairs($section, [
        'Rol / lesion' => textv($fallecido['rol_nombre'] ?? '') . ' / ' . textv($fallecido['lesion'] ?? ''),
        'Documento' => doc_type_number($fallecido),
        'Sexo' => $fallecido['sexo'] ?? '',
        'Nacimiento / edad registrada / edad al accidente' => date_pe($fallecido['fecha_nacimiento'] ?? '') . ' / ' . textv($fallecido['edad'] ?? '') . ' / ' . age_from($fallecido['fecha_nacimiento'] ?? '', $accidente['fecha_accidente'] ?? null),
        'Estado civil / nacionalidad' => textv($fallecido['estado_civil'] ?? '') . ' / ' . textv($fallecido['nacionalidad'] ?? ''),
        'Nacimiento' => textv($fallecido['departamento_nac'] ?? '') . ' / ' . textv($fallecido['provincia_nac'] ?? '') . ' / ' . textv($fallecido['distrito_nac'] ?? ''),
        'Domicilio' => $fallecido['domicilio'] ?? '',
        'Domicilio ubigeo' => textv($fallecido['domicilio_departamento'] ?? '') . ' / ' . textv($fallecido['domicilio_provincia'] ?? '') . ' / ' . textv($fallecido['domicilio_distrito'] ?? ''),
        'Ocupacion / instruccion' => textv($fallecido['ocupacion'] ?? '') . ' / ' . textv($fallecido['grado_instruccion'] ?? ''),
        'Padre / madre' => textv($fallecido['nombre_padre'] ?? '') . ' / ' . textv($fallecido['nombre_madre'] ?? ''),
        'Celular / email' => textv($fallecido['celular'] ?? '') . ' / ' . textv($fallecido['email'] ?? ''),
        'Notas de persona' => $fallecido['notas'] ?? '',
        'Foto / fuente API' => textv($fallecido['foto_path'] ?? '') . ' / ' . textv($fallecido['api_fuente'] ?? '') . ' ' . textv($fallecido['api_ref'] ?? ''),
        'Observaciones' => $fallecido['participacion_observaciones'] ?? '',
    ]);

    add_heading($section, 'Abogado del peaton fallecido', 2);
    add_pairs($section, [
        'Nombre' => name_person($fallecidoLawyer),
        'Condicion' => $fallecidoLawyer['condicion'] ?? '',
        'Colegiatura / registro' => textv($fallecidoLawyer['colegiatura'] ?? '') . ' / ' . textv($fallecidoLawyer['registro'] ?? ''),
        'Casilla electronica' => $fallecidoLawyer['casilla_electronica'] ?? '',
        'Domicilio procesal' => $fallecidoLawyer['domicilio_procesal'] ?? '',
        'Celular / email' => textv($fallecidoLawyer['celular'] ?? '') . ' / ' . textv($fallecidoLawyer['email'] ?? ''),
    ]);

    add_heading($section, 'Acta de levantamiento de cadaver', 2);
    add_pairs($section, [
        'Fecha y hora' => date_pe($occiso['fecha_levantamiento'] ?? '') . ' ' . time_pe($occiso['hora_levantamiento'] ?? ''),
        'Lugar' => $occiso['lugar_levantamiento'] ?? '',
        'Posicion del cuerpo' => $occiso['posicion_cuerpo_levantamiento'] ?? '',
        'Lesiones' => $occiso['lesiones_levantamiento'] ?? '',
        'Diagnostico presuntivo' => $occiso['presuntivo_levantamiento'] ?? '',
        'Medico legista' => $occiso['legista_levantamiento'] ?? '',
        'CMP legista' => $occiso['cmp_legista'] ?? '',
        'Observaciones' => $occiso['observaciones_levantamiento'] ?? '',
    ]);

    add_heading($section, 'Informe pericial de recepcion de cadaver', 2);
    add_pairs($section, [
        'Numero pericial' => $occiso['numero_pericial'] ?? '',
        'Fecha y hora pericial' => date_pe($occiso['fecha_pericial'] ?? '') . ' ' . time_pe($occiso['hora_pericial'] ?? ''),
        'Observaciones periciales' => $occiso['observaciones_pericial'] ?? '',
    ]);

    add_heading($section, 'Protocolo de necropsia', 2);
    add_pairs($section, [
        'Numero de protocolo' => $occiso['numero_protocolo'] ?? '',
        'Fecha y hora de protocolo' => date_pe($occiso['fecha_protocolo'] ?? '') . ' ' . time_pe($occiso['hora_protocolo'] ?? ''),
        'Lesiones de protocolo' => $occiso['lesiones_protocolo'] ?? '',
        'Diagnostico presuntivo' => $occiso['presuntivo_protocolo'] ?? '',
        'Dosaje' => $occiso['dosaje_protocolo'] ?? '',
        'Toxicologico' => $occiso['toxicologico_protocolo'] ?? '',
    ]);

    add_heading($section, 'Epicrisis', 2);
    add_pairs($section, [
        'Nosocomio' => $occiso['nosocomio_epicrisis'] ?? '',
        'Numero de historia' => $occiso['numero_historia_epicrisis'] ?? '',
        'Tratamiento' => $occiso['tratamiento_epicrisis'] ?? '',
        'Hora de alta' => time_pe($occiso['hora_alta_epicrisis'] ?? ''),
    ]);

    add_heading($section, 'Familiar del fallecido', 2);
    add_pairs($section, [
        'Parentesco' => $familiar['parentesco'] ?? '',
        'Nombre' => name_person($familiar),
        'Documento' => doc_type_number($familiar),
        'Sexo' => $familiar['sexo'] ?? '',
        'Nacimiento / edad registrada / edad actual' => date_pe($familiar['fecha_nacimiento'] ?? '') . ' / ' . textv($familiar['edad'] ?? '') . ' / ' . age_from($familiar['fecha_nacimiento'] ?? ''),
        'Estado civil / nacionalidad' => textv($familiar['estado_civil'] ?? '') . ' / ' . textv($familiar['nacionalidad'] ?? ''),
        'Domicilio' => $familiar['domicilio'] ?? '',
        'Ocupacion / instruccion' => textv($familiar['ocupacion'] ?? '') . ' / ' . textv($familiar['grado_instruccion'] ?? ''),
        'Padre / madre' => textv($familiar['nombre_padre'] ?? '') . ' / ' . textv($familiar['nombre_madre'] ?? ''),
        'Celular / email' => textv($familiar['celular'] ?? '') . ' / ' . textv($familiar['email'] ?? ''),
        'Notas de persona' => $familiar['notas'] ?? '',
        'Foto / fuente API' => textv($familiar['foto_path'] ?? '') . ' / ' . textv($familiar['api_fuente'] ?? '') . ' ' . textv($familiar['api_ref'] ?? ''),
        'Observaciones' => $familiar['observaciones'] ?? '',
    ]);

    add_heading($section, 'Abogado del familiar', 2);
    add_pairs($section, [
        'Nombre' => name_person($familiarLawyer),
        'Condicion' => $familiarLawyer['condicion'] ?? '',
        'Colegiatura / registro' => textv($familiarLawyer['colegiatura'] ?? '') . ' / ' . textv($familiarLawyer['registro'] ?? ''),
        'Casilla electronica' => $familiarLawyer['casilla_electronica'] ?? '',
        'Domicilio procesal' => $familiarLawyer['domicilio_procesal'] ?? '',
        'Celular / email' => textv($familiarLawyer['celular'] ?? '') . ' / ' . textv($familiarLawyer['email'] ?? ''),
    ]);
}

$tmp = tempnam($tmpDir, 'infpeatf_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear el archivo temporal.');
}

IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

while (ob_get_level()) { @ob_end_clean(); }
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
