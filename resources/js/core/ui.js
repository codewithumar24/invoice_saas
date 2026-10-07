/** Dialogs, drawers, menus and copy buttons. Uses the native <dialog> element, which gives focus trapping and Esc for free. */

// Open a dialog: <button data-open="dialog-id">
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open]');
    if (opener) {
        const dialog = document.getElementById(opener.dataset.open);
        if (dialog?.showModal) {
            // Optional: fill form fields in the dialog from data-fill='{"name":"value"}'
            if (opener.dataset.fill) {
                Object.entries(JSON.parse(opener.dataset.fill)).forEach(([name, value]) => {
                    const field = dialog.querySelector(`[name="${name}"]`);
                    if (field) field.value = value;
                    const text = dialog.querySelector(`[data-fill-text="${name}"]`);
                    if (text) text.textContent = value;
                });
            }
            if (opener.dataset.action) dialog.querySelector('form')?.setAttribute('action', opener.dataset.action);
            dialog.showModal();
        }
        return;
    }

    const closer = event.target.closest('[data-close]');
    if (closer) {
        closer.closest('dialog')?.close();
        return;
    }

    // Click on the backdrop closes the dialog
    if (event.target instanceof HTMLDialogElement && event.target.open) {
        const box = event.target.getBoundingClientRect();
        const inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;
        if (!inside) event.target.close();
    }
});

// Only one <details class="menu"> open at a time; close on outside click and Esc
document.addEventListener('click', (event) => {
    document.querySelectorAll('details.menu[open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('details.menu[open]').forEach((menu) => {
            menu.removeAttribute('open');
            menu.querySelector('summary')?.focus();
        });
    }
});

// Copy to clipboard: <button data-copy="text">
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;
    try {
        await navigator.clipboard.writeText(button.dataset.copy);
        const original = button.textContent;
        button.textContent = button.dataset.copied || 'Copied';
        setTimeout(() => (button.textContent = original), 1500);
    } catch {
        window.prompt('Copy:', button.dataset.copy);
    }
});

// Print buttons
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-print]')) window.print();
});

/** Promise-based confirm dialog used for every dangerous action. */
export function openConfirm(message, confirmLabel) {
    const dialog = document.getElementById('confirm-dialog');
    if (!dialog?.showModal) return Promise.resolve(window.confirm(message));

    dialog.querySelector('[data-confirm-message]').textContent = message;
    const accept = dialog.querySelector('[data-confirm-accept]');
    if (confirmLabel) accept.textContent = confirmLabel;
    else accept.textContent = accept.dataset.default;

    return new Promise((resolve) => {
        const onClose = () => {
            dialog.removeEventListener('close', onClose);
            resolve(dialog.returnValue === 'yes');
        };
        dialog.addEventListener('close', onClose);
        dialog.returnValue = '';
        dialog.showModal();
    });
}

// Open a dialog automatically (e.g. after a validation error inside it)
document.querySelectorAll('dialog[data-auto-open]').forEach((dialog) => dialog.showModal?.());
