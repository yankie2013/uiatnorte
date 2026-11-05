<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
use App\Support\WelcomeSearch;
use App\Support\Auth;

function checkWelcome(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
foreach (array_keys(WelcomeSearch::CRITERIA) as $criterion) {
    $search=WelcomeSearch::input(['criterio'=>$criterion,'valor'=>'  Dato de prueba  ','pagina'=>'3']);
    checkWelcome($search['criterion']===$criterion && $search['value']==='Dato de prueba' && $search['page']===3, 'Criterio o valor perdido');
    parse_str((string)parse_url(WelcomeSearch::url($search,4),PHP_URL_QUERY),$query);
    $next=WelcomeSearch::input($query);
    checkWelcome($next['criterion']===$criterion && $next['value']===$search['value'] && $next['page']===4, 'Paginación perdió la búsqueda');
}
checkWelcome(WelcomeSearch::input(['criterio'=>'sql','valor'=>'dato'])['error']!=='','Criterio desconocido aceptado');
checkWelcome(WelcomeSearch::input(['valor'=>['dato']])['error']!=='','Arreglo aceptado como texto');
checkWelcome(WelcomeSearch::input(['valor'=>str_repeat('x',201)])['error']!=='','Texto demasiado largo aceptado');
checkWelcome(WelcomeSearch::input(['pagina'=>'-3'])['page']===1,'Página negativa aceptada');
checkWelcome(!WelcomeSearch::input([])['submitted'],'Inicio interpretado como búsqueda');

Auth::startSession();
$_SESSION=[];
checkWelcome(Auth::postLoginDestination()==='index.php','El login no llega al inicio');
$_SESSION['auth_return_to']='/uiatnorte/accidente_vista_tabs.php?accidente_id=7';
checkWelcome(Auth::postLoginDestination()==='/uiatnorte/accidente_vista_tabs.php?accidente_id=7','Se perdió el regreso a página protegida');
$_SESSION['auth_return_to']='https://example.com/';
checkWelcome(Auth::postLoginDestination()==='index.php','Redirección externa aceptada');
$_SESSION=[];
session_destroy();
echo "PASS: cuatro criterios, validación, paginación y destino de autenticación.\n";
