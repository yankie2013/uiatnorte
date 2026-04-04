(() => {
  const storageKey = 'uiat-theme';
  const doc = document.documentElement;
  const buttons = Array.from(document.querySelectorAll('[data-theme-choice]'));
  const metaThemeColor = document.querySelector('meta[name="theme-color"]');
  const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  const readTheme = () => {
    const current = doc.dataset.theme;
    if (current === 'light' || current === 'dark' || current === 'system') {
      return current;
    }

    try {
      const stored = window.localStorage.getItem(storageKey);
      if (stored === 'light' || stored === 'dark' || stored === 'system') {
        return stored;
      }
    } catch (_) {}

    return 'system';
  };

  const resolveTheme = (theme) => {
    if (theme === 'system') {
      return media && media.matches ? 'dark' : 'light';
    }

    return theme === 'dark' ? 'dark' : 'light';
  };

  const updateButtons = (theme) => {
    buttons.forEach((button) => {
      const active = button.dataset.themeChoice === theme;
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  };

  const updateMetaThemeColor = (resolved) => {
    if (!metaThemeColor) {
      return;
    }

    metaThemeColor.setAttribute('content', resolved === 'dark' ? '#0d1526' : '#f4f7fb');
  };

  const applyTheme = (theme, persist) => {
    const safeTheme = theme === 'light' || theme === 'dark' || theme === 'system' ? theme : 'system';
    const resolved = resolveTheme(safeTheme);

    doc.dataset.theme = safeTheme;
    doc.dataset.themeResolved = resolved;
    updateButtons(safeTheme);
    updateMetaThemeColor(resolved);

    if (persist) {
      try {
        window.localStorage.setItem(storageKey, safeTheme);
      } catch (_) {}
    }

    window.dispatchEvent(new CustomEvent('uiat:themechange', {
      detail: {
        theme: safeTheme,
        resolved,
      },
    }));
  };

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      applyTheme(button.dataset.themeChoice || 'system', true);
    });
  });

  if (media) {
    const syncSystemTheme = () => {
      if (readTheme() === 'system') {
        applyTheme('system', false);
      }
    };

    if (typeof media.addEventListener === 'function') {
      media.addEventListener('change', syncSystemTheme);
    } else if (typeof media.addListener === 'function') {
      media.addListener(syncSystemTheme);
    }
  }

  applyTheme(readTheme(), false);
})();
