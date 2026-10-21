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
if (empty($_SESSION['tipo_diligencia_delete_token'])) {
    $_SESSION['tipo_diligencia_delete_token'] = bin2hex(random_bytes(32));
}
$success = '';
$tipoNombre = trim((string) ($_POST['tipo_diligencia_nombre'] ?? ''));
$createdId = 0;
$createdIds = [];
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
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
               FROM documentos_recibidos_activos dr
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
        'accion' => $_POST['accion'] ?? '',
        'contenido' => $_POST['contenido'] ?? '',
        'estado' => 'Pendiente',
        'oficio_id' => $_POST['oficio_id'] ?? '',
        'citacion_id' => $_POST['citacion_id'] ?? [],
        'documento_realizado' => $_POST['documento_realizado'] ?? '',
        'documentos_recibidos' => $_POST['documentos_recibidos'] ?? '',
    ];

    try {
        $pdo->beginTransaction();
        if ($tipoNombre !== '') {
            if (mb_strlen($tipoNombre) > 150) {
                throw new InvalidArgumentException('El tipo de diligencia admite hasta 150 caracteres.');
            }
            $findTipo = $pdo->prepare('SELECT id FROM tipo_diligencia WHERE LOWER(nombre) = LOWER(?) LIMIT 1');
            $findTipo->execute([$tipoNombre]);
            $tipoId = $findTipo->fetchColumn();
            if ($tipoId === false) {
                $insertTipo = $pdo->prepare('INSERT INTO tipo_diligencia (nombre, descripcion, creado_en) VALUES (?, NULL, NOW())');
                $insertTipo->execute([$tipoNombre]);
                $tipoId = $pdo->lastInsertId();
            }
            $data['tipo_diligencia_id'] = (int) $tipoId;
        }
        if ($bulkItems !== []) {
            foreach ($bulkItems as $contenido) {
                $itemData = $data;
                $itemData['contenido'] = $contenido;
                $createdIds[] = $service->crear($itemData);
            }
            $success = count($createdIds) === 1
                ? 'Diligencia creada correctamente.'
                : count($createdIds) . ' diligencias creadas correctamente.';
            $createdId = (int) ($createdIds[0] ?? 0);
        } else {
            $createdId = $service->crear($data);
            $createdIds = [$createdId];
            $success = 'Diligencia creada correctamente.';
        }
        $pdo->commit();
        $tipoNombre = '';
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
if (!$embed) {
    @include __DIR__ . '/sidebar.php';
}
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
body.is-embed { padding: 14px; }
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
.tipo-picker{position:relative}
.tipo-picker-field{display:flex;align-items:center;position:relative}
.tipo-picker-field .input{padding-right:42px}
.tipo-toggle{position:absolute;right:6px;border:0;background:transparent;color:var(--text);padding:8px;cursor:pointer}
.tipo-options{position:absolute;top:100%;left:0;right:0;z-index:30;max-height:280px;overflow:auto;margin-top:4px;padding:5px;background:var(--card);border:1px solid var(--border);border-radius:10px;box-shadow:0 12px 28px rgba(0,0,0,.16)}
.tipo-options[hidden],.tipo-option[hidden],.tipo-empty[hidden]{display:none}
.tipo-option{display:flex;align-items:center;border-radius:6px}
.tipo-option:hover,.tipo-option:focus-within{background:var(--bg)}
.tipo-select{flex:1;min-width:0;text-align:left;padding:10px;border:0;background:transparent;color:var(--text);font:inherit;cursor:pointer}
.tipo-select span,.tipo-select small{display:block;overflow-wrap:anywhere}
.tipo-select small{color:var(--muted);margin-top:3px}
.tipo-delete{flex:none;margin:0 5px;padding:5px 10px;border:0;border-radius:6px;background:transparent;color:#dc2626;font-size:22px;cursor:pointer}
.tipo-delete:hover{background:rgba(220,38,38,.1)}
.tipo-empty{padding:10px}
</style>
</head>
<body<?= $embed ? ' class="is-embed"' : '' ?>>
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
      <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">

      <label for="accion">Acción</label>
      <select id="accion" name="accion" class="input">
        <option value="">Selecciona una acción</option>
        <?php foreach (['Solicitar', 'Remitir', 'Citar', 'Realizar'] as $accion): ?>
          <option value="<?= h($accion) ?>" <?= ($data['accion'] ?? '') === $accion ? 'selected' : '' ?>><?= h($accion) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="tipo_diligencia_nombre">Tipo de diligencia</label>
      <div class="tipo-picker" id="tipo-picker">
        <div class="tipo-picker-field">
          <input id="tipo_diligencia_nombre" name="tipo_diligencia_nombre" class="input" maxlength="150" value="<?= h($tipoNombre) ?>" placeholder="Escribe o selecciona un tipo de diligencia" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="tipos-diligencia" aria-describedby="tipo-diligencia-help">
          <button type="button" class="tipo-toggle" aria-label="Mostrar tipos de diligencia" aria-controls="tipos-diligencia">▾</button>
        </div>
        <div id="tipos-diligencia" class="tipo-options" role="listbox" aria-label="Tipos de diligencia" hidden>
          <?php foreach ($ctx['tipos'] as $tipo): ?>
            <div class="tipo-option" data-id="<?= (int) $tipo['id'] ?>" data-name="<?= h($tipo['nombre']) ?>">
              <button type="button" class="tipo-select" role="option" aria-selected="false"><span><?= h($tipo['nombre']) ?></span><?php if (!empty($tipo['descripcion'])): ?><small><?= h($tipo['descripcion']) ?></small><?php endif; ?></button>
              <button type="button" class="tipo-delete" aria-label="Eliminar tipo <?= h($tipo['nombre']) ?>" title="Eliminar tipo guardado">×</button>
            </div>
          <?php endforeach; ?>
          <div class="help tipo-empty" hidden>No hay coincidencias. Puedes escribir un tipo nuevo.</div>
        </div>
      </div>
      <div id="tipo-diligencia-status" class="help" role="status"></div>
      <div class="help" id="tipo-diligencia-help">Selecciona una sugerencia o escribe un tipo nuevo. Se guardará para reutilizarlo al crear la diligencia.</div>

      <label for="contenido">Contenido / observaciones</label>
      <textarea id="contenido" name="contenido" class="input"><?= h($data['contenido']) ?></textarea>
      <div class="actions">
        <button type="submit" class="btn primary">Guardar</button>
        <?php if ($embed): ?>
          <button type="button" class="btn ghost" onclick="window.parent.postMessage({type:'diligencia.close'}, window.location.origin)">Cancelar</button>
        <?php else: ?>
        <?php if ($returnTo !== ''): ?>
          <a class="btn ghost" href="<?= h($returnTo) ?>">Volver a documentos</a>
        <?php endif; ?>
        <a class="btn ghost" href="diligenciapendiente_listar.php<?= $accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : '' ?>">Volver al listado</a>
        <?php if ($accidenteId > 0): ?>
          <a class="btn ghost" href="Dato_General_accidente.php?accidente_id=<?= h($accidenteId) ?>">Volver al accidente</a>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($embed && $success !== ''): ?>
<script>window.parent.postMessage({type: 'diligencia.saved'}, window.location.origin);</script>
<?php endif; ?>
<script>
(function () {
  const input = document.getElementById('tipo_diligencia_nombre');
  const picker = document.getElementById('tipo-picker');
  const list = document.getElementById('tipos-diligencia');
  const status = document.getElementById('tipo-diligencia-status');
  const normalize = value => value.trim().toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const rows = () => Array.from(list.querySelectorAll('.tipo-option'));
  function open() {
    const query = normalize(input.value);
    rows().forEach(row => {
      row.hidden = !normalize(row.textContent).includes(query);
      row.querySelector('.tipo-select').setAttribute('aria-selected', String(normalize(row.dataset.name) === query));
    });
    list.querySelector('.tipo-empty').hidden = rows().some(row => !row.hidden);
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  }
  function close() {
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
  }
  input.addEventListener('focus', open);
  input.addEventListener('input', () => { status.textContent = ''; open(); });
  picker.querySelector('.tipo-toggle').addEventListener('click', () => {
    if (list.hidden) { input.focus(); open(); } else close();
  });
  document.addEventListener('pointerdown', event => { if (!picker.contains(event.target)) close(); });
  picker.addEventListener('focusout', event => { if (!picker.contains(event.relatedTarget)) close(); });
  picker.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !list.hidden) { event.preventDefault(); event.stopPropagation(); close(); input.focus(); close(); }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (list.hidden) open();
      const options = rows().filter(row => !row.hidden).map(row => row.querySelector('.tipo-select'));
      if (!options.length) return;
      const index = options.indexOf(document.activeElement);
      options[(index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length].focus();
    }
  });
  list.addEventListener('click', async event => {
    const button = event.target.closest('button');
    const row = button?.closest('.tipo-option');
    if (!row || button.disabled) return;
    if (button.classList.contains('tipo-select')) {
      input.value = row.dataset.name;
      input.focus();
      close();
      return;
    }
    button.disabled = true;
    status.textContent = 'Eliminando tipo guardado…';
    try {
      const response = await fetch('tipo_diligencia_eliminar_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: row.dataset.id, token: <?= json_encode($_SESSION['tipo_diligencia_delete_token']) ?>})
      });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo eliminar el tipo.');
      if (normalize(input.value) === normalize(row.dataset.name)) input.value = '';
      row.remove();
      status.textContent = 'Tipo eliminado de las sugerencias.';
      input.focus();
      open();
    } catch (error) {
      status.textContent = error.message || 'No se pudo eliminar el tipo.';
    } finally {
      button.disabled = false;
    }
  });
})();

</script>
</body>
</html>
