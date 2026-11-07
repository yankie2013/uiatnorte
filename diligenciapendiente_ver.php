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
// A JEFE EMI follows this route from the inbox; authorize using the linked case, not the creator.
if (\App\Support\Access::role() === 'jefe_emi') {
    $taskCase = $pdo->prepare('SELECT accidente_id FROM diligencias_pendientes_activos WHERE id=?');
    $taskCase->execute([$id]);
    \App\Support\Access::requireWorkspaceCase((int)$taskCase->fetchColumn());
}
$detail = $id > 0 ? $service->detalle($id) : null;

if ($id <= 0 || $detail === null) {
    http_response_code(404);
    exit('Diligencia no encontrada.');
}

$row = $detail['row'];
$accidenteId = (int) ($row['accidente_id'] ?? 0);
$message = trim((string) ($_GET['msg'] ?? ''));
$errors = [];
$mode = (string) ($_GET['mode'] ?? $_POST['mode'] ?? '');
if (!in_array($mode, ['oficio', 'citacion'], true)) $mode = '';
$ctx = $detail['ctx'] ?? ['oficios' => [], 'citaciones' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve') {
    try {
        $data = $service->defaultData($row);
        $data['citacion_id'] = $detail['citacion_ids'];
        if ($mode !== 'citacion') {
            $data['oficio_id'] = $_POST['oficio_id'] ?? '';
            $allowedOficios = array_map('intval', array_column($ctx['oficios'], 'id'));
            if ((int) $data['oficio_id'] > 0 && !in_array((int) $data['oficio_id'], $allowedOficios, true)) {
                throw new InvalidArgumentException('El oficio no pertenece a este accidente.');
            }
        }
        if ($mode !== 'oficio') {
            $data['citacion_id'] = $_POST['citacion_id'] ?? [];
            $allowedCitaciones = array_map('intval', array_column($ctx['citaciones'], 'id'));
            if (!is_array($data['citacion_id']) || array_diff(array_map('intval', $data['citacion_id']), $allowedCitaciones)) {
                throw new InvalidArgumentException('La citación no pertenece a este accidente.');
            }
        }
        $data['documento_realizado'] = $_POST['documento_realizado'] ?? ($data['documento_realizado'] ?? '');
        if (!empty($_POST['marcar_realizado'])) {
            $data['estado'] = 'Realizado';
        }
        $service->actualizar($id, $data);
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>window.parent.postMessage({type:"diligencia.saved"},location.origin);</script>';
            exit;
        }
        $target = 'diligenciapendiente_ver.php?id=' . urlencode((string) $id)
            . '&embed=' . ($embed ? '1' : '0')
            . '&return_to=' . urlencode($returnTo)
            . '&mode=' . urlencode($mode)
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
  --bg:#f6f7fb; --card:#fff; --text:#111827; --muted:#6b7280; --accent:#1d4ed8; --success:#166534; --danger:#b91c1c; --border:#d9e0ea; --field-label:#24543f;
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg:#0b1220; --card:#111827; --text:#e5e7eb; --muted:#9ca3af; --accent:#60a5fa; --success:#bbf7d0; --danger:#fecaca; --border:#243041; --field-label:#a7dbc2;
  }
}
body{margin:0;padding:24px;background:var(--bg);color:var(--text);font-family:"Segoe UI",sans-serif}body.is-embed{padding:14px}
.container{max-width:980px;margin:0 auto}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 12px 32px rgba(0,0,0,.08)}
.header{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
.title{margin:0;font-size:1.65rem}.sub{color:var(--muted);margin-top:6px}
.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-block;padding:11px 16px;border-radius:10px;text-decoration:none;border:1px solid var(--border);background:transparent;color:var(--text);font-weight:600;cursor:pointer}.btn.primary{background:var(--accent);color:#fff;border-color:transparent}.btn.danger{color:var(--danger)}
.grid{display:grid;grid-template-columns:1.4fr .9fr;gap:18px;margin-top:18px}.panel{border:1px solid var(--border);border-radius:14px;padding:18px}.label{display:block;color:var(--field-label);font-weight:750;font-size:.9rem;margin-bottom:6px}.value{white-space:pre-wrap;line-height:1.4}.badge{display:inline-block;padding:7px 12px;border-radius:999px;background:rgba(29,78,216,.1);color:var(--accent);font-weight:700}.alert{padding:12px 14px;border-radius:12px;margin-top:16px}.alert.success{background:rgba(22,163,74,.12);color:var(--success)}.alert.error{background:rgba(220,38,38,.12);color:var(--danger)}.list{margin:0;padding-left:18px}.muted{color:var(--muted)}
.resolve-panel{margin-top:18px;border:1px solid var(--border);border-radius:16px;padding:18px;background:rgba(29,78,216,.035)}.resolve-title{margin:0 0 4px;font-size:1.05rem}.quick-actions{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 16px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field-full{grid-column:1/-1}.input,select{width:100%;box-sizing:border-box;border:1px solid var(--border);border-radius:10px;padding:10px 12px;background:var(--card);color:var(--text)}.pick-list{display:grid;gap:8px;max-height:290px;overflow:auto;padding-right:4px}.pick-card{display:flex;align-items:flex-start;gap:9px;border:1px solid var(--border);border-radius:12px;padding:10px;background:var(--card)}.pick-card input{margin-top:4px}.pick-card strong{display:block}.pick-card small{display:block;color:var(--muted);line-height:1.35;margin-top:3px}.checkline{display:flex;gap:8px;align-items:center;margin-top:10px}.checkline input{width:auto}
@media (max-width: 900px){body{padding:14px}.grid{grid-template-columns:1fr}}
@media (max-width: 720px){.form-grid{grid-template-columns:1fr}.field-full{grid-column:auto}}
.diligencia-state{display:inline-block;padding:6px 12px;border-radius:999px;font-weight:700;font-size:.9rem;line-height:1.4}
.diligencia-state.pending{background:#fde5e5;color:#9e292d}
.diligencia-state.in-process{background:#fff3b0;color:#705900}
.diligencia-state.completed{background:#e0f2e5;color:#22653b}
.diligencia-state.cancelled{background:#e8ecf0;color:#4b5563}
/* Compact embedded consultation on phones, with one title in the outer dialog. */
@media(max-width:700px){
 body.is-embed{padding:0;font-size:13px;line-height:1.45;-webkit-text-size-adjust:100%}
 .is-embed *{box-sizing:border-box}
 .is-embed .container{width:100%;min-width:0}
 .is-embed .card{padding:12px;border:0;border-radius:0;box-shadow:none}
 .is-embed .header{gap:10px;align-items:center;flex-wrap:nowrap}
 .is-embed .header>div:first-child{min-width:0}
 .is-embed .title{display:none}
 .is-embed .sub{font-size:11px;margin:0;overflow-wrap:anywhere}
 .is-embed .actions{gap:6px;flex-shrink:0}
 .is-embed .btn{padding:9px 12px;font-size:12px;border-radius:9px;min-height:40px;text-align:center}
 .is-embed .grid{grid-template-columns:minmax(0,1fr);gap:10px;margin-top:12px}
 .is-embed .panel{padding:12px;border-radius:11px;min-width:0;overflow-wrap:anywhere}
 .is-embed .panel>div{margin-bottom:12px!important}
 .is-embed .panel>div:last-child{margin-bottom:0!important}
 .is-embed .label{font-size:12px;font-weight:750;margin-bottom:5px;color:var(--field-label)}
 .is-embed .value{font-size:13px;line-height:1.5;overflow-wrap:anywhere}
 .is-embed .badge,.is-embed .diligencia-state{font-size:12px;padding:5px 10px;max-width:100%;white-space:normal}
 .is-embed .resolve-panel{margin-top:10px;padding:12px;border-radius:11px;overflow-wrap:anywhere}
 .is-embed .resolve-title{font-size:14px;line-height:1.4}
 .is-embed .quick-actions{gap:7px;margin:10px 0}
 .is-embed .form-grid{grid-template-columns:minmax(0,1fr);gap:10px}
 .is-embed :is(.input,select,textarea){font-size:16px;max-width:100%}
 .is-embed .pick-card{padding:9px;font-size:12px}
}
/* Strong field-label hierarchy, distinct from the normal sentence-case values. */
.panel .label{display:table;max-width:100%;font-size:11px!important;font-weight:900!important;letter-spacing:.04em;text-transform:uppercase;color:#24543f!important;background:linear-gradient(145deg,rgba(232,245,237,.82),rgba(202,226,212,.55));border:1px solid rgba(111,158,130,.25);border-radius:7px;padding:4px 8px;box-shadow:inset 0 1px 0 rgba(255,255,255,.9),0 1px 3px rgba(36,84,63,.04);backdrop-filter:blur(12px) saturate(130%);-webkit-backdrop-filter:blur(12px) saturate(130%);line-height:1.5;margin-bottom:8px!important;box-decoration-break:clone;-webkit-box-decoration-break:clone}
.panel .label+div{font-weight:400;line-height:1.5}
.diligencia-type-action{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:14px;margin-bottom:16px}
.diligencia-type-action>div{min-width:0;margin-bottom:0!important;overflow-wrap:anywhere}
.diligencia-type-action .badge{max-width:100%;white-space:normal}
@media(max-width:700px){.is-embed .diligencia-type-action{gap:10px;margin-bottom:12px!important}.is-embed .diligencia-type-action>div{margin-bottom:0!important}.diligencia-type-action .label{font-size:10px!important;letter-spacing:.025em}}
.consultation-header{flex-direction:column;gap:10px}
.consultation-actions{justify-content:flex-start;align-items:center}
#consultation-state{width:auto;max-width:150px;font:700 12px/1.4 "Segoe UI",sans-serif;cursor:pointer;padding:8px 28px 8px 12px;border:1px solid transparent;min-height:38px}
#consultation-state option{background:var(--card);color:var(--text)}
#consultation-state-feedback{font-size:12px;margin:8px 0;color:var(--success)}
#consultation-state-feedback:empty{display:none}
#consultation-state-feedback.is-error{color:var(--danger)}
@media(max-width:700px){.is-embed .consultation-header{align-items:flex-start}.is-embed #consultation-state{font-size:12px!important;min-height:38px!important;height:38px!important;padding:7px 28px 7px 10px!important}.is-embed .consultation-actions{flex-wrap:wrap}}
/* Consistent state selector with a custom, centered chevron. */
#consultation-state{appearance:none!important;-webkit-appearance:none!important;border:1px solid rgba(70,90,65,.15);border-radius:999px;box-shadow:inset 0 1px 0 #ffffffa6,0 2px 5px #203e3008;padding:8px 34px 8px 14px!important;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='m2 2 4 4 4-4' fill='none' stroke='%2353695e' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E")!important;background-repeat:no-repeat!important;background-position:right 13px center!important;background-size:12px 8px!important;max-width:none}
#consultation-state:focus-visible{outline:2px solid #568b70;outline-offset:3px}
@media(max-width:700px){.is-embed #consultation-state{padding:7px 34px 7px 12px!important}}
.consultation-actions{width:100%}
.consultation-actions>#consultation-state,.consultation-actions>.diligencia-state{margin-left:auto}
@media(max-width:700px){.is-embed .consultation-actions{flex-wrap:nowrap;width:100%;gap:10px}}
</style>
</head>
<body class="<?= $embed ? 'is-embed' : '' ?>">
<div class="container">
  <div class="card">
    <div class="header consultation-header">
      <h1 class="title">Diligencia</h1>
      <div class="actions consultation-actions">
        <a class="btn primary" href="diligenciapendiente_editar.php?id=<?= h($id) ?>&embed=<?= $embed ? 1 : 0 ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a>
        <?php $statusClass = ['Pendiente'=>'pending','En proceso'=>'in-process','Realizado'=>'completed','Cancelado'=>'cancelled'][$row['estado'] ?? 'Pendiente'] ?? 'pending'; ?>
        <?php if (\App\Repositories\DiligenciaInboxRepository::allowed() && \App\Support\Access::canEdit($accidenteId)): ?>
        <select id="consultation-state" class="diligencia-state <?= h($statusClass) ?>" aria-label="Estado de la diligencia" data-previous="<?= h($row['estado'] ?? 'Pendiente') ?>">
          <?php foreach (['Pendiente','En proceso','Realizado'] as $state): ?><option value="<?= h($state) ?>" <?= ($row['estado'] ?? 'Pendiente') === $state ? 'selected' : '' ?>><?= h($state) ?></option><?php endforeach; ?>
          <?php if (($row['estado'] ?? '') === 'Cancelado'): ?><option selected>Cancelado</option><?php endif; ?>
        </select>
        <?php else: ?><span class="diligencia-state <?= h($statusClass) ?>"><?= h($row['estado'] ?? 'Pendiente') ?></span><?php endif; ?>
        <?php if (!$embed && $accidenteId > 0): ?>
          <a class="btn primary" href="oficios_nuevo.php?accidente_id=<?= h($accidenteId) ?>">+ Nuevo oficio</a>
          <a class="btn primary" href="citacion_nuevo.php?accidente_id=<?= h($accidenteId) ?>">+ Nueva citación</a>
        <?php endif; ?>
      </div>
    </div>
    <p id="consultation-state-feedback" role="status" aria-live="polite"></p>

    <?php if ($message !== ''): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="alert error"><?= h($error) ?></div><?php endforeach; ?>

    <?php if ($mode === ''): ?>
    <div class="grid">
      <div class="panel">
        <div class="diligencia-type-action">
<div style="margin-bottom:16px;">
          <span class="label">Tipo de diligencia</span>
          <div><?= !empty($detail['tipo_nombre']) ? ('<span class="badge">' . h($detail['tipo_nombre']) . '</span>') : '<span class="muted">Sin tipo</span>' ?></div>
        </div>

        <div style="margin-bottom:16px;">
          <span class="label">Motivo / acción</span>
          <div><?= h($row['accion'] ?? '') ?: '<span class="muted">Sin acción</span>' ?></div>
        </div>
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

      </div>
    </div>

    <?php endif; ?>
    <?php if ($mode === '') require __DIR__.'/app/Views/diligencias/action_options.php'; ?>
    <?php
      $ctx = $detail['ctx'] ?? ['oficios' => [], 'citaciones' => []];
      $selectedCitaciones = array_fill_keys(array_map('intval', $detail['citacion_ids'] ?? []), true);
      $returnHere = 'diligenciapendiente_ver.php?id=' . (int) $id . '&embed=' . ($embed ? '1' : '0') . '&mode=' . urlencode($mode);
      $oficioNuevoUrl = 'oficios_nuevo.php?accidente_id=' . (int) $accidenteId . '&embed=' . ($embed ? '1' : '0') . '&diligencia_id=' . (int) $id . '&return_to=' . urlencode($returnHere);
      $citacionNuevaUrl = 'citacion_nuevo.php?accidente_id=' . (int) $accidenteId . '&embed=' . ($embed ? '1' : '0') . '&diligencia_id=' . (int) $id . '&return_to=' . urlencode($returnHere);
      $actaVisualUrl = 'acta_visualizacion_form.php?accidente_id=' . (int) $accidenteId;
      $actaEntregaUrl = 'acta_entrega_vehiculo_form.php?accidente_id=' . (int) $accidenteId;
    ?>
    <?php if ($mode !== ''): ?>
    <section class="resolve-panel">
      <a class="btn" href="<?= h('diligenciapendiente_ver.php?'.http_build_query(['id'=>(int)$id,'embed'=>$embed?1:0,'return_to'=>$returnTo])) ?>">← Volver a la diligencia</a>
      <h2 class="resolve-title" style="margin-top:14px"><?= $mode === 'oficio' ? 'Oficio relacionado' : ($mode === 'citacion' ? 'Citaciones relacionadas' : 'Resolver diligencia') ?></h2>
      <div class="muted"><?= $mode !== '' ? 'Selecciona un documento de este expediente o crea uno nuevo; quedará vinculado a esta diligencia.' : 'Vincula los documentos y registra el resultado de la diligencia.' ?></div>

      <?php if ($accidenteId > 0): ?>
        <div class="quick-actions">
          <?php if ($mode !== 'citacion'): ?>
          <a class="btn primary" href="<?= h($oficioNuevoUrl) ?>" >Crear oficio</a>
          <?php endif; ?>
          <?php if ($mode !== 'oficio'): ?>
          <a class="btn primary" href="<?= h($citacionNuevaUrl) ?>" >Crear citación</a>
          <?php endif; ?>
          <?php if ($mode === ''): ?>
          <a class="btn" href="<?= h($actaVisualUrl) ?>" <?= $embed ? 'target="_top"' : '' ?>>Crear acta de visualizacion</a>
          <a class="btn" href="<?= h($actaEntregaUrl) ?>" <?= $embed ? 'target="_top"' : '' ?>>Crear acta de entrega</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="action" value="resolve">
        <input type="hidden" name="mode" value="<?= h($mode) ?>">
        <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
        <div class="form-grid">
          <?php if ($mode !== 'citacion'): ?>
          <div>
            <label class="label" for="oficio_id">Oficio relacionado</label>
            <select id="oficio_id" name="oficio_id" class="input">
              <option value="">Sin oficio</option>
              <?php foreach (($ctx['oficios'] ?? []) as $oficio): ?>
                <option value="<?= h($oficio['id']) ?>" <?= (string) ($row['oficio_id'] ?? '') === (string) $oficio['id'] ? 'selected' : '' ?>><?= h($oficio['label'] ?? ('Oficio #' . $oficio['id'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <?php if ($mode === ''): ?>
          <div>
            <label class="label" for="documento_realizado">Documento realizado / acta</label>
            <input id="documento_realizado" name="documento_realizado" class="input" maxlength="255" value="<?= h($row['documento_realizado'] ?? '') ?>" placeholder="Ej: Acta de visualizacion de video">
          </div>
          <?php endif; ?>
          <?php if ($mode !== 'oficio'): ?>
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
                    if (App\Support\CitacionMotivo::texto($citacion) !== '') { $detalleCitacion[] = App\Support\CitacionMotivo::texto($citacion); }
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
          <?php endif; ?>
        </div>
        <?php if ($mode === ''): ?>
        <label class="checkline">
          <input type="checkbox" name="marcar_realizado" value="1" <?= (string) ($row['estado'] ?? '') === 'Realizado' ? 'checked' : '' ?>>
          <span>Marcar diligencia como realizada al guardar</span>
        </label>
        <?php endif; ?>
        <div class="actions" style="margin-top:14px;">
          <button type="submit" class="btn primary">Guardar vinculos</button>
          <a class="btn" href="diligenciapendiente_editar.php?id=<?= h($id) ?>&embed=<?= $embed ? 1 : 0 ?>&return_to=<?= urlencode($returnTo) ?>">Editar todo</a>
        </div>
      </form>
    </section>
    <?php endif; ?>

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
<script>
(() => {
 const control = document.getElementById('consultation-state');
 if (!control) return;
 const feedback = document.getElementById('consultation-state-feedback');
 control.addEventListener('change', async () => {
   const previous = control.dataset.previous;
   if (control.value === previous) return;
   control.disabled = true;
   feedback.classList.remove('is-error');
   feedback.textContent = 'Guardando estado…';
   try {
     const response = await fetch('diligencias_pendientes.php?fragment=status', {
       method:'POST',credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'},
       body:new URLSearchParams({action:'update_status',id:<?= json_encode((string)$id) ?>,estado:control.value,_csrf:<?= json_encode(\App\Support\Access::csrf(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>})
     });
     const data = await response.json();
     if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo guardar el estado.');
     control.value = control.dataset.previous = data.estado;
     control.classList.remove('pending','in-process','completed','cancelled');
     control.classList.add({'Pendiente':'pending','En proceso':'in-process','Realizado':'completed'}[data.estado]);
     feedback.textContent = 'Estado guardado.';
     if (window.parent !== window) window.parent.postMessage({type:'diligencia.statusUpdated'},location.origin);
   } catch(error) {
     control.value = previous;
     feedback.classList.add('is-error');
     feedback.textContent = error instanceof SyntaxError ? 'No pudimos leer la respuesta. Intenta nuevamente.' : error.message;
   } finally { control.disabled = false; }
 });
})();
</script>
</body>
</html>
