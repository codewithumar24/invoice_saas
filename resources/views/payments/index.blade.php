@extends('layouts.app')
@section('title', __('Payments'))

@section('content')
    <x-page-header :title="__('Payments')" :subtitle="__('Money received from customers. Record a payment from the invoice it pays.')">
        @can('reports.export')<a href="{{ route('exports.download-now', ['type' => 'payments', 'format' => 'xlsx']) }}" class="btn btn-secondary"><x-icon name="download" size="16" />{{ __('Export') }}</a>@endcan
    </x-page-header>

    <x-filters :placeholder="__('Number, transaction or customer')">
        <div>
            <label for="f-status" class="sr-only">{{ __('Status') }}</label>
            <select name="status" id="f-status" class="input w-auto">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Models\Payment::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ status_label($status) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="f-customer" class="sr-only">{{ __('Customer') }}</label>
            <select name="customer_id" id="f-customer" class="input w-auto max-w-48">
                <option value="">{{ __('All customers') }}</option>
                @foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>@endforeach
            </select>
        </div>
        @if ($methods->count() > 1)
            <div>
                <label for="f-method" class="sr-only">{{ __('Method') }}</label>
                <select name="method" id="f-method" class="input w-auto">
                    <option value="">{{ __('All methods') }}</option>
                    @foreach ($methods as $method)<option value="{{ $method }}" @selected(request('method') === $method)>{{ status_label($method) }}</option>@endforeach
                </select>
            </div>
        @endif
        <div><label for="f-from" class="label">{{ __('From') }}</label><input type="date" name="from" id="f-from" value="{{ request('from') }}" class="input w-auto"></div>
        <div><label for="f-to" class="label">{{ __('To') }}</label><input type="date" name="to" id="f-to" value="{{ request('to') }}" class="input w-auto"></div>
    </x-filters>

    @if ($payments->count())
        <x-table>
            <thead><tr><th><x-sort column="number" :label="__('Payment')" /></th><th><x-sort column="paid_at" :label="__('Date')" /></th><th>{{ __('Customer') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Method') }}</th><th class="text-end"><x-sort column="amount" :label="__('Amount')" /></th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td data-label="{{ __('Payment') }}"><a href="{{ route('payments.show', $payment) }}" class="num font-medium hover:text-brand">{{ $payment->number }}</a></td>
                        <td data-label="{{ __('Date') }}">{{ tdate($payment->paid_at) }}</td>
                        <td data-label="{{ __('Customer') }}">{{ $payment->customer?->name }}</td>
                        <td data-label="{{ __('Invoice') }}">@foreach ($payment->invoices as $invoice)<a class="link num" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a>@endforeach</td>
                        <td data-label="{{ __('Method') }}">{{ $payment->paymentMethod?->name ?? status_label($payment->method) }}</td>
                        <td data-label="{{ __('Amount') }}" class="num text-end">{{ money($payment->amount, $payment->currency) }}</td>
                        <td data-label="{{ __('Status') }}"><x-badge :status="$payment->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $payments->links() }}
    @else
        <div class="card">
            <x-empty :title="request()->except('page') ? __('No payments match these filters') : __('No payments yet')" :text="__('Open an unpaid invoice and choose Record payment. Online payments appear here automatically.')">
                <a href="{{ route('invoices.index', ['payment' => 'unpaid']) }}" class="btn btn-primary">{{ __('View unpaid invoices') }}</a>
            </x-empty>
        </div>
    @endif
@endsection
