{{-- Customer portal layout. Shows the BUSINESS's branding, not the platform's, when the plan allows it. --}}
@php
    $portalTenant = $portalTenant ?? tenant();
    $files = app(\App\Services\FileStorageService::class);
    $branded = feature('custom_branding');
    $appName = $portalTenant->name;
    $logoUrl = $branded && $portalTenant->logo_path ? $files->publicUrl($portalTenant->logo_path) : null;
    $faviconUrl = $branded && $portalTenant->favicon_path ? $files->publicUrl($portalTenant->favicon_path) : null;
    $title = trim($__env->yieldContent('title')) ?: null;
    $noindex = true;
    $slug = $portalTenant->slug;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.head')
    @include('partials.brand-style', ['brandColor' => $branded ? $portalTenant->primary_color : null])
</head>
<body>
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex h-16 max-w-5xl items-center gap-4 px-4 sm:px-6">
            <a href="{{ isset($portalCustomer) ? route('portal.dashboard', $slug) : route('portal.login', $slug) }}" class="flex items-center gap-3">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="h-8 max-w-40 object-contain">
                @else
                    <span class="font-semibold">{{ $appName }}</span>
                @endif
            </a>
            @isset($portalCustomer)
                <div class="ms-auto flex items-center gap-3 text-sm">
                    <span class="hidden text-muted sm:inline">{{ $portalCustomer->name }}</span>
                    <form method="POST" action="{{ route('portal.logout', $slug) }}">@csrf<button class="btn btn-secondary btn-sm">{{ __('Sign out') }}</button></form>
                </div>
            @endisset
        </div>
        @isset($portalCustomer)
            <nav class="mx-auto flex max-w-5xl gap-6 overflow-x-auto px-4 sm:px-6" aria-label="{{ __('Portal') }}">
                @foreach (['dashboard' => __('Overview'), 'invoices' => __('Invoices'), 'quotes' => __('Quotes'), 'payments' => __('Payments'), 'documents' => __('Documents'), 'messages' => __('Messages'), 'profile' => __('Profile')] as $route => $label)
                    <a href="{{ route('portal.'.$route, $slug) }}" class="tab pt-1" @if (request()->routeIs('portal.'.$route)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        @endisset
    </header>

    <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
        @include('partials.errors')
        @yield('content')
    </main>

    @unless (feature('white_label') && setting('white_label'))
        <p class="pb-8 text-center text-xs text-muted">{{ __('Powered by :app', ['app' => config('app.name')]) }}</p>
    @endunless

    @include('partials.flash')
</body>
</html>
