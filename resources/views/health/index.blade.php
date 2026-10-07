@extends('layouts.app')
@section('title', __('Business Health'))

@php
    $currency = tenant()->currency;
    $groups = ['attention' => __('Attention required'), 'risk' => __('Risk'), 'opportunity' => __('Opportunity'), 'trend' => __('Trend')];
@endphp

@section('content')
    <x-page-header :title="__('Business Health')" :subtitle="__('What is happening, why, and what to do about it.')">
        <x-action :action="route('health.refresh')"><x-icon name="refresh" size="14" />{{ __('Update signals') }}</x-action>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Health score: every category shows the formula behind its number --}}
        <x-card :title="__('Health score')" class="lg:row-span-2">
            <div class="flex items-baseline gap-3">
                <p class="num text-5xl font-semibold tracking-tight">{{ $score['overall'] ?? '—' }}</p>
                @if ($score['overall'] !== null)<p class="text-sm text-muted">/ 100</p>@endif
            </div>
            <p class="mt-1 font-medium {{ ($score['overall'] ?? 0) >= 75 ? 'text-ok' : (($score['overall'] ?? 0) >= 50 ? 'text-warn' : 'text-danger') }}">{{ $score['label'] }}</p>

            <dl class="mt-6 space-y-4">
                @foreach ($score['categories'] as $category)
                    <div>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <dt class="font-medium">{{ $category['label'] }}</dt>
                            <dd class="num">{{ $category['score'] ?? __('No data') }}</dd>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-line" role="presentation">
                            <div class="h-full rounded-full {{ ($category['score'] ?? 0) >= 75 ? 'bg-ok' : (($category['score'] ?? 0) >= 50 ? 'bg-warn' : 'bg-danger') }}" style="width: {{ $category['score'] ?? 0 }}%"></div>
                        </div>
                        <p class="caption mt-1">{{ $category['formula'] }}</p>
                    </div>
                @endforeach
            </dl>
            <p class="caption mt-6 border-t border-line pt-4">{{ __('The score is the average of the categories above. Each one is a plain calculation from your own records.') }}</p>
        </x-card>

        {{-- Business brief --}}
        <x-card :title="__('Your business brief, this month')" class="lg:col-span-2">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-3">
                @foreach (['revenue' => __('Revenue'), 'outstanding' => __('Outstanding'), 'overdue' => __('Overdue'), 'expenses' => __('Expenses'), 'profit' => __('Estimated profit')] as $key => $label)
                    <div>
                        <dt class="text-sm text-muted">{{ $label }}</dt>
                        <dd class="num text-xl font-semibold {{ $key === 'overdue' && $brief[$key] > 0 ? 'text-danger' : '' }}">{{ money($brief[$key]) }}</dd>
                    </div>
                @endforeach
                <div>
                    <dt class="text-sm text-muted">{{ __('Collection risk') }}</dt>
                    <dd class="text-xl font-semibold">{{ $score['collection_risk'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">{{ __('Cash flow') }}</dt>
                    <dd class="text-xl font-semibold">{{ $score['cash_flow_state'] }}</dd>
                </div>
            </dl>
        </x-card>

        {{-- What needs attention --}}
        <x-card :title="__('What needs your attention?')" class="lg:col-span-2" :padded="false">
            @if (count($actions))
                <ul class="divide-y divide-line">
                    @foreach ($actions as $action)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 text-sm sm:px-6">
                            <span>{{ $action['text'] }}</span>
                            <a href="{{ $action['url'] }}" class="btn btn-secondary btn-sm">{{ __('Fix now') }}</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="card-pad text-sm text-muted">{{ __('Nothing is waiting for you right now.') }}</p>
            @endif
        </x-card>
    </div>

    {{-- Signals --}}
    <h2 class="h2 mt-8 mb-3">{{ __('Business signals') }}</h2>
    @if ($signals->flatten()->count())
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($groups as $type => $label)
                @continue(! $signals->has($type))
                <x-card :title="$label" :padded="false">
                    <ul class="divide-y divide-line px-4 sm:px-6">
                        @foreach ($signals[$type] as $signal)
                            @include('partials.signal', ['signal' => $signal, 'dismissible' => true])
                        @endforeach
                    </ul>
                </x-card>
            @endforeach
        </div>
    @else
        <div class="card">
            <x-empty :title="__('No signals right now')" :text="__('Signals are detected from your invoices, payments, expenses, customers and stock. They are refreshed every night, or when you choose Update signals.')" />
        </div>
    @endif
    @if ($lastRun)<p class="caption mt-3">{{ __('Last updated :time', ['time' => \Illuminate\Support\Carbon::parse($lastRun)->diffForHumans()]) }}</p>@endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Revenue, expenses and profit, last 6 months')">
            <div class="relative h-72" data-chart-box>
                <div class="skeleton absolute inset-0"></div>
                <canvas data-chart="{{ json_encode(['type' => 'line', 'money' => true, 'currency' => $currency, 'labels' => $trend['labels'], 'datasets' => [['label' => __('Revenue'), 'data' => $trend['revenue']], ['label' => __('Expenses'), 'data' => $trend['expenses']], ['label' => __('Profit'), 'data' => $trend['profit']]]]) }}" role="img" aria-label="{{ __('Six month trend of revenue, expenses and profit') }}"></canvas>
            </div>
        </x-card>

        {{-- Cash-flow forecast --}}
        <x-card :title="__('Expected cash position')" id="forecast" :padded="false">
            <table class="table">
                <thead>
                    <tr><th></th>@foreach ($forecast as $days => $row)<th class="text-end">{{ __(':days days', ['days' => $days]) }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @foreach (['receivables' => __('Outstanding invoices'), 'recurring_revenue' => __('Expected recurring revenue'), 'recurring_expenses' => __('Recurring expenses'), 'upcoming_expenses' => __('Known upcoming expenses')] as $key => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            @foreach ($forecast as $row)
                                <td class="num text-end">{{ in_array($key, ['recurring_expenses', 'upcoming_expenses']) && $row[$key] > 0 ? '−' : '' }}{{ money($row[$key]) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="font-semibold">
                        <td>{{ __('Projected net') }}</td>
                        @foreach ($forecast as $row)
                            <td class="num text-end {{ $row['net'] < 0 ? 'text-danger' : '' }}">{{ money($row['net']) }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
            <p class="caption border-t border-line px-4 py-3 sm:px-6">{{ __('This is a projection from the invoices, recurring invoices and expenses already recorded. It is an estimate, not a guarantee and not financial advice.') }}</p>
        </x-card>
    </div>
@endsection
