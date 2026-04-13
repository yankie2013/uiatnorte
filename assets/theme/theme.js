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
