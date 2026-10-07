@extends('layouts.customer')
@section('title', __('Sign in'))

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="h1">{{ __('Customer sign in') }}</h1>
        <p class="mt-1 mb-6 text-sm text-muted">{{ __('View and pay your invoices from :business.', ['business' => $portalTenant->name]) }}</p>
        <form method="POST" action="{{ route('portal.login', $portalTenant->slug) }}" class="card card-pad space-y-4">
            @csrf
            <x-input name="email" type="email" :label="__('Email')" required autofocus autocomplete="username" />
            <x-input name="password" type="password" :label="__('Password')" required autocomplete="current-password" />
            <button type="submit" class="btn btn-primary w-full">{{ __('Sign in') }}</button>
        </form>
        <p class="mt-4 text-center text-sm text-muted">{{ __('No password yet? Ask :business for a portal invitation.', ['business' => $portalTenant->name]) }}</p>
    </div>
@endsection
