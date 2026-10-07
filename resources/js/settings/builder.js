/** Repeating rows for the automation builder (conditions and actions) and other list-style forms. */
document.querySelectorAll('[data-builder]').forEach((builder) => {
    const rows = builder.querySelector('[data-builder-rows]');
    const template = builder.querySelector('template');
    const prefix = builder.dataset.builder;
    let index = rows.querySelectorAll('[data-builder-row]').length;

    function wire(row, i) {
        row.querySelectorAll('[data-name]').forEach((field) => {
            field.name = `${prefix}[${i}][${field.dataset.name}]`;
        });
    }

    rows.querySelectorAll('[data-builder-row]').forEach((row, i) => wire(row, i));

    // Fields that only apply to one action type: data-only="webhook"
    function syncRow(row) {
        const type = row.querySelector('[data-name="type"]')?.value;
        row.querySelectorAll('[data-only]').forEach((field) => {
            const visible = field.dataset.only.split('|').includes(type);
            field.hidden = !visible;
            field.querySelectorAll('input, textarea, select').forEach((control) => (control.disabled = !visible));
        });
    }

    rows.querySelectorAll('[data-builder-row]').forEach(syncRow);

    builder.addEventListener('click', (event) => {
        if (event.target.closest('[data-builder-add]')) {
            const fragment = template.content.cloneNode(true);
            const row = fragment.querySelector('[data-builder-row]');
            wire(row, index);
            index += 1;
            rows.appendChild(fragment);
            syncRow(rows.lastElementChild);
            rows.lastElementChild.querySelector('select, input, textarea')?.focus();
        }

        const remove = event.target.closest('[data-builder-remove]');
        if (remove) remove.closest('[data-builder-row]').remove();
    });

    builder.addEventListener('change', (event) => {
        if (event.target.matches('[data-name="type"]')) syncRow(event.target.closest('[data-builder-row]'));
    });
});
