@extends('layouts.customer')
@section('title', __('Payments'))

@section('content')
    <h1 class="h1 mb-6">{{ __('Payment history') }}</h1>
    @if ($payments->count())
        <x-table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Receipt') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td data-label="{{ __('Date') }}">{{ $payment->paid_at?->format('d M Y') }}</td>
                        <td data-label="{{ __('Receipt') }}" class="num">{{ $payment->number }}</td>
                        <td data-label="{{ __('Invoice') }}" class="num">{{ $payment->invoices->pluck('number')->implode(', ') }}</td>
                        <td data-label="{{ __('Method') }}">{{ status_label($payment->method) }}</td>
                        <td data-label="{{ __('Amount') }}" class="num text-end">{{ money($payment->amount, $payment->currency) }}</td>
                        <td data-label="{{ __('Status') }}"><x-badge :status="$payment->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $payments->links() }}
    @else
        <div class="card"><x-empty :title="__('No payments yet')" :text="__('Your payments appear here after they are received.')" /></div>
    @endif
@endsection
