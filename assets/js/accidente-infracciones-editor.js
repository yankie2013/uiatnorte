(() => {
  'use strict';
  const normalize = value => String(value || '').trim().toLocaleLowerCase('es').replace(/^art[íi]culo\s*/i, '').replace(/[.°º\s]+$/g, '');
  const copy = value => JSON.parse(JSON.stringify(value));
  const node = (tag, className, text) => {const element = document.createElement(tag); if (className) element.className = className; if (text) element.textContent = text; return element;};
  const button = (text, action) => {const element = node('button', 'btn-shell', text); element.type = 'button'; if (action) element.addEventListener('click', action); return element;};
  window.ReportInfractionEditor = ({context, rows, head, heading, empty, add, references, save, status}) => {
    const saved = context.saved.infracciones?.data || {};
    let values = copy(saved.evaluaciones || []);
    // Conserva artículos del formato anterior al abrir registros existentes.
    if (!saved.evaluaciones) {
      for (const legacy of saved.infracciones || []) {
        let person = values.find(item => String(item.involucrado_id) === String(legacy.involucrado_id));
        if (!person) {person = {involucrado_id: legacy.involucrado_id, vehiculo_id: legacy.vehiculo_id || '', incurso: 'si', articulos: []}; values.push(person);}
        const match = (legacy.codigo || '').match(/^(?:art[íi]culo\s*)?([\d]+(?:\.[\d]+)*[A-Za-z]?)[.°º\s:–-]*(.*)$/i);
        person.articulos.push({numero: match?.[1] || legacy.codigo, titulo: match?.[2] || legacy.codigo, contenido: legacy.descripcion});
      }
    }
    const personInfo = id => context.people.find(person => String(person.id) === String(id));
    const reportHeading = evaluation => {
      const person = personInfo(evaluation.involucrado_id);
      const role = (person?.rol || person?.label?.split(' · ').pop() || 'involucrado').toLocaleLowerCase('es');
      const vehicle = context.vehicles.find(vehicle => String(vehicle.id) === String(evaluation.vehiculo_id));
      return 'Para el ' + role + (vehicle ? ' del ' + (vehicle.tipo || 'vehículo').split(' - ').pop().toLocaleLowerCase('es') + ' de placa de rodaje ' + vehicle.placa : '') + ' · ' + (person?.nombre || person?.label?.split(' · ')[0] || 'Involucrado no disponible');
    };
    const statement = evaluation => {
      const role = (personInfo(evaluation.involucrado_id)?.rol || 'involucrado').toLocaleLowerCase('es');
      return 'El ' + role + (evaluation.incurso === 'si' ? ' se encuentra' : ' no se encuentra') + ' incurso en el Reglamento Nacional de Tránsito-Código de Tránsito, D.S. Nro. 016-20009-MTC' + (evaluation.incurso === 'si' ? ', artículos:' : '.');
    };
    const render = () => {
      rows.replaceChildren(); heading.hidden = true; empty.hidden = true; head.style.justifyContent = 'flex-end';
      values.forEach((evaluation, index) => {
        const article = node('article', 'report-causality-item report-infraction-evaluation');
        article.append(node('h4', 'report-infraction-person', reportHeading(evaluation)), node('p', '', statement(evaluation)));
        evaluation.articulos.forEach(item => article.append(node('h4', '', 'Artículo ' + item.numero + '.- ' + item.titulo), node('p', '', item.contenido)));
        article.append(button('Editar evaluación', () => openModal(index))); rows.append(article);
      });
    };
    const openModal = (initialIndex = null) => {
      if (!context.editable) return;
      const draft = copy(values);
      let editingArticle = null, selectedId = '';
      const dialog = node('dialog', 'report-risk-dialog report-damage-dialog report-integral-dialog report-causality-dialog report-infraction-dialog');
      const title = node('h3', '', 'Infracciones administrativas'); title.id = 'infraction-evaluation-title'; dialog.setAttribute('aria-labelledby', title.id);
      const editor = node('form', 'report-evidence-editor');
      const grid = node('div', 'report-analysis-fields');
      const field = (text, input) => {const label = node('label', '', text); label.append(input); return label;};
      const selectPerson = node('select'); selectPerson.append(new Option('Seleccionar involucrado…', ''));
      context.people.forEach(person => selectPerson.append(new Option(person.label, String(person.id))));
      const selectVehicle = node('select'); selectVehicle.append(new Option('No corresponde', ''));
      context.vehicles.forEach(vehicle => selectVehicle.append(new Option(vehicle.label, String(vehicle.id))));
      const selectStatus = node('select'); selectStatus.append(new Option('Seleccionar…', ''), new Option('Sí, se encuentra incurso', 'si'), new Option('No se encuentra incurso', 'no'));
      grid.append(field('1. Persona involucrada y rol', selectPerson), field('Vehículo (si corresponde)', selectVehicle), field('2. ¿Se encuentra incurso?', selectStatus));
      const preview = node('div', 'report-infraction-preview'); preview.hidden = true;
      const articleEditor = node('section', 'report-infraction-articles'); articleEditor.hidden = true;
      const articleGrid = node('div', 'report-analysis-fields');
      const number = node('input'); number.type = 'text'; number.maxLength = 100; number.placeholder = 'Ej.: 88'; number.setAttribute('list', 'infraction-article-catalog');
      const articleTitle = node('input'); articleTitle.type = 'text'; articleTitle.maxLength = 20000; articleTitle.placeholder = 'Título del artículo';
      const content = node('textarea'); content.rows = 3; content.maxLength = 20000; content.placeholder = 'Contenido del artículo';
      const catalog = node('datalist'); catalog.id = 'infraction-article-catalog';
      const templates = [...(context.articleTemplates || [])];
      for (const evaluation of draft) for (const item of evaluation.articulos) templates.push(copy(item));
      const refreshCatalog = () => {catalog.replaceChildren(); const seen = new Set(); for (const item of [...templates].reverse()) if (!seen.has(normalize(item.numero))) {seen.add(normalize(item.numero)); catalog.append(new Option('Artículo ' + item.numero + ' · ' + item.titulo, item.numero));}};
      refreshCatalog();
      const applyTemplate = () => {const item = [...templates].reverse().find(item => normalize(item.numero) === normalize(number.value)); if (item) {articleTitle.value = item.titulo; content.value = item.contenido;}};
      number.addEventListener('input', applyTemplate);
      articleGrid.append(field('Número del artículo', number), field('Título del artículo', articleTitle));
      const contentField = field('Contenido del artículo', content); contentField.className = 'report-infraction-content';
      const articleList = node('div', 'report-infraction-article-list');
      const message = node('p'); message.setAttribute('role', 'status');
      const selected = () => draft.find(item => String(item.involucrado_id) === selectPerson.value);
      const pendingArticle = () => [number, articleTitle, content].some(input => input.value.trim());
      const resetArticle = () => {editingArticle = null; number.value = ''; articleTitle.value = ''; content.value = ''; addArticle.textContent = 'Añadir artículo';};
      const ensurePerson = () => {
        if (!selectPerson.value || !selectStatus.value) {message.textContent = 'Selecciona una persona e indica si se encuentra incursa.'; return null;}
        let evaluation = selected();
        if (!evaluation) {
          if (draft.length >= 200) {message.textContent = 'Máximo 200 involucrados.'; return null;}
          evaluation = {involucrado_id: selectPerson.value, vehiculo_id: selectVehicle.value, incurso: selectStatus.value, articulos: []}; draft.push(evaluation);
        }
        evaluation.vehiculo_id = selectVehicle.value; evaluation.incurso = selectStatus.value;
        if (evaluation.incurso === 'no') evaluation.articulos = [];
        return evaluation;
      };
      const draw = () => {
        articleList.replaceChildren();
        (selected()?.articulos || []).forEach((item, index) => {
          const row = node('div', 'report-infraction-article-row');
          const text = node('div'); text.append(node('strong', '', 'Artículo ' + item.numero + '.- ' + item.titulo), node('p', '', item.contenido));
          row.append(text, button('Editar', () => {editingArticle = index; number.value = item.numero; articleTitle.value = item.titulo; content.value = item.contenido; addArticle.textContent = 'Actualizar artículo'; number.focus();}), button('Eliminar', () => {selected().articulos.splice(index, 1); resetArticle(); draw();})); articleList.append(row);
        });
        peopleList.replaceChildren();
        draft.forEach((evaluation, index) => {
          const row = node('li'); row.append(node('span', '', reportHeading(evaluation) + ' · ' + (evaluation.incurso === 'si' ? evaluation.articulos.length + ' artículo(s)' : 'No se encuentra incurso')), button('Editar', () => load(String(evaluation.involucrado_id))), button('Eliminar', () => {draft.splice(index, 1); if (selectPerson.value === String(evaluation.involucrado_id)) {selectPerson.value = ''; selectedId = ''; selectStatus.value = ''; resetArticle(); update();} draw();})); peopleList.append(row);
        });
      };
      const update = () => {
        const chosen = !!selectPerson.value;
        selectVehicle.disabled = !chosen; selectStatus.disabled = !chosen;
        preview.hidden = !chosen || !selectStatus.value;
        if (!preview.hidden) {const evaluation = {involucrado_id: selectPerson.value, vehiculo_id: selectVehicle.value, incurso: selectStatus.value}; preview.replaceChildren(node('strong', '', reportHeading(evaluation)), node('p', '', statement(evaluation)));}
        articleEditor.hidden = !chosen || selectStatus.value !== 'si';
        commitPerson.hidden = !chosen || selectStatus.value !== 'no';
      };
      const addCurrentArticle = () => {
        if (!selectPerson.value || selectStatus.value !== 'si') {message.textContent = 'Selecciona una persona que se encuentre incursa.'; return false;}
        if ([number, articleTitle, content].some(input => !input.value.trim())) {message.textContent = 'Completa número, título y contenido del artículo.'; return false;}
        const evaluation = ensurePerson(); if (!evaluation) return false;
        if (editingArticle === null && evaluation.articulos.length >= 200) {message.textContent = 'Máximo 200 artículos por persona.'; return false;}
        const item = {numero: normalize(number.value), titulo: articleTitle.value.trim(), contenido: content.value.trim()};
        const duplicate = evaluation.articulos.findIndex(article => normalize(article.numero) === normalize(item.numero));
        if (editingArticle !== null) evaluation.articulos[editingArticle] = item;
        else if (duplicate >= 0) evaluation.articulos[duplicate] = item;
        else evaluation.articulos.push(item);
        templates.push(copy(item)); refreshCatalog(); resetArticle(); draw(); message.textContent = ''; return true;
      };
      const addArticle = button('Añadir artículo', addCurrentArticle);
      articleEditor.append(node('h4', '', '3. Artículos para esta persona'), articleGrid, catalog, contentField, addArticle, articleList);
      const commitPerson = button('Añadir evaluación sin infracciones', () => {if (ensurePerson()) {resetArticle(); draw(); message.textContent = 'Evaluación añadida a la lista.';}});
      const peopleList = node('ul', 'report-risk-draft report-draft-plain');
      const load = id => {
        selectPerson.value = id; selectedId = id;
        const evaluation = selected(), person = personInfo(id);
        selectVehicle.value = String(evaluation?.vehiculo_id || person?.vehiculo_id || '');
        selectStatus.value = evaluation?.incurso || ''; resetArticle(); update(); draw();
      };
      selectPerson.addEventListener('change', () => {
        const next = selectPerson.value;
        if (pendingArticle()) {selectPerson.value = selectedId; if (!addCurrentArticle()) return;}
        load(next); message.textContent = '';
      });
      selectVehicle.addEventListener('change', () => {if (selected()) selected().vehiculo_id = selectVehicle.value; update(); draw();});
      selectStatus.addEventListener('change', () => {
        const evaluation = selected();
        if (evaluation && selectStatus.value) {evaluation.incurso = selectStatus.value; if (evaluation.incurso === 'no') {evaluation.articulos = []; resetArticle();}}
        update(); draw();
      });
      editor.append(grid, preview, articleEditor, commitPerson);
      editor.addEventListener('submit', event => {event.preventDefault(); if (selectStatus.value === 'si') addCurrentArticle(); else commitPerson.click();});
      const actions = node('div', 'report-risk-actions');
      const cancel = button('Cancelar', () => dialog.close());
      const saveButton = button('Guardar infracciones', async () => {
        if (selectPerson.value) {
          if (!selectStatus.value) {message.textContent = 'Indica si el involucrado se encuentra incurso.'; return;}
          if (selectStatus.value === 'no') {if (!ensurePerson()) return;}
          else if (pendingArticle()) {if (!addCurrentArticle()) return;}
          else {const evaluation = ensurePerson(); if (!evaluation || !evaluation.articulos.length) {message.textContent = 'Añade al menos un artículo para esta persona.'; return;}}
        }
        if (draft.some(item => item.incurso === 'si' && !item.articulos.length)) {message.textContent = 'Completa los artículos de las personas incursas o elimina su evaluación.'; return;}
        const previous = values; values = copy(draft); render();
        saveButton.disabled = true; cancel.disabled = true; editor.inert = true; peopleList.inert = true;
        const preventClose = event => event.preventDefault(); dialog.addEventListener('cancel', preventClose);
        try {
          if (await save()) {context.articleTemplates = [...(context.articleTemplates || []), ...values.flatMap(evaluation => evaluation.articulos).map(item => copy(item))]; dialog.close();}
          else {values = previous; render(); message.textContent = status.textContent;}
        } finally {saveButton.disabled = false; cancel.disabled = false; editor.inert = false; peopleList.inert = false; dialog.removeEventListener('cancel', preventClose);}
      });
      actions.append(cancel, saveButton); dialog.append(title, references(), editor, peopleList, message, actions);
      dialog.addEventListener('close', () => dialog.remove()); document.body.append(dialog); dialog.showModal();
      if (initialIndex !== null) load(String(draft[initialIndex].involucrado_id)); else {update(); draw(); selectPerson.focus();}
    };
    render(); add.addEventListener('click', () => openModal());
    return () => copy(values);
  };
})();
