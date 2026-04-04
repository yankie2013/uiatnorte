<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && file_exists(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require_once __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}

use App\Repositories\AbogadoRepository;
use App\Repositories\DocumentoPlantillaRepository;
use App\Services\AbogadoService;
use PhpOffice\PhpWord\TemplateProcessor;

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize_docx_text(mixed $value): string
{
    $text = trim((string) ($value ?? ''));
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
}

function format_date_short(?string $value): string
{
    if (!$value) {
        return '';
    }

    $time = strtotime($value);
    return $time ? date('d/m/Y', $time) : '';
}

function format_date_long(?string $value): string
{
    if (!$value) {
        return '';
    }

    $time = strtotime($value);
    if (!$time) {
        return '';
    }

    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return date('j', $time) . ' de ' . $months[(int) date('n', $time) - 1] . ' de ' . date('Y', $time);
}

function format_date_abrev(?string $value): string
{
    if (!$value) {
        return '';
    }

    $time = strtotime($value);
    if (!$time) {
        return '';
    }

    $months = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];
    return strtoupper(date('d', $time) . $months[(int) date('n', $time) - 1] . date('Y', $time));
}

function format_hour(?string $value): string
{
    if (!$value) {
        return '';
    }

    $time = strtotime($value);
    return $time ? date('H:i', $time) : '';
}

function slug_filename(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'sin_nombre';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = $ascii;
    }

    $value = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? $value;
    $value = trim($value, '_');
    return $value !== '' ? $value : 'sin_nombre';
}

function join_modalidades(string $value): string
{
    $items = array_values(array_filter(array_map('trim', explode('||', $value)), static fn (string $item): bool => $item !== ''));
    $count = count($items);
    if ($count === 0) {
        return '';
    }
    $items = array_map(
        static function (string $item, int $index): string {
            $item = preg_replace('/\s+/u', ' ', trim($item)) ?? trim($item);
            $item = mb_strtolower($item, 'UTF-8');
            if ($index === 0 && $item !== '') {
                return mb_strtoupper(mb_substr($item, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($item, 1, null, 'UTF-8');
            }
            return $item;
        },
        $items,
        array_keys($items)
    );

    if ($count === 1) {
        return $items[0];
    }
    if ($count === 2) {
        return $items[0] . ' y ' . $items[1];
    }

    return implode(', ', array_slice($items, 0, $count - 1)) . ' y ' . $items[$count - 1];
}

function normalize_role_text(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = $ascii;
    }

    $value = strtolower($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
}

function abogado_representacion_desde_condicion(string $condicion, string $representado): array
{
    $normalized = normalize_role_text($condicion);

    $tipo = '';
    $descripcion = '';

    if ($normalized === '') {
        $tipo = 'persona';
        $descripcion = 'Abogado de la persona representada';
    } elseif (str_contains($normalized, 'familiar')) {
        $tipo = 'familiar';
        $descripcion = 'Abogado de la parte agraviada, representado por el familiar mas cercano';
    } elseif (str_contains($normalized, 'propietario')) {
        $tipo = 'propietario';
        $descripcion = 'Abogado del propietario del vehiculo';
    } elseif (str_contains($normalized, 'testigo')) {
        $tipo = 'testigo';
        $descripcion = 'Abogado del testigo';
    } elseif (
        str_contains($normalized, 'investigado')
        || str_contains($normalized, 'imputado')
        || str_contains($normalized, 'conductor')
        || str_contains($normalized, 'pasajero')
        || str_contains($normalized, 'peaton')
        || str_contains($normalized, 'ocupante')
        || str_contains($normalized, 'intervenido')
        || str_contains($normalized, 'involucrado')
    ) {
        $tipo = 'investigado';
        $descripcion = 'Abogado del investigado';
    } else {
        $tipo = 'persona';
        $descripcion = 'Abogado de la persona representada';
    }

    $deQuien = $descripcion;
    if ($representado !== '') {
        $deQuien .= ': ' . $representado;
    }

    return [
        'tipo' => $tipo,
        'descripcion' => $descripcion,
        'de_quien' => $deQuien,
    ];
}

function citacion_es_manifestacion(string $motivo): bool
{
    $normalized = normalize_role_text($motivo);
    return str_contains($normalized, 'manifestacion');
}

function text_to_lower(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function citacion_resumen_texto(array $citacion, string $personaCitacion, string $calidadCitacion, string $fechaAbrev, string $hora): string
{
    $motivo = trim((string) ($citacion['motivo'] ?? ''));
    $lugar = trim((string) ($citacion['lugar'] ?? ''));
    $partes = [];

    if ($motivo !== '') {
        $partes[] = $motivo;
    } else {
        $partes[] = 'Diligencia';
    }

    if ($personaCitacion !== '') {
        $partes[] = 'de ' . $personaCitacion;
    }

    if ($calidadCitacion !== '') {
        $partes[] = text_to_lower($calidadCitacion);
    }

    $texto = implode(', ', $partes);

    if ($fechaAbrev !== '') {
        $texto .= ', programado para el dia ' . $fechaAbrev;
    }

    if ($hora !== '') {
        $texto .= ', a las ' . $hora . ' horas';
    }

    if ($lugar !== '') {
        $texto .= ', en ' . $lugar;
    }

    return trim($texto, " ,.") . '.';
}

function citacion_detalle_persona_texto(string $personaCitacion, string $calidadCitacion): string
{
    $personaCitacion = trim($personaCitacion);
    $calidadCitacion = trim($calidadCitacion);

    if ($personaCitacion === '') {
        return '';
    }

    $detalle = ', de ' . $personaCitacion;
    if ($calidadCitacion !== '') {
        $detalle .= ', ' . text_to_lower($calidadCitacion);
    }

    return $detalle;
}

function normalize_diligencias(array $rows): array
{
    $result = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $motivo = trim((string) ($row['motivo'] ?? ''));
        $lugar = trim((string) ($row['lugar'] ?? ''));
        $fecha = trim((string) ($row['fecha'] ?? ''));
        $hora = trim((string) ($row['hora'] ?? ''));

        if ($motivo === '' && $lugar === '' && $fecha === '' && $hora === '') {
            continue;
        }

        $result[] = [
            'motivo' => $motivo,
            'lugar' => $lugar,
            'fecha' => $fecha,
            'hora' => $hora,
        ];
    }

    return array_values($result);
}

function template_has_block(string $docxPath, string $blockName): bool
{
    if (!class_exists(ZipArchive::class)) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        return false;
    }

    $needleStart = '${' . $blockName . '}';
    $needleEnd = '${/' . $blockName . '}';
    $found = false;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);
        if (!preg_match('#^word/(document|header[0-9]+|footer[0-9]+)\.xml$#', (string) $entryName)) {
            continue;
        }

        $content = $zip->getFromName($entryName);
        if (!is_string($content) || $content === '') {
            continue;
        }

        if (str_contains($content, $needleStart) && str_contains($content, $needleEnd)) {
            $found = true;
            break;
        }
    }

    $zip->close();
    return $found;
}

function citacion_datetime(array $row): string
{
    $fecha = trim((string) ($row['fecha'] ?? ''));
    $hora = trim((string) ($row['hora'] ?? ''));
    if ($fecha === '') {
        return '';
    }

    if ($hora === '') {
        $hora = '23:59:59';
    } elseif (strlen($hora) === 5) {
        $hora .= ':00';
    }

    return $fecha . ' ' . $hora;
}

$service = new AbogadoService(new AbogadoRepository($pdo));
$documentRepo = new DocumentoPlantillaRepository($pdo);

$abogadoId = (int) ($_GET['abogado_id'] ?? $_POST['abogado_id'] ?? 0);
if ($abogadoId <= 0) {
    http_response_code(400);
    exit('Falta abogado_id.');
}

$row = $service->detalle($abogadoId);
if ($row === null) {
    http_response_code(404);
    exit('El abogado no existe.');
}

$accidenteId = (int) ($row['accidente_id'] ?? 0);
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'abogado_listar.php?accidente_id=' . $accidenteId;
}

$select = [
    "a.id",
    "COALESCE(NULLIF(a.registro_sidpol, ''), NULLIF(a.sidpol, ''), CONCAT('ACC-', a.id)) AS sidpol_text",
    "a.fecha_accidente",
    "a.lugar",
    "a.referencia",
];
$joins = [];

if ($documentRepo->hasTable('comisarias') && $documentRepo->hasColumn('accidentes', 'comisaria_id')) {
    $select[] = "c.nombre AS comisaria_nombre";
    $joins[] = "LEFT JOIN comisarias c ON c.id = a.comisaria_id";
} else {
    $select[] = "NULL AS comisaria_nombre";
}

if ($documentRepo->hasTable('fiscalia') && $documentRepo->hasColumn('accidentes', 'fiscalia_id')) {
    $select[] = "f.nombre AS fiscalia_nombre";
    $joins[] = "LEFT JOIN fiscalia f ON f.id = a.fiscalia_id";
} else {
    $select[] = "NULL AS fiscalia_nombre";
}

$accidenteSql = 'SELECT ' . implode(', ', $select) . ' FROM accidentes a ' . implode(' ', $joins) . ' WHERE a.id = ? LIMIT 1';
$accidenteStmt = $pdo->prepare($accidenteSql);
$accidenteStmt->execute([$accidenteId]);
$accidente = $accidenteStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'id' => $accidenteId,
    'sidpol_text' => 'ACC-' . $accidenteId,
    'fecha_accidente' => null,
    'lugar' => '',
    'referencia' => '',
    'comisaria_nombre' => '',
    'fiscalia_nombre' => '',
];

$modalidad = join_modalidades($documentRepo->accidenteModalidad($accidenteId));
$abogadoNombre = trim((string) (($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '')));
$abogadoApellidos = trim((string) (($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '')));
$representado = trim((string) ($row['persona_rep_nom'] ?? ''));
$condicionRepresentado = trim((string) ($row['condicion_representado'] ?? ''));
$representacion = abogado_representacion_desde_condicion($condicionRepresentado, $representado);
$templatePath = __DIR__ . '/plantillas/notificacion_abogado.docx';
$templateReady = is_file($templatePath);

$values = [
    'fecha_notificacion' => (string) ($_POST['fecha_notificacion'] ?? date('Y-m-d')),
];

$citacionStmt = $pdo->prepare("
    SELECT id,
           persona_nombres,
           persona_apep,
           persona_apem,
           en_calidad,
           tipo_diligencia,
           fecha,
           hora,
           lugar,
           motivo
    FROM citacion
    WHERE accidente_id = ?
      AND (
            fecha > CURDATE()
            OR (
                fecha = CURDATE()
                AND COALESCE(NULLIF(hora, ''), '23:59:59') >= CURTIME()
            )
          )
    ORDER BY fecha ASC, hora ASC, orden_citacion ASC, id ASC
");
$citacionStmt->execute([$accidenteId]);
$citacionesVigentes = $citacionStmt->fetchAll(PDO::FETCH_ASSOC);
$citacionesMap = [];
foreach ($citacionesVigentes as $citacion) {
    $citacionesMap[(int) $citacion['id']] = $citacion;
}

$citacionesSeleccionadas = isset($_POST['citacion_ids']) && is_array($_POST['citacion_ids'])
    ? array_map('intval', $_POST['citacion_ids'])
    : array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $citacionesVigentes);
$citacionesSeleccionadas = array_values(array_filter(array_unique($citacionesSeleccionadas), static fn (int $id): bool => $id > 0));

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['fecha_notificacion'] = trim((string) ($_POST['fecha_notificacion'] ?? ''));

    if ($values['fecha_notificacion'] === '' || strtotime($values['fecha_notificacion']) === false) {
        $errors[] = 'Ingresa una fecha valida para la notificacion.';
    }

    if ($citacionesSeleccionadas === []) {
        $errors[] = 'Selecciona al menos una citacion vigente del accidente.';
    }

    if (!$templateReady) {
        $errors[] = 'Aun no existe la plantilla plantillas/notificacion_abogado.docx.';
    }

    if (!class_exists(TemplateProcessor::class)) {
        $errors[] = 'PhpWord no esta disponible para generar el DOCX.';
    }

    if ($errors === []) {
        $citacionesElegidas = [];
        foreach ($citacionesSeleccionadas as $citacionId) {
            if (!isset($citacionesMap[$citacionId])) {
                continue;
            }
            $citacionesElegidas[] = $citacionesMap[$citacionId];
        }

        if ($citacionesElegidas === []) {
            $errors[] = 'Las citaciones seleccionadas ya no estan vigentes o no pertenecen a este accidente.';
        }
    }

    if ($errors === []) {
        $tpl = new TemplateProcessor($templatePath);

        $valuesMap = [
            'abogado_nombres' => (string) ($row['nombres'] ?? ''),
            'abogado_apellidos' => $abogadoApellidos,
            'abogado_nombre_completo' => $abogadoNombre,
            'abogado_colegiatura' => (string) ($row['colegiatura'] ?? ''),
            'abogado_registro' => (string) ($row['registro'] ?? ''),
            'abogado_celular' => (string) ($row['celular'] ?? ''),
            'abogado_email' => (string) ($row['email'] ?? ''),
            'abogado_casilla_electronica' => (string) ($row['casilla_electronica'] ?? ''),
            'abogado_domicilio_procesal' => (string) ($row['domicilio_procesal'] ?? ''),
            'abogado_tipo_representacion' => $representacion['tipo'],
            'abogado_descripcion_representacion' => $representacion['descripcion'],
            'abogado_de_quien' => $representacion['de_quien'],
            'abogado_de_quien_es' => $representacion['de_quien'],
            'representado_nombre' => $representado,
            'representado_condicion' => $condicionRepresentado,
            'persona_representada' => $representado,
            'persona_representada_nombre' => $representado,
            'persona_representada_condicion' => $condicionRepresentado,
            'accidente_sidpol' => (string) ($accidente['sidpol_text'] ?? ''),
            'accidente_fecha' => format_date_abrev($accidente['fecha_accidente'] ?? null),
            'accidente_fecha_corta' => format_date_short($accidente['fecha_accidente'] ?? null),
            'accidente_fecha_larga' => format_date_long($accidente['fecha_accidente'] ?? null),
            'accidente_hora' => format_hour($accidente['fecha_accidente'] ?? null),
            'accidente_lugar' => (string) ($accidente['lugar'] ?? ''),
            'accidente_referencia' => (string) ($accidente['referencia'] ?? ''),
            'accidente_modalidad' => $modalidad,
            'comisaria_nombre' => (string) ($accidente['comisaria_nombre'] ?? ''),
            'fiscalia_nombre' => (string) ($accidente['fiscalia_nombre'] ?? ''),
            'not_fecha' => format_date_abrev($values['fecha_notificacion']),
            'not_fecha_corta' => format_date_short($values['fecha_notificacion']),
            'not_fecha_larga' => format_date_long($values['fecha_notificacion']),
            'citaciones_total' => (string) count($citacionesElegidas),
            'diligencias_total' => (string) count($citacionesElegidas),
        ];

        $diligenciasTexto = [];
        $blockRows = [];
        foreach ($citacionesElegidas as $index => $citacion) {
            $numero = $index + 1;
            $fechaCorta = format_date_short((string) ($citacion['fecha'] ?? ''));
            $fechaAbrev = format_date_abrev((string) ($citacion['fecha'] ?? ''));
            $hora = substr((string) ($citacion['hora'] ?? ''), 0, 5);
            $personaCit = trim((string) (($citacion['persona_nombres'] ?? '') . ' ' . ($citacion['persona_apep'] ?? '') . ' ' . ($citacion['persona_apem'] ?? '')));
            $esManifestacion = citacion_es_manifestacion((string) ($citacion['motivo'] ?? ''));
            $personaCitacion = $esManifestacion ? $personaCit : '';
            $calidadCitacion = $esManifestacion ? (string) ($citacion['en_calidad'] ?? '') : '';
            $detallePersonaCitacion = citacion_detalle_persona_texto($personaCitacion, $calidadCitacion);
            $resumenCitacion = citacion_resumen_texto($citacion, $personaCitacion, $calidadCitacion, $fechaAbrev, $hora);

            $diligenciasTexto[] = $numero . '. ' . $resumenCitacion;

            $valuesMap['citacion' . $numero . '_id'] = (string) ($citacion['id'] ?? '');
            $valuesMap['citacion' . $numero . '_persona'] = $personaCitacion;
            $valuesMap['citacion' . $numero . '_en_calidad'] = $calidadCitacion;
            $valuesMap['citacion' . $numero . '_detalle_persona'] = $detallePersonaCitacion;
            $valuesMap['citacion' . $numero . '_mostrar_persona'] = $esManifestacion ? 'SI' : 'NO';
            $valuesMap['citacion' . $numero . '_mostrar_en_calidad'] = $esManifestacion ? 'SI' : 'NO';
            $valuesMap['citacion' . $numero . '_persona_linea'] = $personaCitacion !== '' ? 'Persona: ' . $personaCitacion : '';
            $valuesMap['citacion' . $numero . '_en_calidad_linea'] = $calidadCitacion !== '' ? 'En calidad de: ' . $calidadCitacion : '';
            $valuesMap['citacion' . $numero . '_resumen'] = $resumenCitacion;
            $valuesMap['citacion' . $numero . '_tipo_diligencia'] = (string) ($citacion['tipo_diligencia'] ?? '');
            $valuesMap['citacion' . $numero . '_motivo'] = (string) ($citacion['motivo'] ?? '');
            $valuesMap['citacion' . $numero . '_lugar'] = (string) ($citacion['lugar'] ?? '');
            $valuesMap['citacion' . $numero . '_fecha'] = $fechaAbrev;
            $valuesMap['citacion' . $numero . '_fecha_corta'] = $fechaCorta;
            $valuesMap['citacion' . $numero . '_hora'] = $hora;

            $valuesMap['dil' . $numero . '_motivo'] = (string) ($citacion['motivo'] ?? '');
            $valuesMap['dil' . $numero . '_lugar'] = (string) ($citacion['lugar'] ?? '');
            $valuesMap['dil' . $numero . '_fecha'] = $fechaAbrev;
            $valuesMap['dil' . $numero . '_fecha_corta'] = $fechaCorta;
            $valuesMap['dil' . $numero . '_hora'] = $hora;

            $valuesMap['diligencia' . $numero . '_motivo'] = (string) ($citacion['motivo'] ?? '');
            $valuesMap['diligencia' . $numero . '_lugar'] = (string) ($citacion['lugar'] ?? '');
            $valuesMap['diligencia' . $numero . '_fecha'] = $fechaAbrev;
            $valuesMap['diligencia' . $numero . '_fecha_corta'] = $fechaCorta;
            $valuesMap['diligencia' . $numero . '_hora'] = $hora;

            if ($numero === 1) {
                $valuesMap['cit_motivo'] = (string) ($citacion['motivo'] ?? '');
                $valuesMap['cit_lugar'] = (string) ($citacion['lugar'] ?? '');
                $valuesMap['cit_fecha'] = $fechaAbrev;
                $valuesMap['cit_fecha_corta'] = $fechaCorta;
                $valuesMap['cit_hora'] = $hora;
                $valuesMap['cit_detalle_persona'] = $detallePersonaCitacion;
                $valuesMap['dil_motivo'] = (string) ($citacion['motivo'] ?? '');
                $valuesMap['dil_lugar'] = (string) ($citacion['lugar'] ?? '');
                $valuesMap['dil_fecha'] = $fechaAbrev;
                $valuesMap['dil_fecha_corta'] = $fechaCorta;
                $valuesMap['dil_hora'] = $hora;
                $valuesMap['cit_resumen'] = $resumenCitacion;
                $valuesMap['dil_resumen'] = $resumenCitacion;
            }

            $blockRows[] = [
                'diligencia_numero' => (string) $numero,
                'diligencia_motivo' => (string) ($citacion['motivo'] ?? ''),
                'diligencia_lugar' => (string) ($citacion['lugar'] ?? ''),
                'diligencia_fecha' => $fechaAbrev,
                'diligencia_fecha_corta' => $fechaCorta,
                'diligencia_hora' => $hora,
                'citacion_numero' => (string) $numero,
                'citacion_id' => (string) ($citacion['id'] ?? ''),
                'citacion_persona' => $personaCitacion,
                'citacion_en_calidad' => $calidadCitacion,
                'citacion_detalle_persona' => $detallePersonaCitacion,
                'citacion_mostrar_persona' => $esManifestacion ? 'SI' : 'NO',
                'citacion_mostrar_en_calidad' => $esManifestacion ? 'SI' : 'NO',
                'citacion_persona_linea' => $personaCitacion !== '' ? 'Persona: ' . $personaCitacion : '',
                'citacion_en_calidad_linea' => $calidadCitacion !== '' ? 'En calidad de: ' . $calidadCitacion : '',
                'citacion_resumen' => $resumenCitacion,
                'diligencia_resumen' => $resumenCitacion,
                'citacion_tipo_diligencia' => (string) ($citacion['tipo_diligencia'] ?? ''),
                'citacion_motivo' => (string) ($citacion['motivo'] ?? ''),
                'citacion_lugar' => (string) ($citacion['lugar'] ?? ''),
                'citacion_fecha' => $fechaAbrev,
                'citacion_fecha_corta' => $fechaCorta,
                'citacion_hora' => $hora,
            ];
        }

        for ($i = count($citacionesElegidas) + 1; $i <= 12; $i++) {
            $valuesMap['citacion' . $i . '_id'] = '';
            $valuesMap['citacion' . $i . '_persona'] = '';
            $valuesMap['citacion' . $i . '_en_calidad'] = '';
            $valuesMap['citacion' . $i . '_detalle_persona'] = '';
            $valuesMap['citacion' . $i . '_mostrar_persona'] = 'NO';
            $valuesMap['citacion' . $i . '_mostrar_en_calidad'] = 'NO';
            $valuesMap['citacion' . $i . '_persona_linea'] = '';
            $valuesMap['citacion' . $i . '_en_calidad_linea'] = '';
            $valuesMap['citacion' . $i . '_resumen'] = '';
            $valuesMap['citacion' . $i . '_tipo_diligencia'] = '';
            $valuesMap['citacion' . $i . '_motivo'] = '';
            $valuesMap['citacion' . $i . '_lugar'] = '';
            $valuesMap['citacion' . $i . '_fecha'] = '';
            $valuesMap['citacion' . $i . '_fecha_corta'] = '';
            $valuesMap['citacion' . $i . '_hora'] = '';
            $valuesMap['dil' . $i . '_motivo'] = '';
            $valuesMap['dil' . $i . '_lugar'] = '';
            $valuesMap['dil' . $i . '_fecha'] = '';
            $valuesMap['dil' . $i . '_fecha_corta'] = '';
            $valuesMap['dil' . $i . '_hora'] = '';

            $valuesMap['diligencia' . $i . '_motivo'] = '';
            $valuesMap['diligencia' . $i . '_lugar'] = '';
            $valuesMap['diligencia' . $i . '_fecha'] = '';
            $valuesMap['diligencia' . $i . '_fecha_corta'] = '';
            $valuesMap['diligencia' . $i . '_hora'] = '';
        }

        $valuesMap['not_diligencias'] = implode("\n", $diligenciasTexto);

        foreach ($valuesMap as $key => $value) {
            $tpl->setValue($key, sanitize_docx_text($value));
        }

        if ($blockRows !== [] && template_has_block($templatePath, 'citaciones')) {
            try {
                $tpl->cloneBlock('citaciones', count($blockRows), true, false, $blockRows);
            } catch (Throwable) {
            }
        }

        if ($blockRows !== [] && template_has_block($templatePath, 'diligencias')) {
            try {
                $tpl->cloneBlock('diligencias', count($blockRows), true, false, $blockRows);
            } catch (Throwable) {
            }
        }

        $tmpDir = __DIR__ . '/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $tmp = tempnam($tmpDir, 'not_abg_');
        if ($tmp === false) {
            http_response_code(500);
            exit('No se pudo crear un archivo temporal para el DOCX.');
        }

        $docxFile = $tmp . '.docx';
        @rename($tmp, $docxFile);
        $tpl->saveAs($docxFile);

        $filename = 'Notificacion_Abogado_' . $abogadoId . '_' . slug_filename((string) ($row['apellido_paterno'] ?? '')) . '_' . date('Ymd_His') . '.docx';

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($docxFile));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($docxFile);
        @unlink($docxFile);
        exit;
    }
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Notificacion a abogado</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f3f6fb;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--primary-soft:#dbeafe;--danger:#b91c1c;--shadow:0 18px 40px rgba(15,23,42,.14)}
*{box-sizing:border-box}
body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}
.wrap{max-width:1120px;margin:24px auto;padding:0 12px}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.hero h1{margin:0 0 6px;font-size:34px;line-height:1.05}
.hero p{margin:0;color:var(--muted);font-size:16px}
.actions{display:flex;gap:10px;flex-wrap:wrap}
.btn{padding:10px 14px;border-radius:12px;border:1px solid var(--border);background:#fff;color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}
.btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}
.card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:18px;box-shadow:var(--shadow)}
.pill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:var(--primary-soft);color:var(--primary);font-size:12px;font-weight:800}
.notice{border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;padding:12px 14px;border-radius:12px;margin-bottom:12px}
.error{border:1px solid #fecaca;background:#fef2f2;color:var(--danger);padding:12px 14px;border-radius:12px;margin-bottom:12px}
.grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px}
.c12{grid-column:span 12}
.c6{grid-column:span 6}
.field{border:1px solid var(--border);border-radius:14px;padding:14px;background:#fbfcff}
.label{font-size:12px;font-weight:800;color:#a16207;margin-bottom:6px}
.value{font-weight:700;word-break:break-word}
.muted{color:var(--muted)}
.section-title{margin:0 0 12px;font-size:16px}
.form-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px}
.input-wrap{grid-column:span 12}
.input-wrap.half{grid-column:span 6}
label{display:block;font-size:12px;font-weight:800;color:#a16207;margin-bottom:6px}
input[type="date"],input[type="time"],input[type="text"],select{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:11px 12px;background:#fff;color:var(--text);font:inherit}
.list-stack{display:grid;gap:12px}
.diligencia-card{border:1px solid #dbe4f0;border-radius:16px;padding:14px;background:#fcfdff}
.diligencia-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}
.diligencia-title{font-weight:800}
.diligencia-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:10px}
.span-6{grid-column:span 6}
.span-4{grid-column:span 4}
.span-3{grid-column:span 3}
.mini-btn{height:40px;min-width:40px;border-radius:12px;border:1px solid var(--border);background:#fff;color:var(--text);font-weight:900;cursor:pointer}
.mini-btn.add{border-color:#bfdbfe;background:#eff6ff;color:#1d4ed8}
.footer-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:18px}
@media(max-width:760px){.c6,.input-wrap.half,.span-6,.span-4,.span-3{grid-column:span 12}.hero h1{font-size:28px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div class="hero">
      <h1>Notificacion a abogado</h1>
      <p>Registra una o varias diligencias y descarga el DOCX desde tu plantilla.</p>
    </div>
    <div class="actions">
      <a class="btn" href="<?= h($returnTo) ?>">Volver</a>
      <a class="btn" href="abogado_ver.php?id=<?= (int) $abogadoId ?>&return=<?= urlencode($returnTo) ?>">Ver abogado</a>
    </div>
  </div>

  <?php if (!$templateReady): ?>
    <div class="notice">Cuando subas <strong>plantillas/notificacion_abogado.docx</strong>, este flujo quedara listo para descargar el Word automaticamente.</div>
  <?php endif; ?>

  <?php if ($errors !== []): ?>
    <div class="error"><?= h(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:14px;">
    <div class="pill" style="margin-bottom:14px;">Datos preconfigurados</div>
    <div class="grid">
      <div class="c6 field">
        <div class="label">Abogado</div>
        <div class="value"><?= h($abogadoNombre !== '' ? $abogadoNombre : 'Sin nombre') ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Colegiatura / registro</div>
        <div class="value"><?= h(trim((string) (($row['colegiatura'] ?? 'Sin colegiatura') . (($row['registro'] ?? '') !== '' ? ' · Registro ' . $row['registro'] : '')))) ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Representa a</div>
        <div class="value"><?= h($representado !== '' ? $representado : 'Sin persona asociada') ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Condicion representada</div>
        <div class="value"><?= h($condicionRepresentado !== '' ? $condicionRepresentado : 'Sin condicion') ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Tipo de representacion</div>
        <div class="value"><?= h($representacion['descripcion']) ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Accidente</div>
        <div class="value"><?= h(trim((string) (($accidente['sidpol_text'] ?? '') . ' · ' . ($accidente['fecha_accidente'] ?? '') . ' · ' . ($accidente['lugar'] ?? '')))) ?></div>
      </div>
      <div class="c6 field">
        <div class="label">Modalidad / fiscalia</div>
        <div class="value"><?= h(trim((string) (($modalidad !== '' ? $modalidad : 'Sin modalidad') . (!empty($accidente['fiscalia_nombre']) ? ' · ' . $accidente['fiscalia_nombre'] : '')))) ?></div>
      </div>
    </div>
  </div>

  <form class="card" method="post">
    <input type="hidden" name="abogado_id" value="<?= (int) $abogadoId ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">

    <h2 class="section-title">Diligencias a notificar</h2>
    <div class="form-grid">
      <div class="input-wrap half">
        <label for="fecha_notificacion">Fecha de notificacion</label>
        <input id="fecha_notificacion" type="date" name="fecha_notificacion" value="<?= h($values['fecha_notificacion']) ?>" required>
      </div>

      <div class="input-wrap">
        <?php if ($citacionesVigentes === []): ?>
          <div class="notice">No hay citaciones vigentes para este accidente en este momento. Solo se consideran las que aun no han vencido segun su fecha y hora.</div>
        <?php else: ?>
          <div class="list-stack">
            <?php foreach ($citacionesVigentes as $index => $citacion): ?>
              <?php
                $citacionId = (int) ($citacion['id'] ?? 0);
                $personaCit = trim((string) (($citacion['persona_nombres'] ?? '') . ' ' . ($citacion['persona_apep'] ?? '') . ' ' . ($citacion['persona_apem'] ?? '')));
                $checked = in_array($citacionId, $citacionesSeleccionadas, true);
              ?>
              <label class="diligencia-card" style="display:block;cursor:pointer;">
                <div class="diligencia-head">
                  <div class="diligencia-title">Citacion #<?= $citacionId ?></div>
                  <input type="checkbox" name="citacion_ids[]" value="<?= $citacionId ?>" <?= $checked ? 'checked' : '' ?>>
                </div>
                <div class="diligencia-grid">
                  <div class="span-6">
                    <div class="label">Persona citada</div>
                    <div class="value"><?= h($personaCit !== '' ? $personaCit : 'Sin nombre') ?></div>
                  </div>
                  <div class="span-6">
                    <div class="label">En calidad de</div>
                    <div class="value"><?= h((string) ($citacion['en_calidad'] ?? 'Sin dato')) ?></div>
                  </div>
                  <div class="span-6">
                    <div class="label">Motivo</div>
                    <div class="value"><?= h((string) ($citacion['motivo'] ?? 'Sin motivo')) ?></div>
                  </div>
                  <div class="span-6">
                    <div class="label">Lugar</div>
                    <div class="value"><?= h((string) ($citacion['lugar'] ?? 'Sin lugar')) ?></div>
                  </div>
                  <div class="span-3">
                    <div class="label">Fecha</div>
                    <div class="value"><?= h(format_date_short((string) ($citacion['fecha'] ?? ''))) ?></div>
                  </div>
                  <div class="span-3">
                    <div class="label">Hora</div>
                    <div class="value"><?= h(substr((string) ($citacion['hora'] ?? ''), 0, 5)) ?></div>
                  </div>
                  <div class="span-6">
                    <div class="label">Tipo de diligencia</div>
                    <div class="value"><?= h((string) ($citacion['tipo_diligencia'] ?? 'Sin tipo')) ?></div>
                  </div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="muted" style="margin-top:8px;">Se listan solo citaciones vigentes del accidente. Se consideran vigentes si, a la fecha y hora actual, aun no han vencido.</div>
          <div class="muted" style="margin-top:6px;">Marcadores sugeridos para la plantilla: `citacion1_motivo`, `citacion1_lugar`, `citacion1_fecha`, `citacion1_hora`, `citacion1_persona`, `citacion1_en_calidad`, y asi sucesivamente. Tambien puedes usar bloque repetible `citaciones` o `diligencias`.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="footer-actions">
      <a class="btn" href="<?= h($returnTo) ?>">Cancelar</a>
      <button class="btn primary" type="submit" <?= $citacionesVigentes === [] ? 'disabled' : '' ?>>Generar y descargar Word</button>
    </div>
  </form>
</div>
</body>
</html>
