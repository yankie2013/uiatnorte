<?php
declare(strict_types=1);
namespace Uiat\Connector;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class OfficeDrafts
{
    private array $catalog=[];
    public const COMMON=['numero_oficio','fecha_emision','localidad_emision','destinatario_cargo','destinatario_nombre','entidad_destino','subentidad_destino'];
    public function __construct(private OfficeQueries $queries,private CaseQueries $cases,private CameraDrafts $camera,private CameraDraftStore $store,private string $directory,string $catalogHash,private string $downloadUrl){
        $path=$directory.'/office-catalog.json';
        if(is_link($path)||!is_file($path)||!hash_equals($catalogHash,hash_file('sha256',$path)))throw new RuntimeException('Catálogo no verificado.');
        foreach(json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR) as $entry){
            if(!preg_match('/^[a-z_]+$/D',$entry['tipo'])||!preg_match('/^oficio_[a-z_]+\.docx$/D',$entry['plantilla']))throw new RuntimeException('Catálogo inválido.');
            $this->catalog[$entry['tipo']]=$entry;
        }
        if(count($this->catalog)!==10||!str_starts_with($downloadUrl,'https://'))throw new RuntimeException('Catálogo inválido.');
    }
    public function catalog():array {
        return ['tipos'=>array_map(static fn($e)=>['tipo'=>$e['tipo'],'nombre'=>$e['nombre'],'expresiones'=>$e['alias']],array_values($this->catalog)),
            'instruccion'=>'Elige el tipo por la petición natural del usuario. Si solo dice diligencias aclara solicitar o remitir. Pide el expediente y siempre el número manual; no calcules correlativos. Elige persona/vehículo ante varias opciones. Pregunta solo por campos_por_completar, muestra vista_previa y pide confirmación antes de generar. Conserva las fuentes y no inventes hechos, anexos ni destinatarios.'];
    }
    private function clean(mixed $s,int $limit=2000):string {if(!is_string($s)||!mb_check_encoding($s,'UTF-8')||mb_strlen($s)>$limit||preg_match('/[\x00-\x1F\x7F]|\$\{/u',$s))throw new InvalidArgumentException('Texto inválido.');return trim($s);}
    private function date(string $s):DateTimeImmutable {$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);if(!$d||$d->format('Y-m-d')!==$s)throw new InvalidArgumentException('Fecha inválida.');return $d;}
    private function longDate(DateTimeImmutable $d):string {return $d->format('j').' de '.['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][(int)$d->format('n')-1].' de '.$d->format('Y');}
    private function fullName(array $p):string {return trim(implode(' ',array_filter([$p['nombres']??'',$p['apellido_paterno']??'',$p['apellido_materno']??''],static fn($v)=>$v!=='')));}
    private function page(array $rows):array {$more=count($rows)>50;if($more)array_pop($rows);return ['registros'=>$rows,'siguiente_id'=>$more?(int)end($rows)['id']:null];}
    private function number(string $s):void {if(!preg_match('/^(?=.{1,32}$)(?=.*[0-9])[A-Za-z0-9 .\/-]+$/D',$s))throw new InvalidArgumentException('Número inválido.');}
    private function revalidate(Actor $actor,int $case,string $type,array $selected):void {
        $person=null;
        if(isset($selected['persona_registro_id']))$person=$this->queries->person($actor,$case,$selected['persona_registro_id'],$type);
        if(isset($selected['vehiculo_registro_id']))$this->queries->vehicle($actor,$case,$selected['vehiculo_registro_id'],$type);
        if(isset($selected['documento_registro_id'])){if(!$person)throw new RuntimeException('Persona requerida.');$this->queries->pericial($actor,$case,(int)$person['persona_id'],$selected['documento_registro_id']);}
        if(isset($selected['oficio_origen_id'])){if(!$person)throw new RuntimeException('Persona requerida.');$this->queries->reniec($actor,$case,(int)$person['id'],$selected['oficio_origen_id']);}
    }
    public function prepare(Actor $actor,int $case,array $args):array {
        $type=$args['tipo_oficio']??null;if(!is_string($type)||!isset($this->catalog[$type]))throw new InvalidArgumentException('Tipo no disponible.');
        $allowed=['tipo_oficio','expediente_id','confirmacion_id','campos_aportados','persona_registro_id','vehiculo_registro_id','documento_registro_id','oficio_origen_id','despues_persona_id','despues_vehiculo_id',...self::COMMON];
        if(array_diff(array_keys($args),$allowed))throw new InvalidArgumentException('Campo no admitido.');
        foreach(['persona_registro_id','vehiculo_registro_id','documento_registro_id','oficio_origen_id','despues_persona_id','despues_vehiculo_id'] as $key)if(isset($args[$key])&&(!is_int($args[$key])||$args[$key]<1))throw new InvalidArgumentException('Selección inválida.');
        $extra=$args['campos_aportados']??[];if(!is_array($extra)||array_is_list($extra)&&$extra!==[]||strlen(json_encode($extra,JSON_THROW_ON_ERROR))>16000)throw new InvalidArgumentException('Datos inválidos.');
        if($type==='camaras'){
            if(array_diff(array_keys($args),['tipo_oficio','expediente_id','confirmacion_id','campos_aportados',...self::COMMON])||isset($args['subentidad_destino']))throw new InvalidArgumentException('Campo no admitido para cámaras.');
            if(array_diff(array_keys($extra),array_keys(CameraDrafts::FIELDS))||array_intersect(array_keys($extra),self::COMMON))throw new InvalidArgumentException('Campo de cámaras inválido.');
            $forward=array_diff_key($args,array_flip(['tipo_oficio','campos_aportados']));return $this->camera->prepare($actor,$case,$forward+$extra);
        }
        $entry=$this->catalog[$type];$template=$this->directory.'/offices/'.$entry['plantilla'];
        $renderer=new CameraDraftRenderer($template,$entry['sha256']);
        $base=$this->queries->base($actor,$case,$type);
        if(isset($args['confirmacion_id'])){
            if(array_diff(array_keys($args),['tipo_oficio','expediente_id','confirmacion_id'])||!is_string($args['confirmacion_id']))throw new InvalidArgumentException('Confirma sin cambiar campos.');
            $id=$args['confirmacion_id'];$snapshot=$this->store->summary($id,$actor,$case);
            if(($snapshot['tipo_oficio']??null)!==$type||($snapshot['plantilla_sha256']??null)!==$entry['sha256'])throw new RuntimeException('Vista previa no corresponde.');
            $this->revalidate($actor,$case,$type,$snapshot['selecciones']??[]);
            $d=$this->store->generate($id,$actor,$case,$renderer,$type,$entry['sha256']);
            try{$this->cases->ficha($actor,$case);$this->revalidate($actor,$case,$type,$snapshot['selecciones']??[]);}catch(\Throwable $e){$this->store->discard($id,$actor);throw $e;}
            return ['estado'=>'documento_generado','tipo_oficio'=>$type,'borrador_id'=>$id,'descarga_url'=>$this->downloadUrl.'?archivo='.$id,'caduca_en'=>gmdate('c',$d['expires']),'requiere_sesion_uiat'=>true,'registrado_en_uiat'=>false,'firma_digital_verificada'=>false,'instruccion'=>'Descargar con la misma sesión UIAT. Incluye los elementos gráficos de la plantilla original; no registra ni envía el oficio.'];
        }
        $common=[];foreach(self::COMMON as $key)if(isset($args[$key]))$common[$key]=$this->clean($args[$key],200);
        if(($common['numero_oficio']??'')!=='')$this->number($common['numero_oficio']);
        $extraAllowed=[...$entry['campos'],'numero_informe_policial','diligencias_a_remitir','diligencias_solicitadas','adjuntos','accidente_sidpol','accidente_fecha_abrev','accidente_lugar','accidente_lugar_completo','fiscalia_nombre','accidente_modalidades','accidente_consecuencia','accidente_hora','veh_placa','comisaria_nombre'];
        if(array_diff(array_keys($extra),$extraAllowed)||array_intersect(array_keys($extra),self::COMMON))throw new InvalidArgumentException('Campo de plantilla no admitido.');
        foreach($extra as $key=>$value)$extra[$key]=$this->clean($value);
        $v=[];$sources=[];$questions=[];$options=[];$selections=[];
        $set=function(array $names,mixed $value,mixed $source)use(&$v,&$sources){$value=trim((string)$value);if($value!=='')foreach($names as $name){$v[$name]=$value;$sources[$name]=$source;}};
        $ask=function(string $field,string $label)use(&$questions){$questions[$field]=['campo'=>$field,'pregunta'=>$label];};
        $set(['numero','oficio_numero'],$common['numero_oficio']??'','aportado_por_usuario_sin_correlativo');
        $set(['localidad_emision'],$common['localidad_emision']??'','aportado_por_usuario');
        $emission=null;
        if(($common['fecha_emision']??'')!==''){
            $emission=$this->date($common['fecha_emision']);$set(['oficio_fecha','fecha_emision'],$this->longDate($emission),'aportado_por_usuario');$set(['anio','oficio_anio'],$emission->format('Y'),'fecha_emision');
            $year=$this->cases->nombreDelAno((int)$emission->format('Y'));if($year['estado']==='registrado')$set(['nombre_ano','nombre_oficial_ano'],$year['nombres'][0],['tabla'=>'oficio_oficial_ano','anio'=>$emission->format('Y')]);
        }
        if(isset($v['numero'],$v['anio'])){$title='OFICIO N.º '.$v['numero'].'-'.$v['anio'].'-DIRNOS-DIRTTSV/DIVPIAT-UIAT-NORTE.';$set(['oficio_titulo','oficio_numero_completo'],$title,'numero_manual_y_fecha_emision');}
        $set(['oficio_grado_cargo','destino_grado_cargo','grado_cargo','grado_cargo_nombre'],$common['destinatario_cargo']??'','aportado_por_usuario');
        $set(['oficio_persona_destino','destino_persona','persona_destino'],$common['destinatario_nombre']??'','aportado_por_usuario');
        $set(['entidad_nombre','oficio_entidad_nombre','destino_entidad','entidad_destino'],$common['entidad_destino']??'','aportado_por_usuario');
        $set(['destino_subentidad'],$common['subentidad_destino']??'','aportado_por_usuario');
        $set(['accidente_lugar','accidente_lugar_completo'],$base['lugar']??'','accidentes_activos.lugar');$set(['accidente_referencia'],$base['referencia']??'','accidentes_activos.referencia');
        $set(['accidente_modalidad','accidente_modalidades'],$base['modalidad']??'','accidente_modalidad_activos');$set(['accidente_consecuencia'],$base['consecuencia']??'','accidente_consecuencia_activos');
        $set(['fiscalia_nombre'],$base['fiscalia']??'','fiscalia.nombre');$set(['comisaria_nombre'],$base['comisaria']??'','comisarias.nombre');$set(['accidente_distrito'],$base['distrito']??'','ubigeo_distrito.nombre');
        $set(['accidente_sidpol'],$base['registro_sidpol']??'','accidentes_activos.registro_sidpol');
        if(($base['registro_sidpol']??'')!=='')$set(['referencia_texto'],'Registro SIDPOL N.º '.$base['registro_sidpol'],'accidentes_activos.registro_sidpol');
        $accident=null;$rawDate=(string)($base['fecha_accidente']??'');
        if($rawDate!==''){$accident=$this->date(substr($rawDate,0,10));$set(['accidente_fecha','accidente_fecha_abrev'],$this->longDate($accident),'accidentes_activos.fecha_accidente');if(strlen($rawDate)>=16)$set(['accidente_hora'],substr($rawDate,11,5),'accidentes_activos.fecha_accidente');}
        if($emission&&$accident&&$emission<$accident)throw new InvalidArgumentException('Emisión anterior al hecho.');
        $person=null;$needPerson=in_array($type,['protocolo_necropsia','informe_medico','defuncion_reniec'],true);
        if($needPerson){
            $rows=$this->queries->people($actor,$case,$type!=='informe_medico',$args['despues_persona_id']??0);$selected=$args['persona_registro_id']??null;
            if($selected===null&&count($rows)===1&&!isset($args['despues_persona_id']))$selected=(int)$rows[0]['id'];
            if($selected===null&&$rows){$options['personas']=$this->page($rows);$ask('persona_registro_id','Elige la persona del expediente para este oficio.');}
            if($selected!==null){
                $person=$this->queries->person($actor,$case,$selected,$type);$selections['persona_registro_id']=$selected;$src=['seccion'=>'participantes','registro_id'=>$selected];
                $set(['fallecido_nombre_completo','persona_nombre_completo'],$this->fullName($person),$src);$set(['fallecido_nombres'],$person['nombres'],$src);$set(['fallecido_apellidos'],trim($person['apellido_paterno'].' '.$person['apellido_materno']),$src);
                $set(['persona_num_doc','fallecido_documento'],$person['num_doc']??'',$src);
                if(!empty($person['fecha_nacimiento'])){$birth=$this->date(substr($person['fecha_nacimiento'],0,10));$set(['fallecido_nacimiento_fecha'],$this->longDate($birth),$src);if($accident&&$birth<=$accident)$set(['fallecido_edad'],(string)$birth->diff($accident)->y,$src+['calculo'=>'edad_al_dia_del_accidente']);}
                foreach(['sexo','estado_civil','nacionalidad'] as $key)$set(['fallecido_'.$key],$person[$key]??'',$src);
                $set(['fallecido_nacimiento_lugar'],implode(' / ',array_filter([$person['distrito_nac']??'',$person['provincia_nac']??'',$person['departamento_nac']??''])),$src);
                foreach(['nombre_padre','nombre_madre'] as $key)$set([$key],$person[$key]??'',$src);
            }
        } elseif(isset($args['persona_registro_id'])||isset($args['despues_persona_id']))throw new InvalidArgumentException('Este tipo no requiere selección de persona.');
        if(in_array($type,['peritaje','certificado_uper','historial_sunarp'],true)){
            $rows=$this->queries->vehicles($actor,$case,$args['despues_vehiculo_id']??0);$selected=$args['vehiculo_registro_id']??null;
            if($selected===null&&count($rows)===1&&!isset($args['despues_vehiculo_id']))$selected=(int)$rows[0]['id'];
            if($selected===null&&$rows){$options['vehiculos']=$this->page($rows);$ask('vehiculo_registro_id','Elige el vehículo del expediente.');}
            if($selected!==null){$vehicle=$this->queries->vehicle($actor,$case,$selected,$type);$selections['vehiculo_registro_id']=$selected;$src=['seccion'=>'vehiculos','registro_id'=>$selected];
                foreach(['placa','anio','color','marca','modelo','categoria','carroceria','tipo'] as $key)$set(['veh_'.$key],$vehicle[$key]??'',$src);
                foreach(['conductores'=>'conductor_nombre','propietarios'=>'propietario_nombre'] as $key=>$field)if(isset($vehicle[$key])){
                    if(count($vehicle[$key])===1){$row=$vehicle[$key][0];$name=($row['tipo_propietario']??'')==='JURIDICA'?($row['razon_social']??''):$this->fullName($row);$set([$field],$name,$src+['vinculo'=>$key]);}
                    elseif($vehicle[$key])$options[$key]=$this->page($vehicle[$key]);
                }
            }
        } elseif(isset($args['vehiculo_registro_id'])||isset($args['despues_vehiculo_id']))throw new InvalidArgumentException('Este tipo no requiere vehículo.');
        if($type==='protocolo_necropsia'&&$person){$rows=$this->queries->pericial($actor,$case,(int)$person['persona_id'],$args['documento_registro_id']??null);
            if(count($rows)===1){$set(['numero_pericial'],$rows[0]['numero_pericial'],['seccion'=>'documento_occiso','registro_id'=>(int)$rows[0]['id']]);$selections['documento_registro_id']=(int)$rows[0]['id'];}elseif($rows){$options['documentos_periciales']=$this->page($rows);$ask('documento_registro_id','Elige el documento pericial de la persona seleccionada.');}
        } elseif(isset($args['documento_registro_id']))throw new InvalidArgumentException('Documento no aplicable.');
        if($type==='defuncion_reniec'&&$person){$reniec=$this->queries->reniec($actor,$case,(int)$person['id'],$args['oficio_origen_id']??null);
            if(isset($reniec['datos'])){$selections['oficio_origen_id']=$reniec['oficio_id'];foreach($reniec['datos'] as $key=>$value)if(is_scalar($value)&&$value!=='null')$set([$key],$value,['oficio_origen_id'=>$reniec['oficio_id'],'campo'=>$key]);}
            elseif(!empty($reniec['opciones'])){$options['oficios_origen']=$this->page($reniec['opciones']);$ask('oficio_origen_id','Elige de qué oficio del mismo expediente se recuperan los datos registrados de RENIEC.');}
        } elseif(isset($args['oficio_origen_id']))throw new InvalidArgumentException('Oficio origen no aplicable.');
        if(array_intersect(array_keys($extra),['cuerpo_remision','cuerpo_solicitud_diligencias','cuerpo_sunarp']))throw new InvalidArgumentException('El cuerpo se compone con datos y solicitud.');
        if($type==='protocolo_necropsia')foreach(['fecha_informe_pericial','fecha_internamiento'] as $field)if(($extra[$field]??'')!==''){
            $date=$this->date($extra[$field]);
            if($emission&&$date>$emission)throw new InvalidArgumentException('Fecha documental posterior a la emisión.');
            $extra[$field]=$date->format('d').['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'][(int)$date->format('n')-1].$date->format('Y');
        }
        foreach($extra as $key=>$value){
            if($value==='')continue;
            if(isset($v[$key])&&$v[$key]!==$value)throw new InvalidArgumentException('No se puede sobrescribir un dato recuperado: '.$key);
            $v[$key]=$value;$sources[$key]=$sources[$key]??'aportado_por_usuario';
        }
        if($type==='peritaje'&&isset($v['veh_placa']))$set(['oficio_asunto'],'Solicita peritaje de constatación de daños del vehículo de placa '.$v['veh_placa'].'.','tipo_y_vehiculo');
        if($type==='protocolo_necropsia'&&isset($v['fallecido_nombres'],$v['fallecido_apellidos']))$set(['oficio_asunto'],'Solicita protocolo de necropsia de '.$v['fallecido_nombres'].' '.$v['fallecido_apellidos'].'.','tipo_y_persona');
        if($type==='remitir_informe_policial'){
            $report=trim((string)($base['nro_informe_policial']??''));if($report!==''&&isset($extra['numero_informe_policial'])&&$extra['numero_informe_policial']!==$report)throw new InvalidArgumentException('Número de informe distinto al registrado.');
            if($report==='')$report=$extra['numero_informe_policial']??'';
            if($report!=='')$set(['motivo'],'Remite Informe Policial N.º '.$report,$base['nro_informe_policial']??null?'accidentes_activos.nro_informe_policial':'aportado_por_usuario');else $ask('campos_aportados.numero_informe_policial','Indica el número del informe policial a remitir; no está registrado.');
        }
        if($type==='remitir_diligencias'){
            if(($extra['diligencias_a_remitir']??'')!=='')$set(['motivo'],'Remite '.$extra['diligencias_a_remitir'],'seleccion_de_usuario');
            else $ask('campos_aportados.diligencias_a_remitir','Indica las diligencias que efectivamente vas a remitir y sus anexos.');
        }
        if(in_array($type,['remitir_informe_policial','remitir_diligencias'],true)&&isset($v['motivo'])){
            foreach(['accidente_sidpol','accidente_fecha_abrev','accidente_lugar'] as $field)if(($v[$field]??'')==='')$ask('campos_aportados.'.$field,'Completa '.str_replace('_',' ',$field).'; falta en el expediente.');
            if(($v['adjuntos']??'')==='')$ask('campos_aportados.adjuntos','Indica los anexos que efectivamente acompañarán esta remisión, o confirma que no se adjuntan anexos.');
            else $set(['cuerpo_remision'],'Tengo el agrado de dirigirme a Ud. para '.preg_replace('/^Remite\s+/u','remitir ',$v['motivo']).', en relación con el accidente de tránsito registrado con SIDPOL '.($v['accidente_sidpol']??'').', ocurrido el '.($v['accidente_fecha_abrev']??'').' en '.($v['accidente_lugar']??'').'. Anexos indicados para revisión: '.$v['adjuntos'].'.','datos_del_expediente_y_remision_confirmada');
        }
        if($type==='solicitar_diligencias_comisaria'){
            foreach(['accidente_sidpol','accidente_fecha_abrev','accidente_lugar_completo','fiscalia_nombre'] as $field)if(($v[$field]??'')==='')$ask('campos_aportados.'.$field,'Completa '.str_replace('_',' ',$field).'; falta en el expediente.');
            if(($v['diligencias_solicitadas']??'')==='')$ask('campos_aportados.diligencias_solicitadas','Indica qué diligencias o información solicitas a la comisaría.');
            else $set(['cuerpo_solicitud_diligencias'],'Tengo el agrado de dirigirme a Ud. para solicitar información o remisión de las siguientes diligencias: '.$v['diligencias_solicitadas'].', relacionadas con la investigación del accidente de tránsito registrado con SIDPOL '.($v['accidente_sidpol']??'').', ocurrido el '.($v['accidente_fecha_abrev']??'').' en '.($v['accidente_lugar_completo']??'').'. Fiscalía registrada en el expediente: '.($v['fiscalia_nombre']??'').'.','expediente_y_solicitud_confirmada');
        }
        if($type==='historial_sunarp'){
            foreach(['accidente_modalidades','accidente_consecuencia','accidente_fecha_abrev','accidente_hora','accidente_lugar','veh_placa','comisaria_nombre'] as $field)if(($v[$field]??'')==='')$ask('campos_aportados.'.$field,'Completa '.str_replace('_',' ',$field).'; falta en el expediente.');
            $set(['cuerpo_sunarp'],'Tengo el agrado de dirigirme a Ud. para solicitar el historial de transferencias del vehículo de placa '.($v['veh_placa']??'').', vinculado al accidente de tránsito ('.($v['accidente_modalidades']??'').') de consecuencia '.($v['accidente_consecuencia']??'').', ocurrido el '.($v['accidente_fecha_abrev']??'').' a las '.($v['accidente_hora']??'').' horas en '.($v['accidente_lugar']??'').', jurisdicción de '.($v['comisaria_nombre']??'').'.','datos_del_expediente_y_vehiculo');
        }
        if($type==='defuncion_reniec'&&isset($v['fallecido_nombres'],$v['fallecido_apellidos']))$set(['motivo'],'Solicita inscripción de defunción de '.$v['fallecido_nombres'].' '.$v['fallecido_apellidos'].'.','tipo_y_persona');
        foreach(['numero_oficio'=>'Indica el número manual para este oficio.','fecha_emision'=>'Confirma la fecha de emisión YYYY-MM-DD.','localidad_emision'=>'Confirma la localidad de emisión.'] as $field=>$label)if(($common[$field]??'')==='')$ask($field,$label);
        $optional=['destino_subentidad'];
        $commonAlias=['oficio_grado_cargo'=>'destinatario_cargo','destino_grado_cargo'=>'destinatario_cargo','grado_cargo'=>'destinatario_cargo','grado_cargo_nombre'=>'destinatario_cargo','oficio_persona_destino'=>'destinatario_nombre','destino_persona'=>'destinatario_nombre','persona_destino'=>'destinatario_nombre','entidad_nombre'=>'entidad_destino','oficio_entidad_nombre'=>'entidad_destino','destino_entidad'=>'entidad_destino'];
        foreach($entry['campos'] as $field)if(!isset($v[$field])||$v[$field]===''){
            if(in_array($field,['cuerpo_remision','cuerpo_solicitud_diligencias','cuerpo_sunarp'],true))continue;
            if(isset($questions['persona_registro_id'])&&(str_starts_with($field,'fallecido_')||str_starts_with($field,'persona_')||in_array($field,['numero_pericial','nombre_padre','nombre_madre','fecha_fallecimiento','hora_fallecimiento','lugar_fallecimiento','causa_muerte','agente_causante','adjuntos'],true)))continue;
            if(isset($questions['vehiculo_registro_id'])&&(str_starts_with($field,'veh_')||in_array($field,['conductor_nombre','propietario_nombre'],true)))continue;
            if(isset($questions['documento_registro_id'])&&$field==='numero_pericial')continue;
            if(in_array($field,$optional,true)){$v[$field]='';continue;}
            if(in_array($field,['nombre_ano','nombre_oficial_ano'],true)&&$emission===null)continue;
            if(in_array($field,['numero','anio','oficio_numero','oficio_anio','oficio_titulo','oficio_numero_completo','oficio_fecha','fecha_emision','localidad_emision'],true))continue;
            $ask(isset($commonAlias[$field])?$commonAlias[$field]:'campos_aportados.'.$field,'Completa o confirma: '.str_replace('_',' ',$field).'. No hay un dato inequívoco disponible.');
        }
        if($questions)return ['estado'=>'faltan_datos','tipo_oficio'=>$type,'expediente_id'=>$case,'campos_por_completar'=>array_values($questions),'opciones'=>$options,'datos_recuperados'=>array_intersect_key($v,array_flip($entry['campos'])),'fuentes'=>$sources,'instruccion'=>'Pregunta solo por los campos indicados. El número manual es obligatorio. No inventes hechos o anexos; elige registros del mismo expediente cuando hay opciones.'];
        $values=array_intersect_key($v,array_flip($entry['campos']));if(count($values)!==count($entry['campos']))throw new RuntimeException('Plantilla incompleta.');
        $summary=['selecciones'=>$selections,'tipo_oficio'=>$type,'plantilla_sha256'=>$entry['sha256'],'expediente_id'=>$case,'numero_aportado'=>$common['numero_oficio'],'datos_a_usar'=>$values,'fuentes'=>$sources,'registrado_en_uiat'=>false,'firma_digital_verificada'=>false];
        $id=$this->store->preview($actor,$case,$values,$summary);
        return ['estado'=>'pendiente_confirmacion','confirmacion_id'=>$id,'caduca_en_segundos'=>300,'vista_previa'=>$summary,'instruccion'=>'Muestra la vista previa y pide confirmación expresa. Después llama con tipo_oficio, expediente_id y confirmacion_id únicamente. Usa la plantilla original: no registra ni envía documentos.'];
    }
}
