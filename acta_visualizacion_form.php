<?php
require __DIR__.'/auth.php'; require_login(); require __DIR__.'/db.php';

use App\Repositories\ActaVisualizacionRepository;
use App\Services\ActaVisualizacionService;

function h($v): string { return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8'); }
$repo=new ActaVisualizacionRepository($pdo); $service=new ActaVisualizacionService($repo);
$id=(int)($_GET['id']??$_POST['id']??0); $embed=(int)($_GET['embed']??$_POST['embed']??0)===1;
$row=$id?$repo->find($id):null; if($id&&!$row){http_response_code(404);exit('Acta no encontrada');}
$accidenteId=(int)($row['accidente_id']??$_GET['accidente_id']??$_POST['accidente_id']??0); if($accidenteId<=0){http_response_code(400);exit('Falta accidente_id');}
$draftKey='uiat:acta_visualizacion:v1:'.$accidenteId.':'.($id?:'nueva');
$ctx=$service->context($accidenteId); $error='';
$selectedParticipants=array_fill_keys(array_map(fn($p)=>$p['fuente'].':'.$p['fuente_id'],$row['participantes']??[]),true);
$disks=$row['discos']??[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{$id=$service->save($id?:null,$accidenteId,$_POST);$target='accidente_vista_tabs.php?accidente_id='.$accidenteId.'&tab=documentos&subtab=actas';?><!doctype html><meta charset="utf-8"><script>try{localStorage.removeItem(<?=json_encode($draftKey)?>)}catch(e){}<?php if($embed):?>window.parent.postMessage({type:'acta.saved'},'*');<?php else:?>location.replace(<?=json_encode($target)?>);<?php endif?></script><noscript><a href="<?=h($target)?>">Acta guardada. Continuar</a></noscript><?php exit;}catch(Throwable $e){$error=$e->getMessage();$selectedParticipants=array_fill_keys((array)($_POST['participantes']??[]),true);$disks=(array)($_POST['discos']??[]);}
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Acta de visualizacion</title><style>
.office{border:2px solid #0ea5e9;border-radius:14px;margin-top:12px;background:#f0f9ff;overflow:hidden}.office summary{cursor:pointer;padding:14px 16px;font-weight:800;color:#075985}.office-body{padding:0 16px 16px}.responses{margin:10px 0;padding:10px 14px;border-left:4px solid #22c55e;background:#f0fdf4;border-radius:8px}.office-media{margin-top:10px}.empty{padding:16px;border:1px dashed #94a3b8;border-radius:10px;text-align:center}.office .description{grid-template-columns:155px minmax(260px,1fr) auto}.capture-box{display:none}.disk-title,.file-title{cursor:pointer;user-select:none}.disk-title::before,.file-title::before{content:'▾ ';font-size:13px}.disk.collapsed .disk-title::before,.file.collapsed .file-title::before{content:'▸ '}.disk.collapsed>.grid,.disk.collapsed>.head:not(.disk-head),.disk.collapsed>.files{display:none}.file.collapsed>.file-fields,.file.collapsed>.head:not(.file-head),.file.collapsed>.descriptions,.file.collapsed>.description-actions{display:none}
:root{--bg:#f4f6fa;--p:#fff;--line:#d9e0ea;--text:#172033;--muted:#667085;--blue:#245ea8}*{box-sizing:border-box}body{margin:0;padding:18px;background:var(--bg);font:14px system-ui;color:var(--text)}.wrap{max-width:1180px;margin:auto}.panel{background:var(--p);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:14px}.head,.row,.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.head{justify-content:space-between}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.full{grid-column:1/-1}label{display:block;font-weight:700;margin-bottom:5px}input,textarea,select{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:#fff}.btn{padding:9px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;text-decoration:none;color:var(--text);font-weight:700;cursor:pointer}.primary{background:var(--blue);color:#fff}.check-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:7px}.check{display:flex;gap:8px;padding:9px;border:1px solid var(--line);border-radius:8px}.check input{width:auto}.muted{color:var(--muted);font-size:12px}.disk{--disk-color:#2563eb;--disk-soft:#eff6ff;border:2px solid var(--disk-color);border-left-width:8px;border-radius:16px;padding:16px;margin-top:18px;background:linear-gradient(135deg,var(--disk-soft),#fff 38%);box-shadow:0 10px 24px rgba(15,23,42,.08)}.disk-head{padding-bottom:12px;border-bottom:2px solid var(--disk-color)}.disk-title{font-size:18px;color:var(--disk-color)}.files{display:grid;gap:14px;margin-top:12px}.file{--file-color:var(--disk-color);border:2px solid var(--file-color);border-radius:13px;padding:13px;background:#fff;box-shadow:0 6px 16px rgba(15,23,42,.06)}.file-head{margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid color-mix(in srgb,var(--file-color) 45%,white)}.file-title{color:var(--file-color);font-size:15px}.file-fields{display:grid;grid-template-columns:2fr repeat(3,1fr);gap:8px}.descriptions{display:grid;gap:10px;margin-top:12px}.description{--moment-color:var(--file-color);display:grid;grid-template-columns:155px minmax(260px,1fr) 250px auto;gap:10px;padding:12px;border:2px solid var(--moment-color);border-left-width:7px;background:#fff;border-radius:11px;box-shadow:0 4px 12px rgba(15,23,42,.05)}.description:nth-child(even){background:#f8fafc}.moment-title{display:block;color:var(--moment-color);font-size:13px;margin-bottom:8px}.description-actions{display:flex;justify-content:flex-end;margin-top:10px;padding-top:10px;border-top:1px dashed color-mix(in srgb,var(--file-color) 45%,white)}.add-description{color:var(--file-color);border-color:var(--file-color);background:#fff}.capture-box{border:1px dashed var(--moment-color);border-radius:8px;padding:7px;min-height:75px;background:#fff}.capture-box img{display:block;max-width:100%;max-height:160px;margin-top:6px;border-radius:6px}.remove-description,.remove-file,.remove-disk{color:#9f1239;border-color:#fecdd3;background:#fff7f8}.error{padding:10px;background:#fff1f2;color:#9f1239;border-radius:8px;margin-bottom:12px}.draft-bar{position:sticky;z-index:20;top:8px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 14px;border:1px solid #bfdbfe;border-radius:12px;background:rgba(239,246,255,.96);box-shadow:0 8px 24px rgba(15,23,42,.1);backdrop-filter:blur(10px);margin-bottom:14px}.draft-state{display:flex;align-items:center;gap:9px;font-size:12px;font-weight:700}.draft-state::before{content:'';width:9px;height:9px;border-radius:50%;background:#94a3b8}.draft-state.is-saving::before{background:#f59e0b}.draft-state.is-saved::before{background:#22c55e}.draft-state.is-error::before{background:#ef4444}.draft-actions{display:flex;gap:8px}.btn.draft-save{padding:7px 10px;font-size:12px}.field-invalid{outline:3px solid rgba(239,68,68,.2);border-color:#ef4444!important}.save-help{min-height:18px;color:var(--muted);font-size:12px}.save-help.is-error{color:#b91c1c;font-weight:700}.primary[disabled]{cursor:wait;opacity:.72}@media(max-width:850px){.grid,.check-grid,.file-fields,.description{grid-template-columns:1fr}.description-actions{justify-content:stretch}.description-actions .btn{width:100%}.draft-bar{align-items:flex-start;flex-direction:column}.draft-actions{width:100%}.draft-actions .btn{flex:1}}

.participant-selection-block{--group-color:#3973ba;--group-background:#f2f7ff;min-width:0;margin:12px 0 0;padding:10px;border:1px solid var(--line);border-left:3px solid var(--group-color);border-radius:10px;background:var(--group-background)}
.participant-selection-block[data-source="PROPIETARIO"]{--group-color:#b78320;--group-background:#fffbf1}
.participant-selection-block[data-source="FAMILIAR"]{--group-color:#319a88;--group-background:#f1fbf8}
.participant-selection-block[data-source="ABOGADO"]{--group-color:#8261bd;--group-background:#f8f4ff}
.participant-selection-block legend{padding:0 5px;font-size:13px;font-weight:800;color:var(--group-color)}
.participant-count{display:inline-block;margin-left:4px;padding:1px 6px;border-radius:8px;background:#fff;font-size:11px}
.participant-selection-block .check{margin:0;background:#fff;align-items:center;min-width:0}
.participant-selection-block .check>span{min-width:0;overflow-wrap:anywhere}
.participant-selection-block .check input{flex:none;width:16px;height:16px;padding:0;accent-color:var(--group-color)}
@media(max-width:760px){.participant-selection-block{padding:8px;margin-top:9px}.participant-selection-block .check{padding:8px;font-size:12px}.participant-selection-block .muted{font-size:11px}}

.participant-selection-block .participant-role-icon{display:flex;align-items:center;justify-content:center;flex:0 0 30px;width:30px;height:30px;border-radius:9px;background:#e9f1ff;color:#3973ba}
.participant-selection-block .participant-role-icon{font-size:22px;line-height:1;font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif}
.participant-selection-block .participant-role-icon[data-role="owner"]{background:#fff3d5;color:#ad7916}
.participant-selection-block .participant-role-icon[data-role="family"]{background:#e0f7ef;color:#278a74}
.participant-selection-block .participant-role-icon[data-role="lawyer"]{background:#eee7ff;color:#7955b7}
.participant-selection-block .participant-role-icon[data-role="pedestrian"]{background:#def4f8;color:#258496}

.office-registration-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}.office-response-status{display:inline-block;font-size:11px;margin-left:8px}.btn:disabled{opacity:.5;cursor:not-allowed}
#acta-related-dialog{width:min(1000px,calc(100vw - 24px));max-height:calc(100dvh - 24px);padding:12px;border:1px solid var(--line);border-radius:14px;background:#fff;color:var(--text)}
#acta-related-dialog::backdrop{background:#17263d80;backdrop-filter:blur(3px)}
#acta-related-frame{width:100%;height:75dvh;border:0;margin-top:8px}
@media(max-width:760px){.panel>.head{flex-wrap:wrap;gap:8px}.office-registration-actions .btn{font-size:12px;padding:8px}.office-response-status{margin:5px 0}#acta-related-dialog{padding:8px}}
.participant-selection>summary{cursor:pointer;font-weight:800;color:var(--text)}.participant-selection>summary h2{display:inline;font-size:20px;margin:0 0 0 5px}.participant-selection>summary:focus-visible{outline:2px solid var(--blue);outline-offset:5px;border-radius:4px}
.direct-reception[hidden]{display:none}.direct-reception{padding-bottom:12px;margin-bottom:12px;border-bottom:1px solid var(--line)}
</style></head><body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<main class="wrap"><div class="panel head"><div><h1 style="margin:0">Acta de visualizacion</h1><div class="muted">Caso #<?= $accidenteId ?> · <?= h($ctx['accident']['registro_sidpol']) ?></div></div><?php if($embed):?><button class="btn" onclick="parent.postMessage({type:'acta.close'},'*')">Cerrar</button><?php else:?><a class="btn" href="accidente_vista_tabs.php?accidente_id=<?=$accidenteId?>&tab=documentos&subtab=actas">Volver</a><?php endif?></div>
<?php if($error):?><div class="error"><?=h($error)?></div><?php endif?><div class="draft-bar" role="status"><div class="draft-state" id="draftState">Proteccion de borrador activa</div><div class="draft-actions"><button class="btn draft-save" type="button" id="saveDraftNow">Guardar borrador ahora</button><button class="btn draft-save" type="button" id="discardDraft" hidden>Descartar borrador</button></div></div><form method="post" id="actaForm" novalidate><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="accidente_id" value="<?=$accidenteId?>"><input type="hidden" name="embed" value="<?=$embed?1:0?>">
<section class="panel grid"><div><label>Fecha</label><input type="date" name="fecha_visualizacion" value="<?=h($_POST['fecha_visualizacion']??$row['fecha_visualizacion']??date('Y-m-d'))?>" required></div><div><label>Hora de inicio</label><input type="time" name="hora_inicio" value="<?=h(substr((string)($_POST['hora_inicio']??$row['hora_inicio']??date('H:i')),0,5))?>" required></div></section>
<details class="panel participant-selection" open><summary><h2>👥 Personas consideradas</h2></summary>
<?php $participantGroups = $repo->participantGroups($accidenteId, $ctx['participants']); ?>
<?php foreach ($participantGroups as $title => $people): ?>
  <fieldset class="participant-selection-block" data-source="<?= $title === 'Peatones' ? 'FAMILIAR' : 'INVOLUCRADO' ?>">
    <legend><?=h($title)?> <span class="participant-count"><?=count($people)?></span></legend>
    <div class="check-grid">
      <?php foreach ($people as $p): $key=$p['fuente'].':'.$p['fuente_id'];
        $personRole = match ($p['fuente']) {
          'PROPIETARIO' => 'owner', 'FAMILIAR' => 'family', 'ABOGADO' => 'lawyer',
          default => str_starts_with(mb_strtolower($p['condicion']), 'conductor') ? 'driver' : (str_starts_with(mb_strtolower($p['condicion']), 'peat') ? 'pedestrian' : 'person'),
        };
        $roleEmoji = match ($personRole) {
          'driver' => '🚘', 'owner' => '🔑', 'family' => '👥',
          'lawyer' => '⚖️', 'pedestrian' => '🚶', default => '👤',
        };
      ?>
      <label class="check"><input type="checkbox" name="participantes[]" value="<?=h($key)?>" <?=isset($selectedParticipants[$key])?'checked':''?>><span class="participant-role-icon" data-role="<?=h($personRole)?>" aria-hidden="true"><?=h($roleEmoji)?></span><span><strong><?=h($p['nombre'])?></strong><br><span class="muted"><?=h($p['condicion'])?></span></span></label>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php endforeach; ?>
<?php if (!$ctx['participants']): ?><p class="muted">No hay personas registradas en este expediente.</p><?php endif; ?>
</details>
<section class="panel">
  <div class="head"><h2>📹 Oficios de cámaras y documentos recibidos</h2><div class="actions"><button type="button" class="btn primary acta-related-open" data-src="oficios_nuevo.php?accidente_id=<?=$accidenteId?>&amp;embed=1&amp;origin=acta_visualizacion" data-title="📄 Nuevo oficio de cámaras">📄 Nuevo oficio</button><button type="button" class="btn" id="add-direct-video">📥 Video presentado</button></div></div>
  <p class="muted">Consulta los oficios de cámaras, registra sus respuestas y agrega los medios recibidos para el acta.</p>
  <?php if (!$ctx['offices']): ?><div class="empty">Aún no hay oficios de cámaras registrados en este expediente.</div><?php endif; ?>
  <?php foreach ($ctx['offices'] as $i=>$office): ?>
  <details class="office" data-office-id="<?=$office['oficio_id']?>" <?=$i===0?'open':''?>>
    <summary>📄 <?=h($office['descripcion'].' — '.$office['titulo'])?> <span class="office-response-status"><?= $office['respuestas'] ? '✅ Con respuesta' : '⏳ Sin respuesta' ?></span></summary>
    <div class="office-body">
      <?php if ($office['solicitud']): ?><p class="muted"><?=h($office['solicitud'])?></p><?php endif; ?>
      <div class="responses">
        <?php if (!$office['respuestas']): ?><p class="muted">Registra el documento recibido para poder agregar el DISCO o USB.</p><?php endif; ?>
        <?php foreach ($office['respuestas'] as $response): ?><div>📥 <?=h($response['descripcion'])?></div><?php endforeach; ?>
      </div>
      <div class="office-registration-actions">
        <button type="button" class="btn acta-related-open" data-src="documento_recibido_nuevo.php?accidente_id=<?=$accidenteId?>&amp;referencia_oficio_id=<?=$office['oficio_id']?>&amp;embed=1" data-title="📥 Registrar respuesta">📥 Registrar respuesta</button>
        <button type="button" class="btn add-medium" <?= !$office['respuestas'] ? 'disabled' : '' ?>>💿 Agregar DISCO o USB</button>
      </div>
      <div class="office-media"></div>
    </div>
  </details>
  <?php endforeach; ?>
</section>
<section class="panel" id="direct-videos" hidden><h2>📥 Videos presentados por las partes</h2><p class="muted">Registra la recepción y los medios aportados directamente, sin oficio previo.</p><details class="office" data-office-id="0"><summary>👥 Presentaciones directas</summary><div class="office-body"><button type="button" class="btn add-medium">📥 Registrar presentación y DISCO/USB</button><div class="office-media"></div></div></details></section>
<section class="panel grid"><div><label>Estado</label><select name="estado"><?php foreach(['Pendiente','Realizada','Anulada'] as $s):?><option <?=($_POST['estado']??$row['estado']??'Pendiente')===$s?'selected':''?>><?=$s?></option><?php endforeach?></select></div><div class="full"><label>Observaciones</label><textarea name="observaciones"><?=h($_POST['observaciones']??$row['observaciones']??'')?></textarea></div><div class="full"><div class="save-help" id="saveHelp">Tu avance se guarda automaticamente en este navegador.</div></div><div class="full actions" style="justify-content:flex-end"><button class="btn primary" id="saveActaButton">Guardar acta</button></div></section></form></main>
<dialog id="acta-related-dialog"><div class="head"><strong id="acta-related-title"></strong><button type="button" class="btn" id="acta-related-close">Cerrar</button></div><iframe id="acta-related-frame" title="Registrar oficio o respuesta" src="about:blank"></iframe></dialog>
<template id="disk-template"><div class="disk"><input type="hidden" data-field="oficio_id"><div class="direct-reception grid" hidden><div><label>Fecha de recibido*</label><input type="date" data-field="fecha_recibido" required></div><div><label>Tipo de documento*</label><select data-field="tipo_documento_recibido" required><option value="">Seleccionar</option><option>Escrito</option><option>Carta</option><option>Oficio</option><option>Solicitud</option></select></div><div class="full"><label>Presentado por*</label><input data-field="presentado_por" maxlength="255" placeholder="Nombre de la persona o entidad" required></div></div><div class="head disk-head"><strong class="disk-title"></strong><button type="button" class="btn remove-disk">Eliminar medio</button></div><div class="grid" style="margin-top:14px"><div><label>Medio recibido</label><select data-field="tipo_medio" required><option value="">Seleccionar</option><option>DISCO</option><option>USB</option></select></div><div><label>Marca</label><input data-field="marca" required></div><div><label>Número de serie</label><input data-field="numero_serie" required></div><div><label>Capacidad</label><input data-field="capacidad" placeholder="Ej.: 16 GB, 1 TB" required></div><div class="full"><label>Características u observaciones</label><input data-field="observaciones"></div></div><div class="head" style="margin-top:14px"><strong>Archivos contenidos en este medio</strong><button type="button" class="btn add-file">+ Agregar archivo</button></div><div class="files"></div></div></template>
<template id="file-template"><div class="file"><div class="head file-head"><strong class="file-title"></strong><button type="button" class="btn remove-file">Quitar archivo</button></div><div class="file-fields"><div><label>Nombre del archivo</label><input data-file-field="nombre_archivo" required></div><div><label>Tipo</label><input data-file-field="tipo_archivo"></div><div><label>Peso</label><input data-file-field="peso"></div><div><label>Duración</label><input data-file-field="duracion"></div></div><div class="head" style="margin-top:14px"><strong>Momentos observados</strong><span class="muted">Se mostrarán en este mismo orden en el acta.</span></div><div class="descriptions"></div><div class="description-actions"><button type="button" class="btn add-description">+ Agregar descripción debajo</button></div></div></template>
<template id="description-template"><div class="description"><div><strong class="moment-title"></strong><label>Tiempo observado</label><input type="time" step="1" data-description-field="tiempo" required><div class="muted">Hora, minuto y segundo</div></div><div><label>Descripción del momento observado</label><textarea data-description-field="detalle" rows="5" required></textarea></div><button type="button" class="btn remove-description">Quitar momento</button></div></template>
<script>
const initial=<?=json_encode(array_values($disks),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const diskPalettes=[['#2563eb','#eff6ff'],['#7c3aed','#f5f3ff'],['#0f766e','#f0fdfa'],['#c2410c','#fff7ed'],['#be123c','#fff1f2'],['#0369a1','#f0f9ff']],fileColors=['#1d4ed8','#0891b2','#7c3aed','#c2410c','#be123c','#047857'],momentColors=['#2563eb','#7c3aed','#0891b2','#d97706','#be123c','#047857'];
function syncDirectVideos(){const section=document.getElementById('direct-videos');section.hidden=!section.querySelector('.disk')}
function allDisks(){return [...document.querySelectorAll('.disk')]}
function renumber(){allDisks().forEach((d,di)=>{const palette=diskPalettes[di%diskPalettes.length],type=d.querySelector('[data-field=tipo_medio]').value||'DISCO/USB';d.style.setProperty('--disk-color',palette[0]);d.style.setProperty('--disk-soft',palette[1]);d.querySelector('.disk-title').textContent=String.fromCharCode(65+(di%26))+'. '+type+' '+(di+1);d.querySelectorAll('[data-field]').forEach(x=>x.name=`discos[${di}][${x.dataset.field}]`);[...d.querySelectorAll('.file')].forEach((f,fi)=>{const fileColor=fileColors[fi%fileColors.length];f.style.setProperty('--file-color',fileColor);f.querySelector('.file-title').textContent=String.fromCharCode(97+(fi%26))+') Archivo '+(fi+1);f.querySelectorAll('[data-file-field]').forEach(x=>x.name=`discos[${di}][archivos][${fi}][${x.dataset.fileField}]`);[...f.querySelectorAll('.description')].forEach((r,ri)=>{r.style.setProperty('--moment-color',momentColors[ri%momentColors.length]);r.querySelector('.moment-title').textContent='Momento observado '+(ri+1);r.querySelectorAll('[data-description-field]').forEach(x=>x.name=`discos[${di}][archivos][${fi}][descripciones][${ri}][${x.dataset.descriptionField}]`)})})})}
function addDescription(f,data={}){const r=document.getElementById('description-template').content.firstElementChild.cloneNode(true);r.querySelectorAll('[data-description-field]').forEach(x=>x.value=data[x.dataset.descriptionField]||'');r.querySelector('.remove-description').onclick=()=>{r.remove();renumber()};f.querySelector('.descriptions').append(r);renumber()}
function addFile(d,data={}){d.querySelectorAll('.file').forEach(x=>x.classList.add('collapsed'));const f=document.getElementById('file-template').content.firstElementChild.cloneNode(true);f.querySelectorAll('[data-file-field]').forEach(x=>x.value=data[x.dataset.fileField]||'');f.querySelector('.file-title').onclick=()=>f.classList.toggle('collapsed');f.querySelector('.remove-file').onclick=()=>{f.remove();renumber()};f.querySelector('.add-description').onclick=()=>addDescription(f);d.querySelector('.files').append(f);const moments=data.descripciones||[];if(moments.length)moments.forEach(v=>addDescription(f,v));else addDescription(f);renumber()}
function addDisk(office,data={}){office.querySelectorAll('.disk').forEach(x=>x.classList.add('collapsed'));const d=document.getElementById('disk-template').content.firstElementChild.cloneNode(true);data.oficio_id=data.oficio_id||office.dataset.officeId;d.querySelectorAll('[data-field]').forEach(x=>x.value=data[x.dataset.field]||'');const direct=Number(data.oficio_id)===0;d.querySelector('.direct-reception').hidden=!direct;d.querySelectorAll('.direct-reception [data-field]').forEach(x=>x.disabled=!direct);d.querySelector('.disk-title').onclick=()=>d.classList.toggle('collapsed');d.querySelector('[data-field=tipo_medio]').onchange=renumber;d.querySelector('.remove-disk').onclick=()=>{d.remove();renumber();syncDirectVideos()};d.querySelector('.add-file').onclick=()=>addFile(d);office.querySelector('.office-media').append(d);const files=data.archivos||[];if(files.length)files.forEach(f=>addFile(d,f));else addFile(d);office.open=true;if(direct)document.getElementById('direct-videos').hidden=false;renumber()}
document.getElementById('add-direct-video').onclick=()=>{const office=document.querySelector('.office[data-office-id="0"]');addDisk(office);office.closest('.panel').scrollIntoView({behavior:'smooth',block:'start'})};
document.querySelectorAll('.office').forEach(office=>office.querySelector('.add-medium').onclick=()=>addDisk(office));
initial.forEach(data=>{let office=document.querySelector(`.office[data-office-id="${Number(data.oficio_id)||0}"]`);if(!office)office=document.querySelector('.office');if(office)addDisk(office,data)});

/* Proteccion contra perdida de avance */
const actaForm=document.getElementById('actaForm'),draftKey=<?=json_encode($draftKey)?>,draftState=document.getElementById('draftState'),saveHelp=document.getElementById('saveHelp'),saveButton=document.getElementById('saveActaButton'),discardButton=document.getElementById('discardDraft');
let draftTimer=null,hasUnsavedChanges=false,isSubmitting=false,isRestoring=false;
function draftData(){
 const value=name=>actaForm.elements[name]?.value||'';
 return {version:1,savedAt:Date.now(),fields:{fecha_visualizacion:value('fecha_visualizacion'),hora_inicio:value('hora_inicio'),estado:value('estado'),observaciones:value('observaciones')},participants:[...actaForm.querySelectorAll('input[name="participantes[]"]:checked')].map(x=>x.value),disks:allDisks().map(d=>({oficio_id:d.querySelector('[data-field=oficio_id]')?.value||'',fecha_recibido:d.querySelector('[data-field=fecha_recibido]')?.value||'',tipo_documento_recibido:d.querySelector('[data-field=tipo_documento_recibido]')?.value||'',presentado_por:d.querySelector('[data-field=presentado_por]')?.value||'',tipo_medio:d.querySelector('[data-field=tipo_medio]')?.value||'',marca:d.querySelector('[data-field=marca]')?.value||'',numero_serie:d.querySelector('[data-field=numero_serie]')?.value||'',capacidad:d.querySelector('[data-field=capacidad]')?.value||'',observaciones:d.querySelector('[data-field=observaciones]')?.value||'',archivos:[...d.querySelectorAll(':scope > .files > .file')].map(f=>({nombre_archivo:f.querySelector('[data-file-field=nombre_archivo]')?.value||'',tipo_archivo:f.querySelector('[data-file-field=tipo_archivo]')?.value||'',peso:f.querySelector('[data-file-field=peso]')?.value||'',duracion:f.querySelector('[data-file-field=duracion]')?.value||'',descripciones:[...f.querySelectorAll(':scope > .descriptions > .description')].map(r=>({tiempo:r.querySelector('[data-description-field=tiempo]')?.value||'',detalle:r.querySelector('[data-description-field=detalle]')?.value||''}))}))}))};
}
function setDraftState(message,state='') {draftState.textContent=message;draftState.className='draft-state'+(state?' is-'+state:'')}
function saveDraft(showConfirmation=false){
 clearTimeout(draftTimer);draftTimer=null;
 try{localStorage.setItem(draftKey,JSON.stringify(draftData()));hasUnsavedChanges=true;const stamp=new Intl.DateTimeFormat('es-PE',{hour:'2-digit',minute:'2-digit',second:'2-digit'}).format(new Date());setDraftState((showConfirmation?'Borrador guardado':'Autoguardado')+' a las '+stamp,'saved');discardButton.hidden=false}
 catch(e){setDraftState('No se pudo guardar el borrador en este navegador','error')}
}
function scheduleDraft(){if(isRestoring||isSubmitting)return;hasUnsavedChanges=true;setDraftState('Guardando cambios...','saving');clearTimeout(draftTimer);draftTimer=setTimeout(()=>saveDraft(false),700)}
function restoreDraft(data){
 if(!data||data.version!==1)return false;isRestoring=true;
 Object.entries(data.fields||{}).forEach(([name,val])=>{if(actaForm.elements[name])actaForm.elements[name].value=val});
 const selected=new Set(data.participants||[]);actaForm.querySelectorAll('input[name="participantes[]"]').forEach(x=>x.checked=selected.has(x.value));
 document.querySelectorAll('.office-media').forEach(x=>x.replaceChildren());
 (data.disks||[]).forEach(d=>{let office=document.querySelector(`.office[data-office-id="${Number(d.oficio_id)||0}"]`);if(!office)office=document.querySelector('.office');if(office)addDisk(office,d)});
 syncDirectVideos();isRestoring=false;hasUnsavedChanges=true;discardButton.hidden=false;const when=data.savedAt?new Intl.DateTimeFormat('es-PE',{dateStyle:'short',timeStyle:'medium'}).format(new Date(data.savedAt)):'';setDraftState('Borrador recuperado'+(when?' del '+when:''),'saved');return true;
}
function revealInvalid(field){
 const office=field.closest('.office');if(office)office.open=true;
 field.closest('.disk')?.classList.remove('collapsed');field.closest('.file')?.classList.remove('collapsed');
 field.classList.add('field-invalid');field.scrollIntoView({behavior:'smooth',block:'center'});setTimeout(()=>{field.focus({preventScroll:true});field.reportValidity()},250);
}
function validateActa(){
 actaForm.querySelectorAll('.field-invalid').forEach(x=>x.classList.remove('field-invalid'));
 if(!actaForm.querySelector('input[name="participantes[]"]:checked')){const first=actaForm.querySelector('input[name="participantes[]"]');if(first){revealInvalid(first);saveHelp.textContent='Selecciona al menos una persona considerada.';saveHelp.className='save-help is-error'}return false}
 if(!allDisks().length){const button=document.querySelector('.add-medium');button?.scrollIntoView({behavior:'smooth',block:'center'});button?.focus();saveHelp.textContent='Agrega al menos un DISCO o USB antes de guardar.';saveHelp.className='save-help is-error';return false}
 const invalid=[...actaForm.querySelectorAll('[required]')].find(x=>!x.checkValidity());
 if(invalid){revealInvalid(invalid);saveHelp.textContent='Falta completar el campo señalado. La sección correspondiente fue abierta.';saveHelp.className='save-help is-error';return false}
 saveHelp.textContent='Validacion completada. Guardando el acta...';saveHelp.className='save-help';return true;
}
actaForm.addEventListener('input',e=>{e.target.classList.remove('field-invalid');scheduleDraft()});
actaForm.addEventListener('change',scheduleDraft);
new MutationObserver(scheduleDraft).observe(actaForm,{childList:true,subtree:true});
document.getElementById('saveDraftNow').addEventListener('click',()=>saveDraft(true));
discardButton.addEventListener('click',()=>{if(!confirm('Se eliminara solamente el borrador guardado en este navegador.'))return;localStorage.removeItem(draftKey);hasUnsavedChanges=false;discardButton.hidden=true;setDraftState('Borrador descartado. El autoguardado sigue activo.')});
actaForm.addEventListener('submit',e=>{e.preventDefault();if(!validateActa())return;saveDraft(true);isSubmitting=true;saveButton.disabled=true;saveButton.textContent='Guardando acta...';actaForm.submit()});
window.addEventListener('beforeunload',e=>{if(hasUnsavedChanges&&!isSubmitting){e.preventDefault();e.returnValue=''}});
try{const stored=localStorage.getItem(draftKey);if(stored&&<?=json_encode($_SERVER['REQUEST_METHOD']!=='POST')?>)restoreDraft(JSON.parse(stored));else if(<?=json_encode($_SERVER['REQUEST_METHOD']==='POST')?>)saveDraft(false)}catch(e){setDraftState('No se pudo recuperar el borrador','error')}
document.querySelectorAll('.office').forEach(office=>office.open=false);
allDisks().forEach(disk=>disk.classList.add('collapsed'));
document.querySelectorAll('.file').forEach(file=>file.classList.add('collapsed'));
</script>
<script>
(() => {
 const dialog=document.getElementById('acta-related-dialog'),frame=document.getElementById('acta-related-frame');
 document.querySelectorAll('.acta-related-open').forEach(button=>button.addEventListener('click',()=>{
   saveDraft(false);
   document.getElementById('acta-related-title').textContent=button.dataset.title;
   frame.src=button.dataset.src;
   dialog.showModal();
 }));
 document.getElementById('acta-related-close').addEventListener('click',()=>dialog.close());
 dialog.addEventListener('close',()=>{frame.src='about:blank';});
 window.addEventListener('message',event=>{
   if(event.origin!==location.origin || event.source!==frame.contentWindow)return;
   if(['oficio.close','documento_recibido.close'].includes(event.data?.type)){dialog.close();return;}
   if(['oficio.saved','documento_recibido.saved'].includes(event.data?.type)){
     saveDraft(false);hasUnsavedChanges=false;location.reload();
   }
 });
})();
</script>

</body></html>
