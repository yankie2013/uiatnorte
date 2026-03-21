<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\AbogadoRepository;
use App\Services\AbogadoService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new AbogadoService(new AbogadoRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? $_POST['accidente_id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta accidente_id');
}

$error = '';
$data = $service->defaultData(null, $accidenteId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'accidente_id' => $_POST['accidente_id'] ?? $accidenteId,
        'persona_id' => $_POST['persona_id'] ?? '',
        'nombres' => $_POST['nombres'] ?? '',
        'apellido_paterno' => $_POST['apellido_paterno'] ?? '',
        'apellido_materno' => $_POST['apellido_materno'] ?? '',
        'colegiatura' => $_POST['colegiatura'] ?? '',
        'registro' => $_POST['registro'] ?? '',
        'casilla_electronica' => $_POST['casilla_electronica'] ?? '',
        'domicilio_procesal' => $_POST['domicilio_procesal'] ?? '',
        'celular' => $_POST['celular'] ?? '',
        'email' => $_POST['email'] ?? '',
    ];

    try {
        $service->create($data);
        header('Location: abogado_listar.php?accidente_id=' . $accidenteId . '&ok=created');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$ctx = $service->formContext($accidenteId);
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nuevo abogado</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#0284c7;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#38bdf8;--danger:#fecaca}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1040px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(2,132,199,.12);color:var(--primary);border:1px solid rgba(2,132,199,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c6{grid-column:span 6}.c4{grid-column:span 4}.field{display:flex;flex-direction:column;gap:6px}.label{font-size:12px;color:var(--muted);font-weight:700}input,select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}.small{color:var(--muted);font-size:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}.role{display:inline-block;padding:3px 8px;border-radius:999px;border:1px solid var(--border);font-size:11px;color:var(--muted);margin-top:6px}.header-card{margin-bottom:14px}@media(max-width:820px){.c6,.c4{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Abogado <span class="badge">Nuevo</span></h1>
      <div class="small">Accidente ID: <?= (int) $accidenteId ?></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="abogado_listar.php?accidente_id=<?= (int) $accidenteId ?>">Volver</a>
      <button class="btn primary" type="submit" form="frmAbogado">Guardar abogado</button>
    </div>
  </div>

  <?php if ($ctx['accidente']): ?>
    <div class="card header-card">
      <strong>Accidente #<?= (int) $ctx['accidente']['id'] ?></strong>
      <div class="small" style="margin-top:4px;">
        SIDPOL: <?= h((string) ($ctx['accidente']['sidpol'] ?? 'Sin SIDPOL')) ?>
        - Fecha: <?= h((string) ($ctx['accidente']['fecha_accidente'] ?? 'Sin fecha')) ?>
        - Lugar: <?= h((string) ($ctx['accidente']['lugar'] ?? 'Sin lugar')) ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <form method="post" class="card" id="frmAbogado" autocomplete="off">
    <input type="hidden" name="accidente_id" value="<?= (int) $accidenteId ?>">

    <div class="grid">
      <div class="c6 field">
        <label class="label" for="persona_id">Representa a*</label>
        <select name="persona_id" id="persona_id" required>
          <option value="">Selecciona</option>
          <?php foreach ($ctx['personas'] as $persona): ?>
            <option value="<?= (int) $persona['id'] ?>" data-roles="<?= h((string) ($persona['roles'] ?? '')) ?>" <?= (string) $data['persona_id'] === (string) $persona['id'] ? 'selected' : '' ?>>
              <?= h((string) ($persona['nombre'] ?? '')) ?> - <?= h((string) ($persona['roles'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="small">Roles vinculados: <span class="role" id="personaRole">-</span></div>
      </div>
      <div class="c6 field">
        <label class="label" for="colegiatura">Colegiatura*</label>
        <input id="colegiatura" name="colegiatura" value="<?= h($data['colegiatura']) ?>" required>
      </div>

      <div class="c4 field">
        <label class="label" for="apellido_paterno">Apellido paterno*</label>
        <input id="apellido_paterno" name="apellido_paterno" value="<?= h($data['apellido_paterno']) ?>" required>
      </div>
      <div class="c4 field">
        <label class="label" for="apellido_materno">Apellido materno</label>
        <input id="apellido_materno" name="apellido_materno" value="<?= h($data['apellido_materno']) ?>">
      </div>
      <div class="c4 field">
        <label class="label" for="nombres">Nombres*</label>
        <input id="nombres" name="nombres" value="<?= h($data['nombres']) ?>" required>
      </div>

      <div class="c4 field">
        <label class="label" for="registro">Registro</label>
        <input id="registro" name="registro" value="<?= h((string) $data['registro']) ?>">
      </div>
      <div class="c4 field">
        <label class="label" for="casilla_electronica">Casilla electronica</label>
        <input id="casilla_electronica" name="casilla_electronica" value="<?= h((string) $data['casilla_electronica']) ?>">
      </div>
      <div class="c4 field">
        <label class="label" for="celular">Celular</label>
        <input id="celular" name="celular" value="<?= h((string) $data['celular']) ?>">
      </div>

      <div class="c6 field">
        <label class="label" for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= h((string) $data['email']) ?>">
      </div>
      <div class="c6 field">
        <label class="label" for="domicilio_procesal">Domicilio procesal</label>
        <input id="domicilio_procesal" name="domicilio_procesal" value="<?= h((string) $data['domicilio_procesal']) ?>">
      </div>
    </div>

    <div class="actions">
      <a class="btn" href="abogado_listar.php?accidente_id=<?= (int) $accidenteId ?>">Cancelar</a>
      <button class="btn primary" type="submit">Guardar</button>
    </div>
  </form>
</div>
<script>
(function(){
  const select = document.getElementById('persona_id');
  const target = document.getElementById('personaRole');
  if (!select || !target) return;
  function syncRole() {
    const option = select.selectedOptions[0];
    target.textContent = option && option.dataset.roles ? option.dataset.roles : '-';
  }
  select.addEventListener('change', syncRole);
  syncRole();
})();
</script>
</body>
</html>
