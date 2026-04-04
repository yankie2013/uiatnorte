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
$errors = [];
$success = '';
$createdId = 0;

$accidenteId = 0;
if (isset($_REQUEST['accidente_id']) && $_REQUEST['accidente_id'] !== '') {
    $accidenteId = (int) $_REQUEST['accidente_id'];
} elseif (isset($_REQUEST['id']) && $_REQUEST['id'] !== '') {
    $accidenteId = (int) $_REQUEST['id'];
}

$data = $service->defaultData();
$data['accidente_id'] = $accidenteId > 0 ? $accidenteId : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'accidente_id' => $_POST['accidente_id'] ?? '',
        'tipo_diligencia_id' => $_POST['tipo_diligencia_id'] ?? '',
        'contenido' => $_POST['contenido'] ?? '',
        'estado' => 'Pendiente',
        'oficio_id' => $_POST['oficio_id'] ?? '',
        'citacion_id' => $_POST['citacion_id'] ?? [],
        'documento_realizado' => $_POST['documento_realizado'] ?? '',
        'documentos_recibidos' => $_POST['documentos_recibidos'] ?? '',
    ];

    try {
        $createdId = $service->crear($data);
        $success = 'Diligencia creada correctamente.';
        $data = $service->defaultData();
        $data['accidente_id'] = (int) ($_POST['accidente_id'] ?? 0);
    } catch (Throwable $e) {
        $errors = preg_split('/\r?\n/', trim($e->getMessage())) ?: ['No se pudo crear la diligencia.'];
    }
}

$accidenteId = (int) ($data['accidente_id'] ?: 0);
$ctx = $service->formContext($accidenteId > 0 ? $accidenteId : null);
@include __DIR__ . '/sidebar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Nueva diligencia pendiente</title>
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
html[data-theme-resolved="dark"] {
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
body { margin: 0; padding: 24px; background: var(--bg); color: var(--text); font-family: "Segoe UI", sans-serif; }
.container { max-width: 820px; margin: 0 auto; }
.card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; box-shadow: 0 12px 32px rgba(0,0,0,.08); }
h1 { margin: 0 0 6px; font-size: 1.6rem; }
.sub { color: var(--muted); margin-bottom: 18px; }
label { display: block; margin: 14px 0 6px; font-weight: 600; }
.input, textarea, select { width: 100%; box-sizing: border-box; border: 1px solid var(--border); border-radius: 10px; padding: 11px 12px; background: transparent; color: var(--text); }
select { color-scheme: light; }
select option, select optgroup { background: var(--card); color: var(--text); }
select option:checked { background: rgba(29,78,216,.18); color: var(--text); }
html[data-theme-resolved="dark"] select { color-scheme: dark; }
html[data-theme-resolved="dark"] select option,
html[data-theme-resolved="dark"] select optgroup { background: #0f172a; color: #e5e7eb; }
html[data-theme-resolved="dark"] select option:checked { background: #1d4ed8; color: #eff6ff; }
textarea { min-height: 120px; resize: vertical; }
select[multiple] { min-height: 130px; }
.row { display: flex; gap: 10px; align-items: center; }
.row .grow { flex: 1; }
.actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
.btn { display: inline-block; border: 0; border-radius: 10px; padding: 11px 16px; text-decoration: none; cursor: pointer; font-weight: 600; }
.btn.primary { background: var(--primary); color: #fff; }
.btn.primary:hover { background: var(--primary-hover); }
.btn.ghost { background: transparent; color: var(--text); border: 1px solid var(--border); }
.alert { border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; }
.alert.error { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(220,38,38,.18); }
.alert.success { background: var(--success-bg); color: var(--success); border: 1px solid rgba(22,163,74,.2); }
.help { color: var(--muted); font-size: .92rem; margin-top: 6px; }
.modal-backdrop { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,.5); padding: 20px; }
.modal { width: 100%; max-width: 520px; background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; }
.inline-msg { margin-top: 12px; padding: 10px; border-radius: 10px; display: none; }
.inline-msg.ok { display: block; background: var(--success-bg); color: var(--success); }
.inline-msg.error { display: block; background: var(--danger-bg); color: var(--danger); }
@media (max-width: 720px) { body { padding: 14px; } .row { flex-direction: column; align-items: stretch; } }
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Nueva diligencia pendiente</h1>
    <div class="sub">Registra una diligencia vinculada al accidente <?= $accidenteId > 0 ? ('#' . h($accidenteId)) : 'actual' ?>.</div>

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

    <?php if ($success !== ''): ?>
      <div class="alert success">
        <?= h($success) ?>
        <?php if ($createdId > 0): ?>
          <div style="margin-top:8px;">
            <a class="btn ghost" href="diligenciapendiente_ver.php?id=<?= h($createdId) ?>">Ver diligencia #<?= h($createdId) ?></a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="accidente_id" value="<?= h($data['accidente_id']) ?>">

      <label for="tipo_diligencia_id">Tipo de diligencia *</label>
      <div class="row">
        <div class="grow">
          <select id="tipo_diligencia_id" name="tipo_diligencia_id" class="input" required>
            <option value="">Selecciona un tipo</option>
            <?php foreach ($ctx['tipos'] as $tipo): ?>
              <?php $label = $tipo['nombre'] . (!empty($tipo['descripcion']) ? (' - ' . mb_strimwidth((string) $tipo['descripcion'], 0, 120, '...')) : ''); ?>
              <option value="<?= h($tipo['id']) ?>" <?= (string) $data['tipo_diligencia_id'] === (string) $tipo['id'] ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="button" class="btn ghost" id="btn-new-tipo">+ Tipo</button>
      </div>

      <label for="contenido">Contenido / observaciones</label>
      <textarea id="contenido" name="contenido" class="input"><?= h($data['contenido']) ?></textarea>

      <label for="oficio_id">Oficio relacionado</label>
      <select id="oficio_id" name="oficio_id" class="input">
        <option value="">Sin oficio relacionado</option>
        <?php foreach ($ctx['oficios'] as $oficio): ?>
          <option value="<?= h($oficio['id']) ?>" <?= (string) $data['oficio_id'] === (string) $oficio['id'] ? 'selected' : '' ?>><?= h($oficio['label']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="citacion_id">Citaciones relacionadas</label>
      <select id="citacion_id" name="citacion_id[]" class="input" multiple>
        <?php foreach ($ctx['citaciones'] as $citacion): ?>
          <option value="<?= h($citacion['id']) ?>" <?= in_array((int) $citacion['id'], array_map('intval', (array) $data['citacion_id']), true) ? 'selected' : '' ?>><?= h($citacion['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="help">Puedes seleccionar una o varias citaciones.</div>

      <label for="documento_realizado">Documento realizado</label>
      <input id="documento_realizado" name="documento_realizado" class="input" maxlength="255" value="<?= h($data['documento_realizado']) ?>">

      <label for="documentos_recibidos">Documentos recibidos</label>
      <textarea id="documentos_recibidos" name="documentos_recibidos" class="input" maxlength="2000"><?= h($data['documentos_recibidos']) ?></textarea>
      <div class="help">El estado inicial se asigna como <strong>Pendiente</strong>.</div>

      <div class="actions">
        <button type="submit" class="btn primary">Crear diligencia</button>
        <a class="btn ghost" href="diligenciapendiente_listar.php<?= $accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : '' ?>">Volver al listado</a>
        <?php if ($accidenteId > 0): ?>
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
