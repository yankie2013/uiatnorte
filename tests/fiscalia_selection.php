<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
use App\Support\FiscaliaSelection as Selection;
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
foreach (['12','35','10','17','06'] as $dist) check(Selection::officeForDistrict('15','01',$dist)==='transito','Distrito de tránsito.');
check(Selection::officeForDistrict('15','01','25')==='puente','Puente Piedra.');
foreach (['02','39'] as $dist) check(Selection::officeForDistrict('15','01',$dist)==='santa','Santa Rosa y Ancón.');
foreach (['01','02','03','04','05','06','07'] as $dist) check(Selection::officeForDistrict('15','04',$dist)==='canta','Todos los distritos de Canta.');
check(Selection::officeForDistrict('15','01','03')===null,'Otras jurisdicciones conservan el catálogo.');
$expected='Primer Despacho de la 1° Fiscalía Provincial Penal Corporativa de Santa Rosa - Ancón del Distrito Fiscal de Lima Noroeste';
check(Selection::name('santa',1,1)===$expected,'Formato de nombre y número.');
$old=Selection::describe('Primera Fiscalia Provincial Penal Corporativa de Santa Rosa- Primer Despacho-Distrito Fiscal de Lima Noroeste');
check($old['name']===$expected,'Interpreta el catálogo anterior.');
check(Selection::describe($expected)===$old,'El nombre unido conserva los componentes.');
$p=App\Database\Database::connection();
$service=new App\Services\AccidenteService(new App\Repositories\AccidenteRepository($p));
foreach ($service->fiscaliasSeleccion() as $office) {
    check($office['selection']!==null,'Fiscalía catalogada.');
    check($office['nombre']===$office['selection']['name'],'Nombre completo guardado.');
    check(mb_strlen($office['nombre'])<=150,'Capacidad de almacenamiento.');
}
// Los fiscales existentes conservan sus IDs y sus vínculos aun cuando haya nombres equivalentes.
foreach ($p->query('SELECT id,fiscalia_id FROM fiscales') as $fiscal) {
    $source=(int)$fiscal['fiscalia_id'];
    check($service->fiscaliaConFiscal($source,(int)$fiscal['id'])===$source,'Vínculo original preservado.');
    $sourceOffice=current(array_filter($service->fiscaliasSeleccion(),static fn($row)=>(int)$row['id']===$source));
    if (!$sourceOffice) continue;
    foreach ($service->fiscaliasSeleccion() as $alias) {
        if ($alias['selection']!==$sourceOffice['selection']) continue;
        check($service->fiscaliaConFiscal((int)$alias['id'],(int)$fiscal['id'])===$source,'Resuelve fiscales de entradas equivalentes.');
        check(in_array((int)$fiscal['id'],array_map('intval',array_column($service->fiscalesSeleccion((int)$alias['id']),'id')),true),'Fiscal visible al seleccionar la fiscalía equivalente.');
    }
}
echo "PASS: jurisdicciones, formato, catálogo guardado y vínculos de fiscales.\n";
$numbered = $service->nombreFiscaliaRegistro(1, '3');
check($numbered==='Primer Despacho de la 3° Fiscalía Corporativa de Tránsito y Seguridad Vial del Distrito Fiscal de Lima Norte','Número libre independiente del catálogo.');
check($service->nombreFiscaliaRegistro(3,'')==='Primer Despacho de la Fiscalía Provincial Penal Corporativa de Santa Rosa - Ancón del Distrito Fiscal de Lima Noroeste','Número vacío omite el numeral de una fiscalía antes numerada.');
$invalid=false;
try {$service->nombreFiscaliaRegistro(1,'-1');} catch(InvalidArgumentException $e) {$invalid=true;}
check($invalid,'Rechaza números negativos.');
$repo=new App\Repositories\AccidenteRepository($p);
$previousActor=(int)$p->query('SELECT COALESCE(@actor_id,0)')->fetchColumn();
$admin=(int)$p->query("SELECT id FROM usuarios WHERE activo=1 AND rol='admin' LIMIT 1")->fetchColumn();
$p->beginTransaction();
try {
    $p->exec('SET @actor_id='.$admin);
    $numberedId=$repo->findCatalogIdByName('fiscalia','nombre',$numbered) ?? $repo->createCatalogItem('fiscalia','nombre',$numbered);
    check($repo->fiscalBelongsToFiscalia(1,$numberedId),'La selección libre de número conserva el vínculo con el fiscal original.');
    check(in_array(1,array_map('intval',array_column($repo->fiscalesByFiscalia($numberedId),'id')),true),'Fiscal original disponible al reabrir la fiscalía numerada.');
    check($service->fiscaliaConFiscal($numberedId,1)===1,'Acepta fiscal original sin trasladarlo ni duplicarlo.');
} finally {
    $p->rollBack();
    $p->exec('SET @actor_id='.$previousActor);
}
echo "PASS: número opcional, número libre guardado y fiscales originales conservados.\n";
foreach (['puente','santa'] as $office) {
    check(!Selection::sameDispatch(Selection::describe(Selection::name($office,2,1)),Selection::describe(Selection::name($office,2,2))),'Fiscalías de números distintos no comparten fiscales.');
}
$catalog=$service->fiscaliasSeleccion();
$lookup=[];
foreach ($catalog as $row) $lookup[(int)$row['id']]=$row['selection'];
$fiscales=[];
foreach ($p->query('SELECT id,fiscalia_id FROM fiscales') as $row) $fiscales[(int)$row['id']]=$lookup[(int)$row['fiscalia_id']] ?? null;
foreach ($catalog as $office) {
    if (!in_array($office['selection']['office'],['puente','santa'],true)) continue;
    foreach (['','1','2','3'] as $number) {
        $expected=$office['selection'];$expected['number']=$number===''?0:(int)$number;
        $visible=array_map('intval',array_column($service->fiscalesSeleccion((int)$office['id'],$number),'id'));
        $wanted=[];
        foreach ($fiscales as $id=>$selection) if(Selection::sameDispatch($expected,$selection)) $wanted[]=$id;
        sort($visible);sort($wanted);
        check($visible===$wanted,'Filtra los fiscales exactamente por despacho y número.');
    }
}
$invalid=false;
try {$service->nombreFiscaliaRegistro(1,'4');} catch(InvalidArgumentException $e) {$invalid=true;}
check($invalid,'El número no supera 3.');
echo "PASS: Puente Piedra, Santa Rosa y Ancón separan fiscales por despacho y número.\n";
