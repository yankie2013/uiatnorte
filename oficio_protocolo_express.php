<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\OficioRepository;
use App\Services\OficioService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new OficioService(new OficioRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? $_POST['accidente_id'] ?? 0);
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));

if ($returnTo === '' && $accidenteId > 0) {
    $returnTo = 'accidente_vista_tabs.php?accidente_id=' . $accidenteId;
}

$error = '';
$context = null;

try {
    $context = $service->necropsiaQuickContext($accidenteId);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$selectedPersonaId = (string) ($_GET['involucrado_persona_id'] ?? $_GET['invol_id'] ?? $_POST['involucrado_persona_id'] ?? '');
$numeroOficio = (string) ($_POST['numero_oficio'] ?? ($context['next_numero'] ?? ''));

if ($context !== null && count($context['fallecidos']) === 1 && $selectedPersonaId === '') {
    $selectedPersonaId = (string) ($context['fallecidos'][0]['id'] ?? '');
}

$hasFallecidos = $context !== null && count($context['fallecidos']) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $context !== null) {
    try {
        $personaId = (int) $selectedPersonaId;
        if ($personaId <= 0) {
            throw new InvalidArgumentException('Selecciona la persona fallecida para generar el oficio.');
        }

        $created = $service->create([
            'accidente_id' => $context['accidente_id'],
            'anio_oficio' => $context['anio_oficio'],
            'numero_oficio' => $numeroOficio,
            'fecha_emision' => $context['fecha_emision'],
            'oficial_ano_id' => $context['oficial_ano_id'],
            'entidad_id' => $context['preset']['entidad_id'],
            'subentidad_id' => $context['preset']['subentidad_id'] ?? '',
            'grado_cargo_id' => $context['preset']['grado_cargo_id'] ?? '',
            'persona_id' => $context['preset']['persona_id'] ?? '',
            'tipo' => 'SOLICITAR',
            'asunto_id' => $context['preset']['asunto_id'],
            'motivo' => $context['preset']['motivo'],
            'referencia_texto' => '',
            'involucrado_vehiculo_id' => '',
            'involucrado_persona_id' => $personaId,
            'estado' => 'BORRADOR',
        ]);

        header('Location: oficio_protocolo.php?oficio_id=' . urlencode((string) $created['id']) . '&inv_id=' . urlencode((string) $personaId));
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
<title>Necropsia rapida</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#0f766e;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#14b8a6;--danger:#fecaca}}
body{background:var(--page);color:var(--text)}.wrap{max-width:960px;margin:24px auto;padding:16px}.toolbar{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.btn.mini{width:42px;height:42px;padding:0;font-size:20px;line-height:1}.card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:20px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}.c4{grid-column:span 4}.c5{grid-column:span 5}.c7{grid-column:span 7}.c12{grid-column:span 12}label{display:block;font-weight:700;color:var(--muted);margin-bottom:6px}.card input,.card select{width:100%;box-sizing:border-box;min-height:42px;padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);line-height:1.25}.card select{padding-right:38px;appearance:auto;-webkit-appearance:menulist}.field-row{display:flex;gap:8px;align-items:center}.muted{color:var(--muted);font-size:.92rem}.alert{padding:12px 14px;border-radius:12px;margin-bottom:12px}.alert.err{background:rgba(220,38,38,.12);color:var(--danger)}.summary{border:1px dashed var(--border);border-radius:14px;padding:14px;background:rgba(20,184,166,.06)}.summary strong{display:block;margin-bottom:4px}.pill{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(15,118,110,.12);color:var(--primary);font-weight:700}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:6px}@media (max-width:900px){.c4,.c5,.c7{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0;">Necropsia rapida</h1>
      <div class="muted">Crea el oficio de protocolo de necropsia con destino preconfigurado y descarga el Word al instante.</div>
    </div>
    <div class="field-row">
      <?php if ($returnTo !== ''): ?><a class="btn" href="<?= h($returnTo) ?>">Volver</a><?php endif; ?>
      <?php if ($accidenteId > 0): ?><a class="btn" href="oficios_listar.php?accidente_id=<?= urlencode((string) $accidenteId) ?>">Ver oficios</a><?php endif; ?>
    </div>
  </div>

  <?php if ($error !== ''): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>

  <?php if ($context !== null): ?>
    <form method="post" class="card">
      <input type="hidden" name="accidente_id" value="<?= h($context['accidente_id']) ?>">
      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">

      <div class="grid">
        <div class="c12 summary">
          <span class="pill">Destino preconfigurado</span>
          <div class="grid" style="margin-top:12px;">
            <div class="c4">
              <strong>Entidad</strong>
              <div><?= h($context['preset']['entidad_label']) ?></div>
            </div>
            <div class="c4">
              <strong>Persona destino</strong>
              <div><?= h($context['preset']['persona_label'] ?: 'Sin persona fija') ?></div>
            </div>
            <div class="c4">
              <strong>Grado / cargo</strong>
              <div><?= h($context['preset']['grado_cargo_label'] ?: 'Sin grado fijo') ?></div>
            </div>
            <div class="c5">
              <strong>Asunto</strong>
              <div><?= h($context['preset']['asunto_label']) ?></div>
            </div>
            <div class="c7">
              <strong>Motivo base</strong>
              <div><?= h($context['preset']['motivo']) ?></div>
            </div>
          </div>
        </div>

        <div class="c12">
          <label>Accidente</label>
          <input type="text" value="<?= h($context['accidente_label']) ?>" readonly>
        </div>

        <div class="c5">
          <label>Numero de oficio*</label>
          <div class="field-row">
            <input type="number" name="numero_oficio" id="numero_oficio" value="<?= h($numeroOficio) ?>" min="1" required>
            <button class="btn mini" type="button" id="btnSugerir" title="Usar correlativo sugerido">&#8635;</button>
          </div>
          <div class="muted">Sugerido para <?= h((string) $context['anio_oficio']) ?>: <?= h((string) $context['next_numero']) ?></div>
        </div>

        <div class="c7">
          <label>Persona fallecida*</label>
          <select name="involucrado_persona_id" <?= $hasFallecidos ? 'required' : 'disabled' ?>>
            <option value="">Selecciona la persona fallecida</option>
            <?php foreach ($context['fallecidos'] as $persona): ?>
              <option value="<?= h($persona['id']) ?>" <?= (string) $selectedPersonaId === (string) $persona['id'] ? 'selected' : '' ?>><?= h($persona['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="muted">
            <?php if (!$hasFallecidos): ?>
              Este accidente todavia no tiene fallecidos registrados. Registra primero a la persona fallecida para usar este flujo.
            <?php elseif (count($context['fallecidos']) === 1): ?>
              El accidente tiene una sola persona fallecida registrada; ya quedo seleccionada.
            <?php else: ?>
              Si el accidente tiene varios fallecidos, elige a quien corresponde el protocolo.
            <?php endif; ?>
          </div>
        </div>

        <div class="c4">
          <label>Fecha de emision</label>
          <input type="text" value="<?= h($context['fecha_emision']) ?>" readonly>
        </div>

        <div class="c4">
          <label>Anio</label>
          <input type="text" value="<?= h((string) $context['anio_oficio']) ?>" readonly>
        </div>

        <div class="c4">
          <label>Nombre oficial del anio</label>
          <input type="text" value="<?= h($context['oficial_ano_label'] !== '' ? $context['oficial_ano_label'] : 'No configurado') ?>" readonly>
        </div>

        <div class="c12 actions">
          <?php if ($returnTo !== ''): ?><a class="btn" href="<?= h($returnTo) ?>">Cancelar</a><?php endif; ?>
          <button class="btn primary" type="submit" <?= $hasFallecidos ? '' : 'disabled' ?>>Guardar y descargar Word</button>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('numero_oficio');
  const btn = document.getElementById('btnSugerir');
  if (!input || !btn) return;
  const suggested = <?= json_encode((string) ($context['next_numero'] ?? '')) ?>;
  btn.addEventListener('click', function () {
    input.value = suggested;
    input.focus();
  });
});
</script>
</body>
</html>
