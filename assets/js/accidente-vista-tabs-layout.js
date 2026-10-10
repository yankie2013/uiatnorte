// Layout and navigation for the accident tab workspace.
(() => {
  const page = document.querySelector('.case-overview-layout');
  const people = page?.querySelector('.case-header-people');
  const tabs = document.getElementById('accTabs');
  const participantTab = document.getElementById('participantes-tab');
  const participantPane = document.getElementById('participantes');
  // La columna lateral y las pestañas no deben depender del botón de pestaña
  // Participantes: en algunas versiones del marcado ese botón está ausente.
  if (!page || !people || !tabs) return;
  const sidebar = document.createElement('aside');
  sidebar.className = 'case-module-sidebar';
  sidebar.setAttribute('aria-label', 'Secciones del accidente');
  const details = document.createElement('details');
  details.className = 'case-participants-disclosure';
  const summary = document.createElement('summary');
  summary.textContent = 'Participantes';
  const heading = people.querySelector('.case-header-people-heading');
  const count = heading?.querySelector('h2 span');
  if (count) summary.append(' · ' + count.textContent.trim());
  if (heading) heading.hidden = true;
  const arrow = document.createElement('button');
  arrow.type = 'button';
  arrow.className = 'participants-list-toggle';
  arrow.setAttribute('aria-label', 'Expandir lista de participantes');
  arrow.setAttribute('aria-expanded', 'false');
  people.id = 'sidebar-participants-list';
  arrow.setAttribute('aria-controls', people.id);
  arrow.textContent = '⌄';
  summary.append(arrow);
  arrow.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    details.open = !details.open;
  });
  details.append(summary, people);
  sidebar.append(details, tabs);
  page.append(sidebar);
  const generalButton = document.createElement('button');
  generalButton.type = 'button';
  generalButton.className = 'case-mobile-general-button';
  generalButton.textContent = '📁 Datos generales';
  const navigateMobileSection = (section) => {
    const url = new URL(window.location.href);
    url.searchParams.set('mobile_section', section);
    if (section === 'datos-generales') url.searchParams.delete('tab');
    else url.searchParams.set('tab', section);
    window.location.assign(url.href);
  };
  generalButton.addEventListener('click', () => navigateMobileSection('datos-generales'));
  tabs.addEventListener('click', event => {
    if (!mobileLayout.matches) return;
    const trigger = event.target.closest('.nav-link[data-bs-target]');
    if (!trigger) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    navigateMobileSection(trigger.dataset.bsTarget.slice(1));
  }, true);
  tabs.prepend(generalButton);
  const registration = page.querySelector('.case-registration-block');
  const registrationAnchor = document.createComment('Registro del expediente');
  registration?.before(registrationAnchor);
  const mobileLayout = window.matchMedia('(max-width:760px)');
  const generalRecord = document.getElementById('datos-generales');
  const generalAnchor = document.createComment('Datos generales');
  generalRecord?.before(generalAnchor);
  const generalScreen = document.createElement('section');
  generalScreen.className = 'case-mobile-general-screen';
  page.append(generalScreen);
  const screenHeading = document.createElement('div');
  screenHeading.className = 'case-mobile-screen-heading';
  const backLink = document.createElement('a');
  const backUrl = new URL(window.location.href);
  backUrl.searchParams.delete('mobile_section');
  backUrl.searchParams.delete('tab');
  backUrl.hash = '';
  backLink.href = backUrl.href;
  backLink.textContent = '← Atrás';
  const screenTitle = document.createElement('h1');
  screenHeading.append(backLink, screenTitle);
  page.append(screenHeading);
  const adaptCaseMobile = () => {
    if (participantTab) participantTab.hidden = !mobileLayout.matches;
    tabs.setAttribute('aria-orientation', mobileLayout.matches ? 'horizontal' : 'vertical');
    const generalContent = document.querySelector('#general-details-modal .general-modal-content');
    if (registration && generalContent) {
      if (mobileLayout.matches) {
        generalScreen.append(registration);
        if (generalRecord) generalScreen.append(generalRecord);
      } else {
        registrationAnchor.after(registration);
        if (generalRecord) generalAnchor.after(generalRecord);
      }
    }
  };
  const syncMobileScreen = () => {
    const params = new URLSearchParams(window.location.search);
    const requested = params.get('mobile_section') || params.get('tab') || '';
    const selected = requested === 'datos-generales' || document.getElementById(requested + '-tab') ? requested : '';
    page.classList.toggle('case-mobile-hub', mobileLayout.matches && !selected);
    page.classList.toggle('case-mobile-section', mobileLayout.matches && !!selected);
    page.classList.toggle('case-mobile-general-active', mobileLayout.matches && selected === 'datos-generales');
    screenTitle.textContent = selected === 'datos-generales' ? 'Datos generales' : (document.getElementById(selected + '-tab')?.querySelector('.main-tab-title')?.textContent || 'Expediente');
  };
  mobileLayout.addEventListener('change', () => { adaptCaseMobile(); syncMobileScreen(); });
  adaptCaseMobile();
  // Measure the actual header: wrapped text and zoom change its height.
  const caseFacts = page.querySelector('.case-header-facts-list');
  if (caseFacts) {
    const updateStickyLayout = () => {
      const headerHeight = Math.ceil(caseFacts.getBoundingClientRect().height);
      const enoughSpace = window.innerWidth > 900 && window.innerHeight - headerHeight >= 260;
      page.classList.toggle('case-sticky-ready', enoughSpace);
      page.style.setProperty('--case-sticky-offset', (enoughSpace ? headerHeight + 20 : 12) + 'px');
    };
    if ('ResizeObserver' in window) {
      new ResizeObserver(updateStickyLayout).observe(caseFacts);
    }
    window.addEventListener('resize', updateStickyLayout);
    updateStickyLayout();
  }

  tabs.setAttribute('aria-orientation', mobileLayout.matches ? 'horizontal' : 'vertical');
  details.open = false;
  participantPane?.classList.add('show-participants-overview');
  const activateModuleTab = (trigger) => {
    if (!trigger) return;
    const targetSelector = trigger.getAttribute('data-bs-target');
    const targetPane = targetSelector ? page.querySelector(targetSelector) : null;
    if (!targetPane) return;
    const previous = tabs.querySelector('.nav-link.active');
    tabs.querySelectorAll('.nav-link').forEach((button) => {
      const selected = button === trigger;
      button.classList.toggle('active', selected);
      button.setAttribute('aria-selected', String(selected));
      button.tabIndex = selected ? 0 : -1;
    });
    page.querySelectorAll('.tabs-shell-main > .tab-content > .tab-pane').forEach((pane) => pane.classList.remove('show', 'active'));
    targetPane.classList.add('show', 'active');
    trigger.dispatchEvent(new CustomEvent('shown.bs.tab', {
      bubbles: true,
      detail: { relatedTarget: previous && previous !== trigger ? previous : null }
    }));
  };
  // Cambiar paneles localmente: la vista sigue funcionando aunque Bootstrap JS
  // no cargue en producción o su CDN esté inaccesible.
  tabs.addEventListener('click', (event) => {
    const trigger = event.target.closest('.nav-link[data-bs-target]');
    if (!trigger || !tabs.contains(trigger)) return;
    event.preventDefault();
    event.stopPropagation();
    activateModuleTab(trigger);
  });
  const requestedTab = new URLSearchParams(window.location.search).get('tab') || '';
  const tabAliases = { documentos: 'documentos-recibidos', resumen: 'resumen-integral' };
  const requestedTabId = tabAliases[requestedTab] || requestedTab;
  const requestedTrigger = requestedTabId ? document.getElementById(requestedTabId + '-tab') : null;
  activateModuleTab(requestedTrigger || tabs.querySelector('.nav-link.active'));
  syncMobileScreen();
  summary.addEventListener('click', (event) => {
    event.preventDefault();
    participantPane?.classList.add('show-participants-overview');
    activateModuleTab(participantTab);
  });
  document.addEventListener('click', (event) => {
    if (event.target.closest('.js-sidebar-view-person')) participantPane?.classList.remove('show-participants-overview');
  }, true);
  details.addEventListener('toggle', () => {
    arrow.setAttribute('aria-expanded', String(details.open));
    arrow.setAttribute('aria-label', details.open ? 'Contraer lista de participantes' : 'Expandir lista de participantes');
    arrow.textContent = details.open ? '⌃' : '⌄';
  });
})();
