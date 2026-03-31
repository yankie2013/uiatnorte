<?php
// marcador_manifestacion_investigado.php
// Uso: marcador_manifestacion_investigado.php?involucrado_id=12&accidente_id=32&download=1
// Genera manifestacion_investigado.docx reemplazando marcadores ${CLAVE} con datos de DB.

// ---------------------- Config / entorno ----------------------
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

// ---------------------- Dependencias --------------------------
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

// ---------------------- Conexion DB ---------------------------
require __DIR__ . '/db.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "db.php no define \$pdo.";
    exit;
}

// ---------------------- Parámetros ----------------------------
$involucrado_id = isset($_GET['involucrado_id']) ? (int)$_GET['involucrado_id'] : 0;
$accidente_id   = isset($_GET['accidente_id'])   ? (int)$_GET['accidente_id']   : 0;
$download       = isset($_GET['download']) && ($_GET['download'] == '1');

if ($involucrado_id <= 0 || $accidente_id <= 0) {
    echo "Parámetros inválidos. Pasa involucrado_id y accidente_id.";
    exit;
}

// ---------------------- Helpers -------------------------------
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
    if (date('m-d', $dr) < date('m-d', $dn)) $edad--;
    return $edad;
}

function to_str($v) {
    return ($v === null) ? '' : (string)$v;
}

function slug_nombre_archivo($texto) {
    $texto = trim((string) $texto);
    if ($texto === '') {
        return 'sin_nombre';
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

    return $texto !== '' ? strtolower($texto) : 'sin_nombre';
}

// ---------------------- Obtener datos -------------------------
try {
    // 1) registro en involucrados_personas (asegurar que corresponde al accidente)
    $stmt = $pdo->prepare("
        SELECT ip.*, pp.Nombre AS rol_nombre
        FROM involucrados_personas ip
        LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id
        WHERE ip.id = :id AND ip.accidente_id = :accidente_id
        LIMIT 1
    ");
    $stmt->execute(['id' => $involucrado_id, 'accidente_id' => $accidente_id]);
    $invol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invol) {
        echo "Involucrado no encontrado para ese accidente.";
        exit;
    }

    // 2) persona vinculada (si existe)
    $persona = null;
    if (!empty($invol['persona_id'])) {
        $stmtP = $pdo->prepare("SELECT * FROM personas WHERE id = :id LIMIT 1");
        $stmtP->execute(['id' => $invol['persona_id']]);
        $persona = $stmtP->fetch(PDO::FETCH_ASSOC);
    }

    // 3) accidente
    $stmtA = $pdo->prepare("SELECT * FROM accidentes WHERE id = :id LIMIT 1");
    $stmtA->execute(['id' => $accidente_id]);
    $accidente = $stmtA->fetch(PDO::FETCH_ASSOC);
    if (!$accidente) {
        echo "Accidente no encontrado.";
        exit;
    }

    // ---- Obtener FISCALÍA y FISCAL (si existen) ----
    $fiscalia = null;
    $fiscal = null;
    try {
        if (!empty($accidente['fiscalia_id'])) {
            $stF = $pdo->prepare("SELECT * FROM fiscalia WHERE id = :id LIMIT 1");
            $stF->execute([':id' => $accidente['fiscalia_id']]);
            $fiscalia = $stF->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $e) {
        $fiscalia = null;
    }

    try {
        if (!empty($accidente['fiscal_id'])) {
            $stFi = $pdo->prepare("SELECT * FROM fiscales WHERE id = :id LIMIT 1");
            $stFi->execute([':id' => $accidente['fiscal_id']]);
            $fiscal = $stFi->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $e) {
        $fiscal = null;
    }

    // 4) vehiculo asociado (puede venir en involucrados_personas.vehiculo_id)
    $vehiculo = null;
    if (!empty($invol['vehiculo_id'])) {
        $stmtV = $pdo->prepare("SELECT * FROM vehiculos WHERE id = :id LIMIT 1");
        $stmtV->execute(['id' => $invol['vehiculo_id']]);
        $vehiculo = $stmtV->fetch(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    echo "Error DB: " . $e->getMessage();
    exit;
}

// ---------------------- Preparar marcadores -------------------
$replacements = [];

// RAW: campos de involucrados_personas (prefijo INVOL_)
foreach ($invol as $col => $val) {
    $replacements['INVOL_' . strtoupper($col)] = to_str($val);
}
$replacements['INVOL_ROL_NOMBRE'] = to_str($invol['rol_nombre'] ?? '');

// Es conductor? (simple heurística)
$rol_nombre = $invol['rol_nombre'] ?? '';
$esConductor = (stripos($rol_nombre, 'conductor') !== false) || (!empty($invol['rol_id']));
$replacements['INVOL_ES_CONDUCTOR'] = $esConductor ? 'SI' : 'NO';

// Datos de PERSONA (prefijo PERSONA_)
if ($persona) {
    foreach ($persona as $col => $val) {
        $replacements['PERSONA_' . strtoupper($col)] = to_str($val);
    }

    $apellido_paterno = $persona['apellido_paterno'] ?? '';
    $apellido_materno = $persona['apellido_materno'] ?? '';
    $nombres = $persona['nombres'] ?? ($persona['nombre'] ?? '');

    $replacements['CONDUCTOR_APELLIDOS'] = trim($apellido_paterno . ' ' . $apellido_materno);
    $replacements['CONDUCTOR_NOMBRES'] = to_str($nombres);
    $replacements['CONDUCTOR_NOMBRE_COMPLETO'] = trim($nombres . ' ' . $replacements['CONDUCTOR_APELLIDOS']);
    // en tu tabla 'personas' el campo de documento puede llamarse num_doc, num_documento, dni, etc.
    $replacements['CONDUCTOR_DNI'] = to_str($persona['num_doc'] ?? $persona['dni'] ?? $persona['numero_documento'] ?? '');
    $replacements['CONDUCTOR_FECHA_NAC_ABREV'] = fecha_abrev($persona['fecha_nacimiento'] ?? '');
    $replacements['CONDUCTOR_EDAD_AL_ACCIDENTE'] = calcula_edad($persona['fecha_nacimiento'] ?? '', $accidente['fecha_accidente'] ?? '');
    $replacements['CONDUCTOR_SEXO'] = to_str($persona['sexo'] ?? '');
} else {
    // si no hay persona relacionada, dejamos algunos campos con valores desde involucrado o en blanco
    $replacements['CONDUCTOR_APELLIDOS'] = '';
    $replacements['CONDUCTOR_NOMBRES'] = '';
    $replacements['CONDUCTOR_NOMBRE_COMPLETO'] = to_str($invol['nombre'] ?? '');
    $replacements['CONDUCTOR_DNI'] = to_str($invol['dni'] ?? $invol['numero_documento'] ?? '');
    $replacements['CONDUCTOR_FECHA_NAC_ABREV'] = '';
    $replacements['CONDUCTOR_EDAD_AL_ACCIDENTE'] = '';
    $replacements['CONDUCTOR_SEXO'] = to_str($invol['sexo'] ?? '');
}

// ---------------------- BÚSQUEDA E INTEGRACIÓN DE ABOGADO (si aplica) ----------------------
$abogado = null;

if (!empty($invol['persona_id'])) {
    try {
        $stmtAb = $pdo->prepare("
            SELECT *
            FROM abogados
            WHERE persona_id = :pid AND accidente_id = :aid
            LIMIT 1
        ");
        $stmtAb->execute([
            ':pid' => $invol['persona_id'],
            ':aid' => $accidente_id
        ]);
        $abogado = $stmtAb->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $abogado = null;
    }
}

if ($abogado) {
    $replacements['ABOGADO_EXISTE'] = 'SI';
    $replacements['ABOGADO_NOMBRES'] = to_str($abogado['nombres'] ?? '');
    $replacements['ABOGADO_APELLIDO_PATERNO'] = to_str($abogado['apellido_paterno'] ?? '');
    $replacements['ABOGADO_APELLIDO_MATERNO'] = to_str($abogado['apellido_materno'] ?? '');
    $replacements['ABOGADO_NOMBRE_COMPLETO'] = trim(
        ($abogado['nombres'] ?? '') . ' ' .
        ($abogado['apellido_paterno'] ?? '') . ' ' .
        ($abogado['apellido_materno'] ?? '')
    );
    $replacements['ABOGADO_COLEGIATURA'] = to_str($abogado['colegiatura'] ?? '');
    $replacements['ABOGADO_REGISTRO'] = to_str($abogado['registro'] ?? '');
    $replacements['ABOGADO_CASILLA_ELECTRONICA'] = to_str($abogado['casilla_electronica'] ?? '');
    $replacements['ABOGADO_DOMICILIO_PROCESAL'] = to_str($abogado['domicilio_procesal'] ?? '');
    $replacements['ABOGADO_CELULAR'] = to_str($abogado['celular'] ?? '');
    $replacements['ABOGADO_EMAIL'] = to_str($abogado['email'] ?? '');
    $replacements['ABOGADO_CONDICION'] = to_str($abogado['condicion'] ?? '');
} else {
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
}

// ---------------------- Campos específicos del involucrado -------------------
$replacements['CONDUCTOR_LESION'] = to_str($invol['lesion'] ?? '');
$replacements['CONDUCTOR_OBSERVACIONES'] = to_str($invol['observaciones'] ?? '');
$replacements['CONDUCTOR_ORDEN_PERSONA'] = to_str($invol['orden_persona'] ?? '');

// ---------------------- Datos del VEHÍCULO (prefijo VEH_ y equivalentes para conductor)
if ($vehiculo) {
    foreach ($vehiculo as $col => $val) {
        $replacements['VEH_' . strtoupper($col)] = to_str($val);
    }

    $replacements['CONDUCTOR_VEH_ID']    = to_str($vehiculo['id'] ?? '');
    $replacements['CONDUCTOR_VEH_PLACA'] = to_str($vehiculo['placa'] ?? $vehiculo['placa_chasis'] ?? '');
    // en tu esquema puede haber marca/modelo como ids; si hay tablas relacionadas (marcas, modelos) debes hacer JOINs adicionales.
    $replacements['CONDUCTOR_VEH_MARCA'] = to_str($vehiculo['marca'] ?? '');
    $replacements['CONDUCTOR_VEH_MODELO'] = to_str($vehiculo['modelo'] ?? '');
    $replacements['CONDUCTOR_VEH_COLOR'] = to_str($vehiculo['color'] ?? '');
    $replacements['CONDUCTOR_VEH_CLASE'] = to_str($vehiculo['clase'] ?? '');
    $replacements['CONDUCTOR_VEH_SOAT'] = to_str($vehiculo['soat'] ?? '');
    $replacements['CONDUCTOR_VEH_OBS'] = to_str($vehiculo['observaciones'] ?? $vehiculo['notas'] ?? '');
} else {
    $replacements['CONDUCTOR_VEH_ID'] = '';
    $replacements['CONDUCTOR_VEH_PLACA'] = '';
    $replacements['CONDUCTOR_VEH_MARCA'] = '';
    $replacements['CONDUCTOR_VEH_MODELO'] = '';
    $replacements['CONDUCTOR_VEH_COLOR'] = '';
    $replacements['CONDUCTOR_VEH_CLASE'] = '';
    $replacements['CONDUCTOR_VEH_SOAT'] = '';
    $replacements['CONDUCTOR_VEH_OBS'] = '';
}

// ---------------------- Datos de FISCALÍA y FISCAL ----------------------
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
        ($fiscal['nombres'] ?? '') . ' ' .
        ($fiscal['apellido_paterno'] ?? '') . ' ' .
        ($fiscal['apellido_materno'] ?? '')
    );
    $replacements['FISCAL_CARGO'] = to_str($fiscal['cargo'] ?? $fiscal['grado'] ?? '');
    $replacements['FISCAL_CELULAR'] = to_str($fiscal['celular'] ?? '');
    $replacements['FISCAL_EMAIL'] = to_str($fiscal['email'] ?? '');
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

// ---------------------- Datos del ACCIDENTE (prefijo ACCIDENTE_)
foreach ($accidente as $col => $val) {
    $replacements['ACCIDENTE_' . strtoupper($col)] = to_str($val);
}
$replacements['ACCIDENTE_LUGAR'] = to_str($accidente['lugar'] ?? '');
$replacements['ACCIDENTE_REFERENCIA'] = to_str($accidente['referencia'] ?? '');
$replacements['ACCIDENTE_FECHA_ABREV'] = fecha_abrev($accidente['fecha_accidente'] ?? '');
$replacements['ACCIDENTE_HORA'] = !empty($accidente['fecha_accidente']) ? date('H:i', strtotime($accidente['fecha_accidente'])) : '';
$replacements['ACCIDENTE_FECHA'] = $replacements['ACCIDENTE_FECHA_ABREV'];

// Metadatos generales
$replacements['HOY_FECHA'] = date('Y-m-d');
$replacements['HOY_HORA']  = date('H:i:s');
$replacements['INFORME_GENERADO_POR'] = ''; // opcional: nombre del usuario que genera el informe

// ---------------------- Plantilla y reemplazos ----------------
$templatePath = __DIR__ . '/plantillas/manifestacion_investigado.docx';
if (!file_exists($templatePath)) {
    echo "Plantilla no encontrada: {$templatePath}";
    exit;
}

try {
    $template = new TemplateProcessor($templatePath);

    // TemplateProcessor espera la clave SIN ${} (por ejemplo 'CONDUCTOR_NOMBRE_COMPLETO')
    foreach ($replacements as $key => $value) {
        $k = $key;
        if ($value === null) $value = '';
        if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        try {
            $template->setValue($k, $value);
        } catch (Exception $e) {
            // no interrumpimos por un marcador fallido; log opcional
            // error_log("No se pudo reemplazar marcador {$k}: " . $e->getMessage());
        }
    }

    // Guardar en archivo temporal y forzar descarga si corresponde
    $apellidoArchivo = '';
    if ($persona) {
        $apellidoArchivo = trim((string) ($persona['apellido_paterno'] ?? ''));
    }
    if ($apellidoArchivo === '') {
        $apellidosFallback = trim((string) ($replacements['CONDUCTOR_APELLIDOS'] ?? ''));
        if ($apellidosFallback !== '') {
            $partesApellidos = preg_split('/\s+/', $apellidosFallback);
            $apellidoArchivo = trim((string) ($partesApellidos[0] ?? ''));
        }
    }

    $outputName = 'manifestacion_investigado_' . slug_nombre_archivo($apellidoArchivo) . '.docx';
    $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $outputName;
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
