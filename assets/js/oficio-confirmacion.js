(() => {
  const dialog = document.getElementById('oficio-confirmacion');
  const form = document.getElementById('frmOficio') || document.querySelector('form.card');
  if (!dialog || !form) return;
  const summary = document.getElementById('oficio-review-summary');
  let approved = false;
  let opener;
  const get = id => document.getElementById(id);
  const value = id => {
    const field = get(id);
    if (!field) return '';
    if (field.tagName === 'SELECT') return field.value ? (field.selectedOptions[0]?.textContent.trim() || '') : '';
    return field.value?.trim() || '';
  };
  function add(label, text) {
    if (!text) return;
    const title = document.createElement('dt'); title.textContent = label;
    const body = document.createElement('dd'); body.textContent = text;
    summary.append(title, body);
  }
  function fillSummary() {
    summary.replaceChildren();
    add('Comisaría', value('comisaria_id'));
    add('Encargado', value('encargado_id'));
    add('Expediente', value('accidente_id'));
    add('Número', `${value('numero_oficio') || 'Automático'} / ${value('anio_oficio')}`);
    const date = value('fecha_emision');
    add('Fecha de emisión', /^\d{4}-\d{2}-\d{2}$/.test(date) ? date.split('-').reverse().join('/') : date);
    const yearNames = JSON.parse(dialog.dataset.officialYears || '{}');
    add('Nombre oficial del año', yearNames[get('oficial_ano_id')?.value] || '');
    add('Tipo de asunto', value('tipo'));
    add('Categoría / plantilla', value('plantilla_nombre') || value('categoria') || value('asunto_id'));
    add('Contenido', value('asunto_texto') || value('motivo'));
    add('Entidad de destino', value('entidad_id_text'));
    add('Grado y cargo', value('grado_cargo_text'));
    add('Persona destino', value('persona_id_text'));
    if (get('vehiculoBox')?.style.display !== 'none') add(get('vehiculo_manual')?.disabled === false ? 'Placa del vehículo' : 'Vehículo', value('vehiculo_manual') || value('involucrado_vehiculo_id'));
    if (get('fallecidoBox')?.style.display !== 'none') add(get('personaInvolucradaLabel')?.textContent || 'Persona involucrada', value('persona_manual') || value('involucrado_persona_id'));
    if (get('camaraRangoBox')?.style.display !== 'none' && value('camara_rango_desde')) add('Rango de cámaras', `${value('camara_rango_desde')} – ${value('camara_rango_hasta')}`);
    add('Día de cámaras', value('camara_fecha'));
    add('Diligencias solicitadas', value('diligencias_solicitadas'));
    add('Referencia', value('referencia_texto'));
    add('Estado', value('estado'));
  }
  form.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    if (approved) {
      approved = false;
      dialog.querySelectorAll('button').forEach(button => { button.disabled = true; });
      form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
      return;
    }
    event.preventDefault();
    opener = event.submitter || document.activeElement;
    fillSummary();
    dialog.showModal();
  });
  dialog.querySelectorAll('[data-review-cancel]').forEach(button => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('click', event => {
    const box = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) dialog.close();
  });
  dialog.addEventListener('close', () => opener?.focus());
  dialog.querySelectorAll('[data-review-save]').forEach(button => button.addEventListener('click', () => {
    let action = form.querySelector('input[name="save_action"]');
    if (!action) { action = document.createElement('input'); action.type = 'hidden'; action.name = 'save_action'; form.append(action); }
    action.value = button.dataset.reviewSave;
    approved = true;
    form.requestSubmit();
    approved = false;
  }));
})();
