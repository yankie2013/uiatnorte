<?php
// marcador_manifestacion_propietario.php
// Uso: marcador_manifestacion_propietario.php?propietario_id=17&accidente_id=20&download=1

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

if (class_exists('ZipArchive')) {
    \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::ZIPARCHIVE);
} else {
    \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::PCLZIP);
}
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0775, true);
}
\PhpOffice\PhpWord\Settings::setTempDir($tmpDir);

require __DIR__ . '/db.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "db.php no define \$pdo.";
    exit;
}

$propietario_id = isset($_GET['propietario_id']) ? (int) $_GET['propietario_id'] : 0;
$accidente_id   = isset($_GET['accidente_id']) ? (int) $_GET['accidente_id'] : 0;
$download       = isset($_GET['download']) && $_GET['download'] == '1';

if ($propietario_id <= 0 || $accidente_id <= 0) {
    echo "Parámetros inválidos. Pasa propietario_id y accidente_id.";
    exit;
}

function fecha_abrev($fecha)
{
    if (!$fecha) {
        return '';
    }
    $t = strtotime($fecha);
    if (!$t) {
        return '';
    }

    $meses = [
        1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR',
        5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO',
        9 => 'SET', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
    ];

    return date('d', $t) . $meses[(int) date('m', $t)] . date('Y', $t);
}

function calcula_edad($fecha_nac, $fecha_ref)
{
    if (!$fecha_nac || !$fecha_ref) {
        return '';
    }
    $dn = strtotime($fecha_nac);
    $dr = strtotime($fecha_ref);
    if (!$dn || !$dr) {
        return '';
    }

    $edad = date('Y', $dr) - date('Y', $dn);
    if (date('m-d', $dr) < date('m-d', $dn)) {
        $edad--;
    }

    return $edad;
}

function to_str($v)
{
    return $v === null ? '' : (string) $v;
}

function slug_nombre_archivo($texto)
{
    $texto = trim((string) $texto);
    if ($texto === '') {
        return 'sin_apellido';
    }

    $reemplazos = [
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'Ñ' => 'N', 'ñ' => 'n',
    ];
    $texto = strtr($texto, $reemplazos);
    $texto = preg_replace('/[^A-Za-z0-9]+/', '_', $texto);
    $texto = trim((string) $texto, '_');

    return $texto !== '' ? strtolower($texto) : 'sin_apellido';
}

function reemplazar_texto_docx($docxPath, array $mapa)
{
    if (!class_exists('ZipArchive')) {
        return;
    }

    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        return;
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);
        if (!preg_match('#^word/(document|header[0-9]+|footer[0-9]+)\.xml$#', (string) $entryName)) {
            continue;
        }

        $contenido = $zip->getFromName($entryName);
        if ($contenido === false || $contenido === '') {
            continue;
        }

        $actualizado = str_replace(array_keys($mapa), array_values($mapa), $contenido);
        if ($actualizado !== $contenido) {
            $zip->addFromString($entryName, $actualizado);
        }
    }

    $zip->close();
}

try {
    $stmt = $pdo->prepare("
        SELECT pv.*,
               iv.orden_participacion,
               iv.vehiculo_id,
               iv.observaciones AS veh_observaciones,
               v.placa,
               v.color,
               v.anio,
               v.notas AS veh_notas,
               COALESCE(car.nombre, '') AS veh_clase,
               COALESCE(mar.nombre, '') AS veh_marca,
               COALESCE(modv.nombre, '') AS veh_modelo,
               pn.*,
               pr.id AS rep_persona_id_real,
               pr.tipo_doc AS rep_tipo_doc,
               pr.num_doc AS rep_num_doc,
               pr.apellido_paterno AS rep_apellido_paterno,
               pr.apellido_materno AS rep_apellido_materno,
               pr.nombres AS rep_nombres,
               pr.sexo AS rep_sexo,
               pr.fecha_nacimiento AS rep_fecha_nacimiento,
               pr.estado_civil AS rep_estado_civil,
               pr.nacionalidad AS rep_nacionalidad,
               pr.grado_instruccion AS rep_grado_instruccion,
               pr.ocupacion AS rep_ocupacion,
               pr.nombre_padre AS rep_nombre_padre,
               pr.nombre_madre AS rep_nombre_madre,
               pr.domicilio AS rep_domicilio,
               pr.domicilio_departamento AS rep_domicilio_departamento,
               pr.domicilio_provincia AS rep_domicilio_provincia,
               pr.domicilio_distrito AS rep_domicilio_distrito,
               pr.celular AS rep_celular,
               pr.email AS rep_email
          FROM propietario_vehiculo pv
          LEFT JOIN involucrados_vehiculos iv ON iv.id = pv.vehiculo_inv_id
          LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id
          LEFT JOIN carroceria_vehiculo car ON car.id = v.carroceria_id
          LEFT JOIN marcas_vehiculo mar ON mar.id = v.marca_id
          LEFT JOIN modelos_vehiculo modv ON modv.id = v.modelo_id
          LEFT JOIN personas pn ON pn.id = pv.propietario_persona_id
          LEFT JOIN personas pr ON pr.id = pv.representante_persona_id
         WHERE pv.id = :id AND pv.accidente_id = :accidente_id
         LIMIT 1
    ");
    $stmt->execute([
        ':id' => $propietario_id,
        ':accidente_id' => $accidente_id,
    ]);
    $propietario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$propietario) {
        echo "Propietario no encontrado para ese accidente.";
        exit;
    }

    $stmtA = $pdo->prepare("SELECT * FROM accidentes WHERE id = :id LIMIT 1");
    $stmtA->execute([':id' => $accidente_id]);
    $accidente = $stmtA->fetch(PDO::FETCH_ASSOC);
    if (!$accidente) {
        echo "Accidente no encontrado.";
        exit;
    }

    $fiscalia = null;
    $fiscal = null;

    if (!empty($accidente['fiscalia_id'])) {
        $stF = $pdo->prepare("SELECT * FROM fiscalia WHERE id = :id LIMIT 1");
        $stF->execute([':id' => $accidente['fiscalia_id']]);
        $fiscalia = $stF->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!empty($accidente['fiscal_id'])) {
        $stFi = $pdo->prepare("SELECT * FROM fiscales WHERE id = :id LIMIT 1");
        $stFi->execute([':id' => $accidente['fiscal_id']]);
        $fiscal = $stFi->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Exception $e) {
    echo "Error DB: " . $e->getMessage();
    exit;
}

$personaDeclarante = [];
if ((string) ($propietario['tipo_propietario'] ?? '') === 'JURIDICA' && !empty($propietario['rep_persona_id_real'])) {
    $personaDeclarante = [
        'tipo_doc' => $propietario['rep_tipo_doc'] ?? '',
        'num_doc' => $propietario['rep_num_doc'] ?? '',
        'apellido_paterno' => $propietario['rep_apellido_paterno'] ?? '',
        'apellido_materno' => $propietario['rep_apellido_materno'] ?? '',
        'nombres' => $propietario['rep_nombres'] ?? '',
        'sexo' => $propietario['rep_sexo'] ?? '',
        'fecha_nacimiento' => $propietario['rep_fecha_nacimiento'] ?? '',
        'estado_civil' => $propietario['rep_estado_civil'] ?? '',
        'nacionalidad' => $propietario['rep_nacionalidad'] ?? '',
        'grado_instruccion' => $propietario['rep_grado_instruccion'] ?? '',
        'ocupacion' => $propietario['rep_ocupacion'] ?? '',
        'nombre_padre' => $propietario['rep_nombre_padre'] ?? '',
        'nombre_madre' => $propietario['rep_nombre_madre'] ?? '',
        'domicilio' => $propietario['rep_domicilio'] ?? '',
        'domicilio_departamento' => $propietario['rep_domicilio_departamento'] ?? '',
        'domicilio_provincia' => $propietario['rep_domicilio_provincia'] ?? '',
        'domicilio_distrito' => $propietario['rep_domicilio_distrito'] ?? '',
        'celular' => $propietario['rep_celular'] ?? '',
        'email' => $propietario['rep_email'] ?? '',
    ];
} else {
    $personaDeclarante = [
        'tipo_doc' => $propietario['tipo_doc'] ?? '',
        'num_doc' => $propietario['num_doc'] ?? '',
        'apellido_paterno' => $propietario['apellido_paterno'] ?? '',
        'apellido_materno' => $propietario['apellido_materno'] ?? '',
        'nombres' => $propietario['nombres'] ?? '',
        'sexo' => $propietario['sexo'] ?? '',
        'fecha_nacimiento' => $propietario['fecha_nacimiento'] ?? '',
        'estado_civil' => $propietario['estado_civil'] ?? '',
        'nacionalidad' => $propietario['nacionalidad'] ?? '',
        'grado_instruccion' => $propietario['grado_instruccion'] ?? '',
        'ocupacion' => $propietario['ocupacion'] ?? '',
        'nombre_padre' => $propietario['nombre_padre'] ?? '',
        'nombre_madre' => $propietario['nombre_madre'] ?? '',
        'domicilio' => $propietario['domicilio'] ?? '',
        'domicilio_departamento' => $propietario['domicilio_departamento'] ?? '',
        'domicilio_provincia' => $propietario['domicilio_provincia'] ?? '',
        'domicilio_distrito' => $propietario['domicilio_distrito'] ?? '',
        'celular' => $propietario['celular'] ?? '',
        'email' => $propietario['email'] ?? '',
    ];
}

$replacements = [];

foreach ($propietario as $col => $val) {
    $replacements['PROPIETARIO_' . strtoupper((string) $col)] = to_str($val);
}
foreach ($personaDeclarante as $col => $val) {
    $replacements['PERSONA_' . strtoupper((string) $col)] = to_str($val);
}

$apellidos = trim(
    (string) ($personaDeclarante['apellido_paterno'] ?? '') . ' ' .
    (string) ($personaDeclarante['apellido_materno'] ?? '')
);
$nombres = trim((string) ($personaDeclarante['nombres'] ?? ''));
$nombreCompleto = trim($nombres . ' ' . $apellidos);

$replacements['INVOL_ROL_NOMBRE'] = 'Propietario';
$replacements['INVOL_ES_CONDUCTOR'] = 'NO';
$replacements['CONDUCTOR_APELLIDOS'] = $apellidos;
$replacements['CONDUCTOR_NOMBRES'] = $nombres;
$replacements['CONDUCTOR_NOMBRE_COMPLETO'] = $nombreCompleto;
$replacements['CONDUCTOR_DNI'] = to_str($personaDeclarante['num_doc'] ?? '');
$replacements['CONDUCTOR_FECHA_NAC_ABREV'] = fecha_abrev($personaDeclarante['fecha_nacimiento'] ?? '');
$replacements['CONDUCTOR_EDAD_AL_ACCIDENTE'] = calcula_edad($personaDeclarante['fecha_nacimiento'] ?? '', $accidente['fecha_accidente'] ?? '');
$replacements['CONDUCTOR_SEXO'] = to_str($personaDeclarante['sexo'] ?? '');
$replacements['CONDUCTOR_LESION'] = '';
$replacements['CONDUCTOR_ORDEN_PERSONA'] = to_str($propietario['orden_participacion'] ?? '');
$replacements['PERSONA_DEPARTAMENTO_NAC'] = to_str($personaDeclarante['departamento_nac'] ?? '');
$replacements['PERSONA_PROVINCIA_NAC'] = to_str($personaDeclarante['provincia_nac'] ?? '');
$replacements['PERSONA_DISTRITO_NAC'] = to_str($personaDeclarante['distrito_nac'] ?? '');

$observaciones = [];
if ((string) ($propietario['tipo_propietario'] ?? '') === 'JURIDICA') {
    $observaciones[] = 'Declara en calidad de representante de ' . to_str($propietario['razon_social'] ?? '');
    if (!empty($propietario['rol_legal'])) {
        $observaciones[] = 'Rol legal: ' . to_str($propietario['rol_legal']);
    }
} else {
    $observaciones[] = 'Declara en calidad de propietario del vehículo.';
}
if (!empty($propietario['placa'])) {
    $observaciones[] = 'Placa: ' . to_str($propietario['placa']);
}
if (!empty($propietario['observaciones'])) {
    $observaciones[] = trim((string) $propietario['observaciones']);
}
$replacements['CONDUCTOR_OBSERVACIONES'] = implode(' ', array_filter($observaciones));

$replacements['ABOGADO_EXISTE'] = 'NO';
$replacements['ABOGADO_NOMBRES'] = '';
$replacements['ABOGADO_APELLIDO_PATERNO'] = '';
$replacements['ABOGADO_APELLIDO_MATERNO'] = '';
$replacements['ABOGADO_NOMBRE_COMPLETO'] = '';
$replacements['ABOGADO_COLEGIATURA'] = '';
$replacements['ABOGADO_REGISTRO'] = '';
$replacements['ABOGADO_CASILLA_ELECTRONICA'] = '';
$replacements['ABOGADO_DOMICILIO_PROCESAL'] = '';
$replacements['ABOGADO_CELULAR'] = '';
$replacements['ABOGADO_EMAIL'] = '';
$replacements['ABOGADO_CONDICION'] = '';

$replacements['VEH_ID'] = to_str($propietario['vehiculo_id'] ?? '');
$replacements['VEH_PLACA'] = to_str($propietario['placa'] ?? '');
$replacements['VEH_MARCA'] = to_str($propietario['veh_marca'] ?? '');
$replacements['VEH_MODELO'] = to_str($propietario['veh_modelo'] ?? '');
$replacements['VEH_COLOR'] = to_str($propietario['color'] ?? '');
$replacements['VEH_CLASE'] = to_str($propietario['veh_clase'] ?? '');
$replacements['CONDUCTOR_VEH_ID'] = to_str($propietario['vehiculo_id'] ?? '');
$replacements['CONDUCTOR_VEH_PLACA'] = to_str($propietario['placa'] ?? '');
$replacements['CONDUCTOR_VEH_MARCA'] = to_str($propietario['veh_marca'] ?? '');
$replacements['CONDUCTOR_VEH_MODELO'] = to_str($propietario['veh_modelo'] ?? '');
$replacements['CONDUCTOR_VEH_COLOR'] = to_str($propietario['color'] ?? '');
$replacements['CONDUCTOR_VEH_CLASE'] = to_str($propietario['veh_clase'] ?? '');
$replacements['CONDUCTOR_VEH_SOAT'] = '';
$replacements['CONDUCTOR_VEH_OBS'] = to_str($propietario['veh_observaciones'] ?? $propietario['veh_notas'] ?? '');

if ($fiscalia) {
    $replacements['FISCALIA_ID'] = to_str($fiscalia['id'] ?? '');
    $replacements['FISCALIA_NOMBRE'] = to_str($fiscalia['nombre'] ?? '');
    $replacements['FISCALIA_DIRECCION'] = to_str($fiscalia['direccion'] ?? '');
    $replacements['FISCALIA_NOTAS'] = to_str($fiscalia['notas'] ?? '');
} else {
    $replacements['FISCALIA_ID'] = '';
    $replacements['FISCALIA_NOMBRE'] = '';
    $replacements['FISCALIA_DIRECCION'] = '';
    $replacements['FISCALIA_NOTAS'] = '';
}

if ($fiscal) {
    $replacements['FISCAL_ID'] = to_str($fiscal['id'] ?? '');
    $replacements['FISCAL_NOMBRES'] = to_str($fiscal['nombres'] ?? '');
    $replacements['FISCAL_APELLIDO_PATERNO'] = to_str($fiscal['apellido_paterno'] ?? '');
    $replacements['FISCAL_APELLIDO_MATERNO'] = to_str($fiscal['apellido_materno'] ?? '');
    $replacements['FISCAL_NOMBRE_COMPLETO'] = trim(
        to_str($fiscal['nombres'] ?? '') . ' ' .
        to_str($fiscal['apellido_paterno'] ?? '') . ' ' .
        to_str($fiscal['apellido_materno'] ?? '')
    );
    $replacements['FISCAL_CARGO'] = to_str($fiscal['cargo'] ?? $fiscal['grado'] ?? '');
    $replacements['FISCAL_CELULAR'] = to_str($fiscal['telefono'] ?? $fiscal['celular'] ?? '');
    $replacements['FISCAL_EMAIL'] = to_str($fiscal['correo'] ?? $fiscal['email'] ?? '');
} else {
    $replacements['FISCAL_ID'] = '';
    $replacements['FISCAL_NOMBRES'] = '';
    $replacements['FISCAL_APELLIDO_PATERNO'] = '';
    $replacements['FISCAL_APELLIDO_MATERNO'] = '';
    $replacements['FISCAL_NOMBRE_COMPLETO'] = '';
    $replacements['FISCAL_CARGO'] = '';
    $replacements['FISCAL_CELULAR'] = '';
    $replacements['FISCAL_EMAIL'] = '';
}

foreach ($accidente as $col => $val) {
    $replacements['ACCIDENTE_' . strtoupper((string) $col)] = to_str($val);
}
$replacements['ACCIDENTE_LUGAR'] = to_str($accidente['lugar'] ?? '');
$replacements['ACCIDENTE_REFERENCIA'] = to_str($accidente['referencia'] ?? '');
$replacements['ACCIDENTE_FECHA_ABREV'] = fecha_abrev($accidente['fecha_accidente'] ?? '');
$replacements['ACCIDENTE_HORA'] = !empty($accidente['fecha_accidente']) ? date('H:i', strtotime($accidente['fecha_accidente'])) : '';
$replacements['ACCIDENTE_FECHA'] = $replacements['ACCIDENTE_FECHA_ABREV'];
$replacements['HOY_FECHA'] = date('Y-m-d');
$replacements['HOY_HORA'] = date('H:i:s');
$replacements['INFORME_GENERADO_POR'] = '';

$templatePath = __DIR__ . '/plantillas/manifestacion_propietario.docx';
$usingFallbackTemplate = false;
if (!file_exists($templatePath)) {
    $templatePath = __DIR__ . '/plantillas/manifestacion_investigado.docx';
    $usingFallbackTemplate = true;
}
if (!file_exists($templatePath)) {
    echo "Plantilla no encontrada para propietario.";
    exit;
}

try {
    $template = new TemplateProcessor($templatePath);

    foreach ($replacements as $key => $value) {
        if ($value === null) {
            $value = '';
        }
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        try {
            $template->setValue($key, $value);
        } catch (Exception $e) {
        }
    }

    $apellidoArchivo = trim((string) ($personaDeclarante['apellido_paterno'] ?? ''));
    if ($apellidoArchivo === '' && $apellidos !== '') {
        $partesApellidos = preg_split('/\s+/', $apellidos);
        $apellidoArchivo = trim((string) ($partesApellidos[0] ?? ''));
    }

    $outputName = 'manifestacion_propietario_' . slug_nombre_archivo($apellidoArchivo) . '.docx';
    $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $outputName;
    $template->saveAs($tempFile);

    if ($usingFallbackTemplate) {
        reemplazar_texto_docx($tempFile, [
            'en calidad de investigado' => 'en calidad de propietario',
            'EN CALIDAD DE INVESTIGADO' => 'EN CALIDAD DE PROPIETARIO',
        ]);
    }

    if ($download) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . basename($outputName) . '"');
        header('Content-Length: ' . filesize($tempFile));
        readfile($tempFile);
        @unlink($tempFile);
        exit;
    }
} catch (Exception $e) {
    echo "Error generando Word: " . $e->getMessage();
    exit;
}

echo "Documento generado correctamente.";
