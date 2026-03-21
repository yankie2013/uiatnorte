<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\DocumentoLcRepository;
use App\Services\DocumentoLcService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function lc_emit_saved_and_exit_edit(string $message, int $personaId): void
{
    ?>
    <!doctype html>
    <html lang="es"><head><meta charset="utf-8"><title>Licencia actualizada</title></head><body>
    <script>
    try { window.parent && window.parent.postMessage({type:'lc.saved', persona_id:<?= json_encode($personaId) ?>}, '*'); } catch (e) {}
    </script>
    <?= h($message) ?>
    </body></html>
    <?php
    exit;
}

$service = new DocumentoLcService(new DocumentoLcRepository($pdo));
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id');
}

$row = $service->detalle($id);
if ($row === null) {
    http_response_code(404);
    exit('Licencia no encontrada.');
}

$personaContext = $service->contextoPersona((int) $row['persona_id']);
$persona = $personaContext['persona'];
$error = '';
$data = $service->defaultData($row);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'save') {
    $data = $service->defaultData($_POST);
    try {
        $service->update($id, [
            'persona_id' => $row['persona_id'],
            'clase' => $_POST['clase'] ?? '',
            'categoria' => $_POST['categoria'] ?? '',
            'numero' => $_POST['numero'] ?? '',
            'expedido_por' => $_POST['expedido_por'] ?? '',
            'vigente_desde' => $_POST['vigente_desde'] ?? '',
            'vigente_hasta' => $_POST['vigente_hasta'] ?? '',
            'restricciones' => $_POST['restricciones'] ?? '',
        ]);
        if ($embed) {
            lc_emit_saved_and_exit_edit('Licencia actualizada correctamente.', (int) $row['persona_id']);
        }
        header('Location: doc_lc_editar.php?id=' . $id . '&ok=updated');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'del') {
    try {
        $service->delete($id, (int) $row['persona_id']);
        if ($embed) {
            lc_emit_saved_and_exit_edit('Licencia eliminada correctamente.', (int) $row['persona_id']);
        }
        header('Location: doc_lc_nuevo.php?persona_id=' . (int) $row['persona_id'] . '&ok=deleted');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (!$embed) {
    include __DIR__ . '/sidebar.php';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar licencia de conducir</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#0f766e;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#5eead4;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:960px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(15,118,110,.12);color:var(--primary);border:1px solid rgba(15,118,110,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#ecfeff;border-color:transparent}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.c4{grid-column:span 4}.field{display:flex;flex-direction:column;gap:6px}.label{font-size:12px;color:var(--muted);font-weight:700}.small{color:var(--muted);font-size:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}textarea{min-height:88px;resize:vertical}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}@media(max-width:860px){.c6,.c4{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1 style="margin:0 0 6px">Licencia de conducir <span class="badge">Editar</span></h1><div class="small">Registro #<?= (int) $id ?></div></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><?php if (!$embed): ?><a class="btn" href="doc_lc_nuevo.php?persona_id=<?= (int) $row['persona_id'] ?>">Volver</a><?php endif; ?></div>
  </div>

  <?php if (trim((string) ($_GET['ok'] ?? '')) === 'updated'): ?><div class="ok">Licencia actualizada correctamente.</div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:14px;"><strong><?= h(trim((string) (($persona['apellido_paterno'] ?? '') . ' ' . ($persona['apellido_materno'] ?? '') . ', ' . ($persona['nombres'] ?? '')))) ?></strong><div class="small" style="margin-top:4px;">ID: <?= (int) $persona['id'] ?> - DNI: <?= h((string) ($persona['num_doc'] ?? '-')) ?></div></div>

  <form method="post" class="card" autocomplete="off">
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <div class="grid">
      <div class="c4 field"><label class="label">Clase*</label><select name="clase" id="clase" required><option value="">Selecciona</option><?php foreach(['A','B','C'] as $opt): ?><option value="<?= $opt ?>" <?= (string) $data['clase'] === $opt ? 'selected' : '' ?>><?= $opt ?></option><?php endforeach; ?></select></div>
      <div class="c4 field"><label class="label">Categoria</label><select name="categoria" id="categoria"></select></div>
      <div class="c4 field"><label class="label">Numero*</label><input type="text" name="numero" value="<?= h((string) $data['numero']) ?>" required></div>
      <div class="c6 field"><label class="label">Expedido por</label><input type="text" name="expedido_por" value="<?= h((string) $data['expedido_por']) ?>"></div>
      <div class="c3 field"><label class="label">Vigente desde</label><input type="date" name="vigente_desde" value="<?= h((string) $data['vigente_desde']) ?>"></div>
      <div class="c3 field"><label class="label">Vigente hasta</label><input type="date" name="vigente_hasta" value="<?= h((string) $data['vigente_hasta']) ?>"></div>
      <div class="c12 field"><label class="label">Restricciones</label><textarea name="restricciones"><?= h((string) $data['restricciones']) ?></textarea></div>
    </div>
    <div class="actions"><button class="btn primary" type="submit">Guardar cambios</button><button class="btn danger" type="submit" name="do" value="del" onclick="return confirm('Eliminar esta licencia?');">Eliminar</button><?php if (!$embed): ?><a class="btn" href="doc_lc_nuevo.php?persona_id=<?= (int) $row['persona_id'] ?>">Cancelar</a><?php endif; ?></div>
  </form>
</div>
<script>
(function(){const mapa={A:['I','IIa','IIb','IIIa','IIIb','IIIc','IV'],B:['IIb','IIc'],C:[]};const selClase=document.getElementById('clase');const selCat=document.getElementById('categoria');const selected=<?= json_encode((string) $data['categoria']) ?>;function render(){const clase=selClase.value||'';const opts=mapa[clase]||[];selCat.innerHTML='';if(opts.length===0){selCat.disabled=true;const o=document.createElement('option');o.value='';o.textContent='No aplica';selCat.appendChild(o);return;}selCat.disabled=false;const first=document.createElement('option');first.value='';first.textContent='Selecciona';selCat.appendChild(first);opts.forEach(function(value){const o=document.createElement('option');o.value=value;o.textContent=value;if(value===selected)o.selected=true;selCat.appendChild(o);});}selClase.addEventListener('change',function(){render();});render();})();
</script>
</body>
</html>
