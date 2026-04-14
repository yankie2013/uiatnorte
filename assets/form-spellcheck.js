(() => {
  const TEXT_SELECTOR = [
    'textarea',
    'input:not([type])',
    'input[type="text"]',
    '[contenteditable]'
  ].join(',');

  const SKIP_FIELD_PATTERN = /(^|[\s_.:-])(buscar|busqueda|search|query|dni|ruc|placa|telefono|tel|celular|correo|email|mail|gps|coordenad|lat|lng|lon|longitud|fecha|hora|anio|ano|codigo|cod|numero|nro|num|sidpol|id|uuid|token|password|usuario|user|login)([\s_.:-]|$)/i;
  const SKIP_SUBSTRING_PATTERN = /(gps|coordenad|latitud|longitud|placa|dni|ruc|telefono|celular|correo|email|fecha|hora|sidpol)/i;
  const SKIP_EXACT_NAMES = new Set(['q']);
  const SKIP_INPUT_MODES = new Set(['decimal', 'numeric', 'tel', 'email', 'url']);
  const ACCENT_FIXES = {
    arbol: '\u00e1rbol',
    busqueda: 'b\u00fasqueda',
    camara: 'c\u00e1mara',
    camaras: 'c\u00e1maras',
    caracteristica: 'caracter\u00edstica',
    caracteristicas: 'caracter\u00edsticas',
    codigo: 'c\u00f3digo',
    comunicaccion: 'comunicaci\u00f3n',
    comunicacion: 'comunicaci\u00f3n',
    configuracion: 'configuraci\u00f3n',
    descripcion: 'descripci\u00f3n',
    direccion: 'direcci\u00f3n',
    documentacion: 'documentaci\u00f3n',
    educacion: 'educaci\u00f3n',
    fisica: 'f\u00edsica',
    identificacion: 'identificaci\u00f3n',
    iluminacion: 'iluminaci\u00f3n',
    informacion: 'informaci\u00f3n',
    inspeccion: 'inspecci\u00f3n',
    juridica: 'jur\u00eddica',
    localizacion: 'localizaci\u00f3n',
    medica: 'm\u00e9dica',
    medico: 'm\u00e9dico',
    movil: 'm\u00f3vil',
    numero: 'n\u00famero',
    observacion: 'observaci\u00f3n',
    observaciones: 'observaciones',
    ocurrencia: 'ocurrencia',
    pagina: 'p\u00e1gina',
    policia: 'polic\u00eda',
    senal: 'se\u00f1al',
    senales: 'se\u00f1ales',
    senalizacion: 'se\u00f1alizaci\u00f3n',
    senial: 'se\u00f1al',
    seniales: 'se\u00f1ales',
    senializacion: 'se\u00f1alizaci\u00f3n',
    tecnico: 't\u00e9cnico',
    tecnicos: 't\u00e9cnicos',
    transito: 'tr\u00e1nsito',
    ubicacion: 'ubicaci\u00f3n',
    vehiculo: 'veh\u00edculo',
    vehiculos: 'veh\u00edculos',
    via: 'v\u00eda',
    vias: 'v\u00edas',
    visibilizacion: 'visibilizaci\u00f3n'
  };
  const ACCENT_FIX_KEYS = Object.keys(ACCENT_FIXES).sort((a, b) => b.length - a.length);
  const ACCENT_FIX_PATTERN = new RegExp('(^|[^A-Za-z\\u00c0-\\u017f])(' + ACCENT_FIX_KEYS.join('|') + ')(?=$|[^A-Za-z\\u00c0-\\u017f])', 'gi');
  const suggestionState = new WeakMap();
  const WORD_CHAR_PATTERN = /[A-Za-z\u00c0-\u017f]/;
  const MIRROR_STYLE_PROPS = [
    'boxSizing',
    'width',
    'height',
    'borderTopWidth',
    'borderRightWidth',
    'borderBottomWidth',
    'borderLeftWidth',
    'paddingTop',
    'paddingRight',
    'paddingBottom',
    'paddingLeft',
    'fontFamily',
    'fontSize',
    'fontStyle',
    'fontWeight',
    'lineHeight',
    'letterSpacing',
    'textTransform',
    'textAlign',
    'wordSpacing',
    'tabSize'
  ];

  function textFromAttributes(el) {
    return [
      el.getAttribute('name'),
      el.getAttribute('id'),
      el.getAttribute('autocomplete'),
      el.getAttribute('inputmode'),
      el.getAttribute('aria-label'),
      el.getAttribute('title'),
      el.getAttribute('placeholder'),
      el.className && typeof el.className === 'string' ? el.className : ''
    ].filter(Boolean).join(' ').toLowerCase();
  }

  function hasExplicitOptOut(el) {
    return Boolean(el.closest('[spellcheck="false"], [data-spellcheck="off"], [data-no-spellcheck]'));
  }

  function isTextEditable(el) {
    if (!(el instanceof HTMLElement) || el.hidden || el.matches('[type="hidden"]')) {
      return false;
    }

    if (el.matches('[contenteditable]')) {
      return el.getAttribute('contenteditable') !== 'false';
    }

    return !el.disabled && !el.readOnly;
  }

  function shouldSkip(el) {
    if (hasExplicitOptOut(el)) {
      return true;
    }

    const name = (el.getAttribute('name') || '').toLowerCase();
    if (SKIP_EXACT_NAMES.has(name)) {
      return true;
    }

    const type = (el.getAttribute('type') || '').toLowerCase();
    if (type && type !== 'text') {
      return true;
    }

    const inputMode = (el.getAttribute('inputmode') || '').toLowerCase();
    if (SKIP_INPUT_MODES.has(inputMode)) {
      return true;
    }

    const attributes = textFromAttributes(el);
    return SKIP_FIELD_PATTERN.test(attributes) || SKIP_SUBSTRING_PATTERN.test(attributes);
  }

  function applySpellcheck(el) {
    if (!isTextEditable(el)) {
      return;
    }

    if (shouldSkip(el)) {
      el.setAttribute('spellcheck', 'false');
      return;
    }

    el.setAttribute('spellcheck', 'true');
    el.setAttribute('autocorrect', 'on');

    if (!el.getAttribute('lang')) {
      el.setAttribute('lang', document.documentElement.lang || 'es-PE');
    }

    attachSuggestions(el);
    updateSuggestions(el);
  }

  function preserveCase(original, replacement) {
    if (original === original.toUpperCase()) {
      return replacement.toUpperCase();
    }

    if (original.charAt(0) === original.charAt(0).toUpperCase()) {
      return replacement.charAt(0).toUpperCase() + replacement.slice(1);
    }

    return replacement;
  }

  function replacementForWord(word) {
    const replacement = ACCENT_FIXES[word.toLowerCase()];
    if (!replacement || replacement === word) {
      return null;
    }

    return preserveCase(word, replacement);
  }

  function isWordChar(char) {
    return WORD_CHAR_PATTERN.test(char || '');
  }

  function getWordRangeForCaret(value, caret) {
    if (!value) {
      return null;
    }

    let index = Math.max(0, Math.min(caret, value.length));
    if (!isWordChar(value.charAt(index)) && isWordChar(value.charAt(index - 1))) {
      index -= 1;
    } else if (!isWordChar(value.charAt(index)) && !isWordChar(value.charAt(index - 1))) {
      let previous = index - 1;
      while (previous >= 0 && !isWordChar(value.charAt(previous))) {
        previous -= 1;
      }
      if (previous < 0) {
        return null;
      }
      index = previous;
    }

    let start = index;
    let end = index;
    while (start > 0 && isWordChar(value.charAt(start - 1))) {
      start -= 1;
    }
    while (end < value.length && isWordChar(value.charAt(end))) {
      end += 1;
    }

    if (start === end) {
      return null;
    }

    return { start, end, word: value.slice(start, end) };
  }

  function getFirstSuggestionRange(value) {
    let result = null;

    value.replace(ACCENT_FIX_PATTERN, (match, prefix, word, offset) => {
      if (result || !replacementForWord(word)) {
        return match;
      }

      const start = offset + prefix.length;
      const end = start + word.length;
      result = { start, end, word };
      return match;
    });

    return result;
  }

  function getSuggestionRange(el) {
    const value = el.value || '';
    const caret = typeof el.selectionEnd === 'number' ? el.selectionEnd : value.length;
    const current = getWordRangeForCaret(value, caret);

    if (current && replacementForWord(current.word)) {
      return current;
    }

    return getFirstSuggestionRange(value);
  }

  function ensureSuggestionStyles() {
    if (document.getElementById('uiat-spellcheck-styles')) {
      return;
    }

    const style = document.createElement('style');
    style.id = 'uiat-spellcheck-styles';
    style.textContent = [
      '.uiat-spell-suggestions{position:absolute;z-index:2147482600;display:none;align-items:center;gap:6px;max-width:min(320px,calc(100vw - 24px));padding:7px 8px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#475569;box-shadow:0 10px 26px rgba(15,23,42,.18);font:12px/1.4 system-ui,-apple-system,"Segoe UI",sans-serif;}',
      '.uiat-spell-suggestions.is-visible{display:flex;}',
      '.uiat-spell-suggestions::before{content:"";position:absolute;left:14px;top:-6px;width:10px;height:10px;border-left:1px solid #cbd5e1;border-top:1px solid #cbd5e1;background:#fff;transform:rotate(45deg);}',
      '.uiat-spell-suggestions__label{font-weight:700;color:#64748b;white-space:nowrap;}',
      '.uiat-spell-suggestions button{position:relative;appearance:none;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#1e293b;cursor:pointer;font:700 12px/1 system-ui,-apple-system,"Segoe UI",sans-serif;padding:6px 8px;white-space:nowrap;}',
      '.uiat-spell-suggestions button:hover{border-color:#2563eb;color:#1d4ed8;}'
    ].join('');
    document.head.appendChild(style);
  }

  function attachSuggestions(el) {
    if (suggestionState.has(el)) {
      return;
    }

    ensureSuggestionStyles();

    const box = document.createElement('div');
    box.className = 'uiat-spell-suggestions';
    box.setAttribute('aria-live', 'polite');
    document.body.appendChild(box);
    suggestionState.set(el, { box, range: null });

    const update = () => updateSuggestions(el);
    el.addEventListener('input', update);
    el.addEventListener('keyup', update);
    el.addEventListener('click', update);
    el.addEventListener('focus', update);
    el.addEventListener('scroll', update);
    el.addEventListener('blur', () => {
      window.setTimeout(() => hideSuggestions(el), 140);
    });

    window.addEventListener('scroll', update, true);
    window.addEventListener('resize', update);
  }

  function updateSuggestions(el) {
    const state = suggestionState.get(el);
    if (!state) {
      return;
    }

    const box = state.box;
    const range = getSuggestionRange(el);
    state.range = range;

    if (!range) {
      hideSuggestions(el);
      return;
    }

    const replacement = replacementForWord(range.word);
    if (!replacement) {
      hideSuggestions(el);
      return;
    }

    box.innerHTML = '';

    const label = document.createElement('span');
    label.className = 'uiat-spell-suggestions__label';
    label.textContent = 'Sugerencia:';
    box.appendChild(label);

    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = range.word + ' -> ' + replacement;
    button.addEventListener('mousedown', (event) => event.preventDefault());
    button.addEventListener('click', () => {
      applySuggestion(el);
    });
    box.appendChild(button);

    positionSuggestionBox(el, box, range);
    box.classList.add('is-visible');
  }

  function hideSuggestions(el) {
    const state = suggestionState.get(el);
    if (!state) {
      return;
    }

    state.box.classList.remove('is-visible');
  }

  function applySuggestion(el) {
    const state = suggestionState.get(el);
    if (!state || !state.range) {
      return;
    }

    const { start, end, word } = state.range;
    const replacement = replacementForWord(word);
    if (!replacement) {
      hideSuggestions(el);
      return;
    }

    const value = el.value || '';
    el.value = value.slice(0, start) + replacement + value.slice(end);

    const cursor = start + replacement.length;
    if (typeof el.setSelectionRange === 'function') {
      el.setSelectionRange(cursor, cursor);
    }

    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.focus();
    updateSuggestions(el);
  }

  function getTextareaWordPosition(el, range) {
    const style = window.getComputedStyle(el);
    const mirror = document.createElement('div');
    const marker = document.createElement('span');
    const rect = el.getBoundingClientRect();

    MIRROR_STYLE_PROPS.forEach((prop) => {
      mirror.style[prop] = style[prop];
    });

    mirror.style.position = 'absolute';
    mirror.style.left = '-9999px';
    mirror.style.top = '0';
    mirror.style.visibility = 'hidden';
    mirror.style.overflow = 'hidden';
    mirror.style.whiteSpace = el.tagName === 'TEXTAREA' ? 'pre-wrap' : 'pre';
    mirror.style.wordWrap = 'break-word';
    mirror.textContent = (el.value || '').slice(0, range.start);
    marker.textContent = (el.value || '').slice(range.start, range.end) || '.';
    mirror.appendChild(marker);
    document.body.appendChild(mirror);

    const mirrorRect = mirror.getBoundingClientRect();
    const markerRect = marker.getBoundingClientRect();
    const left = rect.left + window.scrollX + markerRect.left - mirrorRect.left - el.scrollLeft;
    const top = rect.top + window.scrollY + markerRect.bottom - mirrorRect.top - el.scrollTop + 6;

    mirror.remove();

    return { left, top };
  }

  function positionSuggestionBox(el, box, range) {
    const rect = el.getBoundingClientRect();
    const position = getTextareaWordPosition(el, range);
    const maxLeft = window.scrollX + window.innerWidth - 24;
    const minLeft = window.scrollX + 8;
    const left = Math.min(Math.max(position.left, minLeft), maxLeft);
    const minTop = rect.top + window.scrollY;
    const maxTop = rect.bottom + window.scrollY + 8;
    const top = Math.min(Math.max(position.top, minTop), maxTop);

    box.style.left = left + 'px';
    box.style.top = top + 'px';
  }

  function scan(root) {
    if (!(root instanceof Element || root instanceof Document)) {
      return;
    }

    if (root instanceof Element && root.matches(TEXT_SELECTOR)) {
      applySpellcheck(root);
    }

    root.querySelectorAll(TEXT_SELECTOR).forEach(applySpellcheck);
  }

  function init() {
    if (!document.documentElement.lang) {
      document.documentElement.lang = 'es-PE';
    }

    scan(document);

    const observer = new MutationObserver((records) => {
      records.forEach((record) => {
        record.addedNodes.forEach(scan);
      });
    });

    observer.observe(document.documentElement, {
      childList: true,
      subtree: true
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
