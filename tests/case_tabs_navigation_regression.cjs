// Run with jsdom available through NODE_PATH; no server or database required.
const { JSDOM } = require('jsdom');
const { readFileSync } = require('node:fs');
const assert = require('node:assert/strict');
const { join } = require('node:path');
const dom = new JSDOM(`
<div class="case-overview-layout">
  <div class="case-header-people"><div class="case-header-people-heading"><h2>Participantes <span>1</span></h2></div></div>
  <div class="tabs-shell-main">
    <div id="accTabs">
      <button id="participantes-tab" class="nav-link active" data-bs-target="#participantes" hidden>Participantes</button>
      <button id="itp-tab" class="nav-link" data-bs-target="#itp">ITP</button>
    </div>
    <div class="tab-content">
      <div id="participantes" class="tab-pane active show">
        <div class="tab-content"><div id="persona" class="tab-pane active show">
          <div class="tab-content"><div id="datos-personales" class="tab-pane active show">Ficha</div></div>
        </div></div>
      </div>
      <div id="itp" class="tab-pane">ITP</div>
    </div>
  </div>
</div>`, { runScripts: 'outside-only', url: 'https://example.test/accidente_vista_tabs.php' });
const { window } = dom;
const doc = window.document;
let shown = 0;
doc.getElementById('participantes-tab').addEventListener('shown.bs.tab', () => shown++);
window.eval(readFileSync(join(__dirname, '../assets/js/accidente-vista-tabs-layout.js'), 'utf8'));
function checkPerson() {
  for (const id of ['persona', 'datos-personales']) {
    assert(doc.getElementById(id).classList.contains('active'), `${id} must retain its active state`);
    assert(doc.getElementById(id).classList.contains('show'), `${id} must retain its visibility`);
  }
}
checkPerson();
doc.getElementById('itp-tab').click();
assert(doc.getElementById('itp').classList.contains('active'));
assert(!doc.getElementById('participantes').classList.contains('active'));
doc.querySelector('.case-participants-disclosure > summary').click();
assert(doc.getElementById('participantes').classList.contains('active'));
checkPerson();
assert.equal(shown, 2, 'Tab lifecycle events must be dispatched on the trigger');
console.log('PASS: initial person details and nested panes survive module navigation.');
window.close();
