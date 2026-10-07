@extends('layouts.app')
@section('title', __('Quote :number', ['number' => $quote->number]))

@php $status = $quote->displayStatus(); @endphp

@section('content')
    <x-page-header :title="$quote->number" :subtitle="$quote->customer?->name" :back="route('quotes.index')">
        <x-badge :status="$status" class="text-sm" />
        @if (in_array($quote->status, ['accepted', 'sent', 'viewed', 'draft']) && ! $quote->converted_invoice_id)
            @can('invoices.create')
                <x-action :action="route('quotes.convert', $quote)" class="btn btn-primary" :confirm="__('Create a draft invoice from this quote?')" :confirm-label="__('Convert to invoice')">{{ __('Convert to invoice') }}</x-action>
            @endcan
        @endif
        @if (! in_array($quote->status, ['accepted', 'converted']))
            @can('quotes.send')<x-action :action="route('quotes.send', $quote)" class="btn btn-secondary"><x-icon name="send" size="16" />{{ $quote->sent_at ? __('Send again') : __('Send') }}</x-action>@endcan
        @endif
        <x-dropdown :label="__('More')">
            @if (! in_array($quote->status, ['accepted', 'converted']))@can('quotes.update')<a href="{{ route('quotes.edit', $quote) }}" class="menu-item" role="menuitem">{{ __('Edit') }}</a>@endcan @endif
            <a href="{{ route('quotes.pdf', $quote) }}" class="menu-item" role="menuitem">{{ __('Download PDF') }}</a>
            @if (in_array($quote->status, ['draft', 'sent', 'viewed']) && ! $quote->isExpired())
                @can('quotes.update')
                    <form method="POST" action="{{ route('quotes.respond', $quote) }}">@csrf<input type="hidden" name="response" value="accept"><button class="menu-item" role="menuitem">{{ __('Mark as accepted') }}</button></form>
                    <form method="POST" action="{{ route('quotes.respond', $quote) }}">@csrf<input type="hidden" name="response" value="reject"><button class="menu-item" role="menuitem">{{ __('Mark as rejected') }}</button></form>
                @endcan
            @endif
            @can('quotes.delete')
                <form method="POST" action="{{ route('quotes.destroy', $quote) }}" data-confirm="{{ __('Delete quote :number?', ['number' => $quote->number]) }}" data-confirm-label="{{ __('Delete quote') }}">@csrf @method('DELETE')<button class="menu-item menu-item-danger" role="menuitem">{{ __('Delete') }}</button></form>
            @endcan
        </x-dropdown>
    </x-page-header>

    @if ($quote->invoice)
        <x-alert tone="ok" class="mb-6">{{ __('This quote was converted to invoice') }} <a class="underline" href="{{ route('invoices.show', $quote->invoice) }}">{{ $quote->invoice->number }}</a>.</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="__('Items')" :padded="false">
                <table class="table table-stack">
                    <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                    <tbody>
                        @foreach ($quote->items as $item)
                            <tr>
                                <td data-label="{{ __('Item') }}"><span class="font-medium">{{ $item->name }}</span>@if ($item->description)<span class="caption block whitespace-pre-line">{{ $item->description }}</span>@endif</td>
                                <td data-label="{{ __('Quantity') }}" class="num text-end">{{ (float) $item->quantity }} {{ $item->unit }}</td>
                                <td data-label="{{ __('Price') }}" class="num text-end">{{ money($item->unit_price, $quote->currency) }}</td>
                                <td data-label="{{ __('Total') }}" class="num text-end">{{ money($item->line_total, $quote->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <dl class="ms-auto max-w-xs space-y-1.5 border-t border-line p-4 text-sm sm:p-6">
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Subtotal') }}</dt><dd class="num">{{ money($quote->subtotal, $quote->currency) }}</dd></div>
                    @if ((float) $quote->discount_total > 0)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Discount') }}</dt><dd class="num">−{{ money($quote->discount_total, $quote->currency) }}</dd></div>@endif
                    @if ((float) $quote->tax_total > 0)<div class="flex justify-between gap-4"><dt class="text-muted">{{ setting('tax_label') }}</dt><dd class="num">{{ money($quote->tax_total, $quote->currency) }}</dd></div>@endif
                    <div class="flex justify-between gap-4 border-t border-line pt-2 text-base font-semibold"><dt>{{ __('Total') }}</dt><dd class="num">{{ money($quote->total, $quote->currency) }}</dd></div>
                </dl>
            </x-card>
            @if ($quote->notes || $quote->terms)
                <x-card :title="__('Notes and terms')">
                    @if ($quote->notes)<p class="whitespace-pre-line text-sm">{{ $quote->notes }}</p>@endif
                    @if ($quote->terms)<p class="mt-3 whitespace-pre-line text-sm text-muted">{{ $quote->terms }}</p>@endif
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card :title="__('Details')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Customer') }}</dt><dd><a class="link" href="{{ route('customers.show', $quote->customer_id) }}">{{ $quote->customer?->name }}</a></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Date') }}</dt><dd>{{ tdate($quote->issue_date) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Valid until') }}</dt><dd>{{ tdate($quote->expiry_date) }}</dd></div>
                </dl>
            </x-card>
            @if ($quote->status !== 'draft')
                <x-card :title="__('Share link')">
                    <p class="text-sm text-muted">{{ __('The customer can view, accept or decline the quote from this secure link.') }}</p>
                    <p class="mt-2 break-all text-sm"><a class="link" href="{{ $quote->publicUrl() }}" target="_blank" rel="noopener">{{ $quote->publicUrl() }}</a></p>
                    <button type="button" class="btn btn-secondary btn-sm mt-3" data-copy="{{ $quote->publicUrl() }}" data-copied="{{ __('Copied') }}">{{ __('Copy link') }}</button>
                </x-card>
            @endif
            <x-card :title="__('Timeline')">@include('partials.timeline', ['items' => $timeline])</x-card>
        </div>
    </div>
@endsection
