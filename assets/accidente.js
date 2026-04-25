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

  const lugar = qs('[name="lugar"]');
  if(!lugar || !lugar.value.trim()){
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

window.abrirModalGeo = function(){ alert('No se pudo cargar el mapa de georreferencia.'); };
window.cerrarModalGeo = function(){};
window.usarGeoAccidente = function(){};
window.limpiarGeoAccidente = function(){};
window.__accidenteGeoDraftPoint = null;
window.abrirGeoExterno = function(ev){
  if(ev) ev.preventDefault();
  const latInput = qs('#latitud');
  const lngInput = qs('#longitud');
  const lat = latInput ? (latInput.value || '').trim() : '';
  const lng = lngInput ? (lngInput.value || '').trim() : '';
  const draft = window.__accidenteGeoDraftPoint;
  const finalLat = lat || (draft ? String(draft.lat) : '');
  const finalLng = lng || (draft ? String(draft.lng) : '');
  if(!finalLat || !finalLng){
    alert('Primero ingresa las coordenadas o marca un punto en el mapa.');
    return false;
  }
  window.open(`https://www.google.com/maps?q=${encodeURIComponent(`${finalLat},${finalLng}`)}`, '_blank', 'noopener');
  return false;
};

/* GEOREFERENCIA DEL ACCIDENTE */
window.initAccidenteGeoMap = function initAccidenteGeoMap(){
  const modal = qs('#modal-geo');
  const mapEl = qs('#geo-map');
  const latInput = qs('#latitud');
  const lngInput = qs('#longitud');
  const openBtn = qs('#btn-open-geo');
  const clearBtn = qs('#btn-clear-geo');
  const closeBtn = qs('#btn-close-geo');
  const cancelBtn = qs('#btn-cancel-geo');
  const useBtn = qs('#btn-use-geo');
  const searchInput = qs('#geo-search');
  const mapType = qs('#geo-map-type');
  const statusBox = qs('#geo-preview-status');
  const coordsText = qs('#geo-coords-text');
  const externalLink = qs('#geo-open-external');

  if(!modal || !mapEl || !latInput || !lngInput || !window.L){
    return;
  }

  const defaultCenter = {lat:-9.189967, lng:-75.015152};
  let map = null;
  let marker = null;
  let draftPoint = null;
  let activeLayer = null;
  let googleAutocompleteService = null;
  let googleGeocoder = null;
  let suggestItems = [];
  let suggestIndex = -1;
  let searchDebounce = null;
  let searchAbort = null;

  const limaBounds = {
    west: -77.25,
    north: -11.75,
    east: -76.75,
    south: -12.35,
  };

  const tileLayers = {
    hybrid: () => L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap',
      maxZoom: 19,
    }),
    satellite: () => L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      attribution: '&copy; Esri, Maxar, Earthstar Geographics',
      maxZoom: 19,
    }),
    roadmap: () => L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap',
      maxZoom: 19,
    }),
    terrain: () => L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenTopoMap, OpenStreetMap',
      maxZoom: 17,
    }),
  };

  function currentPoint(){
    const lat = parseFloat((latInput.value || '').replace(',', '.'));
    const lng = parseFloat((lngInput.value || '').replace(',', '.'));
    if(Number.isFinite(lat) && Number.isFinite(lng)){
      return {lat, lng};
    }
    return null;
  }

  function formatPoint(point){
    return `${point.lat.toFixed(6)}, ${point.lng.toFixed(6)}`;
  }

  function syncStatus(){
    const point = currentPoint();
    if(point){
      if(statusBox){
        statusBox.textContent = `Punto guardado: ${formatPoint(point)}. Este accidente ya queda listo para aparecer en el mapa global.`;
        statusBox.classList.add('is-ready');
      }
      if(coordsText){
        coordsText.textContent = `Coordenadas seleccionadas: ${formatPoint(point)}`;
      }
      if(externalLink){
        externalLink.href = `https://www.google.com/maps?q=${encodeURIComponent(`${point.lat},${point.lng}`)}`;
      }
    }else{
      if(statusBox){
        statusBox.textContent = 'Todavía no hay un punto georreferenciado para este accidente.';
        statusBox.classList.remove('is-ready');
      }
      if(coordsText){
        coordsText.textContent = 'Sin coordenadas seleccionadas.';
      }
      if(externalLink){
        externalLink.href = '#';
      }
    }
  }

  function syncBaseLayer(){
    if(!map){
      return;
    }
    if(activeLayer){
      map.removeLayer(activeLayer);
    }
    const key = mapType && tileLayers[mapType.value] ? mapType.value : 'hybrid';
    activeLayer = tileLayers[key]();
    activeLayer.on('tileerror', () => {
      if(key === 'satellite'){
        if(mapType) mapType.value = 'roadmap';
        syncBaseLayer();
        if(coordsText && (!window.__accidenteGeoDraftPoint)){
          coordsText.textContent = 'La capa satelital no respondió. Se cambió automáticamente a mapa vial.';
        }
      }
    });
    activeLayer.addTo(map);
  }

  function hasGooglePlaces(){
    return !!(window.google && google.maps && google.maps.places && google.maps.places.AutocompleteService);
  }

  function ensureGooglePlaces(){
    if(!hasGooglePlaces()){
      return false;
    }
    if(!googleAutocompleteService){
      googleAutocompleteService = new google.maps.places.AutocompleteService();
    }
    if(!googleGeocoder){
      googleGeocoder = new google.maps.Geocoder();
    }
    return true;
  }

  function ensureSuggestUi(){
    if(!searchInput){
      return null;
    }
    let wrap = searchInput.parentElement;
    if(!wrap || !wrap.classList.contains('geo-search-wrap')){
      wrap = document.createElement('div');
      wrap.className = 'geo-search-wrap';
      searchInput.parentNode.insertBefore(wrap, searchInput);
      wrap.appendChild(searchInput);
    }
    let list = wrap.querySelector('.geo-suggest-list');
    if(!list){
      list = document.createElement('ul');
      list.className = 'geo-suggest-list';
      list.hidden = true;
      wrap.appendChild(list);
    }
    return list;
  }

  function hideSuggestions(){
    const list = ensureSuggestUi();
    suggestItems = [];
    suggestIndex = -1;
    if(list){
      list.hidden = true;
      list.innerHTML = '';
    }
  }

  function normalizeQuery(query){
    const trimmed = (query || '').trim();
    if(!trimmed){
      return '';
    }
    if(/lima|per[uú]/i.test(trimmed)){
      return trimmed;
    }
    return `${trimmed}, Lima, Perú`;
  }

  function buildLocalSearchUrl(query, limit = 6){
    const url = new URL(window.location.href);
    url.searchParams.set('ajax', 'geo_search');
    url.searchParams.set('q', query);
    url.searchParams.set('limit', String(limit));
    return url.toString();
  }

  function buildSearchUrl(query, limit = 6){
    const url = new URL('https://nominatim.openstreetmap.org/search');
    url.searchParams.set('format', 'jsonv2');
    url.searchParams.set('addressdetails', '1');
    url.searchParams.set('limit', String(limit));
    url.searchParams.set('countrycodes', 'pe');
    url.searchParams.set('accept-language', 'es');
    url.searchParams.set('bounded', '1');
    url.searchParams.set('viewbox', `${limaBounds.west},${limaBounds.north},${limaBounds.east},${limaBounds.south}`);
    url.searchParams.set('q', normalizeQuery(query));
    return url.toString();
  }

  function labelFromSuggestion(item){
    if(item && item.provider === 'google'){
      return {
        primary: item.primary || item.description || 'Ubicación sugerida',
        secondary: item.secondary || '',
      };
    }
    const address = item.address || {};
    const primary = address.road || address.pedestrian || address.footway || address.cycleway || address.path || address.neighbourhood || address.suburb || address.industrial || item.name || item.display_name || 'Ubicación sugerida';
    const secondaryParts = [
      address.suburb,
      address.city_district,
      address.city || address.town || address.village,
      address.state,
    ].filter(Boolean);

    return {
      primary,
      secondary: secondaryParts.join(' · ') || item.display_name || '',
    };
  }

  function renderSuggestions(items){
    const list = ensureSuggestUi();
    if(!list){
      return;
    }

    suggestItems = items;
    suggestIndex = -1;
    list.innerHTML = '';

    if(!items.length){
      const empty = document.createElement('li');
      empty.className = 'geo-suggest-empty';
      empty.textContent = 'No se encontraron coincidencias cercanas en Lima.';
      list.appendChild(empty);
      list.hidden = false;
      return;
    }

    items.forEach((item, index) => {
      const li = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'geo-suggest-item';
      button.dataset.index = String(index);
      const labels = labelFromSuggestion(item);
      button.innerHTML = `<span class="geo-suggest-primary"></span><span class="geo-suggest-secondary"></span>`;
      button.querySelector('.geo-suggest-primary').textContent = labels.primary;
      button.querySelector('.geo-suggest-secondary').textContent = labels.secondary;
      button.addEventListener('click', () => {
        selectSuggestion(index);
      });
      li.appendChild(button);
      list.appendChild(li);
    });

    list.hidden = false;
  }

  function updateSuggestionActive(){
    const list = ensureSuggestUi();
    if(!list){
      return;
    }
    list.querySelectorAll('.geo-suggest-item').forEach((button, index) => {
      button.classList.toggle('is-active', index === suggestIndex);
    });
  }

  async function resolveSuggestionPoint(item){
    if(item && item.provider === 'google'){
      if(!ensureGooglePlaces() || !item.placeId){
        return null;
      }
      const result = await new Promise((resolve, reject) => {
        googleGeocoder.geocode({ placeId: item.placeId }, (responses, status) => {
          if(status !== 'OK' || !Array.isArray(responses) || !responses.length){
            reject(new Error('Google Maps no devolvió coordenadas para esa búsqueda.'));
            return;
          }
          resolve(responses[0]);
        });
      });
      const location = result && result.geometry ? result.geometry.location : null;
      if(!location){
        return null;
      }
      return {
        lat: location.lat(),
        lng: location.lng(),
        label: result.formatted_address || item.primary || '',
      };
    }

    const point = {
      lat: parseFloat(item.lat),
      lng: parseFloat(item.lon),
      label: labelFromSuggestion(item).primary || '',
    };
    if(!Number.isFinite(point.lat) || !Number.isFinite(point.lng)){
      return null;
    }
    return point;
  }

  async function applySuggestion(item){
    const point = await resolveSuggestionPoint(item);
    if(!point){
      return false;
    }
    draftPoint = point;
    window.__accidenteGeoDraftPoint = draftPoint;
    renderDraft(true);
    if(searchInput){
      searchInput.value = point.label || labelFromSuggestion(item).primary;
    }
    hideSuggestions();
    if(coordsText && point.label){
      coordsText.textContent = `${item && item.provider === 'google' ? 'Ubicación sugerida por Google Maps' : 'Ubicación sugerida'}: ${point.label}`;
    }
    return true;
  }

  async function selectSuggestion(index){
    if(index < 0 || index >= suggestItems.length){
      return false;
    }
    return applySuggestion(suggestItems[index]);
  }

  async function fetchSuggestions(query, limit = 6){
    try{
      const response = await fetch(buildLocalSearchUrl(query, limit), {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        }
      });
      const json = await response.json();
      if(response.ok && json && json.ok && Array.isArray(json.data)){
        return json.data;
      }
    }catch(error){
      console.warn('Geo local search fallback:', error);
    }

    const fetchOpenStreetSuggestions = async () => {
      if(searchAbort){
        searchAbort.abort();
      }
      searchAbort = new AbortController();
      const response = await fetch(buildSearchUrl(query, limit), {
        signal: searchAbort.signal,
        headers: {
          'Accept': 'application/json',
        }
      });
      const results = await response.json();
      if(!Array.isArray(results)){
        return [];
      }

      const unique = [];
      const seen = new Set();
      results.forEach((item) => {
        const key = `${item.lat},${item.lon},${item.display_name}`;
        if(seen.has(key)){
          return;
        }
        seen.add(key);
        unique.push(item);
      });
      return unique;
    };

    if(ensureGooglePlaces()){
      try{
        const predictions = await Promise.race([
          new Promise((resolve, reject) => {
            googleAutocompleteService.getPlacePredictions({
              input: normalizeQuery(query),
              componentRestrictions: { country: 'pe' },
            }, (items, status) => {
              if(status === google.maps.places.PlacesServiceStatus.ZERO_RESULTS){
                resolve([]);
                return;
              }
              if(status !== google.maps.places.PlacesServiceStatus.OK){
                reject(new Error('Google Maps no devolvió sugerencias en este momento.'));
                return;
              }
              resolve(Array.isArray(items) ? items : []);
            });
          }),
          new Promise((_, reject) => {
            window.setTimeout(() => reject(new Error('Google Maps tardó demasiado en responder.')), 1400);
          })
        ]);

        const mapped = predictions.slice(0, limit).map((item) => ({
          provider: 'google',
          placeId: item.place_id || '',
          description: item.description || '',
          primary: item.structured_formatting?.main_text || item.description || '',
          secondary: item.structured_formatting?.secondary_text || '',
        }));

        if(mapped.length){
          return mapped;
        }
      }catch(error){
        console.warn('Google Places fallback:', error);
      }
    }

    if(coordsText){
      coordsText.textContent = 'Google Maps no respondió. Probando búsqueda alternativa...';
    }

    return fetchOpenStreetSuggestions();
  }

  async function runSuggestionSearch(query, autoSelectFirst = false){
    const trimmed = (query || '').trim();
    if(trimmed.length < 2){
      hideSuggestions();
      return;
    }

    if(coordsText){
      coordsText.textContent = 'Buscando coincidencias en Lima...';
    }

    try{
      const items = await fetchSuggestions(trimmed, autoSelectFirst ? 1 : 6);
      if(autoSelectFirst){
        if(items.length){
          await applySuggestion(items[0]);
        }else if(coordsText){
          coordsText.textContent = 'No se encontró una ubicación con ese texto dentro de Lima.';
        }
        return;
      }
      renderSuggestions(items);
      if(!items.length && coordsText){
        coordsText.textContent = 'No se encontraron coincidencias cercanas en Lima.';
      }
    }catch(error){
      if(error && error.name === 'AbortError'){
        return;
      }
      console.error(error);
      hideSuggestions();
      if(coordsText){
        coordsText.textContent = 'No se pudo resolver la búsqueda en este momento.';
      }
    }
  }

  function ensureMap(){
    if(map){
      return;
    }

    map = L.map(mapEl, {
      center: [defaultCenter.lat, defaultCenter.lng],
      zoom: 6,
      zoomControl: true,
    });
    syncBaseLayer();

    marker = L.marker([defaultCenter.lat, defaultCenter.lng], {
      draggable: true,
    });

    map.on('click', (event) => {
      draftPoint = {
        lat: event.latlng.lat,
        lng: event.latlng.lng,
      };
      window.__accidenteGeoDraftPoint = draftPoint;
      renderDraft();
    });

    marker.on('dragend', (event) => {
      const point = event.target.getLatLng();
      draftPoint = {
        lat: point.lat,
        lng: point.lng,
      };
      window.__accidenteGeoDraftPoint = draftPoint;
      renderDraft();
    });

    if(searchInput){
      ensureSuggestUi();
      searchInput.addEventListener('input', () => {
        const query = (searchInput.value || '').trim();
        if(searchDebounce){
          clearTimeout(searchDebounce);
        }
        if(query.length < 2){
          hideSuggestions();
          return;
        }
        searchDebounce = setTimeout(() => {
          runSuggestionSearch(query, false);
        }, 260);
      });

      searchInput.addEventListener('keydown', (event) => {
        const listVisible = ensureSuggestUi() && !ensureSuggestUi().hidden && suggestItems.length > 0;
        if(event.key === 'ArrowDown' && listVisible){
          event.preventDefault();
          suggestIndex = Math.min(suggestIndex + 1, suggestItems.length - 1);
          updateSuggestionActive();
          return;
        }
        if(event.key === 'ArrowUp' && listVisible){
          event.preventDefault();
          suggestIndex = Math.max(suggestIndex - 1, 0);
          updateSuggestionActive();
          return;
        }
        if(event.key !== 'Enter'){
          return;
        }
        event.preventDefault();
        const query = (searchInput.value || '').trim();
        if(!query){
          hideSuggestions();
          return;
        }
        if(listVisible){
          if(suggestIndex >= 0){
            void selectSuggestion(suggestIndex);
          }else{
            void selectSuggestion(0);
          }
          return;
        }
        void runSuggestionSearch(query, true);
      });

      searchInput.addEventListener('blur', () => {
        window.setTimeout(() => {
          hideSuggestions();
        }, 180);
      });
    }
  }

  function renderDraft(focus = false){
    if(!map || !marker){
      return;
    }

    if(!draftPoint){
      window.__accidenteGeoDraftPoint = null;
      if(map.hasLayer(marker)){
        map.removeLayer(marker);
      }
      if(coordsText){
        coordsText.textContent = 'Sin coordenadas seleccionadas.';
      }
      return;
    }

    marker.setLatLng([draftPoint.lat, draftPoint.lng]);
    window.__accidenteGeoDraftPoint = draftPoint;
    if(!map.hasLayer(marker)){
      marker.addTo(map);
    }
    if(coordsText){
      coordsText.textContent = `Coordenadas seleccionadas: ${formatPoint(draftPoint)}`;
    }
    if(focus){
      map.setView([draftPoint.lat, draftPoint.lng], 18);
    }
  }

  function openModal(){
    modal.classList.add('active');
    ensureMap();
    const savedPoint = currentPoint();
    draftPoint = savedPoint || draftPoint || null;
    if(savedPoint){
      renderDraft(true);
    }else{
      renderDraft(false);
    }
    setTimeout(() => {
      map.invalidateSize();
      if(draftPoint){
        map.setView([draftPoint.lat, draftPoint.lng], map.getZoom() || 18);
      }else{
        map.setView([defaultCenter.lat, defaultCenter.lng], 6);
      }
    }, 120);
  }

  function closeModal(){
    modal.classList.remove('active');
  }

  function commitDraft(){
    if(!draftPoint){
      closeModal();
      return;
    }
    latInput.value = draftPoint.lat.toFixed(7);
    lngInput.value = draftPoint.lng.toFixed(7);
    syncStatus();
    closeModal();
  }

  function clearPoint(){
    draftPoint = null;
    window.__accidenteGeoDraftPoint = null;
    latInput.value = '';
    lngInput.value = '';
    if(map && marker && map.hasLayer(marker)){
      map.removeLayer(marker);
    }
    syncStatus();
  }

  function syncManualPoint(){
    const point = currentPoint();
    draftPoint = point;
    window.__accidenteGeoDraftPoint = point;
    if(map && marker){
      if(point){
        renderDraft(false);
      }else if(map.hasLayer(marker)){
        map.removeLayer(marker);
      }
    }
    syncStatus();
  }

  latInput.addEventListener('input', syncManualPoint);
  lngInput.addEventListener('input', syncManualPoint);
  openBtn?.addEventListener('click', openModal);
  clearBtn?.addEventListener('click', clearPoint);
  closeBtn?.addEventListener('click', closeModal);
  cancelBtn?.addEventListener('click', closeModal);
  useBtn?.addEventListener('click', commitDraft);
  mapType?.addEventListener('change', () => {
    syncBaseLayer();
    if(map && draftPoint){
      map.setView([draftPoint.lat, draftPoint.lng], map.getZoom());
    }
  });
  modal.addEventListener('click', (event) => {
    if(event.target === modal){
      closeModal();
    }
  });

  window.abrirModalGeo = function(){ openModal(); return false; };
  window.cerrarModalGeo = function(){ closeModal(); return false; };
  window.usarGeoAccidente = function(){ commitDraft(); return false; };
  window.limpiarGeoAccidente = function(){ clearPoint(); return false; };
  window.abrirGeoExterno = function(ev){
    if(ev) ev.preventDefault();
    const point = currentPoint();
    const draft = window.__accidenteGeoDraftPoint;
    const finalPoint = point || draft;
    if(!finalPoint){
      alert('Primero ingresa las coordenadas o marca un punto en el mapa.');
      return false;
    }
    window.open(`https://www.google.com/maps?q=${encodeURIComponent(`${finalPoint.lat},${finalPoint.lng}`)}`, '_blank', 'noopener');
    return false;
  };

  syncStatus();
};
