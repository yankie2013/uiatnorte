<?php
// Wrapper de compatibilidad para exportaciones antiguas.
if (isset($_GET['accidente_id']) && !isset($_GET['id'])) {
    $_GET['id'] = $_GET['accidente_id'];
}
require __DIR__ . '/exportar_accidente.php';
