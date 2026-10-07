@extends('layouts.auth')
@section('title', __('Create account'))
@section('heading', __('Create your account'))
@section('intro', __('Start with invoicing. Switch on more modules as your business grows.'))

@section('content')
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-input name="name" :label="__('Your name')" required autofocus autocomplete="name" />
        <x-input name="email" type="email" :label="__('Email')" required autocomplete="username" />
        <x-input name="business_name" :label="__('Business name')" required autocomplete="organization" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-select name="country" :label="__('Country')" :options="$countries" :placeholder="__('Choose')" required />
            <x-select name="timezone" :label="__('Timezone')" :options="array_combine($timezones, $timezones)" value="UTC" required />
        </div>
        <x-input name="password" type="password" :label="__('Password')" required autocomplete="new-password" :hint="__('At least 8 characters with upper and lower case letters and a number.')" />
        <x-input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
        <div>
            <div class="flex items-start gap-3">
                <input type="checkbox" name="terms" id="terms" value="1" class="check mt-1" required @checked(old('terms'))>
                <label for="terms" class="text-sm">{!! __('I accept the :terms and :privacy.', ['terms' => '<a class="link" href="'.e(route('legal.terms')).'" target="_blank" rel="noopener">'.e(__('Terms of Service')).'</a>', 'privacy' => '<a class="link" href="'.e(route('legal.privacy')).'" target="_blank" rel="noopener">'.e(__('Privacy Policy')).'</a>']) !!}</label>
            </div>
            @error('terms')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn-primary w-full">{{ __('Create account') }}</button>
    </form>
    <script>
        // Preselect the visitor's own timezone
        (function () {
            var select = document.getElementById('f-timezone');
            var zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (select && zone && select.value === 'UTC' && [].some.call(select.options, function (o) { return o.value === zone; })) select.value = zone;
        })();
    </script>
@endsection

@section('footer')
    {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="link">{{ __('Sign in') }}</a>
@endsection
