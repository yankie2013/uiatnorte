<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoDosajeRepository;
use App\Services\DocumentoDosajeService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function g($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function p($k,$d=''){ return isset($_POST[$k])? trim($_POST[$k]) : $d; }

$service = new DocumentoDosajeService(new DocumentoDosajeRepository($pdo));

$embed = g('embed','')!=='' ? 1 : 0;
$return_to = g('return_to','');
$persona_id = (int)g('persona_id', (int)p('persona_id',0));
$persona = $service->persona($persona_id);
$personaMissing = $persona === null;
$cualitativoOptions = $service->cualitativoOptions();

$ok=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  try {
    $service->crear($_POST);
    $ok='Guardado.';
    if($embed){
      echo '<!doctype html><meta charset="utf-8"><script>
        try{ window.parent.postMessage({type:"dosaje.saved"}, "*"); }catch(_){ }
        '.($return_to? 'location.href='.json_encode($return_to).';' : '').'
      </script><body style="background:#0b1020;color:#e8eefc;font:13px Inter">Guardado...</body>';
      exit;
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Dosaje — Nuevo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
  .hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
  .ttl{font-weight:900;font-size:22px;display:flex;gap:8px;align-items:center}
  .card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.01));border:1px solid var(--line);border-radius:var(--r);padding:12px;box-shadow:0 10px 26px rgba(0,0,0,.22)}
  .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:8px}
  .c12{grid-column:span 12}.c6{grid-column:span 6}.c4{grid-column:span 4}
  @media(max-width:940px){.c6,.c4{grid-column:span 12}}
  .msg.ok{background:#0c3f2d;color:#c6ffe3;border:1px solid #167a59;padding:8px 10px;border-radius:10px;margin-bottom:10px}
  .msg.err{background:#3f1012;color:#ffd6d6;border:1px solid #7a1616;padding:8px 10px;border-radius:10px;margin-bottom:10px}
  .msg.warn{background:#3c2a0d;color:#ffe3aa;border:1px solid #8a6a25;padding:8px 10px;border-radius:10px;margin-bottom:10px}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <div class="ttl">➕ Nuevo Dosaje</div>
    <?php if($embed): ?>
      <a class="btn small" href="<?= $return_to? h($return_to) : 'javascript:history.back()' ?>">Cerrar ✕</a>
    <?php else: ?>
      <a class="btn small" href="javascript:history.back()">Volver</a>
    <?php endif; ?>
  </div>

  <?php if($ok): ?><div class="msg ok"><?=h($ok)?></div><?php endif; ?>
  <?php if($err): ?><div class="msg err">Error: <?=h($err)?></div><?php endif; ?>
  <?php if($personaMissing): ?><div class="msg warn">Selecciona una persona desde la pantalla anterior antes de registrar el dosaje.</div><?php endif; ?>

  <form method="post" class="card" autocomplete="off" id="frmDosaje">
    <input type="hidden" name="persona_id" value="<?= (int)$persona_id ?>">
    <!-- este hidden se rellena en vivo; de todas formas el server lo recalcula -->
    <input type="hidden" name="leer_cuantitativo" id="leer_cuantitativo">

    <div class="grid">
      <div class="c12">
        <label>Persona</label>
        <?php if($persona): ?>
          <input type="text" value="<?= h($persona['apellido_paterno'].' '.$persona['apellido_materno'].', '.$persona['nombres'].' · DNI '.$persona['num_doc']) ?>" disabled>
        <?php else: ?>
          <input type="text" value="— Selecciona desde la pantalla anterior —" disabled>
        <?php endif; ?>
      </div>

      <div class="c6">
        <label>Número</label>
        <input name="numero" value="<?= h(p('numero','')) ?>" placeholder="Ej. 0001-004567">
      </div>

      <div class="c6">
        <label>N° Registro</label>
        <input name="numero_registro" value="<?= h(p('numero_registro','')) ?>" placeholder="Ej. B-00987">
      </div>

      <div class="c6">
        <label>Fecha extracción</label>
        <input type="date" name="fecha" value="<?= h(p('fecha','')) ?>">
      </div>

      <div class="c6">
        <label>Hora extracción</label>
        <input type="time" name="hora" value="<?= h(p('hora','')) ?>">
      </div>

      <div class="c6">
        <label>Resultado cualitativo</label>
        <?php
          $opts = $cualitativoOptions;
          $sel=p('resultado_cualitativo','');
        ?>
        <select name="resultado_cualitativo" required>
          <option value="">— Seleccionar —</option>
          <?php foreach($opts as $o): ?>
            <option value="<?=$o?>" <?= $sel===$o?'selected':'' ?>><?=$o?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c6">
        <label>Resultado cuantitativo (g/L)</label>
        <input name="resultado_cuantitativo" id="resultado_cuantitativo" inputmode="decimal" placeholder="Ej. 1.80" value="<?= h(p('resultado_cuantitativo','')) ?>">
      </div>

      <div class="c12">
        <label>Lectura del cuantitativo</label>
        <input id="leer_cuantitativo_view" value="" readonly>
      </div>

      <div class="c12">
        <label>Observaciones</label>
        <textarea name="observaciones" rows="4" placeholder="Notas…"><?= h(p('observaciones','')) ?></textarea>
      </div>
    </div>

    <div class="actions">
      <?php if($embed): ?>
        <a class="btn small" href="<?= $return_to? h($return_to) : 'javascript:history.back()' ?>">Cancelar</a>
      <?php else: ?>
        <a class="btn small" href="javascript:history.back()">Cancelar</a>
      <?php endif; ?>
      <button class="btn primary small" type="submit" <?= $personaMissing ? 'disabled aria-disabled="true"' : '' ?>>Guardar</button>
    </div>
  </form>
</div>

<script>
// ===== Conversión en el cliente (para vista previa) =====
function n0_99_es(n){
  n = Math.max(0, Math.min(99, parseInt(n||0,10)));
  const u=['cero','uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve'];
  const m={10:'diez',11:'once',12:'doce',13:'trece',14:'catorce',15:'quince',
           16:'dieciséis',17:'diecisiete',18:'dieciocho',19:'diecinueve',
           20:'veinte',21:'veintiuno',22:'veintidós',23:'veintitrés',24:'veinticuatro',
           25:'veinticinco',26:'veintiséis',27:'veintisiete',28:'veintiocho',29:'veintinueve',
           30:'treinta',40:'cuarenta',50:'cincuenta',60:'sesenta',70:'setenta',80:'ochenta',90:'noventa',  // 80 corrige abajo
          };
  m[80]='ochenta';
  if(n<10) return u[n];
  if(n<30) return m[n];
  const d=Math.floor(n/10)*10, r=n%10;
  return m[d] + (r? ' y ' + u[r] : '');
}
function toLecturaCuant(val){
  if(!val) return '';
  const f = parseFloat(String(val).replace(',','.'));
  if(!isFinite(f)) return '';
  const f2 = Math.round(f*100)/100;
  const g = Math.floor(f2);
  const c = Math.round((f2 - g)*100);
  const gpal = (g===1? 'Un' : n0_99_es(g).charAt(0).toUpperCase()+n0_99_es(g).slice(1));
  const parte_g = gpal + ' ' + (g===1? 'gramo':'gramos');
  const parte_c = c>0 ? (' ' + n0_99_es(c) + ' centigramos') : '';
  const num = f2.toFixed(2);
  return parte_g + parte_c + ' de alcohol por litro de sangre ('+num+' g/L)';
}
function refreshLectura(){
  const q = document.getElementById('resultado_cuantitativo').value.trim();
  const txt = toLecturaCuant(q);
  document.getElementById('leer_cuantitativo_view').value = txt || '—';
  document.getElementById('leer_cuantitativo').value = txt;
}
document.getElementById('resultado_cuantitativo').addEventListener('input', refreshLectura);
window.addEventListener('DOMContentLoaded', refreshLectura);
</script>
</body>
</html>
