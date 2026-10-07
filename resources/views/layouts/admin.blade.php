@php
    $user = auth()->user();
    $title = trim($__env->yieldContent('title')) ?: null;
    $appName = config('app.name').' '.__('Admin');
    $noindex = true;
    $nav = [
        __('Overview') => ['admin.dashboard' => __('Dashboard')],
        __('Customers') => ['admin.tenants.index' => __('Tenants'), 'admin.users' => __('Users'), 'admin.support.index' => __('Support'), 'admin.messages' => __('Contact messages')],
        __('Billing') => ['admin.plans.index' => __('Plans'), 'admin.subscriptions' => __('Subscriptions'), 'admin.payments' => __('Payments'), 'admin.coupons' => __('Coupons')],
        __('Product') => ['admin.flags' => __('Feature flags'), 'admin.announcements' => __('Announcements'), 'admin.email-templates' => __('Email templates'), 'admin.kb' => __('Knowledge base'), 'admin.blog' => __('Blog')],
        __('System') => ['admin.logs' => __('System logs'), 'admin.queue' => __('Queue monitor'), 'admin.backups' => __('Backups'), 'admin.settings' => __('Settings')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $user->theme }}">
<head>
    @include('partials.head')
</head>
<body>
    <div class="min-h-dvh lg:grid lg:grid-cols-[15rem_minmax(0,1fr)]">
        <aside class="hidden border-e border-line bg-surface lg:block">
            <div class="sticky top-0 max-h-dvh overflow-y-auto pb-6">
                <a href="{{ route('admin.dashboard') }}" class="flex h-16 items-center gap-3 px-6">
                    <span class="grid h-8 w-8 place-items-center rounded-md bg-ink text-sm font-semibold text-surface" aria-hidden="true">A</span>
                    <span class="font-semibold">{{ __('Platform admin') }}</span>
                </a>
                <nav class="px-3" aria-label="{{ __('Admin') }}">
                    @foreach ($nav as $group => $links)
                        <p class="nav-group">{{ $group }}</p>
                        @foreach ($links as $route => $label)
                            <a href="{{ route($route) }}" class="nav-link" @if (request()->routeIs(str_replace('.index', '', $route).'*')) aria-current="page" @endif>{{ $label }}</a>
                        @endforeach
                    @endforeach
                </nav>
            </div>
        </aside>

        <div class="flex min-w-0 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-surface/95 px-4 backdrop-blur sm:px-6">
                <button type="button" class="btn btn-ghost btn-icon lg:hidden" data-open="admin-drawer" aria-label="{{ __('Open menu') }}"><x-icon name="menu" /></button>
                <span class="badge badge-warn">{{ __('Platform administration') }}</span>
                <div class="flex-1"></div>
                @if ($user->tenants()->exists())<a href="{{ route('dashboard') }}" class="btn btn-secondary btn-sm">{{ __('My workspace') }}</a>@endif
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost btn-sm">{{ __('Sign out') }}</button></form>
            </header>
            <main id="main" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @include('partials.errors')
                @yield('content')
            </main>
        </div>
    </div>

    <dialog id="admin-drawer" class="drawer" aria-label="{{ __('Menu') }}">
        <div class="flex h-16 items-center justify-between px-6">
            <span class="font-semibold">{{ __('Platform admin') }}</span>
            <button type="button" class="btn btn-ghost btn-icon" data-close aria-label="{{ __('Close menu') }}"><x-icon name="x" /></button>
        </div>
        <nav class="px-3 pb-6">
            @foreach ($nav as $group => $links)
                <p class="nav-group">{{ $group }}</p>
                @foreach ($links as $route => $label)
                    <a href="{{ route($route) }}" class="nav-link">{{ $label }}</a>
                @endforeach
            @endforeach
        </nav>
    </dialog>

    @include('partials.flash')
</body>
</html>
