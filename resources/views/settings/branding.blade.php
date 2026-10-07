@extends('layouts.app')
@section('title', __('Branding'))

@php $files = app(\App\Services\FileStorageService::class); @endphp

@section('content')
    @component('settings._layout', ['tenant' => $tenant, 'files' => $files])
        @unless (feature('custom_branding'))
            <x-alert tone="info">{{ __('Your logo appears on invoices on every plan. Using your logo and colour inside the app and customer portal is part of custom branding.') }} @can('billing.manage')<a class="underline" href="{{ route('settings.billing') }}">{{ __('See plans') }}</a>@endcan</x-alert>
        @endunless

        <form method="POST" action="{{ route('settings.branding.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf @method('PUT')
            <x-card :title="__('Logo and colour')">
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="f-logo" class="label">{{ __('Logo') }}</label>
                        @if ($tenant->logo_path)
                            <img src="{{ $files->publicUrl($tenant->logo_path) }}" alt="{{ __('Current logo') }}" class="mb-3 h-16 max-w-48 rounded border border-line bg-white object-contain p-1">
                        @endif
                        <input type="file" name="logo" id="f-logo" accept="image/png,image/jpeg,image/webp" class="input">
                        <p class="field-hint">{{ __('PNG, JPG or WebP, up to 2 MB. Shown on invoices, quotes, emails and the customer portal.') }}</p>
                        @error('logo')<p class="field-error">{{ $message }}</p>@enderror
                        @if ($tenant->logo_path)<div class="mt-2"><x-checkbox name="remove_logo" :label="__('Remove the logo')" /></div>@endif
                    </div>
                    <div>
                        <label for="f-favicon" class="label">{{ __('Favicon') }}</label>
                        <input type="file" name="favicon" id="f-favicon" accept="image/png,image/x-icon" class="input">
                        <p class="field-hint">{{ __('Square PNG, up to 512 KB.') }}</p>
                        @error('favicon')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="f-primary_color" class="label">{{ __('Primary colour') }}</label>
                        <input type="color" name="primary_color" id="f-primary_color" value="{{ old('primary_color', $tenant->primary_color) }}" class="h-10 w-20 cursor-pointer rounded-md border border-line bg-surface p-1">
                        @error('primary_color')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-card>

            <x-card :title="__('Email branding')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="email_from_name" :label="__('Sender name')" :value="setting('email_from_name')" :hint="__('Shown as the sender of invoices and reminders. Empty uses your business name.')" />
                    <x-input name="email_footer" :label="__('Email footer')" :value="setting('email_footer')" />
                </div>
            </x-card>

            <x-card :title="__('White label')">
                @if (feature('white_label'))
                    <x-switch name="white_label" :label="__('Remove :app branding', ['app' => config('app.name')])" :hint="__('Hides the product name in the app, customer portal, public invoice pages and emails.')" :checked="setting('white_label')" />
                @else
                    <p class="text-sm text-muted">{{ __('Removing the product branding is available on plans with white label.') }}</p>
                @endif

                <div class="mt-4 max-w-md">
                    @if (feature('custom_domain'))
                        <x-input name="custom_domain" :label="__('Custom domain')" :value="$tenant->custom_domain" placeholder="app.yourdomain.com" :hint="__('Point a CNAME record for this host to :host, then ask support to enable its SSL certificate.', ['host' => parse_url(config('app.url'), PHP_URL_HOST)])" />
                    @else
                        <p class="text-sm text-muted">{{ __('Using your own domain (app.yourdomain.com) is available on plans with custom domains.') }}</p>
                    @endif
                </div>
            </x-card>

            @can('settings.update')<div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save branding') }}</button></div>@endcan
        </form>
    @endcomponent
@endsection
