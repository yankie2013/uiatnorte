/* ===== Utilidades DOM ===== */
function $(id){ return document.getElementById(id); }
function q(sel, root=document){ return root.querySelector(sel); }
function qa(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }

/* ===== Modales ===== */
qa('[data-close]').forEach(btn=>{
  btn.addEventListener('click', ()=> $(btn.dataset.close).classList.remove('open'));
});

qa('[data-open="mdPersona"]').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.getAttribute('data-persona-id');
    if(!id) return;
    $('mdPersona').classList.add('open');
    const cont = $('mp_content');
    cont.innerHTML = '<div class="muted">Cargando…</div>';
    try{
      const r = await fetch(`Dato_General_accidente.php?ajax=persona_detalle&id=${encodeURIComponent(id)}`);
      const j = await r.json();
      if(j.ok && j.data){
        cont.innerHTML = renderPersona(j.data);
      }else{
        cont.innerHTML = '<div class="muted">No se pudo cargar el detalle.</div>';
      }
    }catch(e){
      cont.innerHTML = '<div class="muted">Error al cargar.</div>';
    }
  });
});

qa('[data-open="mdVehiculo"]').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.getAttribute('data-vehiculo-id');
    if(!id) return;
    $('mdVehiculo').classList.add('open');
    const cont = $('mv_content');
    cont.innerHTML = '<div class="muted">Cargando…</div>';
    try{
      const r = await fetch(`Dato_General_accidente.php?ajax=vehiculo_detalle&id=${encodeURIComponent(id)}`);
      const j = await r.json();
      if(j.ok && j.data){
        cont.innerHTML = renderVehiculo(j.data);
      }else{
        cont.innerHTML = '<div class="muted">No se pudo cargar el detalle.</div>';
      }
    }catch(e){
      cont.innerHTML = '<div class="muted">Error al cargar.</div>';
    }
  });
});

/* ===== Render detalle Persona ===== */
function renderPersona(p){
  const nombre = (p.nombre_completo || '').trim();
  const rows = [
    ['Nombres y Apellidos', nombre],
    ['Edad', nv(p.edad)],
    ['DNI', nv(p.dni)],
    ['Teléfono', nv(p.telefono)],
    ['Email', nv(p.email)],
    ['Dirección', nv(p.direccion)],
    ['Estado Civil', nv(p.estado_civil)],
    ['Ocupación', nv(p.ocupacion)],
    ['Licencia de Conducir', nv(p.licencia)],
    ['Observaciones', nv(p.observaciones)]
  ];
  return dl(rows);
}

/* ===== Render detalle Vehículo ===== */
function renderVehiculo(v){
  const cat = [v.cat_codigo, v.cat_desc].filter(Boolean).join(' – ');
  const mm  = [v.marca_nombre, v.modelo_nombre].filter(Boolean).join(' ');
  const dims = [
    v.largo_mm ? `Largo: ${v.largo_mm}` : '',
    v.ancho_mm ? `Ancho: ${v.ancho_mm}` : '',
    v.alto_mm  ? `Alto: ${v.alto_mm}`  : ''
  ].filter(Boolean).join('  •  ');

  const rows = [
    ['Placa', nv(v.placa)],
    ['Marca / Modelo', nv(mm)],
    ['Color', nv(v.color)],
    ['Año', nv(v.anio)],
    ['Tipo de vehículo', nv(v.tipo_nombre)],
    ['Categoría', nv(cat)],
    ['Serie VIN', nv(v.serie_vin)],
    ['Nro. Motor', nv(v.nro_motor)],
    ['Dimensiones', nv(dims)],
    ['Notas', nv(v.notas)]
  ];
  return dl(rows);
}

/* ===== Helpers render ===== */
function nv(x){ return (x===null || x===undefined || String(x).trim()==='') ? '—' : String(x); }
function dl(rows){
  return `
    <div class="dl">
      ${rows.map(([k,v])=> `
        <div class="k">${esc(k)}</div>
        <div class="v">${esc(v)}</div>
      `).join('')}
    </div>
  `;
}
function esc(s){ return String(s).replace(/[&<>"']/g, (m)=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }