<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
foreach(['OAuthCore','CaseQueries','CameraDrafts','Consent','SessionBridge','McpKernel','HttpKernel'] as $class)require dirname(__DIR__).'/src/'.$class.'.php';
use Uiat\Connector\{Actor,Users,State,OAuthCore,CaseQueries,CameraDraftStore,CameraDraftRenderer,CameraDrafts,Audit,McpKernel,HttpKernel,Consent,SessionBridge};
use Nyholm\Psr7\ServerRequest;
$count=0;
function test(bool $ok,string $message):void {global $count;if(!$ok)throw new RuntimeException($message);$count++;}
function rejects(callable $fn,string $message):void {try{$fn();}catch(Throwable $e){test(true,$message);return;}throw new RuntimeException('Rechazo esperado: '.$message);}
function wipe(string $path):void {if(is_link($path)||is_file($path)){unlink($path);return;}if(!is_dir($path))return;foreach(scandir($path) as $p)if($p!=='.'&&$p!=='..')wipe($path.'/'.$p);rmdir($path);}
umask(0077);$dir=sys_get_temp_dir().'/uiat-camera-test-'.bin2hex(random_bytes(6));mkdir($dir,0700);mkdir($dir.'/drafts',0700);
try {
 $admin=new PDO('sqlite:'.$dir.'/data.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $admin->exec("CREATE TABLE usuarios(id INT,rol TEXT,activo INT,must_change_password INT,auth_version INT);
 INSERT INTO usuarios VALUES(10,'jefe_emi',1,0,4),(11,'adjunto',1,0,4),(12,'jefe_emi',1,0,4);
 CREATE TABLE accidentes(id INT,sidpol TEXT,registro_sidpol TEXT,fecha_accidente TEXT,estado TEXT,lugar TEXT,responsable_id INT,eliminado_en TEXT);
 INSERT INTO accidentes VALUES(1,'FIC-001','TEST-001','2026-11-01 10:00:00','Pendiente','avenida Ensayo N.º 100',10,NULL),(2,'FIC-002','TEST-002','2026-11-01','Pendiente','SOLO-AJENO',12,NULL),(3,'FIC-003','TEST-003','2026-11-01','Pendiente','ELIMINADO',10,'2026-11-01'),(4,'FIC-004','TEST-004',NULL,'Pendiente',NULL,10,NULL);
 CREATE VIEW accidentes_activos AS SELECT * FROM accidentes WHERE eliminado_en IS NULL;
 CREATE TABLE expediente_colaboradores(accidente_id INT,usuario_id INT,revocado_en TEXT);
 INSERT INTO expediente_colaboradores VALUES(1,11,NULL);
 CREATE TABLE expediente_transferencias(accidente_id INT,destino_id INT,tipo TEXT,estado TEXT);");
 $read=new PDO('sqlite:'.$dir.'/data.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$read->exec('PRAGMA query_only=ON');
 $users=new Users($read);$queries=new CaseQueries($read);$store=new CameraDraftStore($dir.'/drafts');
 $template=dirname(__DIR__).'/assets/camera-draft-template.docx';$renderer=new CameraDraftRenderer($template,hash_file('sha256',$template));
 $drafts=new CameraDrafts($queries,$users,$store,$renderer,'https://uiat.example.invalid/oauth/borrador');$actor=$users->active(10);$other=$users->active(11);
 $fields=['expediente_id'=>1,'nombre_oficial_ano'=>'Año ficticio de prueba 2026','numero_oficio'=>'018-B','destinatario_cargo'=>'Responsable de Videovigilancia','destinatario_nombre'=>'Destinatario de prueba','entidad_destino'=>'Entidad de Prueba',
 'modalidad_confirmada'=>'colisión ficticia','consecuencia_confirmada'=>'daños materiales ficticios','referencia_confirmada'=>'frente al Parque de Prueba','fiscalia_confirmada'=>'la Fiscalía de Prueba','localidad_emision'=>'Ciudad de Prueba','fecha_emision'=>'2026-11-02','rango_desde'=>'09:30','rango_hasta'=>'10:30'];
 $missing=$drafts->prepare($actor,1,['expediente_id'=>1]);test($missing['estado']==='faltan_datos'&&count($missing['campos_por_completar'])===12,'pide datos sin inventar');
 test($missing['datos_registrados']['lugar']==='avenida Ensayo N.º 100','lugar obtenido con alcance');
 rejects(fn()=>$drafts->prepare($actor,2,['expediente_id'=>2]),'expediente ajeno');rejects(fn()=>$drafts->prepare($actor,3,['expediente_id'=>3]),'eliminado');
 rejects(fn()=>$drafts->prepare($actor,1,$fields+['usuario_id'=>99]),'identidad inyectada');rejects(fn()=>$drafts->prepare($actor,1,$fields+['template'=>'../../secret']), 'ruta inyectada');
 rejects(fn()=>$drafts->prepare($actor,1,$fields+['lugar_aportado'=>'otro sitio']), 'no sobrescribir dato registrado');
 rejects(fn()=>$drafts->prepare($actor,1,array_replace($fields,['fecha_emision'=>'2026-02-30'])),'fecha imposible');
 rejects(fn()=>$drafts->prepare($actor,1,array_replace($fields,['fecha_emision'=>'2025-01-01'])),'emisión anterior');
 rejects(fn()=>$drafts->prepare($actor,1,array_replace($fields,['rango_hasta'=>'09:00'])),'horario decreciente');
 rejects(fn()=>$drafts->prepare($actor,1,array_replace($fields,['rango_desde'=>'29:00'])),'horario imposible');
 rejects(fn()=>$drafts->prepare($actor,1,array_replace($fields,['entidad_destino'=>['invalido']])),'tipo inválido');
 rejects(fn()=>$drafts->prepare($actor,1,array_replace($fields,['entidad_destino'=>"Texto\x01"])), 'control XML');
 rejects(fn()=>$drafts->prepare($actor,1,array_replace($fields,['entidad_destino'=>'${inyeccion}'])), 'marcador inyectado');
 $m=$drafts->prepare($actor,4,['expediente_id'=>4]);test(in_array('lugar_aportado',array_column($m['campos_por_completar'],'campo'))&&in_array('fecha_accidente_aportada',array_column($m['campos_por_completar'],'campo')),'faltantes del expediente se preguntan');
 // New fields are tested using fictional schema only; context is opt-in until metadata/grants are approved.
 $admin->exec("ALTER TABLE accidentes ADD COLUMN referencia TEXT;ALTER TABLE accidentes ADD COLUMN fiscalia_id INT;
 CREATE TABLE fiscalia(id INT,nombre TEXT);INSERT INTO fiscalia VALUES(1,'Fiscalía Ficticia');
 UPDATE accidentes SET referencia='Referencia Ficticia',fiscalia_id=1 WHERE id=1;
 CREATE TABLE modalidad_accidente(id INT,nombre TEXT);INSERT INTO modalidad_accidente VALUES(1,'Colisión ficticia'),(2,'Despiste ficticio');
 CREATE TABLE accidente_modalidad(accidente_id INT,modalidad_id INT,eliminado_en TEXT);
 INSERT INTO accidente_modalidad VALUES(1,1,NULL),(1,2,NULL),(1,1,NULL),(1,2,'2026-01-01'),(2,1,NULL);
 CREATE VIEW accidente_modalidad_activos AS SELECT * FROM accidente_modalidad WHERE eliminado_en IS NULL;
 CREATE TABLE consecuencia_accidente(id INT,nombre TEXT);INSERT INTO consecuencia_accidente VALUES(1,'Daños ficticios'),(2,'CONSECUENCIA ELIMINADA');
 CREATE TABLE accidente_consecuencia(accidente_id INT,consecuencia_id INT,eliminado_en TEXT);
 INSERT INTO accidente_consecuencia VALUES(1,1,NULL),(1,2,'2026-01-01');
 CREATE VIEW accidente_consecuencia_activos AS SELECT * FROM accidente_consecuencia WHERE eliminado_en IS NULL;");
 $autoQueries=new CaseQueries($read,true);$auto=new CameraDrafts($autoQueries,$users,$store,$renderer,'https://uiat.example.invalid/oauth/borrador');
 $autoMissing=$auto->prepare($actor,1,['expediente_id'=>1]);$names=array_column($autoMissing['campos_por_completar'],'campo');
 test(count($names)===8&&!array_intersect($names,['modalidad_confirmada','consecuencia_confirmada','referencia_confirmada','fiscalia_confirmada']),'no pide datos registrados');
 test(in_array('numero_oficio',$names,true),'siempre solicita número');
 test($autoMissing['datos_registrados']['modalidad']==='Colisión ficticia; Despiste ficticio','modalidades múltiples ordenadas sin duplicados');
 test($autoMissing['datos_registrados']['consecuencia']==='Daños ficticios','excluye consecuencias eliminadas');
 rejects(fn()=>$auto->prepare($actor,2,['expediente_id'=>2]),'contexto ajeno rechazado');
 rejects(fn()=>$auto->prepare($actor,1,$fields),'no sobrescribe información del expediente');
 $autoFields=array_diff_key($fields,array_flip(['modalidad_confirmada','consecuencia_confirmada','referencia_confirmada','fiscalia_confirmada']));
 $noNumber=$autoFields;unset($noNumber['numero_oficio']);
 test(array_column($auto->prepare($actor,1,$noNumber)['campos_por_completar'],'campo')===['numero_oficio'],'número nunca se completa automáticamente');
 rejects(fn()=>$auto->prepare($actor,1,array_replace($autoFields,['numero_oficio'=>'sin número'])),'número debe contener dígitos');
 $preview=$auto->prepare($actor,1,$autoFields);test($preview['vista_previa']['numero_aportado']==='018-B'&&$preview['vista_previa']['datos_a_usar']['fiscalia_nombre']==='Fiscalía Ficticia','vista previa usa número manual y fiscalía registrada');
 $auto->discard($preview['confirmacion_id'],$actor);
 $empty=$auto->prepare($actor,4,['expediente_id'=>4]);test(count($empty['campos_por_completar'])===14,'pregunta todos los campos ausentes sin inventar');
 $admin->exec("CREATE TABLE oficio_oficial_ano(anio INT,nombre TEXT);INSERT INTO oficio_oficial_ano VALUES(2025,'Año ficticio 2025'),(2026,'Año ficticio de prueba 2026'),(2027,'Primera denominación ficticia'),(2027,'Segunda denominación ficticia');");
 $yearQueries=new CaseQueries($read,true,true);$yearDrafts=new CameraDrafts($yearQueries,$users,$store,$renderer,'https://uiat.example.invalid/oauth/borrador');
 $yearFields=$autoFields;unset($yearFields['nombre_oficial_ano']);
 $yp=$yearDrafts->prepare($actor,1,$yearFields);test($yp['vista_previa']['datos_a_usar']['nombre_oficial_ano']==='Año ficticio de prueba 2026','año oficial según emisión 2026, recuperado de catálogo');$yearDrafts->discard($yp['confirmacion_id'],$actor);
 $noYear=$yearFields;$noYear['fecha_emision']='2028-11-02';$ym=$yearDrafts->prepare($actor,1,$noYear);test(array_column($ym['campos_por_completar'],'campo')===['nombre_oficial_ano'],'catálogo vacío pregunta, no inventa');
 $noYear['fecha_emision']='2027-11-02';$ym=$yearDrafts->prepare($actor,1,$noYear);test(array_column($ym['campos_por_completar'],'campo')===['nombre_oficial_ano'],'catálogo ambiguo pregunta');
 rejects(fn()=>$yearDrafts->prepare($actor,1,$yearFields+['nombre_oficial_ano'=>'OTRO AÑO']),'no sustituye año registrado');
 $yearFields['fecha_emision']='2025-11-02';$yearFields['fecha_accidente_aportada']='2024-01-01';$yearFields['lugar_aportado']='Lugar de prueba';$yearFields['modalidad_confirmada']='Modalidad de prueba';$yearFields['consecuencia_confirmada']='Consecuencia de prueba';$yearFields['referencia_confirmada']='Referencia de prueba';$yearFields['fiscalia_confirmada']='Fiscalía de prueba';$yearFields['expediente_id']=4;$yp=$yearDrafts->prepare($actor,4,$yearFields);test($yp['vista_previa']['datos_a_usar']['nombre_oficial_ano']==='Año ficticio 2025','año de emisión histórico, no año actual ni del accidente');$yearDrafts->discard($yp['confirmacion_id'],$actor);
 $p=$drafts->prepare($actor,1,$fields);$id=$p['confirmacion_id'];test($p['vista_previa']['datos_a_usar']['oficio_asunto']==='Solicita copia de grabaciones de videovigilancia del día 1 de noviembre de 2026 entre las 09:30 a 10:30 horas.','asunto usa día del hecho y rango, no fecha de emisión');test($p['estado']==='pendiente_confirmacion'&&preg_match('/^[a-f0-9]{64}$/D',$id),'vista previa con nonce');test(!glob($dir.'/drafts/*.docx'),'sin documento antes de confirmar');
 test($p['vista_previa']['numero_oficial']===null&&!$p['vista_previa']['registrado_en_uiat'],'no número ni registro');
 rejects(fn()=>$drafts->prepare($actor,1,['expediente_id'=>1,'confirmacion_id'=>$id,'entidad_destino'=>'CAMBIADA']),'no cambiar contenido al confirmar');
 rejects(fn()=>$drafts->prepare($other,1,['expediente_id'=>1,'confirmacion_id'=>$id]),'confirmación de otro usuario');
 $d=$drafts->prepare($actor,1,['expediente_id'=>1,'confirmacion_id'=>$id]);test($d['estado']==='borrador_generado'&&$d['requiere_sesion_uiat'],'genera después de confirmar');
 test(str_starts_with($d['descarga_url'],'https://uiat.example.invalid/oauth/borrador?archivo='),'URL HTTPS sin datos en ruta');
 rejects(fn()=>$drafts->prepare($actor,1,['expediente_id'=>1,'confirmacion_id'=>$id]),'confirmación no repetible');
 $session=['user'=>['id'=>10,'auth_version'=>4]];$bytes=$drafts->download($id,$session);test(str_starts_with($bytes,'PK'),'descarga Word autenticada');
 rejects(fn()=>$drafts->download($id,[]),'sin sesión');rejects(fn()=>$drafts->download($id,['user'=>['id'=>11,'auth_version'=>4]]),'otro usuario aunque colabore');
 rejects(fn()=>$drafts->download('../../settings',$session),'path traversal');
 $z=new ZipArchive();$z->open($dir.'/drafts/'.$id.'.docx');$text=$z->getFromName('word/document.xml');
 test(str_contains($text,'Año ficticio de prueba 2026'),'nombre del año incluido en Word');
 test(str_contains($text,'del día 1 de noviembre de 2026 entre las 09:30 a 10:30 horas.'),'Word conserva día y horario en asunto');
 test(!str_contains($text,'${')&&str_contains($text,'018-B')&&!str_contains($text,'SIN FIRMA')&&!str_contains($text,'BORRADOR')&&!str_contains($text,'PRUEBA'),'documento limpio sin leyendas ni campos pendientes');
 test(!str_contains($z->getFromName('word/footer1.xml'),'Borrador')&&!str_contains($z->getFromName('word/footer1.xml'),'SIN FIRMA'),'pie limpio');
 test($z->locateName('word/media/image1.png')!==false&&$z->locateName('word/media/image2.png')!==false&&$z->locateName('word/media/image3.png')!==false,'gráficos originales restaurados por solicitud del usuario');$z->close();
 if(getenv('UIAT_CAMERA_FIXTURE_OUTPUT'))file_put_contents(getenv('UIAT_CAMERA_FIXTURE_OUTPUT'),$bytes);
 $admin->exec('UPDATE usuarios SET auth_version=5 WHERE id=10');rejects(fn()=>$drafts->download($id,$session),'versión revocada');$admin->exec('UPDATE usuarios SET auth_version=4,activo=0 WHERE id=10');rejects(fn()=>$drafts->download($id,$session),'usuario desactivado');$admin->exec('UPDATE usuarios SET activo=1 WHERE id=10');
 $p2=$drafts->prepare($other,1,$fields);$otherId=$p2['confirmacion_id'];$drafts->prepare($other,1,['expediente_id'=>1,'confirmacion_id'=>$otherId]);
 $admin->exec("UPDATE expediente_colaboradores SET revocado_en='2026-11-02' WHERE usuario_id=11");rejects(fn()=>$drafts->download($otherId,['user'=>['id'=>11,'auth_version'=>4]]),'colaboración revocada al descargar');$admin->exec('UPDATE expediente_colaboradores SET revocado_en=NULL');
 // XML is escaped as text, not executed or inserted as elements.
 $special=$drafts->prepare($actor,1,array_replace($fields,['destinatario_nombre'=>'Ensayo & Cía. <prueba>']));$sid=$special['confirmacion_id'];$drafts->prepare($actor,1,['expediente_id'=>1,'confirmacion_id'=>$sid]);
 $z=new ZipArchive();$z->open($dir.'/drafts/'.$sid.'.docx');$dom=new DOMDocument();test($dom->loadXML($z->getFromName('word/document.xml'))&&str_contains($dom->textContent,'Ensayo & Cía. <prueba>')&&$dom->getElementsByTagName('prueba')->length===0,'XML escapado y texto íntegro');$z->close();
 $meta=$dir.'/drafts/'.$id.'.json';$record=json_decode(file_get_contents($meta),true);$record['expires']=time()-1;file_put_contents($meta,json_encode($record));rejects(fn()=>$drafts->download($id,$session),'caducidad');test(!file_exists($meta)&&!file_exists($dir.'/drafts/'.$id.'.docx'),'limpieza de caducados');
 rejects(fn()=>new CameraDraftRenderer($template,str_repeat('0',64)),'plantilla no verificada');
 $link=str_repeat('a',64);symlink($template,$dir.'/drafts/'.$link.'.json');rejects(fn()=>$drafts->prepare($actor,1,$fields),'rechaza enlaces simbólicos');unlink($dir.'/drafts/'.$link.'.json');test(is_file($template),'no elimina objetivo de enlace');
 // MCP, real OAuth token validation and native HTTP download adapter.
 $state=new State(new PDO('sqlite::memory:'),$users);$state->createSchema();$key=openssl_pkey_new(['private_key_bits'=>2048]);openssl_pkey_export($key,$pem);$pub=openssl_pkey_get_details($key)['key'];
 $core=new OAuthCore($state,'https://uiat.example.invalid/oauth','https://uiat.example.invalid/mcp','fixture-client','https://chatgpt.com/connector_platform_oauth_redirect',$pem,$pub,bin2hex(random_bytes(32)));
 $proof=bin2hex(random_bytes(32));$query=['response_type'=>'code','client_id'=>'fixture-client','redirect_uri'=>'https://chatgpt.com/connector_platform_oauth_redirect','scope'=>'uiat:read','state'=>bin2hex(random_bytes(16)),'resource'=>'https://uiat.example.invalid/mcp','code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$proof,true)),'+/','-_'),'='),'code_challenge_method'=>'S256'];
 $redirect=$core->authorize((new ServerRequest('GET','https://uiat.example.invalid/oauth/authorize'))->withQueryParams($query),$actor,true);parse_str(parse_url($redirect->getHeaderLine('Location'),PHP_URL_QUERY),$codes);
 $response=$core->exchange((new ServerRequest('POST','https://uiat.example.invalid/oauth/token'))->withParsedBody(['grant_type'=>'authorization_code','code'=>$codes['code'],'client_id'=>'fixture-client','redirect_uri'=>$query['redirect_uri'],'resource'=>$query['resource'],'code_verifier'=>$proof]));$token=json_decode((string)$response->getBody(),true)['access_token'];
 $audit=new Audit($dir.'/audit.jsonl');$mcp=new McpKernel($core,$queries,$audit,'https://uiat.example.invalid/meta',$drafts);
 test(count($mcp->tools())===7&&$mcp->tools()[6]['annotations']['readOnlyHint']===false,'herramienta de archivos declarada sin efectos en negocio');
 test(count((new McpKernel($core,$queries,$audit,'https://uiat.example.invalid/meta'))->tools())===6,'deshabilitada por defecto');
 $call=function(array $args,?string $bearer)use($mcp){$r=(new ServerRequest('POST','https://uiat.example.invalid/mcp'))->withBody(Nyholm\Psr7\Stream::create(json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>'preparar_borrador_camaras','arguments'=>$args]])));if($bearer)$r=$r->withHeader('Authorization','Bearer '.$bearer);return $mcp->handle($r);};
 test($call($fields,null)->getStatusCode()===401,'MCP exige OAuth');$r=$call(['expediente_id'=>2],$token);test(json_decode((string)$r->getBody(),true)['result']['isError']===true,'MCP rechaza ajeno sin datos');
 $r=$call($fields,$token);$j=json_decode((string)$r->getBody(),true);test($j['result']['structuredContent']['datos']['estado']==='pendiente_confirmacion','MCP prepara vista previa');$mid=$j['result']['structuredContent']['datos']['confirmacion_id'];
 $r=$call(['expediente_id'=>1,'confirmacion_id'=>$mid],$token);test(!json_decode((string)$r->getBody(),true)['result']['isError'],'MCP genera Word');
 $bridge=new SessionBridge($users,'https://uiat.example.invalid/oauth',static function(){},static function(){},static function(){});
 $http=new HttpKernel($core,new Consent($core,$users,'https://uiat.example.invalid/oauth/authorize'),$mcp,'https://uiat.example.invalid/oauth','https://uiat.example.invalid/mcp',static function(){},$audit,$bridge,$drafts);
 $_SESSION=$session;$r=$http->handle((new ServerRequest('GET','https://uiat.example.invalid/oauth/borrador'))->withQueryParams(['archivo'=>$mid]));test($r->getStatusCode()===200&&str_contains($r->getHeaderLine('Content-Disposition'),'attachment')&&$r->getHeaderLine('Cache-Control')==='no-store, private','descarga HTTP privada como adjunto');
 $_SESSION=[];$r=$http->handle((new ServerRequest('GET','https://uiat.example.invalid/oauth/borrador'))->withQueryParams(['archivo'=>$mid]));test($r->getStatusCode()===404&&!str_starts_with((string)$r->getBody(),'PK'),'descarga HTTP sin sesión rechazada');
 $auditText=file_get_contents($dir.'/audit.jsonl');test(!str_contains($auditText,'avenida')&&!str_contains($auditText,'Destinatario')&&!str_contains($auditText,$token)&&!str_contains($auditText,$mid),'auditoría sin documento, argumentos ni identificadores de descarga');
 // Fail closed and remove unreturned previews/files if audit cannot be written.
 $before=count(glob($dir.'/drafts/*.json'));unlink($dir.'/audit.jsonl');mkdir($dir.'/audit.jsonl',0700);
 test($call($fields,$token)->getStatusCode()===503,'fallo de auditoría impide entregar vista previa');test(count(glob($dir.'/drafts/*.json'))===$before,'limpia vista previa no entregada');rmdir($dir.'/audit.jsonl');
 $r=$call($fields,$token);$j=json_decode((string)$r->getBody(),true);$failId=$j['result']['structuredContent']['datos']['confirmacion_id'];
 unlink($dir.'/audit.jsonl');mkdir($dir.'/audit.jsonl',0700);
 test($call(['expediente_id'=>1,'confirmacion_id'=>$failId],$token)->getStatusCode()===503,'no entrega documento si falla auditoría');
 test(!file_exists($dir.'/drafts/'.$failId.'.json')&&!file_exists($dir.'/drafts/'.$failId.'.docx'),'limpia documento no entregado tras fallo');rmdir($dir.'/audit.jsonl');
 $expired=$drafts->prepare($actor,1,$fields)['confirmacion_id'];$p=$dir.'/drafts/'.$expired.'.json';$d=json_decode(file_get_contents($p),true);$d['expires']=time()-1;file_put_contents($p,json_encode($d));
 rejects(fn()=>$drafts->prepare($actor,1,['expediente_id'=>1,'confirmacion_id'=>$expired]),'vista previa caducada');
 test(!file_exists($p),'limpieza de vista previa caducada');
 $existing=count(array_filter(glob($dir.'/drafts/*.json'),fn($path)=>json_decode(file_get_contents($path),true)['owner']===10));
 for($i=$existing;$i<10;$i++)$drafts->prepare($actor,1,$fields);
 rejects(fn()=>$drafts->prepare($actor,1,$fields),'cuota de diez temporales por usuario');
 foreach(glob($dir.'/drafts/*.json') as $p){$d=json_decode(file_get_contents($p),true);$d['expires']=time()-1;file_put_contents($p,json_encode($d));}
 $store->purgeExpired();test(!glob($dir.'/drafts/*.json')&&!glob($dir.'/drafts/*.docx'),'limpieza programable sin consultas ni usuario');
 // All operations used a read-only data connection; original case/user counts unchanged.
 test((int)$admin->query('SELECT COUNT(*) FROM accidentes')->fetchColumn()===4&&(int)$admin->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===3,'ningún registro de negocio creado');
 echo "$count comprobaciones de borradores correctas. Solo expedientes ficticios; sin conexión a producción.\n";
} finally {wipe($dir);}
