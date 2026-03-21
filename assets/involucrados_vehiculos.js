/* ---------- Utils DOM ---------- */
function $(id){ return document.getElementById(id); }
function q(sel,root=document){ return root.querySelector(sel); }
function qa(sel,root=document){ return Array.from(root.querySelectorAll(sel)); }

/* Modal open/close */
qa('[data-open]').forEach(b=>b.addEventListener('click', ()=>{
  const id=b.dataset.open;
  $(id).classList.add('open');
  if(id==='mdVehiculo'){
    const qv = ($( 'qplaca').value||'').trim().toUpperCase();
    if(qv) $('nv_placa').value = qv;
    $('nv_placa').focus();
  }
}));
qa('[data-close]').forEach(b=>b.addEventListener('click', ()=> $(b.dataset.close).classList.remove('open')));

/* ---------- Controles principales ---------- */
const selVeh  = $('vehiculo_id');
const resumen = $('veh_resumen');
const inputNext = $('next');

function actualizarResumen(){
  const opt = selVeh.options[selVeh.selectedIndex];
  resumen.value = (opt && opt.value) ? opt.textContent : '';
}
selVeh.addEventListener('change', actualizarResumen);

/* Buscar por placa */
$('btnBuscarPlaca').addEventListener('click', async ()=>{
  const qRaw = $('qplaca').value.trim();
  const q = qRaw.toUpperCase();
  $('qplaca').value = q;
  selVeh.innerHTML = '<option value="">— Selecciona de la búsqueda —</option>';
  resumen.value = '';
  if (!q){ alert('Ingresa una placa (o parte) para buscar.'); return; }
  const r = await fetch(`involucrados_vehiculos_nuevo.php?ajax=buscar_vehiculos&q=${encodeURIComponent(q)}`);
  const data = await r.json();
  if (data.length===0){
    if (confirm('No se encontraron vehículos. ¿Deseas registrarlo ahora?')) {
      $('nv_placa').value = q;
      $('mdVehiculo').classList.add('open');
      $('nv_placa').focus();
    }
    return;
  }
  data.forEach(v=>{
    const opt = document.createElement('option');
    opt.value = v.id; opt.textContent = v.texto || v.placa || ('ID '+v.id);
    selVeh.appendChild(opt);
  });
  selVeh.selectedIndex = 1; actualizarResumen();
});

/* ================== Encadenados ================== */
async function fillSelect(url, params, selectEl){
  selectEl.innerHTML = '<option value="">—</option>';
  const usp = new URLSearchParams(params);
  const r = await fetch(`${url}?${usp.toString()}`);
  const data = await r.json();
  data.forEach(row=>{
    const opt = document.createElement('option'); opt.value=row.id; opt.textContent=row.nombre; selectEl.appendChild(opt);
  });
}

const selCat  = $('nv_categoria_id');
const selTipo = $('nv_tipo_id');
const selCarr = $('nv_carroceria_id');
const selMarca= $('nv_marca_id');
const selMod  = $('nv_modelo_id');

selCat.addEventListener('change', async ()=>{
  selCarr.innerHTML='<option value="">—</option>';
  if (!selCat.value){ selTipo.innerHTML='<option value="">—</option>'; return; }
  await fillSelect('involucrados_vehiculos_nuevo.php', {ajax:'tipos_por_categoria', categoria_id: selCat.value}, selTipo);
});
selTipo.addEventListener('change', async ()=>{
  if (!selTipo.value){ selCarr.innerHTML='<option value="">—</option>'; return; }
  await fillSelect('involucrados_vehiculos_nuevo.php', {ajax:'carrocerias_por_tipo', tipo_id: selTipo.value}, selCarr);
});
selMarca.addEventListener('change', async ()=>{
  if (!selMarca.value){ selMod.innerHTML='<option value="">—</option>'; return; }
  await fillSelect('involucrados_vehiculos_nuevo.php', {ajax:'modelos_por_marca', marca_id: selMarca.value}, selMod);
});

/* ================== Alta RÁPIDA catálogos ================== */
async function postForm(url, data){ 
  const r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body: (new URLSearchParams(data)).toString()});
  return r.json();
}

$('btnSaveCat').addEventListener('click', async ()=>{
  const codigo=$('cat_codigo').value.trim().toUpperCase();
  const desc=$('cat_desc').value.trim();
  const msg=$('cat_msg'); msg.textContent='Guardando...';
  const res=await postForm('involucrados_vehiculos_nuevo.php?ajax=crear_categoria',{codigo:codigo,descripcion:desc});
  if(res.ok){
    const opt=new Option(res.nombre,res.id,true,true);
    selCat.add(opt); selCat.dispatchEvent(new Event('change'));
    $('mdCategoria').classList.remove('open');
  }else{ msg.textContent='Error: '+res.error; }
});

$('btnSaveTipo').addEventListener('click', async ()=>{
  const categoria_id=$('t_cat').value;
  const codigo=$('t_codigo').value.trim().toUpperCase();
  const nombre=$('t_nombre').value.trim();
  const descripcion=$('t_desc').value.trim();
  const msg=$('t_msg'); msg.textContent='Guardando...';
  const res=await postForm('involucrados_vehiculos_nuevo.php?ajax=crear_tipo',{categoria_id,codigo,nombre,descripcion});
  if(res.ok){
    const opt=new Option(res.nombre,res.id,true,true);
    selTipo.add(opt); selTipo.dispatchEvent(new Event('change'));
    $('mdTipo').classList.remove('open');
  }else{ msg.textContent='Error: '+res.error; }
});

$('btnSaveCarroceria').addEventListener('click', async ()=>{
  const tipo_id = $('c_tipo').value || selTipo.value;
  const nombre = $('c_nombre').value.trim();
  const descripcion = $('c_desc').value.trim();
  const msg=$('c_msg'); msg.textContent='Guardando...';
  if(!tipo_id){ msg.textContent='Selecciona un tipo.'; return; }
  const res=await postForm('involucrados_vehiculos_nuevo.php?ajax=crear_carroceria',{tipo_id,nombre,descripcion});
  if(res.ok){
    const opt=new Option(res.nombre,res.id,true,true);
    selCarr.add(opt); $('mdCarroceria').classList.remove('open');
  }else{ msg.textContent='Error: '+res.error; }
});

$('btnSaveMarca').addEventListener('click', async ()=>{
  const nombre=$('m_nombre').value.trim();
  const pais_origen=$('m_pais').value.trim();
  const msg=$('m_msg'); msg.textContent='Guardando...';
  const res=await postForm('involucrados_vehiculos_nuevo.php?ajax=crear_marca',{nombre,pais_origen});
  if(res.ok){
    const opt=new Option(res.nombre,res.id,true,true);
    selMarca.add(opt); selMarca.dispatchEvent(new Event('change'));
    $('mdMarca').classList.remove('open');
  }else{ msg.textContent='Error: '+res.error; }
});

$('btnSaveModelo').addEventListener('click', async ()=>{
  const marca_id = $('mo_marca').value || selMarca.value;
  const nombre = $('mo_nombre').value.trim();
  const msg=$('mo_msg'); msg.textContent='Guardando...';
  if(!marca_id){ msg.textContent='Selecciona una marca.'; return; }
  const res=await postForm('involucrados_vehiculos_nuevo.php?ajax=crear_modelo',{marca_id,nombre});
  if(res.ok){
    const opt=new Option(res.nombre,res.id,true,true);
    selMod.add(opt); $('mdModelo').classList.remove('open');
  }else{ msg.textContent='Error: '+res.error; }
});

/* ================== Guardar NUEVO vehículo ================== */
function safeVal(id){ const el=$(id); return el ? el.value.trim() : ''; }

$('btnGuardarNV').addEventListener('click', async ()=>{
  const payload={
    placa:safeVal('nv_placa').toUpperCase(),
    serie_vin:safeVal('nv_serie_vin'),        // puede no existir en HTML; se manda ''
    nro_motor:safeVal('nv_nro_motor'),        // puede no existir en HTML; se manda ''
    categoria_id:selCat.value,tipo_id:selTipo.value,carroceria_id:selCarr.value,
    marca_id:selMarca.value,modelo_id:selMod.value,
    anio:safeVal('nv_anio'),
    color:safeVal('nv_color'),
    largo_mm:safeVal('nv_largo_mm'),
    ancho_mm:safeVal('nv_ancho_mm'),
    alto_mm:safeVal('nv_alto_mm'),
    notas:safeVal('nv_notas')
  };
  const msg=$('nv_msg'); msg.textContent='Guardando...';
  const res=await postForm('involucrados_vehiculos_nuevo.php?ajax=crear_vehiculo',payload);
  if(res.ok){
    const v=res.vehiculo; const opt=new Option(v.texto,v.id,true,true);
    selVeh.add(opt); actualizarResumen(); $('mdVehiculo').classList.remove('open');
  }else{ msg.textContent='Error: '+res.error; }
});

/* Confirmación para "Agregar siguiente" */
$('btnNext').addEventListener('click',(ev)=>{
  if(!confirm('¿Agregar este vehículo y registrar el siguiente?')) ev.preventDefault();
  else $('next').value='1';
});

/* Modal Carrocería: precarga tipos según categoría elegida en modal vehículo */
q('[data-open="mdCarroceria"]').addEventListener('click', async ()=>{
  const tSel = $('c_tipo');
  tSel.innerHTML='<option value="">—</option>';
  if(selCat.value){
    await fillSelect('involucrados_vehiculos_nuevo.php', {ajax:'tipos_por_categoria', categoria_id: selCat.value}, tSel);
    if(selTipo.value) tSel.value = selTipo.value;
  }
});

/* Modal Modelo: sincroniza marca elegida */
q('[data-open="mdModelo"]').addEventListener('click', ()=>{
  const mm = $('mo_marca');
  if(selMarca.value) mm.value = selMarca.value;
});