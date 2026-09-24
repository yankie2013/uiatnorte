<?php
declare(strict_types=1);
namespace App\Support;
use DOMDocument;
use DOMXPath;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

/** Personaliza una copia temporal de la plantilla ANTES de incorporar datos del expediente. */
final class ResponsibleTemplateProcessor extends TemplateProcessor
{
    public function __construct($documentTemplate) {
        $copy=tempnam(sys_get_temp_dir(),'uiat_tpl_');
        if($copy===false || !copy($documentTemplate,$copy))throw new RuntimeException('No se pudo preparar la plantilla.');
        try {
            $profile=self::contextProfile();
            $zip=new ZipArchive();if($zip->open($copy)!==true)throw new RuntimeException('Plantilla Word no disponible.');
            for($i=0;$i<$zip->numFiles;$i++) {
                $name=$zip->getNameIndex($i);
                if(!preg_match('~^word/(document|header\d*|footer\d*)\.xml$~',$name))continue;
                $xml=$zip->getFromIndex($i);if(!is_string($xml))continue;
                $dom=new DOMDocument();$dom->preserveWhiteSpace=true;
                if(!$dom->loadXML($xml,LIBXML_NONET))throw new RuntimeException('Plantilla XML inválida.');
                $xp=new DOMXPath($dom);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $afterName=false;$changed=false;
                foreach($xp->query('//w:p') as $paragraph) {
                    $nodes=iterator_to_array($xp->query('.//w:t',$paragraph));
                    $text=implode('',array_map(fn($n)=>$n->textContent,$nodes));
                    $namePattern='/Giancarlo\s+(?:(?:Jorge|J\.)\s+)?MERINO\s+SANCHO/iu';
                    $hasName=(bool)preg_match($namePattern,$text);
                    $replacements=[];
                    if($hasName) {
                        if(!$profile)throw new RuntimeException('No se identificó al responsable del expediente para esta plantilla.');
                        $replacements[$namePattern]=(string)$profile['nombre'];
                    }
                    if($profile && ($hasName || $afterName))$replacements['/ST3\.?\s*PNP\.?|ST3\.(?=\s)/iu']=(string)($profile['grado']??'');
                    if($profile) {
                        $replacements['/(?:SA\s*[-–]\s*)?31486778(?![0-9@])/u']=($profile['cip']??'')!==''?(string)$profile['cip']:'CIP no consignado';
                        $replacements['/Unidad de Investigaci[oó]n de Accidentes de Tr[aá]nsito Norte|UIAT\s+Norte/iu']=(string)($profile['unidad']??'DEPIAT');
                        $replacements['/986571975/u']=($profile['telefono']??'')!==''?(string)$profile['telefono']:'teléfono no consignado';
                        $replacements['/31486778[^\s<]*@[^\s<]+/u']=(string)($profile['email']??'correo no consignado');
                    }
                    foreach($replacements as $pattern=>$replacement) {
                        $current=implode('',array_map(fn($n)=>$n->textContent,$nodes));
                        if(!preg_match_all($pattern,$current,$matches,PREG_OFFSET_CAPTURE))continue;
                        foreach(array_reverse($matches[0]) as [$match,$offset]) {
                            $start=$offset;$end=$offset+strlen($match);$cursor=0;$inserted=false;
                            foreach($nodes as $node) {
                                $value=$node->textContent;$length=strlen($value);$nodeEnd=$cursor+$length;
                                if($nodeEnd>$start && $cursor<$end) {
                                    $left=substr($value,0,max(0,$start-$cursor));$right=substr($value,min($length,max(0,$end-$cursor)));
                                    $node->textContent=$left.(!$inserted?$replacement:'').$right;$inserted=true;
                                    $node->setAttribute('xml:space','preserve');
                                }
                                $cursor=$nodeEnd;
                            }
                            $changed=true;
                        }
                    }
                    $afterName=$hasName;
                }
                if($changed)$zip->addFromString($name,$dom->saveXML());
            }
            $zip->close();parent::__construct($copy);
        } finally {if(is_file($copy))unlink($copy);}
    }
    private static function contextProfile(): ?array {
        if (is_array($GLOBALS['data']['responsable_documento'] ?? null)) return $GLOBALS['data']['responsable_documento'];
        foreach(['oficio','row','acta','manifestacion','registro'] as $key) {
            $row=$GLOBALS[$key]??null;
            if(is_array($row) && !empty($row['responsable_documento']))return Access::documentProfile($row);
        }
        foreach(['oficio','row','acta','manifestacion','registro'] as $key) {
            $row=$GLOBALS[$key]??null;
            if(is_array($row) && !empty($row['accidente_id']))return Access::profile((int)$row['accidente_id']);
        }
        $case=(int)($GLOBALS['accidenteId']??$GLOBALS['accidente_id']??$GLOBALS['accId']??$_GET['accidente_id']??0);
        if(!$case && isset($GLOBALS['acc']['id']))$case=(int)$GLOBALS['acc']['id'];
        if(!$case && isset($GLOBALS['accidente']['id']))$case=(int)$GLOBALS['accidente']['id'];
        return $case>0?Access::profile($case):null;
    }
}
