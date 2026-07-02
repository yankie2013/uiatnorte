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
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
$detail = $id > 0 ? $service->detalle($id) : null;

if ($id <= 0 || $detail === null) {
    http_response_code(404);
    exit('Diligencia no encontrada.');
}

$row = $detail['row'];
$accidenteId = (int) ($row['accidente_id'] ?? 0);
$message = trim((string) ($_GET['msg'] ?? ''));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve') {
    try {
        $data = $service->defaultData($row);
        $data['oficio_id'] = $_POST['oficio_id'] ?? '';
        $data['citacion_id'] = $_POST['citacion_id'] ?? [];
        $data['documento_realizado'] = $_POST['documento_realizado'] ?? ($data['documento_realizado'] ?? '');
        if (!empty($_POST['marcar_realizado'])) {
            $data['estado'] = 'Realizado';
        }
        $service->actualizar($id, $data);
        $target = 'diligenciapendiente_ver.php?id=' . urlencode((string) $id)
            . '&embed=' . ($embed ? '1' : '0')
            . '&return_to=' . urlencode($returnTo)
            . '&msg=' . urlencode('Diligencia vinculada correctamente.');
        header('Location: ' . $target);
        exit;
    } catch (Throwable $e) {
        $errors = preg_split('/\r?\n/', trim($e->getMessage())) ?: ['No se pudo vincular la diligencia.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $service->eliminar($id);
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>try{window.parent.postMessage({type:"diligencia.deleted"},"*");}catch(_){}</script><body style="font:13px Inter,sans-serif;padding:16px">Eliminado...</body>';
            exit;
        }
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

if (!$embed) {
    @include __DIR__ . '/sidebar.php';
}
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
body{margin:0;padding:24px;background:var(--bg);color:var(--text);font-family:"Segoe UI",sans-serif}body.is-embed{padding:14px}
.container{max-width:980px;margin:0 auto}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 12px 32px rgba(0,0,0,.08)}
.header{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
.title{margin:0;font-size:1.65rem}.sub{color:var(--muted);margin-top:6px}
.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-block;padding:11px 16px;border-radius:10px;text-decoration:none;border:1px solid var(--border);background:transparent;color:var(--text);font-weight:600;cursor:pointer}.btn.primary{background:var(--accent);color:#fff;border-color:transparent}.btn.danger{color:var(--danger)}
.grid{display:grid;grid-template-columns:1.4fr .9fr;gap:18px;margin-top:18px}.panel{border:1px solid var(--border);border-radius:14px;padding:18px}.label{display:block;color:var(--text);font-weight:800;font-size:.9rem;margin-bottom:6px}.value{white-space:pre-wrap;line-height:1.4}.badge{display:inline-block;padding:7px 12px;border-radius:999px;background:rgba(29,78,216,.1);color:var(--accent);font-weight:700}.alert{padding:12px 14px;border-radius:12px;margin-top:16px}.alert.success{background:rgba(22,163,74,.12);color:var(--success)}.alert.error{background:rgba(220,38,38,.12);color:var(--danger)}.list{margin:0;padding-left:18px}.muted{color:var(--muted)}
.resolve-panel{margin-top:18px;border:1px solid var(--border);border-radius:16px;padding:18px;background:rgba(29,78,216,.035)}.resolve-title{margin:0 0 4px;font-size:1.05rem}.quick-actions{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 16px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field-full{grid-column:1/-1}.input,select{width:100%;box-sizing:border-box;border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:var(--card);color:var(--text)}.pick-list{display:grid;gap:8px;max-height:290px;overflow:auto;padding-right:4px}.pick-card{display:flex;align-items:flex-start;gap:9px;border:1px solid var(--border);border-radius:12px;padding:10px;background:var(--card)}.pick-card input{margin-top:4px}.pick-card strong{display:block}.pick-card small{display:block;color:var(--muted);line-height:1.35;margin-top:3px}.checkline{display:flex;gap:8px;align-items:center;margin-top:10px}.checkline input{width:auto}
@media (max-width: 900px){body{padding:14px}.grid{grid-template-columns:1fr}}
@media (max-width: 720px){.form-grid{grid-template-columns:1fr}.field-full{grid-column:auto}}
</style>
</head>
<body class="<?= $embed ? 'is-embed' : '' ?>">
<div class="container">
  <div class="card">
    <div class="header">
      <div>
        <h1 class="title">Diligencia #<?= h($id) ?></h1>
        <div class="sub">Accidente vinculado: <?= $accidenteId > 0 ? ('#' . h($accidenteId)) : 'sin accidente' ?></div>
      </div>
      <div class="actions">
        <?php if (!$embed && $accidenteId > 0): ?>
          <a class="btn primary" href="oficios_nuevo.php?accidente_id=<?= h($accidenteId) ?>">+ Nuevo oficio</a>
          <a class="btn primary" href="citacion_nuevo.php?accidente_id=<?= h($accidenteId) ?>">+ Nueva citación</a>
        <?php endif; ?>
        <a class="btn primary" href="diligenciapendiente_editar.php?id=<?= h($id) ?>&embed=<?= $embed ? 1 : 0 ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a>
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
          <span class="label">Documento recibido</span>
          <div class="value"><?= h($row['documentos_recibidos'] ?? '') ?: '<span class="muted">Sin registro</span>' ?></div>
        </div>
      </div>

      <div class="panel">
        <div style="margin-bottom:16px;">
          <span class="label">Oficio realizado</span>
          <div><?= $detail['oficio_label'] !== '' ? h($detail['oficio_label']) : '<span class="muted">Sin oficio realizado</span>' ?></div>
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

    <?php
      $ctx = $detail['ctx'] ?? ['oficios' => [], 'citaciones' => []];
      $selectedCitaciones = array_fill_keys(array_map('intval', $detail['citacion_ids'] ?? []), true);
      $returnHere = 'diligenciapendiente_ver.php?id=' . (int) $id;
      $oficioNuevoUrl = 'oficios_nuevo.php?accidente_id=' . (int) $accidenteId . '&return_to=' . urlencode($returnHere);
      $citacionNuevaUrl = 'citacion_nuevo.php?accidente_id=' . (int) $accidenteId . '&return_to=' . urlencode($returnHere);
      $actaVisualUrl = 'acta_visualizacion_form.php?accidente_id=' . (int) $accidenteId;
      $actaEntregaUrl = 'acta_entrega_vehiculo_form.php?accidente_id=' . (int) $accidenteId;
    ?>
    <section class="resolve-panel">
      <h2 class="resolve-title">Resolver diligencia</h2>
      <div class="muted">Crea el documento que corresponda o vincula uno que ya exista. Al guardar puedes marcar esta diligencia como realizada.</div>

      <?php if ($accidenteId > 0): ?>
        <div class="quick-actions">
          <a class="btn primary" href="<?= h($oficioNuevoUrl) ?>" <?= $embed ? 'target="_top"' : '' ?>>Crear oficio</a>
          <a class="btn primary" href="<?= h($citacionNuevaUrl) ?>" <?= $embed ? 'target="_top"' : '' ?>>Crear citacion</a>
          <a class="btn" href="<?= h($actaVisualUrl) ?>" <?= $embed ? 'target="_top"' : '' ?>>Crear acta de visualizacion</a>
          <a class="btn" href="<?= h($actaEntregaUrl) ?>" <?= $embed ? 'target="_top"' : '' ?>>Crear acta de entrega</a>
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="action" value="resolve">
        <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
        <div class="form-grid">
          <div>
            <label class="label" for="oficio_id">Oficio relacionado</label>
            <select id="oficio_id" name="oficio_id" class="input">
              <option value="">Sin oficio</option>
              <?php foreach (($ctx['oficios'] ?? []) as $oficio): ?>
                <option value="<?= h($oficio['id']) ?>" <?= (string) ($row['oficio_id'] ?? '') === (string) $oficio['id'] ? 'selected' : '' ?>><?= h($oficio['label'] ?? ('Oficio #' . $oficio['id'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="documento_realizado">Documento realizado / acta</label>
            <input id="documento_realizado" name="documento_realizado" class="input" maxlength="255" value="<?= h($row['documento_realizado'] ?? '') ?>" placeholder="Ej: Acta de visualizacion de video">
          </div>
          <div class="field-full">
            <span class="label">Citaciones creadas para este accidente</span>
            <?php if (!empty($ctx['citaciones'])): ?>
              <div class="pick-list">
                <?php foreach ($ctx['citaciones'] as $citacion): ?>
                  <?php
                    $citacionId = (int) ($citacion['id'] ?? 0);
                    $nombre = trim(implode(' ', array_filter([
                        (string) ($citacion['persona_nombres'] ?? ''),
                        (string) ($citacion['persona_apep'] ?? ''),
                        (string) ($citacion['persona_apem'] ?? ''),
                    ], static fn (string $part): bool => trim($part) !== '')));
                    $detalleCitacion = [];
                    if (!empty($citacion['tipo_diligencia'])) { $detalleCitacion[] = (string) $citacion['tipo_diligencia']; }
                    if (!empty($citacion['en_calidad'])) { $detalleCitacion[] = 'Calidad: ' . $citacion['en_calidad']; }
                    if (!empty($citacion['persona_doc_num'])) { $detalleCitacion[] = trim((string) ($citacion['persona_doc_tipo'] ?? 'Doc') . ' ' . $citacion['persona_doc_num']); }
                    if (!empty($citacion['fecha'])) { $detalleCitacion[] = 'Fecha: ' . $citacion['fecha'] . (!empty($citacion['hora']) ? ' ' . substr((string) $citacion['hora'], 0, 5) : ''); }
                    if (!empty($citacion['lugar'])) { $detalleCitacion[] = 'Lugar: ' . mb_strimwidth((string) $citacion['lugar'], 0, 90, '...'); }
                    if (!empty($citacion['motivo'])) { $detalleCitacion[] = 'Motivo: ' . mb_strimwidth((string) $citacion['motivo'], 0, 120, '...'); }
                  ?>
                  <label class="pick-card">
                    <input type="checkbox" name="citacion_id[]" value="<?= h($citacionId) ?>" <?= isset($selectedCitaciones[$citacionId]) ? 'checked' : '' ?>>
                    <span>
                      <strong><?= h($nombre !== '' ? $nombre : ($citacion['label'] ?? ('Citacion #' . $citacionId))) ?></strong>
                      <small><?= h(implode(' - ', $detalleCitacion)) ?></small>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="muted">No hay citaciones registradas para este accidente.</div>
            <?php endif; ?>
          </div>
        </div>
        <label class="checkline">
          <input type="checkbox" name="marcar_realizado" value="1" <?= (string) ($row['estado'] ?? '') === 'Realizado' ? 'checked' : '' ?>>
          <span>Marcar diligencia como realizada al guardar</span>
        </label>
        <div class="actions" style="margin-top:14px;">
          <button type="submit" class="btn primary">Guardar vinculos</button>
          <a class="btn" href="diligenciapendiente_editar.php?id=<?= h($id) ?>&embed=<?= $embed ? 1 : 0 ?>&return_to=<?= urlencode($returnTo) ?>">Editar todo</a>
        </div>
      </form>
    </section>

    <div class="actions" style="margin-top:18px;">
      <?php if ($embed): ?>
        <button class="btn" type="button" onclick="try{window.parent&&window.parent.postMessage({type:'diligencia.close'},'*');}catch(e){}">Cerrar</button>
      <?php else: ?>
        <a class="btn" href="diligenciapendiente_listar.php<?= $accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : '' ?>">Volver al listado</a>
      <?php endif; ?>
      <?php if (!$embed && $accidenteId > 0): ?>
        <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= h($accidenteId) ?>">Volver al accidente</a>
      <?php endif; ?>
      <form method="post" style="display:inline;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
        <button type="submit" class="btn danger" onclick="return confirm('¿Eliminar diligencia #<?= h($id) ?>?');">Eliminar</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
