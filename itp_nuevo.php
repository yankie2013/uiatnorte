<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\ItpRepository;
use App\Services\ItpService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new ItpService(new ItpRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? $_POST['accidente_id'] ?? 0);
$error = '';
$ok = trim((string) ($_GET['ok'] ?? ''));
$data = $service->defaultData(null, $accidenteId > 0 ? $accidenteId : null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = array_merge($data, [
        'accidente_id' => $_POST['accidente_id'] ?? $accidenteId,
        'fecha_itp' => $_POST['fecha_itp'] ?? '',
        'hora_itp' => $_POST['hora_itp'] ?? '',
        'ocurrencia_policial' => $_POST['ocurrencia_policial'] ?? '',
        'llegada_lugar' => $_POST['llegada_lugar'] ?? '',
        'localizacion_unidades' => $_POST['localizacion_unidades'] ?? '',
        'forma_via' => $_POST['forma_via'] ?? '',
        'punto_referencia' => $_POST['punto_referencia'] ?? '',
        'ubicacion_gps' => $_POST['ubicacion_gps'] ?? '',
        'descripcion_via1' => $_POST['descripcion_via1'] ?? '',
        'configuracion_via1' => $_POST['configuracion_via1'] ?? '',
        'material_via1' => $_POST['material_via1'] ?? '',
        'senializacion_via1' => $_POST['senializacion_via1'] ?? '',
        'ordenamiento_via1' => $_POST['ordenamiento_via1'] ?? '',
        'iluminacion_via1' => $_POST['iluminacion_via1'] ?? '',
        'visibilidad_via1' => $_POST['visibilidad_via1'] ?? '',
        'intensidad_via1' => $_POST['intensidad_via1'] ?? '',
        'fluidez_via1' => $_POST['fluidez_via1'] ?? '',
        'medidas_via1' => $_POST['medidas_via1'] ?? '',
        'observaciones_via1' => $_POST['observaciones_via1'] ?? '',
        'via2_flag' => $_POST['via2_flag'] ?? 0,
        'descripcion_via2' => $_POST['descripcion_via2'] ?? '',
        'configuracion_via2' => $_POST['configuracion_via2'] ?? '',
        'material_via2' => $_POST['material_via2'] ?? '',
        'senializacion_via2' => $_POST['senializacion_via2'] ?? '',
        'ordenamiento_via2' => $_POST['ordenamiento_via2'] ?? '',
        'iluminacion_via2' => $_POST['iluminacion_via2'] ?? '',
        'visibilidad_via2' => $_POST['visibilidad_via2'] ?? '',
        'intensidad_via2' => $_POST['intensidad_via2'] ?? '',
        'fluidez_via2' => $_POST['fluidez_via2'] ?? '',
        'medidas_via2' => $_POST['medidas_via2'] ?? '',
        'observaciones_via2' => $_POST['observaciones_via2'] ?? '',
        'evidencia_biologica' => $_POST['evidencia_biologica'] ?? '',
        'evidencia_fisica' => $_POST['evidencia_fisica'] ?? '',
        'evidencia_material' => $_POST['evidencia_material'] ?? '',
    ]);

    try {
        $newId = $service->create($data);
        header('Location: itp_ver.php?id=' . $newId . '&ok=created');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $accidenteId = (int) ($data['accidente_id'] ?? 0);
    }
}

$ctx = $service->formContext($accidenteId > 0 ? $accidenteId : null);
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nuevo ITP</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c;--ok:#166534;--gold:#b68b1f}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca;--ok:#bbf7d0;--gold:#e6c97d}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1160px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}h1,h2{color:var(--gold);margin:0}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.btn.ghost{background:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px;margin-bottom:14px}.fieldset{border:1px solid var(--border);border-radius:14px;padding:14px;margin-top:14px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.c4{grid-column:span 4}.c3{grid-column:span 3}.field{display:flex;flex-direction:column;gap:6px}.label{font-size:12px;color:var(--gold);font-weight:700}.small{color:var(--muted);font-size:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}textarea{min-height:92px;resize:vertical}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}.tagbox,.measurebox,.pairbox{border:1px dashed var(--border);border-radius:12px;padding:10px}.tag-items,.measure-list,.pair-list{display:flex;flex-direction:column;gap:8px;margin-top:10px}.chip,.measure-item,.pair-item{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--border);border-radius:10px;background:rgba(148,163,184,.08)}.mini-row{display:flex;gap:8px;flex-wrap:wrap}.mini-row input{flex:1}.gps-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.gps-row input{flex:1}.gps-modal{position:fixed;inset:0;display:none;z-index:60}.gps-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.65)}.gps-panel{position:relative;max-width:760px;margin:50px auto 0;background:var(--card);border:1px solid var(--border);border-radius:18px;padding:14px}.gps-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.gps-search input{width:100%;margin-bottom:10px}.gps-map{height:380px;border:1px solid var(--border);border-radius:12px;overflow:hidden}@media(max-width:920px){.c6,.c4,.c3{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1>ITP <span class="badge">Nuevo</span></h1><div class="small">Inspeccion tecnico policial</div></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn ghost" href="itp_listar.php<?= $accidenteId > 0 ? '?accidente_id=' . (int) $accidenteId : '' ?>">Volver</a><button class="btn primary" type="submit" form="frmItp">Guardar ITP</button></div>
  </div>

  <?php if ($ok !== ''): ?><div class="ok"><?= h($ok) ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <form method="post" id="frmItp" class="card" autocomplete="off">
    <?php if ($ctx['accidente']): ?>
      <input type="hidden" name="accidente_id" value="<?= (int) $ctx['accidente']['id'] ?>">
      <div class="fieldset"><strong>Accidente #<?= (int) $ctx['accidente']['id'] ?></strong><div class="small" style="margin-top:4px;">SIDPOL: <?= h((string) ($ctx['accidente']['registro_sidpol'] ?? '-')) ?> - Fecha: <?= h((string) ($ctx['accidente']['fecha_accidente'] ?? '-')) ?> - Lugar: <?= h((string) ($ctx['accidente']['lugar'] ?? '-')) ?></div></div>
    <?php else: ?>
      <div class="field c12"><label class="label">Accidente*</label><select name="accidente_id" required><option value="">Selecciona</option><?php foreach ($ctx['accidentes'] as $acc): ?><option value="<?= (int) $acc['id'] ?>" <?= (int) $data['accidente_id'] === (int) $acc['id'] ? 'selected' : '' ?>>[ID <?= (int) $acc['id'] ?>] <?= h((string) ($acc['fecha_accidente'] ?? '-')) ?> - SIDPOL: <?= h((string) ($acc['registro_sidpol'] ?? '-')) ?> - <?= h((string) ($acc['lugar'] ?? '')) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>

    <div class="fieldset">
      <h2 style="font-size:1rem;margin-bottom:10px;">Datos generales</h2>
      <div class="grid">
        <div class="c3 field"><label class="label">Fecha ITP</label><input type="date" name="fecha_itp" value="<?= h((string) $data['fecha_itp']) ?>"></div>
        <div class="c3 field"><label class="label">Hora ITP</label><input type="time" name="hora_itp" value="<?= h((string) $data['hora_itp']) ?>"></div>
        <div class="c6 field"><label class="label">Forma de la via</label><input type="text" name="forma_via" value="<?= h((string) $data['forma_via']) ?>"></div>
        <div class="c12 field"><label class="label">Punto de referencia</label><input type="text" name="punto_referencia" value="<?= h((string) $data['punto_referencia']) ?>"></div>
        <div class="c12 field"><label class="label">Ubicacion GPS</label><div class="gps-row"><input type="text" id="ubicacion_gps" name="ubicacion_gps" value="<?= h((string) $data['ubicacion_gps']) ?>" readonly><button type="button" class="btn" id="btnGps">Marcar en mapa</button></div></div>
        <div class="c12 field"><label class="label">Localizacion de unidades</label><div class="pairbox" id="pb_localizacion" data-values="<?= h((string) $data['localizacion_unidades']) ?>"><div class="mini-row"><input type="text" class="p-unit" placeholder="Unidad"><input type="text" class="p-place" placeholder="Ubicacion"><button class="btn" type="button" class="p-add">Agregar</button></div><div class="pair-list"></div><input type="hidden" name="localizacion_unidades" value="<?= h((string) $data['localizacion_unidades']) ?>"></div></div>
        <div class="c12 field"><label class="label">Ocurrencia policial</label><textarea name="ocurrencia_policial"><?= h((string) $data['ocurrencia_policial']) ?></textarea></div>
        <div class="c12 field"><label class="label">Llegada al lugar</label><textarea name="llegada_lugar"><?= h((string) $data['llegada_lugar']) ?></textarea></div>
      </div>
    </div>

    <div class="fieldset">
      <h2 style="font-size:1rem;margin-bottom:10px;">Via 1</h2>
      <div class="grid">
        <div class="c12 field"><label class="label">Descripcion</label><textarea name="descripcion_via1"><?= h((string) $data['descripcion_via1']) ?></textarea></div>
        <div class="c4 field"><label class="label">Configuracion</label><input type="text" name="configuracion_via1" value="<?= h((string) $data['configuracion_via1']) ?>"></div>
        <div class="c4 field"><label class="label">Material</label><input type="text" name="material_via1" value="<?= h((string) $data['material_via1']) ?>"></div>
        <div class="c4 field"><label class="label">Senializacion</label><input type="text" name="senializacion_via1" value="<?= h((string) $data['senializacion_via1']) ?>"></div>
        <div class="c4 field"><label class="label">Ordenamiento</label><input type="text" name="ordenamiento_via1" value="<?= h((string) $data['ordenamiento_via1']) ?>"></div>
        <div class="c4 field"><label class="label">Iluminacion</label><input type="text" name="iluminacion_via1" value="<?= h((string) $data['iluminacion_via1']) ?>"></div>
        <div class="c4 field"><label class="label">Visibilidad</label><input type="text" name="visibilidad_via1" value="<?= h((string) $data['visibilidad_via1']) ?>"></div>
        <div class="c6 field"><label class="label">Intensidad</label><input type="text" name="intensidad_via1" value="<?= h((string) $data['intensidad_via1']) ?>"></div>
        <div class="c6 field"><label class="label">Fluidez</label><input type="text" name="fluidez_via1" value="<?= h((string) $data['fluidez_via1']) ?>"></div>
        <div class="c12 field"><label class="label">Medidas</label><div class="measurebox" id="mb_via1" data-values="<?= h((string) $data['medidas_via1']) ?>"><div class="mini-row"><input type="text" class="m-name" placeholder="Que mides"><input type="text" class="m-value" placeholder="Valor"><button type="button" class="btn m-add">Agregar</button></div><div class="measure-list"></div><input type="hidden" name="medidas_via1" value="<?= h((string) $data['medidas_via1']) ?>"></div></div>
        <div class="c12 field"><label class="label">Observaciones</label><div class="tagbox" id="tl_obs_via1" data-values="<?= h((string) $data['observaciones_via1']) ?>"><div class="tag-items"></div><div class="mini-row"><input type="text" placeholder="Agregar observacion"><button type="button" class="btn">Agregar</button></div><input type="hidden" name="observaciones_via1" value="<?= h((string) $data['observaciones_via1']) ?>"></div></div>
      </div>
    </div>

    <input type="hidden" name="via2_flag" id="via2_flag" value="<?= (int) $data['via2_flag'] ?>">
    <div class="actions" style="justify-content:flex-start;margin-top:0;"><button type="button" class="btn" id="btnAddVia2" <?= (int) $data['via2_flag'] === 1 ? 'style="display:none;"' : '' ?>>Anadir via 2</button><button type="button" class="btn ghost" id="btnRemoveVia2" <?= (int) $data['via2_flag'] === 1 ? '' : 'style="display:none;"' ?>>Quitar via 2</button></div>

    <div class="fieldset" id="fs_via2" <?= (int) $data['via2_flag'] === 1 ? '' : 'style="display:none;"' ?>>
      <h2 style="font-size:1rem;margin-bottom:10px;">Via 2</h2>
      <div class="grid">
        <div class="c12 field"><label class="label">Descripcion</label><textarea name="descripcion_via2"><?= h((string) $data['descripcion_via2']) ?></textarea></div>
        <div class="c4 field"><label class="label">Configuracion</label><input type="text" name="configuracion_via2" value="<?= h((string) $data['configuracion_via2']) ?>"></div>
        <div class="c4 field"><label class="label">Material</label><input type="text" name="material_via2" value="<?= h((string) $data['material_via2']) ?>"></div>
        <div class="c4 field"><label class="label">Senializacion</label><input type="text" name="senializacion_via2" value="<?= h((string) $data['senializacion_via2']) ?>"></div>
        <div class="c4 field"><label class="label">Ordenamiento</label><input type="text" name="ordenamiento_via2" value="<?= h((string) $data['ordenamiento_via2']) ?>"></div>
        <div class="c4 field"><label class="label">Iluminacion</label><input type="text" name="iluminacion_via2" value="<?= h((string) $data['iluminacion_via2']) ?>"></div>
        <div class="c4 field"><label class="label">Visibilidad</label><input type="text" name="visibilidad_via2" value="<?= h((string) $data['visibilidad_via2']) ?>"></div>
        <div class="c6 field"><label class="label">Intensidad</label><input type="text" name="intensidad_via2" value="<?= h((string) $data['intensidad_via2']) ?>"></div>
        <div class="c6 field"><label class="label">Fluidez</label><input type="text" name="fluidez_via2" value="<?= h((string) $data['fluidez_via2']) ?>"></div>
        <div class="c12 field"><label class="label">Medidas</label><div class="measurebox" id="mb_via2" data-values="<?= h((string) $data['medidas_via2']) ?>"><div class="mini-row"><input type="text" class="m-name" placeholder="Que mides"><input type="text" class="m-value" placeholder="Valor"><button type="button" class="btn m-add">Agregar</button></div><div class="measure-list"></div><input type="hidden" name="medidas_via2" value="<?= h((string) $data['medidas_via2']) ?>"></div></div>
        <div class="c12 field"><label class="label">Observaciones</label><div class="tagbox" id="tl_obs_via2" data-values="<?= h((string) $data['observaciones_via2']) ?>"><div class="tag-items"></div><div class="mini-row"><input type="text" placeholder="Agregar observacion"><button type="button" class="btn">Agregar</button></div><input type="hidden" name="observaciones_via2" value="<?= h((string) $data['observaciones_via2']) ?>"></div></div>
      </div>
    </div>

    <div class="fieldset">
      <h2 style="font-size:1rem;margin-bottom:10px;">Evidencias</h2>
      <div class="grid">
        <div class="c12 field"><label class="label">Evidencia biologica</label><textarea name="evidencia_biologica"><?= h((string) $data['evidencia_biologica']) ?></textarea></div>
        <div class="c12 field"><label class="label">Evidencia fisica</label><textarea name="evidencia_fisica"><?= h((string) $data['evidencia_fisica']) ?></textarea></div>
        <div class="c12 field"><label class="label">Evidencia material</label><textarea name="evidencia_material"><?= h((string) $data['evidencia_material']) ?></textarea></div>
      </div>
    </div>

    <div class="actions"><a class="btn ghost" href="itp_listar.php<?= $accidenteId > 0 ? '?accidente_id=' . (int) $accidenteId : '' ?>">Cancelar</a><button class="btn primary" type="submit">Guardar</button></div>
  </form>
</div>
<div class="gps-modal" id="gpsModal"><div class="gps-backdrop gps-close"></div><div class="gps-panel"><div class="gps-header"><strong>Seleccionar ubicacion</strong><button type="button" class="btn ghost gps-close">Cerrar</button></div><div class="gps-search"><input type="text" id="gpsSearch" placeholder="Buscar direccion, ciudad o lugar"></div><div id="gpsMap" class="gps-map"></div><p class="small">Busca un lugar o haz clic en el mapa para fijar las coordenadas.</p><div class="actions"><button type="button" class="btn ghost gps-close">Cancelar</button><button type="button" class="btn primary" id="gpsUse">Usar estas coordenadas</button></div></div></div>
<script>
(function(){function formatMeters(v){v=(v||'').toString().replace(',','.').replace(/[^\d.]/g,'');if(!v)return'';const n=parseFloat(v);if(Number.isNaN(n))return'';let f=n.toFixed(2).split('.');if(f[0].length===1)f[0]='0'+f[0];return f[0]+'.'+f[1]+' m';}function initTagBox(root){if(!root)return;const items=root.querySelector('.tag-items');const input=root.querySelector('input[type="text"]');const button=root.querySelector('button');const hidden=root.querySelector('input[type="hidden"]');function arr(){return (hidden.value||'').split(',').map(s=>s.trim()).filter(Boolean);}function render(){items.innerHTML='';arr().forEach((text,index)=>{const chip=document.createElement('div');chip.className='chip';chip.innerHTML='<span>'+text+'</span>';const del=document.createElement('button');del.type='button';del.className='btn ghost';del.textContent='Quitar';del.addEventListener('click',function(){const a=arr();a.splice(index,1);hidden.value=a.join(', ');render();});chip.appendChild(del);items.appendChild(chip);});}function add(){let value=(input.value||'').trim();if(!value)return;value=value.replace(/,/g,' ');const a=arr();a.push(value);hidden.value=a.join(', ');input.value='';render();}button.addEventListener('click',add);input.addEventListener('keydown',function(ev){if(ev.key==='Enter'){ev.preventDefault();add();}});render();}function initMeasureBox(root){if(!root)return;const name=root.querySelector('.m-name');const value=root.querySelector('.m-value');const button=root.querySelector('.m-add');const list=root.querySelector('.measure-list');const hidden=root.querySelector('input[type="hidden"]');function arr(){return (hidden.value||'').split(',').map(s=>s.trim()).filter(Boolean);}function render(){list.innerHTML='';arr().forEach((text,index)=>{const row=document.createElement('div');row.className='measure-item';row.innerHTML='<span>'+text+'</span>';const del=document.createElement('button');del.type='button';del.className='btn ghost';del.textContent='Quitar';del.addEventListener('click',function(){const a=arr();a.splice(index,1);hidden.value=a.join(', ');render();});row.appendChild(del);list.appendChild(row);});}function add(){const left=(name.value||'').trim().replace(/,/g,' ');const right=formatMeters(value.value);if(!left||!right)return;const a=arr();a.push('- '+left+' : '+right);hidden.value=a.join(', ');name.value='';value.value='';render();}button.addEventListener('click',add);[name,value].forEach(function(el){el.addEventListener('keydown',function(ev){if(ev.key==='Enter'){ev.preventDefault();add();}});});render();}function initPairBox(root){if(!root)return;const unit=root.querySelector('.p-unit');const place=root.querySelector('.p-place');const button=root.querySelector('button');const list=root.querySelector('.pair-list');const hidden=root.querySelector('input[type="hidden"]');function arr(){return (hidden.value||'').split(',').map(s=>s.trim()).filter(Boolean);}function render(){list.innerHTML='';arr().forEach((text,index)=>{const row=document.createElement('div');row.className='pair-item';row.innerHTML='<span>'+text+'</span>';const del=document.createElement('button');del.type='button';del.className='btn ghost';del.textContent='Quitar';del.addEventListener('click',function(){const a=arr();a.splice(index,1);hidden.value=a.join(', ');render();});row.appendChild(del);list.appendChild(row);});}function add(){const left=(unit.value||'').trim().replace(/,/g,' ');const right=(place.value||'').trim().replace(/,/g,' ');if(!left||!right)return;const a=arr();a.push('- '+left+' : '+right);hidden.value=a.join(', ');unit.value='';place.value='';render();}button.addEventListener('click',add);[unit,place].forEach(function(el){el.addEventListener('keydown',function(ev){if(ev.key==='Enter'){ev.preventDefault();add();}});});render();}initTagBox(document.getElementById('tl_obs_via1'));initTagBox(document.getElementById('tl_obs_via2'));initMeasureBox(document.getElementById('mb_via1'));initMeasureBox(document.getElementById('mb_via2'));initPairBox(document.getElementById('pb_localizacion'));const fs=document.getElementById('fs_via2');const addBtn=document.getElementById('btnAddVia2');const remBtn=document.getElementById('btnRemoveVia2');const flag=document.getElementById('via2_flag');function toggleInside(enabled){fs.querySelectorAll('input,textarea,select,button').forEach(function(el){if(el.type!=='hidden')el.disabled=!enabled;});}function showVia2(){fs.style.display='';flag.value='1';toggleInside(true);addBtn.style.display='none';remBtn.style.display='';}function hideVia2(){fs.style.display='none';flag.value='0';toggleInside(false);addBtn.style.display='';remBtn.style.display='none';}addBtn.addEventListener('click',showVia2);remBtn.addEventListener('click',hideVia2);if(parseInt(flag.value||'0',10)===1){toggleInside(true);}else{toggleInside(false);}const gpsInput=document.getElementById('ubicacion_gps');const gpsModal=document.getElementById('gpsModal');const gpsUse=document.getElementById('gpsUse');let gpsMap=null,gpsMarker=null,gpsLatLng=null;function openGps(){gpsModal.style.display='block';if(gpsMap&&window.google){setTimeout(function(){google.maps.event.trigger(gpsMap,'resize');if(gpsLatLng)gpsMap.setCenter(gpsLatLng);},200);}}function closeGps(){gpsModal.style.display='none';}document.getElementById('btnGps').addEventListener('click',openGps);document.querySelectorAll('.gps-close').forEach(function(el){el.addEventListener('click',closeGps);});gpsUse.addEventListener('click',function(){if(!gpsLatLng)return;gpsInput.value=gpsLatLng.lat().toFixed(6)+', '+gpsLatLng.lng().toFixed(6);closeGps();});window.initGpsMap=function(){const mapEl=document.getElementById('gpsMap');if(!mapEl||!window.google)return;let center={lat:-9.189967,lng:-75.015152};if(gpsInput.value){const parts=gpsInput.value.split(',');if(parts.length===2){const lat=parseFloat(parts[0]);const lng=parseFloat(parts[1]);if(!isNaN(lat)&&!isNaN(lng)){center={lat:lat,lng:lng};gpsLatLng=new google.maps.LatLng(lat,lng);}}}gpsMap=new google.maps.Map(mapEl,{center:center,zoom:gpsLatLng?16:5});if(gpsLatLng){gpsMarker=new google.maps.Marker({position:gpsLatLng,map:gpsMap});}const searchInput=document.getElementById('gpsSearch');if(searchInput&&google.maps.places){const autocomplete=new google.maps.places.Autocomplete(searchInput,{fields:['geometry','name','formatted_address'],types:['geocode']});autocomplete.addListener('place_changed',function(){const place=autocomplete.getPlace();if(!place.geometry||!place.geometry.location)return;gpsLatLng=place.geometry.location;gpsMap.setCenter(gpsLatLng);gpsMap.setZoom(17);if(gpsMarker)gpsMarker.setMap(null);gpsMarker=new google.maps.Marker({map:gpsMap,position:gpsLatLng});});}gpsMap.addListener('click',function(ev){gpsLatLng=ev.latLng;if(gpsMarker)gpsMarker.setMap(null);gpsMarker=new google.maps.Marker({position:gpsLatLng,map:gpsMap});});};})();
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBBnJS7WFYPLcroPFi-l0felTh2UW_QR4Q&libraries=places&callback=initGpsMap" async defer></script>
</body>
</html>
