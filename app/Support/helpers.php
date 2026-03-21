<?php
declare(strict_types=1);

use App\Support\Config;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = UIAT_BASE_PATH;
        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $base = UIAT_APP_PATH;
        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        $base = UIAT_CONFIG_PATH;
        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = UIAT_STORAGE_PATH;
        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('app_config')) {
    function app_config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}
