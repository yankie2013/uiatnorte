<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoRmlRepository;
use App\Services\DocumentoRmlService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k])? trim($_GET[$k]) : $d; }

$service = new DocumentoRmlService(new DocumentoRmlRepository($pdo));
$persona_id = (int)g('persona_id',0);
$per = $service->persona($persona_id);
$err = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  try {
    $persona_id = (int)($_POST['persona_id'] ?? 0);
    $service->crear($_POST);
    header('Location: documento_rml_listar.php?ok=1&persona_id='.$persona_id);
    exit;
  } catch (Throwable $e) {
    $err = $e->getMessage();
    $persona_id = (int)($_POST['persona_id'] ?? $persona_id);
    $per = $service->persona($persona_id);
  }
}
$personaMissing = $per === null;
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><title>RML - Nuevo</title><meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
  color-scheme: light dark;
  --bg:#0b1020; --panel:#0f1628; --line:#22304a; --ink:#e8eefc; --muted:#9aa4b2;
  --chip:#121a30; --brand:#88aaff; --ok:#10b981; --err:#ef4444;
  --r:14px;
}
@media (prefers-color-scheme: light){
  :root{ --bg:#f6f7fb; --panel:#ffffff; --line:#e5e7eb; --ink:#0f172a; --muted:#64748b; --chip:#f3f4f9; }
}
*{box-sizing:border-box}
body{
  margin:0;
  background:
    radial-gradient(900px 500px at 8% -10%, rgba(136,170,255,.18), transparent 60%),
    radial-gradient(900px 500px at 92% -10%, rgba(136,170,255,.14), transparent 50%),
    var(--bg);
  color:var(--ink);
  font:13px/1.45 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto;
}
.wrap{max-width:920px;margin:20px auto;padding:0 14px}
h1{margin:0 0 10px; font-size:22px; font-weight:900; letter-spacing:.2px; display:flex; align-items:center; gap:8px}
.badge{font-size:11px;border:1px solid var(--line);background:var(--chip);border-radius:999px;padding:2px 8px;color:var(--muted);font-weight:800}
.note{color:var(--muted); margin-bottom:10px; font-size:12px}
.card{
  background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.01));
  border:1px solid var(--line); border-radius:var(--r); padding:12px;
  box-shadow:0 10px 26px rgba(0,0,0,.22)
}
.grid{display:grid; grid-template-columns:repeat(12,1fr); gap:8px}
.col-12{grid-column:span 12}.col-6{grid-column:span 6}.col-4{grid-column:span 4}.col-3{grid-column:span 3}
@media (max-width:940px){ .col-6,.col-4,.col-3{grid-column:span 12} }
label{display:block; color:var(--muted); font-weight:800; margin:0 0 4px; font-size:11.5px}
input, select, textarea{
  width:100%; padding:8px 10px; border-radius:10px; border:1px solid var(--line); background:var(--chip); color:inherit; font-size:13px;
}
input[type="text"], input[type="date"], input[type="number"]{height:34px}
select{height:34px}
textarea{min-height:84px; resize:vertical}
.rowin{display:flex; gap:6px; align-items:center}
.btn{padding:8px 12px; border-radius:10px; border:1px solid var(--line); background:var(--chip); color:inherit; text-decoration:none; font-weight:800; cursor:pointer; height:34px}
.btn.primary{background:#2e3b66; border-color:#3d4a78}
.btn.small{padding:6px 10px; height:32px; font-size:12.5px}
.actions{display:flex; gap:8px; justify-content:flex-end; margin-top:10px}
.err{background:#3f1012; color:#ffd6d6; border:1px solid #7a1616; padding:8px 10px; border-radius:10px; margin:10px 0; font-size:13px}
.warn{background:#3c2a0d; color:#ffe3aa; border:1px solid #8a6a25; padding:8px 10px; border-radius:10px; margin:10px 0; font-size:13px}
.bar{display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:10px}
</style>

</head><body><div class="wrap">
  <div class="bar">
    <h1>RML - Nuevo</h1>
    <div class="rowin"><a class="btn small" href="documento_rml_listar.php<?= $persona_id? '?persona_id='.$persona_id : '' ?>">Volver</a></div>
  </div>
  <div class="note">Incapacidad m&eacute;dico y Atenci&oacute;n facultativo: ingresa n&uacute;mero de d&iacute;as o escribe "No requiere".</div>
  <?php if(!empty($err)): ?><div class="err"><?=h($err)?></div><?php endif; ?>
  <?php if($personaMissing): ?><div class="warn">Selecciona una persona v&aacute;lida antes de registrar el RML.</div><?php endif; ?>
  <form method="post" class="card" autocomplete="off">
    <div class="grid">
      <div class="col-6">
        <label>Persona</label>
        <?php if($per): ?>
          <input type="hidden" name="persona_id" value="<?= (int)$per['id'] ?>">
          <input type="text" value="<?= h($per['nom'].' - DNI '. $per['num_doc']) ?>" readonly>
        <?php else: ?>
          <input type="number" name="persona_id" value="<?= h($_POST['persona_id'] ?? '') ?>" placeholder="ID de persona" required>
        <?php endif; ?>
      </div>
      <div class="col-6">
        <label>N&uacute;mero</label>
        <input name="numero" value="<?= h($_POST['numero'] ?? '') ?>" required>
      </div>
      <div class="col-4">
        <label>Fecha</label>
        <input type="date" name="fecha" value="<?= h($_POST['fecha'] ?? '') ?>" required>
      </div>
      <div class="col-4">
        <label>Incapacidad m&eacute;dico (d&iacute;as o "No requiere")</label>
        <input name="incapacidad_medico" value="<?= h($_POST['incapacidad_medico'] ?? '') ?>" placeholder="Ej. 3 o No requiere">
      </div>
      <div class="col-4">
        <label>Atenci&oacute;n facultativo (d&iacute;as o "No requiere")</label>
        <input name="atencion_facultativo" value="<?= h($_POST['atencion_facultativo'] ?? '') ?>" placeholder="Ej. 1 o No requiere">
      </div>
      <div class="col-12">
        <label>Observaciones</label>
        <textarea name="observaciones" rows="4" placeholder="Notas..."><?= h($_POST['observaciones'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="actions">
      <a class="btn" href="documento_rml_listar.php<?= $persona_id? '?persona_id='.$persona_id : '' ?>">Cancelar</a>
      <button class="btn primary" type="submit" <?= $personaMissing ? 'disabled aria-disabled="true"' : '' ?>>Guardar</button>
    </div>
  </form>
</div></body></html>
