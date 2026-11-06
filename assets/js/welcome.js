(() => {
  const form = document.querySelector('.welcome-search');
  if (!form) return;
  const fields = Array.from(form.querySelectorAll('[data-field]'));
  const status = form.querySelector('.welcome-status');
  const shortcuts = document.querySelector('.welcome-shortcuts');
  const workspace = document.querySelector('.welcome-workspace');
  let timer;
  let controller;
  let revision = 0;
  let composing = false;
  const currentInput = () => form.querySelector('[name="valor"]:not(:disabled)');
  const greeting = document.querySelector('.welcome-hero');
  const syncSearchLayout = (resetScroll = false) => {
    const active = currentInput().value.trim() !== '';
    const entering = active && !document.body.classList.contains('welcome-search-active');
    if (greeting) greeting.hidden = active;
    document.body.classList.toggle('welcome-search-active', active);
    if (entering && resetScroll && window.scrollY > 0) window.scrollTo({ top: 0, behavior: 'instant' });
  };
  syncSearchLayout();
  const announce = message => {
    status.textContent = message;
    status.hidden = !message;
  };
  const invalidate = () => {
    clearTimeout(timer);
    controller?.abort();
    revision++;
    form.removeAttribute('aria-busy');
    document.getElementById('accident-filter-results')?.removeAttribute('aria-busy');
  };
  const hideResults = () => {
    const results = document.getElementById('accident-filter-results');
    results.hidden = true;
    results.replaceChildren();
    document.getElementById('welcome-pagination').replaceChildren();
  };
  const searchUrl = () => {
    const url = new URL(form.action, location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    return url;
  };
  const clear = () => {
    invalidate();
    fields.forEach(field => { field.querySelector('input').value = ''; });
    syncSearchLayout();
    hideResults();
    form.querySelector('.welcome-error')?.remove();
    if (shortcuts) shortcuts.hidden = false;
    announce('');
    const url = new URL(form.action, location.href);
    url.searchParams.set('criterio', form.dataset.criterion);
    history.replaceState(null, '', url);
    currentInput().focus();
  };
  const search = async (url = searchUrl(), pagination = false) => {
    invalidate();
    if (!currentInput().value.trim()) { clear(); return; }
    syncSearchLayout(true);
    const requestRevision = revision;
    controller = new AbortController();
    form.setAttribute('aria-busy', 'true');
    announce('Buscando expedientes…');
    if (shortcuts) shortcuts.hidden = true;
    try {
      const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (requestRevision !== revision) return;
      if (response.redirected && new URL(response.url).pathname.endsWith('/login.php')) {
        location.assign(response.url);
        return;
      }
      if (!response.ok) throw new Error('Search failed');
      const html = await response.text();
      if (requestRevision !== revision) return;
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const results = doc.getElementById('accident-filter-results');
      const paginationContent = doc.getElementById('welcome-pagination');
      if (!results || !paginationContent) throw new Error('Missing results');
      const error = doc.querySelector('.welcome-error');
      if (error) throw new Error('Search unavailable');
      document.getElementById('accident-filter-results').replaceWith(results);
      document.getElementById('welcome-pagination').replaceWith(paginationContent);
      if (typeof initAccidentResults === 'function') initAccidentResults();
      history.replaceState(null, '', url);
      announce(results.querySelector('.welcome-results-heading > span')?.textContent.trim() || 'Búsqueda completada.');
      if (pagination) {
        const scroller = document.querySelector('.welcome-results-scroll');
        if (scroller) scroller.scrollTop = 0;
        else results.scrollIntoView({ block: 'start' });
      }
    } catch (error) {
      if (requestRevision !== revision || error.name === 'AbortError') return;
      hideResults();
      announce('No pudimos cargar los resultados. Pulsa Buscar para intentarlo nuevamente.');
    } finally {
      if (requestRevision === revision) form.removeAttribute('aria-busy');
    }
  };
  const schedule = () => {
    syncSearchLayout(true);
    invalidate();
    hideResults();
    form.querySelector('.welcome-error')?.remove();
    announce('');
    if (!currentInput().value.trim()) { clear(); return; }
    if (shortcuts) shortcuts.hidden = true;
    if (!composing) timer = setTimeout(() => search(), 300);
  };
  fields.forEach(field => {
    const input = field.querySelector('input');
    input.addEventListener('input', schedule);
    input.addEventListener('compositionstart', () => { composing = true; invalidate(); });
    input.addEventListener('compositionend', () => { composing = false; schedule(); });
  });
  form.querySelectorAll('[name="criterio"]').forEach(radio => {
    radio.addEventListener('change', () => {
      fields.forEach(field => {
        const active = field.dataset.field === radio.value;
        field.hidden = !active;
        field.querySelector('input').disabled = !active;
      });
      form.dataset.criterion = radio.value;
      clear();
    });
  });
  form.addEventListener('submit', event => { event.preventDefault(); search(); });
  form.querySelector('.welcome-clear').addEventListener('click', event => { event.preventDefault(); clear(); });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || event.isComposing) return;
    // Leave Escape to an open dialog or card menu when focus is outside the search.
    if (!form.contains(document.activeElement) && document.querySelector('[role="dialog"], .acc-actions-menu[open]')) return;
    event.preventDefault();
    clear();
  });
  document.addEventListener('click', event => {
    const link = event.target.closest('#welcome-pagination a');
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    search(new URL(link.href), true);
  });

  // The greeting scrolls away; the question, criteria and search remain below the account bar.
  const topbar = document.querySelector('.uiat-user-topbar');
  const measure = () => {
    const top = topbar ? topbar.getBoundingClientRect().height : 0;
    document.body.style.setProperty('--welcome-sticky-top', `${top}px`);
    document.body.style.setProperty('--welcome-results-top', `${top + workspace.getBoundingClientRect().height + 16}px`);
  };
  const sentinel = document.createElement('div');
  sentinel.className = 'welcome-sticky-sentinel';
  sentinel.setAttribute('aria-hidden', 'true');
  workspace.before(sentinel);
  let frame;
  const updateSticky = () => {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => {
      const top = topbar ? topbar.getBoundingClientRect().bottom : 0;
      workspace.classList.toggle('is-stuck', sentinel.getBoundingClientRect().top < top);
    });
  };
  if (typeof ResizeObserver !== 'undefined') {
    const observer = new ResizeObserver(() => { measure(); updateSticky(); });
    observer.observe(workspace);
    if (topbar) observer.observe(topbar);
  }
  addEventListener('resize', () => { measure(); updateSticky(); });
  addEventListener('scroll', updateSticky, { passive: true });
  measure();
  updateSticky();
})();
