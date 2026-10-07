@extends('layouts.app')
@section('title', __('Invoice settings'))

@php $columns = (array) setting('invoice_columns'); @endphp

@section('content')
    @component('settings._layout', ['templates' => $templates, 'columns' => $columns])
        <form method="POST" action="{{ route('settings.invoices.update') }}" class="space-y-6">
            @csrf @method('PUT')

            <x-card :title="__('Numbering')">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-input name="invoice_prefix" :label="__('Invoice prefix')" :value="setting('invoice_prefix')" required />
                    <x-input name="invoice_next_number" type="number" min="1" :label="__('Starting number')" :value="setting('invoice_next_number')" required :hint="__('Used when a new sequence begins.')" />
                    <x-input name="invoice_padding" type="number" min="1" max="10" :label="__('Digits')" :value="setting('invoice_padding')" required />
                    <x-select name="invoice_reset" :label="__('Restart numbering')" :options="['never' => __('Never'), 'yearly' => __('Every year'), 'monthly' => __('Every month')]" :value="setting('invoice_reset')" required />
                    <x-input name="quote_prefix" :label="__('Quote prefix')" :value="setting('quote_prefix')" required />
                    <x-input name="payment_prefix" :label="__('Payment prefix')" :value="setting('payment_prefix')" required />
                </div>
                <p class="mt-3 text-sm text-muted">{{ __('Example: :example. Numbers are issued one by one and never repeat, even when several people create invoices at the same moment.', ['example' => setting('invoice_prefix').'-'.date('Y').'-'.str_pad('1', (int) setting('invoice_padding'), '0', STR_PAD_LEFT)]) }}</p>
                <div class="mt-4"><x-switch name="invoice_branch_sequence" :label="__('Separate number sequence per branch')" :checked="setting('invoice_branch_sequence')" /></div>
            </x-card>

            <x-card :title="__('Defaults for new invoices')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="invoice_due_days" type="number" min="0" max="365" :label="__('Due after (days)')" :value="setting('invoice_due_days')" required />
                    <x-input name="invoice_footer" :label="__('Footer')" :value="setting('invoice_footer')" />
                    <x-textarea name="invoice_notes" :label="__('Notes')" :value="setting('invoice_notes')" rows="2" />
                    <x-textarea name="invoice_terms" :label="__('Terms')" :value="setting('invoice_terms')" rows="2" />
                </div>
            </x-card>

            <x-card :title="__('Appearance')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-select name="invoice_template" :label="__('Template')" :options="collect($templates)->mapWithKeys(fn ($t) => [$t => __(ucfirst($t))])->all()" :value="setting('invoice_template')" required />
                    <x-select name="invoice_font" :label="__('Font')" :options="['DejaVu Sans' => 'DejaVu Sans', 'DejaVu Serif' => 'DejaVu Serif', 'Helvetica' => 'Helvetica', 'Times' => 'Times', 'Courier' => 'Courier']" :value="setting('invoice_font')" required :hint="__('DejaVu supports the most currency symbols and languages.')" />
                    <div>
                        <label for="f-invoice_color" class="label">{{ __('Colour') }}</label>
                        <input type="color" name="invoice_color" id="f-invoice_color" value="{{ old('invoice_color', setting('invoice_color')) }}" class="h-10 w-20 cursor-pointer rounded-md border border-line bg-surface p-1">
                    </div>
                </div>
                <fieldset class="mt-6">
                    <legend class="label">{{ __('Columns on the invoice') }}</legend>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        @foreach (['description' => __('Description'), 'quantity' => __('Quantity'), 'unit' => __('Unit'), 'price' => __('Price'), 'discount' => __('Discount'), 'tax' => __('Tax rate')] as $key => $label)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" name="invoice_columns[]" value="{{ $key }}" @checked(in_array($key, old('invoice_columns', $columns)))>{{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <x-switch name="invoice_show_tax" :label="__('Show the tax breakdown')" :checked="setting('invoice_show_tax')" />
                    <x-switch name="invoice_show_payment_link" :label="__('Show the online payment link')" :checked="setting('invoice_show_payment_link')" />
                    <x-switch name="invoice_show_qr" :label="__('Show a QR code on invoice pages')" :checked="setting('invoice_show_qr')" />
                    <x-switch name="track_invoice_views" :label="__('Record when a customer opens an invoice')" :hint="__('Stores the time and browser only.')" :checked="setting('track_invoice_views')" />
                </div>
            </x-card>

            <x-card :title="__('Payment information on invoices')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-textarea name="invoice_payment_instructions" :label="__('Payment instructions')" :value="setting('invoice_payment_instructions')" rows="3" />
                    <x-textarea name="invoice_bank_details" :label="__('Bank details')" :value="setting('invoice_bank_details')" rows="3" :hint="__('Bank name, account name, IBAN or account number.')" />
                </div>
                <p class="mt-3 text-sm text-muted">{{ __('Extra fields such as Purchase Order or Project Code are added under Custom fields.') }} <a class="link" href="{{ route('settings.custom-fields') }}">{{ __('Custom fields') }}</a></p>
            </x-card>

            @can('settings.update')<div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save invoice settings') }}</button></div>@endcan
        </form>
    @endcomponent
@endsection
