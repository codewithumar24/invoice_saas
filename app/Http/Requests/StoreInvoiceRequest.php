<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Note what is NOT accepted: subtotal, tax and total. The browser shows a live preview,
 * but every amount is recalculated on the server from quantities, prices and rates.
 */
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', tenant_exists('customers')],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', Rule::in(array_keys(config('currencies')))],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'shipping' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'adjustment' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payment_method_id' => ['nullable', 'integer', tenant_exists('payment_methods')],
            'project_id' => ['nullable', 'integer', tenant_exists('projects')],
            'branch_id' => ['nullable', 'integer', tenant_exists('branches')],
            'template' => ['nullable', Rule::in(Invoice::TEMPLATES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'footer' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['nullable', 'integer', tenant_exists('products')],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate_id' => ['nullable', 'integer', tenant_exists('tax_rates')],
            'action' => ['nullable', Rule::in(['draft', 'send', 'mark_sent'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'items.*.name' => __('item name'), 'items.*.quantity' => __('quantity'), 'items.*.unit_price' => __('price'),
            'items.*.discount_percent' => __('discount'), 'customer_id' => __('customer'),
        ];
    }

    public function messages(): array
    {
        return ['items.required' => __('Add at least one item to the invoice.')];
    }
}
