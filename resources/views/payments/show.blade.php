@extends('layouts.app')
@section('title', __('Payment :number', ['number' => $payment->number]))

@php $refundable = (float) $payment->refundable(); @endphp

@section('content')
    <x-page-header :title="$payment->number" :subtitle="$payment->customer?->name" :back="route('payments.index')">
        <x-badge :status="$payment->status" class="text-sm" />
        @if ($refundable > 0 && in_array($payment->status, ['paid', 'partially_refunded']))
            @can('payments.refund')<button type="button" class="btn btn-danger" data-open="refund-payment">{{ __('Refund') }}</button>@endcan
        @endif
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Details')">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Amount') }}</dt><dd class="num font-semibold">{{ money($payment->amount, $payment->currency) }}</dd></div>
                @if ((float) $payment->refunded_amount > 0)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Refunded') }}</dt><dd class="num text-danger">−{{ money($payment->refunded_amount, $payment->currency) }}</dd></div>@endif
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Date') }}</dt><dd>{{ tdate($payment->paid_at, 'd M Y, H:i') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Customer') }}</dt><dd><a class="link" href="{{ route('customers.show', $payment->customer_id) }}">{{ $payment->customer?->name }}</a></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Method') }}</dt><dd>{{ $payment->paymentMethod?->name ?? status_label($payment->method) }}</dd></div>
                @if ($payment->gateway)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Gateway') }}</dt><dd>{{ status_label($payment->gateway) }}</dd></div>@endif
                @if ($payment->transaction_id)<div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Transaction ID') }}</dt><dd class="break-all">{{ $payment->transaction_id }}</dd></div>@endif
                @if ($payment->notes)<div><dt class="text-muted">{{ __('Notes') }}</dt><dd class="whitespace-pre-line">{{ $payment->notes }}</dd></div>@endif
            </dl>
        </x-card>

        <div class="space-y-6 lg:col-span-2">
            <x-card :title="__('Applied to')" :padded="false">
                <table class="table table-stack">
                    <thead><tr><th>{{ __('Invoice') }}</th><th class="text-end">{{ __('Amount applied') }}</th><th>{{ __('Invoice status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($payment->allocations as $allocation)
                            <tr>
                                <td data-label="{{ __('Invoice') }}"><a class="link num" href="{{ route('invoices.show', $allocation->invoice) }}">{{ $allocation->invoice->number }}</a></td>
                                <td data-label="{{ __('Amount applied') }}" class="num text-end">{{ money($allocation->amount, $payment->currency) }}</td>
                                <td data-label="{{ __('Invoice status') }}"><x-badge :status="$allocation->invoice->displayStatus()" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>

            <x-card :title="__('Transactions')" :padded="false">
                <table class="table table-stack">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Note') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @foreach ($payment->transactions as $transaction)
                            <tr>
                                <td data-label="{{ __('Date') }}">{{ tdate($transaction->created_at, 'd M Y, H:i') }}</td>
                                <td data-label="{{ __('Type') }}">{{ $transaction->type === 'refund' ? __('Refund') : __('Payment') }}</td>
                                <td data-label="{{ __('Note') }}">{{ $transaction->notes ?: '—' }}</td>
                                <td data-label="{{ __('Amount') }}" class="num text-end {{ $transaction->type === 'refund' ? 'text-danger' : '' }}">{{ $transaction->type === 'refund' ? '−' : '' }}{{ money($transaction->amount, $transaction->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>

    @can('payments.refund')
        <x-modal id="refund-payment" :title="__('Refund payment')" :auto-open="$errors->has('amount')">
            <form method="POST" action="{{ route('payments.refund', $payment) }}" class="space-y-4" data-confirm="{{ __('Record this refund? The invoice balance goes up by the refunded amount.') }}" data-confirm-label="{{ __('Refund') }}">
                @csrf
                <p class="text-sm text-muted">{{ __('The original payment stays in your records and a refund entry is added. Up to :amount can be refunded.', ['amount' => money($refundable, $payment->currency)]) }}</p>
                <x-input name="amount" type="number" step="any" min="0.01" :max="$refundable" :label="__('Refund amount (:currency)', ['currency' => $payment->currency])" :value="$refundable" required />
                <x-input name="reason" :label="__('Reason')" />
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" data-close>{{ __('Keep payment') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('Refund') }}</button>
                </div>
            </form>
        </x-modal>
    @endcan
@endsection
