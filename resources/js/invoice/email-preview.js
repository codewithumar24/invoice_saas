/** "Preview email" dialog: loads the rendered subject and body so the user can adjust them before sending. */
import { request } from '../core/http';
import { toast } from '../core/toast';

document.querySelectorAll('[data-email-preview]').forEach((button) => {
    button.addEventListener('click', async () => {
        const dialog = document.getElementById(button.dataset.dialog);
        const body = dialog.querySelector('[data-preview-body]');
        const loading = dialog.querySelector('[data-preview-loading]');

        body.hidden = true;
        loading.hidden = false;
        dialog.showModal();

        try {
            const { data } = await request(button.dataset.emailPreview);
            dialog.querySelector('[data-preview="to"]').textContent = data.to || '—';
            dialog.querySelector('[name="subject"]').value = data.subject;
            dialog.querySelector('[name="body"]').value = data.body;
            dialog.querySelector('[data-preview="attachment"]').textContent = data.attachment;
            dialog.querySelector('[data-preview="link"]').textContent = data.payment_link;
            loading.hidden = true;
            body.hidden = false;
        } catch (error) {
            dialog.close();
            toast(error.message, 'danger');
        }
    });
});
