@extends('layouts.app')
@section('title', $invoice->exists ? __('Edit invoice :number', ['number' => $invoice->number]) : __('Create invoice'))

@php
    $tenant = tenant();
    $currencyOptions = $currencies;
    $selectedCustomer = old('customer_id', $invoice->customer_id);
@endphp

@section('content')
    <x-page-header :title="$invoice->exists ? __('Edit invoice :number', ['number' => $invoice->number]) : __('Create invoice')" :back="$invoice->exists ? route('invoices.show', $invoice) : route('invoices.index')" />

    <form method="POST" action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" class="space-y-6" id="invoice-form">
        @csrf
        @if ($invoice->exists) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Business information (from Settings) --}}
            <x-card :title="__('From')">
                <x-slot:actions>@can('settings.update')<a href="{{ route('settings.business') }}" class="link text-sm">{{ __('Edit business details') }}</a>@endcan</x-slot:actions>
                <div class="flex items-start gap-4">
                    @if ($tenant->logo_path)
                        <img src="{{ app(\App\Services\FileStorageService::class)->publicUrl($tenant->logo_path) }}" alt="" class="h-12 max-w-32 object-contain">
                    @endif
                    <div class="text-sm">
                        <p class="font-semibold">{{ $tenant->name }}</p>
                        <p class="text-muted">{{ $tenant->fullAddress() ?: __('No address set') }}</p>
                        @if ($tenant->tax_number)<p class="text-muted">{{ setting('tax_label') }}: {{ $tenant->tax_number }}</p>@endif
                    </div>
                </div>
            </x-card>

            {{-- Customer --}}
            <x-card :title="__('Bill to')">
                <x-slot:actions>@can('customers.create')<button type="button" class="link text-sm" data-open="new-customer">{{ __('New customer') }}</button>@endcan</x-slot:actions>
                <div>
                    <label for="f-customer_id" class="label">{{ __('Customer') }}<span class="text-danger" aria-hidden="true"> *</span></label>
                    <select name="customer_id" id="f-customer_id" class="input @error('customer_id') input-error @enderror" required>
                        <option value="">{{ __('Choose a customer') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" data-currency="{{ $customer->currency }}" data-terms="{{ $customer->payment_terms_days }}" @selected((string) $selectedCustomer === (string) $customer->id)>{{ $customer->displayName() }}</option>
                        @endforeach
                    </select>
                    @error('customer_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </x-card>
        </div>

        {{-- Invoice information --}}
        <x-card :title="__('Invoice details')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <span class="label">{{ __('Invoice number') }}</span>
                    <p class="input num bg-raised">{{ $invoice->number ?: __('Assigned when saved') }}</p>
                </div>
                <x-input name="issue_date" type="date" :label="__('Issue date')" :value="$invoice->issue_date" required />
                <x-input name="due_date" type="date" :label="__('Due date')" :value="$invoice->due_date" required />
                <x-select name="currency" :label="__('Currency')" :options="$currencyOptions" :value="$invoice->currency" required />
                <x-input name="reference" :label="__('Reference')" :value="$invoice->reference" :hint="__('Purchase order or your own reference')" />
                <x-input name="payment_terms" :label="__('Payment terms')" :value="$invoice->payment_terms" />
                @if ($projects->count())
                    <x-select name="project_id" :label="__('Project')" :options="$projects->pluck('name', 'id')->all()" :value="$invoice->project_id" :placeholder="__('None')" />
                @endif
                @if ($branches->count())
                    <x-select name="branch_id" :label="__('Branch')" :options="$branches->pluck('name', 'id')->all()" :value="$invoice->branch_id" :placeholder="__('None')" />
                @endif
            </div>
            @if ($customFields->count())
                <div class="mt-4 border-t border-line pt-4">@include('partials.custom-fields', ['model' => $invoice])</div>
            @endif
        </x-card>

        {{-- Items --}}
        <x-card :title="__('Items')">
            @if ($taxInclusive)<p class="caption mb-3">{{ __('Your prices include tax (set in Settings, Tax).') }}</p>@endif
            @include('partials.line-items', ['currency' => $invoice->currency ?: $tenant->currency])
        </x-card>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-6">
                {{-- Payment --}}
                <x-card :title="__('Payment')">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-select name="payment_method_id" :label="__('Preferred payment method')" :options="$paymentMethods->pluck('name', 'id')->all()" :value="$invoice->payment_method_id" :placeholder="__('Any')" />
                        <x-input name="deposit_amount" type="number" step="any" min="0" :label="__('Deposit requested')" :value="$invoice->deposit_amount > 0 ? (float) $invoice->deposit_amount : null" />
                    </div>
                </x-card>

                {{-- Notes --}}
                <x-card :title="__('Notes')">
                    <div class="space-y-4">
                        <x-textarea name="notes" :label="__('Notes for the customer')" :value="$invoice->notes" rows="2" />
                        <x-textarea name="terms" :label="__('Terms')" :value="$invoice->terms" rows="2" />
                        <x-input name="footer" :label="__('Footer')" :value="$invoice->footer" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-select name="template" :label="__('Template')" :options="collect(\App\Models\Invoice::TEMPLATES)->mapWithKeys(fn ($t) => [$t => __(ucfirst($t))])->all()" :value="$invoice->template" />
                            <x-input name="tags" :label="__('Tags')" :value="$invoice->exists ? $invoice->tags->pluck('name')->implode(', ') : null" :hint="__('Separate with commas')" />
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- Summary --}}
            <x-card :title="__('Summary')">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4"><dt class="text-muted">{{ __('Subtotal') }}</dt><dd class="num" data-total="subtotal">0</dd></div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="flex flex-wrap items-center gap-2">
                            <label for="f-discount_value" class="text-muted">{{ __('Discount') }}</label>
                            <input type="number" name="discount_value" id="f-discount_value" value="{{ old('discount_value', $invoice->discount_value > 0 ? (float) $invoice->discount_value : null) }}" min="0" step="any" class="input num w-24 py-1" placeholder="0">
                            <label for="f-discount_type" class="sr-only">{{ __('Discount type') }}</label>
                            <select name="discount_type" id="f-discount_type" class="input w-auto py-1">
                                <option value="percent" @selected(old('discount_type', $invoice->discount_type) === 'percent')>%</option>
                                <option value="fixed" @selected(old('discount_type', $invoice->discount_type) === 'fixed')>{{ __('Amount') }}</option>
                            </select>
                        </dt>
                        <dd class="num" data-total="discount">0</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4"><dt class="text-muted">{{ setting('tax_label') }}@if ($taxInclusive) <span class="caption">({{ __('included') }})</span>@endif</dt><dd class="num" data-total="tax">0</dd></div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="flex items-center gap-2"><label for="f-shipping" class="text-muted">{{ __('Shipping') }}</label>
                            <input type="number" name="shipping" id="f-shipping" value="{{ old('shipping', $invoice->shipping > 0 ? (float) $invoice->shipping : null) }}" min="0" step="any" class="input num w-28 py-1" placeholder="0"></dt>
                        <dd class="num" data-total="shipping">0</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="flex items-center gap-2"><label for="f-adjustment" class="text-muted">{{ __('Adjustment') }}</label>
                            <input type="number" name="adjustment" id="f-adjustment" value="{{ old('adjustment', (float) $invoice->adjustment != 0 ? (float) $invoice->adjustment : null) }}" step="any" class="input num w-28 py-1" placeholder="0"></dt>
                        <dd class="num" data-total="adjustment">0</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-t border-line pt-3 text-base font-semibold"><dt>{{ __('Grand total') }}</dt><dd class="num" data-total="grand">0</dd></div>
                </dl>
                <p class="caption mt-4">{{ __('Totals are a preview. The final amounts are calculated when you save.') }}</p>
                @foreach (['discount_value', 'shipping', 'adjustment'] as $field)
                    @error($field)<p class="field-error">{{ $message }}</p>@enderror
                @endforeach
            </x-card>
        </div>

        <div class="sticky bottom-0 -mx-4 flex flex-wrap justify-end gap-2 border-t border-line bg-surface/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <a href="{{ $invoice->exists ? route('invoices.show', $invoice) : route('invoices.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
            @if (! $invoice->exists || $invoice->status === 'draft')
                <button type="submit" name="action" value="draft" class="btn btn-secondary">{{ __('Save as draft') }}</button>
                @can('invoices.send')
                    <button type="submit" name="action" value="mark_sent" class="btn btn-secondary">{{ __('Save and mark as sent') }}</button>
                    <button type="submit" name="action" value="send" class="btn btn-primary"><x-icon name="send" size="16" />{{ __('Save and send') }}</button>
                @endcan
            @else
                <button type="submit" name="action" value="draft" class="btn btn-primary">{{ __('Save invoice') }}</button>
            @endif
        </div>
    </form>

    {{-- Create a customer without leaving the invoice --}}
    @can('customers.create')
        <x-modal id="new-customer" :title="__('New customer')">
            <form method="POST" action="{{ route('customers.store') }}" class="space-y-4" data-quick-customer="#f-customer_id" data-no-loading>
                @csrf
                <x-input name="name" :label="__('Name')" required id="qc-name" />
                <x-input name="company" :label="__('Company')" id="qc-company" />
                <x-input name="email" type="email" :label="__('Email')" id="qc-email" />
                <x-input name="phone" :label="__('Phone')" id="qc-phone" />
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Add customer') }}</button>
                </div>
            </form>
        </x-modal>
    @endcan
@endsection
