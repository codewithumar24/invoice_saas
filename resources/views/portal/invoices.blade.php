@extends('layouts.customer')
@section('title', __('Invoices'))

@section('content')
    <h1 class="h1 mb-6">{{ __('Invoices') }}</h1>
    @if ($invoices->count())
        <x-table>
            <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Balance') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($invoices as $invoice)
                    <tr>
                        <td data-label="{{ __('Number') }}" class="num font-medium">{{ $invoice->number }}</td>
                        <td data-label="{{ __('Date') }}">{{ $invoice->issue_date->format('d M Y') }}</td>
                        <td data-label="{{ __('Due') }}">{{ $invoice->due_date->format('d M Y') }}</td>
                        <td data-label="{{ __('Total') }}" class="num text-end">{{ money($invoice->total, $invoice->currency) }}</td>
                        <td data-label="{{ __('Balance') }}" class="num text-end">{{ money($invoice->balance_due, $invoice->currency) }}</td>
                        <td data-label="{{ __('Status') }}"><x-badge :status="$invoice->displayStatus()" /></td>
                        <td class="text-end">
                            <div class="flex justify-end gap-1">
                                <a href="{{ $invoice->publicUrl() }}" class="btn btn-secondary btn-sm">{{ (float) $invoice->balance_due > 0 && $invoice->status !== 'cancelled' ? __('View and pay') : __('View') }}</a>
                                <a href="{{ route('public.invoice.pdf', $invoice->public_token) }}" class="btn btn-ghost btn-sm">{{ __('PDF') }}</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $invoices->links() }}
    @else
        <div class="card"><x-empty :title="__('No invoices yet')" :text="__('Invoices from :business appear here.', ['business' => $portalTenant->name])" /></div>
    @endif
@endsection
