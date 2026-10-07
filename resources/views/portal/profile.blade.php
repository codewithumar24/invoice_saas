@extends('layouts.customer')
@section('title', __('Profile'))

@php $address = $customer->billingAddress(); @endphp

@section('content')
    <h1 class="h1 mb-6">{{ __('Your details') }}</h1>
    <form method="POST" action="{{ route('portal.profile.update', $portalTenant->slug) }}" class="max-w-2xl space-y-6">
        @csrf @method('PUT')
        <x-card :title="__('Contact')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" :label="__('Name')" :value="$customer->name" required />
                <x-input name="company" :label="__('Company')" :value="$customer->company" />
                <x-input name="phone" :label="__('Phone')" :value="$customer->phone" />
                <div><span class="label">{{ __('Email') }}</span><p class="input bg-raised">{{ $customer->email }}</p><p class="field-hint">{{ __('Ask :business to change your email.', ['business' => $portalTenant->name]) }}</p></div>
            </div>
        </x-card>
        <x-card :title="__('Billing address')">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><x-input name="address[line1]" :label="__('Address')" :value="$address?->line1" /></div>
                <x-input name="address[city]" :label="__('City')" :value="$address?->city" />
                <x-input name="address[state]" :label="__('State or region')" :value="$address?->state" />
                <x-input name="address[postal_code]" :label="__('Postal code')" :value="$address?->postal_code" />
                <x-select name="address[country]" :label="__('Country')" :options="config('countries')" :value="$address?->country" :placeholder="__('Choose')" />
            </div>
        </x-card>
        <x-card :title="__('Change password')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="password" type="password" :label="__('New password')" autocomplete="new-password" :hint="__('Leave empty to keep your current password.')" />
                <x-input name="password_confirmation" type="password" :label="__('Confirm new password')" autocomplete="new-password" />
            </div>
        </x-card>
        <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save details') }}</button></div>
    </form>
@endsection
