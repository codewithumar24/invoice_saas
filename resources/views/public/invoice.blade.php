{{-- Public invoice page, reached by a secret link. Carries the business's branding and nothing internal. --}}
@php
    $title = __('Invoice :number', ['number' => $invoice->number]);
    $noindex = true;
    $appName = $tenant->name;
    $canPay = (float) $invoice->balance_due > 0 && $invoice->status !== 'cancelled';
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
        @if ($justPaid)
            <x-alert tone="ok" class="no-print mb-4">{{ __('Thank you. Your payment is being confirmed; this page updates as soon as it is received.') }}</x-alert>
        @endif
        @if (session('error'))<x-alert tone="danger" class="no-print mb-4">{{ session('error') }}</x-alert>@endif

        <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-surface p-4">
            <div>
                <p class="text-sm text-muted">{{ $canPay ? __('Amount due') : __('Status') }}</p>
                <p class="num text-2xl font-semibold">{{ $canPay ? money($invoice->balance_due, $invoice->currency) : status_label($invoice->displayStatus()) }}</p>
                @if ($canPay)<p class="text-sm {{ $invoice->isOverdue() ? 'text-danger' : 'text-muted' }}">{{ $invoice->isOverdue() ? __('Was due on :date', ['date' => $invoice->due_date->format('d M Y')]) : __('Due on :date', ['date' => $invoice->due_date->format('d M Y')]) }}</p>@endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('public.invoice.pdf', $invoice->public_token) }}" class="btn btn-secondary"><x-icon name="download" size="16" />{{ __('Download PDF') }}</a>
                <button type="button" class="btn btn-secondary" data-print><x-icon name="print" size="16" />{{ __('Print') }}</button>
                @foreach ($onlineMethods as $method)
                    <form method="POST" action="{{ route('public.invoice.pay', [$invoice->public_token, $method->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ __('Pay with :method', ['method' => $method->name]) }}</button>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="print-sheet border border-line bg-white p-6 shadow-sm sm:p-10" style="color-scheme: light;">
            @include('pdf._invoice')
        </div>

        @if ($canPay && $offlineMethods->filter(fn ($method) => $method->instructions)->count())
            <section class="no-print mt-4 rounded-lg border border-line bg-surface p-4">
                <h2 class="h3">{{ __('Other ways to pay') }}</h2>
                <dl class="mt-2 space-y-3 text-sm">
                    @foreach ($offlineMethods->filter(fn ($method) => $method->instructions) as $method)
                        <div><dt class="font-medium">{{ $method->name }}</dt><dd class="whitespace-pre-line text-muted">{{ $method->instructions }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($canPay && setting('invoice_show_qr'))
            <div class="no-print mt-4 flex items-center gap-4 rounded-lg border border-line bg-surface p-4">
                <div class="w-24 shrink-0 rounded border border-line bg-white p-1" data-qr="{{ $invoice->publicUrl() }}" data-qr-label="{{ __('QR code for this invoice') }}"></div>
                <p class="text-sm text-muted">{{ __('Scan to open this invoice on your phone.') }}</p>
            </div>
        @endif

        @unless (feature('white_label') && setting('white_label'))
            <p class="no-print mt-6 text-center text-xs text-muted">{{ __('Powered by :app', ['app' => config('app.name')]) }}</p>
        @endunless
    </main>
</body>
</html>
