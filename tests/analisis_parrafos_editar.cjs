// Run with jsdom through NODE_PATH. Exercises the actual editor without a server.
const {JSDOM} = require('jsdom');
const {readFileSync} = require('node:fs');
const assert = require('node:assert/strict');
const script = readFileSync(require('node:path').join(__dirname, '../assets/js/accidente-analisis-informe.js'), 'utf8');
function setup(section, data) {
  const context = {caseId: 1, saved: {[section]: {version: 1, data}}, vehicles: [{id: 7, placa: 'TEST-123', label: 'Automóvil TEST-123'}], people: [], editable: true, csrf: 'fixture'};
  const dom = new JSDOM(`<script type="application/json" id="report-analysis-context">${JSON.stringify(context)}</script><form class="report-analysis" data-section="${section}"><h3>Análisis</h3><fieldset><div class="report-analysis-groups"></div></fieldset><p role="status"></p></form>`, {runScripts: 'outside-only', url: 'https://example.test/accidente_vista_tabs.php'});
  const {window: w} = dom;
  w.HTMLDialogElement.prototype.showModal = function () {this.open = true;};
  w.HTMLDialogElement.prototype.close = function () {this.open = false; this.dispatchEvent(new w.Event('close'));};
  const saves = [];
  w.fetch = async (_, options) => {saves.push(JSON.parse(options.body.get('contenido'))); return {ok: true, json: async () => ({ok: true, version: 2})};};
  w.eval(script);
  return {dom, w, doc: w.document, saves};
}
const clickText = (root, label) => {
  const button = [...root.querySelectorAll('button')].find(b => b.textContent === label);
  assert(button, `Falta el botón ${label}`); button.click();
};
const tick = () => new Promise(resolve => setImmediate(resolve));
(async () => {
  for (const section of ['velocidad', 'danos-lesiones', 'integral']) {
    const {dom, doc, saves} = setup(section, {parrafos: [{texto: 'Primer párrafo'}, {texto: 'Segundo párrafo'}]});
    clickText(doc.querySelector('[data-group="parrafos"]'), 'Añadir párrafo');
    const dialog = doc.querySelector('dialog');
    const items = () => [...dialog.querySelectorAll('.report-risk-draft > li')];
    assert.equal(dialog.querySelectorAll('.report-draft-item-actions button').length, 4);
    clickText(items()[1], 'Editar');
    const input = dialog.querySelector('textarea');
    assert.equal(input.value, 'Segundo párrafo');
    input.value = 'Segundo corregido'; clickText(dialog, 'Actualizar');
    assert.equal(items().length, 2, 'Editar no debe duplicar el párrafo.');
    assert.match(items()[1].textContent, /Segundo corregido/);
    clickText(items()[0], 'Editar'); input.value = 'Cambio descartado'; clickText(dialog, 'Cancelar edición');
    assert.match(items()[0].textContent, /Primer párrafo/);
    clickText(items()[1], 'Editar');
    clickText(items()[0], 'Eliminar');
    input.value = 'Párrafo restante corregido'; clickText(dialog, 'Actualizar');
    assert.equal(items().length, 1, 'Eliminar antes del elemento editado debe ajustar su índice.');
    clickText(dialog, 'Guardar párrafos'); await tick();
    assert.deepEqual(saves[0].parrafos, [{texto: 'Párrafo restante corregido'}]);
    assert.equal(doc.querySelector('dialog'), null);
    dom.window.close();
    console.log(`${section}: editar, cancelar, eliminar, orden y guardar OK`);
  }
  {
    const {dom, doc, saves} = setup('velocidad', {estimaciones: [{vehiculo_id: 7, desplazamiento: 'Estimación anterior'}]});
    clickText(doc.querySelector('[data-group="estimaciones"]'), 'Añadir estimación');
    const dialog = doc.querySelector('dialog'); clickText(dialog.querySelector('li'), 'Editar');
    assert.equal(dialog.querySelector('select').value, '7');
    dialog.querySelector('textarea').value = 'Estimación corregida';
    clickText(dialog, 'Guardar estimaciones'); await tick();
    assert.deepEqual(saves[0].estimaciones, [{vehiculo_id: '7', desplazamiento: 'Estimación corregida'}]);
    dom.window.close(); console.log('Estimaciones: edición directa al guardar conserva el vehículo OK');
  }
  {
    const {dom, doc, saves} = setup('integral', {parrafos: Array.from({length: 200}, (_, i) => ({texto: 'Párrafo ' + i}))});
    clickText(doc.querySelector('[data-group="parrafos"]'), 'Añadir párrafo');
    const dialog = doc.querySelector('dialog'); clickText(dialog.querySelector('li'), 'Editar');
    const input = dialog.querySelector('textarea'); input.value = '';
    clickText(dialog, 'Guardar párrafos'); await tick(); assert.equal(saves.length, 0, 'No guardar una edición vacía.');
    input.value = 'Corregido en lista completa'; clickText(dialog, 'Actualizar');
    assert.equal(dialog.querySelectorAll('li').length, 200);
    clickText(dialog, 'Guardar párrafos'); await tick(); assert.equal(saves[0].parrafos.length, 200);
    dom.window.close(); console.log('Lista completa: permite editar 200 registros y rechaza párrafo vacío OK');
  }
})().catch(error => {console.error(error); process.exitCode = 1;});
