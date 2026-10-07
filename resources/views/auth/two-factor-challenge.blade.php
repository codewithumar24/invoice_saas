@extends('layouts.auth')
@section('title', __('Two-factor authentication'))
@section('heading', __('Enter your code'))
@section('intro', __('Open your authenticator app and enter the 6-digit code for this account.'))

@section('content')
    <form method="POST" action="{{ route('two-factor.challenge') }}" class="space-y-4">
        @csrf
        <x-input name="code" :label="__('Authentication code')" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus />
        <details class="text-sm">
            <summary class="link cursor-pointer">{{ __('Use a backup code instead') }}</summary>
            <div class="mt-3"><x-input name="recovery_code" :label="__('Backup code')" autocomplete="off" /></div>
        </details>
        <button type="submit" class="btn btn-primary w-full">{{ __('Verify') }}</button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('login') }}" class="link">{{ __('Back to sign in') }}</a>
@endsection
