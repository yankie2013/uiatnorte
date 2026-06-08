(() => {
  const storageKey = 'uiat-theme';
  const doc = document.documentElement;
  const metaThemeColor = document.querySelector('meta[name="theme-color"]');
  const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  const clearStoredTheme = () => {
    try {
      window.localStorage.removeItem(storageKey);
    } catch (_) {}
  };

  const updateMetaThemeColor = (resolved) => {
    if (!metaThemeColor) {
      return;
    }

    metaThemeColor.setAttribute('content', resolved === 'dark' ? '#0d1526' : '#f4f7fb');
  };

  const applySystemTheme = () => {
    clearStoredTheme();
    const resolved = media && media.matches ? 'dark' : 'light';

    doc.dataset.theme = 'system';
    doc.dataset.themeResolved = resolved;
    updateMetaThemeColor(resolved);

    window.dispatchEvent(new CustomEvent('uiat:themechange', {
      detail: {
        theme: 'system',
        resolved,
      },
    }));
  };

  if (media) {
    if (typeof media.addEventListener === 'function') {
      media.addEventListener('change', applySystemTheme);
    } else if (typeof media.addListener === 'function') {
      media.addListener(applySystemTheme);
    }
  }

  applySystemTheme();
})();

(() => {
  const fieldSelectors = '.field-card, .data-card, .field, .line-card';
  const valueSelectors = '.field-value, .value, .v, .val';
  const controlSelectors = [
    'input:not([type="hidden"]):not([type="button"]):not([type="submit"]):not([type="reset"])',
    'select',
    'textarea',
    '[contenteditable="true"]',
  ].join(',');
  const emptyTexts = new Set(['', '-', '—', 'â€”']);

  const clean = (value) => String(value ?? '').replace(/\s+/g, ' ').trim();
  const isEmptyText = (value) => emptyTexts.has(clean(value));

  const controlHasValue = (control) => {
    if (!control || control.disabled) {
      return false;
    }

    if (control.matches('[contenteditable="true"]')) {
      return !isEmptyText(control.textContent);
    }

    const type = String(control.type || '').toLowerCase();

    if (type === 'checkbox' || type === 'radio') {
      return control.checked;
    }

    if (type === 'file') {
      return !!(control.files && control.files.length);
    }

    if (control instanceof HTMLSelectElement && control.multiple) {
      return Array.from(control.selectedOptions).some((option) => !isEmptyText(option.value));
    }

    return !isEmptyText(control.value);
  };

  const fieldHasValue = (field) => {
    const controls = Array.from(field.querySelectorAll(controlSelectors));
    if (controls.length > 0) {
      return controls.some(controlHasValue);
    }

    const valueNode = field.querySelector(valueSelectors);
    if (valueNode) {
      return !isEmptyText(valueNode.textContent);
    }

    return !isEmptyText(field.textContent);
  };

  const syncField = (field) => {
    if (!field || field.closest('#app-sidebar')) {
      return;
    }

    field.classList.toggle('is-empty-field', !fieldHasValue(field));
  };

  const syncControl = (control) => {
    if (!control || control.closest('#app-sidebar')) {
      return;
    }

    const field = control.closest(fieldSelectors);
    if (field) {
      syncField(field);
      return;
    }

    const shouldTrack = control.matches('[required], [aria-required="true"]');
    if (shouldTrack) {
      control.classList.toggle('is-empty-field', !controlHasValue(control));
    } else {
      control.classList.remove('is-empty-field');
    }
  };

  const syncAll = (root = document) => {
    root.querySelectorAll(fieldSelectors).forEach(syncField);
    root.querySelectorAll(controlSelectors).forEach(syncControl);
  };

  let pendingControls = new Set();
  let controlFrame = 0;

  const scheduleControlSync = (control) => {
    if (!(control instanceof Element) || !control.matches(controlSelectors)) {
      return;
    }

    pendingControls.add(control);
    if (controlFrame) {
      return;
    }

    controlFrame = window.requestAnimationFrame(() => {
      pendingControls.forEach(syncControl);
      pendingControls = new Set();
      controlFrame = 0;
    });
  };

  const scheduleSync = (root = document) => {
    window.requestAnimationFrame(() => syncAll(root));
  };

  const patchControlProperty = (prototype, property) => {
    const descriptor = Object.getOwnPropertyDescriptor(prototype, property);
    if (!descriptor || typeof descriptor.set !== 'function' || typeof descriptor.get !== 'function') {
      return;
    }

    Object.defineProperty(prototype, property, {
      configurable: true,
      enumerable: descriptor.enumerable,
      get() {
        return descriptor.get.call(this);
      },
      set(value) {
        descriptor.set.call(this, value);
        scheduleControlSync(this);
      },
    });
  };

  patchControlProperty(HTMLInputElement.prototype, 'value');
  patchControlProperty(HTMLInputElement.prototype, 'checked');
  patchControlProperty(HTMLTextAreaElement.prototype, 'value');
  patchControlProperty(HTMLSelectElement.prototype, 'value');

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => syncAll(), { once: true });
  } else {
    syncAll();
  }

  ['input', 'change', 'keyup', 'blur'].forEach((eventName) => {
    document.addEventListener(eventName, (event) => {
      if (!(event.target instanceof Element) || !event.target.matches(controlSelectors)) {
        return;
      }

      syncControl(event.target);
    }, true);
  });

  document.addEventListener('click', () => scheduleSync(), true);
  document.addEventListener('submit', () => syncAll(), true);

  new MutationObserver((items) => {
    items.forEach((item) => {
      if (item.type === 'characterData' && item.target.parentElement) {
        const field = item.target.parentElement.closest(fieldSelectors);
        if (field) syncField(field);
      }

      item.addedNodes.forEach((node) => {
        if (!(node instanceof Element)) return;
        if (node.matches(fieldSelectors) || node.matches(controlSelectors)) {
          syncAll(node.parentElement || document);
          return;
        }
        if (node.querySelector(fieldSelectors) || node.querySelector(controlSelectors)) {
          syncAll(node);
        }
      });
    });
  }).observe(document.documentElement, {
    childList: true,
    subtree: true,
    characterData: true,
  });

  window.uiatSyncEmptyFields = syncAll;
})();
