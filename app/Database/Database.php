<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = app_config('database', []);
        $host = (string) ($config['host'] ?? 'localhost');
        $port = (int) ($config['port'] ?? 3306);
        $name = (string) ($config['name'] ?? '');
        $user = (string) ($config['user'] ?? '');
        $pass = (string) ($config['pass'] ?? '');
        $charset = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($config['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';
        $collation = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($config['collation'] ?? 'utf8mb4_general_ci')) ?: 'utf8mb4_general_ci';
        $timeZone = (string) ($config['time_zone'] ?? '-05:00');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        self::$connection = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        self::$connection->exec("SET time_zone = " . self::$connection->quote($timeZone));
        self::$connection->exec(sprintf('SET NAMES %s COLLATE %s', $charset, $collation));

        // La identidad se toma de la sesión; nunca de parámetros del formulario.
        $actorId = (int) ($_SESSION['user']['id'] ?? 0);
        $identity = false;
        $columns = [];
        $available = [];
        if ($actorId > 0) {
            $columns = self::$connection->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios'")->fetchAll(PDO::FETCH_COLUMN);
            $available = array_fill_keys($columns, true);
        }
        if ($actorId > 0) {
            $select = ['id', 'nombre', 'email', 'rol', 'activo'];
            foreach (['grado', 'must_change_password', 'auth_version'] as $optional) {
                $select[] = isset($available[$optional]) ? "`$optional`" : "0 AS `$optional`";
            }
            $actor = self::$connection->prepare('SELECT ' . implode(',', $select) . ' FROM usuarios WHERE id=?');
            $actor->execute([$actorId]);
            $identity = $actor->fetch();
        }
        $actorId = $identity && (int)$identity['activo'] === 1 && !(int)$identity['must_change_password'] && (int)$identity['auth_version'] === (int)($_SESSION['user']['auth_version'] ?? 0) ? (int)$identity['id'] : 0;
        self::$connection->exec('SET @actor_id = ' . $actorId);
        if ($actorId > 0 && isset($_SESSION['user'])) {
            $_SESSION['user'] = array_intersect_key($identity, array_flip(['id','nombre','grado','email','rol','auth_version']));
            $_SESSION['rol'] = $identity['rol'];
        } elseif (isset($_SESSION['user'])) {
            unset($_SESSION['user'], $_SESSION['rol'], $_SESSION['id']);
        }
        return self::$connection;
    }

    public static function defineLegacyConstants(): void
    {
        $config = app_config('database', []);
        $constants = [
            'DB_HOST' => $config['host'] ?? 'localhost',
            'DB_PORT' => $config['port'] ?? 3306,
            'DB_NAME' => $config['name'] ?? '',
            'DB_USER' => $config['user'] ?? '',
            'DB_PASS' => $config['pass'] ?? '',
        ];

        foreach ($constants as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
}
