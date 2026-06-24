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

function im_clean(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function im_normalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Ã¡' => 'a', 'Ã©' => 'e', 'Ã­' => 'i', 'Ã³' => 'o', 'Ãº' => 'u', 'Ã±' => 'n',
    ]);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text);
}

function im_fecha_larga(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return date('j', $time) . ' de ' . $months[(int) date('n', $time) - 1] . ' de ' . date('Y', $time);
}

function im_fecha_abrev(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];
    return strtoupper(date('d', $time) . $months[(int) date('n', $time)] . date('Y', $time));
}

$oficioId = (int) ($_GET['oficio_id'] ?? 0);
if ($oficioId <= 0) {
    http_response_code(400);
    exit('Falta oficio_id.');
}

$template = __DIR__ . '/plantillas/oficio_informe_medico.docx';
if (!is_file($template)) {
    http_response_code(500);
    exit('No se encuentra la plantilla: plantillas/oficio_informe_medico.docx');
}

$st = $pdo->prepare("
SELECT o.*, e.nombre AS entidad_nombre, COALESCE(e.siglas, '') AS entidad_siglas,
       s.nombre AS asunto_nombre, COALESCE(s.detalle, '') AS asunto_detalle,
       se.nombre AS subentidad_nombre,
       pe.nombres AS destino_nombres, pe.apellido_paterno AS destino_apep, COALESCE(pe.apellido_materno, '') AS destino_apem,
       ac.registro_sidpol, ac.sidpol, ac.lugar, ac.referencia AS accidente_referencia, ac.sentido, ac.fecha_accidente,
       c.nombre AS comisaria_nombre,
       fa.nombre AS fiscalia_nombre, fa.direccion AS fiscalia_direccion, fa.telefono AS fiscalia_telefono, fa.correo AS fiscalia_correo,
       fi.nombres AS fiscal_nombres, fi.apellido_paterno AS fiscal_apellido_paterno,
       COALESCE(fi.apellido_materno, '') AS fiscal_apellido_materno, fi.dni AS fiscal_dni,
       fi.telefono AS fiscal_telefono, fi.correo AS fiscal_correo, fi.cargo AS fiscal_cargo,
       ud.nombre AS accidente_distrito,
       ao.nombre AS nombre_oficial_ano, gc.nombre AS grado_cargo_nombre, gc.abreviatura AS grado_cargo_abrev,
       inv.id AS involucrado_id, inv.lesion AS persona_lesion, inv.observaciones AS persona_observaciones,
       inv.orden_persona, pp.Nombre AS persona_rol,
       p.nombres AS persona_nombres, p.apellido_paterno AS persona_apellido_paterno,
       COALESCE(p.apellido_materno, '') AS persona_apellido_materno, p.tipo_doc AS persona_tipo_doc,
       p.num_doc AS persona_num_doc, p.fecha_nacimiento AS persona_fecha_nacimiento, p.edad AS persona_edad,
       p.sexo AS persona_sexo, p.estado_civil AS persona_estado_civil, p.nacionalidad AS persona_nacionalidad,
       p.domicilio AS persona_domicilio, p.ocupacion AS persona_ocupacion,
       p.grado_instruccion AS persona_grado_instruccion, p.celular AS persona_celular, p.email AS persona_email
FROM oficios o
LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino
LEFT JOIN oficio_asunto s ON s.id = o.asunto_id
LEFT JOIN oficio_subentidad se ON se.id = o.subentidad_destino_id
LEFT JOIN oficio_persona_entidad pe ON pe.id = o.persona_destino_id
LEFT JOIN accidentes ac ON ac.id = o.accidente_id
LEFT JOIN comisarias c ON c.id = ac.comisaria_id
LEFT JOIN fiscalia fa ON fa.id = ac.fiscalia_id
LEFT JOIN fiscales fi ON fi.id = ac.fiscal_id
LEFT JOIN ubigeo_distrito ud ON ud.cod_dep = ac.cod_dep AND ud.cod_prov = ac.cod_prov AND ud.cod_dist = ac.cod_dist
LEFT JOIN oficio_oficial_ano ao ON ao.id = o.oficial_ano_id
LEFT JOIN grado_cargo gc ON gc.id = o.grado_cargo_id
LEFT JOIN involucrados_personas inv ON inv.id = o.involucrado_persona_id AND inv.accidente_id = o.accidente_id
LEFT JOIN personas p ON p.id = inv.persona_id
LEFT JOIN participacion_persona pp ON pp.Id = inv.rol_id
WHERE o.id = ?
LIMIT 1");
$st->execute([$oficioId]);
$oficio = $st->fetch(PDO::FETCH_ASSOC);
if (!$oficio) {
    http_response_code(404);
    exit('Oficio no encontrado.');
}

$match = im_normalize(($oficio['asunto_nombre'] ?? '') . ' ' . ($oficio['asunto_detalle'] ?? ''));
if (!str_contains($match, 'informe') || !str_contains($match, 'medico')) {
    http_response_code(422);
    exit('Este oficio no corresponde al asunto Informe medico.');
}
if (empty($oficio['involucrado_id'])) {
    http_response_code(422);
    exit('El oficio no tiene una persona involucrada seleccionada.');
}
$lesion = im_normalize((string) ($oficio['persona_lesion'] ?? ''));
if (!str_contains($lesion, 'herid') && !str_contains($lesion, 'lesion') && !str_contains($lesion, 'fallec')) {
    http_response_code(422);
    exit('La persona seleccionada no figura como herida, lesionada o fallecida.');
}

$st = $pdo->prepare('SELECT ma.nombre FROM accidente_modalidad am JOIN modalidad_accidente ma ON ma.id = am.modalidad_id WHERE am.accidente_id = ? ORDER BY ma.id');
$st->execute([(int) $oficio['accidente_id']]);
$modalidades = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
$st = $pdo->prepare('SELECT ca.nombre FROM accidente_consecuencia ac JOIN consecuencia_accidente ca ON ca.id = ac.consecuencia_id WHERE ac.accidente_id = ? ORDER BY ca.id');
$st->execute([(int) $oficio['accidente_id']]);
$consecuencias = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

$destinoPersona = im_clean(($oficio['destino_nombres'] ?? '') . ' ' . ($oficio['destino_apep'] ?? '') . ' ' . ($oficio['destino_apem'] ?? ''));
if ($destinoPersona === '') {
    $destinoPersona = im_clean($oficio['persona_destino_manual'] ?? '');
}
$personaNombre = im_clean(($oficio['persona_nombres'] ?? '') . ' ' . ($oficio['persona_apellido_paterno'] ?? '') . ' ' . ($oficio['persona_apellido_materno'] ?? ''));
$personaApellidos = im_clean(($oficio['persona_apellido_paterno'] ?? '') . ' ' . ($oficio['persona_apellido_materno'] ?? ''));
$fiscalNombre = im_clean(($oficio['fiscal_nombres'] ?? '') . ' ' . ($oficio['fiscal_apellido_paterno'] ?? '') . ' ' . ($oficio['fiscal_apellido_materno'] ?? ''));
$accidenteLugarCompleto = im_clean(implode(' - ', array_filter([
    $oficio['lugar'] ?? '', $oficio['accidente_referencia'] ?? '', $oficio['accidente_distrito'] ?? '', $oficio['comisaria_nombre'] ?? '',
], static fn(mixed $value): bool => im_clean($value) !== '')));
$numeroCompleto = im_clean(($oficio['numero'] ?? '') . '-' . ($oficio['anio'] ?? ''));

$values = [
    'nombre_oficial_ano' => $oficio['nombre_oficial_ano'] ?? '',
    'oficio_numero' => $oficio['numero'] ?? '',
    'oficio_anio' => $oficio['anio'] ?? '',
    'oficio_numero_completo' => $numeroCompleto,
    'oficio_fecha' => im_fecha_larga($oficio['fecha_emision'] ?? null),
    'oficio_fecha_abrev' => im_fecha_abrev($oficio['fecha_emision'] ?? null),
    'oficio_motivo' => $oficio['motivo'] ?? '',
    'oficio_referencia' => $oficio['referencia_texto'] ?? '',
    'oficio_entidad_nombre' => $oficio['entidad_nombre'] ?? '',
    'oficio_entidad_siglas' => $oficio['entidad_siglas'] ?? '',
    'oficio_subentidad_nombre' => $oficio['subentidad_nombre'] ?? '',
    'oficio_persona_destino' => $destinoPersona,
    'oficio_grado_cargo' => im_clean(($oficio['grado_cargo_nombre'] ?? '') . ' ' . ($oficio['grado_cargo_abrev'] ?? '')),
    'grado_cargo_nombre' => $oficio['grado_cargo_nombre'] ?? '',
    'asunto_nombre' => $oficio['asunto_nombre'] ?? '',
    'asunto_detalle' => $oficio['asunto_detalle'] ?? '',
    'accidente_id' => $oficio['accidente_id'] ?? '',
    'accidente_sidpol' => $oficio['registro_sidpol'] ?: ($oficio['sidpol'] ?? ''),
    'accidente_fecha' => im_fecha_larga($oficio['fecha_accidente'] ?? null),
    'accidente_fecha_abrev' => im_fecha_abrev($oficio['fecha_accidente'] ?? null),
    'accidente_hora' => !empty($oficio['fecha_accidente']) && strtotime((string) $oficio['fecha_accidente']) ? date('H:i', strtotime((string) $oficio['fecha_accidente'])) : '',
    'accidente_lugar' => $oficio['lugar'] ?? '',
    'accidente_lugar_completo' => $accidenteLugarCompleto,
    'accidente_referencia' => $oficio['accidente_referencia'] ?? '',
    'accidente_distrito' => $oficio['accidente_distrito'] ?? '',
    'accidente_sentido' => $oficio['sentido'] ?? '',
    'accidente_modalidades' => implode(', ', array_map('im_clean', $modalidades)),
    'accidente_consecuencias' => implode(', ', array_map('im_clean', $consecuencias)),
    'comisaria_nombre' => $oficio['comisaria_nombre'] ?? '',
    'fiscalia_nombre' => $oficio['fiscalia_nombre'] ?? '',
    'fiscalia_direccion' => $oficio['fiscalia_direccion'] ?? '',
    'fiscalia_telefono' => $oficio['fiscalia_telefono'] ?? '',
    'fiscalia_correo' => $oficio['fiscalia_correo'] ?? '',
    'fiscal_nombre' => $fiscalNombre,
    'fiscal_nombres' => $oficio['fiscal_nombres'] ?? '',
    'fiscal_apellidos' => im_clean(($oficio['fiscal_apellido_paterno'] ?? '') . ' ' . ($oficio['fiscal_apellido_materno'] ?? '')),
    'fiscal_dni' => $oficio['fiscal_dni'] ?? '',
    'fiscal_cargo' => $oficio['fiscal_cargo'] ?? '',
    'fiscal_telefono' => $oficio['fiscal_telefono'] ?? '',
    'fiscal_correo' => $oficio['fiscal_correo'] ?? '',
    'persona_involucrada_id' => $oficio['involucrado_id'] ?? '',
    'persona_nombre_completo' => $personaNombre,
    'persona_nombres' => $oficio['persona_nombres'] ?? '',
    'persona_apellidos' => $personaApellidos,
    'persona_apellido_paterno' => $oficio['persona_apellido_paterno'] ?? '',
    'persona_apellido_materno' => $oficio['persona_apellido_materno'] ?? '',
    'persona_tipo_doc' => $oficio['persona_tipo_doc'] ?? '',
    'persona_num_doc' => $oficio['persona_num_doc'] ?? '',
    'persona_documento' => im_clean(($oficio['persona_tipo_doc'] ?? '') . ' ' . ($oficio['persona_num_doc'] ?? '')),
    'persona_fecha_nacimiento' => im_fecha_larga($oficio['persona_fecha_nacimiento'] ?? null),
    'persona_fecha_nacimiento_abrev' => im_fecha_abrev($oficio['persona_fecha_nacimiento'] ?? null),
    'persona_edad' => $oficio['persona_edad'] ?? '',
    'persona_sexo' => $oficio['persona_sexo'] ?? '',
    'persona_estado_civil' => $oficio['persona_estado_civil'] ?? '',
    'persona_nacionalidad' => $oficio['persona_nacionalidad'] ?? '',
    'persona_domicilio' => $oficio['persona_domicilio'] ?? '',
    'persona_ocupacion' => $oficio['persona_ocupacion'] ?? '',
    'persona_grado_instruccion' => $oficio['persona_grado_instruccion'] ?? '',
    'persona_celular' => $oficio['persona_celular'] ?? '',
    'persona_email' => $oficio['persona_email'] ?? '',
    'persona_rol' => $oficio['persona_rol'] ?? '',
    'persona_orden' => $oficio['orden_persona'] ?? '',
    'persona_lesion' => $oficio['persona_lesion'] ?? '',
    'persona_observaciones' => $oficio['persona_observaciones'] ?? '',
];

$tpl = new TemplateProcessor($template);
foreach ($values as $key => $value) {
    $tpl->setValue($key, im_clean($value));
}
if (isset($_GET['vars']) && method_exists($tpl, 'getVariables')) {
    header('Content-Type: text/plain; charset=utf-8');
    $variables = $tpl->getVariables();
    sort($variables);
    exit(implode(PHP_EOL, $variables) . PHP_EOL);
}

$tmp = tempnam(sys_get_temp_dir(), 'informe_medico_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear el archivo temporal.');
}
$tpl->saveAs($tmp);
while (ob_get_level()) {
    ob_end_clean();
}
$filename = 'Oficio_Informe_Medico_' . preg_replace('/[^0-9]/', '', (string) ($oficio['numero'] ?? $oficioId)) . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($tmp);
@unlink($tmp);
exit;
