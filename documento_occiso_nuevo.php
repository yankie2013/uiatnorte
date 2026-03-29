<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoOccisoRepository;
use App\Services\DocumentoOccisoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function append_query(string $url, array $params): string {
  $frag = '';
  if (str_contains($url, '#')) {
    [$url, $frag] = explode('#', $url, 2);
    $frag = '#' . $frag;
  }
  $query = http_build_query(array_filter($params, static fn($v) => $v !== null && $v !== ''));
  if ($query === '') return $url . $frag;
  return $url . (str_contains($url, '?') ? '&' : '?') . $query . $frag;
}

$service = new DocumentoOccisoService(new DocumentoOccisoRepository($pdo));
$persona_id_get = (int) g('persona_id', 0);
$accidente_id_get = (int) g('accidente_id', 0);
$embed = (int) g('embed', 0);
$return_to = g('return_to', '');
$personas = $service->personaOptions();
$accidentes = $service->accidenteOptions();
$old = [
    'persona_id' => $persona_id_get,
    'accidente_id' => $accidente_id_get,
];
$ok = false;
$err = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  try {
    $newId = $service->crear($_POST);
    $personaId = (int) ($_POST['persona_id'] ?? $persona_id_get);
    $accidenteId = (int) ($_POST['accidente_id'] ?? $accidente_id_get);
    $backTo = $return_to !== ''
      ? $return_to
      : append_query('documento_occiso_list.php', [
          'persona_id' => $personaId ?: null,
          'accidente_id' => $accidenteId ?: null,
          'embed' => $embed ?: null,
        ]);
    header('Location: ' . append_query('documento_occiso_ver.php', [
      'id' => $newId,
      'persona_id' => $personaId ?: null,
      'accidente_id' => $accidenteId ?: null,
      'embed' => $embed ?: null,
      'return_to' => $backTo,
    ]));
    exit;
  } catch (Throwable $e) {
    $err = $e->getMessage();
    $old = $service->mergeOld($old, $_POST);
    $persona_id_get = (int)($old['persona_id'] ?? $persona_id_get);
    $accidente_id_get = (int)($old['accidente_id'] ?? $accidente_id_get);
  }
}
?><!doctype html>
<html lang="es" data-theme="auto">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Documento Occiso - Nuevo</title>
<style>
/* =========== Theming (Claro / Oscuro) =========== */
:root{
  --bg:#f6f7fb; --card:#fff; --text:#111; --muted:#5b6471;
  --bd:#e6e8ee; --primary:#2563eb; --primary-ink:#fff; --shadow:0 10px 24px rgba(17,24,39,.08);
}
@media (prefers-color-scheme: dark){
  :root{ --bg:#0f1115; --card:#141821; --text:#eef2ff; --muted:#a3adc2;
         --bd:#2a3040; --primary:#5b8cff; --primary-ink:#0b1020; --shadow:0 12px 28px rgba(0,0,0,.45); }
}
:root[data-theme="light"]{ --bg:#f6f7fb; --card:#fff; --text:#111; --muted:#5b6471; --bd:#e6e8ee; --primary:#2563eb; --primary-ink:#fff; --shadow:0 10px 24px rgba(17,24,39,.08); }
:root[data-theme="dark"] { --bg:#0f1115; --card:#141821; --text:#eef2ff; --muted:#a3adc2; --bd:#2a3040; --primary:#5b8cff; --primary-ink:#0b1020; --shadow:0 12px 28px rgba(0,0,0,.45); }

/* Layout */
*{box-sizing:border-box}
body{margin:0; font:13px/1.35 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; background:var(--bg); color:var(--text);}
.wrap{max-width:1200px; margin:14px auto; padding:0 10px;}
.topbar{display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:12px;}
h2{margin:0; font-size:18px; font-weight:800; letter-spacing:.2px}
.theme{display:flex; align-items:center; gap:8px;}
.theme button{border:1px solid var(--bd); background:var(--card); color:var(--text); border-radius:10px; padding:6px 10px; font-weight:700; cursor:pointer; box-shadow:var(--shadow);}

/* Grid 2 col */
.cols{display:grid; grid-template-columns:1fr 1fr; gap:16px;}
@media (max-width: 900px){ .cols{grid-template-columns:1fr;} }

/* Card */
.card{background:var(--card); border:1px solid var(--bd); border-radius:16px; box-shadow:var(--shadow); overflow:hidden; display:flex; flex-direction:column; min-width:0;}
.card-h{padding:10px 14px; font-size:12.5px; font-weight:800; letter-spacing:.3px; text-transform:uppercase; color:var(--muted); border-bottom:1px solid var(--bd); background:linear-gradient(180deg, rgba(255,255,255,.02), transparent);}
.card-b{ padding:14px; display:grid; gap:12px; }

/* Grids internos */
.g2{display:grid; grid-template-columns:1fr 1fr; gap:12px;}
.g3{display:grid; grid-template-columns:repeat(3,1fr); gap:12px;}
@media (max-width: 640px){ .g2,.g3{grid-template-columns:1fr;} }

/* Inputs compactos */
label{display:block; font-size:11px; font-weight:700; color:var(--muted); margin:2px 1px 4px;}
input,select,textarea{width:100%; border:1px solid var(--bd); background:transparent; color:var(--text); border-radius:8px; padding:6px 8px; font-size:12px; outline:none; transition: box-shadow .15s ease, border-color .15s ease;}
textarea{ min-height:80px; resize:vertical; line-height:1.35; }
input:focus,select:focus,textarea:focus{ border-color:var(--primary); box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 25%, transparent); }

/* Alertas y acciones */
.alert{padding:10px 12px; border-radius:12px; margin-bottom:12px; font-size:12.5px; border:1px solid var(--bd);}
.alert.ok{ background: color-mix(in srgb, var(--primary) 14%, transparent); border-color: color-mix(in srgb, var(--primary) 35%, var(--bd)); }
.alert.err{ background: color-mix(in srgb, crimson 12%, transparent); border-color: color-mix(in srgb, crimson 40%, var(--bd)); }
.actions{display:flex; justify-content:flex-end; gap:10px; margin-top:12px;}
.btn{ border:1px solid var(--bd); background:var(--card); color:var(--text); padding:10px 14px; border-radius:12px; font-weight:800; cursor:pointer; }
.btn.primary{ background:var(--primary); color:var(--primary-ink); border-color:transparent; }
.btn:hover{ filter:brightness(1.03); }

/* Multi-input compacto (+ en ultima fila, x en llenos) */
.multi { display:grid; gap:8px; }
.multi .rows { display:grid; gap:8px; }
.multi .row { display:grid; grid-template-columns:1fr auto; gap:8px; }
.multi .row input{ width:100%; border:1px solid var(--bd); background:transparent; color:var(--text); border-radius:8px; padding:6px 8px; font-size:12px; }
.multi .ctrls{ display:flex; gap:6px; align-items:center; }
.multi .btn-mini{ border:1px solid var(--bd); background:var(--card); color:var(--text); border-radius:8px; padding:4px 10px; font-weight:800; cursor:pointer; line-height:1; }
.multi .btn-mini.add{ border-style:dashed; }
.multi .btn-mini.remove{ border-style:solid; }
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <h2>Documento Occiso - Nuevo</h2>
    <div class="theme">
      <small style="opacity:.7">Tema</small>
      <button type="button" id="tLight">Claro</button>
      <button type="button" id="tAuto">Auto</button>
      <button type="button" id="tDark">Oscuro</button>
    </div>
  </div>

  <?php if($ok): ?>
    <div class="alert ok">Guardado correctamente.</div>
    <script>try{window.parent&&window.parent.postMessage({type:'occiso.saved'},'*');}catch(e){}</script>
  <?php elseif($err): ?>
    <div class="alert err"><b>Error:</b> <?=h($err)?></div>
  <?php endif; ?>

 <form method="post" autocomplete="off">

  <!-- Relaciones arriba, ancho completo -->
  <div class="card" style="margin-bottom:16px;">
    <div class="card-h">Relaciones</div>
    <div class="card-b">
      <div class="g2">
        <div>
          <label>Persona <span style="opacity:.6">*</span></label>
          <select name="persona_id" id="persona_id" <?= $persona_id_get ? 'disabled' : '' ?> required>
            <option value="">&mdash; Selecciona &mdash;</option>
            <?php foreach($personas as $p): ?>
              <option value="<?=$p['id']?>" <?= ($persona_id_get && (int)$p['id']===$persona_id_get)?'selected':'' ?>>
                <?=h($p['etiqueta'])?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if($persona_id_get): ?>
            <input type="hidden" name="persona_id" value="<?=$persona_id_get?>">
          <?php endif; ?>
        </div>
        <div>
          <label>Accidente <span style="opacity:.6">*</span></label>
          <select name="accidente_id" id="accidente_id" <?= $accidente_id_get ? 'disabled' : '' ?> required>
            <option value="">&mdash; Selecciona &mdash;</option>
            <?php foreach($accidentes as $a): ?>
              <option value="<?=$a['id']?>" <?= ($accidente_id_get && (int)$a['id']===$accidente_id_get)?'selected':'' ?>>
                <?=h($a['etiqueta']?:'Accidente #'.$a['id'])?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if($accidente_id_get): ?>
            <input type="hidden" name="accidente_id" value="<?=$accidente_id_get?>">
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Resto en dos columnas -->
  <div class="cols">
    <!-- Columna izquierda: Levantamiento + Pericial -->
    <div class="col">
      <div class="card">
        <div class="card-h">Levantamiento</div>
        <div class="card-b">
          <div class="g3">
            <div><label>Fecha</label><input type="date" name="fecha_levantamiento"></div>
            <div><label>Hora</label><input type="time" name="hora_levantamiento"></div>
            <div><label>Lugar</label><input type="text" name="lugar_levantamiento" maxlength="255"></div>
          </div>
          <div class="g2">
            <div><label>Posici&oacute;n del cuerpo</label><input type="text" name="posicion_cuerpo_levantamiento" maxlength="255"></div>
            <div><label>Presuntivo</label><input type="text" name="presuntivo_levantamiento" maxlength="255"></div>
          </div>
          <!-- Legista / CMP -->
          <div class="g2">
            <div><label>Legista</label><input type="text" name="legista_levantamiento" maxlength="255"></div>
            <div><label>CMP del legista</label><input type="text" name="cmp_legista" maxlength="20"></div>
          </div>
          <!-- Observaciones y Lesiones -->
          <div>
            <label>Observaciones</label>
            <div class="multi" data-name="observaciones_levantamiento">
              <div class="rows"></div>
              <textarea name="observaciones_levantamiento" hidden></textarea>
            </div>
          </div>
          <div>
            <label>Lesiones</label>
            <div class="multi" data-name="lesiones_levantamiento">
              <div class="rows"></div>
              <textarea name="lesiones_levantamiento" hidden></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-h">Pericial</div>
        <div class="card-b">
          <div class="g3">
            <div><label>N&ordm; pericial</label><input type="text" name="numero_pericial" maxlength="50"></div>
            <div><label>Fecha</label><input type="date" name="fecha_pericial"></div>
            <div><label>Hora</label><input type="time" name="hora_pericial"></div>
          </div>
          <div>
            <label>Observaciones</label>
            <div class="multi" data-name="observaciones_pericial">
              <div class="rows"></div>
              <textarea name="observaciones_pericial" hidden></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Columna derecha: Protocolo + Epicrisis -->
    <div class="col">
      <div class="card">
        <div class="card-h">Protocolo de necropsia</div>
        <div class="card-b">
          <div class="g3">
            <div><label>N&ordm; protocolo</label><input type="text" name="numero_protocolo" maxlength="50"></div>
            <div><label>Fecha</label><input type="date" name="fecha_protocolo"></div>
            <div><label>Hora</label><input type="time" name="hora_protocolo"></div>
          </div>
          <div class="g3">
            <div><label>Presuntivo</label><input type="text" name="presuntivo_protocolo" maxlength="255"></div>
            <div><label>Dosaje</label><input type="text" name="dosaje_protocolo" maxlength="255"></div>
            <div><label>Toxicol&oacute;gico</label><input type="text" name="toxicologico_protocolo" maxlength="255"></div>
          </div>
          <div>
            <label>Lesiones</label>
            <div class="multi" data-name="lesiones_protocolo">
              <div class="rows"></div>
              <textarea name="lesiones_protocolo" hidden></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-h">Epicrisis</div>
        <div class="card-b">
          <div class="g3">
            <div><label>Nosocomio</label><input type="text" name="nosocomio_epicrisis" maxlength="255"></div>
            <div><label>N&ordm; Historia cl&iacute;nica</label><input type="text" name="numero_historia_epicrisis" maxlength="50"></div>
            <div><label>Hora de alta</label><input type="time" name="hora_alta_epicrisis"></div>
          </div>
          <div>
            <label>Tratamiento</label>
            <div class="multi" data-name="tratamiento_epicrisis">
              <div class="rows"></div>
              <textarea name="tratamiento_epicrisis" hidden></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="actions">
    <button type="submit" class="btn primary">Guardar</button>
    <button type="button" class="btn" onclick="cerrar()">Cerrar</button>
  </div>
</form>
</div>

<script>
const OLD_FORM = <?= json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

/* ===== Theme toggle (persistente) ===== */
const root = document.documentElement;
function applyTheme(m){ root.setAttribute('data-theme', m); localStorage.setItem('occiso-theme', m); }
document.getElementById('tLight').onclick=()=>applyTheme('light');
document.getElementById('tDark').onclick =()=>applyTheme('dark');
document.getElementById('tAuto').onclick =()=>applyTheme('auto');
(function(){ const saved=localStorage.getItem('occiso-theme'); if(saved) root.setAttribute('data-theme', saved); })();

/* ===== MultiInput ===== */
(function(){
  Object.entries(OLD_FORM || {}).forEach(([name, value]) => {
    if (value === null || value === '' || typeof value === 'undefined') return;
    const field = document.querySelector(`[name="${name}"]`);
    if (!field || field.disabled) return;
    field.value = String(value);
  });
})();

(function(){
  function makeRow(value=''){
    const row = document.createElement('div');
    row.className = 'row';
    row.innerHTML = `
      <input type="text" placeholder="Escribe aqui..." value="${value.replace(/"/g,'&quot;')}">
      <div class="ctrls"></div>
    `;
    return row;
  }

  function renderControls(container){
    const rows = Array.from(container.querySelectorAll('.row'));
    const lastIndex = rows.length - 1;

    rows.forEach((row, idx)=>{
      const input = row.querySelector('input');
      const ctrls = row.querySelector('.ctrls');
      ctrls.innerHTML = '';

      const val = input.value.trim();
      const isLast = idx === lastIndex;

      if (val !== '') {
        const rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'btn-mini remove';
        rm.textContent = 'x';
        rm.title = 'Quitar';
        rm.onclick = ()=>{
          row.remove();
          ensureAtLeastOne(container);
          renderControls(container);
          syncHidden(container);
        };
        ctrls.appendChild(rm);
      }

      if (isLast) {
        const add = document.createElement('button');
        add.type = 'button';
        add.className = 'btn-mini add';
        add.textContent = '+';
        add.title = 'Agregar';
        add.onclick = ()=>{
          const rowsBox = container.querySelector('.rows');
          rowsBox.appendChild(makeRow(''));
          renderControls(container);
        };
        ctrls.appendChild(add);
      }

      input.oninput = ()=>{ renderControls(container); };
    });
  }

  function ensureAtLeastOne(container){
    const rows = container.querySelector('.rows');
    if (!rows.querySelector('.row')) rows.appendChild(makeRow(''));
  }

  function syncHidden(container){
    const hidden = container.querySelector('textarea[hidden]');
    const values = Array.from(container.querySelectorAll('.row input'))
      .map(i=>i.value.trim()).filter(Boolean);
    hidden.value = values.join('\n');
  }

  document.querySelectorAll('.multi').forEach(m=>{
    const rowsBox = m.querySelector('.rows');
    const hidden  = m.querySelector('textarea[hidden]');

    const initial = (hidden.value || '').split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
    if (initial.length) initial.forEach(v=>rowsBox.appendChild(makeRow(v)));
    else rowsBox.appendChild(makeRow(''));

    renderControls(m);
    m.closest('form').addEventListener('submit', ()=>{ syncHidden(m); });
  });
})();

/* ===== PreselecciÃƒÂ³n desde GET (fallback por si el navegador no lo marca) ===== */
(function(){
  const qs = new URLSearchParams(location.search);
  const pid = qs.get('persona_id');
  const aid = qs.get('accidente_id');

  const sp = document.getElementById('persona_id');
  if (sp && pid && !sp.disabled) sp.value = pid;

  const sa = document.getElementById('accidente_id');
  if (sa && aid && !sa.disabled) sa.value = aid;
})();

/* ===== Cerrar embebido ===== */
function cerrar(){
  try{ window.parent && window.parent.postMessage({type:'occiso.close'}, '*'); }catch(e){}
  if(history.length>1) history.back();
}
</script>
</body>
</html>
