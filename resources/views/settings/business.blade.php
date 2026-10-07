@extends('layouts.app')
@section('title', __('Business settings'))

@section('content')
    @component('settings._layout')
        <form method="POST" action="{{ route('settings.business.update') }}" class="space-y-6">
            @csrf @method('PUT')
            <x-card :title="__('General')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="name" :label="__('Business name')" :value="$tenant->name" required />
                    <x-select name="business_type" :label="__('Business type')" :options="collect(config('saas.business_types'))->map(fn ($t) => __($t['label']))->all()" :value="$tenant->business_type" :placeholder="__('Choose')" />
                    <x-input name="email" type="email" :label="__('Email')" :value="$tenant->email" />
                    <x-input name="phone" :label="__('Phone')" :value="$tenant->phone" />
                    <x-input name="website" type="url" :label="__('Website')" :value="$tenant->website" placeholder="https://" />
                    <x-input name="tax_number" :label="__('Tax registration number')" :value="$tenant->tax_number" />
                </div>
            </x-card>
            <x-card :title="__('Address')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><x-input name="address" :label="__('Address')" :value="$tenant->address" /></div>
                    <x-input name="city" :label="__('City')" :value="$tenant->city" />
                    <x-input name="state" :label="__('State or region')" :value="$tenant->state" />
                    <x-input name="postal_code" :label="__('Postal code')" :value="$tenant->postal_code" />
                    <x-select name="country" :label="__('Country')" :options="$countries" :value="$tenant->country" :placeholder="__('Choose')" />
                </div>
            </x-card>
            <x-card :title="__('Region')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select name="timezone" :label="__('Timezone')" :options="array_combine($timezones, $timezones)" :value="$tenant->timezone" required :hint="__('Due dates, reminders and reports follow this timezone, not the server\'s.')" />
                    <x-select name="locale" :label="__('Language')" :options="config('saas.locales')" :value="$tenant->locale" required :hint="__('Used for emails to customers and as the default for your team.')" />
                </div>
            </x-card>
            @can('settings.update')<div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save business details') }}</button></div>@endcan
        </form>
    @endcomponent
@endsection
