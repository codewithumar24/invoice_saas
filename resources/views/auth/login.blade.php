@extends('layouts.auth')
@section('title', __('Sign in'))
@section('heading', __('Sign in'))
@section('intro', __('Welcome back. Enter your details to open your workspace.'))

@section('content')
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-input name="email" type="email" :label="__('Email')" required autofocus autocomplete="username" />
        <x-input name="password" type="password" :label="__('Password')" required autocomplete="current-password" />
        <div class="flex items-center justify-between gap-4">
            <x-checkbox name="remember" :label="__('Remember me')" />
            <a href="{{ route('password.request') }}" class="link text-sm">{{ __('Forgot password?') }}</a>
        </div>
        <button type="submit" class="btn btn-primary w-full">{{ __('Sign in') }}</button>
    </form>
    <p class="mt-6 border-t border-line pt-4 text-xs text-muted">{{ __('For your security, sign-in attempts are limited and new devices trigger an email alert.') }}</p>
@endsection

@section('footer')
    {{ __('New here?') }} <a href="{{ route('register') }}" class="link">{{ __('Create a free account') }}</a>
@endsection
