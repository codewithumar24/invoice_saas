/** Toast messages. Flash messages from the server are rendered into #toasts and removed after a few seconds. */
const region = () => document.getElementById('toasts');

export function toast(message, tone = 'ok') {
    const host = region();
    if (!host) return;
    const item = document.createElement('div');
    item.className = 'toast';
    item.setAttribute('role', tone === 'danger' ? 'alert' : 'status');
    const dot = document.createElement('span');
    dot.className = `mt-1.5 h-2 w-2 shrink-0 rounded-full ${tone === 'danger' ? 'bg-danger' : 'bg-ok'}`;
    const text = document.createElement('span');
    text.textContent = message;
    item.append(dot, text);
    host.appendChild(item);
    schedule(item);
}

function schedule(item) {
    const delay = item.getAttribute('role') === 'alert' ? 9000 : 5000;
    setTimeout(() => item.remove(), delay);
}

document.querySelectorAll('#toasts .toast').forEach(schedule);

document.addEventListener('click', (event) => {
    event.target.closest('[data-dismiss-toast]')?.closest('.toast')?.remove();
});
