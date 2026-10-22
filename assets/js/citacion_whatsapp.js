// Horarios de Lima para todos los mensajes de WhatsApp.
(() => {
    if (window.refreshWhatsAppGreeting) return;
    window.refreshWhatsAppGreeting = (message) => {
        const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'America/Lima', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date());
        const hour = Number(parts.find(part => part.type === 'hour').value);
        const minute = Number(parts.find(part => part.type === 'minute').value);
        const minutes = hour * 60 + minute;
        const greeting = minutes >= 60 && minutes < 720 ? 'Buenos días' : minutes >= 720 && minutes <= 1080 ? 'Buenas tardes' : 'Buenas noches';
        return message.replace(/^(Hola|Buenos días|Buenas tardes|Buenas noches)(?=,)/u, greeting);
    };
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link) return;
        const url = new URL(link.href, window.location.href);
        if (!['wa.me', 'api.whatsapp.com', 'web.whatsapp.com'].includes(url.hostname)) return;
        const message = url.searchParams.get('text');
        if (!message) return;
        url.searchParams.set('text', window.refreshWhatsAppGreeting(message));
        link.href = url.href;
    }, true);
})();
