<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'type' => ['required', Rule::in(['product', 'service'])],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->where('tenant_id', tenant()->id)->whereNull('deleted_at')->ignore($product?->id)],
            'barcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', tenant_exists('product_categories')],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'tax_rate_id' => ['nullable', 'integer', tenant_exists('tax_rates')],
            'unit' => ['nullable', 'string', 'max:50'],
            'track_inventory' => ['nullable', 'boolean'],
            'opening_stock' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'tags' => ['nullable', 'string', 'max:500'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:1000'],
            'variants' => ['nullable', 'array', 'max:50'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['track_inventory' => $this->boolean('track_inventory')]);
    }
}
