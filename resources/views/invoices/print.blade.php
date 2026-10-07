{{-- Print view: the same document as the PDF, on a clean A4 sheet (@media print in app.css). --}}
@php $title = $invoice->number; $noindex = true; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.head')
    @include('pdf._styles')
</head>
<body>
    <div class="no-print mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-4">
        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost">&larr; {{ __('Back to invoice') }}</a>
        <button type="button" class="btn btn-primary" data-print><x-icon name="print" size="16" />{{ __('Print invoice') }}</button>
    </div>
    <div class="print-sheet mx-auto mb-10 max-w-3xl border border-line bg-white p-8 shadow-sm sm:p-12" style="color-scheme: light;">
        @include('pdf._invoice')
    </div>
    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
</body>
</html>
