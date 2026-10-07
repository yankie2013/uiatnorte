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
    if (['lc.saved', 'docveh:created', 'docveh:updated'].includes(event.data?.type)) {
      close();
      window.location.reload();
    }
  });
})();
