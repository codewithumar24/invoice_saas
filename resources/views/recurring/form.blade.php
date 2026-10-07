@extends('layouts.app')
@section('title', $rule->exists ? __('Edit recurring invoice') : __('New recurring invoice'))

@section('content')
    <x-page-header :title="$rule->exists ? __('Edit recurring invoice') : __('New recurring invoice')" :back="route('recurring.index')" />

    <form method="POST" action="{{ $rule->exists ? route('recurring.update', $rule) : route('recurring.store') }}" class="space-y-6">
        @csrf
        @if ($rule->exists) @method('PUT') @endif

        <x-card :title="__('Customer and schedule')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                    <label for="f-customer_id" class="label">{{ __('Customer') }}<span class="text-danger" aria-hidden="true"> *</span></label>
                    <select name="customer_id" id="f-customer_id" class="input @error('customer_id') input-error @enderror" required>
                        <option value="">{{ __('Choose a customer') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" data-currency="{{ $customer->currency }}" @selected((string) old('customer_id', $rule->customer_id) === (string) $customer->id)>{{ $customer->displayName() }}</option>
                        @endforeach
                    </select>
                    @error('customer_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2"><x-input name="title" :label="__('Title')" :value="$rule->title" :hint="__('For your own reference, for example: Monthly retainer')" /></div>
                <x-select name="frequency" :label="__('Repeats')" :options="['weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'quarterly' => __('Quarterly'), 'yearly' => __('Yearly'), 'custom' => __('Custom interval')]" :value="$rule->frequency" required />
                <div data-show-when="frequency=custom"><x-input name="interval_days" type="number" min="1" :label="__('Every how many days')" :value="$rule->interval_days" /></div>
                <x-input name="start_date" type="date" :label="__('First invoice on')" :value="$rule->start_date" required />
                <x-input name="end_date" type="date" :label="__('Stop after (optional)')" :value="$rule->end_date" />
                <x-input name="due_days" type="number" min="0" max="365" :label="__('Due after (days)')" :value="$rule->due_days" required />
                <x-select name="currency" :label="__('Currency')" :options="$currencies" :value="$rule->currency" required />
            </div>
            <div class="mt-4"><x-switch name="auto_send" :label="__('Email each invoice to the customer automatically')" :checked="$rule->auto_send" /></div>
        </x-card>

        <x-card :title="__('Items')">
            @include('partials.line-items', ['currency' => $rule->currency ?: tenant()->currency])
            <dl class="ms-auto mt-4 max-w-xs space-y-1.5 border-t border-line pt-4 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Subtotal') }}</dt><dd class="num" data-total="subtotal">0</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ setting('tax_label') }}</dt><dd class="num" data-total="tax">0</dd></div>
                <div class="flex justify-between gap-4 text-base font-semibold"><dt>{{ __('Each invoice') }}</dt><dd class="num" data-total="grand">0</dd></div>
            </dl>
        </x-card>

        <x-card :title="__('Notes')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-textarea name="notes" :label="__('Notes for the customer')" :value="$rule->notes" rows="2" />
                <x-textarea name="terms" :label="__('Terms')" :value="$rule->terms" rows="2" />
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('recurring.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ $rule->exists ? __('Save recurring invoice') : __('Create recurring invoice') }}</button>
        </div>
    </form>
@endsection
