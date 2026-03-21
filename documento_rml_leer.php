<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoRmlRepository;
use App\Services\DocumentoRmlService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

$service = new DocumentoRmlService(new DocumentoRmlRepository($pdo));
$id = (int) g('id', 0);
$returnTo = g('return_to', '');
$row = $service->detalle($id);
if (!$row) {
    http_response_code(404);
    exit('No encontrado');
}
$returnTo = $returnTo !== '' ? $returnTo : ('documento_rml_listar.php?persona_id=' . (int)$row['persona_id']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>RML - Ver</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{color-scheme: light dark;--bg:#0b1020;--panel:#0f1628;--line:#22304a;--ink:#e8eefc;--muted:#9aa4b2;--chip:#121a30;--r:14px}
@media (prefers-color-scheme: light){:root{--bg:#f6f7fb;--panel:#ffffff;--line:#e5e7eb;--ink:#0f172a;--muted:#64748b;--chip:#f3f4f9}}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(900px 500px at 8% -10%, rgba(136,170,255,.18), transparent 60%),radial-gradient(900px 500px at 92% -10%, rgba(136,170,255,.14), transparent 50%),var(--bg);color:var(--ink);font:13px/1.45 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto}.wrap{max-width:920px;margin:20px auto;padding:0 14px}h1{margin:0 0 10px;font-size:22px;font-weight:900;letter-spacing:.2px;display:flex;align-items:center;gap:8px}.badge{font-size:11px;border:1px solid var(--line);background:var(--chip);border-radius:999px;padding:2px 8px;color:var(--muted);font-weight:800}.card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.01));border:1px solid var(--line);border-radius:var(--r);padding:12px;box-shadow:0 10px 26px rgba(0,0,0,.22)}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:8px}.col-12{grid-column:span 12}.col-6{grid-column:span 6}.col-4{grid-column:span 4}@media (max-width:940px){.col-6,.col-4{grid-column:span 12}}label{display:block;color:var(--muted);font-weight:800;margin:0 0 4px;font-size:11.5px}input,textarea{width:100%;padding:8px 10px;border-radius:10px;border:1px solid var(--line);background:var(--chip);color:inherit;font-size:13px}textarea{min-height:84px;resize:vertical}.rowin{display:flex;gap:6px;align-items:center}.btn{padding:8px 12px;border-radius:10px;border:1px solid var(--line);background:var(--chip);color:inherit;text-decoration:none;font-weight:800;cursor:pointer;height:34px}.btn.small{padding:6px 10px;height:32px;font-size:12.5px}.bar{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px}
</style>
</head>
<body><div class="wrap">
  <div class="bar">
    <h1>RML - Ver <span class="badge">ID #<?= (int)$row['id'] ?></span></h1>
    <div class="rowin">
      <a class="btn small" href="documento_rml_editar.php?id=<?= (int)$row['id'] ?>">Editar</a>
      <a class="btn small" href="documento_rml_eliminar.php?id=<?= (int)$row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
      <a class="btn small" href="<?= h($returnTo) ?>">Volver</a>
    </div>
  </div>
  <div class="card">
    <div class="grid">
      <div class="col-6"><label>Persona</label><input value="<?= h(($row['per_nom'] ?: '-') . ' - DNI ' . ($row['num_doc'] ?: '-')) ?>" readonly></div>
      <div class="col-6"><label>Numero</label><input value="<?= h($row['numero']) ?>" readonly></div>
      <div class="col-4"><label>Fecha</label><input value="<?= h($row['fecha']) ?>" readonly></div>
      <div class="col-4"><label>Incapacidad medico</label><input value="<?= h($row['incapacidad_medico'] ?: 'No requiere') ?>" readonly></div>
      <div class="col-4"><label>Atencion facultativo</label><input value="<?= h($row['atencion_facultativo'] ?: 'No requiere') ?>" readonly></div>
      <div class="col-12"><label>Observaciones</label><textarea readonly><?= h($row['observaciones'] ?: '') ?></textarea></div>
    </div>
  </div>
</div></body></html>
