<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';
require __DIR__ . '/config_api.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET NAMES utf8mb4');

function out(bool $ok, array $data = [], string $msg = '', int $status = 200): never
{
    http_response_code($status);
    echo json_encode([
        'ok' => $ok,
        'data' => $data,
        'msg' => $msg,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function norm_date(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $value = trim($value);

    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
        [$day, $month, $year] = explode('/', $value);
        return sprintf('%s-%s-%s', $year, $month, $day);
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function edad_entera(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (preg_match('/(\d+)/', (string) $value, $matches)) {
        return (int) $matches[1];
    }

    return null;
}

function getv(array $source, array $keys): mixed
{
    foreach ($keys as $key) {
        if (isset($source[$key]) && $source[$key] !== '' && $source[$key] !== null) {
            return $source[$key];
        }
    }

    return null;
}

function save_photo_local(string $dni, ?string $fotoBase64): ?string
{
    if ($fotoBase64 === null || $fotoBase64 === '') {
        return null;
    }

    $dir = __DIR__ . '/uploads/reniec';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }

    $binary = base64_decode($fotoBase64, true);
    if ($binary === false) {
        return null;
    }

    $dest = $dir . '/' . $dni . '.jpg';
    if (@file_put_contents($dest, $binary) === false) {
        return null;
    }

    return 'uploads/reniec/' . $dni . '.jpg';
}

function sexo_mf(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $upper = function_exists('mb_strtoupper')
        ? mb_strtoupper(trim($value), 'UTF-8')
        : strtoupper(trim($value));

    if ($upper === 'M' || $upper === 'MASCULINO') {
        return 'M';
    }

    if ($upper === 'F' || $upper === 'FEMENINO') {
        return 'F';
    }

    return null;
}

function safe_error_message(string $message): string
{
    $lower = function_exists('mb_strtolower')
        ? mb_strtolower($message, 'UTF-8')
        : strtolower($message);

    if (str_contains($lower, 'dni debe tener 8 dígitos') || str_contains($lower, 'dni inválido')) {
        return 'DNI inválido (8 dígitos).';
    }

    if (str_contains($lower, 'no se encontraron resultados') || str_contains($lower, 'error http 404')) {
        return 'No se encontraron datos para ese DNI.';
    }

    if (
        str_contains($lower, 'invalid_token')
        || str_contains($lower, 'token inválido')
        || str_contains($lower, 'falta configurar api_token')
        || str_contains($lower, 'configura api_token')
        || str_contains($lower, 'error http 401')
    ) {
        return 'El servicio RENIEC no está disponible en este momento.';
    }

    if (
        str_contains($lower, 'error curl')
        || str_contains($lower, 'timed out')
        || str_contains($lower, 'could not resolve host')
        || str_contains($lower, 'error http 503')
        || str_contains($lower, 'no se pudo conectar con el servicio')
    ) {
        return 'No se pudo conectar con el servicio RENIEC.';
    }

    if (str_contains($lower, 'api devolvió html') || str_contains($lower, 'respuesta inválida')) {
        return 'El servicio RENIEC devolvió una respuesta inválida.';
    }

    return 'No se pudo consultar RENIEC.';
}

function status_for_error_message(string $message): int
{
    $lower = function_exists('mb_strtolower')
        ? mb_strtolower($message, 'UTF-8')
        : strtolower($message);

    if (str_contains($lower, 'dni debe tener 8 dígitos') || str_contains($lower, 'dni inválido')) {
        return 400;
    }

    if (str_contains($lower, 'no se encontraron resultados') || str_contains($lower, 'error http 404')) {
        return 404;
    }

    if (
        str_contains($lower, 'invalid_token')
        || str_contains($lower, 'token inválido')
        || str_contains($lower, 'falta configurar api_token')
        || str_contains($lower, 'configura api_token')
        || str_contains($lower, 'error http 401')
    ) {
        return 503;
    }

    if (
        str_contains($lower, 'error curl')
        || str_contains($lower, 'timed out')
        || str_contains($lower, 'could not resolve host')
        || str_contains($lower, 'error http 503')
        || str_contains($lower, 'no se pudo conectar con el servicio')
        || str_contains($lower, 'api devolvió html')
        || str_contains($lower, 'respuesta inválida')
    ) {
        return 502;
    }

    return 500;
}

if (!current_user()) {
    out(false, [], 'Sesión vencida. Inicia sesión nuevamente.', 401);
}

try {
    $dniRaw = trim((string) ($_POST['dni'] ?? $_GET['dni'] ?? ''));
    $dni = preg_replace('/\D/', '', $dniRaw);
    $forzar = (($_POST['forzar'] ?? $_GET['forzar'] ?? '') === '1');
    $guardar = (($_POST['guardar'] ?? $_GET['guardar'] ?? '') === '1');

    if (strlen($dni) !== 8) {
        out(false, [], 'DNI inválido (8 dígitos).', 400);
    }

    if (!$forzar) {
        $query = $pdo->prepare("SELECT * FROM personas WHERE tipo_doc='DNI' AND num_doc=? LIMIT 1");
        $query->execute([$dni]);
        $persona = $query->fetch(PDO::FETCH_ASSOC);

        if ($persona) {
            $tieneDatos = (
                (!empty($persona['apellido_paterno']) && !empty($persona['apellido_materno']) && !empty($persona['nombres']))
                || (!empty($persona['foto_path']) || !empty($persona['domicilio']))
            );

            if ($tieneDatos) {
                out(true, [
                    'tipo_doc' => 'DNI',
                    'num_doc' => $dni,
                    'apellido_paterno' => $persona['apellido_paterno'] ?? null,
                    'apellido_materno' => $persona['apellido_materno'] ?? null,
                    'nombres' => $persona['nombres'] ?? null,
                    'fecha_nacimiento' => $persona['fecha_nacimiento'] ?? null,
                    'edad' => isset($persona['edad']) ? (int) $persona['edad'] : null,
                    'sexo' => $persona['sexo'] ?? null,
                    'foto_path' => $persona['foto_path'] ?? null,
                    'domicilio' => $persona['domicilio'] ?? null,
                    'estado_civil' => $persona['estado_civil'] ?? null,
                    'nacionalidad' => $persona['nacionalidad'] ?? 'PERUANA',
                    'grado_instruccion' => $persona['grado_instruccion'] ?? null,
                    'nombre_padre' => $persona['nombre_padre'] ?? null,
                    'nombre_madre' => $persona['nombre_madre'] ?? null,
                    'domicilio_departamento' => $persona['domicilio_departamento'] ?? null,
                    'domicilio_provincia' => $persona['domicilio_provincia'] ?? null,
                    'domicilio_distrito' => $persona['domicilio_distrito'] ?? null,
                    'api_fuente' => $persona['api_fuente'] ?? 'DB_CACHE',
                    'api_ref' => $persona['api_ref'] ?? $dni,
                    'cache' => true,
                ]);
            }
        }
    }

    $json = consultar_dni($dni);
    $source = is_array($json) ? ($json['data'] ?? $json) : [];

    $apellidoPaterno = getv($source, ['ap_paterno']);
    $apellidoMaterno = getv($source, ['ap_materno']);
    $nombres = getv($source, ['nombres']);
    $fechaNacimiento = norm_date((string) getv($source, ['fec_nacimiento']));
    $edad = edad_entera(getv($source, ['edad']));
    $sexo = sexo_mf((string) getv($source, ['género', 'genero']));
    $direccion = getv($source, ['dirección', 'direccion']);
    $estadoCivil = getv($source, ['estado_civil']);
    $gradoInstruccion = getv($source, ['gradoInstruccion', 'grado_instruccion']);
    $nombrePadre = getv($source, ['padre']);
    $nombreMadre = getv($source, ['madre']);
    $fotoBase64 = getv($source, ['foto']);
    $fotoPath = save_photo_local($dni, is_string($fotoBase64) ? $fotoBase64 : null);

    $ubigeo = getv($source, ['ubi_dirección', 'ubi_direccion']);
    $departamento = null;
    $provincia = null;
    $distrito = null;

    if (is_string($ubigeo) && trim($ubigeo) !== '') {
        $parts = array_map('trim', explode(' - ', $ubigeo));
        $departamento = $parts[0] ?? null;
        $provincia = $parts[1] ?? null;
        $distrito = $parts[2] ?? null;
    }

    $data = [
        'tipo_doc' => 'DNI',
        'num_doc' => $dni,
        'apellido_paterno' => $apellidoPaterno,
        'apellido_materno' => $apellidoMaterno,
        'nombres' => $nombres,
        'fecha_nacimiento' => $fechaNacimiento,
        'edad' => $edad,
        'sexo' => $sexo,
        'foto_path' => $fotoPath,
        'domicilio' => $direccion,
        'estado_civil' => $estadoCivil,
        'nacionalidad' => 'PERUANA',
        'grado_instruccion' => $gradoInstruccion,
        'nombre_padre' => $nombrePadre,
        'nombre_madre' => $nombreMadre,
        'domicilio_departamento' => $departamento,
        'domicilio_provincia' => $provincia,
        'domicilio_distrito' => $distrito,
        'api_fuente' => 'RENIEC_SEEKER',
        'api_ref' => $dni,
        'cache' => false,
    ];

    $debeGuardar = $guardar;

    if ($debeGuardar) {
        $query = $pdo->prepare("SELECT id FROM personas WHERE tipo_doc='DNI' AND num_doc=? LIMIT 1");
        $query->execute([$dni]);
        $row = $query->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $update = $pdo->prepare("
                UPDATE personas SET
                    apellido_paterno = :ap_pat,
                    apellido_materno = :ap_mat,
                    nombres = :nombres,
                    fecha_nacimiento = :fnac,
                    edad = :edad,
                    sexo = :sexo,
                    foto_path = COALESCE(:foto, foto_path),
                    domicilio = COALESCE(:domicilio, domicilio),
                    estado_civil = COALESCE(:estado_civil, estado_civil),
                    grado_instruccion = COALESCE(:grado, grado_instruccion),
                    nombre_padre = COALESCE(:padre, nombre_padre),
                    nombre_madre = COALESCE(:madre, nombre_madre),
                    domicilio_departamento = COALESCE(:dep, domicilio_departamento),
                    domicilio_provincia = COALESCE(:prov, domicilio_provincia),
                    domicilio_distrito = COALESCE(:dist, domicilio_distrito),
                    api_fuente = :api_fuente,
                    api_ref = :api_ref
                WHERE id = :id
            ");
            $update->execute([
                ':ap_pat' => $apellidoPaterno,
                ':ap_mat' => $apellidoMaterno,
                ':nombres' => $nombres,
                ':fnac' => $fechaNacimiento,
                ':edad' => $edad,
                ':sexo' => $sexo,
                ':foto' => $fotoPath,
                ':domicilio' => $direccion,
                ':estado_civil' => $estadoCivil,
                ':grado' => $gradoInstruccion,
                ':padre' => $nombrePadre,
                ':madre' => $nombreMadre,
                ':dep' => $departamento,
                ':prov' => $provincia,
                ':dist' => $distrito,
                ':api_fuente' => 'RENIEC_SEEKER',
                ':api_ref' => $dni,
                ':id' => $row['id'],
            ]);

            $data['persona_id'] = (int) $row['id'];
            $data['guardado'] = true;
        } else {
            $insert = $pdo->prepare("
                INSERT INTO personas (
                    tipo_doc, num_doc, apellido_paterno, apellido_materno, nombres,
                    sexo, fecha_nacimiento, edad, estado_civil, nacionalidad,
                    domicilio, grado_instruccion, nombre_padre, nombre_madre,
                    domicilio_departamento, domicilio_provincia, domicilio_distrito,
                    foto_path, api_fuente, api_ref, creado_en
                ) VALUES (
                    'DNI', :num_doc, :ap_pat, :ap_mat, :nombres,
                    :sexo, :fnac, :edad, :estado_civil, 'PERUANA',
                    :domicilio, :grado, :padre, :madre,
                    :dep, :prov, :dist,
                    :foto, :api_fuente, :api_ref, NOW()
                )
            ");
            $insert->execute([
                ':num_doc' => $dni,
                ':ap_pat' => $apellidoPaterno,
                ':ap_mat' => $apellidoMaterno,
                ':nombres' => $nombres,
                ':sexo' => $sexo,
                ':fnac' => $fechaNacimiento,
                ':edad' => $edad,
                ':estado_civil' => $estadoCivil,
                ':domicilio' => $direccion,
                ':grado' => $gradoInstruccion,
                ':padre' => $nombrePadre,
                ':madre' => $nombreMadre,
                ':dep' => $departamento,
                ':prov' => $provincia,
                ':dist' => $distrito,
                ':foto' => $fotoPath,
                ':api_fuente' => 'RENIEC_SEEKER',
                ':api_ref' => $dni,
            ]);

            $data['persona_id'] = (int) $pdo->lastInsertId();
            $data['guardado'] = true;
        }
    }

    out(true, $data);
} catch (Throwable $e) {
    error_log('buscar_dni.php ERROR: ' . $e->getMessage());
    out(false, [], safe_error_message($e->getMessage()), status_for_error_message($e->getMessage()));
}
