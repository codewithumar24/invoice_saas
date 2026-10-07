/** Create a customer from inside the invoice form without leaving the page. */
import { request } from '../core/http';
import { toast } from '../core/toast';

const form = document.querySelector('[data-quick-customer]');

if (form) {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        const button = form.querySelector('button[type="submit"]');
        button.dataset.loading = '';
        form.querySelectorAll('.field-error').forEach((node) => node.remove());

        try {
            const { data, message } = await request(form.action, { method: 'POST', body: new FormData(form) });
            const select = document.querySelector(form.dataset.quickCustomer);
            const option = new Option(data.name, data.id, true, true);
            if (data.currency) option.dataset.currency = data.currency;
            if (data.payment_terms_days) option.dataset.terms = data.payment_terms_days;
            select.add(option);
            select.dispatchEvent(new Event('change', { bubbles: true }));
            form.reset();
            form.closest('dialog').close();
            toast(message);
        } catch (error) {
            Object.entries(error.errors || {}).forEach(([name, messages]) => {
                const field = form.querySelector(`[name="${name}"]`);
                if (!field) return;
                const note = document.createElement('p');
                note.className = 'field-error';
                note.textContent = messages[0];
                field.insertAdjacentElement('afterend', note);
            });
            if (!Object.keys(error.errors || {}).length) toast(error.message, 'danger');
        } finally {
            delete button.dataset.loading;
        }
    }, true);
}
