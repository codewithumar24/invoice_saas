@extends('layouts.app')
@section('title', $quote->exists ? __('Edit quote :number', ['number' => $quote->number]) : __('Create quote'))

@section('content')
    <x-page-header :title="$quote->exists ? __('Edit quote :number', ['number' => $quote->number]) : __('Create quote')" :back="$quote->exists ? route('quotes.show', $quote) : route('quotes.index')" />

    <form method="POST" action="{{ $quote->exists ? route('quotes.update', $quote) : route('quotes.store') }}" class="space-y-6">
        @csrf
        @if ($quote->exists) @method('PUT') @endif

        <x-card :title="__('Quote details')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="f-customer_id" class="label">{{ __('Customer') }}<span class="text-danger" aria-hidden="true"> *</span></label>
                    <select name="customer_id" id="f-customer_id" class="input @error('customer_id') input-error @enderror" required>
                        <option value="">{{ __('Choose a customer') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" data-currency="{{ $customer->currency }}" @selected((string) old('customer_id', $quote->customer_id) === (string) $customer->id)>{{ $customer->displayName() }}</option>
                        @endforeach
                    </select>
                    @error('customer_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-input name="issue_date" type="date" :label="__('Date')" :value="$quote->issue_date" required />
                <x-input name="expiry_date" type="date" :label="__('Valid until')" :value="$quote->expiry_date" />
                <x-select name="currency" :label="__('Currency')" :options="$currencies" :value="$quote->currency" required />
            </div>
        </x-card>

        <x-card :title="__('Items')">
            @include('partials.line-items', ['currency' => $quote->currency ?: tenant()->currency])
        </x-card>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-card :title="__('Notes')">
                <div class="space-y-4">
                    <x-textarea name="notes" :label="__('Notes for the customer')" :value="$quote->notes" rows="2" />
                    <x-textarea name="terms" :label="__('Terms')" :value="$quote->terms" rows="2" />
                </div>
            </x-card>
            <x-card :title="__('Summary')">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4"><dt class="text-muted">{{ __('Subtotal') }}</dt><dd class="num" data-total="subtotal">0</dd></div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="flex flex-wrap items-center gap-2">
                            <label for="f-discount_value" class="text-muted">{{ __('Discount') }}</label>
                            <input type="number" name="discount_value" id="f-discount_value" value="{{ old('discount_value', $quote->discount_value > 0 ? (float) $quote->discount_value : null) }}" min="0" step="any" class="input num w-24 py-1" placeholder="0">
                            <label for="f-discount_type" class="sr-only">{{ __('Discount type') }}</label>
                            <select name="discount_type" id="f-discount_type" class="input w-auto py-1">
                                <option value="percent" @selected(old('discount_type', $quote->discount_type) === 'percent')>%</option>
                                <option value="fixed" @selected(old('discount_type', $quote->discount_type) === 'fixed')>{{ __('Amount') }}</option>
                            </select>
                        </dt>
                        <dd class="num" data-total="discount">0</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4"><dt class="text-muted">{{ setting('tax_label') }}</dt><dd class="num" data-total="tax">0</dd></div>
                    <div class="flex items-center justify-between gap-4 border-t border-line pt-3 text-base font-semibold"><dt>{{ __('Total') }}</dt><dd class="num" data-total="grand">0</dd></div>
                </dl>
            </x-card>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ $quote->exists ? route('quotes.show', $quote) : route('quotes.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
            <button type="submit" name="action" value="draft" class="btn btn-secondary">{{ $quote->exists && $quote->status !== 'draft' ? __('Save quote') : __('Save as draft') }}</button>
            @can('quotes.send')<button type="submit" name="action" value="send" class="btn btn-primary"><x-icon name="send" size="16" />{{ __('Save and send') }}</button>@endcan
        </div>
    </form>
@endsection
