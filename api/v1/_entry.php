<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Api/ReadApi.php';
ini_set('display_errors', '0');

use App\Api\ApiError;
use App\Api\ReadApi;
use App\Api\RequestLimiter;
use App\Support\Auth;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
$requestId = bin2hex(random_bytes(16));
header('X-Request-ID: ' . $requestId);
$endpoint = isset($endpoint) && in_array($endpoint, ['buscar_expedientes','consultar_expediente','consultar_diligencias'], true) ? $endpoint : 'desconocido';
$status = 500;
$actorId = null;
$payload = ['error'=>'Servicio no disponible.'];
$audit = null;
try {
    if (getenv('UIAT_READ_API_ENABLED') !== '1') throw new ApiError(503, 'API deshabilitada.');
    // A pre-provisioned private directory outside the document root is mandatory.
    $privatePath = (string)getenv('UIAT_READ_API_PRIVATE_DIR');
    if (!str_starts_with($privatePath, DIRECTORY_SEPARATOR)) throw new RuntimeException('Absolute private path required');
    $dir = realpath($privatePath);
    $root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? UIAT_BASE_PATH)) ?: UIAT_BASE_PATH;
    if (!$dir || $dir === $root || str_starts_with($dir, $root . DIRECTORY_SEPARATOR)
        || $dir === UIAT_BASE_PATH || str_starts_with($dir, UIAT_BASE_PATH . DIRECTORY_SEPARATOR)
        || !is_writable($dir) || (fileperms($dir) & 0077)) throw new RuntimeException('Private directory unavailable');
    $audit = fopen($dir . '/audit.jsonl', 'ab');
    if (!$audit || !chmod($dir . '/audit.jsonl', 0600)) throw new RuntimeException('Audit unavailable');
    // Count every enabled API request, including anonymous requests and invalid methods.
    // REMOTE_ADDR is supplied by the server; forwarded client headers are ignored.
    if (!RequestLimiter::consume($dir, (string)($_SERVER['REMOTE_ADDR'] ?? ''), (string)getenv('UIAT_READ_API_PASSWORD'))) {
        header('Retry-After: 60');
        throw new ApiError(429, 'Límite de solicitudes alcanzado.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        throw new ApiError(405, 'Solo se admite GET.');
    }
    if (strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')) === 'cross-site') throw new ApiError(403, 'Solicitud no permitida.');
    // Never trust forwarding headers to establish TLS.
    if (empty($_SERVER['HTTPS']) || strtolower((string)$_SERVER['HTTPS']) === 'off') throw new ApiError(403, 'Se requiere HTTPS.');
    Auth::startSession();
    if (empty($_SESSION['user']['id'])) throw new ApiError(401, 'Inicia sesión nuevamente.');
    $session = $_SESSION;
    $dsn = (string)getenv('UIAT_READ_API_DSN');
    $dbUser = (string)getenv('UIAT_READ_API_USER');
    $dbPass = getenv('UIAT_READ_API_PASSWORD');
    if (!str_starts_with($dsn, 'mysql:') || $dbUser === '' || $dbPass === false || $dbPass === '') throw new RuntimeException('Dedicated connection required');
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $s = $pdo->prepare('SELECT id,rol,activo,must_change_password,auth_version FROM usuarios WHERE id=?');
    $s->execute([(int)$session['user']['id']]);
    $actor = ReadApi::authenticate($session, $s->fetch(), time());
    $actorId = (int)$actor['id'];
    $_SESSION['api_last_seen'] = time();
    session_write_close();
    // Per-user shared limit, including across different sessions and endpoints.
    $rate = fopen($dir . '/rate-' . $actorId . '.json', 'c+');
    if (!$rate || !chmod($dir . '/rate-' . $actorId . '.json', 0600) || !flock($rate, LOCK_EX)) throw new RuntimeException('Rate limiter unavailable');
    try {
        $state = json_decode(stream_get_contents($rate), true);
        $window = intdiv(time(), 60);
        $count = is_array($state) && ($state['window'] ?? null) === $window ? (int)$state['count'] : 0;
        if ($count >= 60) { header('Retry-After: 60'); throw new ApiError(429, 'Límite de consultas alcanzado.'); }
        $encoded = json_encode(['window'=>$window,'count'=>$count+1], JSON_THROW_ON_ERROR);
        rewind($rate);
        if (!ftruncate($rate, 0) || fwrite($rate, $encoded) !== strlen($encoded) || !fflush($rate)) throw new RuntimeException('Rate limiter unavailable');
    } finally { flock($rate, LOCK_UN); fclose($rate); }
    $payload = (new ReadApi($pdo))->handle($endpoint, $_GET, $actor);
    $status = 200;
} catch (ApiError $e) {
    $status = $e->status;
    $payload = ['error'=>$e->getMessage()];
} catch (Throwable $e) {
    // Do not log exceptions: driver errors can contain SQL, paths or secrets.
    $status = 503;
    $payload = ['error'=>'Servicio no disponible.'];
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
}
if (is_resource($audit)) {
    $record = json_encode(['fecha'=>gmdate('c'),'solicitud'=>$requestId,'usuario_id'=>$actorId,'endpoint'=>$endpoint,'estado'=>$status], JSON_THROW_ON_ERROR) . "\n";
    $locked = flock($audit, LOCK_EX);
    $written = $locked && fwrite($audit, $record) === strlen($record) && fflush($audit);
    if ($locked) flock($audit, LOCK_UN);
    fclose($audit);
    if (!$written) { $status = 503; $payload = ['error'=>'Servicio no disponible.']; }
}
http_response_code($status);
echo json_encode(['ok'=>$status === 200,'solicitud'=>$requestId] + $payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
