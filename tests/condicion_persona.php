<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Support/CondicionPersona.php';
use App\Support\CondicionPersona as Condicion;
$count=0;
foreach(['Herido'=>['herido','HERIDA','lesionado',' lesionada '],'Fallecido'=>['fallecido','FALLECIDA','occiso',' occisa '],'Ileso'=>['ileso','ILESA']] as $expected=>$aliases){
    foreach($aliases as $alias){
        if(Condicion::normalizar($alias)!==$expected || Condicion::esHerido($alias)!==($expected==='Herido') || Condicion::esFallecido($alias)!==($expected==='Fallecido') || Condicion::esIleso($alias)!==($expected==='Ileso'))throw new RuntimeException('Equivalencia incorrecta: '.$alias);
        $count++;
    }
}
foreach([null,'','  '] as $empty)if(Condicion::normalizar($empty)!==null)throw new RuntimeException('No inventar condición vacía');
foreach(['no lesionado','desconocido',[],3] as $invalid){
    $rejected=false;try{Condicion::normalizar($invalid);}catch(InvalidArgumentException $e){$rejected=true;}
    if(!$rejected || Condicion::esIleso($invalid))throw new RuntimeException('Condición desconocida aceptada');
}
echo "PASS: $count equivalencias, mayúsculas, espacios, vacíos y rechazo de condiciones desconocidas.\n";
