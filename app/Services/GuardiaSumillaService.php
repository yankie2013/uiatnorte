<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

final class GuardiaSumillaService
{
 public function __construct(private PDO $pdo){}
 private function rows(string $sql,array $params):array{$st=$this->pdo->prepare($sql);$st->execute($params);return $st->fetchAll(PDO::FETCH_ASSOC);}
 private function text(mixed $value):string{return trim(preg_replace('/\s+/u',' ',(string)($value??''))??'');}
 private function upper(mixed $value):string{return mb_strtoupper($this->text($value),'UTF-8');}
 private function value(mixed $value):string{return $this->upper($value)?:'SIN REGISTRAR';}
 private function date(?string $date,bool $time=false):string{
  if(!$date)return 'SIN REGISTRAR';$stamp=strtotime($date);if(!$stamp)return 'SIN REGISTRAR';
  $months=['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SET','OCT','NOV','DIC'];
  return date('d',$stamp).$months[(int)date('n',$stamp)-1].date('Y',$stamp).($time?' / '.date('H:i',$stamp).' HORAS':'');
 }
 private function plate(?string $plate):string{return !$plate || str_starts_with($plate,'SPLACA')?'SIN PLACA':$this->upper($plate);}
 private function validity(?string $start,?string $end,?string $at):string{
  if(!$at||!$end)return 'VIGENCIA SIN REGISTRAR';
  $day=substr($at,0,10);
  return (!$start||$start<=$day)&&$end>=$day?'VIGENTE A LA FECHA DEL ACCIDENTE':'NO VIGENTE A LA FECHA DEL ACCIDENTE';
 }
 public function whatsappText(int $id):string{
  $case=$this->rows("SELECT a.*,ud.nombre distrito,c.nombre comisaria,f.nombre fiscalia,CONCAT_WS(' ',fi.nombres,fi.apellido_paterno,fi.apellido_materno) fiscal,fi.telefono fiscal_telefono,cg.comunicante,cg.telefono,u.nombre jefe_nombre,u.grado jefe_grado,u.telefono jefe_telefono FROM accidentes a JOIN comunicaciones_guardia cg ON cg.accidente_id=a.id LEFT JOIN ubigeo_distrito ud ON ud.cod_dep=a.cod_dep AND ud.cod_prov=a.cod_prov AND ud.cod_dist=a.cod_dist LEFT JOIN comisarias c ON c.id=a.comisaria_id LEFT JOIN fiscalia f ON f.id=a.fiscalia_id LEFT JOIN fiscales fi ON fi.id=a.fiscal_id LEFT JOIN usuarios u ON u.id=cg.jefe_id WHERE a.id=? AND a.eliminado_en IS NULL AND cg.eliminado_en IS NULL",[$id])[0]??null;
  if(!$case)throw new RuntimeException('Registro no encontrado.');
  $modalities=$this->rows('SELECT m.nombre FROM accidente_modalidad x JOIN modalidad_accidente m ON m.id=x.modalidad_id WHERE x.accidente_id=? ORDER BY m.id',[$id]);
  $people=$this->rows("SELECT ip.id involucrado_id,ip.vehiculo_id,ip.lesion,p.nombres,p.apellido_paterno,p.apellido_materno,r.Nombre rol,IF(ip.snapshot_guardado=1,ip.edad_snapshot,TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,a.fecha_accidente)) edad FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id JOIN accidentes a ON a.id=ip.accidente_id LEFT JOIN participacion_persona r ON r.Id=ip.rol_id WHERE ip.accidente_id=? ORDER BY ip.id",[$id]);
  $location=$this->text($case['lugar']);
  if($case['sentido'])$location.=' · Sentido: '.$this->text($case['sentido']);
  if($case['referencia'])$location.=' ('.$this->text($case['referencia']).')';
  if($case['distrito'])$location.=' · Distrito de '.$this->text($case['distrito']);
  $lines=['COMISARÍA: '.$this->value($case['comisaria']),'CLASE DE ACCIDENTE: '.$this->value(implode(' Y ',array_column($modalities,'nombre'))),'LUGAR: '.($location?:'Sin registrar'),'Fecha y hora del accidente: '.$this->date($case['fecha_accidente'],true),'Fecha y hora de comunicación: '.$this->date($case['fecha_comunicacion'],true),'Fecha y hora de intervención: '.$this->date($case['fecha_intervencion'],true)];
  $personLines=function(array $person):array{
   $state=match($person['lesion']){'Fallecido'=>'FALLECIDO','Herido'=>'LESIONADO',default=>'ILESO'};
   return [($this->text($person['rol'])?:'Participante').': ('.$state.')',$this->text($person['nombres'].' '.$person['apellido_paterno'].' '.$person['apellido_materno']).($person['edad']!==null?' ('.$person['edad'].')':'')];
  };
  $used=[];
  foreach((new \App\Repositories\InvolucradoPersonaRepository($this->pdo))->vehiculosPorAccidente($id) as $index=>$vehicle){
   $lines[]='';$lines[]=($vehicle['orden_participacion']?:'UT-'.($index+1)).': '.$vehicle['placa'].($vehicle['tipo']==='Combinado vehicular'?' (Combinado vehicular)':'');
   foreach($people as $person)if(in_array((int)$person['vehiculo_id'],array_map('intval',$vehicle['ids']??[$vehicle['id']]),true)){
    array_push($lines,...$personLines($person));$used[$person['involucrado_id']]=true;
   }
  }
  foreach($people as $person)if(!isset($used[$person['involucrado_id']])){$lines[]='';array_push($lines,...$personLines($person));}
  $contact=fn($name,$phone)=>($this->text($name)?:'Sin registrar').($this->text($phone)?' · Telf. '.$this->text($phone):'');
  $lines[]='';$lines[]='EMI: '.$contact(trim(($case['jefe_grado']??'').' '.($case['jefe_nombre']??'')),$case['jefe_telefono']);
  $lines[]='SIAT: '.$contact($case['comunicante_nombre']?:$case['comunicante'],$case['comunicante_telefono']?:$case['telefono']);
  $lines[]='FISCALÍA: '.$contact($case['fiscal'],$case['fiscal_telefono']);$lines[]=$this->value($case['fiscalia']);
  return implode("\n",$lines);
 }
 public function build(int $id):PhpWord{
  $case=$this->rows("SELECT a.*,c.nombre comisaria,f.nombre fiscalia,CONCAT_WS(' ',fi.nombres,fi.apellido_paterno,fi.apellido_materno) fiscal,fi.cargo fiscal_cargo,cg.comunicante,cg.telefono,cg.descripcion,cg.jefe_id,u.nombre jefe_nombre,u.grado jefe_grado,u.telefono jefe_telefono FROM accidentes a JOIN comunicaciones_guardia cg ON cg.accidente_id=a.id LEFT JOIN comisarias c ON c.id=a.comisaria_id LEFT JOIN fiscalia f ON f.id=a.fiscalia_id LEFT JOIN fiscales fi ON fi.id=a.fiscal_id LEFT JOIN usuarios u ON u.id=cg.jefe_id WHERE a.id=? AND a.eliminado_en IS NULL AND cg.eliminado_en IS NULL",[$id])[0]??null;
  if(!$case)throw new RuntimeException('Registro no encontrado.');
  $modalities=$this->rows('SELECT m.nombre FROM accidente_modalidad x JOIN modalidad_accidente m ON m.id=x.modalidad_id WHERE x.accidente_id=?',[$id]);
  $consequences=$this->rows('SELECT c.nombre FROM accidente_consecuencia x JOIN consecuencia_accidente c ON c.id=x.consecuencia_id WHERE x.accidente_id=?',[$id]);
  $vehicles=$this->rows('SELECT iv.id invol_id,iv.tipo participacion,iv.orden_participacion,v.*,t.nombre tipo,m.nombre marca,mo.nombre modelo,c.codigo categoria FROM involucrados_vehiculos_activos iv JOIN vehiculos v ON v.id=iv.vehiculo_id LEFT JOIN tipos_vehiculo t ON t.id=v.tipo_id LEFT JOIN marcas_vehiculo m ON m.id=v.marca_id LEFT JOIN modelos_vehiculo mo ON mo.id=v.modelo_id LEFT JOIN categoria_vehiculos c ON c.id=v.categoria_id WHERE iv.accidente_id=? ORDER BY iv.orden_participacion,iv.id',[$id]);
  $people=$this->rows("SELECT ip.id involucrado_id,ip.vehiculo_id,ip.lesion,ip.observaciones,ip.orden_persona,p.*,r.Nombre rol,IF(ip.snapshot_guardado=1,ip.edad_snapshot,TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,a.fecha_accidente)) edad_accidente,d.tipo fallecimiento_tipo,d.observaciones fallecimiento_observaciones,h.nombre hospital FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id JOIN accidentes a ON a.id=ip.accidente_id LEFT JOIN participacion_persona r ON r.Id=ip.rol_id LEFT JOIN guardia_persona_fallecimiento d ON d.involucrado_persona_id=ip.id LEFT JOIN guardia_hospitales h ON h.id=d.hospital_id WHERE ip.accidente_id=? ORDER BY ip.id",[$id]);
  Settings::setOutputEscapingEnabled(true);
  $word=new PhpWord();$word->setDefaultFontName('Arial');$word->setDefaultFontSize(10.5);
  $word->setDefaultParagraphStyle(['spaceAfter'=>0,'spacing'=>0,'lineHeight'=>1.05]);
  $section=$word->addSection(['pageSizeW'=>11906,'pageSizeH'=>16838,'marginTop'=>900,'marginBottom'=>900,'marginLeft'=>1100,'marginRight'=>1100]);
  $line=static function(string $text,bool $bold=false)use($section){$section->addText($text,['bold'=>$bold,'color'=>'000000'],['spaceAfter'=>0,'keepNext'=>$bold]);};
  $blank=static fn()=>$section->addTextBreak(1);
  $line('JEFE DIVPIAT.');$line('CRNL PNP GERMAN J. AMANCIO MACHUCA');$line('JEFE UIAT NORTE');$line('CMDTE PNP JOSE A. SUASNABAR VIDARTE');$blank();
  $line('ASUNTO',true);
  $line('ACCIDENTE DE TRÁNSITO ('.$this->value(implode(' Y ',array_column($modalities,'nombre'))).') CON CONSECUENCIA DE '.$this->value(implode(' Y ',array_column($consequences,'nombre'))).'.');
  $line('LUGAR DEL HECHO: '.$this->value($case['lugar']).($case['referencia']?' COMO REFERENCIA '.$this->upper($case['referencia']):'').'.');
  $line('JURISDICCIÓN: '.$this->value($case['comisaria']).'.');
  $line('FECHA Y HORA DEL ACCIDENTE: '.$this->date($case['fecha_accidente'],true));
  $line('FECHA Y HORA DE COMUNICACIÓN: '.$this->date($case['fecha_comunicacion'],true));
  $line('FECHA Y HORA DE INTERVENCIÓN: '.$this->date($case['fecha_intervencion'],true));$blank();
  $line('OBSERVACIONES',true);
  $hasDeath=false;
  foreach($people as $person)if($person['lesion']==='Fallecido'){
   $hasDeath=true;$name=$this->upper($person['nombres'].' '.$person['apellido_paterno'].' '.$person['apellido_materno']);
   $place=match($person['fallecimiento_tipo']){'lugar_hechos'=>'FALLECE EN EL LUGAR DE LOS HECHOS','hospital'=>'FALLECE EN '.$this->value($person['hospital']),default=>'LUGAR DE FALLECIMIENTO SIN REGISTRAR'};
   $line($name.': '.$place.'.'.($person['fallecimiento_observaciones']?' '.$this->upper($person['fallecimiento_observaciones']):''));
  }
  if(!$hasDeath)$line($this->value($case['descripcion']));
  $blank();$line('UNIDADES PARTICIPANTES',true);$blank();
  foreach($vehicles as $vehicle){
   $doc=$this->rows('SELECT * FROM documento_vehiculo_activos WHERE involucrado_vehiculo_id=? ORDER BY id DESC LIMIT 1',[$vehicle['invol_id']])[0]??[];
   $owners=$this->rows("SELECT pv.razon_social,CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno) nombre FROM propietario_vehiculo pv LEFT JOIN personas p ON p.id=pv.propietario_persona_id WHERE pv.accidente_id=? AND pv.vehiculo_inv_id=?",[$id,$vehicle['invol_id']]);
   $line($this->value($vehicle['tipo']).' CON PLACA DE RODAJE '.$this->plate($vehicle['placa']).', CATEGORÍA '.$this->value($vehicle['categoria']).', MARCA '.$this->value($vehicle['marca']).', MODELO '.$this->value($vehicle['modelo']).', AÑO '.$this->value($vehicle['anio']).', COLOR '.$this->value($vehicle['color']).', SERIE/VIN '.$this->value($vehicle['serie_vin']).', NRO. DE MOTOR '.$this->value($vehicle['nro_motor']).', SOAT '.$this->value($doc['aseguradora_soat']??null).' PÓLIZA NRO. '.$this->value($doc['numero_soat']??null).' ('.$this->validity($doc['vigente_soat']??null,$doc['vencimiento_soat']??null,$case['fecha_accidente']).').'.($owners?' PROPIETARIO: '.$this->upper(implode(', ',array_map(fn($o)=>$o['razon_social']?:$o['nombre'],$owners))).'.':''));
   if(str_starts_with($vehicle['participacion'],'Combinado vehicular'))$line('PARTICIPACIÓN: '.$this->upper($vehicle['orden_participacion'].' - '.$vehicle['participacion']).'.');
   $blank();
  }
  foreach($people as $person){
   $state=match($person['lesion']){'Fallecido'=>'FALLECIDO','Herido'=>'HERIDO',default=>'ILESO'};
   $line($this->value($person['rol']).($person['orden_persona']?' '.$person['orden_persona']:'').' ('.$state.')',true);
   $name=$this->upper($person['nombres'].' '.$person['apellido_paterno'].' '.$person['apellido_materno']);
   $text=$name.($person['edad_accidente']!==null?' ('.$person['edad_accidente'].' AÑOS)':'').', '.$this->value($person['tipo_doc']).' NRO. '.$this->value($person['num_doc']).', FECHA DE NACIMIENTO '.$this->date($person['fecha_nacimiento']).', NACIONALIDAD '.$this->value($person['nacionalidad']).'.';
   if(mb_strtolower((string)$person['rol'],'UTF-8')==='conductor'){
    $lc=$this->rows('SELECT * FROM documento_lc WHERE persona_id=? ORDER BY id DESC LIMIT 1',[$person['id']])[0]??[];
    $text.=' LICENCIA DE CONDUCIR NRO. '.$this->value($lc['numero']??null).', CLASE '.$this->value($lc['clase']??null).', CATEGORÍA '.$this->value($lc['categoria']??null).' ('.$this->validity($lc['vigente_desde']??null,$lc['vigente_hasta']??null,$case['fecha_accidente']).').';
   }
   foreach($vehicles as $vehicle)if((int)$vehicle['id']===(int)$person['vehiculo_id'])$text.=' VINCULADO A '.$this->value($vehicle['tipo']).' DE PLACA '.$this->plate($vehicle['placa']).'.';
   if($person['observaciones'])$text.=' '.$this->upper($person['observaciones']);
   $line($text);$blank();
  }
  $line('EMI: '.$this->value(trim(($case['jefe_grado']??'').' '.($case['jefe_nombre']??''))).($case['jefe_telefono']?' ('.$case['jefe_telefono'].')':'').'.');
  $communicator=$case['comunicante_nombre']?:$case['comunicante'];$phone=$case['comunicante_telefono']?:$case['telefono'];
  $line('SIAT CIA: '.$this->value($communicator).($phone?' ('.$phone.')':'').'.');
  $line('FISCAL: '.$this->value($case['fiscal']).($case['fiscal_cargo']?', '.$this->upper($case['fiscal_cargo']):'').($case['fiscalia']?', '.$this->upper($case['fiscalia']):'').'.');
  return $word;
 }
}
