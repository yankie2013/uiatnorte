<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\ItpRepository;
use App\Services\ItpService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function render_csv_list(?string $value): string
{
    $items = array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn ($item): bool => $item !== ''));
    if ($items === []) {
        return '<div class="small">Sin registros.</div>';
    }
    $html = '<ul class="list">';
    foreach ($items as $item) {
        $html .= '<li>' . h($item) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function render_field(string $class, string $label, string $value, bool $multiline = false, bool $html = false): void
{
    echo '<div class="' . h($class) . ' field">';
    echo '<div class="label">' . h($label) . '</div>';
    echo '<div class="value">';
    if ($html) {
        echo $value;
    } elseif ($multiline) {
        echo nl2br(h($value !== '' ? $value : '-'));
    } else {
        echo h($value !== '' ? $value : '-');
    }
    echo '</div></div>';
}

$service = new ItpService(new ItpRepository($pdo));
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id');
}

$detail = $service->detalle($id);
if ($detail === null) {
    http_response_code(404);
    exit('ITP no encontrado.');
}

$ok = trim((string) ($_GET['ok'] ?? ''));
$returnTo = trim((string) ($_GET['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'itp_listar.php?accidente_id=' . (int) $detail['accidente_id'];
}

$via1Simple = [
    ['c4', 'Configuracion', (string) ($detail['configuracion_via1'] ?? '')],
    ['c4', 'Material', (string) ($detail['material_via1'] ?? '')],
    ['c4', 'Senializacion', (string) ($detail['senializacion_via1'] ?? '')],
    ['c4', 'Ordenamiento', (string) ($detail['ordenamiento_via1'] ?? '')],
    ['c4', 'Iluminacion', (string) ($detail['iluminacion_via1'] ?? '')],
    ['c4', 'Visibilidad', (string) ($detail['visibilidad_via1'] ?? '')],
    ['c6', 'Intensidad', (string) ($detail['intensidad_via1'] ?? '')],
    ['c6', 'Fluidez', (string) ($detail['fluidez_via1'] ?? '')],
];

$via2Fields = ['descripcion_via2','configuracion_via2','material_via2','senializacion_via2','ordenamiento_via2','iluminacion_via2','visibilidad_via2','intensidad_via2','fluidez_via2','medidas_via2','observaciones_via2'];
$hasVia2 = false;
foreach ($via2Fields as $field) {
    if (!empty($detail[$field])) {
        $hasVia2 = true;
        break;
    }
}

$via2Simple = [
    ['c4', 'Configuracion', (string) ($detail['configuracion_via2'] ?? '')],
    ['c4', 'Material', (string) ($detail['material_via2'] ?? '')],
    ['c4', 'Senializacion', (string) ($detail['senializacion_via2'] ?? '')],
    ['c4', 'Ordenamiento', (string) ($detail['ordenamiento_via2'] ?? '')],
    ['c4', 'Iluminacion', (string) ($detail['iluminacion_via2'] ?? '')],
    ['c4', 'Visibilidad', (string) ($detail['visibilidad_via2'] ?? '')],
    ['c6', 'Intensidad', (string) ($detail['intensidad_via2'] ?? '')],
    ['c6', 'Fluidez', (string) ($detail['fluidez_via2'] ?? '')],
];

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Detalle ITP</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c;--gold:#b68b1f}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca;--gold:#e6c97d}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1120px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}h1,h2{color:var(--gold);margin:0}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px;margin-bottom:14px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.c4{grid-column:span 4}.field{padding:12px;border:1px solid var(--border);border-radius:14px;background:rgba(148,163,184,.08)}.label{font-size:12px;color:var(--gold);font-weight:700;margin-bottom:4px}.value{font-weight:700;word-break:break-word}.small{color:var(--muted);font-size:12px}.ok{background:rgba(22,163,74,.12);padding:10px;border-radius:10px;margin-bottom:12px}.list{margin:0;padding-left:18px}@media(max-width:920px){.c6,.c4{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1>ITP <span class="badge">Detalle</span></h1>
      <div class="small">Registro #<?= (int) $id ?></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="<?= h($returnTo) ?>">Volver</a>
      <a class="btn" href="itp_editar.php?id=<?= (int) $id ?>">Editar</a>
      <a class="btn danger" href="itp_eliminar.php?id=<?= (int) $id ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
    </div>
  </div>

  <?php if ($ok === 'created'): ?><div class="ok">ITP creado correctamente.</div><?php endif; ?>
  <?php if ($ok === 'updated'): ?><div class="ok">ITP actualizado correctamente.</div><?php endif; ?>

  <div class="card">
    <strong>Accidente #<?= (int) $detail['accidente_id'] ?></strong>
    <div class="small" style="margin-top:4px;">
      SIDPOL: <?= h((string) ($detail['registro_sidpol'] ?? '-')) ?>
      - Fecha: <?= h((string) ($detail['fecha_accidente'] ?? '-')) ?>
      - Lugar: <?= h((string) ($detail['lugar'] ?? '-')) ?>
    </div>
  </div>

  <div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px;">Datos generales</h2>
    <div class="grid">
      <?php render_field('c4', 'Fecha ITP', (string) ($detail['fecha_itp'] ?? '')); ?>
      <?php render_field('c4', 'Hora ITP', (string) ($detail['hora_itp'] ?? '')); ?>
      <?php render_field('c4', 'Forma de la via', (string) ($detail['forma_via'] ?? '')); ?>
      <?php render_field('c12', 'Punto de referencia', (string) ($detail['punto_referencia'] ?? '')); ?>
      <?php render_field('c12', 'Ubicacion GPS', !empty($detail['ubicacion_gps']) ? '<a href="https://www.google.com/maps?q=' . urlencode((string) $detail['ubicacion_gps']) . '" target="_blank" rel="noopener noreferrer">' . h((string) $detail['ubicacion_gps']) . '</a>' : '-', false, true); ?>
      <?php render_field('c12', 'Localizacion de unidades', render_csv_list($detail['localizacion_unidades'] ?? ''), false, true); ?>
      <?php render_field('c12', 'Ocurrencia policial', (string) ($detail['ocurrencia_policial'] ?? ''), true); ?>
      <?php render_field('c12', 'Llegada al lugar', (string) ($detail['llegada_lugar'] ?? ''), true); ?>
    </div>
  </div>

  <div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px;">Via 1</h2>
    <div class="grid">
      <?php render_field('c12', 'Descripcion', (string) ($detail['descripcion_via1'] ?? ''), true); ?>
      <?php foreach ($via1Simple as [$class, $label, $value]) render_field($class, $label, $value); ?>
      <?php render_field('c12', 'Medidas', render_csv_list($detail['medidas_via1'] ?? ''), false, true); ?>
      <?php render_field('c12', 'Observaciones', render_csv_list($detail['observaciones_via1'] ?? ''), false, true); ?>
    </div>
  </div>

  <?php if ($hasVia2): ?>
    <div class="card">
      <h2 style="font-size:1rem;margin-bottom:10px;">Via 2</h2>
      <div class="grid">
        <?php render_field('c12', 'Descripcion', (string) ($detail['descripcion_via2'] ?? ''), true); ?>
        <?php foreach ($via2Simple as [$class, $label, $value]) render_field($class, $label, $value); ?>
        <?php render_field('c12', 'Medidas', render_csv_list($detail['medidas_via2'] ?? ''), false, true); ?>
        <?php render_field('c12', 'Observaciones', render_csv_list($detail['observaciones_via2'] ?? ''), false, true); ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px;">Evidencias</h2>
    <div class="grid">
      <?php render_field('c12', 'Evidencia biologica', (string) ($detail['evidencia_biologica'] ?? ''), true); ?>
      <?php render_field('c12', 'Evidencia fisica', (string) ($detail['evidencia_fisica'] ?? ''), true); ?>
      <?php render_field('c12', 'Evidencia material', (string) ($detail['evidencia_material'] ?? ''), true); ?>
    </div>
  </div>
</div>
</body>
</html>
