/** Column visibility for large tables. The choice is remembered per table in localStorage. */
document.querySelectorAll('[data-columns]').forEach((panel) => {
    const table = document.getElementById(panel.dataset.columns);
    if (!table) return;

    const key = `columns:${panel.dataset.columns}`;
    const hidden = new Set(JSON.parse(localStorage.getItem(key) || '[]'));

    function apply() {
        table.querySelectorAll('[data-col]').forEach((cell) => {
            cell.hidden = hidden.has(cell.dataset.col);
        });
        panel.querySelectorAll('input[type="checkbox"]').forEach((box) => (box.checked = !hidden.has(box.value)));
    }

    panel.addEventListener('change', (event) => {
        if (!event.target.matches('input[type="checkbox"]')) return;
        event.target.checked ? hidden.delete(event.target.value) : hidden.add(event.target.value);
        localStorage.setItem(key, JSON.stringify([...hidden]));
        apply();
    });

    apply();
});
