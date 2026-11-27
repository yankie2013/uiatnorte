<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\AccidentPlace;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;
use RuntimeException;
use ZipArchive;

/** Builds the seven analysis components using paragraph patterns from the user's models. */
final class AnalisisWordService
{
    public const TITLES = [
        'descripcion'=>'I. DESCRIPCIÓN ANALÍTICA',
        'velocidad'=>'A. DETERMINACIÓN DE VELOCIDAD DE LOS VEHICULOS PARTICIPANTES',
        'danos-lesiones'=>'A. ANÁLISIS COMPARATIVO DE DAÑOS Y LESIONES',
        'evidencias'=>'A. ANÁLISIS DE EVIDENCIAS.',
        'integral'=>'A. ANÁLISIS INTEGRAL',
        'causalidad'=>'I. CAUSALIDAD DEL ACCIDENTE',
        'infracciones'=>'INFRACCIONES ADMINISTRATIVAS',
    ];
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private DOMDocument $dom;
    private DOMXPath $xp;
    private DOMElement $body;
    private array $patterns = [];
    private ZipArchive $zip;
    private array $context = [];
    private int $imageIndex = 0;

    public static function load(PDO $pdo, int $case, string $section): array
    {
        if (!isset(self::TITLES[$section])) throw new RuntimeException('Apartado no válido.');
        $query = static function (string $sql, array $params = []) use ($pdo): array {
            $stmt=$pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };
        $accident = $query('SELECT a.*, d.nombre AS distrito FROM accidentes_activos a LEFT JOIN ubigeo_distrito d ON d.cod_dep=a.cod_dep AND d.cod_prov=a.cod_prov AND d.cod_dist=a.cod_dist WHERE a.id=?', [$case])[0] ?? null;
        if (!$accident) throw new RuntimeException('Expediente no disponible.');
        $saved = $query('SELECT seccion, contenido FROM accidente_analisis_informe WHERE accidente_id=?', [$case]);
        $sections=[];
        foreach ($saved as $row) $sections[$row['seccion']]=json_decode($row['contenido'], true, 32, JSON_THROW_ON_ERROR);
        $data = $sections[$section] ?? [];
        if ($section==='infracciones' && !isset($sections[$section])) {
            $data=['infracciones'=>$sections['causalidad']['infracciones'] ?? []];
        }
        $vehicles=[];
        foreach ($query('SELECT v.id, v.placa, tv.nombre AS tipo FROM involucrados_vehiculos_activos iv JOIN vehiculos v ON v.id=iv.vehiculo_id LEFT JOIN tipos_vehiculo tv ON tv.id=v.tipo_id WHERE iv.accidente_id=?', [$case]) as $v) $vehicles[(int)$v['id']]=$v;
        $people=[];
        foreach ($query('SELECT ip.id, ip.vehiculo_id, pp.Nombre AS rol, p.nombres, p.apellido_paterno, p.apellido_materno FROM involucrados_personas_activos ip JOIN personas p ON p.id=ip.persona_id LEFT JOIN participacion_persona pp ON pp.Id=ip.rol_id WHERE ip.accidente_id=?', [$case]) as $p) {
            $p['nombre']=trim(implode(' ',[$p['apellido_paterno'],$p['apellido_materno'],$p['nombres']]));
            $people[(int)$p['id']]=$p;
        }
        if ($section==='descripcion' && !isset($data['contenido'])) {
            $itp=$query("SELECT * FROM itp_activos WHERE accidente_id=? ORDER BY COALESCE(fecha_itp,'9999-12-31') DESC, COALESCE(hora_itp,'23:59:59') DESC, id DESC LIMIT 1",[$case])[0] ?? [];
            $data=['contenido'=>self::descriptionFromItp($itp)];
        }
        return [$data, ['accidente'=>$accident,'vehicles'=>$vehicles,'people'=>$people]];
    }

    public static function descriptionFromItp(array $itp): array
    {
        $result=['inspeccion'=>['fecha'=>$itp['fecha_itp']??'','hora'=>substr($itp['hora_itp']??'',0,5),'llegada'=>$itp['llegada_lugar']??''], 'vias'=>[], 'consideraciones'=>[], 'aproximaciones'=>[], 'posiciones'=>[]];
        if (!$itp) return $result;
        foreach ([1,2] as $n) {
            $suffix='_via'.$n;
            if ($n===2 && !array_filter($itp, static fn($v,$k)=>str_ends_with((string)$k,$suffix) && trim((string)$v)!=='', ARRAY_FILTER_USE_BOTH)) continue;
            $road=['nombre'=>$n===1?(($itp['forma_via']??'')?:'Vía 1'):'Vía 2'];
            foreach (['descripcion','configuracion','material','ordenamiento','iluminacion','visibilidad','intensidad','fluidez','medidas','observaciones'] as $key) $road[$key]=$itp[$key.$suffix]??'';
            $road['control']=$itp['senializacion'.$suffix]??$itp['señalizacion'.$suffix]??'';
            $result['vias'][]=$road;
        }
        foreach (self::lines((string)($itp['localizacion_unidades']??''),true) as $line) {
            $parts=explode(':',$line,2); $result['posiciones'][]=['unidad'=>count($parts)===2?trim($parts[0]):'Unidad involucrada','ubicacion'=>trim($parts[count($parts)-1])];
        }
        return $result;
    }

    public function generate(string $section, array $data, array $context, string $output): void
    {
        if (!isset(self::TITLES[$section])) throw new RuntimeException('Apartado no válido.');
        $template=dirname(__DIR__,2).'/plantillas/analisis/'.$section.'.docx';
        if (!is_file($template) || !copy($template,$output)) throw new RuntimeException('No se encontró el modelo Word del apartado.');
        $this->context=$context; $this->patterns=[]; $this->imageIndex=0; $this->zip=new ZipArchive();
        if ($this->zip->open($output)!==true) throw new RuntimeException('No se pudo abrir el documento.');
        try {
            $this->dom=new DOMDocument();
            if (!$this->dom->loadXML($this->zip->getFromName('word/document.xml'),LIBXML_NONET)) throw new RuntimeException('Modelo Word no válido.');
            $this->xp=new DOMXPath($this->dom); $this->xp->registerNamespace('w',self::W);
            $this->body=$this->xp->query('/w:document/w:body')->item(0);
            foreach ($this->xp->query('w:sdt',$this->body) as $sdt) {
                $role=$this->xp->query('w:sdtPr/w:tag',$sdt)->item(0)->getAttributeNS(self::W,'val');
                $this->patterns[$role]=$this->xp->query('w:sdtContent/w:p',$sdt)->item(0)->cloneNode(true);
            }
            $properties=$this->xp->query('w:sectPr',$this->body)->item(0)->cloneNode(true);
            while ($this->body->firstChild) $this->body->removeChild($this->body->firstChild);
            if ($section==='velocidad' && !empty($data['riesgos'])) {
                $this->p('subtitulo','Condiciones de riesgo'); $this->items($data['riesgos']);
            }
            $this->p('titulo',self::TITLES[$section]);
            switch ($section) {
                case 'descripcion': $this->description($data['contenido']??[]); break;
                case 'velocidad':
                    $intro=$this->xp->query('.//w:t',$this->patterns['intro'])->item(0)->textContent;
                    $this->p('intro',$intro);
                    foreach ($data['parrafos']??[] as $i=>$item) $this->p('lista',self::letter($i).'. '.$item['texto']);
                    foreach ($data['estimaciones']??[] as $item) {
                        $vehicle=$context['vehicles'][(int)$item['vehiculo_id']]??[];
                        $this->p('subtitulo','Para el '.$this->vehicle($vehicle));
                        $this->p('texto',$item['desplazamiento']);
                    }
                    break;
                case 'danos-lesiones':
                    $this->p('subtitulo','1. Se tomará en cuenta lo siguiente:'); $this->items($data['consideraciones']??[]);
                    foreach ($data['parrafos']??[] as $i=>$item) $this->p('texto',($i+2).'. '.$item['texto']);
                    $this->p('subtitulo',(count($data['parrafos']??[])+2).'. Lo analizado en los puntos que anteceden permite determinar:');
                    $this->items($data['determinaciones']??[]); break;
                case 'evidencias':
                    foreach ($data['evidencias']??[] as $i=>$item) {
                        $this->p('subtitulo',($i+1).'. Evidencia '.$item['tipo'].' ('.$item['detalle'].')');
                        $this->p('texto',$item['ubicacion']);
                        foreach (self::lines($item['caracteristicas']) as $line) $this->p('lista','– '.$line);
                        $this->p('texto',$item['consecuencia']); $this->p('texto',$item['determinacion']);
                    }
                    break;
                case 'integral': foreach ($data['parrafos']??[] as $i=>$item) $this->p('texto',($i+1).'. '.$item['texto']); break;
                case 'causalidad':
                    $this->p('subtitulo','A. FACTORES INTERVINIENTES');
                    foreach ($data['factores']??[] as $i=>$item) {
                        $this->p('apartado',($i+1).'. Factor '.mb_strtolower($item['tipo']));
                        $p=$context['people'][(int)$item['involucrado_id']]??[];
                        if ($p) $this->p('texto',($p['nombre']??'').' · '.($p['rol']??''));
                        $this->p('texto',$item['descripcion']);
                    }
                    break;
                case 'infracciones': $this->infractions($data); break;
            }
            $this->body->appendChild($properties);
            $this->zip->addFromString('word/document.xml',$this->dom->saveXML());
            $settings=new DOMDocument();
            if ($settings->loadXML($this->zip->getFromName('word/settings.xml'),LIBXML_NONET)) {
                $xpath=new DOMXPath($settings); $xpath->registerNamespace('w',self::W);
                $node=$xpath->query('//w:updateFields')->item(0);
                if (!$node) {$node=$settings->createElementNS(self::W,'w:updateFields');$settings->documentElement->appendChild($node);}
                $node->setAttributeNS(self::W,'w:val','true');$this->zip->addFromString('word/settings.xml',$settings->saveXML());
            }
        } finally { $this->zip->close(); }
    }

    private function p(string $role, string $text): void
    {
        if (trim($text)==='') return;
        // Real paragraphs avoid stretched justified lines when an article contains Enter.
        if (preg_match('/[\r\n]/', $text)) {
            foreach (preg_split('/\r\n|\r|\n/', $text) as $line) $this->p($role, $line);
            return;
        }
        $pattern=$this->patterns[$role]??$this->patterns['texto']??$this->patterns['titulo'];
        $p=$this->dom->createElementNS(self::W,'w:p');
        $props=$this->xp->query('w:pPr',$pattern)->item(0);
        if ($props) $p->appendChild($props->cloneNode(true));
        $pPr=$this->xp->query('w:pPr',$p)->item(0);
        if (!$pPr) {$pPr=$this->dom->createElementNS(self::W,'w:pPr');$p->appendChild($pPr);}
        foreach (iterator_to_array($this->xp->query('w:numPr|w:sectPr|w:pageBreakBefore|w:keepNext',$pPr)) as $node) $pPr->removeChild($node);
        // Suppress inherited list numbering: each component has independent explicit labels.
        $num=$this->dom->createElementNS(self::W,'w:numPr');$id=$this->dom->createElementNS(self::W,'w:numId');$id->setAttributeNS(self::W,'w:val','0');$num->appendChild($id);
        $before=null;
        foreach ($pPr->childNodes as $node) {
            if ($node instanceof DOMElement && !in_array($node->localName,['pStyle','keepNext','keepLines','pageBreakBefore','framePr','widowControl'],true)) {$before=$node;break;}
        }
        $pPr->insertBefore($num,$before);
        if (in_array($role,['titulo','subtitulo','apartado','subapartado','adicional','anexos'],true)) {
            $before=$this->xp->query('*[not(self::w:pStyle)]',$pPr)->item(0);
            $pPr->insertBefore($this->dom->createElementNS(self::W,'w:keepNext'),$before);
        }
        $run=$this->dom->createElementNS(self::W,'w:r');
        $rPr=$this->xp->query('w:r/w:rPr',$pattern)->item(0);if($rPr)$run->appendChild($rPr->cloneNode(true));
        foreach (preg_split('/\r\n|\r|\n/',$text) as $i=>$line) {
            if ($i) $run->appendChild($this->dom->createElementNS(self::W,'w:br'));
            $t=$this->dom->createElementNS(self::W,'w:t');$t->setAttributeNS('http://www.w3.org/XML/1998/namespace','xml:space','preserve');
            $t->appendChild($this->dom->createTextNode(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',$line)));
            $run->appendChild($t);
        }
        $p->appendChild($run);$this->body->appendChild($p);
    }
    private function items(array $items): void { foreach($items as $item)$this->p('lista','– '.$item['texto']); }
    private static function lines(string $text, bool $csv=false): array {
        return array_values(array_filter(array_map(static fn($s)=>trim(preg_replace('/^\s*[-–•]\s*/u','',$s)),preg_split($csv?'/\r?\n|,/u':'/\r?\n/u',$text)),static fn($s)=>$s!==''));
    }
    private static function letter(int $index): string {
        $out='';do{$out=chr(97+$index%26).$out;$index=intdiv($index,26)-1;}while($index>=0);return $out;
    }
    private function vehicle(array $v): string {
        return mb_strtolower(trim((string)($v['tipo']??''))?:'vehículo').' de placa de rodaje '.(trim((string)($v['placa']??''))?:'sin placa registrada');
    }
    private function description(array $data): void
    {
        $inspection=$data['inspeccion']??[];$accident=$this->context['accidente']??[];
        $place=AccidentPlace::format(trim((string)($inspection['lugar']??''))?:($accident['lugar']??''),$accident['distrito']??'');
        $parts=[];
        if (!empty($inspection['fecha'])) {$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$inspection['fecha']);$parts[]='Realizada el '.($date?$date->format('d/m/Y'):$inspection['fecha']);}
        if (!empty($inspection['hora'])) $parts[]='a las '.$inspection['hora'].' horas aproximadamente';
        if ($place!=='') $parts[]='en '.$place;
        $this->p('subtitulo','A. INSPECCIÓN TÉCNICO POLICIAL');
        if ($parts) $this->p('intro',implode(', ',$parts).', constatándose lo siguiente:');
        foreach (['llegada'=>'1. Llegada al lugar del evento','metodo'=>'2. Método utilizado de ingreso a la escena del evento','perennizacion'=>'3. Perennización'] as $key=>$title) {
            $this->p('apartado',$title);
            if ($key==='perennizacion') foreach(self::lines($inspection[$key]??'') as $line)$this->p('lista','– '.$line);
            else $this->p('texto',$inspection[$key]??'');
        }
        $this->p('apartado','4. Referente a la vía');
        foreach ($data['vias']??[] as $road) {
            $this->p('subapartado',$road['nombre']??'Vía');$this->p('texto',$road['descripcion']??'');
            $labels=['configuracion'=>'a. Configuración','material'=>'b. Material y estado de la calzada','control'=>'c. Dispositivo control de tránsito','ordenamiento'=>'d. Ordenamiento del tránsito','maniobrabilidad'=>'e. Área de maniobrabilidad','iluminacion'=>'f. Iluminación','visibilidad'=>'g. Visibilidad','intensidad'=>'h. Intensidad vehicular','fluidez'=>'i. Fluidez vehicular'];
            foreach($labels as $key=>$label) if(trim($road[$key]??'')!=='')$this->p('atributo',$label.': '.$road[$key]);
            foreach(['medidas'=>'Medidas','observaciones'=>'Observaciones'] as $key=>$label)if(!empty($road[$key])){$this->p('subapartado',$label);$this->p('texto',$road[$key]);}
            if(!empty($road['fotografia']))$this->picture($road['fotografia'],$road['pie']??'');
            elseif(!empty($road['pie']))$this->p('pie',$road['pie']);
        }
        $this->p('apartado','5. Condiciones climáticas');$this->p('texto',$inspection['clima']??'');
        $this->p('apartado','6. Zona de conflicto');
        if(!empty($data['consideraciones'])){$this->p('subapartado','a. Teniendo en consideración lo siguiente:');$this->items($data['consideraciones']);}
        if(!empty($data['aproximaciones'])){$this->p('subapartado','b. Se establece por aproximación:');foreach($data['aproximaciones'] as $i=>$item){$this->p('subapartado',($i+1).') '.$item['titulo']);$this->p('texto',$item['descripcion']);}}
        $this->p('apartado','7. Posiciones finales');
        foreach($data['posiciones']??[] as $i=>$item){$this->p('subapartado',self::letter($i).'. '.$item['unidad']);$this->p('texto',$item['ubicacion']);}
    }
    private function infractions(array $data): void
    {
        $evaluations=$data['evaluaciones']??null;
        if($evaluations===null){
            $legacy=[];foreach($data['infracciones']??[] as $item){$id=(int)$item['involucrado_id'];$legacy[$id]??=['involucrado_id'=>$id,'vehiculo_id'=>$item['vehiculo_id']??'','incurso'=>'si','articulos'=>[]];
                preg_match('/^(?:art[íi]culo\s*)?([\d]+(?:\.[\d]+)*[A-Za-z]?)[.°º\s:–-]*(.*)$/iu',$item['codigo']??'',$m);
                $legacy[$id]['articulos'][]=['numero'=>$m[1]??($item['codigo']??''),'titulo'=>($m[2]??'')?:($item['codigo']??''),'contenido'=>$item['descripcion']??''];}
            $evaluations=array_values($legacy);
        }
        foreach($evaluations as $item){
            $person=$this->context['people'][(int)$item['involucrado_id']]??[];$role=mb_strtolower($person['rol']??'involucrado');
            $v=$this->context['vehicles'][(int)($item['vehiculo_id']??0)]??null;
            $this->p('subtitulo','Para el '.$role.($v?' del '.$this->vehicle($v):'').' · '.($person['nombre']??'Involucrado no disponible'));
            $yes=$item['incurso']==='si';
            $this->p('intro','El '.$role.($yes?' se encuentra':' no se encuentra').' incurso en el Reglamento Nacional de Tránsito-Código de Tránsito, D.S. Nro. 016-20009-MTC'.($yes?', artículos:':'.'));
            if($yes)foreach($item['articulos']??[] as $article){$this->p('apartado','Artículo '.$article['numero'].'.- '.$article['titulo']);$this->p('texto',$article['contenido']);}
        }
        if(!empty($data['informacion'])){$this->p('adicional','INFORMACIÓN ADICIONAL');foreach($data['informacion'] as $item)$this->p('texto',$item['texto']);}
        if(!empty($data['anexos'])){$this->p('anexos','ANEXOS');$this->items($data['anexos']);}
    }
    private function picture(string $reference, string $caption): void
    {
        // Only local, user-uploaded images: no network requests or filesystem escape via saved URLs.
        $root=dirname(__DIR__,2);$relative=$reference;
        if(preg_match('~^https?://~i',$reference)){
            $path=(string)parse_url($reference,PHP_URL_PATH);$pos=strpos($path,'/uploads/');
            $relative=$pos===false?'':substr($path,$pos+1);
        }
        $uploads=realpath($root.'/uploads');$file=$relative!==''?realpath($root.'/'.$relative):false;
        $size=$file&&$uploads&&str_starts_with($file,$uploads.DIRECTORY_SEPARATOR)&&is_file($file)?@getimagesize($file):false;
        if(!$size || !in_array($size['mime']??'',['image/jpeg','image/png'],true)){
            $this->p('pie',($caption!==''?$caption.' — ':'').'Fotografía: '.$reference);return;
        }
        $n=++$this->imageIndex;$id='rIdAnalysisPhoto'.$n;$ext=$size['mime']==='image/png'?'png':'jpg';$name='analisis-foto-'.$n.'.'.$ext;
        $this->zip->addFile($file,'word/media/'.$name);
        $rels=new DOMDocument();$rels->loadXML($this->zip->getFromName('word/_rels/document.xml.rels'),LIBXML_NONET);
        $r=$rels->createElementNS('http://schemas.openxmlformats.org/package/2006/relationships','Relationship');
        foreach(['Id'=>$id,'Type'=>'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image','Target'=>'media/'.$name] as $k=>$v)$r->setAttribute($k,$v);
        $rels->documentElement->appendChild($r);$this->zip->addFromString('word/_rels/document.xml.rels',$rels->saveXML());
        $types=new DOMDocument();$types->loadXML($this->zip->getFromName('[Content_Types].xml'),LIBXML_NONET);$found=false;
        foreach($types->getElementsByTagName('Default') as $entry)if($entry->getAttribute('Extension')===$ext)$found=true;
        if(!$found){$entry=$types->createElementNS('http://schemas.openxmlformats.org/package/2006/content-types','Default');$entry->setAttribute('Extension',$ext);$entry->setAttribute('ContentType',$size['mime']);$types->documentElement->appendChild($entry);$this->zip->addFromString('[Content_Types].xml',$types->saveXML());}
        $ratio=min(420/$size[0],300/$size[1]);$cx=(int)round($size[0]*$ratio*9525);$cy=(int)round($size[1]*$ratio*9525);
        $xml='<w:p xmlns:w="'.self::W.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:pPr><w:keepNext/><w:jc w:val="center"/></w:pPr><w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><wp:extent cx="'.$cx.'" cy="'.$cy.'"/><wp:docPr id="'.(10000+$n).'" name="Fotografía '.$n.'"/><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="0" name="'.$name.'"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="'.$id.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
        $picture=new DOMDocument();$picture->loadXML($xml,LIBXML_NONET);$this->body->appendChild($this->dom->importNode($picture->documentElement,true));
        if($caption!=='')$this->p('pie',$caption);
    }
}
