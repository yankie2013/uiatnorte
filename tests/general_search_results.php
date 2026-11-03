<?php
declare(strict_types=1);

// In-memory fictional fixtures only; never load bootstrap, .env or the application DB.
require dirname(__DIR__) . '/app/Support/GeneralSearchResultDetails.php';
require dirname(__DIR__) . '/app/Support/WorkspacePage.php';

use App\Support\GeneralSearchResultDetails;

function assertSearch(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function responsible_label(?string $name, ?string $grade = null): string
{
    return trim(trim((string) $grade) . ' ' . trim((string) $name)) ?: 'Sin asignar';
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE involucrados_vehiculos(id INTEGER, accidente_id INTEGER, vehiculo_id INTEGER, orden_participacion TEXT, eliminado_en TEXT);
CREATE TABLE vehiculos(id INTEGER, placa TEXT);
CREATE TABLE involucrados_personas(id INTEGER, accidente_id INTEGER, vehiculo_id INTEGER, persona_id INTEGER, rol_id INTEGER, eliminado_en TEXT);
CREATE TABLE personas(id INTEGER, nombres TEXT, apellido_paterno TEXT, apellido_materno TEXT);
CREATE TABLE participacion_persona(Id INTEGER, Nombre TEXT, Orden INTEGER);
INSERT INTO participacion_persona VALUES(1,'Conductor',1),(2,'Peatón',2),(3,'Testigo presencial',3);
INSERT INTO personas VALUES(1,'Ana <Ficticia>','Ensayo','Uno'),(2,'Persona','Peatón','Ficticia'),(3,'Testigo','Ficticio',''),(4,'Registro','Eliminado','');
INSERT INTO vehiculos VALUES(10,'TEST-001'),(20,'AJENO-002'),(30,'BORRADO-003'),(40,'SPLACA999');
INSERT INTO involucrados_vehiculos VALUES(1,53,10,'UT-1',NULL),(2,54,20,'UT-2',NULL),(3,53,30,'UT-3','2000-01-01'),(4,53,40,'UT-4',NULL);
INSERT INTO involucrados_personas VALUES(1,53,10,1,1,NULL),(2,53,NULL,2,2,NULL),(3,53,20,3,3,NULL),(4,53,30,4,1,'2000-01-01'),(5,54,20,1,1,NULL);");
$resultDetails = GeneralSearchResultDetails::load($pdo, [53, 53]);
$people = $resultDetails['participants'][53];
assertSearch(count($people) === 3, 'Exclude deleted participants and do not duplicate cases.');
assertSearch(count($resultDetails['vehicles'][53]) === 2, 'Exclude deleted and foreign vehicles.');
assertSearch($people[0]['vehicles'][0]['placa'] === 'TEST-001', 'Driver plate association.');
assertSearch($people[1]['vehicles'] === [], 'Do not assign a vehicle to the pedestrian.');
assertSearch($people[2]['vehicles'] === [], 'Reject foreign-case vehicle even if the person references its ID.');
assertSearch(!isset($resultDetails['participants'][54]), 'Load only the selected result page.');
assertSearch(GeneralSearchResultDetails::load($pdo, []) === ['participants' => [], 'vehicles' => []], 'Empty search.');
try {
    GeneralSearchResultDetails::load($pdo, range(1, 51));
    throw new RuntimeException('Missing page limit.');
} catch (InvalidArgumentException) {}

$rows = [['id' => 53, 'registro_sidpol' => 'FIC-0001', 'fecha_accidente' => '2000-01-02 15:45:00', 'lugar' => 'Lugar <Ficticio>', 'jurisdiccion' => 'Comisaría de prueba', 'responsable' => 'Responsable ficticio', 'responsable_grado' => 'Grado de prueba', 'estado' => 'Pendiente']];
$more = true; $page = 2; $q = 'Persona ficticia'; $oficio = '028-2000'; $filter = 'mios';
ob_start();
require dirname(__DIR__) . '/partials/gestion_busqueda_resultados.php';
$html = ob_get_clean();
$document = new DOMDocument();
@$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
$xpath = new DOMXPath($document);
assertSearch($xpath->query('//h3/a')->item(0)->textContent === 'FIC-0001', 'Show SIDPOL without internal ID.');
foreach (['02/01/2000 15:45', 'Lugar &lt;Ficticio&gt;', 'Comisaría de prueba', 'Grado de prueba Responsable ficticio', 'Ana &lt;Ficticia&gt; Ensayo Uno', 'Peatón', 'Testigo presencial', 'TEST-001', 'Sin placa registrada'] as $expected) {
    assertSearch(str_contains($html, $expected), 'Missing result detail: ' . $expected);
}
assertSearch(!str_contains($html, 'AJENO-002') && !str_contains($html, 'BORRADO-003'), 'No foreign or deleted plates in rendered results.');
assertSearch(!str_contains($html, '#53') && !str_contains($html, 'SPLACA999'), 'No internal case or placeholder plate identifiers.');
assertSearch(str_contains($html, 'pagina=1') && str_contains($html, 'pagina=3') && str_contains($html, 'filtro=mios'), 'Preserve pagination and filters.');
assertSearch($xpath->query('//a[contains(@class,"js-case-modal-trigger") and @data-case-id="53"]')->length === 2, 'Preserve modal navigation.');

$rows = [['id' => 55, 'fecha_accidente' => '0000-00-00 00:00:00']]; $more = false;
ob_start(); require dirname(__DIR__) . '/partials/gestion_busqueda_resultados.php'; $emptyFields = ob_get_clean();
foreach (['Sin SIDPOL registrado', 'Sin fecha y hora registradas', 'Sin lugar registrado', 'Sin jurisdicción registrada', 'Sin asignar', 'Sin participantes registrados.'] as $expected) {
    assertSearch(str_contains($emptyFields, $expected), 'Missing fallback: ' . $expected);
}
$rows = [];
ob_start(); require dirname(__DIR__) . '/partials/gestion_busqueda_resultados.php'; $empty = ob_get_clean();
assertSearch(str_contains($empty, 'No hay expedientes para esta búsqueda.'), 'Empty results message.');
echo "OK: datos ficticios en memoria; asociaciones por expediente, eliminados, roles, placas, detalles, escape HTML, paginación y modal.\n";
