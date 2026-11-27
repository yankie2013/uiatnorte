// Saved inspections keep all sections readable when JavaScript is unavailable.
(() => {
  document.querySelectorAll('.itp-organized-view').forEach((view, recordIndex) => {
    const sections = Array.from(view.children).filter(el => el.classList.contains('itp-section'));
    if (sections.length < 2) return;
    const nav = document.createElement('div');
    nav.className = 'itp-section-tabs';
    nav.setAttribute('role', 'tablist');
    nav.setAttribute('aria-label', 'Secciones de la inspección técnico policial');
    const buttons = sections.map((section, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.id = `itp-section-tab-${recordIndex}-${index}`;
      section.id = `itp-section-panel-${recordIndex}-${index}`;
      button.textContent = section.querySelector('h3').textContent;
      button.setAttribute('role', 'tab');
      button.setAttribute('aria-controls', section.id);
      section.setAttribute('role', 'tabpanel');
      section.setAttribute('aria-labelledby', button.id);
      section.tabIndex = 0;
      button.addEventListener('click', () => activate(index));
      button.addEventListener('keydown', event => {
        let next;
        if (event.key === 'ArrowRight') next = (index + 1) % sections.length;
        if (event.key === 'ArrowLeft') next = (index - 1 + sections.length) % sections.length;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = sections.length - 1;
        if (next === undefined) return;
        event.preventDefault();
        activate(next);
        buttons[next].focus();
      });
      nav.append(button);
      return button;
    });
    function activate(index) {
      sections.forEach((section, i) => {
        section.hidden = i !== index;
        buttons[i].setAttribute('aria-selected', String(i === index));
        buttons[i].tabIndex = i === index ? 0 : -1;
      });
    }
    view.prepend(nav);
    activate(0);
  });
})();
