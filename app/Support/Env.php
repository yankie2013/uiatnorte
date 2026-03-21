<?php
declare(strict_types=1);

namespace App\Support;

final class Env
{
    public static function load(array $paths): void
    {
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '' || !is_file($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $line) {
                self::loadLine($line);
            }
        }
    }

    private static function loadLine(string $line): void
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            return;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            return;
        }

        $name = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            return;
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        self::set($name, $value);
    }

    private static function set(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
