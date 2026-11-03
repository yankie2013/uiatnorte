<?php
declare(strict_types=1);
namespace Uiat\Connector;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/** Fixed sanitized template, no PHPWord/application bootstrap, signatures or arbitrary paths. */
final class CameraDraftRenderer
{
    public function __construct(private string $template,private string $sha256) {
        if(is_link($template)||!is_file($template)||!hash_equals($sha256,hash_file('sha256',$template)))throw new RuntimeException('Plantilla no verificada.');
    }
    public function render(array $values,string $output): void {
        if(!hash_equals($this->sha256,hash_file('sha256',$this->template)))throw new RuntimeException('Plantilla modificada.');
        if(file_exists($output)||is_link($output)||!copy($this->template,$output))throw new RuntimeException('Borrador no disponible.');
        chmod($output,0600);$zip=new ZipArchive();
        try {
            if($zip->open($output)!==true)throw new RuntimeException('Plantilla inválida.');
            $parts=[];for($i=0;$i<$zip->numFiles;$i++){ $part=$zip->getNameIndex($i);if(preg_match('~^word/(?:document|header[0-9]+|footer[0-9]+)\.xml$~D',$part))$parts[]=$part;}
            if(!in_array('word/document.xml',$parts,true))throw new RuntimeException('Plantilla inválida.');
            foreach($parts as $part) {
                $xml=$zip->getFromName($part);if(!is_string($xml)||str_contains($xml,'<!DOCTYPE'))throw new RuntimeException('Plantilla inválida.');
                $dom=new DOMDocument();$dom->preserveWhiteSpace=true;
                if(!$dom->loadXML($xml,LIBXML_NONET))throw new RuntimeException('Plantilla inválida.');
                $xp=new DOMXPath($dom);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                foreach($xp->query('//w:p[not(.//w:p)]') as $p) {
                    $nodes=iterator_to_array($xp->query('.//w:t',$p));$text=implode('',array_map(fn($n)=>$n->textContent,$nodes));
                    preg_match_all('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/',$text,$matches,PREG_OFFSET_CAPTURE);
                    for($i=count($matches[0])-1;$i>=0;$i--) {
                        [$marker,$start]=$matches[0][$i];$key=$matches[1][$i][0];
                        if(!array_key_exists($key,$values)||!is_string($values[$key]))throw new RuntimeException('Campo de plantilla no admitido.');
                        $end=$start+strlen($marker);$offset=0;$inserted=false;
                        foreach($nodes as $node) {
                            $before=$node->textContent;$length=strlen($before);$last=$offset+$length;
                            if($last>$start&&$offset<$end) {
                                $after=substr($before,0,max(0,$start-$offset)).(!$inserted?$values[$key]:'').substr($before,min($length,max(0,$end-$offset)));
                                while($node->firstChild)$node->removeChild($node->firstChild);
                                $node->appendChild($dom->createTextNode($after));$node->setAttribute('xml:space','preserve');$inserted=true;
                            }
                            $offset=$last;
                        }
                    }
                }
                $result=$dom->saveXML();if(str_contains($result,'${'))throw new RuntimeException('Campos sin completar.');
                if(!$zip->addFromString($part,$result))throw new RuntimeException('No se pudo generar el borrador.');
            }
            if(!$zip->close())throw new RuntimeException('No se pudo guardar el borrador.');
        } catch(\Throwable $e){try{$zip->close();}catch(\Throwable $ignored){}@unlink($output);throw $e;}
    }
}

/** Private, bounded, short-lived artifacts. Every operation is serialized under one lock. */
final class CameraDraftStore
{
    public function __construct(private string $directory) {
        $this->directory=rtrim($directory,'/');
        if(is_link($directory)||!is_dir($directory)||(fileperms($directory)&0077)!==0)throw new RuntimeException('Directorio de borradores privado requerido.');
    }
    private function locked(\Closure $fn): mixed {
        $path=$this->directory.'/.lock';if(is_link($path))throw new RuntimeException('Almacenamiento no disponible.');
        $f=fopen($path,'c+b');if(!$f)throw new RuntimeException('Almacenamiento no disponible.');
        try {chmod($path,0600);if(!flock($f,LOCK_EX))throw new RuntimeException('Almacenamiento no disponible.');return $fn();}
        finally {flock($f,LOCK_UN);fclose($f);}
    }
    private function path(string $id,string $ext): string {
        if(!preg_match('/^[a-f0-9]{64}$/D',$id))throw new RuntimeException('Borrador no disponible.');
        return $this->directory.'/'.$id.'.'.$ext;
    }
    private function read(string $id): array {
        $p=$this->path($id,'json');
        if(is_link($p)||!is_file($p)||filesize($p)>32768||(fileperms($p)&0077)!==0)throw new RuntimeException('Borrador no disponible.');
        $data=json_decode(file_get_contents($p),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($data))throw new RuntimeException('Borrador no disponible.');return $data;
    }
    private function save(string $id,array $data): void {
        $p=$this->path($id,'json');if(is_link($p))throw new RuntimeException('Almacenamiento no disponible.');
        $raw=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if(strlen($raw)>32768||file_put_contents($p,$raw)!==strlen($raw)||!chmod($p,0600))throw new RuntimeException('Almacenamiento no disponible.');
    }
    private function sweep(): array {
        $counts=[];$total=0;$ids=[];
        // Snapshot the inventory before deleting files: DirectoryIterator may yield cached entries after unlink.
        foreach(new \DirectoryIterator($this->directory) as $file) {
            if($file->isDot()||$file->getFilename()==='.lock')continue;
            if(++$total>2000)throw new RuntimeException('Capacidad temporal agotada.');
            if($file->isLink()||!$file->isFile()||!preg_match('/^([a-f0-9]{64})\.(json|docx)$/D',$file->getFilename(),$m))throw new RuntimeException('Almacenamiento no disponible.');
            if($m[2]==='json')$ids[]=$m[1];
        }
        foreach($ids as $id) {
            $d=$this->read($id);
            if(($d['expires']??0)<=time()){$this->remove($id);continue;}
            $counts[$d['owner']]=($counts[$d['owner']]??0)+1;
        }
        return $counts;
    }
    private function remove(string $id): void {
        foreach(['docx','json'] as $ext){$p=$this->path($id,$ext);if(is_link($p))throw new RuntimeException('Almacenamiento no disponible.');if(is_file($p)&&!unlink($p))throw new RuntimeException('Almacenamiento no disponible.');}
    }
    private function owned(array $data,Actor $actor,int $case): void {
        if(($data['owner']??null)!==$actor->id||($data['version']??null)!==$actor->version||($data['case']??null)!==$case||($data['expires']??0)<=time())throw new RuntimeException('Borrador no disponible.');
    }
    public function summary(string $id,Actor $actor,int $case): array {
        return $this->locked(function()use($id,$actor,$case){$this->sweep();$d=$this->read($id);$this->owned($d,$actor,$case);if($d['stage']!=='preview')throw new RuntimeException('Vista previa no disponible.');return $d['summary'];});
    }
    public function purgeExpired(): void {$this->locked(function(){$this->sweep();});}
    public function preview(Actor $actor,int $case,array $values,array $summary): string {
        return $this->locked(function()use($actor,$case,$values,$summary){
            $counts=$this->sweep();if(($counts[$actor->id]??0)>=10||array_sum($counts)>=500)throw new RuntimeException('Demasiados borradores temporales.');
            $id=bin2hex(random_bytes(32));$p=$this->path($id,'json');if(file_exists($p))throw new RuntimeException('Almacenamiento no disponible.');
            $this->save($id,['owner'=>$actor->id,'version'=>$actor->version,'case'=>$case,'stage'=>'preview','expires'=>time()+300,'values'=>$values,'summary'=>$summary]);return $id;
        });
    }
    public function generate(string $id,Actor $actor,int $case,CameraDraftRenderer $renderer,?string $expectedType=null,?string $expectedTemplate=null): array {
        return $this->locked(function()use($id,$actor,$case,$renderer,$expectedType,$expectedTemplate){
            $this->sweep();$d=$this->read($id);$this->owned($d,$actor,$case);
            if(($expectedType!==null&&($d['summary']['tipo_oficio']??null)!==$expectedType)||($expectedTemplate!==null&&($d['summary']['plantilla_sha256']??null)!==$expectedTemplate))throw new RuntimeException('Confirmación no corresponde al tipo o plantilla.');
            if($d['stage']!=='preview')throw new RuntimeException('Confirmación ya utilizada.');
            $d['stage']='generating';$this->save($id,$d);
            try {
                $renderer->render($d['values'],$this->path($id,'docx'));
                if(filesize($this->path($id,'docx'))>2097152)throw new RuntimeException('Borrador demasiado grande.');
                $d['stage']='ready';$d['expires']=time()+900;$d['sha256']=hash_file('sha256',$this->path($id,'docx'));unset($d['values']);$this->save($id,$d);return $d;
            }catch(\Throwable $e){$this->remove($id);throw $e;}
        });
    }
    public function download(string $id,Actor $actor,\Closure $authorize): string {
        return $this->locked(function()use($id,$actor,$authorize){
            $this->sweep();$d=$this->read($id);$this->owned($d,$actor,(int)$d['case']);
            if($d['stage']!=='ready')throw new RuntimeException('Borrador no disponible.');
            $authorize((int)$d['case']);$p=$this->path($id,'docx');
            if(is_link($p)||!is_file($p)||(fileperms($p)&0077)!==0||filesize($p)>2097152||!hash_equals($d['sha256'],hash_file('sha256',$p)))throw new RuntimeException('Borrador no disponible.');
            return file_get_contents($p);
        });
    }
    public function discard(string $id,Actor $actor): void {
        $this->locked(function()use($id,$actor){$d=$this->read($id);if($d['owner']===$actor->id&&$d['version']===$actor->version)$this->remove($id);});
    }
}

/** Only derives a temporary Word file. No automatic office numbers, application writes, signatures or outbound requests. */
final class CameraDrafts
{
    public const FIELDS=[
        'nombre_oficial_ano'=>'Nombre oficial del año de emisión; solo aportar si no hay coincidencia única en UIAT',
        'numero_oficio'=>'Número del oficio aportado expresamente por el usuario; obligatorio, sin correlativo automático',
        'destinatario_cargo'=>'Cargo del destinatario','destinatario_nombre'=>'Nombre del destinatario','entidad_destino'=>'Entidad destinataria',
        'modalidad_confirmada'=>'Modalidad aportada solo si no está registrada en el expediente','consecuencia_confirmada'=>'Consecuencia aportada solo si no está registrada en el expediente',
        'referencia_confirmada'=>'Referencia del lugar','fiscalia_confirmada'=>'Fiscalía que dispone la investigación',
        'localidad_emision'=>'Localidad de emisión del borrador','fecha_emision'=>'Fecha de emisión prevista YYYY-MM-DD','rango_desde'=>'Inicio del video HH:MM','rango_hasta'=>'Fin del video HH:MM',
        'lugar_aportado'=>'Lugar confirmado por el usuario solo si falta en UIAT','fecha_accidente_aportada'=>'Fecha YYYY-MM-DD solo si falta en UIAT',
    ];
    public function __construct(private CaseQueries $queries,private Users $users,private CameraDraftStore $store,private CameraDraftRenderer $renderer,private string $downloadUrl) {
        if(!str_starts_with($downloadUrl,'https://')||parse_url($downloadUrl,PHP_URL_QUERY)!==null||parse_url($downloadUrl,PHP_URL_FRAGMENT)!==null)throw new RuntimeException('Ruta HTTPS requerida.');
    }
    private function clean(mixed $value,int $limit=180): string {
        if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>$limit||preg_match('/[\x00-\x1F\x7F]|\$\{/u',$value))throw new InvalidArgumentException('Campo inválido.');return trim($value);
    }
    private function date(string $value): DateTimeImmutable {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new InvalidArgumentException('Fecha inválida.');return $d;
    }
    private function spanish(DateTimeImmutable $d): string {return $d->format('j').' de '.['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][(int)$d->format('n')-1].' de '.$d->format('Y');}
    public function prepare(Actor $actor,int $case,array $args): array {
        $source=$this->queries->fichaParaBorrador($actor,$case);
        if(isset($args['confirmacion_id'])) {
            if(array_diff(array_keys($args),['expediente_id','confirmacion_id']))throw new InvalidArgumentException('Confirma la vista previa sin cambiar campos.');
            $id=$args['confirmacion_id'];if(!is_string($id))throw new InvalidArgumentException('Confirmación inválida.');
            $d=$this->store->generate($id,$actor,$case,$this->renderer,'camaras');
            // Revalidate after rendering, before revealing a download identifier.
            try{$this->queries->ficha($actor,$case);}catch(\Throwable $e){$this->store->discard($id,$actor);throw $e;}
            return ['estado'=>'borrador_generado','borrador_id'=>$id,'descarga_url'=>$this->downloadUrl.'?archivo='.$id,'caduca_en'=>gmdate('c',$d['expires']),
                'requiere_sesion_uiat'=>true,'numero_oficial'=>null,'firmado'=>false,'registrado_en_uiat'=>false,
                'instruccion'=>'Abrir el enlace en el navegador con la misma cuenta UIAT. Si pide iniciar sesión, hacerlo en UIAT. El enlace no autoriza por sí solo.'];
        }
        if(array_diff(array_keys($args),['expediente_id',...array_keys(self::FIELDS)]))throw new InvalidArgumentException('Campo no admitido.');
        $fields=[];foreach(self::FIELDS as $name=>$label)if(array_key_exists($name,$args))$fields[$name]=$this->clean($args[$name],$name==='nombre_oficial_ano'?200:180);
        $provenance=[];
        foreach(['modalidad_confirmada'=>'modalidad','consecuencia_confirmada'=>'consecuencia','referencia_confirmada'=>'referencia','fiscalia_confirmada'=>'fiscalia'] as $field=>$column) {
            $stored=trim((string)($source[$column]??''));
            if($stored!=='') {
                if(($fields[$field]??'')!==''&&$fields[$field]!==$stored)throw new InvalidArgumentException('El dato registrado no se puede sobrescribir: '.$column);
                $fields[$field]=$stored;$provenance[$field]=$source['fuentes_contexto'][$column];
            } else $provenance[$field]='aportado_por_usuario_por_falta_de_dato_registrado';
        }
        if(($fields['numero_oficio']??'')!==''&&!preg_match('/^(?=.{1,32}$)(?=.*[0-9])[A-Za-z0-9 .\/-]+$/D',$fields['numero_oficio']))throw new InvalidArgumentException('Número de oficio inválido.');
        $provenance['numero_oficio']='aportado_por_usuario_sin_asignacion_correlativa';
        $yearQuestion=null;
        if(($fields['fecha_emision']??'')!=='') {
            $emissionYear=(int)$this->date($fields['fecha_emision'])->format('Y');$year=$this->queries->nombreDelAno($emissionYear);
            if($year['estado']==='registrado') {
                $registered=$year['nombres'][0];
                if(($fields['nombre_oficial_ano']??'')!==''&&$fields['nombre_oficial_ano']!==$registered)throw new InvalidArgumentException('El nombre del año registrado no se puede sobrescribir.');
                $fields['nombre_oficial_ano']=$registered;$provenance['nombre_oficial_ano']=['tabla'=>'oficio_oficial_ano','campo'=>'nombre','anio'=>$emissionYear];
            } elseif(($fields['nombre_oficial_ano']??'')==='') {
                $yearQuestion=['campo'=>'nombre_oficial_ano','pregunta'=>'Indica el nombre oficial exacto del año de emisión '.$emissionYear.'. No hay una coincidencia única disponible en UIAT.'];
            } else $provenance['nombre_oficial_ano']='aportado_por_usuario_sin_coincidencia_unica_en_catalogo';
        }
        $missing=[];
        if($yearQuestion)$missing[]=$yearQuestion;
        foreach(self::FIELDS as $name=>$label)if(!in_array($name,['lugar_aportado','fecha_accidente_aportada','nombre_oficial_ano'],true)&&($fields[$name]??'')==='')$missing[]=['campo'=>$name,'pregunta'=>$label];
        $place=trim((string)($source['lugar']??''));$date=substr((string)($source['fecha_accidente']??''),0,10);
        if($place!==''&&isset($fields['lugar_aportado']))throw new InvalidArgumentException('El lugar registrado no se puede sobrescribir en este borrador.');
        if($date!==''&&isset($fields['fecha_accidente_aportada']))throw new InvalidArgumentException('La fecha registrada no se puede sobrescribir.');
        $provenance+=['lugar'=>$place!==''?'UIAT: accidentes_activos.lugar':'aportado_por_usuario','fecha_accidente'=>$date!==''?'UIAT: accidentes_activos.fecha_accidente':'aportado_por_usuario','datos_del_destinatario_y_solicitud'=>'aportados_por_usuario_sin_cotejo_automatico'];
        if($place==='')$place=$fields['lugar_aportado']??'';
        if($date==='')$date=$fields['fecha_accidente_aportada']??'';
        if($place==='')$missing[]=['campo'=>'lugar_aportado','pregunta'=>'El lugar falta en UIAT. Confirma el lugar para este borrador.'];
        if($date==='')$missing[]=['campo'=>'fecha_accidente_aportada','pregunta'=>'La fecha falta en UIAT. Confirma YYYY-MM-DD para este borrador.'];
        if($missing)return ['estado'=>'faltan_datos','campos_por_completar'=>$missing,'expediente_id'=>$case,'datos_registrados'=>$source,'fuentes'=>$provenance,'instruccion'=>'Pregunta solo por campos_por_completar. El número debe solicitarse siempre al usuario; no calcularlo ni reutilizar uno anterior. No inventes hechos ni destinatarios y no generes aún un oficio.'];
        foreach(['rango_desde','rango_hasta'] as $key)if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$fields[$key]))throw new InvalidArgumentException('Horario inválido.');
        if($fields['rango_desde']>=$fields['rango_hasta'])throw new InvalidArgumentException('Este borrador admite un rango creciente dentro del mismo día del accidente.');
        $accident=$this->date($date);$emission=$this->date($fields['fecha_emision']);
        if($emission<$accident)throw new InvalidArgumentException('La emisión no puede ser anterior al hecho.');
        $values=['nombre_oficial_ano'=>$fields['nombre_oficial_ano'],'numero_oficio'=>$fields['numero_oficio'],'localidad_emision'=>$fields['localidad_emision'],'oficio_fecha'=>$this->spanish($emission),'oficio_anio'=>$emission->format('Y'),'oficio_grado_cargo'=>$fields['destinatario_cargo'],'oficio_persona_destino'=>$fields['destinatario_nombre'],'entidad_nombre'=>$fields['entidad_destino'],
            'oficio_asunto'=>'Solicita copia de grabaciones de videovigilancia del día '.$this->spanish($accident).' entre las '.$fields['rango_desde'].' a '.$fields['rango_hasta'].' horas.','accidente_lugar'=>$place,'accidente_referencia'=>$fields['referencia_confirmada'],'accidente_modalidad'=>$fields['modalidad_confirmada'],'accidente_consecuencia'=>$fields['consecuencia_confirmada'],'accidente_fecha_abrev'=>$this->spanish($accident),'oficio_rango_desde'=>$fields['rango_desde'],'oficio_rango_hasta'=>$fields['rango_hasta'],'fiscalia_nombre'=>$fields['fiscalia_confirmada']];
        $summary=['tipo_oficio'=>'camaras','expediente_id'=>$case,'registro_sidpol'=>$source['registro_sidpol'],'datos_a_usar'=>$values,'fuentes'=>$provenance,'numero_oficial'=>null,'numero_aportado'=>$fields['numero_oficio'],'firmado'=>false,'registrado_en_uiat'=>false];
        $id=$this->store->preview($actor,$case,$values,$summary);
        return ['estado'=>'pendiente_confirmacion','confirmacion_id'=>$id,'caduca_en_segundos'=>300,'vista_previa'=>$summary,
            'instruccion'=>'Muestra los datos y pide confirmación expresa al usuario. Solo después llama a esta herramienta con expediente_id y confirmacion_id, sin otros campos. Usa únicamente el número aportado por el usuario; no asigna correlativos ni registra, firma o envía documentos.'];
    }
    public function download(string $id,array $session): string {
        $u=$session['user']??[];$actor=$this->users->active((int)($u['id']??0));
        if(!$actor||!isset($u['auth_version'])||$actor->version!==(int)$u['auth_version'])throw new RuntimeException('Borrador no disponible.');
        return $this->store->download($id,$actor,fn(int $case)=>$this->queries->ficha($actor,$case));
    }
    public function discard(string $id,Actor $actor): void {$this->store->discard($id,$actor);}
}
