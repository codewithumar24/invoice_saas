@extends('layouts.app')
@section('title', $customer->exists ? __('Edit customer') : __('Add customer'))

@php $address = $customer->exists ? $customer->billingAddress() : null; @endphp

@section('content')
    <x-page-header :title="$customer->exists ? __('Edit :name', ['name' => $customer->name]) : __('Add customer')" :back="$customer->exists ? route('customers.show', $customer) : route('customers.index')" />

    <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($customer->exists) @method('PUT') @endif

        <x-card :title="__('Contact')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" :label="__('Name')" :value="$customer->name" required autofocus />
                <x-input name="company" :label="__('Company')" :value="$customer->company" />
                <x-input name="email" type="email" :label="__('Email')" :value="$customer->email" :hint="__('Invoices, quotes and reminders are sent here.')" />
                <x-input name="phone" :label="__('Phone')" :value="$customer->phone" />
                <x-input name="website" type="url" :label="__('Website')" :value="$customer->website" placeholder="https://" />
                <x-input name="tax_number" :label="__('Tax number')" :value="$customer->tax_number" />
            </div>
        </x-card>

        <x-card :title="__('Billing address')">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><x-input name="address[line1]" :label="__('Address')" :value="$address?->line1" /></div>
                <div class="sm:col-span-2"><x-input name="address[line2]" :label="__('Address line 2')" :value="$address?->line2" /></div>
                <x-input name="address[city]" :label="__('City')" :value="$address?->city" />
                <x-input name="address[state]" :label="__('State or region')" :value="$address?->state" />
                <x-input name="address[postal_code]" :label="__('Postal code')" :value="$address?->postal_code" />
                <x-select name="address[country]" :label="__('Country')" :options="config('countries')" :value="$address?->country" :placeholder="__('Choose')" />
            </div>
        </x-card>

        <x-card :title="__('Billing defaults')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="currency" :label="__('Currency')" :options="collect(config('currencies'))->map(fn ($c, $code) => $code.' — '.$c['name'])->all()" :value="$customer->currency" :placeholder="__('Workspace currency (:code)', ['code' => tenant()->currency])" />
                <x-input name="payment_terms_days" type="number" min="0" max="365" :label="__('Payment terms (days)')" :value="$customer->payment_terms_days" :hint="__('New invoices for this customer are due after this many days.')" />
                <div class="sm:col-span-2"><x-input name="tags" :label="__('Tags')" :value="$customer->exists ? $customer->tags->pluck('name')->implode(', ') : null" :hint="__('Separate tags with commas, for example: VIP, Enterprise')" /></div>
                <div class="sm:col-span-2"><x-textarea name="notes" :label="__('Internal notes')" :value="$customer->notes" :hint="__('Only your team sees these.')" /></div>
            </div>
        </x-card>

        @if ($customFields->count())
            <x-card :title="__('More details')">
                @include('partials.custom-fields', ['model' => $customer])
            </x-card>
        @endif

        <div class="flex justify-end gap-2">
            <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ $customer->exists ? __('Save customer') : __('Add customer') }}</button>
        </div>
    </form>
@endsection
