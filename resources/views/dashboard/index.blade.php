@extends('layouts.app')
@section('title', __('Dashboard'))

@php
    $currency = tenant()->currency;
    $user = auth()->user();
    $stats = [
        'revenue' => ['label' => __('Revenue'), 'value' => money($summary['revenue']), 'change' => $summary['revenue_change'], 'href' => feature('reports') && $user->hasPermission('reports.view') ? route('reports.show', 'revenue') : null],
        'expenses' => ['label' => __('Expenses'), 'value' => money($summary['expenses']), 'change' => $summary['expenses_change'], 'goodWhenUp' => false, 'href' => $user->hasPermission('expenses.view') ? route('expenses.index') : null],
        'profit' => ['label' => __('Profit'), 'value' => money($summary['profit']), 'change' => $summary['profit_change'], 'tone' => $summary['profit'] < 0 ? 'danger' : null, 'note' => __('Revenue minus expenses')],
        'outstanding' => ['label' => __('Outstanding'), 'value' => money($summary['outstanding']), 'note' => __('Unpaid invoice amount'), 'href' => route('invoices.index', ['payment' => 'unpaid'])],
        'overdue' => ['label' => __('Overdue'), 'value' => money($summary['overdue']), 'tone' => $summary['overdue'] > 0 ? 'danger' : null, 'note' => trans_choice(':count invoice past due|:count invoices past due', $summary['overdue_count'], ['count' => $summary['overdue_count']]), 'href' => route('invoices.index', ['status' => 'overdue'])],
        'customers' => ['label' => __('Customers'), 'value' => number_format($summary['customers']), 'note' => __('Active customers'), 'href' => route('customers.index')],
        'cash_flow' => ['label' => __('Cash flow'), 'value' => money($summary['cash_net']), 'tone' => $summary['cash_net'] < 0 ? 'danger' : null, 'note' => __(':in in, :out out', ['in' => money($summary['cash_in']), 'out' => money($summary['cash_out'])])],
    ];
    if (isset($extra['inventory'])) {
        $stats['inventory'] = ['label' => __('Inventory'), 'value' => money($extra['inventory']['value']), 'note' => trans_choice(':count product low on stock|:count products low on stock', $extra['inventory']['low'], ['count' => $extra['inventory']['low']]), 'tone' => $extra['inventory']['low'] ? 'danger' : null, 'href' => route('inventory.index')];
    }
    if (isset($extra['projects'])) {
        $stats['projects'] = ['label' => __('Projects'), 'value' => number_format($extra['projects']['active']), 'note' => trans_choice(':count deadline this week|:count deadlines this week', $extra['projects']['due_soon'], ['count' => $extra['projects']['due_soon']]), 'href' => route('projects.index')];
    }
    if (isset($extra['time'])) {
        $stats['time'] = ['label' => __('Time tracked this week'), 'value' => \App\Models\TimeEntry::formatDuration($extra['time']['week']), 'note' => __(':time not yet invoiced', ['time' => \App\Models\TimeEntry::formatDuration($extra['time']['unbilled'])]), 'href' => route('time.index')];
    }
    $cards = collect($widgets)->filter(fn ($widget) => isset($stats[$widget]));
@endphp

@section('content')
    <x-page-header :title="__('Dashboard')" :subtitle="$from->equalTo($to) ? tdate($from) : tdate($from).' – '.tdate($to)">
        @include('partials.period')
        <button type="button" class="btn btn-secondary" data-open="customize-dashboard">{{ __('Customize') }}</button>
    </x-page-header>

    {{-- What needs your attention? The permanent action centre: every line goes straight to where it can be fixed. --}}
    <section class="card mb-6" aria-labelledby="attention-title">
        <div class="card-head"><h2 class="h3" id="attention-title">{{ __('What needs your attention?') }}</h2></div>
        @if (count($actions))
            <ul class="divide-y divide-line">
                @foreach ($actions as $action)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 sm:px-6">
                        <span class="flex items-center gap-3 text-sm">
                            <span class="h-2 w-2 rounded-full {{ ['danger' => 'bg-danger', 'attention' => 'bg-warn', 'info' => 'bg-info'][$action['tone']] ?? 'bg-warn' }}" aria-hidden="true"></span>
                            {{ $action['text'] }}
                        </span>
                        <a href="{{ $action['url'] }}" class="btn btn-secondary btn-sm">{{ __('Fix now') }}</a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="px-4 py-4 text-sm text-muted sm:px-6">{{ __('Nothing is waiting for you. Invoices, stock and quotes are all in order.') }}</p>
        @endif
    </section>

    {{-- Top cards, in the order this user chose --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $widget)
            <x-stat :label="$stats[$widget]['label']" :value="$stats[$widget]['value']" :change="$stats[$widget]['change'] ?? null" :note="$stats[$widget]['note'] ?? null"
                :href="$stats[$widget]['href'] ?? null" :tone="$stats[$widget]['tone'] ?? null" :good-when-up="$stats[$widget]['goodWhenUp'] ?? true" />
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Revenue and expenses')" class="lg:col-span-2">
            <div class="relative h-72" data-chart-box>
                <div class="skeleton absolute inset-0"></div>
                <canvas data-chart="{{ json_encode(['type' => 'bar', 'money' => true, 'currency' => $currency, 'labels' => $series['labels'], 'datasets' => [['label' => __('Revenue'), 'data' => $series['revenue']], ['label' => __('Expenses'), 'data' => $series['expenses']]]]) }}" role="img" aria-label="{{ __('Revenue and expenses for the selected period') }}"></canvas>
            </div>
        </x-card>

        @if (in_array('signals', $widgets) && $brief)
            <x-card :title="__('Business signals')" :padded="false">
                <x-slot:actions><a href="{{ route('health.index') }}" class="link text-sm">{{ __('Business Health') }}</a></x-slot:actions>
                @if ($signals->count())
                    <ul class="divide-y divide-line px-4 sm:px-6">
                        @foreach ($signals as $signal)
                            @include('partials.signal', ['signal' => $signal])
                        @endforeach
                    </ul>
                @else
                    <p class="card-pad text-sm text-muted">{{ __('No signals yet. They appear here as invoices, payments and expenses build up.') }}</p>
                @endif
            </x-card>
        @else
            <x-card :title="__('Invoice status')" :padded="false">
                @include('dashboard._status')
            </x-card>
        @endif

        @if (feature('expenses'))
            <x-card :title="__('Expenses by category')">
                @if (count($expenseCategories))
                    <div class="relative h-64" data-chart-box>
                        <div class="skeleton absolute inset-0"></div>
                        <canvas data-chart="{{ json_encode(['type' => 'doughnut', 'money' => true, 'currency' => $currency, 'labels' => array_keys($expenseCategories), 'datasets' => [['label' => __('Expenses'), 'data' => array_values($expenseCategories)]]]) }}" role="img" aria-label="{{ __('Expenses by category') }}"></canvas>
                    </div>
                @else
                    <x-empty :title="__('No expenses in this period')" :text="__('Record expenses to see where your money goes.')">
                        @can('expenses.create')<a href="{{ route('expenses.create') }}" class="btn btn-secondary btn-sm">{{ __('Add expense') }}</a>@endcan
                    </x-empty>
                @endif
            </x-card>
        @endif

        @if (in_array('signals', $widgets) && $brief)
            <x-card :title="__('Invoice status')" :padded="false">
                @include('dashboard._status')
            </x-card>
        @endif

        <x-card :title="__('Recent activity')" :padded="false" class="{{ feature('expenses') ? '' : 'lg:col-span-2' }}">
            @if ($activity->count())
                <ul class="divide-y divide-line">
                    @foreach ($activity as $item)
                        <li class="px-4 py-2.5 text-sm sm:px-6">
                            <p>{{ $item->description }}</p>
                            <p class="caption">{{ $item->user?->name ?? __('System') }}, {{ $item->created_at->diffForHumans() }}</p>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="card-pad text-sm text-muted">{{ __('Activity from your team and customers shows up here.') }}</p>
            @endif
        </x-card>

        <div class="lg:col-span-3">
            <h2 class="h2 mb-3">{{ __('Recent transactions') }}</h2>
            @if ($transactions->count())
                <x-table>
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td data-label="{{ __('Date') }}">{{ tdate($transaction->created_at) }}</td>
                                <td data-label="{{ __('Customer') }}">{{ $transaction->customer?->name ?? '—' }}</td>
                                <td data-label="{{ __('Type') }}">{{ $transaction->type === 'refund' ? __('Refund') : __('Payment') }}@if ($transaction->invoice) <span class="text-muted">· {{ $transaction->invoice->number }}</span>@endif</td>
                                <td data-label="{{ __('Amount') }}" class="num text-end {{ $transaction->type === 'refund' ? 'text-danger' : '' }}">{{ $transaction->type === 'refund' ? '−' : '' }}{{ money($transaction->amount, $transaction->currency) }}</td>
                                <td data-label="{{ __('Status') }}"><x-badge :status="$transaction->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @else
                <div class="card">
                    <x-empty :title="__('No payments yet')" :text="__('Send an invoice and record the payment when it arrives. Payments and refunds are listed here.')">
                        @can('invoices.create')<a href="{{ route('invoices.create') }}" class="btn btn-primary">{{ __('Create your first invoice') }}</a>@endcan
                    </x-empty>
                </div>
            @endif
        </div>
    </div>

    {{-- Personalise: choose widgets and their order; saved per user --}}
    <x-modal id="customize-dashboard" :title="__('Customize dashboard')">
        <form method="POST" action="{{ route('dashboard.widgets') }}">
            @csrf
            <p class="mb-4 text-sm text-muted">{{ __('Tick the cards you want and use the arrows to change their order.') }}</p>
            <ul class="divide-y divide-line rounded-md border border-line" data-sortable>
                @foreach (collect($widgets)->merge(array_keys($allWidgets))->unique() as $key)
                    @continue(! isset($allWidgets[$key]))
                    <li class="flex items-center gap-3 px-3 py-2" data-sortable-item>
                        <input type="checkbox" class="check" name="widgets[]" value="{{ $key }}" id="widget-{{ $key }}" @checked(in_array($key, $widgets))>
                        <label for="widget-{{ $key }}" class="flex-1 text-sm">{{ __($allWidgets[$key]) }}</label>
                        <button type="button" class="btn btn-ghost btn-icon btn-sm" data-move="up" aria-label="{{ __('Move up') }}"><x-icon name="arrow-up" size="14" /></button>
                        <button type="button" class="btn btn-ghost btn-icon btn-sm" data-move="down" aria-label="{{ __('Move down') }}"><x-icon name="arrow-down" size="14" /></button>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save dashboard') }}</button>
            </div>
        </form>
    </x-modal>
@endsection
