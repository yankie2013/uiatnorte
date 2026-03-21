<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\DiligenciaConductorRepository;
use App\Services\DiligenciaConductorService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new DiligenciaConductorService(new DiligenciaConductorRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? $_POST['accidente_id'] ?? 0);
$personaId = (int) ($_GET['persona_id'] ?? $_POST['persona_id'] ?? 0);
$vehiculoId = (int) ($_GET['vehiculo_id'] ?? $_POST['vehiculo_id'] ?? 0);
$invPerIdQs = (int) ($_GET['inv_per_id'] ?? $_POST['inv_per_id'] ?? 0);
$involucradoPersonaId = (int) ($_GET['involucrado_persona_id'] ?? $_POST['involucrado_persona_id'] ?? $invPerIdQs);
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;

try {
    $detail = $service->detailContext($accidenteId, $involucradoPersonaId, $personaId, $vehiculoId);
} catch (Throwable $e) {
    http_response_code(400);
    echo '<pre style="color:#f87171;background:#111;padding:12px;border-radius:8px">' . h($e->getMessage()) . "\naccidente_id={$accidenteId} involucrado_persona_id={$involucradoPersonaId}</pre>";
    exit;
}

$row = $detail['row'];
$data = $service->defaultData($row, [
    'accidente_id' => $accidenteId,
    'involucrado_persona_id' => $involucradoPersonaId,
    'persona_id' => $personaId,
    'vehiculo_id' => $vehiculoId,
]);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'accidente_id' => $accidenteId,
        'involucrado_persona_id' => $involucradoPersonaId,
        'persona_id' => $personaId,
        'vehiculo_id' => $vehiculoId,
        'licencia_numero' => $_POST['licencia_numero'] ?? '',
        'licencia_categoria' => $_POST['licencia_categoria'] ?? '',
        'licencia_vencimiento' => $_POST['licencia_vencimiento'] ?? '',
        'dosaje_fecha' => $_POST['dosaje_fecha'] ?? '',
        'dosaje_resultado' => $_POST['dosaje_resultado'] ?? 'No realizado',
        'dosaje_gramos' => $_POST['dosaje_gramos'] ?? '',
        'recon_medico_fecha' => $_POST['recon_medico_fecha'] ?? '',
        'recon_medico_result' => $_POST['recon_medico_result'] ?? 'No realizado',
        'toxico_fecha' => $_POST['toxico_fecha'] ?? '',
        'toxico_resultado' => $_POST['toxico_resultado'] ?? 'No realizado',
        'toxico_sustancias' => $_POST['toxico_sustancias'] ?? '',
        'manif_inicio' => $_POST['manif_inicio'] ?? '',
        'manif_fin' => $_POST['manif_fin'] ?? '',
        'manif_duracion_min' => $_POST['manif_duracion_min'] ?? '',
    ];

    try {
        $service->save($data);
        $detail = $service->detailContext($accidenteId, $involucradoPersonaId, $personaId, $vehiculoId);
        $data = $service->defaultData($detail['row'], [
            'accidente_id' => $accidenteId,
            'involucrado_persona_id' => $involucradoPersonaId,
            'persona_id' => $personaId,
            'vehiculo_id' => $vehiculoId,
        ]);
        $success = 'Guardado correctamente.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (!$embed) {
    @include __DIR__ . '/sidebar.php';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Diligencias del conductor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--ok:#166534;--danger:#991b1b}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#111827;--text:#e5e7eb;--muted:#94a3b8;--border:#243041;--primary:#3b82f6;--ok:#bbf7d0;--danger:#fecaca}}
*{box-sizing:border-box}body{margin:0;padding:24px;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:920px;margin:0 auto}.hdr{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}.ttl{font-size:1.35rem;font-weight:900}.badge{display:inline-block;padding:4px 10px;border-radius:999px;border:1px solid var(--border);background:rgba(148,163,184,.08);color:var(--muted)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.f{grid-column:span 6}.f label{display:block;font-weight:700;font-size:12px;color:var(--muted);margin-bottom:6px}.f input,.f select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:transparent;color:var(--text)}.actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:16px}.btn{padding:10px 14px;border:1px solid var(--border);background:var(--card);border-radius:10px;color:var(--text);cursor:pointer;font-weight:700;text-decoration:none}.btn.primary{background:var(--primary);border-color:transparent;color:#fff}.msg{margin-bottom:12px;padding:10px 12px;border-radius:10px;background:rgba(22,163,74,.12);color:var(--ok);font-weight:700}.err{margin-bottom:12px;padding:10px 12px;border-radius:10px;background:rgba(220,38,38,.12);color:var(--danger);font-weight:700;white-space:pre-wrap}.subcard{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:12px;margin-bottom:12px}.muted{color:var(--muted)}@media(max-width:740px){body{padding:14px}.f{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <div class="ttl">Diligencias del conductor</div>
    <div class="badge">Accidente #<?= (int) $accidenteId ?> · Involucrado #<?= (int) $involucradoPersonaId ?><?php if ($detail['vehiculo_placa'] !== ''): ?> · Vehiculo <?= h($detail['vehiculo_placa']) ?><?php endif; ?></div>
  </div>

  <?php if ($detail['persona_nombre'] !== ''): ?>
    <div class="subcard"><strong>Conductor:</strong> <?= h($detail['persona_nombre']) ?></div>
  <?php endif; ?>

  <div class="card">
    <?php if ($success !== ''): ?><div class="msg"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="err">Error: <?= h($error) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="accidente_id" value="<?= (int) $accidenteId ?>">
      <input type="hidden" name="persona_id" value="<?= (int) $personaId ?>">
      <input type="hidden" name="vehiculo_id" value="<?= (int) $vehiculoId ?>">
      <input type="hidden" name="involucrado_persona_id" value="<?= (int) $involucradoPersonaId ?>">
      <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">

      <div class="grid">
        <div class="f"><label>N° Licencia</label><input name="licencia_numero" value="<?= h((string) $data['licencia_numero']) ?>"></div>
        <div class="f"><label>Categoria</label><input name="licencia_categoria" value="<?= h((string) $data['licencia_categoria']) ?>"></div>
        <div class="f"><label>Licencia · vencimiento</label><input type="date" name="licencia_vencimiento" value="<?= h((string) $data['licencia_vencimiento']) ?>"></div>
        <div class="f"></div>

        <div class="f"><label>Dosaje · fecha y hora</label><input type="datetime-local" name="dosaje_fecha" value="<?= h((string) $data['dosaje_fecha']) ?>"></div>
        <div class="f"><label>Dosaje · resultado</label><select name="dosaje_resultado"><?php foreach (['Positivo','Negativo','No realizado'] as $item): ?><option value="<?= $item ?>" <?= (string) $data['dosaje_resultado'] === $item ? 'selected' : '' ?>><?= $item ?></option><?php endforeach; ?></select></div>
        <div class="f"><label>Dosaje · gramos (g/L)</label><input type="number" step="0.01" name="dosaje_gramos" value="<?= h((string) $data['dosaje_gramos']) ?>"></div>
        <div class="f"></div>

        <div class="f"><label>Reconocimiento medico · fecha y hora</label><input type="datetime-local" name="recon_medico_fecha" value="<?= h((string) $data['recon_medico_fecha']) ?>"></div>
        <div class="f"><label>Reconocimiento medico · resultado</label><select name="recon_medico_result"><?php foreach (['Apto','No apto','Observado','No realizado'] as $item): ?><option value="<?= $item ?>" <?= (string) $data['recon_medico_result'] === $item ? 'selected' : '' ?>><?= $item ?></option><?php endforeach; ?></select></div>

        <div class="f"><label>Toxicologico · fecha y hora</label><input type="datetime-local" name="toxico_fecha" value="<?= h((string) $data['toxico_fecha']) ?>"></div>
        <div class="f"><label>Toxicologico · resultado</label><select name="toxico_resultado"><?php foreach (['Positivo','Negativo','No realizado'] as $item): ?><option value="<?= $item ?>" <?= (string) $data['toxico_resultado'] === $item ? 'selected' : '' ?>><?= $item ?></option><?php endforeach; ?></select></div>
        <div class="f"><label>Toxicologico · sustancias</label><input name="toxico_sustancias" value="<?= h((string) $data['toxico_sustancias']) ?>" placeholder="Cannabis, cocaina, ..."></div>
        <div class="f"></div>

        <div class="f"><label>Manifestacion · inicio</label><input type="datetime-local" name="manif_inicio" value="<?= h((string) $data['manif_inicio']) ?>"></div>
        <div class="f"><label>Manifestacion · fin</label><input type="datetime-local" name="manif_fin" value="<?= h((string) $data['manif_fin']) ?>"></div>
        <div class="f"><label>Manifestacion · duracion (min)</label><input type="number" name="manif_duracion_min" value="<?= h((string) $data['manif_duracion_min']) ?>"></div>
        <div class="f"></div>
      </div>

      <div class="actions">
        <?php if (!$embed): ?><a class="btn" href="javascript:history.back()">Cancelar</a><?php endif; ?>
        <button class="btn primary" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
