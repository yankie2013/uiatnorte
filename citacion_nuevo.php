<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require __DIR__ . '/google_calendar.php';

use App\Repositories\CitacionRepository;
use App\Services\CitacionService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new CitacionService(new CitacionRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta accidente_id');
}

$ctx = $service->formContext($accidenteId);
$data = $service->defaultData();
$error = '';
$success = '';
$newId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'persona' => $_POST['persona'] ?? '',
        'en_calidad' => $_POST['en_calidad'] ?? '',
        'tipo_diligencia' => $_POST['tipo_diligencia'] ?? '',
        'fecha' => $_POST['fecha'] ?? '',
        'hora' => $_POST['hora'] ?? '',
        'lugar' => $_POST['lugar'] ?? '',
        'motivo' => $_POST['motivo'] ?? '',
        'orden_citacion' => $_POST['orden_citacion'] ?? 1,
        'oficio_id' => $_POST['oficio_id'] ?? '',
    ];

    try {
        $created = $service->create($accidenteId, $data);
        $newId = (int) $created['id'];
        $success = 'Citación registrada correctamente.';
        try {
            $linkEvento = gc_crear_evento_citacion($service->calendarPayload($accidenteId, $newId, $created));
            if ($linkEvento) {
                $success .= ' Evento creado en Google Calendar.';
            }
        } catch (Throwable $calendarError) {
            $success .= ' (No se pudo crear el evento en Google Calendar: ' . $calendarError->getMessage() . ')';
        }
        $data = $service->defaultData();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$hoy = date('Y-m-d');
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nueva Citación</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--ok:#166534;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6;--ok:#bbf7d0;--danger:#fecaca}}
body{background:var(--page);color:var(--text)}.wrap{max-width:1020px;margin:24px auto;padding:0 12px}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:10px}.c12{grid-column:span 12}.c6{grid-column:span 6}.c3{grid-column:span 3}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--primary);border:1px solid rgba(29,78,216,.18);font-size:11px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin:10px 0}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin:10px 0}.small{color:var(--muted);font-size:12px}.hstack{display:flex;align-items:center;gap:8px}label{font-weight:700;color:var(--muted);font-size:13px}input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text);box-sizing:border-box}textarea{min-height:110px;resize:vertical}.actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:16px}@media(max-width:820px){.c6,.c3{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <h1 style="margin:0 0 10px">Citación <span class="badge">Nueva</span></h1>
  <div class="small">Accidente ID: <?= (int) $accidenteId ?></div>

  <div class="actions">
    <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= (int) $accidenteId ?>">← Volver</a>
    <a class="btn" href="citacion_listar.php?accidente_id=<?= (int) $accidenteId ?>">Ver citaciones</a>
    <button class="btn primary" type="submit" form="frmCitacion">Guardar citación</button>
  </div>

  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="ok"><?= h($success) ?><?php if ($newId): ?> - <a class="btn" href="citacion_diligencia.php?citacion_id=<?= (int) $newId ?>" target="_blank" rel="noopener">Descargar DOCX</a><?php endif; ?></div><?php endif; ?>

  <form method="post" class="card" id="frmCitacion">
    <div class="grid">
      <div class="c12">
        <label>Persona a citar*</label>
        <select name="persona" required>
          <option value="">Selecciona</option>
          <?php foreach ($ctx['personas'] as $persona): ?>
            <?php
              $val = $persona['fuente'] . ':' . $persona['fuente_id'];
              $doc = trim(($persona['tipo_doc'] ?? '') . ' ' . ($persona['num_doc'] ?? ''));
              $txt = '[' . $persona['fuente'] . '] ' . trim((string) ($persona['nombre'] ?? '')) . ' - ' . ($persona['relacion'] ?? '');
              if ($doc !== '') $txt .= ' · ' . $doc;
              if (!empty($persona['extra'])) $txt .= ' · ' . $persona['extra'];
            ?>
            <option value="<?= h($val) ?>" <?= $data['persona'] === $val ? 'selected' : '' ?>><?= h($txt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c6">
        <label>En calidad de*</label>
        <select name="en_calidad" required>
          <option value="">Selecciona</option>
          <?php foreach ($ctx['calidades'] as $calidad): ?>
            <option value="<?= h($calidad) ?>" <?= $data['en_calidad'] === $calidad ? 'selected' : '' ?>><?= h($service->calidadLabel((string) $calidad)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c6">
        <label>Tipo de diligencia*</label>
        <select name="tipo_diligencia" required>
          <option value="">Selecciona</option>
          <?php foreach ($ctx['tipos'] as $tipo): ?>
            <option value="<?= h($tipo) ?>" <?= $data['tipo_diligencia'] === $tipo ? 'selected' : '' ?>><?= h($tipo) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c3"><label>Fecha*</label><input type="date" name="fecha" value="<?= h($data['fecha'] ?: $hoy) ?>" required></div>
      <div class="c3"><label>Hora*</label><input type="time" name="hora" value="<?= h($data['hora'] ?: '09:00') ?>" required></div>
      <div class="c3"><label>Orden de citación</label><input type="number" name="orden_citacion" value="<?= h((string) $data['orden_citacion']) ?>" min="1"></div>
      <div class="c3"><label>Oficio que ordena</label><input type="number" name="oficio_id" value="<?= h((string) $data['oficio_id']) ?>" placeholder="ID de oficio"></div>

      <div class="c12">
        <label>Lugar*</label>
        <div class="hstack">
          <select id="lugar_sel" style="flex:1;">
            <option value="">Selecciona</option>
            <option value="Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE">Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE</option>
            <option value="Lugar de los hechos">Lugar de los hechos</option>
          </select>
          <button type="button" id="btnLugarOtro" class="btn">＋</button>
        </div>
        <input id="lugar_otro" type="text" placeholder="Escribe el lugar..." style="display:none; margin-top:8px;">
        <input type="hidden" name="lugar" id="lugar_final" value="<?= h($data['lugar']) ?>">
      </div>

      <div class="c12">
        <label>Motivo / Observaciones*</label>
        <div class="hstack">
          <select id="motivo_sel" style="flex:1;">
            <option value="">Selecciona</option>
            <option value="Rendir manifestación">Rendir manifestación</option>
            <option value="Visualización de video">Visualización de video</option>
            <option value="Imposición de PIT">Imposición de PIT</option>
            <option value="Diligencia DIRCRI">Diligencia DIRCRI</option>
            <option value="Entrega vehiculo">Entrega vehiculo</option>
            <option value="Entrega enseres">Entrega enseres</option>
            <option value="Entrega documentos">Entrega documentos</option>
          </select>
          <button type="button" id="btnMotivoOtro" class="btn">＋</button>
        </div>
        <textarea id="motivo_otro" rows="4" placeholder="Escribe el motivo..." style="display:none; margin-top:8px;"></textarea>
        <input type="hidden" name="motivo" id="motivo_final" value="<?= h($data['motivo']) ?>">
      </div>
    </div>
  </form>

  <div class="card" style="margin-top:16px;">
    <h2 style="margin-top:0;font-size:15px;">Calendario de turnos</h2>
    <p class="small">Aquí puedes ver tus días de servicio y franco.</p>
    <div style="border-radius:12px;overflow:hidden;">
      <iframe src="https://calendar.google.com/calendar/embed?height=600&wkst=1&ctz=America%2FLima&showPrint=0&src=YTg4ZTg1MjI3NDkwZDZhMzJlNDcyMzIwMDZjMDYxZjljMDYyNmIzMmM2M2E1ZmQ2NWRkMGVlNGVkNTFlNTYwZUBncm91cC5jYWxlbmRhci5nb29nbGUuY29t&src=MjUzNmFiZGUzMWY3YjA5ZmNhMDBhMGQ4NjEwZTY0ZDM3MWMwNDBmMGQ4ZWU0YTlhZTdlMWJhMmZhY2RiNjFkYUBncm91cC5jYWxlbmRhci5nb29nbGUuY29t&src=N250Ym05aGFibG00am0wbGJpMXN2Mm83YTRAZ3JvdXAuY2FsZW5kYXIuZ29vZ2xlLmNvbQ&color=%2333b679&color=%23d50000&color=%23009688" style="border:solid 1px #777;width:100%;height:600px;" frameborder="0" scrolling="no"></iframe>
    </div>
  </div>
</div>
<script>
(function(){
  const form = document.getElementById('frmCitacion');
  const lugarSel = document.getElementById('lugar_sel');
  const lugarOtro = document.getElementById('lugar_otro');
  const lugarFinal = document.getElementById('lugar_final');
  const btnLugar = document.getElementById('btnLugarOtro');
  const motivoSel = document.getElementById('motivo_sel');
  const motivoOtro = document.getElementById('motivo_otro');
  const motivoFinal = document.getElementById('motivo_final');
  const btnMotivo = document.getElementById('btnMotivoOtro');
  let usandoLugarCustom = false;
  let usandoMotivoCustom = false;

  function initPreset(select, other, hidden, toggleBtn, setFlag) {
    const preset = (hidden.value || '').trim();
    if (!preset) return;
    let found = false;
    for (const opt of select.options) { if (opt.value === preset) { found = true; break; } }
    if (found) {
      select.value = preset;
    } else {
      setFlag(true);
      other.style.display = select.tagName === 'TEXTAREA' ? 'block' : 'block';
      select.style.display = 'none';
      toggleBtn.textContent = '✕';
      other.value = preset;
    }
  }

  initPreset(lugarSel, lugarOtro, lugarFinal, btnLugar, (v)=> usandoLugarCustom = v);
  initPreset(motivoSel, motivoOtro, motivoFinal, btnMotivo, (v)=> usandoMotivoCustom = v);

  btnLugar.addEventListener('click', ()=>{
    usandoLugarCustom = !usandoLugarCustom;
    lugarOtro.style.display = usandoLugarCustom ? 'block' : 'none';
    lugarSel.style.display = usandoLugarCustom ? 'none' : 'block';
    btnLugar.textContent = usandoLugarCustom ? '✕' : '＋';
    if (usandoLugarCustom && lugarSel.value) lugarOtro.value = lugarSel.value;
  });

  btnMotivo.addEventListener('click', ()=>{
    usandoMotivoCustom = !usandoMotivoCustom;
    motivoOtro.style.display = usandoMotivoCustom ? 'block' : 'none';
    motivoSel.style.display = usandoMotivoCustom ? 'none' : 'block';
    btnMotivo.textContent = usandoMotivoCustom ? '✕' : '＋';
    if (usandoMotivoCustom && motivoSel.value) motivoOtro.value = motivoSel.value;
  });

  form.addEventListener('submit', (event)=>{
    lugarFinal.value = (usandoLugarCustom ? lugarOtro.value : lugarSel.value).trim();
    motivoFinal.value = (usandoMotivoCustom ? motivoOtro.value : motivoSel.value).trim();
    if (!lugarFinal.value) {
      event.preventDefault();
      alert('Indica el lugar.');
      return;
    }
    if (!motivoFinal.value) {
      event.preventDefault();
      alert('Indica el motivo / observaciones.');
    }
  });
})();
</script>
</body>
</html>
