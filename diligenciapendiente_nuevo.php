<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/app/Support/CaseSummaryWidget.php';

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
$createdIds = [];
$returnTo = trim((string) ($_REQUEST['return_to'] ?? ''));
if ($returnTo !== '' && (preg_match('~^(?:https?:)?//~i', $returnTo) || preg_match('~^[a-z][a-z0-9+.-]*:~i', $returnTo))) {
    $returnTo = '';
}

$accidenteId = 0;
if (isset($_REQUEST['accidente_id']) && $_REQUEST['accidente_id'] !== '') {
    $accidenteId = (int) $_REQUEST['accidente_id'];
} elseif (isset($_REQUEST['id']) && $_REQUEST['id'] !== '') {
    $accidenteId = (int) $_REQUEST['id'];
}

$data = $service->defaultData();
$data['accidente_id'] = $accidenteId > 0 ? $accidenteId : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $accidenteId > 0) {
    $documentoRecibidoId = (int) ($_GET['documento_recibido_id'] ?? 0);
    if ($documentoRecibidoId > 0) {
        $stDoc = $pdo->prepare(
            "SELECT dr.*,
                    (
                        SELECT GROUP_CONCAT(NULLIF(TRIM(a.descripcion), '') ORDER BY a.orden, a.id SEPARATOR '; ')
                          FROM documentos_recibidos_anexos a
                         WHERE a.documento_recibido_id = dr.id
                    ) AS anexos_texto
               FROM documentos_recibidos dr
              WHERE dr.id = ? AND dr.accidente_id = ?
              LIMIT 1"
        );
        $stDoc->execute([$documentoRecibidoId, $accidenteId]);
        $doc = $stDoc->fetch(PDO::FETCH_ASSOC);

        if ($doc) {
            $docAsunto = trim((string) ($doc['asunto'] ?? ''));
            $docNumero = trim((string) ($doc['numero_documento'] ?? ''));
            $docSiglas = trim((string) ($doc['siglas_documento'] ?? ''));
            if ($docSiglas !== '') {
                $docNumero = trim($docNumero . ' ' . $docSiglas);
            }
            $docTipo = trim((string) ($doc['tipo_documento'] ?? ''));
            $docEntidad = trim((string) ($doc['entidad_persona'] ?? ''));
            $docContenido = trim((string) ($doc['contenido'] ?? ''));
            $docAnexos = trim((string) ($doc['anexos_texto'] ?? ''));
            $textoBase = $docAsunto . ' ' . $docTipo . ' ' . $docContenido . ' ' . $docAnexos;
            $textoNormalizado = function_exists('mb_strtolower')
                ? mb_strtolower($textoBase, 'UTF-8')
                : strtolower($textoBase);
            $esVideo = (bool) preg_match('/camara|cámara|video|vigilancia|grabacion|grabación|filmacion|filmación/u', $textoNormalizado);
            $docLabelParts = array_filter([
                $docTipo !== '' ? $docTipo : 'Documento recibido',
                $docNumero !== '' ? 'N° ' . $docNumero : '',
                $docAsunto,
            ]);
            $docLabel = implode(' - ', $docLabelParts);
            $docLabel = $docLabel !== '' ? $docLabel : 'Documento recibido #' . $documentoRecibidoId;
            $resumenParts = ['Documento recibido #' . $documentoRecibidoId . ': ' . $docLabel];
            if ($docEntidad !== '') {
                $resumenParts[] = 'Remitente: ' . $docEntidad;
            }
            if ($docAnexos !== '') {
                $resumenParts[] = 'Anexos: ' . $docAnexos;
            }
            if ($docContenido !== '') {
                $contenidoCorto = function_exists('mb_substr') ? mb_substr($docContenido, 0, 450, 'UTF-8') : substr($docContenido, 0, 450);
                $resumenParts[] = 'Contenido: ' . $contenidoCorto . ((strlen($docContenido) > strlen($contenidoCorto)) ? '...' : '');
            }

            $data['contenido'] = $esVideo
                ? 'Realizar diligencia de visualización del video remitido mediante ' . $docLabel . ', dejando constancia del contenido relevante para la investigación.'
                : 'Atender y evaluar el documento recibido ' . $docLabel . ', realizando la diligencia pendiente que corresponda para la investigación.';
            $data['documentos_recibidos'] = implode("\n", $resumenParts);
            if (!empty($doc['referencia_oficio_id'])) {
                $data['oficio_id'] = (int) $doc['referencia_oficio_id'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bulkItems = [];
    if (isset($_POST['diligencias_bulk']) && is_array($_POST['diligencias_bulk'])) {
        foreach ($_POST['diligencias_bulk'] as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $bulkItems[] = $text;
            }
        }
        $bulkItems = array_values(array_unique($bulkItems));
    }

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
        if ($bulkItems !== []) {
            $pdo->beginTransaction();
            foreach ($bulkItems as $contenido) {
                $itemData = $data;
                $itemData['contenido'] = $contenido;
                $createdIds[] = $service->crear($itemData);
            }
            $pdo->commit();
            $success = count($createdIds) === 1
                ? 'Diligencia creada correctamente.'
                : count($createdIds) . ' diligencias creadas correctamente.';
            $createdId = (int) ($createdIds[0] ?? 0);
        } else {
            $createdId = $service->crear($data);
            $createdIds = [$createdId];
            $success = 'Diligencia creada correctamente.';
        }
        $data = $service->defaultData();
        $data['accidente_id'] = (int) ($_POST['accidente_id'] ?? 0);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors = preg_split('/\r?\n/', trim($e->getMessage())) ?: ['No se pudo crear la diligencia.'];
    }
}

$accidenteId = (int) ($data['accidente_id'] ?: 0);
$ctx = $service->formContext($accidenteId > 0 ? $accidenteId : null);
$caseSummaryContext = case_summary_widget_context($pdo, $accidenteId);
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
.ocr-toolbar { display: flex; justify-content: flex-end; margin-top: 8px; }
.bulk-wrap { display: none; margin-top: 12px; border: 1px solid var(--border); border-radius: 12px; padding: 12px; background: rgba(29,78,216,.04); }
.bulk-wrap.is-visible { display: block; }
.bulk-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.bulk-title { font-weight: 700; }
.bulk-list { display: flex; flex-direction: column; gap: 8px; }
.bulk-row { display: flex; align-items: flex-start; gap: 8px; }
.bulk-row textarea { min-height: 58px; }
.bulk-row .remove { flex: 0 0 auto; padding: 9px 12px; }
.modal.large { max-width: 760px; max-height: calc(100vh - 40px); display: flex; flex-direction: column; overflow: hidden; }
.modal-scroll { min-height: 0; overflow-y: auto; padding-right: 4px; }
.paste-zone { margin-top: 12px; padding: 12px; border-radius: 12px; border: 1px dashed var(--border); background: rgba(148,163,184,.10); }
.ocr-preview-wrap { display: none; margin-top: 12px; }
.ocr-preview-wrap img { display: block; width: 100%; max-width: 100%; max-height: 240px; border-radius: 12px; border: 1px solid var(--border); object-fit: contain; }
.ocr-status { min-height: 18px; margin-top: 10px; font-size: .9rem; color: var(--muted); }
#diligenciasOcrTextBox { min-height: 160px; }
@media (max-width: 720px) { body { padding: 14px; } .row { flex-direction: column; align-items: stretch; } }
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Nueva diligencia pendiente</h1>
    <div class="sub">Registra una diligencia vinculada al accidente <?= $accidenteId > 0 ? ('#' . h($accidenteId)) : 'actual' ?>.</div>
    <?= case_summary_widget_render($caseSummaryContext, 'diligencia-pendiente-nuevo') ?>

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
        <?php if (count($createdIds) > 1): ?>
          <div style="margin-top:8px;">
            <a class="btn ghost" href="diligenciapendiente_listar.php<?= $accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : '' ?>">Ver diligencias creadas</a>
            <?php if ($returnTo !== ''): ?><a class="btn ghost" href="<?= h($returnTo) ?>">Volver a documentos</a><?php endif; ?>
          </div>
        <?php elseif ($createdId > 0): ?>
          <div style="margin-top:8px;">
            <a class="btn ghost" href="diligenciapendiente_ver.php?id=<?= h($createdId) ?>">Ver diligencia #<?= h($createdId) ?></a>
            <?php if ($returnTo !== ''): ?><a class="btn ghost" href="<?= h($returnTo) ?>">Volver a documentos</a><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="accidente_id" value="<?= h($data['accidente_id']) ?>">
      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">

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

      <label for="contenido">Contenido / observaciones</label>
      <textarea id="contenido" name="contenido" class="input"><?= h($data['contenido']) ?></textarea>
      <div class="ocr-toolbar">
        <button type="button" class="btn ghost" id="btn-open-diligencias-ocr">Subir/Pegar imagen con lista</button>
      </div>
      <div class="bulk-wrap" id="bulk-wrap">
        <div class="bulk-head">
          <div>
            <div class="bulk-title">Diligencias detectadas</div>
            <div class="help" id="bulk-count">0 diligencias listas para crear.</div>
          </div>
          <button type="button" class="btn ghost" id="btn-clear-bulk">Limpiar</button>
        </div>
        <div class="bulk-list" id="bulk-list"></div>
        <div class="help">Al guardar, se creara una diligencia pendiente por cada item de esta lista usando el tipo seleccionado.</div>
      </div>

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
        <?php if ($returnTo !== ''): ?>
          <a class="btn ghost" href="<?= h($returnTo) ?>">Volver a documentos</a>
        <?php endif; ?>
        <a class="btn ghost" href="diligenciapendiente_listar.php<?= $accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : '' ?>">Volver al listado</a>
        <?php if ($accidenteId > 0): ?>
          <a class="btn ghost" href="Dato_General_accidente.php?accidente_id=<?= h($accidenteId) ?>">Volver al accidente</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="diligencias-ocr-backdrop" aria-hidden="true">
  <div class="modal large" role="dialog" aria-modal="true" aria-labelledby="diligencias-ocr-title">
    <h2 id="diligencias-ocr-title" style="margin-top:0;">Extraer diligencias desde imagen</h2>
    <div class="modal-scroll">
      <div class="help">Sube o pega una imagen con una lista numerada o por letras. Luego revisa el texto y aplica la separacion.</div>
      <input type="file" id="diligenciasOcrImageInput" accept="image/png,image/jpeg,image/jpg,image/webp" style="margin-top:12px;">
      <div class="paste-zone" id="diligenciasOcrPasteZone" tabindex="0">Pega aqui una imagen con Ctrl+V o Cmd+V</div>
      <div class="ocr-preview-wrap" id="diligenciasOcrPreviewWrap">
        <img id="diligenciasOcrPreview" alt="Vista previa OCR">
      </div>
      <div class="ocr-status" id="diligenciasOcrStatus"></div>
      <textarea id="diligenciasOcrTextBox" class="input" rows="8" placeholder="Aqui aparecera el texto detectado. Puedes corregirlo antes de aplicar."></textarea>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn ghost" id="diligenciasOcrCancel">Cancelar</button>
      <button type="button" class="btn ghost" id="diligenciasOcrProcess">Procesar imagen</button>
      <button type="button" class="btn primary" id="diligenciasOcrApply" disabled>Aplicar lista</button>
    </div>
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
  const form = document.querySelector('form[method="post"]');
  const contenido = document.getElementById('contenido');
  const bulkWrap = document.getElementById('bulk-wrap');
  const bulkList = document.getElementById('bulk-list');
  const bulkCount = document.getElementById('bulk-count');
  const clearBulkBtn = document.getElementById('btn-clear-bulk');
  const openOcrBtn = document.getElementById('btn-open-diligencias-ocr');
  const ocrBackdrop = document.getElementById('diligencias-ocr-backdrop');
  const ocrImageInput = document.getElementById('diligenciasOcrImageInput');
  const ocrPasteZone = document.getElementById('diligenciasOcrPasteZone');
  const ocrPreviewWrap = document.getElementById('diligenciasOcrPreviewWrap');
  const ocrPreview = document.getElementById('diligenciasOcrPreview');
  const ocrStatus = document.getElementById('diligenciasOcrStatus');
  const ocrTextBox = document.getElementById('diligenciasOcrTextBox');
  const ocrCancel = document.getElementById('diligenciasOcrCancel');
  const ocrProcess = document.getElementById('diligenciasOcrProcess');
  const ocrApply = document.getElementById('diligenciasOcrApply');
  let ocrClipboardFile = null;

  function setOcrStatus(text, ok = null) {
    ocrStatus.textContent = text || '';
    ocrStatus.style.color = ok === true ? '#166534' : ok === false ? '#991b1b' : '';
  }

  function updateBulkState() {
    const rows = Array.from(bulkList.querySelectorAll('textarea[name="diligencias_bulk[]"]'));
    const total = rows.filter((item) => item.value.trim() !== '').length;
    bulkWrap.classList.toggle('is-visible', rows.length > 0);
    bulkCount.textContent = total === 1 ? '1 diligencia lista para crear.' : `${total} diligencias listas para crear.`;
  }

  function addBulkRow(value) {
    const row = document.createElement('div');
    row.className = 'bulk-row';
    const textarea = document.createElement('textarea');
    textarea.className = 'input';
    textarea.name = 'diligencias_bulk[]';
    textarea.value = value || '';
    textarea.addEventListener('input', updateBulkState);
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn ghost remove';
    remove.textContent = 'Quitar';
    remove.addEventListener('click', () => {
      row.remove();
      updateBulkState();
    });
    row.appendChild(textarea);
    row.appendChild(remove);
    bulkList.appendChild(row);
  }

  function setBulkItems(items) {
    bulkList.innerHTML = '';
    items.forEach((item) => addBulkRow(item));
    updateBulkState();
  }

  function clearBulkItems() {
    bulkList.innerHTML = '';
    updateBulkState();
  }

  function normalizeOcrText(text) {
    return String(text || '')
      .replace(/[“”]/g, '"')
      .replace(/[‘’]/g, "'")
      .replace(/\u00a0/g, ' ')
      .replace(/[ \t]+/g, ' ')
      .replace(/\n{3,}/g, '\n\n')
      .trim();
  }

  function splitDiligencias(text) {
    const normalized = normalizeOcrText(text)
      .replace(/(?:^|\n)\s*([0-9]{1,3}|[A-Za-z])\s*[\).\:-]\s+/g, '\n@@ITEM@@ ')
      .replace(/\s+([0-9]{1,3})\s*[\).\:-]\s+(?=[A-ZÁÉÍÓÚÑ])/g, '\n@@ITEM@@ ')
      .replace(/\s+([A-Za-z])\s*[\).\:-]\s+(?=[A-ZÁÉÍÓÚÑ])/g, '\n@@ITEM@@ ');
    let items = normalized
      .split(/\n@@ITEM@@\s*/)
      .map((item) => item.replace(/^@@ITEM@@\s*/, '').replace(/\s+/g, ' ').trim())
      .filter(Boolean);
    if (!items.length && normalized !== '') {
      items = normalized.split(/\r?\n+/).map((item) => item.replace(/\s+/g, ' ').trim()).filter(Boolean);
    }
    return items.map((item) => item.replace(/^[0-9]{1,3}\s*[\).\:-]\s*/, '').replace(/^[A-Za-z]\s*[\).\:-]\s*/, '').trim());
  }

  function openOcrModal() {
    ocrClipboardFile = null;
    ocrImageInput.value = '';
    ocrTextBox.value = '';
    ocrApply.disabled = true;
    ocrPreviewWrap.style.display = 'none';
    ocrPreview.removeAttribute('src');
    setOcrStatus('Sube una imagen y luego procesa el OCR.');
    ocrBackdrop.style.display = 'flex';
    ocrBackdrop.setAttribute('aria-hidden', 'false');
    setTimeout(() => ocrPasteZone.focus(), 0);
  }

  function closeOcrModal() {
    ocrBackdrop.style.display = 'none';
    ocrBackdrop.setAttribute('aria-hidden', 'true');
  }

  function loadOcrPreview(file) {
    if (!file) {
      ocrPreviewWrap.style.display = 'none';
      ocrPreview.removeAttribute('src');
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      ocrPreview.src = String(reader.result || '');
      ocrPreviewWrap.style.display = 'block';
    };
    reader.readAsDataURL(file);
  }

  function setOcrFile(file, label) {
    ocrClipboardFile = file || null;
    loadOcrPreview(file || null);
    if (file) setOcrStatus(`${label} lista. Presiona "Procesar imagen".`);
  }

  function selectedOcrFile() {
    return (ocrImageInput.files && ocrImageInput.files[0]) || ocrClipboardFile;
  }

  function clipboardImage(event) {
    const items = event.clipboardData?.items || [];
    for (const item of items) {
      if (item.kind === 'file' && item.type.startsWith('image/')) {
        const file = item.getAsFile();
        if (file) return file;
      }
    }
    return null;
  }

  async function ensureTesseractLoaded() {
    if (window.Tesseract) return window.Tesseract;
    await new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
      script.async = true;
      script.onload = resolve;
      script.onerror = () => reject(new Error('No se pudo cargar el motor OCR.'));
      document.head.appendChild(script);
    });
    if (!window.Tesseract) throw new Error('El motor OCR no quedo disponible.');
    return window.Tesseract;
  }

  clearBulkBtn.addEventListener('click', clearBulkItems);
  openOcrBtn.addEventListener('click', openOcrModal);
  ocrCancel.addEventListener('click', closeOcrModal);
  ocrBackdrop.addEventListener('click', (event) => { if (event.target === ocrBackdrop) closeOcrModal(); });
  ocrImageInput.addEventListener('change', () => {
    const file = ocrImageInput.files && ocrImageInput.files[0];
    if (!file) {
      ocrClipboardFile = null;
      loadOcrPreview(null);
      return;
    }
    setOcrFile(file, 'Imagen subida');
  });
  ocrPasteZone.addEventListener('paste', (event) => {
    const file = clipboardImage(event);
    if (!file) return;
    event.preventDefault();
    ocrImageInput.value = '';
    setOcrFile(file, 'Imagen pegada');
  });
  document.addEventListener('paste', (event) => {
    if (ocrBackdrop.getAttribute('aria-hidden') !== 'false') return;
    const tag = document.activeElement?.tagName || '';
    if (tag === 'INPUT' || tag === 'TEXTAREA') return;
    const file = clipboardImage(event);
    if (!file) return;
    event.preventDefault();
    ocrImageInput.value = '';
    setOcrFile(file, 'Imagen pegada');
  });
  ocrProcess.addEventListener('click', async () => {
    const file = selectedOcrFile();
    if (!file) {
      setOcrStatus('Selecciona o pega una imagen primero.', false);
      return;
    }
    ocrProcess.disabled = true;
    ocrApply.disabled = true;
    ocrTextBox.value = '';
    try {
      const Tesseract = await ensureTesseractLoaded();
      const result = await Tesseract.recognize(file, 'spa', {
        logger: (message) => {
          if (!message.status) return;
          const progress = typeof message.progress === 'number' ? ` ${Math.round(message.progress * 100)}%` : '';
          setOcrStatus(`${message.status}${progress}`);
        }
      });
      const text = normalizeOcrText(result?.data?.text || '');
      if (!text) throw new Error('No se pudo extraer texto de la imagen.');
      ocrTextBox.value = text;
      ocrApply.disabled = false;
      setOcrStatus('Texto extraido. Revisa y aplica la lista.', true);
    } catch (error) {
      setOcrStatus(error.message || 'No se pudo procesar la imagen.', false);
    } finally {
      ocrProcess.disabled = false;
    }
  });
  ocrTextBox.addEventListener('input', () => {
    ocrApply.disabled = ocrTextBox.value.trim() === '';
  });
  ocrApply.addEventListener('click', () => {
    const items = splitDiligencias(ocrTextBox.value);
    if (!items.length) {
      setOcrStatus('No se encontraron diligencias separadas por numero o letra.', false);
      return;
    }
    setBulkItems(items);
    if (contenido.value.trim() === '') {
      contenido.value = items[0] || '';
    }
    closeOcrModal();
  });
  form.addEventListener('submit', () => {
    Array.from(bulkList.querySelectorAll('textarea[name="diligencias_bulk[]"]')).forEach((item) => {
      if (item.value.trim() === '') item.disabled = true;
    });
  });
  updateBulkState();
})();

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
