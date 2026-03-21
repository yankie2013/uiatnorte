<?php
declare(strict_types=1);

namespace App\Support;

final class Config
{
    private static array $items = [];

    public static function bootstrap(string $configPath): void
    {
        if (self::$items !== []) {
            return;
        }

        foreach (glob($configPath . '/*.php') ?: [] as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $config = require $file;

            if (is_array($config)) {
                self::$items[$key] = $config;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return self::$items;
        }

        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
