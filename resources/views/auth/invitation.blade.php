@extends('layouts.auth')
@section('title', __('Join :name', ['name' => $tenant->name]))
@section('heading', __('Join :name', ['name' => $tenant->name]))
@section('intro', __(':inviter invited :email as :role.', ['inviter' => $invitation->inviter?->name, 'email' => $invitation->email, 'role' => $invitation->role?->name]))

@section('content')
    <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}" class="space-y-4">
        @csrf
        @if ($existingUser)
            <p class="text-sm text-muted">{{ __('You already have an account with this email. Enter your password to join the workspace.') }}</p>
            <x-input name="password" type="password" :label="__('Password')" required autofocus autocomplete="current-password" />
        @else
            <x-input name="name" :label="__('Your name')" required autofocus autocomplete="name" />
            <x-input name="password" type="password" :label="__('Password')" required autocomplete="new-password" :hint="__('At least 8 characters with upper and lower case letters and a number.')" />
            <x-input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
        @endif
        <button type="submit" class="btn btn-primary w-full">{{ __('Accept invitation') }}</button>
    </form>
@endsection
