<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
foreach(['OAuthCore','CaseQueries','CameraDrafts','OfficeQueries','OfficeDrafts','Consent','SessionBridge','McpKernel','HttpKernel'] as $class)require dirname(__DIR__).'/src/'.$class.'.php';
use Uiat\Connector\{Users,CaseQueries,CameraDraftStore,CameraDraftRenderer,CameraDrafts,OfficeQueries,OfficeDrafts};
$n=0;function checkOffice(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException($label);$n++;}
function rejectsOffice(callable $f,string $label):void{try{$f();}catch(Throwable $e){checkOffice(true,$label);return;}throw new RuntimeException('Rechazo esperado: '.$label);}
function wipeOffice(string $p):void{if(is_link($p)||is_file($p)){unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p) as $name)if($name!=='.'&&$name!=='..')wipeOffice($p.'/'.$name);rmdir($p);}
umask(0077);$tmp=sys_get_temp_dir().'/uiat-offices-'.bin2hex(random_bytes(6));mkdir($tmp,0700);mkdir($tmp.'/drafts',0700);
try{
 $sql=file_get_contents(getenv('UIAT_OFFICE_FIXTURE_SQL')?:__DIR__.'/office_fixture.sql');$admin=new PDO('sqlite:'.$tmp.'/data.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$admin->exec($sql);
 $read=new PDO('sqlite:'.$tmp.'/data.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$read->exec('PRAGMA query_only=ON');$users=new Users($read);$actor=$users->active(10);$cases=new CaseQueries($read,true,true);$queries=new OfficeQueries($read,$cases);$store=new CameraDraftStore($tmp.'/drafts');$assets=dirname(__DIR__).'/assets';
 $camera=new CameraDrafts($cases,$users,$store,new CameraDraftRenderer($assets.'/camera-draft-template.docx',hash_file('sha256',$assets.'/camera-draft-template.docx')),'https://uiat.example.invalid/oauth/borrador');
 $offices=new OfficeDrafts($queries,$cases,$camera,$store,$assets,hash_file('sha256',$assets.'/office-catalog.json'),'https://uiat.example.invalid/oauth/borrador');

 $base=['tipo_oficio'=>'protocolo_necropsia','expediente_id'=>1,'persona_registro_id'=>4,'numero_oficio'=>'0000','fecha_emision'=>'2026-11-03','localidad_emision'=>'Santa Rosa','destinatario_cargo'=>'Sub-Gerente','destinatario_nombre'=>'Destinatario de prueba','entidad_destino'=>'División de Tanatología Forense – Entidad ficticia'];
 $pending=$offices->prepare($actor,1,$base);
 checkOffice($pending['estado']==='faltan_datos','no genera sin fechas y referencia fiscal');
 $missing=array_column($pending['campos_por_completar'],'campo');
 foreach(['fecha_internamiento','fecha_informe_pericial','referencia_disposicion_fiscal','constancia_adjuntos'] as $field)checkOffice(in_array('campos_aportados.'.$field,$missing,true),'campo solicitado '.$field);
 $extra=['fecha_internamiento'=>'2000-01-02','fecha_informe_pericial'=>'2000-01-02','referencia_disposicion_fiscal'=>'Disposición Fiscal de prueba.','constancia_adjuntos'=>'Sin anexos para esta muestra.'];
 $args=$base+['campos_aportados'=>$extra];
 $preview=$offices->prepare($actor,1,$args);checkOffice($preview['estado']==='pendiente_confirmacion','vista previa completa');
 checkOffice($preview['vista_previa']['datos_a_usar']['fecha_internamiento']==='02ENE2000','fecha válida de internamiento separada');
 $bad=$args;$bad['campos_aportados']['fecha_internamiento']='2026-02-30';rejectsOffice(fn()=>$offices->prepare($actor,1,$bad),'fecha inválida rechazada');
 $bad=$args;$bad['campos_aportados']['fecha_informe_pericial']='2027-01-01';rejectsOffice(fn()=>$offices->prepare($actor,1,$bad),'informe posterior a emisión rechazado');
 $bad=$args;$bad['expediente_id']=2;rejectsOffice(fn()=>$offices->prepare($actor,2,$bad),'caso ajeno rechazado');
 $bad=$args;$bad['campos_aportados']['numero_pericial']='OTRO';rejectsOffice(fn()=>$offices->prepare($actor,1,$bad),'no sobrescribe pericial registrado');
 $ready=$offices->prepare($actor,1,['tipo_oficio'=>'protocolo_necropsia','expediente_id'=>1,'confirmacion_id'=>$preview['confirmacion_id']]);
 $bytes=$camera->download($ready['borrador_id'],['user'=>['id'=>10,'auth_version'=>4]]);
 $out=getenv('UIAT_NECROPSIA_SAMPLE')?:$tmp.'/necropsia-ficticia.docx';file_put_contents($out,$bytes);$z=new ZipArchive();$z->open($out);$xml=$z->getFromName('word/document.xml');$text=html_entity_decode(strip_tags($xml),ENT_QUOTES|ENT_XML1,'UTF-8');
 foreach(['CARÁCTER DE MUY URGENTE','Diagnostico Integrado, Fluidos y exámenes auxiliares','Disposición Fiscal de prueba.','Informe Pericial N.º PER-TEST-001','Sin anexos para esta muestra.','02ENE2000'] as $piece)checkOffice(str_contains($text,$piece),'texto original y datos '.$piece);
 checkOffice(!str_contains($xml,'<w:tbl>'),'sin tabla de sellos');checkOffice(!str_contains($xml,'${'),'sin marcadores');checkOffice(!str_contains($text,'Registro SIDPOL N.º'),'no sustituye disposición fiscal por SIDPOL');
 checkOffice(!str_contains($text,'Fiscalía registrada en el expediente:'),'sin cuerpo genérico');$z->close();
 rejectsOffice(fn()=>$offices->prepare($actor,1,['tipo_oficio'=>'protocolo_necropsia','expediente_id'=>1,'confirmacion_id'=>$preview['confirmacion_id']]),'confirmación de un solo uso');
 checkOffice((int)$admin->query('SELECT COUNT(*) FROM oficios')->fetchColumn()===4,'no registra oficio');
 echo "OK: $n comprobaciones de fidelidad y seguridad de necropsia. Solo datos ficticios.\n";
}finally{wipeOffice($tmp);}
