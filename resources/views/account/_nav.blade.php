<nav class="mb-6 flex gap-6 overflow-x-auto border-b border-line" aria-label="{{ __('Account') }}">
    <a href="{{ route('account.profile') }}" class="tab" @if (request()->routeIs('account.profile')) aria-current="page" @endif>{{ __('Profile') }}</a>
    <a href="{{ route('account.security') }}" class="tab" @if (request()->routeIs('account.security')) aria-current="page" @endif>{{ __('Password & security') }}</a>
    <a href="{{ route('account.notifications') }}" class="tab" @if (request()->routeIs('account.notifications')) aria-current="page" @endif>{{ __('Notifications') }}</a>
</nav>
