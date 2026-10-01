<?php
/** Solo MAMP local: revisa vistas con DEFINER inexistente. --apply respalda y corrige esos definidores. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/bootstrap/app.php';

try {
    $config = app_config('database', []);
    if (($config['host'] ?? '') !== '127.0.0.1' || (int) ($config['port'] ?? 0) !== 8889 || ($config['name'] ?? '') !== 'uiatnorte_dev') {
        throw new RuntimeException('Este script solo admite 127.0.0.1:8889/uiatnorte_dev. No se abrió ninguna conexión.');
    }
    $pdo = App\Database\Database::connection();
    $server = $pdo->query('SELECT DATABASE() db, @@port port, @@datadir datadir')->fetch();
    if ($server['db'] !== 'uiatnorte_dev' || (int) $server['port'] !== 8889 || !str_starts_with($server['datadir'], '/Applications/MAMP/')) {
        throw new RuntimeException('El servidor no corresponde a la instalación local de MAMP.');
    }
    if ($pdo->query('SHOW REPLICA STATUS')->fetchAll() !== []) throw new RuntimeException('Hay replicación configurada. No se aplicó ningún cambio.');
    if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND engine='FEDERATED'")->fetchColumn() > 0) {
        throw new RuntimeException('Hay tablas remotas. No se aplicó ningún cambio.');
    }
    $views = $pdo->query("SELECT TABLE_NAME FROM information_schema.views v WHERE TABLE_SCHEMA=DATABASE()
        AND NOT EXISTS (SELECT 1 FROM mysql.user u WHERE CONCAT(u.User,'@',u.Host)=v.DEFINER)")->fetchAll(PDO::FETCH_COLUMN);
    $apply = in_array('--apply', $argv, true);
    if ($views === []) { echo "No hay vistas con definidores inexistentes.\n"; exit; }
    $changes = [];
    foreach ($views as $name) {
        $quoted = '`' . str_replace('`', '``', $name) . '`';
        $row = $pdo->query("SHOW CREATE VIEW $quoted")->fetch();
        $old = preg_replace('/^CREATE\b/', 'ALTER', $row['Create View'], 1);
        $new = preg_replace('/\bDEFINER\s*=\s*`(?:``|[^`])*`@`(?:``|[^`])*`/i', 'DEFINER=CURRENT_USER', $old, 1, $count);
        if ($count !== 1) throw new RuntimeException("No se reconoció el DEFINER de $name.");
        $changes[$name] = [$old, $new, $row['character_set_client'], $row['collation_connection']];
        echo ($apply ? 'Preparada: ' : 'Pendiente: ') . $name . "\n";
    }
    if (!$apply) { echo "Solo lectura. Ejecuta con --apply para respaldar y corregir las vistas locales.\n"; exit; }
    $backupDir = (getenv('HOME') ?: sys_get_temp_dir()) . '/.uiatnorte/backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) throw new RuntimeException('No se pudo crear el directorio de respaldo.');
    $backup = $backupDir . '/definers-uiatnorte-dev-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql';
    $sql = "-- Respaldo de definidores de vistas LOCALES. No contiene filas de las tablas.\nUSE `uiatnorte_dev`;\n";
    foreach ($changes as [$old, , $charset, $collation]) $sql .= "SET NAMES $charset COLLATE $collation;\n$old;\n";
    if (file_put_contents($backup, $sql, LOCK_EX) === false) throw new RuntimeException('No se pudo guardar el respaldo; no se aplicaron cambios.');
    chmod($backup, 0600);
    echo "Respaldo: $backup\n";
    foreach ($changes as $name => [, $new, $charset, $collation]) {
        $pdo->exec("SET NAMES $charset COLLATE $collation");
        $pdo->exec($new);
        $pdo->query('SELECT * FROM `' . str_replace('`', '``', $name) . '` LIMIT 0');
        echo "Corregida y verificada: $name\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
