(() => {
    const trigger = document.querySelector('.js-citations-panel');
    const panel = document.getElementById('case-citations-panel');
    const content = document.getElementById('case-participants-content');
    const frame = document.getElementById('case-citations-frame');
    const dialog = document.getElementById('case-citation-dialog');
    const createFrame = document.getElementById('case-citation-create-frame');
    if (!trigger || !panel || !content || !frame || !dialog) return;
    trigger.setAttribute('aria-expanded', 'false');
    trigger.addEventListener('click', event => {
        event.preventDefault();
        panel.hidden = false;
        content.hidden = true;
        trigger.setAttribute('aria-expanded', 'true');
        if (!frame.getAttribute('src')) frame.src = frame.dataset.src;
    });
    document.getElementById('case-citations-back').addEventListener('click', () => {
        panel.hidden = true;
        content.hidden = false;
        trigger.setAttribute('aria-expanded', 'false');
    });
    document.getElementById('case-citation-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { createFrame.src = 'about:blank'; });
    let observer;
    frame.addEventListener('load', () => {
        let doc;
        try { doc = frame.contentDocument; } catch { return; }
        if (!doc) return;
        observer?.disconnect();
        const resize = () => { frame.style.height = Math.max(400, doc.documentElement.scrollHeight) + 'px'; };
        observer = new ResizeObserver(resize);
        observer.observe(doc.body);
        resize();
        doc.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (!link) return;
            const url = new URL(link.href, location.href);
            if (url.origin !== location.origin || !url.pathname.endsWith('/citacion_nuevo.php')) return;
            event.preventDefault();
            url.searchParams.set('embed', '1');
            url.searchParams.set('modal', '1');
            createFrame.src = url.href;
            dialog.showModal();
        });
    });
    window.addEventListener('message', event => {
        if (event.origin !== location.origin || event.source !== createFrame.contentWindow || event.data?.type !== 'citacion.saved') return;
        dialog.close();
        frame.src = frame.dataset.src;
    });
})();
