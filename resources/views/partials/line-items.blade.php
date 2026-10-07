{{--
    Line items editor shared by invoices, quotes and recurring invoices.
    Rows are built by resources/js/invoice/items.js from the <template> below.
    The totals shown here are a live preview; the server recalculates everything on save.
--}}
@php
    $oldItems = old('items');
    $rowErrors = [];
    foreach ($errors->getMessages() as $key => $messages) {
        if (preg_match('/^items\.(\d+)\.(\w+)$/', $key, $match)) {
            $rowErrors[(int) $match[1]][$match[2]] = $messages[0];
        }
    }
    $config = [
        'items' => array_values($oldItems ?? $items),
        // Errors are matched to rows by position, because rows are re-numbered when the form is rebuilt
        'errors' => $oldItems ? array_map(fn ($key) => $rowErrors[$key] ?? null, array_keys($oldItems)) : [],
        'products' => $products,
        'taxRates' => $taxRates,
        'taxInclusive' => $taxInclusive,
        'taxExempt' => (bool) setting('tax_exempt') || setting('tax_system') === 'none',
        'currency' => $currency,
        'decimals' => collect(config('currencies'))->map(fn ($c) => $c['decimals'])->all(),
    ];
@endphp
<div data-line-items>
    <script type="application/json" data-config>@json($config)</script>

    {{-- Column headings (desktop). On small screens each field shows its own label instead. --}}
    <div class="hidden gap-3 border-b border-line pb-2 text-xs font-medium text-muted lg:grid lg:grid-cols-[minmax(0,2.2fr)_5rem_5rem_7rem_5rem_8rem_7rem_2.25rem]" aria-hidden="true">
        <span>{{ __('Item') }}</span><span>{{ __('Quantity') }}</span><span>{{ __('Unit') }}</span><span>{{ __('Price') }}</span><span>{{ __('Discount %') }}</span><span>{{ setting('tax_label') }}</span><span class="text-end">{{ __('Total') }}</span><span></span>
    </div>

    <div data-rows class="divide-y divide-line"></div>

    <template>
        <div data-row class="grid grid-cols-2 gap-3 py-4 sm:grid-cols-4 lg:grid-cols-[minmax(0,2.2fr)_5rem_5rem_7rem_5rem_8rem_7rem_2.25rem] lg:items-start">
            <div class="col-span-2 space-y-2 sm:col-span-4 lg:col-span-1">
                @if (count($products))
                    <label class="sr-only" data-for="product_id">{{ __('Choose from catalogue') }}</label>
                    <select class="input" data-field="product_id">
                        <option value="">{{ __('Custom item, or choose from catalogue') }}</option>
                        @foreach ($products->groupBy('type') as $type => $group)
                            <optgroup label="{{ $type === 'service' ? __('Services') : __('Products') }}">
                                @foreach ($group as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                @else
                    <input type="hidden" data-field="product_id">
                @endif
                <label class="sr-only" data-for="name">{{ __('Item name') }}</label>
                <input type="text" class="input" data-field="name" placeholder="{{ __('Item name') }}" maxlength="255" required>
                <label class="sr-only" data-for="description">{{ __('Description') }}</label>
                <textarea class="input min-h-0" rows="1" data-field="description" placeholder="{{ __('Description (optional)') }}" maxlength="2000"></textarea>
            </div>
            <div>
                <label class="label lg:sr-only" data-for="quantity">{{ __('Quantity') }}</label>
                <input type="number" class="input num" data-field="quantity" value="1" min="0.0001" step="any" required>
            </div>
            <div>
                <label class="label lg:sr-only" data-for="unit">{{ __('Unit') }}</label>
                <input type="text" class="input" data-field="unit" maxlength="50" placeholder="{{ __('pcs, hour') }}">
            </div>
            <div>
                <label class="label lg:sr-only" data-for="unit_price">{{ __('Price') }}</label>
                <input type="number" class="input num" data-field="unit_price" value="0" min="0" step="any" required>
            </div>
            <div>
                <label class="label lg:sr-only" data-for="discount_percent">{{ __('Discount %') }}</label>
                <input type="number" class="input num" data-field="discount_percent" min="0" max="100" step="any" placeholder="0">
            </div>
            <div>
                <label class="label lg:sr-only" data-for="tax_rate_id">{{ setting('tax_label') }}</label>
                <select class="input" data-field="tax_rate_id">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($taxRates as $rate)<option value="{{ $rate->id }}">{{ $rate->name }} ({{ (float) $rate->rate }}%)</option>@endforeach
                </select>
            </div>
            <div class="flex items-center justify-between gap-2 lg:block lg:pt-2 lg:text-end">
                <span class="label mb-0 lg:sr-only">{{ __('Total') }}</span>
                <span class="num font-medium" data-line-total>0</span>
            </div>
            <div class="flex justify-end lg:pt-0.5">
                <button type="button" class="btn btn-ghost btn-icon text-muted hover:text-danger" data-remove-row aria-label="{{ __('Remove item') }}"><x-icon name="trash" size="16" /></button>
            </div>
        </div>
    </template>

    <button type="button" class="btn btn-secondary btn-sm mt-3" data-add-row><x-icon name="plus" size="14" />{{ __('Add item') }}</button>
    @error('items')<p class="field-error">{{ $message }}</p>@enderror
</div>
