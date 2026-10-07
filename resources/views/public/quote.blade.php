@php
    $title = __('Quote :number', ['number' => $quote->number]);
    $noindex = true;
    $appName = $tenant->name;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.head')
    @include('partials.brand-style', ['brandColor' => feature('custom_branding') ? $tenant->primary_color : null])
    @include('pdf._styles')
</head>
<body>
    <main class="mx-auto max-w-3xl px-4 py-6 sm:py-10">
        @if (session('success'))<x-alert tone="ok" class="no-print mb-4">{{ session('success') }}</x-alert>@endif
        @if ($errors->any())<x-alert tone="danger" class="no-print mb-4">{{ $errors->first() }}</x-alert>@endif

        <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-surface p-4">
            <div>
                <p class="text-sm text-muted">{{ __('Quote total') }}</p>
                <p class="num text-2xl font-semibold">{{ money($quote->total, $quote->currency) }}</p>
                @if ($quote->expiry_date)<p class="text-sm text-muted">{{ __('Valid until :date', ['date' => $quote->expiry_date->format('d M Y')]) }}</p>@endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('public.quote.pdf', $quote->public_token) }}" class="btn btn-secondary"><x-icon name="download" size="16" />{{ __('Download PDF') }}</a>
                @if ($quote->canRespond())
                    <form method="POST" action="{{ route('public.quote.respond', $quote->public_token) }}" data-confirm="{{ __('Decline this quote?') }}" data-confirm-label="{{ __('Decline quote') }}">
                        @csrf<input type="hidden" name="response" value="reject">
                        <button type="submit" class="btn btn-secondary">{{ __('Decline') }}</button>
                    </form>
                    <form method="POST" action="{{ route('public.quote.respond', $quote->public_token) }}">
                        @csrf<input type="hidden" name="response" value="accept">
                        <button type="submit" class="btn btn-primary"><x-icon name="check" size="16" />{{ __('Accept quote') }}</button>
                    </form>
                @else
                    <x-badge :status="$quote->displayStatus()" class="self-center text-sm" />
                @endif
            </div>
        </div>

        <div class="print-sheet border border-line bg-white p-6 shadow-sm sm:p-10" style="color-scheme: light;">
            @include('pdf._quote')
        </div>

        @unless (feature('white_label') && setting('white_label'))
            <p class="no-print mt-6 text-center text-xs text-muted">{{ __('Powered by :app', ['app' => config('app.name')]) }}</p>
        @endunless
    </main>
    @include('partials.flash')
</body>
</html>
