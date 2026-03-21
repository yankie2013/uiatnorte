<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\DiligenciaPendienteRepository;
use App\Services\DiligenciaPendienteService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new DiligenciaPendienteService(new DiligenciaPendienteRepository($pdo));
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$detail = $id > 0 ? $service->detalle($id) : null;

if ($id <= 0 || $detail === null) {
    http_response_code(404);
    exit('Diligencia no encontrada.');
}

$row = $detail['row'];
$accidenteId = (int) ($row['accidente_id'] ?? 0);
$message = trim((string) ($_GET['msg'] ?? ''));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $service->eliminar($id);
        $target = 'diligenciapendiente_listar.php';
        if ($accidenteId > 0) {
            $target .= '?accidente_id=' . urlencode((string) $accidenteId) . '&msg=' . urlencode('Diligencia eliminada correctamente.');
        } else {
            $target .= '?msg=' . urlencode('Diligencia eliminada correctamente.');
        }
        header('Location: ' . $target);
        exit;
    } catch (Throwable $e) {
        $errors = preg_split('/\r?\n/', trim($e->getMessage())) ?: ['No se pudo eliminar la diligencia.'];
    }
}

@include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ver diligencia #<?= h($id) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root {
  --bg:#f6f7fb; --card:#fff; --text:#111827; --muted:#6b7280; --accent:#1d4ed8; --success:#166534; --danger:#b91c1c; --border:#d9e0ea;
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg:#0b1220; --card:#111827; --text:#e5e7eb; --muted:#9ca3af; --accent:#60a5fa; --success:#bbf7d0; --danger:#fecaca; --border:#243041;
  }
}
body{margin:0;padding:24px;background:var(--bg);color:var(--text);font-family:"Segoe UI",sans-serif}
.container{max-width:980px;margin:0 auto}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 12px 32px rgba(0,0,0,.08)}
.header{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
.title{margin:0;font-size:1.65rem}.sub{color:var(--muted);margin-top:6px}
.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-block;padding:11px 16px;border-radius:10px;text-decoration:none;border:1px solid var(--border);background:transparent;color:var(--text);font-weight:600;cursor:pointer}.btn.primary{background:var(--accent);color:#fff;border-color:transparent}.btn.danger{color:var(--danger)}
.grid{display:grid;grid-template-columns:1.4fr .9fr;gap:18px;margin-top:18px}.panel{border:1px solid var(--border);border-radius:14px;padding:18px}.label{display:block;color:var(--muted);font-weight:700;font-size:.9rem;margin-bottom:6px}.value{white-space:pre-wrap;line-height:1.4}.badge{display:inline-block;padding:7px 12px;border-radius:999px;background:rgba(29,78,216,.1);color:var(--accent);font-weight:700}.alert{padding:12px 14px;border-radius:12px;margin-top:16px}.alert.success{background:rgba(22,163,74,.12);color:var(--success)}.alert.error{background:rgba(220,38,38,.12);color:var(--danger)}.list{margin:0;padding-left:18px}.muted{color:var(--muted)}
@media (max-width: 900px){body{padding:14px}.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <div class="header">
      <div>
        <h1 class="title">Diligencia #<?= h($id) ?></h1>
        <div class="sub">Accidente vinculado: <?= $accidenteId > 0 ? ('#' . h($accidenteId)) : 'sin accidente' ?></div>
      </div>
      <div class="actions">
        <?php if ($accidenteId > 0): ?>
          <a class="btn primary" href="oficios_nuevo.php?accidente_id=<?= h($accidenteId) ?>">+ Nuevo oficio</a>
          <a class="btn primary" href="citacion_nuevo.php?accidente_id=<?= h($accidenteId) ?>">+ Nueva citación</a>
        <?php endif; ?>
        <a class="btn" href="diligenciapendiente_editar.php?id=<?= h($id) ?>">Editar</a>
      </div>
    </div>

    <?php if ($message !== ''): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="alert error"><?= h($error) ?></div><?php endforeach; ?>

    <div class="grid">
      <div class="panel">
        <div style="margin-bottom:16px;">
          <span class="label">Tipo de diligencia</span>
          <div><?= !empty($detail['tipo_nombre']) ? ('<span class="badge">' . h($detail['tipo_nombre']) . '</span>') : '<span class="muted">Sin tipo</span>' ?></div>
        </div>

        <div style="margin-bottom:16px;">
          <span class="label">Estado</span>
          <div><?= h($row['estado'] ?? 'Pendiente') ?></div>
        </div>

        <div style="margin-bottom:16px;">
          <span class="label">Contenido / observaciones</span>
          <div class="value"><?= h($row['contenido'] ?? '') ?></div>
        </div>

        <div style="margin-bottom:16px;">
          <span class="label">Documento realizado</span>
          <div class="value"><?= h($row['documento_realizado'] ?? '') ?: '<span class="muted">Sin registro</span>' ?></div>
        </div>

        <div>
          <span class="label">Documentos recibidos</span>
          <div class="value"><?= h($row['documentos_recibidos'] ?? '') ?: '<span class="muted">Sin registro</span>' ?></div>
        </div>
      </div>

      <div class="panel">
        <div style="margin-bottom:16px;">
          <span class="label">Oficio relacionado</span>
          <div><?= $detail['oficio_label'] !== '' ? h($detail['oficio_label']) : '<span class="muted">Sin oficio relacionado</span>' ?></div>
        </div>

        <div style="margin-bottom:16px;">
          <span class="label">Citaciones relacionadas</span>
          <?php if ($detail['citacion_ids']): ?>
            <ul class="list">
              <?php foreach ($detail['citacion_ids'] as $citacionId): ?>
                <li><?= h($detail['citaciones_labels'][$citacionId] ?? ('Citación #' . $citacionId)) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="muted">Sin citaciones relacionadas</div>
          <?php endif; ?>
        </div>

        <div style="margin-bottom:16px;">
          <span class="label">Creado</span>
          <div><?= h($row['creado_en'] ?? '') ?: '<span class="muted">Sin dato</span>' ?></div>
        </div>

        <div>
          <span class="label">Última actualización</span>
          <div><?= h($row['actualizado_en'] ?? '') ?: '<span class="muted">Sin dato</span>' ?></div>
        </div>
      </div>
    </div>

    <div class="actions" style="margin-top:18px;">
      <a class="btn" href="diligenciapendiente_listar.php<?= $accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : '' ?>">Volver al listado</a>
      <?php if ($accidenteId > 0): ?>
        <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= h($accidenteId) ?>">Volver al accidente</a>
      <?php endif; ?>
      <form method="post" style="display:inline;">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn danger" onclick="return confirm('¿Eliminar diligencia #<?= h($id) ?>?');">Eliminar</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
