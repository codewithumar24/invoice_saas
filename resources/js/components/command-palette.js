/** Global command search: Ctrl/Cmd + K. Searches records (server) and quick commands (local). */
import { request } from '../core/http';

const dialog = document.getElementById('command-palette');

if (dialog) {
    const input = dialog.querySelector('input');
    const list = dialog.querySelector('[data-results]');
    const commands = JSON.parse(dialog.querySelector('[data-commands]')?.textContent || '[]');
    let timer = null;
    let active = -1;

    const open = () => {
        if (dialog.open) return;
        dialog.showModal();
        input.value = '';
        render(commands.slice(0, 8).map((c) => ({ ...c, group: dialog.dataset.commandsLabel })));
        input.focus();
    };

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            open();
        }
    });

    document.querySelectorAll('[data-open-palette]').forEach((button) => button.addEventListener('click', open));

    function render(items) {
        list.replaceChildren();
        active = -1;
        let lastGroup = null;

        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'px-4 py-6 text-center text-sm text-muted';
            empty.textContent = dialog.dataset.empty;
            list.appendChild(empty);
            return;
        }

        items.forEach((item) => {
            if (item.group !== lastGroup) {
                const heading = document.createElement('p');
                heading.className = 'px-4 pt-3 pb-1 text-xs font-medium text-muted';
                heading.textContent = item.group;
                list.appendChild(heading);
                lastGroup = item.group;
            }
            const link = document.createElement('a');
            link.href = item.url;
            link.className = 'flex items-baseline justify-between gap-4 px-4 py-2 text-sm hover:bg-raised aria-selected:bg-brand-soft';
            link.setAttribute('role', 'option');
            const title = document.createElement('span');
            title.className = 'font-medium';
            title.textContent = item.title;
            const subtitle = document.createElement('span');
            subtitle.className = 'truncate text-xs text-muted';
            subtitle.textContent = item.subtitle || '';
            link.append(title, subtitle);
            list.appendChild(link);
        });
    }

    async function search(term) {
        const local = commands.filter((c) => c.title.toLowerCase().includes(term.toLowerCase())).map((c) => ({ ...c, group: dialog.dataset.commandsLabel }));

        if (term.length < 2) {
            render(local.length ? local : commands.slice(0, 8).map((c) => ({ ...c, group: dialog.dataset.commandsLabel })));
            return;
        }

        try {
            const { data } = await request(`${dialog.dataset.url}?q=${encodeURIComponent(term)}`);
            const remote = data.flatMap((group) => group.results.map((result) => ({ ...result, group: group.label })));
            render([...local, ...remote]);
        } catch {
            render(local);
        }
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => search(input.value.trim()), 180);
    });

    input.addEventListener('keydown', (event) => {
        const options = [...list.querySelectorAll('[role="option"]')];
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', index === active ? 'true' : 'false'));
            options[active]?.scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter') {
            const target = options[active] || options[0];
            if (target) {
                event.preventDefault();
                window.location.href = target.href;
            }
        }
    });
}
