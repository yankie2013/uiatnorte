(() => {
  'use strict';
  const context = JSON.parse(document.getElementById('report-analysis-context').textContent);
  const text = (key, label) => ({key, label});
  const person = {key: 'involucrado_id', label: 'Involucrado y rol en el accidente', options: context.people};
  const choice = (key, label, values) => ({key, label, options: values.map(value => ({id: value, label: value}))});
  const groups = {
    velocidad: [
      ['riesgos', 'Condiciones de riesgo', 'Añadir condición de riesgo', [text('texto', 'Condición de riesgo')]],
      ['parrafos', 'Párrafos del análisis', 'Añadir párrafo', [text('texto', 'Párrafo')]],
      ['estimaciones', 'Estimación por vehículo', 'Añadir estimación', [
        {key: 'vehiculo_id', label: 'Vehículo involucrado', options: context.vehicles},
        text('desplazamiento', 'Se estima que esta unidad realizaba su desplazamiento a… (velocidad, unidad y sustento)')]]
    ],
    evidencias: [['evidencias', 'Evidencias', 'Añadir evidencia', [choice('tipo', 'Tipo de evidencia', ['Biológica', 'Física', 'Material']), text('detalle', 'Detalle (mancha de sangre, masa cerebral, grasa, etc.)'), text('caracteristicas', 'Características'), text('ubicacion', 'Lugar donde fue ubicada'), text('consecuencia', '¿A consecuencia de qué habría quedado?'), text('determinacion', '¿Qué podría determinar?')]]],
    integral: [['parrafos', 'Párrafos del análisis integral', 'Añadir párrafo', [text('texto', 'Párrafo')]]],
    causalidad: [
      ['factores', 'Factores del accidente', 'Añadir factor', [choice('tipo', 'Tipo de factor', ['Determinante', 'Predominante', 'Contributivo']), person, text('descripcion', 'Descripción y fundamento del factor')]],
      ['infracciones', 'Infracciones administrativas por involucrado y rol', 'Añadir infracción', [person, text('codigo', 'Código / norma de la infracción'), text('descripcion', 'Descripción y fundamento de la infracción')]]
    ]
  };
  let pending = new Set();
  window.addEventListener('beforeunload', event => { if (pending.size) {event.preventDefault(); event.returnValue = '';} });
  for (const form of document.querySelectorAll('.report-analysis')) {
    const section = form.dataset.section;
    let version = context.saved[section]?.version || 0;
    const containers = {};
    const status = form.querySelector('[role="status"]');
    const dirty = () => { pending.add(section); status.textContent = 'Cambios sin guardar.'; };
    form.addEventListener('input', dirty);
    form.addEventListener('change', dirty);
    for (const [key, title, addLabel, fields] of groups[section]) {
      const group = document.createElement('section'); group.className = 'report-analysis-group';
      const head = document.createElement('div'); head.className = 'report-analysis-head';
      const heading = document.createElement('h4'); heading.textContent = title;
      const add = document.createElement('button'); add.type = 'button'; add.className = 'btn-shell'; add.textContent = addLabel;
      head.append(heading, add);
      const rows = document.createElement('div'); containers[key] = rows;
      const empty = document.createElement('p'); empty.className = 'report-analysis-empty'; empty.textContent = 'Sin registros. Usa «' + addLabel + '» para comenzar.';
      group.append(head, rows, empty); form.querySelector('.report-analysis-groups').append(group);
      const renumber = () => {
        Array.from(rows.children).forEach((row, i) => { row.querySelector('strong').textContent = `${i + 1}.`; });
        empty.hidden = rows.children.length > 0;
      };
      const addRow = (data = {}) => {
        const row = document.createElement('article'); row.className = 'report-analysis-row';
        const rowHead = document.createElement('div'); rowHead.className = 'report-analysis-row-head';
        const number = document.createElement('strong');
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn-shell'; remove.textContent = 'Quitar';
        remove.addEventListener('click', () => {row.remove(); renumber(); dirty();});
        rowHead.append(number, remove);
        const grid = document.createElement('div'); grid.className = 'report-analysis-fields';
        for (const field of fields) {
          const label = document.createElement('label'); label.textContent = field.label;
          const input = document.createElement(field.options ? 'select' : 'textarea');
          input.dataset.field = field.key; input.required = true;
          if (field.options) {
            input.append(new Option('Seleccionar…', ''));
            for (const option of field.options) input.append(new Option(option.label, String(option.id)));
            if (data[field.key] && !field.options.some(o => String(o.id) === String(data[field.key]))) {
              input.append(new Option('Registro ya no disponible en el expediente — selecciona otro', String(data[field.key])));
              input.lastChild.disabled = true;
            }
          } else {input.maxLength = 20000; label.classList.add('report-analysis-full');}
          input.value = data[field.key] ?? ''; label.append(input); grid.append(label);
        }
        row.append(rowHead, grid); rows.append(row); renumber(); return row;
      };
      for (const data of context.saved[section]?.data?.[key] || []) addRow(data);
      add.addEventListener('click', () => {
        if (rows.children.length >= 200) {status.textContent = 'Máximo 200 registros por lista.'; return;}
        const row = addRow(); dirty(); row.querySelector('select,textarea').focus();
      });
    }
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (!context.editable || !form.reportValidity()) return;
      const content = {};
      for (const [key, rows] of Object.entries(containers)) content[key] = Array.from(rows.children, row => Object.fromEntries(Array.from(row.querySelectorAll('[data-field]'), input => [input.dataset.field, input.value])));
      const body = new FormData();
      Object.entries({accidente_id: context.caseId, seccion: section, contenido: JSON.stringify(content), version, _csrf: context.csrf}).forEach(([key, value]) => body.append(key, value));
      const fieldset = form.querySelector('fieldset'); fieldset.disabled = true; status.textContent = 'Guardando…';
      try {
        const response = await fetch('accidente_analisis_guardar.php', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body});
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo guardar.');
        version = result.version; pending.delete(section); status.textContent = 'Apartado guardado correctamente.';
      } catch (error) { status.textContent = error instanceof SyntaxError ? 'Respuesta no válida. Verifica tu sesión y vuelve a intentar; tus cambios siguen en pantalla.' : error.message; }
      finally {fieldset.disabled = false;}
    });
  }
})();
