<?php
require_once __DIR__ . '/bootstrap/app.php';
$welcomeHome = true;
$welcomeSearch = \App\Support\WelcomeSearch::input($_GET);
$welcomeHasSearch = $welcomeSearch['value'] !== '' && $welcomeSearch['error'] === '';
// Pass only the selected, validated criterion into the existing permission-scoped search.
$_GET = ['ver_todos'=>'1', 'estado'=>'todos'];
if ($welcomeHasSearch) $_GET[$welcomeSearch['criterion']] = $welcomeSearch['value'];
require __DIR__ . '/accidente_listar.php';
