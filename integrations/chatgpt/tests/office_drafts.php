<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
foreach(['OAuthCore','CaseQueries','CameraDrafts','OfficeQueries','OfficeDrafts','Consent','SessionBridge','McpKernel','HttpKernel'] as $class)require dirname(__DIR__).'/src/'.$class.'.php';
use Uiat\Connector\{Users,CaseQueries,CameraDraftStore,CameraDraftRenderer,CameraDrafts,OfficeQueries,OfficeDrafts};
$n=0;function checkOffice(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException($label);$n++;}
function rejectsOffice(callable $f,string $label):void{try{$f();}catch(Throwable $e){checkOffice(true,$label);return;}throw new RuntimeException('Rechazo esperado: '.$label);}
function wipeOffice(string $p):void{if(is_link($p)||is_file($p)){unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p) as $name)if($name!=='.'&&$name!=='..')wipeOffice($p.'/'.$name);rmdir($p);}
function prepareNecropsiaFixture($actor,$case,$args){global $offices;
 if(($args['tipo_oficio']??'')==='protocolo_necropsia'&&!isset($args['confirmacion_id']))$args['campos_aportados']=($args['campos_aportados']??[])+['fecha_internamiento'=>'2000-01-02','fecha_informe_pericial'=>'2000-01-02','referencia_disposicion_fiscal'=>'Disposición Fiscal de prueba.','constancia_adjuntos'=>'Sin anexos para esta muestra.'];
 return $offices->prepare($actor,$case,$args);
}
umask(0077);$tmp=sys_get_temp_dir().'/uiat-offices-'.bin2hex(random_bytes(6));mkdir($tmp,0700);mkdir($tmp.'/drafts',0700);
try{
 $sql=file_get_contents(getenv('UIAT_OFFICE_FIXTURE_SQL')?:__DIR__.'/office_fixture.sql');$admin=new PDO('sqlite:'.$tmp.'/data.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$admin->exec($sql);
 $read=new PDO('sqlite:'.$tmp.'/data.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$read->exec('PRAGMA query_only=ON');$users=new Users($read);$actor=$users->active(10);$cases=new CaseQueries($read,true,true);$queries=new OfficeQueries($read,$cases);$store=new CameraDraftStore($tmp.'/drafts');$assets=dirname(__DIR__).'/assets';
 $camera=new CameraDrafts($cases,$users,$store,new CameraDraftRenderer($assets.'/camera-draft-template.docx',hash_file('sha256',$assets.'/camera-draft-template.docx')),'https://uiat.example.invalid/oauth/borrador');
 $offices=new OfficeDrafts($queries,$cases,$camera,$store,$assets,hash_file('sha256',$assets.'/office-catalog.json'),'https://uiat.example.invalid/oauth/borrador');
 checkOffice(count($offices->catalog()['tipos'])===10,'diez tipos');
 $base=['expediente_id'=>1,'numero_oficio'=>'0018-A','fecha_emision'=>'2026-11-02','localidad_emision'=>'Ciudad Ficticia','destinatario_cargo'=>'Cargo Ficticio','destinatario_nombre'=>'Destinatario Ficticio','entidad_destino'=>'Entidad Ficticia'];
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'../../settings']),'tipo cerrado');
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,2,array_replace($base,['expediente_id'=>2,'tipo_oficio'=>'peritaje'])),'expediente ajeno');
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,3,array_replace($base,['expediente_id'=>3,'tipo_oficio'=>'peritaje'])),'expediente eliminado');
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'peritaje','usuario_id'=>99]),'identidad inyectada');
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'peritaje','vehiculo_registro_id'=>21]),'vehículo de otro expediente');
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'protocolo_necropsia','persona_registro_id'=>2]),'persona de otro expediente');
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'protocolo_necropsia','persona_registro_id'=>1]),'no elegir ileso como fallecido');
 $selection=prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'peritaje']);checkOffice(in_array('vehiculo_registro_id',array_column($selection['campos_por_completar'],'campo'),true),'pide vehículo si hay varios');checkOffice(count($selection['opciones']['vehiculos']['registros'])===2,'excluye vehículo eliminado y ajeno');
 $selection=prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'protocolo_necropsia']);checkOffice(count($selection['opciones']['personas']['registros'])===2,'pide elegir fallecido');
 $docs=$queries->pericial($actor,1,3,null);checkOffice(count($docs)===1&&$docs[0]['numero_pericial']==='PER-TEST-001','pericial exige accidente Y persona, sin OR');
 rejectsOffice(fn()=>$queries->pericial($actor,1,3,2),'pericial cruzado rechazado');
 $reniec=$queries->reniec($actor,1,4,null);checkOffice(($reniec['oficio_id']??0)===1,'origen RENIEC exige caso y persona correctos, sin eliminado');
 rejectsOffice(fn()=>$queries->reniec($actor,1,4,3),'RENIEC de expediente ajeno rechazado');
 $previewIds=[];$generated=[];
 foreach($offices->catalog()['tipos'] as $type){
  $kind=$type['tipo'];$args=$base+['tipo_oficio'=>$kind];
  if(in_array($kind,['peritaje','certificado_uper','historial_sunarp'],true))$args['vehiculo_registro_id']=11;
  if(in_array($kind,['protocolo_necropsia','informe_medico','defuncion_reniec'],true))$args['persona_registro_id']=4;
  for($attempt=0;$attempt<4;$attempt++){
   $result=prepareNecropsiaFixture($actor,1,$args);
   if($result['estado']==='pendiente_confirmacion')break;
   foreach($result['campos_por_completar'] as $question){$key=$question['campo'];
    if(str_starts_with($key,'campos_aportados.'))$args['campos_aportados'][substr($key,17)]='Dato ficticio '.substr($key,17);
    elseif(in_array($key,CameraDrafts::FIELDS?array_keys(CameraDrafts::FIELDS):[],true)&&!in_array($key,OfficeDrafts::COMMON,true))$args['campos_aportados'][$key]=match($key){'rango_desde'=>'09:30','rango_hasta'=>'10:30',default=>'Dato ficticio '.$key};
    else throw new RuntimeException('Selección inesperada: '.$key.' para '.$kind);
   }
  }
  checkOffice($result['estado']==='pendiente_confirmacion','vista previa '.$kind);$id=$result['confirmacion_id'];$previewIds[$kind]=$id;
  if($kind==='protocolo_necropsia')checkOffice($result['vista_previa']['datos_a_usar']['numero_pericial']==='PER-TEST-001','protocolo automático');
  if($kind==='remitir_informe_policial')checkOffice(str_contains($result['vista_previa']['datos_a_usar']['motivo'],'IP-TEST-001'),'informe desde expediente');
  $ready=prepareNecropsiaFixture($actor,1,['tipo_oficio'=>$kind,'expediente_id'=>1,'confirmacion_id'=>$id]);checkOffice(in_array($ready['estado'],['documento_generado','borrador_generado'],true),'genera '.$kind);
  $bytes=$camera->download($ready['borrador_id'],['user'=>['id'=>10,'auth_version'=>4]]);checkOffice(str_starts_with($bytes,'PK'),'Word descargable '.$kind);
  $path=$tmp.'/drafts/'.$ready['borrador_id'].'.docx';$zip=new ZipArchive();$zip->open($path);$xml=$zip->getFromName('word/document.xml');checkOffice(!str_contains($xml,'${')&&str_contains($xml,'0018-A'),'número manual sin campos pendientes '.$kind);$zip->close();
  if(getenv('UIAT_OFFICE_OUTPUT_DIR')){if(!is_dir(getenv('UIAT_OFFICE_OUTPUT_DIR')))mkdir(getenv('UIAT_OFFICE_OUTPUT_DIR'),0700,true);file_put_contents(getenv('UIAT_OFFICE_OUTPUT_DIR').'/'.$kind.'.docx',$bytes);}
  $generated[]=$kind;
  rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,['tipo_oficio'=>$kind,'expediente_id'=>1,'confirmacion_id'=>$id]),'confirmación no repetible '.$kind);
  $store->discard($id,$actor);
 }
 $p=prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'protocolo_necropsia','persona_registro_id'=>4]);$id=$p['confirmacion_id'];
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,['tipo_oficio'=>'informe_medico','expediente_id'=>1,'confirmacion_id'=>$id]),'no cambiar tipo al confirmar');
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,['tipo_oficio'=>'camaras','expediente_id'=>1,'confirmacion_id'=>$id]),'no pasar confirmación general a cámaras');
 rejectsOffice(fn()=>prepareNecropsiaFixture($users->active(11),1,['tipo_oficio'=>'protocolo_necropsia','expediente_id'=>1,'confirmacion_id'=>$id]),'otro usuario no confirma');
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'protocolo_necropsia','persona_registro_id'=>4,'campos_aportados'=>['fallecido_nombres'=>'CAMBIO']]),'no cambiar nombre registrado');
 rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'peritaje','campos_aportados'=>['template'=>'/etc/passwd']]),'no admitir plantilla arbitraria');
 $noNumber=$base;unset($noNumber['numero_oficio']);$missing=prepareNecropsiaFixture($actor,1,$noNumber+['tipo_oficio'=>'protocolo_necropsia','persona_registro_id'=>4]);checkOffice(in_array('numero_oficio',array_column($missing['campos_por_completar'],'campo'),true),'siempre pide número');
 $pending=prepareNecropsiaFixture($actor,1,$base+['tipo_oficio'=>'protocolo_necropsia','persona_registro_id'=>4]);$pid=$pending['confirmacion_id'];
 $admin->exec("UPDATE involucrados_personas SET lesion='Ileso' WHERE id=4");rejectsOffice(fn()=>prepareNecropsiaFixture($actor,1,['tipo_oficio'=>'protocolo_necropsia','expediente_id'=>1,'confirmacion_id'=>$pid]),'persona deja de cumplir condición antes de confirmar');$admin->exec("UPDATE involucrados_personas SET lesion='Fallecido' WHERE id=4");$store->discard($pid,$actor);
 $state=new \Uiat\Connector\State(new PDO('sqlite::memory:'),$users);$state->createSchema();$key=openssl_pkey_new(['private_key_bits'=>2048]);openssl_pkey_export($key,$pem);$pub=openssl_pkey_get_details($key)['key'];
 $core=new \Uiat\Connector\OAuthCore($state,'https://uiat.example.invalid/oauth','https://uiat.example.invalid/mcp','fixture-client','https://chatgpt.com/connector_platform_oauth_redirect',$pem,$pub,bin2hex(random_bytes(32)));
 $proof=bin2hex(random_bytes(32));$params=['response_type'=>'code','client_id'=>'fixture-client','redirect_uri'=>'https://chatgpt.com/connector_platform_oauth_redirect','scope'=>'uiat:read','state'=>bin2hex(random_bytes(16)),'resource'=>'https://uiat.example.invalid/mcp','code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$proof,true)),'+/','-_'),'='),'code_challenge_method'=>'S256'];
 $redirect=$core->authorize((new \Nyholm\Psr7\ServerRequest('GET','https://uiat.example.invalid/oauth/authorize'))->withQueryParams($params),$actor,true);parse_str(parse_url($redirect->getHeaderLine('Location'),PHP_URL_QUERY),$codes);
 $response=$core->exchange((new \Nyholm\Psr7\ServerRequest('POST','https://uiat.example.invalid/oauth/token'))->withParsedBody(['grant_type'=>'authorization_code','code'=>$codes['code'],'client_id'=>'fixture-client','redirect_uri'=>$params['redirect_uri'],'resource'=>$params['resource'],'code_verifier'=>$proof]));$token=json_decode((string)$response->getBody(),true)['access_token'];
 $audit=new \Uiat\Connector\Audit($tmp.'/audit.jsonl');$mcp=new \Uiat\Connector\McpKernel($core,$cases,$audit,'https://uiat.example.invalid/meta',$camera,$offices);checkOffice(count($mcp->tools())===9,'nueve herramientas con catálogo y generador');
 $call=function(string $name,array $arguments,?string $bearer)use($mcp){$r=new \Nyholm\Psr7\ServerRequest('POST','https://uiat.example.invalid/mcp');$r=$r->withBody(\Nyholm\Psr7\Stream::create(json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>['name'=>$name,'arguments'=>$arguments]])));if($bearer)$r=$r->withHeader('Authorization','Bearer '.$bearer);return $mcp->handle($r);};
 checkOffice($call('listar_tipos_oficio',[],null)->getStatusCode()===401,'catálogo exige OAuth');$r=$call('listar_tipos_oficio',[],$token);$data=json_decode((string)$r->getBody(),true);checkOffice(count($data['result']['structuredContent']['datos']['tipos'])===10,'catálogo MCP funcional');
 $r=$call('preparar_oficio',$base+['tipo_oficio'=>'protocolo_necropsia','persona_registro_id'=>4,'campos_aportados'=>['fecha_internamiento'=>'2000-01-02','fecha_informe_pericial'=>'2000-01-02','referencia_disposicion_fiscal'=>'Disposición Fiscal de prueba.','constancia_adjuntos'=>'Sin anexos para esta muestra.']],$token);$data=json_decode((string)$r->getBody(),true);checkOffice($data['result']['structuredContent']['datos']['estado']==='pendiente_confirmacion','vista previa MCP general');$mid=$data['result']['structuredContent']['datos']['confirmacion_id'];
 $r=$call('preparar_oficio',['tipo_oficio'=>'protocolo_necropsia','expediente_id'=>1,'confirmacion_id'=>$mid],$token);checkOffice(json_decode((string)$r->getBody(),true)['result']['structuredContent']['datos']['estado']==='documento_generado','generación MCP general');
 $log=file_get_contents($tmp.'/audit.jsonl');checkOffice(!str_contains($log,$token)&&!str_contains($log,$mid)&&!str_contains($log,'Persona Ficticia')&&!str_contains($log,'PER-TEST'),'auditoría sin datos personales ni documento');
 unlink($tmp.'/audit.jsonl');mkdir($tmp.'/audit.jsonl',0700);
 $before=count(glob($tmp.'/drafts/*.json'));$r=$call('preparar_oficio',$base+['tipo_oficio'=>'protocolo_necropsia','persona_registro_id'=>4,'campos_aportados'=>['fecha_internamiento'=>'2000-01-02','fecha_informe_pericial'=>'2000-01-02','referencia_disposicion_fiscal'=>'Disposición Fiscal de prueba.','constancia_adjuntos'=>'Sin anexos para esta muestra.']],$token);checkOffice($r->getStatusCode()===503&&count(glob($tmp.'/drafts/*.json'))===$before,'fallo de auditoría retira vista previa general');rmdir($tmp.'/audit.jsonl');
 checkOffice((int)$admin->query('SELECT COUNT(*) FROM oficios')->fetchColumn()===4,'ningún oficio registrado');
 echo "$n comprobaciones de generador general correctas; diez tipos con expedientes ficticios.\n";
}finally{wipeOffice($tmp);}
