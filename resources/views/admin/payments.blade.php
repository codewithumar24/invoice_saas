@extends('layouts.admin')
@section('title', __('Payments'))

@section('content')
    <x-page-header :title="__('Subscription payments')" :subtitle="__('Invoices for workspace subscriptions. In manual billing mode, mark an invoice paid when the transfer arrives.')" />
    <x-filters :search="false">
        <div>
            <label for="f-status" class="sr-only">{{ __('Status') }}</label>
            <select name="status" id="f-status" class="input w-auto"><option value="">{{ __('All statuses') }}</option>@foreach (['paid', 'pending', 'failed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ status_label($status) }}</option>@endforeach</select>
        </div>
    </x-filters>

    <x-table>
        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Tenant') }}</th><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td data-label="{{ __('Number') }}" class="num font-medium">{{ $invoice->number }}</td>
                    <td data-label="{{ __('Tenant') }}">@if ($invoice->tenant)<a class="link" href="{{ route('admin.tenants.show', $invoice->tenant) }}">{{ $invoice->tenant->name }}</a>@else — @endif</td>
                    <td data-label="{{ __('Date') }}">{{ $invoice->created_at->format('d M Y') }}</td>
                    <td data-label="{{ __('Description') }}">{{ $invoice->description }}</td>
                    <td data-label="{{ __('Amount') }}" class="num text-end">{{ \App\Support\Money::format($invoice->amount, $invoice->currency) }}</td>
                    <td data-label="{{ __('Status') }}"><x-badge :status="$invoice->status" /></td>
                    <td class="text-end">@if ($invoice->status !== 'paid')<x-action :action="route('admin.payments.paid', $invoice->id)" :confirm="__('Mark :number as paid?', ['number' => $invoice->number])" :confirm-label="__('Mark as paid')">{{ __('Mark as paid') }}</x-action>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">{{ __('No payments yet.') }}</td></tr>
            @endforelse
        </tbody>
    </x-table>
    {{ $invoices->links() }}
@endsection
