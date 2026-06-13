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

function uper_clean(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

function uper_fecha_larga(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return date('j', $time) . ' de ' . $months[(int) date('n', $time) - 1] . ' de ' . date('Y', $time);
}

function uper_fecha_abrev(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];
    return strtoupper(date('d', $time) . $months[(int) date('n', $time)] . date('Y', $time));
}

function uper_normalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    return preg_replace('/[^a-z0-9]+/', ' ', $converted !== false ? $converted : $text) ?? $text;
}

function uper_join_es(array $items): string
{
    $items = array_values(array_filter(array_map('uper_clean', $items), static fn(string $item): bool => $item !== ''));
    if (count($items) <= 1) {
        return $items[0] ?? '';
    }
    return implode(', ', array_slice($items, 0, -1)) . ' y ' . $items[count($items) - 1];
}

$oficioId = (int) ($_GET['oficio_id'] ?? 0);
if ($oficioId <= 0) {
    http_response_code(400);
    exit('Falta oficio_id.');
}

$template = __DIR__ . '/plantillas/oficio_informacion_certificado_uper.docx';
if (!is_file($template)) {
    http_response_code(500);
    exit('No se encuentra la plantilla: plantillas/oficio_informacion_certificado_uper.docx');
}

$sql = "
SELECT o.*, e.nombre AS entidad_nombre, COALESCE(e.siglas, '') AS entidad_siglas,
       s.nombre AS asunto_nombre, COALESCE(s.detalle, '') AS asunto_detalle,
       se.nombre AS subentidad_nombre, se.tipo AS subentidad_tipo,
       pe.nombres AS destino_nombres, pe.apellido_paterno AS destino_apep,
       COALESCE(pe.apellido_materno, '') AS destino_apem,
       ac.id AS accidente_id, ac.registro_sidpol, ac.sidpol,
       ac.tipo_registro AS accidente_tipo_registro, ac.lugar, ac.referencia AS accidente_referencia,
       ac.sentido, ac.fecha_accidente, ac.estado AS accidente_estado,
       ac.latitud AS accidente_latitud, ac.longitud AS accidente_longitud,
       ac.cod_dep AS accidente_cod_dep, ac.cod_prov AS accidente_cod_prov, ac.cod_dist AS accidente_cod_dist,
       ac.fecha_comunicacion AS accidente_fecha_comunicacion,
       ac.fecha_intervencion AS accidente_fecha_intervencion,
       ac.comunicante_nombre AS accidente_comunicante_nombre,
       ac.comunicante_telefono AS accidente_comunicante_telefono,
       ac.comunicacion_decreto AS accidente_comunicacion_decreto,
       ac.comunicacion_oficio AS accidente_comunicacion_oficio,
       ac.comunicacion_carpeta_nro AS accidente_comunicacion_carpeta_nro,
       ac.nro_informe_policial AS accidente_nro_informe_policial,
       ac.folder AS accidente_folder, ac.secuencia AS accidente_secuencia,
       ac.priority AS accidente_prioridad,
       c.nombre AS comisaria_nombre, f.nombre AS fiscalia_nombre,
       ud.nombre AS accidente_distrito, up.nombre AS accidente_provincia, udp.nombre AS accidente_departamento,
       ao.nombre AS nombre_oficial_ano,
       gc.nombre AS grado_cargo_nombre, gc.abreviatura AS grado_cargo_abrev, gc.tipo AS grado_cargo_tipo,
       iv.id AS veh_inv_id, iv.orden_participacion AS veh_orden, iv.tipo AS veh_tipo_participacion, iv.observaciones AS veh_observaciones,
       v.placa AS veh_placa, v.serie_vin AS veh_serie_vin, v.nro_motor AS veh_nro_motor,
       v.anio AS veh_anio, v.color AS veh_color, v.largo_mm AS veh_largo_mm,
       v.ancho_mm AS veh_ancho_mm, v.alto_mm AS veh_alto_mm, v.notas AS veh_notas,
       mv.nombre AS veh_marca, modv.nombre AS veh_modelo,
       cv.codigo AS veh_categoria, cv.descripcion AS veh_categoria_descripcion,
       tv.codigo AS veh_tipo_codigo, tv.nombre AS veh_tipo, tv.descripcion AS veh_tipo_descripcion,
       COALESCE(car.nombre, car.descripcion) AS veh_carroceria, car.descripcion AS veh_carroceria_descripcion
FROM oficios o
LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino
LEFT JOIN oficio_asunto s ON s.id = o.asunto_id
LEFT JOIN oficio_subentidad se ON se.id = o.subentidad_destino_id
LEFT JOIN oficio_persona_entidad pe ON pe.id = o.persona_destino_id
LEFT JOIN accidentes ac ON ac.id = o.accidente_id
LEFT JOIN comisarias c ON c.id = ac.comisaria_id
LEFT JOIN fiscalia f ON f.id = ac.fiscalia_id
LEFT JOIN ubigeo_departamento udp ON udp.cod_dep = ac.cod_dep
LEFT JOIN ubigeo_provincia up ON up.cod_dep = ac.cod_dep AND up.cod_prov = ac.cod_prov
LEFT JOIN ubigeo_distrito ud ON ud.cod_dep = ac.cod_dep AND ud.cod_prov = ac.cod_prov AND ud.cod_dist = ac.cod_dist
LEFT JOIN oficio_oficial_ano ao ON ao.id = o.oficial_ano_id
LEFT JOIN grado_cargo gc ON gc.id = o.grado_cargo_id
LEFT JOIN involucrados_vehiculos iv ON iv.id = o.involucrado_vehiculo_id AND iv.accidente_id = o.accidente_id
LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id
LEFT JOIN marcas_vehiculo mv ON mv.id = v.marca_id
LEFT JOIN modelos_vehiculo modv ON modv.id = v.modelo_id
LEFT JOIN categoria_vehiculos cv ON cv.id = v.categoria_id
LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
LEFT JOIN carroceria_vehiculo car ON car.id = v.carroceria_id
WHERE o.id = ?
LIMIT 1";
$st = $pdo->prepare($sql);
$st->execute([$oficioId]);
$oficio = $st->fetch(PDO::FETCH_ASSOC);
if (!$oficio) {
    http_response_code(404);
    exit('Oficio no encontrado.');
}

$match = uper_normalize(($oficio['asunto_nombre'] ?? '') . ' ' . ($oficio['asunto_detalle'] ?? ''));
if (!str_contains($match, 'informacion') || !str_contains($match, 'certificado')) {
    http_response_code(422);
    exit('Este oficio no corresponde al asunto Informacion certificado.');
}
if ((int) ($oficio['involucrado_vehiculo_id'] ?? 0) <= 0 || (int) ($oficio['veh_inv_id'] ?? 0) <= 0) {
    http_response_code(422);
    exit('Selecciona el vehiculo involucrado del accidente para generar el oficio.');
}

$destinoPersona = uper_clean(($oficio['destino_nombres'] ?? '') . ' ' . ($oficio['destino_apep'] ?? '') . ' ' . ($oficio['destino_apem'] ?? ''));
if ($destinoPersona === '') {
    $destinoPersona = uper_clean($oficio['persona_destino_manual'] ?? '');
}
$entidadLinea = uper_clean(($oficio['entidad_nombre'] ?? '') . (($oficio['entidad_siglas'] ?? '') !== '' ? ' (' . $oficio['entidad_siglas'] . ')' : '') . (($oficio['subentidad_nombre'] ?? '') !== '' ? ' - ' . $oficio['subentidad_nombre'] : ''));
$fechaAccidente = $oficio['fecha_accidente'] ?? null;
$st = $pdo->prepare('SELECT ma.nombre FROM accidente_modalidad am JOIN modalidad_accidente ma ON ma.id = am.modalidad_id WHERE am.accidente_id = ? ORDER BY ma.id');
$st->execute([(int) ($oficio['accidente_id'] ?? 0)]);
$accidenteModalidades = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
$st = $pdo->prepare('SELECT ca.nombre FROM accidente_consecuencia ac JOIN consecuencia_accidente ca ON ca.id = ac.consecuencia_id WHERE ac.accidente_id = ? ORDER BY ca.id');
$st->execute([(int) ($oficio['accidente_id'] ?? 0)]);
$accidenteConsecuencias = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
$accidenteLugarCompleto = uper_clean(($oficio['lugar'] ?? '') . (($oficio['accidente_referencia'] ?? '') !== '' ? ' - ' . $oficio['accidente_referencia'] : ''));
$accidenteUbicacion = uper_clean(implode(', ', array_filter([
    $oficio['accidente_distrito'] ?? '',
    $oficio['accidente_provincia'] ?? '',
    $oficio['accidente_departamento'] ?? '',
])));
$accidenteCoordenadas = uper_clean(($oficio['accidente_latitud'] ?? '') . (($oficio['accidente_longitud'] ?? '') !== '' ? ', ' . $oficio['accidente_longitud'] : ''));
$accidenteResumen = uper_clean('Accidente de transito ocurrido el ' . uper_fecha_abrev($fechaAccidente)
    . (($fechaAccidente && strtotime($fechaAccidente)) ? ' a horas ' . date('H:i', strtotime($fechaAccidente)) : '')
    . ($accidenteLugarCompleto !== '' ? ' en ' . $accidenteLugarCompleto : '')
    . '.');

$values = [
    'nombre_oficial_ano' => $oficio['nombre_oficial_ano'] ?? '',
    'oficio_numero' => $oficio['numero'] ?? '',
    'oficio_anio' => $oficio['anio'] ?? '',
    'oficio_fecha' => uper_fecha_larga($oficio['fecha_emision'] ?? null),
    'oficio_fecha_abrev' => uper_fecha_abrev($oficio['fecha_emision'] ?? null),
    'oficio_motivo' => $oficio['motivo'] ?? '',
    'oficio_referencia' => $oficio['referencia_texto'] ?? '',
    'oficio_entidad_nombre' => $oficio['entidad_nombre'] ?? '',
    'oficio_entidad_siglas' => $oficio['entidad_siglas'] ?? '',
    'oficio_entidad_linea' => $entidadLinea,
    'oficio_subentidad_nombre' => $oficio['subentidad_nombre'] ?? '',
    'oficio_subentidad_tipo' => $oficio['subentidad_tipo'] ?? '',
    'oficio_persona_destino' => $destinoPersona,
    'oficio_grado_cargo' => uper_clean(($oficio['grado_cargo_nombre'] ?? '') . (($oficio['grado_cargo_abrev'] ?? '') !== '' ? ' - ' . $oficio['grado_cargo_abrev'] : '')),
    'asunto_nombre' => $oficio['asunto_nombre'] ?? '',
    'asunto_detalle' => $oficio['asunto_detalle'] ?? '',
    'accidente_sidpol' => $oficio['registro_sidpol'] ?: ($oficio['sidpol'] ?? ''),
    'accidente_id' => $oficio['accidente_id'] ?? '',
    'accidente_tipo_registro' => $oficio['accidente_tipo_registro'] ?? '',
    'accidente_estado' => $oficio['accidente_estado'] ?? '',
    'accidente_lugar' => $oficio['lugar'] ?? '',
    'accidente_lugar_completo' => $accidenteLugarCompleto,
    'accidente_referencia' => $oficio['accidente_referencia'] ?? '',
    'accidente_sentido' => $oficio['sentido'] ?? '',
    'accidente_fecha' => uper_fecha_larga($fechaAccidente),
    'accidente_fecha_abrev' => uper_fecha_abrev($fechaAccidente),
    'accidente_hora' => $fechaAccidente && strtotime($fechaAccidente) ? date('H:i', strtotime($fechaAccidente)) : '',
    'accidente_modalidad' => uper_join_es($accidenteModalidades),
    'accidente_modalidades' => uper_join_es($accidenteModalidades),
    'accidente_consecuencia' => uper_join_es($accidenteConsecuencias),
    'accidente_consecuencias' => uper_join_es($accidenteConsecuencias),
    'accidente_departamento' => $oficio['accidente_departamento'] ?? '',
    'accidente_provincia' => $oficio['accidente_provincia'] ?? '',
    'accidente_distrito' => $oficio['accidente_distrito'] ?? '',
    'accidente_cod_dep' => $oficio['accidente_cod_dep'] ?? '',
    'accidente_cod_prov' => $oficio['accidente_cod_prov'] ?? '',
    'accidente_cod_dist' => $oficio['accidente_cod_dist'] ?? '',
    'accidente_ubicacion' => $accidenteUbicacion,
    'accidente_latitud' => $oficio['accidente_latitud'] ?? '',
    'accidente_longitud' => $oficio['accidente_longitud'] ?? '',
    'accidente_coordenadas' => $accidenteCoordenadas,
    'accidente_fecha_comunicacion' => uper_fecha_larga($oficio['accidente_fecha_comunicacion'] ?? null),
    'accidente_fecha_comunicacion_abrev' => uper_fecha_abrev($oficio['accidente_fecha_comunicacion'] ?? null),
    'accidente_hora_comunicacion' => !empty($oficio['accidente_fecha_comunicacion']) && strtotime((string) $oficio['accidente_fecha_comunicacion']) ? date('H:i', strtotime((string) $oficio['accidente_fecha_comunicacion'])) : '',
    'accidente_fecha_intervencion' => uper_fecha_larga($oficio['accidente_fecha_intervencion'] ?? null),
    'accidente_fecha_intervencion_abrev' => uper_fecha_abrev($oficio['accidente_fecha_intervencion'] ?? null),
    'accidente_hora_intervencion' => !empty($oficio['accidente_fecha_intervencion']) && strtotime((string) $oficio['accidente_fecha_intervencion']) ? date('H:i', strtotime((string) $oficio['accidente_fecha_intervencion'])) : '',
    'accidente_comunicante_nombre' => $oficio['accidente_comunicante_nombre'] ?? '',
    'accidente_comunicante_telefono' => $oficio['accidente_comunicante_telefono'] ?? '',
    'accidente_comunicacion_decreto' => $oficio['accidente_comunicacion_decreto'] ?? '',
    'accidente_comunicacion_oficio' => $oficio['accidente_comunicacion_oficio'] ?? '',
    'accidente_comunicacion_carpeta_nro' => $oficio['accidente_comunicacion_carpeta_nro'] ?? '',
    'accidente_nro_informe_policial' => $oficio['accidente_nro_informe_policial'] ?? '',
    'accidente_folder' => $oficio['accidente_folder'] ?? '',
    'accidente_secuencia' => $oficio['accidente_secuencia'] ?? '',
    'accidente_prioridad' => $oficio['accidente_prioridad'] ?? '',
    'accidente_resumen' => $accidenteResumen,
    'comisaria_nombre' => $oficio['comisaria_nombre'] ?? '',
    'fiscalia_nombre' => $oficio['fiscalia_nombre'] ?? '',
    'grado_cargo_nombre' => $oficio['grado_cargo_nombre'] ?? '',
    'grado_cargo_abrev' => $oficio['grado_cargo_abrev'] ?? '',
    'grado_cargo_tipo' => $oficio['grado_cargo_tipo'] ?? '',
    'veh_placa' => $oficio['veh_placa'] ?? '',
    'veh_marca' => $oficio['veh_marca'] ?? '',
    'veh_modelo' => $oficio['veh_modelo'] ?? '',
    'veh_categoria' => $oficio['veh_categoria'] ?? '',
    'veh_categoria_descripcion' => $oficio['veh_categoria_descripcion'] ?? '',
    'veh_tipo_codigo' => $oficio['veh_tipo_codigo'] ?? '',
    'veh_tipo' => $oficio['veh_tipo'] ?? '',
    'veh_tipo_descripcion' => $oficio['veh_tipo_descripcion'] ?? '',
    'veh_carroceria' => $oficio['veh_carroceria'] ?? '',
    'veh_carroceria_descripcion' => $oficio['veh_carroceria_descripcion'] ?? '',
    'veh_anio' => $oficio['veh_anio'] ?? '',
    'veh_color' => $oficio['veh_color'] ?? '',
    'veh_serie_vin' => $oficio['veh_serie_vin'] ?? '',
    'veh_nro_motor' => $oficio['veh_nro_motor'] ?? '',
    'veh_largo_mm' => $oficio['veh_largo_mm'] ?? '',
    'veh_ancho_mm' => $oficio['veh_ancho_mm'] ?? '',
    'veh_alto_mm' => $oficio['veh_alto_mm'] ?? '',
    'veh_medidas' => uper_clean(($oficio['veh_largo_mm'] ?? '') . ' x ' . ($oficio['veh_ancho_mm'] ?? '') . ' x ' . ($oficio['veh_alto_mm'] ?? '') . ' mm'),
    'veh_orden' => $oficio['veh_orden'] ?? '',
    'veh_tipo_participacion' => $oficio['veh_tipo_participacion'] ?? '',
    'veh_observaciones' => $oficio['veh_observaciones'] ?? '',
    'veh_notas' => $oficio['veh_notas'] ?? '',
];

$tpl = new TemplateProcessor($template);
foreach ($values as $key => $value) {
    $tpl->setValue($key, uper_clean($value));
}
if (isset($_GET['vars']) && method_exists($tpl, 'getVariables')) {
    header('Content-Type: text/plain; charset=utf-8');
    $variables = $tpl->getVariables();
    sort($variables);
    exit(implode(PHP_EOL, $variables) . PHP_EOL);
}

$tmp = tempnam(sys_get_temp_dir(), 'uper_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear el archivo temporal.');
}
$tpl->saveAs($tmp);
while (ob_get_level()) {
    ob_end_clean();
}
$filename = 'Oficio_Informacion_Certificado_UPER_' . preg_replace('/[^0-9]/', '', (string) ($oficio['numero'] ?? $oficioId)) . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($tmp);
@unlink($tmp);
exit;
