@extends('layouts.auth')
@section('title', __('New workspace'))
@section('heading', __('Create a workspace'))
@section('intro', __('Each workspace is a separate business with its own customers, invoices, team and settings.'))

@section('content')
    <form method="POST" action="{{ route('workspaces.store') }}" class="space-y-4">
        @csrf
        <x-input name="name" :label="__('Business name')" required autofocus />
        <x-select name="country" :label="__('Country')" :options="$countries" :placeholder="__('Choose')" :value="auth()->user()->country" />
        <x-select name="timezone" :label="__('Timezone')" :options="array_combine($timezones, $timezones)" :value="auth()->user()->timezone ?? 'UTC'" required />
        <button type="submit" class="btn btn-primary w-full">{{ __('Create workspace') }}</button>
    </form>
@endsection

@section('footer')
    @if (auth()->user()->tenants()->exists())<a href="{{ route('dashboard') }}" class="link">{{ __('Back to dashboard') }}</a>@endif
@endsection
