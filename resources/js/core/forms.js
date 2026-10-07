/** Form UX: loading state on submit, confirm dialogs for dangerous actions, unsaved-change friendly behaviour. */
import { openConfirm } from './ui';

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    // Dangerous actions (delete, cancel, refund ...) ask first
    const message = form.dataset.confirm || event.submitter?.dataset.confirm;
    if (message && !form.dataset.confirmed) {
        event.preventDefault();
        const submitter = event.submitter;
        openConfirm(message, form.dataset.confirmLabel || submitter?.dataset.confirmLabel).then((yes) => {
            if (!yes) return;
            form.dataset.confirmed = '1';
            // Keep the clicked button's name/value (e.g. action=send)
            if (submitter?.name) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitter.name;
                hidden.value = submitter.value;
                form.appendChild(hidden);
            }
            form.submit();
        });
        return;
    }

    if (form.dataset.noLoading !== undefined || form.target === '_blank') return;

    // Loading state: the clicked button spins and cannot be pressed twice
    const button = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
    if (button) {
        // Defer so the button's own name/value is still submitted
        setTimeout(() => {
            button.dataset.loading = '';
            form.querySelectorAll('button').forEach((b) => (b.disabled = true));
        }, 0);
    }
});

// Pages restored from the back/forward cache must not keep spinning buttons
window.addEventListener('pageshow', () => {
    document.querySelectorAll('button[data-loading]').forEach((b) => delete b.dataset.loading);
    document.querySelectorAll('form button:disabled').forEach((b) => (b.disabled = false));
});

// Filters: submit the form as soon as a select changes
document.addEventListener('change', (event) => {
    if (event.target.matches('[data-autosubmit]')) event.target.form?.requestSubmit();
});

// Light client-side validation feedback; the server always validates again
document.addEventListener('invalid', (event) => {
    event.target.classList.add('input-error');
}, true);

document.addEventListener('input', (event) => {
    if (event.target.classList?.contains('input-error') && event.target.validity?.valid) {
        event.target.classList.remove('input-error');
    }
});

// Show a field only when another field has a given value: data-show-when="field=value"
function syncConditional() {
    document.querySelectorAll('[data-show-when]').forEach((element) => {
        const [name, values] = element.dataset.showWhen.split('=');
        const scope = element.closest('form') || document;
        const control = scope.querySelector(`[name="${name}"]:checked`) || scope.querySelector(`[name="${name}"]`);
        const current = control?.type === 'checkbox' ? (control.checked ? '1' : '0') : control?.value;
        element.hidden = !values.split('|').includes(current ?? '');
    });
}

document.addEventListener('change', syncConditional);
syncConditional();
