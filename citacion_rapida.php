<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require __DIR__ . '/google_calendar.php';

use App\Repositories\CitacionRepository;
use App\Services\CitacionService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new CitacionService(new CitacionRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? $_POST['accidente_id'] ?? 0);
$personaSelector = trim((string) ($_GET['persona'] ?? $_POST['persona'] ?? ''));
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));

if ($returnTo === '' && $accidenteId > 0) {
    $returnTo = 'accidente_vista_tabs.php?accidente_id=' . $accidenteId;
}

$error = '';
$success = '';
$newId = null;
$context = null;

try {
    $context = $service->quickContext($accidenteId, $personaSelector);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$data = $context['defaults'] ?? [
    'en_calidad' => '',
    'tipo_diligencia' => '',
    'fecha' => date('Y-m-d'),
    'hora' => '09:00',
    'lugar' => '',
    'motivo' => '',
    'orden_citacion' => 1,
    'oficio_id' => '',
];

$lugarOpciones = [
    'Lugar de los hechos',
    'Carretera Panamericana Norte km. 42 (alt. garita control SUNAT) sede de la Unidad de Investigacion de Accidentes de Transito-Lima Norte',
];

$motivoOpciones = [
    'Rendir manifestacion',
    'Visualizacion de video',
    'Imposicion de PIT',
    'Diligencia DIRCRI',
    'Entrega vehiculo',
    'Entrega enseres',
    'Entrega documentos',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $context !== null) {
    $data = [
        'en_calidad' => $_POST['en_calidad'] ?? '',
        'tipo_diligencia' => $_POST['tipo_diligencia'] ?? '',
        'fecha' => $_POST['fecha'] ?? '',
        'hora' => $_POST['hora'] ?? '',
        'lugar' => $_POST['lugar'] ?? '',
        'motivo' => $_POST['motivo'] ?? '',
        'orden_citacion' => $_POST['orden_citacion'] ?? 1,
        'oficio_id' => $_POST['oficio_id'] ?? '',
    ];

    try {
        $created = $service->create($accidenteId, ['persona' => $personaSelector] + $data);
        $newId = (int) $created['id'];

        try {
            gc_crear_evento_citacion($service->calendarPayload($accidenteId, $newId, $created));
        } catch (Throwable $calendarError) {
            // Si falla Google Calendar no bloqueamos la descarga del Word.
        }

        header('Location: citacion_diligencia.php?citacion_id=' . urlencode((string) $newId));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Citacion rapida</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6;--danger:#fecaca;--ok:#bbf7d0}}
body{background:var(--page);color:var(--text)}.wrap{max-width:1040px;margin:24px auto;padding:16px}.toolbar{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:20px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}.c3{grid-column:span 3}.c4{grid-column:span 4}.c6{grid-column:span 6}.c8{grid-column:span 8}.c12{grid-column:span 12}label{display:block;font-weight:700;color:var(--muted);margin-bottom:6px}.card input,.card select,.card textarea{width:100%;box-sizing:border-box;min-height:42px;padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);line-height:1.25}.card select{padding-right:38px;appearance:auto;-webkit-appearance:menulist}.card textarea{min-height:120px;resize:vertical}.muted{color:var(--muted);font-size:.92rem}.alert{padding:12px 14px;border-radius:12px;margin-bottom:12px}.alert.err{background:rgba(220,38,38,.12);color:var(--danger)}.alert.ok{background:rgba(22,163,74,.12);color:var(--ok)}.summary{border:1px dashed var(--border);border-radius:14px;padding:14px;background:rgba(148,163,184,.06)}.summary strong{display:block;margin-bottom:4px}.pill{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--primary);font-weight:700}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:6px}.doc-link{display:inline-flex;margin-top:10px}@media (max-width:900px){.c3,.c4,.c6,.c8{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0;">Citacion rapida</h1>
      <div class="muted">Preconfigura la persona, el accidente y la calidad para registrar la diligencia mas rapido.</div>
    </div>
    <div class="actions" style="margin-top:0;">
      <?php if ($returnTo !== ''): ?><a class="btn" href="<?= h($returnTo) ?>">Volver</a><?php endif; ?>
      <?php if ($accidenteId > 0): ?><a class="btn" href="citacion_listar.php?accidente_id=<?= (int) $accidenteId ?>">Ver citaciones</a><?php endif; ?>
    </div>
  </div>

  <?php if ($error !== ''): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
  <?php if ($context !== null): ?>
    <form method="post" class="card">
      <input type="hidden" name="accidente_id" value="<?= (int) $accidenteId ?>">
      <input type="hidden" name="persona" value="<?= h($personaSelector) ?>">
      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">

      <div class="grid">
        <div class="c12 summary">
          <span class="pill">Accidente y persona preconfigurados</span>
          <div class="grid" style="margin-top:12px;">
            <div class="c6">
              <strong>Accidente</strong>
              <div><?= h($context['accidente']['label']) ?></div>
            </div>
            <div class="c6">
              <strong>Persona citada</strong>
              <div><?= h($context['persona']['nombre']) ?></div>
            </div>
            <div class="c3">
              <strong>Fuente</strong>
              <div><?= h($context['persona']['fuente']) ?></div>
            </div>
            <div class="c3">
              <strong>Relacion</strong>
              <div><?= h($context['persona']['relacion'] !== '' ? $context['persona']['relacion'] : 'Sin relacion') ?></div>
            </div>
            <div class="c3">
              <strong>Documento</strong>
              <div><?= h($context['persona']['doc'] !== '' ? $context['persona']['doc'] : 'Sin documento') ?></div>
            </div>
            <div class="c3">
              <strong>Edad</strong>
              <div><?= $context['persona']['edad'] !== null ? (int) $context['persona']['edad'] . ' anos' : 'Sin dato' ?></div>
            </div>
            <div class="c12">
              <strong>Domicilio</strong>
              <div><?= h($context['persona']['domicilio'] !== '' ? $context['persona']['domicilio'] : 'Sin domicilio registrado') ?></div>
            </div>
          </div>
        </div>

        <div class="c4">
          <label>En calidad de*</label>
          <select name="en_calidad" required>
            <option value="">Selecciona</option>
            <?php foreach ($context['calidades'] as $calidad): ?>
              <option value="<?= h($calidad) ?>" <?= (string) $data['en_calidad'] === (string) $calidad ? 'selected' : '' ?>><?= h($service->calidadLabel((string) $calidad)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c4">
          <label>Tipo de diligencia*</label>
          <select name="tipo_diligencia" required>
            <option value="">Selecciona</option>
            <?php foreach ($context['tipos'] as $tipo): ?>
              <option value="<?= h($tipo) ?>" <?= (string) $data['tipo_diligencia'] === (string) $tipo ? 'selected' : '' ?>><?= h($tipo) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c4">
          <label>Oficio que ordena</label>
          <select name="oficio_id">
            <option value="">Sin oficio</option>
            <?php foreach ($context['oficios'] as $oficio): ?>
              <?php $label = 'Oficio ' . ($oficio['numero'] ?? '?') . '/' . ($oficio['anio'] ?? '?') . ' · ID ' . ($oficio['id'] ?? ''); ?>
              <option value="<?= (int) ($oficio['id'] ?? 0) ?>" <?= (string) $data['oficio_id'] === (string) ($oficio['id'] ?? '') ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c3">
          <label>Fecha*</label>
          <input type="date" name="fecha" value="<?= h((string) $data['fecha']) ?>" required>
        </div>

        <div class="c3">
          <label>Hora*</label>
          <input type="time" name="hora" value="<?= h((string) $data['hora']) ?>" required>
        </div>

        <div class="c6">
          <label>Lugar*</label>
          <select name="lugar" required>
            <option value="">Selecciona</option>
            <?php foreach ($lugarOpciones as $lugarOpcion): ?>
              <option value="<?= h($lugarOpcion) ?>" <?= (string) $data['lugar'] === (string) $lugarOpcion ? 'selected' : '' ?>><?= h($lugarOpcion) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c12">
          <label>Motivo / observaciones*</label>
          <div style="display:flex;gap:8px;align-items:flex-start;">
            <select id="motivo_sel" style="flex:1;">
              <option value="">Selecciona</option>
              <?php foreach ($motivoOpciones as $motivoOpcion): ?>
                <option value="<?= h($motivoOpcion) ?>"><?= h($motivoOpcion) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" id="btnMotivoOtro" class="btn" title="Escribir otro motivo">+</button>
          </div>
          <textarea id="motivo_otro" rows="4" placeholder="Escribe el motivo..." style="display:none; margin-top:8px;"></textarea>
          <input type="hidden" name="motivo" id="motivo_final" value="<?= h((string) $data['motivo']) ?>">
        </div>

        <div class="c12 actions">
          <?php if ($returnTo !== ''): ?><a class="btn" href="<?= h($returnTo) ?>">Cancelar</a><?php endif; ?>
          <button class="btn primary" type="submit">Guardar y descargar Word</button>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('form.card');
  const motivoSel = document.getElementById('motivo_sel');
  const motivoOtro = document.getElementById('motivo_otro');
  const motivoFinal = document.getElementById('motivo_final');
  const btnMotivo = document.getElementById('btnMotivoOtro');
  let usandoMotivoCustom = false;

  if (motivoSel && motivoOtro && motivoFinal && btnMotivo) {
    const preset = (motivoFinal.value || '').trim();
    if (preset !== '') {
      let found = false;
      for (const option of motivoSel.options) {
        if (option.value === preset) {
          found = true;
          break;
        }
      }

      if (found) {
        motivoSel.value = preset;
      } else {
        usandoMotivoCustom = true;
        motivoSel.style.display = 'none';
        motivoOtro.style.display = 'block';
        motivoOtro.value = preset;
        btnMotivo.textContent = 'x';
      }
    }

    btnMotivo.addEventListener('click', function () {
      usandoMotivoCustom = !usandoMotivoCustom;
      motivoOtro.style.display = usandoMotivoCustom ? 'block' : 'none';
      motivoSel.style.display = usandoMotivoCustom ? 'none' : 'block';
      btnMotivo.textContent = usandoMotivoCustom ? 'x' : '+';

      if (usandoMotivoCustom && motivoSel.value) {
        motivoOtro.value = motivoSel.value;
      }
    });
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      if (!motivoFinal) return;
      motivoFinal.value = (usandoMotivoCustom ? motivoOtro.value : motivoSel.value).trim();
      if (!motivoFinal.value) {
        event.preventDefault();
        alert('Indica el motivo / observaciones.');
      }
    });
  }
});
</script>
</body>
</html>
