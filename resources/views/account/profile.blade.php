@extends('layouts.app')
@section('title', __('Profile'))

@section('content')
    <x-page-header :title="__('Your account')" />
    @include('account._nav')

    <form method="POST" action="{{ route('account.profile.update') }}" class="card card-pad max-w-2xl space-y-4">
        @csrf @method('PUT')
        <x-input name="name" :label="__('Name')" :value="$user->name" required />
        <x-input name="email" type="email" :label="__('Email')" :value="$user->email" required :hint="__('Changing your email requires verifying the new address.')" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-select name="timezone" :label="__('Timezone')" :options="array_combine($timezones, $timezones)" :value="$user->timezone" :placeholder="__('Use workspace timezone')" />
            <x-select name="locale" :label="__('Language')" :options="config('saas.locales')" :value="$user->locale" :placeholder="__('Use workspace language')" />
        </div>
        <x-select name="theme" :label="__('Theme')" :options="['light' => __('Light'), 'dark' => __('Dark'), 'system' => __('Match my device')]" :value="$user->theme" required />
        <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save profile') }}</button></div>
    </form>
@endsection
