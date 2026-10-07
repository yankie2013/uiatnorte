<?php
declare(strict_types=1);
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
require_once __DIR__.'/vendor/autoload.php';
if(!class_exists(\PhpOffice\PhpWord\PhpWord::class) && is_file(__DIR__.'/PHPWord-1.4.0/vendor/autoload.php'))require_once __DIR__.'/PHPWord-1.4.0/vendor/autoload.php';
use App\Support\Access;
$id=(int)($_GET['accidente_id']??0);
$st=$pdo->prepare('SELECT c.creado_por,c.jefe_id FROM comunicaciones_guardia c JOIN accidentes a ON a.id=c.accidente_id WHERE a.id=? AND c.eliminado_en IS NULL AND a.eliminado_en IS NULL');$st->execute([$id]);$record=$st->fetch();
$allowed=$record && $record['jefe_id'] && (Access::admin() || (Access::role()==='guardia' && (int)$record['creado_por']===Access::id()) || Access::canViewWorkspaceCase($id));
if(!$allowed){http_response_code(403);exit('La sumilla está disponible después de entregar el registro.');}
$tmp=null;
try{
 $word=(new App\Services\GuardiaSumillaService($pdo))->build($id);
 $tmp=tempnam(sys_get_temp_dir(),'sumilla_');
 if($tmp===false)throw new RuntimeException('No se pudo preparar la descarga.');
 \PhpOffice\PhpWord\IOFactory::createWriter($word,'Word2007')->save($tmp);
 while(ob_get_level())ob_end_clean();
 header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
 header('Content-Disposition: attachment; filename="Sumilla_ingreso_'.$id.'.docx"');header('Content-Length: '.filesize($tmp));header('Cache-Control: private, no-store');
 readfile($tmp);
}catch(Throwable $e){error_log('Sumilla guardia: '.$e->getMessage());http_response_code(500);echo 'No se pudo generar la sumilla. Revisa el registro del servidor.';}
finally{if(is_string($tmp)&&is_file($tmp))unlink($tmp);}
