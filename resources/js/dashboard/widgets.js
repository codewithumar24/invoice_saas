/** Dashboard personalisation: tick the widgets you want and move them up or down. The order is saved per user. */
document.querySelectorAll('[data-sortable]').forEach((list) => {
    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-move]');
        if (!button) return;
        const item = button.closest('[data-sortable-item]');

        if (button.dataset.move === 'up' && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
        } else if (button.dataset.move === 'down' && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
        }

        button.focus();
    });
});
