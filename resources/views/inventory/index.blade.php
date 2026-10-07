@extends('layouts.app')
@section('title', __('Inventory'))

@php $canManage = auth()->user()->hasPermission('inventory.manage'); @endphp

@section('content')
    <x-page-header :title="__('Inventory')">
        <a href="{{ route('inventory.movements') }}" class="btn btn-secondary">{{ __('Stock history') }}</a>
        @if ($canManage)
            <button type="button" class="btn btn-secondary" data-open="locations">{{ __('Locations') }}</button>
            <button type="button" class="btn btn-secondary" data-open="transfer-stock">{{ __('Transfer') }}</button>
            <button type="button" class="btn btn-primary" data-open="adjust-stock">{{ __('Stock in / out') }}</button>
        @endif
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Stock value')" :value="money($valuation)" :note="__('At purchase price')" />
        <x-stat :label="__('Low on stock')" :value="$lowCount" :tone="$lowCount ? 'danger' : null" :href="route('inventory.index', ['low' => 1])" :note="__('At or below minimum')" />
        <x-stat :label="__('Locations')" :value="$locations->count()" />
    </div>

    <x-filters :placeholder="__('Product name or SKU')">
        @if ($locations->count() > 1)
            <div>
                <label for="f-location" class="sr-only">{{ __('Location') }}</label>
                <select name="location" id="f-location" class="input w-auto">
                    <option value="">{{ __('All locations') }}</option>
                    @foreach ($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location') === (string) $location->id)>{{ $location->name }}</option>@endforeach
                </select>
            </div>
        @endif
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" name="low" value="1" @checked(request('low'))>{{ __('Low stock only') }}</label>
    </x-filters>

    @if ($products->count())
        <x-table>
            <thead><tr><th><x-sort column="name" :label="__('Product')" /></th><th>{{ __('SKU') }}</th>@foreach ($locations as $location)<th class="text-end">{{ $location->name }}</th>@endforeach<th class="text-end"><x-sort column="stock_quantity" :label="__('Total')" /></th><th class="text-end">{{ __('Minimum') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($products as $product)
                    @php $byLocation = $product->stock->keyBy('location_id'); @endphp
                    <tr>
                        <td data-label="{{ __('Product') }}"><a href="{{ route('products.show', $product) }}" class="font-medium hover:text-brand">{{ $product->name }}</a></td>
                        <td data-label="{{ __('SKU') }}" class="num text-muted">{{ $product->sku ?: '—' }}</td>
                        @foreach ($locations as $location)
                            <td data-label="{{ $location->name }}" class="num text-end">{{ (float) ($byLocation[$location->id]->quantity ?? 0) }}</td>
                        @endforeach
                        <td data-label="{{ __('Total') }}" class="num text-end font-medium {{ $product->isLowStock() ? 'text-danger' : '' }}">{{ (float) $product->stock_quantity }}</td>
                        <td data-label="{{ __('Minimum') }}" class="num text-end text-muted">{{ (float) $product->minimum_stock }}</td>
                        <td class="text-end">@if ($product->isLowStock())<x-badge tone="danger">{{ __('Low') }}</x-badge>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $products->links() }}
    @else
        <div class="card">
            <x-empty :title="request()->except('page') ? __('No products match these filters') : __('No products are tracked yet')" :text="__('Open a product and switch on Track inventory. Its stock then appears here and moves with every invoice.')">
                <a href="{{ route('products.index', ['type' => 'product']) }}" class="btn btn-primary">{{ __('Go to products') }}</a>
            </x-empty>
        </div>
    @endif

    @if ($canManage)
        <x-modal id="adjust-stock" :title="__('Stock in / out')" :auto-open="$errors->hasAny(['product_id', 'location_id', 'type', 'unit_cost']) || ($errors->has('quantity') && ! old('from_location_id'))">
            <form method="POST" action="{{ route('inventory.adjust') }}" class="space-y-4">
                @csrf
                <x-select name="product_id" :label="__('Product')" :options="$allProducts->mapWithKeys(fn ($p) => [$p->id => $p->name.($p->sku ? ' ('.$p->sku.')' : '')])->all()" :placeholder="__('Choose a product')" required id="adjust-product" />
                <x-select name="type" :label="__('What happened?')" :options="collect($types)->map(fn ($label) => __($label))->all()" required id="adjust-type" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="quantity" type="number" step="any" :label="__('Quantity')" required id="adjust-quantity" :hint="__('For an adjustment, use a minus sign to reduce stock.')" />
                    <x-select name="location_id" :label="__('Location')" :options="$locations->pluck('name', 'id')->all()" :value="$locations->firstWhere('is_default', true)?->id" required id="adjust-location" />
                </div>
                <div data-show-when="type=purchase"><x-input name="unit_cost" type="number" step="any" min="0" :label="__('Cost per unit')" id="adjust-cost" /></div>
                <x-input name="notes" :label="__('Note')" id="adjust-notes" />
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Update stock') }}</button>
                </div>
            </form>
        </x-modal>

        <x-modal id="transfer-stock" :title="__('Transfer stock')" :auto-open="$errors->hasAny(['from_location_id', 'to_location_id'])">
            @if ($locations->count() < 2)
                <p class="text-sm text-muted">{{ __('Add a second location first, then you can move stock between them.') }}</p>
            @else
                <form method="POST" action="{{ route('inventory.transfer') }}" class="space-y-4">
                    @csrf
                    <x-select name="product_id" :label="__('Product')" :options="$allProducts->pluck('name', 'id')->all()" :placeholder="__('Choose a product')" required id="transfer-product" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-select name="from_location_id" :label="__('From')" :options="$locations->pluck('name', 'id')->all()" required />
                        <x-select name="to_location_id" :label="__('To')" :options="$locations->pluck('name', 'id')->all()" :value="$locations->skip(1)->first()?->id" required />
                    </div>
                    <x-input name="quantity" type="number" step="any" min="0.0001" :label="__('Quantity')" required id="transfer-quantity" />
                    <x-input name="notes" :label="__('Note')" id="transfer-notes" />
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Transfer stock') }}</button>
                    </div>
                </form>
            @endif
        </x-modal>

        <x-modal id="locations" :title="__('Locations')" :auto-open="$errors->hasAny(['name', 'address', 'limit'])">
            <ul class="mb-4 divide-y divide-line rounded-md border border-line">
                @foreach ($locations as $location)
                    <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                        <span><span class="font-medium">{{ $location->name }}</span> <span class="text-muted">{{ status_label($location->type) }}@if ($location->is_default), {{ __('default') }}@endif</span></span>
                        @unless ($location->is_default)
                            <x-action :action="route('inventory.locations.destroy', $location)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete location :name?', ['name' => $location->name])" :confirm-label="__('Delete')">{{ __('Delete') }}</x-action>
                        @endunless
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('inventory.locations.store') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="name" :label="__('New location name')" required id="location-name" />
                    <x-select name="type" :label="__('Type')" :options="['warehouse' => __('Warehouse'), 'store' => __('Store'), 'office' => __('Office'), 'branch' => __('Branch')]" id="location-type" />
                </div>
                <x-input name="address" :label="__('Address')" id="location-address" />
                @error('limit')<p class="field-error">{{ $message }}</p>@enderror
                <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Add location') }}</button></div>
            </form>
        </x-modal>
    @endif
@endsection
