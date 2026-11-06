<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
function h($value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
foreach (['Solicitar'=>'oficio','Remitir'=>'oficio','Citar'=>'citacion','Realizar'=>'actas',''=>'editar'] as $action=>$expected) {
    $row=['accion'=>$action];$id=7;$embed=true;$returnTo='diligencias_pendientes.php';
    ob_start();require dirname(__DIR__).'/app/Views/diligencias/action_options.php';$html=ob_get_clean();
    $dom=new DOMDocument();@$dom->loadHTML($html);$x=new DOMXPath($dom);$links=$x->query('//a');
    if($links->length!==1)throw new RuntimeException('Opciones incorrectas para '.$action);
    $href=$links->item(0)->getAttribute('href');parse_str((string)parse_url($href,PHP_URL_QUERY),$query);
    if(($query['id']??'')!=='7' || ($query['embed']??'')!=='1')throw new RuntimeException('Se perdió la diligencia o el modo modal');
    if($expected==='editar'){
        if(!str_starts_with($href,'diligenciapendiente_editar.php?'))throw new RuntimeException('No inventar acción para diligencias sin acción');
    }elseif(($query['mode']??'')!==$expected)throw new RuntimeException('Documento incorrecto para '.$action);
}
echo "PASS: oficio para Solicitar/Remitir, citación para Citar, actas para Realizar y edición si falta acción.\n";
