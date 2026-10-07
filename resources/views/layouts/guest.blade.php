@php
    $title = trim($__env->yieldContent('title')) ?: null;
    $description = trim($__env->yieldContent('description')) ?: __('Invoice customers, track payments, expenses and stock, and understand what is happening in your business from one workspace.');
    $canonical = url()->current();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.head')
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $title ?? config('app.name') }}">
    <meta name="twitter:description" content="{{ $description }}">
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => config('app.name'), 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'url' => url('/'), 'description' => $description], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @stack('head')
</head>
<body class="bg-surface">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2 focus:shadow-lg">{{ __('Skip to content') }}</a>

    <header class="sticky top-0 z-20 border-b border-line bg-surface/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="grid h-8 w-8 place-items-center rounded-md bg-brand text-sm font-semibold text-on-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr(config('app.name'), 0, 1)) }}</span>
                <span class="font-semibold">{{ config('app.name') }}</span>
            </a>
            <nav class="hidden flex-1 items-center gap-6 text-sm md:flex" aria-label="{{ __('Main') }}">
                <a href="{{ route('features') }}" class="text-muted hover:text-ink" @if (request()->routeIs('features')) aria-current="page" @endif>{{ __('Features') }}</a>
                <a href="{{ route('solutions') }}" class="text-muted hover:text-ink" @if (request()->routeIs('solutions')) aria-current="page" @endif>{{ __('Solutions') }}</a>
                <a href="{{ route('pricing') }}" class="text-muted hover:text-ink" @if (request()->routeIs('pricing')) aria-current="page" @endif>{{ __('Pricing') }}</a>
                <a href="{{ route('help') }}" class="text-muted hover:text-ink" @if (request()->routeIs('help*')) aria-current="page" @endif>{{ __('Help') }}</a>
                <a href="{{ route('blog') }}" class="text-muted hover:text-ink" @if (request()->routeIs('blog*')) aria-current="page" @endif>{{ __('Blog') }}</a>
            </nav>
            <div class="ms-auto flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('Open dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost hidden sm:inline-flex">{{ __('Sign in') }}</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">{{ __('Start free') }}</a>
                @endauth
                <button type="button" class="btn btn-ghost btn-icon md:hidden" data-open="site-menu" aria-label="{{ __('Open menu') }}"><x-icon name="menu" /></button>
            </div>
        </div>
    </header>

    <dialog id="site-menu" class="drawer" aria-label="{{ __('Menu') }}">
        <div class="flex h-16 items-center justify-between px-6">
            <span class="font-semibold">{{ config('app.name') }}</span>
            <button type="button" class="btn btn-ghost btn-icon" data-close aria-label="{{ __('Close menu') }}"><x-icon name="x" /></button>
        </div>
        <nav class="flex flex-col px-3" aria-label="{{ __('Mobile') }}">
            @foreach (['features' => __('Features'), 'solutions' => __('Solutions'), 'pricing' => __('Pricing'), 'help' => __('Help'), 'blog' => __('Blog'), 'about' => __('About'), 'contact' => __('Contact'), 'login' => __('Sign in')] as $route => $label)
                <a href="{{ route($route) }}" class="nav-link">{{ $label }}</a>
            @endforeach
        </nav>
    </dialog>

    <main id="main">
        @if (session('success'))<div class="mx-auto max-w-6xl px-4 pt-6 sm:px-6"><x-alert tone="ok">{{ session('success') }}</x-alert></div>@endif
        @yield('content')
    </main>

    <footer class="mt-24 border-t border-line bg-raised">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 text-sm sm:grid-cols-2 sm:px-6 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <p class="font-semibold">{{ config('app.name') }}</p>
                <p class="mt-2 max-w-xs text-muted">{{ __('One workspace to invoice customers, manage operations, track money and understand your business.') }}</p>
                <form method="GET" class="mt-4" data-no-loading onsubmit="return false">
                    <label for="site-language" class="sr-only">{{ __('Language') }}</label>
                    <select id="site-language" class="input w-auto" onchange="window.location = '{{ url('locale') }}/' + this.value">
                        @foreach (config('saas.locales') as $code => $name)
                            <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <nav aria-label="{{ __('Product') }}">
                <p class="font-medium">{{ __('Product') }}</p>
                <ul class="mt-3 space-y-2 text-muted">
                    <li><a class="hover:text-ink" href="{{ route('features') }}">{{ __('Features') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('pricing') }}">{{ __('Pricing') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('solutions') }}">{{ __('Solutions') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('help') }}">{{ __('Help centre') }}</a></li>
                </ul>
            </nav>
            <nav aria-label="{{ __('Company') }}">
                <p class="font-medium">{{ __('Company') }}</p>
                <ul class="mt-3 space-y-2 text-muted">
                    <li><a class="hover:text-ink" href="{{ route('about') }}">{{ __('About') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('contact') }}">{{ __('Contact') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('blog') }}">{{ __('Blog') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('legal.security') }}">{{ __('Security') }}</a></li>
                </ul>
            </nav>
            <nav aria-label="{{ __('Legal') }}">
                <p class="font-medium">{{ __('Legal') }}</p>
                <ul class="mt-3 space-y-2 text-muted">
                    <li><a class="hover:text-ink" href="{{ route('legal.terms') }}">{{ __('Terms of Service') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('legal.privacy') }}">{{ __('Privacy Policy') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('legal.cookies') }}">{{ __('Cookie Policy') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('legal.refund-policy') }}">{{ __('Refund Policy') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('legal.acceptable-use') }}">{{ __('Acceptable Use') }}</a></li>
                    <li><a class="hover:text-ink" href="{{ route('legal.data-processing') }}">{{ __('Data Processing') }}</a></li>
                    <li><button type="button" class="hover:text-ink" data-open="cookie-preferences">{{ __('Cookie preferences') }}</button></li>
                </ul>
            </nav>
        </div>
        <p class="border-t border-line px-4 py-4 text-center text-xs text-muted">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
    </footer>

    @include('partials.cookies')
    @include('partials.flash')
</body>
</html>
