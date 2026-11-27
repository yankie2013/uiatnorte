<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
use App\Services\AnalisisWordService;
function assertAnalysisWord(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$dir=dirname(__DIR__).'/tmp/analisis-word-qa';if(!is_dir($dir))mkdir($dir,0775,true);
$context=['accidente'=>['lugar'=>'Av. de Prueba 123','distrito'=>'Los Olivos','registro_sidpol'=>'PRUEBA'], 'vehicles'=>[8=>['tipo'=>'Automóvil','placa'=>'TEST-123']], 'people'=>[9=>['nombre'=>'PERSONA DE PRUEBA','rol'=>'Conductor'],10=>['nombre'=>'PEATÓN DE PRUEBA','rol'=>'Peatón']]];
$paragraph=['texto'=>'Texto guardado con tildes, ñ, & y <símbolos>.'];
$fixtures=[
    'descripcion'=>['contenido'=>['inspeccion'=>['fecha'=>'2026-11-27','hora'=>'08:30','llegada'=>'Recepción de la escena del evento.','metodo'=>'Método registrado por el investigador.','perennizacion'=>"Descriptivo\nPlanimétrico\nFotográfico",'clima'=>'Condiciones climáticas registradas.'], 'vias'=>[['nombre'=>'Av. de Prueba','descripcion'=>'Descripción de la calzada y sus características.','configuracion'=>'Recta y plana','material'=>'Asfalto','control'=>'Marcas discontinuas & señalización vertical','ordenamiento'=>'De sur a norte','maniobrabilidad'=>'Porción circulable','iluminacion'=>'Natural','visibilidad'=>'Buena','intensidad'=>'Discontinua','fluidez'=>'Moderada','observaciones'=>'Observación guardada']], 'consideraciones'=>[$paragraph], 'aproximaciones'=>[['titulo'=>'Área de impacto','descripcion'=>'Ubicación relativa al punto de referencia.']], 'posiciones'=>[['unidad'=>'Automóvil TEST-123','ubicacion'=>'Posición final registrada.']]]],
    'velocidad'=>['riesgos'=>[$paragraph],'parrafos'=>array_fill(0,3,$paragraph),'estimaciones'=>[['vehiculo_id'=>8,'desplazamiento'=>'Estimación cualitativa registrada por el investigador.']]],
    'danos-lesiones'=>['consideraciones'=>[$paragraph],'parrafos'=>array_fill(0,2,$paragraph),'determinaciones'=>[$paragraph]],
    'evidencias'=>['evidencias'=>[['tipo'=>'Biológica','detalle'=>'Muestra registrada','ubicacion'=>'Ubicada sobre la calzada.','caracteristicas'=>"- De color rojo\n- De bordes irregulares\nÁrea: 0.50 × 0.50 m",'consecuencia'=>'Consecuencia consignada.','determinacion'=>'Determinación consignada.']]],
    'integral'=>['parrafos'=>array_fill(0,6,['texto'=>str_repeat('Narración integral del comportamiento de los participantes y la secuencia registrada. ',7)])],
    'causalidad'=>['factores'=>[['tipo'=>'Predominante','involucrado_id'=>9,'descripcion'=>$paragraph['texto']],['tipo'=>'Contributivo','involucrado_id'=>10,'descripcion'=>'Descripción del factor contributivo.']]],
    'infracciones'=>['evaluaciones'=>[['involucrado_id'=>9,'vehiculo_id'=>8,'incurso'=>'si','articulos'=>[['numero'=>'88','titulo'=>'Título registrado & conservado','contenido'=>"Contenido del artículo guardado.\nb) Segundo párrafo del artículo."]]],['involucrado_id'=>10,'vehiculo_id'=>'','incurso'=>'no','articulos'=>[]]],'informacion'=>[$paragraph],'anexos'=>[['texto'=>'Anexo registrado']]],
];
function readWord(string $file):array {
    $z=new ZipArchive();assertAnalysisWord($z->open($file)===true,'DOCX inválido.');$d=new DOMDocument();
    assertAnalysisWord($d->loadXML($z->getFromName('word/document.xml'),LIBXML_NONET),'XML inválido.');
    $x=new DOMXPath($d);$x->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $text='';foreach($x->query('//w:body//w:t') as $n)$text.=$n->textContent."\n";
    assertAnalysisWord($x->query('//w:sectPr')->length===1,'Debe conservar una sección.');
    assertAnalysisWord($x->query('//w:headerReference')->length>0,'Debe conservar la cabecera y marca de agua.');
    assertAnalysisWord(!str_contains($text,'${'),'Quedaron marcadores visibles.');
    foreach(['B5S-187','4709-JC','BDL-756','Giancarlo','Vicente POLICARPO'] as $sample)assertAnalysisWord(!str_contains($text,$sample),'Se filtró texto de ejemplo.');
    for($i=0;$i<$z->numFiles;$i++){ $name=$z->getNameIndex($i);if(str_ends_with($name,'.xml')||str_ends_with($name,'.rels')){ $xml=new DOMDocument();assertAnalysisWord($xml->loadXML($z->getFromIndex($i),LIBXML_NONET),'Parte XML no válida: '.$name); }}
    $z->close();return [$text,$d];
}
foreach($fixtures as $section=>$data){
    $file="$dir/$section.docx";(new AnalisisWordService())->generate($section,$data,$context,$file);[$text]=readWord($file);
    assertAnalysisWord(str_contains($text,AnalisisWordService::TITLES[$section]),'Falta título: '.$section);
    if($section==='descripcion')assertAnalysisWord(str_contains($text,'Av. de Prueba 123, distrito de Los Olivos')&&str_contains($text,'Marcas discontinuas & señalización vertical'),'Faltan distrito o señalización.');
    if($section==='infracciones')assertAnalysisWord(str_contains($text,'no se encuentra incurso')&&str_contains($text,'Artículo 88.-'),'Falta evaluación o artículo.');
    if($section==='evidencias')assertAnalysisWord(str_contains($text,'– De color rojo')&&!str_contains($text,'Esta evidencia fue localizada en:'),'Características o ubicación incorrectas.');
    if($section==='velocidad')assertAnalysisWord(str_contains($text,'D.S. Nº06-2009-MTC')&&str_contains($text,'a. Texto guardado')&&str_contains($text,'TEST-123'),'Faltan introducción, párrafos o vehículo.');
    (new AnalisisWordService())->generate($section,[],$context,"$dir/empty-$section.docx");readWord("$dir/empty-$section.docx");
    echo "$section: OK (completo y vacío).\n";
}
$seed=AnalisisWordService::descriptionFromItp(['fecha_itp'=>'2026-11-27','forma_via'=>'Vía principal','señalizacion_via1'=>'Señalización existente','descripcion_via1'=>'Calzada','localizacion_unidades'=>'Peatón: Ubicación final']);
assertAnalysisWord($seed['vias'][0]['control']==='Señalización existente'&&count($seed['vias'])===1&&$seed['posiciones'][0]['unidad']==='Peatón','Importación ITP incorrecta.');
(new AnalisisWordService())->generate('infracciones',['infracciones'=>[['involucrado_id'=>9,'vehiculo_id'=>8,'codigo'=>'Artículo 90.- Reglas generales','descripcion'=>'Contenido anterior']]],$context,"$dir/legacy.docx");[$text]=readWord("$dir/legacy.docx");assertAnalysisWord(str_contains($text,'Artículo 90.- Reglas generales')&&str_contains($text,'Contenido anterior'),'Se perdieron infracciones anteriores.');
$photoDir=dirname(__DIR__).'/uploads';if(!is_dir($photoDir))mkdir($photoDir,0775,true);
$photo=tempnam($photoDir,'qa_analysis_');$im=imagecreatetruecolor(300,180);imagefill($im,0,0,imagecolorallocate($im,65,105,140));imagepng($im,$photo);imagedestroy($im);
try{
    $photoData=$fixtures['descripcion'];$photoData['contenido']['vias'][0]['fotografia']='uploads/'.basename($photo);$photoData['contenido']['vias'][0]['pie']='Fotografía del expediente de prueba.';
    (new AnalisisWordService())->generate('descripcion',$photoData,$context,"$dir/descripcion-foto.docx");readWord("$dir/descripcion-foto.docx");
    $z=new ZipArchive();$z->open("$dir/descripcion-foto.docx");assertAnalysisWord($z->locateName('word/media/analisis-foto-1.png')!==false,'Falta fotografía propia.');$z->close();
}finally{unlink($photo);}
echo "Importación ITP, compatibilidad y fotografía: OK.\n";
// Exercise the same persisted-data loader used by the download, without touching MySQL.
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach([
'CREATE TABLE accidentes_activos(id INTEGER, lugar TEXT, registro_sidpol TEXT, cod_dep TEXT, cod_prov TEXT, cod_dist TEXT)',
'CREATE TABLE ubigeo_distrito(cod_dep TEXT,cod_prov TEXT,cod_dist TEXT,nombre TEXT)',
'CREATE TABLE accidente_analisis_informe(accidente_id INTEGER,seccion TEXT,contenido TEXT)',
'CREATE TABLE involucrados_vehiculos_activos(accidente_id INTEGER,vehiculo_id INTEGER)',
'CREATE TABLE vehiculos(id INTEGER,placa TEXT,tipo_id INTEGER)',
'CREATE TABLE tipos_vehiculo(id INTEGER,nombre TEXT)',
'CREATE TABLE involucrados_personas_activos(id INTEGER,accidente_id INTEGER,vehiculo_id INTEGER,persona_id INTEGER,rol_id INTEGER)',
'CREATE TABLE personas(id INTEGER,nombres TEXT,apellido_paterno TEXT,apellido_materno TEXT)',
'CREATE TABLE participacion_persona(Id INTEGER,Nombre TEXT)',
'CREATE TABLE itp_activos(id INTEGER,accidente_id INTEGER,fecha_itp TEXT,hora_itp TEXT,forma_via TEXT,descripcion_via1 TEXT,señalizacion_via1 TEXT,localizacion_unidades TEXT)',
] as $sql)$db->exec($sql);
$db->exec("INSERT INTO accidentes_activos VALUES(1,'Lugar guardado','PRUEBA','15','01','17');INSERT INTO ubigeo_distrito VALUES('15','01','17','Los Olivos');INSERT INTO tipos_vehiculo VALUES(2,'Automóvil');INSERT INTO vehiculos VALUES(8,'TEST-123',2);INSERT INTO involucrados_vehiculos_activos VALUES(1,8);INSERT INTO personas VALUES(7,'Persona','Prueba','QA');INSERT INTO participacion_persona VALUES(1,'Conductor');INSERT INTO involucrados_personas_activos VALUES(9,1,8,7,1);INSERT INTO itp_activos VALUES(3,1,'2026-11-26','08:00','Vía anterior','Anterior','Anterior','');INSERT INTO itp_activos VALUES(4,1,'2026-11-27','08:30','Vía actual','Descripción actual','Marcas actuales','Peatón: Lugar');");
[$loaded,$loadedContext]=AnalisisWordService::load($db,1,'descripcion');
assertAnalysisWord($loaded['contenido']['vias'][0]['control']==='Marcas actuales'&&$loadedContext['accidente']['distrito']==='Los Olivos','Debe cargar ITP más reciente y distrito.');
$insert=$db->prepare('INSERT INTO accidente_analisis_informe VALUES(?,?,?)');
foreach($fixtures as $section=>$fixture)$insert->execute([1,$section,json_encode($fixture,JSON_UNESCAPED_UNICODE)]);
foreach($fixtures as $section=>$fixture){[$loaded,$loadedContext]=AnalisisWordService::load($db,1,$section);assertAnalysisWord($loaded===$fixture,'El loader cambió el contenido guardado: '.$section);assertAnalysisWord($loadedContext['vehicles'][8]['placa']==='TEST-123'&&$loadedContext['people'][9]['rol']==='Conductor','Falta contexto de vehículo o persona.');}
$db->exec("DELETE FROM accidente_analisis_informe WHERE seccion='infracciones'");
$db->prepare("UPDATE accidente_analisis_informe SET contenido=? WHERE seccion='causalidad'")->execute([json_encode(['infracciones'=>[['codigo'=>'88','descripcion'=>'Legacy','involucrado_id'=>9]]])]);
[$loaded]=AnalisisWordService::load($db,1,'infracciones');assertAnalysisWord($loaded['infracciones'][0]['descripcion']==='Legacy','No cargó infracciones legacy de causalidad.');
try{AnalisisWordService::load($db,999,'integral');throw new RuntimeException('Aceptó expediente inexistente.');}catch(RuntimeException $e){assertAnalysisWord($e->getMessage()==='Expediente no disponible.','Fallo inesperado con expediente inexistente.');}
require dirname(__DIR__).'/word_filename_helper.php';assertAnalysisWord(str_ends_with(uiat_docx_filename(['Análisis','velocidad','PRUEBA'],'analisis'),'.docx'),'Nombre de descarga incorrecto.');
echo "Carga persistida de siete apartados, contexto, ITP reciente, legacy y expediente inexistente: OK.\n";
