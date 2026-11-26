(() => {
  'use strict';
  const context = JSON.parse(document.getElementById('itp-localization-options').textContent);
  const select = document.querySelector('#pb_localizacion .p-unit');
  const caseInput = document.getElementById('accidente_id');
  if (!select) return;
  const fill = (items, placeholder = 'Selecciona un vehículo o una persona…') => {
    select.replaceChildren(new Option(placeholder, ''));
    const groups = new Map();
    for (const item of items) {
      if (!groups.has(item.group)) {const group = document.createElement('optgroup'); group.label = item.group; groups.set(item.group, group); select.append(group);}
      groups.get(item.group).append(new Option(item.label, item.label.replace(/,/g, ' ')));
    }
  };
  fill(context.items);
  let request = 0;
  caseInput?.addEventListener('change', async () => {
    const current = ++request;
    fill([], caseInput.value ? 'Cargando involucrados…' : 'Primero selecciona un accidente');
    if (!caseInput.value) return;
    try {
      const response = await fetch('itp_localizacion_opciones.php?accidente_id=' + encodeURIComponent(caseInput.value), {headers: {'X-Requested-With': 'XMLHttpRequest'}});
      const result = await response.json();
      if (current !== request) return;
      if (!response.ok || !result.ok) throw new Error('No disponible');
      fill(result.items);
    } catch {if (current === request) fill([], 'No se pudieron cargar los involucrados');}
  });
})();
