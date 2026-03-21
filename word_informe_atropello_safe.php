<?php
// Wrapper de compatibilidad para la version "safe" del informe.
if (isset($_GET['accidente_id']) && !isset($_GET['id'])) {
    $_GET['id'] = $_GET['accidente_id'];
}
require __DIR__ . '/word_informe_atropello.php';
