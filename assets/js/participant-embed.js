// Conserva el modo integrado en filtros y formularios.
document.querySelectorAll('form').forEach(form => {
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'case_embed'; input.value = '1';
    form.appendChild(input);
});
