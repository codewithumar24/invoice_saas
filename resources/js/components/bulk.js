/** Bulk actions: tick rows, pick an action, submit one form with the selected ids. */
document.querySelectorAll('[data-bulk]').forEach((scope) => {
    const boxes = () => [...scope.querySelectorAll('[data-bulk-item]')];
    const all = scope.querySelector('[data-bulk-all]');
    const bar = scope.querySelector('[data-bulk-bar]');
    const count = scope.querySelector('[data-bulk-count]');

    function sync() {
        const selected = boxes().filter((box) => box.checked);
        if (bar) bar.hidden = selected.length === 0;
        if (count) count.textContent = selected.length;
        if (all) {
            all.checked = selected.length > 0 && selected.length === boxes().length;
            all.indeterminate = selected.length > 0 && selected.length < boxes().length;
        }
    }

    all?.addEventListener('change', () => {
        boxes().forEach((box) => (box.checked = all.checked));
        sync();
    });

    scope.addEventListener('change', (event) => {
        if (event.target.matches('[data-bulk-item]')) sync();
    });

    sync();
});
