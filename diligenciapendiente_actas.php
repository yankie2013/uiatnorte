<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\DiligenciaPendienteRepository;
use App\Support\Access;
use App\Support\DiligenciaActas;
use App\Support\DiligenciaDocumentLink;

function h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$row = (new DiligenciaPendienteRepository($pdo))->find($id);
if (!$row) { http_response_code(404); exit('Diligencia no encontrada.'); }
$caseId = (int) $row['accidente_id'];
Access::requireInvestigationCase($caseId);
$options = DiligenciaActas::options($pdo, $caseId);
$selected = DiligenciaActas::selected($pdo, $id);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Access::checkCsrf();
        $selected = $_POST['actas'] ?? [];
        if (!is_array($selected) || array_filter($selected, static fn($value): bool => !is_string($value))) {
            throw new InvalidArgumentException('Selección de actas inválida.');
        }
        DiligenciaActas::sync($pdo, $id, $caseId, $selected);
        DiligenciaDocumentLink::finish($id, $embed);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
if (!$embed) require __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Actas de la diligencia</title>
<style>
:root{color-scheme:light dark;--bg:#f6f7fb;--card:#fff;--text:#172033;--muted:#667085;--line:#dfe3ea}*{box-sizing:border-box}body{margin:0;padding:18px;background:var(--bg);color:var(--text);font:14px system-ui}.card{max-width:900px;margin:auto;padding:24px;border:1px solid var(--line);border-radius:16px;background:var(--card)}h1{margin:0 0 8px;font-size:23px}h2{font-size:17px;margin-top:24px}.muted{color:var(--muted)}.actions{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}.btn{border:1px solid var(--line);border-radius:9px;padding:10px 14px;background:var(--card);color:var(--text);text-decoration:none;font:inherit;cursor:pointer}.primary{background:#2563eb;color:#fff;border-color:#2563eb}.acta-row{display:flex;gap:10px;align-items:center;margin:8px 0;padding:12px;border:1px solid var(--line);border-radius:10px}.acta-row label{display:flex;align-items:center;gap:10px;flex:1;cursor:pointer}.acta-row input{width:18px;height:18px}.error{color:#b91c1c;padding:12px;background:#fff1f2;border-radius:8px}@media(prefers-color-scheme:dark){:root{--bg:#0b1220;--card:#111827;--text:#e5e7eb;--muted:#9ca3af;--line:#34445f}}
</style></head><body><main class="card">
<h1>Actas de la diligencia</h1>
<p class="muted"><?= h($row['contenido'] ?? '') ?></p>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
<h2>Actas relacionadas</h2>
<p class="muted">Selecciona las actas de este expediente que correspondan a la diligencia.</p>
<form method="post">
<input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>"><input type="hidden" name="_csrf" value="<?= h(Access::csrf()) ?>">
<?php if (!$options): ?><p class="muted">Todavía no hay actas registradas en este expediente.</p><?php endif; ?>
<?php foreach ($options as $key => $acta): ?>
<div class="acta-row"><label><input type="checkbox" name="actas[]" value="<?= h($key) ?>" <?= in_array($key, $selected, true) ? 'checked' : '' ?>><span><?= h($acta['label']) ?></span></label><a class="btn" href="<?= h($acta['form']) ?>?id=<?= $acta['id'] ?>&amp;embed=<?= $embed ? 1 : 0 ?>">Ver</a></div>
<?php endforeach; ?>
<div class="actions"><button class="btn primary" type="submit">Guardar vínculos</button><?php if ($embed): ?><button class="btn" type="button" onclick="parent.postMessage({type:'diligencia.close'},location.origin)">Cerrar</button><?php endif; ?></div>
</form>
<h2>Crear nueva acta</h2>
<p class="muted">El acta nueva quedará vinculada automáticamente a esta diligencia.</p>
<div class="actions"><?php foreach (DiligenciaActas::TYPES as $config): ?><a class="btn" href="<?= h($config['form']) ?>?accidente_id=<?= $caseId ?>&amp;embed=<?= $embed ? 1 : 0 ?>&amp;diligencia_id=<?= $id ?>"><?= h($config['label']) ?></a><?php endforeach; ?></div>
</main></body></html>
