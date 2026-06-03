<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/ia_oficios.log');

$logFile = __DIR__ . '/logs/ia_oficios.log';

function ia_log(string $message): void
{
    global $logFile;
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $logFile);
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fail_user(string $message, int $status = 400): void
{
    http_response_code($status);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;padding:24px">';
    echo '<h2>No se pudo generar el oficio</h2><p>' . h($message) . '</p>';
    echo '<p><a href="javascript:history.back()">Volver</a></p></body>';
    exit;
}

function clean_text($value, int $max = 5000): string
{
    $text = trim((string) $value);
    $text = preg_replace('/[^\P{C}\r\n\t]+/u', '', $text) ?? $text;
    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $max, 'UTF-8');
    }
    return substr($text, 0, $max);
}

function fecha_larga(?string $date): string
{
    $date = trim((string) $date);
    if ($date === '') {
        return '';
    }
    $time = strtotime($date);
    if (!$time) {
        return $date;
    }
    $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return date('j', $time) . ' de ' . $months[(int) date('n', $time) - 1] . ' de ' . date('Y', $time);
}

function fecha_actual_lima(): string
{
    return 'Lima, ' . fecha_larga(date('Y-m-d'));
}

function fecha_abrev(?string $date): string
{
    $date = trim((string) $date);
    if ($date === '') {
        return '';
    }
    $time = strtotime($date);
    if (!$time) {
        return $date;
    }
    $months = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
    return strtoupper(date('d', $time) . $months[(int) date('n', $time) - 1] . date('Y', $time));
}

function first_non_empty(array $row, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return $default;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->execute([$table, $column]);
    return $cache[$key] = ((int) $st->fetchColumn() > 0);
}

function table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->execute([$table]);
    return $cache[$table] = ((int) $st->fetchColumn() > 0);
}

function join_list(array $items): string
{
    $items = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $items), static fn ($v) => $v !== ''));
    if (!$items) {
        return '';
    }
    if (count($items) === 1) {
        return $items[0];
    }
    return implode(', ', array_slice($items, 0, -1)) . ' y ' . end($items);
}

function load_accidente(PDO $pdo, int $accidenteId): array
{
    $selectDistrito = 'NULL AS distrito_nombre';
    if (table_exists($pdo, 'ubigeo_distrito')) {
        $selectDistrito = "(SELECT nombre FROM ubigeo_distrito ud WHERE ud.cod_dep=a.cod_dep AND ud.cod_prov=a.cod_prov AND ud.cod_dist=a.cod_dist LIMIT 1) AS distrito_nombre";
    }

    $sql = "
        SELECT a.*, f.nombre AS fiscalia_nombre, {$selectDistrito}
        FROM accidentes a
        LEFT JOIN fiscalia f ON f.id = a.fiscalia_id
        WHERE a.id = ?
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$accidenteId]);
    $accidente = $st->fetch(PDO::FETCH_ASSOC);
    if (!$accidente) {
        return [];
    }

    $modalidades = [];
    if (table_exists($pdo, 'accidente_modalidad') && table_exists($pdo, 'modalidad_accidente')) {
        $st = $pdo->prepare("SELECT ma.nombre FROM accidente_modalidad am JOIN modalidad_accidente ma ON ma.id=am.modalidad_id WHERE am.accidente_id=? ORDER BY ma.id");
        $st->execute([$accidenteId]);
        $modalidades = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } elseif (column_exists($pdo, 'accidentes', 'modalidad')) {
        $modalidades[] = (string) ($accidente['modalidad'] ?? '');
    }

    $consecuencias = [];
    if (table_exists($pdo, 'accidente_consecuencia') && table_exists($pdo, 'consecuencia_accidente')) {
        $st = $pdo->prepare("SELECT ca.nombre FROM accidente_consecuencia ac JOIN consecuencia_accidente ca ON ca.id=ac.consecuencia_id WHERE ac.accidente_id=? ORDER BY ca.id");
        $st->execute([$accidenteId]);
        $consecuencias = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } elseif (column_exists($pdo, 'accidentes', 'consecuencia')) {
        $consecuencias[] = (string) ($accidente['consecuencia'] ?? '');
    }

    $accidente['modalidad_resumen'] = join_list($modalidades);
    $accidente['consecuencia_resumen'] = join_list($consecuencias);
    return $accidente;
}

function infer_destinatario(string $prompt): string
{
    if (preg_match('/(?:a la|al|ante la|ante el)\s+([^,.]+(?:Municipalidad|Comisaria|Fiscalia|Gerencia|Subgerencia|Unidad|Divisi[oó]n|Empresa|Entidad)[^,.]*)/iu', $prompt, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/Municipalidad\s+(?:Distrital\s+)?(?:de\s+)?[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+/u', $prompt, $m)) {
        return trim($m[0]);
    }
    return 'A QUIEN CORRESPONDA';
}

function extract_time_range(string $prompt, array $accidente): array
{
    if (preg_match('/([0-2]?\d(?::[0-5]\d)?)\s*(?:h|horas)?\s*(?:a|hasta|-)\s*([0-2]?\d(?::[0-5]\d)?)\s*(?:h|horas)?/iu', $prompt, $m)) {
        return [normalize_hour($m[1]), normalize_hour($m[2])];
    }

    if (!empty($accidente['fecha_accidente']) && strtotime((string) $accidente['fecha_accidente'])) {
        $time = strtotime((string) $accidente['fecha_accidente']);
        return [date('H:i', $time - 1800), date('H:i', $time + 1800)];
    }

    return ['', ''];
}

function normalize_hour(string $hour): string
{
    $hour = trim($hour);
    if (preg_match('/^\d{1,2}$/', $hour)) {
        return str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00';
    }
    if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $hour, $m)) {
        return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
    }
    return $hour;
}

function split_numero_oficio(string $numeroOficio): array
{
    if (preg_match('/^\s*([A-Za-z0-9]+)\s*[-\/]\s*(20\d{2})\s*$/', $numeroOficio, $m)) {
        return [$m[1], $m[2]];
    }
    return [$numeroOficio, date('Y')];
}

function load_nombre_oficial_ano(PDO $pdo): string
{
    if (!table_exists($pdo, 'oficio_oficial_ano')) {
        return '';
    }

    $year = date('Y');
    $st = $pdo->prepare("SELECT nombre FROM oficio_oficial_ano WHERE COALESCE(vigente,0)=1 ORDER BY anio DESC, id DESC LIMIT 1");
    $st->execute();
    $nombre = trim((string) ($st->fetchColumn() ?: ''));
    if ($nombre !== '') {
        return $nombre;
    }

    $st = $pdo->prepare("SELECT nombre FROM oficio_oficial_ano WHERE anio=? ORDER BY id DESC LIMIT 1");
    $st->execute([$year]);
    return trim((string) ($st->fetchColumn() ?: ''));
}

function fallback_cuerpo(array $accidente, string $prompt): string
{
    $sidpol = first_non_empty($accidente, ['registro_sidpol', 'sidpol'], 'el registro correspondiente');
    $fecha = fecha_larga((string) ($accidente['fecha_accidente'] ?? ''));
    $hora = '';
    if (!empty($accidente['fecha_accidente']) && strtotime((string) $accidente['fecha_accidente'])) {
        $hora = date('H:i', strtotime((string) $accidente['fecha_accidente']));
    }
    $lugar = first_non_empty($accidente, ['lugar'], 'el lugar materia de investigacion');
    $distrito = first_non_empty($accidente, ['distrito_nombre', 'cod_dist'], 'el distrito correspondiente');
    $modalidad = first_non_empty($accidente, ['modalidad_resumen'], 'accidente de transito');
    $consecuencia = first_non_empty($accidente, ['consecuencia_resumen'], 'consecuencias materia de investigacion');

    $hecho = "Tengo el agrado de dirigirme a usted, en atencion a las diligencias seguidas por esta Unidad, relacionadas con el accidente de transito registrado en el SIDPOL {$sidpol}";
    if ($fecha !== '') {
        $hecho .= ", ocurrido con fecha {$fecha}";
    }
    if ($hora !== '') {
        $hecho .= ", aproximadamente a horas {$hora}";
    }
    $hecho .= ", en {$lugar}, distrito de {$distrito}, bajo la modalidad de {$modalidad}, con {$consecuencia}.";

    return $hecho . "\n\n" .
        "Sobre el particular, se solicita se sirva disponer a quien corresponda la verificacion, ubicacion, preservacion y remision de las imagenes captadas por camaras de videovigilancia publicas o privadas que pudieran existir en las inmediaciones del lugar de los hechos, conforme al requerimiento indicado por el usuario: " . $prompt . "\n\n" .
        "La informacion solicitada resulta necesaria para el esclarecimiento de los hechos materia de investigacion y debera ser remitida a esta Unidad Policial por el medio oficial que corresponda.\n\n" .
        "Sin otro particular, hago propicia la ocasion para expresarle los sentimientos de mi especial consideracion y estima personal.";
}

function call_openai_responses(string $apiKey, array $accidente, string $promptUsuario): string
{
    $model = trim((string) (getenv('OPENAI_MODEL') ?: 'gpt-4.1'));
    $accidenteJson = json_encode($accidente, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $payload = [
        'model' => $model,
        'input' => [
            [
                'role' => 'system',
                'content' => 'Eres un asistente legal-policial peruano. Redactas cuerpos de oficios formales para la PNP/UIAT. No inventes datos: usa solo los datos de la base y el pedido del usuario. Si falta un dato importante, redacta de forma generica. Devuelve solo el cuerpo del oficio, sin encabezado, sin asunto, sin firma y sin explicaciones.',
            ],
            [
                'role' => 'user',
                'content' => "Datos del accidente en JSON:\n{$accidenteJson}\n\nPedido del usuario:\n{$promptUsuario}\n\nRedacta el cuerpo del oficio solicitando camaras de videovigilancia con estilo policial formal peruano.",
            ],
        ],
        'temperature' => 0.2,
        'max_output_tokens' => 1600,
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $curlError !== '') {
        throw new RuntimeException('Error cURL OpenAI: ' . $curlError);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('OpenAI HTTP ' . $status . ': ' . substr($raw, 0, 1000));
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respuesta OpenAI no JSON');
    }

    $text = trim((string) ($data['output_text'] ?? ''));
    if ($text === '' && !empty($data['output']) && is_array($data['output'])) {
        foreach ($data['output'] as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                    $text .= "\n" . (string) $content['text'];
                }
            }
        }
        $text = trim($text);
    }

    if ($text === '') {
        throw new RuntimeException('OpenAI no devolvio texto usable');
    }
    return $text;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail_user('Metodo no permitido.', 405);
    }

    $accidenteId = (int) ($_POST['accidente_id'] ?? 0);
    $numeroOficio = clean_text($_POST['numero_oficio'] ?? '', 60);
    $promptUsuario = clean_text($_POST['prompt_usuario'] ?? '', 3000);

    if ($accidenteId <= 0 || $numeroOficio === '') {
        fail_user('Debe indicar accidente_id y numero_oficio.');
    }
    if ($promptUsuario === '') {
        fail_user('Debe indicar el pedido para generar el oficio.');
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    $accidente = load_accidente($pdo, $accidenteId);
    if (!$accidente) {
        fail_user('No se encontro el accidente indicado.', 404);
    }

    $apiKey = trim((string) getenv('OPENAI_API_KEY'));
    if ($apiKey === '') {
        ia_log('Falta OPENAI_API_KEY para accidente_id=' . $accidenteId);
        fail_user('El servicio de IA no esta configurado. Contacte al administrador.', 500);
    }

    try {
        $cuerpo = call_openai_responses($apiKey, $accidente, $promptUsuario);
    } catch (Throwable $e) {
        ia_log($e->getMessage());
        $cuerpo = fallback_cuerpo($accidente, $promptUsuario);
    }

    $templatePath = __DIR__ . '/plantillas/oficio_camaras.docx';
    if (!is_file($templatePath)) {
        ia_log('No existe plantilla: ' . $templatePath);
        fail_user('No se encontro la plantilla Word requerida.', 500);
    }

    $outputDir = __DIR__ . '/documentos_generados';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
        ia_log('No se pudo crear documentos_generados');
        fail_user('No se pudo preparar la carpeta de salida.', 500);
    }

    $safeNumero = preg_replace('/[^A-Za-z0-9_-]+/', '_', $numeroOficio) ?: 'SN';
    $fileName = 'oficio_camaras_' . $accidenteId . '_' . $safeNumero . '.docx';
    $outputPath = $outputDir . '/' . $fileName;

    $destinatario = infer_destinatario($promptUsuario);
    $referencia = first_non_empty($accidente, ['registro_sidpol', 'sidpol'], 'SIDPOL no consignado');
    $firma = 'JEFE DE LA UNIDAD DE INVESTIGACION DE ACCIDENTES DE TRANSITO NORTE';
    [$rangoDesde, $rangoHasta] = extract_time_range($promptUsuario, $accidente);
    [$oficioNumeroSolo, $oficioAnio] = split_numero_oficio($numeroOficio);
    $nombreOficialAno = load_nombre_oficial_ano($pdo);

    $template = new TemplateProcessor($templatePath);
    $template->setValue('NUM_OFICIO', h($numeroOficio));
    $template->setValue('FECHA_ACTUAL', h(fecha_actual_lima()));
    $template->setValue('DESTINATARIO', h($destinatario));
    $template->setValue('ASUNTO', h('Solicita imagenes de camaras de videovigilancia'));
    $template->setValue('REFERENCIA', h('Registro SIDPOL: ' . $referencia));
    $template->setValue('CUERPO', h($cuerpo));
    $template->setValue('FIRMA', h($firma));

    $template->setValue('nombre_oficial_ano', h($nombreOficialAno));
    $template->setValue('oficio_fecha', h(fecha_larga(date('Y-m-d'))));
    $template->setValue('oficio_numero', h($oficioNumeroSolo));
    $template->setValue('oficio_anio', h($oficioAnio));
    $template->setValue('oficio_grado_cargo', '');
    $template->setValue('oficio_persona_destino', '');
    $template->setValue('entidad_nombre', h($destinatario));
    $template->setValue('oficio_rango_desde', h($rangoDesde));
    $template->setValue('oficio_rango_hasta', h($rangoHasta));
    $template->setValue('accidente_fecha_abrev', h(fecha_abrev((string) ($accidente['fecha_accidente'] ?? ''))));
    $template->setValue('accidente_lugar', h(first_non_empty($accidente, ['lugar'], 'el lugar materia de investigacion')));
    $template->setValue('accidente_referencia', h(first_non_empty($accidente, ['referencia'], 'sin referencia consignada')));
    $template->setValue('accidente_modalidad', h(first_non_empty($accidente, ['modalidad_resumen'], 'accidente de transito')));
    $template->setValue('accidente_consecuencia', h(first_non_empty($accidente, ['consecuencia_resumen'], 'consecuencias materia de investigacion')));
    $template->setValue('fiscalia_nombre', h(first_non_empty($accidente, ['fiscalia_nombre'], 'la autoridad competente')));
    $template->saveAs($outputPath);

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    header('Content-Length: ' . filesize($outputPath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($outputPath);
    exit;
} catch (Throwable $e) {
    ia_log($e->getMessage());
    fail_user('Ocurrio un error interno al generar el oficio.', 500);
}
