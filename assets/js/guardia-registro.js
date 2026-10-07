(() => {
  const frame = document.getElementById('guardia-form');
  if (!frame) return;
  let observer;
  frame.addEventListener('load', () => {
    observer?.disconnect();
    const child = frame.contentWindow;
    const url = new URL(child.location.href);
    if (url.pathname.endsWith('/guardia_registro.php')) {
      window.location.assign(url.href);
      return;
    }
    const content = child.document.querySelector('.wrap') || child.document.body;
    const resize = () => {
      const height = Math.ceil(content.getBoundingClientRect().bottom + child.scrollY + 12);
      frame.style.height = `${Math.max(180, height)}px`;
    };
    resize();
    observer = new ResizeObserver(resize);
    observer.observe(content);
  });
})();

(() => {
  const dialog = document.getElementById('guardia-document-dialog');
  if (!dialog) return;
  const frame = document.getElementById('guardia-document-frame');
  const close = () => { dialog.close(); frame.removeAttribute('src'); };
  document.getElementById('guardia-document-close').addEventListener('click', close);
  document.querySelectorAll('.guardia-document-open').forEach(link => {
    link.addEventListener('click', event => {
      event.preventDefault();
      document.getElementById('guardia-document-title').textContent = link.dataset.title;
      frame.title = link.dataset.title;
      frame.src = link.href;
      dialog.showModal();
    });
  });
  dialog.addEventListener('close', () => frame.removeAttribute('src'));
  window.addEventListener('message', event => {
    if (event.origin !== window.location.origin || event.source !== frame.contentWindow) return;
    if (['lc.saved', 'docveh:created', 'docveh:updated', 'fallecimiento.saved'].includes(event.data?.type)) {
      close();
      window.location.reload();
    }
  });
})();

(() => {
 const button = document.getElementById('guardia-whatsapp-copy');
 const text = document.getElementById('guardia-whatsapp-text');
 const status = document.getElementById('guardia-whatsapp-status');
 if (!button || !text || !status) return;
 button.addEventListener('click', async () => {
  button.disabled = true;
  let copied = false;
  try {
   if (navigator.clipboard && window.isSecureContext) {
    await navigator.clipboard.writeText(text.value);
    copied = true;
   }
  } catch (_) { /* Use the selection fallback when clipboard permission is denied. */ }
  if (!copied) {
   text.hidden = false;
   text.focus();
   text.select();
   try { copied = document.execCommand('copy'); } catch (_) { copied = false; }
  }
  text.hidden = copied;
  status.textContent = copied ? '✓ Copiado. Ya puedes pegarlo en WhatsApp.' : 'Selecciona el resumen y cópialo con Ctrl+C o ⌘C para pegarlo en WhatsApp.';
  button.disabled = false;
  if (copied) button.focus();
 });
})();
