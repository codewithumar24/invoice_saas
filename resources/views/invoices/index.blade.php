@extends('layouts.app')
@section('title', __('Invoices'))

@section('content')
    <x-page-header :title="__('Invoices')">
        @can('reports.export')
            <a href="{{ route('exports.download-now', ['type' => 'invoices', 'format' => 'xlsx'] + request()->only(['status', 'from', 'to'])) }}" class="btn btn-secondary"><x-icon name="download" size="16" />{{ __('Export') }}</a>
        @endcan
        @can('invoices.create')<a href="{{ route('invoices.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ __('Create invoice') }}</a>@endcan
    </x-page-header>

    {{-- Status tabs --}}
    <nav class="mb-4 flex gap-6 overflow-x-auto border-b border-line" aria-label="{{ __('Invoice status') }}">
        <a href="{{ route('invoices.index') }}" class="tab" @if (! request('status')) aria-current="page" @endif>{{ __('All') }}</a>
        @foreach (\App\Models\Invoice::STATUSES as $status)
            <a href="{{ route('invoices.index', ['status' => $status]) }}" class="tab" @if (request('status') === $status) aria-current="page" @endif>{{ status_label($status) }}</a>
        @endforeach
    </nav>

    <x-filters :placeholder="__('Number, reference or customer')">
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        <div>
            <label for="f-customer" class="sr-only">{{ __('Customer') }}</label>
            <select name="customer_id" id="f-customer" class="input w-auto max-w-48">
                <option value="">{{ __('All customers') }}</option>
                @foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="f-payment" class="sr-only">{{ __('Payment status') }}</label>
            <select name="payment" id="f-payment" class="input w-auto">
                <option value="">{{ __('Paid and unpaid') }}</option>
                <option value="unpaid" @selected(request('payment') === 'unpaid')>{{ __('Unpaid') }}</option>
                <option value="paid" @selected(request('payment') === 'paid')>{{ __('Paid') }}</option>
            </select>
        </div>
        @if ($currencies->count() > 1)
            <div>
                <label for="f-currency" class="sr-only">{{ __('Currency') }}</label>
                <select name="currency" id="f-currency" class="input w-auto">
                    <option value="">{{ __('All currencies') }}</option>
                    @foreach ($currencies as $code)<option value="{{ $code }}" @selected(request('currency') === $code)>{{ $code }}</option>@endforeach
                </select>
            </div>
        @endif
        <div><label for="f-from" class="label">{{ __('From') }}</label><input type="date" name="from" id="f-from" value="{{ request('from') }}" class="input w-auto"></div>
        <div><label for="f-to" class="label">{{ __('To') }}</label><input type="date" name="to" id="f-to" value="{{ request('to') }}" class="input w-auto"></div>
        <div class="w-28"><label for="f-min" class="label">{{ __('Min amount') }}</label><input type="number" step="any" name="min" id="f-min" value="{{ request('min') }}" class="input"></div>
        <div class="w-28"><label for="f-max" class="label">{{ __('Max amount') }}</label><input type="number" step="any" name="max" id="f-max" value="{{ request('max') }}" class="input"></div>
    </x-filters>

    @if ($invoices->count())
        <div class="mb-2 flex justify-end">
            <details class="menu">
                <summary class="btn btn-ghost btn-sm"><x-icon name="columns" size="14" />{{ __('Columns') }}</summary>
                <div class="menu-panel p-3" data-columns="invoices-table">
                    @foreach (['reference' => __('Reference'), 'issue' => __('Issue date'), 'due' => __('Due date'), 'balance' => __('Balance'), 'tags' => __('Tags')] as $col => $label)
                        <label class="flex items-center gap-2 py-1 text-sm"><input type="checkbox" class="check" value="{{ $col }}" checked>{{ $label }}</label>
                    @endforeach
                </div>
            </details>
        </div>

        <form method="POST" action="{{ route('invoices.bulk') }}" data-bulk>
            @csrf
            @include('partials.bulk-bar', ['actions' => ['send' => __('Send selected'), 'mark_paid' => __('Mark as paid'), 'export' => __('Export CSV'), 'delete' => __('Delete drafts / cancel issued')]])

            <x-table id="invoices-table">
                <thead>
                    <tr>
                        <th class="w-8"><input type="checkbox" class="check" data-bulk-all aria-label="{{ __('Select all') }}"></th>
                        <th><x-sort column="number" :label="__('Number')" /></th>
                        <th>{{ __('Customer') }}</th>
                        <th data-col="reference">{{ __('Reference') }}</th>
                        <th data-col="issue"><x-sort column="issue_date" :label="__('Issued')" /></th>
                        <th data-col="due"><x-sort column="due_date" :label="__('Due')" /></th>
                        <th class="text-end"><x-sort column="total" :label="__('Total')" /></th>
                        <th class="text-end" data-col="balance"><x-sort column="balance_due" :label="__('Balance')" /></th>
                        <th>{{ __('Status') }}</th>
                        <th data-col="tags">{{ __('Tags') }}</th>
                        <th><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td><input type="checkbox" class="check" name="ids[]" value="{{ $invoice->id }}" data-bulk-item aria-label="{{ __('Select :name', ['name' => $invoice->number]) }}"></td>
                            <td data-label="{{ __('Number') }}"><a href="{{ route('invoices.show', $invoice) }}" class="num font-medium hover:text-brand">{{ $invoice->number }}</a></td>
                            <td data-label="{{ __('Customer') }}">{{ $invoice->customer?->name }}</td>
                            <td data-label="{{ __('Reference') }}" data-col="reference" class="text-muted">{{ $invoice->reference ?: '—' }}</td>
                            <td data-label="{{ __('Issued') }}" data-col="issue">{{ tdate($invoice->issue_date) }}</td>
                            <td data-label="{{ __('Due') }}" data-col="due" class="{{ $invoice->isOverdue() ? 'text-danger' : '' }}">{{ tdate($invoice->due_date) }}</td>
                            <td data-label="{{ __('Total') }}" class="num text-end">{{ money($invoice->total, $invoice->currency) }}</td>
                            <td data-label="{{ __('Balance') }}" data-col="balance" class="num text-end {{ $invoice->balance_due > 0 ? '' : 'text-muted' }}">{{ money($invoice->balance_due, $invoice->currency) }}</td>
                            <td data-label="{{ __('Status') }}"><x-badge :status="$invoice->displayStatus()" /></td>
                            <td data-label="{{ __('Tags') }}" data-col="tags">@foreach ($invoice->tags as $tag)<span class="badge badge-neutral">{{ $tag->name }}</span> @endforeach</td>
                            <td class="text-end">
                                <x-dropdown class="btn btn-ghost btn-icon btn-sm">
                                    <x-slot:trigger><x-icon name="more" /><span class="sr-only">{{ __('Actions for :number', ['number' => $invoice->number]) }}</span></x-slot:trigger>
                                    <a class="menu-item" role="menuitem" href="{{ route('invoices.show', $invoice) }}">{{ __('View') }}</a>
                                    @if ($invoice->isEditable())@can('invoices.update')<a class="menu-item" role="menuitem" href="{{ route('invoices.edit', $invoice) }}">{{ __('Edit') }}</a>@endcan @endif
                                    @can('invoices.download')
                                        <a class="menu-item" role="menuitem" href="{{ route('invoices.pdf', $invoice) }}">{{ __('Download PDF') }}</a>
                                        <a class="menu-item" role="menuitem" href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener">{{ __('Print') }}</a>
                                    @endcan
                                    @can('invoices.duplicate')<button class="menu-item" role="menuitem" formaction="{{ route('invoices.duplicate', $invoice) }}" formnovalidate>{{ __('Duplicate') }}</button>@endcan
                                </x-dropdown>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </form>
        {{ $invoices->links() }}
    @else
        <div class="card">
            @if (request()->except('page'))
                <x-empty :title="__('No invoices match these filters')" :text="__('Try another status or clear the filters.')">
                    <a href="{{ route('invoices.index') }}" class="btn btn-secondary">{{ __('Clear filters') }}</a>
                </x-empty>
            @else
                <x-empty :title="__('No invoices yet')" :text="__('An invoice tells your customer what they owe and by when. Create one, send it by email and record the payment when it arrives.')">
                    @can('invoices.create')<a href="{{ route('invoices.create') }}" class="btn btn-primary">{{ __('Create your first invoice') }}</a>@endcan
                </x-empty>
            @endif
        </div>
    @endif
@endsection
