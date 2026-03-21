<?php
declare(strict_types=1);

if (defined('UIAT_BOOTSTRAPPED')) {
    return;
}

define('UIAT_BOOTSTRAPPED', true);
define('UIAT_BASE_PATH', dirname(__DIR__));
define('UIAT_APP_PATH', UIAT_BASE_PATH . '/app');
define('UIAT_CONFIG_PATH', UIAT_BASE_PATH . '/config');
define('UIAT_STORAGE_PATH', UIAT_BASE_PATH . '/storage');

require_once UIAT_APP_PATH . '/Support/Env.php';
require_once UIAT_APP_PATH . '/Support/Config.php';
require_once UIAT_APP_PATH . '/Support/helpers.php';

\App\Support\Env::load([
    UIAT_BASE_PATH . '/.env',
    UIAT_BASE_PATH . '/.env.local',
]);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = UIAT_APP_PATH . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

\App\Support\Config::bootstrap(UIAT_CONFIG_PATH);

$timezone = (string) app_config('app.timezone', 'America/Lima');
date_default_timezone_set($timezone);

$debug = (bool) app_config('app.debug', false);
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', $debug);
}

if (APP_DEBUG) {
    @ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    @ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
