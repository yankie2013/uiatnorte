function qs(s,root=document){return root.querySelector(s)}
function qsa(s,root=document){return Array.from(root.querySelectorAll(s))}
qsa('.plus').forEach(b=>b.addEventListener('click',()=>abrirModal(b.dataset.modal)));
function abrirModal(id){qs('#'+id).classList.add('active')}
function cerrarModal(id){qs('#'+id).classList.remove('active')}

function cerrarModalXL(id){
  const m = qs('#'+id); if(!m) return;
  m.classList.remove('active');
  const fr = m.querySelector('iframe'); if(fr) fr.src='about:blank';
  const ld = m.querySelector('.loader'); if(ld) ld.classList.remove('hide');
}

/* Helpers select */
function resetSelect(sel){ if(!sel) return; sel.innerHTML='<option value="" disabled selected>-- Selecciona --</option>'; }
function lockComisaria(lock=true){
  const s=qs('#comisaria'); if(!s) return;
  if(lock){ s.value=''; s.setAttribute('disabled','disabled'); s.setCustomValidity('Selecciona una Comisaría'); }
  else{ s.removeAttribute('disabled'); s.setCustomValidity(''); }
}
document.addEventListener('DOMContentLoaded', ()=>{ lockComisaria(true); });

/* Participantes en modal (iframe) */
document.addEventListener('DOMContentLoaded', ()=>{
  qsa('.pillbtn[data-modal]').forEach(a=>{
    a.addEventListener('click', (ev)=>{
      ev.preventDefault();
      const modalId = a.getAttribute('data-modal');
      const src     = a.getAttribute('data-src');
      const m  = qs('#'+modalId);
      const fr = m ? m.querySelector('iframe') : null;
      const ld = m ? m.querySelector('.loader') : null;
      if(!fr || !src) return;

      if(ld) ld.classList.remove('hide');
      fr.onload = ()=>{ if(ld) ld.classList.add('hide'); };
      fr.src = src;
      m.classList.add('active');

      setTimeout(()=>{ if(ld && !ld.classList.contains('hide')) window.open(src, '_blank'); }, 6000);
    });
  });
});

/* Crear catálogos y fiscal */
async function crearBasico(ev,tipo){
  ev.preventDefault();
  const fd=new FormData(ev.target);
  if (tipo === 'comisaria') {
    fd.append('cod_dep',  qs('#dep').value || '');
    fd.append('cod_prov', qs('#prov').value || '');
    fd.append('cod_dist', qs('#dist').value || '');
  }
  const r=await fetch(`?ajax=create&type=${encodeURIComponent(tipo)}`,{method:'POST',body:fd});
  const j=await r.json();
  if(j.ok){
    if (j.type==='modalidad') {
      const c=document.createElement('label');
      c.className='option-card'; c.dataset.kind='mod'; c.dataset.text=(j.label||'').toLowerCase();
      c.innerHTML = `<input type="checkbox" name="modalidad_ids[]" value="${j.id}" checked>
                     <span class="check"></span><span class="text">${j.label}</span>`;
      qs('#grid-mod').prepend(c);
      /* NUEVO: enganchar evento y reflejar en resumen (orden por clic) */
      registerOptionCard(c);
      if(!modOrder.includes(j.label)) modOrder.push(j.label);
      updateSummary('mod');

    } else if (j.type==='consecuencia') {
      const c=document.createElement('label');
      c.className='option-card'; c.dataset.kind='con'; c.dataset.text=(j.label||'').toLowerCase();
      c.innerHTML = `<input type="checkbox" name="consecuencia_ids[]" value="${j.id}" checked>
                     <span class="check"></span><span class="text">${j.label}</span>`;
      qs('#grid-con').prepend(c);
      registerOptionCard(c);
      if(!conOrder.includes(j.label)) conOrder.push(j.label);
      updateSummary('con');

    } else if (j.type==='comisaria') {
      const sel = qs('#comisaria');
      lockComisaria(false);
      const o=document.createElement('option');
      o.value=j.id; o.textContent=j.label;
      sel.appendChild(o);
      sel.value=j.id;
    } else if (j.type==='fiscalia') {
      const sel=qs('#fiscalia'); const o=document.createElement('option'); o.value=j.id; o.textContent=j.label; sel.appendChild(o); sel.value=j.id;
      cargarFiscales();
    }
    ev.target.reset(); cerrarModal('modal-'+tipo);
  } else {
    alert(j.msg||'No se pudo crear');
  }
  return false;
}

async function crearFiscal(ev){
  ev.preventDefault();
  const fiscaliaSel = qs('#fiscalia').value;
  if(!fiscaliaSel){ alert('Primero selecciona una Fiscalía.'); return false; }
  const fd=new FormData(ev.target); fd.append('fiscalia_id', fiscaliaSel);
  const r=await fetch(`?ajax=create&type=fiscal`,{method:'POST',body:fd});
  const j=await r.json();
  if(j.ok){
    const sel=qs('#fiscal'); const o=document.createElement('option'); o.value=j.id; o.textContent=j.label;
    sel.appendChild(o); sel.value=j.id; ev.target.reset(); cerrarModal('modal-fiscal');
    actualizarTelefonoFiscal();
  } else alert(j.msg||'No se pudo crear');
  return false;
}

/* Ubigéo */
qs('#dep').addEventListener('change', async (e)=>{
  const dep=e.target.value;
  resetSelect(qs('#prov')); resetSelect(qs('#dist')); resetSelect(qs('#comisaria')); lockComisaria(true);
  if(!dep) return;
  const r=await fetch(`?ajax=prov&dep=${encodeURIComponent(dep)}`); const j=await r.json();
  j.data.forEach(x=>{ const o=document.createElement('option'); o.value=x.cod_prov; o.textContent=x.nombre; qs('#prov').appendChild(o); });
});
qs('#prov').addEventListener('change', async (e)=>{
  const dep=qs('#dep').value, prov=e.target.value;
  resetSelect(qs('#dist')); resetSelect(qs('#comisaria')); lockComisaria(true);
  if(!dep||!prov) return;
  const r=await fetch(`?ajax=dist&dep=${encodeURIComponent(dep)}&prov=${encodeURIComponent(prov)}`); const j=await r.json();
  j.data.forEach(x=>{ const o=document.createElement('option'); o.value=x.cod_dist; o.textContent=x.nombre; qs('#dist').appendChild(o); });
});
qs('#dist').addEventListener('change', async (e)=>{
  const dep=qs('#dep').value, prov=qs('#prov').value, dist=e.target.value;
  resetSelect(qs('#comisaria')); lockComisaria(true);
  if(!dep||!prov||!dist) return;
  try{
    const r=await fetch(`?ajax=comisarias_dist&dep=${encodeURIComponent(dep)}&prov=${encodeURIComponent(prov)}&dist=${encodeURIComponent(dist)}`);
    const j=await r.json();
    const sel=qs('#comisaria');
    if(j.ok){
      if(j.data && j.data.length){
        j.data.forEach(c=>{ const o=document.createElement('option'); o.value=c.id; o.textContent=c.nombre; sel.appendChild(o); });
      }else{
        const o=document.createElement('option');
        o.value=""; o.disabled=true; o.textContent='(No hay comisarías mapeadas; usa ＋ para crear)';
        sel.appendChild(o);
      }
      lockComisaria(false);
    }else{
      lockComisaria(true);
    }
  }catch(err){
    console.error(err); lockComisaria(true);
  }
});

/* Fiscales dependientes y teléfono */
qs('#fiscalia').addEventListener('change', cargarFiscales);
qs('#fiscal').addEventListener('change', actualizarTelefonoFiscal);

async function cargarFiscales(){
  const fid = qs('#fiscalia').value;
  const sel=qs('#fiscal');
  sel.innerHTML = '<option value="" disabled selected>-- Selecciona (según fiscalía) --</option>';
  qs('#fiscal_tel').value='';
  if(!fid) return;
  const r=await fetch(`?ajax=fiscales&fiscalia_id=${encodeURIComponent(fid)}`);
  const j=await r.json();
  j.data.forEach(x=>{ const o=document.createElement('option'); o.value=x.id; o.textContent=x.nombre; sel.appendChild(o); });
}
async function actualizarTelefonoFiscal(){
  const f=qs('#fiscal').value;
  qs('#fiscal_tel').value='';
  if(!f) return;
  const r=await fetch(`?ajax=fiscal_info&fiscal_id=${encodeURIComponent(f)}`);
  const j=await r.json();
  if(j.ok && j.data){ qs('#fiscal_tel').value = j.data.telefono || ''; }
}

/* Filtro de tarjetas (no toca orden) */
function filterOptions(kind, q){
  q = (q||'').toLowerCase().trim();
  document.querySelectorAll('.option-card[data-kind="'+kind+'"]').forEach(el=>{
    const txt = el.dataset.text || '';
    el.style.display = txt.includes(q) ? '' : 'none';
  });
}

/* =======================
   RESUMEN EN ORDEN DE CLIC
   ======================= */
const modOrder = [];   // Modalidades según clic
const conOrder = [];   // Consecuencias según clic

function summaryInput(kind){
  return (kind==='mod' ? document.getElementById('summary-mod')
                       : document.getElementById('summary-con'));
}
function formatSummaryList(items){
  const values = (items || []).filter(Boolean);
  if(values.length === 0) return '';
  if(values.length === 1) return values[0];
  if(values.length === 2) return values[0] + ' y ' + values[1];
  return values.slice(0, -1).join(', ') + ' y ' + values[values.length - 1];
}
function updateSummary(kind){
  const arr = (kind==='mod' ? modOrder : conOrder);
  const input = summaryInput(kind);
  if(!input) return;
  input.value = formatSummaryList(arr);
  if(!arr.length){ input.value=''; input.placeholder='Selecciona opciones…'; }
}
function onOptionChange(ev){
  const chk = ev.target;
  if(!chk || chk.type!=='checkbox') return;
  const card = chk.closest('.option-card');
  const kind = card.dataset.kind;
  const name = card.querySelector('.text').textContent.trim();
  const arr  = (kind==='mod' ? modOrder : conOrder);

  if(chk.checked){
    if(!arr.includes(name)) arr.push(name);
  }else{
    const i = arr.indexOf(name);
    if(i>-1) arr.splice(i,1);
  }
  updateSummary(kind);
}
function registerOptionCard(card){
  const chk = card.querySelector('input[type="checkbox"]');
  if(chk){ chk.addEventListener('change', onOptionChange); }
}

document.addEventListener('DOMContentLoaded', ()=>{
  // enganchar todos
  document.querySelectorAll('.option-card').forEach(registerOptionCard);
  // reconstruir si ya hay checks (reenvío / estado previo)
  document.querySelectorAll('.option-card input[type="checkbox"]:checked').forEach(ch=>{
    const card = ch.closest('.option-card');
    const kind = card.dataset.kind;
    const name = card.querySelector('.text').textContent.trim();
    const arr  = (kind==='mod' ? modOrder : conOrder);
    if(!arr.includes(name)) arr.push(name);
  });
  updateSummary('mod'); updateSummary('con');
});

// “Todos” respetando orden de clic
function toggleAll(kind, checked){
  const arr = (kind==='mod' ? modOrder : conOrder);
  const cards = document.querySelectorAll('.option-card[data-kind="'+kind+'"] input[type="checkbox"]');
  cards.forEach(ch=>{
    if (ch.offsetParent===null) return; // solo visibles si filtras
    const name = ch.closest('.option-card').querySelector('.text').textContent.trim();
    if(checked){
      ch.checked = true;
      if(!arr.includes(name)) arr.push(name);
    }else{
      ch.checked = false;
      const i = arr.indexOf(name);
      if(i>-1) arr.splice(i,1);
    }
  });
  updateSummary(kind);
}

/* VALIDACIÓN reforzada */
function validarForm(){
  const dep = qs('#dep'), prov = qs('#prov'), dist = qs('#dist'), comi = qs('#comisaria');

  if(!qs('[name="sidpol"]').value.trim()){
    alert('Completa SIDPOL'); return false;
  }
  if(!qs('[name="lugar"]').value.trim()){
    alert('Completa el Lugar del hecho'); return false;
  }
  if(!dep.value){ dep.setCustomValidity('Selecciona un Departamento'); dep.reportValidity(); return false; }
  if(!prov.value){ prov.setCustomValidity('Selecciona una Provincia');  prov.reportValidity(); return false; }
  if(!dist.value){ dist.setCustomValidity('Selecciona un Distrito');    dist.reportValidity(); return false; }

  if(comi.hasAttribute('disabled')){ comi.removeAttribute('disabled'); }
  if(!comi.value){ comi.setCustomValidity('Selecciona una Comisaría'); comi.reportValidity(); return false; }

  if(!qs('[name="fecha_accidente"]').value){
    alert('Registra fecha y hora del accidente'); return false;
  }
  if(document.querySelectorAll('input[name="modalidad_ids[]"]:checked').length===0){
    alert('Selecciona al menos una Modalidad'); return false;
  }
  if(document.querySelectorAll('input[name="consecuencia_ids[]"]:checked').length===0){
    alert('Selecciona al menos una Consecuencia'); return false;
  }
  [dep,prov,dist,comi].forEach(el=>el.setCustomValidity(''));
  return true;
}
