<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!class_exists(\Dompdf\Dompdf::class) && file_exists(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require_once __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}

use App\Repositories\DocumentoPlantillaRepository;
use App\Services\DocumentoPlantillaService;
use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: text/html; charset=utf-8');

$citacionId = isset($_GET['citacion_id']) ? (int) $_GET['citacion_id'] : 0;
if ($citacionId <= 0) {
    http_response_code(400);
    exit('Falta citacion_id');
}

if (!class_exists(Dompdf::class) || !class_exists(Options::class)) {
    http_response_code(500);
    exit('Dompdf no esta disponible para generar el PDF.');
}

$service = new DocumentoPlantillaService(new DocumentoPlantillaRepository($pdo));

try {
    $data = $service->citacionData($citacionId);
} catch (Throwable $e) {
    http_response_code($e instanceof \InvalidArgumentException ? 404 : 500);
    exit($e->getMessage());
}

function h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$values = $data['values'];
$filename = (string) ($data['filename'] ?? ('Citacion_' . $citacionId . '.docx'));
$filename = preg_replace('/\.docx$/i', '.pdf', $filename) ?? ('Citacion_' . $citacionId . '.pdf');
$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: ('Citacion_' . $citacionId . '.pdf');

$persona = trim((string) ($values['persona_nombre_completo'] ?? ''));
$documento = trim((string) ($values['persona_doc'] ?? ''));
$domicilio = trim((string) ($values['persona_domicilio'] ?? ''));
$calidad = trim((string) ($values['cit_en_calidad'] ?? ''));
$diligencia = trim((string) ($values['cit_tipo_diligencia'] ?? ''));
$fecha = trim((string) ($values['cit_fecha_larga'] ?? $values['cit_fecha'] ?? ''));
$hora = trim((string) ($values['cit_hora'] ?? ''));
$lugar = trim((string) ($values['cit_lugar'] ?? ''));
$motivo = trim((string) ($values['cit_motivo'] ?? ''));
$oficio = trim((string) ($values['cit_oficio'] ?? ''));
$orden = trim((string) ($values['cit_orden'] ?? ''));
$edad = trim((string) ($values['persona_edad'] ?? ''));
$celular = trim((string) ($values['persona_celular'] ?? ''));
$email = trim((string) ($values['persona_email'] ?? ''));
$accidenteFecha = trim((string) ($values['accidente_fecha_larga'] ?? $values['accidente_fecha'] ?? ''));
$accidenteHora = trim((string) ($values['accidente_hora'] ?? ''));
$accidenteLugar = trim((string) ($values['accidente_lugar'] ?? ''));
$accidenteModalidad = trim((string) ($values['accidente_modalidad'] ?? ''));
$accidenteSidpol = trim((string) ($values['accidente_sidpol'] ?? ''));
$comisaria = trim((string) ($values['comisaria_nombre'] ?? ''));
$fiscalia = trim((string) ($values['fiscalia_nombre'] ?? ''));

$html = '<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
@page { margin: 2.2cm 1.8cm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; line-height: 1.45; }
h1 { font-size: 18px; margin: 0 0 4px; text-align: center; }
.subtitle { text-align:center; font-size: 11px; margin-bottom: 18px; color: #374151; }
.section { margin-bottom: 14px; }
.label { font-weight: bold; color: #374151; }
.box { border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; }
.grid { width: 100%; border-collapse: collapse; }
.grid td { vertical-align: top; padding: 6px 8px; border: 1px solid #d1d5db; }
.muted { color: #6b7280; }
.paragraph { text-align: justify; margin: 0 0 10px; }
.footer { margin-top: 22px; font-size: 10px; color: #4b5563; }
</style>
</head>
<body>
<h1>CITACION PARA DILIGENCIA</h1>
<div class="subtitle">Unidad de Investigacion de Accidentes de Transito - Norte</div>

<div class="section box">
  <div><span class="label">Persona citada:</span> ' . h($persona !== '' ? $persona : 'No registrada') . '</div>
  <div><span class="label">Documento:</span> ' . h($documento !== '' ? $documento : 'No registrado') . '</div>
  <div><span class="label">Domicilio:</span> ' . h($domicilio !== '' ? $domicilio : 'No registrado') . '</div>
</div>

<table class="grid section">
  <tr>
    <td><span class="label">En calidad de</span><br>' . h($calidad) . '</td>
    <td><span class="label">Tipo de diligencia</span><br>' . h($diligencia) . '</td>
  </tr>
  <tr>
    <td><span class="label">Fecha</span><br>' . h($fecha) . '</td>
    <td><span class="label">Hora</span><br>' . h($hora) . '</td>
  </tr>
  <tr>
    <td><span class="label">Lugar</span><br>' . h($lugar) . '</td>
    <td><span class="label">Orden de citacion</span><br>' . h($orden !== '' ? $orden : 'No registrada') . '</td>
  </tr>
  <tr>
    <td><span class="label">Oficio que ordena</span><br>' . h($oficio !== '' ? $oficio : 'Sin oficio') . '</td>
    <td><span class="label">Edad / contacto</span><br>' . h(trim(($edad !== '' ? 'Edad: ' . $edad : '') . ($celular !== '' ? ' · Cel: ' . $celular : '') . ($email !== '' ? ' · ' . $email : '')) ?: 'Sin datos adicionales') . '</td>
  </tr>
</table>

<div class="section box">
  <div class="label">Motivo / observaciones</div>
  <div>' . nl2br(h($motivo !== '' ? $motivo : 'Sin observaciones registradas')) . '</div>
</div>

<div class="section box">
  <div class="label">Referencia del accidente</div>
  <div><strong>SIDPOL:</strong> ' . h($accidenteSidpol !== '' ? $accidenteSidpol : 'No registrado') . '</div>
  <div><strong>Fecha:</strong> ' . h($accidenteFecha !== '' ? $accidenteFecha : 'No registrada') . '</div>
  <div><strong>Hora:</strong> ' . h($accidenteHora !== '' ? $accidenteHora : 'No registrada') . '</div>
  <div><strong>Lugar:</strong> ' . h($accidenteLugar !== '' ? $accidenteLugar : 'No registrado') . '</div>
  <div><strong>Modalidad:</strong> ' . h($accidenteModalidad !== '' ? $accidenteModalidad : 'No registrada') . '</div>
  <div><strong>Comisaria / Fiscalia:</strong> ' . h(trim(($comisaria !== '' ? $comisaria : '') . ($fiscalia !== '' ? ' · ' . $fiscalia : '')) ?: 'No registradas') . '</div>
</div>

<p class="paragraph">Por medio del presente documento se deja constancia de la citacion programada para la diligencia indicada, vinculada al accidente de transito referido. La persona citada debera presentarse en la fecha, hora y lugar consignados, portando su documento de identidad y cualquier documentacion relacionada que le haya sido requerida.</p>

<p class="paragraph">En caso de imposibilidad material de asistir, corresponde comunicarlo oportunamente a la unidad instructora para la reprogramacion o coordinacion respectiva.</p>

<div class="footer">Documento generado automaticamente el ' . h(date('d/m/Y H:i')) . '.</div>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('chroot', __DIR__);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();

while (ob_get_level()) {
    ob_end_clean();
}
if (headers_sent()) {
    http_response_code(500);
    exit('No se pudo iniciar la descarga del PDF porque ya habia salida previa.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $dompdf->output();
exit;
