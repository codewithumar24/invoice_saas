<?php

namespace App\Http\Requests;

use App\Services\FileStorageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', tenant_exists('expense_categories')],
            'project_id' => ['nullable', 'integer', tenant_exists('projects')],
            'branch_id' => ['nullable', 'integer', tenant_exists('branches')],
            'vendor' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'lte:amount'],
            'currency' => ['required', Rule::in(array_keys(config('currencies')))],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurring_frequency' => ['nullable', 'required_if:is_recurring,1', Rule::in(['weekly', 'monthly', 'quarterly', 'yearly'])],
            'tags' => ['nullable', 'string', 'max:500'],
            'receipts' => ['nullable', 'array', 'max:5'],
            // Receipts: images or PDF only; type is detected from the file content
            'receipts.*' => FileStorageService::rule(['jpg', 'jpeg', 'png', 'webp', 'pdf']),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_recurring' => $this->boolean('is_recurring')]);
    }
}
