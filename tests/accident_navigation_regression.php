<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Support/AccidentNavigation.php';

use App\Support\AccidentNavigation;

$source = '/accidente_listar.php?distrito=Comas&comisaria_id=12&estado=todos';
$list = AccidentNavigation::listUrl($source);
parse_str((string) parse_url((string)$list, PHP_URL_QUERY), $listFilters);
if (($listFilters['distrito'] ?? null) !== 'Comas'
    || ($listFilters['comisaria_id'] ?? null) !== '12'
    || ($listFilters['estado'] ?? null) !== 'todos') {
    throw new RuntimeException('La URL de producción perdió los filtros de la lista.');
}

$labels = array_column(AccidentNavigation::breadcrumbs($list), 'label');
if ($labels !== ['Gestión de la información', 'Distritos', 'Comisarías', 'Lista de accidentes']) {
    throw new RuntimeException('La barra no muestra la lista de accidentes como ubicación actual.');
}

$caseUrl = AccidentNavigation::caseUrl(7, $list);
parse_str((string) parse_url($caseUrl, PHP_URL_QUERY), $caseQuery);
if (($caseQuery['lista'] ?? null) !== $list) {
    throw new RuntimeException('El expediente perdió el enlace a su lista de origen.');
}
$caseCrumbs = AccidentNavigation::breadcrumbs($caseQuery['lista'], 'Expediente #7');
if ($caseCrumbs[3]['url'] !== $list || $caseCrumbs[4]['label'] !== 'Expediente #7') {
    throw new RuntimeException('El expediente no permite volver a la lista de origen.');
}

if (AccidentNavigation::listUrl('https://example.com/accidente_listar.php') !== null) {
    throw new RuntimeException('La lista acepta una URL externa.');
}

echo "PASS: barra y regreso al listado de origen.\n";
