<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\OficioRepository;
use App\Services\OficioService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new OficioService(new OficioRepository($pdo));
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$oficio = $id > 0 ? $service->oficio($id) : null;
if ($id <= 0 || $oficio === null) {
    header('Location: oficios_listar.php');
    exit;
}

if (isset($_GET['ajax'])) {
    $ajax = trim((string) $_GET['ajax']);
    header('Content-Type: application/json; charset=utf-8');
    try {
        switch ($ajax) {
            case 'subentidades':
                echo json_encode(['ok' => true, 'items' => $service->subentidades((int) ($_GET['entidad_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'personas':
                echo json_encode(['ok' => true, 'items' => $service->personas((int) ($_GET['entidad_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'asuntos':
                echo json_encode(['ok' => true, 'items' => $service->asuntos((int) ($_GET['entidad_id'] ?? 0), (string) ($_GET['tipo'] ?? 'SOLICITAR'))], JSON_UNESCAPED_UNICODE);
                break;
            case 'asunto_info':
                echo json_encode(['ok' => true, 'item' => $service->asuntoInfo((int) ($_GET['id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'asunto_variantes':
                echo json_encode(['ok' => true, 'items' => $service->asuntoVariantes((int) ($_GET['id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'grado_cargo':
                echo json_encode(['ok' => true, 'items' => $service->gradoCargo()], JSON_UNESCAPED_UNICODE);
                break;
            case 'nextnum':
                $anio = (int) ($_GET['anio'] ?? 0);
                if ($anio <= 0) throw new InvalidArgumentException('Año inválido.');
                echo json_encode(['ok' => true, 'next' => $service->nextNumero($anio)], JSON_UNESCAPED_UNICODE);
                break;
            case 'vehiculos_accidente':
                echo json_encode(['ok' => true, 'items' => $service->vehiculosAccidente((int) ($_GET['accidente_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            case 'fallecidos_accidente':
                echo json_encode(['ok' => true, 'items' => $service->fallecidosAccidente((int) ($_GET['accidente_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
                break;
            default:
                throw new InvalidArgumentException('ajax inválido.');
        }
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$ctx = $service->formContext((int) ($oficio['accidente_id'] ?? 0));
$data = $service->defaultData($oficio);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'accidente_id' => $_POST['accidente_id'] ?? '',
        'anio_oficio' => $_POST['anio_oficio'] ?? '',
        'numero_oficio' => $_POST['numero_oficio'] ?? '',
        'fecha_emision' => $_POST['fecha_emision'] ?? '',
        'oficial_ano_id' => $_POST['oficial_ano_id'] ?? '',
        'entidad_id' => $_POST['entidad_id'] ?? '',
        'subentidad_id' => $_POST['subentidad_id'] ?? '',
        'grado_cargo_id' => $_POST['grado_cargo_id'] ?? '',
        'persona_id' => $_POST['persona_id'] ?? '',
        'tipo' => $_POST['tipo'] ?? 'SOLICITAR',
        'asunto_id' => $_POST['asunto_id'] ?? '',
        'motivo' => $_POST['motivo'] ?? '',
        'referencia_texto' => $_POST['referencia_texto'] ?? '',
        'involucrado_vehiculo_id' => $_POST['involucrado_vehiculo_id'] ?? '',
        'involucrado_persona_id' => $_POST['involucrado_persona_id'] ?? '',
        'estado' => $_POST['estado'] ?? 'BORRADOR',
    ];
    try {
        $service->update($id, $data);
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>try{ window.parent.postMessage({type:"oficio.saved"}, "*"); }catch(_){ }</script><body style="font:13px Inter,sans-serif;padding:16px">Guardado...</body>';
            exit;
        }
        $success = 'Cambios guardados correctamente.';
        $oficio = $service->oficio($id);
        $data = $service->defaultData($oficio);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$entidadActual = (int) ($data['entidad_id'] ?: 0);
$tipoActual = (string) ($data['tipo'] ?: 'SOLICITAR');
$subentidadesActuales = $entidadActual > 0 ? $service->subentidades($entidadActual) : [];
$personasActuales = $entidadActual > 0 ? $service->personas($entidadActual) : [];
$asuntosActuales = $entidadActual > 0 ? $service->asuntos($entidadActual, $tipoActual) : [];
$vehiculosActuales = !empty($data['accidente_id']) ? $service->vehiculosAccidente((int) $data['accidente_id']) : [];
$fallecidosActuales = !empty($data['accidente_id']) ? $service->fallecidosAccidente((int) $data['accidente_id']) : [];
$listarHref = 'oficios_listar.php' . (!empty($data['accidente_id']) ? ('?accidente_id=' . urlencode((string) $data['accidente_id'])) : '');

if (!$embed) {
    include __DIR__ . '/sidebar.php';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar Oficio</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6;--danger:#fecaca;--ok:#bbf7d0}}
body{background:var(--page);color:var(--text)}.wrap{max-width:1180px;margin:24px auto;padding:16px}.toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.btn.mini{width:40px;height:40px;padding:0;font-size:20px;line-height:1}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c2{grid-column:span 2}.c3{grid-column:span 3}.c4{grid-column:span 4}.c5{grid-column:span 5}.c6{grid-column:span 6}.c8{grid-column:span 8}.c12{grid-column:span 12}label{display:block;font-weight:700;color:var(--muted);margin-bottom:6px}input,select,textarea{width:100%;box-sizing:border-box;padding:11px 12px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text)}textarea{min-height:110px;resize:vertical}.field-row{display:flex;gap:8px;align-items:center}.alert{padding:12px 14px;border-radius:12px;margin-bottom:12px}.alert.ok{background:rgba(22,163,74,.12);color:var(--ok)}.alert.err{background:rgba(220,38,38,.12);color:var(--danger)}.muted{color:var(--muted);font-size:.9rem}.preview{border:1px dashed var(--border);border-radius:12px;padding:12px;background:rgba(148,163,184,.06)}.preview h4{margin:.1rem 0 .5rem}.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:9999;padding:18px}.modal{width:min(980px,96vw);height:min(680px,90vh);background:var(--card);border-radius:16px;overflow:hidden;border:1px solid var(--border)}.modal header{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid var(--border)}.modal iframe{width:100%;height:calc(100% - 52px);border:0}@media (max-width:900px){.c2,.c3,.c4,.c5,.c6,.c8{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <h1>Editar Oficio #<?= h($id) ?></h1>
<div class="toolbar">
  <?php if ($embed): ?>
    <button class="btn" type="button" onclick="try{window.parent&&window.parent.postMessage({type:'oficio.close'},'*');}catch(e){}">Cerrar</button>
  <?php else: ?>
    <a class="btn" href="<?= h($listarHref) ?>">← Volver</a>
    <?php if (!empty($data['accidente_id'])): ?>
      <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= urlencode((string) $data['accidente_id']) ?>">Datos generales SIDPOL</a>
    <?php endif; ?>
    <a class="btn" href="oficios_leer.php?id=<?= h($id) ?>">Ver detalle</a>
  <?php endif; ?>
</div>

  <?php if ($error !== ''): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="alert ok"><?= h($success) ?></div><?php endif; ?>

  <form method="post" class="card">
    <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <div class="grid">
      <div class="c12">
        <label>Accidente asociado*</label>
        <select name="accidente_id" id="accidente_id" required>
          <option value="">Selecciona el accidente</option>
          <?php foreach ($ctx['accidentes'] as $accidente): ?>
            <option value="<?= h($accidente['id']) ?>" <?= (string) $data['accidente_id'] === (string) $accidente['id'] ? 'selected' : '' ?>><?= h($accidente['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="c2"><label>Año*</label><input type="number" name="anio_oficio" id="anio_oficio" value="<?= h($data['anio_oficio']) ?>" required></div>
      <div class="c3"><label>Número*</label><div class="field-row"><input type="number" name="numero_oficio" id="numero_oficio" value="<?= h($data['numero_oficio']) ?>"><button class="btn mini" type="button" onclick="recalcularNumero()">↻</button></div></div>
      <div class="c3"><label>Fecha de emisión*</label><input type="date" name="fecha_emision" id="fecha_emision" value="<?= h($data['fecha_emision']) ?>" required></div>
      <div class="c4"><label>Nombre oficial del año*</label><div class="field-row"><select name="oficial_ano_id" id="oficial_ano_id" required><option value="">Selecciona</option><?php foreach ($ctx['oficial_anos'] as $ano): $label=$ano['anio'].' - '.$ano['nombre'].((int)($ano['vigente']??0)===1?' (Vigente)':''); ?><option value="<?= h($ano['id']) ?>" <?= (string) $data['oficial_ano_id'] === (string) $ano['id'] ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select><button class="btn mini" type="button" onclick="openCreate('ano')">+</button></div></div>
      <div class="c6"><label>Entidad destino*</label><div class="field-row"><select name="entidad_id" id="entidad_id" required><option value="">Selecciona</option><?php foreach ($ctx['entidades'] as $entidad): $label=$entidad['nombre'].($entidad['siglas']!==''?' ('.$entidad['siglas'].')':''); ?><option value="<?= h($entidad['id']) ?>" <?= (string) $data['entidad_id'] === (string) $entidad['id'] ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select><button class="btn mini" type="button" onclick="openCreate('entidad')">+</button></div></div>
      <div class="c6"><label>Subentidad</label><div class="field-row"><select name="subentidad_id" id="subentidad_id"><option value="">Ninguna</option><?php foreach ($subentidadesActuales as $item): ?><option value="<?= h($item['id']) ?>" <?= (string) $data['subentidad_id'] === (string) $item['id'] ? 'selected' : '' ?>><?= h($item['nombre']) ?></option><?php endforeach; ?></select><button class="btn mini" type="button" onclick="openCreate('subentidad')">+</button></div></div>
      <div class="c6"><label>Grado y cargo</label><div class="field-row"><select name="grado_cargo_id" id="grado_cargo_id"><option value="">(Opcional)</option><?php foreach ($ctx['grado_cargo'] as $cargo): $label=$cargo['nombre'].($cargo['abrev']!==''?' - '.$cargo['abrev']:'').' ['.$cargo['tipo'].']'; ?><option value="<?= h($cargo['id']) ?>" <?= (string) $data['grado_cargo_id'] === (string) $cargo['id'] ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select><button class="btn mini" type="button" onclick="openCreate('cargo')">+</button></div></div>
      <div class="c6"><label>Persona destino</label><div class="field-row"><select name="persona_id" id="persona_id"><option value="">Ninguna</option><?php foreach ($personasActuales as $persona): ?><option value="<?= h($persona['id']) ?>" <?= (string) $data['persona_id'] === (string) $persona['id'] ? 'selected' : '' ?>><?= h(trim($persona['nombre'])) ?></option><?php endforeach; ?></select><button class="btn mini" type="button" onclick="openCreate('persona')">+</button></div></div>
      <div class="c4"><label>Tipo de asunto</label><select name="tipo" id="tipo"><?php foreach ($ctx['tipos'] as $tipo): ?><option value="<?= h($tipo) ?>" <?= $data['tipo'] === $tipo ? 'selected' : '' ?>><?= h($tipo) ?></option><?php endforeach; ?></select></div>
      <div class="c8"><label>Asunto*</label><div class="field-row"><select name="asunto_id" id="asunto_id" required><option value="">Selecciona el asunto</option><?php foreach ($asuntosActuales as $asunto): ?><option value="<?= h($asunto['id']) ?>" <?= (string) $data['asunto_id'] === (string) $asunto['id'] ? 'selected' : '' ?>><?= h($asunto['nombre']) ?></option><?php endforeach; ?></select><button class="btn mini" type="button" onclick="openCreate('asunto')">+</button></div></div>
      <div class="c12"><div id="asuntoPreview" class="preview" style="display:none;"><h4 id="asuntoNombre"></h4><div class="field-row" id="asuntoVarBox" style="display:none; margin:0 0 .6rem 0;"><label style="margin:0;">Variante</label><select id="asuntoVarSelect"></select></div><div id="asuntoDetalle"></div></div></div>
      <div class="c12"><label>Motivo / contexto*</label><textarea name="motivo" id="motivo" required><?= h($data['motivo']) ?></textarea></div>
      <div class="c12"><label>Referencia</label><input type="text" name="referencia_texto" value="<?= h($data['referencia_texto']) ?>"></div>
      <div class="c6" id="vehiculoBox" style="display:none;"><label>Vehículo involucrado</label><select name="involucrado_vehiculo_id" id="involucrado_vehiculo_id"><option value="">Selecciona</option><?php foreach ($vehiculosActuales as $item): ?><option value="<?= h($item['id']) ?>" <?= (string) $data['involucrado_vehiculo_id'] === (string) $item['id'] ? 'selected' : '' ?>><?= h($item['nombre']) ?></option><?php endforeach; ?></select></div>
      <div class="c6" id="fallecidoBox" style="display:none;"><label>Persona fallecida</label><select name="involucrado_persona_id" id="involucrado_persona_id"><option value="">Selecciona</option><?php foreach ($fallecidosActuales as $item): ?><option value="<?= h($item['id']) ?>" <?= (string) $data['involucrado_persona_id'] === (string) $item['id'] ? 'selected' : '' ?>><?= h($item['nombre']) ?></option><?php endforeach; ?></select></div>
      <div class="c4"><label>Estado</label><select name="estado"><?php foreach ($ctx['estados'] as $estado): ?><option value="<?= h($estado) ?>" <?= (string) $data['estado'] === (string) $estado ? 'selected' : '' ?>><?= h($estado) ?></option><?php endforeach; ?></select></div>
      <div class="c12" style="display:flex;justify-content:flex-end;gap:10px;">
        <?php if ($embed): ?>
          <button class="btn" type="button" onclick="try{window.parent&&window.parent.postMessage({type:'oficio.close'},'*');}catch(e){}">Cancelar</button>
        <?php else: ?>
          <a class="btn" href="<?= h($listarHref) ?>">Cancelar</a>
        <?php endif; ?>
        <button class="btn primary" type="submit">Guardar cambios</button>
      </div>
    </div>
  </form>
</div>

<div class="modal-backdrop" id="modalBackdrop"><div class="modal"><header><h3 id="modalTitle">Nuevo registro</h3><button class="btn" type="button" onclick="closeModal()">Cerrar</button></header><iframe id="modalFrame" src="about:blank"></iframe></div></div>
<script>
const accSel = document.getElementById('accidente_id'); const entidadSel = document.getElementById('entidad_id'); const subSel = document.getElementById('subentidad_id'); const personaSel = document.getElementById('persona_id'); const tipoSel = document.getElementById('tipo'); const asuntoSel = document.getElementById('asunto_id'); const motivoTxt = document.getElementById('motivo'); const fechaInp = document.getElementById('fecha_emision'); const anioInp = document.getElementById('anio_oficio'); const numInp = document.getElementById('numero_oficio'); let lastModal = null;
async function fetchJSON(url){ const response = await fetch(url,{headers:{'Accept':'application/json'}}); const data = await response.json(); if(!response.ok || data.ok===false){ throw new Error(data.msg || 'Error cargando datos.'); } return data; }
function fillSelect(select, items, selectedValue, placeholder, labelKey='nombre'){ select.innerHTML=''; select.add(new Option(placeholder,'')); items.forEach((item)=>{ const option = new Option(item[labelKey] || '', item.id); if(String(selectedValue)===String(item.id)) option.selected = true; select.add(option); }); }
async function loadSubentidades(entidadId, selected=''){ if(!entidadId){ fillSelect(subSel, [], '', 'Ninguna'); return; } const data = await fetchJSON('?ajax=subentidades&entidad_id='+encodeURIComponent(entidadId)); fillSelect(subSel, data.items||[], selected, 'Ninguna'); }
async function loadPersonas(entidadId, selected=''){ if(!entidadId){ fillSelect(personaSel, [], '', 'Ninguna'); return; } const data = await fetchJSON('?ajax=personas&entidad_id='+encodeURIComponent(entidadId)); fillSelect(personaSel, data.items||[], selected, 'Ninguna'); }
async function loadAsuntos(entidadId, tipo, selected=''){ if(!entidadId){ fillSelect(asuntoSel, [], '', 'Selecciona el asunto'); return; } const data = await fetchJSON('?ajax=asuntos&entidad_id='+encodeURIComponent(entidadId)+'&tipo='+encodeURIComponent(tipo)); fillSelect(asuntoSel, data.items||[], selected, 'Selecciona el asunto'); }
async function loadGradoCargo(selected=''){ const select=document.getElementById('grado_cargo_id'); const current=selected||select.value; const data=await fetchJSON('?ajax=grado_cargo'); fillSelect(select,data.items||[],current,'(Opcional)'); }
async function refreshAsuntoPreview(){ const box=document.getElementById('asuntoPreview'); const n=document.getElementById('asuntoNombre'); const detail=document.getElementById('asuntoDetalle'); const varBox=document.getElementById('asuntoVarBox'); const varSel=document.getElementById('asuntoVarSelect'); if(!asuntoSel.value){ box.style.display='none'; return; } const info=await fetchJSON('?ajax=asunto_info&id='+encodeURIComponent(asuntoSel.value)); if(!info.item){ box.style.display='none'; return; } n.textContent=info.item.nombre||''; detail.textContent=(info.item.detalle||'').trim()||'—'; box.style.display='block'; const variantes=await fetchJSON('?ajax=asunto_variantes&id='+encodeURIComponent(asuntoSel.value)); if(variantes.items && variantes.items.length>1){ varSel.innerHTML=''; variantes.items.forEach((item,index)=>{ const text=(item.detalle||'').trim(); const label='Plantilla '+(index+1)+(text?' - '+(text.length>60?text.slice(0,60)+'…':text):''); const option=new Option(label,item.id); if(String(item.id)===String(asuntoSel.value)) option.selected=true; varSel.add(option); }); varBox.style.display='flex'; varSel.onchange=async()=>{ const info2=await fetchJSON('?ajax=asunto_info&id='+encodeURIComponent(varSel.value)); if(info2.item){ detail.textContent=(info2.item.detalle||'').trim()||'—'; } asuntoSel.value=varSel.value; await toggleBoxesPorAsunto(); }; } else { varBox.style.display='none'; varSel.innerHTML=''; } }
function asuntoTexto(){ const option=asuntoSel.options[asuntoSel.selectedIndex]; return option ? option.text.toLowerCase() : ''; }
function asuntoEsPeritaje(){ return asuntoTexto().includes('peritaje de constatación de daños') || asuntoTexto().includes('peritaje de constatacion de danos'); }
function asuntoEsNecropsia(){ const text=asuntoTexto(); return text.includes('protocolo de necropsia') || text.includes('protocolo de autopsia') || text.includes('necropsia'); }
async function loadVehiculosAccidente(selected=''){ const sel=document.getElementById('involucrado_vehiculo_id'); if(!accSel.value){ fillSelect(sel, [], '', 'Selecciona'); return; } const data=await fetchJSON('?ajax=vehiculos_accidente&accidente_id='+encodeURIComponent(accSel.value)); fillSelect(sel,data.items||[],selected,'Selecciona'); }
async function loadFallecidosAccidente(selected=''){ const sel=document.getElementById('involucrado_persona_id'); if(!accSel.value){ fillSelect(sel, [], '', 'Selecciona'); return; } const data=await fetchJSON('?ajax=fallecidos_accidente&accidente_id='+encodeURIComponent(accSel.value)); fillSelect(sel,data.items||[],selected,'Selecciona'); }
async function toggleBoxesPorAsunto(){ const vehBox=document.getElementById('vehiculoBox'); const fallBox=document.getElementById('fallecidoBox'); if(asuntoEsPeritaje()){ vehBox.style.display='block'; await loadVehiculosAccidente(document.getElementById('involucrado_vehiculo_id').value); } else { vehBox.style.display='none'; document.getElementById('involucrado_vehiculo_id').value=''; } if(asuntoEsNecropsia()){ fallBox.style.display='block'; await loadFallecidosAccidente(document.getElementById('involucrado_persona_id').value); } else { fallBox.style.display='none'; document.getElementById('involucrado_persona_id').value=''; } }
async function recalcularNumero(){ const year=parseInt(anioInp.value||'',10); if(!year) return; const data=await fetchJSON('?ajax=nextnum&anio='+encodeURIComponent(year)); numInp.value=data.next; }
function openModal(title,url,kind){ lastModal=kind; document.getElementById('modalTitle').textContent=title; document.getElementById('modalFrame').src=url; document.getElementById('modalBackdrop').style.display='flex'; }
function closeModal(){ document.getElementById('modalBackdrop').style.display='none'; document.getElementById('modalFrame').src='about:blank'; const entidadId=entidadSel.value||''; const tipo=tipoSel.value||'SOLICITAR'; if(lastModal==='subentidad'&&entidadId) loadSubentidades(entidadId, subSel.value); else if(lastModal==='persona'&&entidadId) loadPersonas(entidadId, personaSel.value); else if(lastModal==='asunto'&&entidadId) loadAsuntos(entidadId, tipo, asuntoSel.value).then(refreshAsuntoPreview).then(toggleBoxesPorAsunto); else if(lastModal==='cargo') loadGradoCargo(document.getElementById('grado_cargo_id').value); else if(lastModal==='entidad' || lastModal==='ano') location.reload(); lastModal=null; }
function openCreate(kind){ const entidadId=entidadSel.value||''; const tipo=tipoSel.value||'SOLICITAR'; if(kind==='entidad') return openModal('Nueva entidad','oficio_entidad_nuevo.php',kind); if(kind==='subentidad'){ if(!entidadId) return alert('Selecciona primero una entidad.'); return openModal('Nueva subentidad','oficio_subentidad_nuevo.php?entidad_id='+encodeURIComponent(entidadId),kind);} if(kind==='persona'){ if(!entidadId) return alert('Selecciona primero una entidad.'); return openModal('Nueva persona','oficio_persona_entidad_nuevo.php?entidad_id='+encodeURIComponent(entidadId),kind);} if(kind==='asunto'){ if(!entidadId) return alert('Selecciona primero una entidad.'); return openModal('Nuevo asunto','oficio_asunto_nuevo.php?entidad_id='+encodeURIComponent(entidadId)+'&tipo='+encodeURIComponent(tipo),kind);} if(kind==='ano') return openModal('Nuevo nombre oficial del año','oficio_oficial_ano_nuevo.php',kind); if(kind==='cargo') return openModal('Nuevo grado/cargo','oficio_cargo_nuevo.php',kind); }
window.closeModal=closeModal; window.openCreate=openCreate;
fechaInp.addEventListener('change',()=>{ const year=(fechaInp.value||'').slice(0,4); if(year){ anioInp.value=year; recalcularNumero().catch(console.error); } }); accSel.addEventListener('change',()=>{ toggleBoxesPorAsunto().catch(console.error); }); entidadSel.addEventListener('change', async()=>{ const entidadId=entidadSel.value||''; await loadSubentidades(entidadId); await loadPersonas(entidadId); await loadAsuntos(entidadId,tipoSel.value||'SOLICITAR'); await refreshAsuntoPreview(); await toggleBoxesPorAsunto(); }); tipoSel.addEventListener('change', async()=>{ await loadAsuntos(entidadSel.value||'',tipoSel.value||'SOLICITAR'); await refreshAsuntoPreview(); await toggleBoxesPorAsunto(); }); asuntoSel.addEventListener('change', async()=>{ await refreshAsuntoPreview(); await toggleBoxesPorAsunto(); }); document.addEventListener('DOMContentLoaded', async()=>{ await refreshAsuntoPreview().catch(()=>{}); await toggleBoxesPorAsunto(); });
</script>
</body>
</html>
