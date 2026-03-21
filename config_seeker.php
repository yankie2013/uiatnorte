<?php
// Legacy bridge for endpoints that still include this file directly.

declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

$SEEKER_BASE = trim((string) app_config('services.seeker.base_url', 'https://seeker.red'));
$SEEKER_TOKEN = trim((string) app_config('services.seeker.token', ''));
