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

function clean_spaces(mixed $value): ?string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return null;
    }

    return preg_replace('/\s+/u', ' ', $text) ?: $text;
}

function upper_text(mixed $value): ?string
{
    $text = clean_spaces($value);
    if ($text === null) {
        return null;
    }

    return function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
}

function title_text(mixed $value): ?string
{
    $text = clean_spaces($value);
    if ($text === null) {
        return null;
    }

    $lower = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    return function_exists('mb_convert_case')
        ? mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8')
        : ucwords($lower);
}

function address_text(mixed $value): ?string
{
    $text = title_text($value);
    if ($text === null) {
        return null;
    }

    $replacements = [
        '/\bMza?\.?\b/iu' => 'MZ',
        '/\bManzana\b/iu' => 'MZ',
        '/\bLt\.?\b/iu' => 'Lote',
        '/\bLote\b/iu' => 'Lote',
        '/\bNro\.?\b/iu' => 'Nro.',
        '/\bNumero\b/iu' => 'Nro.',
        '/\bNúmero\b/iu' => 'Nro.',
        '/\bN[°º]/iu' => 'Nro.',
        '/\bAv\.?\b/iu' => 'Av.',
        '/\bAvenida\b/iu' => 'Av.',
        '/\bJr\.?\b/iu' => 'Jr.',
        '/\bJiron\b/iu' => 'Jr.',
        '/\bJirón\b/iu' => 'Jr.',
        '/\bPsje\.?\b/iu' => 'Psje.',
        '/\bPasaje\b/iu' => 'Psje.',
        '/\bDpto\.?\b/iu' => 'Dpto.',
        '/\bDepartamento\b/iu' => 'Dpto.',
        '/\bInt\.?\b/iu' => 'Int.',
        '/\bInterior\b/iu' => 'Int.',
    ];

    foreach ($replacements as $pattern => $replacement) {
        $text = preg_replace($pattern, $replacement, $text) ?? $text;
    }

    return clean_spaces($text);
}

function first_text(array $source, array $keys): ?string
{
    $value = getv($source, $keys);
    return is_scalar($value) ? clean_spaces($value) : null;
}

function api_key(string $key): string
{
    $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
    $key = strtr($key, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n',
    ]);
    return preg_replace('/[^a-z0-9]/', '', $key) ?: $key;
}

function find_source_value(array $source, array $keys, array $contexts = []): mixed
{
    $wanted = array_flip(array_map('api_key', $keys));
    $contextKeys = array_map('api_key', $contexts);

    $walk = function (array $node, array $path) use (&$walk, $wanted, $contextKeys): mixed {
        foreach ($node as $key => $value) {
            $keyText = api_key((string) $key);
            $nextPath = [...$path, $keyText];
            $pathText = implode('', $nextPath);
            $hasContext = $contextKeys === [];
            foreach ($contextKeys as $context) {
                if ($context !== '' && str_contains($pathText, $context)) {
                    $hasContext = true;
                    break;
                }
            }

            if (isset($wanted[$keyText]) && $hasContext && $value !== '' && $value !== null) {
                return $value;
            }

            if (is_array($value)) {
                $found = $walk($value, $nextPath);
                if ($found !== null && $found !== '') {
                    return $found;
                }
            }
        }

        return null;
    };

    return $walk($source, []);
}

function source_text(array $source, array $keys, array $contexts = []): ?string
{
    $value = find_source_value($source, $keys, $contexts);
    return is_scalar($value) ? clean_spaces($value) : null;
}

function resolve_ubigeo(PDO $pdo, mixed $value, array &$notes, string $label): array
{
    $raw = is_scalar($value) ? clean_spaces($value) : null;
    if ($raw === null) {
        return ['departamento' => null, 'provincia' => null, 'distrito' => null];
    }

    if (str_contains($raw, ' - ') || str_contains($raw, '/')) {
        $parts = str_contains($raw, ' - ')
            ? array_map('trim', explode(' - ', $raw))
            : array_map('trim', explode('/', $raw));
        return [
            'departamento' => title_text($parts[0] ?? null),
            'provincia' => title_text($parts[1] ?? null),
            'distrito' => title_text($parts[2] ?? null),
        ];
    }

    $digits = preg_replace('/\D/', '', $raw);
    if ($digits !== null && strlen($digits) === 6) {
        $codDep = substr($digits, 0, 2);
        $codProv = substr($digits, 2, 2);
        $codDist = substr($digits, 4, 2);

        $dep = null;
        $prov = null;
        $dist = null;

        $st = $pdo->prepare('SELECT nombre FROM ubigeo_departamento WHERE cod_dep = ? LIMIT 1');
        $st->execute([$codDep]);
        $dep = $st->fetchColumn() ?: null;

        $st = $pdo->prepare('SELECT nombre FROM ubigeo_provincia WHERE cod_dep = ? AND cod_prov = ? LIMIT 1');
        $st->execute([$codDep, $codProv]);
        $prov = $st->fetchColumn() ?: null;

        $st = $pdo->prepare('SELECT nombre FROM ubigeo_distrito WHERE cod_dep = ? AND cod_prov = ? AND cod_dist = ? LIMIT 1');
        $st->execute([$codDep, $codProv, $codDist]);
        $dist = $st->fetchColumn() ?: null;

        if ($dep !== null || $prov !== null || $dist !== null) {
            return [
                'departamento' => title_text($dep),
                'provincia' => title_text($prov),
                'distrito' => title_text($dist),
            ];
        }

        $notes[] = $label . ' recibido como código no resuelto: ' . $raw;
        return ['departamento' => null, 'provincia' => null, 'distrito' => null];
    }

    if (preg_match('/^\d+$/', $raw)) {
        $notes[] = $label . ' recibido como código no resuelto: ' . $raw;
        return ['departamento' => null, 'provincia' => null, 'distrito' => null];
    }

    return ['departamento' => title_text($raw), 'provincia' => null, 'distrito' => null];
}

function numeric_code(?string $value): ?string
{
    if ($value === null || !preg_match('/^\d+$/', $value)) {
        return null;
    }

    return str_pad($value, 2, '0', STR_PAD_LEFT);
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
                    'apellido_paterno' => upper_text($persona['apellido_paterno'] ?? null),
                    'apellido_materno' => upper_text($persona['apellido_materno'] ?? null),
                    'nombres' => title_text($persona['nombres'] ?? null),
                    'fecha_nacimiento' => $persona['fecha_nacimiento'] ?? null,
                    'edad' => isset($persona['edad']) ? (int) $persona['edad'] : null,
                    'sexo' => $persona['sexo'] ?? null,
                    'foto_path' => $persona['foto_path'] ?? null,
                    'domicilio' => address_text($persona['domicilio'] ?? null),
                    'estado_civil' => title_text($persona['estado_civil'] ?? null),
                    'nacionalidad' => title_text($persona['nacionalidad'] ?? 'PERUANA'),
                    'departamento_nac' => title_text($persona['departamento_nac'] ?? null),
                    'provincia_nac' => title_text($persona['provincia_nac'] ?? null),
                    'distrito_nac' => title_text($persona['distrito_nac'] ?? null),
                    'grado_instruccion' => title_text($persona['grado_instruccion'] ?? null),
                    'nombre_padre' => title_text($persona['nombre_padre'] ?? null),
                    'nombre_madre' => title_text($persona['nombre_madre'] ?? null),
                    'domicilio_departamento' => title_text($persona['domicilio_departamento'] ?? null),
                    'domicilio_provincia' => title_text($persona['domicilio_provincia'] ?? null),
                    'domicilio_distrito' => title_text($persona['domicilio_distrito'] ?? null),
                    'notas' => $persona['notas'] ?? null,
                    'api_fuente' => $persona['api_fuente'] ?? 'DB_CACHE',
                    'api_ref' => $persona['api_ref'] ?? $dni,
                    'cache' => true,
                ]);
            }
        }
    }

    $json = consultar_dni($dni);
    $source = is_array($json) ? ($json['data'] ?? $json) : [];

    $notes = [];
    $apellidoPaterno = upper_text(getv($source, ['ap_paterno', 'apellido_paterno', 'ape_paterno']));
    $apellidoMaterno = upper_text(getv($source, ['ap_materno', 'apellido_materno', 'ape_materno']));
    $nombres = title_text(getv($source, ['nombres']));
    $fechaNacimiento = norm_date((string) getv($source, ['fec_nacimiento']));
    $edad = edad_entera(getv($source, ['edad']));
    $sexo = sexo_mf((string) getv($source, ['género', 'genero']));
    $direccion = address_text(getv($source, ['dirección', 'direccion']));
    $estadoCivil = title_text(getv($source, ['estado_civil']));
    $gradoInstruccion = title_text(getv($source, ['gradoInstruccion', 'grado_instruccion']));
    $nombrePadre = title_text(getv($source, ['padre']));
    $nombreMadre = title_text(getv($source, ['madre']));
    $fotoBase64 = getv($source, ['foto']);
    $fotoPath = save_photo_local($dni, is_string($fotoBase64) ? $fotoBase64 : null);

    $domicilioUbigeo = resolve_ubigeo($pdo, getv($source, ['ubi_dirección', 'ubi_direccion', 'ubigeo_direccion']), $notes, 'Ubigeo de domicilio');
    $departamento = title_text(first_text($source, ['departamento', 'dep_direccion', 'departamento_domicilio'])) ?? $domicilioUbigeo['departamento'];
    $provincia = title_text(first_text($source, ['provincia', 'prov_direccion', 'provincia_domicilio'])) ?? $domicilioUbigeo['provincia'];
    $distrito = title_text(first_text($source, ['distrito', 'dist_direccion', 'distrito_domicilio'])) ?? $domicilioUbigeo['distrito'];

    $nacimientoUbigeoRaw = getv($source, [
        'ubi_nacimiento',
        'ubigeo_nacimiento',
        'ubigeo_nac',
        'cod_ubigeo_nacimiento',
        'cod_ubigeo_nac',
        'codigo_ubigeo_nacimiento',
        'codigo_ubigeo_nac',
        'ubigeo_reniec',
        'ubigeoreniec',
        'origen',
        'lugar_nacimiento',
        'lugar_de_nacimiento',
    ]) ?? find_source_value($source, [
        'ubigeo',
        'ubi',
        'codigo_ubigeo',
        'cod_ubigeo',
        'codubigeo',
        'ubigeo_nacimiento',
        'cod_ubigeo_nacimiento',
        'origen',
        'lugar_nacimiento',
    ], ['nacimiento', 'nac']);
    $nacimientoUbigeo = resolve_ubigeo($pdo, $nacimientoUbigeoRaw, $notes, 'Ubigeo de nacimiento');
    $departamentoNacRaw = first_text($source, ['departamento_nac', 'departamento_nacimiento', 'dep_nacimiento', 'dep_nac', 'cod_dep_nac'])
        ?? source_text($source, ['departamento', 'departamento_nombre', 'nom_departamento', 'nombre_departamento', 'dep', 'cod_dep', 'coddep', 'codigo_departamento', 'codigo_dep'], ['nacimiento', 'nac']);
    $provinciaNacRaw = first_text($source, ['provincia_nac', 'provincia_nacimiento', 'prov_nacimiento', 'prov_nac', 'cod_prov_nac'])
        ?? source_text($source, ['provincia', 'provincia_nombre', 'nom_provincia', 'nombre_provincia', 'prov', 'cod_prov', 'codprov', 'codigo_provincia', 'codigo_prov'], ['nacimiento', 'nac']);
    $distritoNacRaw = first_text($source, ['distrito_nac', 'distrito_nacimiento', 'dist_nacimiento', 'dist_nac', 'cod_dist_nac'])
        ?? source_text($source, ['distrito', 'distrito_nombre', 'nom_distrito', 'nombre_distrito', 'dist', 'cod_dist', 'coddist', 'codigo_distrito', 'codigo_dist'], ['nacimiento', 'nac']);
    $nacimientoPartes = ['departamento' => null, 'provincia' => null, 'distrito' => null];
    $codDepNac = numeric_code($departamentoNacRaw);
    $codProvNac = numeric_code($provinciaNacRaw);
    $codDistNac = numeric_code($distritoNacRaw);
    if ($codDepNac !== null && $codProvNac !== null && $codDistNac !== null) {
        $nacimientoPartes = resolve_ubigeo($pdo, $codDepNac . $codProvNac . $codDistNac, $notes, 'Ubigeo de nacimiento');
    }

    $departamentoNac = ($codDepNac === null ? title_text($departamentoNacRaw) : null) ?? $nacimientoUbigeo['departamento'] ?? $nacimientoPartes['departamento'];
    $provinciaNac = ($codProvNac === null ? title_text($provinciaNacRaw) : null) ?? $nacimientoUbigeo['provincia'] ?? $nacimientoPartes['provincia'];
    $distritoNac = ($codDistNac === null ? title_text($distritoNacRaw) : null) ?? $nacimientoUbigeo['distrito'] ?? $nacimientoPartes['distrito'];

    if ($departamentoNac !== null && preg_match('/^\d+$/', $departamentoNac)) {
        $notes[] = 'Departamento nacimiento recibido como código no resuelto: ' . $departamentoNac;
        $departamentoNac = null;
    }
    if ($provinciaNac !== null && preg_match('/^\d+$/', $provinciaNac)) {
        $notes[] = 'Provincia nacimiento recibido como código no resuelto: ' . $provinciaNac;
        $provinciaNac = null;
    }
    if ($distritoNac !== null && preg_match('/^\d+$/', $distritoNac)) {
        $notes[] = 'Distrito nacimiento recibido como código no resuelto: ' . $distritoNac;
        $distritoNac = null;
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
        'nacionalidad' => 'Peruana',
        'departamento_nac' => $departamentoNac,
        'provincia_nac' => $provinciaNac,
        'distrito_nac' => $distritoNac,
        'grado_instruccion' => $gradoInstruccion,
        'nombre_padre' => $nombrePadre,
        'nombre_madre' => $nombreMadre,
        'domicilio_departamento' => $departamento,
        'domicilio_provincia' => $provincia,
        'domicilio_distrito' => $distrito,
        'notas' => implode("\n", array_values(array_unique($notes))),
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
                    nacionalidad = COALESCE(:nacionalidad, nacionalidad),
                    departamento_nac = COALESCE(:dep_nac, departamento_nac),
                    provincia_nac = COALESCE(:prov_nac, provincia_nac),
                    distrito_nac = COALESCE(:dist_nac, distrito_nac),
                    grado_instruccion = COALESCE(:grado, grado_instruccion),
                    nombre_padre = COALESCE(:padre, nombre_padre),
                    nombre_madre = COALESCE(:madre, nombre_madre),
                    domicilio_departamento = COALESCE(:dep, domicilio_departamento),
                    domicilio_provincia = COALESCE(:prov, domicilio_provincia),
                    domicilio_distrito = COALESCE(:dist, domicilio_distrito),
                    notas = COALESCE(NULLIF(CONCAT_WS('\n', notas, :notas), ''), notas),
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
                ':nacionalidad' => 'Peruana',
                ':dep_nac' => $departamentoNac,
                ':prov_nac' => $provinciaNac,
                ':dist_nac' => $distritoNac,
                ':grado' => $gradoInstruccion,
                ':padre' => $nombrePadre,
                ':madre' => $nombreMadre,
                ':dep' => $departamento,
                ':prov' => $provincia,
                ':dist' => $distrito,
                ':notas' => $data['notas'] !== '' ? $data['notas'] : null,
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
                    departamento_nac, provincia_nac, distrito_nac,
                    domicilio, grado_instruccion, nombre_padre, nombre_madre,
                    domicilio_departamento, domicilio_provincia, domicilio_distrito,
                    notas, foto_path, api_fuente, api_ref, creado_en
                ) VALUES (
                    'DNI', :num_doc, :ap_pat, :ap_mat, :nombres,
                    :sexo, :fnac, :edad, :estado_civil, 'Peruana',
                    :dep_nac, :prov_nac, :dist_nac,
                    :domicilio, :grado, :padre, :madre,
                    :dep, :prov, :dist,
                    :notas, :foto, :api_fuente, :api_ref, NOW()
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
                ':dep_nac' => $departamentoNac,
                ':prov_nac' => $provinciaNac,
                ':dist_nac' => $distritoNac,
                ':domicilio' => $direccion,
                ':grado' => $gradoInstruccion,
                ':padre' => $nombrePadre,
                ':madre' => $nombreMadre,
                ':dep' => $departamento,
                ':prov' => $provincia,
                ':dist' => $distrito,
                ':notas' => $data['notas'] !== '' ? $data['notas'] : null,
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
