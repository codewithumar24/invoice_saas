@extends('layouts.customer')
@section('title', __('Set your password'))

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="h1">{{ __('Set your password') }}</h1>
        <p class="mt-1 mb-6 text-sm text-muted">{{ __('Choose a password for your customer portal at :business.', ['business' => $portalTenant->name]) }}</p>
        <form method="POST" action="{{ route('portal.password', [$portalTenant->slug, $token]) }}" class="card card-pad space-y-4">
            @csrf
            <x-input name="password" type="password" :label="__('Password')" required autofocus autocomplete="new-password" :hint="__('At least 8 characters with upper and lower case letters and a number.')" />
            <x-input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
            <button type="submit" class="btn btn-primary w-full">{{ __('Save and sign in') }}</button>
        </form>
    </div>
@endsection
