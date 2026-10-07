@extends('layouts.app')
@section('title', __('Expenses'))

@section('content')
    <x-page-header :title="__('Expenses')" :subtitle="__('Total for this view: :amount', ['amount' => money($total)])">
        @can('expenses.create')
            <a href="{{ route('imports.index') }}" class="btn btn-secondary">{{ __('Import CSV') }}</a>
            <a href="{{ route('expenses.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ __('Add expense') }}</a>
        @endcan
    </x-page-header>

    <x-filters :placeholder="__('Vendor, reference or notes')">
        <div>
            <label for="f-category" class="sr-only">{{ __('Category') }}</label>
            <select name="category" id="f-category" class="input w-auto">
                <option value="">{{ __('All categories') }}</option>
                <option value="none" @selected(request('category') === 'none')>{{ __('Without category') }}</option>
                @foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>@endforeach
            </select>
        </div>
        <div><label for="f-from" class="label">{{ __('From') }}</label><input type="date" name="from" id="f-from" value="{{ request('from') }}" class="input w-auto"></div>
        <div><label for="f-to" class="label">{{ __('To') }}</label><input type="date" name="to" id="f-to" value="{{ request('to') }}" class="input w-auto"></div>
        <div class="w-28"><label for="f-min" class="label">{{ __('Min amount') }}</label><input type="number" step="any" name="min" id="f-min" value="{{ request('min') }}" class="input"></div>
        <div class="w-28"><label for="f-max" class="label">{{ __('Max amount') }}</label><input type="number" step="any" name="max" id="f-max" value="{{ request('max') }}" class="input"></div>
    </x-filters>

    @if ($expenses->count())
        <form method="POST" action="{{ route('expenses.bulk') }}" data-bulk>
            @csrf
            @component('partials.bulk-bar', ['actions' => ['categorize' => __('Set category'), 'export' => __('Export CSV')]])
                <select name="category_id" class="input w-auto py-1.5" aria-label="{{ __('Category') }}" data-show-when="action=categorize">
                    @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                </select>
            @endcomponent

            <x-table>
                <thead><tr>
                    <th class="w-8"><input type="checkbox" class="check" data-bulk-all aria-label="{{ __('Select all') }}"></th>
                    <th><x-sort column="expense_date" :label="__('Date')" /></th><th><x-sort column="vendor" :label="__('Vendor')" /></th><th>{{ __('Category') }}</th><th>{{ __('Receipt') }}</th><th class="text-end"><x-sort column="amount" :label="__('Amount')" /></th>
                </tr></thead>
                <tbody>
                    @foreach ($expenses as $expense)
                        <tr>
                            <td><input type="checkbox" class="check" name="ids[]" value="{{ $expense->id }}" data-bulk-item aria-label="{{ __('Select expense') }}"></td>
                            <td data-label="{{ __('Date') }}">{{ tdate($expense->expense_date) }}</td>
                            <td data-label="{{ __('Vendor') }}">
                                @can('expenses.update')<a href="{{ route('expenses.edit', $expense) }}" class="font-medium hover:text-brand">{{ $expense->vendor ?: __('No vendor') }}</a>@else<span class="font-medium">{{ $expense->vendor ?: __('No vendor') }}</span>@endcan
                                @if ($expense->is_recurring)<span class="badge badge-neutral ms-1">{{ __('Recurring') }}</span>@endif
                            </td>
                            <td data-label="{{ __('Category') }}">@if ($expense->category){{ $expense->category->name }}@else<span class="badge badge-warn">{{ __('Needs a category') }}</span>@endif</td>
                            <td data-label="{{ __('Receipt') }}">
                                @forelse ($expense->receipts as $receipt)
                                    <a class="link" href="{{ route('expenses.receipts.show', $receipt) }}">{{ __('View') }}</a>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td data-label="{{ __('Amount') }}" class="num text-end">{{ money($expense->amount, $expense->currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </form>
        {{ $expenses->links() }}
    @else
        <div class="card">
            @if (request()->except('page'))
                <x-empty :title="__('No expenses match these filters')"><a href="{{ route('expenses.index') }}" class="btn btn-secondary">{{ __('Clear filters') }}</a></x-empty>
            @else
                <x-empty :title="__('No expenses yet')" :text="__('Record what the business spends, attach the receipt, and profit and cash flow on your dashboard become real numbers.')">
                    @can('expenses.create')<a href="{{ route('expenses.create') }}" class="btn btn-primary">{{ __('Add your first expense') }}</a>@endcan
                </x-empty>
            @endif
        </div>
    @endif
@endsection
