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
