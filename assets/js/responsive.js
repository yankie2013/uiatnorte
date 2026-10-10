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
    document.querySelectorAll('form.grid, form .grid').forEach(grid => {
      if (!grid.querySelector(':scope > .c3, :scope > .c4, :scope > .c6, :scope > .c12')) return;
      grid.classList.add('uiat-compact-form-grid');
      grid.closest('form').classList.add('uiat-compact-form');
      Array.from(grid.children).forEach(field => {
        const control = field.querySelector('input:not([type="hidden"]), select, textarea');
        if (!control || control.tagName === 'TEXTAREA' || field.querySelectorAll('input:not([type="hidden"]), select').length > 1) return;
        const label = (field.querySelector('label')?.textContent || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        const shortType = ['date', 'time', 'number'].includes(control.type);
        const shortLabel = /^(sexo|edad|estado civil|nacionalidad|celular|telefono|numero de hijos|orden de citacion|oficio que ordena|clase|categoria de licencia)\b/.test(label);
        if (shortType || shortLabel) field.classList.add('uiat-compact-half');
      });
    });
    // Shared mobile filter layout, including listings whose controls are not wrapped in a grid.
    document.querySelectorAll('form[method="get" i]').forEach(form => {
      if (!form.querySelector('input:not([type="hidden"]), select')) return;
      form.classList.add('uiat-compact-filter');
      const grid = form.matches('.grid,.filters,.search') ? form : form.querySelector(':scope > .grid, :scope > .filters-grid');
      if (!grid || form.id === 'filterForm') return;
      grid.classList.add('uiat-filter-grid');
      Array.from(grid.children).forEach(field => {
        if (field.matches('input[type="hidden"]')) return;
        const controls = field.matches('input,select') ? [field] : Array.from(field.querySelectorAll('input:not([type="hidden"]),select'));
        if (controls.length === 1) {
          const control = controls[0];
          if (['date','time','number'].includes(control.type) || (control.tagName === 'SELECT' && !['accidente_id','persona_id'].includes(control.name))) field.classList.add('uiat-filter-half');
        }
        if (field.matches('button,a.btn') || (!controls.length && field.querySelector('button,a.btn'))) field.classList.add('uiat-filter-actions');
      });
    });
    const topbar = document.querySelector('.uiat-user-topbar');
    const breadcrumb = topbar?.querySelector('.uiat-user-breadcrumb');
    if (breadcrumb) {
      const anchor = document.createComment('breadcrumb position');
      breadcrumb.before(anchor);
      const mobileNavigation = window.matchMedia('(max-width:760px)');
      const adaptNavigation = () => {
        breadcrumb.classList.toggle('uiat-mobile-breadcrumb', mobileNavigation.matches);
        document.body.classList.toggle('uiat-separated-breadcrumb', mobileNavigation.matches);
        if (mobileNavigation.matches) topbar.after(breadcrumb);
        else anchor.after(breadcrumb);
      };
      mobileNavigation.addEventListener('change', adaptNavigation);
      adaptNavigation();
    }
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
