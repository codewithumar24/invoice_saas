@extends('layouts.app')
@section('title', $report['title'])

@php
    $from = $report['from'];
    $to = $report['to'];
    $exportQuery = ['type' => 'report:'.$report['key'], 'period' => 'custom', 'from' => $from->toDateString(), 'to' => $to->toDateString()] + array_filter($filters);
    $cell = function ($value, string $type) {
        return match ($type) {
            'money' => money($value),
            'number' => rtrim(rtrim(number_format((float) $value, 2), '0'), '.'),
            'date' => $value ? tdate($value) : '—',
            default => ($value === null || $value === '') ? '—' : $value,
        };
    };
@endphp

@section('content')
    <x-page-header :title="$report['title']" :subtitle="tdate($from).' – '.tdate($to)" :back="route('reports.index')">
        @can('reports.export')
            <x-dropdown :label="__('Export')">
                @foreach (['pdf' => 'PDF', 'csv' => 'CSV', 'xlsx' => 'Excel'] as $format => $label)
                    <a class="menu-item" role="menuitem" href="{{ route('exports.download-now', $exportQuery + ['format' => $format]) }}">{{ $label }}</a>
                @endforeach
            </x-dropdown>
        @endcan
        <button type="button" class="btn btn-secondary" data-print><x-icon name="print" size="16" />{{ __('Print') }}</button>
    </x-page-header>

    <div class="no-print mb-6 flex flex-wrap items-end gap-3">
        @include('partials.period')
        <form method="GET" class="flex flex-wrap items-end gap-3" data-no-loading>
            <input type="hidden" name="period" value="{{ $period }}">
            @if ($period === 'custom')<input type="hidden" name="from" value="{{ $from->toDateString() }}"><input type="hidden" name="to" value="{{ $to->toDateString() }}">@endif
            @if (in_array($report['key'], ['revenue', 'payments', 'sales_by_customer', 'top_customers', 'sales_by_product', 'sales_by_service', 'sales_by_employee', 'tax_summary']))
                <div>
                    <label for="r-customer" class="sr-only">{{ __('Customer') }}</label>
                    <select name="customer_id" id="r-customer" class="input w-auto max-w-48" data-autosubmit>
                        <option value="">{{ __('All customers') }}</option>
                        @foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $customer->id)>{{ $customer->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            @if ($report['key'] === 'expenses')
                <div>
                    <label for="r-category" class="sr-only">{{ __('Category') }}</label>
                    <select name="category_id" id="r-category" class="input w-auto" data-autosubmit>
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($expenseCategories as $category)<option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            @if (in_array($report['key'], ['sales_by_product', 'sales_by_service']) && $productCategories->count())
                <div>
                    <label for="r-pcategory" class="sr-only">{{ __('Category') }}</label>
                    <select name="category_id" id="r-pcategory" class="input w-auto" data-autosubmit>
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($productCategories as $category)<option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            @if ($report['key'] === 'product_movement' && $locations->count() > 1)
                <div>
                    <label for="r-location" class="sr-only">{{ __('Location') }}</label>
                    <select name="location_id" id="r-location" class="input w-auto" data-autosubmit>
                        <option value="">{{ __('All locations') }}</option>
                        @foreach ($locations as $location)<option value="{{ $location->id }}" @selected((string) ($filters['location_id'] ?? '') === (string) $location->id)>{{ $location->name }}</option>@endforeach
                    </select>
                </div>
            @endif
        </form>
    </div>

    @if (! empty($report['note']))<p class="mb-4 text-sm text-muted">{{ $report['note'] }}</p>@endif

    @if (count($report['rows']))
        @if ($report['chart'])
            <x-card class="no-print mb-6">
                <div class="relative h-72" data-chart-box>
                    <div class="skeleton absolute inset-0"></div>
                    <canvas data-chart="{{ json_encode($report['chart'] + ['money' => collect($report['columns'])->contains(fn ($c) => $c['type'] === 'money') && $report['key'] !== 'invoices_by_status' && $report['key'] !== 'customer_growth', 'currency' => tenant()->currency]) }}" role="img" aria-label="{{ $report['title'] }}"></canvas>
                </div>
            </x-card>
        @endif

        <x-table>
            <thead>
                <tr>@foreach ($report['columns'] as $column)<th class="{{ in_array($column['type'], ['money', 'number']) ? 'text-end' : '' }}">{{ $column['label'] }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        @foreach ($report['columns'] as $key => $column)
                            <td data-label="{{ $column['label'] }}" class="{{ in_array($column['type'], ['money', 'number']) ? 'num text-end' : '' }}">{{ $cell($row[$key] ?? null, $column['type']) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if (count($report['totals']))
                <tfoot>
                    <tr class="font-semibold">
                        @foreach ($report['columns'] as $key => $column)
                            <td data-label="{{ $column['label'] }}" class="{{ in_array($column['type'], ['money', 'number']) ? 'num text-end' : '' }}">
                                @if ($loop->first){{ __('Total') }}@elseif (array_key_exists($key, $report['totals'])){{ $cell($report['totals'][$key], $column['type']) }}@endif
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </x-table>
    @else
        <div class="card">
            <x-empty :title="__('Nothing to report for this period')" :text="__('Choose a longer period, or check the filters above.')" />
        </div>
    @endif
@endsection
