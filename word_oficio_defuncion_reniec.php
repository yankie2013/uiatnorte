<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && file_exists(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require_once __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}

use App\Support\ResponsibleTemplateProcessor as TemplateProcessor;

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_errors.log');

if (!class_exists(TemplateProcessor::class)) {
    http_response_code(500);
    exit('PhpWord no esta disponible para generar el DOCX.');
}

$template = __DIR__ . '/plantillas/oficio_defuncion_reniec.docx';
if (!is_file($template)) {
    http_response_code(404);
    exit('Falta subir la plantilla: plantillas/oficio_defuncion_reniec.docx');
}

$oficioId = (int) ($_GET['oficio_id'] ?? 0);
if ($oficioId <= 0) {
    http_response_code(400);
    exit('Falta oficio_id.');
}

function ident_clean(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

function ident_fecha_larga(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return date('j', $time) . ' de ' . $months[(int) date('n', $time) - 1] . ' de ' . date('Y', $time);
}

function ident_fecha_abrev(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if (!$time) {
        return '';
    }
    $months = [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];
    return strtoupper(date('d', $time) . $months[(int) date('n', $time)] . date('Y', $time));
}

function ident_normalize(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
    ]);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET NAMES utf8mb4');

$st = $pdo->prepare("
SELECT o.*,
       e.nombre AS entidad_nombre, COALESCE(e.siglas,'') AS entidad_siglas,
       a.nombre AS asunto_nombre, COALESCE(a.detalle,'') AS asunto_detalle,
       ac.registro_sidpol, ac.sidpol, ac.lugar, ac.referencia, ac.fecha_accidente,
       c.nombre AS comisaria_nombre,
       ao.nombre AS nombre_oficial_ano,
       gc.nombre AS grado_cargo_nombre, gc.abreviatura AS grado_cargo_abrev,
       p.nombres AS fall_nombres, p.apellido_paterno AS fall_ap, p.apellido_materno AS fall_am,
       p.tipo_doc AS fall_doc_tipo, p.num_doc AS fall_doc_num, p.edad AS fall_edad,
       p.fecha_nacimiento, p.estado_civil, p.sexo, p.nacionalidad, p.distrito_nac, p.provincia_nac, p.departamento_nac
FROM oficios_activos o
LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino
LEFT JOIN oficio_asunto a ON a.id = o.asunto_id
LEFT JOIN accidentes_activos ac ON ac.id = o.accidente_id
LEFT JOIN comisarias c ON c.id = ac.comisaria_id
LEFT JOIN oficio_oficial_ano ao ON ao.id = o.oficial_ano_id
LEFT JOIN grado_cargo gc ON gc.id = o.grado_cargo_id
LEFT JOIN involucrados_personas_activos ip ON ip.id = o.involucrado_persona_id
LEFT JOIN personas p ON p.id = ip.persona_id
WHERE o.id = ?
LIMIT 1");
$st->execute([$oficioId]);
$oficio = $st->fetch(PDO::FETCH_ASSOC);
if (!$oficio) {
    http_response_code(404);
    exit('Oficio no encontrado.');
}

$match = ident_normalize(($oficio['asunto_nombre'] ?? '') . ' ' . ($oficio['asunto_detalle'] ?? ''));
if (!\App\Support\DefuncionReniec::matches($match)) {
    http_response_code(422);
    exit('Este oficio no corresponde a Inscripción defunción RENIEC.');
}
if (empty($oficio['involucrado_persona_id'])) {
    http_response_code(422);
    exit('Selecciona la persona fallecida para generar este oficio.');
}

$fallecidoNombre = ident_clean(($oficio['fall_nombres'] ?? '') . ' ' . ($oficio['fall_ap'] ?? '') . ' ' . ($oficio['fall_am'] ?? ''));
$accidenteLugar = ident_clean(($oficio['lugar'] ?? '') . (($oficio['referencia'] ?? '') !== '' ? ' - ' . $oficio['referencia'] : ''));
$responsableDocumento = \App\Support\Access::documentProfile($oficio);
$firmaGrado = ident_clean($responsableDocumento['grado'] ?? '');

$repository = new \App\Repositories\OficioRepository($pdo);
try {
    $morgue = \App\Support\DefuncionReniec::validate($repository->reniecDatos($oficioId));
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    exit($error->getMessage());
}
unset($morgue['adjuntos']);
$edad = $oficio['fall_edad'] ?? '';
if ($edad === '' || $edad === null) {
    if (!empty($oficio['fecha_nacimiento']) && !empty($oficio['fecha_accidente'])) {
        $edad = (new DateTimeImmutable($oficio['fecha_nacimiento']))->diff(new DateTimeImmutable($oficio['fecha_accidente']))->y;
    }
}
$values = $morgue + [
    'fallecido_nombres' => $oficio['fall_nombres'] ?? '',
    'fallecido_apellidos' => trim(($oficio['fall_ap'] ?? '') . ' ' . ($oficio['fall_am'] ?? '')),
    'fallecido_nacimiento_lugar' => implode(' / ', array_filter([$oficio['distrito_nac'], $oficio['provincia_nac'], $oficio['departamento_nac']], static fn($v) => $v !== null && $v !== '')),
    'fallecido_nacimiento_fecha' => ident_fecha_abrev($oficio['fecha_nacimiento'] ?? null),
    'fallecido_documento' => trim((string) ($oficio['fall_doc_num'] ?? '')),
    'fallecido_estado_civil' => $oficio['estado_civil'] ?? '',
    'fallecido_sexo' => match (mb_strtoupper(trim((string) ($oficio['sexo'] ?? '')), 'UTF-8')) {
        'M', 'MASCULINO' => 'MASCULINO',
        'F', 'FEMENINO' => 'FEMENINO',
        default => $oficio['sexo'] ?? '',
    },
    'fallecido_nacionalidad' => $oficio['nacionalidad'] ?? '',
    'firma_cip' => $responsableDocumento['cip'] ?? '',
    'firma_unidad' => $responsableDocumento['unidad'] ?? '',
    'firma_siglas' => $responsableDocumento['siglas'] ?? 'DIVPIAT-UIAT-NORTE',
    'adjuntos' => \App\Support\DefuncionReniec::attachmentsText($morgue),
    'nombre_oficial_ano' => $oficio['nombre_oficial_ano'] ?? '',
    'oficio_numero' => $oficio['numero'] ?? '',
    'oficio_anio' => $oficio['anio'] ?? '',
    'oficio_fecha' => ident_fecha_larga($oficio['fecha_emision'] ?? null),
    'oficio_fecha_abrev' => ident_fecha_abrev($oficio['fecha_emision'] ?? null),
    'entidad_nombre' => $oficio['entidad_nombre'] ?? '',
    'entidad_siglas' => $oficio['entidad_siglas'] ?? '',
    'asunto_nombre' => $oficio['asunto_nombre'] ?? '',
    'motivo' => $oficio['motivo'] ?? '',
    'referencia_texto' => $oficio['referencia_texto'] ?? '',
    'accidente_sidpol' => $oficio['registro_sidpol'] ?: ($oficio['sidpol'] ?? ''),
    'accidente_fecha' => ident_fecha_larga($oficio['fecha_accidente'] ?? null),
    'accidente_fecha_abrev' => ident_fecha_abrev($oficio['fecha_accidente'] ?? null),
    'accidente_lugar' => $accidenteLugar,
    'comisaria_nombre' => $oficio['comisaria_nombre'] ?? '',
    'fallecido_nombre' => $fallecidoNombre,
    'fallecido_doc_tipo' => $oficio['fall_doc_tipo'] ?? '',
    'fallecido_doc_num' => $oficio['fall_doc_num'] ?? '',
    'fallecido_edad' => $edad !== '' && $edad !== null ? $edad . ' AÑOS' : '',
    'investigador_grado' => $firmaGrado,
    'investigador_nombre' => ($responsableDocumento['nombre'] ?? ''),
    'firma_grado' => $firmaGrado,
    'firma_nombre' => ($responsableDocumento['nombre'] ?? ''),
    'firma_cargo' => $responsableDocumento['cargo'] ?? '',
];

// Apply the requested casing only to the personal and morgue data block.
foreach ($values as $key => $value) {
    if (str_starts_with($key, 'fallecido_') || array_key_exists($key, \App\Support\DefuncionReniec::FIELDS)) {
        $values[$key] = mb_strtoupper((string) $value, 'UTF-8');
    }
}

$tpl = new TemplateProcessor($template);
foreach ($values as $key => $value) {
    $tpl->setValue($key, htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8'));
}

if (isset($_GET['vars']) && method_exists($tpl, 'getVariables')) {
    header('Content-Type: text/plain; charset=utf-8');
    $vars = $tpl->getVariables();
    sort($vars);
    echo implode(PHP_EOL, $vars) . PHP_EOL;
    exit;
}

$tmp = tempnam(sys_get_temp_dir(), 'ident_cadaver_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear temporal DOCX.');
}
$tpl->saveAs($tmp);

while (ob_get_level()) {
    ob_end_clean();
}

$filename = 'Inscripcion_Defuncion_RENIEC_' . preg_replace('/[^0-9]/', '', (string) ($oficio['numero'] ?? $oficioId)) . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($tmp);
@unlink($tmp);
exit;
