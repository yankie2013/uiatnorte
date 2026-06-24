(function () {
  const tabs = document.getElementById('anexos_tabs');
  const panels = document.getElementById('anexos_panels');
  const addButton = document.getElementById('anexo_agregar');
  if (!tabs || !panels || !addButton) return;

  const items = () => ({
    tabs: Array.from(tabs.querySelectorAll('.annex-tab')),
    panels: Array.from(panels.querySelectorAll('.annex-panel'))
  });

  function activate(index, focusTab) {
    const current = items();
    const safeIndex = Math.max(0, Math.min(index, current.tabs.length - 1));
    current.tabs.forEach((tab, itemIndex) => {
      const active = itemIndex === safeIndex;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
      current.panels[itemIndex].hidden = !active;
    });
    if (focusTab) current.tabs[safeIndex]?.focus();
  }

  function refresh() {
    const current = items();
    current.tabs.forEach((tab, index) => {
      const tabId = 'anexo_tab_' + index;
      const panelId = 'anexo_panel_' + index;
      tab.textContent = 'Anexo ' + (index + 1);
      tab.id = tabId;
      tab.setAttribute('aria-controls', panelId);
      current.panels[index].id = panelId;
      current.panels[index].setAttribute('aria-labelledby', tabId);
      current.panels[index].querySelector('.annex-panel-title').textContent = 'Anexo remitido ' + (index + 1);
      current.panels[index].querySelector('.annex-remove').disabled = current.panels.length === 1;
    });
  }

  tabs.addEventListener('click', (event) => {
    const tab = event.target.closest('.annex-tab');
    if (tab) activate(items().tabs.indexOf(tab), false);
  });

  tabs.addEventListener('keydown', (event) => {
    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
    event.preventDefault();
    const current = items();
    const active = current.tabs.indexOf(event.target.closest('.annex-tab'));
    activate((active + (event.key === 'ArrowRight' ? 1 : -1) + current.tabs.length) % current.tabs.length, true);
  });

  addButton.addEventListener('click', () => {
    const tab = document.createElement('button');
    tab.type = 'button';
    tab.className = 'annex-tab';
    tab.setAttribute('role', 'tab');
    tabs.appendChild(tab);

    const panel = document.createElement('div');
    panel.className = 'annex-panel';
    panel.setAttribute('role', 'tabpanel');
    panel.innerHTML = '<div class="annex-panel-head"><span class="annex-panel-title"></span><button type="button" class="annex-remove">Quitar anexo</button></div><label>Descripción del anexo</label><textarea name="anexos[]" maxlength="1000" placeholder="Ej.: Un CD que contiene grabaciones de videovigilancia"></textarea>';
    panels.appendChild(panel);
    refresh();
    activate(items().tabs.length - 1, false);
    panel.querySelector('textarea').focus();
  });

  panels.addEventListener('click', (event) => {
    const removeButton = event.target.closest('.annex-remove');
    if (!removeButton || removeButton.disabled) return;
    const current = items();
    const panel = removeButton.closest('.annex-panel');
    const index = current.panels.indexOf(panel);
    current.tabs[index].remove();
    panel.remove();
    refresh();
    activate(Math.min(index, items().tabs.length - 1), true);
  });

  refresh();
  activate(0, false);
})();
