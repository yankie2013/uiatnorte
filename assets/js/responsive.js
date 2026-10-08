(() => {
  const prepareTables = () => {
    document.querySelectorAll('table').forEach(table => {
      if (table.closest('.uiat-responsive-table,.table-wrap,.table-shell,.table-scroll,.table-responsive,[data-print-layout]') || table.closest('[contenteditable="true"]')) return;
      const wrapper = document.createElement('div');
      wrapper.className = 'uiat-responsive-table';
      wrapper.tabIndex = 0;
      wrapper.setAttribute('role', 'region');
      wrapper.setAttribute('aria-label', 'Tabla; desplaza horizontalmente para ver todas las columnas');
      table.before(wrapper);
      wrapper.append(table);
    });
  };
  const start = () => {
    prepareTables();
    const facts = document.querySelector('.case-overview-layout .case-facts-scroll');
    if (facts && !facts.closest('.uiat-mobile-case-summary')) {
      const details = document.createElement('details');
      details.className = 'uiat-mobile-case-summary';
      const summary = document.createElement('summary');
      summary.textContent = '📋 Datos generales del accidente';
      facts.before(details);
      details.append(summary, facts);
      const mobile = window.matchMedia('(max-width:760px)');
      const adapt = () => { details.open = !mobile.matches; };
      mobile.addEventListener('change', adapt);
      adapt();
    }
    new MutationObserver(records => {
      if (records.some(record => [...record.addedNodes].some(node => node instanceof Element && (node.matches('table') || node.querySelector('table'))))) prepareTables();
    }).observe(document.body, {childList:true, subtree:true});
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
  else start();
})();
