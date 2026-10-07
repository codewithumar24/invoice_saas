/*
 | Line items for invoices, quotes and recurring invoices.
 | Adds/removes rows, fills a row from the product catalogue and shows a LIVE PREVIEW of the totals.
 | The preview is for convenience only: the server recalculates every amount when the form is saved.
 */
const host = document.querySelector('[data-line-items]');

if (host) {
    const config = JSON.parse(host.querySelector('[data-config]').textContent);
    const body = host.querySelector('[data-rows]');
    const template = host.querySelector('template');
    const form = host.closest('form');
    let index = 0;

    const products = new Map(config.products.map((product) => [String(product.id), product]));
    const rates = new Map(config.taxRates.map((rate) => [String(rate.id), Number(rate.rate)]));
    const defaultRate = config.taxRates.find((rate) => rate.is_default)?.id ?? '';

    const currencyField = form.querySelector('[name="currency"]');
    const currency = () => currencyField?.value || config.currency;
    const decimals = () => config.decimals[currency()] ?? 2;
    const round = (value) => {
        const factor = 10 ** decimals();
        return Math.round((value + Number.EPSILON) * factor) / factor;
    };
    const money = (value) => {
        try {
            return new Intl.NumberFormat(document.documentElement.lang || 'en', { style: 'currency', currency: currency(), minimumFractionDigits: decimals(), maximumFractionDigits: decimals() }).format(value);
        } catch {
            return `${currency()} ${value.toFixed(decimals())}`;
        }
    };
    const number = (input) => {
        const value = parseFloat(input?.value);
        return Number.isFinite(value) ? value : 0;
    };

    function addRow(data = {}) {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('[data-row]');

        row.querySelectorAll('[data-field]').forEach((field) => {
            const name = field.dataset.field;
            field.name = `items[${index}][${name}]`;
            field.id = `item-${index}-${name}`;
            if (data[name] !== undefined && data[name] !== null) field.value = data[name];
        });

        row.querySelectorAll('label[data-for]').forEach((label) => (label.htmlFor = `item-${index}-${label.dataset.for}`));

        // New empty rows start with the default tax rate
        if (data.tax_rate_id === undefined) row.querySelector('[data-field="tax_rate_id"]').value = defaultRate;

        // Server-side validation errors for this row
        const errors = config.errors?.[index];
        if (errors) {
            Object.entries(errors).forEach(([name, message]) => {
                const field = row.querySelector(`[data-field="${name}"]`);
                if (!field) return;
                field.classList.add('input-error');
                const note = document.createElement('p');
                note.className = 'field-error';
                note.textContent = message;
                field.insertAdjacentElement('afterend', note);
            });
        }

        index += 1;
        body.appendChild(fragment);
        return row;
    }

    function fillFromProduct(row, productId) {
        const product = products.get(String(productId));
        if (!product) return;
        const set = (name, value) => {
            const field = row.querySelector(`[data-field="${name}"]`);
            if (field) field.value = value ?? '';
        };
        set('name', product.name);
        set('description', product.description);
        set('unit', product.unit);
        set('unit_price', Number(product.selling_price));
        set('tax_rate_id', product.tax_rate_id ?? defaultRate);
        if (!number(row.querySelector('[data-field="quantity"]'))) set('quantity', 1);
    }

    function recalculate() {
        const lines = [...body.querySelectorAll('[data-row]')].map((row) => {
            const gross = number(row.querySelector('[data-field="quantity"]')) * number(row.querySelector('[data-field="unit_price"]'));
            const net = round(gross - gross * number(row.querySelector('[data-field="discount_percent"]')) / 100);
            const rate = config.taxExempt ? 0 : rates.get(row.querySelector('[data-field="tax_rate_id"]').value) ?? 0;
            row.querySelector('[data-line-total]').textContent = money(net);
            return { net, rate };
        });

        const subtotal = lines.reduce((sum, line) => sum + line.net, 0);
        const discountValue = number(form.querySelector('[name="discount_value"]'));
        const discountType = form.querySelector('[name="discount_type"]')?.value || 'percent';
        const discount = discountType === 'fixed' ? Math.min(Math.max(discountValue, 0), Math.max(subtotal, 0)) : round(subtotal * discountValue / 100);

        // Tax is calculated per line on the discounted base, the same way the server does it
        let tax = 0;
        lines.forEach((line) => {
            const base = subtotal > 0 ? line.net - discount * (line.net / subtotal) : 0;
            tax += round(config.taxInclusive ? base * line.rate / (100 + line.rate) : base * line.rate / 100);
        });

        const shipping = number(form.querySelector('[name="shipping"]'));
        const adjustment = number(form.querySelector('[name="adjustment"]'));
        const total = subtotal - discount + (config.taxInclusive ? 0 : tax) + shipping + adjustment;

        const show = (key, value) => {
            const element = host.closest('form').querySelector(`[data-total="${key}"]`);
            if (element) element.textContent = money(value);
        };

        show('subtotal', subtotal);
        show('discount', -discount);
        show('tax', tax);
        show('shipping', shipping);
        show('adjustment', adjustment);
        show('grand', total);
    }

    host.addEventListener('click', (event) => {
        if (event.target.closest('[data-add-row]')) {
            const row = addRow();
            row.querySelector('[data-field="name"]')?.focus();
            recalculate();
        }

        const remove = event.target.closest('[data-remove-row]');
        if (remove) {
            remove.closest('[data-row]').remove();
            if (!body.querySelector('[data-row]')) addRow();
            recalculate();
        }
    });

    host.addEventListener('change', (event) => {
        if (event.target.matches('[data-field="product_id"]')) {
            fillFromProduct(event.target.closest('[data-row]'), event.target.value);
        }
        recalculate();
    });

    form.addEventListener('input', recalculate);

    // When a customer is chosen, use their currency and payment terms
    const customerField = form.querySelector('[name="customer_id"]');
    customerField?.addEventListener('change', () => {
        const option = customerField.selectedOptions[0];
        if (!option) return;
        if (option.dataset.currency && currencyField) currencyField.value = option.dataset.currency;
        const due = form.querySelector('[name="due_date"]');
        const issue = form.querySelector('[name="issue_date"]');
        if (option.dataset.terms && due && issue?.value) {
            const date = new Date(`${issue.value}T00:00:00`);
            date.setDate(date.getDate() + Number(option.dataset.terms));
            due.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        }
        recalculate();
    });

    (config.items.length ? config.items : [{}]).forEach((item) => addRow(item));
    recalculate();
}
