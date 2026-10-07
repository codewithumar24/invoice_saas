@extends('layouts.app')
@section('title', $product->name)

@php
    $margin = (float) $product->selling_price > 0 && (float) $product->purchase_price > 0
        ? round(((float) $product->selling_price - (float) $product->purchase_price) / (float) $product->selling_price * 100) : null;
@endphp

@section('content')
    <x-page-header :title="$product->name" :subtitle="$product->isService() ? __('Service') : __('Product')" :back="route('products.index')">
        @if ($product->status === 'archived')<x-badge tone="neutral">{{ __('Archived') }}</x-badge>@endif
        @can('products.update')<a href="{{ route('products.edit', $product) }}" class="btn btn-secondary">{{ __('Edit') }}</a>@endcan
        @can('products.delete')
            <x-action :action="route('products.destroy', $product)" method="DELETE" class="btn btn-danger" :confirm="__('Delete :name? Items used on invoices are archived instead.', ['name' => $product->name])" :confirm-label="__('Delete')">{{ __('Delete') }}</x-action>
        @endcan
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat :label="__('Selling price')" :value="money($product->selling_price)" :note="$product->unit ? __('per :unit', ['unit' => $product->unit]) : null" />
        @unless ($product->isService())
            <x-stat :label="__('Purchase price')" :value="money($product->purchase_price)" />
            <x-stat :label="__('Margin')" :value="$margin !== null ? $margin.'%' : '—'" :tone="$margin !== null && $margin < 10 ? 'danger' : null" />
            @if ($product->track_inventory)
                <x-stat :label="__('In stock')" :value="(float) $product->stock_quantity" :tone="$product->isLowStock() ? 'danger' : null" :note="__('Minimum: :min', ['min' => (float) $product->minimum_stock])" />
            @endif
        @endunless
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Details')">
            @if ($product->image_path)
                <img src="{{ app(\App\Services\FileStorageService::class)->publicUrl($product->image_path) }}" alt="{{ $product->name }}" class="mb-4 max-h-48 rounded-md border border-line object-contain" loading="lazy">
            @endif
            <dl class="space-y-2 text-sm">
                @if ($product->description)<div><dt class="text-muted">{{ __('Description') }}</dt><dd class="whitespace-pre-line">{{ $product->description }}</dd></div>@endif
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('SKU') }}</dt><dd class="num">{{ $product->sku ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Barcode') }}</dt><dd class="num">{{ $product->barcode ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Category') }}</dt><dd>{{ $product->category?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ setting('tax_label') }}</dt><dd>{{ $product->taxRate ? $product->taxRate->name.' ('.(float) $product->taxRate->rate.'%)' : __('None') }}</dd></div>
                @if ($product->duration_minutes)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Duration') }}</dt><dd>{{ __(':minutes min', ['minutes' => $product->duration_minutes]) }}</dd></div>@endif
                @foreach ($customFields as $label => $value)
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>
            @if ($product->tags->count())<div class="mt-3 flex flex-wrap gap-1">@foreach ($product->tags as $tag)<span class="badge badge-neutral">{{ $tag->name }}</span>@endforeach</div>@endif
        </x-card>

        <div class="space-y-6 lg:col-span-2">
            @if ($product->variants->count())
                <x-card :title="__('Variants')" :padded="false">
                    <table class="table table-stack">
                        <thead><tr><th>{{ __('Variant') }}</th><th>{{ __('SKU') }}</th><th class="text-end">{{ __('Price') }}</th></tr></thead>
                        <tbody>
                            @foreach ($product->variants as $variant)
                                <tr><td data-label="{{ __('Variant') }}">{{ $variant->name }}</td><td data-label="{{ __('SKU') }}" class="num">{{ $variant->sku ?: '—' }}</td><td data-label="{{ __('Price') }}" class="num text-end">{{ money($variant->price) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif

            @if ($product->track_inventory && feature('inventory'))
                <x-card :title="__('Stock by location')" :padded="false">
                    <x-slot:actions><a href="{{ route('inventory.index', ['search' => $product->name]) }}" class="link text-sm">{{ __('Adjust stock') }}</a></x-slot:actions>
                    <table class="table">
                        <tbody>
                            @forelse ($product->stock as $stock)
                                <tr><td>{{ $stock->location?->name }}</td><td class="num text-end">{{ (float) $stock->quantity }}</td></tr>
                            @empty
                                <tr><td class="text-muted">{{ __('No stock recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-card>

                <x-card :title="__('Recent stock movements')" :padded="false">
                    <x-slot:actions><a href="{{ route('inventory.movements', ['product_id' => $product->id]) }}" class="link text-sm">{{ __('Full history') }}</a></x-slot:actions>
                    @if ($movements->count())
                        <table class="table table-stack">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Location') }}</th><th>{{ __('Note') }}</th><th class="text-end">{{ __('Quantity') }}</th></tr></thead>
                            <tbody>
                                @foreach ($movements as $movement)
                                    <tr>
                                        <td data-label="{{ __('Date') }}">{{ tdate($movement->created_at) }}</td>
                                        <td data-label="{{ __('Type') }}">{{ status_label($movement->type) }}</td>
                                        <td data-label="{{ __('Location') }}">{{ $movement->location?->name }}</td>
                                        <td data-label="{{ __('Note') }}" class="text-muted">{{ $movement->notes ?: '—' }}</td>
                                        <td data-label="{{ __('Quantity') }}" class="num text-end {{ (float) $movement->quantity < 0 ? 'text-danger' : 'text-ok' }}">{{ (float) $movement->quantity > 0 && $movement->type !== 'transfer' ? '+' : '' }}{{ (float) $movement->quantity }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="card-pad text-sm text-muted">{{ __('Purchases, sales, returns and adjustments are listed here.') }}</p>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
@endsection
