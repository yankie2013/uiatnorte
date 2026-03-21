<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoDosajeRepository;
use App\Services\DocumentoDosajeService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function g($k,$d=''){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function p($k,$d=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }

$service = new DocumentoDosajeService(new DocumentoDosajeRepository($pdo));
$id = (int) g('id', (int) p('id', 0));
$embed = g('embed', '') !== '' ? 1 : 0;
$return_to = g('return_to', '');
if ($id <= 0) {
    http_response_code(400);
    exit('ID invalido');
}

$detalle = $service->detalle($id);
if (!$detalle) {
    http_response_code(404);
    exit('Registro no encontrado');
}

$row = $detalle['row'];
$fecha_value = $detalle['fecha'];
$hora_value = $detalle['hora'];
$cualitativoOptions = $service->cualitativoOptions();
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('do', '') === 'save') {
    try {
        $service->actualizar($id, $_POST);
        $ok = 'Guardado.';
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>';
            echo 'try{ window.parent.postMessage({type:"dosaje.saved"}, "*"); }catch(_){ }';
            if ($return_to) {
                echo 'location.href=' . json_encode($return_to) . ';';
            }
            echo '</script><body style="background:#0b1020;color:#e8eefc;font:13px Inter">Guardado...</body>';
            exit;
        }
        $detalle = $service->detalle($id);
        $row = $detalle['row'];
        $fecha_value = $detalle['fecha'];
        $hora_value = $detalle['hora'];
    } catch (Throwable $e) {
        $err = $e->getMessage();
        $row = array_merge($row, [
            'numero' => p('numero', $row['numero'] ?? ''),
            'numero_registro' => p('numero_registro', $row['numero_registro'] ?? ''),
            'resultado_cualitativo' => p('resultado_cualitativo', $row['resultado_cualitativo'] ?? ''),
            'resultado_cuantitativo' => p('resultado_cuantitativo', $row['resultado_cuantitativo'] ?? ''),
            'observaciones' => p('observaciones', $row['observaciones'] ?? ''),
            'leer_cuantitativo' => $service->cuantitativoTexto(p('resultado_cuantitativo', '')),
        ]);
        $fecha_value = p('fecha', $fecha_value);
        $hora_value = p('hora', $hora_value);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar Dosaje</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
  .hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
  .ttl{font-weight:900;font-size:22px;display:flex;gap:8px;align-items:center}
  .card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.01));border:1px solid var(--line);border-radius:var(--r);padding:12px;box-shadow:0 10px 26px rgba(0,0,0,.22)}
  .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:8px}
  .c12{grid-column:span 12}.c6{grid-column:span 6}
  @media(max-width:940px){.c6{grid-column:span 12}}
  .msg.ok{background:#0c3f2d;color:#c6ffe3;border:1px solid #167a59;padding:8px 10px;border-radius:10px;margin-bottom:10px}
  .msg.err{background:#3f1012;color:#ffd6d6;border:1px solid #7a1616;padding:8px 10px;border-radius:10px;margin-bottom:10px}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <div class="ttl">Editar Dosaje</div>
    <a class="btn small" href="<?= $return_to ? h($return_to) : 'javascript:history.back()' ?>">Cerrar</a>
  </div>

  <?php if ($ok): ?><div class="msg ok"><?= h($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="msg err">Error: <?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <div class="grid">
      <div class="c12">
        <label>Persona</label>
        <?php $pn = trim(($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ', ' . ($row['nombres'] ?? '')); ?>
        <input value="<?= h($pn ?: '-') ?> - DNI <?= h($row['num_doc'] ?? '-') ?>" disabled>
      </div>
    </div>

    <form method="post" class="grid" autocomplete="off">
      <input type="hidden" name="do" value="save">
      <input type="hidden" name="id" value="<?= (int) $id ?>">

      <div class="c6">
        <label>Numero</label>
        <input name="numero" value="<?= h(p('numero', $row['numero'] ?? '')) ?>">
      </div>

      <div class="c6">
        <label>N&ordm; Registro</label>
        <input name="numero_registro" value="<?= h(p('numero_registro', $row['numero_registro'] ?? '')) ?>">
      </div>

      <div class="c6">
        <label>Fecha extraccion</label>
        <input type="date" name="fecha" value="<?= h(p('fecha', $fecha_value)) ?>">
      </div>

      <div class="c6">
        <label>Hora extraccion</label>
        <input type="time" name="hora" value="<?= h(p('hora', $hora_value)) ?>">
      </div>

      <div class="c6">
        <label>Resultado cualitativo</label>
        <?php $sel = p('resultado_cualitativo', $row['resultado_cualitativo'] ?? ''); ?>
        <select name="resultado_cualitativo" required>
          <option value="">Selecciona</option>
          <?php foreach ($cualitativoOptions as $opt): ?>
            <option value="<?= h($opt) ?>" <?= $sel === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c6">
        <label>Resultado cuantitativo (g/L)</label>
        <input name="resultado_cuantitativo" id="resultado_cuantitativo" value="<?= h(p('resultado_cuantitativo', $row['resultado_cuantitativo'] ?? '')) ?>" inputmode="decimal" placeholder="Ej. 1.80">
      </div>

      <div class="c12">
        <label>Lectura del cuantitativo</label>
        <input id="leer_cuantitativo_view" value="<?= h($row['leer_cuantitativo'] ?? '') ?>" readonly>
      </div>

      <div class="c12">
        <label>Observaciones</label>
        <textarea name="observaciones" rows="4"><?= h(p('observaciones', $row['observaciones'] ?? '')) ?></textarea>
      </div>

      <div class="c12 actions" style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
        <a class="btn" href="<?= $return_to ? h($return_to) : 'javascript:history.back()' ?>">Cancelar</a>
        <button class="btn primary" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function n0_99_es(n){
  n = Math.max(0, Math.min(99, parseInt(n || 0, 10)));
  const u=['cero','uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve'];
  const m={10:'diez',11:'once',12:'doce',13:'trece',14:'catorce',15:'quince',16:'dieciseis',17:'diecisiete',18:'dieciocho',19:'diecinueve',20:'veinte',21:'veintiuno',22:'veintidos',23:'veintitres',24:'veinticuatro',25:'veinticinco',26:'veintiseis',27:'veintisiete',28:'veintiocho',29:'veintinueve',30:'treinta',40:'cuarenta',50:'cincuenta',60:'sesenta',70:'setenta',80:'ochenta',90:'noventa'};
  if(n < 10) return u[n];
  if(n < 30) return m[n];
  const d = Math.floor(n/10)*10, r = n%10;
  return m[d] + (r ? ' y ' + u[r] : '');
}
function toLecturaCuant(val){
  if(!val) return '';
  const f = parseFloat(String(val).replace(',', '.'));
  if(!isFinite(f)) return '';
  const f2 = Math.round(f*100)/100;
  const g = Math.floor(f2);
  const c = Math.round((f2 - g)*100);
  const gp = (g===1 ? 'Un' : n0_99_es(g).charAt(0).toUpperCase()+n0_99_es(g).slice(1));
  const parteG = gp + ' ' + (g===1 ? 'gramo' : 'gramos');
  const parteC = c>0 ? (' ' + n0_99_es(c) + ' centigramos') : '';
  return parteG + parteC + ' de alcohol por litro de sangre (' + f2.toFixed(2) + ' g/L)';
}
function refreshLectura(){
  const v = document.getElementById('resultado_cuantitativo').value.trim();
  document.getElementById('leer_cuantitativo_view').value = toLecturaCuant(v) || '-';
}
document.getElementById('resultado_cuantitativo').addEventListener('input', refreshLectura);
window.addEventListener('DOMContentLoaded', refreshLectura);
</script>
</body>
</html>
