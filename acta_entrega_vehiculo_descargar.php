<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/word_filename_helper.php';

use App\Repositories\ActaRepository;
use PhpOffice\PhpWord\TemplateProcessor;

function acta_name(array $r, string $prefix): string {
    return trim(preg_replace('/\s+/u', ' ', trim((string) ($r[$prefix . '_nombres'] ?? '') . ' ' . (string) ($r[$prefix . '_apellido_paterno'] ?? '') . ' ' . (string) ($r[$prefix . '_apellido_materno'] ?? ''))) ?? '');
}
function acta_date(string $date): string {
    $months=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre']; $t=strtotime($date);
    return $t ? date('j',$t).' de '.$months[(int)date('n',$t)-1].' de '.date('Y',$t) : '';
}
function acta_date_abbrev(string $date): string {
    $months=['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC']; $t=strtotime($date);
    return $t ? date('d',$t).$months[(int)date('n',$t)-1].date('Y',$t) : '';
}
function acta_composite(array $vehicles, string $field): string {
    $values = [];
    foreach ($vehicles as $vehicle) {
        $value = trim((string) ($vehicle[$field] ?? ''));
        if ($value !== '') $values[] = $value;
    }
    return implode('/', $values);
}
function acta_dimensions(array $vehicle): string {
    $parts = [];
    foreach (['largo_mm'=>'L','ancho_mm'=>'A','alto_mm'=>'H'] as $field=>$label) {
        $value = trim((string) ($vehicle[$field] ?? ''));
        if ($value !== '') $parts[] = $label . ': ' . $value;
    }
    return implode(' x ', $parts);
}

$id = (int) ($_GET['id'] ?? 0);
$repo = new ActaRepository($pdo);
$row = $repo->find($id);
if (!$row) { http_response_code(404); exit('Acta no encontrada'); }
$template = is_file(__DIR__ . '/plantillas/acta_entrega_vehiculo.docx')
    ? __DIR__ . '/plantillas/acta_entrega_vehiculo.docx'
    : __DIR__ . '/acta_entrega_vehiculo.docx';
if (!is_file($template)) { http_response_code(500); exit('Falta la plantilla acta_entrega_vehiculo.docx'); }

$vehicles = $repo->vehicles((int) $row['accidente_id']);
$deliveryVehicles = $repo->deliveryVehicles((int) $row['involucrado_vehiculo_id']);
$vehicleLines = array_map(static fn(array $v): string => trim($v['orden_participacion'].' - placa '.$v['placa'].(!empty($v['color'])?' - color '.$v['color']:'')), $vehicles);
$usesConductorAsOwner = empty($row['propietario_vehiculo_id']);
$ownerType = $usesConductorAsOwner ? 'CONDUCTOR' : (string) $row['tipo_propietario'];
$ownerName = $usesConductorAsOwner
    ? acta_name($row, 'conductor')
    : ($row['tipo_propietario'] === 'JURIDICA' ? trim((string) $row['razon_social']) : acta_name($row, 'propietario'));
$ownerDocType = $usesConductorAsOwner ? (string) $row['conductor_tipo_doc'] : ($row['tipo_propietario'] === 'JURIDICA' ? 'RUC' : (string) $row['propietario_tipo_doc']);
$ownerDoc = $usesConductorAsOwner ? (string) $row['conductor_num_doc'] : ($row['tipo_propietario'] === 'JURIDICA' ? (string) $row['ruc'] : (string) $row['propietario_num_doc']);
$ownerAddress = $usesConductorAsOwner ? $row['conductor_domicilio'] : ($row['tipo_propietario'] === 'JURIDICA' ? $row['domicilio_fiscal'] : $row['propietario_domicilio']);
$ownerPhone = $usesConductorAsOwner ? $row['conductor_celular'] : $row['propietario_celular'];
$ownerEmail = $usesConductorAsOwner ? $row['conductor_email'] : $row['propietario_email'];
$representativeName = !$usesConductorAsOwner && $row['tipo_propietario'] === 'JURIDICA' ? acta_name($row, 'representante') : '';
$district = trim((string) ($row['accidente_distrito'] ?? '')) ?: 'distrito no consignado';
$introDate = acta_date_abbrev((string) $row['fecha_entrega']);
$introStart = substr((string) $row['hora_inicio'], 0, 5);
$ownerAddress = trim((string) $ownerAddress) ?: 'domicilio no consignado';
$presentationOpening = "En el distrito de {$district}, siendo las {$introStart} horas del {$introDate}, presente ante el instructor, la persona de ";
$presentationPerson = $ownerName;
$presentationCompany = '';
$presentationAfterPerson = '';
$presentationClosing = '';
if (!$usesConductorAsOwner && $row['tipo_propietario'] === 'JURIDICA') {
    $presentationPerson = $representativeName;
    $presentationCompany = (string) $row['razon_social'];
    $presentationAfterPerson = ", con {$row['representante_tipo_doc']} N° {$row['representante_num_doc']}, en calidad de {$row['rol_legal']} de la empresa ";
    $presentationClosing = ", identificada con RUC N° {$row['ruc']} y con domicilio fiscal en " . (trim((string) $row['domicilio_fiscal']) ?: 'domicilio no consignado') . ", en calidad de propietario del vehículo de placa de rodaje " . acta_composite($deliveryVehicles, 'placa') . ", realizándose la entrega del siguiente vehículo motorizado:";
} else {
    $presentationAfterPerson = ", con {$ownerDocType} N° {$ownerDoc} y domicilio en {$ownerAddress}, en calidad de propietario del vehículo de placa de rodaje " . acta_composite($deliveryVehicles, 'placa') . ", realizándose la entrega del siguiente vehículo motorizado:";
}
$ownerPresentation = $presentationOpening . $presentationPerson . $presentationAfterPerson . $presentationCompany . $presentationClosing;
$composite = [
    'placa' => acta_composite($deliveryVehicles, 'placa'),
    'clase' => acta_composite($deliveryVehicles, 'clase'),
    'categoria' => acta_composite($deliveryVehicles, 'categoria'),
    'carroceria' => acta_composite($deliveryVehicles, 'carroceria'),
    'marca' => acta_composite($deliveryVehicles, 'marca'),
    'modelo' => acta_composite($deliveryVehicles, 'modelo'),
    'anio' => acta_composite($deliveryVehicles, 'anio'),
    'color' => acta_composite($deliveryVehicles, 'color'),
    'vin' => acta_composite($deliveryVehicles, 'serie_vin'),
    'motor' => acta_composite($deliveryVehicles, 'nro_motor'),
    'dimensiones' => implode('/', array_map('acta_dimensions', $deliveryVehicles)),
    'ut' => acta_composite($deliveryVehicles, 'orden_participacion'),
    'participacion' => acta_composite($deliveryVehicles, 'involucrado_tipo'),
];
$values = [
    'acta_id'=>$row['id'], 'acta_tipo'=>$row['tipo'], 'acta_estado'=>$row['estado'],
    'fecha_entrega'=>acta_date((string)$row['fecha_entrega']), 'fecha_entrega_corta'=>date('d/m/Y',strtotime((string)$row['fecha_entrega'])), 'fecha_entrega_abrev'=>$introDate,
    'hora_inicio'=>substr((string)$row['hora_inicio'],0,5), 'hora_culminacion'=>substr((string)$row['hora_culminacion'],0,5),
    'acta_distrito'=>$district, 'acta_presentacion_propietario'=>$ownerPresentation, 'propietario_presentacion'=>$ownerPresentation,
    'acta_intro_apertura'=>$presentationOpening, 'acta_intro_persona'=>$presentationPerson,
    'acta_intro_despues_persona'=>$presentationAfterPerson, 'acta_intro_empresa'=>$presentationCompany, 'acta_intro_cierre'=>$presentationClosing,
    'placa_rodaje'=>$composite['placa'], 'vehiculo_placa'=>$composite['placa'], 'vehiculo_placa_compuesto'=>$composite['placa'],
    'vehiculo_ut'=>$composite['ut'], 'vehiculo_ut_compuesto'=>$composite['ut'], 'vehiculo_participacion_compuesto'=>$composite['participacion'],
    'vehiculo_clase'=>$composite['clase'], 'vehiculo_clase_compuesto'=>$composite['clase'], 'vehiculo_tipo'=>$composite['clase'],
    'vehiculo_categoria'=>$composite['categoria'], 'vehiculo_categoria_compuesto'=>$composite['categoria'],
    'vehiculo_carroceria'=>$composite['carroceria'], 'vehiculo_carroceria_compuesto'=>$composite['carroceria'],
    'vehiculo_color'=>$composite['color'], 'vehiculo_color_compuesto'=>$composite['color'], 'vehiculo_anio'=>$composite['anio'], 'vehiculo_anio_compuesto'=>$composite['anio'],
    'vehiculo_vin'=>$composite['vin'], 'vehiculo_vin_compuesto'=>$composite['vin'], 'vehiculo_motor'=>$composite['motor'], 'vehiculo_motor_compuesto'=>$composite['motor'],
    'vehiculo_marca'=>$composite['marca'], 'vehiculo_marca_compuesto'=>$composite['marca'], 'vehiculo_modelo'=>$composite['modelo'], 'vehiculo_modelo_compuesto'=>$composite['modelo'],
    'vehiculo_dimensiones'=>$composite['dimensiones'], 'vehiculo_dimensiones_compuesto'=>$composite['dimensiones'],
    'conductor_nombre'=>acta_name($row,'conductor'), 'conductor_tipo_doc'=>$row['conductor_tipo_doc'], 'conductor_num_doc'=>$row['conductor_num_doc'],
    'conductor_domicilio'=>$row['conductor_domicilio'], 'conductor_celular'=>$row['conductor_celular'], 'conductor_email'=>$row['conductor_email'],
    'propietario_tipo'=>$ownerType, 'propietario_origen'=>$usesConductorAsOwner ? 'Conductor consignado como propietario por falta de registro' : 'Propietario registrado',
    'propietario_nombre'=>$ownerName, 'propietario_razon_social'=>$row['razon_social'],
    'propietario_tipo_doc'=>$ownerDocType, 'propietario_num_doc'=>$ownerDoc, 'propietario_ruc'=>$row['ruc'],
    'propietario_domicilio'=>$ownerAddress, 'propietario_celular'=>$ownerPhone, 'propietario_email'=>$ownerEmail, 'propietario_rol_legal'=>$row['rol_legal'],
    'representante_nombre'=>$representativeName, 'representante_tipo_doc'=>$row['representante_tipo_doc'], 'representante_num_doc'=>$row['representante_num_doc'],
    'representante_rol_legal'=>$row['rol_legal'], 'representante_domicilio'=>$row['representante_domicilio'],
    'representante_celular'=>$row['representante_celular'], 'representante_email'=>$row['representante_email'],
    'vehiculos_involucrados'=>implode("\n",$vehicleLines), 'accidente_sidpol'=>$row['registro_sidpol'], 'accidente_lugar'=>$row['lugar'],
    'observaciones'=>$row['observaciones'] ?? '',
];
$tpl = new TemplateProcessor($template);
foreach ($values as $key=>$value) { $tpl->setValue($key, htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); }
$tmp = tempnam(sys_get_temp_dir(), 'acta_');
$tpl->saveAs($tmp);
$filename = uiat_docx_filename(['acta_entrega_vehiculo', $row['placa'], $row['fecha_entrega']], 'acta_entrega_vehiculo');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
