<?php

$lifetimeDays = (int) (getenv('SESSION_LIFETIME_DAYS') ?: 365);
$lifetimeDays = max(1, $lifetimeDays);

$secureCookie = getenv('SESSION_SECURE_COOKIE');

return [
    'name' => getenv('SESSION_NAME') ?: 'UIATNORTESESSID',
    'lifetime' => $lifetimeDays * 86400,
    'path' => getenv('SESSION_PATH') ?: '/',
    'domain' => getenv('SESSION_DOMAIN') ?: '',
    'secure' => $secureCookie === false ? null : filter_var($secureCookie, FILTER_VALIDATE_BOOL),
    'http_only' => true,
    'same_site' => getenv('SESSION_SAME_SITE') ?: 'Lax',
    'save_path' => getenv('SESSION_SAVE_PATH') ?: storage_path('sessions'),
];
