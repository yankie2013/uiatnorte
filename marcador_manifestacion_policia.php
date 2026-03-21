<?php
// marcador_manifestacion_policia.php
// Uso: marcador_manifestacion_policia.php?policia_id=10&accidente_id=32&download=1

ini_set('display_errors', 0);
error_reporting(E_ALL);

// ------------ Dependencias ------------
require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

// ------------ Conexión ------------
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

    $outputName = "manifestacion_efectivopolicial_p{$policia_id}_a{$accidente_id}.docx";
    $tempFile = sys_get_temp_dir() . '/' . $outputName;
    $template->saveAs($tempFile);

    if ($download) {
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
