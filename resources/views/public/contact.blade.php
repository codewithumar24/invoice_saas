@extends('layouts.guest')
@section('title', __('Contact'))

@section('content')
    <div class="mx-auto max-w-xl px-4 py-16 sm:px-6">
        <h1 class="font-serif text-4xl font-semibold tracking-tight">{{ __('Contact') }}</h1>
        <p class="mt-4 text-muted">{{ __('Questions about the product, pricing or your account? Write to us and we reply by email.') }}@if (platform_setting('support_email')) {{ __('You can also email :email.', ['email' => platform_setting('support_email')]) }}@endif</p>

        <form method="POST" action="{{ route('contact.send') }}" class="mt-8 space-y-4">
            @csrf
            <x-input name="name" :label="__('Name')" required autocomplete="name" />
            <x-input name="email" type="email" :label="__('Email')" required autocomplete="email" />
            <x-textarea name="message" :label="__('Message')" rows="6" required />
            {{-- Honeypot: hidden from people, tempting for bots --}}
            <div class="hidden" aria-hidden="true"><label for="website">Website</label><input type="text" name="website" id="website" tabindex="-1" autocomplete="off"></div>
            <button type="submit" class="btn btn-primary">{{ __('Send message') }}</button>
        </form>
    </div>
@endsection
