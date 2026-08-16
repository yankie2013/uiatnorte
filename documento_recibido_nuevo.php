<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/app/Support/CaseSummaryWidget.php';

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
$caseSummaryContext = case_summary_widget_context($pdo, (int) ($data['accidente_id'] ?? 0));
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
        $caseSummaryContext = case_summary_widget_context($pdo, (int) ($data['accidente_id'] ?? 0));
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
  :root{--bg:#f3f7fb;--panel:#fff;--text:#172033;--muted:#66758a;--border:#d8e2ee;--primary:#0f9f91;--primary-dark:#087d73;--soft:#f7fafd;--radius:14px;--gap:14px;--max-width:920px;--shadow:0 10px 28px rgba(30,64,100,.08)}
  @media (prefers-color-scheme:dark){:root{--bg:#0b1220;--panel:#111b2e;--text:#e8eef8;--muted:#9aa9bd;--border:#30405a;--primary:#14b8a6;--primary-dark:#0f9488;--soft:#0e1728;--shadow:0 12px 30px rgba(0,0,0,.24)}}
  *{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;background:var(--bg);color:var(--text);padding:18px}body.is-embed{padding:14px;background:var(--soft)}
  .wrap{max-width:var(--max-width);margin:0 auto;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}body.is-embed .wrap{border:0;box-shadow:none;padding:10px 12px;background:transparent}
  .head{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap}.eyebrow{margin:0 0 4px;color:var(--primary-dark);font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}h1{margin:0;font-size:1.25rem;line-height:1.25}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 15px;border-radius:9px;text-decoration:none;font:inherit;font-weight:750;border:1px solid transparent;cursor:pointer}.btn.secondary{background:var(--panel);border-color:var(--border);color:var(--text)}.btn.primary{background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;box-shadow:0 7px 16px rgba(15,159,145,.22)}
  .form-stack{display:grid;gap:14px}.form-section{border:1px solid var(--border);border-radius:12px;background:var(--panel);padding:15px}.section-head{margin-bottom:12px;padding-bottom:9px;border-bottom:1px solid var(--border)}.section-head h2{margin:0;font-size:.96rem}.section-head p{margin:3px 0 0;font-size:.82rem;color:var(--muted)}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--gap)}.full{grid-column:1/-1}
  label{display:block;font-size:.86rem;font-weight:750;margin-bottom:6px}input[type="text"],input[type="date"],select,textarea{width:100%;min-height:42px;padding:10px 11px;border-radius:9px;border:1px solid var(--border);background:var(--panel);color:var(--text);font:inherit;font-size:.92rem;outline:none;transition:border-color .15s,box-shadow .15s}input:focus,select:focus,textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(15,159,145,.13)}input[readonly]{background:var(--soft);color:var(--muted)}textarea{min-height:105px;resize:vertical;line-height:1.45}
  .help{font-size:.78rem;color:var(--muted);margin-top:5px;line-height:1.35}.error{background:#fff1f1;padding:11px 13px;border-radius:10px;border:1px solid #f2b8b8;color:#8a1f1f;margin-bottom:14px}.form-actions{display:flex;justify-content:space-between;align-items:center;gap:10px;padding-top:2px}
  .annex-toolbar{display:flex;align-items:center;gap:9px;flex-wrap:wrap}.annex-tabs{display:flex;gap:7px;flex:1;flex-wrap:wrap}.annex-tab{min-height:38px;padding:8px 13px;border:1px solid var(--border);border-radius:9px;background:var(--panel);color:var(--text);font:inherit;font-size:.82rem;font-weight:800;cursor:pointer}.annex-tab.is-active{border-color:var(--primary);background:rgba(15,159,145,.11);color:var(--primary-dark);box-shadow:0 0 0 2px rgba(15,159,145,.08)}.annex-add{white-space:nowrap}.annex-panels{margin-top:12px}.annex-panel{padding:13px;border:1px solid var(--border);border-radius:10px;background:var(--soft)}.annex-panel[hidden]{display:none}.annex-panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:9px}.annex-panel-title{font-size:.84rem;font-weight:850}.annex-remove{border:0;background:transparent;color:#b42318;font:inherit;font-size:.78rem;font-weight:800;cursor:pointer;padding:5px}.annex-remove:disabled{display:none}
  .ai-section{border-color:rgba(15,159,145,.38);background:linear-gradient(145deg,rgba(15,159,145,.07),var(--panel) 58%)}.ai-title-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.ai-badge{display:inline-flex;padding:4px 8px;border-radius:999px;background:rgba(15,159,145,.13);color:var(--primary-dark);font-size:.7rem;font-weight:850;letter-spacing:.04em}.scan-grid{display:grid;grid-template-columns:minmax(230px,.8fr) minmax(280px,1.2fr);gap:14px}.scan-upload{display:grid;gap:9px}.scan-file{width:100%;padding:11px;border:1px dashed var(--primary);border-radius:10px;background:var(--soft);color:var(--text);font:inherit;font-size:.83rem}.scan-paste{display:flex;align-items:center;justify-content:center;min-height:48px;padding:10px;border:1px dashed var(--border);border-radius:10px;background:var(--panel);color:var(--muted);font-size:.78rem;font-weight:700;text-align:center;outline:none}.scan-paste:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(15,159,145,.13)}.scan-preview{display:none;width:100%;max-height:260px;object-fit:contain;border:1px solid var(--border);border-radius:10px;background:var(--panel)}.scan-preview.is-visible{display:block}.scan-controls{display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:10px}.scan-status{min-height:20px;color:var(--muted);font-size:.82rem;line-height:1.4}.scan-status.is-success{color:#087d55}.scan-status.is-error{color:#b42318}.scan-result{display:none;width:100%;padding:10px;border-radius:10px;background:var(--soft);border:1px solid var(--border);font-size:.78rem;color:var(--muted);line-height:1.45}.scan-result.is-visible{display:block}.scan-result strong{color:var(--text)}
  @media (prefers-color-scheme:dark){.error{background:#37191f;border-color:#73333e;color:#fecaca}.scan-status.is-success{color:#5eead4}.scan-status.is-error{color:#fca5a5}}@media (max-width:680px){body,body.is-embed{padding:8px}.wrap,body.is-embed .wrap{padding:8px}.form-grid,.scan-grid{grid-template-columns:1fr}.full{grid-column:auto}.form-actions{align-items:stretch}.form-actions .btn{flex:1}}
</style>
</head>
<body class="<?= $embed ? 'is-embed' : '' ?>">
<?php if (!$embed) include __DIR__ . '/sidebar.php'; ?>
<div class="wrap">
  <div class="head">
    <div>
      <p class="eyebrow">Documentos recibidos</p>
      <h1>Registrar nuevo documento</h1>
      <div class="help">Completa los datos principales y vincúlalo con un oficio si corresponde.</div>
      <?= case_summary_widget_render($caseSummaryContext, 'documento-recibido-nuevo') ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($embed): ?>
        <button type="button" class="btn secondary" onclick="try{window.parent&&window.parent.postMessage({type:'documento_recibido.close'},'*');}catch(e){}">Cerrar</button>
      <?php else: ?>
        <button type="button" class="btn secondary" onclick="if(window.history.length>1){window.history.back();}else{window.location.href='accidente_vista_tabs.php?accidente_id=<?= (int) ($accidenteId ?? 0) ?>&tab=documentos&subtab=recibidos';}">← Volver atrás</button>
        <a href="index.php" class="btn secondary">Panel</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($errores): ?>
    <div class="error"><?php foreach ($errores as $e): ?>- <?= h($e) ?><br><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="POST" class="form-stack" novalidate>
    <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <section class="form-section ai-section">
      <div class="section-head">
        <div class="ai-title-row"><h2>Escanear documento</h2><span class="ai-badge">ANÁLISIS INTELIGENTE</span></div>
        <p>Sube una foto legible y completaremos automáticamente los campos. Podrás corregirlos antes de guardar.</p>
      </div>
      <div class="scan-grid">
        <div class="scan-upload">
          <input class="scan-file" id="documento_scan_imagen" type="file" accept="image/jpeg,image/png,image/webp" capture="environment">
          <div class="scan-paste" id="documento_scan_pegar" tabindex="0">También puedes pegar una imagen aquí con Ctrl+V</div>
          <img class="scan-preview" id="documento_scan_preview" alt="Vista previa del documento seleccionado">
        </div>
        <div class="scan-controls">
          <button class="btn primary" id="documento_scan_analizar" type="button">Analizar imagen y completar</button>
          <div class="scan-status" id="documento_scan_estado" aria-live="polite">Selecciona una foto del documento.</div>
          <div class="scan-result" id="documento_scan_resultado"></div>
          <div class="help">La imagen se utiliza para extraer la información; no se guarda como adjunto.</div>
        </div>
      </div>
    </section>

    <section class="form-section">
      <div class="section-head"><h2>Identificación y fechas</h2><p>El accidente ya viene seleccionado desde la vista actual.</p></div>
      <div class="form-grid">
    <div class="full">
      <label for="accidente_id">Accidente</label>
      <select id="accidente_id" name="accidente_id">
        <option value="">Sin accidente asociado</option>
        <?php foreach ($ctx['accidentes'] as $a): ?>
          <option value="<?= h($a['id']) ?>" <?= ((string)$data['accidente_id'] === (string)$a['id']) ? 'selected' : '' ?>><?= h($a['id']) ?> - <?= h(($a['sidpol'] ?? '')) ?><?= !empty($a['lugar']) ? (' - '.h($a['lugar'])) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="fecha_recepcion">Fecha de recepción</label>
      <input id="fecha_recepcion" type="date" name="fecha_recepcion" value="<?= h($data['fecha_recepcion']) ?>" readonly>
      <div class="help">Se registra automáticamente con la fecha de hoy.</div>
    </div>
    <div>
      <label for="fecha_documento">Fecha del documento</label>
      <input id="fecha_documento" type="date" name="fecha_documento" value="<?= h($data['fecha_documento']) ?>">
    </div>
      </div>
    </section>

    <section class="form-section">
      <div class="section-head"><h2>Datos del documento</h2><p>Información que permitirá reconocerlo rápidamente en el listado.</p></div>
      <div class="form-grid">
    <div class="full">
      <label for="asunto">Asunto</label>
      <input id="asunto" type="text" name="asunto" value="<?= h($data['asunto']) ?>" placeholder="Ej.: Remisión de resultado de dosaje etílico">
    </div>
    <div>
      <label for="entidad_persona">Entidad o persona remitente</label>
      <input id="entidad_persona" type="text" name="entidad_persona" value="<?= h($data['entidad_persona']) ?>" placeholder="Nombre de la entidad o persona">
    </div>
    <div>
      <label for="tipo_documento">Tipo de documento</label>
      <input id="tipo_documento" type="text" name="tipo_documento" value="<?= h($data['tipo_documento']) ?>" placeholder="Ej.: Oficio, informe, certificado">
    </div>
    <div>
      <label for="numero_documento">Número de documento y siglas</label>
      <input id="numero_documento" type="text" name="numero_documento" value="<?= h($data['numero_documento']) ?>" placeholder="Número y año, si corresponde">
    </div>
    <div>
      <label for="estado">Estado</label>
      <select id="estado" name="estado">
        <option value="">Sin estado</option>
        <?php foreach ($ctx['estados'] as $estado): ?>
          <option value="<?= h($estado) ?>" <?= ($data['estado'] === $estado) ? 'selected' : '' ?>><?= h($estado) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
      </div>
    </section>

    <section class="form-section">
      <div class="section-head"><h2>Detalle y relación</h2><p>Resume el contenido y enlázalo con un oficio del accidente cuando corresponda.</p></div>
      <div class="form-grid">
    <div class="full">
      <label for="contenido">Contenido</label>
      <textarea id="contenido" name="contenido" placeholder="Describe brevemente la información recibida..."><?= h($data['contenido']) ?></textarea>
    </div>
    <div class="full">
      <label for="referencia_oficio_id">Oficio relacionado</label>
      <select id="referencia_oficio_id" name="referencia_oficio_id">
        <option value="">Sin oficio relacionado</option>
        <?php foreach ($ctx['oficios'] as $o): ?>
          <option value="<?= h($o['id']) ?>" <?= ((string)$data['referencia_oficio_id'] === (string)$o['id']) ? 'selected' : '' ?>><?= h($service->oficioLabel($o, $ctx['asuntos'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
      </div>
    </section>
    <section class="form-section">
      <div class="section-head"><h2>Anexos remitidos</h2><p>Registra cada anexo por separado. Puedes agregar uno o varios mediante las pestañas.</p></div>
      <div class="annex-editor" id="anexos_editor">
        <div class="annex-toolbar">
          <div class="annex-tabs" id="anexos_tabs" role="tablist" aria-label="Anexos remitidos">
            <?php foreach ($data['anexos'] as $index => $anexo): ?>
              <button type="button" class="annex-tab <?= $index === 0 ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">Anexo <?= $index + 1 ?></button>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn secondary annex-add" id="anexo_agregar">+ Agregar anexo</button>
        </div>
        <div class="annex-panels" id="anexos_panels">
          <?php foreach ($data['anexos'] as $index => $anexo): ?>
            <div class="annex-panel" role="tabpanel" <?= $index === 0 ? '' : 'hidden' ?>>
              <div class="annex-panel-head"><span class="annex-panel-title">Anexo remitido <?= $index + 1 ?></span><button type="button" class="annex-remove">Quitar anexo</button></div>
              <label>Descripción del anexo</label>
              <textarea name="anexos[]" maxlength="1000" placeholder="Ej.: Un CD que contiene grabaciones de videovigilancia"><?= h($anexo) ?></textarea>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <div class="form-actions">
        <?php if ($embed): ?>
          <button type="button" class="btn secondary" onclick="try{window.parent&&window.parent.postMessage({type:'documento_recibido.close'},'*');}catch(e){}">Cancelar</button>
        <?php else: ?>
          <a class="btn secondary" href="documento_recibido_listar.php">Cancelar</a>
        <?php endif; ?>
      <button class="btn primary" type="submit">Guardar documento</button>
    </div>
  </form>
</div>
<script>
(function () {
  const tabs = document.getElementById('anexos_tabs');
  const panels = document.getElementById('anexos_panels');
  const addButton = document.getElementById('anexo_agregar');
  if (!tabs || !panels || !addButton) return;

  function items() {
    return {
      tabs: Array.from(tabs.querySelectorAll('.annex-tab')),
      panels: Array.from(panels.querySelectorAll('.annex-panel'))
    };
  }

  function activate(index, focusTab) {
    const current = items();
    const safeIndex = Math.max(0, Math.min(index, current.tabs.length - 1));
    current.tabs.forEach((tab, itemIndex) => {
      const active = itemIndex === safeIndex;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
      current.panels[itemIndex].hidden = !active;
    });
    if (focusTab) current.tabs[safeIndex]?.focus();
  }

  function refresh() {
    const current = items();
    current.tabs.forEach((tab, index) => {
      const tabId = 'anexo_tab_' + index;
      const panelId = 'anexo_panel_' + index;
      tab.textContent = 'Anexo ' + (index + 1);
      tab.id = tabId;
      tab.setAttribute('aria-controls', panelId);
      current.panels[index].id = panelId;
      current.panels[index].setAttribute('aria-labelledby', tabId);
      current.panels[index].querySelector('.annex-panel-title').textContent = 'Anexo remitido ' + (index + 1);
      current.panels[index].querySelector('.annex-remove').disabled = current.panels.length === 1;
    });
  }

  tabs.addEventListener('click', (event) => {
    const tab = event.target.closest('.annex-tab');
    if (!tab) return;
    activate(items().tabs.indexOf(tab), false);
  });

  tabs.addEventListener('keydown', (event) => {
    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
    event.preventDefault();
    const current = items();
    const active = current.tabs.indexOf(event.target.closest('.annex-tab'));
    const next = (active + (event.key === 'ArrowRight' ? 1 : -1) + current.tabs.length) % current.tabs.length;
    activate(next, true);
  });

  addButton.addEventListener('click', () => {
    const tab = document.createElement('button');
    tab.type = 'button';
    tab.className = 'annex-tab';
    tab.setAttribute('role', 'tab');
    tabs.appendChild(tab);

    const panel = document.createElement('div');
    panel.className = 'annex-panel';
    panel.setAttribute('role', 'tabpanel');
    panel.innerHTML = '<div class="annex-panel-head"><span class="annex-panel-title"></span><button type="button" class="annex-remove">Quitar anexo</button></div><label>Descripción del anexo</label><textarea name="anexos[]" maxlength="1000" placeholder="Ej.: Un CD que contiene grabaciones de videovigilancia"></textarea>';
    panels.appendChild(panel);
    refresh();
    const newIndex = items().tabs.length - 1;
    activate(newIndex, false);
    panel.querySelector('textarea').focus();
  });

  panels.addEventListener('click', (event) => {
    const removeButton = event.target.closest('.annex-remove');
    if (!removeButton || removeButton.disabled) return;
    const current = items();
    const panel = removeButton.closest('.annex-panel');
    const index = current.panels.indexOf(panel);
    current.tabs[index].remove();
    panel.remove();
    refresh();
    activate(Math.min(index, items().tabs.length - 1), true);
  });

  refresh();
  activate(0, false);
})();

(function () {
  const imageInput = document.getElementById('documento_scan_imagen');
  const pasteZone = document.getElementById('documento_scan_pegar');
  const preview = document.getElementById('documento_scan_preview');
  const analyzeButton = document.getElementById('documento_scan_analizar');
  const statusBox = document.getElementById('documento_scan_estado');
  const resultBox = document.getElementById('documento_scan_resultado');
  let pastedFile = null;

  function setStatus(message, state) {
    statusBox.textContent = message || '';
    statusBox.classList.toggle('is-success', state === 'success');
    statusBox.classList.toggle('is-error', state === 'error');
  }

  function showPreview(file) {
    if (!file) {
      preview.removeAttribute('src');
      preview.classList.remove('is-visible');
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      preview.src = String(reader.result || '');
      preview.classList.add('is-visible');
    };
    reader.readAsDataURL(file);
  }

  function useFile(file, source) {
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(String(file.type || '').toLowerCase())) {
      setStatus('Usa una imagen JPG, PNG o WEBP.', 'error');
      return;
    }
    pastedFile = source === 'paste' ? file : null;
    showPreview(file);
    resultBox.classList.remove('is-visible');
    setStatus('Imagen lista. Presiona “Analizar imagen y completar”.');
  }

  function currentFile() {
    return (imageInput.files && imageInput.files[0]) || pastedFile;
  }

  function imageFromClipboard(event) {
    for (const item of Array.from(event.clipboardData?.items || [])) {
      if (item.kind === 'file' && String(item.type || '').startsWith('image/')) {
        return item.getAsFile();
      }
    }
    return null;
  }

  function applyValue(id, value) {
    const field = document.getElementById(id);
    const clean = String(value || '').trim();
    if (!field || clean === '') return false;
    field.value = clean;
    field.dispatchEvent(new Event('input', {bubbles: true}));
    field.dispatchEvent(new Event('change', {bubbles: true}));
    return true;
  }

  async function ensureLocalOcr() {
    if (window.Tesseract) return window.Tesseract;
    await new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
      script.async = true;
      script.onload = resolve;
      script.onerror = () => reject(new Error('No se pudo cargar el respaldo de lectura OCR.'));
      document.head.appendChild(script);
    });
    if (!window.Tesseract) throw new Error('El motor OCR no quedó disponible.');
    return window.Tesseract;
  }

  function parseLocalDocument(rawText) {
    const text = String(rawText || '').replace(/\r/g, '').replace(/[ \t]+/g, ' ').replace(/\n{3,}/g, '\n\n').trim();
    const lines = text.split('\n').map((line) => line.trim()).filter(Boolean);
    const typePattern = /\b(OFICIO|INFORME|CARTA|ACTA|CERTIFICADO|MEMORANDO|SOLICITUD)\b/iu;
    const documentLineIndex = lines.findIndex((line) => typePattern.test(line));
    const documentLine = documentLineIndex >= 0 ? lines[documentLineIndex] : '';
    const typeMatch = (documentLine || text).match(typePattern);
    const numberMatch = documentLine.match(/(?:N[°ºo.]?|NRO\.?|NO\.?)\s*[:.-]?\s*(.+)$/iu);
    const numero = String(numberMatch?.[1] || '').replace(/\s+/g, ' ').trim();
    const siglas = numero.replace(/^\s*\d+[\s-]*\d{4}[\s-]*/u, '').trim();

    const months = {enero:'01',febrero:'02',marzo:'03',abril:'04',mayo:'05',junio:'06',julio:'07',agosto:'08',septiembre:'09',setiembre:'09',octubre:'10',noviembre:'11',diciembre:'12'};
    let fecha = '';
    const longDate = text.match(/\b(\d{1,2})\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\s+(?:de|del)\s+(\d{4})\b/iu);
    const shortDate = text.match(/\b(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})\b/u);
    if (longDate) fecha = longDate[3] + '-' + months[longDate[2].toLowerCase()] + '-' + String(longDate[1]).padStart(2, '0');
    else if (shortDate) fecha = shortDate[3] + '-' + String(shortDate[2]).padStart(2, '0') + '-' + String(shortDate[1]).padStart(2, '0');

    const headerLines = documentLineIndex > 0 ? lines.slice(0, documentLineIndex) : lines.slice(0, 10);
    const entityTerms = /\b(comisar[ií]a|unidad|divisi[oó]n|departamento|direcci[oó]n|ministerio|municipalidad|fiscal[ií]a|regi[oó]n policial|polic[ií]a nacional)\b/iu;
    const entityCandidates = headerLines
      .filter((line) => entityTerms.test(line) && line.length <= 220)
      .map((line) => {
        const specific = line.match(/\b(Comisar[ií]a(?:\s+PNP)?(?:\s+de)?\s+[\p{L}][\p{L}\s.-]{1,50})(?=\s*[|;]|$)/iu);
        return specific ? specific[1].trim() : line;
      });
    const entity = entityCandidates.sort((a, b) => {
      const score = (value) => (/comisar[ií]a/iu.test(value) ? 10 : 0) + (/unidad|divisi[oó]n|departamento/iu.test(value) ? 5 : 0) - value.length / 200;
      return score(b) - score(a);
    })[0] || '';

    const asuntoIndex = lines.findIndex((line) => /^ASUNTO\b/iu.test(line));
    let asunto = '';
    if (asuntoIndex >= 0) {
      asunto = lines[asuntoIndex].replace(/^ASUNTO\s*[:.-]?\s*/iu, '').trim();
      if (!asunto && lines[asuntoIndex + 1]) asunto = lines[asuntoIndex + 1];
    }

    const bodyStart = asuntoIndex >= 0 ? asuntoIndex + 1 : Math.max(documentLineIndex + 1, 0);
    const bodyText = lines.slice(bodyStart)
      .filter((line) => !/^(SEÑOR|SEÑORA|ASUNTO|REF\.?|REFERENCIA)\b/iu.test(line))
      .join(' ')
      .replace(/\s+/g, ' ')
      .trim();
    const excerpt = bodyText.length > 520 ? bodyText.slice(0, 517).replace(/\s+\S*$/u, '') + '...' : bodyText;
    const contenido = asunto !== ''
      ? 'El documento comunica: ' + asunto.replace(/[.\s]+$/u, '') + '. ' + excerpt
      : excerpt;

    return {
      tipo_documento: String(typeMatch?.[1] || '').toUpperCase(),
      fecha_documento: fecha,
      numero_documento: numero,
      siglas_unidad: siglas,
      entidad_persona: entity,
      asunto,
      contenido: contenido.trim(),
      advertencias: ['Lectura realizada con OCR local. Revisa especialmente el número, las siglas y el resumen.']
    };
  }

  async function analyzeWithLocalOcr(file) {
    setStatus('El análisis avanzado no está configurado; ejecutando lectura OCR local...');
    const Tesseract = await ensureLocalOcr();
    const result = await Tesseract.recognize(file, 'spa', {
      logger: (message) => {
        if (!message.status) return;
        const progress = typeof message.progress === 'number' ? ' ' + Math.round(message.progress * 100) + '%' : '';
        setStatus('OCR local: ' + message.status + progress);
      }
    });
    const text = String(result?.data?.text || '').trim();
    if (!text) throw new Error('No se pudo leer texto. Prueba con una foto más nítida y frontal.');
    return parseLocalDocument(text);
  }

  imageInput.addEventListener('change', () => {
    const file = imageInput.files && imageInput.files[0];
    pastedFile = null;
    showPreview(null);
    if (file) useFile(file, 'input');
  });

  pasteZone.addEventListener('paste', (event) => {
    const file = imageFromClipboard(event);
    if (!file) {
      setStatus('El portapapeles no contiene una imagen.', 'error');
      return;
    }
    event.preventDefault();
    imageInput.value = '';
    useFile(file, 'paste');
  });

  analyzeButton.addEventListener('click', async () => {
    const file = currentFile();
    if (!file) {
      setStatus('Selecciona o pega una imagen primero.', 'error');
      return;
    }

    analyzeButton.disabled = true;
    analyzeButton.textContent = 'Analizando documento...';
    resultBox.classList.remove('is-visible');
    setStatus('Leyendo membrete, fecha, número, asunto y contenido...');

    try {
      const payload = new FormData();
      payload.append('documento_imagen', file, file.name || 'documento.jpg');
      const response = await fetch('documento_recibido_analizar_ia.php', {
        method: 'POST',
        body: payload,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const json = await response.json().catch(() => null);
      let data = null;
      if (response.status === 503) {
        data = await analyzeWithLocalOcr(file);
      } else if (!response.ok || !json || !json.ok) {
        throw new Error(json?.message || 'No se pudo analizar la imagen.');
      } else {
        data = json.data || {};
      }

      const applied = [
        applyValue('tipo_documento', data.tipo_documento),
        applyValue('fecha_documento', data.fecha_documento),
        applyValue('numero_documento', data.numero_documento),
        applyValue('entidad_persona', data.entidad_persona),
        applyValue('asunto', data.asunto),
        applyValue('contenido', data.contenido)
      ].filter(Boolean).length;

      const details = [];
      if (data.siglas_unidad) details.push('<strong>Siglas detectadas:</strong> ' + escapeHtml(data.siglas_unidad));
      if (Array.isArray(data.advertencias) && data.advertencias.length) {
        details.push('<strong>Revisar:</strong> ' + data.advertencias.map(escapeHtml).join(' · '));
      }
      resultBox.innerHTML = details.join('<br>') || '<strong>Lectura completa.</strong> Revisa los campos antes de guardar.';
      resultBox.classList.add('is-visible');
      setStatus(applied + ' campos completados automáticamente.', 'success');
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'No se pudo analizar la imagen.', 'error');
    } finally {
      analyzeButton.disabled = false;
      analyzeButton.textContent = 'Analizar imagen y completar';
    }
  });

  function escapeHtml(value) {
    const node = document.createElement('span');
    node.textContent = String(value || '');
    return node.innerHTML;
  }
})();
</script>
</body>
</html>
