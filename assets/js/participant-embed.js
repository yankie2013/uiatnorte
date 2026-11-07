// Conserva el modo integrado en filtros y formularios.
document.querySelectorAll('[data-participant-cancel]').forEach(link => {
    link.addEventListener('click', event => {
        if (window.parent === window) return;
        event.preventDefault();
        parent.postMessage({type: 'participant.cancel'}, location.origin);
    });
});
document.querySelectorAll('form').forEach(form => {
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'case_embed'; input.value = '1';
    form.appendChild(input);
});
