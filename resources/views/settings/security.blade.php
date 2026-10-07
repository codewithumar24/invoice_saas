@extends('layouts.app')
@section('title', __('Security settings'))

@section('content')
    @component('settings._layout')
        <form method="POST" action="{{ route('settings.security.update') }}" class="space-y-6">
            @csrf @method('PUT')
            <x-card :title="__('Two-factor authentication')">
                <x-switch name="two_factor_required" :label="__('Require two-factor authentication for every team member')" :hint="__('Members without it are sent to set it up before they can use the workspace.')" :checked="setting('two_factor_required')" />
                <p class="mt-4 text-sm text-muted">{{ __('Your own two-factor authentication, password and devices are under') }} <a class="link" href="{{ route('account.security') }}">{{ __('Password & security') }}</a>.</p>
            </x-card>

            <x-card :title="__('Customer portal')">
                @if (feature('customer_portal'))
                    <x-switch name="portal_enabled" :label="__('Customer portal is on')" :hint="__('Customers you invite can sign in to view and pay invoices, answer quotes and download documents.')" :checked="setting('portal_enabled')" />
                    <p class="mt-3 break-all text-sm text-muted">{{ __('Portal address') }}: {{ route('portal.login', tenant()->slug) }}</p>
                @else
                    <input type="hidden" name="portal_enabled" value="1">
                    <p class="text-sm text-muted">{{ __('The customer portal is not part of your plan.') }}</p>
                @endif
            </x-card>

            <x-card :title="__('How your account is protected')">
                <ul class="list-disc space-y-1 ps-5 text-sm text-muted">
                    <li>{{ __('Sign-in attempts are limited and recorded.') }}</li>
                    <li>{{ __('A sign-in from a new address triggers an email alert.') }}</li>
                    <li>{{ __('Changing a password signs out all other sessions.') }}</li>
                    <li>{{ __('Sensitive changes are written to the audit log, which cannot be edited.') }}</li>
                </ul>
            </x-card>

            @can('settings.update')<div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save security settings') }}</button></div>@endcan
        </form>
    @endcomponent
@endsection
