/** Running time-tracking timer: counts up from the seconds already recorded on the server. */
document.querySelectorAll('[data-timer]').forEach((element) => {
    const base = Number(element.dataset.timer);
    const startedAt = Date.now();
    const running = element.dataset.running === '1';

    const format = (total) => {
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    };

    const tick = () => {
        element.textContent = format(base + (running ? Math.floor((Date.now() - startedAt) / 1000) : 0));
    };

    tick();
    if (running) setInterval(tick, 1000);
});

// Task list follows the chosen project
document.querySelectorAll('[data-project-tasks]').forEach((projectSelect) => {
    const taskSelect = document.querySelector(projectSelect.dataset.projectTasks);
    if (!taskSelect) return;

    const sync = () => {
        [...taskSelect.options].forEach((option) => {
            option.hidden = option.value !== '' && option.dataset.project !== projectSelect.value;
        });
        if (taskSelect.selectedOptions[0]?.hidden) taskSelect.value = '';
    };

    projectSelect.addEventListener('change', sync);
    sync();
});
