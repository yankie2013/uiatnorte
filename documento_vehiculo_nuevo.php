<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoVehiculoRepository;
use App\Services\DocumentoVehiculoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function p($k,$d=null){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }

function render_docveh_error(string $message, int $status = 400): void {
    http_response_code($status);
    echo "<!doctype html><html lang='es'><meta charset='utf-8'><link rel='stylesheet' href='style_mushu.css'>";
    echo "<body class='p'><div class='alert error'>" . h($message) . "</div></body></html>";
    exit;
}

$service = new DocumentoVehiculoService(new DocumentoVehiculoRepository($pdo));
$invol_id = (int) (g('invol_id', 0) ?: ($_POST['involucrado_vehiculo_id'] ?? 0));
if ($invol_id <= 0) {
    render_docveh_error('Falta el parametro invol_id.');
}

$allowedSections = [
    'propiedad' => 'Tarjeta de Propiedad',
    'soat' => 'SOAT',
    'revision' => 'Revision Tecnica',
    'peritaje' => 'Peritaje',
];
$section = strtolower((string) (g('section', p('section', '')) ?? ''));
if (!isset($allowedSections[$section])) {
    $section = '';
}
$singleCardMode = $section !== '';
$sectionTitle = $singleCardMode ? ' · ' . $allowedSections[$section] : '';

$info = $service->contextoNuevo($invol_id);
if (!$info) {
    render_docveh_error('No se encontro el involucrado de vehiculo #' . $invol_id . '.');
}

$form = $service->emptyForm();
$form['vehiculo_id'] = !empty($info['vehiculo_id']) ? (string) $info['vehiculo_id'] : '';
$error_msg = null;
$guardado = false;
$nuevo_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $service->mergeOld($form, $_POST);
    try {
        $nuevo_id = $service->crear($invol_id, $_POST);
        $guardado = true;
    } catch (Throwable $e) {
        $error_msg = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Documento de Vehiculo - Nuevo<?= h($sectionTitle) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
.p{ padding:18px; }
.topbar{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
.vehicle-chip{
  display:inline-flex; align-items:center; gap:10px;
  font-size:14px; line-height:1.25; font-weight:800;
  padding:10px 14px; border-radius:999px;
  background:var(--bg-2, rgba(255,255,255,.06)); color:var(--fg,#e7eaf0);
  border:1px solid var(--line, rgba(255,255,255,.15));
}
.cards-2col{
  display:grid !important;
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  gap:12px !important;
  align-items:start;
}
.cards-2col > .card{ min-width:0; height:100%; }
.cards-2col.single-card-mode{
  grid-template-columns: 1fr !important;
}
.cards-2col.single-card-mode > .card{
  display:none;
}
.cards-2col.single-card-mode > .card.is-active{
  display:block;
}
@media (max-width: 640px){ .cards-2col{ grid-template-columns: 1fr !important; } }
.grid-2{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.col-span-2{ grid-column: 1 / -1; }
label{ font-weight:600; font-size:12px; color:var(--fg-2); }
input[type="text"],input[type="date"],textarea,select{
  width:100%; padding:10px 12px; border-radius:12px;
  border:1px solid var(--line); background:var(--bg-2); color:var(--fg);
}
textarea{ min-height:96px; resize:vertical; }
.form-actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:12px; }
.btn{ padding:10px 14px; border-radius:12px; border:1px solid transparent; cursor:pointer; font-weight:700; }
.btn.primary{ background:var(--brand); color:#fff; }
.btn.ghost{ background:transparent; border-color:var(--line); color:var(--fg); }
.btn.icon{ width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; }
.alert.success{ background:var(--success-bg); color:var(--success-fg); padding:12px 14px; border-radius:12px; margin-bottom:12px; }
.alert.error{ background:var(--error-bg); color:var(--error-fg); padding:12px 14px; margin-bottom:12px; border-radius:12px; }
#danosWrap{ display:flex; flex-direction:column; gap:8px; }
.danio-row{ display:flex; align-items:center; gap:8px; }
.danio-row input{ flex:1; }
.danio-row .remove{ display:none; }
.danio-row.filled .remove{ display:inline-flex; }
.section-header{ display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px; }
</style>
</head>
<body class="p">

<?php if ($guardado && $nuevo_id): ?>
  <div class="alert success">Documento creado (#<?=h($nuevo_id)?>) para el involucrado vehiculo #<?=h($invol_id)?>.</div>
  <script>
  try { parent.postMessage({type:'docveh:created', id:<?=json_encode($nuevo_id)?>, involucrado_vehiculo_id:<?=json_encode($invol_id)?>}, '*'); } catch (e) {}
  </script>
<?php elseif($error_msg): ?>
  <div class="alert error">Error: <?=h($error_msg)?></div>
<?php endif; ?>

<div class="topbar">
  <div>
    <div class="title">Nuevo - Documento de Vehiculo</div>
    <?php if ($singleCardMode): ?>
      <div class="muted" style="margin-top:4px;"><?= h($allowedSections[$section]) ?></div>
    <?php endif; ?>
    <div class="vehicle-chip">
      <?php if (!empty($info['placa'])): ?>
        <span>Placa: <b><?=h($info['placa'])?></b></span>
        <?php if (!empty($info['color'])): ?><span>- <?=h($info['color'])?></span><?php endif; ?>
        <?php if (!empty($info['anio'])): ?><span>- <?=h($info['anio'])?></span><?php endif; ?>
      <?php else: ?>
        <span><b>Sin vehiculo vinculado</b></span>
      <?php endif; ?>
    </div>
  </div>
  <div><a class="btn ghost" href="javascript:history.back()">Volver</a></div>
</div>

<form method="post" autocomplete="off" id="formDocVeh">
  <input type="hidden" name="involucrado_vehiculo_id" value="<?=h($invol_id)?>">
  <input type="hidden" name="vehiculo_id" value="<?= h($form['vehiculo_id']) ?>">
  <input type="hidden" name="section" value="<?= h($section) ?>">
  <textarea name="danos_peritaje" id="danos_peritaje" hidden><?=h($form['danos_peritaje'])?></textarea>

  <div class="cards-2col<?= $singleCardMode ? ' single-card-mode' : '' ?>">
    <div class="card<?= $section === 'propiedad' ? ' is-active' : '' ?>" data-doc-section="propiedad">
      <div class="card-h">Tarjeta de Propiedad <small>(SUNARP)</small></div>
      <div class="card-b grid-2">
        <div>
          <label>Numero</label>
          <input type="text" name="numero_propiedad" maxlength="50" value="<?= h($form['numero_propiedad']) ?>">
        </div>
        <div>
          <label>Titulo</label>
          <input type="text" name="titulo_propiedad" maxlength="100" value="<?= h($form['titulo_propiedad']) ?>">
        </div>
        <div>
          <label>Partida</label>
          <input type="text" name="partida_propiedad" maxlength="100" value="<?= h($form['partida_propiedad']) ?>">
        </div>
        <div>
          <label>Sede</label>
          <input type="text" name="sede_propiedad" maxlength="100" value="<?= h($form['sede_propiedad']) ?>">
        </div>
      </div>
    </div>

    <div class="card<?= $section === 'soat' ? ' is-active' : '' ?>" data-doc-section="soat">
      <div class="card-h">SOAT</div>
      <div class="card-b grid-2">
        <div>
          <label>Numero</label>
          <input type="text" name="numero_soat" maxlength="50" value="<?= h($form['numero_soat']) ?>">
        </div>
        <div>
          <label>Aseguradora</label>
          <input type="text" name="aseguradora_soat" maxlength="100" value="<?= h($form['aseguradora_soat']) ?>">
        </div>
        <div>
          <label>Vigente desde</label>
          <input type="date" name="vigente_soat" value="<?= h($form['vigente_soat']) ?>">
        </div>
        <div>
          <label>Vence</label>
          <input type="date" name="vencimiento_soat" value="<?= h($form['vencimiento_soat']) ?>">
        </div>
      </div>
    </div>

    <div class="card<?= $section === 'revision' ? ' is-active' : '' ?>" data-doc-section="revision">
      <div class="card-h">Revision Tecnica</div>
      <div class="card-b grid-2">
        <div>
          <label>Numero</label>
          <input type="text" name="numero_revision" maxlength="50" value="<?= h($form['numero_revision']) ?>">
        </div>
        <div>
          <label>Certificadora</label>
          <input type="text" name="certificadora_revision" maxlength="100" value="<?= h($form['certificadora_revision']) ?>">
        </div>
        <div>
          <label>Vigente desde</label>
          <input type="date" name="vigente_revision" value="<?= h($form['vigente_revision']) ?>">
        </div>
        <div>
          <label>Vence</label>
          <input type="date" name="vencimiento_revision" value="<?= h($form['vencimiento_revision']) ?>">
        </div>
      </div>
    </div>

    <div class="card<?= $section === 'peritaje' ? ' is-active' : '' ?>" data-doc-section="peritaje">
      <div class="card-h">Peritaje</div>
      <div class="card-b">
        <div class="grid-2">
          <div>
            <label>Numero</label>
            <input type="text" name="numero_peritaje" maxlength="50" value="<?= h($form['numero_peritaje']) ?>">
          </div>
          <div>
            <label>Fecha</label>
            <input type="date" name="fecha_peritaje" value="<?= h($form['fecha_peritaje']) ?>">
          </div>
          <div class="col-span-2">
            <label>Perito</label>
            <input type="text" name="perito_peritaje" maxlength="100" value="<?= h($form['perito_peritaje']) ?>">
          </div>
          <div>
            <label>Sistema electrico</label>
            <input type="text" name="sistema_electrico_peritaje" maxlength="255" value="<?= h($form['sistema_electrico_peritaje']) ?>">
          </div>
          <div>
            <label>Sistema de frenos</label>
            <input type="text" name="sistema_frenos_peritaje" maxlength="255" value="<?= h($form['sistema_frenos_peritaje']) ?>">
          </div>
          <div>
            <label>Sistema de direccion</label>
            <input type="text" name="sistema_direccion_peritaje" maxlength="255" value="<?= h($form['sistema_direccion_peritaje']) ?>">
          </div>
          <div>
            <label>Sistema de transmision</label>
            <input type="text" name="sistema_transmision_peritaje" maxlength="255" value="<?= h($form['sistema_transmision_peritaje']) ?>">
          </div>
          <div>
            <label>Sistema de suspension</label>
            <input type="text" name="sistema_suspension_peritaje" maxlength="255" value="<?= h($form['sistema_suspension_peritaje']) ?>">
          </div>
          <div>
            <label>Planta motriz</label>
            <input type="text" name="planta_motriz_peritaje" maxlength="255" value="<?= h($form['planta_motriz_peritaje']) ?>">
          </div>
          <div class="col-span-2">
            <label>Otros</label>
            <input type="text" name="otros_peritaje" maxlength="255" value="<?= h($form['otros_peritaje']) ?>">
          </div>
        </div>

        <div class="section-header" style="margin-top:10px;">
          <label style="margin:0;">Danos constatados</label>
          <button class="btn icon" type="button" id="btnAddDanio" title="Agregar dano">+</button>
        </div>
        <div id="danosWrap"></div>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <button type="button" class="btn ghost" onclick="history.back()">Cancelar</button>
    <button class="btn primary" type="submit">Guardar documento</button>
  </div>
</form>

<script>
(function(){
  const wrap = document.getElementById('danosWrap');
  const addBtn = document.getElementById('btnAddDanio');
  const hidden = document.getElementById('danos_peritaje');
  const form = document.getElementById('formDocVeh');

  function rowTemplate(value='') {
    const row = document.createElement('div');
    row.className = 'danio-row' + (value.trim() ? ' filled' : '');

    const inp = document.createElement('input');
    inp.type = 'text';
    inp.placeholder = 'Describa un dano...';
    inp.className = 'danio-input';
    inp.value = value || '';

    const rm = document.createElement('button');
    rm.type = 'button';
    rm.className = 'btn icon remove';
    rm.title = 'Eliminar';
    rm.textContent = 'x';

    rm.addEventListener('click', () => {
      row.remove();
      if (!wrap.querySelector('.danio-row')) addRow('');
      syncHidden();
    });

    inp.addEventListener('input', () => {
      row.classList.toggle('filled', inp.value.trim().length > 0);
      syncHidden();
    });

    row.appendChild(inp);
    row.appendChild(rm);
    return row;
  }

  function addRow(value='') {
    const row = rowTemplate(value);
    wrap.appendChild(row);
    if (!value) row.querySelector('input').focus();
    return row;
  }

  function syncHidden() {
    const vals = Array.from(wrap.querySelectorAll('.danio-input'))
      .map((i) => i.value.trim())
      .filter(Boolean);
    hidden.value = vals.join('\n');
  }

  const pre = hidden.value ? hidden.value.split(/\r?\n/) : [''];
  pre.forEach((v) => addRow(v));

  addBtn.addEventListener('click', () => addRow(''));
  wrap.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab' || event.shiftKey) return;
    if (!event.target || !event.target.classList.contains('danio-input')) return;

    const inputs = Array.from(wrap.querySelectorAll('.danio-input'));
    const lastInput = inputs[inputs.length - 1];
    if (event.target !== lastInput) return;
    if (!event.target.value.trim()) return;

    event.preventDefault();
    syncHidden();
    addRow('');
  });
  form.addEventListener('submit', syncHidden);
})();
</script>

</body>
</html>
