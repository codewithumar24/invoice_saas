@extends('layouts.auth')
@section('title', __('Forgot password'))
@section('heading', __('Reset your password'))
@section('intro', __('Enter your email and we will send you a link to choose a new password.'))

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-input name="email" type="email" :label="__('Email')" required autofocus autocomplete="username" />
        <button type="submit" class="btn btn-primary w-full">{{ __('Send reset link') }}</button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('login') }}" class="link">{{ __('Back to sign in') }}</a>
@endsection
