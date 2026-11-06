(() => {
  const layout = document.querySelector('.inbox-layout');
  const form = document.getElementById('inbox-filters');
  if (!layout) return;
  const list = document.getElementById('inbox-list');
  const detail = document.getElementById('inbox-detail');
  const listPane = document.querySelector('.inbox-list-pane');
  const detailPane = document.querySelector('.inbox-detail-pane');
  const status = document.getElementById('inbox-status');
  const stateFilters = document.querySelector('.inbox-state-filters');
  let activeState = stateFilters?.dataset.currentState || new URL(location.href).searchParams.get('estado') || '';
  const mobile = () => window.matchMedia('(max-width:800px)').matches;
  let selectedId = Number(layout.dataset.selectedId) || 0;
  let revision = 0;
  let controller;
  let timer;
  let lastAction;
  const announce = (message, error = false) => {
    status.textContent = message;
    status.classList.toggle('is-error', error);
    if (error) {
      const retry = document.createElement('button');
      retry.type = 'button'; retry.className = 'inbox-retry-button'; retry.textContent = 'Reintentar';
      retry.addEventListener('click', () => lastAction?.());
      status.append(' ', retry);
    }
  };
  const invalidate = () => {
    clearTimeout(timer);
    controller?.abort();
    revision++;
    list.removeAttribute('aria-busy'); detail.removeAttribute('aria-busy');
  };
  const urlFor = () => {
    const url = new URL(form?.action || 'diligencias_pendientes.php', location.href);
    if (form) url.search = new URLSearchParams(new FormData(form)).toString();
    else {
      const current = new URL(location.href);
      for (const key of ['q']) if (current.searchParams.has(key)) url.searchParams.set(key, current.searchParams.get(key));
    }
    if (!form) url.searchParams.set('estado', activeState);
    return url;
  };
  const syncUrl = page => {
    const url = urlFor();
    if (selectedId) url.searchParams.set('seleccion', String(selectedId));
    url.searchParams.set('page', String(page || new URL(location.href).searchParams.get('page') || 1));
    history.replaceState(null, '', url);
  };
  const request = async (url, target, apply) => {
    invalidate();
    const requestRevision = revision;
    controller = new AbortController();
    target.setAttribute('aria-busy', 'true');
    announce(target === list ? 'Buscando diligencias…' : 'Cargando expediente…');
    try {
      const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (requestRevision !== revision) return;
      if (response.redirected && new URL(response.url).pathname.endsWith('/login.php')) { location.assign(response.url); return; }
      const data = await response.json();
      if (requestRevision !== revision) return;
      if (!response.ok) throw new Error(data.error || 'No pudimos cargar la información.');
      apply(data);
    } catch (error) {
      if (requestRevision !== revision || error.name === 'AbortError') return;
      announce(error instanceof SyntaxError ? 'No pudimos leer la respuesta. Intenta nuevamente.' : (error.message === 'Failed to fetch' ? 'No pudimos conectar. Intenta nuevamente.' : error.message), true);
    } finally {
      if (requestRevision === revision) target.removeAttribute('aria-busy');
    }
  };
  const markSelected = id => {
    selectedId = Number(id) || 0;
    layout.dataset.selectedId = String(selectedId);
    list.querySelectorAll('[data-task-id]').forEach(row => {
      const active = Number(row.dataset.taskId) === selectedId;
      row.classList.toggle('is-selected', active);
      row.setAttribute('aria-selected', String(active));
      row.tabIndex = active ? 0 : -1;
    });
  };
  const loadList = (page = 1, locate = true) => {
    lastAction = () => loadList(page, locate);
    const url = urlFor();
    url.searchParams.set('fragment', 'list'); url.searchParams.set('page', String(page));
    url.searchParams.set('seleccion', String(selectedId));
    if (locate) url.searchParams.set('locate', '1');
    return request(url, list, data => {
      if (typeof data.listHtml !== 'string' || typeof data.detailHtml !== 'string') throw new Error('Respuesta de búsqueda inválida.');
      list.innerHTML = data.listHtml; detail.innerHTML = data.detailHtml;
      markSelected(data.selectedId);
      if (stateFilters && data.counts) {
        activeState = data.state ?? activeState;
        stateFilters.dataset.currentState = activeState;
        stateFilters.querySelectorAll('[data-inbox-state]').forEach(link => {
          const state = link.dataset.inboxState;
          link.classList.toggle('is-active', state === activeState);
          if (state === activeState) link.setAttribute('aria-current','true'); else link.removeAttribute('aria-current');
          link.querySelector('[data-state-count]').textContent = state === '' ? Object.values(data.counts).reduce((a,b) => a + Number(b),0) : data.counts[state] || 0;
        });
      }
      layout.classList.remove('show-detail');
      listPane.scrollTop = 0; detailPane.scrollTop = 0;
      list.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
      syncUrl(data.page);
      const counter = document.querySelector('.gestion > header .page-title-suffix');
      if (counter) counter.textContent = `${data.total} ${data.total === 1 ? 'diligencia' : 'diligencias'}`;
      announce(`${data.total} ${data.total === 1 ? 'diligencia' : 'diligencias'}.`);
    });
  };
  stateFilters?.addEventListener('click', event => {
    const link = event.target.closest('[data-inbox-state]');
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    activeState = link.dataset.inboxState;
    if (form) form.querySelector('[name="estado"]').value = activeState;
    loadList();
  });
  const dialog = document.getElementById('inbox-task-dialog');
  const frame = document.getElementById('inbox-task-frame');
  const modalStatus = document.getElementById('inbox-modal-status');
  const closeModal = () => {
    if (dialog?.open) dialog.close();
  };
  const openModal = id => {
    if (!dialog || !frame) return false;
    modalStatus.hidden = false;
    modalStatus.textContent = 'Cargando diligencia…';
    frame.hidden = true;
    document.getElementById('inbox-modal-title').textContent = `Consulta de diligencia #${id}`;
    const url = new URL('diligenciapendiente_ver.php', location.href);
    url.searchParams.set('id', String(id));
    url.searchParams.set('embed', '1');
    url.searchParams.set('return_to', location.pathname + location.search);
    frame.src = url.href;
    if (!dialog.open) dialog.showModal();
    return true;
  };
  if (dialog && frame) {
    dialog.querySelector('.inbox-modal-close').addEventListener('click', closeModal);
    dialog.addEventListener('click', event => {
      const box = dialog.getBoundingClientRect();
      if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) closeModal();
    });
    dialog.addEventListener('close', () => {
      frame.removeAttribute('src');
      frame.hidden = true;
      list.querySelector('[aria-selected="true"]')?.focus();
    });
    frame.addEventListener('load', () => {
      if (!dialog.open || !frame.getAttribute('src')) return;
      modalStatus.hidden = true; frame.hidden = false;
    });
    frame.addEventListener('error', () => {
      modalStatus.hidden = false;
      modalStatus.textContent = 'No pudimos cargar la diligencia. Cierra la ventana e inténtalo nuevamente.';
    });
    addEventListener('message', event => {
      if (event.origin !== location.origin || event.source !== frame.contentWindow || !dialog.open) return;
      if (event.data?.type === 'diligencia.close') { closeModal(); return; }
      if (['diligencia.saved', 'diligencia.deleted', 'acta.saved', 'acta.deleted'].includes(event.data?.type)) {
        closeModal(); loadList();
      }
    });
  }
  const select = (id, showConsultation = false) => {
    const consultationOpened = showConsultation && openModal(id);
    lastAction = () => select(id, showConsultation);
    const url = new URL(form?.action || 'diligencias_pendientes.php', location.href);
    url.searchParams.set('fragment', 'detail'); url.searchParams.set('id', String(id));
    return request(url, detail, data => {
      if (typeof data.detailHtml !== 'string' || Number(data.selectedId) !== Number(id)) throw new Error('Respuesta de expediente inválida.');
      detail.innerHTML = data.detailHtml;
      markSelected(data.selectedId);
      detailPane.scrollTop = 0;
      syncUrl(); announce('Expediente actualizado.');
      if (consultationOpened) return;
      if (mobile()) { layout.classList.add('show-detail'); detail.focus(); }
    });
  };
  list.addEventListener('click', event => {
    const row = event.target.closest('[data-task-id]');
    const page = event.target.closest('[data-page]');
    if ((!row && !page) || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    if (page) loadList(Number(page.dataset.page), false);
    else select(Number(row.dataset.taskId), true);
  });
  list.addEventListener('keydown', event => {
    const row = event.target.closest('[data-task-id]');
    if (!row) return;
    const rows = Array.from(list.querySelectorAll('[data-task-id]'));
    const index = rows.indexOf(row);
    let next;
    if (event.key === 'ArrowDown') next = rows[Math.min(index + 1, rows.length - 1)];
    if (event.key === 'ArrowUp') next = rows[Math.max(index - 1, 0)];
    if (event.key === 'Home') next = rows[0];
    if (event.key === 'End') next = rows[rows.length - 1];
    if (event.key === 'Enter' || event.key === ' ') next = row;
    if (!next) return;
    event.preventDefault(); next.focus(); select(Number(next.dataset.taskId), event.key === 'Enter' || event.key === ' ');
  });
  const backToList = () => {
    layout.classList.remove('show-detail');
    list.querySelector('[aria-selected="true"]')?.focus();
  };
  document.querySelector('.inbox-mobile-back').addEventListener('click', backToList);
  detailPane.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); backToList(); } });
  if (form) {
  let composing = false;
  form.addEventListener('input', () => {
    invalidate();
    if (!composing) timer = setTimeout(() => loadList(), 300);
  });
  form.querySelector('[name="q"]').addEventListener('compositionstart', () => { composing = true; invalidate(); });
  form.querySelector('[name="q"]').addEventListener('compositionend', () => { composing = false; invalidate(); timer = setTimeout(() => loadList(), 300); });
  form.querySelector('[name="estado"]').addEventListener('change', () => loadList());
  form.addEventListener('submit', event => { event.preventDefault(); loadList(); });
  document.getElementById('inbox-clear')?.addEventListener('click', event => {
    event.preventDefault(); form.querySelector('[name="q"]').value = ''; form.querySelector('[name="estado"]').value = ''; loadList();
  });
  }
  const measure = () => {
    if (!mobile()) layout.style.setProperty('--inbox-height', `${Math.max(280, window.innerHeight - layout.getBoundingClientRect().top - 20)}px`);
  };
  addEventListener('resize', measure);
  if (typeof ResizeObserver !== 'undefined') new ResizeObserver(measure).observe(document.querySelector('.gestion') || layout);
  measure();
})();
