@extends('layouts.customer')
@section('title', __('Overview'))

@section('content')
    <h1 class="h1 mb-6">{{ __('Hello, :name', ['name' => $portalCustomer->name]) }}</h1>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div class="card card-pad">
            <p class="text-sm text-muted">{{ __('Amount due') }}</p>
            @forelse ($outstanding as $currency => $amount)
                <p class="num text-2xl font-semibold">{{ money($amount, $currency) }}</p>
            @empty
                <p class="text-2xl font-semibold">{{ __('Nothing to pay') }}</p>
            @endforelse
        </div>
        <div class="card card-pad">
            <p class="text-sm text-muted">{{ __('Overdue invoices') }}</p>
            <p class="num text-2xl font-semibold {{ $overdueCount ? 'text-danger' : '' }}">{{ $overdueCount }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Invoices to pay')" :padded="false">
            @if ($openInvoices->count())
                <ul class="divide-y divide-line">
                    @foreach ($openInvoices as $invoice)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                            <div>
                                <p class="num font-medium">{{ $invoice->number }}</p>
                                <p class="caption {{ $invoice->isOverdue() ? 'text-danger' : '' }}">{{ __('Due :date', ['date' => $invoice->due_date->format('d M Y')]) }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="num font-medium">{{ money($invoice->balance_due, $invoice->currency) }}</span>
                                <a href="{{ $invoice->publicUrl() }}" class="btn btn-primary btn-sm">{{ __('View and pay') }}</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="card-pad text-sm text-muted">{{ __('You have no unpaid invoices.') }}</p>
            @endif
        </x-card>

        <x-card :title="__('Quotes waiting for your answer')" :padded="false">
            @if ($pendingQuotes->count())
                <ul class="divide-y divide-line">
                    @foreach ($pendingQuotes as $quote)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                            <div><p class="num font-medium">{{ $quote->number }}</p><p class="caption">{{ money($quote->total, $quote->currency) }}</p></div>
                            <a href="{{ $quote->publicUrl() }}" class="btn btn-secondary btn-sm">{{ __('Review quote') }}</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="card-pad text-sm text-muted">{{ __('No quotes are waiting.') }}</p>
            @endif
        </x-card>
    </div>
@endsection
