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
