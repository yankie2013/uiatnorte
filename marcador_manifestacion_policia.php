<?php
// marcador_manifestacion_policia.php
// Uso: marcador_manifestacion_policia.php?policia_id=10&accidente_id=32&download=1

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

// ------------ Dependencias ------------
require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

// ------------ Conexión ------------
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

// ------------ Parámetros GET ------------
$policia_id   = isset($_GET['policia_id'])   ? (int)$_GET['policia_id']   : 0;
$accidente_id = isset($_GET['accidente_id']) ? (int)$_GET['accidente_id'] : 0;
$download     = isset($_GET['download']) && ($_GET['download'] == '1');

if ($policia_id <= 0 || $accidente_id <= 0) {
    echo "Parámetros inválidos.";
    exit;
}

// ------------ Helpers ------------

// formato solicitado: 02DIC2025
function fecha_abrev($fecha) {
    if (!$fecha) return '';
    $t = strtotime($fecha);
    if (!$t) return '';

    $meses = [
        1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR',
        5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO',
        9 => 'SET', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC'
    ];

    return date('d', $t) . $meses[(int)date('m', $t)] . date('Y', $t);
}

function calcula_edad($fecha_nac, $fecha_ref) {
    if (!$fecha_nac || !$fecha_ref) return '';
    $dn = strtotime($fecha_nac);
    $dr = strtotime($fecha_ref);
    if (!$dn || !$dr) return '';

    $edad = date('Y', $dr) - date('Y', $dn);

    if (date('m-d', $dr) < date('m-d', $dn)) {
        $edad--;
    }

    return $edad;
}

function to_str($v) {
    return ($v === null) ? '' : (string)$v;
}

function slug_nombre_archivo($texto) {
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

// ------------ Obtener datos ------------
try {
    // policial_interviniente
    $stmtP = $pdo->prepare("SELECT * FROM policial_interviniente WHERE id = :id LIMIT 1");
    $stmtP->execute(['id' => $policia_id]);
    $policia = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$policia) {
        echo "Policía no encontrado.";
        exit;
    }

    // persona vinculada
    $persona = null;
    if (!empty($policia['persona_id'])) {
        $stmtPer = $pdo->prepare("SELECT * FROM personas WHERE id = :id LIMIT 1");
        $stmtPer->execute(['id' => $policia['persona_id']]);
        $persona = $stmtPer->fetch(PDO::FETCH_ASSOC);
    }

    // accidente
    $stmtA = $pdo->prepare("SELECT * FROM accidentes WHERE id = :id LIMIT 1");
    $stmtA->execute(['id' => $accidente_id]);
    $accidente = $stmtA->fetch(PDO::FETCH_ASSOC);

    if (!$accidente) {
        echo "Accidente no encontrado.";
        exit;
    }

    // fiscalia y fiscal vinculados al accidente
    $fiscalia = null;
    if (!empty($accidente['fiscalia_id'])) {
        $stmtFiscalia = $pdo->prepare("SELECT * FROM fiscalia WHERE id = :id LIMIT 1");
        $stmtFiscalia->execute(['id' => (int) $accidente['fiscalia_id']]);
        $fiscalia = $stmtFiscalia->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $fiscal = null;
    if (!empty($accidente['fiscal_id'])) {
        $stmtFiscal = $pdo->prepare("SELECT * FROM fiscales WHERE id = :id LIMIT 1");
        $stmtFiscal->execute(['id' => (int) $accidente['fiscal_id']]);
        $fiscal = $stmtFiscal->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ultima manifestacion registrada para este efectivo en este accidente
    $manifestacion = null;
    if (!empty($policia['persona_id'])) {
        $stmtM = $pdo->prepare(
            "SELECT *
               FROM Manifestacion
              WHERE persona_id = :persona_id
                AND accidente_id = :accidente_id
           ORDER BY id DESC
              LIMIT 1"
        );
        $stmtM->execute([
            'persona_id' => (int) $policia['persona_id'],
            'accidente_id' => $accidente_id,
        ]);
        $manifestacion = $stmtM->fetch(PDO::FETCH_ASSOC) ?: null;
    }

} catch (Exception $e) {
    echo "Error DB: " . $e->getMessage();
    exit;
}

// ------------ Preparar marcadores ------------

$replacements = [];

// --- policial_interviniente (directo de la tabla) ---
foreach ($policia as $col => $val) {
    $replacements['policia_' . $col] = to_str($val);
}

// --- persona vinculada ---
if ($persona) {

    // todos sus campos
    foreach ($persona as $col => $val) {
        $replacements['policia_persona_' . $col] = to_str($val);
    }

    // nombres compuestos
    $apellido_paterno  = $persona['apellido_paterno'] ?? '';
    $apellido_materno  = $persona['apellido_materno'] ?? '';
    $nombres           = $persona['nombres'] ?? '';

    $replacements['policia_apellidos'] = trim($apellido_paterno . ' ' . $apellido_materno);
    $replacements['policia_nombres']   = $nombres;
    $replacements['policia_dni']       = $persona['num_doc'] ?? '';

    // fecha nacimiento abreviada
    $replacements['policia_fecha_nacimiento_abrev'] = fecha_abrev($persona['fecha_nacimiento'] ?? '');

    // edad al accidente
    $replacements['policia_edad_al_accidente'] =
        calcula_edad($persona['fecha_nacimiento'] ?? '', $accidente['fecha_accidente'] ?? '');

} else {
    // sin persona
    $replacements['policia_apellidos'] = '';
    $replacements['policia_nombres'] = '';
    $replacements['policia_dni'] = '';
    $replacements['policia_fecha_nacimiento_abrev'] = '';
    $replacements['policia_edad_al_accidente'] = '';
}

// nombre completo
$replacements['policia_nombre'] = trim(
    ($policia['grado_policial'] ?? '') . ' ' .
    ($replacements['policia_nombres'] ?? '') . ' ' .
    ($replacements['policia_apellidos'] ?? '')
);

// CIP o DNI
$replacements['policia_numero'] = $policia['cip'] ?? ($persona['num_doc'] ?? '');

// cargo / dependencia
$replacements['policia_cargo'] = to_str($policia['dependencia_policial'] ?? $policia['rol_funcion'] ?? '');

// --- accidente ---
foreach ($accidente as $col => $val) {
    $replacements['accidente_' . $col] = to_str($val);
}

// --- fiscalia y fiscal en texto, no como ids numericos ---
$fiscaliaNombre = to_str($fiscalia['nombre'] ?? '');
$fiscalNombreCompleto = $fiscal ? trim(
    to_str($fiscal['nombres'] ?? '') . ' ' .
    to_str($fiscal['apellido_paterno'] ?? '') . ' ' .
    to_str($fiscal['apellido_materno'] ?? '')
) : '';

$replacements['fiscalia_nombre'] = $fiscaliaNombre;
$replacements['fiscalia_direccion'] = to_str($fiscalia['direccion'] ?? '');
$replacements['fiscalia_notas'] = to_str($fiscalia['notas'] ?? '');
$replacements['fiscal_nombre'] = $fiscalNombreCompleto;
$replacements['fiscal_nombres'] = to_str($fiscal['nombres'] ?? '');
$replacements['fiscal_apellido_paterno'] = to_str($fiscal['apellido_paterno'] ?? '');
$replacements['fiscal_apellido_materno'] = to_str($fiscal['apellido_materno'] ?? '');
$replacements['fiscal_cargo'] = to_str($fiscal['cargo'] ?? $fiscal['grado'] ?? '');
$replacements['fiscal_telefono'] = to_str($fiscal['telefono'] ?? $fiscal['celular'] ?? '');
$replacements['fiscal_email'] = to_str($fiscal['correo'] ?? $fiscal['email'] ?? '');

// Si la plantilla usa estos marcadores antiguos, reemplazar con valores legibles.
$replacements['accidente_fiscalia_id'] = $fiscaliaNombre;
$replacements['accidente_fiscal_id'] = $fiscalNombreCompleto;

// Alias en mayusculas compatibles con otras plantillas.
$replacements['FISCALIA_NOMBRE'] = $replacements['fiscalia_nombre'];
$replacements['FISCALIA_DIRECCION'] = $replacements['fiscalia_direccion'];
$replacements['FISCALIA_NOTAS'] = $replacements['fiscalia_notas'];
$replacements['FISCAL_NOMBRE_COMPLETO'] = $replacements['fiscal_nombre'];
$replacements['FISCAL_NOMBRES'] = $replacements['fiscal_nombres'];
$replacements['FISCAL_APELLIDO_PATERNO'] = $replacements['fiscal_apellido_paterno'];
$replacements['FISCAL_APELLIDO_MATERNO'] = $replacements['fiscal_apellido_materno'];
$replacements['FISCAL_CARGO'] = $replacements['fiscal_cargo'];
$replacements['FISCAL_CELULAR'] = $replacements['fiscal_telefono'];
$replacements['FISCAL_EMAIL'] = $replacements['fiscal_email'];

// amigables
$replacements['accidente_lugar']      = to_str($accidente['lugar'] ?? '');
$replacements['accidente_referencia'] = to_str($accidente['referencia'] ?? '');

// fecha abreviada del accidente
$replacements['accidente_fecha_abrev'] = fecha_abrev($accidente['fecha_accidente'] ?? '');

// hora del accidente
$replacements['accidente_hora'] =
    !empty($accidente['fecha_accidente'])
        ? date('H:i', strtotime($accidente['fecha_accidente']))
        : '';

// compatibilidad con ${accidente_fecha}
$replacements['accidente_fecha'] = $replacements['accidente_fecha_abrev'];

// --- manifestacion registrada ---
$manifestacionFecha = (string) ($manifestacion['fecha'] ?? '');
$manifestacionInicio = (string) ($manifestacion['horario_inicio'] ?? '');
$manifestacionTermino = (string) ($manifestacion['hora_termino'] ?? '');
$manifestacionModalidad = (string) ($manifestacion['modalidad'] ?? '');

$replacements['manifestacion_id'] = to_str($manifestacion['id'] ?? '');
$replacements['manifestacion_fecha'] = $manifestacionFecha !== '' ? date('d/m/Y', strtotime($manifestacionFecha)) : '';
$replacements['manifestacion_fecha_abrev'] = fecha_abrev($manifestacionFecha);
$replacements['manifestacion_hora_inicio'] = $manifestacionInicio !== '' ? substr($manifestacionInicio, 0, 5) : '';
$replacements['manifestacion_hora_termino'] = $manifestacionTermino !== '' ? substr($manifestacionTermino, 0, 5) : '';
$replacements['manifestacion_modalidad'] = $manifestacionModalidad;

// alias cortos compatibles con otras plantillas de manifestacion
$replacements['manif_fecha'] = $replacements['manifestacion_fecha'];
$replacements['manif_fecha_abrev'] = $replacements['manifestacion_fecha_abrev'];
$replacements['manif_hora'] = $replacements['manifestacion_hora_inicio'];
$replacements['manif_hora_inicio'] = $replacements['manifestacion_hora_inicio'];
$replacements['manif_hora_termino'] = $replacements['manifestacion_hora_termino'];
$replacements['manif_modalidad'] = $replacements['manifestacion_modalidad'];

// ------------ Cargar plantilla ------------
$templatePath = __DIR__ . '/plantillas/manifestacion_efectivopolicial.docx';
if (!file_exists($templatePath)) {
    echo "Plantilla no encontrada.";
    exit;
}

try {

    $template = new TemplateProcessor($templatePath);

    foreach ($replacements as $key => $value) {
        $template->setValue($key, $value);
    }

    $apellidoArchivo = '';
    if ($persona) {
        $apellidoArchivo = trim((string) ($persona['apellido_paterno'] ?? ''));
    }
    if ($apellidoArchivo === '') {
        $apellidosFallback = trim((string) ($replacements['policia_apellidos'] ?? ''));
        if ($apellidosFallback !== '') {
            $partesApellidos = preg_split('/\s+/', $apellidosFallback);
            $apellidoArchivo = trim((string) ($partesApellidos[0] ?? ''));
        }
    }

    $outputName = 'manifestacion_efectivo_policial_' . slug_nombre_archivo($apellidoArchivo) . '.docx';
    $tempFile = sys_get_temp_dir() . '/' . $outputName;
    $template->saveAs($tempFile);

    if ($download) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="'.$outputName.'"');
        header('Content-Length: ' . filesize($tempFile));
        readfile($tempFile);
        unlink($tempFile);
        exit;
    }

} catch (Exception $e) {
    echo "Error generando Word: " . $e->getMessage();
    exit;
}

echo "Documento generado correctamente.";
