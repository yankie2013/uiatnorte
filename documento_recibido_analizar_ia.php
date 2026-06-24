<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function texto_respuesta_openai(array $response): string
{
    $text = trim((string) ($response['output_text'] ?? ''));
    if ($text !== '') {
        return $text;
    }

    foreach (($response['output'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach (($item['content'] ?? []) as $content) {
            if (is_array($content) && ($content['type'] ?? '') === 'output_text') {
                $text .= "\n" . (string) ($content['text'] ?? '');
            }
        }
    }

    return trim($text);
}

function limpiar_json_modelo(string $text): string
{
    $text = trim($text);
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/su', $text, $match)) {
        return trim((string) $match[1]);
    }
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'message' => 'Método no permitido.'], 405);
}

$file = $_FILES['documento_imagen'] ?? null;
if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    responder_json(['ok' => false, 'message' => 'Selecciona o pega una imagen del documento.'], 422);
}
if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    responder_json(['ok' => false, 'message' => 'No se pudo recibir la imagen.'], 422);
}

$size = (int) ($file['size'] ?? 0);
if ($size <= 0 || $size > 12 * 1024 * 1024) {
    responder_json(['ok' => false, 'message' => 'La imagen debe pesar como máximo 12 MB.'], 422);
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
$mime = $tmpPath !== '' && is_file($tmpPath)
    ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath)
    : '';
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowedMimes, true)) {
    responder_json(['ok' => false, 'message' => 'Usa una imagen JPG, PNG o WEBP.'], 422);
}

$apiKey = trim((string) getenv('OPENAI_API_KEY'));
if ($apiKey === '') {
    responder_json(['ok' => false, 'message' => 'El servicio de análisis inteligente no está configurado.'], 503);
}

$bytes = file_get_contents($tmpPath);
if ($bytes === false) {
    responder_json(['ok' => false, 'message' => 'No se pudo leer la imagen recibida.'], 422);
}

$schema = [
    'type' => 'object',
    'additionalProperties' => false,
    'properties' => [
        'tipo_documento' => ['type' => 'string'],
        'fecha_documento' => ['type' => 'string'],
        'numero_documento' => ['type' => 'string'],
        'siglas_unidad' => ['type' => 'string'],
        'entidad_persona' => ['type' => 'string'],
        'asunto' => ['type' => 'string'],
        'contenido' => ['type' => 'string'],
        'advertencias' => ['type' => 'array', 'items' => ['type' => 'string']],
    ],
    'required' => [
        'tipo_documento',
        'fecha_documento',
        'numero_documento',
        'siglas_unidad',
        'entidad_persona',
        'asunto',
        'contenido',
        'advertencias',
    ],
];

$instructions = <<<'PROMPT'
Analiza la imagen de un documento administrativo o policial peruano y extrae únicamente datos que sean visibles.

Reglas:
- tipo_documento: clase documental en mayúsculas, por ejemplo OFICIO, INFORME, CARTA, ACTA o CERTIFICADO.
- fecha_documento: fecha de emisión en formato YYYY-MM-DD. Si no es legible, devuelve cadena vacía.
- numero_documento: copia el identificador COMPLETO impreso después de “N°”, incluyendo número, año y siglas de la unidad. No lo confundas con números citados en el cuerpo.
- siglas_unidad: copia solamente la parte de siglas o código institucional contenida en el número del documento.
- entidad_persona: dependencia, institución o persona QUE EMITE el documento. Prioriza el membrete superior y la firma. No uses al destinatario indicado después de “SEÑOR”. Ejemplo: si el membrete dice “Comisaría de Collique”, esa es la entidad remitente.
- asunto: transcribe o resume en una sola línea el texto rotulado “ASUNTO”.
- contenido: redacta un resumen objetivo de 2 a 4 oraciones del pedido, remisión o información principal. No agregues conclusiones ni datos ausentes.
- advertencias: incluye breves avisos sobre campos dudosos o ilegibles. Si todo es claro, devuelve un arreglo vacío.
- No inventes. Cuando un dato no pueda leerse, devuelve cadena vacía.
PROMPT;

$payload = [
    'model' => trim((string) (getenv('OPENAI_MODEL') ?: 'gpt-4.1')),
    'input' => [
        [
            'role' => 'system',
            'content' => 'Eres un extractor documental preciso para una unidad policial peruana. Obedeces el esquema y distingues remitente de destinatario.',
        ],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'input_text', 'text' => $instructions],
                [
                    'type' => 'input_image',
                    'image_url' => 'data:' . $mime . ';base64,' . base64_encode($bytes),
                    'detail' => 'high',
                ],
            ],
        ],
    ],
    'text' => [
        'format' => [
            'type' => 'json_schema',
            'name' => 'documento_recibido_extraido',
            'strict' => true,
            'schema' => $schema,
        ],
    ],
    'temperature' => 0.1,
    'max_output_tokens' => 1200,
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
    CURLOPT_CONNECTTIMEOUT => 12,
    CURLOPT_TIMEOUT => 90,
]);

$raw = curl_exec($ch);
$curlError = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false || $curlError !== '') {
    error_log('Documento recibido IA: ' . $curlError);
    responder_json(['ok' => false, 'message' => 'No se pudo conectar con el servicio de análisis.'], 502);
}
if ($status < 200 || $status >= 300) {
    error_log('Documento recibido IA HTTP ' . $status . ': ' . substr((string) $raw, 0, 800));
    responder_json(['ok' => false, 'message' => 'El servicio no pudo analizar esta imagen. Intenta con una foto más clara.'], 502);
}

$response = json_decode((string) $raw, true);
if (!is_array($response)) {
    responder_json(['ok' => false, 'message' => 'El servicio devolvió una respuesta inválida.'], 502);
}

$text = limpiar_json_modelo(texto_respuesta_openai($response));
$result = json_decode($text, true);
if (!is_array($result)) {
    error_log('Documento recibido IA JSON inválido: ' . substr($text, 0, 800));
    responder_json(['ok' => false, 'message' => 'No se pudieron ordenar los datos detectados.'], 502);
}

$fields = ['tipo_documento', 'fecha_documento', 'numero_documento', 'siglas_unidad', 'entidad_persona', 'asunto', 'contenido'];
$clean = [];
foreach ($fields as $field) {
    $clean[$field] = trim((string) ($result[$field] ?? ''));
}
$clean['advertencias'] = array_values(array_filter(
    array_map(static fn(mixed $item): string => trim((string) $item), (array) ($result['advertencias'] ?? [])),
    static fn(string $item): bool => $item !== ''
));

responder_json(['ok' => true, 'data' => $clean]);
