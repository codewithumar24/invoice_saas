@extends('layouts.app')
@section('title', __('Password & security'))

@section('content')
    <x-page-header :title="__('Your account')" />
    @include('account._nav')

    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <x-card :title="__('Change password')">
            <form method="POST" action="{{ route('account.password') }}" class="space-y-4">
                @csrf @method('PUT')
                <x-input name="current_password" type="password" :label="__('Current password')" required autocomplete="current-password" id="pw-current" />
                <x-input name="password" type="password" :label="__('New password')" required autocomplete="new-password" :hint="__('At least 8 characters with upper and lower case letters and a number.')" />
                <x-input name="password_confirmation" type="password" :label="__('Confirm new password')" required autocomplete="new-password" />
                <p class="text-sm text-muted">{{ __('Changing your password signs out your other sessions.') }}</p>
                <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Change password') }}</button></div>
            </form>
        </x-card>

        <x-card :title="__('Two-factor authentication')">
            @if ($recoveryCodes)
                <x-alert tone="warn" class="mb-4">
                    <p class="font-medium">{{ __('Save these backup codes now. They are shown only once.') }}</p>
                    <ul class="num mt-2 grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-sm">
                        @foreach ($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach
                    </ul>
                    <button type="button" class="btn btn-secondary btn-sm mt-3" data-copy="{{ implode("\n", $recoveryCodes) }}" data-copied="{{ __('Copied') }}">{{ __('Copy codes') }}</button>
                </x-alert>
            @endif

            @if ($user->hasTwoFactor())
                <p class="flex items-center gap-2 text-sm"><x-badge tone="ok">{{ __('On') }}</x-badge> {{ __('Enabled on :date', ['date' => tdate($user->two_factor_confirmed_at)]) }}</p>
                <p class="mt-2 text-sm text-muted">{{ trans_choice(':count backup code left.|:count backup codes left.', count($user->two_factor_recovery_codes ?? []), ['count' => count($user->two_factor_recovery_codes ?? [])]) }}</p>
                <div class="mt-4 space-y-4 border-t border-line pt-4">
                    <form method="POST" action="{{ route('two-factor.recovery-codes') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="min-w-48 flex-1"><x-input name="current_password" type="password" :label="__('Password')" required id="pw-codes" autocomplete="current-password" /></div>
                        <button type="submit" class="btn btn-secondary">{{ __('New backup codes') }}</button>
                    </form>
                    <form method="POST" action="{{ route('two-factor.disable') }}" class="flex flex-wrap items-end gap-3" data-confirm="{{ __('Switch off two-factor authentication? Your account will be protected by your password only.') }}">
                        @csrf @method('DELETE')
                        <div class="min-w-48 flex-1"><x-input name="current_password" type="password" :label="__('Password')" required id="pw-disable" autocomplete="current-password" /></div>
                        <button type="submit" class="btn btn-danger">{{ __('Switch off') }}</button>
                    </form>
                </div>
            @elseif ($pendingSecret)
                <ol class="list-decimal space-y-4 ps-5 text-sm">
                    <li>
                        {{ __('Scan this QR code with an authenticator app (Google Authenticator, Authy, 1Password).') }}
                        <div class="mt-3 w-44 rounded-md border border-line bg-white p-2" data-qr="{{ $otpauth }}" data-qr-label="{{ __('Two-factor setup QR code') }}"></div>
                        <p class="mt-2 text-muted">{{ __('Or enter this key by hand:') }} <code class="num break-all font-mono">{{ $pendingSecret }}</code></p>
                    </li>
                    <li>
                        {{ __('Enter the 6-digit code the app shows.') }}
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-3 flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="w-40"><x-input name="code" :label="__('Code')" inputmode="numeric" autocomplete="one-time-code" required /></div>
                            <button type="submit" class="btn btn-primary">{{ __('Confirm') }}</button>
                        </form>
                    </li>
                </ol>
            @else
                <p class="text-sm text-muted">{{ __('Add a second step when you sign in: a code from an app on your phone. Backup codes are provided in case you lose the phone.') }}</p>
                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-48 flex-1"><x-input name="current_password" type="password" :label="__('Confirm your password')" required id="pw-enable" autocomplete="current-password" /></div>
                    <button type="submit" class="btn btn-primary">{{ __('Set up') }}</button>
                </form>
            @endif
        </x-card>

        <x-card :title="__('Devices and sessions')" class="lg:col-span-2" :padded="false">
            @if ($sessions === null)
                <p class="card-pad text-sm text-muted">{{ __('The device list is available when sessions are stored in the database (SESSION_DRIVER=database). You can still sign out everywhere below.') }}</p>
            @else
                <table class="table table-stack">
                    <thead><tr><th>{{ __('Device') }}</th><th>{{ __('IP address') }}</th><th>{{ __('Last active') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            <tr>
                                <td data-label="{{ __('Device') }}"><span class="line-clamp-1 max-w-md">{{ $session->agent ?: __('Unknown device') }}</span></td>
                                <td data-label="{{ __('IP address') }}" class="num">{{ $session->ip }}</td>
                                <td data-label="{{ __('Last active') }}">{{ $session->current ? __('This device') : $session->last_active->diffForHumans() }}</td>
                                <td class="text-end">
                                    @unless ($session->current)
                                        <x-action :action="route('account.sessions.destroy', $session->id)" method="DELETE" :confirm="__('Sign out this device?')">{{ __('Sign out') }}</x-action>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <form method="POST" action="{{ route('account.sessions.logout-others') }}" class="flex flex-wrap items-end gap-3 border-t border-line p-4 sm:p-6">
                @csrf
                <div class="min-w-48 flex-1 sm:max-w-xs"><x-input name="current_password" type="password" :label="__('Password')" required id="pw-sessions" autocomplete="current-password" /></div>
                <button type="submit" class="btn btn-secondary">{{ __('Sign out all other sessions') }}</button>
            </form>
        </x-card>
    </div>
@endsection
