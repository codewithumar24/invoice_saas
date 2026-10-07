@extends('layouts.auth')
@section('title', __('Choose a new password'))
@section('heading', __('Choose a new password'))

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input name="email" type="email" :label="__('Email')" :value="$email" required autocomplete="username" />
        <x-input name="password" type="password" :label="__('New password')" required autofocus autocomplete="new-password" :hint="__('At least 8 characters with upper and lower case letters and a number.')" />
        <x-input name="password_confirmation" type="password" :label="__('Confirm new password')" required autocomplete="new-password" />
        <button type="submit" class="btn btn-primary w-full">{{ __('Reset password') }}</button>
    </form>
@endsection
