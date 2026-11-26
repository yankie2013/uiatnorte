(() => {
  'use strict';
  const context = JSON.parse(document.getElementById('report-analysis-context').textContent);
  const text = (key, label) => ({key, label});
  const person = {key: 'involucrado_id', label: 'Involucrado y rol en el accidente', options: context.people};
  const choice = (key, label, values) => ({key, label, options: values.map(value => ({id: value, label: value}))});
  const groups = {
    descripcion: [['contenido', 'Descripción analítica', 'Completar descripción analítica', []]],
    velocidad: [
      ['riesgos', 'Condiciones de riesgo', 'Añadir condición de riesgo', [text('texto', 'Condición de riesgo')]],
      ['parrafos', 'Párrafos del análisis', 'Añadir párrafo', [text('texto', 'Párrafo')]],
      ['estimaciones', 'Estimación por vehículo', 'Añadir estimación', [
        {key: 'vehiculo_id', label: 'Vehículo involucrado', options: context.vehicles},
        text('desplazamiento', 'Se estima que esta unidad realizaba su desplazamiento a… (velocidad, unidad y sustento)')]]
    ],
    'danos-lesiones': [
      ['consideraciones', 'Se tomará en cuenta lo siguiente:', 'Añadir elementos a considerar', [text('texto', 'Elemento a considerar')]],
      ['parrafos', 'Párrafos del análisis', 'Añadir párrafo', [text('texto', 'Párrafo')]],
      ['determinaciones', 'Lo analizado en los puntos que anteceden permite determinar:', 'Añadir determinación', [text('texto', 'Permite determinar')]]
    ],
    evidencias: [['evidencias', 'Evidencias', 'Añadir evidencia', [choice('tipo', 'Tipo de evidencia', ['Biológica', 'Física', 'Material']), text('detalle', 'Detalle (mancha de sangre, masa cerebral, grasa, etc.)'), text('caracteristicas', 'Características'), text('ubicacion', 'Lugar donde fue ubicada'), text('consecuencia', '¿A consecuencia de qué habría quedado?'), text('determinacion', '¿Qué podría determinar?')]]],
    integral: [['parrafos', 'Párrafos del análisis integral', 'Añadir párrafo', [text('texto', 'Párrafo')]]],
    causalidad: [
      ['factores', 'Factores del accidente', 'Añadir factor', [choice('tipo', 'Tipo de factor', ['Determinante', 'Predominante', 'Contributivo']), person, text('descripcion', 'Descripción y fundamento del factor')]]
    ],
    infracciones: [
      ['evaluaciones', 'Infracciones administrativas', 'Evaluar involucrado', []],
      ['informacion', 'INFORMACIÓN ADICIONAL', 'Añadir información adicional', [text('texto', 'Párrafo de información adicional')]],
      ['anexos', 'ANEXOS', 'Añadir anexo', [text('texto', 'Anexo')]]
    ]
  };
  const updateAnalysisTab = (section, data = {}) => {
    const tab = document.querySelector(`#analisis-tabs [data-bs-target="#analisis-${section}"]`);
    if (!tab) return;
    const hasInformation = section === 'descripcion' ? Boolean(data.contenido && (Object.values(data.contenido.inspeccion || {}).some(Boolean) || ['vias','consideraciones','aproximaciones','posiciones'].some(key => data.contenido[key]?.length))) : (groups[section] || []).some(([key]) => {
      const rows = data[key] || (key === 'evaluaciones' ? data.infracciones : []);
      return Array.isArray(rows) && rows.length > 0;
    });
    tab.dataset.information = hasInformation ? 'filled' : 'empty';
    tab.title = hasInformation ? 'Con información guardada' : 'Sin información guardada';
  };
  for (const section of Object.keys(groups)) updateAnalysisTab(section, context.saved[section]?.data);
  const buildAnalysisReferences = (prefix, includeIntegral = false) => {
    const references = document.createElement('div');
            references.className = 'report-integral-references';
            const tabs = document.createElement('div'); tabs.className = 'report-reference-tabs'; tabs.setAttribute('role', 'tablist'); tabs.setAttribute('aria-label', 'Información del expediente');
            const panel = document.createElement('div'); panel.className = 'report-reference-panel'; panel.id = `${prefix}-reference-panel`; panel.setAttribute('role', 'tabpanel'); panel.tabIndex = 0;
            const sources = [
              ['Determinación de velocidad', '#analisis-velocidad .report-analysis-groups'],
              ['Descripción analítica', '#analisis-descripcion .report-analysis-groups'],
              ['Daños y lesiones', '#analisis-danos-lesiones .report-analysis-groups'],
              ['Evidencias', '#analisis-evidencias .report-analysis-groups'],
              ['Participantes', '#case-participants-content'],
              ['ITP', '#itp .module-card']
            ];
            if (includeIntegral) sources.splice(4, 0, ['Análisis integral', '#analisis-integral .report-analysis-groups']);
            const copyReference = source => {
              const copy = source.cloneNode(true);
              copy.removeAttribute('hidden');
              copy.querySelectorAll('script,style,iframe,form,button,input[type="hidden"],.editable-toolbar,.record-actions,.editable-actions,.case-person-actions,.overview-add-related').forEach(element => element.remove());
              copy.querySelectorAll('input,select,textarea').forEach(input => {
                const value = document.createElement('div'); value.className = 'report-reference-value';
                value.textContent = input.tagName === 'SELECT' ? input.selectedOptions[0]?.textContent || '' : input.value;
                input.replaceWith(value);
              });
              copy.querySelectorAll('a').forEach(link => {const text = document.createElement('span'); text.textContent = link.textContent; link.replaceWith(text);});
              for (const element of [copy, ...copy.querySelectorAll('*')]) {
                element.removeAttribute('id');
                for (const attribute of [...element.attributes]) {
                  if (attribute.name.startsWith('on') || (attribute.name.startsWith('data-') && attribute.name !== 'data-tone')) element.removeAttribute(attribute.name);
                }
                for (const name of [...element.classList]) if (name.startsWith('js-')) element.classList.remove(name);
              }
              return copy;
            };
            const activate = index => {
              [...tabs.children].forEach((button, i) => {button.setAttribute('aria-selected', String(i === index)); button.tabIndex = i === index ? 0 : -1;});
              panel.setAttribute('aria-labelledby', `${prefix}-reference-tab-${index}`); panel.replaceChildren();
              const nodes = document.querySelectorAll(sources[index][1]);
              if (sources[index][0] === 'Participantes') {
                const grid = document.createElement('div'); grid.className = 'report-reference-people';
                const source = nodes[0];
                source?.querySelectorAll('.overview-person-info').forEach(info => {
                  const card = document.createElement('article'); card.className = 'report-reference-person';
                  const copy = copyReference(info);
                  for (const element of [copy, ...copy.querySelectorAll('*')]) {element.removeAttribute('class'); element.removeAttribute('style');}
                  card.append(copy);
                  const unit = info.closest('.participant-overview-unit');
                  const vehicles = unit?.querySelectorAll('.overview-vehicle-identity');
                  if (vehicles?.length) {
                    const vehicle = document.createElement('p'); vehicle.className = 'report-reference-person-vehicle';
                    vehicle.textContent = [...vehicles].map(item => item.textContent.trim().replace(/\s+/g, ' ')).join(' · '); card.append(vehicle);
                  }
                  grid.append(card);
                });
                panel.append(grid);
              } else {
                for (const source of nodes) panel.append(copyReference(source));
              }
              if (!panel.textContent.trim()) {const empty = document.createElement('p'); empty.textContent = 'No hay información registrada en este apartado.'; panel.append(empty);}
            };
            sources.forEach(([label], index) => {
              const button = document.createElement('button'); button.type = 'button'; button.textContent = label; button.id = `${prefix}-reference-tab-${index}`;
              button.setAttribute('role', 'tab'); button.setAttribute('aria-controls', panel.id); button.addEventListener('click', () => activate(index));
              button.addEventListener('keydown', event => {
                let next;
                if (event.key === 'ArrowRight') next = (index + 1) % sources.length;
                if (event.key === 'ArrowLeft') next = (index + sources.length - 1) % sources.length;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = sources.length - 1;
                if (next !== undefined) {event.preventDefault(); activate(next); tabs.children[next].focus();}
              });
              tabs.append(button);
            });
            references.append(tabs, panel); activate(0);
    return references;
  };
  let pending = new Set();
  window.addEventListener('beforeunload', event => { if (pending.size) {event.preventDefault(); event.returnValue = '';} });
  for (const form of document.querySelectorAll('.report-analysis')) {
    const section = form.dataset.section;
    let version = context.saved[section]?.version || 0;
    const containers = {};
    const serializers = {};
    const status = form.querySelector('[role="status"]');
    const updateFinalNumber = () => {
      if (section !== 'danos-lesiones') return;
      const heading = form.querySelector('[data-group="determinaciones"] h4');
      if (heading) heading.textContent = `${(containers.parrafos?.children.length || 0) + 2}. Lo analizado en los puntos que anteceden permite determinar:`;
    };
    const dirty = () => { pending.add(section); status.textContent = 'Cambios sin guardar.'; };
    form.addEventListener('input', dirty);
    form.addEventListener('change', dirty);
    for (const [key, title, addLabel, fields] of groups[section]) {
      const group = document.createElement('section'); group.className = 'report-analysis-group'; group.dataset.group = key;
      const head = document.createElement('div'); head.className = 'report-analysis-head';
      const heading = document.createElement('h4'); heading.textContent = key === 'consideraciones' ? '1. ' + title : title;
      if (key === 'consideraciones') group.classList.add('report-considerations');
      const add = document.createElement('button'); add.type = 'button'; add.className = 'btn-shell'; add.textContent = addLabel;
      head.append(heading, add);
      const rows = document.createElement(key === 'determinaciones' ? 'ul' : 'div'); containers[key] = rows;
      const empty = document.createElement('p'); empty.className = 'report-analysis-empty'; empty.textContent = 'Sin registros. Usa «' + addLabel + '» para comenzar.';
      if (section === 'velocidad' && key === 'parrafos') {
        const title = form.querySelector(':scope > h3');
        const introduction = form.querySelector(':scope > .report-speed-introduction');
        if (title && introduction) {
          title.classList.add('report-speed-title');
          form.querySelector('.report-analysis-groups').append(title, introduction);
        }
      }
      group.append(head, rows, empty); form.querySelector('.report-analysis-groups').append(group);
      if (section === 'descripcion') {
        serializers[key] = window.ReportDescriptionEditor({context, form, rows, head, heading, empty, add, save: saveSection, status});
        continue;
      }
      if (key === 'evaluaciones') {
        serializers[key] = window.ReportInfractionEditor({context, form, rows, head, heading, empty, add, references: () => buildAnalysisReferences('infractions', true), save: saveSection, status});
        continue;
      }
      if (section === 'causalidad' || (section === 'infracciones' && key === 'infracciones')) {
        const isFactor = key === 'factores';
        let values = (context.saved[section]?.data?.[key] || []).map(value => ({...value}));
        const participantLabel = value => context.people.find(person => String(person.id) === String(value.involucrado_id))?.label || 'Involucrado no disponible';
        const itemTitle = value => isFactor ? 'Factor ' + value.tipo.toLocaleLowerCase('es') : value.codigo;
        const render = () => {
          rows.replaceChildren(); empty.hidden = true; heading.hidden = !values.length;
          heading.textContent = isFactor ? 'A. FACTORES INTERVINIENTES' : '';
          if (!isFactor) heading.hidden = true;
          head.classList.toggle('report-causality-empty', !values.length);
          const participantsShown = new Set(), normsShown = new Set();
          values.forEach((value, index) => {
            const article = document.createElement('article'); article.className = 'report-causality-item';
            if (!isFactor) {
              const identity = `${value.involucrado_id}-${value.vehiculo_id || ''}`;
              if (!participantsShown.has(identity)) {
                participantsShown.add(identity);
                const participant = context.people.find(person => String(person.id) === String(value.involucrado_id));
                const vehicle = context.vehicles.find(vehicle => String(vehicle.id) === String(value.vehiculo_id));
                const header = document.createElement('h4'); header.className = 'report-infraction-person';
                header.textContent = 'Para ' + (participant?.label || 'el involucrado') + (vehicle ? ' · Vehículo de placa ' + vehicle.placa : '');
                article.append(header);
                if (value.introduccion) {const intro = document.createElement('p'); intro.textContent = value.introduccion; article.append(intro);}
              }
              const normKey = identity + '-' + (value.norma || '');
              if (value.norma && !normsShown.has(normKey)) {normsShown.add(normKey); const norm = document.createElement('p'); norm.className = 'report-infraction-norm'; norm.textContent = value.norma; article.append(norm);}
            }
            const title = document.createElement('h4'); title.textContent = isFactor ? `${index + 1}. ${itemTitle(value)}` : itemTitle(value);
            const person = document.createElement('p'); person.className = 'report-causality-person'; person.textContent = participantLabel(value);
            const description = document.createElement('p'); description.textContent = value.descripcion;
            const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'btn-shell report-evidence-edit'; edit.textContent = isFactor ? 'Editar factor' : 'Editar infracción'; edit.addEventListener('click', () => openModal(index));
            article.append(title); if (isFactor) article.append(person); article.append(description, edit);
            for (const field of fields) {const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.dataset.field = field.key; hidden.value = value[field.key] ?? ''; article.append(hidden);}
            rows.append(article);
          });
        };
        const openModal = (initialIndex = null) => {
          if (!context.editable) return;
          let draft = values.map(value => ({...value})), editing = initialIndex;
          const dialog = document.createElement('dialog'); dialog.className = 'report-risk-dialog report-damage-dialog report-integral-dialog report-causality-dialog';
          const title = document.createElement('h3'); title.id = `causality-${key}-title`; title.textContent = isFactor ? 'Factores del accidente' : 'Infracciones administrativas'; dialog.setAttribute('aria-labelledby', title.id);
          const references = buildAnalysisReferences(`causality-${key}`, true);
          const editor = document.createElement('form'); editor.className = 'report-evidence-editor';
          const grid = document.createElement('div'); grid.className = 'report-analysis-fields'; const inputs = {};
          for (const field of fields) {
            const label = document.createElement('label'); label.textContent = field.label;
            const input = document.createElement(field.options ? 'select' : field.key === 'codigo' ? 'input' : 'textarea'); input.required = !field.optional; inputs[field.key] = input;
            if (field.options) {input.append(new Option(field.optional ? 'No corresponde' : 'Seleccionar…', '')); for (const option of field.options) input.append(new Option(option.label, String(option.id)));}
            else {input.maxLength = 20000; if (field.key === 'codigo') input.type = 'text'; else {input.rows = 3; label.className = 'report-analysis-full';}}
            label.append(input); grid.append(label);
          }
          const stageButton = document.createElement('button'); stageButton.type = 'submit'; stageButton.className = 'btn-shell'; stageButton.textContent = isFactor ? 'Añadir factor a la lista' : 'Añadir infracción a la lista'; editor.append(grid, stageButton);
          const list = document.createElement('ul'); list.className = 'report-risk-draft report-draft-plain';
          const message = document.createElement('p'); message.setAttribute('role', 'status');
          const reset = () => {editing = null; editor.reset(); stageButton.textContent = isFactor ? 'Añadir factor a la lista' : 'Añadir infracción a la lista';};
          const load = index => {
            editing = index;
            for (const field of fields) {
              const input = inputs[field.key], value = String(draft[index][field.key] ?? '');
              if (field.options && value && ![...input.options].some(option => option.value === value)) {const missing = new Option('Involucrado ya no disponible — selecciona otro', value); missing.disabled = true; input.append(missing);}
              input.value = value;
            }
            stageButton.textContent = 'Actualizar registro'; Object.values(inputs)[0].focus();
          };
          const draw = () => {
            list.replaceChildren();
            draft.forEach((value, index) => {
              const item = document.createElement('li'); const text = document.createElement('span'); text.textContent = `${index + 1}. ${itemTitle(value)} · ${participantLabel(value)}`;
              const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'btn-shell'; edit.textContent = 'Editar'; edit.addEventListener('click', () => load(index));
              const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn-shell'; remove.textContent = 'Eliminar'; remove.addEventListener('click', () => {draft.splice(index, 1); if (editing === index) reset(); else if (editing !== null && editing > index) editing--; draw();});
              item.append(text, edit, remove); list.append(item);
            });
          };
          const stage = () => {
            if (!editor.reportValidity()) return false;
            if (editing === null && draft.length >= 200) {message.textContent = 'Máximo 200 registros.'; return false;}
            const value = Object.fromEntries(fields.map(field => [field.key, inputs[field.key].value.trim()]));
            if (fields.some(field => !field.optional && !value[field.key])) {message.textContent = 'Completa todos los campos.'; return false;}
            if (editing === null) draft.push(value); else draft[editing] = value;
            reset(); draw(); message.textContent = ''; return true;
          };
          editor.addEventListener('submit', event => {event.preventDefault(); stage();});
          const actions = document.createElement('div'); actions.className = 'report-risk-actions';
          const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn-shell'; cancel.textContent = 'Cancelar'; cancel.addEventListener('click', () => dialog.close());
          const save = document.createElement('button'); save.type = 'button'; save.className = 'btn-shell'; save.textContent = isFactor ? 'Guardar factores' : 'Guardar infracciones';
          save.addEventListener('click', async () => {
            if ((editing !== null || Object.values(inputs).some(input => input.value.trim())) && !stage()) return;
            const previous = values;
            const identityOrder = new Map(), normOrder = new Map();
            values = draft.map(value => ({...value}));
            if (!isFactor) {
              for (const value of values) {const identity = `${value.involucrado_id}-${value.vehiculo_id || ''}`; if (!identityOrder.has(identity)) identityOrder.set(identity, identityOrder.size); const norm = identity + '-' + value.norma; if (!normOrder.has(norm)) normOrder.set(norm, normOrder.size);}
              values.sort((a, b) => {const left = `${a.involucrado_id}-${a.vehiculo_id || ''}`, right = `${b.involucrado_id}-${b.vehiculo_id || ''}`; return identityOrder.get(left) - identityOrder.get(right) || normOrder.get(left + '-' + a.norma) - normOrder.get(right + '-' + b.norma);});
            }
            render();
            save.disabled = true; cancel.disabled = true; editor.inert = true; list.inert = true;
            const preventClose = event => event.preventDefault(); dialog.addEventListener('cancel', preventClose);
            try {if (await saveSection()) dialog.close(); else {values = previous; render(); message.textContent = status.textContent;}}
            finally {save.disabled = false; cancel.disabled = false; editor.inert = false; list.inert = false; dialog.removeEventListener('cancel', preventClose);}
          });
          actions.append(cancel, save); dialog.append(title, references, editor, list, message, actions);
          dialog.addEventListener('close', () => dialog.remove()); document.body.append(dialog); dialog.showModal(); draw();
          if (initialIndex !== null) load(initialIndex); else Object.values(inputs)[0].focus();
        };
        render(); add.addEventListener('click', () => openModal()); continue;
      }
      if (key === 'evidencias') {
        heading.hidden = true; empty.hidden = true; head.style.justifyContent = 'flex-end';
        let values = (context.saved[section]?.data?.evidencias || []).map(value => ({...value}));
        const render = () => {
          rows.replaceChildren();
          values.forEach((value, index) => {
            const article = document.createElement('article'); article.className = 'report-evidence';
            const title = document.createElement('h4'); title.textContent = `${index + 1}. Evidencia ${value.tipo} (${value.detalle})`;
            const location = document.createElement('p'); location.textContent = value.ubicacion;
            const characteristics = document.createElement('ul'); characteristics.className = 'report-evidence-characteristics';
            for (const line of value.caracteristicas.split(/\r?\n/).map(line => line.replace(/^\s*[-–•]\s*/, '').trim()).filter(Boolean)) {
              const item = document.createElement('li'); item.textContent = line; characteristics.append(item);
            }
            const consequence = document.createElement('p'); consequence.textContent = value.consecuencia;
            const determination = document.createElement('p'); determination.textContent = value.determinacion;
            const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'btn-shell report-evidence-edit'; edit.textContent = 'Editar evidencia'; edit.addEventListener('click', () => openModal(index));
            article.append(title, location, characteristics, consequence, determination, edit);
            for (const field of fields) {
              const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.dataset.field = field.key; hidden.value = value[field.key] || ''; article.append(hidden);
            }
            rows.append(article);
          });
        };
        const openModal = (initialIndex = null) => {
          if (!context.editable) return;
          let draft = values.map(value => ({...value})); let editing = initialIndex;
          const dialog = document.createElement('dialog'); dialog.className = 'report-risk-dialog report-damage-dialog report-evidence-dialog'; dialog.setAttribute('aria-labelledby', 'report-evidence-title');
          const title = document.createElement('h3'); title.id = 'report-evidence-title'; title.textContent = 'Análisis de evidencias';
          const editor = document.createElement('form'); editor.className = 'report-evidence-editor';
          const grid = document.createElement('div'); grid.className = 'report-analysis-fields'; const inputs = {};
          let characteristicEntry, drawCharacteristics, selectedDetail = '';
          for (const field of fields) {
            const label = document.createElement('label'); label.textContent = field.label;
            const input = document.createElement(field.options ? 'select' : ['detalle', 'caracteristicas'].includes(field.key) ? 'input' : 'textarea'); input.required = field.key !== 'caracteristicas'; inputs[field.key] = input;
            if (field.key === 'detalle') input.type = 'text';
            if (field.key === 'caracteristicas') input.type = 'hidden';
            if (field.options) {input.append(new Option('Seleccionar…', '')); field.options.forEach(option => input.append(new Option(option.label, option.id)));}
            else {input.rows = 2; input.maxLength = 20000;}
            if (field.key === 'caracteristicas') input.placeholder = 'Una característica por línea: color, forma, dimensiones…';
            if (field.key === 'consecuencia') input.placeholder = 'Redacta el párrafo que explica cómo habría quedado esta evidencia.';
            if (field.key === 'determinacion') input.placeholder = 'Redacta lo que esta evidencia podría determinar.';
            label.append(input);
            if (field.key === 'detalle') {
              const suggestions = document.createElement('datalist'); suggestions.id = 'report-evidence-details';
              const details = [...(context.evidenceDetails || []), ...values.map(value => value.detalle)];
              [...new Set(details)].filter(Boolean).forEach(detail => suggestions.append(new Option(detail, detail)));
              input.setAttribute('list', suggestions.id); input.placeholder = 'Escribe o selecciona un detalle guardado'; label.append(suggestions);
            }
            if (field.key === 'caracteristicas') {
              const entry = document.createElement('div'); entry.className = 'report-characteristic-entry';
              characteristicEntry = document.createElement('input'); characteristicEntry.type = 'text'; characteristicEntry.maxLength = 20000; characteristicEntry.placeholder = 'Forma, longitud, textura, área…'; characteristicEntry.setAttribute('aria-label', 'Nueva característica');
              const add = document.createElement('button'); add.type = 'button'; add.className = 'btn-shell'; add.textContent = 'Añadir';
              const list = document.createElement('ul'); list.className = 'report-characteristic-list';
              drawCharacteristics = () => {
                list.replaceChildren();
                input.value.split(/\r?\n/).map(line => line.replace(/^\s*[-–•]\s*/, '').trim()).filter(Boolean).forEach((value, index, lines) => {
                  const item = document.createElement('li'); const text = document.createElement('span'); text.textContent = value;
                  const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn-shell'; remove.textContent = 'Eliminar'; remove.setAttribute('aria-label', 'Eliminar característica: ' + value);
                  remove.addEventListener('click', () => {lines.splice(index, 1); input.value = lines.join('\n'); drawCharacteristics();}); item.append(text, remove); list.append(item);
                });
              };
              const addCharacteristic = () => {
                const value = characteristicEntry.value.replace(/^\s*[-–•]\s*/, '').trim();
                if (!value) return;
                input.value = [input.value, value].filter(Boolean).join('\n'); characteristicEntry.value = ''; drawCharacteristics();
              };
              add.addEventListener('click', addCharacteristic);
              characteristicEntry.addEventListener('keydown', event => {if (event.key === 'Enter' && !event.isComposing) {event.preventDefault(); addCharacteristic();}});
              entry.append(characteristicEntry, add); label.append(entry, list);
            }
            grid.append(label);
          }
          const normalizeDetail = value => value.trim().toLocaleLowerCase('es');
          const applyDetailTemplate = () => {
            const detail = normalizeDetail(inputs.detalle.value);
            if (detail === selectedDetail) return;
            selectedDetail = detail;
            const template = (context.evidenceTemplates || []).find(value => normalizeDetail(value.detalle) === detail);
            if (!template) return;
            inputs.caracteristicas.value = template.caracteristicas || '';
            characteristicEntry.value = ''; drawCharacteristics();
          };
          inputs.detalle.addEventListener('input', applyDetailTemplate);
          inputs.detalle.addEventListener('change', applyDetailTemplate);
          const append = document.createElement('button'); append.type = 'submit'; append.className = 'btn-shell'; append.textContent = 'Añadir a la lista';
          editor.append(grid, append);
          const list = document.createElement('ul'); list.className = 'report-risk-draft report-draft-plain';
          const message = document.createElement('p'); message.setAttribute('role', 'status');
          const load = index => {editing = index; for (const field of fields) inputs[field.key].value = draft[index][field.key] || ''; drawCharacteristics(); characteristicEntry.value = ''; selectedDetail = normalizeDetail(inputs.detalle.value); append.textContent = 'Actualizar evidencia'; inputs.tipo.focus();};
          const reset = () => {editing = null; editor.reset(); selectedDetail = ''; characteristicEntry.value = ''; drawCharacteristics(); append.textContent = 'Añadir a la lista';};
          const draw = () => {
            list.replaceChildren();
            draft.forEach((value, index) => {
              const item = document.createElement('li');
              const text = document.createElement('span'); text.textContent = `${index + 1}. Evidencia ${value.tipo} (${value.detalle})`;
              const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'btn-shell'; edit.textContent = 'Editar'; edit.addEventListener('click', () => load(index));
              const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn-shell'; remove.textContent = 'Eliminar';
              remove.addEventListener('click', () => {draft.splice(index, 1); if (editing === index) reset(); else if (editing !== null && editing > index) editing--; draw();});
              item.append(text, edit, remove); list.append(item);
            });
          };
          const stage = () => {
            if (characteristicEntry.value.trim()) {inputs.caracteristicas.value = [inputs.caracteristicas.value, characteristicEntry.value.trim()].filter(Boolean).join('\n'); characteristicEntry.value = ''; drawCharacteristics();}
            if (!editor.reportValidity()) return false;
            if (editing === null && draft.length >= 200) {message.textContent = 'Máximo 200 evidencias.'; return false;}
            const value = Object.fromEntries(fields.map(field => [field.key, inputs[field.key].value.trim()]));
            if (Object.values(value).some(value => !value)) {message.textContent = 'Completa todos los campos.'; return false;}
            if (editing === null) draft.push(value); else draft[editing] = value;
            reset(); draw(); message.textContent = ''; return true;
          };
          editor.addEventListener('submit', event => {event.preventDefault(); stage();});
          const actions = document.createElement('div'); actions.className = 'report-risk-actions';
          const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn-shell'; cancel.textContent = 'Cancelar'; cancel.addEventListener('click', () => dialog.close());
          const save = document.createElement('button'); save.type = 'button'; save.className = 'btn-shell'; save.textContent = 'Guardar evidencias';
          save.addEventListener('click', async () => {
            if ((editing !== null || characteristicEntry.value.trim() || Object.values(inputs).some(input => input.value.trim())) && !stage()) return;
            const previous = values; values = draft.map(value => ({...value})); render();
            save.disabled = true; cancel.disabled = true; editor.inert = true; list.inert = true;
            const preventClose = event => event.preventDefault(); dialog.addEventListener('cancel', preventClose);
            try {
              if (await saveSection()) {
                context.evidenceDetails = [...new Set([...(context.evidenceDetails || []), ...values.map(value => value.detalle)])];
                for (const value of values) {
                  context.evidenceTemplates = (context.evidenceTemplates || []).filter(template => normalizeDetail(template.detalle) !== normalizeDetail(value.detalle));
                  context.evidenceTemplates.push({detalle: value.detalle, caracteristicas: value.caracteristicas});
                }
                dialog.close();
              }
              else {values = previous; render(); message.textContent = status.textContent;}
            } finally {save.disabled = false; cancel.disabled = false; editor.inert = false; list.inert = false; dialog.removeEventListener('cancel', preventClose);}
          });
          actions.append(cancel, save); dialog.append(title, editor, list, message, actions);
          dialog.addEventListener('close', () => dialog.remove()); document.body.append(dialog); dialog.showModal(); draw();
          if (initialIndex !== null) load(initialIndex); else inputs.tipo.focus();
        };
        render(); add.addEventListener('click', () => openModal()); continue;
      }
      if (key === 'informacion' || key === 'anexos' || key === 'riesgos' || key === 'consideraciones' || key === 'determinaciones' || key === 'parrafos' || key === 'estimaciones') {
        const estimation = key === 'estimaciones';
        const numbered = key === 'parrafos';
        const plainParagraph = key === 'informacion';
        const paragraphStart = section === 'danos-lesiones' ? 2 : 1;
        const paragraphMarker = index => {
          if (section !== 'velocidad') return `${index + paragraphStart}. `;
          let number = index + 1, label = '';
          while (number > 0) {number--; label = String.fromCharCode(97 + number % 26) + label; number = Math.floor(number / 26);}
          return label + '. ';
        };
        const shortEntry = key === 'riesgos' || key === 'consideraciones' || key === 'determinaciones' || key === 'anexos';
        const readRows = () => Array.from(rows.children, row => Object.fromEntries(Array.from(row.querySelectorAll('[data-field]'), input => [input.dataset.field, input.value])));
        const describe = value => {
          if (!estimation) return value.texto;
          const vehicle = context.vehicles.find(vehicle => String(vehicle.id) === String(value.vehiculo_id));
          return 'Para el vehículo de placa ' + (vehicle?.placa || 'Sin placa registrada') + '\n' + value.desplazamiento;
        };
        rows.className = 'report-risk-list';
        const render = values => {
          rows.replaceChildren();
          for (const [index, value] of values.entries()) {
            const item = document.createElement(key === 'determinaciones' ? 'li' : 'div'); item.className = estimation ? 'report-estimation-item' : numbered || plainParagraph ? 'report-paragraph-item' : 'report-risk-item';
            const content = document.createElement('span'); content.textContent = (numbered ? paragraphMarker(index) : '') + describe(value);
            if (estimation) {
              const vehicle = context.vehicles.find(vehicle => String(vehicle.id) === String(value.vehiculo_id));
              const title = document.createElement('strong'); title.textContent = 'Para el vehículo de placa ' + (vehicle?.placa || 'Sin placa registrada');
              content.replaceChildren(title, document.createElement('br'), document.createTextNode(value.desplazamiento));
            }
            item.append(content);
            for (const field of fields) {
              const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.dataset.field = field.key; hidden.value = value[field.key] ?? ''; item.append(hidden);
            }
            rows.append(item);
          }
          empty.hidden = ['danos-lesiones', 'integral', 'velocidad', 'infracciones'].includes(section) || values.length > 0;
          if (['danos-lesiones', 'integral', 'velocidad', 'infracciones'].includes(section)) {
            heading.hidden = values.length === 0;
            group.classList.toggle('report-group-empty', values.length === 0);
          }
          updateFinalNumber();
        };
        render((context.saved[section]?.data?.[key] || []).filter(value => (value.texto || value.desplazamiento || '').trim()));
        add.addEventListener('click', () => {
          const dialog = document.createElement('dialog'); dialog.className = 'report-risk-dialog';
          const heading = document.createElement('h3'); heading.textContent = title; heading.id = `report-dialog-${section}-${key}`;
          dialog.setAttribute('aria-labelledby', heading.id);
          const references = document.createElement('div');
          if (['velocidad', 'danos-lesiones', 'integral', 'infracciones'].includes(section)) dialog.classList.add('report-damage-dialog');
          if (section === 'velocidad') dialog.classList.add('report-speed-dialog');
          if (section === 'integral') dialog.classList.add('report-integral-dialog');
          if (section === 'integral') references.append(buildAnalysisReferences('integral'));
          if (section === 'danos-lesiones' && numbered) {
            dialog.classList.add('report-damage-dialog'); references.className = 'report-damage-references';
            for (const [title, entries, emptyText] of [
              ['Daños del vehículo', context.damageReferences || [], 'No hay vehículos involucrados registrados.'],
              ['Lesiones de la persona fallecida', context.deceasedReferences || [], 'No hay personas fallecidas registradas.']
            ]) {
              const panel = document.createElement('section'); panel.className = 'report-damage-reference';
              const label = document.createElement('h4'); label.textContent = title; panel.append(label);
              const detail = document.createElement('div'); detail.className = 'report-damage-detail'; detail.setAttribute('aria-live', 'polite');
              if (entries.length > 1) {
                const select = document.createElement('select'); select.setAttribute('aria-label', title);
                entries.forEach((entry, index) => select.append(new Option(entry.label, String(index))));
                select.addEventListener('change', () => {detail.textContent = entries[Number(select.value)].detail;}); panel.append(select);
              } else if (entries.length === 1) {
                const name = document.createElement('p'); name.textContent = entries[0].label; panel.append(name);
              }
              detail.textContent = entries[0]?.detail || emptyText; panel.append(detail); references.append(panel);
            }
          }
          const editor = document.createElement('form'); editor.className = 'report-risk-entry';
          if (estimation) editor.classList.add('report-estimation-entry');
          const vehicleSelect = estimation ? document.createElement('select') : null;
          if (vehicleSelect) {
            vehicleSelect.setAttribute('aria-label', 'Vehículo involucrado');
            vehicleSelect.append(new Option('Seleccionar vehículo…', ''));
            for (const vehicle of context.vehicles) vehicleSelect.append(new Option(vehicle.label, String(vehicle.id)));
            editor.append(vehicleSelect);
          }
          const input = document.createElement(shortEntry ? 'input' : 'textarea');
          if (shortEntry) input.type = 'text'; else input.rows = 3;
          input.maxLength = 20000;
          input.placeholder = key === 'informacion' ? 'Escribe la información adicional y pulsa Enter' : key === 'anexos' ? 'Escribe un anexo y pulsa Enter' : estimation ? 'Se estima que esta unidad realizaba su desplazamiento a una velocidad constante…' : numbered ? 'Escribe un párrafo y pulsa Enter' : key === 'determinaciones' ? 'Escribe qué permite determinar el análisis y pulsa Enter' : key === 'consideraciones' ? 'Escribe qué se tomará en cuenta y pulsa Enter' : 'Escribe una condición de riesgo y pulsa Enter';
          input.setAttribute('aria-label', estimation ? 'Párrafo de estimación de velocidad' : numbered ? 'Nuevo párrafo' : key === 'determinaciones' ? 'Permite determinar' : key === 'consideraciones' ? 'Elemento a considerar en el análisis' : 'Nueva condición de riesgo');
          if (estimation) input.value = 'Se estima que esta unidad realizaba su desplazamiento a una velocidad constante ';
          const addItem = document.createElement('button'); addItem.type = 'submit'; addItem.className = 'btn-shell'; addItem.textContent = 'Añadir';
          editor.append(input, addItem);
          if (section === 'integral') {const label = document.createElement('label'); label.className = 'report-integral-writing-label'; label.textContent = 'Redactar párrafos del análisis integral'; input.id = 'integral-new-paragraph'; label.htmlFor = input.id; editor.prepend(label);}
          const list = document.createElement('ul'); list.className = 'report-risk-draft' + (!shortEntry ? ' report-draft-plain' : '');
          const message = document.createElement('p'); message.setAttribute('role', 'status');
          let draft = readRows();
          const draw = () => {
            list.replaceChildren();
            draft.forEach((value, index) => {
              const item = document.createElement('li');
              const text = document.createElement('span'); text.textContent = (numbered ? paragraphMarker(index) : '') + describe(value);
              const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn-shell'; remove.textContent = 'Eliminar'; remove.setAttribute('aria-label', 'Eliminar: ' + describe(value));
              remove.addEventListener('click', () => {draft.splice(index, 1); draw();});
              item.append(text, remove); list.append(item);
            });
          };
          const append = () => {
            if (!input.value.trim()) return true;
            if (estimation && input.value.trim() === 'Se estima que esta unidad realizaba su desplazamiento a una velocidad constante') {
              if (!vehicleSelect.value) return true;
              message.textContent = 'Completa el párrafo con la velocidad estimada y su sustento.'; input.focus(); return false;
            }
            if (estimation && !vehicleSelect.value) {message.textContent = 'Selecciona el vehículo involucrado.'; vehicleSelect.focus(); return false;}
            if (draft.length >= 200) {message.textContent = 'Máximo 200 registros por lista.'; return false;}
            draft.push(estimation ? {vehiculo_id: vehicleSelect.value, desplazamiento: input.value.trim()} : {texto: input.value.trim()}); input.value = ''; message.textContent = ''; draw(); input.focus(); return true;
          };
          editor.addEventListener('submit', event => {event.preventDefault(); append();});
          input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {event.preventDefault(); append();}
          });
          const actions = document.createElement('div'); actions.className = 'report-risk-actions';
          const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn-shell'; cancel.textContent = 'Cancelar'; cancel.addEventListener('click', () => dialog.close());
          const save = document.createElement('button'); save.type = 'button'; save.className = 'btn-shell'; save.textContent = key === 'informacion' ? 'Guardar información adicional' : key === 'anexos' ? 'Guardar anexos' : estimation ? 'Guardar estimaciones' : numbered ? 'Guardar párrafos' : key === 'determinaciones' ? 'Guardar determinaciones' : key === 'consideraciones' ? 'Guardar elementos' : 'Guardar condiciones';
          save.addEventListener('click', async () => {
            if (!append()) return;
            const previous = readRows();
            render(draft); save.disabled = true; cancel.disabled = true; editor.inert = true; list.inert = true;
            const preventClose = event => event.preventDefault(); dialog.addEventListener('cancel', preventClose);
            try {
              if (await saveSection()) dialog.close();
              else {render(previous); message.textContent = status.textContent || 'Revisa los campos obligatorios del apartado.';}
            } finally {save.disabled = false; cancel.disabled = false; editor.inert = false; list.inert = false; dialog.removeEventListener('cancel', preventClose);}
          });
          actions.append(cancel, save); dialog.append(heading); if (references.childElementCount) dialog.append(references); dialog.append(editor, list, message, actions);
          dialog.addEventListener('close', () => dialog.remove()); document.body.append(dialog); draw(); dialog.showModal(); input.focus();
        });
        continue;
      }
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
          } else {input.rows = 2; input.maxLength = 20000; label.classList.add('report-analysis-full');}
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
    async function saveSection() {
      if (!context.editable || !form.reportValidity()) return false;
      const content = {};
      for (const [key, rows] of Object.entries(containers)) content[key] = serializers[key] ? serializers[key]() : Array.from(rows.children, row => Object.fromEntries(Array.from(row.querySelectorAll('[data-field]'), input => [input.dataset.field, input.value])));
      const body = new FormData();
      Object.entries({accidente_id: context.caseId, seccion: section, contenido: JSON.stringify(content), version, _csrf: context.csrf}).forEach(([key, value]) => body.append(key, value));
      const fieldset = form.querySelector('fieldset'); fieldset.disabled = true; status.textContent = 'Guardando…';
      try {
        const response = await fetch('accidente_analisis_guardar.php', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body});
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo guardar.');
        version = result.version; context.saved[section] = {version, data: content}; updateAnalysisTab(section, content); pending.delete(section); status.textContent = 'Apartado guardado correctamente.'; return true;
      } catch (error) { status.textContent = error instanceof SyntaxError ? 'Respuesta no válida. Verifica tu sesión y vuelve a intentar; tus cambios siguen en pantalla.' : error.message; return false; }
      finally {fieldset.disabled = false;}
    }
    form.addEventListener('submit', event => {event.preventDefault(); saveSection();});
  }
})();
