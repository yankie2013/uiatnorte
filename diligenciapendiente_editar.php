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
$data = $service->defaultData($row);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'accidente_id' => $accidenteId,
        'tipo_diligencia_id' => $_POST['tipo_diligencia_id'] ?? '',
        'accion' => $_POST['accion'] ?? '',
        'contenido' => $_POST['contenido'] ?? '',
        'estado' => $_POST['estado'] ?? 'Pendiente',
        'oficio_id' => $_POST['oficio_id'] ?? '',
        'citacion_id' => $_POST['citacion_id'] ?? [],
        'documento_realizado' => $_POST['documento_realizado'] ?? '',
        'documentos_recibidos' => $_POST['documentos_recibidos'] ?? '',
    ];

    try {
        $service->actualizar($id, $data);
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>try{window.parent.postMessage({type:"diligencia.saved"},"*");}catch(_){}</script><body style="font:13px Inter,sans-serif;padding:16px">Guardado...</body>';
            exit;
        }
        header('Location: diligenciapendiente_ver.php?id=' . $id . '&msg=' . urlencode('Diligencia actualizada correctamente.'));
        exit;
    } catch (Throwable $e) {
        $errors = preg_split('/\r?\n/', trim($e->getMessage())) ?: ['No se pudo actualizar la diligencia.'];
    }
}

$ctx = $service->formContext($accidenteId > 0 ? $accidenteId : null);
if (!$embed) {
    @include __DIR__ . '/sidebar.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar diligencia #<?= h($id) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root {
    --bg: #f6f7fb;
    --card: #ffffff;
    --text: #111827;
    --muted: #6b7280;
    --border: #d9e0ea;
    --primary: #1d4ed8;
    --primary-hover: #1e40af;
    --success: #166534;
    --success-bg: #ecfdf3;
    --danger: #991b1b;
    --danger-bg: #fef2f2;
}
@media (prefers-color-scheme: dark) {
    :root {
        --bg: #0b1220;
        --card: #111827;
        --text: #e5e7eb;
        --muted: #9ca3af;
        --border: #243041;
        --primary: #3b82f6;
        --primary-hover: #60a5fa;
        --success: #bbf7d0;
        --success-bg: #052e16;
        --danger: #fecaca;
        --danger-bg: #450a0a;
    }
}
body { margin: 0; padding: 24px; background: var(--bg); color: var(--text); font-family: "Segoe UI", sans-serif; } body.is-embed { padding: 14px; }
.container { max-width: 860px; margin: 0 auto; }
.card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; box-shadow: 0 12px 32px rgba(0,0,0,.08); }
h1 { margin: 0 0 6px; font-size: 1.6rem; }
.sub { color: var(--muted); margin-bottom: 18px; }
label { display: block; margin: 14px 0 6px; font-weight: 600; }
.input, textarea, select { width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 10px; padding: 11px 12px; background: transparent; color: var(--text); }
textarea { min-height: 110px; resize: vertical; }
select[multiple] { min-height: 140px; }
.row { display: flex; gap: 10px; align-items: center; }
.row .grow { flex: 1; }
.actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
.btn { display: inline-block; border: 0; border-radius: 10px; padding: 11px 16px; text-decoration: none; cursor: pointer; font-weight: 600; }
.btn.primary { background: var(--primary); color: #fff; }
.btn.primary:hover { background: var(--primary-hover); }
.btn.ghost { background: transparent; color: var(--text); border: 1px solid var(--border); }
.alert { border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; }
.alert.error { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(220,38,38,.18); }
.help { color: var(--muted); font-size: .92rem; margin-top: 6px; }
.modal-backdrop { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,.5); padding: 20px; }
.modal { width: 100%; max-width: 520px; background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; }
.inline-msg { margin-top: 12px; padding: 10px; border-radius: 10px; display: none; }
.inline-msg.ok { display: block; background: var(--success-bg); color: var(--success); }
.inline-msg.error { display: block; background: var(--danger-bg); color: var(--danger); }
@media (max-width: 720px) { body { padding: 14px; } .row { flex-direction: column; align-items: stretch; } }
/* Glass labels match the consultation; values keep their normal weight. */
body{font-size:13px;line-height:1.45}
label:not(.citation-option),.citation-picker-heading{display:table;max-width:100%;margin:12px 0 7px;padding:4px 8px;border:1px solid rgba(111,158,130,.25);border-radius:7px;background:linear-gradient(145deg,rgba(232,245,237,.82),rgba(202,226,212,.55));color:#24543f;font-size:11px;font-weight:900;letter-spacing:.04em;text-transform:uppercase;box-shadow:inset 0 1px 0 rgba(255,255,255,.9),0 1px 3px rgba(36,84,63,.04);backdrop-filter:blur(12px) saturate(130%);-webkit-backdrop-filter:blur(12px) saturate(130%)}
h1{font-size:21px;line-height:1.3}
.input,textarea,select{font:400 13px/1.5 "Segoe UI",sans-serif;padding:9px 10px}
.btn{font:600 12px/1.4 "Segoe UI",sans-serif;padding:10px 13px}
.row .grow{min-width:0}
.help{font-size:11px}
@media(max-width:720px){
 body,body.is-embed{padding:10px;font-size:13px;-webkit-text-size-adjust:100%}
 .card{padding:14px;border-radius:12px;box-shadow:none}
 h1{font-size:18px;margin-bottom:4px}
 .sub{font-size:11px;margin-bottom:12px}
 .row{flex-direction:row;align-items:center;gap:7px}
 .row .btn{flex-shrink:0;padding:10px}
 .input,textarea,select{font-size:14px;min-height:40px;border-radius:9px}
 textarea{min-height:100px;line-height:1.5}
 select[multiple]{min-height:110px}
 .actions{gap:8px;margin-top:14px}
 .actions .btn{min-height:40px}
 .modal-backdrop{padding:12px}
 .modal{box-sizing:border-box;max-height:90dvh;overflow-y:auto;padding:14px}
 .modal h2{font-size:17px}
}
/* Screen-specific overrides for the shared mobile rules injected by PWA support. */
@media(max-width:760px){
 body.diligencia-edit{padding:6px!important;font-size:12px}
 body.diligencia-edit>.container{margin-top:0!important;padding:0!important}
 .diligencia-edit .card{padding:12px;border-radius:10px;box-shadow:none}
 .diligencia-edit h1{font-size:17px!important;line-height:1.3!important;margin:0 0 10px}
 .diligencia-edit.is-embed .sub{display:none}
 .diligencia-edit label:not(.citation-option),.diligencia-edit .citation-picker-heading{font-size:10px;margin:9px 0 5px;padding:3px 7px;letter-spacing:.025em}
 .diligencia-edit .input{font-size:13px!important;font-family:"Segoe UI",sans-serif;padding:7px 9px!important;border-radius:8px;line-height:1.4!important}
 .diligencia-edit :is(input.input,select.input):not([multiple]){height:36px!important;min-height:36px!important}
 .diligencia-edit select.input:not([multiple]){padding-right:28px!important;background-position:right 8px center!important;background-size:13px 13px!important}
 .diligencia-edit textarea.input{min-height:90px;font-size:13px!important;line-height:1.5!important}
 .diligencia-edit select[multiple]{min-height:90px;font-size:12px!important}
 .diligencia-edit form .row{display:flex;flex-direction:row;flex-wrap:nowrap!important;gap:6px}
 .diligencia-edit form .row>.grow{flex:1 1 0!important;min-width:0!important}
 .diligencia-edit form .row>.btn{flex:0 0 auto!important;width:auto;padding:7px 10px;font-size:11px;min-height:36px}
 .diligencia-edit .actions .btn{font-size:12px;padding:9px 12px;min-height:38px}
 .diligencia-edit .help{font-size:10px;margin-top:4px}
}
.citation-options{display:grid;gap:7px;max-height:260px;overflow-y:auto;padding:1px;overscroll-behavior:contain}
label.citation-option{display:flex;align-items:flex-start;gap:9px;margin:0;padding:10px;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--text);font-weight:400;cursor:pointer}
.citation-option:has(input:checked){border-color:#73ab90;background:rgba(115,171,144,.1)}
.citation-option:focus-within{outline:2px solid #24543f;outline-offset:1px}
.citation-option input[type=checkbox]{flex:0 0 17px;width:17px;height:17px;min-height:17px;margin:2px 0 0;accent-color:#24543f}
.citation-option-content{display:grid;gap:3px;min-width:0;overflow-wrap:anywhere;font-size:12px;line-height:1.4}
.citation-option-content strong{font-size:12px;font-weight:700}
.citation-option-content>span:first-of-type{color:#327353;font-weight:600}
.citation-option-content small{font-size:10px;color:var(--muted)}
@media(max-width:760px){.citation-options{max-height:230px}.diligencia-edit label.citation-option{padding:9px;letter-spacing:normal;text-transform:none;box-shadow:none;backdrop-filter:none}.citation-option-content{font-size:11px}}
</style>
</head>
<body class="diligencia-edit <?= $embed ? 'is-embed' : '' ?>">
<div class="container">
  <div class="card">
    <h1>Editar diligencia #<?= h($id) ?></h1>
    <div class="sub">Accidente vinculado: <?= $accidenteId > 0 ? ('#' . h($accidenteId)) : 'sin accidente' ?></div>

    <?php if ($errors): ?>
      <div class="alert error">
        <strong>Corrige lo siguiente:</strong>
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= h($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
      <label for="accion">Acción</label>
      <select id="accion" name="accion" class="input">
        <option value="">Selecciona una acción</option>
        <?php foreach (['Solicitar', 'Remitir', 'Citar', 'Realizar'] as $accion): ?>
          <option value="<?= h($accion) ?>" <?= ($data['accion'] ?? '') === $accion ? 'selected' : '' ?>><?= h($accion) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="tipo_diligencia_id">Tipo de diligencia</label>
      <div class="row">
        <div class="grow">
          <select id="tipo_diligencia_id" name="tipo_diligencia_id" class="input">
            <option value="">Sin tipo por ahora</option>
            <?php foreach ($ctx['tipos'] as $tipo): ?>
              <?php $label = $tipo['nombre'] . (!empty($tipo['descripcion']) ? (' - ' . mb_strimwidth((string) $tipo['descripcion'], 0, 120, '...')) : ''); ?>
              <option value="<?= h($tipo['id']) ?>" <?= (string) $data['tipo_diligencia_id'] === (string) $tipo['id'] ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="button" class="btn ghost" id="btn-new-tipo">+ Tipo</button>
      </div>

      <label for="estado">Estado</label>
      <select id="estado" name="estado" class="input">
        <?php foreach ($ctx['estados'] as $estado): ?>
          <option value="<?= h($estado) ?>" <?= (string) $data['estado'] === (string) $estado ? 'selected' : '' ?>><?= h($estado) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="contenido">Contenido / observaciones</label>
      <textarea id="contenido" name="contenido" class="input"><?= h($data['contenido']) ?></textarea>

      <label for="oficio_id">Oficio realizado</label>
      <select id="oficio_id" name="oficio_id" class="input">
        <option value="">Sin oficio realizado</option>
        <?php foreach ($ctx['oficios'] as $oficio): ?>
          <option value="<?= h($oficio['id']) ?>" <?= (string) $data['oficio_id'] === (string) $oficio['id'] ? 'selected' : '' ?>><?= h($oficio['label']) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="citation-picker" role="group" aria-labelledby="citation-picker-title">
        <div class="citation-picker-heading" id="citation-picker-title">Citaciones relacionadas</div>
        <div class="citation-options">
        <?php foreach ($ctx['citaciones'] as $citacion):
            $citationId = (int) $citacion['id'];
            $citationName = trim(implode(' ', array_filter([$citacion['persona_nombres'] ?? '', $citacion['persona_apep'] ?? '', $citacion['persona_apem'] ?? ''])));
            $citationType = trim((string) ($citacion['tipo_diligencia'] ?? '')) ?: (trim((string) ($citacion['texto'] ?? '')) ?: 'Tipo no registrado');
            $citationDate = trim((string) ($citacion['fecha'] ?? ''));
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $citationDate);
            $citationDate = $parsedDate && $parsedDate->format('Y-m-d') === $citationDate ? $parsedDate->format('d/m/Y') : 'Sin fecha';
            $citationTime = trim((string) ($citacion['hora'] ?? ''));
            if (preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/', $citationTime)) $citationDate .= ' · ' . substr($citationTime, 0, 5);
        ?>
          <label class="citation-option" for="citation-<?= $citationId ?>">
            <input type="checkbox" id="citation-<?= $citationId ?>" name="citacion_id[]" value="<?= $citationId ?>" <?= in_array($citationId, array_map('intval', (array) $data['citacion_id']), true) ? 'checked' : '' ?>>
            <span class="citation-option-content"><strong><?= h($citationType) ?></strong><span><?= h($citationDate) ?></span><span><?= h($citationName ?: 'Persona no registrada') ?></span><small>Citación <?= h(trim((string) ($citacion['numero'] ?? '')) ?: '#' . $citationId) ?></small></span>
          </label>
        <?php endforeach; ?>
        <?php if (!$ctx['citaciones']): ?><p class="help">Este expediente no tiene citaciones disponibles.</p><?php endif; ?>
        </div>
        <div class="help">Marca las citaciones que deseas vincular a esta diligencia.</div>
      </div>

      <label for="documento_realizado">Documento realizado</label>
      <input id="documento_realizado" name="documento_realizado" class="input" maxlength="255" value="<?= h($data['documento_realizado']) ?>">

      <label for="documentos_recibidos">Documentos recibidos</label>
      <textarea id="documentos_recibidos" name="documentos_recibidos" class="input" maxlength="2000"><?= h($data['documentos_recibidos']) ?></textarea>

      <div class="actions">
        <button type="submit" class="btn primary">Guardar cambios</button>
        <a class="btn ghost" href="diligenciapendiente_ver.php?id=<?= h($id) ?>&embed=<?= $embed ? 1 : 0 ?>&return_to=<?= urlencode($returnTo) ?>"><?= $embed ? 'Cancelar' : 'Ver detalle' ?></a>
        <?php if (!$embed): ?><a class="btn ghost" href="diligenciapendiente_listar.php?accidente_id=<?= h($accidenteId) ?>">Volver al listado</a><?php endif; ?>
        <?php if (!$embed && $accidenteId > 0): ?>
          <a class="btn ghost" href="Dato_General_accidente.php?accidente_id=<?= h($accidenteId) ?>">Volver al accidente</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="modal-backdrop" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <h2 id="modal-title" style="margin-top:0;">Nuevo tipo de diligencia</h2>
    <label for="tipo_nombre">Nombre *</label>
    <input id="tipo_nombre" class="input" maxlength="150" placeholder="Ej: Toma de declaración">
    <label for="tipo_descripcion">Descripción</label>
    <textarea id="tipo_descripcion" class="input" maxlength="800"></textarea>
    <div id="modal-msg" class="inline-msg"></div>
    <div class="modal-actions">
      <button type="button" class="btn ghost" id="modal-cancel">Cancelar</button>
      <button type="button" class="btn primary" id="modal-save">Crear y usar</button>
    </div>
  </div>
</div>

<script>
(function () {
  const backdrop = document.getElementById('modal-backdrop');
  const openBtn = document.getElementById('btn-new-tipo');
  const cancelBtn = document.getElementById('modal-cancel');
  const saveBtn = document.getElementById('modal-save');
  const nombreInput = document.getElementById('tipo_nombre');
  const descripcionInput = document.getElementById('tipo_descripcion');
  const msg = document.getElementById('modal-msg');
  const select = document.getElementById('tipo_diligencia_id');

  function setMessage(text, kind) {
    msg.textContent = text;
    msg.className = 'inline-msg ' + kind;
  }

  function openModal() {
    msg.className = 'inline-msg';
    msg.textContent = '';
    nombreInput.value = '';
    descripcionInput.value = '';
    backdrop.style.display = 'flex';
    backdrop.setAttribute('aria-hidden', 'false');
    nombreInput.focus();
  }

  function closeModal() {
    backdrop.style.display = 'none';
    backdrop.setAttribute('aria-hidden', 'true');
  }

  openBtn.addEventListener('click', openModal);
  cancelBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', function (event) {
    if (event.target === backdrop) {
      closeModal();
    }
  });

  saveBtn.addEventListener('click', function () {
    const payload = {
      nombre: nombreInput.value.trim(),
      descripcion: descripcionInput.value.trim()
    };

    if (!payload.nombre) {
      setMessage('El nombre es obligatorio.', 'error');
      nombreInput.focus();
      return;
    }

    saveBtn.disabled = true;
    saveBtn.textContent = 'Creando...';

    fetch('tipo_diligencia_crear_ajax.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    })
      .then((response) => response.json())
      .then((json) => {
        if (!json || !json.ok) {
          throw new Error(json && json.error ? json.error : 'No se pudo crear el tipo.');
        }

        const option = document.createElement('option');
        option.value = String(json.id);
        option.textContent = json.nombre + (json.descripcion ? ' - ' + String(json.descripcion).substring(0, 120) : '');
        select.appendChild(option);
        select.value = String(json.id);
        setMessage('Tipo creado correctamente.', 'ok');
        setTimeout(closeModal, 700);
      })
      .catch((error) => {
        setMessage(error.message || 'No se pudo crear el tipo.', 'error');
      })
      .finally(() => {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Crear y usar';
      });
  });
})();
</script>
</body>
</html>
