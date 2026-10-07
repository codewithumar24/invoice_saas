@extends('layouts.admin')
@section('title', __('Platform settings'))

@section('content')
    <x-page-header :title="__('Platform settings')" />

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')

        <x-card :title="__('General')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="app_name" :label="__('App name')" :value="platform_setting('app_name', config('app.name'))" required :hint="__('For display in emails and settings. The name in the page header comes from APP_NAME in the environment file.')" />
                <x-input name="support_email" type="email" :label="__('Support email')" :value="platform_setting('support_email')" />
                <x-select name="default_currency" :label="__('Default currency')" :options="collect(config('currencies'))->map(fn ($c, $code) => $code.' — '.$c['name'])->all()" :value="platform_setting('default_currency', 'USD')" required />
            </div>
        </x-card>

        <x-card :title="__('Registration and plans')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="default_plan" :label="__('Plan after the trial / free plan')" :options="$plans->pluck('name', 'slug')->all()" :value="platform_setting('default_plan', 'free')" required />
                <x-input name="trial_days" type="number" min="0" max="365" :label="__('Trial days for new workspaces')" :value="platform_setting('trial_days', 14)" required :hint="__('0 starts new workspaces directly on the default plan.')" />
            </div>
            <div class="mt-4"><x-switch name="registration_enabled" :label="__('Registration is open')" :checked="platform_setting('registration_enabled', true)" /></div>
        </x-card>

        <x-card :title="__('Billing')">
            <x-select name="billing_gateway" :label="__('How are subscriptions paid?')" :options="['manual' => __('Manual: plans change at once and an invoice is recorded for your team to collect'), 'stripe' => __('Stripe: card payment through Stripe Checkout')]" :value="platform_setting('billing_gateway', 'manual')" required />
            <p class="mt-3 text-sm text-muted">{{ __('Stripe needs STRIPE_SECRET and STRIPE_WEBHOOK_SECRET in the environment file and this webhook URL in your Stripe account:') }} <code class="break-all">{{ route('webhooks.billing') }}</code></p>
            <p class="mt-2 text-sm">{{ __('Stripe keys configured') }}: <x-badge :tone="config('services.stripe.secret') ? 'ok' : 'neutral'">{{ config('services.stripe.secret') ? __('Yes') : __('No') }}</x-badge></p>
        </x-card>

        <x-card :title="__('Infrastructure')">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-muted">{{ __('Email provider') }}</dt><dd>{{ config('mail.default') }} ({{ config('mail.from.address') }})</dd></div>
                <div><dt class="text-muted">{{ __('Storage provider') }}</dt><dd>{{ config('filesystems.default') }}</dd></div>
                <div><dt class="text-muted">{{ __('Queue') }}</dt><dd>{{ config('queue.default') }}</dd></div>
                <div><dt class="text-muted">{{ __('Cache') }}</dt><dd>{{ config('cache.default') }}</dd></div>
            </dl>
            <p class="caption mt-3">{{ __('These are set in the environment file (.env) because they hold credentials, which must stay out of the database and out of source control.') }}</p>
        </x-card>

        <x-card :title="__('Maintenance mode')">
            <x-switch name="maintenance_mode" :label="__('Maintenance mode is on')" :hint="__('Users see a maintenance page. Platform administrators can still sign in and use the admin area.')" :checked="platform_setting('maintenance_mode', false)" />
            <div class="mt-4"><x-input name="maintenance_message" :label="__('Message for users')" :value="platform_setting('maintenance_message')" /></div>
        </x-card>

        <x-card :title="__('Admin security')">
            <x-switch name="admin_require_2fa" :label="__('Require two-factor authentication for platform administrators')" :checked="platform_setting('admin_require_2fa', false)" />
            <div class="mt-4"><x-input name="admin_ip_allowlist" :label="__('Allow the admin area only from these IP addresses')" :value="platform_setting('admin_ip_allowlist')" :hint="__('Comma separated. Empty allows any address. Your current address is :ip.', ['ip' => request()->ip()])" /></div>
        </x-card>

        <x-card :title="__('Analytics')">
            <x-input name="analytics_snippet_id" :label="__('Google Analytics measurement id')" :value="platform_setting('analytics_snippet_id')" placeholder="G-XXXXXXX" :hint="__('Loaded on the public website only after a visitor accepts analytics cookies.')" />
        </x-card>

        <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save platform settings') }}</button></div>
    </form>
@endsection
