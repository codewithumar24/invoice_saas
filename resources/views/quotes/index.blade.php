@extends('layouts.app')
@section('title', __('Quotes'))

@section('content')
    <x-page-header :title="__('Quotes')">
        @can('quotes.create')<a href="{{ route('quotes.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ __('Create quote') }}</a>@endcan
    </x-page-header>

    <nav class="mb-4 flex gap-6 overflow-x-auto border-b border-line" aria-label="{{ __('Quote status') }}">
        <a href="{{ route('quotes.index') }}" class="tab" @if (! request('status')) aria-current="page" @endif>{{ __('All') }}</a>
        @foreach (['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'] as $status)
            <a href="{{ route('quotes.index', ['status' => $status]) }}" class="tab" @if (request('status') === $status) aria-current="page" @endif>{{ $status === 'sent' ? __('Awaiting response') : status_label($status) }}</a>
        @endforeach
    </nav>

    <x-filters :placeholder="__('Number or customer')">
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        <div>
            <label for="f-customer" class="sr-only">{{ __('Customer') }}</label>
            <select name="customer_id" id="f-customer" class="input w-auto max-w-48">
                <option value="">{{ __('All customers') }}</option>
                @foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>@endforeach
            </select>
        </div>
        <div><label for="f-from" class="label">{{ __('From') }}</label><input type="date" name="from" id="f-from" value="{{ request('from') }}" class="input w-auto"></div>
        <div><label for="f-to" class="label">{{ __('To') }}</label><input type="date" name="to" id="f-to" value="{{ request('to') }}" class="input w-auto"></div>
    </x-filters>

    @if ($quotes->count())
        <x-table>
            <thead><tr><th><x-sort column="number" :label="__('Number')" /></th><th>{{ __('Customer') }}</th><th><x-sort column="issue_date" :label="__('Date')" /></th><th><x-sort column="expiry_date" :label="__('Valid until')" /></th><th class="text-end"><x-sort column="total" :label="__('Total')" /></th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
                @foreach ($quotes as $quote)
                    <tr>
                        <td data-label="{{ __('Number') }}"><a href="{{ route('quotes.show', $quote) }}" class="num font-medium hover:text-brand">{{ $quote->number }}</a></td>
                        <td data-label="{{ __('Customer') }}">{{ $quote->customer?->name }}</td>
                        <td data-label="{{ __('Date') }}">{{ tdate($quote->issue_date) }}</td>
                        <td data-label="{{ __('Valid until') }}">{{ tdate($quote->expiry_date) }}</td>
                        <td data-label="{{ __('Total') }}" class="num text-end">{{ money($quote->total, $quote->currency) }}</td>
                        <td data-label="{{ __('Status') }}"><x-badge :status="$quote->displayStatus()" /></td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $quotes->links() }}
    @else
        <div class="card">
            @if (request()->except('page'))
                <x-empty :title="__('No quotes match these filters')"><a href="{{ route('quotes.index') }}" class="btn btn-secondary">{{ __('Clear filters') }}</a></x-empty>
            @else
                <x-empty :title="__('No quotes yet')" :text="__('Send a quote before the work starts. The customer accepts or declines online, and an accepted quote becomes an invoice in one click.')">
                    @can('quotes.create')<a href="{{ route('quotes.create') }}" class="btn btn-primary">{{ __('Create your first quote') }}</a>@endcan
                </x-empty>
            @endif
        </div>
    @endif
@endsection
