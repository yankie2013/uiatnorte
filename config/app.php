<?php

$debug = getenv('APP_DEBUG');

return [
    'name' => getenv('APP_NAME') ?: 'UIAT Norte',
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Lima',
    'debug' => $debug === false ? true : filter_var($debug, FILTER_VALIDATE_BOOL),
];
