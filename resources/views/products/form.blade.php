@extends('layouts.app')
@section('title', $product->exists ? __('Edit :name', ['name' => $product->name]) : __('Add to catalogue'))

@php $variants = old('variants', $product->exists ? $product->variants->map->only(['name', 'sku', 'price'])->all() : []); @endphp

@section('content')
    <x-page-header :title="$product->exists ? __('Edit :name', ['name' => $product->name]) : __('Add to catalogue')" :back="$product->exists ? route('products.show', $product) : route('products.index')" />

    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <x-card :title="__('Details')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="type" :label="__('Type')" :options="['product' => __('Product (a physical item)'), 'service' => __('Service (work you do)')]" :value="$product->type" required />
                <x-input name="name" :label="__('Name')" :value="$product->name" required />
                <div class="sm:col-span-2"><x-textarea name="description" :label="__('Description')" :value="$product->description" rows="2" :hint="__('Copied to the invoice line.')" /></div>
                <x-input name="selling_price" type="number" step="any" min="0" :label="__('Selling price (:currency)', ['currency' => tenant()->currency])" :value="$product->exists ? (float) $product->selling_price : null" required />
                <x-select name="tax_rate_id" :label="setting('tax_label')" :options="$taxRates->mapWithKeys(fn ($r) => [$r->id => $r->name.' ('.(float) $r->rate.'%)'])->all()" :value="$product->tax_rate_id" :placeholder="__('None')" />
                <x-input name="unit" :label="__('Unit')" :value="$product->unit" placeholder="{{ __('pcs, kg, hour') }}" />
                <div>
                    <x-select name="category_id" :label="__('Category')" :options="$categories->pluck('name', 'id')->all()" :value="$product->category_id" :placeholder="__('None')" />
                    <button type="button" class="link mt-1 text-sm" data-open="new-category">{{ __('New category') }}</button>
                </div>
                <div data-show-when="type=service"><x-input name="duration_minutes" type="number" min="0" :label="__('Duration (minutes)')" :value="$product->duration_minutes" /></div>
                <x-select name="status" :label="__('Status')" :options="['active' => __('Active'), 'archived' => __('Archived')]" :value="$product->status ?? 'active'" />
            </div>
        </x-card>

        <div data-show-when="type=product" class="space-y-6">
            <x-card :title="__('Product identifiers and cost')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-input name="sku" :label="__('SKU')" :value="$product->sku" />
                    <x-input name="barcode" :label="__('Barcode')" :value="$product->barcode" />
                    <x-input name="purchase_price" type="number" step="any" min="0" :label="__('Purchase price')" :value="$product->exists ? (float) $product->purchase_price : null" :hint="__('Used for profit and stock value.')" />
                </div>
                <div class="mt-4">
                    <label for="f-image" class="label">{{ __('Image') }}</label>
                    <input type="file" name="image" id="f-image" accept="image/png,image/jpeg,image/webp" class="input">
                    @error('image')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </x-card>

            @if (feature('inventory'))
                <x-card :title="__('Inventory')">
                    <x-switch name="track_inventory" :label="__('Track stock for this product')" :hint="__('Sending an invoice takes stock out; cancelling it puts stock back.')" :checked="$product->track_inventory" />
                    <div class="mt-4 grid gap-4 sm:grid-cols-2" data-show-when="track_inventory=1">
                        @unless ($product->exists)
                            <x-input name="opening_stock" type="number" step="any" min="0" :label="__('Opening stock')" />
                        @else
                            <div><span class="label">{{ __('In stock') }}</span><p class="input num bg-raised">{{ (float) $product->stock_quantity }}</p><p class="field-hint">{{ __('Change stock from Inventory, so every change has a history.') }}</p></div>
                        @endunless
                        <x-input name="minimum_stock" type="number" step="any" min="0" :label="__('Minimum stock')" :value="(float) $product->minimum_stock ?: null" :hint="__('You are alerted when stock reaches this level.')" />
                    </div>
                </x-card>
            @endif

            <x-card :title="__('Variants')">
                <p class="mb-3 text-sm text-muted">{{ __('Optional: sizes, colours or editions with their own SKU and price.') }}</p>
                <div data-builder="variants">
                    {{-- Sent first, so an empty list is still submitted and removed variants are deleted --}}
                    <input type="hidden" name="variants" value="">
                    <div data-builder-rows class="space-y-2">
                        @foreach ($variants as $variant)
                            <div data-builder-row class="grid grid-cols-[1fr_1fr_7rem_2.25rem] gap-2">
                                <input type="text" class="input" data-name="name" value="{{ $variant['name'] ?? '' }}" placeholder="{{ __('Variant name') }}" aria-label="{{ __('Variant name') }}" required>
                                <input type="text" class="input" data-name="sku" value="{{ $variant['sku'] ?? '' }}" placeholder="{{ __('SKU') }}" aria-label="{{ __('SKU') }}">
                                <input type="number" step="any" min="0" class="input num" data-name="price" value="{{ isset($variant['price']) ? (float) $variant['price'] : '' }}" placeholder="{{ __('Price') }}" aria-label="{{ __('Price') }}">
                                <button type="button" class="btn btn-ghost btn-icon text-muted hover:text-danger" data-builder-remove aria-label="{{ __('Remove variant') }}"><x-icon name="trash" size="16" /></button>
                            </div>
                        @endforeach
                    </div>
                    <template>
                        <div data-builder-row class="grid grid-cols-[1fr_1fr_7rem_2.25rem] gap-2">
                            <input type="text" class="input" data-name="name" placeholder="{{ __('Variant name') }}" aria-label="{{ __('Variant name') }}" required>
                            <input type="text" class="input" data-name="sku" placeholder="{{ __('SKU') }}" aria-label="{{ __('SKU') }}">
                            <input type="number" step="any" min="0" class="input num" data-name="price" placeholder="{{ __('Price') }}" aria-label="{{ __('Price') }}">
                            <button type="button" class="btn btn-ghost btn-icon text-muted hover:text-danger" data-builder-remove aria-label="{{ __('Remove variant') }}"><x-icon name="trash" size="16" /></button>
                        </div>
                    </template>
                    <button type="button" class="btn btn-secondary btn-sm mt-3" data-builder-add><x-icon name="plus" size="14" />{{ __('Add variant') }}</button>
                </div>
            </x-card>
        </div>

        <x-card :title="__('Organise')">
            <x-input name="tags" :label="__('Tags')" :value="$product->exists ? $product->tags->pluck('name')->implode(', ') : null" :hint="__('Separate with commas')" />
            @if ($customFields->count())<div class="mt-4">@include('partials.custom-fields', ['model' => $product])</div>@endif
        </x-card>

        <div class="flex justify-end gap-2">
            <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>

    <x-modal id="new-category" :title="__('New category')">
        <form method="POST" action="{{ route('products.categories.store') }}" class="space-y-4">
            @csrf
            <x-input name="name" :label="__('Category name')" required id="category-name" />
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Add category') }}</button>
            </div>
        </form>
    </x-modal>
@endsection
