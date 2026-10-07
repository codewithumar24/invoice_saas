@extends('layouts.auth')
@section('title', __('Verify your email'))
@section('heading', __('Check your inbox'))
@section('intro', __('We sent a verification link to :email. Open it to finish setting up your account.', ['email' => auth()->user()->email]))

@section('content')
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary w-full">{{ __('Send the link again') }}</button>
    </form>
    <p class="mt-4 text-sm text-muted">{{ __('The link expires after 60 minutes. Check your spam folder if it does not arrive.') }}</p>
@endsection

@section('footer')
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="link">{{ __('Sign out') }}</button></form>
@endsection
