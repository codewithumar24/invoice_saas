@php
    $user = auth()->user();
    $tenant = tenant();
    $branded = $tenant && feature('custom_branding');
    $whiteLabel = $tenant && feature('white_label') && setting('white_label');
    $appName = $whiteLabel ? $tenant->name : config('app.name');
    $logoUrl = $branded && $tenant->logo_path ? app(\App\Services\FileStorageService::class)->publicUrl($tenant->logo_path) : null;
    $faviconUrl = $branded && $tenant->favicon_path ? app(\App\Services\FileStorageService::class)->publicUrl($tenant->favicon_path) : null;
    $title = trim($__env->yieldContent('title')) ?: null;
    $noindex = true;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}" data-theme="{{ $user->theme }}">
<head>
    @include('partials.head')
    @include('partials.brand-style', ['brandColor' => $branded ? $tenant->primary_color : null])
</head>
<body>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2 focus:shadow-lg">{{ __('Skip to content') }}</a>

    @if (session('impersonator_id'))
        <div class="no-print flex flex-wrap items-center justify-center gap-3 bg-warn-soft px-4 py-2 text-sm text-warn" role="status">
            <span>{{ __('You are signed in as :name for support. Everything you do is recorded.', ['name' => $user->name]) }}</span>
            <form method="POST" action="{{ route('impersonation.stop') }}">@csrf<button class="btn btn-secondary btn-sm">{{ __('End session') }}</button></form>
        </div>
    @endif

    <div class="min-h-dvh lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]">
        {{-- Desktop sidebar --}}
        <aside class="no-print hidden border-e border-line bg-surface lg:block">
            <div class="sticky top-0 flex max-h-dvh flex-col overflow-y-auto">
                <a href="{{ route('dashboard') }}" class="flex h-16 shrink-0 items-center gap-3 px-6">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="h-8 max-w-40 object-contain">
                    @else
                        <span class="grid h-8 w-8 place-items-center rounded-md bg-brand text-sm font-semibold text-on-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr($appName, 0, 1)) }}</span>
                        <span class="truncate font-semibold">{{ $appName }}</span>
                    @endif
                </a>
                @include('partials.sidebar')
            </div>
        </aside>

        <div class="flex min-w-0 flex-col">
            <header class="no-print sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-line bg-surface/95 px-4 backdrop-blur sm:px-6">
                <button type="button" class="btn btn-ghost btn-icon lg:hidden" data-open="nav-drawer" aria-label="{{ __('Open menu') }}"><x-icon name="menu" /></button>

                {{-- Workspace switcher --}}
                <details class="menu min-w-0">
                    <summary class="btn btn-ghost max-w-[11rem] sm:max-w-xs" aria-haspopup="menu">
                        <span class="truncate">{{ $tenant->name }}</span><x-icon name="chevron-down" size="14" />
                    </summary>
                    <div class="menu-panel start-0 end-auto w-64" role="menu">
                        <p class="px-3 py-1 text-xs text-muted">{{ __('Workspaces') }}</p>
                        @foreach ($workspaces ?? [] as $workspace)
                            <form method="POST" action="{{ route('workspaces.switch', $workspace) }}">
                                @csrf
                                <button class="menu-item" role="menuitem" @if ($workspace->id === $tenant->id) aria-current="true" @endif>
                                    <span class="flex-1 truncate">{{ $workspace->name }}</span>
                                    @if ($workspace->id === $tenant->id)<x-icon name="check" size="14" class="text-brand" />@endif
                                </button>
                            </form>
                        @endforeach
                        <a href="{{ route('workspaces.create') }}" class="menu-item border-t border-line" role="menuitem"><x-icon name="plus" size="14" />{{ __('New workspace') }}</a>
                    </div>
                </details>

                <div class="flex-1"></div>

                <button type="button" class="btn btn-secondary hidden text-muted sm:inline-flex" data-open-palette>
                    <x-icon name="search" size="16" /><span>{{ __('Search') }}</span><kbd class="rounded border border-line px-1 text-[0.6875rem]">Ctrl K</kbd>
                </button>
                <button type="button" class="btn btn-ghost btn-icon sm:hidden" data-open-palette aria-label="{{ __('Search') }}"><x-icon name="search" /></button>

                {{-- Quick create --}}
                <details class="menu">
                    <summary class="btn btn-primary" aria-haspopup="menu"><x-icon name="plus" size="16" /><span class="hidden sm:inline">{{ __('Create') }}</span></summary>
                    <div class="menu-panel" role="menu">
                        @if (feature('invoicing') && $user->hasPermission('invoices.create'))<a class="menu-item" role="menuitem" href="{{ route('invoices.create') }}">{{ __('Invoice') }}</a>@endif
                        @if (feature('quotes') && $user->hasPermission('quotes.create'))<a class="menu-item" role="menuitem" href="{{ route('quotes.create') }}">{{ __('Quote') }}</a>@endif
                        @if ($user->hasPermission('customers.create'))<a class="menu-item" role="menuitem" href="{{ route('customers.create') }}">{{ __('Customer') }}</a>@endif
                        @if (feature('expenses') && $user->hasPermission('expenses.create'))<a class="menu-item" role="menuitem" href="{{ route('expenses.create') }}">{{ __('Expense') }}</a>@endif
                        @if (feature('products') && $user->hasPermission('products.create'))<a class="menu-item" role="menuitem" href="{{ route('products.create') }}">{{ __('Product or service') }}</a>@endif
                        @if ($user->hasPermission('payments.create') && feature('invoicing'))<a class="menu-item" role="menuitem" href="{{ route('invoices.index', ['payment' => 'unpaid']) }}">{{ __('Payment') }}</a>@endif
                        @if (feature('projects') && $user->hasPermission('projects.manage'))<a class="menu-item" role="menuitem" href="{{ route('projects.create') }}">{{ __('Project') }}</a>@endif
                    </div>
                </details>

                {{-- Notification centre --}}
                <details class="menu">
                    <summary class="btn btn-ghost btn-icon relative" aria-haspopup="menu" aria-label="{{ __('Notifications') }}">
                        <x-icon name="bell" />
                        @if (($unreadNotifications ?? collect())->count())
                            <span class="absolute end-1.5 top-1.5 h-2 w-2 rounded-full bg-danger"></span>
                        @endif
                    </summary>
                    <div class="menu-panel w-80 max-w-[calc(100vw-2rem)]" role="menu">
                        <div class="flex items-center justify-between px-3 py-2">
                            <p class="text-sm font-semibold">{{ __('Notifications') }}</p>
                            @if (($unreadNotifications ?? collect())->count())
                                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="text-xs text-brand hover:underline">{{ __('Mark all read') }}</button></form>
                            @endif
                        </div>
                        @forelse ($unreadNotifications ?? [] as $notification)
                            <a href="{{ route('notifications.read', $notification->id) }}" class="menu-item items-start border-t border-line" role="menuitem">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand"></span>
                                <span class="min-w-0">
                                    <span class="block text-sm">{{ $notification->data['title'] ?? '' }}</span>
                                    <span class="caption">{{ $notification->created_at->diffForHumans() }}</span>
                                </span>
                            </a>
                        @empty
                            <p class="border-t border-line px-3 py-6 text-center text-sm text-muted">{{ __('You are all caught up.') }}</p>
                        @endforelse
                        <a href="{{ route('notifications.index') }}" class="menu-item justify-center border-t border-line text-brand" role="menuitem">{{ __('View all notifications') }}</a>
                    </div>
                </details>

                {{-- User menu --}}
                <details class="menu">
                    <summary class="grid h-9 w-9 cursor-pointer place-items-center rounded-full bg-brand-soft text-sm font-semibold text-brand" aria-haspopup="menu" aria-label="{{ __('Account menu') }}">{{ $user->initials() }}</summary>
                    <div class="menu-panel w-60" role="menu">
                        <div class="border-b border-line px-3 py-2">
                            <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                            <p class="caption truncate">{{ $user->email }}</p>
                        </div>
                        <a class="menu-item" role="menuitem" href="{{ route('account.profile') }}">{{ __('Profile') }}</a>
                        <a class="menu-item" role="menuitem" href="{{ route('account.security') }}">{{ __('Password & security') }}</a>
                        <a class="menu-item" role="menuitem" href="{{ route('account.notifications') }}">{{ __('Notification preferences') }}</a>
                        <div class="flex items-center gap-1 border-t border-line px-3 py-2" role="group" aria-label="{{ __('Theme') }}">
                            <span class="flex-1 text-sm text-muted">{{ __('Theme') }}</span>
                            <button type="button" class="btn btn-ghost btn-icon" data-set-theme="light" aria-label="{{ __('Light') }}"><x-icon name="sun" size="16" /></button>
                            <button type="button" class="btn btn-ghost btn-icon" data-set-theme="dark" aria-label="{{ __('Dark') }}"><x-icon name="moon" size="16" /></button>
                        </div>
                        @if ($user->is_super_admin)
                            <a class="menu-item border-t border-line" role="menuitem" href="{{ route('admin.dashboard') }}">{{ __('Platform admin') }}</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-line">
                            @csrf
                            <button class="menu-item" role="menuitem"><x-icon name="logout" size="16" />{{ __('Sign out') }}</button>
                        </form>
                    </div>
                </details>
            </header>

            @foreach ($announcements ?? [] as $announcement)
                <div class="no-print flex items-start gap-3 border-b border-line bg-info-soft px-4 py-2.5 text-sm text-info sm:px-6" role="status">
                    <p class="flex-1"><span class="font-medium">{{ $announcement->title }}</span> {{ $announcement->body }}</p>
                    <form method="POST" action="{{ route('announcements.dismiss', $announcement) }}">@csrf<button aria-label="{{ __('Dismiss') }}" class="hover:opacity-70"><x-icon name="x" size="16" /></button></form>
                </div>
            @endforeach

            @if ($tenant->deletion_requested_at)
                <div class="no-print border-b border-line bg-danger-soft px-4 py-2.5 text-sm text-danger sm:px-6" role="status">
                    {{ __('This workspace is scheduled for deletion on :date.', ['date' => tdate($tenant->deletion_requested_at->copy()->addDays(30))]) }}
                    <a class="underline" href="{{ route('settings.data') }}">{{ __('Review') }}</a>
                </div>
            @endif

            <main id="main" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                @include('partials.errors')
                @yield('content')
            </main>
        </div>
    </div>

    {{-- Mobile navigation drawer --}}
    <dialog id="nav-drawer" class="drawer" aria-label="{{ __('Menu') }}">
        <div class="flex h-16 items-center justify-between px-6">
            <span class="font-semibold">{{ $appName }}</span>
            <button type="button" class="btn btn-ghost btn-icon" data-close aria-label="{{ __('Close menu') }}"><x-icon name="x" /></button>
        </div>
        @include('partials.sidebar')
    </dialog>

    {{-- Command palette (Ctrl/Cmd + K) --}}
    <dialog id="command-palette" class="modal mt-[12dvh]" aria-label="{{ __('Search') }}" data-url="{{ route('search') }}" data-empty="{{ __('Nothing found. Try a name, number or email.') }}" data-commands-label="{{ __('Go to') }}">
        <div class="flex items-center gap-3 border-b border-line px-4">
            <x-icon name="search" class="text-muted" />
            <input type="search" class="w-full bg-transparent py-4 text-base outline-none placeholder:text-muted" placeholder="{{ __('Search customers, invoices, products… or type a command') }}" aria-label="{{ __('Search') }}" autocomplete="off">
        </div>
        <div class="max-h-[50dvh] overflow-y-auto pb-2" data-results role="listbox"></div>
        @php
            $commands = collect([
                ['invoices.create', __('Create invoice'), 'invoices.create', 'invoicing'], ['quotes.create', __('Create quote'), 'quotes.create', 'quotes'],
                ['customers.create', __('Add customer'), 'customers.create', null], ['expenses.create', __('Add expense'), 'expenses.create', 'expenses'],
                ['products.create', __('Add product or service'), 'products.create', 'products'], ['customers.index', __('Find customer'), 'customers.view', null],
                ['reports.index', __('Open reports'), 'reports.view', 'reports'], ['health.index', __('Open Business Health'), 'health.view', 'business_health'],
                ['settings.business', __('Open settings'), 'settings.view', null],
            ])->filter(fn ($c) => $user->hasPermission($c[2]) && ($c[3] === null || feature($c[3])))->map(fn ($c) => ['title' => $c[1], 'url' => route($c[0])])->values();
        @endphp
        <script type="application/json" data-commands>@json($commands)</script>
    </dialog>

    @include('partials.flash')
    @stack('scripts')
</body>
</html>
