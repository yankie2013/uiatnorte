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
$filters = [
    'accidente_id' => $_GET['accidente_id'] ?? '',
    'estado' => trim((string) ($_GET['estado'] ?? '')),
    'tipo' => (int) ($_GET['tipo'] ?? 0),
    'q' => trim((string) ($_GET['q'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$message = trim((string) ($_GET['msg'] ?? ''));

function url_with_params(array $overrides): string
{
    $query = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    $qs = http_build_query($query);
    return basename(__FILE__) . ($qs !== '' ? ('?' . $qs) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $service->cambiarEstado((int) ($_POST['id'] ?? 0), (string) ($_POST['estado'] ?? ''));
        echo json_encode(['ok' => true, 'msg' => 'Estado actualizado.']);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_create_from_ocr') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $items = $_POST['items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }

        $contents = [];
        foreach ($items as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $contents[] = $text;
            }
        }
        $contents = array_values(array_unique($contents));
        if ($contents === []) {
            throw new InvalidArgumentException('No se detectaron diligencias para registrar.');
        }

        $accidenteIdPost = (int) ($_POST['accidente_id'] ?? 0);
        if ($accidenteIdPost <= 0) {
            throw new InvalidArgumentException('No se indicó el accidente asociado.');
        }

        $created = [];
        $pdo->beginTransaction();
        foreach ($contents as $contenido) {
            $created[] = $service->crear([
                'accidente_id' => $accidenteIdPost,
                'tipo_diligencia_id' => '',
                'contenido' => $contenido,
                'estado' => 'Pendiente',
                'oficio_id' => '',
                'citacion_id' => [],
                'documento_realizado' => '',
                'documentos_recibidos' => '',
            ]);
        }
        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'created' => count($created),
            'msg' => count($created) === 1 ? '1 diligencia creada correctamente.' : count($created) . ' diligencias creadas correctamente.',
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(422);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $service->eliminar((int) ($_POST['id'] ?? 0));
        $target = url_with_params(['msg' => 'Diligencia eliminada correctamente.', 'page' => 1]);
        header('Location: ' . $target);
        exit;
    } catch (Throwable $e) {
        $message = $e->getMessage();
    }
}

$ctx = $service->listado($filters, $page, $perPage);
$rows = $ctx['rows'];
$total = (int) $ctx['total'];
$totalPages = max(1, (int) ceil($total / $perPage));
$accidenteId = (int) ($filters['accidente_id'] ?: 0);
$newUrl = 'diligenciapendiente_nuevo.php' . ($accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : '');
@include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Listado de diligencias</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--bg:#f6f7fb;--card:#fff;--text:#111827;--muted:#6b7280;--accent:#1d4ed8;--border:#d9e0ea;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--bg:#0b1220;--card:#111827;--text:#e5e7eb;--muted:#9ca3af;--accent:#60a5fa;--border:#243041;--danger:#fecaca}}
body{margin:0;padding:24px;background:var(--bg);color:var(--text);font-family:"Segoe UI",sans-serif}.container{max-width:1120px;margin:0 auto}.header{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px}.title{margin:0;font-size:1.7rem}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px;box-shadow:0 12px 32px rgba(0,0,0,.08)}.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}.input,select,textarea{padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);box-sizing:border-box}.btn{display:inline-block;padding:10px 14px;border-radius:10px;text-decoration:none;border:1px solid var(--border);background:transparent;color:var(--text);font-weight:600;cursor:pointer}.btn.primary{background:var(--accent);color:#fff;border-color:transparent}.btn:disabled{opacity:.65;cursor:not-allowed}.alert{padding:12px 14px;border-radius:12px;margin-bottom:14px;background:rgba(29,78,216,.12);color:var(--text)}table{width:100%;border-collapse:collapse}th,td{padding:12px;border-bottom:1px solid var(--border);vertical-align:top}th{text-align:left;color:var(--muted);font-size:.9rem}.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--accent);font-weight:700}.small{font-size:.9rem;color:var(--muted)}.actions{display:flex;gap:8px;flex-wrap:wrap}.pager{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:14px;flex-wrap:wrap}.content{white-space:pre-wrap;line-height:1.35}.estado{min-width:140px}.danger{color:var(--danger)}.modal-backdrop{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.5);padding:20px;z-index:1200}.modal{width:100%;max-width:760px;max-height:calc(100vh - 40px);overflow:auto;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:20px;box-shadow:0 20px 50px rgba(0,0,0,.3)}.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}.paste-zone{margin-top:12px;padding:12px;border-radius:12px;border:1px dashed var(--border);background:rgba(148,163,184,.10)}.ocr-preview{display:none;margin-top:12px}.ocr-preview img{display:block;width:100%;max-height:260px;object-fit:contain;border:1px solid var(--border);border-radius:12px}.ocr-status{min-height:20px;margin-top:10px;color:var(--muted);font-size:.92rem}.ocr-list{display:none;margin-top:12px}.ocr-list textarea{width:100%;min-height:170px;resize:vertical}
@media (max-width:760px){body{padding:14px}table,thead,tbody,tr,td,th{display:block}thead{display:none}td{padding:10px 0}td::before{content:attr(data-label);display:block;color:var(--muted);font-size:.84rem;margin-bottom:4px}}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div>
      <h1 class="title">Listado de diligencias</h1>
      <div class="small">Consulta, filtra y actualiza el estado de las diligencias pendientes.</div>
    </div>
    <div class="actions">
      <?php if ($accidenteId > 0): ?>
        <button class="btn primary" type="button" id="btn-ocr-bulk">Subir lista por imagen</button>
      <?php endif; ?>
      <a class="btn primary" href="<?= h($newUrl) ?>">+ Nueva diligencia</a>
      <?php if ($accidenteId > 0): ?>
        <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= h($accidenteId) ?>">Volver al accidente</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <?php if ($message !== ''): ?><div class="alert"><?= h($message) ?></div><?php endif; ?>

    <form method="get" class="filters">
      <?php if ($accidenteId > 0): ?><input type="hidden" name="accidente_id" value="<?= h($accidenteId) ?>"><?php endif; ?>
      <input class="input" type="text" name="q" value="<?= h($filters['q']) ?>" placeholder="Buscar contenido, documentos o tipo..." style="min-width:240px;">
      <select name="tipo" class="input">
        <option value="0">Tipo de diligencia</option>
        <?php foreach ($ctx['tipos'] as $tipo): ?>
          <option value="<?= h($tipo['id']) ?>" <?= (int) $filters['tipo'] === (int) $tipo['id'] ? 'selected' : '' ?>><?= h($tipo['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="estado" class="input">
        <option value="">Estado</option>
        <?php foreach ($ctx['estados'] as $estado): ?>
          <option value="<?= h($estado) ?>" <?= $filters['estado'] === $estado ? 'selected' : '' ?>><?= h($estado) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn">Filtrar</button>
      <a class="btn" href="<?= h(url_with_params(['q' => null, 'tipo' => null, 'estado' => null, 'page' => 1, 'msg' => null])) ?>">Limpiar</a>
    </form>

    <div class="small" style="margin-bottom:10px;">Mostrando <?= count($rows) ?> de <?= h($total) ?> registro(s).</div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Tipo</th>
          <th>Contenido</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5" class="small">No se encontraron diligencias con los filtros aplicados.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
          <?php
            $citaciones = [];
            if (!empty($row['citacion_ids'])) {
                $decoded = json_decode((string) $row['citacion_ids'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $citId) {
                        $citId = (int) $citId;
                        if ($citId > 0) {
                            $citaciones[] = $citId;
                        }
                    }
                }
            }
            if ($citaciones === [] && !empty($row['citacion_id'])) {
                $citaciones[] = (int) $row['citacion_id'];
            }
          ?>
          <tr>
            <td data-label="#"><strong><?= h($row['id']) ?></strong><div class="small">Acc. <?= h($row['accidente_id'] ?? '-') ?></div></td>
            <td data-label="Tipo"><?= !empty($row['tipo_nombre']) ? ('<span class="badge">' . h($row['tipo_nombre']) . '</span>') : '<span class="small">Sin tipo</span>' ?></td>
            <td data-label="Contenido">
              <div class="content"><?= h($row['contenido'] ?? '') ?></div>
              <?php if (!empty($row['documento_realizado'])): ?><div class="small" style="margin-top:8px;">Documento realizado: <?= h($row['documento_realizado']) ?></div><?php endif; ?>
              <?php if (!empty($row['documentos_recibidos'])): ?><div class="small" style="margin-top:4px;">Documentos recibidos: <?= h(mb_strimwidth((string) $row['documentos_recibidos'], 0, 140, '...')) ?></div><?php endif; ?>
              <?php if (!empty($row['oficio_id'])): ?><div class="small" style="margin-top:4px;">Oficio: <?= h($ctx['oficios_labels'][(int) $row['oficio_id']] ?? ('Oficio #' . $row['oficio_id'])) ?></div><?php endif; ?>
              <?php if ($citaciones): ?>
                <div class="small" style="margin-top:4px;">Citaciones:
                  <?= h(implode(', ', array_map(static fn ($citId) => $ctx['citaciones_labels'][$citId] ?? ('Citación #' . $citId), $citaciones))) ?>
                </div>
              <?php endif; ?>
            </td>
            <td data-label="Estado" class="estado">
              <select class="input js-estado" data-id="<?= h($row['id']) ?>">
                <?php foreach ($ctx['estados'] as $estado): ?>
                  <option value="<?= h($estado) ?>" <?= (string) ($row['estado'] ?? 'Pendiente') === $estado ? 'selected' : '' ?>><?= h($estado) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td data-label="Acciones">
              <div class="actions">
                <a class="btn" href="diligenciapendiente_ver.php?id=<?= h($row['id']) ?>">Ver</a>
                <a class="btn" href="diligenciapendiente_editar.php?id=<?= h($row['id']) ?>">Editar</a>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= h($row['id']) ?>">
                  <button type="submit" class="btn danger" onclick="return confirm('¿Eliminar diligencia #<?= h($row['id']) ?>?');">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="pager">
      <div class="small">Página <?= h($page) ?> de <?= h($totalPages) ?></div>
      <div class="actions">
        <?php if ($page > 1): ?>
          <a class="btn" href="<?= h(url_with_params(['page' => 1, 'msg' => null])) ?>">Primera</a>
          <a class="btn" href="<?= h(url_with_params(['page' => $page - 1, 'msg' => null])) ?>">Anterior</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="btn" href="<?= h(url_with_params(['page' => $page + 1, 'msg' => null])) ?>">Siguiente</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($accidenteId > 0): ?>
<div class="modal-backdrop" id="ocr-bulk-modal" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="ocr-bulk-title">
    <h2 id="ocr-bulk-title" style="margin-top:0;">Cargar diligencias desde imagen</h2>
    <div class="small">Selecciona o pega una imagen con una lista numerada. Se registrara automaticamente una diligencia pendiente por cada item detectado.</div>
    <input type="file" id="ocrBulkImage" accept="image/png,image/jpeg,image/jpg,image/webp" style="margin-top:12px;">
    <div class="paste-zone" id="ocrBulkPaste" tabindex="0">Pega aqui una imagen con Ctrl+V o Cmd+V</div>
    <div class="ocr-preview" id="ocrBulkPreviewWrap"><img id="ocrBulkPreview" alt="Vista previa OCR"></div>
    <div class="ocr-status" id="ocrBulkStatus"></div>
    <div class="ocr-list" id="ocrBulkListWrap">
      <div class="small" style="margin-bottom:6px;">Texto detectado antes de guardar:</div>
      <textarea id="ocrBulkList" class="input" readonly></textarea>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn" id="ocrBulkCancel">Cerrar</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
<?php if ($accidenteId > 0): ?>
(function () {
  const accidenteId = <?= json_encode($accidenteId) ?>;
  const openBtn = document.getElementById('btn-ocr-bulk');
  const modal = document.getElementById('ocr-bulk-modal');
  const imageInput = document.getElementById('ocrBulkImage');
  const pasteZone = document.getElementById('ocrBulkPaste');
  const previewWrap = document.getElementById('ocrBulkPreviewWrap');
  const preview = document.getElementById('ocrBulkPreview');
  const status = document.getElementById('ocrBulkStatus');
  const cancelBtn = document.getElementById('ocrBulkCancel');
  const listWrap = document.getElementById('ocrBulkListWrap');
  const listText = document.getElementById('ocrBulkList');
  let clipboardFile = null;
  let busy = false;

  function setStatus(text, ok = null) {
    status.textContent = text || '';
    status.style.color = ok === true ? '#166534' : ok === false ? '#b91c1c' : '';
  }

  function openModal() {
    clipboardFile = null;
    imageInput.value = '';
    previewWrap.style.display = 'none';
    preview.removeAttribute('src');
    listWrap.style.display = 'none';
    listText.value = '';
    setStatus('Sube o pega la imagen para crear las diligencias.');
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => pasteZone.focus(), 0);
  }

  function closeModal() {
    if (busy) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
  }

  function showPreview(file) {
    if (!file) {
      previewWrap.style.display = 'none';
      preview.removeAttribute('src');
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      preview.src = String(reader.result || '');
      previewWrap.style.display = 'block';
    };
    reader.readAsDataURL(file);
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
    return items.map((item) => item.replace(/^[0-9]{1,3}\s*[\).\:-]\s*/, '').replace(/^[A-Za-z]\s*[\).\:-]\s*/, '').trim()).filter(Boolean);
  }

  async function createItems(items) {
    const payload = new URLSearchParams();
    payload.set('action', 'bulk_create_from_ocr');
    payload.set('accidente_id', String(accidenteId));
    items.forEach((item) => payload.append('items[]', item));

    const response = await fetch(window.location.pathname + window.location.search, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body: payload
    });
    const json = await response.json();
    if (!response.ok || !json.ok) {
      throw new Error(json.msg || 'No se pudieron crear las diligencias.');
    }
    return json;
  }

  async function processFile(file, label) {
    if (!file || busy) return;
    busy = true;
    imageInput.disabled = true;
    showPreview(file);
    listWrap.style.display = 'none';
    listText.value = '';
    try {
      setStatus(`${label}. Leyendo imagen...`);
      const Tesseract = await ensureTesseractLoaded();
      const result = await Tesseract.recognize(file, 'spa', {
        logger: (message) => {
          if (!message.status) return;
          const progress = typeof message.progress === 'number' ? ` ${Math.round(message.progress * 100)}%` : '';
          setStatus(`${message.status}${progress}`);
        }
      });
      const items = splitDiligencias(result?.data?.text || '');
      if (!items.length) throw new Error('No se detectaron diligencias separadas por numero o letra.');
      listText.value = items.map((item, index) => `${index + 1}. ${item}`).join('\n\n');
      listWrap.style.display = 'block';
      setStatus(`Se detectaron ${items.length} diligencias. Guardando...`);
      const saved = await createItems(items);
      setStatus(saved.msg || 'Diligencias creadas correctamente.', true);
      const target = new URL(window.location.href);
      target.searchParams.set('msg', saved.msg || 'Diligencias creadas correctamente.');
      target.searchParams.set('page', '1');
      setTimeout(() => { window.location.href = target.toString(); }, 900);
    } catch (error) {
      setStatus(error.message || 'No se pudo procesar la imagen.', false);
      busy = false;
      imageInput.disabled = false;
    }
  }

  openBtn.addEventListener('click', openModal);
  cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
  imageInput.addEventListener('change', () => {
    const file = imageInput.files && imageInput.files[0];
    if (file) processFile(file, 'Imagen subida');
  });
  pasteZone.addEventListener('paste', (event) => {
    const file = clipboardImage(event);
    if (!file) return;
    event.preventDefault();
    clipboardFile = file;
    imageInput.value = '';
    processFile(clipboardFile, 'Imagen pegada');
  });
})();
<?php endif; ?>

document.querySelectorAll('.js-estado').forEach(function (select) {
  select.addEventListener('change', function () {
    const current = this;
    const previous = current.dataset.prev || current.value;
    current.disabled = true;

    fetch(window.location.pathname + window.location.search, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body: new URLSearchParams({
        action: 'update_status',
        id: current.dataset.id,
        estado: current.value
      })
    })
      .then(async function (response) {
        const data = await response.json();
        if (!response.ok || !data.ok) {
          throw new Error(data.msg || 'No se pudo actualizar el estado.');
        }
        current.dataset.prev = current.value;
      })
      .catch(function (error) {
        alert(error.message || 'No se pudo actualizar el estado.');
        current.value = previous;
      })
      .finally(function () {
        current.disabled = false;
      });
  });
  select.dataset.prev = select.value;
});
</script>
</body>
</html>
