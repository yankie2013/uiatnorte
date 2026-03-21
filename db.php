<?php
require_once __DIR__ . '/bootstrap/app.php';

use App\Database\Database;

try {
    $pdo = Database::connection();
    Database::defineLegacyConstants();
} catch (PDOException $e) {
    exit('Error de conexión: ' . $e->getMessage());
}

if (!function_exists('db')) {
    function db(): PDO
    {
        global $pdo;
        return $pdo;
    }
}
