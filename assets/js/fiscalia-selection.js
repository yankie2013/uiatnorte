(() => {
  const data = document.getElementById('fiscalia-selection-data');
  if (!data) return;
  const rows = JSON.parse(data.textContent);
  const get = id => document.getElementById(id);
  const catalog = get('fiscalia');
  const dispatch = get('fiscalia-despacho');
  const number = get('fiscalia-numero');
  const office = get('fiscalia-nombre');
  const fiscalDistrict = get('fiscalia-distrito');
  const summary = get('fiscalia-resumen');
  const offices = {
    transito: ['Fiscalía Corporativa de Tránsito y Seguridad Vial', 'Lima Norte'],
    puente: ['Fiscalía Provincial Penal Corporativa de Puente Piedra', 'Lima Noroeste'],
    santa: ['Fiscalía Provincial Penal Corporativa de Santa Rosa - Ancón', 'Lima Noroeste'],
    canta: ['Fiscalía Provincial Penal de Canta', 'Lima Norte']
  };
  const dispatchLabels = ['Sin despacho especificado', 'Primer Despacho', 'Segundo Despacho', 'Tercer Despacho', 'Cuarto Despacho'];
  let currentOffice = null;
  let candidates = [];
  const setCatalog = value => {
    const previous = catalog.value;
    catalog.value = String(value || '');
    if (catalog.value !== previous) catalog.dispatchEvent(new Event('change', {bubbles:true}));
    document.querySelector('[data-modal="modal-fiscal"]').disabled = !catalog.value;
  };
  const fill = (select, values, label, selected, placeholder) => {
    select.replaceChildren();
    if (placeholder) select.add(new Option(placeholder, ''));
    values.forEach(value => select.add(new Option(label(value), String(value))));
    select.value = selected !== undefined && values.includes(selected) ? String(selected) : (placeholder ? '' : String(values[0] ?? ''));
  };
  const savedNumber = get('fiscalia-numero-valor');
  if (['1','2','3'].includes(savedNumber.value)) number.value = savedNumber.value;
  const syncNumber = () => { savedNumber.value = number.value; };
  syncNumber();
  const selectOffice = () => {
    syncNumber();
    if (!currentOffice || dispatch.value === '') {
      if (currentOffice) {
        setCatalog('');
        summary.textContent = 'Selecciona el despacho para visualizar el nombre completo.';
      }
      return;
    }
    // El número es independiente: el vínculo con los fiscales depende del despacho.
    const matching = candidates.filter(row => row.selection.dispatch === Number(dispatch.value));
    const row = matching.find(row => row.selection.number === Number(number.value || 0)) || matching[0];
    setCatalog(row?.id);
    const prefix = Number(dispatch.value) ? `${dispatchLabels[Number(dispatch.value)]} de la ` : '';
    const numeral = savedNumber.value ? `${savedNumber.value}° ` : '';
    summary.textContent = `${prefix}${numeral}${offices[currentOffice][0]} del Distrito Fiscal de ${offices[currentOffice][1]}`;
  };
  const refresh = () => {
    const dep = get('dep').value, prov = get('prov').value, dist = get('dist').value;
    currentOffice = null;
    if (dep === '15' && prov === '04' && ['01','02','03','04','05','06','07'].includes(dist)) currentOffice = 'canta';
    if (dep === '15' && prov === '01') {
      if (['12','35','10','17','06'].includes(dist)) currentOffice = 'transito';
      if (dist === '25') currentOffice = 'puente';
      if (['02','39'].includes(dist)) currentOffice = 'santa';
    }
    const legacy = !!dist && !currentOffice;
    get('fiscalia-catalogo-box').hidden = !legacy;
    ['fiscalia-despacho-box','fiscalia-nombre-box','fiscalia-distrito-box'].forEach(id => get(id).hidden = legacy);
    if (!currentOffice) {
      candidates = [];
      dispatch.disabled = office.disabled = true;
      fill(dispatch, [], String, undefined, '-- Selecciona distrito --');
      fill(office, [], String, undefined, '-- Selecciona distrito --');
      fiscalDistrict.value = '';
      setCatalog('');
      summary.textContent = legacy ? 'Selecciona una fiscalía del catálogo.' : 'Selecciona el distrito y los datos de la fiscalía para visualizar su nombre completo.';
      return;
    }
    const previous = rows.find(row => String(row.id) === catalog.value)?.selection;
    candidates = rows.filter(row => row.selection?.office === currentOffice);
    office.replaceChildren(new Option(offices[currentOffice][0], currentOffice));
    office.disabled = false;
    fiscalDistrict.value = `Distrito Fiscal de ${offices[currentOffice][1]}`;
    const dispatches = [...new Set(candidates.map(row => row.selection.dispatch))].sort((a,b) => a-b);
    const keep = previous?.office === currentOffice;
    fill(dispatch, dispatches, value => dispatchLabels[value], keep ? previous.dispatch : undefined, dispatches.length > 1 ? '-- Selecciona --' : '');
    dispatch.disabled = dispatches.length <= 1;
    selectOffice();
  };
  dispatch.addEventListener('change', selectOffice);
  number.addEventListener('change', () => {
    const previous = catalog.value;
    selectOffice();
    if (previous === catalog.value && ['puente','santa'].includes(currentOffice)) catalog.dispatchEvent(new Event('change', {bubbles:true}));
  });
  ['dep','prov','dist'].forEach(id => get(id).addEventListener('change', () => queueMicrotask(refresh)));
  catalog.addEventListener('change', () => {
    if (!currentOffice) summary.textContent = catalog.selectedOptions[0]?.textContent || 'Selecciona una fiscalía.';
  });
  document.addEventListener('fiscalia-catalog-added', event => {
    const row = event.detail;
    if (!rows.some(item => String(item.id) === String(row.id))) {
      rows.push(row);
      catalog.add(new Option(row.nombre, String(row.id)));
    }
    refresh();
  });
  document.addEventListener('DOMContentLoaded', refresh);
})();
