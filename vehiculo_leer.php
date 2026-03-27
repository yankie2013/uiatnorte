<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\VehiculoRepository;
use App\Services\VehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function fmtDate($s){ return $s ? date('Y-m-d H:i', strtotime($s)) : '-'; }
function fmtMetric($value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    return rtrim(rtrim((string) $value, '0'), '.');
}
function cleanPairValue(string $value): string {
    return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
}
function parseVehiculoNotas(?string $notes): array {
    $notes = trim((string) $notes);
    if ($notes === '') {
        return ['pairs' => [], 'raw_text' => '', 'visible_notes' => ''];
    }

    $pairs = [];
    $rawText = '';
    $visibleParts = [];

    foreach (preg_split('/\|/u', $notes) ?: [] as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        if (preg_match('/^([^:]+):\s*(.+)$/u', $part, $m)) {
            $label = cleanPairValue($m[1]);
            $value = cleanPairValue($m[2]);
            if ($label === '') {
                continue;
            }

            if (mb_strtoupper($label, 'UTF-8') === 'OCR TEXTO') {
                $rawText = $value;
                continue;
            }

            $pairs[$label] = $value;
            continue;
        }

        $visibleParts[] = $part;
    }

    return [
        'pairs' => $pairs,
        'raw_text' => $rawText,
        'visible_notes' => implode("\n", $visibleParts),
    ];
}
function noteValue(array $pairs, array $labels): string {
    foreach ($labels as $label) {
        if (!empty($pairs[$label])) {
            return (string) $pairs[$label];
        }
    }
    return '';
}
function fieldValue(...$values): string {
    foreach ($values as $value) {
        $value = trim((string) ($value ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '-';
}
function renderField(string $label, string $value, string $class = ''): string {
    $classAttr = trim('field ' . $class);
    return '<div class="' . h($classAttr) . '"><div class="label">' . h($label) . '</div><div class="val">' . nl2br(h($value !== '' ? $value : '-')) . '</div></div>';
}

$vehiculoService = new VehiculoService(new VehiculoRepository($pdo));
$id = (int) g('id', 0);
$placa = (string) g('placa', '');
$returnTo = (string) g('return_to', 'vehiculo_listar.php');
$selfUrl = $_SERVER['REQUEST_URI'] ?? ('vehiculo_leer.php?id=' . $id);

$detalle = $vehiculoService->detalle($id, $placa);
$v = $detalle['vehiculo'] ?? null;
$cntInv = $detalle['accidentes_vinculados'] ?? null;

$placaTxt = trim((string) ($v['placa'] ?? ''));
$notasInfo = parseVehiculoNotas($v['notas'] ?? '');
$pairs = $notasInfo['pairs'];
$visibleNotes = $notasInfo['visible_notes'];
$rawOcrText = $notasInfo['raw_text'];

$categoria = trim(
    (string) ($v['cat_codigo'] ?? '')
    . (!empty($v['cat_desc']) ? (' - ' . $v['cat_desc']) : '')
);
$tipo = trim(
    (string) ($v['tipo_codigo'] ?? '')
    . (!empty($v['tipo_nombre']) ? (' - ' . $v['tipo_nombre']) : '')
);
$carroceria = fieldValue($v['carroceria_nombre'] ?? '', noteValue($pairs, ['Carrocería OCR', 'Carrocería API']));
$marca = fieldValue($v['marca_nombre'] ?? '', noteValue($pairs, ['Marca OCR', 'Marca API']));
$modelo = fieldValue($v['modelo_nombre'] ?? '', noteValue($pairs, ['Modelo OCR', 'Modelo API']));
$color = fieldValue($v['color'] ?? '', noteValue($pairs, ['Color OCR', 'Color API']));
$combustible = fieldValue(noteValue($pairs, ['Combustible OCR', 'Combustible API']));
$tipoUso = fieldValue(noteValue($pairs, ['Tipo Uso OCR', 'Tipo Uso API']));
$partida = fieldValue(noteValue($pairs, ['Partida OCR', 'Partida API']));
$asientos = fieldValue(noteValue($pairs, ['Asientos OCR', 'Asientos API']));
$serieVin = fieldValue($v['serie_vin'] ?? '');
$nroMotor = fieldValue($v['nro_motor'] ?? '');
$anio = fieldValue($v['anio'] ?? '');
$largo = fieldValue(fmtMetric($v['largo_mm'] ?? null));
$ancho = fieldValue(fmtMetric($v['ancho_mm'] ?? null));
$alto = fieldValue(fmtMetric($v['alto_mm'] ?? null));
$subtitulo = trim(
    ($marca !== '-' ? $marca : '')
    . ($modelo !== '-' ? (($marca !== '-' ? ' / ' : '') . $modelo) : '')
    . ($anio !== '-' ? ((($marca !== '-' || $modelo !== '-') ? ' - ' : '') . $anio) : '')
);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= $v ? 'Ficha de vehiculo #' . (int)$v['id'] : 'Buscar vehiculo' ?> | UIAT Norte</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{color-scheme:light dark}
  body{margin:0;font-family:Inter,system-ui,sans-serif;font-size:11px;background:#111}
  .wrap{max-width:1120px;margin:14px auto;padding:0 12px}
  .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:8px;flex-wrap:wrap}
  .title h1{margin:0;font-size:16px;font-weight:800}
  .badge{display:inline-block;padding:2px 8px;border-radius:999px;background:rgba(127,127,127,.12);border:1px solid rgba(127,127,127,.25);font-size:10px;font-weight:700}
  .actions{display:flex;gap:8px;flex-wrap:wrap}
  .btn{padding:5px 9px;border-radius:11px;border:1px solid rgba(127,127,127,.25);text-decoration:none;font-weight:700;font-size:11px;color:inherit;background:transparent}
  .btn.primary{background:#0d6efd;color:#fff;border-color:#0b5ed7}
  .btn.danger{color:#ef4444}
  .card{border:1px solid rgba(127,127,127,.25);border-radius:18px;padding:12px 12px 10px;box-shadow:0 8px 24px rgba(0,0,0,.22);background:rgba(255,255,255,.08)}
  .hero{padding-bottom:10px;border-bottom:1px solid rgba(127,127,127,.20);margin-bottom:10px}
  .hero-plate{font-size:24px;line-height:1;font-weight:900;letter-spacing:.7px}
  .hero-sub{margin-top:4px;font-size:13px;opacity:.88;font-weight:600}
  .section{margin-top:12px}
  .section h2{margin:0 0 6px;font-size:10px;letter-spacing:.65px;text-transform:uppercase;opacity:.72}
  .grid{display:grid;gap:6px;grid-template-columns:repeat(4,minmax(0,1fr))}
  @media(max-width:980px){.grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
  @media(max-width:760px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:560px){.grid{grid-template-columns:1fr}}
  .field{border:1px solid rgba(127,127,127,.22);border-radius:12px;padding:8px 9px;background:rgba(127,127,127,.07);min-height:48px}
  .span-2{grid-column:span 2}
  .span-4{grid-column:1/-1}
  @media(max-width:760px){.span-2{grid-column:span 1}}
  .label{font-size:9px;font-weight:800;letter-spacing:.25px;opacity:.72;margin-bottom:4px;text-transform:uppercase}
  .val{font-weight:700;font-size:12px;line-height:1.22;word-break:break-word}
  .val.small{font-size:11px}
  .search-card{border:1px solid rgba(127,127,127,.25);border-radius:18px;padding:14px;background:rgba(255,255,255,.08)}
  .search-form{display:grid;gap:8px;grid-template-columns:1fr auto}
  .search-form input{padding:9px 11px;border-radius:12px;border:1px solid rgba(127,127,127,.25);background:transparent;color:inherit}
  .notes{white-space:pre-wrap;font-size:11px;line-height:1.35}
  @media(max-width:560px){
    .wrap{margin:10px auto;padding:0 10px}
    .card{padding:10px 10px 9px;border-radius:16px}
    .hero-plate{font-size:21px}
    .hero-sub{font-size:12px}
    .field{min-height:auto}
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div class="title">
      <h1><?= $v ? 'Ficha de vehiculo' : 'Buscar vehiculo' ?></h1>
      <?php if($v): ?><span class="badge">ID #<?= (int)$v['id'] ?></span><?php endif; ?>
    </div>
    <div class="actions">
      <a class="btn" href="<?= h($returnTo) ?>">Volver</a>
      <?php if($v): ?>
        <a class="btn primary" href="vehiculo_editar.php?id=<?= (int)$v['id'] ?>&return_to=<?= urlencode($selfUrl) ?>">Editar</a>
        <a class="btn danger" href="vehiculo_eliminar.php?id=<?= (int)$v['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if(!$v): ?>
    <div class="search-card">
      <form method="get" class="search-form">
        <input type="text" name="placa" placeholder="Buscar por placa (ABC123)">
        <button class="btn primary" type="submit">Buscar</button>
      </form>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="hero">
        <div class="hero-plate"><?= h($placaTxt !== '' ? $placaTxt : '-') ?></div>
        <?php if($subtitulo !== ''): ?><div class="hero-sub"><?= h($subtitulo) ?></div><?php endif; ?>
      </div>

      <div class="section">
        <h2>Identificación</h2>
        <div class="grid">
          <?= renderField('Marca', $marca) ?>
          <?= renderField('Modelo', $modelo) ?>
          <?= renderField('Año', $anio) ?>
          <?= renderField('Color', $color) ?>
          <?= renderField('Serie VIN', $serieVin, 'span-2') ?>
          <?= renderField('Nro motor', $nroMotor, 'span-2') ?>
        </div>
      </div>

      <div class="section">
        <h2>Clasificación</h2>
        <div class="grid">
          <?= renderField('Categoría', fieldValue($categoria), 'span-2') ?>
          <?= renderField('Tipo', fieldValue($tipo), 'span-2') ?>
          <?= renderField('Carrocería', $carroceria) ?>
          <?= renderField('Tipo de uso', $tipoUso, 'span-2') ?>
          <?= renderField('Combustible', $combustible) ?>
        </div>
      </div>

      <div class="section">
        <h2>Dimensiones</h2>
        <div class="grid">
          <?= renderField('Largo (m)', $largo) ?>
          <?= renderField('Ancho (m)', $ancho) ?>
          <?= renderField('Alto (m)', $alto) ?>
          <?= renderField('Asientos', $asientos) ?>
        </div>
      </div>

      <div class="section">
        <h2>Otros Detalles</h2>
        <div class="grid">
          <?= renderField('N° Partida', $partida) ?>
          <?= renderField('Creado', fmtDate($v['creado_en'] ?? null), 'span-2') ?>
          <?= renderField('Actualizado', fmtDate($v['actualizado_en'] ?? null), 'span-2') ?>
          <?php if($cntInv !== null): ?>
            <?= renderField('Accidentes vinculados', (string) ((int) $cntInv)) ?>
            <div class="field">
              <div class="label">Acciones</div>
              <div class="val small">
                <a class="btn" href="involucrados_vehiculos_listar.php?q=<?= urlencode($v['placa'] ?? '') ?>">Ver en listados</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if($visibleNotes !== '' || $rawOcrText !== ''): ?>
        <div class="section">
          <h2>Notas</h2>
          <div class="grid">
            <?php if($visibleNotes !== ''): ?>
              <?= renderField('Notas registradas', $visibleNotes, 'span-4') ?>
            <?php endif; ?>
            <?php if($rawOcrText !== ''): ?>
              <?= renderField('Texto OCR', $rawOcrText, 'span-4') ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
