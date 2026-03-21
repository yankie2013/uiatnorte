<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\PolicialIntervinienteRepository;
use App\Services\PolicialIntervinienteService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new PolicialIntervinienteService(new PolicialIntervinienteRepository($pdo));

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $op = (string) $_GET['ajax'];
        if ($op === 'buscar_dni') {
            $persona = $service->personaPorDni((string) ($_GET['dni'] ?? ''));
            if ($persona === null) {
                echo json_encode(['ok' => false, 'msg' => 'No existe en BD. Usa el boton + para registrarla.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(['ok' => true, 'persona' => $persona], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($op === 'buscar_id') {
            $persona = $service->personaPorId((int) ($_GET['id'] ?? 0));
            echo json_encode(['ok' => true, 'persona' => $persona], JSON_UNESCAPED_UNICODE);
            exit;
        }
        throw new InvalidArgumentException('Operacion no valida.');
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$accidenteId = (int) ($_GET['accidente_id'] ?? $_POST['accidente_id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta accidente_id');
}

$error = '';
$data = $service->defaultData(null, $accidenteId);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'accidente_id' => $_POST['accidente_id'] ?? $accidenteId,
        'persona_id' => $_POST['persona_id'] ?? '',
        'grado_policial' => $_POST['grado_policial'] ?? '',
        'cip' => $_POST['cip'] ?? '',
        'dependencia_policial' => $_POST['dependencia_policial'] ?? '',
        'rol_funcion' => $_POST['rol_funcion'] ?? '',
        'observaciones' => $_POST['observaciones'] ?? '',
        'celular' => $_POST['celular'] ?? '',
        'email' => $_POST['email'] ?? '',
    ];

    try {
        $service->create($data);
        header('Location: policial_interviniente_listar.php?accidente_id=' . $accidenteId . '&ok=created');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$ctx = $service->formContext($accidenteId);
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nuevo interviniente policial</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#16a34a;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#4ade80;--danger:#fecaca}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1040px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(22,163,74,.12);color:var(--primary);border:1px solid rgba(22,163,74,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#052e16;border-color:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.field{display:flex;flex-direction:column;gap:6px}.label{font-size:12px;color:var(--muted);font-weight:700}.small{color:var(--muted);font-size:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}input,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}textarea{min-height:96px;resize:vertical}.search-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.search-row input{flex:1}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}.header-card{margin-bottom:14px}.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:9999;padding:16px}.modal{width:960px;max-width:96%;height:86vh;background:var(--card);border-radius:12px;overflow:hidden;display:flex;flex-direction:column}.modal header{padding:10px 12px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}.modal .body{flex:1}.modal iframe{border:0;width:100%;height:100%}@media(max-width:820px){.c6{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1 style="margin:0 0 6px">Interviniente policial <span class="badge">Nuevo</span></h1><div class="small">Accidente ID: <?= (int) $accidenteId ?></div></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="policial_interviniente_listar.php?accidente_id=<?= (int) $accidenteId ?>">Volver</a><button class="btn primary" type="submit" form="frmPolicia">Guardar registro</button></div>
  </div>

  <?php if ($ctx['accidente']): ?><div class="card header-card"><strong>Accidente #<?= (int) $ctx['accidente']['id'] ?></strong><div class="small" style="margin-top:4px;">SIDPOL: <?= h((string) ($ctx['accidente']['sidpol'] ?? 'Sin SIDPOL')) ?> - Registro: <?= h((string) ($ctx['accidente']['registro_sidpol'] ?? 'Sin registro')) ?> - Fecha: <?= h((string) ($ctx['accidente']['fecha_accidente'] ?? 'Sin fecha')) ?> - Lugar: <?= h((string) ($ctx['accidente']['lugar'] ?? 'Sin lugar')) ?></div></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <form method="post" class="card" id="frmPolicia" autocomplete="off">
    <input type="hidden" name="accidente_id" value="<?= (int) $accidenteId ?>">
    <input type="hidden" name="persona_id" id="persona_id" value="<?= h((string) $data['persona_id']) ?>">
    <div class="grid">
      <div class="c12 field"><label class="label">DNI de la persona*</label><div class="search-row"><input type="text" id="dni" maxlength="8" value="<?= h((string) $data['num_doc']) ?>" placeholder="Ingresa DNI"><button type="button" class="btn" id="btnBuscar">Buscar</button><button type="button" class="btn" id="btnNuevo">+</button><button type="button" class="btn" id="btnLimpiar">Limpiar</button></div><div class="small" id="persona_info"><?= $data['nombre_persona'] !== '' ? 'Persona: ' . h($data['nombre_persona']) : '-' ?></div></div>
      <div class="c12 field"><label class="label">Nombre completo</label><input type="text" id="persona_nombre" value="<?= h((string) $data['nombre_persona']) ?>" readonly></div>
      <div class="c12 field"><label class="label">Domicilio</label><input type="text" id="persona_domicilio" value="<?= h((string) $data['domicilio']) ?>" readonly></div>
      <div class="c6 field"><label class="label">Celular</label><input type="text" name="celular" id="persona_celular" value="<?= h((string) $data['celular']) ?>"></div>
      <div class="c6 field"><label class="label">Email</label><input type="email" name="email" id="persona_email" value="<?= h((string) $data['email']) ?>"></div>
      <div class="c6 field"><label class="label">Grado policial*</label><input type="text" name="grado_policial" value="<?= h((string) $data['grado_policial']) ?>" required></div>
      <div class="c6 field"><label class="label">CIP*</label><input type="text" name="cip" value="<?= h((string) $data['cip']) ?>" required></div>
      <div class="c12 field"><label class="label">Dependencia policial*</label><input type="text" name="dependencia_policial" value="<?= h((string) $data['dependencia_policial']) ?>" required></div>
      <div class="c6 field"><label class="label">Rol / funcion</label><input type="text" name="rol_funcion" value="<?= h((string) $data['rol_funcion']) ?>"></div>
      <div class="c12 field"><label class="label">Observaciones</label><textarea name="observaciones"><?= h((string) $data['observaciones']) ?></textarea></div>
    </div>
    <div class="actions"><a class="btn" href="policial_interviniente_listar.php?accidente_id=<?= (int) $accidenteId ?>">Cancelar</a><button class="btn primary" type="submit">Guardar</button></div>
  </form>
</div>
<div class="modal-backdrop" id="personaModal"><div class="modal"><header><h3>Nueva persona</h3><button class="btn" type="button" id="btnModalCerrar">Cerrar</button></header><div class="body"><iframe id="personaFrame" src="about:blank"></iframe></div></div></div>
<script>
(function(){const dni=document.getElementById('dni');const info=document.getElementById('persona_info');const personaId=document.getElementById('persona_id');const nombre=document.getElementById('persona_nombre');const domicilio=document.getElementById('persona_domicilio');const celular=document.getElementById('persona_celular');const email=document.getElementById('persona_email');const modal=document.getElementById('personaModal');const frame=document.getElementById('personaFrame');function pintar(persona){personaId.value=persona.id||'';nombre.value=[persona.apellido_paterno||'',persona.apellido_materno||'',persona.nombres||''].join(' ').trim();domicilio.value=persona.domicilio||'';celular.value=persona.celular||'';email.value=persona.email||'';if(persona.num_doc)dni.value=persona.num_doc;info.innerHTML='Persona: <b>'+nombre.value+'</b> (id: '+persona.id+')';}document.getElementById('btnBuscar').addEventListener('click',async function(){try{const r=await fetch('policial_interviniente_nuevo.php?ajax=buscar_dni&dni='+encodeURIComponent((dni.value||'').trim()),{cache:'no-store'});const j=await r.json();if(!j.ok){info.textContent=j.msg||'No encontrado.';return;}pintar(j.persona);}catch(e){info.textContent='Error consultando.';}});document.getElementById('btnLimpiar').addEventListener('click',function(){personaId.value='';nombre.value='';domicilio.value='';celular.value='';email.value='';dni.value='';info.textContent='-';});document.getElementById('btnNuevo').addEventListener('click',function(){frame.src='persona_nuevo.php'+(((dni.value||'').trim()!=='')?('?dni='+encodeURIComponent(dni.value.trim())):'');modal.style.display='flex';});document.getElementById('btnModalCerrar').addEventListener('click',function(){modal.style.display='none';frame.src='about:blank';});window.addEventListener('message',async function(ev){const d=ev.data||{};if(d.type==='persona_creada'&&d.id){const r=await fetch('policial_interviniente_nuevo.php?ajax=buscar_id&id='+encodeURIComponent(d.id),{cache:'no-store'});const j=await r.json();if(j.ok&&j.persona)pintar(j.persona);modal.style.display='none';frame.src='about:blank';}});})();
</script>
</body>
</html>
