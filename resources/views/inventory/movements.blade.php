@extends('layouts.app')
@section('title', __('Stock history'))

@section('content')
    <x-page-header :title="__('Stock history')" :subtitle="__('Every purchase, sale, return, adjustment, damage and transfer.')" :back="route('inventory.index')" />

    <x-filters :search="false">
        <div>
            <label for="f-product" class="sr-only">{{ __('Product') }}</label>
            <select name="product_id" id="f-product" class="input w-auto max-w-56">
                <option value="">{{ __('All products') }}</option>
                @foreach ($products as $product)<option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="f-type" class="sr-only">{{ __('Type') }}</label>
            <select name="type" id="f-type" class="input w-auto">
                <option value="">{{ __('All types') }}</option>
                @foreach (['purchase', 'sale', 'return', 'adjustment', 'damage', 'transfer'] as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ status_label($type) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="f-location" class="sr-only">{{ __('Location') }}</label>
            <select name="location" id="f-location" class="input w-auto">
                <option value="">{{ __('All locations') }}</option>
                @foreach ($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location') === (string) $location->id)>{{ $location->name }}</option>@endforeach
            </select>
        </div>
        <div><label for="f-from" class="label">{{ __('From') }}</label><input type="date" name="from" id="f-from" value="{{ request('from') }}" class="input w-auto"></div>
        <div><label for="f-to" class="label">{{ __('To') }}</label><input type="date" name="to" id="f-to" value="{{ request('to') }}" class="input w-auto"></div>
    </x-filters>

    @if ($movements->count())
        <x-table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Product') }}</th><th>{{ __('Type') }}</th><th>{{ __('Location') }}</th><th>{{ __('Note') }}</th><th>{{ __('By') }}</th><th class="text-end">{{ __('Quantity') }}</th></tr></thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td data-label="{{ __('Date') }}">{{ tdate($movement->created_at, 'd M Y, H:i') }}</td>
                        <td data-label="{{ __('Product') }}">{{ $movement->product?->name }}</td>
                        <td data-label="{{ __('Type') }}">{{ status_label($movement->type) }}</td>
                        <td data-label="{{ __('Location') }}">{{ $movement->location?->name }}@if ($movement->toLocation) &rarr; {{ $movement->toLocation->name }}@endif</td>
                        <td data-label="{{ __('Note') }}" class="text-muted">{{ $movement->notes ?: '—' }}</td>
                        <td data-label="{{ __('By') }}">{{ $movement->user?->name ?? __('System') }}</td>
                        <td data-label="{{ __('Quantity') }}" class="num text-end {{ $movement->type === 'transfer' ? '' : ((float) $movement->quantity < 0 ? 'text-danger' : 'text-ok') }}">{{ (float) $movement->quantity > 0 && $movement->type !== 'transfer' ? '+' : '' }}{{ (float) $movement->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $movements->links() }}
    @else
        <div class="card"><x-empty :title="__('No stock movements yet')" :text="__('Movements are recorded automatically when invoices are sent and when you add or adjust stock.')" /></div>
    @endif
@endsection
