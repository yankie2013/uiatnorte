<?php
declare(strict_types=1);
// Explicit opt-in; local socket only; never bootstrap, .env, or existing databases.
if (PHP_SAPI !== 'cli' || !in_array('--isolated-local', $argv, true)) exit("Use --isolated-local (base y usuario efímeros, datos ficticios).\n");
require_once dirname(__DIR__) . '/app/Api/ReadApi.php';
use App\Api\ReadApi;
use App\Api\ApiError;
$socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
$port = getenv('UIAT_TEST_PORT');
if ($port !== false && (!ctype_digit($port) || (int)$port < 1024 || (int)$port > 65535)) exit(2);
$localDsn = $port === false ? 'mysql:unix_socket=' . $socket : 'mysql:host=127.0.0.1;port=' . $port;
$admin = null;
$reader = null;
$dbCreated = false;
$userCreated = false;
$suffix = bin2hex(random_bytes(6));
$db = 'uiat_api_' . $suffix . '_test';
$username = 'uiat_api_' . $suffix;
$password = bin2hex(random_bytes(32));
$count = 0;
$testExitCode = 0;
function verify(bool $result): void { global $count; $count++; if (!$result) throw new RuntimeException('Verification failed'); }
function forbidden(callable $fn): void {
    try { $fn(); } catch (PDOException $e) { verify(in_array((int)($e->errorInfo[1] ?? 0), [1142,1143,1044], true)); return; }
    throw new RuntimeException('Privilege unexpectedly allowed');
}
try {
    $admin = new PDO($localDsn . ';charset=utf8mb4', getenv('UIAT_TEST_ADMIN_USER') ?: 'root', getenv('UIAT_TEST_ADMIN_PASSWORD') ?: 'root', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $version = $admin->query('SELECT VERSION()')->fetchColumn();
    $admin->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4");
    $dbCreated = true;
    $admin->exec("USE `$db`");
    // Reuse only the literal synthetic fixture, never data from the application.
    $fixture = file_get_contents(__DIR__ . '/read_api.php');
    if (!preg_match('/\$p->exec\("(.*?)"\);/s', $fixture, $match)) throw new RuntimeException('Fixture missing');
    foreach (explode(';', $match[1]) as $sql) if (trim($sql) !== '') $admin->exec($sql);
    $admin->exec('CREATE TABLE usuarios(id INT,rol VARCHAR(30),activo INT,must_change_password INT,auth_version INT,pass_hash VARCHAR(255))');
    $admin->exec("INSERT INTO usuarios VALUES(10,'jefe_emi',1,0,4,'FICTICIO_NO_CREDENCIAL')");
    $account = $admin->quote($username) . ($port === false ? "@'localhost'" : "@'%'");
    $admin->exec('CREATE USER ' . $account . ' IDENTIFIED BY ' . $admin->quote($password));
    $userCreated = true;
    $grants = [
        'accidentes_activos'=>'id,sidpol,registro_sidpol,fecha_accidente,estado,responsable_id',
        'diligencias_pendientes_activos'=>'id,accidente_id,tipo_diligencia_id,estado',
        'usuarios'=>'id,rol,activo,must_change_password,auth_version',
        'expediente_colaboradores'=>'accidente_id,usuario_id,revocado_en',
        'expediente_transferencias'=>'accidente_id,destino_id,tipo,estado',
    ];
    foreach ($grants as $table=>$columns) $admin->exec("GRANT SELECT ($columns) ON `$db`.`$table` TO $account");
    $reader = new PDO($localDsn . ";dbname=$db;charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $api = new ReadApi($reader);
    $chief = ['id'=>10,'rol'=>'jefe_emi'];
    verify(count($api->handle('buscar_expedientes',['q'=>'FIC'],$chief)['datos'])===1);
    verify((int)$api->handle('consultar_expediente',['expediente_id'=>'1'],$chief)['datos']['id']===1);
    verify(count($api->handle('consultar_diligencias',['expediente_id'=>'1'],$chief)['datos'])===1);
    foreach (['consultar_expediente','consultar_diligencias'] as $endpoint) {
        try { $api->handle($endpoint,['expediente_id'=>'2'],$chief); throw new RuntimeException('Isolation failed'); }
        catch (ApiError $e) { verify($e->status===404); }
    }
    verify(count($api->handle('buscar_expedientes',['q'=>'FIC','limite'=>'1'],['id'=>99,'rol'=>'admin'])['datos'])===1);
    verify(count($api->handle('consultar_diligencias',['expediente_id'=>'1'],['id'=>11,'rol'=>'adjunto'])['datos'])===1);
    $admin->exec("UPDATE expediente_colaboradores SET revocado_en='2000-01-01' WHERE usuario_id=11");
    try { $api->handle('consultar_diligencias',['expediente_id'=>'1'],['id'=>11,'rol'=>'adjunto']); throw new RuntimeException('Revocation failed'); }
    catch (ApiError $e) { verify($e->status===404); }
    foreach (["SELECT * FROM accidentes", "SELECT pass_hash FROM usuarios", "UPDATE usuarios SET activo=0", "DELETE FROM expediente_colaboradores", "INSERT INTO expediente_colaboradores VALUES(2,11,NULL)", "CREATE TABLE forbidden_table(id INT)"] as $sql) forbidden(fn()=>$reader->exec($sql));
    $s=$reader->prepare('SELECT id,rol,activo,must_change_password,auth_version FROM usuarios WHERE id=?');
    $s->execute([10]);
    verify(ReadApi::authenticate(['user'=>['id'=>10,'auth_version'=>4],'api_authenticated_at'=>time(),'api_last_seen'=>time()],$s->fetch(),time())['id']===10);
    echo "$count comprobaciones correctas en motor local $version; datos exclusivamente ficticios.\n";
} catch (Throwable $e) {
    // Deliberately never print credentials, SQL, connection errors or real records.
    fwrite(STDERR, "Falló la prueba aislada. Tipo: " . get_class($e) . "; paso $count.\n");
    $testExitCode = 1;
} finally {
    $reader = null;
    if ($admin && $userCreated) $admin->exec('DROP USER ' . $admin->quote($username) . ($port === false ? "@'localhost'" : "@'%'"));
    if ($admin && $dbCreated) $admin->exec("DROP DATABASE `$db`");
    if ($dbCreated || $userCreated) echo "Recursos ficticios eliminados; ninguna base existente fue consultada.\n";
}
exit($testExitCode);
