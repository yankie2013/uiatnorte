<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap/app.php';
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Support\DefuncionReniec;
function checkReniec(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
checkReniec(DefuncionReniec::matches('Inscripción defunción RENIEC'), 'Reconoce inscripción');
checkReniec(DefuncionReniec::matches('Defuncion RENIEC'), 'Reconoce alias sin tildes');
checkReniec(!DefuncionReniec::matches('Informe médico'), 'No afecta otros oficios');
$values = array_fill_keys(array_keys(DefuncionReniec::FIELDS + DefuncionReniec::CERTIFICATE_FIELDS), 'Texto exacto de morgue');
$values['certificado_defuncion_numero']='00012345';
$values['certificado_necropsia_numero']='00098765';
checkReniec(DefuncionReniec::attachmentsText($values)==='Se adjunta copias de Certificado de Defunción General N° 00012345, Certificado de Necropsia N° 00098765 y constancia de inhumación.', 'Formato de adjuntos y ceros iniciales');
$values['fecha_fallecimiento']='26/08/2026';
$values['hora_fallecimiento']='16:30:00 HORAS';
$values['causa_muerte']="HEMORRAGIA AGUDA\nTRAUMATISMO TORÁXICO Y ABDOMINAL CERRADOS";
checkReniec(DefuncionReniec::validate($values)['causa_muerte']===$values['causa_muerte'], 'Conserva descripción de morgue');
foreach (DefuncionReniec::FIELDS + DefuncionReniec::CERTIFICATE_FIELDS as $field => $label) {
    $bad=$values;unset($bad[$field]);$rejected=false;
    try { DefuncionReniec::validate($bad); } catch (InvalidArgumentException) {$rejected=true;}
    checkReniec($rejected, 'Rechaza dato ausente: '.$field);
}
$p=App\Database\Database::connection();
$r=new App\Repositories\OficioRepository($p);
$s=new App\Services\OficioService($r);
$rule=new ReflectionMethod($s, 'asuntoRules');
checkReniec($rule->invoke($s,'Defunción RENIEC','')['requires_fallecido'], 'Exige fallecido en servidor');
$meta=new ReflectionMethod($s,'downloadMetaForAsunto');
checkReniec(str_contains($meta->invoke($s,'Defunción RENIEC','','SOLICITAR')['url'],'word_oficio_defuncion_reniec.php'), 'Ofrece descarga Word');
$t=new PhpOffice\PhpWord\TemplateProcessor(dirname(__DIR__).'/plantillas/oficio_defuncion_reniec.docx');
foreach(array_keys(DefuncionReniec::FIELDS) as $field)checkReniec(in_array($field,$t->getVariables(),true),'Plantilla tiene '.$field);
foreach($t->getVariables() as $key)$t->setValue($key,htmlspecialchars(($key === 'adjuntos' ? DefuncionReniec::attachmentsText($values) : ($values[$key] ?? 'PRUEBA')),ENT_XML1|ENT_QUOTES,'UTF-8'));
$sample=sys_get_temp_dir().'/reniec-prueba.docx';$t->saveAs($sample);
$z=new ZipArchive();$z->open($sample);$xml=$z->getFromName('word/document.xml');$z->close();
checkReniec(!str_contains($xml,'${'),'Word completo sin marcadores');
$d=new DOMDocument();checkReniec($d->loadXML($xml),'XML válido');
foreach(['CASTILLO VASQUEZ','07125059','2001024678','HARO AMBROSIO'] as $example)checkReniec(!str_contains($xml,$example),'Retira datos del ejemplo');
// Roll back all storage checks: no test oficio is left behind.
$p->beginTransaction();
try {
    $id=(int)$p->query('SELECT id FROM oficios ORDER BY id DESC LIMIT 1')->fetchColumn();
    checkReniec($id>0,'Existe oficio para comprobar recuperación');
    $p->prepare('DELETE FROM oficio_defuncion_reniec WHERE oficio_id=?')->execute([$id]);
    $p->prepare('INSERT INTO oficio_defuncion_reniec (oficio_id,datos) VALUES (?,?)')->execute([$id,json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    checkReniec($s->defaultData($r->find($id))['reniec']===$values,'Editar recupera los datos guardados');
} finally {$p->rollBack();}
echo "PASS: campos obligatorios, texto de morgue, recuperación y plantilla Word RENIEC.\n";
