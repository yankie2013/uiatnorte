(() => {
  const start = document.querySelector('input[name="vigente_soat"]');
  const end = document.querySelector('input[name="vencimiento_soat"]');
  if (!start || !end) return;
  const updateExpiry = () => {
    if (!start.value || !start.validity.valid) return;
    const [year, month, day] = start.value.split('-').map(Number);
    const nextYear = year + 1;
    // El 29 de febrero vence el 28 cuando el siguiente año no es bisiesto.
    const lastDay = new Date(Date.UTC(nextYear, month, 0)).getUTCDate();
    end.value = `${nextYear}-${String(month).padStart(2, '0')}-${String(Math.min(day, lastDay)).padStart(2, '0')}`;
    end.dispatchEvent(new Event('input', { bubbles: true }));
    end.dispatchEvent(new Event('change', { bubbles: true }));
  };
  start.addEventListener('change', updateExpiry);
})();
