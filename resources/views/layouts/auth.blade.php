@php $title = trim($__env->yieldContent('title')) ?: null; $noindex = true; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.head')
</head>
<body>
    <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-8 flex items-center justify-center gap-3">
            <span class="grid h-9 w-9 place-items-center rounded-md bg-brand font-semibold text-on-brand" aria-hidden="true">{{ mb_strtoupper(mb_substr(config('app.name'), 0, 1)) }}</span>
            <span class="text-lg font-semibold">{{ config('app.name') }}</span>
        </a>

        <div class="card card-pad">
            <h1 class="h1 mb-1">@yield('heading')</h1>
            @hasSection('intro')<p class="mb-6 text-sm text-muted">@yield('intro')</p>@else<div class="mb-6"></div>@endif

            @if (session('success'))<x-alert tone="ok" class="mb-4">{{ session('success') }}</x-alert>@endif
            @if (session('error'))<x-alert tone="danger" class="mb-4">{{ session('error') }}</x-alert>@endif

            @yield('content')
        </div>

        <div class="mt-6 text-center text-sm text-muted">@yield('footer')</div>
    </main>
</body>
</html>
