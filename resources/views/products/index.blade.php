@extends('layouts.app')
@section('title', __('Products & services'))

@section('content')
    <x-page-header :title="__('Products & services')" :subtitle="__('Your catalogue. An invoice can mix products and services.')">
        @can('products.create')
            <a href="{{ route('imports.index') }}" class="btn btn-secondary">{{ __('Import CSV') }}</a>
            <a href="{{ route('products.create', ['type' => 'service']) }}" class="btn btn-secondary">{{ __('Add service') }}</a>
            <a href="{{ route('products.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ __('Add product') }}</a>
        @endcan
    </x-page-header>

    <nav class="mb-4 flex gap-6 overflow-x-auto border-b border-line" aria-label="{{ __('Type') }}">
        <a href="{{ route('products.index', request()->except(['type', 'page'])) }}" class="tab" @if (! request('type')) aria-current="page" @endif>{{ __('All') }}</a>
        <a href="{{ route('products.index', ['type' => 'product'] + request()->except('page')) }}" class="tab" @if (request('type') === 'product') aria-current="page" @endif>{{ __('Products') }}</a>
        <a href="{{ route('products.index', ['type' => 'service'] + request()->except('page')) }}" class="tab" @if (request('type') === 'service') aria-current="page" @endif>{{ __('Services') }}</a>
    </nav>

    <x-filters :placeholder="__('Name, SKU or barcode')">
        @if (request('type'))<input type="hidden" name="type" value="{{ request('type') }}">@endif
        @if ($categories->count())
            <div>
                <label for="f-category" class="sr-only">{{ __('Category') }}</label>
                <select name="category" id="f-category" class="input w-auto">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>@endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="f-status" class="sr-only">{{ __('Status') }}</label>
            <select name="status" id="f-status" class="input w-auto">
                <option value="">{{ __('Active') }}</option>
                <option value="archived" @selected(request('status') === 'archived')>{{ __('Archived') }}</option>
            </select>
        </div>
    </x-filters>

    @if ($products->count())
        <form method="POST" action="{{ route('products.bulk') }}" data-bulk>
            @csrf
            @include('partials.bulk-bar', ['actions' => ['archive' => __('Archive'), 'activate' => __('Make active'), 'export' => __('Export CSV')]])
            <x-table>
                <thead><tr>
                    <th class="w-8"><input type="checkbox" class="check" data-bulk-all aria-label="{{ __('Select all') }}"></th>
                    <th><x-sort column="name" :label="__('Name')" /></th><th>{{ __('Type') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Category') }}</th>
                    <th class="text-end"><x-sort column="selling_price" :label="__('Price')" /></th><th class="text-end"><x-sort column="stock_quantity" :label="__('Stock')" /></th>
                </tr></thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td><input type="checkbox" class="check" name="ids[]" value="{{ $product->id }}" data-bulk-item aria-label="{{ __('Select :name', ['name' => $product->name]) }}"></td>
                            <td data-label="{{ __('Name') }}"><a href="{{ route('products.show', $product) }}" class="font-medium hover:text-brand">{{ $product->name }}</a></td>
                            <td data-label="{{ __('Type') }}">{{ $product->isService() ? __('Service') : __('Product') }}</td>
                            <td data-label="{{ __('SKU') }}" class="num text-muted">{{ $product->sku ?: '—' }}</td>
                            <td data-label="{{ __('Category') }}">{{ $product->category?->name ?? '—' }}</td>
                            <td data-label="{{ __('Price') }}" class="num text-end">{{ money($product->selling_price) }}@if ($product->unit)<span class="text-muted"> / {{ $product->unit }}</span>@endif</td>
                            <td data-label="{{ __('Stock') }}" class="num text-end">
                                @if ($product->track_inventory)
                                    <span class="{{ $product->isLowStock() ? 'font-medium text-danger' : '' }}">{{ (float) $product->stock_quantity }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </form>
        {{ $products->links() }}
    @else
        <div class="card">
            @if (request()->except('page'))
                <x-empty :title="__('Nothing matches these filters')"><a href="{{ route('products.index') }}" class="btn btn-secondary">{{ __('Clear filters') }}</a></x-empty>
            @else
                <x-empty :title="__('Your catalogue is empty')" :text="__('Add the products and services you sell. They fill invoice lines in one click, with the right price and tax.')">
                    @can('products.create')
                        <a href="{{ route('products.create') }}" class="btn btn-primary">{{ __('Add a product') }}</a>
                        <a href="{{ route('products.create', ['type' => 'service']) }}" class="btn btn-secondary">{{ __('Add a service') }}</a>
                    @endcan
                </x-empty>
            @endif
        </div>
    @endif
@endsection
