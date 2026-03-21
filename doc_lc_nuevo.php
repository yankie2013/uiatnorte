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

function lc_emit_saved_and_exit(string $message, int $personaId): void
{
    ?>
    <!doctype html>
    <html lang="es"><head><meta charset="utf-8"><title>Licencia guardada</title></head><body>
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
$personaId = (int) ($_GET['persona_id'] ?? $_POST['persona_id'] ?? 0);
$ok = trim((string) ($_GET['ok'] ?? ''));
$error = '';
$context = null;
$data = $service->defaultData();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['buscar'])) {
    $persona = $service->buscarPersona($personaId > 0 ? $personaId : null, (string) ($_GET['dni'] ?? ''));
    if ($persona !== null) {
        $url = 'doc_lc_nuevo.php?persona_id=' . (int) $persona['id'];
        if ($embed) {
            $url .= '&embed=1';
        }
        if ($returnTo !== '') {
            $url .= '&return_to=' . rawurlencode($returnTo);
        }
        header('Location: ' . $url);
        exit;
    }
    $error = 'No se encontro la persona buscada.';
}

if ($personaId > 0) {
    try {
        $context = $service->contextoPersona($personaId);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $personaId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'save') {
    $data = $service->defaultData($_POST);
    try {
        $service->create([
            'persona_id' => $_POST['persona_id'] ?? 0,
            'clase' => $_POST['clase'] ?? '',
            'categoria' => $_POST['categoria'] ?? '',
            'numero' => $_POST['numero'] ?? '',
            'expedido_por' => $_POST['expedido_por'] ?? '',
            'vigente_desde' => $_POST['vigente_desde'] ?? '',
            'vigente_hasta' => $_POST['vigente_hasta'] ?? '',
            'restricciones' => $_POST['restricciones'] ?? '',
        ]);
        if ($embed) {
            lc_emit_saved_and_exit('Licencia guardada correctamente.', (int) ($_POST['persona_id'] ?? 0));
        }
        header('Location: doc_lc_nuevo.php?persona_id=' . (int) ($_POST['persona_id'] ?? 0) . '&ok=created');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $personaId = (int) ($_POST['persona_id'] ?? 0);
        if ($personaId > 0) {
            $context = $service->contextoPersona($personaId);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'del') {
    $personaId = (int) ($_POST['persona_id'] ?? 0);
    try {
        $service->delete((int) ($_POST['id'] ?? 0), $personaId);
        if ($embed) {
            lc_emit_saved_and_exit('Licencia eliminada correctamente.', $personaId);
        }
        header('Location: doc_lc_nuevo.php?persona_id=' . $personaId . '&ok=deleted');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if ($personaId > 0) {
            $context = $service->contextoPersona($personaId);
        }
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
<title>Nueva licencia de conducir</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1040px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.c4{grid-column:span 4}.field{display:flex;flex-direction:column;gap:6px}.label{font-size:12px;color:var(--muted);font-weight:700}.small{color:var(--muted);font-size:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}textarea{min-height:88px;resize:vertical}.search-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.search-row input{flex:1}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:860px}th,td{padding:12px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;background:rgba(148,163,184,.08)}.stack-actions{display:flex;gap:8px;flex-wrap:wrap}@media(max-width:860px){.c6,.c4{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1 style="margin:0 0 6px">Licencia de conducir <span class="badge">Nueva</span></h1><div class="small">Modulo `documento_lc`</div></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><?php if (!$embed): ?><a class="btn" href="javascript:history.back()">Volver</a><?php endif; ?></div>
  </div>

  <?php if ($ok !== ''): ?><div class="ok"><?php $messages=['created'=>'Licencia registrada correctamente.','deleted'=>'Licencia eliminada correctamente.']; echo h($messages[$ok] ?? 'Operacion realizada correctamente.'); ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <?php if ($personaId <= 0 || $context === null): ?>
    <div class="card">
      <h3 style="margin-top:0;">Seleccionar persona</h3>
      <form method="get" class="grid" autocomplete="off">
        <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
        <?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?= h($returnTo) ?>"><?php endif; ?>
        <div class="c6 field"><label class="label">Buscar por DNI</label><div class="search-row"><input type="text" name="dni" maxlength="12" value="<?= h((string) ($_GET['dni'] ?? '')) ?>" placeholder="Ej: 12345678"><button class="btn" type="submit" name="buscar" value="1">Buscar</button></div></div>
        <div class="c6 field"><label class="label">O usar ID de persona</label><div class="search-row"><input type="number" name="persona_id" min="1" value="<?= h((string) ($_GET['persona_id'] ?? '')) ?>" placeholder="Ej: 15"><button class="btn" type="submit" name="buscar" value="1">Cargar</button></div></div>
      </form>
    </div>
  <?php else: ?>
    <?php $persona = $context['persona']; $licencias = $context['licencias']; $nombre = trim((string) (($persona['apellido_paterno'] ?? '') . ' ' . ($persona['apellido_materno'] ?? '') . ', ' . ($persona['nombres'] ?? ''))); ?>
    <div class="card" style="margin-bottom:14px;"><strong><?= h($nombre) ?></strong><div class="small" style="margin-top:4px;">ID: <?= (int) $persona['id'] ?> - DNI: <?= h((string) ($persona['num_doc'] ?? '-')) ?></div></div>

    <form method="post" class="card" autocomplete="off">
      <input type="hidden" name="do" value="save">
      <input type="hidden" name="persona_id" value="<?= (int) $persona['id'] ?>">
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
      <div class="actions"><button class="btn primary" type="submit">Guardar licencia</button><?php if (!$embed): ?><a class="btn" href="javascript:history.back()">Cancelar</a><?php endif; ?></div>
    </form>

    <div class="table-wrap" style="margin-top:14px;">
      <table>
        <thead><tr><th>ID</th><th>Clase</th><th>Categoria</th><th>Numero</th><th>Expedido por</th><th>Vigencia</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if ($licencias === []): ?>
          <tr><td colspan="7" style="text-align:center;padding:24px;" class="small">No hay licencias registradas para esta persona.</td></tr>
        <?php else: ?>
          <?php foreach ($licencias as $lic): ?>
            <tr>
              <td>#<?= (int) $lic['id'] ?></td>
              <td><?= h((string) (($lic['clase'] ?? '') !== '' ? $lic['clase'] : '-')) ?></td>
              <td><?= h((string) (($lic['categoria'] ?? '') !== '' ? $lic['categoria'] : '-')) ?></td>
              <td><?= h((string) (($lic['numero'] ?? '') !== '' ? $lic['numero'] : '-')) ?></td>
              <td><?= h((string) (($lic['expedido_por'] ?? '') !== '' ? $lic['expedido_por'] : '-')) ?></td>
              <td><?= h((string) (($lic['vigente_desde'] ?? '') !== '' ? $lic['vigente_desde'] : '-')) ?> - <?= h((string) (($lic['vigente_hasta'] ?? '') !== '' ? $lic['vigente_hasta'] : '-')) ?></td>
              <td><div class="stack-actions"><a class="btn" href="doc_lc_leer.php?id=<?= (int) $lic['id'] ?><?= $embed ? '&embed=1' : '' ?><?= $returnTo !== '' ? '&return_to=' . rawurlencode($returnTo) : '' ?>">Ver</a><a class="btn" href="doc_lc_editar.php?id=<?= (int) $lic['id'] ?><?= $embed ? '&embed=1' : '' ?><?= $returnTo !== '' ? '&return_to=' . rawurlencode($returnTo) : '' ?>">Editar</a><form method="post" style="display:inline;" onsubmit="return confirm('Eliminar esta licencia?');"><input type="hidden" name="do" value="del"><input type="hidden" name="id" value="<?= (int) $lic['id'] ?>"><input type="hidden" name="persona_id" value="<?= (int) $persona['id'] ?>"><input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>"><input type="hidden" name="return_to" value="<?= h($returnTo) ?>"><button class="btn danger" type="submit">Eliminar</button></form></div></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<script>
(function(){const mapa={A:['I','IIa','IIb','IIIa','IIIb','IIIc','IV'],B:['IIb','IIc'],C:[]};const selClase=document.getElementById('clase');const selCat=document.getElementById('categoria');if(!selClase||!selCat)return;const selected=<?= json_encode((string) $data['categoria']) ?>;function render(){const clase=selClase.value||'';const opts=mapa[clase]||[];selCat.innerHTML='';if(opts.length===0){selCat.disabled=true;const o=document.createElement('option');o.value='';o.textContent='No aplica';selCat.appendChild(o);return;}selCat.disabled=false;const first=document.createElement('option');first.value='';first.textContent='Selecciona';selCat.appendChild(first);opts.forEach(function(value){const o=document.createElement('option');o.value=value;o.textContent=value;if(value===selected)o.selected=true;selCat.appendChild(o);});}selClase.addEventListener('change',function(){render();});render();})();
</script>
</body>
</html>
