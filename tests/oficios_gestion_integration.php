<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/app.php';
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Repositories\OficioRepository;
use App\Services\OficioService;
$p = App\Database\Database::connection();
$repository = new OficioRepository($p);
$service = new OficioService($repository);
function expect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$actors = $p->query("SELECT id,nombre,rol,unidad FROM usuarios WHERE activo=1 AND rol IN ('secretaria','jefe_emi','adjunto','admin','guardia','administracion') ORDER BY id")->fetchAll();
$creator = current(array_filter($actors, fn($u)=>$u['rol']==='secretaria'));
$chief = current(array_filter($actors, fn($u)=>$u['rol']==='jefe_emi' && $u['unidad']===$creator['unidad']));
$other = current(array_filter($actors, fn($u)=>$u['id']!==$creator['id'] && $u['rol']!=='jefe_emi'));
expect((bool)$creator && (bool)$chief && (bool)$other, 'Se requieren usuarios de prueba activos.');
App\Support\Auth::startSession();
$sessionBefore = $_SESSION ?? [];
$p->beginTransaction();
try {
    $_SESSION['user']=$creator;
    $p->exec('SET @actor_id='.(int)$creator['id']);
    $ctx=$service->gestionContext();
    expect(in_array((int)$chief['id'], array_map('intval',array_column($ctx['encargados'],'id')),true),'Jefe de misma unidad disponible.');
    $case = current(array_filter($ctx['expedientes'],fn($c)=>(int)$c['responsable_id']===(int)$chief['id']));
    expect((bool)$case,'Se requiere expediente del JEFE EMI de prueba.');
    $form=$service->formContext();
    $asunto=$p->query("SELECT id,nombre FROM oficio_asunto WHERE activo=1 AND tipo='SOLICITAR' AND nombre LIKE '%video%' LIMIT 1")->fetch();
    $input=array_merge($service->defaultData(),[
        'gestion'=>1,'anio_oficio'=>2000,'oficial_ano_id'=>'','comisaria_id'=>'','encargado_id'=>'','accidente_id'=>'',
        'entidad_id'=>$form['entidades'][0]['id'],'oficial_ano_id'=>$form['oficial_ano_default'],
        'asunto_id'=>$asunto['id'],'plantilla_nombre'=>$asunto['nombre'],'asunto_texto'=>'Prueba de cámaras',
        'camara_rango_desde'=>'14:00','camara_rango_hasta'=>'15:00',
    ]);
    $payload=(new ReflectionMethod($service,'payload'))->invoke($service,$input,null);
    expect($payload['accidente_id']===null,'Gestión permite oficio sin expediente.');
    $standaloneInput=array_merge($input,['case_mode'=>'standalone','comisaria_id'=>$case['comisaria_id'],'encargado_id'=>$chief['id'],'accidente_id'=>$case['id']]);
    $standalonePayload=(new ReflectionMethod($service,'payload'))->invoke($service,$standaloneInput,null);
    expect($standalonePayload['accidente_id']===null && $standalonePayload['comisaria_id']===null,'Sin caso descarta vínculos enviados.');
    expect((int)$standalonePayload['encargado_id']===(int)$chief['id'],'Sin caso conserva encargado.');
    $relatedPayload=(new ReflectionMethod($service,'payload'))->invoke($service,array_merge($standaloneInput,['case_mode'=>'related']),null);
    expect((int)$relatedPayload['accidente_id']===(int)$case['id'],'Con caso conserva expediente coincidente.');
    $invalid=false;
    try {(new ReflectionMethod($service,'payload'))->invoke($service,array_merge($input,['case_mode'=>'related']),null);} catch(InvalidArgumentException $e){$invalid=true;}
    expect($invalid,'Con caso exige comisaría, encargado y expediente.');

    expect((int)$payload['anio']===(int)date('Y'),'Año automático del registro, sin aceptar año enviado.');
    expect((int)$payload['oficial_ano_id']===(int)$repository->oficialAnos()[0]['id'],'Nombre oficial más reciente automático.');
    expect(str_contains($payload['motivo'],'14:00'),'Se conserva el rango de cámaras.');
    expect($payload['comisaria_id']===null,'Comisaría opcional se guarda como NULL.');
    foreach ([[0,null],[0,(int)$chief['id']],[(int)$case['comisaria_id'],null]] as [$station,$manager]) {
        $invalid=false;
        try {$repository->validateGestion($station,$manager,(int)$case['id']);}catch(InvalidArgumentException $e){$invalid=true;}
        expect($invalid,'Vincular expediente requiere ambos filtros.');
    }
    expect(str_contains($case['label'],'Fecha:') && str_contains($case['label'],'Lugar:') && str_contains($case['label'],'Tipo:'),'Selector informa fecha, lugar y tipo.');
    $repository->validateGestion((int)$case['comisaria_id'],(int)$chief['id'],(int)$case['id']);
    $differentStation=current(array_filter($ctx['comisarias'],fn($c)=>(int)$c['id']!==(int)$case['comisaria_id']));
    $invalid=false;
    try {$repository->validateGestion((int)$differentStation['id'],(int)$chief['id'],(int)$case['id']);}catch(InvalidArgumentException $e){$invalid=true;}
    expect($invalid,'Ambos filtros deben coincidir con el expediente.');
    $id=$repository->create($payload);
    $row=$repository->find($id);
    expect((int)$row['creado_por']===(int)$creator['id'],'Autoría real de sesión.');
    expect($service->canEdit($row),'Registrante puede editar.');
    expect($service->nextNumero((int)$payload['anio'])===(int)$payload['numero']+1,'Correlativo compartido avanza.');
    $p->prepare('UPDATE oficios SET encargado_id=? WHERE id=?')->execute([$chief['id'],$id]);
    $row=$repository->find($id);
    expect(json_decode($row['responsable_documento'],true)['nombre']===$chief['nombre'],'Encargado se incorpora al documento.');
    $p->prepare('UPDATE oficios SET accidente_id=?,comisaria_id=? WHERE id=?')->execute([$case['id'],$case['comisaria_id'],$id]);
    $denied=false;
    try {$p->prepare('UPDATE oficios SET comisaria_id=? WHERE id=?')->execute([$differentStation['id'],$id]);}catch(PDOException $e){$denied=$e->getCode()==='45000';}
    expect($denied,'La base de datos exige coincidencia de comisaría y encargado.');
    $_SESSION['user']=$other;
    $p->exec('SET @actor_id='.(int)$other['id']);
    expect(!$service->canEdit($row),'Otro registrante no puede editar.');
    $denied=false;
    try {$p->prepare('UPDATE oficios SET motivo=? WHERE id=?')->execute(['No autorizado',$id]);} catch(PDOException $e) {$denied=$e->getCode()==='45000';}
    expect($denied,'La base de datos rechaza la edición no autorizada.');
    $otherChief = current(array_filter($actors, fn($u)=>$u['rol']==='jefe_emi' && (int)$u['id']!==(int)$chief['id']));
    expect((bool)$otherChief, 'Se requiere otro JEFE EMI para verificar aislamiento.');
    $_SESSION['user']=$otherChief;
    $p->exec('SET @actor_id='.(int)$otherChief['id']);
    expect(!$service->canEdit($repository->find($id)), 'JEFE EMI ajeno no puede editar.');
    foreach (['update','changeEstado'] as $operation) {
        $denied=false;
        try { if ($operation==='update') $service->update($id,[]); else $service->changeEstado($id,'ENVIADO'); }
        catch (InvalidArgumentException $e) { $denied=true; }
        expect($denied, 'Servicio rechaza '.$operation.' del JEFE EMI ajeno.');
    }
    $denied=false;
    try {$p->prepare('UPDATE oficios SET motivo=? WHERE id=?')->execute(['JEFE ajeno',$id]);} catch(PDOException $e) {$denied=$e->getCode()==='45000';}
    expect($denied, 'SQL rechaza al JEFE EMI ajeno.');
    $_SESSION['user']=$chief;
    $p->exec('SET @actor_id='.(int)$chief['id']);
    expect($service->canEdit($row),'Encargado asignado puede editar.');
    $p->prepare('UPDATE oficios SET motivo=? WHERE id=?')->execute(['Edición JEFE EMI',$id]);
    $_SESSION['user']=$creator;
    $p->exec('SET @actor_id='.(int)$creator['id']);
    $invalid=false;
    try {$repository->validateGestion((int)$case['comisaria_id'],(int)$other['id'],0);}catch(InvalidArgumentException $e){$invalid=true;}
    expect($invalid,'Se rechaza un encargado sin rol JEFE EMI.');
    $invalid=false;
    try {$repository->validateGestion(-1,null,0);}catch(InvalidArgumentException $e){$invalid=true;}
    expect($invalid,'Se rechaza comisaría inválida.');
    $input['plantilla_nombre']='Protocolo de necropsia'; $input['asunto_id']='';
    $invalid=false;
    try {(new ReflectionMethod($service,'payload'))->invoke($service,$input,null);}catch(InvalidArgumentException $e){$invalid=str_contains($e->getMessage(),'fallecida');}
    expect($invalid,'Necropsia exige seleccionar occiso.');
    $recipientInput=$input;
    $recipientInput['plantilla_nombre']=$asunto['nombre'];
    $recipientInput['asunto_id']=$asunto['id'];
    $recipientInput['numero_oficio']=$service->nextNumero((int)date('Y'));
    $recipientInput['entidad_id']='';
    $recipientInput['entidad_nombre']='Entidad prueba reutilizable '.bin2hex(random_bytes(4));
    $recipientInput['grado_cargo_nombre']='Cargo prueba reutilizable '.bin2hex(random_bytes(4));
    $recipientPayload=(new ReflectionMethod($service,'payload'))->invoke($service,$recipientInput,null);
    $recipientPayload=$repository->resolveRecipientValues($recipientPayload);
    expect($recipientPayload['entidad_id_destino']>0 && $recipientPayload['grado_cargo_id']>0,'Valores nuevos se agregan al catálogo.');
    $again=$repository->resolveRecipientValues($recipientPayload);
    expect($again['entidad_id_destino']===$recipientPayload['entidad_id_destino'] && $again['grado_cargo_id']===$recipientPayload['grado_cargo_id'],'Valores escritos nuevamente se reutilizan sin duplicar.');
    $matchedPayload=(new ReflectionMethod($service,'payload'))->invoke($service,$recipientInput,null);
    expect($matchedPayload['entidad_nombre_nueva']==='' && $matchedPayload['grado_cargo_nombre_nuevo']==='','Coincidencias se reconocen desde el formulario.');
    $recipientId=$repository->create($recipientPayload);
    $saved=$repository->find($recipientId);
    expect((int)$saved['entidad_id_destino']===$recipientPayload['entidad_id_destino'] && (int)$saved['grado_cargo_id']===$recipientPayload['grado_cargo_id'],'Oficio guarda ambos valores nuevos.');
    foreach ([['Protocolo de necropsia','Protocolo de necropsia integral'], ['Peritaje técnico de constatación de daños','Peritaje de daños']] as [$requestedName,$usedName]) {
        $templateIds=[];
        foreach ([$requestedName,$usedName] as $name) {
            $st=$p->prepare("INSERT INTO oficio_asunto (entidad_id,tipo,nombre,detalle,activo) VALUES (?,'SOLICITAR',?,'',1)");
            $st->execute([$recipientPayload['entidad_id_destino'],$name]);
            $templateIds[]=(int)$p->lastInsertId();
        }
        $presetPayload=$recipientPayload;
        $presetPayload['numero']=$service->nextNumero((int)date('Y'));
        $presetPayload['asunto_id']=$templateIds[1];
        $presetPayload['motivo']='Contenido del último oficio de prueba';
        $presetPayload['persona_destino_id']=null;
        $presetPayload['persona_destino_manual']='Destinatario de prueba';
        $presetPayload['referencia_texto']='Referencia última utilizada';
        $repository->create($presetPayload);
        $preset=$service->plantillaInfo($templateIds[0]);
        expect($preset['source']==='latest' && $preset['motivo']===$presetPayload['motivo'],'Plantillas equivalentes recuperan el último contenido.');
        expect($preset['entidad_id']===$recipientPayload['entidad_id_destino'] && $preset['grado_cargo_id']===$recipientPayload['grado_cargo_id'] && $preset['persona_destino_manual']==='Destinatario de prueba','Plantilla recupera entidad, grado/cargo y persona destino.');
        expect(!str_contains($preset['grado_cargo_nombre'],'[CARGO]'),'Grado/cargo se rellena sin etiquetas.');
        expect(!array_key_exists('involucrado_persona_id',$preset) && !array_key_exists('involucrado_vehiculo_id',$preset),'La plantilla no arrastra involucrados de otro expediente.');
    }
    $destination=App\Support\OficioSaveResponse::destination($service,$recipientId,0,true,'save');
    expect($destination==='oficios_listar.php','Guardar regresa al listado de oficios.');
    expect(App\Support\OficioSaveResponse::destination($service,$id,(int)$case['id'],true,'save')==='oficios_listar.php','Regresar desde Gestión no filtra aunque haya expediente vinculado.');
    $generalDownload=App\Support\OficioSaveResponse::destination($service,$recipientId,(int)$case['id'],true,'download');
    expect(!str_contains($generalDownload,'accidente_id='),'Descargar desde Gestión no añade filtro del expediente.');
    $destination=App\Support\OficioSaveResponse::destination($service,$recipientId,0,true,'download');
    parse_str((string)parse_url($destination,PHP_URL_QUERY),$query);
    expect(str_starts_with($destination,'oficios_listar.php?') && isset($_SESSION['oficio_pending_downloads'][$query['download_saved']]),'Guardar y descargar regresa al listado con descarga pendiente.');
    $oficio=$repository->find($id);
    $GLOBALS['of']=$oficio;
    $template=new App\Support\ResponsibleTemplateProcessor(dirname(__DIR__).'/plantillas/oficio_camaras.docx');
    expect(count($template->getVariables())>0,'Plantilla Word disponible para Gestión.');
    unset($template,$GLOBALS['of']);
    $_SERVER['REQUEST_METHOD']='GET';
    $_SERVER['REQUEST_URI']='/oficios_editar.php?id='.$id;
    $_SERVER['SCRIPT_NAME']='/oficios_editar.php';
    $_GET=['id'=>$id]; $_POST=[];
    define('UIAT_GESTION_EDIT_ID',$id);
    $pdo=$p;
    ob_start();
    require dirname(__DIR__).'/oficios_nuevo.php';
    $html=ob_get_clean();
    expect(str_contains($html,'Editar Oficio'),'Gestión utiliza el formulario completo al editar.');
    expect(!str_contains($html,'<label for="anio_oficio">') && !str_contains($html,'<label for="oficial_ano_id">'),'Gestión oculta año y nombre oficial.');
    expect(str_contains($html,"&id={$id}"),'Las consultas AJAX conservan el oficio que se edita.');
    echo "PASS: Gestión sin expediente, selección de encargado, correlativo común, cámara y permisos de edición en servicio y base de datos.\n";
} finally {
    $p->rollBack(); $_SESSION=$sessionBefore; $p->exec('SET @actor_id=0');
}
