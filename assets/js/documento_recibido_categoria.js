(function () {
  document.querySelectorAll('[data-creatable-combobox]').forEach(function (root, rootIndex) {
    var input = root.querySelector('input');
    if (!input) return;

    var options;
    try {
      options = JSON.parse(root.getAttribute('data-options') || '[]');
    } catch (_) {
      options = [];
    }

    var list = document.createElement('ul');
    list.className = 'category-options';
    list.id = 'category-options-' + rootIndex;
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    root.appendChild(list);
    input.setAttribute('aria-controls', list.id);

    var visibleOptions = [];
    var activeIndex = -1;

    function normalized(value) {
      return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    }

    function closeList() {
      list.hidden = true;
      activeIndex = -1;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
    }

    function choose(value) {
      input.value = value;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      closeList();
      input.focus();
    }

    function renderList() {
      var query = normalized(input.value.trim());
      visibleOptions = options.filter(function (option) {
        return normalized(option).includes(query);
      });
      activeIndex = -1;
      list.innerHTML = '';

      visibleOptions.forEach(function (option, index) {
        var item = document.createElement('li');
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'category-option';
        button.id = list.id + '-option-' + index;
        button.setAttribute('role', 'option');
        button.textContent = option;
        button.addEventListener('mousedown', function (event) {
          event.preventDefault();
          choose(option);
        });
        item.appendChild(button);
        list.appendChild(item);
      });

      if (visibleOptions.length === 0) {
        var empty = document.createElement('li');
        empty.className = 'category-empty';
        empty.textContent = input.value.trim()
          ? 'Sin coincidencias. El texto escrito se guardará como un valor nuevo.'
          : 'No hay valores disponibles.';
        list.appendChild(empty);
      }

      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    function setActive(nextIndex) {
      var buttons = list.querySelectorAll('.category-option');
      buttons.forEach(function (button) { button.classList.remove('is-active'); });
      if (!buttons.length) return;
      activeIndex = (nextIndex + buttons.length) % buttons.length;
      buttons[activeIndex].classList.add('is-active');
      buttons[activeIndex].scrollIntoView({ block: 'nearest' });
      input.setAttribute('aria-activedescendant', buttons[activeIndex].id);
    }

    input.addEventListener('focus', renderList);
    input.addEventListener('click', renderList);
    input.addEventListener('input', renderList);
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        closeList();
        return;
      }
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (list.hidden) renderList();
        setActive(activeIndex + 1);
        return;
      }
      if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (list.hidden) renderList();
        setActive(activeIndex - 1);
        return;
      }
      if (event.key === 'Enter' && activeIndex >= 0 && visibleOptions[activeIndex]) {
        event.preventDefault();
        choose(visibleOptions[activeIndex]);
      }
    });

    document.addEventListener('mousedown', function (event) {
      if (!root.contains(event.target)) closeList();
    });
  });
})();
