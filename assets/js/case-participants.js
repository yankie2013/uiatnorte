(() => {
    const panel = document.getElementById('case-participant-list-panel');
    const frame = document.getElementById('case-participant-list-frame');
    const content = document.getElementById('case-participants-content');
    const citations = document.getElementById('case-citations-panel');
    const dialog = document.getElementById('case-participant-dialog');
    const createFrame = document.getElementById('case-participant-create-frame');
    if (!panel || !frame || !dialog) return;
    const stems = ['involucrados_personas', 'involucrados_vehiculos', 'familiar_fallecido', 'propietario_vehiculo', 'abogado', 'policial_interviniente'];
    const page = url => url.pathname.split('/').pop();
    let listUrl;
    document.querySelectorAll('.js-participant-list').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        const url = new URL(link.href);
        url.searchParams.set('case_embed', '1');
        listUrl = url.href;
        frame.src = listUrl;
        panel.hidden = false; content.hidden = true; citations.hidden = true;
        document.getElementById('case-participant-list-title').textContent = link.querySelector('span:last-child')?.firstChild.textContent || 'Participantes';
        document.getElementById('case-new-menu').hidden = true;
        document.querySelector('.js-case-new-trigger')?.setAttribute('aria-expanded', 'false');
    }));
    document.getElementById('case-participant-list-back').addEventListener('click', () => { panel.hidden = true; content.hidden = false; });
    document.querySelector('.js-citations-panel')?.addEventListener('click', () => { panel.hidden = true; });
    document.getElementById('case-citations-back')?.addEventListener('click', () => { panel.hidden = true; });
    document.getElementById('case-participant-dialog-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { createFrame.src = 'about:blank'; });
    let observer;
    frame.addEventListener('load', () => {
        let doc;
        try { doc = frame.contentDocument; } catch { return; }
        if (!doc) return;
        observer?.disconnect();
        const resize = () => { frame.style.height = Math.max(450, doc.body.scrollHeight + 30) + 'px'; };
        observer = new ResizeObserver(resize); observer.observe(doc.body); resize();
        doc.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (!link) return;
            const url = new URL(link.href);
            if (url.origin !== location.origin) return;
            if (stems.some(stem => page(url) === stem + '_nuevo.php')) {
                event.preventDefault();
                url.searchParams.set('case_embed', '1');
                createFrame.src = url.href;
                document.getElementById('case-participant-dialog-title').textContent = link.textContent.trim() || 'Nuevo registro';
                dialog.showModal();
            } else if (stems.some(stem => page(url) === stem + '_listar.php')) {
                event.preventDefault(); url.searchParams.set('case_embed', '1'); frame.src = url.href;
            } else if (page(url) === 'accidente_vista_tabs.php' || page(url) === 'Dato_General_accidente.php') {
                event.preventDefault(); panel.hidden = true; content.hidden = false;
            }
        });
    });
    window.addEventListener('message', event => {
        if (event.origin !== location.origin || event.source !== createFrame.contentWindow || event.data?.type !== 'participant.saved') return;
        dialog.close(); frame.src = listUrl;
        // Al regresar al resumen, carga también sus participantes y contadores actualizados.
        document.getElementById('case-participant-list-back').onclick = () => location.reload();
    });
})();
