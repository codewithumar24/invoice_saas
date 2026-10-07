@extends('layouts.app')
@section('title', __('Invoice :number', ['number' => $invoice->number]))

@php
    $status = $invoice->displayStatus();
    $canPay = (float) $invoice->balance_due > 0 && $invoice->status !== 'cancelled';
    $paymentOpen = $errors->hasAny(['amount', 'paid_at', 'payment_method_id', 'transaction_id']);
@endphp

@section('content')
    <x-page-header :title="$invoice->number" :subtitle="$invoice->customer?->name" :back="route('invoices.index')">
        <x-badge :status="$status" class="text-sm" />
        @if ($canPay)@can('payments.create')<button type="button" class="btn btn-primary" data-open="record-payment">{{ __('Record payment') }}</button>@endcan @endif
        @if ($invoice->status !== 'cancelled')
            @can('invoices.send')
                <button type="button" class="btn btn-secondary" data-email-preview="{{ route('invoices.email-preview', $invoice) }}" data-dialog="send-invoice"><x-icon name="send" size="16" />{{ $invoice->sent_at ? __('Send again') : __('Send') }}</button>
            @endcan
        @endif
        <x-dropdown :label="__('More')">
            @if ($invoice->isEditable())@can('invoices.update')<a href="{{ route('invoices.edit', $invoice) }}" class="menu-item" role="menuitem">{{ __('Edit') }}</a>@endcan @endif
            @can('invoices.download')
                <a href="{{ route('invoices.pdf', $invoice) }}" class="menu-item" role="menuitem">{{ __('Download PDF') }}</a>
                <a href="{{ route('invoices.print', $invoice) }}" class="menu-item" role="menuitem" target="_blank" rel="noopener">{{ __('Print') }}</a>
            @endcan
            @can('invoices.duplicate')<form method="POST" action="{{ route('invoices.duplicate', $invoice) }}">@csrf<button class="menu-item" role="menuitem">{{ __('Duplicate') }}</button></form>@endcan
            @if ($invoice->status === 'draft')
                @can('invoices.send')<form method="POST" action="{{ route('invoices.mark-sent', $invoice) }}">@csrf<button class="menu-item" role="menuitem">{{ __('Mark as sent') }}</button></form>@endcan
                @can('invoices.delete')
                    <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" data-confirm="{{ __('Delete this draft invoice?') }}" data-confirm-label="{{ __('Delete draft') }}">@csrf @method('DELETE')<button class="menu-item menu-item-danger" role="menuitem">{{ __('Delete draft') }}</button></form>
                @endcan
            @elseif ($invoice->status !== 'cancelled' && $invoice->status !== 'paid')
                @can('invoices.delete')
                    <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" data-confirm="{{ __('Cancel invoice :number? It stays in your records as cancelled and stock is put back.', ['number' => $invoice->number]) }}" data-confirm-label="{{ __('Cancel invoice') }}">@csrf<button class="menu-item menu-item-danger" role="menuitem">{{ __('Cancel invoice') }}</button></form>
                @endcan
            @endif
        </x-dropdown>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat :label="__('Total')" :value="money($invoice->total, $invoice->currency)" />
                <x-stat :label="__('Paid')" :value="money($invoice->amount_paid, $invoice->currency)" />
                <x-stat :label="__('Balance due')" :value="money($invoice->balance_due, $invoice->currency)" :tone="$status === 'overdue' ? 'danger' : null"
                    :note="$status === 'overdue' ? trans_choice(':count day overdue|:count days overdue', $invoice->daysOverdue(), ['count' => $invoice->daysOverdue()]) : __('Due :date', ['date' => tdate($invoice->due_date)])" />
            </div>

            <x-card :title="__('Items')" :padded="false">
                <table class="table table-stack">
                    <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ setting('tax_label') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td data-label="{{ __('Item') }}">
                                    <span class="font-medium">{{ $item->name }}</span>
                                    @if ($item->description)<span class="caption block whitespace-pre-line">{{ $item->description }}</span>@endif
                                </td>
                                <td data-label="{{ __('Quantity') }}" class="num text-end">{{ (float) $item->quantity }} {{ $item->unit }}</td>
                                <td data-label="{{ __('Price') }}" class="num text-end">{{ money($item->unit_price, $invoice->currency) }}</td>
                                <td data-label="{{ __('Discount') }}" class="num text-end">{{ (float) $item->discount_percent > 0 ? (float) $item->discount_percent.'%' : '—' }}</td>
                                <td data-label="{{ setting('tax_label') }}" class="num text-end">{{ (float) $item->tax_rate > 0 ? (float) $item->tax_rate.'%' : '—' }}</td>
                                <td data-label="{{ __('Total') }}" class="num text-end">{{ money($item->line_total, $invoice->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <dl class="ms-auto max-w-xs space-y-1.5 border-t border-line p-4 text-sm sm:p-6">
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Subtotal') }}</dt><dd class="num">{{ money($invoice->subtotal, $invoice->currency) }}</dd></div>
                    @if ((float) $invoice->discount_total > 0)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Discount') }}</dt><dd class="num">−{{ money($invoice->discount_total, $invoice->currency) }}</dd></div>@endif
                    @foreach ($invoice->taxes as $tax)
                        <div class="flex justify-between gap-4"><dt class="text-muted">{{ $tax->name }} ({{ (float) $tax->rate }}%)@if ($invoice->tax_inclusive) {{ __('included') }}@endif</dt><dd class="num">{{ money($tax->amount, $invoice->currency) }}</dd></div>
                    @endforeach
                    @if ((float) $invoice->shipping > 0)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Shipping') }}</dt><dd class="num">{{ money($invoice->shipping, $invoice->currency) }}</dd></div>@endif
                    @if ((float) $invoice->adjustment != 0)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Adjustment') }}</dt><dd class="num">{{ money($invoice->adjustment, $invoice->currency) }}</dd></div>@endif
                    <div class="flex justify-between gap-4 border-t border-line pt-2 text-base font-semibold"><dt>{{ __('Total') }}</dt><dd class="num">{{ money($invoice->total, $invoice->currency) }}</dd></div>
                </dl>
            </x-card>

            <x-card :title="__('Payments')" :padded="false">
                @if ($invoice->allocations->count())
                    <table class="table table-stack">
                        <thead><tr><th>{{ __('Payment') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Applied') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($invoice->allocations as $allocation)
                                <tr>
                                    <td data-label="{{ __('Payment') }}"><a href="{{ route('payments.show', $allocation->payment) }}" class="num font-medium hover:text-brand">{{ $allocation->payment->number }}</a></td>
                                    <td data-label="{{ __('Date') }}">{{ tdate($allocation->payment->paid_at) }}</td>
                                    <td data-label="{{ __('Method') }}">{{ $allocation->payment->paymentMethod?->name ?? status_label($allocation->payment->method) }}</td>
                                    <td data-label="{{ __('Applied') }}" class="num text-end">{{ money($allocation->amount, $invoice->currency) }}</td>
                                    <td data-label="{{ __('Status') }}"><x-badge :status="$allocation->payment->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <x-empty :title="__('No payments recorded')" :text="$canPay ? __('When the customer pays, record it here. Online payments are recorded automatically.') : null" />
                @endif
            </x-card>

            @if ($invoice->notes || $invoice->terms)
                <x-card :title="__('Notes and terms')">
                    @if ($invoice->notes)<p class="whitespace-pre-line text-sm">{{ $invoice->notes }}</p>@endif
                    @if ($invoice->terms)<p class="mt-3 whitespace-pre-line text-sm text-muted">{{ $invoice->terms }}</p>@endif
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card :title="__('Details')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Customer') }}</dt><dd><a class="link" href="{{ route('customers.show', $invoice->customer_id) }}">{{ $invoice->customer?->name }}</a></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Issue date') }}</dt><dd>{{ tdate($invoice->issue_date) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Due date') }}</dt><dd>{{ tdate($invoice->due_date) }}</dd></div>
                    @if ($invoice->reference)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Reference') }}</dt><dd>{{ $invoice->reference }}</dd></div>@endif
                    @if ($invoice->currency !== tenant()->currency)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Exchange rate') }}</dt><dd class="num">1 {{ $invoice->currency }} = {{ rtrim(rtrim($invoice->exchange_rate, '0'), '.') }} {{ tenant()->currency }}</dd></div>@endif
                    @if ($invoice->project)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Project') }}</dt><dd><a class="link" href="{{ route('projects.show', $invoice->project) }}">{{ $invoice->project->name }}</a></dd></div>@endif
                    @if ($invoice->quote)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('From quote') }}</dt><dd><a class="link" href="{{ route('quotes.show', $invoice->quote) }}">{{ $invoice->quote->number }}</a></dd></div>@endif
                    @foreach ($customFields as $label => $value)
                        <div class="flex justify-between gap-4"><dt class="text-muted">{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Created by') }}</dt><dd>{{ $invoice->creator?->name ?? __('System') }}</dd></div>
                </dl>
                @if ($invoice->tags->count())
                    <div class="mt-3 flex flex-wrap gap-1">@foreach ($invoice->tags as $tag)<span class="badge badge-neutral">{{ $tag->name }}</span>@endforeach</div>
                @endif
            </x-card>

            @if ($invoice->status !== 'draft')
                <x-card :title="__('Share link')">
                    <p class="text-sm text-muted">{{ __('The customer can view, download and pay the invoice from this secure link.') }}</p>
                    <p class="mt-2 break-all text-sm"><a class="link" href="{{ $invoice->publicUrl() }}" target="_blank" rel="noopener">{{ $invoice->publicUrl() }}</a></p>
                    <div class="mt-3 flex items-start gap-4">
                        <button type="button" class="btn btn-secondary btn-sm" data-copy="{{ $invoice->publicUrl() }}" data-copied="{{ __('Copied') }}">{{ __('Copy link') }}</button>
                        @if (setting('invoice_show_qr'))<div class="w-24 rounded border border-line bg-white p-1" data-qr="{{ $invoice->publicUrl() }}" data-qr-label="{{ __('QR code for the invoice link') }}"></div>@endif
                    </div>
                    @if ($invoice->viewed_at)<p class="caption mt-3">{{ __('First viewed by the customer on :date', ['date' => tdate($invoice->viewed_at, 'd M Y, H:i')]) }}</p>@endif
                </x-card>
            @endif

            <x-card :title="__('Timeline')">
                @include('partials.timeline', ['items' => $timeline])
            </x-card>
        </div>
    </div>

    {{-- Record payment --}}
    @can('payments.create')
        <x-modal id="record-payment" :title="__('Record payment')" :auto-open="$paymentOpen">
            <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="space-y-4">
                @csrf
                <x-input name="amount" type="number" step="any" min="0.01" :label="__('Amount (:currency)', ['currency' => $invoice->currency])" :value="(float) $invoice->balance_due" required :hint="__('Balance due: :amount', ['amount' => money($invoice->balance_due, $invoice->currency)])" />
                <x-input name="paid_at" type="date" :label="__('Payment date')" :value="today_local()" required />
                <x-select name="payment_method_id" :label="__('Payment method')" :options="$paymentMethods->pluck('name', 'id')->all()" :placeholder="__('Not specified')" />
                <x-input name="transaction_id" :label="__('Transaction ID or reference')" />
                <x-textarea name="notes" :label="__('Notes')" rows="2" id="payment-notes" />
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Record payment') }}</button>
                </div>
            </form>
        </x-modal>
    @endcan

    {{-- Preview and send the email --}}
    @can('invoices.send')
        <x-modal id="send-invoice" :title="__('Send invoice by email')" wide>
            <div data-preview-loading class="space-y-3">
                <div class="skeleton h-10"></div><div class="skeleton h-40"></div>
            </div>
            <form method="POST" action="{{ route('invoices.send', $invoice) }}" class="space-y-4" data-preview-body hidden>
                @csrf
                <p class="text-sm"><span class="text-muted">{{ __('To') }}:</span> <span class="font-medium" data-preview="to"></span></p>
                <x-input name="subject" :label="__('Subject')" required id="send-subject" />
                <x-textarea name="body" :label="__('Message')" rows="9" required id="send-body" />
                <dl class="space-y-1 rounded-md bg-raised p-3 text-sm">
                    <div class="flex gap-2"><dt class="text-muted">{{ __('Attachment') }}:</dt><dd data-preview="attachment"></dd></div>
                    <div class="flex gap-2"><dt class="shrink-0 text-muted">{{ __('Payment link') }}:</dt><dd class="break-all" data-preview="link"></dd></div>
                </dl>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary"><x-icon name="send" size="16" />{{ __('Send invoice') }}</button>
                </div>
            </form>
        </x-modal>
    @endcan
@endsection
