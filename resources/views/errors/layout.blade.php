{{-- Error pages never show technical details; those go to the logs. --}}
@php $title = $heading; $noindex = true; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body>
    <main class="mx-auto flex min-h-dvh max-w-lg flex-col justify-center px-6 py-12">
        <p class="num text-sm font-medium text-muted">{{ __('Error :code', ['code' => $code]) }}</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $heading }}</h1>
        <p class="mt-3 text-muted">{{ $text }}</p>
        <div class="mt-8 flex flex-wrap gap-2">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-primary">{{ __('Go back') }}</a>
            <a href="{{ url('/') }}" class="btn btn-secondary">{{ __('Home') }}</a>
            @auth
                @if (Route::has('support.index'))<a href="{{ route('support.index') }}" class="btn btn-ghost">{{ __('Contact support') }}</a>@endif
            @endauth
        </div>
    </main>
</body>
</html>
