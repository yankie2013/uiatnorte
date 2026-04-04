<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\DocumentoRecibidoRepository;
use App\Services\DocumentoRecibidoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$service = new DocumentoRecibidoService(new DocumentoRecibidoRepository($pdo));
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
$accidenteId = isset($_GET['accidente_id']) && $_GET['accidente_id'] !== '' ? (int) $_GET['accidente_id'] : null;
$ctx = $service->formContext($accidenteId);
$data = $service->defaultData(['accidente_id' => $accidenteId ?: '']);
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $service->defaultData($_POST);
    try {
        $newId = $service->crear($_POST);
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>try{ window.parent.postMessage({type:"documento_recibido.saved"}, "*"); }catch(_){ }</script><body style="font:13px Inter,sans-serif;padding:16px">Guardado...</body>';
            exit;
        }
        $redir = 'documento_recibido_listar.php?msg=creado';
        if (!empty($_POST['accidente_id'])) {
            $redir .= '&accidente_id=' . urlencode((string) $_POST['accidente_id']);
        }
        header('Location: ' . $redir);
        exit;
    } catch (Throwable $e) {
        $errores[] = $e->getMessage();
        $ctx = $service->formContext(($data['accidente_id'] !== '' ? (int) $data['accidente_id'] : null));
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Nuevo Documento Recibido</title>
<style>
  :root{--bg:#fafafa;--panel:#ffffff;--text:#111827;--muted:#6b7280;--border:#e5e7eb;--primary:#0b84ff;--radius:10px;--gap:14px;--max-width:980px;--shadow:0 6px 20px rgba(16,24,40,0.06)}
  @media (prefers-color-scheme: dark){:root{--bg:#0b1220;--panel:#0f1724;--text:#e6eef8;--muted:#9aa7bf;--border:#243041;--primary:#4ea8ff}}
  *{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;background:var(--bg);color:var(--text);padding:20px}
  .wrap{max-width:var(--max-width);margin:18px auto;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
  .head{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap}
  .btn{display:inline-flex;gap:8px;padding:8px 12px;border-radius:8px;text-decoration:none;font-weight:700;border:1px solid transparent;cursor:pointer}
  .btn.secondary{background:transparent;border:1px solid var(--border);color:var(--text)} .btn.primary{background:var(--primary);color:#fff}
  form.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:var(--gap)} .full{grid-column:1/-1}
  label{display:block;font-weight:700;margin-bottom:6px} input[type="text"],input[type="date"],select,textarea{width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);font-size:.95rem} textarea{min-height:120px;resize:vertical}
  .help{font-size:.9rem;color:var(--muted);margin-top:6px} .error{background:#fff0f0;padding:10px;border-radius:8px;border:1px solid #f5c2c2;color:#8a1f1f;margin-bottom:12px}
  @media (max-width:800px){form.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div>
      <h1 style="margin:0;font-size:1.1rem;">Nuevo Documento Recibido</h1>
      <div class="help">Puedes asociarlo a un accidente y, opcionalmente, a un oficio existente.</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($embed): ?>
        <button type="button" class="btn secondary" onclick="try{window.parent&&window.parent.postMessage({type:'documento_recibido.close'},'*');}catch(e){}">Cerrar</button>
      <?php else: ?>
        <a href="documento_recibido_listar.php" class="btn secondary">Volver</a>
        <a href="index.php" class="btn secondary">Panel</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($errores): ?>
    <div class="error"><?php foreach ($errores as $e): ?>- <?= h($e) ?><br><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="POST" class="form-grid" novalidate>
    <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <div>
      <label for="accidente_id">Accidente</label>
      <select id="accidente_id" name="accidente_id">
        <option value="">(ninguno)</option>
        <?php foreach ($ctx['accidentes'] as $a): ?>
          <option value="<?= h($a['id']) ?>" <?= ((string)$data['accidente_id'] === (string)$a['id']) ? 'selected' : '' ?>><?= h($a['id']) ?> - <?= h(($a['sidpol'] ?? '')) ?><?= !empty($a['lugar']) ? (' - '.h($a['lugar'])) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="fecha_recepcion">Fecha de recepcion</label>
      <input id="fecha_recepcion" type="date" name="fecha_recepcion" value="<?= h($data['fecha_recepcion']) ?>" readonly>
      <div class="help">Se registra automaticamente con la fecha de hoy.</div>
    </div>
    <div>
      <label for="fecha_documento">Fecha del documento</label>
      <input id="fecha_documento" type="date" name="fecha_documento" value="<?= h($data['fecha_documento']) ?>">
    </div>
    <div class="full">
      <label for="asunto">Asunto</label>
      <input id="asunto" type="text" name="asunto" value="<?= h($data['asunto']) ?>">
    </div>
    <div>
      <label for="entidad_persona">Entidad / Persona</label>
      <input id="entidad_persona" type="text" name="entidad_persona" value="<?= h($data['entidad_persona']) ?>">
    </div>
    <div>
      <label for="tipo_documento">Tipo de documento</label>
      <input id="tipo_documento" type="text" name="tipo_documento" value="<?= h($data['tipo_documento']) ?>">
    </div>
    <div>
      <label for="numero_documento">Número de documento</label>
      <input id="numero_documento" type="text" name="numero_documento" value="<?= h($data['numero_documento']) ?>">
    </div>
    <div>
      <label for="estado">Estado</label>
      <select id="estado" name="estado">
        <option value="">(ninguno)</option>
        <?php foreach ($ctx['estados'] as $estado): ?>
          <option value="<?= h($estado) ?>" <?= ($data['estado'] === $estado) ? 'selected' : '' ?>><?= h($estado) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="full">
      <label for="contenido">Contenido</label>
      <textarea id="contenido" name="contenido"><?= h($data['contenido']) ?></textarea>
    </div>
    <div>
      <label for="referencia_oficio_id">Referencia a oficio</label>
      <select id="referencia_oficio_id" name="referencia_oficio_id">
        <option value="">(ninguno)</option>
        <?php foreach ($ctx['oficios'] as $o): ?>
          <option value="<?= h($o['id']) ?>" <?= ((string)$data['referencia_oficio_id'] === (string)$o['id']) ? 'selected' : '' ?>><?= h($service->oficioLabel($o, $ctx['asuntos'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="full" style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
      <div>
        <?php if ($embed): ?>
          <button type="button" class="btn secondary" onclick="try{window.parent&&window.parent.postMessage({type:'documento_recibido.close'},'*');}catch(e){}">Cancelar</button>
        <?php else: ?>
          <a class="btn secondary" href="documento_recibido_listar.php">Cancelar</a>
        <?php endif; ?>
      </div>
      <div><button class="btn primary" type="submit">Guardar Documento</button></div>
    </div>
  </form>
</div>
</body>
</html>
