(() => {
  const root = document.getElementById('uiat-navigation');
  if (!root || root.dataset.initialized) return;
  root.dataset.initialized = 'true';
  // Several existing forms also run inside an iframe without an embed parameter.
  if (window.self !== window.top) { root.remove(); return; }
  const sidebar = document.getElementById('app-sidebar');
  const toggle = document.getElementById('sidebar-toggle');
  const backdrop = document.getElementById('sidebar-backdrop');
  const mobile = window.matchMedia('(max-width:760px)');
  const mouse = window.matchMedia('(hover:hover) and (pointer:fine)');
  let keyboard = false;
  let hover = false;
  let closeTimer;
  const links = [...sidebar.querySelectorAll('a')];
  document.body.classList.add('uiat-has-sidebar');
  if (!document.body.classList.contains('uiat-dashboard') && !document.querySelector('.uiat-user-topbar')) {
    const template = root.querySelector('#uiat-accountbar-template');
    const bar = template?.content.firstElementChild?.cloneNode(true);
    if (bar) {
      const heading = document.querySelector('main h1, .wrap h1, .page h1, h1');
      const section = heading?.textContent?.trim() || document.title.split(/[|·]/)[0].trim() || 'Panel general';
      const breadcrumb = bar.querySelector('.uiat-user-breadcrumb strong');
      if (breadcrumb && !bar.hasAttribute('data-custom-breadcrumb')) breadcrumb.textContent = section;
      bar.classList.add('uiat-global-topbar');
      document.body.prepend(bar);
      document.body.classList.add('uiat-has-accountbar');
    }
  }
  const setOpen = (open, restoreFocus = false) => {
    clearTimeout(closeTimer);
    sidebar.classList.toggle('expanded', open);
    document.body.classList.toggle('uiat-sidebar-open', mobile.matches && open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    sidebar.inert = mobile.matches && !open;
    backdrop.hidden = !(mobile.matches && open);
    if (restoreFocus) toggle.focus();
  };
  document.addEventListener('keydown', event => {
    if (event.key === 'Tab') keyboard = true;
    if (event.key === 'Escape' && sidebar.classList.contains('expanded')) {
      if (mobile.matches || !mouse.matches) setOpen(false, true);
      else {
        if (sidebar.contains(document.activeElement)) document.activeElement.blur();
        setOpen(false);
      }
    }
    if (event.key === 'Tab' && mobile.matches && sidebar.classList.contains('expanded')) {
      const items = [toggle, ...links];
      const position = items.indexOf(document.activeElement);
      if (event.shiftKey && position <= 0) { event.preventDefault(); items.at(-1).focus(); }
      else if (!event.shiftKey && (position < 0 || position === items.length - 1)) { event.preventDefault(); toggle.focus(); }
    }
  });
  document.addEventListener('pointerdown', () => { keyboard = false; }, true);
  sidebar.addEventListener('pointerenter', event => {
    if (mobile.matches || event.pointerType !== 'mouse' || !mouse.matches) return;
    hover = true;
    setOpen(true);
  });
  sidebar.addEventListener('pointerleave', event => {
    if (mobile.matches || event.pointerType !== 'mouse') return;
    hover = false;
    closeTimer = setTimeout(() => {
      if (!(keyboard && sidebar.contains(document.activeElement))) setOpen(false);
    }, 140);
  });
  sidebar.addEventListener('focusin', () => {
    if (!mobile.matches && keyboard) setOpen(true);
  });
  sidebar.addEventListener('focusout', event => {
    if (!mobile.matches && !sidebar.contains(event.relatedTarget) && !hover) setOpen(false);
  });
  toggle.addEventListener('click', () => {
    const open = !sidebar.classList.contains('expanded');
    setOpen(open);
    if (open && mobile.matches) links[0].focus();
  });
  backdrop.addEventListener('click', () => setOpen(false, true));
  document.addEventListener('pointerdown', event => {
    if (!root.contains(event.target) && sidebar.classList.contains('expanded')) setOpen(false);
  });
  document.addEventListener('click', event => {
    const returnLink = event.target.closest('.uiat-user-breadcrumb a[data-restore-list]');
    if (returnLink) sessionStorage.setItem('uiat-restore-list', new URL(returnLink.href, location.href).href);
    document.querySelectorAll('.uiat-user-menu[open]').forEach(menu => {
      if (!menu.contains(event.target)) menu.open = false;
    });
  });
  links.forEach(link => link.addEventListener('click', () => setOpen(false)));
  mobile.addEventListener('change', () => {
    const focused = sidebar.contains(document.activeElement);
    hover = false;
    setOpen(false, focused && mobile.matches);
  });
  window.addEventListener('pageshow', () => { hover = false; setOpen(false); });
  setOpen(false);
})();
