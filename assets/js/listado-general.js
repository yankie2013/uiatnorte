document.querySelectorAll('.js-responsible-form').forEach(form => {
    const select = form.querySelector('select');
    const button = form.querySelector('button');
    const status = form.querySelector('.responsible-status');
    let savedValue = select.value;
    let saving = false;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        if (select.value === savedValue) {
            status.textContent = 'Este responsable ya está asignado.';
            return;
        }
        const body = new FormData(form);
        const selectedValue = select.value;
        saving = true;
        select.disabled = button.disabled = true;
        status.textContent = 'Guardando…';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json' }
            });
            if (!response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('No se pudo confirmar el guardado. Comprueba tu sesión y recarga antes de reintentar.');
            }
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo guardar.');
            savedValue = selectedValue;
            status.textContent = result.message;
        } catch (error) {
            status.textContent = error.message || 'No se pudo confirmar el guardado. Recarga antes de reintentar.';
        } finally {
            saving = false;
            select.disabled = button.disabled = false;
        }
    });
});
